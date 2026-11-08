<?php
/**
 * Database Connection Configuration
 * Pio De Roda Hospitality Reservation and Event Booking System
 * 
 * Uses PHP PDO with utf8mb4 encoding, exception-based error handling,
 * and secure local XAMPP settings.
 */

// T-18: one timezone everywhere. Every page and API loads this file, so this
// is the single place that decides what "today" means for bookings, calendar
// ranges and the PDR-YYYY booking year.
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$dbname = 'pdrsystem';
$username = 'root';
$password = '';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);

    // Keep NOW()/CURRENT_TIMESTAMP aligned with the PHP timezone above so
    // lockout windows and payment timestamps cannot drift by 8 hours.
    try {
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $tzError) {
        error_log('Could not set MySQL session time_zone: ' . $tzError->getMessage());
    }
} catch (PDOException $e) {
    // Log error securely internally in production, display generic message to user
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    exit("Database connection failed. Please try again later.");
}
