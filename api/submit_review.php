<?php
/**
 * API: Customer submits a review (T-33).
 *
 *   POST { reservationId, bookingPin, rating (1-5), feedback }
 *
 * A review is accepted only for a COMPLETED reservation, and only after the
 * Reservation ID + Booking PIN match. It starts as Pending: nothing is visible
 * publicly until an admin approves it.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/rate_limit.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);
    exit();
}

// Same coupon as booking lookup: reviews are public and PIN-guarded.
$limit = pdrRateLimit('review', 5, 900);
if (!$limit['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . (int) $limit['retryAfter']);
    echo json_encode(['ok' => false, 'reason' => 'TOO_MANY_ATTEMPTS', 'retryAfterSeconds' => (int) $limit['retryAfter']]);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

$reservationId = strtoupper(trim((string) ($data['reservationId'] ?? '')));
$bookingPin = trim((string) ($data['bookingPin'] ?? ''));
$rating = (int) ($data['rating'] ?? 0);
$feedback = trim((string) ($data['feedback'] ?? ''));

if ($reservationId === '' || $bookingPin === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
    exit();
}
if ($rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'rating']);
    exit();
}
if ($feedback === '' || mb_strlen($feedback) > 2000) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'feedback']);
    exit();
}

try {
    $stmt = $pdo->prepare(
        "SELECT reservation_id, booking_pin_hash, customer_name, status
           FROM reservations WHERE reservation_id = ? LIMIT 1"
    );
    $stmt->execute([$reservationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Wrong ID and wrong PIN look identical on purpose.
    if (!$row || !password_verify($bookingPin, (string) $row['booking_pin_hash'])) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    if ($row['status'] !== 'Completed') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'NOT_COMPLETED']);
        exit();
    }

    $existing = $pdo->prepare("SELECT id FROM reviews WHERE reservation_id = ? LIMIT 1");
    $existing->execute([$reservationId]);
    if ($existing->fetchColumn()) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'ALREADY_SUBMITTED']);
        exit();
    }

    $insert = $pdo->prepare(
        "INSERT INTO reviews (reservation_id, customer_name, rating, feedback_text, status)
         VALUES (?, ?, ?, ?, 'Pending')"
    );
    $insert->execute([
        $reservationId,
        mb_substr((string) $row['customer_name'], 0, 100),
        $rating,
        $feedback,
    ]);

    echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
} catch (Throwable $e) {
    // A duplicate-key race is still "already submitted".
    if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'ALREADY_SUBMITTED']);
        exit();
    }
    error_log('Review submit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
