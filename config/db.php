<?php
/**
 * Database Connection Configuration
 * Pio De Roda Hospitality Reservation and Event Booking System
 * 
 * Uses PHP PDO with utf8mb4 encoding, exception-based error handling,
 * and secure local XAMPP settings.
 */

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
} catch (PDOException $e) {
    // Log error securely internally in production, display generic message to user
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    exit("Database connection failed. Please try again later.");
}
