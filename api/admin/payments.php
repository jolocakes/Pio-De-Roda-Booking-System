<?php
/**
 * API: Admin payment tracking (T-19).
 *
 *   GET  ?q=&status=&page=&per_page=      reservations with payment totals
 *   GET  ?picker=1                        compact list for the record modal
 *   GET  ?id=PDR-2026-0001                one reservation + payment history
 *   POST { action: "record", ... }        record a manual (cash/GCash/bank) payment
 *   POST { action: "delete", paymentId }  delete a manual payment and recalculate
 *
 * Xendit rows are written by api/payments/webhook.php. This endpoint never
 * touches gateway rows except by reading them when it recalculates totals.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/audit.php';
require_once __DIR__ . '/../../config/rbac.php';
require_once __DIR__ . '/../../config/payments.php';

pdrSessionStart();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}

/**
 * SQL expression for what a reservation has collected from Verified rows.
 * Kept as a subquery instead of the cached column so the admin list always
 * matches the payment ledger, even if an older callback failed midway.
 */
function pdrAdminPaidSubquery(): string
{
    return "COALESCE((SELECT SUM(" . pdrPaymentAmountSql('p') . ")"
        . " FROM payments p"
        . " WHERE p.reservation_id = r.reservation_id AND p.status = 'Verified'), 0)";
}

