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

try {
    // =========================================================================
    // Step 1: Get form data
    // =========================================================================
    $name = '';
    $email = ''; // Visitor's email
    $message = '';

    // Get data from JSON (fetch AJAX) or POST
    $raw = @file_get_contents('php://input');
    $data = @json_decode($raw, true);

    if ($data !== false && is_array($data)) {
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

    // =========================================================================
    // Step 2: Validation
    // =========================================================================
    if (empty($name) || empty($email) || empty($message)) {
        echo json_encode(['ok' => false, 'error' => 'All fields are required.']);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
    
    $to = 'pioderodabooking@gmail.com';
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
            'message' => "✅ Your message has been sent! We'll get back to you soon."
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