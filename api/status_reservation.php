<?php
/**
 * API: Get Reservation Status
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$reservationId = trim($data['reservationId'] ?? '');
$bookingPin = trim($data['bookingPin'] ?? '');

if (empty($reservationId) || empty($bookingPin)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT status, booking_pin_hash FROM reservations WHERE reservation_id = ?");
    $stmt->execute([$reservationId]);
    $res = $stmt->fetch();

    if (!$res || !password_verify($bookingPin, $res['booking_pin_hash'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    echo json_encode(['ok' => true, 'status' => $res['status']]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK']);
}
