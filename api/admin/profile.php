<?php
/**
 * API: change the signed-in account's own password.
 *
 *   POST { current_password, new_password, confirm_password }
 *
 * Available to every role: an account can always rotate its own secret.
 * The current password is verified, the new one must satisfy the shared
 * policy, and the session ID is regenerated afterwards so a pre-change
 * session identifier stops working.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/audit.php';
require_once __DIR__ . '/../../config/password_policy.php';
require_once __DIR__ . '/../rate_limit.php';

pdrSessionStart();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);
    exit();
}

pdrRequireCsrfJson();

// Throttle guessing of the CURRENT password from a hijacked session.
$limit = pdrRateLimit('password_change', 10, 900);
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

$current = (string) ($data['current_password'] ?? '');
$new = (string) ($data['new_password'] ?? '');
$confirm = (string) ($data['confirm_password'] ?? '');

if ($current === '' || $new === '' || $confirm === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

try {
    $id = (int) $_SESSION['admin_id'];
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM admin_users WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify the current secret before giving any other feedback, so the
    // endpoint is not an oracle for policy details without the old password.
    if (!$account || !password_verify($current, (string) $account['password_hash'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'WRONG_PASSWORD']);
        exit();
    }

    if ($new !== $confirm) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'MISMATCH']);
        exit();
    }

    $policyError = pdrPasswordError($new);
    if ($policyError !== null) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'WEAK_PASSWORD', 'detail' => $policyError]);
        exit();
    }

    if ($new === $current) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'SAME_PASSWORD']);
        exit();
    }

    $update = $pdo->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
    $update->execute([password_hash($new, PASSWORD_DEFAULT), $id]);

    pdrAudit($pdo, 'account.password_change', 'admin_user', (string) $id, [
        'username' => $account['username'],
    ]);

    // Rotate the session ID: the old identifier must not survive a secret change.
    session_regenerate_id(true);

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Password change error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
