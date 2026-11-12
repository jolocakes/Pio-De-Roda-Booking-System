<?php
/**
 * Status-change emails — sent when admin moves a booking to Confirmed,
 * Cancelled or Completed (T-14).
 *
 * Design rules:
 *   - "Pending" is the creation state and is already covered by the
 *     confirmation email, so it has no template here.
 *   - Every customer-supplied value is escaped before it reaches HTML.
 *   - Sending is ALWAYS best effort: a mail failure is logged and returned,
 *     never thrown, so a status update can never be lost because of SMTP.
 */

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/confirmation_template.php';

/**
 * Build the email for a status transition.
 * Returns ['subject' => ..., 'html' => ..., 'text' => ...] or null when the
 * status has no customer-facing template.
 */
function pdrStatusEmailFor(string $status, array $r): ?array
{
    $map = [
        'Confirmed' => [
            'subject' => 'Reservation CONFIRMED — Booking ID %s | Pio De Roda',
            'heading' => 'Your reservation is confirmed',
            'badge'   => 'CONFIRMED',
            'lead'    => 'Good news — our staff has approved your reservation.',
            'steps'   => [
                'You may now proceed to payment. Your estimated total is shown below.',
                'Keep your Reservation ID and Booking PIN — you need both to check your booking status.',
                'If you need to reschedule or cancel, please contact us as early as possible.',
            ],
            'note'    => 'Please settle payment before the event date so we can finalize your booking.',
        ],
        'Cancelled' => [
            'subject' => 'Reservation CANCELLED — Booking ID %s | Pio De Roda',
            'heading' => 'Your reservation was cancelled',
            'badge'   => 'CANCELLED',
            'lead'    => 'Your reservation has been cancelled and its date has been released.',
            'steps'   => [
                'If this was a mistake, contact us and we will check whether the date is still available.',
                'You are welcome to make a new reservation at any time.',
            ],
            'note'    => 'If you already made a payment, please contact us so we can advise on the refund process.',
        ],
        // T-25: a rejection is a venue decision, not a customer cancellation.
        // The wording is deliberately distinct so the customer is not told they
        // cancelled something they did not.
        'Rejected' => [
            'subject' => 'Reservation NOT APPROVED — Booking ID %s | Pio De Roda',
            'heading' => 'We could not approve your reservation',
            'badge'   => 'NOT APPROVED',
            'lead'    => 'Our staff reviewed your reservation and unfortunately could not approve it. The requested date has been released.',
            'steps'   => [
                'Contact us so we can suggest another date or an alternative arrangement.',
                'You are welcome to submit a new reservation for a different date.',
            ],
            'note'    => 'If you already made a payment, please contact us so we can advise on the refund process.',
        ],
        'Completed' => [
            'subject' => 'Thank you for visiting — Booking ID %s | Pio De Roda',
            'heading' => 'Thank you for visiting Pio De Roda',
            'badge'   => 'COMPLETED',
            'lead'    => 'Your reservation is now marked as completed. Thank you for choosing us!',
            'steps'   => [
                'We would love to hear about your experience — you can leave a review on our website.',
                'We look forward to welcoming you again.',
            ],
            'note'    => 'This is a courtesy confirmation — no action is needed.',
        ],
    ];

    if (!isset($map[$status])) {
        return null;
    }

    $cfg = $map[$status];
    $e = function ($v) {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    };

    $id    = $e($r['reservation_id'] ?? '');
    $name  = $e($r['customer_name'] ?? '');
    $total = number_format((float) ($r['estimated_total'] ?? 0), 2);

    $subject = sprintf($cfg['subject'], (string) ($r['reservation_id'] ?? ''));

    // ---------- Plain text ----------
    $text = "Dear {$r['customer_name']},\n"
        . "\n"
        . $cfg['lead'] . "\n"
        . "\n"
        . "BOOKING DETAILS\n"
        . "Reservation ID: " . ($r['reservation_id'] ?? '') . "\n"
        . "Service:        " . ($r['main_service'] ?? '') . "\n"
        . "Package:        " . ($r['selected_package'] ?? '') . "\n"
        . "Date:           " . ($r['event_date'] ?? '') . "\n"
        . "Time:           " . ($r['event_time'] ?? '') . "\n"
        . "Guests:         " . (int) ($r['guests'] ?? 0) . " pax\n"
        . "Estimated total: PHP {$total}\n"
        . "\n"
        . "NEXT STEPS\n";
    foreach ($cfg['steps'] as $i => $step) {
        $text .= ($i + 1) . '. ' . $step . "\n";
    }
    $text .= "\n" . $cfg['note'] . "\n"
        . "\n"
        . "— Pio De Roda Cafe and Restaurant\n"
        . "508 De Ocampo St., Indang, Cavite 4122\n"
        . "(046) 415 0390 / (0926) 684 5833\n";

    // ---------- HTML ----------
    $stepsHtml = '';
    foreach ($cfg['steps'] as $step) {
        $stepsHtml .= '<li style="margin-bottom:8px;">' . $e($step) . '</li>';
    }

    $rows = pdrEmailRow('Name', $name)
        . pdrEmailRow('Main Service', $e($r['main_service'] ?? ''))
        . pdrEmailRow('Package', $e($r['selected_package'] ?? ''))
        . pdrEmailRow('Event Date', $e($r['event_date'] ?? ''))
        . pdrEmailRow('Event Time', $e($r['event_time'] ?? ''))
        . pdrEmailRow('Guests', (int) ($r['guests'] ?? 0) . ' pax')
        . pdrEmailRow('Estimated Total', '&#8369;' . $total, true);

    $heading = $e($cfg['heading']);
    $lead    = $e($cfg['lead']);
    $badge   = $e($cfg['badge']);
    $note    = $e($cfg['note']);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background-color:#F6ECDD;font-family:Georgia,'Times New Roman',serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F6ECDD;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#FBF6EE;border:1px solid rgba(91,8,8,0.14);border-radius:12px;overflow:hidden;">

          <tr>
            <td style="background-color:#5B0808;padding:22px 28px;">
              <div style="font-size:22px;color:#F6ECDD;letter-spacing:2px;font-weight:bold;">PIO DE RODA</div>
              <div style="font-size:11px;color:#D9A84E;letter-spacing:3px;margin-top:2px;">CAFE AND RESTAURANT</div>
            </td>
          </tr>

          <tr>
            <td style="padding:28px;">
              <div style="display:inline-block;background-color:#5B0808;color:#F6ECDD;font-size:11px;letter-spacing:2px;padding:5px 12px;border-radius:20px;margin-bottom:14px;">{$badge}</div>
              <h1 style="margin:0 0 12px;font-size:22px;color:#5B0808;font-weight:normal;">{$heading}</h1>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#2A0E0E;">{$lead}</p>

              <div style="font-size:11px;letter-spacing:2px;color:#6B4C4C;margin:0 0 8px;">BOOKING DETAILS</div>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Helvetica,Arial,sans-serif;font-size:13px;color:#2A0E0E;margin-bottom:18px;">
                {$rows}
              </table>

              <p style="margin:0 0 6px;font-size:14px;line-height:1.6;color:#2A0E0E;"><strong>Next steps</strong></p>
              <ol style="margin:0 0 18px;padding-left:20px;font-size:14px;line-height:1.7;color:#2A0E0E;">
                {$stepsHtml}
              </ol>

              <p style="margin:0;font-size:13px;line-height:1.6;color:#8C1414;background-color:rgba(140,20,20,0.07);border-radius:8px;padding:10px 12px;">{$note}</p>
            </td>
          </tr>

          <tr>
            <td style="background-color:#5B0808;padding:18px 28px;font-family:Helvetica,Arial,sans-serif;font-size:11px;color:#E3C9C9;line-height:1.7;">
              Pio De Roda Cafe and Restaurant<br/>
              508 De Ocampo St., Indang, Cavite 4122<br/>
              (046) 415 0390 / (0926) 684 5833
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/**
 * Fetch the reservation and send its status email. Never throws.
 * Returns ['ok' => bool, 'mode' => 'smtp'|'outbox'|'none', 'error' => ?string].
 */
function pdrDispatchStatusEmail(PDO $pdo, string $reservationId, string $status): array
{
    try {
        if (!in_array($status, ['Confirmed', 'Cancelled', 'Rejected', 'Completed'], true)) {
            return ['ok' => false, 'mode' => 'none', 'error' => 'no template for status'];
        }

        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ? LIMIT 1");
        $stmt->execute([$reservationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return ['ok' => false, 'mode' => 'none', 'error' => 'reservation not found'];
        }

        $to = (string) ($row['email'] ?? '');
        if ($to === '') {
            return ['ok' => false, 'mode' => 'none', 'error' => 'reservation has no email'];
        }

        $tpl = pdrStatusEmailFor($status, $row);
        if ($tpl === null) {
            return ['ok' => false, 'mode' => 'none', 'error' => 'no template for status'];
        }

        return pdrSendMail($to, $tpl['subject'], $tpl['html'], $tpl['text']);
    } catch (Throwable $e) {
        error_log('Status email failure: ' . $e->getMessage());
        return ['ok' => false, 'mode' => 'none', 'error' => $e->getMessage()];
    }
}
