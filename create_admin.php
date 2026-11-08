<?php
/**
 * Setup Script: Create / reset an admin user.
 *
 * CLI ONLY. This script must never be reachable over HTTP: it can create an
 * administrator account or overwrite an existing one's password.
 *
 * Usage:
 *   C:\xampp\php\php.exe create_admin.php <username> <password> "<Full Name>"
 *
 * Both username and password are REQUIRED. There is no default password, so a
 * mis-run command can never silently reset an account to a known value.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('CLI only');
}

require_once __DIR__ . '/config/db.php';

$username = trim((string) ($argv[1] ?? ''));
$password = (string) ($argv[2] ?? '');
$fullName = trim((string) ($argv[3] ?? 'Pio De Roda Administrator'));

if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php create_admin.php <username> <password> [full name]\n");
    fwrite(STDERR, "Refusing to run without an explicit username and password.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Refusing to set a password shorter than 12 characters.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, full_name, role) VALUES (?, ?, ?, 'Administrator') ON DUPLICATE KEY UPDATE password_hash = ?, full_name = ?");
    $stmt->execute([$username, $hash, $fullName, $hash, $fullName]);
    echo "Admin user '{$username}' created/updated successfully!\n";
    echo "Log in and change this password if it was shared anywhere.\n";
} catch (Exception $e) {
    fwrite(STDERR, "Error creating admin user: " . $e->getMessage() . "\n");
    exit(1);
}
