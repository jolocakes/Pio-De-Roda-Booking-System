<?php
/**
 * API: Admin Get Reservations
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../admin/auth_check.php';

try {
    $stmt = $pdo->query("SELECT * FROM reservations ORDER BY id DESC");
    $rows = $stmt->fetchAll();

    $reservations = [];
    foreach ($rows as $r) {
        $reservations[] = [
            'id' => $r['reservation_id'],
            'customerName' => $r['customer_name'],
            'contactPerson' => $r['contact_person'],
            'contactNumber' => $r['contact_number'],
            'email' => $r['email'],
            'service' => $r['main_service'],
            'eventDate' => $r['event_date'],
            'eventTime' => $r['event_time'],
            'eventType' => $r['event_type'],
            'guests' => $r['guests'],
            'selectedPackage' => $r['selected_package'],
            'mainCourses' => $r['main_courses'] ? json_decode($r['main_courses'], true) : [],
            'additionalServices' => $r['additional_services'] ? json_decode($r['additional_services'], true) : [],
            'estimatedTotal' => '₱' . number_format($r['estimated_total'], 2),
            'paymentMethod' => $r['payment_method'],
            'status' => $r['status'],
            'submittedAt' => $r['submitted_at']
        ];
    }

    echo json_encode($reservations);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
