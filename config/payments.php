<?php
/**
 * Shared payment-total helpers.
 *
 * reservations.amount_paid / payment_status are a cached summary of the
 * verified rows in the payments table. The Xendit webhook and the admin
 * "Record Payment" action both use this helper, so a manual (cash/GCash/bank)
 * payment is never wiped out by a later gateway callback — and vice versa.
 */

if (!function_exists('pdrPaymentStatus')) {
    /**
     * Map a collected amount to the status the rest of the app already uses:
     * Unpaid / Partially Paid / Paid.
     */
    function pdrPaymentStatus(float $total, float $paid): string
    {
        if ($paid <= 0.009) {
            return 'Unpaid';
        }
        if ($total > 0 && $paid + 0.01 >= $total) {
            return 'Paid';
        }
        return 'Partially Paid';
    }
}

if (!function_exists('pdrPaymentAmountSql')) {
    /**
     * SQL expression for what a payment row actually collected. `paid_amount`
     * is the gateway-reported figure; older/fallback rows only have `amount`.
     */
    function pdrPaymentAmountSql(string $alias = 'p'): string
    {
        return "CASE WHEN {$alias}.paid_amount IS NOT NULL AND {$alias}.paid_amount > 0"
            . " THEN {$alias}.paid_amount ELSE {$alias}.amount END";
    }
}

if (!function_exists('pdrRecomputeReservationPayment')) {
    /**
     * Recalculate reservations.amount_paid + payment_status from every
     * Verified payment row (manual and Xendit). Returns the new totals, or
     * null when the reservation does not exist.
     */
    function pdrRecomputeReservationPayment(PDO $pdo, string $reservationId): ?array
    {
        $sum = $pdo->prepare(
            "SELECT COALESCE(SUM(" . pdrPaymentAmountSql('p') . "), 0)
               FROM payments p
              WHERE p.reservation_id = ? AND p.status = 'Verified'"
        );
        $sum->execute([$reservationId]);
        $paid = round((float) $sum->fetchColumn(), 2);

        $r = $pdo->prepare("SELECT estimated_total FROM reservations WHERE reservation_id = ?");
        $r->execute([$reservationId]);
        $total = $r->fetchColumn();
        if ($total === false) {
            return null;
        }
        $total = (float) $total;

        $status = pdrPaymentStatus($total, $paid);
        $upd = $pdo->prepare(
            "UPDATE reservations
                SET amount_paid = ?, payment_status = ?
              WHERE reservation_id = ?"
        );
        $upd->execute([$paid, $status, $reservationId]);

        return [
            'estimatedTotal' => round($total, 2),
            'amountPaid'     => $paid,
            'balance'        => max(0.0, round($total - $paid, 2)),
            'paymentStatus'  => $status,
        ];
    }
}

if (!function_exists('pdrXenditInvoice')) {
    /**
     * Fetch one Xendit invoice. Returns null on any network/auth/parse error so
     * callers can fall back to the cached database state.
     */
    function pdrXenditInvoice(array $config, string $invoiceId): ?array
    {
        $key = (string) ($config['secret_key'] ?? '');
        if ($invoiceId === '' || $key === '' || strpos($key, 'REPLACE') !== false) {
            return null;
        }

        $base = rtrim((string) ($config['base_url'] ?? 'https://api.xendit.co'), '/');
        $ch = curl_init($base . '/v2/invoices/' . rawurlencode($invoiceId));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $key . ':',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '' || $code >= 300) {
            return null;
        }
        $data = json_decode((string) $body, true);
        return is_array($data) ? $data : null;
    }
}

