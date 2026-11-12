<?php
/**
 * Printable receipt / invoice (T-38).
 *
 *   receipt.php?reservationId=PDR-2026-0001
 *
 * Read-only. Totals come from the reservation row; verified payments come from
 * the payments table. "Print / Save as PDF" uses the browser's print dialog —
 * no PDF library needed, and it works identically on every machine.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';

function rH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$reservationId = trim((string) ($_GET['reservationId'] ?? ''));
$reservation = null;
$payments = [];
$paidTotal = 0.0;
$error = '';

if ($reservationId === '') {
    $error = 'No reservation was specified.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE reservation_id = ? LIMIT 1");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$reservation) {
            $error = 'Reservation not found.';
        } else {
            try {
                $payStmt = $pdo->prepare(
                    "SELECT provider, payment_type, payment_channel, amount, paid_amount,
                            status, paid_at, created_at
                       FROM payments
                      WHERE reservation_id = ?
                      ORDER BY created_at ASC, id ASC"
                );
                $payStmt->execute([$reservationId]);
                $payments = $payStmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($payments as $p) {
                    if (in_array($p['status'], ['Verified', 'For Review'], true)) {
                        $amount = (float) ($p['paid_amount'] ?? 0);
                        if ($amount <= 0) {
                            $amount = (float) ($p['amount'] ?? 0);
                        }
                        $paidTotal += $amount;
                    }
                }
            } catch (Throwable $payErr) {
                // payments table may not exist on an older install.
                $payments = [];
            }
        }
    } catch (Throwable $e) {
        $error = 'Could not load the reservation.';
    }
}

$total = $reservation ? (float) $reservation['estimated_total'] : 0.0;
$balance = max(0, $total - $paidTotal);
$issuedAt = date('F j, Y g:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt <?php echo rH($reservationId); ?> | Pio De Roda Cafe</title>
    <style>
        :root { --maroon: #5B0808; --crimson: #8C1414; --muted: #6b7280; --line: #e5e7eb; }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 28px;
            font-family: "Work Sans", Arial, sans-serif;
            color: #1f2937; background: #f3f4f6;
        }
        .sheet {
            max-width: 780px; margin: 0 auto; background: #fff;
            border-radius: 10px; padding: 36px 40px;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
        }
        .toolbar { max-width: 780px; margin: 0 auto 14px; display: flex; gap: 10px; justify-content: space-between; }
        .toolbar a, .toolbar button {
            font: inherit; font-size: 14px; cursor: pointer; text-decoration: none;
            border-radius: 7px; padding: 9px 15px; border: 1px solid #d1d5db; background: #fff; color: #374151;
        }
        .toolbar button.primary { background: var(--crimson); border-color: var(--crimson); color: #fff; }
        .head { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid var(--maroon); padding-bottom: 16px; }
        .head h1 { margin: 0 0 4px; font-size: 22px; color: var(--maroon); letter-spacing: .01em; }
        .head p { margin: 2px 0; font-size: 12.5px; color: var(--muted); }
        .doc-title { text-align: right; }
        .doc-title strong { display: block; font-size: 20px; color: var(--crimson); letter-spacing: .06em; }
        .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin: 22px 0 6px; }
        .meta h3 { margin: 0 0 6px; font-size: 11px; letter-spacing: .12em; text-transform: uppercase; color: var(--crimson); }
        .meta p { margin: 3px 0; font-size: 13.5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 18px; font-size: 13.5px; }
        th { text-align: left; background: #f9fafb; color: #4b5563; font-size: 11.5px; letter-spacing: .06em; text-transform: uppercase; padding: 9px 10px; border-bottom: 1px solid var(--line); }
        td { padding: 9px 10px; border-bottom: 1px solid var(--line); vertical-align: top; }
        td.num, th.num { text-align: right; }
        .totals { margin-top: 18px; margin-left: auto; width: 320px; font-size: 14px; }
        .totals div { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px dashed var(--line); }
        .totals div.grand { border-bottom: 0; border-top: 2px solid var(--maroon); margin-top: 4px; padding-top: 10px; font-weight: 700; font-size: 16px; color: var(--maroon); }
        .note { margin-top: 26px; font-size: 12px; color: var(--muted); line-height: 1.6; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #eef2ff; color: #3730a3; }
        .error { max-width: 780px; margin: 40px auto; background: #fff; border-radius: 10px; padding: 30px; text-align: center; color: #991b1b; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; border-radius: 0; max-width: none; padding: 0; }
            a[href]::after { content: ""; }
        }
    </style>
</head>
<body>

<?php if ($error !== ''): ?>
    <div class="error">
        <h2>Receipt unavailable</h2>
        <p><?php echo rH($error); ?></p>
        <p><a href="reservation.php">&larr; Back to reservations</a></p>
    </div>
<?php else: ?>
    <div class="toolbar">
        <a href="reservation.php">&larr; Back to reservations</a>
        <button type="button" class="primary" onclick="window.print()">Print / Save as PDF</button>
    </div>

    <div class="sheet">
        <div class="head">
            <div>
                <h1>Pio De Roda Cafe &amp; Restaurant</h1>
                <p>508 De Ocampo St., Indang, Cavite 4122</p>
                <p>(046) 415 0390 / (0926) 684 5833</p>
                <p>pioderodabooking@gmail.com</p>
            </div>
            <div class="doc-title">
                <strong>RECEIPT</strong>
                <p><?php echo rH($reservation['reservation_id']); ?></p>
                <p>Issued <?php echo rH($issuedAt); ?></p>
                <p><span class="badge"><?php echo rH($reservation['status']); ?></span></p>
            </div>
        </div>

        <div class="meta">
            <div>
                <h3>Billed to</h3>
                <p><strong><?php echo rH($reservation['customer_name']); ?></strong></p>
                <?php if (!empty($reservation['contact_person'])): ?>
                    <p>Contact: <?php echo rH($reservation['contact_person']); ?></p>
                <?php endif; ?>
                <p><?php echo rH($reservation['contact_number']); ?></p>
                <p><?php echo rH($reservation['email']); ?></p>
                <?php if (!empty($reservation['address'])): ?>
                    <p><?php echo rH($reservation['address']); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <h3>Event</h3>
                <p><?php echo rH($reservation['main_service']); ?></p>
                <p>Package: <?php echo rH($reservation['selected_package']); ?></p>
                <p>Date: <?php echo rH($reservation['event_date']); ?> at <?php echo rH($reservation['event_time']); ?></p>
                <p>Guests: <?php echo rH($reservation['guests']); ?> pax</p>
                <?php if (!empty($reservation['event_type'])): ?>
                    <p>Type: <?php echo rH($reservation['event_type']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="num">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td>Reservation estimate — no payment recorded yet</td>
                        <td><?php echo rH($reservation['event_date']); ?></td>
                        <td><?php echo rH($reservation['payment_status'] ?? 'Unpaid'); ?></td>
                        <td class="num">₱<?php echo number_format($total, 2); ?></td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                        <?php
                        $amount = (float) ($p['paid_amount'] ?? 0);
                        if ($amount <= 0) {
                            $amount = (float) ($p['amount'] ?? 0);
                        }
                        ?>
                        <tr>
                            <td>
                                <?php echo rH($p['payment_type'] ?: 'Payment'); ?>
                                <?php if (!empty($p['provider'])): ?>
                                    <br><small style="color:#6b7280;"><?php echo rH($p['provider']); ?>
                                    <?php echo !empty($p['payment_channel']) ? ' — ' . rH($p['payment_channel']) : ''; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo rH($p['paid_at'] ?: $p['created_at']); ?></td>
                            <td><span class="badge"><?php echo rH($p['status']); ?></span></td>
                            <td class="num">₱<?php echo number_format($amount, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="totals">
            <div><span>Estimated total</span><span>₱<?php echo number_format($total, 2); ?></span></div>
            <div><span>Payments recorded</span><span>₱<?php echo number_format($paidTotal, 2); ?></span></div>
            <div class="grand"><span>Balance due</span><span>₱<?php echo number_format($balance, 2); ?></span></div>
        </div>

        <p class="note">
            This document is a system-generated receipt for the reservation above. It is not an
            official BIR sales invoice. Amounts are based on the booking estimate and payments
            recorded in the system at the time of printing. Please keep this receipt for your records.
        </p>
    </div>
<?php endif; ?>

</body>
</html>
