<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Per-service capacity rules (api/capacity.php).
 *
 * These are pure functions: no database, no .env, no HTTP. They decide which
 * start times a service can no longer take, so a regression here would either
 * double-book a venue or wrongly close a booth.
 */
final class ServiceCapacityTest extends TestCase
{
    private static function rule(int $duration, ?int $maxPerDay, ?int $maxConcurrent): array
    {
        return [
            'id' => 'test',
            'name' => 'Test Service',
            'duration' => $duration,
            'maxPerDay' => $maxPerDay,
            'maxConcurrent' => $maxConcurrent,
        ];
    }

    private static function booking(string $time, int $duration = 0): array
    {
        return ['time' => $time, 'duration' => $duration];
    }

    public function testMinutesOfDayParsesOfferedSlots(): void
    {
        self::assertSame(8 * 60, pdrMinutesOfDay('08:00'));
        self::assertSame(21 * 60, pdrMinutesOfDay('21:00'));
        self::assertNull(pdrMinutesOfDay('24:00'));
        self::assertNull(pdrMinutesOfDay('not-a-time'));
    }

    public function testOverlapIsHalfOpen(): void
    {
        // Touching ranges do not overlap: [8:00, 12:00) and [12:00, 16:00).
        self::assertFalse(pdrIntervalsOverlap(480, 720, 720, 960));
        self::assertTrue(pdrIntervalsOverlap(480, 720, 600, 840));
    }

    public function testZeroDurationOnlyClashesOnExactStart(): void
    {
        self::assertTrue(pdrIntervalsOverlap(600, 600, 600, 600));
        self::assertFalse(pdrIntervalsOverlap(600, 600, 660, 660));
        // A zero-length range inside a longer one shares its start only.
        self::assertFalse(pdrIntervalsOverlap(600, 600, 540, 660));
    }

    public function testVenueBlocksEverySlotOnceItsOneBookingExists(): void
    {
        $rule = self::rule(240, 1, 1);
        $slots = pdrTimeSlots();

        self::assertSame([], pdrBlockedStartTimes($rule, [], $slots));

        $taken = pdrBlockedStartTimes($rule, [self::booking('10:00', 240)], $slots);
        self::assertSame(array_values($slots), $taken, 'A full venue-date accepts no start time.');
    }

    public function testBoothWithConcurrencyTwoAllowsTwoAtOnce(): void
    {
        // Daily cap is 3 here so the concurrency rule is what is under test.
        $rule = self::rule(210, 3, 2);
        $slots = pdrTimeSlots();

        $one = pdrBlockedStartTimes($rule, [self::booking('10:00', 210)], $slots);
        self::assertSame([], $one, 'One booth booking leaves room for a simultaneous second.');

        $two = pdrBlockedStartTimes(
            $rule,
            [self::booking('10:00', 210), self::booking('11:00', 210)],
            $slots
        );
        // Both 10:00 and 11:00 ranges overlap 08:00–13:00, so only 14:00+ opens.
        self::assertSame(['08:00', '09:00', '10:00', '11:00', '12:00', '13:00'], $two);
    }

    public function testBoothStopsAfterTwoDailyBookings(): void
    {
        $rule = self::rule(210, 2, 2);
        $blocked = pdrBlockedStartTimes(
            $rule,
            [self::booking('10:00', 210), self::booking('14:00', 210)],
            pdrTimeSlots()
        );

        self::assertSame(array_values(pdrTimeSlots()), $blocked);
    }

    public function testIntimateCelebrationCannotOverlap(): void
    {
        $rule = self::rule(120, 2, 1);
        $blocked = pdrBlockedStartTimes($rule, [self::booking('14:00', 120)], pdrTimeSlots());

        // 12:00 ends exactly when 14:00 starts, so it stays open.
        self::assertSame(['13:00', '14:00', '15:00'], $blocked);
    }

    public function testEquipmentRentalOnlyBlocksItsStartTime(): void
    {
        // Zero duration + concurrency 1: only the exact start minute clashes.
        $rule = self::rule(0, 2, 1);
        $blocked = pdrBlockedStartTimes($rule, [self::booking('10:00', 0)], pdrTimeSlots());

        self::assertSame(['10:00'], $blocked);
    }

    public function testUncappedServiceNeverBlocksASlot(): void
    {
        $rule = self::rule(90, null, null);
        $bookings = [self::booking('09:00', 90), self::booking('10:00', 90)];

        self::assertSame([], pdrBlockedStartTimes($rule, $bookings, pdrTimeSlots()));
    }
}
