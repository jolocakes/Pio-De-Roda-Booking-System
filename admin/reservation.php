<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// T-36: a Viewer may read reservations but never change them.
$canManageReservations = pdrCan('manage_reservations');

/** Escape every value rendered into HTML. */
function pdrH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$reservations = [];
$totalRows = 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$fromFilter = trim((string) ($_GET['from'] ?? ''));
$toFilter = trim((string) ($_GET['to'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromFilter)) {
    $fromFilter = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $toFilter)) {
    $toFilter = '';
}

try {
    // T-28: filter + paginate in SQL. Loading every reservation into PHP does
    // not scale past a few thousand rows and makes the page slow to render.
    // T-22: archived reservations live in the Archive module, not the active list.
    $where = ['archived_at IS NULL'];
    $params = [];

    if ($q !== '') {
        $where[] = '(reservation_id LIKE ? OR customer_name LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if (in_array($statusFilter, ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'Rejected'], true)) {
        $where[] = 'status = ?';
        $params[] = $statusFilter;
    }
    if ($fromFilter !== '') {
        $where[] = 'event_date >= ?';
        $params[] = $fromFilter;
    }
    if ($toFilter !== '') {
        $where[] = 'event_date <= ?';
        $params[] = $toFilter;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations $whereSql");
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET are integers computed here (never user strings), so they can
    // be interpolated safely even with emulated prepares disabled.
    $stmt = $pdo->prepare(
        "SELECT * FROM reservations $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $reservations[] = [
            'rowId' => $r['id'],
            'id' => $r['reservation_id'],
            'customer' => $r['customer_name'],
            'phone' => $r['contact_number'],
            'email' => $r['email'],
            'address' => $r['address'] ?? null,
            'service' => $r['main_service'],
            'date' => $r['event_date'],
            'time' => $r['event_time'],
            'guests' => $r['guests'],
            'package' => $r['selected_package'] ?? '',
            'status' => $r['status'],
            'amount' => '₱' . number_format($r['estimated_total'], 2),
        ];
    }
} catch (Exception $e) {
    $reservations = [];
    $totalRows = 0;
    $page = 1;
    $totalPages = 1;
}

