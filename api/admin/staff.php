<?php
/**
 * API: Admin staff accounts (T-36).
 *
 *   GET  -> list accounts (never the password hash)
 *   POST { action: "create", username, full_name, password, role }
 *   POST { action: "update", id, full_name, role, password? }
 *   POST { action: "delete", id }
 *
 * Admin-only. The last remaining admin cannot be deleted or demoted, so the
 * system can never lock itself out.
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

pdrRequireApiCapability('manage_staff');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $rows = $pdo->query(
            "SELECT id, username, full_name, role, created_at
               FROM admin_users
              ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['role_normalized'] = pdrRoleNormalize($row['role']);
            $row['role_label'] = pdrRoleLabel($row['role']);
        }
        unset($row);

        echo json_encode(['ok' => true, 'accounts' => $rows]);
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

    $action = trim((string) ($data['action'] ?? ''));
    $validRoles = ['admin', 'staff', 'viewer'];

    $countAdmins = (int) $pdo->query(
        "SELECT COUNT(*) FROM admin_users WHERE LOWER(role) IN ('admin', 'administrator')"
    )->fetchColumn();

    if ($action === 'create') {
        $username = trim((string) ($data['username'] ?? ''));
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $role = pdrRoleNormalize((string) ($data['role'] ?? 'viewer'));

        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'username']);
            exit();
        }
        if ($fullName === '' || mb_strlen($fullName) > 120) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'full_name']);
            exit();
        }
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'password']);
            exit();
        }
        if (!in_array($role, $validRoles, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'role']);
            exit();
        }

        $exists = $pdo->prepare("SELECT id FROM admin_users WHERE username = ? LIMIT 1");
        $exists->execute([$username]);
        if ($exists->fetchColumn()) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'reason' => 'DUPLICATE_USERNAME']);
            exit();
        }

        // Store a readable label; pdrRoleNormalize() accepts it on next login.
        $storedRole = ['admin' => 'Administrator', 'staff' => 'Staff', 'viewer' => 'Viewer'][$role];

        $stmt = $pdo->prepare(
            "INSERT INTO admin_users (username, password_hash, full_name, role)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $fullName,
            $storedRole,
        ]);
        $newId = (int) $pdo->lastInsertId();

        pdrAudit($pdo, 'staff.create', 'admin_user', (string) $newId, [
            'username' => $username,
            'role' => $role,
        ]);

        echo json_encode(['ok' => true, 'id' => $newId]);
        exit();
    }

    if ($action === 'update') {
        $id = (int) ($data['id'] ?? 0);
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $role = pdrRoleNormalize((string) ($data['role'] ?? 'viewer'));
        $password = (string) ($data['password'] ?? '');

        if ($id <= 0 || $fullName === '' || mb_strlen($fullName) > 120) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID']);
            exit();
        }
        if (!in_array($role, $validRoles, true)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'role']);
            exit();
        }
        if ($password !== '' && strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'password']);
            exit();
        }

        $target = $pdo->prepare("SELECT id, username, role FROM admin_users WHERE id = ? LIMIT 1");
        $target->execute([$id]);
        $account = $target->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
            exit();
        }

        $wasAdmin = pdrRoleNormalize($account['role']) === 'admin';
        if ($wasAdmin && $role !== 'admin' && $countAdmins <= 1) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'reason' => 'LAST_ADMIN']);
            exit();
        }

        $storedRole = ['admin' => 'Administrator', 'staff' => 'Staff', 'viewer' => 'Viewer'][$role];

        if ($password !== '') {
            $stmt = $pdo->prepare(
                "UPDATE admin_users SET full_name = ?, role = ?, password_hash = ? WHERE id = ?"
            );
            $stmt->execute([$fullName, $storedRole, password_hash($password, PASSWORD_DEFAULT), $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE admin_users SET full_name = ?, role = ? WHERE id = ?");
            $stmt->execute([$fullName, $storedRole, $id]);
        }

        pdrAudit($pdo, 'staff.update', 'admin_user', (string) $id, [
            'username' => $account['username'],
            'role' => $role,
            'password_changed' => $password !== '',
        ]);

        echo json_encode(['ok' => true, 'id' => $id]);
        exit();
    }

    if ($action === 'delete') {
        $id = (int) ($data['id'] ?? 0);
        $currentId = (int) ($_SESSION['admin_id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID']);
            exit();
        }
        if ($id === $currentId) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'reason' => 'CANNOT_DELETE_SELF']);
            exit();
        }

        $target = $pdo->prepare("SELECT username, role FROM admin_users WHERE id = ? LIMIT 1");
        $target->execute([$id]);
        $account = $target->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
            exit();
        }
        if (pdrRoleNormalize($account['role']) === 'admin' && $countAdmins <= 1) {
            http_response_code(409);
            echo json_encode(['ok' => false, 'reason' => 'LAST_ADMIN']);
            exit();
        }

        $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$id]);
        pdrAudit($pdo, 'staff.delete', 'admin_user', (string) $id, [
            'username' => $account['username'],
        ]);

        echo json_encode(['ok' => true, 'id' => $id]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'action']);
} catch (Throwable $e) {
    error_log('Staff API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
