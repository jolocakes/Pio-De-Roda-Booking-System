<?php
/**
 * API: Lookup Reservation (Customer view with masked phone/email)
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/lookup_guard.php';
require_once __DIR__ . '/capacity.php';

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
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ?");
    $stmt->execute([$reservationId]);
    $res = $stmt->fetch();

    if (!$res || !password_verify($bookingPin, $res['booking_pin_hash'])) {
        pdrLookupRecord($pdo, $reservationId, false);
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    pdrLookupRecord($pdo, $reservationId, true);

    // Mask phone and email
    $phone = $res['contact_number'];
    $phoneMasked = strlen($phone) < 4 ? '••••' : substr($phone, 0, 2) . str_repeat('*', strlen($phone) - 3) . substr($phone, -1);

    $email = $res['email'];
    $at = strpos($email, '@');
    $emailMasked = $at === false ? '••••' : substr($email, 0, 1) . '***' . substr($email, $at);

    // T-37: expose the self-service policy so the page never has to hardcode
    // the windows. The server re-checks every rule on submit anyway.
    $daysUntilEvent = pdrDaysUntilDate((string) $res['event_date']);
    $rescheduleCount = (int) ($res['reschedule_count'] ?? 0);
    $changeable = in_array((string) $res['status'], ['Pending', 'Confirmed'], true);

    $customerView = [
        'reservationId' => $res['reservation_id'],
        'status' => $res['status'],
        'submittedAt' => $res['submitted_at'],
        'customerName' => $res['customer_name'],
        'contactPerson' => $res['contact_person'],
        'contactNumberMasked' => $phoneMasked,
        'emailMasked' => $emailMasked,
        'address' => $res['address'] ?? null,
        'eventDate' => $res['event_date'],
        'eventTime' => $res['event_time'],
        'eventType' => $res['event_type'],
        'guests' => $res['guests'],
        'mainService' => $res['main_service'],
        'selectedPackage' => $res['selected_package'],
        'mainCourses' => $res['main_courses'] ? json_decode($res['main_courses'], true) : [],
        'additionalServices' => $res['additional_services'] ? json_decode($res['additional_services'], true) : [],
        // Raw numeric amount — the client formats it (see booking-status.js money()).
        // Sending a pre-formatted "₱1,234.00" here made Number() return NaN.
        'estimatedTotal' => round((float) $res['estimated_total'], 2),
        'daysUntilEvent' => $daysUntilEvent,
        'rescheduleCount' => $rescheduleCount,
        'maxReschedules' => PDR_MAX_RESCHEDULES,
        'cancelMinDays' => PDR_CANCEL_MIN_DAYS,
        'rescheduleMinDays' => PDR_RESCHEDULE_MIN_DAYS,
        'canCancel' => $changeable && $daysUntilEvent >= PDR_CANCEL_MIN_DAYS,
        'canReschedule' => $changeable
            && $daysUntilEvent >= PDR_RESCHEDULE_MIN_DAYS
            && $rescheduleCount < PDR_MAX_RESCHEDULES,
    ];

    echo json_encode(['ok' => true, 'reservation' => $customerView]);

} catch (Throwable $e) {
    error_log('lookup_reservation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK']);
}
