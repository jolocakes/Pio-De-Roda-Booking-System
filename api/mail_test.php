<?php
/**
 * API: Mail self-test / outbox peek — Pio De Roda
 *
 *   GET mail_test.php          -> configuration status (never prints secrets)
 *   POST {"test": true}        -> sends a sample confirmation email through
 *                                 the same code path reservations use, then
 *                                 reports where it went (smtp | outbox).
 *
 * Passwords/credentials are never echoed back — only whether they are set.
 */
header('Content-Type: application/json');
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

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (empty($data['test'])) {
        echo json_encode($status);
        exit();
    }

    // Sample message built by the SAME template reservations use.
    // `to` is optional: defaults to test@example.com, but you can pass your
    // own address to see a real confirmation email land in your inbox.
    $to = trim($data['to'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $to = 'test@example.com';
    }
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

    echo json_encode(array_merge($status, ['sent' => $result]));

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