if (!function_exists('pdrSyncXenditPayments')) {
    /**
     * Reconcile Pending Xendit rows with the gateway.
     *
     * The invoice callback is best-effort: the public webhook tunnel can be
     * down, sleep, or rotate its hostname while the customer is checking out.
     * Without this, a paid invoice stays Pending in our ledger forever and the
     * result page keeps saying "Unpaid". Called whenever payment status is read
     * (customer pages + admin history).
     *
     * @return array{checked:int,settled:int,expired:int,review:int,totals:?array}
     */
    function pdrSyncXenditPayments(PDO $pdo, string $reservationId): array
    {
        $out = ['checked' => 0, 'settled' => 0, 'expired' => 0, 'review' => 0, 'totals' => null];

        try {
            $cfgFile = __DIR__ . '/xendit.php';
            if (!is_file($cfgFile)) {
                return $out;
            }
            $config = require $cfgFile;
            $key = (string) ($config['secret_key'] ?? '');
            if ($key === '' || strpos($key, 'REPLACE') !== false) {
                return $out;
            }

            $stmt = $pdo->prepare(
                "SELECT id, provider_reference, amount
                   FROM payments
                  WHERE reservation_id = ? AND provider = 'xendit'
                    AND status = 'Pending' AND provider_reference IS NOT NULL
                  ORDER BY id DESC
                  LIMIT 10"
            );
            $stmt->execute([$reservationId]);
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$pending) {
                return $out;
            }

            $settle = $pdo->prepare(
                "UPDATE payments
                    SET status = 'Verified', paid_amount = ?, payment_channel = ?, paid_at = ?, raw_response = ?
                  WHERE id = ? AND status = 'Pending'"
            );
            $review = $pdo->prepare(
                "UPDATE payments
                    SET status = 'For Review', paid_amount = ?, payment_channel = ?, paid_at = ?, raw_response = ?
                  WHERE id = ? AND status = 'Pending'"
            );
            $expire = $pdo->prepare(
                "UPDATE payments SET status = 'Rejected', raw_response = ? WHERE id = ? AND status = 'Pending'"
            );

            foreach ($pending as $row) {
                $out['checked']++;
                $invoice = pdrXenditInvoice($config, (string) $row['provider_reference']);
                if ($invoice === null) {
                    continue;
                }

                $status  = strtoupper((string) ($invoice['status'] ?? ''));
                $raw     = json_encode($invoice, JSON_UNESCAPED_UNICODE);
                $channel = $invoice['payment_channel'] ?? $invoice['payment_method'] ?? null;
                $channel = is_string($channel) ? $channel : null;
                $paidAt  = (string) ($invoice['paid_at'] ?? '');
                $paidAtTs = $paidAt !== '' ? strtotime($paidAt) : false;
                $paidAtSql = $paidAtTs !== false ? date('Y-m-d H:i:s', $paidAtTs) : date('Y-m-d H:i:s');

                if (in_array($status, ['PAID', 'SETTLED'], true)) {
                    $expected = round((float) $row['amount'], 2);
                    $reported = isset($invoice['paid_amount'])
                        ? round((float) $invoice['paid_amount'], 2)
                        : round((float) ($invoice['amount'] ?? 0), 2);

                    if ($expected > 0 && abs($reported - $expected) <= 0.01) {
                        $settle->execute([$reported, $channel, $paidAtSql, $raw, $row['id']]);
                        if ($settle->rowCount() > 0) {
                            $out['settled']++;
                        }
                    } else {
                        // Same rule as the webhook: never auto-verify an amount
                        // that does not match the invoice we created.
                        $review->execute([$reported, $channel, $paidAtSql, $raw, $row['id']]);
                        if ($review->rowCount() > 0) {
                            $out['review']++;
                        }
                    }
                } elseif ($status === 'EXPIRED') {
                    $expire->execute([$raw, $row['id']]);
                    if ($expire->rowCount() > 0) {
                        $out['expired']++;
                    }
                } else {
                    // Still PENDING: keep the latest payload for support.
                    $upd = $pdo->prepare("UPDATE payments SET raw_response = ? WHERE id = ?");
                    $upd->execute([$raw, $row['id']]);
                }
            }

            if ($out['settled'] > 0) {
                $out['totals'] = pdrRecomputeReservationPayment($pdo, $reservationId);
                $pdo->prepare("UPDATE reservations SET payment_method = 'Xendit' WHERE reservation_id = ?")
                    ->execute([$reservationId]);
            }
        } catch (Throwable $e) {
            // Reconciliation is an enhancement; never break the page because
            // Xendit is unreachable or a migration is missing.
            error_log('pdrSyncXenditPayments(' . $reservationId . '): ' . $e->getMessage());
        }

        return $out;
    }
}
