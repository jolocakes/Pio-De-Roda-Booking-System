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
    $checkStmt = $pdo->prepare("SELECT id FROM reservations WHERE reservation_id = ? LIMIT 1");
    $checkStmt->execute([$reservationId]);

    if (!$checkStmt->fetch()) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    $stmt = $pdo->prepare(
        "UPDATE reservations
            SET main_service = ?, event_date = ?, event_time = ?, guests = ?, status = ?
          WHERE reservation_id = ?"
    );
    $stmt->execute([$service, $date, $time, $guests, $status, $reservationId]);

    echo json_encode([
        'ok' => true,
        'reservationId' => $reservationId,
        'service' => $service,
        'date' => $date,
        'time' => $time,
        'guests' => $guests,
        'status' => $status,
    ]);
} catch (Throwable $e) {
    error_log('Reservation update error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
