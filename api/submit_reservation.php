<?php
/**
 * API: Submit Reservation
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/capacity.php';

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
$address = trim($data['address'] ?? '');
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

// Address is REQUIRED for the off-site services (booths, catering, equipment
// rental) — same list the customer form uses (services.requiresAddress).
$ADDRESS_REQUIRED_SERVICES = ['coffee', 'noncoffee', 'pizza', 'catering-menu', 'equipment'];
if (in_array($serviceId, $ADDRESS_REQUIRED_SERVICES, true) && $address === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'address']);
    exit();
}
if ($address === '') {
    $address = null; // services that don't collect it store NULL
}

// Check if date is in the past
if (strtotime($eventDate) < strtotime(date('Y-m-d'))) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

try {
    // RULE 2 — Venue Rentals is exclusive per DATE. One customer per venue-day.
    // RULE 1 — a date holds at most PDR_MAX_SERVICES_PER_DAY active bookings.
    $dateCheck = pdrDateTaken($pdo, $eventDate);
    if ($dateCheck['taken']) {
        http_response_code(409);
        echo json_encode([
            'ok'     => false,
            'reason' => 'DATE_TAKEN',
            'cause'  => $dateCheck['reason'], // 'VENUE' or 'FULL'
            'count'  => $dateCheck['count'],
        ]);
        exit();
    }

    // RULE 3 — time slots are blocked PER SERVICE. Another service may share
    // this time; only the SAME service already holding it blocks it.
    $takenTimes = pdrTakenTimes($pdo, $eventDate, $mainService);
    if (in_array($eventTime, $takenTimes, true)) {
        http_response_code(409);
        echo json_encode([
            'ok'     => false,
            'reason' => 'TIME_TAKEN',
            'time'   => $eventTime,
            'service'=> $mainService,
        ]);
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
        contact_number, email, address, service_id, main_service, event_date, event_time,
        event_type, guests, selected_package, main_courses, additional_services,
        estimated_total, payment_method
    ) VALUES (?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $insert->execute([
        $reservationId, $pinHash, $customerName, $contactPerson,
        $contactNumber, $email, $address, $serviceId, $mainService, $eventDate, $eventTime,
        $eventType, $guests, $selectedPackage, $mainCourses, $additionalServices,
        $estimatedTotal, $paymentMethod
    ]);

    // Send the confirmation email (thank you + pending staff confirmation ->
    // payment + Booking ID and PIN). Best effort: a mail problem must NEVER
    // fail or slow down a reservation that is already saved — pdrSendMail
    // catches everything and reports instead of throwing.
    $emailResult = ['ok' => false, 'mode' => 'none', 'error' => 'not attempted'];
    try {
        require_once __DIR__ . '/mail/mailer.php';
        require_once __DIR__ . '/mail/confirmation_template.php';
        $tpl = pdrConfirmEmail([
            'reservationId'  => $reservationId,
            'bookingPin'     => $bookingPin,          // plaintext PIN, only known here
            'customerName'   => $customerName,
            'mainService'    => $mainService,
            'selectedPackage'=> $selectedPackage,
            'eventDate'      => $eventDate,
            'eventTime'      => $eventTime,
            'eventType'      => $eventType,
            'guests'         => $guests,
            'address'        => $address,
            'estimatedTotal' => $estimatedTotal,
        ]);
        $emailResult = pdrSendMail($email, $tpl['subject'], $tpl['html'], $tpl['text']);
    } catch (Throwable $me) {
        $emailResult = ['ok' => false, 'mode' => 'none', 'error' => $me->getMessage()];
    }

    echo json_encode([
        'ok' => true,
        'reservationId' => $reservationId,
        'bookingPin' => $bookingPin,
        'status' => 'Pending',
        // Where the confirmation email went: 'smtp' (real send), 'outbox'
        // (saved locally until SMTP is configured) or 'none' (failed).
        'email' => $emailResult['mode'],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK', 'error' => $e->getMessage()]);
}
