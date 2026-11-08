<?php
/**
 * T-05 migration runner: create the lookup_attempts table.
 *
 * Usage (CLI only):
 *   C:\xampp\php\php.exe database\run_lookup_attempts_migration.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/db.php';

$sql = file_get_contents(__DIR__ . '/migrate_lookup_attempts.sql');
if ($sql === false) {
    fwrite(STDERR, "Could not read migrate_lookup_attempts.sql\n");
    exit(1);
}

// Strip line comments and split on semicolons (simple migration splitter).
$sql = preg_replace('/--.*$/m', '', $sql);
$parts = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');

$ok = 0;
foreach ($parts as $stmt) {
    try {
        $pdo->exec($stmt);
        $ok++;
        echo "OK: " . strtok($stmt, "\n") . "\n";
    } catch (PDOException $e) {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "\nDone. statements=$ok\n";
