<?php
/**
 * API: Admin customer directory (T-20).
 *
 *   GET  ?q=&page=&per_page=       list active customers
 *   GET  ?id=123                   one customer + booking history
 *   POST { action: "create"|"update"|"archive"|"restore", ... }
 *
 * The directory is kept in sync by api/submit_reservation.php (upsert on every
 * booking) and backfilled by database/migrations/010_customers_archive.sql.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/audit.php';
require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/../../config/rbac.php';

pdrSessionStart();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    pdrRequireApiCapability('view');

    try {
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($id > 0) {
            $stmt = $pdo->prepare(
                "SELECT c.*,
                        COUNT(r.id) AS bookings,
                        COALESCE(SUM(r.estimated_total), 0) AS lifetime_value,
                        MAX(r.event_date) AS last_event_date
                   FROM customers c
                   LEFT JOIN reservations r
                          ON r.email = c.email AND r.archived_at IS NULL
                  WHERE c.id = ?
                  GROUP BY c.id
                  LIMIT 1"
            );
            $stmt->execute([$id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$customer) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
                exit();
            }

            $historyStmt = $pdo->prepare(
                "SELECT reservation_id, main_service, selected_package, event_date,
                        event_time, guests, estimated_total, amount_paid, status,
                        submitted_at
                   FROM reservations
                  WHERE email = ? AND archived_at IS NULL
                  ORDER BY event_date DESC, id DESC"
            );
            $historyStmt->execute([$customer['email']]);

            echo json_encode([
                'ok' => true,
                'customer' => $customer,
                'history' => $historyStmt->fetchAll(PDO::FETCH_ASSOC),
            ]);
            exit();
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(200, (int) ($_GET['per_page'] ?? 25)));
        $archived = ((int) ($_GET['archived'] ?? 0)) === 1;

        $where = [$archived ? 'c.archived_at IS NOT NULL' : 'c.archived_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $where[] = '(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT c.*,
                    COUNT(r.id) AS bookings,
                    COALESCE(SUM(r.estimated_total), 0) AS lifetime_value,
                    MAX(r.event_date) AS last_event_date,
                    MAX(r.submitted_at) AS last_booking_at
               FROM customers c
               LEFT JOIN reservations r
                      ON r.email = c.email AND r.archived_at IS NULL
               $whereSql
              GROUP BY c.id
              ORDER BY c.full_name ASC
              LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);

        echo json_encode([
            'ok' => true,
            'customers' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
        exit();
    } catch (Throwable $e) {
        error_log('Customer directory API error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
        exit();
    }
}

if (!in_array($method, ['POST', 'PATCH', 'DELETE'], true)) {
    http_response_code(405);
    header('Allow: GET, POST, PATCH, DELETE');
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

$action = trim((string) ($data['action'] ?? ($method === 'DELETE' ? 'archive' : '')));
$allowedActions = ['create', 'update', 'archive', 'restore'];
if (!in_array($action, $allowedActions, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'action']);
    exit();
}

if ($action === 'create' || $action === 'update') {
    pdrRequireApiCapability('manage_customers');
} else {
    pdrRequireApiCapability('manage_archive');
}

try {
    if ($action === 'archive' || $action === 'restore') {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'id']);
            exit();
        }

        if ($action === 'restore') {
            $stmt = $pdo->prepare("UPDATE customers SET archived_at = NULL, archive_reason = NULL WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            $reason = trim((string) ($data['reason'] ?? 'Archived by staff'));
            $stmt = $pdo->prepare("UPDATE customers SET archived_at = NOW(), archive_reason = ? WHERE id = ?");
            $stmt->execute([mb_substr($reason, 0, 255), $id]);
        }

        pdrAudit($pdo, 'customer.' . $action, 'customer', (string) $id);
        echo json_encode(['ok' => true, 'id' => $id, 'action' => $action]);
        exit();
    }

    $id = (int) ($data['id'] ?? 0);
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $email = trim((string) ($data['email'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    $address = trim((string) ($data['address'] ?? ''));
    $status = trim((string) ($data['status'] ?? 'Active'));

    if ($fullName === '' || mb_strlen($fullName) > 100) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'full_name']);
        exit();
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'email']);
        exit();
    }
    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $status = 'Active';
    }

    if ($action === 'create') {
        $stmt = $pdo->prepare(
            "INSERT INTO customers (email, full_name, phone, address, status)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $email,
            mb_substr($fullName, 0, 100),
            mb_substr($phone, 0, 20),
            mb_substr($address, 0, 255),
            $status,
        ]);
        $id = (int) $pdo->lastInsertId();
        pdrAudit($pdo, 'customer.create', 'customer', (string) $id);
        http_response_code(201);
        echo json_encode(['ok' => true, 'id' => $id]);
        exit();
    }

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'id']);
        exit();
    }

    $stmt = $pdo->prepare(
        "UPDATE customers
            SET full_name = ?, email = ?, phone = ?, address = ?, status = ?
          WHERE id = ?"
    );
    $stmt->execute([
        mb_substr($fullName, 0, 100),
        $email,
        mb_substr($phone, 0, 20),
        mb_substr($address, 0, 255),
        $status,
        $id,
    ]);
    pdrAudit($pdo, 'customer.update', 'customer', (string) $id);
    echo json_encode(['ok' => true, 'id' => $id]);
} catch (PDOException $e) {
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'reason' => 'EMAIL_EXISTS']);
        exit();
    }
    error_log('Customer directory write error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
} catch (Throwable $e) {
    error_log('Customer directory API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
