<?php
/**
 * API: read the administrative audit trail (T-35).
 *
 *   GET ?q=&action=&actor=&from=&to=&page=&per_page=
 *
 * Admin-only. The log is append-only: this endpoint never writes or deletes.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/rbac.php';

pdrSessionStart();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}

pdrRequireApiCapability('view_audit');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);
    exit();
}

try {
    $q = trim((string) ($_GET['q'] ?? ''));
    $action = trim((string) ($_GET['action'] ?? ''));
    $actor = trim((string) ($_GET['actor'] ?? ''));
    $from = trim((string) ($_GET['from'] ?? ''));
    $to = trim((string) ($_GET['to'] ?? ''));

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $from = '';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $to = '';
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(200, (int) ($_GET['per_page'] ?? 50)));

    $where = '1=1';
    $params = [];

    if ($q !== '') {
        $where .= ' AND (actor_username LIKE ? OR action LIKE ? OR target_type LIKE ?'
            . ' OR target_id LIKE ? OR details LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($action !== '') {
        $where .= ' AND action = ?';
        $params[] = $action;
    }
    if ($actor !== '') {
        $where .= ' AND actor_username = ?';
        $params[] = $actor;
    }
    if ($from !== '') {
        $where .= ' AND created_at >= ?';
        $params[] = $from . ' 00:00:00';
    }
    if ($to !== '') {
        $where .= ' AND created_at <= ?';
        $params[] = $to . ' 23:59:59';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE $where");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT id, actor_id, actor_username, action, target_type, target_id,
                details, ip_hash, user_agent, created_at
           FROM audit_logs
          WHERE $where
          ORDER BY created_at DESC, id DESC
          LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['actor_id'] = $row['actor_id'] === null ? null : (int) $row['actor_id'];
        if ($row['details'] !== null && $row['details'] !== '') {
            $decoded = json_decode((string) $row['details'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['details'] = $decoded;
            }
        }
    }
    unset($row);

    // Filter options are independent of the active filters so the dropdowns
    // do not collapse to the one value the administrator just chose.
    $actions = $pdo->query(
        "SELECT DISTINCT action FROM audit_logs
          WHERE action <> ''
          ORDER BY action ASC"
    )->fetchAll(PDO::FETCH_COLUMN);

    $actors = $pdo->query(
        "SELECT DISTINCT actor_username FROM audit_logs
          WHERE actor_username IS NOT NULL AND actor_username <> ''
          ORDER BY actor_username ASC"
    )->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'ok' => true,
        'logs' => $rows,
        'meta' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ],
        'filters' => [
            'actions' => $actions,
            'actors' => $actors,
        ],
    ]);
} catch (Throwable $e) {
    error_log('Audit list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
