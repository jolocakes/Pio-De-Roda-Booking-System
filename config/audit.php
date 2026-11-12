<?php
/**
 * T-35: administrative audit trail.
 *
 * Every state-changing admin endpoint calls pdrAudit() after the write
 * succeeds. Logging is best effort: a missing table or a bad value must never
 * turn a successful action into an error for the administrator.
 */

require_once __DIR__ . '/env.php';

function pdrAudit(
    PDO $pdo,
    string $action,
    ?string $targetType = null,
    ?string $targetId = null,
    array $details = []
): void {
    try {
        $actorId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null;
        $actorUsername = isset($_SESSION['admin_username'])
            ? mb_substr((string) $_SESSION['admin_username'], 0, 50)
            : null;

        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $secret = (string) pdrEnv('PDR_LOOKUP_SECRET', 'pdr-audit-local-secret');
        $ipHash = hash('sha256', $secret . '|audit|' . $ip);

        $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $payload = empty($details)
            ? null
            : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs
                (actor_id, actor_username, action, target_type, target_id, details, ip_hash, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $actorId,
            $actorUsername,
            mb_substr($action, 0, 50),
            $targetType === null ? null : mb_substr($targetType, 0, 40),
            $targetId === null ? null : mb_substr($targetId, 0, 64),
            $payload,
            $ipHash,
            $userAgent === '' ? null : $userAgent,
        ]);
    } catch (Throwable $e) {
        error_log('Audit log failure: ' . $e->getMessage());
    }
}
