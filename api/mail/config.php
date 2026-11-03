<?php
/**
 * Mail configuration — Pio De Roda booking confirmation emails.
 *
 * HOW TO ACTIVATE REAL SENDING
 * ----------------------------
 * XAMPP has no mail server, so until SMTP credentials are filled in below,
 * every email is written to the OUTBOX folder (api/mail/outbox/) as a .eml
 * file instead of being lost. The moment `host`, `username` and `password`
 * are filled in, emails go out through that SMTP account for real.
 *
 * Gmail example:
 *   host        = 'smtp.gmail.com'
 *   port        = 587
 *   encryption  = 'tls'
 *   username    = 'your@gmail.com'
 *   password    = 'your Gmail App Password (NOT your login password)
 *                  -> create one at https://myaccount.google.com/apppasswords'
 *
 * Set `enabled` to false to force outbox mode even with credentials filled in
 * (useful for testing without sending anything).
 *
 * SECURITY: this file may contain a password. It returns an array and prints
 * nothing, so requesting it over HTTP yields an empty response — never echo
 * these values anywhere.
 */
return [
    'enabled'     => true, // false = never hit the network, always use the outbox
    'host'        => 'smtp.gmail.com',
    'port'        => 587,  // 587 for TLS, 465 for SSL
    'encryption'  => 'tls',// 'tls' | 'ssl' | 'none'
    'username'    => 'pioderoda.booking@gmail.com',
    'password'    => 'roxh vgss puoh nbmh', // Gmail App Password (revocable at myaccount.google.com/apppasswords)

    // Who the customer sees as the sender.
    'from_email'  => 'pioderoda.booking@gmail.com',
    'from_name'   => 'Pio De Roda Cafe & Restaurant',

    // Where un-sent emails are parked as .eml files (viewable/double-clickable).
    'outbox'      => __DIR__ . '/outbox',

    // SMTP timeout in seconds — keep short so a dead server can never stall
    // the reservation response for long.
    'timeout'     => 8,
];
