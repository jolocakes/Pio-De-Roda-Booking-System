<?php
/**
 * API: Create Xendit Invoice for a CONFIRMED reservation (full or deposit).
 * POST JSON: { reservationId, bookingPin, paymentType: "full"|"deposit" }
 * Requires verified booking (ID+PIN) and status Confirmed. Test-mode only config.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

$xenditConfig = __DIR__ . '/../../config/xendit.php';
if (!is_file($xenditConfig)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'CONFIG_MISSING']);
    exit;
}
$xendit = require $xenditConfig;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];

$reservationId = trim((string)($data['reservationId'] ?? ''));
$bookingPin    = trim((string)($data['bookingPin'] ?? ''));
$paymentType   = strtolower(trim((string)($data['paymentType'] ?? 'full')));

if ($reservationId === '' || $bookingPin === '') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
    exit;
}
if (!in_array($paymentType, ['full', 'deposit'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'paymentType']);
    exit;
}
if (empty($xendit['secret_key']) || strpos($xendit['secret_key'], 'REPLACE') !== false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'XENDIT_NOT_CONFIGURED']);
    exit;
}

try {
    try {
        $stmt = $pdo->prepare("SELECT reservation_id, booking_pin_hash, status, email, customer_name, estimated_total, event_date, main_service, payment_status, amount_paid FROM reservations WHERE reservation_id = ?");
        $stmt->execute([$reservationId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $mig) {
        $stmt = $pdo->prepare("SELECT reservation_id, booking_pin_hash, status, email, customer_name, estimated_total, event_date, main_service FROM reservations WHERE reservation_id = ?");
        $stmt->execute([$reservationId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res) { $res['amount_paid'] = 0; $res['payment_status'] = null; }
    }

    if (!$res || !password_verify($bookingPin, $res['booking_pin_hash'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit;
    }
    if (($res['status'] ?? '') !== 'Confirmed') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'NOT_CONFIRMED', 'status' => $res['status']]);
        exit;
    }

    $estimated = (float)($res['estimated_total'] ?? 0);
    if ($estimated <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID_AMOUNT']);
        exit;
    }

    $amountPaid = (float)($res['amount_paid'] ?? 0);
    $balance    = max(0.0, round($estimated - $amountPaid, 2));
    if ($balance <= 0) {
        echo json_encode(['ok' => true, 'already_paid' => true, 'message' => 'This booking is already fully paid.']);
        exit;
    }

    $depositPercent = (int)($xendit['deposit_percent'] ?? 50);
    if ($depositPercent < 1 || $depositPercent > 99) $depositPercent = 50;

    if ($paymentType === 'deposit' && $amountPaid <= 0) {
        $amount = round($estimated * ($depositPercent / 100), 2);
        if ($amount < 1) $amount = 1.00;
        if ($amount > $balance) $amount = $balance;
    } else {
        // full payment, or paying remaining balance
        $paymentType = 'full';
        $amount = $balance;
    }

    // Reuse an open (Pending) invoice for this reservation+type+amount to avoid duplicates (requires migration).
    try {
        $reuse = $pdo->prepare("SELECT provider_reference, raw_response FROM payments WHERE reservation_id = ? AND provider = 'xendit' AND status = 'Pending' AND amount = ? AND payment_type = ? ORDER BY id DESC LIMIT 1");
        $reuse->execute([$reservationId, $amount, $paymentType]);
        $existing = $reuse->fetch(PDO::FETCH_ASSOC);
        if ($existing && !empty($existing['raw_response'])) {
            $prev = json_decode($existing['raw_response'], true);
            if (!empty($prev['invoice_url']) && !empty($prev['id'])) {
                echo json_encode(['ok' => true, 'invoice_url' => $prev['invoice_url'], 'invoice_id' => $prev['id'], 'reused' => true]);
                exit;
            }
        }
    } catch (Throwable $migReuse) { /* migration not applied; continue */ }

    $externalId = 'PDR-' . $res['reservation_id'] . '-' . ($paymentType === 'deposit' ? 'DEP' : 'FULL') . '-' . time();
    $appBase = rtrim((string)($xendit['app_base_url'] ?? ''), '/');
    $successUrl = $appBase . '/customer/payment/result.html?result=success&id=' . rawurlencode($res['reservation_id']);
    $failureUrl = $appBase . '/customer/payment/result.html?result=failure&id=' . rawurlencode($res['reservation_id']);

    $payload = [
        'external_id'          => $externalId,
        'amount'               => $amount,
        'payer_email'          => $res['email'],
        'description'          => 'Pio De Roda Booking ' . $res['reservation_id'] . ' (' . strtoupper($paymentType) . ')',
        'invoice_duration'     => (int)($xendit['invoice_duration'] ?? 86400),
        'currency'             => $xendit['currency'] ?? 'PHP',
        'success_redirect_url' => $successUrl,
        'failure_redirect_url' => $failureUrl,
    ];
    $methods = $xendit['payment_methods'] ?? null;
    if (is_array($methods) && count($methods) > 0) {
        $payload['payment_methods'] = array_values($methods);
    }

    $ch = curl_init(rtrim((string)$xendit['base_url'], '/') . '/v2/invoices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => $xendit['secret_key'] . ':',
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    $invoice = json_decode((string)$response, true);
    if ($curlErr || $httpCode >= 300 || !is_array($invoice) || empty($invoice['invoice_url']) || empty($invoice['id'])) {
        error_log('Xendit create invoice failed: http=' . $httpCode . ' curl=' . $curlErr . ' body=' . $response);
        http_response_code(502);
        $detail = is_array($invoice) ? ($invoice['message'] ?? $invoice['error_code'] ?? null) : null;
        echo json_encode(['ok' => false, 'reason' => 'PAYMENT_INIT_FAILED', 'detail' => $detail]);
        exit;
    }

    try {
        $ins = $pdo->prepare("INSERT INTO payments (reservation_id, provider, provider_reference, external_id, amount, payment_type, status, raw_response) VALUES (?, 'xendit', ?, ?, ?, ?, 'Pending', ?)");
        $ins->execute([
            $res['reservation_id'],
            $invoice['id'],
            $externalId,
            $amount,
            $paymentType,
            json_encode($invoice, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $migIns) {
        // Fallback for pre-migration `payments` table shape.
        $ins = $pdo->prepare("INSERT INTO payments (reservation_id, amount, status) VALUES (?, ?, 'Pending')");
        $ins->execute([$res['reservation_id'], $amount]);
        error_log('Xendit payment row saved with fallback schema (run database/migrate_xendit_payments.sql): ' . $migIns->getMessage());
    }

    echo json_encode([
        'ok'          => true,
        'invoice_url' => $invoice['invoice_url'],
        'invoice_id'  => $invoice['id'],
        'amount'      => $amount,
        'payment_type'=> $paymentType,
    ]);

} catch (Throwable $e) {
    error_log('create_invoice error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
