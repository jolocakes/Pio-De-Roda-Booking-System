<?php
/**
 * API: Admin Update Reservation
 * Pio De Roda Hospitality Reservation and Event Booking System
 *
 * Updates the editable reservation fields by reservation_id:
 * service, event date, event time, guest count, and status.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/audit.php';
require_once __DIR__ . '/../../config/rbac.php';
require_once __DIR__ . '/../capacity.php';

pdrSessionStart();

// JSON 401 instead of an HTML redirect so the admin page can show a clear message.
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}

pdrRequireApiCapability('manage_reservations');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);
    exit();
}

pdrRequireCsrfJson();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'body']);
    exit();
}

$reservationId = trim((string) ($data['reservationId'] ?? ''));
$service       = trim((string) ($data['service'] ?? ''));
$date          = trim((string) ($data['date'] ?? ''));
$time          = trim((string) ($data['time'] ?? ''));
$guestsInput   = $data['guests'] ?? null;
$status        = trim((string) ($data['status'] ?? ''));

$allowedStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'Rejected'];

if ($reservationId === '' || strlen($reservationId) > 30) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'reservationId']);
    exit();
}

if ($service === '' || strlen($service) > 100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'service']);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'date']);
    exit();
}

$dateCheck = DateTime::createFromFormat('!Y-m-d', $date);
if (!$dateCheck || $dateCheck->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'date']);
    exit();
}

if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'time']);
    exit();
}

if (!is_scalar($guestsInput)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'guests']);
    exit();
}

$guests = filter_var(trim((string) $guestsInput), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($guests === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'guests']);
    exit();
}

if (!in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'status']);
    exit();
}

try {
    // Does the reservation exist, and what is its current state? We need the
    // current status so the capacity checks can exclude the row itself when it
    // is being rescheduled (otherwise it always conflicts with its own slot).
    $checkStmt = $pdo->prepare("SELECT id, status, selected_package, additional_services, service_id, guests FROM reservations WHERE reservation_id = ? LIMIT 1");
    $checkStmt->execute([$reservationId]);
    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    // T-13: the service must be a real, active catalog entry — not arbitrary
    // text. Resolving by name keeps the admin UI's service labels working while
    // guaranteeing `services.id`/`max_cap` line up with `main_service`.
    $svcStmt = $pdo->prepare("SELECT id, name, per_head, per_head_by_pkg, max_cap FROM services WHERE name = ? AND is_active = 1 ORDER BY id ASC LIMIT 1");
    $svcStmt->execute([$service]);
    $svcRow = $svcStmt->fetch(PDO::FETCH_ASSOC);
    if (!$svcRow) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'service']);
        exit();
    }
    $serviceName = $svcRow['name'];
    $serviceId   = $svcRow['id'];

    // Guest cap: reject over-capacity edits, same rule the customer form uses.
    // The effective limit is the tighter of the service cap and the selected
    // package cap (either may be NULL = unlimited), so an admin cannot bypass a
    // package-level cap that the booking flow already enforces.
    $serviceCap = ($svcRow['max_cap'] !== null && (int) $svcRow['max_cap'] > 0) ? (int) $svcRow['max_cap'] : null;
    $packageCap = null;
    if (!empty($existing['selected_package'])) {
        $pkgStmt = $pdo->prepare(
            "SELECT max_cap FROM service_packages
              WHERE service_id = ? AND package_name = ? AND is_active = 1
              ORDER BY sort_order ASC LIMIT 1"
        );
        $pkgStmt->execute([$serviceId, $existing['selected_package']]);
        $pkgCapRaw = $pkgStmt->fetchColumn();
        if ($pkgCapRaw !== false && $pkgCapRaw !== null && (int) $pkgCapRaw > 0) {
            $packageCap = (int) $pkgCapRaw;
        }
    }
    $maxCap = null;
    foreach ([$serviceCap, $packageCap] as $cap) {
        if ($cap !== null) {
            $maxCap = ($maxCap === null) ? $cap : min($maxCap, $cap);
        }
    }
    if ($maxCap !== null && $guests > $maxCap) {
        http_response_code(422);
        echo json_encode([
            'ok'      => false,
            'reason'  => 'CAPACITY_EXCEEDED',
            'field'   => 'guests',
            'guests'  => $guests,
            'maxCap'  => $maxCap,
            'service' => $serviceName,
        ]);
        exit();
    }

    // A reschedule only has to obey booking rules while the reservation still
    // occupies the calendar. Cancelled/Completed rows are historical records.
    $activeStatuses = ['Pending', 'Confirmed'];
    if (in_array($status, $activeStatuses, true)) {
        $slotCheck = pdrCheckBookingSlot($pdo, $date, $serviceId, $time, $reservationId);
        if (!$slotCheck['ok']) {
            http_response_code(409);
            echo json_encode([
                'ok'             => false,
                'reason'         => $slotCheck['reason'],
                'cause'          => $slotCheck['cause'] ?? null,
                'count'          => $slotCheck['count'] ?? null,
                'time'           => $slotCheck['time'] ?? $time,
                'service'        => $slotCheck['service'] ?? $serviceName,
                'blackoutReason' => $slotCheck['blackoutReason'] ?? null,
            ]);
            exit();
        }
    }

    // Re-price when the price inputs actually change. A scheduling-only edit
    // (date/time/status) must not rewrite a historical total, but changing the
    // service or the guest count invalidates it. We reuse the exact pricing
    // rules the public booking endpoint uses (config/pricing.php).
    $newTotal = null;
    $newAddonsJson = null;
    $serviceChanged = (string) ($existing['service_id'] ?? '') !== (string) $serviceId;
    $guestsChanged  = (int) ($existing['guests'] ?? 0) !== (int) $guests;

    if ($serviceChanged || $guestsChanged) {
        require_once __DIR__ . '/../../config/pricing.php';

        // Re-derive the add-on input shape from the stored JSON. Only name,
        // packageName and quantity are inputs; prices are always recomputed.
        $storedAddons = [];
        $decodedAddons = json_decode((string) ($existing['additional_services'] ?? '[]'), true);
        if (is_array($decodedAddons)) {
            foreach ($decodedAddons as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $storedAddons[] = [
                    'name'        => (string) ($line['name'] ?? ''),
                    'packageName' => (string) ($line['packageName'] ?? ''),
                    'quantity'    => (int) ($line['quantity'] ?? 1),
                ];
            }
        }

        $priced = pdrPriceReservation(
            $pdo,
            $svcRow,
            (string) ($existing['selected_package'] ?? ''),
            (int) $guests,
            $storedAddons
        );

        if (!$priced['ok']) {
            // The booking's package/tier does not exist on the new service (or an
            // add-on is no longer priceable). Refuse rather than store a wrong
            // total or silently zero it.
            http_response_code(422);
            echo json_encode([
                'ok'      => false,
                'reason'  => 'PACKAGE_INCOMPATIBLE',
                'field'   => $priced['field'] ?? 'selected_package',
                'message' => 'This reservation\'s package/tier (or an add-on) is not valid for the new service. Keep the current service, or recreate the booking with a valid package.',
            ]);
            exit();
        }

        $newTotal = $priced['total'];
        $newAddonsJson = json_encode($priced['addons'], JSON_UNESCAPED_UNICODE);
    }

    if ($newTotal !== null) {
        $stmt = $pdo->prepare(
            "UPDATE reservations
                SET service_id = ?, main_service = ?, event_date = ?, event_time = ?, guests = ?, status = ?,
                    estimated_total = ?, additional_services = ?
              WHERE reservation_id = ?"
        );
        $stmt->execute([
            $serviceId, $serviceName, $date, $time, $guests, $status,
            $newTotal, $newAddonsJson, $reservationId,
        ]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE reservations
                SET service_id = ?, main_service = ?, event_date = ?, event_time = ?, guests = ?, status = ?
              WHERE reservation_id = ?"
        );
        $stmt->execute([$serviceId, $serviceName, $date, $time, $guests, $status, $reservationId]);
    }

    // T-14: email the customer when this edit changes the status into one of the
    // customer-facing states. Field-only edits stay silent.
    $emailMode = null;
    if ($status !== $existing['status']) {
        require_once __DIR__ . '/../mail/status_template.php';
        $emailResult = pdrDispatchStatusEmail($pdo, $reservationId, $status);
        $emailMode = $emailResult['mode'] ?? 'none';
    }

    pdrAudit($pdo, 'reservation.update', 'reservation', $reservationId, [
        'from_status' => $existing['status'],
        'to_status' => $status,
        'service' => $serviceName,
        'date' => $date,
        'time' => $time,
        'guests' => $guests,
        'repriced' => $newTotal !== null,
        'estimated_total' => $newTotal,
    ]);

    echo json_encode([
        'ok' => true,
        'reservationId' => $reservationId,
        'service' => $serviceName,
        'date' => $date,
        'time' => $time,
        'guests' => $guests,
        'status' => $status,
        'changed' => $status !== $existing['status'],
        'repriced' => $newTotal !== null,
        'estimatedTotal' => $newTotal,
        'email' => $emailMode,
    ]);
} catch (Throwable $e) {
    error_log('Reservation update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
