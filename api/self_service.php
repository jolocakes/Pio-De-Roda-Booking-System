<?php
/**
 * API: Customer self-service changes (T-37).
 *
 *   POST { reservationId, bookingPin, action: "cancel" }
 *   POST { reservationId, bookingPin, action: "reschedule", newDate, newTime? }
 *
 * Guarded by the same Reservation ID + Booking PIN pair as the status lookup,
 * with the same brute-force throttling. Policy (customer/privacy + legal page):
 *   - cancel      : at least PDR_CANCEL_MIN_DAYS (7) days before the event
 *   - reschedule  : at least PDR_RESCHEDULE_MIN_DAYS (3) days before, at most
 *                   PDR_MAX_RESCHEDULES (1) times per booking
 * A customer can only change a booking that is still Pending or Confirmed.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/audit.php';
require_once __DIR__ . '/lookup_guard.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/capacity.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);
    exit();
}

// A second, cheap per-IP coupon on top of the per-reservation guard.
$limit = pdrRateLimit('self_service', 10, 900);
if (!$limit['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . (int) $limit['retryAfter']);
    echo json_encode([
        'ok' => false,
        'reason' => 'TOO_MANY_ATTEMPTS',
        'retryAfterSeconds' => (int) $limit['retryAfter'],
    ]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

$reservationId = pdrLookupNormalizeId((string) ($data['reservationId'] ?? ''));
$bookingPin = trim((string) ($data['bookingPin'] ?? ''));
$action = trim((string) ($data['action'] ?? ''));

if ($reservationId === '' || $bookingPin === '') {
    http_response_code(404);
    echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
    exit();
}
if (!in_array($action, ['cancel', 'reschedule'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'action']);
    exit();
}

$lock = pdrLookupGuard($pdo, $reservationId);
if ($lock !== null) {
    pdrLookupReject($lock);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ? LIMIT 1");
    $stmt->execute([$reservationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Wrong ID and wrong PIN look identical on purpose.
    if (!$row || !password_verify($bookingPin, (string) $row['booking_pin_hash'])) {
        pdrLookupRecord($pdo, $reservationId, false);
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    pdrLookupRecord($pdo, $reservationId, true);

    $status = (string) $row['status'];
    if (!in_array($status, ['Pending', 'Confirmed'], true)) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'NOT_CHANGEABLE', 'status' => $status]);
        exit();
    }

    $daysUntil = pdrDaysUntilDate((string) $row['event_date']);
    $rescheduleCount = (int) ($row['reschedule_count'] ?? 0);

    if ($action === 'cancel') {
        if ($daysUntil < PDR_CANCEL_MIN_DAYS) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'reason' => 'TOO_LATE_TO_CANCEL',
                'daysUntilEvent' => $daysUntil,
                'cancelMinDays' => PDR_CANCEL_MIN_DAYS,
            ]);
            exit();
        }

        $upd = $pdo->prepare("UPDATE reservations SET status = 'Cancelled' WHERE reservation_id = ?");
        $upd->execute([$reservationId]);

        pdrAudit($pdo, 'reservation.customer_cancel', 'reservation', $reservationId, [
            'from_status' => $status,
            'days_until_event' => $daysUntil,
        ]);

        echo json_encode([
            'ok' => true,
            'action' => 'cancel',
            'status' => 'Cancelled',
        ]);
        exit();
    }

    // ---------- reschedule ----------
    if ($daysUntil < PDR_RESCHEDULE_MIN_DAYS) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'reason' => 'TOO_LATE_TO_RESCHEDULE',
            'daysUntilEvent' => $daysUntil,
            'rescheduleMinDays' => PDR_RESCHEDULE_MIN_DAYS,
        ]);
        exit();
    }
    if ($rescheduleCount >= PDR_MAX_RESCHEDULES) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'reason' => 'RESCHEDULE_LIMIT',
            'rescheduleCount' => $rescheduleCount,
            'maxReschedules' => PDR_MAX_RESCHEDULES,
        ]);
        exit();
    }

    $newDate = trim((string) ($data['newDate'] ?? ''));
    $newTime = trim((string) ($data['newTime'] ?? (string) $row['event_time']));

    $windowError = pdrBookingWindowError($newDate);
    if ($windowError !== null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => $windowError, 'field' => 'newDate']);
        exit();
    }
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $newTime)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'newTime']);
        exit();
    }
    if ($newDate === (string) $row['event_date'] && $newTime === (string) $row['event_time']) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'NO_CHANGE']);
        exit();
    }

    // Ignore this reservation itself: moving a booking to its own date/time is
    // not a conflict.
    $dateCheck = pdrDateTaken($pdo, $newDate, $reservationId);
    if ($dateCheck['taken']) {
        http_response_code(409);
        echo json_encode([
            'ok' => false,
            'reason' => $dateCheck['reason'] === 'BLACKOUT' ? 'DATE_BLACKOUT' : 'DATE_TAKEN',
            'dateReason' => $dateCheck['reason'],
            'blackoutReason' => $dateCheck['blackoutReason'] ?? null,
        ]);
        exit();
    }

    $takenTimes = pdrTakenTimes($pdo, $newDate, (string) $row['main_service'], $reservationId);
    if (in_array($newTime, $takenTimes, true)) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'TIME_TAKEN', 'field' => 'newTime']);
        exit();
    }

    $upd = $pdo->prepare(
        "UPDATE reservations
            SET event_date = ?, event_time = ?, reschedule_count = reschedule_count + 1
          WHERE reservation_id = ?"
    );
    $upd->execute([$newDate, $newTime, $reservationId]);

    pdrAudit($pdo, 'reservation.customer_reschedule', 'reservation', $reservationId, [
        'from_date' => $row['event_date'],
        'from_time' => $row['event_time'],
        'to_date' => $newDate,
        'to_time' => $newTime,
        'reschedule_count' => $rescheduleCount + 1,
    ]);

    echo json_encode([
        'ok' => true,
        'action' => 'reschedule',
        'eventDate' => $newDate,
        'eventTime' => $newTime,
        'rescheduleCount' => $rescheduleCount + 1,
        'maxReschedules' => PDR_MAX_RESCHEDULES,
    ]);
} catch (Throwable $e) {
    error_log('Self service error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
