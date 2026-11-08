<?php
/**
 * Retention sweep for lookup_attempts (T-05 brute-force audit trail).
 *
 * CLI only. Deletes rows older than the retention window so the table does not
 * grow forever. Schedule it (Windows Task Scheduler, cron, or a daily run):
 *
 *   C:\xampp\php\php.exe database\cleanup_lookup_attempts.php
 *   C:\xampp\php\php.exe database\cleanup_lookup_attempts.php 30   # keep 30 days
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/db.php';

$days = (int) ($argv[1] ?? 7);
if ($days < 1 || $days > 365) {
    fwrite(STDERR, "Retention must be between 1 and 365 days.\n");
    exit(1);
}

try {
    // Table may not exist yet on a fresh database.
    $exists = $pdo->query("SHOW TABLES LIKE 'lookup_attempts'")->fetchColumn();
    if (!$exists) {
        echo "lookup_attempts does not exist; nothing to clean.\n";
        exit(0);
    }

    $stmt = $pdo->prepare(
        'DELETE FROM lookup_attempts WHERE attempted_at < (NOW() - INTERVAL ? DAY)'
    );
    $stmt->execute([$days]);

    echo "Deleted " . $stmt->rowCount() . " row(s) older than {$days} day(s).\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Cleanup failed: ' . $e->getMessage() . "\n");
    exit(1);
}
