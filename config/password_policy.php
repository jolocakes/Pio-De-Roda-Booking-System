<?php
/**
 * One password policy for every admin / staff account.
 *
 * The rule is intentionally simple and length-based. It applies whenever a
 * password is created or changed:
 *   - at least PDR_PASSWORD_MIN characters
 *   - at most  PDR_PASSWORD_MAX bytes (PHP's default bcrypt hash silently
 *     truncates anything past 72 bytes, so we reject it instead)
 *
 * Existing accounts are not forced to reset: an old short password still logs
 * in. The stricter rule takes effect the next time the password is changed.
 */

if (!defined('PDR_PASSWORD_MIN')) {
    define('PDR_PASSWORD_MIN', 12);
}
if (!defined('PDR_PASSWORD_MAX')) {
    define('PDR_PASSWORD_MAX', 72);
}

if (!function_exists('pdrPasswordError')) {
    /**
     * @return string|null A machine-readable reason, or null when acceptable.
     */
    function pdrPasswordError(string $password): ?string
    {
        $length = strlen($password);
        if ($length < PDR_PASSWORD_MIN) {
            return 'TOO_SHORT';
        }
        if ($length > PDR_PASSWORD_MAX) {
            return 'TOO_LONG';
        }
        return null;
    }
}

if (!function_exists('pdrPasswordPolicyText')) {
    function pdrPasswordPolicyText(): string
    {
        return 'Use at least ' . PDR_PASSWORD_MIN . ' characters (maximum ' . PDR_PASSWORD_MAX . ').';
    }
}
