<?php
/**
 * Admin Authentication Guard
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
require_once __DIR__ . '/../config/csrf.php';

pdrSessionStart();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    header("Location: index.php");
    exit();
}

// Pages including this guard can render pdrCsrfField() for POST forms.
pdrCsrfToken();
