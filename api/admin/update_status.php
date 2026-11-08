<?php
/**
 * API: Admin Update Reservation Status
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';

pdrSessionStart();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['PATCH', 'POST'], true)) {
    http_response_code(405);
    header('Allow: PATCH, POST');
    echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);
    exit();
}

pdrRequireCsrfJson();

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

$reservationId = trim((string) ($data['reservationId'] ?? ''));
$newStatus = trim((string) ($data['status'] ?? ''));

$allowedStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];
if ($reservationId === '' || !in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

try {
    $checkStmt = $pdo->prepare("SELECT status FROM reservations WHERE reservation_id = ?");
    $checkStmt->execute([$reservationId]);
    $current = $checkStmt->fetch();

    if (!$current) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    $changed = $current['status'] !== $newStatus;
    if ($changed) {
        $stmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");
        $stmt->execute([$newStatus, $reservationId]);
    }

    // T-14: tell the customer when their booking is Confirmed / Cancelled /
    // Completed. Best effort — pdrDispatchStatusEmail never throws, and the
    // status change above is already committed.
    $emailMode = null;
    if ($changed) {
        require_once __DIR__ . '/../mail/status_template.php';
        $emailResult = pdrDispatchStatusEmail($pdo, $reservationId, $newStatus);
        $emailMode = $emailResult['mode'] ?? 'none';
    }

    echo json_encode([
        'ok' => true,
        'reservationId' => $reservationId,
        'status' => $newStatus,
        'changed' => $changed,
        'email' => $emailMode,
    ]);
} catch (Exception $e) {
    error_log("Reservation status update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK']);
}
