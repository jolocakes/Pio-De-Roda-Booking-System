<?php
/**
 * Mail configuration — Pio De Roda booking confirmation emails.
 *
 * Credentials are read from the project-root .env file (git-ignored) or from
 * real environment variables. This file contains NO secrets and is safe to
 * commit. Copy .env.example to .env and fill in the SMTP values.
 *
 * XAMPP has no mail server. When SMTP credentials are absent (or `enabled` is
 * false), every email is written to the OUTBOX folder as a .eml file instead
 * of being lost.
 *
 * Gmail example:
 *   PDR_MAIL_HOST=smtp.gmail.com
 *   PDR_MAIL_PORT=587
 *   PDR_MAIL_ENCRYPTION=tls
 *   PDR_MAIL_USERNAME=your@gmail.com
 *   PDR_MAIL_PASSWORD=your Gmail App Password (NOT your login password)
 *     -> create one at https://myaccount.google.com/apppasswords
 */

require_once __DIR__ . '/../../config/env.php';

$host = (string) pdrEnv('PDR_MAIL_HOST', 'smtp.gmail.com');
$username = (string) pdrEnv('PDR_MAIL_USERNAME', '');
$fromEmail = (string) pdrEnv('PDR_MAIL_FROM_EMAIL', $username);

return [
    'enabled'     => filter_var(pdrEnv('PDR_MAIL_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
    'host'        => $host,
    'port'        => (int) pdrEnv('PDR_MAIL_PORT', '587'),
    'encryption'  => (string) pdrEnv('PDR_MAIL_ENCRYPTION', 'tls'),
    'username'    => $username,
    'password'    => (string) pdrEnv('PDR_MAIL_PASSWORD', ''),

    // Who the customer sees as the sender.
    'from_email'  => $fromEmail,
    'from_name'   => (string) pdrEnv('PDR_MAIL_FROM_NAME', 'Pio De Roda Cafe & Restaurant'),

    // Where un-sent emails are parked as .eml files (viewable/double-clickable).
    'outbox'      => __DIR__ . '/outbox',

    // SMTP timeout in seconds — keep short so a dead server can never stall
    // the reservation response for long.
    'timeout'     => 8,
];
