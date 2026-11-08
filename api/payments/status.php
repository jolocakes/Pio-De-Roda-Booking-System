<?php
/**
 * API: Get payment summary for a reservation (verified by ID+PIN).
 * POST JSON: { reservationId, bookingPin }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../lookup_guard.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$reservationId = pdrLookupNormalizeId((string) ($data['reservationId'] ?? ''));
$bookingPin    = trim((string) ($data['bookingPin'] ?? ''));

if ($reservationId === '' || $bookingPin === '') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
    exit();
}

$lock = pdrLookupGuard($pdo, $reservationId);
if ($lock !== null) {
    pdrLookupReject($lock);
}

try {
    $stmt = $pdo->prepare("SELECT reservation_id, booking_pin_hash, status, estimated_total, payment_status, amount_paid, payment_method FROM reservations WHERE reservation_id = ?");
    $stmt->execute([$reservationId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res || !password_verify($bookingPin, $res['booking_pin_hash'])) {
        pdrLookupRecord($pdo, $reservationId, false);
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    pdrLookupRecord($pdo, $reservationId, true);

    $est = (float)($res['estimated_total'] ?? 0);
    $paid = (float)($res['amount_paid'] ?? 0);
    $balance = max(0.0, round($est - $paid, 2));

    $depositPercent = 50;
    $xenditConfig = __DIR__ . '/../../config/xendit.php';
    if (is_file($xenditConfig)) {
        $xc = require $xenditConfig;
        $depositPercent = (int)($xc['deposit_percent'] ?? 50);
    }

    $p = $pdo->prepare("SELECT id, provider, provider_reference, amount, payment_type, payment_channel, status, created_at, paid_at FROM payments WHERE reservation_id = ? ORDER BY id DESC LIMIT 10");
    $p->execute([$reservationId]);
    $payments = $p->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'depositPercent' => $depositPercent,
        'reservation' => [
            'reservationId' => $res['reservation_id'],
            'status'        => $res['status'],
            'estimatedTotal'=> $est,
            'amountPaid'    => $paid,
            'balance'       => $balance,
            'paymentStatus' => $res['payment_status'] ?? ($balance <= 0 && $est > 0 ? 'Paid' : 'Unpaid'),
            'paymentMethod' => $res['payment_method'],
        ],
        'payments' => $payments,
    ]);
} catch (Throwable $e) {
    // Fallback if migration not yet applied (payment_status/amount_paid columns missing)
    try {
        $stmt = $pdo->prepare("SELECT reservation_id, booking_pin_hash, status, estimated_total, payment_method FROM reservations WHERE reservation_id = ?");
        $stmt->execute([$reservationId]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res || !password_verify($bookingPin, $res['booking_pin_hash'])) {
            pdrLookupRecord($pdo, $reservationId, false);
            http_response_code(404); echo json_encode(['ok'=>false,'reason'=>'NOT_FOUND']); exit();
        }
        pdrLookupRecord($pdo, $reservationId, true);
        echo json_encode(['ok'=>true,'reservation'=>[
            'reservationId'=>$res['reservation_id'],'status'=>$res['status'],
            'estimatedTotal'=>(float)$res['estimated_total'],'amountPaid'=>0,
            'balance'=>(float)$res['estimated_total'],'paymentStatus'=>'Unpaid',
            'paymentMethod'=>$res['payment_method']
        ],'payments'=>[],'warning'=>'MIGRATION_NEEDED']);
    } catch (Throwable $e2) {
        error_log('payments/status fallback error: ' . $e2->getMessage());
        http_response_code(500); echo json_encode(['ok'=>false,'reason'=>'SERVER_ERROR']);
    }
}
