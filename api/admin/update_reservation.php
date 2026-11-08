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
require_once __DIR__ . '/../capacity.php';

pdrSessionStart();

// JSON 401 instead of an HTML redirect so the admin page can show a clear message.
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}

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

$allowedStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];

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
    $checkStmt = $pdo->prepare("SELECT id, status FROM reservations WHERE reservation_id = ? LIMIT 1");
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
    $svcStmt = $pdo->prepare("SELECT id, name, max_cap FROM services WHERE name = ? AND is_active = 1 LIMIT 1");
    $svcStmt->execute([$service]);
    $svcRow = $svcStmt->fetch(PDO::FETCH_ASSOC);
    if (!$svcRow) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'service']);
        exit();
    }
    $serviceName = $svcRow['name'];
    $serviceId   = $svcRow['id'];

    // Guest cap: reject over-capacity edits, same limit the customer form uses.
    $maxCap = ($svcRow['max_cap'] !== null && (int) $svcRow['max_cap'] > 0) ? (int) $svcRow['max_cap'] : null;
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
        $dateCheck = pdrDateTaken($pdo, $date, $reservationId);
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

        $takenTimes = pdrTakenTimes($pdo, $date, $serviceName, $reservationId);
        if (in_array($time, $takenTimes, true)) {
            http_response_code(409);
            echo json_encode([
                'ok'     => false,
                'reason' => 'TIME_TAKEN',
                'time'   => $time,
                'service'=> $serviceName,
            ]);
            exit();
        }
    }

    $stmt = $pdo->prepare(
        "UPDATE reservations
            SET service_id = ?, main_service = ?, event_date = ?, event_time = ?, guests = ?, status = ?
          WHERE reservation_id = ?"
    );
    $stmt->execute([$serviceId, $serviceName, $date, $time, $guests, $status, $reservationId]);

    // T-14: email the customer when this edit changes the status into one of the
    // customer-facing states. Field-only edits stay silent.
    $emailMode = null;
    if ($status !== $existing['status']) {
        require_once __DIR__ . '/../mail/status_template.php';
        $emailResult = pdrDispatchStatusEmail($pdo, $reservationId, $status);
        $emailMode = $emailResult['mode'] ?? 'none';
    }

    echo json_encode([
        'ok' => true,
        'reservationId' => $reservationId,
        'service' => $serviceName,
        'date' => $date,
        'time' => $time,
        'guests' => $guests,
        'status' => $status,
        'changed' => $status !== $existing['status'],
        'email' => $emailMode,
    ]);
} catch (Throwable $e) {
    error_log('Reservation update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