function pdrAdminPaymentRow(array $row): array
{
    $total = round((float) ($row['estimated_total'] ?? 0), 2);
    $paid  = round((float) ($row['amount_paid_computed'] ?? 0), 2);

    return [
        'reservationId'     => $row['reservation_id'],
        'customerName'      => $row['customer_name'],
        'mainService'       => $row['main_service'] ?? null,
        'eventDate'         => $row['event_date'] ?? null,
        'reservationStatus' => $row['status'] ?? null,
        'estimatedTotal'    => $total,
        'amountPaid'        => $paid,
        'balance'           => max(0.0, round($total - $paid, 2)),
        'paymentStatus'     => pdrPaymentStatus($total, $paid),
        'paymentMethod'     => $row['latest_method'] ?? $row['payment_method'] ?? null,
        'lastPaymentAt'     => $row['last_payment_at'] ?? null,
        'paymentCount'      => (int) ($row['payment_count'] ?? 0),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    pdrRequireApiCapability('view');

    try {
        $paidSub = pdrAdminPaidSubquery();

        /* ---------- one reservation + its payment history ---------- */
        $id = trim((string) ($_GET['id'] ?? ''));
        if ($id !== '') {
            // Settle any invoice Xendit already marked paid but whose webhook
            // never reached us, so the history modal is never stale.
            pdrSyncXenditPayments($pdo, $id);

            $stmt = $pdo->prepare(
                "SELECT r.reservation_id, r.customer_name, r.main_service, r.event_date,
                        r.event_time, r.guests, r.status, r.payment_method,
                        COALESCE(r.estimated_total, 0) AS estimated_total,
                        {$paidSub} AS amount_paid_computed,
                        (SELECT p.payment_channel
                           FROM payments p
                          WHERE p.reservation_id = r.reservation_id AND p.status = 'Verified'
                          ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC
                          LIMIT 1) AS latest_method,
                        (SELECT MAX(COALESCE(p.paid_at, p.created_at))
                           FROM payments p
                          WHERE p.reservation_id = r.reservation_id AND p.status = 'Verified') AS last_payment_at,
                        (SELECT COUNT(*)
                           FROM payments p
                          WHERE p.reservation_id = r.reservation_id AND p.status = 'Verified') AS payment_count
                   FROM reservations r
                  WHERE r.reservation_id = ? AND r.archived_at IS NULL
                  LIMIT 1"
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
                exit();
            }

            $paymentsStmt = $pdo->prepare(
                "SELECT id, provider, provider_reference, external_id, amount, paid_amount,
                        payment_type, payment_channel, status, paid_at, created_at
                   FROM payments
                  WHERE reservation_id = ?
                  ORDER BY COALESCE(paid_at, created_at) DESC, id DESC"
            );
            $paymentsStmt->execute([$id]);

            $payments = [];
            foreach ($paymentsStmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
                $payments[] = [
                    'id'                => (int) $p['id'],
                    'provider'          => $p['provider'],
                    'providerReference' => $p['provider_reference'],
                    'externalId'        => $p['external_id'],
                    'amount'            => round((float) $p['amount'], 2),
                    'paidAmount'        => $p['paid_amount'] === null ? null : round((float) $p['paid_amount'], 2),
                    'paymentType'       => $p['payment_type'],
                    'paymentChannel'    => $p['payment_channel'],
                    'status'            => $p['status'],
                    'paidAt'            => $p['paid_at'],
                    'createdAt'         => $p['created_at'],
                    // Only manual entries are editable from this screen.
                    'canDelete'         => $p['provider'] === 'manual',
                ];
            }

            echo json_encode([
                'ok'          => true,
                'reservation' => pdrAdminPaymentRow($row),
                'payments'    => $payments,
            ]);
            exit();
        }

        /* ---------- compact picker list ---------- */
        if ((string) ($_GET['picker'] ?? '') === '1') {
            $stmt = $pdo->query(
                "SELECT r.reservation_id, r.customer_name, r.main_service, r.event_date,
                        r.status, COALESCE(r.estimated_total, 0) AS estimated_total,
                        {$paidSub} AS amount_paid_computed,
                        (SELECT p.payment_channel
                           FROM payments p
                          WHERE p.reservation_id = r.reservation_id AND p.status = 'Verified'
                          ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC
                          LIMIT 1) AS latest_method
                   FROM reservations r
                  WHERE r.archived_at IS NULL
                    AND r.status IN ('Pending', 'Confirmed', 'Completed')
                  ORDER BY (GREATEST(COALESCE(r.estimated_total, 0) - ({$paidSub}), 0) > 0) DESC, r.event_date DESC, r.reservation_id DESC
                  LIMIT 500"
            );

            $rows = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $rows[] = pdrAdminPaymentRow($row);
            }

            echo json_encode(['ok' => true, 'reservations' => $rows]);
            exit();
        }

        /* ---------- paginated list ---------- */
        $q      = trim((string) ($_GET['q'] ?? ''));
        $status = strtolower(trim((string) ($_GET['status'] ?? '')));
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));

        $where  = ['r.archived_at IS NULL'];
        $params = [];

        if ($q !== '') {
            $where[]  = '(r.reservation_id LIKE ? OR r.customer_name LIKE ? OR r.main_service LIKE ?)';
            $like     = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status === 'paid') {
            $where[] = "r.estimated_total > 0 AND ({$paidSub}) + 0.01 >= r.estimated_total";
        } elseif ($status === 'partial') {
            $where[] = "({$paidSub}) > 0.009 AND (r.estimated_total <= 0 OR ({$paidSub}) + 0.01 < r.estimated_total)";
        } elseif ($status === 'unpaid') {
            $where[] = "({$paidSub}) <= 0.009";
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations r WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $listStmt = $pdo->prepare(
            "SELECT r.reservation_id, r.customer_name, r.main_service, r.event_date,
                    r.status, r.payment_method,
                    COALESCE(r.estimated_total, 0) AS estimated_total,
                    {$paidSub} AS amount_paid_computed,
                    GREATEST(COALESCE(r.estimated_total, 0) - ({$paidSub}), 0) AS balance_calc,
                    (SELECT p.payment_channel
                       FROM payments p
                      WHERE p.reservation_id = r.reservation_id AND p.status = 'Verified'
                      ORDER BY COALESCE(p.paid_at, p.created_at) DESC, p.id DESC
                      LIMIT 1) AS latest_method,
                    (SELECT MAX(COALESCE(p.paid_at, p.created_at))
                       FROM payments p
                      WHERE p.reservation_id = r.reservation_id AND p.status = 'Verified') AS last_payment_at,
                    (SELECT COUNT(*)
                       FROM payments p
                      WHERE p.reservation_id = r.reservation_id AND p.status = 'Verified') AS payment_count
               FROM reservations r
              WHERE {$whereSql}
              ORDER BY (balance_calc > 0) DESC, r.event_date DESC, r.reservation_id DESC
              LIMIT {$perPage} OFFSET {$offset}"
        );
        $listStmt->execute($params);

        $rows = [];
        foreach ($listStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = pdrAdminPaymentRow($row);
        }

        $summaryStmt = $pdo->query(
            "SELECT COALESCE(SUM(x.paid), 0) AS collected,
                    COALESCE(SUM(GREATEST(x.est - x.paid, 0)), 0) AS outstanding,
                    COALESCE(SUM(CASE WHEN x.est > 0 AND x.paid + 0.01 >= x.est THEN 1 ELSE 0 END), 0) AS paid_count,
                    COALESCE(SUM(CASE WHEN x.paid > 0.009 AND (x.est <= 0 OR x.paid + 0.01 < x.est) THEN 1 ELSE 0 END), 0) AS partial_count
               FROM (
                    SELECT COALESCE(r.estimated_total, 0) AS est,
                           COALESCE((SELECT SUM(" . pdrPaymentAmountSql('p') . ")
                                       FROM payments p
                                      WHERE p.reservation_id = r.reservation_id
                                        AND p.status = 'Verified'), 0) AS paid
                      FROM reservations r
                     WHERE r.archived_at IS NULL
               ) x"
        );
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'ok'           => true,
            'reservations' => $rows,
            'summary'      => [
                'collected'    => round((float) ($summary['collected'] ?? 0), 2),
                'outstanding'  => round((float) ($summary['outstanding'] ?? 0), 2),
                'paidCount'    => (int) ($summary['paid_count'] ?? 0),
                'partialCount' => (int) ($summary['partial_count'] ?? 0),
            ],
            'meta' => [
                'page'       => $page,
                'perPage'    => $perPage,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
        ]);
        exit();

    } catch (Throwable $e) {
        error_log('Admin payments GET error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
        exit();
    }
}

