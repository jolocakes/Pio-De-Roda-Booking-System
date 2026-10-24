<?php
/**
 * API: Admin Update Reservation Status
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../admin/auth_check.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

$reservationId = trim($data['reservationId'] ?? '');
$newStatus = trim($data['status'] ?? '');

$allowedStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];
if (empty($reservationId) || !in_array($newStatus, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

try {
    $checkStmt = $pdo->prepare("SELECT status FROM reservations WHERE reservation_id = ?");
    $checkStmt->execute([$reservationId]);
    $current = $checkStmt->fetch();

    if (!$current) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    if ($current['status'] !== $newStatus) {
        $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
        $stmt->execute([$newStatus, $reservationId]);
    }

    echo json_encode(['ok' => true, 'reservationId' => $reservationId, 'status' => $newStatus]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK']);
}
