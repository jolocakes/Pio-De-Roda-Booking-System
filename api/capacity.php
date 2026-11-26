<?php
/**
 * Booking capacity rules — the single source of truth for availability.
 *
 * POLICY:
 *   1. Global daily cap: at most PDR_MAX_DAILY_BOOKINGS (2) active bookings per
 *      date across all services. When reached, the date is unavailable.
 *   2. Each service has a duration in minutes. A booking occupies
 *      [start, start + duration).
 *   3. `max_per_day` — how many active bookings of that service a date may
 *      hold. NULL means no service-specific daily cap (global cap still applies).
 *   4. `max_concurrent` — how many bookings of that service may overlap in
 *      time. NULL means no concurrency cap.
 *   5. A staff blackout closes the date for every service.
 *
 * Every place that asks "can this be booked?" MUST go through these helpers so
 * the landing-page calendar, the booking calendar, the time picker and the
 * submit-time conflict check can never disagree with each other.
 */

// T-40: booking window. The customer may book at least this many days ahead and
// at most this many days ahead. Admin edits are intentionally allowed to
// override these (staff may accept a late booking by phone).
define('PDR_MIN_LEAD_DAYS', 3);
define('PDR_MAX_HORIZON_DAYS', 365);
// Business rule: maximum active (Pending/Confirmed) bookings per date across all services.
define('PDR_MAX_DAILY_BOOKINGS', 2);

// T-37: self-service policy (customer, with Reservation ID + PIN).
define('PDR_CANCEL_MIN_DAYS', 7);
define('PDR_RESCHEDULE_MIN_DAYS', 3);
define('PDR_MAX_RESCHEDULES', 1);

// Statuses that occupy a slot. Cancelled/Completed/Rejected free the slot.
function pdrActiveStatuses() {
    return ['Pending', 'Confirmed'];
}

/** Total active bookings on a date (all services), optionally excluding one reservation. */
function pdrActiveBookingCount($pdo, $eventDate, ?string $excludeReservationId = null): int {
    $statuses = pdrActiveStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
          WHERE event_date = ? AND status IN ($in)
            AND (? IS NULL OR reservation_id <> ?)"
    );
    $stmt->execute(array_merge([(string) $eventDate], $statuses, [$excludeReservationId, $excludeReservationId]));
    return (int) $stmt->fetchColumn();
}

/**
 * The hourly start times the booking form offers. Kept here so the server can
 * compute blocked start times with the same list the client renders.
 */
function pdrTimeSlots(): array {
    return [
        '08:00', '09:00', '10:00', '11:00', '12:00', '13:00',
        '14:00', '15:00', '16:00', '17:00', '18:00', '19:00',
        '20:00', '21:00',
    ];
}

/* ==========================================================================
   Time / interval helpers
   ========================================================================== */

/** "HH:MM" -> minutes since midnight, or null when the value is not a time. */
function pdrMinutesOfDay(string $time): ?int {
    if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($time), $m)) {
        return null;
    }
    $hours = (int) $m[1];
    $minutes = (int) $m[2];
    if ($hours > 23 || $minutes > 59) {
        return null;
    }
    return $hours * 60 + $minutes;
}

/**
 * Do two time ranges overlap? A zero-length range (duration 0) only clashes
 * with another range that starts at the exact same minute.
 */
function pdrIntervalsOverlap(int $aStart, int $aEnd, int $bStart, int $bEnd): bool {
    if ($aEnd <= $aStart || $bEnd <= $bStart) {
        return $aStart === $bStart;
    }
    return $aStart < $bEnd && $bStart < $aEnd;
}

/* ==========================================================================
   Per-service rules (cached per request)
   ========================================================================== */

/**
 * Every service's capacity rule, keyed by service id.
 *
 * Falls back to "no limits" when migration 011 has not run yet, so a
 * pre-migration database keeps working instead of throwing.
 */
