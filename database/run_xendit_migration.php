<?php
/**
 * Run the Xendit payments migration (one-time) using the app's DB connection.
 * Usage (from project root):
 *   C:\xampp\php\php.exe database\run_xendit_migration.php
 * Review database/migrate_xendit_payments.sql before running. Back up first.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require __DIR__ . '/../config/db.php';

$sql = file_get_contents(__DIR__ . '/migrate_xendit_payments.sql');
if ($sql === false) { fwrite(STDERR, "Could not read migrate_xendit_payments.sql\n"); exit(1); }

// Strip line comments and split on semicolons (simple splitter for this migration).
$sql = preg_replace('/--.*$/m', '', $sql);
$parts = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');

$ok = 0; $skip = 0;
foreach ($parts as $stmt) {
    try {
        $pdo->exec($stmt);
        echo "OK: " . strtok(trim($stmt), "\n") . "\n";
        $ok++;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false || stripos($msg, 'Duplicate key') !== false || stripos($msg, 'already exists') !== false) {
            echo "SKIP (already applied): " . strtok(trim($stmt), "\n") . "\n";
            $skip++;
        } else {
            fwrite(STDERR, "ERROR: $msg\nSTATEMENT: " . substr($stmt, 0, 200) . "\n");
            exit(1);
        }
    }
}

echo "\nDone. OK=$ok, SKIP=$skip\n";

// Show verification
foreach (['payments','reservations'] as $t) {
    $cols = $pdo->query("SHOW COLUMNS FROM `$t`")->fetchAll(PDO::FETCH_COLUMN);
    echo "\n$t columns:\n - " . implode("\n - ", $cols) . "\n";
}
