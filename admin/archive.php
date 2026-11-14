<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/rbac.php';

// T-22 revision: "History" is the read-only record of finished bookings.
// Archived (soft-deleted) rows remain available on the second tab for admins
// and staff who still use the archive workflow.
pdrRequirePageCapability('view');
$adminName = (string) ($_SESSION['admin_name'] ?? 'Admin User');
$canArchive = pdrCan('manage_archive');

$hq = trim((string) ($_GET['hq'] ?? ''));
$hstatus = trim((string) ($_GET['hstatus'] ?? ''));
if (!in_array($hstatus, ['', 'Pending', 'Confirmed', 'Cancelled', 'Completed', 'Rejected'], true)) {
    $hstatus = '';
}
$hfrom = trim((string) ($_GET['hfrom'] ?? ''));
$hto = trim((string) ($_GET['hto'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hfrom)) {
    $hfrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hto)) {
    $hto = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - Pio De Roda Cafe</title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo pdrAsset('archive.css'); ?>" />
    <link rel="stylesheet" href="<?php echo pdrAsset('filters.css'); ?>" />
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>" />
</head>
<body>

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
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                <li class="nav-item"><a href="reservation.php"><i class="fa-solid fa-calendar-check"></i> Reservations</a></li>
                <li class="nav-item"><a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> Calendar</a></li>
                <li class="nav-item"><a href="service.php"><i class="fa-solid fa-concierge-bell"></i> Services</a></li>
                <li class="nav-item"><a href="event.php"><i class="fa-solid fa-calendar-days"></i> Events</a></li>
                <li class="nav-item"><a href="customer.php"><i class="fa-solid fa-users"></i> Customers</a></li>
                <li class="nav-item"><a href="payment.php"><i class="fa-solid fa-money-bill-wave"></i> Payment</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a></li>
                <li class="nav-item active">
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

    <div class="main-wrapper">
        <div class="top-navbar">
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Booking History</span>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></p>
                    <span><?php echo htmlspecialchars(pdrRoleLabel(), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </div>

        <div class="content-body">
            <div class="page-header-container">
                <div class="page-title-group">
                    <h1>Booking History</h1>
                    <p>Completed, cancelled, rejected and past reservations. This view is read-only; use Reservations or Events to manage upcoming bookings.</p>
                </div>
            </div>

            <div id="pageAlert" role="status" aria-live="polite" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>

            <div class="filter-tabs" style="margin-bottom:16px;">
                <button type="button" class="tab-btn active" data-history-tab="past">Past Bookings</button>
                <?php if ($canArchive): ?>
                    <button type="button" class="tab-btn" data-history-tab="archived">Archived Records</button>
                <?php endif; ?>
            </div>

            <!-- Past / finished bookings -->
            <section id="pastPanel">
                <form method="get" action="archive.php" class="filter-bar" id="historyFilterForm">
                    <input type="search" name="hq" class="search-input"
                        placeholder="Search reservation ID, customer or service..."
                        value="<?php echo htmlspecialchars($hq, ENT_QUOTES, 'UTF-8'); ?>">

                    <label class="filter-field"><span>Status</span>
                        <select name="hstatus" class="filter-select">
                            <option value="">All Statuses</option>
                            <?php foreach (['Pending', 'Confirmed', 'Cancelled', 'Rejected', 'Completed'] as $opt): ?>
                                <option value="<?php echo $opt; ?>" <?php echo $hstatus === $opt ? 'selected' : ''; ?>>
                                    <?php echo $opt; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="filter-field"><span>From</span>
                        <input type="date" name="hfrom" class="filter-date"
                            value="<?php echo htmlspecialchars($hfrom, ENT_QUOTES, 'UTF-8'); ?>">
                    </label>

                    <label class="filter-field"><span>To</span>
                        <input type="date" name="hto" class="filter-date"
                            value="<?php echo htmlspecialchars($hto, ENT_QUOTES, 'UTF-8'); ?>">
                    </label>

                    <button type="submit" class="btn-filter">Filter</button>
                    <a class="btn-reset" href="archive.php">Reset</a>
                </form>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Reservation ID</th>
                                <th>Customer</th>
                                <th>Service</th>
                                <th>Event Date &amp; Time</th>
                                <th>Guests</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="historyTableBody">
                            <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:30px;">Loading history&hellip;</td></tr>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;padding:12px 4px 2px;">
                    <span id="historyRange" style="font-size:13px;color:#718096;"></span>
                    <span style="display:flex;gap:6px;align-items:center;">
                        <button type="button" class="btn-reset" id="historyPrev" style="padding:7px 12px;">&lsaquo; Prev</button>
                        <span id="historyPage" style="font-size:13px;color:#4a5568;"></span>
                        <button type="button" class="btn-reset" id="historyNext" style="padding:7px 12px;">Next &rsaquo;</button>
                    </span>
                </div>
            </section>

            <?php if ($canArchive): ?>
            <!-- Archived (soft-deleted) records, kept for restore/cleanup -->
            <section id="archivedPanel" hidden>
                <div class="controls-card">
                    <div class="filter-tabs">
                        <button class="tab-btn active" data-archive-tab="reservations">Reservations</button>
                        <button class="tab-btn" data-archive-tab="customers">Customers</button>
                        <button class="tab-btn" data-archive-tab="reviews">Reviews &amp; Feedback</button>
                    </div>

                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Search archived records..." autocomplete="off">
                    </div>
                </div>

                <div class="table-container">
                    <table>
                        <thead id="archiveTableHead"></thead>
                        <tbody id="archiveTableBody">
                            <tr><td style="text-align:center;color:var(--text-muted);padding:30px;">Loading archived records&hellip;</td></tr>
                        </tbody>
                    </table>
                </div>

                <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;padding:12px 4px 2px;">
                    <span id="archiveRange" style="font-size:13px;color:#718096;"></span>
                    <span style="display:flex;gap:6px;align-items:center;">
                        <button type="button" class="btn-reset" id="archivePrev" style="padding:7px 12px;">&lsaquo; Prev</button>
                        <span id="archivePage" style="font-size:13px;color:#4a5568;"></span>
                        <button type="button" class="btn-reset" id="archiveNext" style="padding:7px 12px;">Next &rsaquo;</button>
                    </span>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reservation detail modal -->
    <div id="historyModal" class="modal-backdrop" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="historyModalTitle">
            <div class="modal-header">
                <h2 id="historyModalTitle">Reservation details</h2>
                <button type="button" class="modal-close" id="historyModalClose" aria-label="Close">&times;</button>
            </div>
            <div class="modal-body" id="historyModalBody"></div>
        </div>
    </div>

    <script>
        const PDR_CSRF_TOKEN = <?php echo json_encode(pdrCsrfToken()); ?>;
        const CAN_ARCHIVE = <?php echo $canArchive ? 'true' : 'false'; ?>;
        const HISTORY_INITIAL = {
            q: <?php echo json_encode($hq); ?>,
            status: <?php echo json_encode($hstatus); ?>,
            from: <?php echo json_encode($hfrom); ?>,
            to: <?php echo json_encode($hto); ?>
        };

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
            });
        }
        function fmtDate(value) {
            if (!value) return '—';
            return window.PDRFormat ? (PDRFormat.format(value) || String(value)) : String(value);
        }
        function fmtDateTime(value) {
            if (!value) return '—';
            return window.PDRFormat ? (PDRFormat.formatDateTime(value) || String(value)) : String(value);
        }
        function showAlert(message, type) {
            const box = document.getElementById('pageAlert');
            if (!box) return;
            box.textContent = message;
            box.style.display = 'block';
            box.style.background = type === 'error' ? '#fef2f2' : '#f0fdf4';
            box.style.color = type === 'error' ? '#b91c1c' : '#166534';
            box.style.border = '1px solid ' + (type === 'error' ? '#fecaca' : '#bbf7d0');
        }

        /* ---------------- Past bookings ---------------- */
        const historyState = {
            q: HISTORY_INITIAL.q || '',
            status: HISTORY_INITIAL.status || '',
            from: HISTORY_INITIAL.from || '',
            to: HISTORY_INITIAL.to || '',
            page: 1,
            perPage: 25,
            total: 0,
            totalPages: 1,
            records: []
        };

        async function loadHistory(page) {
            if (page) historyState.page = page;
            const tbody = document.getElementById('historyTableBody');
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:30px;">Loading history&hellip;</td></tr>';

            const params = new URLSearchParams({
                history: 1,
                page: historyState.page,
                per_page: historyState.perPage
            });
            if (historyState.q) params.set('q', historyState.q);
            if (historyState.status) params.set('status', historyState.status);
            if (historyState.from) params.set('from', historyState.from);
            if (historyState.to) params.set('to', historyState.to);

            try {
                const res = await fetch('../api/admin/reservations.php?' + params.toString(), {
                    headers: { Accept: 'application/json' }
                });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data.reason || 'load');
                historyState.records = data.reservations || [];
                historyState.total = data.meta.total;
                historyState.totalPages = data.meta.totalPages;
                historyState.page = data.meta.page;
                renderHistoryTable();
                renderHistoryPagination();
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#b91c1c;padding:30px;">Could not load booking history.</td></tr>';
                showAlert('Could not load booking history.', 'error');
            }
        }

        function renderHistoryTable() {
            const tbody = document.getElementById('historyTableBody');
            if (!historyState.records.length) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:30px;">No past bookings match these filters.</td></tr>';
                return;
            }
            tbody.innerHTML = historyState.records.map(function (r) {
                return '<tr>' +
                    '<td><strong>' + esc(r.id) + '</strong></td>' +
                    '<td>' + esc(r.customerName) + '</td>' +
                    '<td>' + esc(r.service) + '</td>' +
                    '<td>' + fmtDate(r.eventDate) + '<br><small style="color:#718096;">' + esc(r.eventTime || '—') + '</small></td>' +
                    '<td>' + esc(r.guests) + '</td>' +
                    '<td>' + esc(r.estimatedTotal) + '</td>' +
                    '<td><span class="history-status status-' + esc(String(r.status || '').toLowerCase()) + '">' + esc(r.status) + '</span></td>' +
                    '<td><button type="button" class="btn-restore-sm" data-history-id="' + esc(r.id) + '"><i class="fa-solid fa-eye"></i> View</button></td>' +
                '</tr>';
            }).join('');

            tbody.querySelectorAll('[data-history-id]').forEach(function (btn) {
                btn.onclick = function () { openHistoryModal(btn.dataset.historyId); };
            });
        }

        function renderHistoryPagination() {
            const from = historyState.total === 0 ? 0 : ((historyState.page - 1) * historyState.perPage) + 1;
            const to = Math.min(historyState.page * historyState.perPage, historyState.total);
            document.getElementById('historyRange').textContent =
                'Showing ' + from + '–' + to + ' of ' + historyState.total + ' booking' + (historyState.total === 1 ? '' : 's');
            document.getElementById('historyPage').textContent = 'Page ' + historyState.page + ' of ' + historyState.totalPages;
            document.getElementById('historyPrev').disabled = historyState.page <= 1;
            document.getElementById('historyNext').disabled = historyState.page >= historyState.totalPages;
        }

        function openHistoryModal(reservationId) {
            const record = historyState.records.find(function (r) { return String(r.id) === String(reservationId); });
            if (!record) return;
            const body = document.getElementById('historyModalBody');
            const packages = record.selectedPackage || '—';
            const address = record.address || '—';

            body.innerHTML =
                '<dl class="detail-list">' +
                    '<div><dt>Reservation ID</dt><dd><strong>' + esc(record.id) + '</strong></dd></div>' +
                    '<div><dt>Status</dt><dd><span class="history-status status-' + esc(String(record.status || '').toLowerCase()) + '">' + esc(record.status) + '</span></dd></div>' +
                    '<div><dt>Customer</dt><dd>' + esc(record.customerName) + '</dd></div>' +
                    '<div><dt>Contact</dt><dd>' + esc(record.contactNumber || '—') + '</dd></div>' +
                    '<div><dt>Email</dt><dd>' + esc(record.email || '—') + '</dd></div>' +
                    '<div><dt>Address</dt><dd>' + esc(address) + '</dd></div>' +
                    '<div><dt>Service</dt><dd>' + esc(record.service) + '</dd></div>' +
                    '<div><dt>Package</dt><dd>' + esc(packages) + '</dd></div>' +
                    '<div><dt>Event date</dt><dd>' + fmtDate(record.eventDate) + ' at ' + esc(record.eventTime || '—') + '</dd></div>' +
                    '<div><dt>Guests</dt><dd>' + esc(record.guests) + ' pax</dd></div>' +
                    '<div><dt>Estimated total</dt><dd><strong>' + esc(record.estimatedTotal) + '</strong></dd></div>' +
                    '<div><dt>Submitted</dt><dd>' + fmtDateTime(record.submittedAt) + '</dd></div>' +
                '</dl>';

            const modal = document.getElementById('historyModal');
            modal.hidden = false;
            document.getElementById('historyModalClose').focus();
        }

        function closeHistoryModal() {
            document.getElementById('historyModal').hidden = true;
        }

        /* ---------------- Archived records ---------------- */
        const archiveState = { type: 'reservations', q: '', page: 1, total: 0, totalPages: 1, records: [], loaded: false };

        async function loadArchive() {
            if (!CAN_ARCHIVE) return;
            const tbody = document.getElementById('archiveTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td style="text-align:center;color:var(--text-muted);padding:30px;">Loading archived records&hellip;</td></tr>';

            const params = new URLSearchParams({ type: archiveState.type, page: archiveState.page, per_page: 50 });
            if (archiveState.q) params.set('q', archiveState.q);

            try {
                const res = await fetch('../api/admin/archive.php?' + params.toString(), {
                    headers: { Accept: 'application/json' }
                });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data.reason || 'load');
                archiveState.records = data.records || [];
                archiveState.total = data.meta.total;
                archiveState.totalPages = data.meta.totalPages;
                archiveState.page = data.meta.page;
                archiveState.loaded = true;
                renderArchiveTable();
                renderArchivePagination();
            } catch (err) {
                tbody.innerHTML = '<tr><td style="text-align:center;color:#b91c1c;padding:30px;">Could not load archived records.</td></tr>';
                showAlert('Could not load archived records.', 'error');
            }
        }

        function renderArchiveTable() {
            const thead = document.getElementById('archiveTableHead');
            const tbody = document.getElementById('archiveTableBody');
            tbody.innerHTML = '';

            if (!archiveState.records.length) {
                thead.innerHTML = '<tr><th>Archived</th></tr>';
                tbody.innerHTML = '<tr><td style="text-align:center;color:var(--text-muted);padding:30px;">No archived records found.</td></tr>';
                return;
            }

            if (archiveState.type === 'reservations') {
                thead.innerHTML = '<tr><th>Reservation ID</th><th>Customer</th><th>Service</th><th>Event Date</th><th>Status</th><th>Archived On</th><th>Reason</th><th>Actions</th></tr>';
                archiveState.records.forEach(function (r) {
                    tbody.insertAdjacentHTML('beforeend',
                        '<tr><td><strong>' + esc(r.reservation_id) + '</strong></td>' +
                        '<td>' + esc(r.customer_name) + '</td>' +
                        '<td>' + esc(r.main_service) + '</td>' +
                        '<td>' + fmtDate(r.event_date) + '</td>' +
                        '<td>' + esc(r.status) + '</td>' +
                        '<td>' + fmtDateTime(r.archived_at) + '</td>' +
                        '<td><span class="category-badge">' + esc(r.archive_reason || '—') + '</span></td>' +
                        archiveActionCell(r.id) + '</tr>');
                });
            } else if (archiveState.type === 'customers') {
                thead.innerHTML = '<tr><th>Customer Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Archived On</th><th>Reason</th><th>Actions</th></tr>';
                archiveState.records.forEach(function (r) {
                    tbody.insertAdjacentHTML('beforeend',
                        '<tr><td><strong>' + esc(r.full_name) + '</strong></td>' +
                        '<td>' + esc(r.email) + '</td>' +
                        '<td>' + esc(r.phone || '—') + '</td>' +
                        '<td>' + esc(r.status) + '</td>' +
                        '<td>' + fmtDateTime(r.archived_at) + '</td>' +
                        '<td><span class="category-badge">' + esc(r.archive_reason || '—') + '</span></td>' +
                        archiveActionCell(r.id) + '</tr>');
                });
            } else {
                thead.innerHTML = '<tr><th>Customer</th><th>Rating</th><th>Feedback</th><th>Moderation</th><th>Archived On</th><th>Actions</th></tr>';
                archiveState.records.forEach(function (r) {
                    tbody.insertAdjacentHTML('beforeend',
                        '<tr><td><strong>' + esc(r.customer_name) + '</strong></td>' +
                        '<td><span style="color:var(--gold);font-weight:600;">' + esc(r.rating) + ' / 5</span></td>' +
                        '<td>' + esc(r.feedback_text) + '</td>' +
                        '<td>' + esc(r.status) + '</td>' +
                        '<td>' + fmtDateTime(r.archived_at) + '</td>' +
                        archiveActionCell(r.id) + '</tr>');
                });
            }

            tbody.querySelectorAll('[data-restore]').forEach(function (btn) {
                btn.onclick = function () { restoreRecord(Number(btn.dataset.restore)); };
            });
            tbody.querySelectorAll('[data-delete]').forEach(function (btn) {
                btn.onclick = function () { deleteRecord(Number(btn.dataset.delete)); };
            });
        }

        function archiveActionCell(id) {
            return '<td style="white-space:nowrap;">' +
                '<button type="button" class="btn-restore-sm" data-restore="' + id + '" title="Restore"><i class="fa-solid fa-rotate-left"></i> Restore</button> ' +
                '<button type="button" class="btn-delete-sm" data-delete="' + id + '" title="Delete permanently"><i class="fa-solid fa-trash"></i></button>' +
                '</td>';
        }

        function renderArchivePagination() {
            const from = archiveState.total === 0 ? 0 : ((archiveState.page - 1) * 50) + 1;
            const to = Math.min(archiveState.page * 50, archiveState.total);
            document.getElementById('archiveRange').textContent = 'Showing ' + from + '–' + to + ' of ' + archiveState.total + ' record' + (archiveState.total === 1 ? '' : 's');
            document.getElementById('archivePage').textContent = 'Page ' + archiveState.page + ' of ' + archiveState.totalPages;
            document.getElementById('archivePrev').disabled = archiveState.page <= 1;
            document.getElementById('archiveNext').disabled = archiveState.page >= archiveState.totalPages;
        }

        async function archiveAction(action, id) {
            const res = await fetch('../api/admin/archive.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': PDR_CSRF_TOKEN
                },
                body: JSON.stringify({ action: action, type: archiveState.type, id: id })
            });
            const data = await res.json().catch(function () { return {}; });
            if (!res.ok || !data.ok) throw new Error(data.reason || 'action');
            return data;
        }

        async function restoreRecord(id) {
            if (!confirm('Restore this record back to the active lists?')) return;
            try {
                await archiveAction('restore', id);
                showAlert('Record restored.', 'success');
                await loadArchive();
            } catch (err) {
                showAlert('Could not restore the record.', 'error');
            }
        }

        async function deleteRecord(id) {
            if (!confirm('Permanently delete this record?\n\nThis cannot be undone.')) return;
            try {
                await archiveAction('delete', id);
                showAlert('Record permanently deleted.', 'success');
                await loadArchive();
            } catch (err) {
                showAlert('Could not delete the record.', 'error');
            }
        }

        /* ---------------- Wiring ---------------- */
        document.getElementById('historyFilterForm').addEventListener('submit', function () {
            historyState.page = 1;
        });

        document.querySelectorAll('[data-history-tab]').forEach(function (tab) {
            tab.onclick = function () {
                const view = tab.dataset.historyTab;
                document.querySelectorAll('[data-history-tab]').forEach(function (t) {
                    t.classList.toggle('active', t === tab);
                });
                document.getElementById('pastPanel').hidden = view !== 'past';
                const archivedPanel = document.getElementById('archivedPanel');
                if (archivedPanel) archivedPanel.hidden = view !== 'archived';
                if (view === 'archived' && !archiveState.loaded) loadArchive();
            };
        });

        document.getElementById('historyPrev').onclick = function () {
            if (historyState.page > 1) loadHistory(historyState.page - 1);
        };
        document.getElementById('historyNext').onclick = function () {
            if (historyState.page < historyState.totalPages) loadHistory(historyState.page + 1);
        };

        if (CAN_ARCHIVE) {
            document.querySelectorAll('[data-archive-tab]').forEach(function (tab) {
                tab.onclick = function () {
                    archiveState.type = tab.dataset.archiveTab;
                    archiveState.page = 1;
                    document.querySelectorAll('[data-archive-tab]').forEach(function (t) { t.classList.remove('active'); });
                    tab.classList.add('active');
                    loadArchive();
                };
            });

            let searchTimer = null;
            document.getElementById('searchInput').addEventListener('input', function (ev) {
                clearTimeout(searchTimer);
                const value = ev.target.value.trim();
                searchTimer = setTimeout(function () {
                    archiveState.q = value;
                    archiveState.page = 1;
                    loadArchive();
                }, 250);
            });

            document.getElementById('archivePrev').onclick = function () {
                if (archiveState.page > 1) { archiveState.page--; loadArchive(); }
            };
            document.getElementById('archiveNext').onclick = function () {
                if (archiveState.page < archiveState.totalPages) { archiveState.page++; loadArchive(); }
            };
        }

        document.getElementById('historyModalClose').onclick = closeHistoryModal;
        document.getElementById('historyModal').addEventListener('click', function (ev) {
            if (ev.target === ev.currentTarget) closeHistoryModal();
        });
        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') closeHistoryModal();
        });

        document.addEventListener('DOMContentLoaded', function () {
            loadHistory(1);
        });
    </script>
<script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
<script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
<script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>
