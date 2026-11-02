<?php
/**
 * API: Public Customer Services Catalog
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/catalog_response.php';

try {
    echo json_encode(pdrBuildCatalogResponse($pdo), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit();
} catch (Throwable $e) {
    error_log('Public catalog API failure: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}



