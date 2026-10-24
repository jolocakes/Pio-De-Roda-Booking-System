<?php
/**
 * API: Lookup Reservation (Customer view with masked phone/email)
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
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
    $stmt->execute([$reservationId]);
    $res = $stmt->fetch();

    if (!$res || !password_verify($bookingPin, $res['booking_pin_hash'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    // Mask phone and email
    $phone = $res['contact_number'];
    $phoneMasked = strlen($phone) < 4 ? '••••' : substr($phone, 0, 2) . str_repeat('*', strlen($phone) - 3) . substr($phone, -1);

    $email = $res['email'];
    $at = strpos($email, '@');
    $emailMasked = $at === false ? '••••' : substr($email, 0, 1) . '***' . substr($email, $at);

    $customerView = [
        'reservationId' => $res['reservation_id'],
        'status' => $res['status'],
        'submittedAt' => $res['submitted_at'],
        'customerName' => $res['customer_name'],
        'contactPerson' => $res['contact_person'],
        'contactNumberMasked' => $phoneMasked,
        'emailMasked' => $emailMasked,
        'eventDate' => $res['event_date'],
        'eventTime' => $res['event_time'],
        'eventType' => $res['event_type'],
        'guests' => $res['guests'],
        'mainService' => $res['main_service'],
        'selectedPackage' => $res['selected_package'],
        'mainCourses' => $res['main_courses'] ? json_decode($res['main_courses'], true) : [],
        'additionalServices' => $res['additional_services'] ? json_decode($res['additional_services'], true) : [],
        'estimatedTotal' => '₱' . number_format($res['estimated_total'], 2)
    ];

    echo json_encode(['ok' => true, 'reservation' => $customerView]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK']);
}
