<?php
/**
 * Admin Authentication Guard
 * Pio De Roda Hospitality Reservation and Event Booking System
 *
 * HTML pages get a redirect to the login page. API/JSON clients (anything under
 * /api/, or a request that explicitly asks for JSON) get a structured 401 so a
 * fetch() call can react instead of receiving an HTML login page.
 */
require_once __DIR__ . '/../config/csrf.php';
require_once __DIR__ . '/../config/assets.php';

pdrSessionStart();

if (!function_exists('pdrAuthWantsJson')) {
    function pdrAuthWantsJson(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri !== '' && stripos($uri, '/api/') !== false) {
            return true;
        }
        if (stripos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false) {
            return true;
        }
        if (strcasecmp((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0) {
            return true;
        }
        return false;
    }
}

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    if (pdrAuthWantsJson()) {
        http_response_code(401);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
        exit();
    }

    header('Location: index.php');
    exit();
}

// Pages including this guard can render pdrCsrfField() for POST forms.
pdrCsrfToken();

// T-36: role helpers (pdrCan / pdrRequirePageCapability) are used by the
// individual pages to gate privileged areas.
require_once __DIR__ . '/../config/rbac.php';