function pdrServiceRules($pdo): array {
    static $cache = [];
    $key = is_object($pdo) ? spl_object_id($pdo) : 0;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $out = [];
    try {
        $rows = $pdo->query(
            "SELECT id, name, duration_minutes, max_per_day, max_concurrent FROM services"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $migrationMissing) {
        // Pre-011 database: every service behaves as before (no duration, no
        // per-service caps). The date-level blackout still applies.
        try {
            $rows = $pdo->query("SELECT id, name FROM services")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $fatal) {
            $rows = [];
        }
    }

    foreach ($rows as $row) {
        $out[(string) $row['id']] = [
            'id'            => (string) $row['id'],
            'name'          => (string) $row['name'],
            'duration'      => isset($row['duration_minutes']) && $row['duration_minutes'] !== null
                ? max(0, (int) $row['duration_minutes'])
                : 0,
            'maxPerDay'     => isset($row['max_per_day']) && $row['max_per_day'] !== null
                ? max(1, (int) $row['max_per_day'])
                : null,
            'maxConcurrent' => isset($row['max_concurrent']) && $row['max_concurrent'] !== null
                ? max(1, (int) $row['max_concurrent'])
                : null,
        ];
    }

    $cache[$key] = $out;
    return $out;
}

/** Resolve a service id from its display name (legacy rows may only have a name). */
function pdrServiceIdByName($pdo, string $name): ?string {
    foreach (pdrServiceRules($pdo) as $id => $rule) {
        if ($rule['name'] === $name) {
            return $id;
        }
    }
    return null;
}

/* ==========================================================================
   Blackout dates
   ========================================================================== */

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

/* ==========================================================================
   T-23: date-exclusive (flagship) services
   ========================================================================== */

/**
 * Services flagged `is_exclusive = 1`. These no longer block other services,
 * but the landing-page calendar still uses them to describe the date
 * ("venue booked"), so the flag is kept.
 *
 * Returns ['ids' => [...], 'names' => [...], 'enabled' => bool].
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

/* ==========================================================================
   Booking window
   ========================================================================== */

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

/* ==========================================================================
   Per-service occupancy
   ========================================================================== */

/**
 * Active bookings of one service on one date, as ['time' => 'HH:MM',
 * 'duration' => minutes] entries. Legacy rows with a NULL service_id are
 * matched by service name.
 */
function pdrServiceBookings($pdo, $eventDate, string $serviceId, ?string $excludeReservationId = null): array {
    $rules = pdrServiceRules($pdo);
    if (!isset($rules[$serviceId])) {
        return [];
    }
    $name = $rules[$serviceId]['name'];
    $duration = $rules[$serviceId]['duration'];

    $statuses = pdrActiveStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare(
        "SELECT event_time
           FROM reservations
          WHERE event_date = ? AND status IN ($in)
            AND (service_id = ? OR (service_id IS NULL AND main_service = ?))
            AND (? IS NULL OR reservation_id <> ?)"
    );
    $stmt->execute(array_merge(
        [$eventDate],
        $statuses,
        [$serviceId, $name],
        [$excludeReservationId, $excludeReservationId]
    ));

    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $time) {
        $out[] = ['time' => (string) $time, 'duration' => $duration];
    }
    return $out;
}

/**
 * Which of the offered start times would break the rule for this service?
 *
 * Pure helper (no DB) so it is easy to unit test. Returns the blocked 'HH:MM'
 * strings. A service that has reached `max_per_day` blocks every slot.
 */
function pdrBlockedStartTimes(array $rule, array $bookings, array $slots): array {
    $duration = max(0, (int) ($rule['duration'] ?? 0));
    $maxPerDay = $rule['maxPerDay'] ?? null;
    $maxConcurrent = $rule['maxConcurrent'] ?? null;

    if ($maxPerDay !== null && count($bookings) >= $maxPerDay) {
        return array_values($slots);
    }
    if ($maxConcurrent === null) {
        return [];
    }

    $blocked = [];
    foreach ($slots as $slot) {
        $start = pdrMinutesOfDay((string) $slot);
        if ($start === null) {
            continue;
        }
        $end = $start + $duration;
        $overlap = 0;
        foreach ($bookings as $booking) {
            $bStart = pdrMinutesOfDay((string) ($booking['time'] ?? ''));
            if ($bStart === null) {
                continue;
            }
            $bEnd = $bStart + max(0, (int) ($booking['duration'] ?? 0));
            if (pdrIntervalsOverlap($start, $end, $bStart, $bEnd)) {
                $overlap++;
            }
        }
        if ($overlap >= $maxConcurrent) {
            $blocked[] = (string) $slot;
        }
    }
    return $blocked;
}

/**
 * Full availability status for one service on one date.
 *
 * Returns ['taken' => bool, 'count' => int, 'reason' => 'BLACKOUT'|'FULL'|null,
 *          'blockedTimes' => [...], 'durationMinutes', 'maxPerDay',
 *          'maxConcurrent', 'blackoutReason' => ?string].
 */
