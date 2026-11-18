<?php
/**
 * Post-deploy verification for the PDR Booking System.
 *
 * CLI only:  sudo -u www-data php deploy/check.php
 *
 * Exit code 0 = all critical checks passed. Warnings never fail the run.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("CLI only\n");
}

$root = dirname(__DIR__);
$failures = 0;
$warnings = 0;

function result(string $tag, string $message): void
{
    $colour = match ($tag) {
        'OK'   => "\033[32m",
        'WARN' => "\033[33m",
        default => "\033[31m",
    };
    printf("  %s[%s]%s %s\n", $colour, $tag, "\033[0m", $message);
}

function fail(string $message): void
{
    global $failures;
    $failures++;
    result('FAIL', $message);
}

function warn(string $message): void
{
    global $warnings;
    $warnings++;
    result('WARN', $message);
}

function pass(string $message): void
{
    result('OK', $message);
}

echo "\nPDR deployment check\n";
echo str_repeat('-', 60), "\n";

// ---------------------------------------------------------------- PHP/ext ---
if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    pass('PHP ' . PHP_VERSION);
} else {
    fail('PHP ' . PHP_VERSION . ' is too old (need 8.1+)');
}

foreach (['pdo_mysql', 'curl', 'mbstring', 'json', 'openssl', 'session', 'fileinfo'] as $ext) {
    if (extension_loaded($ext)) {
        pass("Extension: {$ext}");
    } else {
        fail("Missing PHP extension: {$ext}");
    }
}

// --------------------------------------------------------------------- env ---
if (!is_file($root . '/.env')) {
    fail('.env is missing (copy .env.example to .env)');
} else {
    pass('.env exists');
    $mode = substr(sprintf('%o', fileperms($root . '/.env')), -3);
    if ($mode === '640' || $mode === '600') {
        pass(".env permissions are {$mode}");
    } else {
        warn(".env permissions are {$mode} (expect 640 or 600)");
    }
}

require_once $root . '/config/env.php';

$requiredEnv = ['PDR_DB_HOST', 'PDR_DB_NAME', 'PDR_DB_USER', 'PDR_LOOKUP_SECRET'];
foreach ($requiredEnv as $key) {
    $value = (string) pdrEnv($key, '');
    if ($value === '') {
        fail("{$key} is empty");
    }
}

// ----------------------------------------------------------------- database ---
$pdo = null;
$host = (string) pdrEnv('PDR_DB_HOST', 'localhost');
$port = (string) pdrEnv('PDR_DB_PORT', '3306');
$dbname = (string) pdrEnv('PDR_DB_NAME', 'pdrsystem');
$user = (string) pdrEnv('PDR_DB_USER', 'root');
$pass = (string) pdrEnv('PDR_DB_PASS', '');

if (pdrEnv('PDR_DB_PASS', '') === '') {
    warn('PDR_DB_PASS is empty (fine for local XAMPP root, not for production)');
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
    pass("Connected to MySQL database `{$dbname}`");
} catch (Throwable $e) {
    fail('Database connection failed: ' . $e->getMessage());
}

if ($pdo !== null) {
    $expected = [
        'admin_users', 'audit_logs', 'blackout_dates', 'catalog_guidelines', 'customers',
        'login_attempts', 'lookup_attempts', 'payments', 'reservations', 'reviews',
        'schema_migrations', 'service_addons', 'service_menus', 'service_packages', 'services',
    ];
    $stmt = $pdo->query('SHOW TABLES');
    $actual = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $missing = array_values(array_diff($expected, $actual));
    if ($missing === []) {
        pass(count($expected) . ' expected tables present');
    } else {
        fail('Missing tables: ' . implode(', ', $missing));
    }

    try {
        $services = (int) $pdo->query('SELECT COUNT(*) FROM services')->fetchColumn();
        $services > 0
            ? pass("{$services} services in the catalog")
            : warn('services table is empty (run database/seed_catalog.php)');
    } catch (Throwable $e) {
        fail('Could not count services: ' . $e->getMessage());
    }

    try {
        $admins = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        $admins > 0
            ? pass("{$admins} admin account(s)")
            : warn('No admin account yet (run create_admin.php)');
    } catch (Throwable $e) {
        fail('Could not count admin users: ' . $e->getMessage());
    }

    try {
        $tz = (string) $pdo->query("SELECT @@session.time_zone")->fetchColumn();
        $tz === '+08:00'
            ? pass('MySQL session time zone is +08:00')
            : warn("MySQL session time zone is {$tz} (config/db.php sets +08:00 per connection)");
    } catch (Throwable $e) {
        warn('Could not read MySQL time zone: ' . $e->getMessage());
    }
}

// -------------------------------------------------------------------- mail ---
$mailEnabled = strtolower((string) pdrEnv('PDR_MAIL_ENABLED', 'false')) === 'true';
$mailPass = (string) pdrEnv('PDR_MAIL_PASSWORD', '');
$outbox = $root . '/api/mail/outbox';

if ($mailEnabled && $mailPass === '') {
    warn('PDR_MAIL_ENABLED=true but PDR_MAIL_PASSWORD is blank: confirmations go to api/mail/outbox/ (outbox mode)');
}
if (is_dir($outbox)) {
    is_writable($outbox)
        ? pass('api/mail/outbox is writable')
        : fail('api/mail/outbox is not writable by the web user');
} else {
    warn('api/mail/outbox directory is missing');
}

// ------------------------------------------------------------- integrations ---
if (is_file($root . '/config/xendit.php')) {
    pass('config/xendit.php present (online payments enabled)');
} else {
    warn('config/xendit.php missing - online payments are disabled');
}

if ((string) pdrEnv('PDR_TURNSTILE_SITE_KEY', '') !== '' && (string) pdrEnv('PDR_TURNSTILE_SECRET', '') !== '') {
    pass('Cloudflare Turnstile configured');
} else {
    warn('Turnstile keys empty - bot challenge disabled');
}

// -------------------------------------------------------------- htaccess 404 ---
$htaccess = @file_get_contents($root . '/.htaccess');
if ($htaccess !== false && preg_match('#^ErrorDocument\s+404\s+/pdrsystem/#m', $htaccess) === 1) {
    warn('.htaccess still points ErrorDocument 404 at /pdrsystem/... - change it to /customer/404.html for a root deployment');
} elseif ($htaccess !== false) {
    pass('ErrorDocument 404 is set up for a root deployment');
}

// -------------------------------------------------------------------- summary ---
echo str_repeat('-', 60), "\n";
printf("  %d failure(s), %d warning(s)\n\n", $failures, $warnings);
exit($failures > 0 ? 1 : 0);
