<?php
/** Stage 4C.5 additive migration; CLI only and test database only. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI execution required.\n");
    exit(1);
}
require_once __DIR__ . '/../config/db_stage4c5_test.php';

function assertStage4c5TestDatabase(PDO $pdo): void
{
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'pdrsystem_stage4c5_test') {
        throw new RuntimeException('Database isolation guard failed.');
    }
}

try {
    assertStage4c5TestDatabase($pdo);
    $requiredTables = ['services', 'service_packages', 'service_menus', 'service_addons', 'reservations', 'payments', 'admin_users'];
    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    foreach ($requiredTables as $table) {
        $check->execute([$table]);
        if ((int)$check->fetchColumn() !== 1) {
            throw new RuntimeException('Required table missing; migration stopped.');
        }
    }

    assertStage4c5TestDatabase($pdo);
    $pdo->exec("ALTER TABLE service_packages ADD COLUMN IF NOT EXISTS max_cap INT NULL DEFAULT NULL AFTER sort_order");
    assertStage4c5TestDatabase($pdo);
    $pdo->exec("ALTER TABLE services ADD COLUMN IF NOT EXISTS customer_display JSON DEFAULT NULL AFTER guidelines");
    assertStage4c5TestDatabase($pdo);
    $pdo->exec("CREATE TABLE IF NOT EXISTS catalog_guidelines (
        id VARCHAR(64) NOT NULL,
        heading VARCHAR(150) NOT NULL,
        items JSON NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_catalog_guidelines_active (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    assertStage4c5TestDatabase($pdo);

    echo "Migration complete for pdrsystem_stage4c5_test.\n";
} catch (Throwable $e) {
    error_log('Stage 4C.5 test migration failed: ' . $e->getMessage());
    fwrite(STDERR, "Migration failed; inspect the test database before retrying.\n");
    exit(1);
}
