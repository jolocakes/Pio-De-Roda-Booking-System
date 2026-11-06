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
return [
    'secret_key'     => 'xnd_development_REPLACE_WITH_YOUR_TEST_SECRET_KEY',
    'callback_token' => 'REPLACE_WITH_YOUR_WEBHOOK_VERIFICATION_TOKEN',
    'base_url'       => 'https://api.xendit.co',
    'currency'       => 'PHP',

    // Base URL of this app (adjust for your host/path). No trailing slash.
    'app_base_url'   => 'http://localhost/Pio-De-Roda-Booking-System',

    // Restrict invoice payment methods (Xendit Invoice `payment_methods`).
    // For PH this commonly includes EWALLET (e.g. GCash/Maya) and BANK_TRANSFER.
    // Set to [] or null to show all payment methods enabled on your Xendit account.
    // Adjust if your account expects specific method codes.
    'payment_methods' => ['EWALLET', 'BANK_TRANSFER'],

    // Deposit option shown to customers (percentage of estimated_total). 50 = 50%.
    'deposit_percent' => 50,

    // Invoice expiry in seconds (86400 = 24 hours).
    'invoice_duration' => 86400,
];
