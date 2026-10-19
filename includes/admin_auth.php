<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params(['httponly'=>true, 'secure'=>!empty($_SERVER['HTTPS']), 'samesite'=>'Lax', 'path'=>'/']);
    session_start();
}
if (empty($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api.php')) json_response(['ok'=>false,'reason'=>'UNAUTHORIZED'], 401);
    header('Location: login.php'); exit;
}
try {
    $check = db()->prepare("SELECT id FROM admins WHERE id = ? AND active = 1 AND role = 'admin' LIMIT 1");
    $check->execute([$_SESSION['admin_id']]);
    if (!$check->fetchColumn()) { $_SESSION = []; session_destroy(); header('Location: login.php'); exit; }
} catch (Throwable $e) {
    if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api.php')) json_response(['ok'=>false,'reason'=>'NETWORK'], 503);
    http_response_code(503); exit('Administrator access is temporarily unavailable.');
}
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
function require_csrf(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) json_response(['ok'=>false,'reason'=>'CSRF'], 403);
}
