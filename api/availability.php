<?php
/**
 * API: Availability (dates + time slots)
 * Pio De Roda Hospitality Reservation and Event Booking System
 *
 * Policy lives in capacity.php. This endpoint only reports it.
 *
 *   GET availability.php                 -> every fully-taken date (legacy shape)
 *   GET availability.php?year=2026&month=11
 *        -> { dates: [...], times: {...}, meta: {...} } for that month
 *
 * `dates` is always present so older callers keep working. `times` maps a date
 * to the services already holding each time slot, and `meta` carries the
 * count/venue flags the calendars use for tooltips and gray-out decisions.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/capacity.php';

try {
    $year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
    $month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'reason' => 'INVALID', 'dates' => [], 'times' => [], 'meta' => []]);
        exit();
    }

    $payload = pdrAvailabilityPayload($pdo, $year, $month);

    echo json_encode([
        'ok'    => true,
        'dates' => $payload['dates'],
        'times' => $payload['times'],
        'meta'  => $payload['meta'],
        'rules' => [
            'maxPerDay'         => PDR_MAX_SERVICES_PER_DAY,
            'exclusiveService'  => PDR_EXCLUSIVE_SERVICE,
            'activeStatuses'    => pdrActiveStatuses(),
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'NETWORK', 'dates' => [], 'times' => [], 'meta' => []]);
}