if ($method === 'POST') {
    pdrRequireApiCapability('manage_payments');
    pdrRequireCsrfJson();

    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID']);
        exit();
    }

    $action = strtolower(trim((string) ($data['action'] ?? '')));

    /* ---------- reconcile Pending Xendit invoices with the gateway ---------- */
    if ($action === 'sync') {
        $reservationId = trim((string) ($data['reservationId'] ?? ''));

        if ($reservationId !== '') {
            $ids = [$reservationId];
        } else {
            $q = $pdo->query(
                "SELECT DISTINCT reservation_id
                   FROM payments
                  WHERE provider = 'xendit' AND status = 'Pending'
                  ORDER BY reservation_id
                  LIMIT 10"
            );
            $ids = $q->fetchAll(PDO::FETCH_COLUMN);
        }

        $checked = 0;
        $settled = 0;
        foreach ($ids as $rid) {
            $r = pdrSyncXenditPayments($pdo, (string) $rid);
            $checked += (int) $r['checked'];
            $settled += (int) $r['settled'];
        }

        if ($settled > 0) {
            pdrAudit($pdo, 'payment.sync', 'payments', null, [
                'reservations' => count($ids),
                'settled'      => $settled,
            ]);
        }

        echo json_encode([
            'ok'           => true,
            'reservations' => count($ids),
            'checked'      => $checked,
            'settled'      => $settled,
        ]);
        exit();
    }

    /* ---------- delete a manual payment ---------- */
    if ($action === 'delete') {
        $paymentId = (int) ($data['paymentId'] ?? 0);
        if ($paymentId <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            $sel = $pdo->prepare(
                "SELECT id, reservation_id, provider, amount, payment_channel
                   FROM payments
                  WHERE id = ?
                  FOR UPDATE"
            );
            $sel->execute([$paymentId]);
            $payment = $sel->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
                exit();
            }
            if ($payment['provider'] !== 'manual') {
                $pdo->rollBack();
                http_response_code(409);
                echo json_encode(['ok' => false, 'reason' => 'NOT_MANUAL']);
                exit();
            }

            $del = $pdo->prepare("DELETE FROM payments WHERE id = ?");
            $del->execute([$paymentId]);

            $totals = pdrRecomputeReservationPayment($pdo, $payment['reservation_id']);
            $pdo->commit();

            pdrAudit($pdo, 'payment.delete', 'payment', (string) $paymentId, [
                'reservationId' => $payment['reservation_id'],
                'amount'        => (float) $payment['amount'],
            ]);

            echo json_encode([
                'ok'          => true,
                'reservation' => $totals,
            ]);
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Admin payment delete error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
            exit();
        }
    }

    /* ---------- record a manual payment ---------- */
    if ($action !== 'record') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID']);
        exit();
    }

    $reservationId = trim((string) ($data['reservationId'] ?? ''));
    $amount        = round((float) ($data['amount'] ?? 0), 2);
    $paymentMethod = trim((string) ($data['method'] ?? ''));
    $paidDate      = trim((string) ($data['paidAt'] ?? ''));
    $note          = trim((string) ($data['note'] ?? ''));

    $allowedMethods = ['Cash', 'GCash', 'Bank Transfer', 'Other'];
    if ($reservationId === '' || $amount <= 0 || !in_array($paymentMethod, $allowedMethods, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID']);
        exit();
    }

    if (mb_strlen($note) > 255) {
        $note = mb_substr($note, 0, 255);
    }

    $today = date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidDate)) {
        $paidDate = $today;
    }
    $parsed = DateTime::createFromFormat('Y-m-d', $paidDate);
    if (!$parsed || $parsed->format('Y-m-d') !== $paidDate || $paidDate > $today) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID_DATE']);
        exit();
    }
    $paidAt = $paidDate === $today ? date('Y-m-d H:i:s') : $paidDate . ' 00:00:00';

    try {
        $pdo->beginTransaction();

        // Lock the reservation row so two staff members cannot overpay it.
        $lock = $pdo->prepare(
            "SELECT estimated_total, amount_paid, status
               FROM reservations
              WHERE reservation_id = ? AND archived_at IS NULL
              FOR UPDATE"
        );
        $lock->execute([$reservationId]);
        $reservation = $lock->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
            exit();
        }

        $total = round((float) $reservation['estimated_total'], 2);
        if (!in_array((string) $reservation['status'], ['Pending', 'Confirmed', 'Completed'], true)) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'reason' => 'NOT_PAYABLE']);
            exit();
        }
        if ($total <= 0) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'reason' => 'NO_ESTIMATE']);
            exit();
        }

        // Re-derive the balance from the ledger, not the cached column.
        $sum = $pdo->prepare(
            "SELECT COALESCE(SUM(" . pdrPaymentAmountSql('p') . "), 0)
               FROM payments p
              WHERE p.reservation_id = ? AND p.status = 'Verified'"
        );
        $sum->execute([$reservationId]);
        $alreadyPaid = round((float) $sum->fetchColumn(), 2);
        $balance = max(0.0, round($total - $alreadyPaid, 2));

        if ($balance <= 0.009) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok' => false, 'reason' => 'ALREADY_PAID']);
            exit();
        }
        if ($amount > $balance + 0.01) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode([
                'ok'      => false,
                'reason'  => 'EXCEEDS_BALANCE',
                'balance' => $balance,
            ]);
            exit();
        }

        $paymentType = ($amount + 0.01 >= $balance) ? 'full' : 'partial';
        $meta = json_encode([
            'note'        => $note,
            'recorded_by' => (string) ($_SESSION['admin_username'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $ins = $pdo->prepare(
            "INSERT INTO payments
                (reservation_id, provider, provider_reference, external_id, amount, paid_amount,
                 payment_type, payment_channel, status, paid_at, raw_response)
             VALUES (?, 'manual', NULL, NULL, ?, ?, ?, ?, 'Verified', ?, ?)"
        );
        $ins->execute([
            $reservationId,
            $amount,
            $amount,
            $paymentType,
            $paymentMethod,
            $paidAt,
            $meta,
        ]);
        $paymentId = (int) $pdo->lastInsertId();

        $totals = pdrRecomputeReservationPayment($pdo, $reservationId);

        $pdo->commit();

        pdrAudit($pdo, 'payment.record', 'reservation', $reservationId, [
            'paymentId' => $paymentId,
            'amount'    => $amount,
            'method'    => $paymentMethod,
            'paidAt'    => $paidAt,
            'status'    => $totals['paymentStatus'] ?? null,
        ]);

        echo json_encode([
            'ok'          => true,
            'paymentId'   => $paymentId,
            'reservation' => $totals,
        ]);
        exit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Admin payment record error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
        exit();
    }
}

http_response_code(405);
header('Allow: GET, POST');
echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);