function pdrServiceDateStatus($pdo, $eventDate, string $serviceId, ?string $excludeReservationId = null): array {
    $blackout = pdrBlackoutReason($pdo, $eventDate);
    if ($blackout !== null) {
        return [
            'taken'           => true,
            'count'           => 0,
            'reason'          => 'BLACKOUT',
            'blockedTimes'    => pdrTimeSlots(),
            'blackoutReason'  => $blackout,
            'durationMinutes' => null,
            'maxPerDay'       => null,
            'maxConcurrent'   => null,
        ];
    }

    // Global business rule: at most PDR_MAX_DAILY_BOOKINGS active bookings per date.
    $totalActive = pdrActiveBookingCount($pdo, $eventDate, $excludeReservationId);
    if ($totalActive >= PDR_MAX_DAILY_BOOKINGS) {
        return [
            'taken'           => true,
            'count'           => $totalActive,
            'reason'          => 'FULL',
            'blockedTimes'    => pdrTimeSlots(),
            'durationMinutes' => null,
            'maxPerDay'       => null,
            'maxConcurrent'   => null,
        ];
    }

    $rules = pdrServiceRules($pdo);
    if (!isset($rules[$serviceId])) {
        return [
            'taken'           => false,
            'count'           => 0,
            'reason'          => null,
            'blockedTimes'    => [],
            'durationMinutes' => 0,
            'maxPerDay'       => null,
            'maxConcurrent'   => null,
        ];
    }

    $rule = $rules[$serviceId];
    $bookings = pdrServiceBookings($pdo, $eventDate, $serviceId, $excludeReservationId);
    $count = count($bookings);
    $taken = $rule['maxPerDay'] !== null && $count >= $rule['maxPerDay'];

    return [
        'taken'           => $taken,
        'count'           => $count,
        'reason'          => $taken ? 'FULL' : null,
        'blockedTimes'    => pdrBlockedStartTimes($rule, $bookings, pdrTimeSlots()),
        'durationMinutes' => $rule['duration'],
        'maxPerDay'       => $rule['maxPerDay'],
        'maxConcurrent'   => $rule['maxConcurrent'],
    ];
}

/**
 * Can this exact service/date/time be booked? The one check every write path
 * uses (public submit, self-service reschedule, admin reschedule/approval).
 *
 * Returns ['ok' => bool, 'reason' => 'DATE_TAKEN'|'TIME_TAKEN', 'cause' => ...].
 */
function pdrCheckBookingSlot($pdo, string $eventDate, string $serviceId, string $eventTime, ?string $excludeReservationId = null): array {
    $status = pdrServiceDateStatus($pdo, $eventDate, $serviceId, $excludeReservationId);

    if (!empty($status['taken'])) {
        return [
            'ok'             => false,
            'reason'         => 'DATE_TAKEN',
            'cause'          => $status['reason'], // BLACKOUT | FULL
            'count'          => $status['count'],
            'blackoutReason' => $status['blackoutReason'] ?? null,
        ];
    }

    if (in_array($eventTime, $status['blockedTimes'] ?? [], true)) {
        $rules = pdrServiceRules($pdo);
        return [
            'ok'      => false,
            'reason'  => 'TIME_TAKEN',
            'cause'   => 'CONCURRENT',
            'time'    => $eventTime,
            'service' => $rules[$serviceId]['name'] ?? '',
        ];
    }

    return ['ok' => true, 'count' => $status['count']];
}

/* ==========================================================================
   Backwards-compatible wrappers
   ========================================================================== */

/**
 * Legacy date-level check. Prefer pdrCheckBookingSlot() for new code.
 *
 * When a service id is supplied the answer is per-service. Without one it
 * answers the landing-page question: is the date closed to EVERY service
 * (blackout, or every service at its own limit)?
 */
