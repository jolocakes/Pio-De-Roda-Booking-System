<?php
/**
 * API: Admin archive / restore / permanent delete (T-22).
 *
 *   GET  ?type=reservations|customers|reviews&q=&page=&per_page=
 *   POST { action: "archive"|"restore"|"delete", type, id, reason? }
 *
 * Soft-deleted rows keep all of their data; only archived_at is set. Permanent
 * delete is explicit and audited.
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

pdrRequireApiCapability('manage_archive');

$types = [
    'reservations' => [
        'table' => 'reservations',
        'id' => 'id',
        'select' => "id, reservation_id, customer_name, main_service, event_date,
                     status, archived_at, archive_reason",
        'search' => "reservation_id LIKE ? OR customer_name LIKE ? OR main_service LIKE ?",
        'label' => 'reservation',
    ],
    'customers' => [
        'table' => 'customers',
        'id' => 'id',
        'select' => "id, full_name, email, phone, status, archived_at, archive_reason",
        'search' => "full_name LIKE ? OR email LIKE ? OR phone LIKE ?",
        'label' => 'customer',
    ],
    'reviews' => [
        'table' => 'reviews',
        'id' => 'id',
        'select' => "id, customer_name, rating, feedback_text, status,
                     archived_at, archived",
        'search' => "customer_name LIKE ? OR feedback_text LIKE ?",
        'label' => 'review',
    ],
];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $type = trim((string) ($_GET['type'] ?? 'reservations'));
    if (!isset($types[$type])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'type']);
        exit();
    }
    $cfg = $types[$type];

    try {
        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($_GET['per_page'] ?? 50)));

        $where = $type === 'reviews'
            ? '(archived_at IS NOT NULL OR archived = 1)'
            : 'archived_at IS NOT NULL';
        $params = [];
        if ($q !== '') {
            $where .= ' AND (' . $cfg['search'] . ')';
            $like = '%' . $q . '%';
            // Every search clause has three placeholders except reviews (two).
            $params = $type === 'reviews' ? [$like, $like] : [$like, $like, $like];
        }

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$cfg['table']} WHERE $where");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT {$cfg['select']}
               FROM {$cfg['table']}
              WHERE $where
              ORDER BY archived_at DESC, {$cfg['id']} DESC
              LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        echo json_encode([
            'ok' => true,
            'type' => $type,
            'records' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
        exit();
    } catch (Throwable $e) {
        error_log('Archive list error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
        exit();
    }
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

$type = trim((string) ($data['type'] ?? ''));
$action = trim((string) ($data['action'] ?? ''));
$id = (int) ($data['id'] ?? 0);

if (!isset($types[$type]) || !in_array($action, ['archive', 'restore', 'delete'], true) || $id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

$cfg = $types[$type];

try {
    if ($action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM {$cfg['table']} WHERE {$cfg['id']} = ?");
        $stmt->execute([$id]);
    } elseif ($action === 'restore') {
        if ($type === 'reviews') {
            $stmt = $pdo->prepare("UPDATE reviews SET archived_at = NULL, archived = 0 WHERE id = ?");
        } else {
            $stmt = $pdo->prepare(
                "UPDATE {$cfg['table']} SET archived_at = NULL, archive_reason = NULL WHERE {$cfg['id']} = ?"
            );
        }
        $stmt->execute([$id]);
    } else {
        $reason = mb_substr(trim((string) ($data['reason'] ?? 'Archived by staff')), 0, 255);
        if ($type === 'reviews') {
            $stmt = $pdo->prepare("UPDATE reviews SET archived_at = NOW(), archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare(
                "UPDATE {$cfg['table']} SET archived_at = NOW(), archive_reason = ? WHERE {$cfg['id']} = ?"
            );
            $stmt->execute([$reason, $id]);
        }
    }

    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
        exit();
    }

    pdrAudit($pdo, 'archive.' . $action, $cfg['label'], (string) $id, ['type' => $type]);
    echo json_encode(['ok' => true, 'type' => $type, 'id' => $id, 'action' => $action]);
} catch (Throwable $e) {
    error_log('Archive action error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
