<?php
/**
 * API: Submit Reservation
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

$customerName = trim($data['customerName'] ?? '');
$contactPerson = trim($data['contactPerson'] ?? $customerName);
$contactNumber = trim($data['contactNumber'] ?? '');
$email = trim($data['email'] ?? '');
$eventDate = trim($data['eventDate'] ?? '');
$eventTime = trim($data['eventTime'] ?? '');
$eventType = trim($data['eventType'] ?? '');
$guests = intval($data['guests'] ?? 0);
$mainService = trim($data['mainService'] ?? '');
$serviceId = trim($data['serviceId'] ?? '');
if (!empty($serviceId)) {
    $chkService = $pdo->prepare("SELECT id FROM services WHERE id = ?");
    $chkService->execute([$serviceId]);
    if (!$chkService->fetch()) {
        $serviceId = null;
    }
} else {
    $serviceId = null;
}
$selectedPackage = trim($data['selectedPackage'] ?? '');
$mainCourses = isset($data['mainCourses']) ? json_encode($data['mainCourses']) : null;
$additionalServices = isset($data['additionalServices']) ? json_encode($data['additionalServices']) : null;
$estimatedTotal = floatval($data['estimatedTotal'] ?? 0);
$paymentMethod = trim($data['paymentMethod'] ?? 'Cash');

// Server-side validation
if (empty($customerName) || empty($eventDate) || empty($eventTime) || empty($mainService) || empty($contactNumber) || empty($email)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

// Check if date is in the past
if (strtotime($eventDate) < strtotime(date('Y-m-d'))) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

try {
    // Check if date is already taken by a Pending or Confirmed reservation
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE event_date = ? AND status IN ('Pending', 'Confirmed')");
    $stmt->execute([$eventDate]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'DATE_TAKEN']);
        exit();
    }

    // Generate unique Reservation ID: PDR-{YYYY}-{####}
    $year = date('Y', strtotime($eventDate));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE reservation_id LIKE ?");
    $stmt->execute(["PDR-$year-%"]);
    $count = $stmt->fetchColumn();
    $reservationId = sprintf("PDR-%s-%04d", $year, $count + 1);

    // Ensure uniqueness
    while (true) {
        $check = $pdo->prepare("SELECT id FROM reservations WHERE reservation_id = ?");
        $check->execute([$reservationId]);
        if (!$check->fetch()) break;
        $count++;
        $reservationId = sprintf("PDR-%s-%04d", $year, $count + 1);
    }

    // Generate random 6-digit PIN
    $pinNum = mt_rand(100000, 999999);
    $bookingPin = strval($pinNum);
    $pinHash = password_hash($bookingPin, PASSWORD_DEFAULT);

    // Insert into database
    $insert = $pdo->prepare("INSERT INTO reservations (
        reservation_id, booking_pin_hash, status, customer_name, contact_person,
        contact_number, email, service_id, main_service, event_date, event_time,
        event_type, guests, selected_package, main_courses, additional_services,
        estimated_total, payment_method
    ) VALUES (?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $insert->execute([
        $reservationId, $pinHash, $customerName, $contactPerson,
        $contactNumber, $email, $serviceId, $mainService, $eventDate, $eventTime,
        $eventType, $guests, $selectedPackage, $mainCourses, $additionalServices,
        $estimatedTotal, $paymentMethod
    ]);

    echo json_encode([
        'ok' => true,
        'reservationId' => $reservationId,
        'bookingPin' => $bookingPin,
        'status' => 'Pending'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK', 'error' => $e->getMessage()]);
}
