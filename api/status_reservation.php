<?php
/**
 * API: Get Reservation Status
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lookup_guard.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$reservationId = pdrLookupNormalizeId((string) ($data['reservationId'] ?? ''));
$bookingPin = trim((string) ($data['bookingPin'] ?? ''));

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
    $stmt = $pdo->prepare("SELECT status, booking_pin_hash FROM reservations WHERE reservation_id = ?");
    $stmt->execute([$reservationId]);
    $res = $stmt->fetch();

    if (!$res || !password_verify($bookingPin, $res['booking_pin_hash'])) {
        pdrLookupRecord($pdo, $reservationId, false);
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    pdrLookupRecord($pdo, $reservationId, true);
    echo json_encode(['ok' => true, 'status' => $res['status']]);

} catch (Throwable $e) {
    error_log('status_reservation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK']);
}
