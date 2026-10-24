<?php
/**
 * Admin Authentication Guard
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header("Location: index.php");
    exit();
}
