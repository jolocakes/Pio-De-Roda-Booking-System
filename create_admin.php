<?php
/**
 * Setup Script: Create First Admin User
 * Pio De Roda Hospitality Reservation and Event Booking System
 * Run via CLI: php create_admin.php username password "Full Name"
 */
require_once __DIR__ . '/config/db.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'AdminPDR2026!';
$fullName = $argv[3] ?? 'Pio De Roda Administrator';

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, full_name, role) VALUES (?, ?, ?, 'Administrator') ON DUPLICATE KEY UPDATE password_hash = ?, full_name = ?");
    $stmt->execute([$username, $hash, $fullName, $hash, $fullName]);
    echo "Admin user '{$username}' created/updated successfully!\n";
} catch (Exception $e) {
    echo "Error creating admin user: " . $e->getMessage() . "\n";
}
