<?php
/**
 * Process contact form submission and send email
 * Option A: Restaurant email as "From" (Simple & Reliable)
 * - Email always from: Pio De Roda Cafe & Restaurant <pioderodabooking@gmail.com>
 * - Visitor's email visible in message body
 * - Replies go to restaurant's inbox
 * - Works on any XAMPP/SMTP setup
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/../config/captcha.php';

// Read the body once: php://input is a stream and must not be re-read.
$raw = @file_get_contents('php://input');
$data = @json_decode($raw, true);
$isJson = is_array($data);

// T-32 honeypot: the hidden "company" field must stay empty. Report a normal
// success to the bot, but send no mail and consume no rate-limit budget.
$honeypot = $isJson ? ($data['company'] ?? '') : ($_POST['company'] ?? '');
if (is_string($honeypot) && trim($honeypot) !== '') {
    echo json_encode([
        'ok' => true,
        'mode' => 'ignored',
        'message' => "Your message has been sent! We'll get back to you soon.",
    ]);
    exit();
}

// 5 submissions per 15 minutes per IP. The form is public and each accepted
// submission sends a real email, so it is a spam/relay amplifier if unthrottled.
$limit = pdrRateLimit('contact', 5, 900);
if (!$limit['allowed']) {
    http_response_code(429);
    header('Retry-After: ' . (int) $limit['retryAfter']);
    echo json_encode([
        'ok' => false,
        'error' => 'Too many messages. Please wait a few minutes and try again.',
        'retryAfterSeconds' => (int) $limit['retryAfter'],
    ]);
    exit();
}

try {
    // =========================================================================
    // Step 1: Get form data
    // =========================================================================
    $name = '';
    $email = ''; // Visitor's email
    $message = '';

    if ($isJson) {
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $message = $data['message'] ?? '';
    }

    // Also check traditional POST
    if (empty($name)) {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $message = $_POST['message'] ?? '';
    }

    // Optional Turnstile token (the contact form only sends one when a widget
    // is present). When configured, a supplied-but-invalid token is rejected.
    $captchaToken = $isJson ? ($data['turnstileToken'] ?? '') : ($_POST['turnstileToken'] ?? '');
    if (is_string($captchaToken) && trim($captchaToken) !== '') {
        $captcha = pdrVerifyCaptcha($captchaToken, pdrCaptchaClientIp(), 'contact');
        if (!$captcha['ok']) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Please complete the human verification and try again.']);
            exit();
        }
    }

    // Coerce to strings and cap sizes so a huge payload cannot be mailed out.
    $name = is_string($name) ? trim($name) : '';
    $email = is_string($email) ? trim($email) : '';
    $message = is_string($message) ? trim($message) : '';

    if (mb_strlen($name) > 100) {
        $name = mb_substr($name, 0, 100);
    }
    if (mb_strlen($email) > 254) {
        $email = mb_substr($email, 0, 254);
    }
    if (mb_strlen($message) > 2000) {
        $message = mb_substr($message, 0, 2000);
    }

    // Strip CR/LF from header-adjacent values (defense in depth).
    $name = str_replace(["\r", "\n"], ' ', $name);
    $email = str_replace(["\r", "\n"], '', $email);

    // =========================================================================
    // Step 2: Validation
    // =========================================================================
    if ($name === '' || $email === '' || $message === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'All fields are required.']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
        exit();
    }

    // =========================================================================
    // Step 3: Load mail configuration and send email
    // =========================================================================
    require_once __DIR__ . '/mail/mailer.php';
    require_once __DIR__ . '/mail/confirmation_template.php';
    $cfg = pdrMailConfig();

    // Ensure outbox directory exists
    $outboxDir = $cfg['outbox'];
    if (!is_dir($outboxDir)) {
        @mkdir($outboxDir, 0775, true);
    }

    // =========================================================================
    // Step 4: Build and send the email (Option A)
    // =========================================================================
    // THE KEY: Always use the restaurant's email as the From address
    // This ensures the email passes spam filters and reaches the inbox
    
    // Recipient comes from .env (PDR_CONTACT_RECIPIENT), falling back to the
    // monitored restaurant inbox. Never caller-controlled.
    $to = trim((string) pdrEnv('PDR_CONTACT_RECIPIENT', 'pioderodabooking@gmail.com'));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $to = 'pioderodabooking@gmail.com';
    }
    $subject = 'Inquiry from Website Contact Form';
    $fromEmail = 'pioderodabooking@gmail.com'; // Restaurant's email (always)
    $fromName = 'Pio De Roda Cafe & Restaurant';

    // Text body - includes visitor's email so you can see it
    $textBody = "Name: $name\n\n";
    $textBody .= "Email: $email\n\n";      // Visitor's email visible here
    $textBody .= "Message:\n$message\n";

    // HTML body - includes visitor's email visible
    $htmlBody = "<h3>New Contact Form Inquiry</h3>"
        . "<p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>"
        . "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>"  // Visible
        . "<p><strong>Message:</strong> " . nl2br(htmlspecialchars($message)) . "</p>"
        . "<hr><p><small>Sent via Pio De Roda website contact form</small></p>";

    // ======================================================================
    // Send using the existing pdrSendMail function
    // This tries SMTP first, falls back to outbox if it can't send
    // ======================================================================
    $result = pdrSendMail($to, $subject, $htmlBody, $textBody);

    if ($result['ok']) {
        $mode = $result['mode'] ?? 'outbox';
        echo json_encode([
            'ok' => true,
            'mode' => $mode,
            'message' => "Your message has been sent! We'll get back to you soon."
        ]);
    } else {
        echo json_encode([
            'ok' => false,
            'error' => 'Failed to send email: ' . ($result['error'] ?? 'Unknown error')
        ]);
    }

} catch (Throwable $e) {
    error_log("Contact form error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Unexpected error. Please try again later.'
    ]);
}