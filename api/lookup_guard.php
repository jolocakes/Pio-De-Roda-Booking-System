<?php
/**
 * Brute-force throttling for customer lookup / PIN verification endpoints.
 *
 * The lookup_attempts table stores hashed client IPs (not raw addresses) and
 * the attempted reservation ID. A client is locked out when either the IP or
 * the reservation ID accumulates too many failures inside the window.
 */

require_once __DIR__ . '/../config/env.php';

const PDR_LOOKUP_MAX_FAILS = 5;          // failures allowed per window
const PDR_LOOKUP_WINDOW_SECONDS = 900;   // 15 minutes
const PDR_LOOKUP_LOCK_SECONDS = 900;     // 15-minute lockout

function pdrLookupNormalizeId(string $reservationId): string
{
    return strtoupper(trim($reservationId));
}

function pdrLookupIpHash(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $secret = (string) pdrEnv('PDR_LOOKUP_SECRET', 'pdr-lookup-local-secret');
    return hash('sha256', $secret . '|' . $ip);
}

function pdrLookupTableReady(PDO $pdo): bool
{
    static $ready = null;
    if ($ready === null) {
        try {
            $pdo->query('SELECT 1 FROM lookup_attempts LIMIT 1');
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
        }
    }
    return $ready;
}

/** Seconds remaining on the lock, or 0 when not locked. */
function pdrLookupLockRemaining(PDO $pdo, string $column, string $value): int
{
    if ($value === '') {
        return 0;
    }
    // $column is always one of the two literal names below, never user input.
    if ($column !== 'ip_hash' && $column !== 'reservation_id') {
        return 0;
    }

    $sql = "SELECT COUNT(*) AS failures, UNIX_TIMESTAMP(MAX(attempted_at)) AS last_fail
              FROM lookup_attempts
             WHERE success = 0
               AND {$column} = ?
               AND attempted_at >= (NOW() - INTERVAL " . PDR_LOOKUP_WINDOW_SECONDS . " SECOND)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$value]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    if ((int) ($row['failures'] ?? 0) < PDR_LOOKUP_MAX_FAILS) {
        return 0;
    }

    $lastFail = (int) ($row['last_fail'] ?? time());
    $remaining = PDR_LOOKUP_LOCK_SECONDS - (time() - $lastFail);
    return max(1, min(PDR_LOOKUP_LOCK_SECONDS, $remaining));
}

/**
 * Returns null when the request may proceed, or an error payload when the
 * caller must be rejected. Fails closed if the throttling table is missing.
 */
function pdrLookupGuard(PDO $pdo, string $reservationId): ?array
{
    if (!pdrLookupTableReady($pdo)) {
        error_log('lookup_guard: lookup_attempts table is missing; run database/migrate_lookup_attempts.sql');
        return ['reason' => 'SECURITY_MIGRATION_REQUIRED', 'retryAfterSeconds' => 0];
    }

    try {
        $ipHash = pdrLookupIpHash();
        $id = pdrLookupNormalizeId($reservationId);
        $wait = max(
            pdrLookupLockRemaining($pdo, 'ip_hash', $ipHash),
            pdrLookupLockRemaining($pdo, 'reservation_id', $id)
        );
        if ($wait > 0) {
            return ['reason' => 'TOO_MANY_ATTEMPTS', 'retryAfterSeconds' => $wait];
        }
    } catch (Throwable $e) {
        error_log('lookup_guard check failed: ' . $e->getMessage());
        return ['reason' => 'SECURITY_MIGRATION_REQUIRED', 'retryAfterSeconds' => 0];
    }

    return null;
}

/** Send the standard JSON lockout / misconfiguration response and exit. */
function pdrLookupReject(array $lock): void
{
    $isLocked = ($lock['reason'] ?? '') === 'TOO_MANY_ATTEMPTS';
    http_response_code($isLocked ? 429 : 503);
    if ($isLocked && !empty($lock['retryAfterSeconds'])) {
        header('Retry-After: ' . (int) $lock['retryAfterSeconds']);
    }
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'reason' => $lock['reason'] ?? 'TOO_MANY_ATTEMPTS',
        'retryAfterSeconds' => (int) ($lock['retryAfterSeconds'] ?? 0),
    ]);
    exit();
}

/**
 * Record the result of one lookup. Successful lookups clear earlier failures
 * for the same IP and reservation ID; failures remain for the audit trail.
 */
function pdrLookupRecord(PDO $pdo, string $reservationId, bool $success): void
{
    if (!pdrLookupTableReady($pdo)) {
        return;
    }

    $ipHash = pdrLookupIpHash();
    $id = substr(pdrLookupNormalizeId($reservationId), 0, 30);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO lookup_attempts (ip_hash, reservation_id, success) VALUES (?, ?, ?)'
        );
        $stmt->execute([$ipHash, $id, $success ? 1 : 0]);

        if ($success) {
            $delete = $pdo->prepare(
                'DELETE FROM lookup_attempts WHERE success = 0 AND (ip_hash = ? OR reservation_id = ?)'
            );
            $delete->execute([$ipHash, $id]);
        } elseif (random_int(1, 100) === 1) {
            // Opportunistic retention cleanup keeps the table small.
            $pdo->exec('DELETE FROM lookup_attempts WHERE attempted_at < (NOW() - INTERVAL 7 DAY)');
        }
    } catch (Throwable $e) {
        error_log('lookup_attempts write failed: ' . $e->getMessage());
    }
}
