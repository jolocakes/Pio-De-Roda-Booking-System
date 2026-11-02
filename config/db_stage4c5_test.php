<?php
/**
 * Stage 4C.5 isolated test connection. Do not use for production or customer traffic.
 */
if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit();
}

$host = 'localhost';
$dbname = 'pdrsystem_stage4c5_test';
$username = 'root';
$password = '';

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($activeDatabase !== 'pdrsystem_stage4c5_test') {
        throw new RuntimeException('Test connection database guard failed.');
    }
} catch (Throwable $e) {
    error_log('Stage 4C.5 test DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Test database connection failed.');
}
