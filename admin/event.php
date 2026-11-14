<?php 
require_once __DIR__ . '/auth_check.php'; 
require_once __DIR__ . '/../config/db.php';

$reservations = [];
$totalRows = 0;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$dateFrom = trim((string) ($_GET['from'] ?? ''));
$dateTo = trim((string) ($_GET['to'] ?? ''));

try {
    // T-28: filter + paginate in SQL. The page used to render every event at
    // once, which does not scale.
    // T-22: archived reservations are managed from the Archive page.
    $where = ['archived_at IS NULL'];
    $params = [];

    if ($q !== '') {
        $where[] = '(reservation_id LIKE ? OR customer_name LIKE ? OR main_service LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    if (in_array($statusFilter, ['Pending', 'Confirmed', 'Cancelled', 'Completed', 'Rejected'], true)) {
        $where[] = 'status = ?';
        $params[] = $statusFilter;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $where[] = 'event_date >= ?';
        $params[] = $dateFrom;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $where[] = 'event_date <= ?';
        $params[] = $dateTo;
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM reservations $whereSql");
    $countStmt->execute($params);
    $totalRows = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    // LIMIT/OFFSET are integers computed here, never user strings.
    $stmt = $pdo->prepare(
        "SELECT
            id,
            reservation_id,
            customer_name,
            contact_number,
            email,
            event_type,
            main_service,
            event_date,
            event_time,
            guests,
            selected_package,
            additional_services,
            estimated_total,
            status
         FROM reservations
         $whereSql
         ORDER BY event_date ASC, event_time ASC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reservations = [];
    $totalRows = 0;
    $page = 1;
    $totalPages = 1;
}

$eventQuery = array_filter([
    'q' => $q,
    'status' => $statusFilter,
    'from' => $dateFrom,
    'to' => $dateTo,
], static fn($v) => $v !== '');
$eventPageUrl = static function (int $target) use ($eventQuery): string {
    return 'event.php?' . http_build_query(array_merge($eventQuery, ['page' => $target]));
};
$eventShownFrom = $totalRows === 0 ? 0 : (($page - 1) * $perPage) + 1;
$eventShownTo = min($page * $perPage, $totalRows);

function eventH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Pio De Roda Cafe</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?php echo pdrAsset('event.css'); ?>" />
    <link rel="stylesheet" href="<?php echo pdrAsset('filters.css'); ?>" />
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>" />


</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
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

                <li class="nav-item ">
                    <a href="reservation.php"><i class="fa-solid fa-calendar-check"></i> Reservations</a>
                </li>

                <li class="nav-item">
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> Calendar</a>
                </li>

                <li class="nav-item">
                    <a href="service.php"><i class="fa-solid fa-concierge-bell"></i> Services</a>
                </li>

                <li class="nav-item active">
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
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <div class="top-navbar">
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Events Management</span>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p>Admin User</p>
                    <span>Administrator</span>
                </div>
            </div>
        </div>

        <div class="content-body">
            <div class="page-header-container">
                <div class="page-title-group">
                    <p>Organize, view, and record all catering and venue event specifications from customer reservations.</p>
                </div>
                <div style="font-size: 13px; color: var(--text-muted); background: var(--bg-card, #fff); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border-color);">
                    <i class="fa-solid fa-info-circle" style="color: var(--primary);"></i> Events originate automatically from customer reservations.
                </div>
            </div>

            <!-- T-28: server-side search / filters -->
            <form method="get" action="event.php" class="filter-bar">
                <input type="search" name="q" class="search-input" value="<?php echo eventH($q); ?>"
                    placeholder="Search ID, customer, or service...">

                <label class="filter-field"><span>Status</span>
                    <select name="status" class="filter-select">
                        <option value="">All Statuses</option>
                        <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Rejected', 'Completed'] as $opt): ?>
                            <option value="<?php echo eventH($opt); ?>" <?php echo $statusFilter === $opt ? 'selected' : ''; ?>>
                                <?php echo eventH($opt); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="filter-field"><span>From</span>
                    <input type="date" name="from" class="filter-date" value="<?php echo eventH($dateFrom); ?>">
                </label>

                <label class="filter-field"><span>To</span>
                    <input type="date" name="to" class="filter-date" value="<?php echo eventH($dateTo); ?>">
                </label>

                <button type="submit" class="btn-filter">Filter</button>
                <a class="btn-reset" href="event.php">Reset</a>
            </form>

            <!-- T-43: bulk actions for the events table -->
            <div id="eventBulkBar" style="display:none;align-items:center;gap:10px;flex-wrap:wrap;background:#fff;border:1px solid var(--border-color);border-radius:8px;padding:10px 14px;margin-bottom:14px;">
                <strong id="eventBulkCount" style="font-size:13px;color:#4a5568;">0 selected</strong>
                <button type="button" class="btn-outline-sm" data-event-bulk="Confirmed" title="Approve selected">
                    <i class="fa-solid fa-check"></i> Approve
                </button>
                <button type="button" class="btn-outline-sm" data-event-bulk="Rejected" title="Reject selected">
                    <i class="fa-solid fa-xmark"></i> Reject
                </button>
                <button type="button" class="btn-outline-sm" data-event-bulk="Cancelled" title="Cancel selected">
                    <i class="fa-solid fa-ban"></i> Cancel
                </button>
                <button type="button" class="btn-outline-sm" id="eventBulkClear">Clear selection</button>
                <span id="eventBulkMessage" role="status" aria-live="polite" style="font-size:13px;color:#718096;"></span>
            </div>

            <!-- Events Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAllEvents" aria-label="Select all events on this page"></th>
                            <th>Reservation ID</th>
                            <th>Event / Service</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Date &amp; Time</th>
                            <th>Guests</th>
                            <th>Package</th>
                            <th>Status</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <!-- Rendered dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- T-28: pagination -->
            <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;padding:12px 4px 2px;">
                <span style="font-size:13px;color:#718096;">
                    Showing <?php echo (int) $eventShownFrom; ?>&ndash;<?php echo (int) $eventShownTo; ?>
                    of <?php echo (int) $totalRows; ?> event<?php echo $totalRows === 1 ? '' : 's'; ?>
                </span>
                <?php if ($totalPages > 1): ?>
                    <span style="display:flex;gap:6px;align-items:center;font-size:13px;">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo eventH($eventPageUrl(1)); ?>" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#4a5568;">&laquo; First</a>
                            <a href="<?php echo eventH($eventPageUrl($page - 1)); ?>" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#4a5568;">&lsaquo; Prev</a>
                        <?php endif; ?>
                        <span style="color:#4a5568;padding:0 6px;">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></span>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo eventH($eventPageUrl($page + 1)); ?>" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#4a5568;">Next &rsaquo;</a>
                            <a href="<?php echo eventH($eventPageUrl($totalPages)); ?>" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#4a5568;">Last &raquo;</a>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Event Details Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-box">
            <h3 id="view_event_title">Event Details</h3>
            
            <div class="detail-row"><strong>Reservation ID:</strong> <span id="v_id"></span></div>
            <div class="detail-row"><strong>Customer:</strong> <span id="v_customer"></span></div>
            <div class="detail-row"><strong>Contact Number:</strong> <span id="v_phone"></span></div>
            <div class="detail-row"><strong>Email:</strong> <span id="v_email"></span></div>
            <div class="detail-row"><strong>Event Type:</strong> <span id="v_type"></span></div>
            <div class="detail-row"><strong>Event Date:</strong> <span id="v_date"></span></div>
            <div class="detail-row"><strong>Event Time:</strong> <span id="v_time"></span></div>
            <div class="detail-row"><strong>Guest Count:</strong> <span id="v_guests"></span></div>
            <div class="detail-row"><strong>Status:</strong> <span id="v_status"></span></div>
            <hr style="border:0; border-top: 1px solid var(--border-color); margin: 15px 0;">
            <div class="detail-row"><strong>Catering Package:</strong> <span id="v_package"></span></div>
            <div class="detail-row"><strong>Additional Services:</strong> <span id="v_services"></span></div>
            <div class="detail-row"><strong>Estimated Total:</strong> <span id="v_total"></span></div>

            <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Client-Side JavaScript Logic -->
    <script>
        // Reservation data from the PHP query
        const eventsData = <?php echo json_encode($reservations, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
        const PDR_CSRF_TOKEN = <?php echo json_encode(pdrCsrfToken()); ?>;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (ch) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[ch];
            });
        }

        // Format a YYYY-MM-DD date without timezone drift.
        function formatDate(dateStr) {
            if (!dateStr) return '—';
            if (window.PDRFormat) {
                return PDRFormat.format(dateStr) || dateStr;
            }
            const date = new Date(dateStr + 'T00:00:00');
            if (isNaN(date.getTime())) return dateStr;
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        function statusClass(status) {
            const value = String(status || '').toLowerCase();
            if (['pending', 'confirmed', 'cancelled', 'completed', 'rejected'].indexOf(value) !== -1) {
                return 'status-' + value;
            }
            return 'status-pending';
        }

        function formatCurrency(value) {
            const amount = Number(value);
            if (!isFinite(amount)) return '₱0.00';
            return '₱' + amount.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function additionalServicesText(raw) {
            if (!raw) return 'None';

            try {
                const items = typeof raw === 'string' ? JSON.parse(raw) : raw;
                if (!Array.isArray(items) || items.length === 0) return 'None';

                const names = items.map(function (item) {
                    if (typeof item === 'string') return item;
                    if (item && typeof item === 'object') {
                        return item.name || item.packageName || '';
                    }
                    return '';
                }).filter(Boolean);

                return names.length ? names.join(', ') : 'None';
            } catch (err) {
                return String(raw);
            }
        }

        function renderEventsTable() {
            const tbody = document.getElementById('eventsTableBody');
            tbody.innerHTML = '';

            if (!Array.isArray(eventsData) || eventsData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; color: var(--text-muted); padding:30px;">No events available.</td></tr>';
                return;
            }

            eventsData.forEach(function (event, index) {
                const tr = document.createElement('tr');
                const guests = Number(event.guests) || 0;

                tr.innerHTML = `
                    <td><input type="checkbox" class="event-check" value="${escapeHtml(event.reservation_id)}" aria-label="Select reservation ${escapeHtml(event.reservation_id)}"></td>
                    <td><strong>${escapeHtml(event.reservation_id)}</strong></td>
                    <td>${escapeHtml(event.main_service || '—')}</td>
                    <td>${escapeHtml(event.customer_name || '—')}</td>
                    <td>${escapeHtml(event.event_type || '—')}</td>
                    <td>
                        ${escapeHtml(formatDate(event.event_date))}
                        <br><small style="color:var(--text-muted);">${escapeHtml(event.event_time || '—')}</small>
                    </td>
                    <td>${guests} pax</td>
                    <td>${escapeHtml(event.selected_package || '—')}</td>
                    <td><span class="status-badge ${statusClass(event.status)}">${escapeHtml(event.status || 'Pending')}</span></td>
                    <td style="text-align: right;">
                        <button type="button" class="btn-outline-sm" onclick="viewDetails(${index})">
                            <i class="fa-solid fa-eye"></i> View
                        </button>
                        <button type="button" class="btn-outline-sm" onclick="archiveEvent(${index})" title="Archive event">
                            <i class="fa-solid fa-box-archive"></i>
                        </button>
                    </td>
                `;

                tbody.appendChild(tr);
            });
        }

        function viewDetails(index) {
            const data = eventsData[index];
            if (!data) return;

            document.getElementById('view_event_title').innerText = 'Reservation ' + (data.reservation_id || '');
            document.getElementById('v_id').innerText = data.reservation_id || '—';
            document.getElementById('v_customer').innerText = data.customer_name || '—';
            document.getElementById('v_phone').innerText = data.contact_number || 'Not provided';
            document.getElementById('v_email').innerText = data.email || 'Not provided';
            document.getElementById('v_type').innerText = data.event_type || 'Not provided';
            document.getElementById('v_date').innerText = formatDate(data.event_date);
            document.getElementById('v_time').innerText = data.event_time || 'Not provided';
            document.getElementById('v_guests').innerText = (Number(data.guests) || 0) + ' Guests';
            document.getElementById('v_status').innerText = data.status || 'Pending';
            document.getElementById('v_status').className = 'status-badge ' + statusClass(data.status);
            document.getElementById('v_package').innerText = data.selected_package || 'Not provided';
            document.getElementById('v_services').innerText = additionalServicesText(data.additional_services);
            document.getElementById('v_total').innerText = formatCurrency(data.estimated_total);

            document.getElementById('viewModal').style.display = 'flex';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        // T-22: archive one event.
        async function archiveEvent(index) {
            const item = eventsData[index];
            if (!item || !item.id) return;
            if (!confirm('Archive reservation ' + item.reservation_id + '?\n\nYou can restore it from the Archive page.')) {
                return;
            }
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
                        id: Number(item.id),
                        reason: 'Archived from Events list'
                    })
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.ok) {
                    alert('Could not archive this event.');
                    return;
                }
                eventsData.splice(index, 1);
                renderEventsTable();
                initEventBulk();
            } catch (err) {
                alert('Network error while archiving this event.');
            }
        }

        // T-43: bulk approve / reject / cancel for the events table.
        function initEventBulk() {
            const bar = document.getElementById('eventBulkBar');
            const countLabel = document.getElementById('eventBulkCount');
            const message = document.getElementById('eventBulkMessage');
            const selectAll = document.getElementById('selectAllEvents');
            const checks = Array.prototype.slice.call(document.querySelectorAll('.event-check'));
            if (!bar) return;

            function selected() {
                return checks.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
            }
            function refresh() {
                const ids = selected();
                countLabel.textContent = ids.length + ' selected';
                bar.style.display = ids.length ? 'flex' : 'none';
                if (selectAll) {
                    selectAll.checked = ids.length > 0 && ids.length === checks.length;
                    selectAll.indeterminate = ids.length > 0 && ids.length < checks.length;
                }
            }

            if (selectAll) {
                selectAll.onchange = function () {
                    checks.forEach(function (c) { c.checked = selectAll.checked; });
                    refresh();
                };
            }
            checks.forEach(function (c) { c.onchange = refresh; });

            document.querySelectorAll('[data-event-bulk]').forEach(function (button) {
                button.onclick = async function () {
                    const ids = selected();
                    const status = button.dataset.eventBulk || '';
                    if (!ids.length || !status) return;
                    if (!confirm('Set ' + ids.length + ' event(s) to ' + status + '?')) return;

                    button.disabled = true;
                    message.textContent = 'Working...';
                    try {
                        const res = await fetch('../api/admin/bulk_status.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': PDR_CSRF_TOKEN
                            },
                            body: JSON.stringify({ reservationIds: ids, status: status })
                        });
                        const data = await res.json().catch(function () { return {}; });
                        if (!res.ok || !data.ok) {
                            message.textContent = 'Bulk update failed.';
                            return;
                        }
                        message.textContent = (data.updated || []).length + ' updated, ' +
                            (data.failed || []).length + ' failed.';
                        setTimeout(function () { window.location.reload(); }, 600);
                    } catch (err) {
                        message.textContent = 'Network error during bulk update.';
                    } finally {
                        button.disabled = false;
                        refresh();
                    }
                };
            });

            document.getElementById('eventBulkClear').onclick = function () {
                checks.forEach(function (c) { c.checked = false; });
                if (selectAll) selectAll.checked = false;
                refresh();
            };

            refresh();
        }

        document.addEventListener('DOMContentLoaded', function () {
            renderEventsTable();
            initEventBulk();
        });
    </script>
<script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
<script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
<script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>
