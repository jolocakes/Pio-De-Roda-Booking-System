<?php
/**
 * API: Admin Get Reservations
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/../../config/rbac.php';

pdrRequireApiCapability('view');

try {
    // T-28: optional filters + pagination. Without ?page= the endpoint keeps
    // its original shape (a plain array) because the calendar and other pages
    // already consume it that way.
    $where = [];
    $params = [];

    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(reservation_id LIKE ? OR customer_name LIKE ? OR main_service LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    $status = trim((string) ($_GET['status'] ?? ''));
    if (in_array($status, ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'Rejected'], true)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }

    $from = trim((string) ($_GET['from'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
        $where[] = 'event_date >= ?';
        $params[] = $from;
    }
    $to = trim((string) ($_GET['to'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
        $where[] = 'event_date <= ?';
        $params[] = $to;
    }

    // T-22 revision: "History" is a read-only view of finished bookings, not a
    // list of soft-deleted rows. Past events and terminal statuses qualify.
    $history = ((int) ($_GET['history'] ?? 0)) === 1;
    if ($history) {
        $where[] = 'archived_at IS NULL';
        $where[] = "(event_date < CURDATE() OR status IN ('Completed', 'Cancelled', 'Rejected'))";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $orderSql = $history ? 'event_date DESC, id DESC' : 'id DESC';

    $paginate = isset($_GET['page']);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? 25);
    $perPage = max(1, min(200, $perPage));

    if ($paginate) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $stmt = $pdo->prepare(
            "SELECT * FROM reservations $whereSql ORDER BY $orderSql LIMIT $perPage OFFSET $offset"
        );
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM reservations $whereSql ORDER BY $orderSql");
        $stmt->execute($params);
    }

    $rows = $stmt->fetchAll();

    $reservations = [];
    foreach ($rows as $r) {
        $reservations[] = [
            'id' => $r['reservation_id'],
            'customerName' => $r['customer_name'],
            'contactPerson' => $r['contact_person'],
            'contactNumber' => $r['contact_number'],
            'email' => $r['email'],
            'address' => $r['address'] ?? null,
            'service' => $r['main_service'],
            'eventDate' => $r['event_date'],
            'eventTime' => $r['event_time'],
            'eventType' => $r['event_type'],
            'guests' => $r['guests'],
            'selectedPackage' => $r['selected_package'],
            'mainCourses' => $r['main_courses'] ? json_decode($r['main_courses'], true) : [],
            'additionalServices' => $r['additional_services'] ? json_decode($r['additional_services'], true) : [],
            'estimatedTotal' => '₱' . number_format($r['estimated_total'], 2),
            'paymentMethod' => $r['payment_method'],
            'status' => $r['status'],
            'submittedAt' => $r['submitted_at']
        ];
    }

    if ($paginate) {
        echo json_encode([
            'ok' => true,
            'reservations' => $reservations,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
        exit();
    }

    echo json_encode($reservations);
} catch (Exception $e) {
    // Never return raw exception text to the client (see other endpoints).
    error_log('admin reservations error: ' . $e->getMessage());
    http_response_code(500);
    error_log('Admin reservations API error: ' . $e->getMessage());
    echo json_encode([
        'ok' => false,
        'error' => 'SERVER_ERROR',
        'reason' => 'SERVER_ERROR'
    ]);
}
