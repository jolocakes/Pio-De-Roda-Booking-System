<?php
/**
 * Xendit webhook receiver (invoice callbacks). PUBLIC endpoint, token-verified.
 * Configure in Xendit Dashboard > Settings > Webhooks (use your public tunnel URL for local test).
 * Source of truth for payment status — do not rely on redirect URLs alone.
 */
require_once __DIR__ . '/../../config/db.php';

$xenditConfig = __DIR__ . '/../../config/xendit.php';
if (!is_file($xenditConfig)) { http_response_code(500); exit; }
$xendit = require $xenditConfig;

$token = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? ($_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '');
$expected = (string)($xendit['callback_token'] ?? '');
if ($expected === '' || strpos($expected, 'REPLACE') !== false || $token === '' || !hash_equals($expected, $token)) {
    http_response_code(403);
    echo 'invalid token';
    exit;
}

$raw = file_get_contents('php://input');
$event = json_decode((string)$raw, true);
if (!is_array($event)) {
    http_response_code(400);
    echo 'bad payload';
    exit;
}

// Xendit invoice callback fields: id, external_id, status, amount, paid_amount, payment_method, payment_channel, ...
$invoiceId  = (string)($event['id'] ?? '');
$externalId = (string)($event['external_id'] ?? '');
$status     = strtoupper((string)($event['status'] ?? ''));
$channel    = $event['payment_channel'] ?? $event['payment_method'] ?? null;
$paidAmount = isset($event['paid_amount']) ? (float)$event['paid_amount'] : (isset($event['amount']) ? (float)$event['amount'] : null);

$paidStatuses = ['PAID', 'SETTLED'];
$failedStatuses = ['EXPIRED'];

try {
    // Locate payment row (prefer invoice id)
    $row = null;
    if ($invoiceId !== '') {
        $q = $pdo->prepare("SELECT * FROM payments WHERE provider = 'xendit' AND provider_reference = ? LIMIT 1");
        $q->execute([$invoiceId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row && $externalId !== '') {
        $q = $pdo->prepare("SELECT * FROM payments WHERE provider = 'xendit' AND external_id = ? ORDER BY id DESC LIMIT 1");
        $q->execute([$externalId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
    }
    if (!$row) {
        // Unknown invoice (e.g. created outside this app) — acknowledge to stop retries.
        error_log('Xendit webhook: no matching payment for invoice=' . $invoiceId . ' external=' . $externalId);
        http_response_code(200);
        echo 'ok';
        exit;
    }

    if (in_array($status, $paidStatuses, true)) {
        if (($row['status'] ?? '') !== 'Verified') {
            // T-15: verify the amount actually paid against the amount this app
            // asked Xendit to collect (the `payments.amount` we created the
            // invoice with). A PAID callback whose amount does not line up is
            // NOT allowed to mark itself Verified — it is flagged for a human.
            $expectedAmount = (float) ($row['amount'] ?? 0);
            $amountMismatch = ($paidAmount === null)
                || ($expectedAmount <= 0)
                || (abs($paidAmount - $expectedAmount) > 0.01);

            if ($amountMismatch) {
                error_log(sprintf(
                    'Xendit webhook amount mismatch: invoice=%s external=%s expected=%.2f paid=%s',
                    $invoiceId,
                    $externalId,
                    $expectedAmount,
                    $paidAmount === null ? 'null' : number_format($paidAmount, 2, '.', '')
                ));

                $upd = $pdo->prepare(
                    "UPDATE payments
                        SET status = 'For Review', payment_channel = ?, paid_at = NOW(), raw_response = ?
                      WHERE id = ? AND status <> 'Verified'"
                );
                $upd->execute([is_string($channel) ? $channel : json_encode($channel), $raw, $row['id']]);

                // Best effort: record the amount Xendit reported (column added
                // by database/migrations/003_payment_review.sql; ignored if absent).
                try {
                    $p = $pdo->prepare("UPDATE payments SET paid_amount = ? WHERE id = ?");
                    $p->execute([$paidAmount, $row['id']]);
                } catch (Throwable $ignoredPaidAmount) {
                    // Column not migrated yet — raw_response still has the payload.
                }

                http_response_code(200);
                echo 'amount-mismatch-flagged';
                exit;
            }

            $upd = $pdo->prepare("UPDATE payments SET status = 'Verified', payment_channel = ?, paid_at = NOW(), raw_response = ? WHERE id = ? AND status <> 'Verified'");
            $upd->execute([is_string($channel) ? $channel : json_encode($channel), $raw, $row['id']]);

            // Record the verified amount too (best effort; column may not exist
            // on a database that has not run the migrations yet).
            try {
                $p = $pdo->prepare("UPDATE payments SET paid_amount = ? WHERE id = ?");
                $p->execute([$paidAmount, $row['id']]);
            } catch (Throwable $ignoredPaidAmount) {
                // raw_response still holds the gateway payload.
            }

            // Recompute reservation paid totals from verified payments.
            $sum = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id = ? AND provider = 'xendit' AND status = 'Verified'");
            $sum->execute([$row['reservation_id']]);
            $totalPaid = (float)$sum->fetchColumn();

            $r = $pdo->prepare("SELECT estimated_total FROM reservations WHERE reservation_id = ?");
            $r->execute([$row['reservation_id']]);
            $est = (float)$r->fetchColumn();
            $payStatus = ($est > 0 && $totalPaid + 0.01 >= $est) ? 'Paid' : 'Partially Paid';

            try {
                $ur = $pdo->prepare("UPDATE reservations SET payment_method = 'Xendit', payment_status = ?, amount_paid = ? WHERE reservation_id = ?");
                $ur->execute([$payStatus, $totalPaid, $row['reservation_id']]);
            } catch (Throwable $migRes) {
                $ur = $pdo->prepare("UPDATE reservations SET payment_method = 'Xendit' WHERE reservation_id = ?");
                $ur->execute([$row['reservation_id']]);
            }
        }
    } elseif (in_array($status, $failedStatuses, true)) {
        if (($row['status'] ?? '') === 'Pending') {
            $upd = $pdo->prepare("UPDATE payments SET status = 'Rejected', raw_response = ? WHERE id = ? AND status = 'Pending'");
            $upd->execute([$raw, $row['id']]);
        }
    } else {
        // PENDING/other: keep latest payload for debugging
        $upd = $pdo->prepare("UPDATE payments SET raw_response = ? WHERE id = ?");
        $upd->execute([$raw, $row['id']]);
    }

    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    error_log('Xendit webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo 'error';
}
