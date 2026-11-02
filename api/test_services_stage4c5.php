<?php
/** Localhost-only Stage 4C.5 catalog verification endpoint. */
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit();
}
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/db_stage4c5_test.php';
require_once __DIR__ . '/catalog_response.php';
try {
    echo json_encode(pdrBuildCatalogResponse($pdo), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Stage 4C.5 test catalog API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