function pdrDateTaken($pdo, $eventDate, ?string $excludeReservationId = null, ?string $serviceId = null) {
    if ($serviceId !== null && $serviceId !== '') {
        $status = pdrServiceDateStatus($pdo, (string) $eventDate, (string) $serviceId, $excludeReservationId);
        return [
            'taken'          => $status['taken'],
            'count'          => $status['count'],
            'hasVenue'       => false,
            'reason'         => $status['reason'],
            'blackoutReason' => $status['blackoutReason'] ?? null,
        ];
    }

    $blackout = pdrBlackoutReason($pdo, $eventDate);
    if ($blackout !== null) {
        return [
            'taken'          => true,
            'count'          => 0,
            'hasVenue'       => false,
            'reason'         => 'BLACKOUT',
            'blackoutReason' => $blackout,
        ];
    }

    // Count every active booking that date, regardless of service.
    $statuses = pdrActiveStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations WHERE event_date = ? AND status IN ($in)"
    );
    $countStmt->execute(array_merge([(string) $eventDate], $statuses));
    $count = (int) $countStmt->fetchColumn();

    $rules = pdrServiceRules($pdo);
    $allClosed = count($rules) > 0;

    $exclusive = pdrExclusiveServices($pdo);
    $hasVenue = false;
    foreach ($rules as $id => $rule) {
        $status = pdrServiceDateStatus($pdo, (string) $eventDate, (string) $id, $excludeReservationId);
        if (in_array($id, $exclusive['ids'], true) && $status['taken']) {
            $hasVenue = true;
        }
        $openSlots = count(pdrTimeSlots()) - count($status['blockedTimes'] ?? []);
        if (!$status['taken'] && $openSlots > 0) {
            $allClosed = false;
        }
    }

    return [
        'taken'    => $allClosed,
        'count'    => $count,
        'hasVenue' => $hasVenue,
        'reason'   => $allClosed ? 'FULL' : null,
    ];
}

/**
 * Legacy helper: the start times this service can no longer take on a date.
 * Now duration-aware, so it returns blocked starts rather than only exact
 * matches. Callers use `in_array($time, $takenTimes)`.
 */
function pdrTakenTimes($pdo, $eventDate, $mainService, ?string $excludeReservationId = null) {
    $serviceId = pdrServiceIdByName($pdo, (string) $mainService);
    if ($serviceId === null) {
        return [];
    }
    $status = pdrServiceDateStatus($pdo, (string) $eventDate, $serviceId, $excludeReservationId);
    return $status['blockedTimes'] ?? [];
}

/* ==========================================================================
   Monthly availability payload
   ========================================================================== */

/**
 * Full availability payload for a month. Drives BOTH calendars and the time
 * picker so there is exactly one definition of "unavailable".
 *
 * Returns:
 *   dates     => ['YYYY-MM-DD', ...] dates closed on the landing-page calendar
 *                (every service is full, or a blackout)
 *   times     => ['YYYY-MM-DD' => ['HH:MM' => ['serviceA', ...], ...]]
 *   meta      => ['YYYY-MM-DD' => ['count' => n, 'hasVenue' => bool,
 *                                  'taken' => bool, 'blackout' => bool]]
 *   services  => [serviceId => ['id','name','durationMinutes','maxPerDay',
 *                               'maxConcurrent','dates' => [date => [...]]]]
 *   timeSlots => the offered 'HH:MM' starts
 */
