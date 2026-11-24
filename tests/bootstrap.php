<?php
/**
 * PHPUnit bootstrap.
 *
 * The unit suite covers dependency-free helpers only: no database, no .env,
 * no HTTP. That keeps CI fast and lets it run without MySQL or credentials.
 */

declare(strict_types=1);

// Match config/db.php so date-based rules behave exactly as in the app.
date_default_timezone_set('Asia/Manila');

$root = dirname(__DIR__);

require_once $root . '/config/password_policy.php';
require_once $root . '/config/rbac.php';
require_once $root . '/config/payments.php';
require_once $root . '/lib/PdfReport.php';
require_once $root . '/api/capacity.php';
