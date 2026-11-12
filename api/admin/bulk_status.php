<?php
/**
 * API: Admin bulk reservation actions (T-43).
 *
 *   POST { reservationIds: ["PDR-...", ...], status: "Confirmed"|"Rejected"|"Cancelled" }
 *
 * Every reservation is checked individually against the same capacity rules as
 * a single status update (capacity.php). A row that cannot change is reported
 * in `failed` — the rest still go through, so one bad booking never blocks a
 * whole batch. A maximum of 50 rows per request keeps the operation bounded.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../../config/audit.php';
require_once __DIR__ . '/../../config/rbac.php';
require_once __DIR__ . '/../capacity.php';
require_once __DIR__ . '/../mail/status_template.php';

pdrSessionStart();

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}

pdrRequireApiCapability('manage_reservations');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);
    exit();
}

pdrRequireCsrfJson();

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID']);
    exit();
}

$ids = $data['reservationIds'] ?? [];
$newStatus = trim((string) ($data['status'] ?? ''));

if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'reservationIds']);
    exit();
}
if (!in_array($newStatus, ['Confirmed', 'Rejected', 'Cancelled'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'status']);
    exit();
}

// Normalise + bound the batch.
$ids = array_values(array_unique(array_filter(array_map(
    static fn($v) => is_string($v) ? trim($v) : '',
    $ids
))));
if (count($ids) === 0 || count($ids) > 50) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'reason' => 'INVALID', 'field' => 'reservationIds']);
    exit();
}

$updated = [];
$skipped = [];
$failed = [];

try {
    $checkStmt = $pdo->prepare(
        "SELECT status, main_service, event_date, event_time
           FROM reservations WHERE reservation_id = ?"
    );
    $updStmt = $pdo->prepare("UPDATE reservations SET status = ? WHERE reservation_id = ?");

    foreach ($ids as $reservationId) {
        try {
            $checkStmt->execute([$reservationId]);
            $current = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$current) {
                $failed[] = ['id' => $reservationId, 'reason' => 'NOT_FOUND'];
                continue;
            }
            if ($current['status'] === $newStatus) {
                $skipped[] = ['id' => $reservationId, 'reason' => 'UNCHANGED'];
                continue;
            }

            // Re-activating obeys the same rules as a single update.
            if (in_array($newStatus, ['Pending', 'Confirmed'], true)) {
                $dateCheck = pdrDateTaken($pdo, $current['event_date'], $reservationId);
                if ($dateCheck['taken']) {
                    $failed[] = ['id' => $reservationId, 'reason' => 'DATE_TAKEN'];
                    continue;
                }
                $takenTimes = pdrTakenTimes($pdo, $current['event_date'], $current['main_service'], $reservationId);
                if (in_array($current['event_time'], $takenTimes, true)) {
                    $failed[] = ['id' => $reservationId, 'reason' => 'TIME_TAKEN'];
                    continue;
                }
            }

            $updStmt->execute([$newStatus, $reservationId]);

            // Best effort: never let an SMTP problem undo the status change.
            try {
                pdrDispatchStatusEmail($pdo, $reservationId, $newStatus);
            } catch (Throwable $mailErr) {
                error_log('Bulk status email failed for ' . $reservationId . ': ' . $mailErr->getMessage());
            }

            pdrAudit($pdo, 'reservation.bulk_status', 'reservation', $reservationId, [
                'from' => $current['status'],
                'to' => $newStatus,
            ]);

            $updated[] = $reservationId;
        } catch (Throwable $rowErr) {
            error_log('Bulk status row failed for ' . $reservationId . ': ' . $rowErr->getMessage());
            $failed[] = ['id' => $reservationId, 'reason' => 'SERVER_ERROR'];
        }
    }

    echo json_encode([
        'ok' => true,
        'status' => $newStatus,
        'updated' => $updated,
        'skipped' => $skipped,
        'failed' => $failed,
    ]);
} catch (Throwable $e) {
    error_log('Bulk status error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
