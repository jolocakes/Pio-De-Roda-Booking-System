<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    #[DataProvider('amounts')]
    public function testStatusFromCollectedTotal(float $total, float $paid, string $expected): void
    {
        self::assertSame($expected, pdrPaymentStatus($total, $paid));
    }

    public static function amounts(): array
    {
        return [
            'nothing collected'          => [1000.0, 0.0, 'Unpaid'],
            'rounding noise stays unpaid'=> [1000.0, 0.009, 'Unpaid'],
            'first peso collected'       => [1000.0, 0.01, 'Partially Paid'],
            'half paid'                  => [1000.0, 500.0, 'Partially Paid'],
            'still short of the total'   => [1000.0, 999.98, 'Partially Paid'],
            'exact total'                => [1000.0, 1000.0, 'Paid'],
            'overpaid'                   => [1000.0, 1000.01, 'Paid'],
            'zero-price booking'         => [0.0, 0.0, 'Unpaid'],
        ];
    }

    public function testAmountSqlFallsBackToTheLegacyColumn(): void
    {
        self::assertSame(
            'CASE WHEN p.paid_amount IS NOT NULL AND p.paid_amount > 0'
                . ' THEN p.paid_amount ELSE p.amount END',
            pdrPaymentAmountSql('p')
        );
    }

    public function testAmountSqlHonoursTheAlias(): void
    {
        self::assertStringContainsString('pay.paid_amount', pdrPaymentAmountSql('pay'));
        self::assertStringContainsString('pay.amount', pdrPaymentAmountSql('pay'));
    }
}