// Keep the current filters when building pagination links.
$pageQuery = array_filter([
    'q' => $q,
    'status' => $statusFilter,
    'from' => $fromFilter,
    'to' => $toFilter,
], static fn($v) => $v !== '');
$pageUrl = static function (int $target) use ($pageQuery): string {
    return 'reservation.php?' . http_build_query(array_merge($pageQuery, ['page' => $target]));
};
$shownFrom = $totalRows === 0 ? 0 : (($page - 1) * $perPage) + 1;
$shownTo = min($page * $perPage, $totalRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management - Pio De Roda</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo pdrAsset('reservation.css'); ?>" />
    <link rel="stylesheet" href="<?php echo pdrAsset('filters.css'); ?>" />
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>" />

</head>
<body>

    <!-- Left Navigation Bar -->
    <aside class="sidebar">
        <div>
            <div class="brand-header">
                <div class="brand-logo-container">
                    <img src="pioderoda.jpg" alt="Pio De Roda Logo" class="brand-logo-img">
                </div>
                <h2>PIO DE RODA</h2>
                <p>RESERVATION SYSTEM</p>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
                </li>

                <li class="nav-item active">
                    <a href="reservation.php"><i class="fa-solid fa-calendar-check"></i> Reservations</a>
                </li>

                <li class="nav-item">
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> Calendar</a>
                </li>

                <li class="nav-item">
                    <a href="service.php"><i class="fa-solid fa-concierge-bell"></i> Services</a>
                </li>

                <li class="nav-item">
                    <a href="event.php"><i class="fa-solid fa-calendar-days"></i> Events</a>
                </li>

                <li class="nav-item">
                    <a href="customer.php"><i class="fa-solid fa-users"></i> Customers</a>
                </li>

                <li class="nav-item">
                    <a href="payment.php"><i class="fa-solid fa-money-bill-wave"></i> Payment</a>
                </li>

                <li class="nav-item">
                    <a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a>
                </li>

                <li class="nav-item">
                    <a href="archive.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a>
                </li>
                <?php if (pdrCan('manage_staff')): ?>
                    <li class="nav-item"><a href="staff.php"><i class="fa-solid fa-user-shield"></i> Staff</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="sidebar-footer">
            <li class="nav-item" style="list-style: none;">
                <form method="POST" action="logout.php" class="logout-form">
                    <?php echo pdrCsrfField(); ?>
                    <button type="submit" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                </form>
            </li>
        </div>
    </aside>

    <main class="main-wrapper">
        <header class="top-navbar">
            <div class="header-title-section">
                <h1>Reservation Management</h1>
            </div>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p>Admin User</p>
                    <span>Administrator</span>
                </div>
            </div>
        </header>

        <div class="content-body">
            <div id="pageAlert" class="page-alert" role="status" aria-live="polite"></div>

            <!-- Search & Filter Controls (T-28: server-side, survives pagination) -->
            <form method="get" action="reservation.php" class="filter-bar" id="filterForm">
                <input type="search" id="searchInput" name="q" class="search-input"
                    placeholder="Search by customer name or reservation ID..."
                    value="<?php echo pdrH($q); ?>">

                <label class="filter-field"><span>Status</span>
                    <select id="statusFilter" name="status" class="filter-select">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Rejected', 'Completed'] as $opt): ?>
                            <option value="<?php echo pdrH($opt); ?>" <?php echo $statusFilter === $opt ? 'selected' : ''; ?>>
                                <?php echo pdrH($opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="filter-field"><span>From</span>
                    <input type="date" id="fromFilter" name="from" class="filter-date"
                        value="<?php echo pdrH($fromFilter); ?>">
                </label>

                <label class="filter-field"><span>To</span>
                    <input type="date" id="toFilter" name="to" class="filter-date"
                        value="<?php echo pdrH($toFilter); ?>">
                </label>

                <button type="submit" class="btn-filter">Filter</button>
                <a class="btn-reset" href="reservation.php">Reset</a>
            </form>

            <!-- T-43: bulk actions (applies to the checkboxes on this page) -->
            <?php if ($canManageReservations): ?>
            <div id="bulkBar" style="display:none;align-items:center;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:10px 14px;margin-bottom:14px;">
                <strong id="bulkCount" style="font-size:13px;color:#4a5568;">0 selected</strong>
                <button type="button" class="btn btn-approve" data-bulk-status="Confirmed" title="Approve selected">
                    <i class="fa-solid fa-check"></i> Approve
                </button>
                <button type="button" class="btn btn-reject" data-bulk-status="Rejected" title="Reject selected">
                    <i class="fa-solid fa-xmark"></i> Reject
                </button>
                <button type="button" class="btn btn-cancel" data-bulk-status="Cancelled" title="Cancel selected">
                    <i class="fa-solid fa-ban"></i> Cancel
                </button>
                <button type="button" class="btn-reset" id="bulkClear">Clear selection</button>
                <span id="bulkMessage" role="status" aria-live="polite" style="font-size:13px;color:#718096;"></span>
            </div>
            <?php endif; ?>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3>All Reservations</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="reservationsTable">
                        <thead>
                            <tr>
                                <th style="width:34px;">
                                    <?php if ($canManageReservations): ?>
                                    <input type="checkbox" id="selectAllReservations" aria-label="Select all reservations on this page">
                                    <?php endif; ?>
                                </th>
                                <th>ID</th>
                                <th>Customer Name</th>
                                <th>Service</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reservations)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center;color:#718096;padding:24px;">
                                        No reservations match these filters.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach($reservations as $row): ?>
                                <tr id="row-<?php echo pdrH($row['id']); ?>">
                                    <td>
                                        <?php if ($canManageReservations): ?>
                                        <input type="checkbox" class="reservation-check"
                                            value="<?php echo pdrH($row['id']); ?>"
                                            aria-label="Select reservation <?php echo pdrH($row['id']); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo pdrH($row['id']); ?></strong></td>
                                    <td><?php echo pdrH($row['customer']); ?></td>
                                    <td><?php echo pdrH($row['service']); ?></td>
                                    <td><?php echo pdrH($row['date'] . ' ' . $row['time']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo pdrH(strtolower((string) $row['status'])); ?>" id="status-badge-<?php echo pdrH($row['id']); ?>">
                                            <?php echo pdrH($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="action-cell">
                                        <!-- View details -->
                                        <button type="button" class="btn btn-view" title="View Details"
                                            data-view-details
                                            data-id="<?php echo pdrH($row['id']); ?>"
                                            data-customer="<?php echo pdrH($row['customer']); ?>"
                                            data-phone="<?php echo pdrH($row['phone']); ?>"
                                            data-email="<?php echo pdrH($row['email']); ?>"
                                            data-service="<?php echo pdrH($row['service']); ?>"
                                            data-date="<?php echo pdrH($row['date']); ?>"
                                            data-time="<?php echo pdrH($row['time']); ?>"
                                            data-guests="<?php echo pdrH((string) $row['guests']); ?>"
                                            data-amount="<?php echo pdrH($row['amount']); ?>"
                                            data-package="<?php echo pdrH($row['package']); ?>"
                                            data-status="<?php echo pdrH($row['status']); ?>">
                                            <i class="fa-solid fa-eye"></i>
                                        </button>

                                        <!-- Approve -->
                                        <?php if ($canManageReservations): ?>
                                        <button type="button" class="btn btn-approve" title="Approve"
                                            data-status-action data-id="<?php echo pdrH($row['id']); ?>" data-status="Confirmed">
                                            <i class="fa-solid fa-check"></i>
                                        </button>

                                        <!-- Reject -->
                                        <button type="button" class="btn btn-reject" title="Reject"
                                            data-status-action data-id="<?php echo pdrH($row['id']); ?>" data-status="Rejected">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>

                                        <!-- Reschedule / Edit -->
                                        <button class="btn btn-edit" title="Edit / Reschedule" data-id="<?php echo pdrH((string) $row['id']); ?>"
                                            data-service="<?php echo pdrH((string) $row['service']); ?>"
                                            data-date="<?php echo pdrH((string) $row['date']); ?>"
                                            data-time="<?php echo pdrH((string) $row['time']); ?>"
                                            data-guests="<?php echo pdrH((string) $row['guests']); ?>"
                                            data-status="<?php echo pdrH((string) $row['status']); ?>"
                                            onclick="openEditModal(this)">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>

                                        <!-- Cancel -->
                                        <button type="button" class="btn btn-cancel" title="Cancel Booking"
                                            data-status-action data-id="<?php echo pdrH($row['id']); ?>" data-status="Cancelled">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>

                                        <!-- T-41: resend the confirmation email (issues a new PIN) -->
                                        <button type="button" class="btn" title="Resend confirmation email"
                                            style="background:#eef2ff;color:#3730a3;"
                                            data-resend-confirmation data-id="<?php echo pdrH($row['id']); ?>">
                                            <i class="fa-solid fa-envelope"></i>
                                        </button>
                                        <?php endif; // end manage_reservations-only actions ?>

                                        <!-- T-38: printable receipt -->
                                        <a class="btn" title="Printable receipt"
                                            style="background:#f0fdf4;color:#166534;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;"
                                            href="receipt.php?reservationId=<?php echo urlencode((string) $row['id']); ?>"
                                            target="_blank" rel="noopener">
                                            <i class="fa-solid fa-receipt"></i>
                                        </a>

                                        <?php if ($canManageReservations): ?>
                                        <!-- T-22: soft-delete into the Archive module -->
                                        <button type="button" class="btn" title="Archive reservation"
                                            style="background:#f8fafc;color:#475569;"
                                            data-archive-reservation
                                            data-row-id="<?php echo (int) $row['rowId']; ?>"
                                            data-reservation-id="<?php echo pdrH($row['id']); ?>">
                                            <i class="fa-solid fa-box-archive"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- T-28: pagination (25 rows per page) -->
                <div class="pagination-bar" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:12px 4px 2px;">
                    <span style="font-size:13px;color:#718096;">
                        Showing <?php echo (int) $shownFrom; ?>&ndash;<?php echo (int) $shownTo; ?>
                        of <?php echo (int) $totalRows; ?> reservation<?php echo $totalRows === 1 ? '' : 's'; ?>
                    </span>
                    <?php if ($totalPages > 1): ?>
                        <span style="display:flex;gap:6px;align-items:center;">
                            <?php if ($page > 1): ?>
                                <a class="btn-reset" href="<?php echo pdrH($pageUrl(1)); ?>">&laquo; First</a>
                                <a class="btn-reset" href="<?php echo pdrH($pageUrl($page - 1)); ?>">&lsaquo; Prev</a>
                            <?php endif; ?>
                            <span style="font-size:13px;color:#4a5568;padding:0 6px;">
                                Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?>
                            </span>
                            <?php if ($page < $totalPages): ?>
                                <a class="btn-reset" href="<?php echo pdrH($pageUrl($page + 1)); ?>">Next &rsaquo;</a>
                                <a class="btn-reset" href="<?php echo pdrH($pageUrl($totalPages)); ?>">Last &raquo;</a>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal 1: View Reservation Details -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Reservation Details</h3>
                <span class="close-modal" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body" id="viewModalContent">
                <!-- Injected via JavaScript -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-cancel" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal 2: Reschedule / Edit Reservation -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Reschedule / Edit Reservation</h3>
                <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editResId">

                <label for="editService">Service</label>
                <input type="text" id="editService" maxlength="100" required>

                <label for="editDate">Date</label>
                <input type="date" id="editDate" required>

                <label for="editTime">Time</label>
                <input type="time" id="editTime" required>

                <label for="editGuests">Guests</label>
                <input type="number" id="editGuests" min="1" step="1" required>

                <label for="editStatus">Update Status</label>
                <select id="editStatus">
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Rejected">Rejected</option>
                    <option value="Completed">Completed</option>
                </select>

                <div id="editMessage" class="form-message" role="alert" aria-live="polite"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // Server-issued CSRF token for every state-changing admin request.
        const PDR_CSRF_TOKEN = <?php echo json_encode(
            pdrCsrfToken(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>;

        // T-28: filtering happens server-side (see the filter form above), so
        // there is no client-side table filter anymore — it could only filter
        // the 25 rows that happen to be on the current page.

        function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
}

function showEditMessage(message, type) {
    const el = document.getElementById('editMessage');
    if (!el) return;
    el.textContent = message;
    el.className = 'form-message ' + (type || '');
    el.style.display = 'block';
}

function clearEditMessage() {
    const el = document.getElementById('editMessage');
    if (!el) return;
    el.textContent = '';
    el.className = 'form-message';
    el.style.display = 'none';
}

function showPageAlert(message, type) {
    const el = document.getElementById('pageAlert');
    if (!el) return;
    el.textContent = message;
    el.className = 'page-alert ' + (type || '');
    el.style.display = 'block';
    window.setTimeout(() => {
        el.style.display = 'none';
    }, 5000);
}

document.addEventListener('DOMContentLoaded', () => {
    try {
        if (sessionStorage.getItem('pdrReservationUpdated') === '1') {
            sessionStorage.removeItem('pdrReservationUpdated');
            showPageAlert('Reservation updated successfully.', 'success');
        }
    } catch (err) {
        // sessionStorage may be unavailable in private mode.
    }
});


        // View Reservation Details Modal — build DOM nodes with textContent so
        // customer-controlled values can never become HTML/script.
        function viewDetails(id, customer, phone, email, service, date, time, guests, amount, packageName, status) {
            const content = document.getElementById('viewModalContent');
            if (!content) return;

            const fields = [
                ['Reservation ID', id],
                ['Customer Name', customer],
                ['Phone', phone],
                ['Email', email],
                ['Service', service],
                ['Package', packageName],
                ['Date & Time', date + ' at ' + time],
                ['Guests', guests],
                ['Total Amount', amount],
                ['Status', status],
            ];

            content.replaceChildren();
            fields.forEach(([label, value]) => {
                const p = document.createElement('p');
                const strong = document.createElement('strong');
                strong.textContent = label + ':';
                p.appendChild(strong);
                p.appendChild(document.createTextNode(' ' + (value == null ? '' : String(value))));
                content.appendChild(p);
            });

            document.getElementById('viewModal').style.display = 'flex';
        }

        let isUpdatingStatus = false;

        // Update Reservation Status (Approve, Reject, Cancel)
        async function updateStatus(id, newStatus) {
           
            const validStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'Rejected'];
            if (!validStatuses.includes(newStatus)) {
                alert('Invalid status: ' + newStatus);
                return false;
            }

            const verb = newStatus === 'Rejected'
                ? 'reject this reservation? The customer will be told it was not approved.'
                : `change status to ${newStatus}?`;
            if (!confirm(`Are you sure you want to ${verb}`)) {
                return false;
            }

            if (isUpdatingStatus) return false;
            isUpdatingStatus = true;

            try {
                const res = await fetch('../api/admin/update_status.php', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': PDR_CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        reservationId: id,
                        status: newStatus
                    })
                });

                const data = await res.json().catch(() => null);

                if (res.ok && data && data.ok) {
                    const badge = document.getElementById(`status-badge-${id}`);
                    if (badge) {
                        badge.innerText = newStatus;
                        badge.className = `badge badge-${newStatus.toLowerCase()}`;
                    }
                    return true;
                } else {
                    const errorMsg = (data && data.reason) ? data.reason : 'Failed to update reservation status.';
                    alert('Error updating status: ' + errorMsg);
                    return false;
                }
            } catch (err) {
                alert('Network error while updating reservation status.');
                return false;
            } finally {
                isUpdatingStatus = false;
            }
        }

        // Edit / Reschedule Modal Open
        function openEditModal(button) {
            const data = button.dataset;
            document.getElementById('editResId').value = data.id || '';
            document.getElementById('editService').value = data.service || '';
            document.getElementById('editDate').value = data.date || '';
            document.getElementById('editTime').value = data.time || '';
            document.getElementById('editGuests').value = data.guests || '';
            document.getElementById('editStatus').value = data.status || 'Pending';
            clearEditMessage();
            document.getElementById('editModal').style.display = 'flex';
        }

        // Save Edit / Reschedule
        async function saveEdit() {
            const id = document.getElementById('editResId').value.trim();
            const service = document.getElementById('editService').value.trim();
            const date = document.getElementById('editDate').value;
            const time = document.getElementById('editTime').value;
            const guests = Number(document.getElementById('editGuests').value);
            const status = document.getElementById('editStatus').value;
            const saveBtn = document.querySelector('#editModal .btn-primary');
            const originalLabel = saveBtn ? saveBtn.textContent : 'Save Changes';

            clearEditMessage();

            if (!id || !service || !date || !time) {
                showEditMessage('Please fill in the service, date, and time.', 'error');
                return;
            }

            if (!Number.isInteger(guests) || guests < 1) {
                showEditMessage('Guest count must be at least 1.', 'error');
                return;
            }

            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving...';
            }

            try {
                const res = await fetch('../api/admin/update_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': PDR_CSRF_TOKEN
                    },
                    body: JSON.stringify({
                        reservationId: id,
                        service: service,
                        date: date,
                        time: time,
                        guests: guests,
                        status: status
                    })
                });

                const data = await res.json().catch(() => null);

                if (res.ok && data && data.ok) {
                    showEditMessage('Reservation updated successfully.', 'success');
                    try {
                        sessionStorage.setItem('pdrReservationUpdated', '1');
                    } catch (err) {
                        // Ignore storage failures.
                    }
                    window.setTimeout(() => location.reload(), 700);
                    return;
                }

                const reason = data && data.reason ? data.reason : 'SERVER_ERROR';
                const fieldLabels = {
                    reservationId: 'reservation ID',
                    service: 'service',
                    date: 'date',
                    time: 'time',
                    guests: 'guest count',
                    status: 'status'
                };
                let message = 'Failed to update reservation.';

                if (reason === 'UNAUTHORIZED') {
                    message = 'Your session expired. Please log in again.';
                } else if (reason === 'NOT_FOUND') {
                    message = 'Reservation not found. Refresh the page and try again.';
                } else if (reason === 'INVALID' && data && data.field) {
                    message = 'Please check the ' + (fieldLabels[data.field] || data.field) + ' value.';
                } else if (reason === 'SERVER_ERROR') {
                    message = 'Could not save changes. Please try again.';
                }

                showEditMessage(message, 'error');
            } catch (err) {
                showEditMessage('Network error while updating reservation.', 'error');
            } finally {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalLabel;
                }
            }
        }

        // Status buttons carry the reservation ID/status in data attributes so
        // no server value is interpolated into JavaScript source.
        document.querySelectorAll('[data-status-action]').forEach((button) => {
            button.addEventListener('click', () => {
                updateStatus(button.dataset.id || '', button.dataset.status || '');
            });
        });

        // T-25: make the existing details modal reachable. Values travel in
        // data attributes (escaped server-side) and are rendered with
        // textContent, so a customer name can never inject markup.
        document.querySelectorAll('[data-view-details]').forEach((button) => {
            button.addEventListener('click', () => {
                const d = button.dataset;
                viewDetails(
                    d.id, d.customer, d.phone, d.email, d.service,
                    d.date, d.time, d.guests, d.amount, d.package, d.status
                );
            });
        });

        // T-41: resend the confirmation email. The server can only store a PIN
        // hash, so the action issues a NEW PIN — warn the admin first.
        document.querySelectorAll('[data-resend-confirmation]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.dataset.id || '';
                if (!id) return;
                if (!confirm(
                    'Resend the confirmation email for ' + id + '?\n\n' +
                    'A NEW Booking PIN will be issued and the old PIN will stop working.'
                )) {
                    return;
                }

                button.disabled = true;
                try {
                    const res = await fetch('../api/admin/resend_confirmation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': PDR_CSRF_TOKEN
                        },
                        body: JSON.stringify({ reservationId: id })
                    });
                    const data = await res.json().catch(() => ({}));

                    if (!res.ok || !data.ok) {
                        const messages = {
                            UNAUTHORIZED: 'Your session expired. Please log in again.',
                            NOT_FOUND: 'Reservation not found. Refresh and try again.',
                            NO_EMAIL: 'This reservation has no valid email address.',
                            SERVER_ERROR: 'Could not resend the email. Please try again.'
                        };
                        showPageAlert(messages[data.reason] || 'Could not resend the email.', 'error');
                        return;
                    }

                    showPageAlert(
                        data.emailMode === 'smtp'
                            ? 'Confirmation email sent. A new Booking PIN was issued.'
                            : 'Email saved to the outbox (SMTP not configured). A new Booking PIN was issued.',
                        'success'
                    );
                } catch (err) {
                    showPageAlert('Network error while resending the email.', 'error');
                } finally {
                    button.disabled = false;
                }
            });
        });

        // T-22: soft-delete a reservation into the Archive module.
        document.querySelectorAll('[data-archive-reservation]').forEach((button) => {
            button.addEventListener('click', async () => {
                const rowId = Number(button.dataset.rowId || 0);
                const reservationId = button.dataset.reservationId || '';
                if (!rowId) return;
                if (!confirm('Archive reservation ' + reservationId + '?\n\nYou can restore it from the Archive page.')) {
                    return;
                }

                button.disabled = true;
                try {
                    const res = await fetch('../api/admin/archive.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': PDR_CSRF_TOKEN
                        },
                        body: JSON.stringify({
                            action: 'archive',
                            type: 'reservations',
                            id: rowId,
                            reason: 'Archived from Reservations list'
                        })
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        showPageAlert('Could not archive the reservation.', 'error');
                        return;
                    }
                    const row = document.getElementById('row-' + reservationId);
                    if (row) row.remove();
                    showPageAlert('Reservation archived. Restore it from the Archive page.', 'success');
                } catch (err) {
                    showPageAlert('Network error while archiving the reservation.', 'error');
                } finally {
                    button.disabled = false;
                }
            });
        });

        // T-43: bulk selection + actions.
        (function initBulkActions() {
            const selectAll = document.getElementById('selectAllReservations');
            const checks = Array.prototype.slice.call(document.querySelectorAll('.reservation-check'));
            const bar = document.getElementById('bulkBar');
            const countLabel = document.getElementById('bulkCount');
            const message = document.getElementById('bulkMessage');
            const clearBtn = document.getElementById('bulkClear');
            if (!bar || !checks.length) return;

            function selectedIds() {
                return checks.filter((c) => c.checked).map((c) => c.value);
            }

            function refresh() {
                const ids = selectedIds();
                countLabel.textContent = ids.length + ' selected';
                bar.style.display = ids.length ? 'flex' : 'none';
                if (selectAll) {
                    selectAll.checked = ids.length > 0 && ids.length === checks.length;
                    selectAll.indeterminate = ids.length > 0 && ids.length < checks.length;
                }
            }

            if (selectAll) {
                selectAll.addEventListener('change', () => {
                    checks.forEach((c) => { c.checked = selectAll.checked; });
                    refresh();
                });
            }
            checks.forEach((c) => c.addEventListener('change', refresh));

            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    checks.forEach((c) => { c.checked = false; });
                    if (selectAll) selectAll.checked = false;
                    refresh();
                });
            }

            document.querySelectorAll('[data-bulk-status]').forEach((button) => {
                button.addEventListener('click', async () => {
                    const ids = selectedIds();
                    if (!ids.length) return;
                    const status = button.dataset.bulkStatus || '';
                    const verbs = {
                        Confirmed: 'approve',
                        Rejected: 'reject',
                        Cancelled: 'cancel'
                    };
                    if (!confirm('Are you sure you want to ' + (verbs[status] || 'update') +
                        ' ' + ids.length + ' reservation' + (ids.length === 1 ? '' : 's') + '?')) {
                        return;
                    }

                    message.textContent = 'Working...';
                    button.disabled = true;
                    try {
                        const res = await fetch('../api/admin/bulk_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': PDR_CSRF_TOKEN
                            },
                            body: JSON.stringify({ reservationIds: ids, status: status })
                        });
                        const data = await res.json().catch(() => ({}));

                        if (!res.ok || !data.ok) {
                            message.textContent = 'Bulk update failed.';
                            return;
                        }

                        // Reflect what actually changed without a full reload.
                        const updated = data.updated || [];
                        updated.forEach((id) => {
                            const badge = document.getElementById('status-badge-' + id);
                            if (badge) {
                                badge.textContent = status;
                                badge.className = 'badge badge-' + status.toLowerCase();
                            }
                            const row = document.getElementById('row-' + id);
                            if (row) {
                                const cb = row.querySelector('.reservation-check');
                                if (cb) cb.checked = false;
                            }
                        });

                        const failed = (data.failed || []).length;
                        const skipped = (data.skipped || []).length;
                        message.textContent = updated.length + ' updated' +
                            (skipped ? ', ' + skipped + ' unchanged' : '') +
                            (failed ? ', ' + failed + ' failed' : '') + '.';
                        setTimeout(refresh, 0);
                    } catch (err) {
                        message.textContent = 'Network error during bulk update.';
                    } finally {
                        button.disabled = false;
                    }
                });
            });

            refresh();
        })();

    </script>
<script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
<script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
<script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>