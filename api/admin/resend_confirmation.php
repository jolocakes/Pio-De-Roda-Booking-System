<?php
/**
 * API: Admin resend the booking confirmation email (T-41).
 *
 * The plaintext Booking PIN is never stored, only its hash. A resend therefore
 * ISSUES A NEW PIN (invalidating the old one) and emails it. The admin UI warns
 * about that before calling this endpoint.
 *
 *   POST { reservationId: "PDR-..." }
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/audit.php';
require_once __DIR__ . '/../../config/rbac.php';

pdrSessionStart();

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

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

$reservationId = trim((string) ($data['reservationId'] ?? ''));
if ($reservationId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'reservationId']);
    exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ? LIMIT 1");
    $stmt->execute([$reservationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    $email = trim((string) ($row['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'NO_EMAIL']);
        exit();
    }

    // Re-issue the PIN. random_int is cryptographically secure.
    $bookingPin = strval(random_int(100000, 999999));
    $pinHash = password_hash($bookingPin, PASSWORD_DEFAULT);

    $upd = $pdo->prepare("UPDATE reservations SET booking_pin_hash = ? WHERE reservation_id = ?");
    $upd->execute([$pinHash, $reservationId]);

    // A re-issued PIN should not stay locked out from earlier failed attempts.
    try {
        $pdo->prepare("DELETE FROM lookup_attempts WHERE reservation_id = ?")->execute([$reservationId]);
    } catch (Throwable $ignore) {
        // lookup_attempts may not exist on an older install; not fatal.
    }

    require_once __DIR__ . '/../mail/mailer.php';
    require_once __DIR__ . '/../mail/confirmation_template.php';

    $tpl = pdrConfirmEmail([
        'reservationId'   => $row['reservation_id'],
        'bookingPin'      => $bookingPin,
        'customerName'    => $row['customer_name'],
        'mainService'     => $row['main_service'],
        'selectedPackage' => $row['selected_package'],
        'eventDate'       => $row['event_date'],
        'eventTime'       => $row['event_time'],
        'eventType'       => $row['event_type'],
        'guests'          => $row['guests'],
        'address'         => $row['address'] ?? '',
        'estimatedTotal'  => $row['estimated_total'],
    ]);

    $result = pdrSendMail($email, $tpl['subject'], $tpl['html'], $tpl['text']);

    pdrAudit($pdo, 'reservation.resend_confirmation', 'reservation', $reservationId, [
        'email_mode' => $result['mode'] ?? 'none',
        'mail_ok'    => !empty($result['ok']),
        'pin_reissued' => true,
    ]);

    echo json_encode([
        'ok' => true,
        'reservationId' => $reservationId,
        'emailMode' => $result['mode'] ?? 'none',
        'mailOk' => !empty($result['ok']),
        'pinReissued' => true,
    ]);
} catch (Throwable $e) {
    error_log('Resend confirmation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
