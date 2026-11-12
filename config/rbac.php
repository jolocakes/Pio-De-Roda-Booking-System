<?php
/**
 * T-36: role-based access control.
 *
 * Three roles, from most to least privileged:
 *   admin  — everything, including catalog + staff accounts + audit log
 *   staff  — daily operations: reservations, events, calendar/blackouts,
 *            payments, reviews, reports
 *   viewer — read-only: can open the pages but cannot change anything
 *
 * The database stores a free-form role string. Normalisation means the existing
 * "Administrator" row keeps working, and "Admin"/"Staff"/"Viewer" (any casing)
 * are accepted.
 */

if (!function_exists('pdrRoleNormalize')) {
    function pdrRoleNormalize(?string $role): string
    {
        $value = strtolower(trim((string) $role));
        if (in_array($value, ['admin', 'administrator', 'superadmin', 'super admin'], true)) {
            return 'admin';
        }
        if (in_array($value, ['staff', 'employee', 'member'], true)) {
            return 'staff';
        }
        // Unknown or empty roles get the LOWEST privilege, never admin.
        return 'viewer';
    }
}

if (!function_exists('pdrCurrentRole')) {
    function pdrCurrentRole(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return pdrRoleNormalize($_SESSION['admin_role'] ?? null);
    }
}

if (!function_exists('pdrRoleLabel')) {
    function pdrRoleLabel(?string $role = null): string
    {
        $role = $role === null ? pdrCurrentRole() : pdrRoleNormalize($role);
        return ['admin' => 'Administrator', 'staff' => 'Staff', 'viewer' => 'Viewer'][$role] ?? 'Viewer';
    }
}

/**
 * Capability map. Add a capability here once and reference it everywhere.
 */
if (!function_exists('pdrCan')) {
    function pdrCan(string $capability, ?string $role = null): bool
    {
        $role = $role === null ? pdrCurrentRole() : pdrRoleNormalize($role);

        $matrix = [
            'view'               => ['admin', 'staff', 'viewer'],
            'export_reports'     => ['admin', 'staff', 'viewer'],
            'manage_reservations'=> ['admin', 'staff'],
            'manage_payments'    => ['admin', 'staff'],
            'manage_reviews'     => ['admin', 'staff'],
            'manage_blackouts'   => ['admin', 'staff'],
            'manage_catalog'     => ['admin'],
            'manage_staff'       => ['admin'],
            'view_audit'         => ['admin'],
        ];

        return in_array($role, $matrix[$capability] ?? [], true);
    }
}

/**
 * Guard for HTML pages: send the user back to the dashboard when the role is
 * not allowed. Never reveals whether the page exists.
 */
if (!function_exists('pdrRequirePageCapability')) {
    function pdrRequirePageCapability(string $capability): void
    {
        if (!pdrCan($capability)) {
            header('Location: dashboard.php?denied=1');
            exit();
        }
    }
}

/**
 * Guard for JSON APIs.
 */
if (!function_exists('pdrRequireApiCapability')) {
    function pdrRequireApiCapability(string $capability): void
    {
        if (!pdrCan($capability)) {
            http_response_code(403);
            header('Content-Type: application/json');
            header('Cache-Control: no-store');
            echo json_encode(['ok' => false, 'reason' => 'FORBIDDEN']);
            exit();
        }
    }
}
