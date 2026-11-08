<?php
/**
 * API: Mail self-test / outbox peek — Pio De Roda
 *
 * CLI ONLY. This endpoint used to be reachable over HTTP and allowed an
 * arbitrary recipient address. It now refuses web requests and always sends
 * to the fixed PDR_MAIL_TEST_RECIPIENT from .env.
 *
 * Usage (from the project root):
 *   C:\xampp\php\php.exe api\mail_test.php            -> configuration status
 *   C:\xampp\php\php.exe api\mail_test.php --send     -> send a sample email
 *
 * Passwords/credentials are never printed — only whether they are set.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('CLI only');
}

require_once __DIR__ . '/mail/mailer.php';
require_once __DIR__ . '/mail/confirmation_template.php';

try {
    $cfg = pdrMailConfig();

    $status = [
        'ok'        => true,
        'smtpReady' => pdrSmtpConfigured($cfg),
        'enabled'   => !empty($cfg['enabled']),
        'host'      => $cfg['host'] ?: '(not set)',
        'port'      => (int) $cfg['port'],
        'encryption'=> $cfg['encryption'],
        'username'  => $cfg['username'] !== '' ? '(set)' : '(not set)',
        'password'  => $cfg['password'] !== '' ? '(set)' : '(not set)',
        'outbox'    => $cfg['outbox'],
    ];

    if (!in_array('--send', $argv ?? [], true)) {
        echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    }

    // Fixed recipient only — no caller-supplied address is accepted.
    $to = trim((string) pdrEnv('PDR_MAIL_TEST_RECIPIENT', $cfg['from_email']));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        fwrite(STDERR, "PDR_MAIL_TEST_RECIPIENT is not a valid email address.\n");
        exit(1);
    }

    // Sample message built by the SAME template reservations use.
    $sample = pdrConfirmEmail([
        'reservationId' => 'PDR-0000-0000',
        'bookingPin'    => '000000',
        'customerName'  => 'Test Customer',
        'mainService'   => 'Coffee Booth Package',
        'selectedPackage'=> 'Intimate Package (50 Cups)',
        'eventDate'     => '2026-12-24',
        'eventTime'     => '14:00',
        'eventType'     => 'Christmas Party',
        'guests'        => 50,
        'address'       => '123 Test St., Indang, Cavite',
        'estimatedTotal'=> 15000,
    ]);

    $result = pdrSendMail($to, $sample['subject'], $sample['html'], $sample['text']);

    echo json_encode(array_merge($status, [
        'recipient' => $to,
        'sent'      => $result,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit($result['ok'] ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode(['ok' => false, 'error' => $e->getMessage()]) . "\n");
    exit(1);
}
