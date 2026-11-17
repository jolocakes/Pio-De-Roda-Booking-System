<?php
/**
 * Shared CSRF helpers for admin pages and state-changing API endpoints.
 */

function pdrSessionStart(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // T-58: harden the session cookie. PHP's defaults (and the php.ini in this
    // XAMPP install) leave HttpOnly and SameSite unset, so the session ID is
    // readable by JavaScript and sent on cross-site navigations.
    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_start();
}

function pdrCsrfToken(): string
{
    pdrSessionStart();
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function pdrCsrfValid(?string $token): bool
{
    pdrSessionStart();
    $expected = $_SESSION['csrf_token'] ?? '';
    return is_string($expected)
        && $expected !== ''
        && is_string($token)
        && $token !== ''
        && hash_equals($expected, $token);
}

/** Token from the X-CSRF-Token header, or the POST field as a fallback. */
function pdrRequestCsrfToken(): string
{
    $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (is_string($header) && $header !== '') {
        return $header;
    }
    $post = $_POST['csrf_token'] ?? '';
    return is_string($post) ? $post : '';
}

/** Reject a state-changing request with a JSON 403 when the token is invalid. */
function pdrRequireCsrfJson(): void
{
    if (!pdrCsrfValid(pdrRequestCsrfToken())) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'reason' => 'INVALID_CSRF_TOKEN']);
        exit();
    }
}

/** Hidden input for HTML forms. */
function pdrCsrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(pdrCsrfToken(), ENT_QUOTES, 'UTF-8')
        . '">';
}
