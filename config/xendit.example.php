<?php
/**
 * Xendit TEST configuration (example).
 * Copy to config/xendit.php and fill in your TEST credentials.
 * NEVER commit config/xendit.php (it contains secrets).
 *
 * Dashboard (Test mode): https://dashboard.xendit.co/
 *  - API key: Settings > Developers > API Keys (xnd_development_...)
 *  - Callback token: Settings > Webhooks > Webhook verification token
 */

// The public base URL lives in .env so it does not have to be hand-edited in
// this file on every tunnel/domain change. Local XAMPP serves the project root
// at http://localhost (no /pdrsystem/ prefix).
require_once __DIR__ . '/env.php';

return [
    'secret_key'     => 'xnd_development_REPLACE_WITH_YOUR_TEST_SECRET_KEY',
    'callback_token' => 'REPLACE_WITH_YOUR_WEBHOOK_VERIFICATION_TOKEN',
    'base_url'       => 'https://api.xendit.co',
    'currency'       => 'PHP',

    // Public base URL of this app. Set PDR_APP_BASE_URL in .env for the real
    // domain; the fallback matches the local XAMPP vhost. No trailing slash.
    'app_base_url'   => rtrim((string) pdrEnv('PDR_APP_BASE_URL', 'http://localhost'), '/'),

    // Restrict invoice payment methods (Xendit Invoice `payment_methods`).
    // Leave this empty to show every channel enabled on your Xendit account.
    // Listing a channel the account does not have makes invoice creation fail
    // with UNAVAILABLE_PAYMENT_METHOD_ERROR, e.g. BANK_TRANSFER on most PH test
    // accounts (they expose EWALLET, QRPH, over-the-counter and direct debit).
    'payment_methods' => [],

    // Deposit option shown to customers (percentage of estimated_total). 50 = 50%.
    'deposit_percent' => 50,

    // Invoice expiry in seconds (86400 = 24 hours).
    'invoice_duration' => 86400,
];
