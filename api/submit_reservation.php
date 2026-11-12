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

    $pkgStmt = $pdo->prepare("SELECT price, max_cap FROM service_packages WHERE service_id = ? AND package_name = ? AND is_active = 1 ORDER BY sort_order ASC LIMIT 1");
    $pkgStmt->execute([$serviceId, $selectedPackage]);
    $pkgRow = $pkgStmt->fetch(PDO::FETCH_ASSOC);

    if ($pkgRow) {
        $estimatedTotal = (int)$serviceRow['per_head_by_pkg'] === 1
            ? (float)$pkgRow['price'] * $guests   // "per head" services price the package rate × guests
            : (float)$pkgRow['price'];            // all other services: flat package price

        // T-09: a package may cap guests more tightly than its parent service.
        if (isset($pkgRow['max_cap']) && $pkgRow['max_cap'] !== null && (int)$pkgRow['max_cap'] > 0) {
            $packageMaxCap = (int)$pkgRow['max_cap'];
        }
    } else {
        // Menu-tiered picks (e.g. Dine-In Menu A / Menu B) resolve through service_menus.
        $menuStmt = $pdo->prepare("SELECT menu_code FROM service_menus WHERE service_id = ? AND item_name = ? AND is_active = 1 LIMIT 1");
        $menuStmt->execute([$serviceId, $selectedPackage]);
        $menuRow = $menuStmt->fetch(PDO::FETCH_ASSOC);
        if (!$menuRow) {
            // Not an active package or menu tier of this service.
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'selectedPackage']);
            exit();
        }
        if ($serviceRow['per_head'] !== null) {
            $estimatedTotal = (float)$serviceRow['per_head'] * $guests;
        } else {
            // Menu rate is unset and the service has no per-head rate — refusing
            // to store a reservation whose price cannot be resolved safely.
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'selectedPackage']);
            exit();
        }
    }

    // --- Additional Services: validate and re-price every line from the database ---
    $addonLines = [];
    if (isset($data['additionalServices']) && $data['additionalServices'] !== null) {
        if (!is_array($additionalServicesInput)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
            exit();
        }
        if (count($additionalServicesInput) > 0) {
            $lookupAddon = $pdo->prepare(
                "SELECT sa.name, sa.service_id, sa.price_from, sa.fixed_price, sa.allow_qty
                   FROM service_addons sa
                   JOIN services svc ON svc.id = sa.service_id
                  WHERE sa.name = ? AND sa.is_active = 1 AND svc.is_active = 1
                  ORDER BY sa.id ASC LIMIT 1"
            );
            $lookupLinked = $pdo->prepare("SELECT id, per_head, per_head_by_pkg FROM services WHERE id = ? AND is_active = 1 LIMIT 1");
            $lookupLinkedPkg = $pdo->prepare("SELECT price FROM service_packages WHERE service_id = ? AND package_name = ? AND is_active = 1 ORDER BY sort_order ASC LIMIT 1");
            $checkAddonName = $pdo->prepare(
                "SELECT (SELECT COUNT(*) FROM service_packages WHERE service_id = ? AND package_name = ? AND is_active = 1)
                       + (SELECT COUNT(*) FROM service_menus    WHERE service_id = ? AND item_name    = ? AND is_active = 1) AS hits"
            );

            $seenAddonNames = [];
            foreach ($additionalServicesInput as $entry) {
                if (!is_array($entry)) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                    exit();
                }
                $addonName = trim((string)($entry['name'] ?? ''));
                $aPkgName  = trim((string)($entry['packageName'] ?? ''));
                $addonQty  = max(1, (int)($entry['quantity'] ?? 1));
                if ($addonName === '' || $aPkgName === '') {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                    exit();
                }
                if ($addonName === $mainService) {
                    // Mirrors the booking-page rule: the main service cannot also be an add-on.
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                    exit();
                }
                if (isset($seenAddonNames[$addonName])) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                    exit();
                }
                $seenAddonNames[$addonName] = true;

                $lookupAddon->execute([$addonName]);
                $addon = $lookupAddon->fetch(PDO::FETCH_ASSOC);
                if (!$addon) {
                    // Unknown add-on, inactive add-on, or an add-on whose own parent
                    // service was deactivated.
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                    exit();
                }

                if ($addon['price_from'] !== null) {
                    // Rates derive from the add-on's linked catalog service.
                    $lookupLinked->execute([$addon['price_from']]);
                    $linked = $lookupLinked->fetch(PDO::FETCH_ASSOC);
                    if (!$linked) {
                        http_response_code(400);
                        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                        exit();
                    }
                    if ((int)$linked['per_head_by_pkg'] === 1) {
                        $lookupLinkedPkg->execute([$linked['id'], $aPkgName]);
                        $linkedPkg = $lookupLinkedPkg->fetch(PDO::FETCH_ASSOC);
                        if (!$linkedPkg) {
                            http_response_code(400);
                            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                            exit();
                        }
                        $addonRate = (float)$linkedPkg['price'];
                        $addonPerHead = true;
                    } elseif ($linked['per_head'] !== null) {
                        $addonRate = (float)$linked['per_head'];
                        $addonPerHead = true;
                    } else {
                        $lookupLinkedPkg->execute([$linked['id'], $aPkgName]);
                        $linkedPkg = $lookupLinkedPkg->fetch(PDO::FETCH_ASSOC);
                        if (!$linkedPkg) {
                            http_response_code(400);
                            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                            exit();
                        }
                        $addonRate = (float)$linkedPkg['price'];
                        $addonPerHead = false;
                    }
                    // The chosen package/tier must be an ACTIVE option of the pricing source.
                    $checkAddonName->execute([$linked['id'], $aPkgName, $linked['id'], $aPkgName]);
                    if ((int)$checkAddonName->fetchColumn() < 1) {
                        http_response_code(400);
                        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                        exit();
                    }
                } elseif ($addon['fixed_price'] !== null) {
                    $addonRate = (float)$addon['fixed_price'];
                    $addonPerHead = false;
                    $checkAddonName->execute([$addon['service_id'], $aPkgName, $addon['service_id'], $aPkgName]);
                    if ((int)$checkAddonName->fetchColumn() < 1) {
                        http_response_code(400);
                        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                        exit();
                    }
                } else {
                    // Unpriceable add-on (no pricing source configured) — the browser's
                    // own price for it is never accepted.
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'additionalServices']);
                    exit();
                }

                // Line pricing follows the exact rules the booking page uses:
                // per-head add-ons multiply the rate by the guest count, quantity
                // add-ons multiply by the chosen quantity, the rest are flat.
                $addonTotal = ($addonPerHead ? $addonRate * $guests : $addonRate)
                            * ((int)$addon['allow_qty'] === 1 ? $addonQty : 1);
                $addonLines[] = [
                    'name'        => $addon['name'],
                    'packageName' => $aPkgName,
                    'quantity'    => (int)$addon['allow_qty'] === 1 ? $addonQty : 1,
                    'unitPrice'   => round($addonRate, 2),
                    'totalPrice'  => round($addonTotal, 2),
                ];
                $estimatedTotal += $addonTotal;
            }
        }
        $additionalServices = json_encode($addonLines, JSON_UNESCAPED_UNICODE);
    }

    $estimatedTotal = round($estimatedTotal, 2);
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
