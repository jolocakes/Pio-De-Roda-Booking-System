<?php
/**
 * Admin Logout — POST + CSRF only.
 * A state-changing action must never be triggered by a bare GET link.
 */
require_once __DIR__ . '/../config/csrf.php';

pdrSessionStart();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

if (!pdrCsrfValid(pdrRequestCsrfToken())) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: index.php");
exit();
