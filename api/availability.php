<?php
/**
 * API: Get Unavailable Dates
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // Fetch dates that have confirmed reservations only
    $stmt = $pdo->query("SELECT DISTINCT event_date FROM reservations WHERE status = 'Confirmed'");
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['dates' => $dates]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['dates' => [], 'error' => 'Database error']);
}
