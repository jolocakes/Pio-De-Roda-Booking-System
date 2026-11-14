<?php
/**
 * Customer-facing printable receipt / invoice (T-38).
 *
 * POST only, guarded by the same Reservation ID + Booking PIN pair as the
 * Booking Status page. It never exposes the PIN, and it only reads.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../api/lookup_guard.php';

function receiptH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function receiptMoney($value): string
{
    return '₱' . number_format((float) $value, 2);
}

$reservationId = pdrLookupNormalizeId((string) ($_POST['reservationId'] ?? ''));
$bookingPin = trim((string) ($_POST['bookingPin'] ?? ''));
$error = '';
$reservation = null;
$payments = [];
$paidTotal = 0.0;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    $error = 'Open this receipt from your Booking Status page.';
} elseif ($reservationId === '' || $bookingPin === '') {
    $error = 'Reservation ID and Booking PIN are required.';
} else {
    $lock = pdrLookupGuard($pdo, $reservationId);
    if ($lock !== null) {
        pdrLookupReject($lock);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ? LIMIT 1");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$reservation || !password_verify($bookingPin, (string) $reservation['booking_pin_hash'])) {
            pdrLookupRecord($pdo, $reservationId, false);
            $error = 'No reservation matched that ID and PIN.';
            $reservation = null;
        } else {
            pdrLookupRecord($pdo, $reservationId, true);
            try {
                $payStmt = $pdo->prepare(
                    "SELECT provider, payment_type, payment_channel, amount, paid_amount,
                            status, paid_at, created_at
                       FROM payments
                      WHERE reservation_id = ?
                      ORDER BY id ASC"
                );
                $payStmt->execute([$reservationId]);
                $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($payments as $payment) {
                    if (($payment['status'] ?? '') === 'Verified') {
                        $paidTotal += (float) ($payment['paid_amount'] ?? $payment['amount'] ?? 0);
                    }
                }
            } catch (Throwable $e) {
                $payments = [];
            }
        }
    } catch (Throwable $e) {
        error_log('Customer receipt error: ' . $e->getMessage());
        $error = 'The receipt could not be loaded right now.';
    }
}

$balance = $reservation ? max(0, (float) $reservation['estimated_total'] - $paidTotal) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?php echo receiptH($reservationId); ?> | Pio De Roda</title>
<style>
  :root { --maroon:#7b1113; --gold:#b8860b; --ink:#1f2937; --muted:#6b7280; --line:#e5e7eb; }
  * { box-sizing:border-box; }
  body { margin:0; background:#f3f4f6; color:var(--ink); font-family:'Work Sans',Arial,sans-serif; }
  .sheet { max-width:760px; margin:28px auto; background:#fff; border:1px solid var(--line); border-radius:10px; padding:34px 38px; }
  .head { display:flex; justify-content:space-between; gap:20px; border-bottom:2px solid var(--maroon); padding-bottom:16px; }
  .brand h1 { margin:0; font-size:24px; color:var(--maroon); }
  .brand p { margin:4px 0 0; color:var(--muted); font-size:13px; }
  .meta { text-align:right; font-size:13px; color:var(--muted); }
  .meta strong { display:block; color:var(--ink); font-size:16px; letter-spacing:.04em; }
  h2 { font-size:14px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:24px 0 8px; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  th, td { text-align:left; padding:8px 6px; border-bottom:1px solid var(--line); vertical-align:top; }
  th { color:var(--muted); font-weight:600; }
  .money { text-align:right; white-space:nowrap; }
  .totals { margin-top:18px; margin-left:auto; width:min(320px,100%); }
  .totals .row { display:flex; justify-content:space-between; padding:7px 0; font-size:14px; }
  .totals .row.grand { border-top:2px solid var(--maroon); margin-top:6px; padding-top:12px; font-weight:700; font-size:16px; color:var(--maroon); }
  .actions { display:flex; gap:10px; justify-content:center; margin:22px 0 0; }
  button { padding:10px 18px; border:0; border-radius:7px; background:var(--maroon); color:#fff; font-weight:600; cursor:pointer; }
  button.secondary { background:#e5e7eb; color:#374151; }
  .error { max-width:640px; margin:40px auto; background:#fff; border:1px solid #fecaca; color:#991b1b; padding:22px; border-radius:10px; }
  .note { color:var(--muted); font-size:12px; margin-top:24px; }
  /* Print / Save-as-PDF: the page margin keeps the receipt off the paper
     edge. Without @page the sheet was printed flush against the paper. */
  @page { size: A4 portrait; margin: 15mm 14mm; }
  @media print {
    html, body { background:#fff; margin:0; padding:0; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .sheet { border:0; border-radius:0; margin:0; padding:0; max-width:none; box-shadow:none; }
    .actions, .toolbar { display:none; }
    .head, tr, .totals, .note { break-inside: avoid; page-break-inside: avoid; }
    h2 { break-after: avoid; page-break-after: avoid; }
  }
</style>
</head>
<body>
<?php if ($error !== ''): ?>
  <div class="error">
    <h2 style="color:#991b1b;margin-top:0;">Receipt unavailable</h2>
    <p><?php echo receiptH($error); ?></p>
    <p><a href="booking-status.html">Go to Booking Status</a></p>
  </div>
<?php else: ?>
  <div class="sheet">
    <div class="head">
      <div class="brand">
        <h1>Pio De Roda Cafe &amp; Restaurant</h1>
        <p>Indang, Cavite, Philippines</p>
      </div>
      <div class="meta">
        <strong>RECEIPT</strong>
        <?php echo receiptH($reservation['reservation_id']); ?><br>
        Issued <?php echo receiptH(date('M d, Y')); ?>
      </div>
    </div>

    <h2>Customer</h2>
    <table>
      <tr><th style="width:140px;">Name</th><td><?php echo receiptH($reservation['customer_name']); ?></td></tr>
      <tr><th>Email</th><td><?php echo receiptH($reservation['email']); ?></td></tr>
      <tr><th>Contact</th><td><?php echo receiptH($reservation['contact_number']); ?></td></tr>
      <?php if (!empty($reservation['address'])): ?>
      <tr><th>Address</th><td><?php echo receiptH($reservation['address']); ?></td></tr>
      <?php endif; ?>
    </table>

    <h2>Booking</h2>
    <table>
      <tr><th style="width:140px;">Service</th><td><?php echo receiptH($reservation['main_service']); ?></td></tr>
      <tr><th>Package</th><td><?php echo receiptH($reservation['selected_package'] ?? '—'); ?></td></tr>
      <tr><th>Event date</th><td><?php echo receiptH(date('M d, Y', strtotime((string) $reservation['event_date']))); ?></td></tr>
      <tr><th>Event time</th><td><?php echo receiptH($reservation['event_time']); ?></td></tr>
      <tr><th>Guests</th><td><?php echo (int) $reservation['guests']; ?> pax</td></tr>
      <tr><th>Status</th><td><?php echo receiptH($reservation['status']); ?></td></tr>
    </table>

    <h2>Payments recorded</h2>
    <table>
      <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th>Status</th><th class="money">Amount</th></tr></thead>
      <tbody>
        <?php if (!$payments): ?>
          <tr><td colspan="5" style="color:#6b7280;">No payments have been recorded yet.</td></tr>
        <?php else: ?>
          <?php foreach ($payments as $payment): ?>
            <tr>
              <td><?php echo receiptH($payment['paid_at'] ?: $payment['created_at']); ?></td>
              <td><?php echo receiptH($payment['payment_channel'] ?: $payment['provider'] ?: '—'); ?></td>
              <td><?php echo receiptH($payment['payment_type'] ?: '—'); ?></td>
              <td><?php echo receiptH($payment['status']); ?></td>
              <td class="money"><?php echo receiptMoney($payment['paid_amount'] ?? $payment['amount']); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="totals">
      <div class="row"><span>Estimated total</span><span><?php echo receiptMoney($reservation['estimated_total']); ?></span></div>
      <div class="row"><span>Verified payments</span><span><?php echo receiptMoney($paidTotal); ?></span></div>
      <div class="row grand"><span>Balance</span><span><?php echo receiptMoney($balance); ?></span></div>
    </div>

    <p class="note">This receipt reflects the records currently stored by Pio De Roda Cafe &amp; Restaurant. Please present it to staff if you need a correction. Payment status is confirmed by our staff or payment provider.</p>

    <div class="actions">
      <button type="button" onclick="window.print()">Print / Save as PDF</button>
      <button type="button" class="secondary" onclick="window.close()">Close</button>
    </div>
  </div>
<?php endif; ?>
</body>
</html>
