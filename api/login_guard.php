<?php
/**
 * T-17: admin login throttling and account lockout.
 *
 * Policy (rolling 15-minute window, per IP and per username):
 *   - 5 failed attempts from one IP          -> IP temporarily locked.
 *   - 10 failed attempts against one account -> account temporarily locked.
 *   - Progressive delay before password_verify() from the 3rd failure on
 *     (1s, 2s, 4s, then 8s max) so scripted guessing gets expensive.
 *
 * A successful login resets the effective counters because only failures that
 * happened AFTER the latest success are counted. The table doubles as an audit
 * trail; rows older than 30 days are pruned opportunistically.
 *
 * Fail-open: if the table is missing (migration not run), login still works —
 * a logging outage must not lock the administrator out of their own site.
 */

require_once __DIR__ . '/../config/env.php';

if (!defined('PDR_LOGIN_WINDOW_SECONDS')) {
    define('PDR_LOGIN_WINDOW_SECONDS', 15 * 60);
    define('PDR_LOGIN_IP_MAX_FAILURES', 5);
    define('PDR_LOGIN_ACCOUNT_MAX_FAILURES', 10);
}

function pdrLoginIpHash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $secret = (string) pdrEnv('PDR_LOOKUP_SECRET', 'pdr-login-guard-local');
    return hash('sha256', $secret . '|admin-login|' . $ip);
}

/**
 * @return array{allowed:bool,retryAfter:int,delay:int,ipFailures:int,accountFailures:int}
 */
function pdrLoginGuardCheck(PDO $pdo, string $username): array
{
    $result = [
        'allowed' => true,
        'retryAfter' => 0,
        'delay' => 0,
        'ipFailures' => 0,
        'accountFailures' => 0,
    ];

    try {
        $ipHash = pdrLoginIpHash();
        $username = mb_substr($username, 0, 100);

        // Failures after the most recent success in this scope still count.
        $sql = "SELECT
                    COUNT(*) AS failures,
                    UNIX_TIMESTAMP(MAX(attempted_at)) AS last_failure
                  FROM login_attempts
                 WHERE %s = ?
                   AND success = 0
                   AND attempted_at >= NOW() - INTERVAL " . PDR_LOGIN_WINDOW_SECONDS . " SECOND
                   AND attempted_at > COALESCE((
                        SELECT MAX(attempted_at) FROM login_attempts
                         WHERE %s = ? AND success = 1
                   ), '1970-01-01 00:00:00')";
        // NOTE: %s is a trusted column name chosen below, never user input.
        $ipStmt = $pdo->prepare(sprintf($sql, 'ip_hash', 'ip_hash'));
        $ipStmt->execute([$ipHash, $ipHash]);
        $ipRow = $ipStmt->fetch(PDO::FETCH_ASSOC) ?: ['failures' => 0, 'last_failure' => null];

        $userStmt = $pdo->prepare(sprintf($sql, 'username', 'username'));
        $userStmt->execute([$username, $username]);
        $userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['failures' => 0, 'last_failure' => null];

        $ipFailures = (int) ($ipRow['failures'] ?? 0);
        $accountFailures = (int) ($userRow['failures'] ?? 0);
        $result['ipFailures'] = $ipFailures;
        $result['accountFailures'] = $accountFailures;

        $locked = $ipFailures >= PDR_LOGIN_IP_MAX_FAILURES
            || $accountFailures >= PDR_LOGIN_ACCOUNT_MAX_FAILURES;

        if ($locked) {
            $lastFailure = max(
                (int) ($ipRow['last_failure'] ?? 0),
                (int) ($userRow['last_failure'] ?? 0)
            );
            $result['allowed'] = false;
            $result['retryAfter'] = max(1, PDR_LOGIN_WINDOW_SECONDS - (time() - $lastFailure));
            return $result;
        }

        // Exponential backoff: 0, 0, 0, 1s, 2s, 4s, 8s...
        $count = max($ipFailures, $accountFailures);
        if ($count >= 3) {
            $result['delay'] = min(8, 1 << ($count - 3));
        }
    } catch (Throwable $e) {
        error_log('Login guard check failed (failing open): ' . $e->getMessage());
    }

    return $result;
}

/** Record one attempt (success or failure) and prune old audit rows occasionally. */
function pdrLoginGuardRecord(PDO $pdo, string $username, bool $success): void
{
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO login_attempts (ip_hash, username, success, attempted_at)
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([pdrLoginIpHash(), mb_substr($username, 0, 100), $success ? 1 : 0]);

        if (random_int(1, 20) === 1) {
            pdrLoginGuardCleanup($pdo);
        }
    } catch (Throwable $e) {
        error_log('Login guard record failed: ' . $e->getMessage());
    }
}

/** Remove audit rows older than 30 days. */
function pdrLoginGuardCleanup(PDO $pdo): void
{
    try {
        $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 30 DAY");
    } catch (Throwable $e) {
        // Best effort only.
    }
}
