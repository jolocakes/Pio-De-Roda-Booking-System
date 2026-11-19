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