function pdrAvailabilityPayload($pdo, $year, $month) {
    $statuses = pdrActiveStatuses();
    $in = implode(',', array_fill(0, count($statuses), '?'));
    $rules = pdrServiceRules($pdo);
    $slots = pdrTimeSlots();

    // All active bookings in the month, with the service resolved for legacy
    // rows that predate service_id.
    $stmt = $pdo->prepare(
        "SELECT event_date, event_time, service_id, main_service
           FROM reservations
          WHERE status IN ($in)
            AND YEAR(event_date) = ? AND MONTH(event_date) = ?"
    );
    $stmt->execute(array_merge($statuses, [(int) $year, (int) $month]));

    $rowsByDate = [];
    $byDateService = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $date = (string) $row['event_date'];
        $rowsByDate[$date][] = $row;

        $serviceId = $row['service_id'] !== null && $row['service_id'] !== ''
            ? (string) $row['service_id']
            : pdrServiceIdByName($pdo, (string) $row['main_service']);
        if ($serviceId !== null && isset($rules[$serviceId])) {
            $byDateService[$date][$serviceId][] = [
                'time'     => (string) $row['event_time'],
                'duration' => $rules[$serviceId]['duration'],
            ];
        }
    }

    // Every service, with the dates it already holds something on.
    $services = [];
    foreach ($rules as $serviceId => $rule) {
        $services[$serviceId] = [
            'id'              => $serviceId,
            'name'            => $rule['name'],
            'durationMinutes' => $rule['duration'],
            'maxPerDay'       => $rule['maxPerDay'],
            'maxConcurrent'   => $rule['maxConcurrent'],
            'dates'           => [],
        ];
    }

    $exclusive = pdrExclusiveServices($pdo);
    $dates = [];
    $meta = [];
    $times = [];

    $allDates = array_unique(array_merge(array_keys($rowsByDate), array_keys($byDateService)));
    sort($allDates);

    foreach ($allDates as $date) {
        $rows = $rowsByDate[$date] ?? [];
        $total = count($rows);

        // Legacy per-time view used by the admin calendar.
        foreach ($rows as $row) {
            $time = (string) $row['event_time'];
            $times[$date][$time][] = (string) $row['main_service'];
        }
        foreach ($times[$date] ?? [] as $time => $names) {
            $times[$date][$time] = array_values(array_unique($names));
        }

        // Per-service occupancy.
        foreach ($byDateService[$date] ?? [] as $serviceId => $bookings) {
            $rule = $rules[$serviceId];
            $taken = $rule['maxPerDay'] !== null && count($bookings) >= $rule['maxPerDay'];
            $services[$serviceId]['dates'][$date] = [
                'count'        => count($bookings),
                'taken'        => $taken,
                'maxPerDay'    => $rule['maxPerDay'],
                'blockedTimes' => pdrBlockedStartTimes($rule, $bookings, $slots),
            ];
        }

        // Global business rule: max PDR_MAX_DAILY_BOOKINGS active bookings per date.
        // When reached, no service can take the date.
        $globalFull = $total >= PDR_MAX_DAILY_BOOKINGS;
        if ($globalFull) {
            foreach ($rules as $serviceId => $rule) {
                $existing = $services[$serviceId]['dates'][$date] ?? null;
                $services[$serviceId]['dates'][$date] = [
                    'count'        => $existing['count'] ?? 0,
                    'taken'        => true,
                    'maxPerDay'    => $rule['maxPerDay'],
                    'blockedTimes' => $slots,
                ];
            }
        }

        // A date only reads as "closed" on the landing page when NO service can
        // still take it.
        $allClosed = count($rules) > 0;
        foreach ($rules as $serviceId => $rule) {
            $entry = $services[$serviceId]['dates'][$date] ?? null;
            if ($entry === null) {
                $allClosed = false;
                break;
            }
            $openSlots = count($slots) - count($entry['blockedTimes'] ?? []);
            if (empty($entry['taken']) && $openSlots > 0) {
                $allClosed = false;
                break;
            }
        }
        if ($globalFull) {
            $allClosed = true;
        }

        // Flagship booking is still reported so the calendar can explain the date.
        $hasVenue = false;
        foreach ($exclusive['ids'] as $exclusiveId) {
            if (!empty($services[$exclusiveId]['dates'][$date]['taken'])) {
                $hasVenue = true;
                break;
            }
        }

        $meta[$date] = [
            'count'    => $total,
            'hasVenue' => $hasVenue,
            'taken'    => $allClosed,
        ];
        if ($allClosed) {
            $dates[] = $date;
        }
    }

    // T-24: staff blackouts always win and are reported separately so the UI
    // can say "closed by staff" instead of "fully booked".
    foreach (pdrBlackoutDates($pdo, $year, $month) as $blackoutDate => $reason) {
        $existing = $meta[$blackoutDate] ?? ['count' => 0, 'hasVenue' => false];
        $meta[$blackoutDate] = [
            'count'          => $existing['count'],
            'hasVenue'       => $existing['hasVenue'],
            'taken'          => true,
            'blackout'       => true,
            'blackoutReason' => $reason,
        ];
        if (!in_array($blackoutDate, $dates, true)) {
            $dates[] = $blackoutDate;
        }
        foreach ($services as $serviceId => $service) {
            $services[$serviceId]['dates'][$blackoutDate] = [
                'count'          => $services[$serviceId]['dates'][$blackoutDate]['count'] ?? 0,
                'taken'          => true,
                'maxPerDay'      => $services[$serviceId]['maxPerDay'],
                'blockedTimes'   => $slots,
                'blackout'       => true,
                'blackoutReason' => $reason,
            ];
        }
    }

    return [
        'dates'     => $dates,
        'times'     => $times,
        'meta'      => $meta,
        'services'  => $services,
        'timeSlots' => $slots,
    ];
}
