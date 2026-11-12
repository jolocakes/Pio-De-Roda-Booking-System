<?php
/**
 * API: Admin review moderation (T-33).
 *
 *   GET  -> list reviews (default: pending first)
 *   POST { id, action: "approve"|"hide"|"pending", note? }
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

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

pdrRequireApiCapability($method === 'GET' ? 'view' : 'manage_reviews');

try {
    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT id, reservation_id, customer_name, rating, feedback_text,
                    status, admin_note, moderated_at, created_at
               FROM reviews
              ORDER BY FIELD(status, 'Pending', 'Approved', 'Hidden'), created_at DESC"
        );
        echo json_encode(['ok' => true, 'reviews' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    if ($method !== 'POST') {
        http_response_code(405);
        header('Allow: GET, POST');
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

    $id = (int) ($data['id'] ?? 0);
    $action = trim((string) ($data['action'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));

    $map = ['approve' => 'Approved', 'hide' => 'Hidden', 'pending' => 'Pending'];
    if ($id <= 0 || !isset($map[$action])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID']);
        exit();
    }

    $status = $map[$action];
    $note = $note === '' ? null : mb_substr($note, 0, 255);

    $stmt = $pdo->prepare(
        "UPDATE reviews
            SET status = ?, admin_note = ?, moderated_by = ?, moderated_at = NOW()
          WHERE id = ?"
    );
    $stmt->execute([$status, $note, (int) $_SESSION['admin_id'], $id]);

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    pdrAudit($pdo, 'review.moderate', 'review', (string) $id, [
        'status' => $status,
        'note' => $note,
    ]);

    echo json_encode(['ok' => true, 'id' => $id, 'status' => $status]);
} catch (Throwable $e) {
    error_log('Review moderation error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
