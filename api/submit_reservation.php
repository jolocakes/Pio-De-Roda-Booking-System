<?php
/**
 * API: Submit Reservation
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/capacity.php';
require_once __DIR__ . '/rate_limit.php';

// T-57: the booking endpoint writes to the database, upserts a customer and
// sends an email, so it needs the same per-IP ceiling the other public
// endpoints have. 10 attempts per 15 minutes is well above real use.
$reservationLimit = pdrRateLimit('reservation', 10, 900);
if (!$reservationLimit['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . $reservationLimit['retryAfter']);
    echo json_encode([
        'ok' => false,
        'reason' => 'RATE_LIMITED',
        'retryAfter' => $reservationLimit['retryAfter'],
    ]);
    exit();
}

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
$serviceRow = null;
if ($serviceId !== '') {
    // The service must exist AND be active. Unknown or deactivated services are
    // rejected instead of being silently stored with a NULL service_id.
    $chkService = $pdo->prepare("SELECT id, name, base_price, per_head, per_head_by_pkg, max_cap FROM services WHERE id = ? AND is_active = 1");
    $chkService->execute([$serviceId]);
    $serviceRow = $chkService->fetch(PDO::FETCH_ASSOC);
    if (!$serviceRow) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'serviceId']);
        exit();
    }
    // Canonical service name from the database — this value drives the
    // per-service time-slot rules in capacity.php and availability.php.
    $mainService = $serviceRow['name'];
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'serviceId']);
    exit();
}
$selectedPackage = trim($data['selectedPackage'] ?? '');
// Raw inputs are kept until validated below. Additional-service lines are
// NEVER stored as the browser sent them — every line is re-priced from the
// database in the catalog-validation block further down.
$mainCoursesInput = isset($data['mainCourses']) ? $data['mainCourses'] : null;
$additionalServicesInput = isset($data['additionalServices']) ? $data['additionalServices'] : null;
// The browser estimate is recorded but never trusted; the stored total is
// recomputed server-side below.
$estimatedTotal = floatval($data['estimatedTotal'] ?? 0);
$paymentMethod = trim($data['paymentMethod'] ?? 'Cash');

// Server-side validation
if (empty($customerName) || empty($eventDate) || empty($eventTime) || empty($mainService) || empty($contactNumber) || empty($email)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

// T-59: bound every free-text field. Without these, a scripted client can store
// arbitrarily large values. Reject (rather than truncate) so the customer knows
// which field is too long.
$lengthRules = [
    ['customerName',  $customerName,  120],
    ['contactPerson', $contactPerson, 120],
    ['contactNumber', $contactNumber, 30],
    ['address',       $address,       255],
    ['eventType',     $eventType,     60],
];
foreach ($lengthRules as [$lengthField, $lengthValue, $lengthMax]) {
    if (mb_strlen((string) $lengthValue) > $lengthMax) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => $lengthField]);
        exit();
    }
}

// Email format — the booking page validates this client-side only. The
// confirmation email carries the only copy of the booking PIN, so an
// undeliverable address means a customer who can never check their status.
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'email']);
    exit();
}

// Guests: must be at least 1 — it is the basis for every per-head price below.
if ($guests < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'guests']);
    exit();
}

// ============================================================
// CATALOG VALIDATION + SERVER-SIDE PRICING (new bookings only)
//   1. Every referenced catalog row (service, package, menu tier, add-on)
//      must exist and be ACTIVE — inactive or unknown items are rejected,
//      never silently stored.
//   2. The stored estimated total is RECOMPUTED from database prices; the
//      browser estimate is never trusted.
//   3. Existing reservations are never rewritten — this only shapes the new
//      record being inserted below, preserving historical reservation
//      prices exactly as they were booked.
// ============================================================
$additionalServices = '[]';
$mainCourses = null;
$packageMaxCap = null; // T-09: set when the selected package carries its own cap
if (isset($data['mainCourses'])) {
    if (!is_array($mainCoursesInput)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'mainCourses']);
        exit();
    }
    $mainCourses = json_encode(array_values(array_map('strval', $mainCoursesInput)), JSON_UNESCAPED_UNICODE);
}

try {
    // --- Selected Food / Package: must be an ACTIVE package (or menu tier) of this service ---
    if (!isset($data['selectedPackage']) || !is_string($data['selectedPackage']) || trim($data['selectedPackage']) === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'selectedPackage']);
        exit();
    }
    $selectedPackage = trim($data['selectedPackage']);

    // All catalog pricing lives in config/pricing.php so the admin edit endpoint
    // applies exactly the same rules when it has to re-price a booking.
    require_once __DIR__ . '/../config/pricing.php';

    $addonInput = [];
    if (isset($data['additionalServices']) && $data['additionalServices'] !== null) {
        if (!is_array($additionalServicesInput)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
            exit();
        }
        foreach ($additionalServicesInput as $entry) {
            if (!is_array($entry)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                exit();
            }
            $addonInput[] = [
                'name'        => trim((string) ($entry['name'] ?? '')),
                'packageName' => trim((string) ($entry['packageName'] ?? '')),
                'quantity'    => max(1, (int) ($entry['quantity'] ?? 1)),
            ];
        }
    }

    $priced = pdrPriceReservation($pdo, $serviceRow, $selectedPackage, $guests, $addonInput);
    if (!$priced['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => $priced['field'] ?? 'selectedPackage']);
        exit();
    }

    $estimatedTotal = $priced['total'];
    $packageMaxCap = $priced['packageMaxCap'];
    if (isset($data['additionalServices']) && $data['additionalServices'] !== null) {
        $additionalServices = json_encode($priced['addons'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $catalogError) {
    error_log('Reservation catalog validation failure: ' . $catalogError->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
    exit();
}

// T-09: enforce guest capacity server-side.
// The effective limit is the tighter of the service cap and the selected
// package cap (either may be NULL = unlimited). The browser-side max is only a
// convenience — this is the check that actually protects the catalog.
$serviceMaxCap = (isset($serviceRow['max_cap']) && $serviceRow['max_cap'] !== null && (int)$serviceRow['max_cap'] > 0)
    ? (int)$serviceRow['max_cap']
    : null;
$effectiveCap = null;
foreach ([$serviceMaxCap, $packageMaxCap] as $cap) {
    if ($cap !== null) {
        $effectiveCap = ($effectiveCap === null) ? $cap : min($effectiveCap, $cap);
    }
}
if ($effectiveCap !== null && $guests > $effectiveCap) {
    http_response_code(422);
    echo json_encode([
        'ok'      => false,
        'reason'  => 'CAPACITY_EXCEEDED',
        'field'   => 'guests',
        'guests'  => $guests,
        'maxCap'  => $effectiveCap,
        'service' => $mainService,
    ]);
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

// T-40: enforce the booking window server-side. "Not in the past" is not
// enough — same-day spam and year-3000 bookings are both rejected here.
$windowError = pdrBookingWindowError($eventDate);
if ($windowError !== null) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'reason' => $windowError, // TOO_SOON | TOO_FAR | INVALID
        'field' => 'eventDate',
        'minLeadDays' => PDR_MIN_LEAD_DAYS,
        'maxHorizonDays' => PDR_MAX_HORIZON_DAYS,
    ]);
    exit();
}

// T-45: CAPTCHA on the booking form. This is fail-open while the Turnstile
// keys are unset (fresh clone / local XAMPP), and enforced as soon as both
// keys exist in .env. A Cloudflare outage is logged and allowed through by
// pdrVerifyCaptcha() so bookings never depend on the gateway being up.
require_once __DIR__ . '/../config/captcha.php';
$captcha = pdrVerifyCaptcha($data['captchaToken'] ?? null, pdrCaptchaClientIp(), 'reservation');
if (!$captcha['ok']) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'reason' => 'CAPTCHA_FAILED',
        'field' => 'captchaToken',
        'mode' => $captcha['mode'],
    ]);
    exit();
}

try {
    // Schema changes are intentionally NOT executed here. The `address`
    // column is provisioned by database/migrate_services.php; running DDL on
    // every reservation request is slow and fails on MySQL 5.7/8.0.

    // Per-service capacity: the booking must fit inside its service's own
    // duration, daily cap and concurrency limit. One helper so submit agrees
    // exactly with the calendar and time picker.
    $slotCheck = pdrCheckBookingSlot($pdo, $eventDate, $serviceId, $eventTime);
    if (!$slotCheck['ok']) {
        http_response_code(409);
        echo json_encode([
            'ok'             => false,
            'reason'         => $slotCheck['reason'], // DATE_TAKEN | TIME_TAKEN
            'cause'          => $slotCheck['cause'] ?? null,
            'count'          => $slotCheck['count'] ?? null,
            'time'           => $slotCheck['time'] ?? $eventTime,
            'service'        => $slotCheck['service'] ?? $mainService,
            'blackoutReason' => $slotCheck['blackoutReason'] ?? null,
        ]);
        exit();
    }

    // T-11: collision-safe reservation ID allocation.
    // We no longer SELECT a candidate first — that check-then-insert left a race
    // window where two requests could pick the same ID. `reservations.reservation_id`
    // has a UNIQUE index, so the database is the arbiter. We generate a random
    // candidate, INSERT, and retry ONLY when MySQL reports a duplicate key
    // (SQLSTATE 23000 / error 1062).
    $year = date('Y', strtotime($eventDate));
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no 0/O/1/I ambiguity

    // Generate random 6-digit PIN
    $pinNum = random_int(100000, 999999);
    $bookingPin = strval($pinNum);
    $pinHash = password_hash($bookingPin, PASSWORD_DEFAULT);

    // Insert into database
    $insert = $pdo->prepare("INSERT INTO reservations (
        reservation_id, booking_pin_hash, status, customer_name, contact_person,
        contact_number, email, address, service_id, main_service, event_date, event_time,
        event_type, guests, selected_package, main_courses, additional_services,
        estimated_total, payment_method
    ) VALUES (?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $reservationId = '';
    $maxIdAttempts = 12;
    for ($attempt = 0; $attempt < $maxIdAttempts && $reservationId === ''; $attempt++) {
        $suffix = '';
        for ($i = 0; $i < 10; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $candidate = sprintf('PDR-%s-%s', $year, $suffix);

        try {
            $insert->execute([
                $candidate, $pinHash, $customerName, $contactPerson,
                $contactNumber, $email, $address, $serviceId, $mainService, $eventDate, $eventTime,
                $eventType, $guests, $selectedPackage, $mainCourses, $additionalServices,
                $estimatedTotal, $paymentMethod
            ]);
            $reservationId = $candidate;
        } catch (PDOException $dup) {
            $driverCode = isset($dup->errorInfo[1]) ? (int) $dup->errorInfo[1] : 0;
            if ($dup->getCode() === '23000' && $driverCode === 1062) {
                // Clear the failed statement before reusing it with a new ID.
                try { $insert->closeCursor(); } catch (Throwable $ignored) {}
                continue; // ID collision — try a fresh random suffix
            }
            throw $dup; // any other failure is a real error
        }
    }

    if ($reservationId === '') {
        throw new RuntimeException('Could not allocate a unique reservation ID after ' . $maxIdAttempts . ' attempts.');
    }

    // T-20: keep the customer directory in sync. The directory is derived from
    // reservations, so the newest booking wins for name/phone/address. A
    // failure here must never lose the reservation that was just saved.
    try {
        $customerStmt = $pdo->prepare(
            "INSERT INTO customers (email, full_name, phone, address)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                phone = VALUES(phone),
                address = VALUES(address),
                status = 'Active'"
        );
        $customerStmt->execute([$email, $customerName, $contactNumber, $address]);
    } catch (Throwable $customerError) {
        error_log('Customer directory sync failed for ' . $reservationId . ': ' . $customerError->getMessage());
    }

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
    // Log the detail, never echo it: raw exception text can expose SQL/schema
    // internals to an anonymous client (every other endpoint logs + returns a
    // generic reason).
    error_log('submit_reservation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK']);
}
