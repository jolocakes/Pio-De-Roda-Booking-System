<?php
/**
 * Confirmation email content — sent the moment a reservation is created.
 *
 * Says exactly what the customer needs, per Pio De Roda's process:
 *   1. Thank you for the reservation.
 *   2. Our staff will CONFIRM it — once confirmed you can proceed to payment.
 *   3. The Reservation ID and Booking PIN (the two things they must save).
 *
 * Returns ['subject' => ..., 'html' => ..., 'text' => ...].
 * All values are escaped — the PIN/ID come from the server, but the customer
 * name and address are user input.
 */

/** Brand colors mirror the site (maroon / cream / gold) — inline styles only,
 *  since email clients strip <style> blocks. */
function pdrConfirmEmail($r)
{
    $e = function ($v) {
        return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
    };

    $id  = $e($r['reservationId']);
    $pin = $e($r['bookingPin']);
    $name = $e($r['customerName']);

    $subject = 'Reservation Received — Booking ID ' . $r['reservationId'] . ' | Pio De Roda';

    // ---------- Plain-text alternative (also what the outbox preview shows) ----------
    $text = "Thank you, {$r['customerName']}!\n"
        . "\n"
        . "We have received your reservation with Pio De Roda Cafe and Restaurant.\n"
        . "Your booking is now PENDING CONFIRMATION.\n"
        . "\n"
        . "NEXT STEPS\n"
        . "1. Our staff will review and confirm your reservation.\n"
        . "2. Once confirmed, we will contact you so you can PROCEED TO PAYMENT.\n"
        . "3. Please wait for the confirmation before making any payment.\n"
        . "\n"
        . "YOUR BOOKING CREDENTIALS\n"
        . "Reservation ID: {$r['reservationId']}\n"
        . "Booking PIN:     {$r['bookingPin']}\n"
        . "\n"
        . "Please save both — you will need them to check your booking status.\n"
        . "The PIN will not be shown again.\n"
        . "\n"
        . "RESERVATION DETAILS\n"
        . "Name:      {$r['customerName']}\n"
        . "Service:   {$r['mainService']}\n"
        . "Package:   {$r['selectedPackage']}\n"
        . "Date:      {$r['eventDate']}\n"
        . "Time:      {$r['eventTime']}\n"
        . "Event:     {$r['eventType']}\n"
        . "Guests:    {$r['guests']} pax\n"
        . ( !empty($r['address']) ? "Address:   {$r['address']}\n" : '' )
        . "Estimate:  ₱" . number_format((float) $r['estimatedTotal'], 2) . "\n"
        . "\n"
        . "— Pio De Roda Cafe and Restaurant\n"
        . "508 De Ocampo St., Indang, Cavite 4122\n"
        . "(046) 415 0390 / (0926) 684 5833\n";

    // ---------- HTML ----------
    $addressRow = '';
    if (!empty($r['address'])) {
        $addressRow = pdrEmailRow('Address', $e($r['address']));
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<body style="margin:0;padding:0;background-color:#F6ECDD;font-family:Georgia,'Times New Roman',serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F6ECDD;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#FBF6EE;border:1px solid rgba(91,8,8,0.14);border-radius:12px;overflow:hidden;">

          <!-- Header -->
          <tr>
            <td style="background-color:#5B0808;padding:22px 28px;">
              <div style="font-size:22px;color:#F6ECDD;letter-spacing:2px;font-weight:bold;">PIO DE RODA</div>
              <div style="font-size:11px;color:#D9A84E;letter-spacing:3px;margin-top:2px;">CAFE AND RESTAURANT</div>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:28px;">
              <h1 style="margin:0 0 14px;font-size:22px;color:#5B0808;font-weight:normal;">Thank you, {$name}!</h1>

              <p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#2A0E0E;">
                We have received your reservation. Your booking is now
                <strong>PENDING CONFIRMATION</strong>.
              </p>

              <p style="margin:0 0 6px;font-size:14px;line-height:1.6;color:#2A0E0E;">
                <strong>What happens next:</strong>
              </p>
              <ol style="margin:0 0 18px;padding-left:20px;font-size:14px;line-height:1.7;color:#2A0E0E;">
                <li>Our staff will review and <strong>confirm your reservation</strong>.</li>
                <li>Once confirmed, we will contact you so you can <strong>proceed to payment</strong>.</li>
                <li>Please wait for the confirmation before making any payment.</li>
              </ol>

              <!-- Credentials box -->
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F6ECDD;border:1px solid #B8862E;border-radius:10px;margin:0 0 18px;">
                <tr>
                  <td align="center" style="padding:18px 14px;">
                    <div style="font-size:10px;letter-spacing:2px;color:#6B4C4C;margin-bottom:4px;">RESERVATION ID</div>
                    <div style="font-size:22px;letter-spacing:2px;color:#5B0808;font-weight:bold;">{$id}</div>
                    <div style="font-size:10px;letter-spacing:2px;color:#6B4C4C;margin:14px 0 4px;">BOOKING PIN</div>
                    <div style="font-size:26px;letter-spacing:8px;color:#5B0808;font-weight:bold;">{$pin}</div>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 20px;font-size:13px;line-height:1.6;color:#8C1414;background-color:rgba(140,20,20,0.07);border-radius:8px;padding:10px 12px;">
                Please save your Reservation ID and Booking PIN. You will need both
                to check your booking status later. <strong>The PIN will not be shown again.</strong>
              </p>

              <!-- Details -->
              <div style="font-size:11px;letter-spacing:2px;color:#6B4C4C;margin:0 0 8px;">RESERVATION DETAILS</div>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:Helvetica,Arial,sans-serif;font-size:13px;color:#2A0E0E;">
                __ROWS__
              </table>
            </td>
          </tr>

          <!-- Footer -->
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

    $rows = pdrEmailRow('Name', $e($r['customerName']))
        . pdrEmailRow('Main Service', $e($r['mainService']))
        . pdrEmailRow('Package', $e($r['selectedPackage']))
        . pdrEmailRow('Event Date', $e($r['eventDate']))
        . pdrEmailRow('Event Time', $e($r['eventTime']))
        . pdrEmailRow('Event Type', $e($r['eventType']))
        . pdrEmailRow('Guests', $e($r['guests']) . ' pax')
        . $addressRow
        . pdrEmailRow('Estimated Total', '&#8369;' . number_format((float) $r['estimatedTotal'], 2), true);

    $html = str_replace('__ROWS__', $rows, $html);

    return ['subject' => $subject, 'html' => $html, 'text' => $text];
}

/** One label/value row of the details table. */
function pdrEmailRow($label, $value, $strong = false)
{
    $labelStyle = 'padding:7px 0;border-bottom:1px solid rgba(91,8,8,0.10);color:#6B4C4C;width:40%;';
    $valueStyle = 'padding:7px 0;border-bottom:1px solid rgba(91,8,8,0.10);color:#2A0E0E;'
        . ($strong ? 'font-weight:bold;color:#5B0808;' : '');
    $open = $strong ? '<strong>' : '';
    $close = $strong ? '</strong>' : '';
    return '<tr><td style="' . $labelStyle . '">' . $label . '</td>'
        . '<td style="' . $valueStyle . '">' . $open . $value . $close . '</td></tr>';
}
