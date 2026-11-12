<?php
/**
 * Booking capacity rules — the single source of truth for availability.
 *
 * POLICY (agreed with Pio De Roda):
 *   1. A date holds at most MAX_SERVICES_PER_DAY active bookings (Pending or
 *      Confirmed). At that count the date is fully taken.
 *   2. A service marked `services.is_exclusive = 1` is exclusive per DATE: once
 *      such a booking exists on a date, no further booking of any service may
 *      take that date. One customer per exclusive-service day.
 *   3. Time slots are blocked PER SERVICE: a booking at 14:00 blocks 14:00
 *      only for that same service. Two different services may share 14:00.
 *
 * T-23: exclusivity is a catalog flag, NOT a hardcoded service name. Renaming
 * the venue (or adding another date-exclusive service) changes nothing here.
 *
 * Every place that asks "can this be booked?" MUST go through these helpers so
 * the landing-page calendar, the booking calendar, the time picker and the
 * submit-time conflict check can never disagree with each other.
 */

// How many active bookings one date may hold (rule 1).
define('PDR_MAX_SERVICES_PER_DAY', 2);

// T-40: booking window. The customer may book at least this many days ahead and
// at most this many days ahead. Admin edits are intentionally allowed to
// override these (staff may accept a late booking by phone).
define('PDR_MIN_LEAD_DAYS', 3);
define('PDR_MAX_HORIZON_DAYS', 365);

// T-37: self-service policy (customer, with Reservation ID + PIN).
define('PDR_CANCEL_MIN_DAYS', 7);
define('PDR_RESCHEDULE_MIN_DAYS', 3);
define('PDR_MAX_RESCHEDULES', 1);

// Statuses that occupy a slot. Cancelled/Completed/Rejected free the slot.
function pdrActiveStatuses() {
    return ['Pending', 'Confirmed'];
}

/**
 * T-24: why is this date blocked by staff, or null if it is not?
 * Fail-safe: a missing migration reads as "no blackout" instead of breaking
 * every calendar request.
 */
function pdrBlackoutReason($pdo, $eventDate) {
    try {
        $stmt = $pdo->prepare("SELECT reason FROM blackout_dates WHERE blackout_date = ? LIMIT 1");
        $stmt->execute([$eventDate]);
        $reason = $stmt->fetchColumn();
        return $reason === false ? null : (string) ($reason ?? '');
    } catch (Throwable $tableMissing) {
        return null;
    }
}

/** T-24: blackout dates in a month, keyed by date. */
function pdrBlackoutDates($pdo, $year, $month): array {
    try {
        $stmt = $pdo->prepare(
            "SELECT blackout_date, reason
               FROM blackout_dates
              WHERE YEAR(blackout_date) = ? AND MONTH(blackout_date) = ?"
        );
        $stmt->execute([(int) $year, (int) $month]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['blackout_date']] = (string) ($row['reason'] ?? '');
        }
        return $out;
    } catch (Throwable $tableMissing) {
        return [];
    }
}

/**
 * T-23: the exclusive services, read from the catalog.
 *
 * Returns ['ids' => [...], 'names' => [...], 'enabled' => bool].
 * Cached per request because every calendar request calls this more than once.
 * If migration 004 has not run yet, it falls back to the old venue name so the
 * site keeps working instead of throwing.
 */
function pdrExclusiveServices($pdo) {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = ['ids' => [], 'names' => [], 'enabled' => false];
    try {
        $rows = $pdo->query(
            "SELECT id, name FROM services WHERE is_exclusive = 1 AND is_active = 1"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cached['ids'][] = (string) $row['id'];
            $cached['names'][] = (string) $row['name'];
        }
    } catch (Throwable $migrationMissing) {
        // Pre-004 database: keep the historical behavior.
        $cached['ids'] = [];
        $cached['names'] = ['Venue Rentals'];
    }
    $cached['enabled'] = count($cached['ids']) > 0 || count($cached['names']) > 0;
    return $cached;
}

/**
 * SQL predicate that is true when a reservation row holds an exclusive date.
 *
 * Uses service_id (stable across renames) and falls back to the service NAME
 * only for legacy rows saved before service_id was populated.
 *
 * Returns ['sql' => string, 'params' => array].
 */
function pdrExclusiveCase($pdo, string $alias = 'r') {
    $cfg = pdrExclusiveServices($pdo);
    if (!$cfg['enabled']) {
        return ['sql' => '0 = 1', 'params' => []];
    }

    $parts = [];
    $params = [];
    if ($cfg['ids']) {
        $parts[] = "{$alias}.service_id IN (" . implode(',', array_fill(0, count($cfg['ids']), '?')) . ")";
        $params = array_merge($params, $cfg['ids']);
    }
    if ($cfg['names']) {
        $parts[] = "({$alias}.service_id IS NULL AND {$alias}.main_service IN (" .
            implode(',', array_fill(0, count($cfg['names']), '?')) . "))";
        $params = array_merge($params, $cfg['names']);
    }

    return ['sql' => '(' . implode(' OR ', $parts) . ')', 'params' => $params];
}

