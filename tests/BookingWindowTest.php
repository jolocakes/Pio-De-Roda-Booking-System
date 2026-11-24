<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingWindowTest extends TestCase
{
    private static function dateFromToday(int $days): string
    {
        return (new DateTimeImmutable('today'))
            ->modify(($days >= 0 ? '+' : '') . $days . ' days')
            ->format('Y-m-d');
    }

    #[DataProvider('dayOffsets')]
    public function testBookingWindow(int $daysAhead, ?string $expected): void
    {
        self::assertSame($expected, pdrBookingWindowError(self::dateFromToday($daysAhead)));
    }

    public static function dayOffsets(): array
    {
        return [
            'same day is too soon'      => [0, 'TOO_SOON'],
            'inside lead time'          => [PDR_MIN_LEAD_DAYS - 1, 'TOO_SOON'],
            'first allowed day'         => [PDR_MIN_LEAD_DAYS, null],
            'well inside horizon'       => [90, null],
            'last allowed day'          => [PDR_MAX_HORIZON_DAYS, null],
            'past the horizon'          => [PDR_MAX_HORIZON_DAYS + 1, 'TOO_FAR'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function testInvalidDatesAreRejected(string $value): void
    {
        self::assertSame('INVALID', pdrBookingWindowError($value));
    }

    public static function invalidDates(): array
    {
        return [
            'empty string'      => [''],
            'not a date'        => ['next tuesday'],
            'overflowing day'   => ['2026-02-30'],
            'missing zeroes'    => ['2026-3-7'],
        ];
    }

    #[DataProvider('dayCounts')]
    public function testDaysUntilDate(int $daysAhead, int $expected): void
    {
        self::assertSame($expected, pdrDaysUntilDate(self::dateFromToday($daysAhead)));
    }

    public static function dayCounts(): array
    {
        return [
            'today'     => [0, 0],
            'tomorrow'  => [1, 1],
            'in a week' => [7, 7],
            'yesterday' => [-1, -1],
        ];
    }

    public function testDaysUntilInvalidDateIsSafelyNegative(): void
    {
        self::assertSame(PHP_INT_MIN, pdrDaysUntilDate('not-a-date'));
    }

    public function testActiveStatusesAreExactlyPendingAndConfirmed(): void
    {
        self::assertSame(['Pending', 'Confirmed'], pdrActiveStatuses());
    }
}
