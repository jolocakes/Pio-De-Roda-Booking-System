<?php
/**
 * Booking capacity rules — the single source of truth for availability.
 *
 * POLICY (agreed with Pio De Roda):
 *   1. A date holds at most MAX_SERVICES_PER_DAY active bookings (Pending or
 *      Confirmed). At that count the date is fully taken.
 *   2. Venue Rentals is exclusive per DATE: once a venue booking exists on a
 *      date, no further booking of any service may take that date. One
 *      customer per venue-day.
 *   3. Time slots are blocked PER SERVICE: a booking at 14:00 blocks 14:00
 *      only for that same service. Two different services may share 14:00.
 *
 * Every place that asks "can this be booked?" MUST go through these helpers so
 * the landing-page calendar, the booking calendar, the time picker and the
 * submit-time conflict check can never disagree with each other.
 */

// How many active bookings one date may hold (rule 1).
define('PDR_MAX_SERVICES_PER_DAY', 2);

// The service that is exclusive per date (rule 2). Compared against
// reservations.main_service; null disables the venue rule entirely.
define('PDR_EXCLUSIVE_SERVICE', 'Venue Rentals');

// Statuses that occupy a slot. Cancelled/Completed free the slot.
function pdrActiveStatuses() {
    return ['Pending', 'Confirmed'];
}

/**
 * Active bookings grouped by date, with a per-date venue flag.
 *
 * Returns [ 'YYYY-MM-DD' => ['count' => int, 'hasVenue' => bool], ... ]
 * Only dates that actually have at least one active booking are listed.
 */
function pdrDateLoad($pdo, ?string $excludeReservationId = null) {
    $statuses = pdrActiveStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare(
        "SELECT event_date,
                COUNT(*) AS cnt,
                MAX(CASE WHEN main_service = ? THEN 1 ELSE 0 END) AS has_venue
           FROM reservations
          WHERE status IN ($in)
            AND (? IS NULL OR reservation_id <> ?)
          GROUP BY event_date"
    );
    $stmt->execute(array_merge([PDR_EXCLUSIVE_SERVICE], $statuses, [$excludeReservationId, $excludeReservationId]));

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[$r['event_date']] = [
            'count'     => (int) $r['cnt'],
            'hasVenue'  => (bool) $r['has_venue'],
        ];
    }
    return $out;
}

/**
 * Is this date fully taken? (rules 1 and 2)
 *
 * Returns ['taken' => bool, 'count' => int, 'hasVenue' => bool,
 *          'reason' => 'VENUE'|'FULL'|null]
 */
function pdrDateTaken($pdo, $eventDate, ?string $excludeReservationId = null) {
    $load = pdrDateLoad($pdo, $excludeReservationId);
    $row  = isset($load[$eventDate]) ? $load[$eventDate] : ['count' => 0, 'hasVenue' => false];

    if ($row['hasVenue']) {
        return ['taken' => true, 'count' => $row['count'], 'hasVenue' => true,  'reason' => 'VENUE'];
    }
    if ($row['count'] >= PDR_MAX_SERVICES_PER_DAY) {
        return ['taken' => true, 'count' => $row['count'], 'hasVenue' => false, 'reason' => 'FULL'];
    }
    return ['taken' => false, 'count' => $row['count'], 'hasVenue' => false, 'reason' => null];
}

/**
 * Time slots already occupied on a date BY A GIVEN SERVICE (rule 3).
 *
 * Returns the list of 'HH:MM' strings that service already holds that day.
 * Other services' bookings on the same date/time do NOT appear here.
 */
function pdrTakenTimes($pdo, $eventDate, $mainService, ?string $excludeReservationId = null) {
    $statuses = pdrActiveStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT event_time
           FROM reservations
          WHERE event_date = ? AND main_service = ? AND status IN ($in)
            AND (? IS NULL OR reservation_id <> ?)"
    );
    $stmt->execute(array_merge([$eventDate, $mainService], $statuses, [$excludeReservationId, $excludeReservationId]));
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Every time blocked on a date by ANY service — for the time picker, which is
 * rendered before the customer's own service is known to be exclusive.
 * Merged view: a slot is blocked if ANY active booking holds it.
 */
function pdrAllTakenTimes($pdo, $eventDate) {
    $statuses = pdrActiveStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare(
        "SELECT DISTINCT event_time
           FROM reservations
          WHERE event_date = ? AND status IN ($in)"
    );
    $stmt->execute(array_merge([$eventDate], $statuses));
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Full availability payload for a month. Drives BOTH calendars and the time
 * picker so there is exactly one definition of "unavailable".
 *
 * Returns:
 *   dates  => ['YYYY-MM-DD', ...] dates that are fully taken (gray)
 *   times  => ['YYYY-MM-DD' => ['HH:MM' => ['serviceA','serviceB'], ...]]
 *   meta   => ['YYYY-MM-DD' => ['count' => n, 'hasVenue' => bool, 'taken' => bool]]
 */
function pdrAvailabilityPayload($pdo, $year, $month) {
    $statuses = pdrActiveStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));

    // --- date-level load (rule 1 + rule 2) ---
    $stmt = $pdo->prepare(
        "SELECT event_date,
                COUNT(*) AS cnt,
                MAX(CASE WHEN main_service = ? THEN 1 ELSE 0 END) AS has_venue
           FROM reservations
          WHERE status IN ($in)
            AND YEAR(event_date) = ? AND MONTH(event_date) = ?
          GROUP BY event_date"
    );
    $stmt->execute(array_merge([PDR_EXCLUSIVE_SERVICE], $statuses, [(int)$year, (int)$month]));

    $dates = [];
    $meta  = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $count    = (int) $r['cnt'];
        $hasVenue = (bool) $r['has_venue'];
        $taken    = $hasVenue || $count >= PDR_MAX_SERVICES_PER_DAY;
        $meta[$r['event_date']] = ['count' => $count, 'hasVenue' => $hasVenue, 'taken' => $taken];
        if ($taken) $dates[] = $r['event_date'];
    }

    // --- time-level load (rule 3) ---
    $stmt = $pdo->prepare(
        "SELECT event_date, event_time, main_service
           FROM reservations
          WHERE status IN ($in)
            AND YEAR(event_date) = ? AND MONTH(event_date) = ?"
    );
    $stmt->execute(array_merge($statuses, [(int)$year, (int)$month]));

    $times = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $times[$r['event_date']][$r['event_time']][] = $r['main_service'];
    }

    return ['dates' => $dates, 'times' => $times, 'meta' => $meta];
}