/**
 * T-40: is a date inside the customer booking window?
 *
 * Returns null when allowed, or 'TOO_SOON' / 'TOO_FAR' / 'INVALID'.
 * Compares plain YYYY-MM-DD strings in the business timezone (Asia/Manila),
 * never UTC, so "today" does not shift by eight hours.
 */
function pdrBookingWindowError($eventDate) {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $eventDate);
    if (!$date || $date->format('Y-m-d') !== (string) $eventDate) {
        return 'INVALID';
    }

    $today = new DateTimeImmutable('today');
    $daysAhead = (int) $today->diff($date)->format('%r%a');

    if ($daysAhead < PDR_MIN_LEAD_DAYS) {
        return 'TOO_SOON';
    }
    if ($daysAhead > PDR_MAX_HORIZON_DAYS) {
        return 'TOO_FAR';
    }
    return null;
}

/**
 * T-37: whole days from today (Asia/Manila) to the given date. Negative when
 * the date is in the past.
 */
function pdrDaysUntilDate($eventDate): int {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $eventDate);
    if (!$date) {
        return PHP_INT_MIN;
    }
    $today = new DateTimeImmutable('today');
    return (int) $today->diff($date)->format('%r%a');
}

/**
 * Active bookings grouped by date, with a per-date venue flag.
 *
 * Returns [ 'YYYY-MM-DD' => ['count' => int, 'hasVenue' => bool], ... ]
 * Only dates that actually have at least one active booking are listed.
 */
function pdrDateLoad($pdo, ?string $excludeReservationId = null) {
    $statuses = pdrActiveStatuses();
    $statusIn = implode(',', array_fill(0, count($statuses), '?'));
    $exclusive = pdrExclusiveCase($pdo, 'r');
    $stmt = $pdo->prepare(
        "SELECT r.event_date,
                COUNT(*) AS cnt,
                MAX(CASE WHEN {$exclusive['sql']} THEN 1 ELSE 0 END) AS has_venue
           FROM reservations r
          WHERE r.status IN ($statusIn)
            AND (? IS NULL OR r.reservation_id <> ?)
          GROUP BY r.event_date"
    );
    $stmt->execute(array_merge(
        $exclusive['params'],
        $statuses,
        [$excludeReservationId, $excludeReservationId]
    ));

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
    // T-24: staff blackout closes the date for every service.
    $blackout = pdrBlackoutReason($pdo, $eventDate);
    if ($blackout !== null) {
        return [
            'taken' => true,
            'count' => 0,
            'hasVenue' => false,
            'reason' => 'BLACKOUT',
            'blackoutReason' => $blackout,
        ];
    }

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
    $exclusive = pdrExclusiveCase($pdo, 'r');
    $stmt = $pdo->prepare(
        "SELECT r.event_date,
                COUNT(*) AS cnt,
                MAX(CASE WHEN {$exclusive['sql']} THEN 1 ELSE 0 END) AS has_venue
           FROM reservations r
          WHERE r.status IN ($in)
            AND YEAR(r.event_date) = ? AND MONTH(r.event_date) = ?
          GROUP BY r.event_date"
    );
    $stmt->execute(array_merge($exclusive['params'], $statuses, [(int)$year, (int)$month]));

    $dates = [];
    $meta  = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $count    = (int) $r['cnt'];
        $hasVenue = (bool) $r['has_venue'];
        $taken    = $hasVenue || $count >= PDR_MAX_SERVICES_PER_DAY;
        $meta[$r['event_date']] = ['count' => $count, 'hasVenue' => $hasVenue, 'taken' => $taken];
        if ($taken) $dates[] = $r['event_date'];
    }

    // T-24: staff blackouts always win and are reported separately so the UI
    // can say "closed by staff" instead of "fully booked".
    foreach (pdrBlackoutDates($pdo, $year, $month) as $blackoutDate => $reason) {
        $existing = $meta[$blackoutDate] ?? ['count' => 0, 'hasVenue' => false];
        $meta[$blackoutDate] = [
            'count' => $existing['count'],
            'hasVenue' => $existing['hasVenue'],
            'taken' => true,
            'blackout' => true,
            'blackoutReason' => $reason,
        ];
        if (!in_array($blackoutDate, $dates, true)) {
            $dates[] = $blackoutDate;
        }
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
