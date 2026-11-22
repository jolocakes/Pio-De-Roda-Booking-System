<?php
/**
 * Stage 4E — Phase B: Production additive schema migration for customer catalog integration.
 * CLI only, target database `pdrsystem` only.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI execution required.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

function assertStage4eProductionDatabase(PDO $pdo): void
{
    $activeDb = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($activeDb !== 'pdrsystem') {
        throw new RuntimeException("Production database guard failed: expected 'pdrsystem', got '{$activeDb}'.");
    }
}

function getColumnDefinition(PDO $pdo, string $table, string $column): ?array
{
    $stmt = $pdo->query("DESCRIBE `{$table}`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
        if ($col['Field'] === $column) {
            return $col;
        }
    }
    return null;
}

function verifyMaxCapColumn(array $col): void
{
    if (
        strtolower($col['Type']) !== 'int(11)' ||
        $col['Null'] !== 'YES' ||
        $col['Default'] !== null
    ) {
        throw new RuntimeException('Unexpected existing definition for service_packages.max_cap.');
    }
}

function verifyCustomerDisplayColumn(array $col): void
{
    if (
        strtolower($col['Type']) !== 'longtext' ||
        $col['Null'] !== 'YES' ||
        $col['Default'] !== null
    ) {
        throw new RuntimeException('Unexpected existing definition for services.customer_display.');
    }
}

function verifyCatalogGuidelinesTable(PDO $pdo): void
{
    $cols = $pdo->query("DESCRIBE `catalog_guidelines`")->fetchAll(PDO::FETCH_ASSOC);
    $expectedCols = [
        ['Field' => 'id', 'Type' => 'varchar(64)', 'Null' => 'NO', 'Key' => 'PRI', 'Default' => null],
        ['Field' => 'heading', 'Type' => 'varchar(150)', 'Null' => 'NO', 'Key' => '', 'Default' => null],
        ['Field' => 'items', 'Type' => 'longtext', 'Null' => 'NO', 'Key' => '', 'Default' => null],
        ['Field' => 'sort_order', 'Type' => 'int(11)', 'Null' => 'NO', 'Key' => '', 'Default' => '0'],
        ['Field' => 'is_active', 'Type' => 'tinyint(1)', 'Null' => 'NO', 'Key' => 'MUL', 'Default' => '1'],
    ];
    if (count($cols) !== count($expectedCols)) {
        throw new RuntimeException('Unexpected column count in catalog_guidelines.');
    }
    foreach ($expectedCols as $i => $exp) {
        $actual = $cols[$i];
        if (
            $actual['Field'] !== $exp['Field'] ||
            strtolower($actual['Type']) !== $exp['Type'] ||
            $actual['Null'] !== $exp['Null'] ||
            $actual['Key'] !== $exp['Key'] ||
            $actual['Default'] !== $exp['Default']
        ) {
            throw new RuntimeException("Unexpected column definition in catalog_guidelines for '{$exp['Field']}'.");
        }
    }

    $indexes = $pdo->query("SHOW INDEX FROM `catalog_guidelines`")->fetchAll(PDO::FETCH_ASSOC);
    $keyMap = [];
    foreach ($indexes as $idx) {
        $keyMap[$idx['Key_name']][] = $idx['Column_name'];
    }
    if (
        ($keyMap['PRIMARY'] ?? []) !== ['id'] ||
        ($keyMap['idx_catalog_guidelines_active'] ?? []) !== ['is_active', 'sort_order']
    ) {
        throw new RuntimeException('Unexpected index structure in catalog_guidelines.');
    }
}

$ddlStatements = [
    'service_packages.max_cap' => "ALTER TABLE service_packages ADD COLUMN IF NOT EXISTS max_cap INT NULL DEFAULT NULL AFTER sort_order",
    'services.customer_display' => "ALTER TABLE services ADD COLUMN IF NOT EXISTS customer_display JSON DEFAULT NULL AFTER guidelines",
    'catalog_guidelines' => "CREATE TABLE IF NOT EXISTS catalog_guidelines (
        id VARCHAR(64) NOT NULL,
        heading VARCHAR(150) NOT NULL,
        items JSON NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_catalog_guidelines_active (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

try {
    assertStage4eProductionDatabase($pdo);

    $requiredTables = [
        'admin_users',
        'payments',
        'reservations',
        'reviews',
        'services',
        'service_packages',
        'service_menus',
        'service_addons',
    ];
    $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    foreach ($requiredTables as $table) {
        $tableCheck->execute([$table]);
        if ((int)$tableCheck->fetchColumn() !== 1) {
            throw new RuntimeException("Required baseline table '{$table}' is missing; migration stopped.");
        }
    }

    // Refuse unexpected existing column/table definitions before executing DDL
    $existingMaxCap = getColumnDefinition($pdo, 'service_packages', 'max_cap');
    if ($existingMaxCap !== null) {
        verifyMaxCapColumn($existingMaxCap);
    }

    $existingCustomerDisplay = getColumnDefinition($pdo, 'services', 'customer_display');
    if ($existingCustomerDisplay !== null) {
        verifyCustomerDisplayColumn($existingCustomerDisplay);
    }

    $tableCheck->execute(['catalog_guidelines']);
    if ((int)$tableCheck->fetchColumn() === 1) {
        verifyCatalogGuidelinesTable($pdo);
    }

    echo "Target Database Verified: pdrsystem\n";
    echo "Planned DDL Statements:\n";
    foreach ($ddlStatements as $label => $sql) {
        echo "  [{$label}] {$sql};\n";
    }

    $confirmed = in_array('--confirm-stage4e-phase-b', $argv ?? [], true);
    if (!$confirmed) {
        echo "Dry-run complete. Pass --confirm-stage4e-phase-b to execute the schema migration.\n";
        exit(0);
    }

    assertStage4eProductionDatabase($pdo);
    $pdo->exec($ddlStatements['service_packages.max_cap']);

    assertStage4eProductionDatabase($pdo);
    $pdo->exec($ddlStatements['services.customer_display']);

    assertStage4eProductionDatabase($pdo);
    $pdo->exec($ddlStatements['catalog_guidelines']);

    assertStage4eProductionDatabase($pdo);

    // Post-DDL verification
    $postMaxCap = getColumnDefinition($pdo, 'service_packages', 'max_cap');
    if ($postMaxCap === null) {
        throw new RuntimeException('service_packages.max_cap missing after migration.');
    }
    verifyMaxCapColumn($postMaxCap);

    $postCustomerDisplay = getColumnDefinition($pdo, 'services', 'customer_display');
    if ($postCustomerDisplay === null) {
        throw new RuntimeException('services.customer_display missing after migration.');
    }
    verifyCustomerDisplayColumn($postCustomerDisplay);

    verifyCatalogGuidelinesTable($pdo);

    echo "Stage 4E Phase B schema migration completed and verified for pdrsystem.\n";
} catch (Throwable $e) {
    error_log('Stage 4E Phase B production schema migration failed: ' . $e->getMessage());
    fwrite(STDERR, "Migration failed: " . $e->getMessage() . "\n");
    exit(1);
}
