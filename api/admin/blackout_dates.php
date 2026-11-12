<?php
/**
 * API: Admin calendar blackout dates (T-24)
 *
 *   GET    -> list of blackout dates (future + today by default)
 *   POST   -> add/update a blackout  { date: "YYYY-MM-DD", reason?: string }
 *   DELETE -> remove a blackout      { date: "YYYY-MM-DD" }
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

pdrRequireApiCapability($method === 'GET' ? 'view' : 'manage_blackouts');

try {
    if ($method === 'GET') {
        $stmt = $pdo->query(
            "SELECT blackout_date, reason, created_at
               FROM blackout_dates
              ORDER BY blackout_date ASC"
        );
        echo json_encode(['ok' => true, 'blackouts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit();
    }

    if (!in_array($method, ['POST', 'DELETE'], true)) {
        http_response_code(405);
        header('Allow: GET, POST, DELETE');
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

    $date = trim((string) ($data['date'] ?? ''));
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'date']);
        exit();
    }

    if ($method === 'DELETE') {
        $stmt = $pdo->prepare("DELETE FROM blackout_dates WHERE blackout_date = ?");
        $stmt->execute([$date]);
        pdrAudit($pdo, 'blackout.remove', 'blackout', $date, []);
        echo json_encode(['ok' => true, 'date' => $date, 'removed' => $stmt->rowCount() > 0]);
        exit();
    }

    $reason = trim((string) ($data['reason'] ?? ''));
    if (mb_strlen($reason) > 255) {
        $reason = mb_substr($reason, 0, 255);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO blackout_dates (blackout_date, reason, created_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason)"
    );
    $stmt->execute([$date, $reason === '' ? null : $reason, (int) ($_SESSION['admin_id'] ?? 0) ?: null]);

    pdrAudit($pdo, 'blackout.set', 'blackout', $date, ['reason' => $reason]);
    echo json_encode(['ok' => true, 'date' => $date, 'reason' => $reason]);
} catch (Throwable $e) {
    error_log('Blackout API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
