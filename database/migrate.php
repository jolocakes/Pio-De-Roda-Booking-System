<?php
/**
 * T-10: versioned database migration runner.
 *
 * Usage (CLI only):
 *   C:\xampp\php\php.exe database\migrate.php            Apply pending migrations
 *   C:\xampp\php\php.exe database\migrate.php --status    Show applied / pending
 *
 * How it works:
 *   - database/migrations/*.sql are applied in filename order.
 *   - A `schema_migrations` ledger records each applied filename, so running
 *     this again is a no-op (idempotent at the migration level).
 *   - Individual statements are also written defensively (CREATE ... IF NOT
 *     EXISTS, ADD COLUMN IF NOT EXISTS) so a partially-applied database heals.
 *
 * Fresh installs do NOT need this file: import database/schema.sql, which
 * already contains every table and column.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config/db.php';

$migrationsDir = __DIR__ . '/migrations';
$showStatus = in_array('--status', $argv ?? [], true);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS `schema_migrations` (
        `filename` VARCHAR(191) NOT NULL,
        `checksum` CHAR(64) NOT NULL,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`filename`)
     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$files = glob($migrationsDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($files, SORT_STRING);

if (!$files) {
    fwrite(STDERR, "No migration files found in $migrationsDir\n");
    exit(1);
}

$ledger = [];
foreach ($pdo->query("SELECT filename, checksum FROM schema_migrations") as $row) {
    $ledger[$row['filename']] = $row['checksum'];
}

/**
 * Split a SQL file into statements, honouring quotes and comments.
 * Returns a list of executable statements.
 */
function pdrSplitSql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $len = strlen($sql);
    $quote = null;

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($quote !== null) {
            $buffer .= $ch;
            if ($ch === '\\') {
                if ($next !== '') {
                    $buffer .= $next;
                    $i++;
                }
            } elseif ($ch === $quote) {
                // Doubled quote ('' or "") is an escaped quote.
                if ($next === $quote) {
                    $buffer .= $next;
                    $i++;
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        // Line comments: -- (must be followed by whitespace/EOL) and #
        if ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }
        if ($ch === '#') {
            while ($i < $len && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }

        // Block comments
        if ($ch === '/' && $next === '*') {
            $i += 2;
            while ($i + 1 < $len && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++; // skip the '/'
            $buffer .= ' ';
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buffer .= $ch;
            continue;
        }

        if ($ch === ';') {
            $stmt = trim($buffer);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $ch;
    }

    $stmt = trim($buffer);
    if ($stmt !== '') {
        $statements[] = $stmt;
    }

    return $statements;
}

$pending = 0;
$applied = 0;

foreach ($files as $path) {
    $filename = basename($path);
    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "ERROR: cannot read $filename\n");
        exit(1);
    }
    $checksum = hash('sha256', $sql);

    if (isset($ledger[$filename])) {
        if ($showStatus) {
            echo "APPLIED  $filename\n";
        }
        if ($ledger[$filename] !== $checksum && !$showStatus) {
            fwrite(STDERR, "WARNING: $filename changed after it was applied (checksum mismatch).\n");
        }
        continue;
    }

    $pending++;
    if ($showStatus) {
        echo "PENDING  $filename\n";
        continue;
    }

    $statements = pdrSplitSql($sql);
    echo "Applying $filename (" . count($statements) . " statement(s))...\n";

    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $applied++;
        } catch (PDOException $e) {
            $code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
            // Make the runner forgiving of already-existing objects even if a
            // migration was written without IF NOT EXISTS.
            if (in_array($code, [1050, 1060, 1061, 1091], true)) {
                echo "  skipped (already exists)\n";
                continue;
            }
            fwrite(STDERR, "ERROR in $filename: " . $e->getMessage() . "\n");
            fwrite(STDERR, "Statement: " . substr($statement, 0, 200) . "\n");
            exit(1);
        }
    }

    $record = $pdo->prepare("INSERT INTO schema_migrations (filename, checksum) VALUES (?, ?)");
    $record->execute([$filename, $checksum]);
    echo "  recorded $filename\n";
}

if ($showStatus) {
    echo "\nPending: $pending\n";
    exit(0);
}

echo "\nDone. pending_applied=$pending statements_executed=$applied\n";
exit(0);
