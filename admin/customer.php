<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/rbac.php';

pdrRequirePageCapability('view');
$canManageCustomers = pdrCan('manage_customers');
$canArchive = pdrCan('manage_archive');
$adminName = (string) ($_SESSION['admin_name'] ?? 'Admin User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Pio De Roda Cafe</title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo pdrAsset('customer.css'); ?>" />
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
                <li class="nav-item active"><a href="customer.php"><i class="fa-solid fa-users"></i> Customers</a></li>
                <li class="nav-item"><a href="payment.php"><i class="fa-solid fa-money-bill-wave"></i> Payment</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a></li>
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

    <div class="main-wrapper">
        <div class="top-navbar">
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Customers Management</span>
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
                    <p>Manage customer profiles, reservation history, and account status. Records are derived automatically from reservations.</p>
                </div>
                <?php if ($canManageCustomers): ?>
                <button class="btn-add-new" type="button" onclick="openCustomerModal()">
                    <i class="fa-solid fa-plus"></i> Add Customer
                </button>
                <?php endif; ?>
            </div>

            <div id="pageAlert" class="page-alert" role="status" aria-live="polite" style="display:none;margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:13px;"></div>

            <form id="customerFilters" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:#fff;border:1px solid var(--border-color,#e2e8f0);border-radius:8px;padding:12px;margin-bottom:14px;">
                <input type="search" id="customerSearch" placeholder="Search name, email, or phone..."
                    style="flex:1 1 240px;min-width:180px;padding:9px 11px;border:1px solid #cbd5e1;border-radius:6px;">
                <button type="submit" class="btn-add-new" style="padding:9px 16px;">Search</button>
                <button type="button" class="btn-cancel" style="padding:9px 14px;" onclick="resetCustomerSearch()">Reset</button>
                <span id="customerMeta" style="margin-left:auto;font-size:13px;color:#718096;"></span>
            </form>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Bookings</th>
                            <th>Account Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px;">Loading customers&hellip;</td></tr>
                    </tbody>
                </table>
            </div>

            <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;padding:12px 4px 2px;">
                <span id="customerRange" style="font-size:13px;color:#718096;"></span>
                <span style="display:flex;gap:6px;align-items:center;">
                    <button type="button" class="btn-cancel" id="customerPrev" style="padding:7px 12px;">&lsaquo; Prev</button>
                    <span id="customerPage" style="font-size:13px;color:#4a5568;"></span>
                    <button type="button" class="btn-cancel" id="customerNext" style="padding:7px 12px;">Next &rsaquo;</button>
                </span>
            </div>
        </div>
    </div>

    <!-- Add / Edit customer -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="customerModalTitle">
            <h3 id="customerModalTitle">Add New Customer</h3>
            <form id="addCustomerForm" novalidate>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="cust_name">Customer Name</label>
                        <input type="text" id="cust_name" required maxlength="100" placeholder="e.g. Maria Santos">
                    </div>
                    <div class="form-group">
                        <label for="cust_email">Email Address</label>
                        <input type="email" id="cust_email" required maxlength="100" placeholder="e.g. maria@email.com">
                    </div>
                    <div class="form-group">
                        <label for="cust_phone">Phone Number</label>
                        <input type="text" id="cust_phone" maxlength="20" placeholder="e.g. 09123456789">
                    </div>
                    <div class="form-group">
                        <label for="cust_status">Account Status</label>
                        <select id="cust_status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="cust_address">Address</label>
                        <input type="text" id="cust_address" maxlength="255" placeholder="e.g. Indang, Cavite">
                    </div>
                </div>
                <p id="customerFormError" role="alert" style="color:#b91c1c;font-size:13px;margin:8px 0 0;"></p>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeCustomerModal()">Cancel</button>
                    <button type="submit" class="btn-add-new" id="customerSaveBtn">Save Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer detail + booking history -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="detailTitle" style="max-width:760px;width:min(94vw,760px);">
            <h3 id="detailTitle">Customer Details</h3>
            <div id="detailBody"></div>
            <div class="modal-actions" style="margin-top:16px;">
                <button type="button" class="btn-cancel" onclick="closeDetailModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        const PDR_CSRF_TOKEN = <?php echo json_encode(pdrCsrfToken()); ?>;
        const CAN_MANAGE_CUSTOMERS = <?php echo $canManageCustomers ? 'true' : 'false'; ?>;
        const CAN_ARCHIVE = <?php echo $canArchive ? 'true' : 'false'; ?>;

        const state = { page: 1, perPage: 25, q: '', totalPages: 1, total: 0, customers: [] };
        let editingId = 0;

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (ch) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
            });
        }
        function money(value) {
            const n = Number(value);
            return isFinite(n) ? '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '₱0.00';
        }
        function showAlert(message, type) {
            const box = document.getElementById('pageAlert');
            box.textContent = message;
            box.style.display = 'block';
            box.style.background = type === 'error' ? '#fef2f2' : '#f0fdf4';
            box.style.color = type === 'error' ? '#b91c1c' : '#166534';
            box.style.border = '1px solid ' + (type === 'error' ? '#fecaca' : '#bbf7d0');
        }

        async function loadCustomers() {
            const tbody = document.getElementById('customersTableBody');
            const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
            if (state.q) params.set('q', state.q);

            try {
                const res = await fetch('../api/admin/customers.php?' + params.toString(), {
                    headers: { Accept: 'application/json' }
                });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error(data.reason || 'load');

                state.customers = data.customers || [];
                state.total = data.meta.total;
                state.totalPages = data.meta.totalPages;
                state.page = data.meta.page;
                renderCustomers();
                renderPagination();
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#b91c1c;padding:30px;">Could not load customers. Please refresh.</td></tr>';
                showAlert('Could not load customers.', 'error');
            }
        }

        function renderCustomers() {
            const tbody = document.getElementById('customersTableBody');
            tbody.innerHTML = '';

            if (!state.customers.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:30px;">No customers found.</td></tr>';
                return;
            }

            state.customers.forEach(function (cust) {
                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><strong>' + esc(cust.full_name) + '</strong></td>' +
                    '<td>' + esc(cust.email) + '</td>' +
                    '<td>' + esc(cust.phone || '—') + '</td>' +
                    '<td>' + esc(cust.address || '—') + '</td>' +
                    '<td>' + Number(cust.bookings || 0) + '</td>' +
                    '<td><span class="status-badge ' + (cust.status === 'Active' ? 'status-active' : 'status-inactive') + '">' + esc(cust.status) + '</span></td>' +
                    '<td style="white-space:nowrap;">' +
                        '<button type="button" class="btn-outline-sm" data-view="' + Number(cust.id) + '" title="View details"><i class="fa-solid fa-eye"></i></button> ' +
                        (CAN_MANAGE_CUSTOMERS ? '<button type="button" class="btn-outline-sm" data-edit="' + Number(cust.id) + '" title="Edit"><i class="fa-solid fa-pen"></i></button> ' : '') +
                        (CAN_ARCHIVE ? '<button type="button" class="btn-delete-sm" data-archive="' + Number(cust.id) + '" title="Archive"><i class="fa-solid fa-box-archive"></i></button>' : '') +
                    '</td>';
                tbody.appendChild(tr);
            });

            tbody.querySelectorAll('[data-view]').forEach(function (btn) {
                btn.onclick = function () { openDetail(Number(btn.dataset.view)); };
            });
            tbody.querySelectorAll('[data-edit]').forEach(function (btn) {
                btn.onclick = function () { openCustomerModal(Number(btn.dataset.edit)); };
            });
            tbody.querySelectorAll('[data-archive]').forEach(function (btn) {
                btn.onclick = function () { archiveCustomer(Number(btn.dataset.archive)); };
            });
        }

        function renderPagination() {
            const from = state.total === 0 ? 0 : ((state.page - 1) * state.perPage) + 1;
            const to = Math.min(state.page * state.perPage, state.total);
            document.getElementById('customerRange').textContent = 'Showing ' + from + '–' + to + ' of ' + state.total + ' customer' + (state.total === 1 ? '' : 's');
            document.getElementById('customerPage').textContent = 'Page ' + state.page + ' of ' + state.totalPages;
            document.getElementById('customerPrev').disabled = state.page <= 1;
            document.getElementById('customerNext').disabled = state.page >= state.totalPages;
        }

        async function openDetail(id) {
            const box = document.getElementById('detailBody');
            box.innerHTML = '<p style="color:#718096;">Loading…</p>';
            document.getElementById('detailModal').style.display = 'flex';

            try {
                const res = await fetch('../api/admin/customers.php?id=' + encodeURIComponent(id), {
                    headers: { Accept: 'application/json' }
                });
                const data = await res.json();
                if (!res.ok || !data.ok) throw new Error('detail');
                const c = data.customer;
                const history = data.history || [];

                let html =
                    '<div class="detail-row"><strong>Name:</strong> <span>' + esc(c.full_name) + '</span></div>' +
                    '<div class="detail-row"><strong>Email:</strong> <span>' + esc(c.email) + '</span></div>' +
                    '<div class="detail-row"><strong>Phone:</strong> <span>' + esc(c.phone || '—') + '</span></div>' +
                    '<div class="detail-row"><strong>Address:</strong> <span>' + esc(c.address || '—') + '</span></div>' +
                    '<div class="detail-row"><strong>Status:</strong> <span>' + esc(c.status) + '</span></div>' +
                    '<div class="detail-row"><strong>Bookings:</strong> <span>' + Number(c.bookings || 0) + '</span></div>' +
                    '<div class="detail-row"><strong>Lifetime value:</strong> <span>' + money(c.lifetime_value) + '</span></div>' +
                    '<hr style="border:0;border-top:1px solid #e2e8f0;margin:14px 0;">' +
                    '<h4 style="margin:0 0 8px;font-size:15px;">Booking history</h4>';

                if (!history.length) {
                    html += '<p style="color:#718096;font-size:13px;">No reservations yet.</p>';
                } else {
                    html += '<div style="overflow:auto;"><table style="width:100%;font-size:13px;"><thead><tr>' +
                        '<th>ID</th><th>Service</th><th>Date</th><th>Status</th><th>Total</th></tr></thead><tbody>';
                    history.forEach(function (r) {
                        html += '<tr>' +
                            '<td><strong>' + esc(r.reservation_id) + '</strong></td>' +
                            '<td>' + esc(r.main_service) + '</td>' +
                            '<td>' + esc(window.PDRFormat ? (PDRFormat.format(r.event_date) || r.event_date) : r.event_date) + '</td>' +
                            '<td>' + esc(r.status) + '</td>' +
                            '<td>' + money(r.estimated_total) + '</td>' +
                        '</tr>';
                    });
                    html += '</tbody></table></div>';
                }

                box.innerHTML = html;
            } catch (err) {
                box.innerHTML = '<p style="color:#b91c1c;">Could not load the customer.</p>';
            }
        }

        function closeDetailModal() { document.getElementById('detailModal').style.display = 'none'; }

        function openCustomerModal(id) {
            editingId = Number(id || 0);
            const form = document.getElementById('addCustomerForm');
            form.reset();
            document.getElementById('customerFormError').textContent = '';
            document.getElementById('customerModalTitle').textContent = editingId ? 'Edit Customer' : 'Add New Customer';
            document.getElementById('customerSaveBtn').textContent = editingId ? 'Save Changes' : 'Save Customer';

            if (editingId) {
                const c = state.customers.find(function (row) { return Number(row.id) === editingId; });
                if (c) {
                    document.getElementById('cust_name').value = c.full_name || '';
                    document.getElementById('cust_email').value = c.email || '';
                    document.getElementById('cust_phone').value = c.phone || '';
                    document.getElementById('cust_address').value = c.address || '';
                    document.getElementById('cust_status').value = c.status || 'Active';
                }
            }

            document.getElementById('addModal').style.display = 'flex';
            document.getElementById('cust_name').focus();
        }
        function closeCustomerModal() { document.getElementById('addModal').style.display = 'none'; }

        document.getElementById('addCustomerForm').addEventListener('submit', async function (ev) {
            ev.preventDefault();
            const errorBox = document.getElementById('customerFormError');
            errorBox.textContent = '';

            const payload = {
                action: editingId ? 'update' : 'create',
                id: editingId || undefined,
                full_name: document.getElementById('cust_name').value.trim(),
                email: document.getElementById('cust_email').value.trim(),
                phone: document.getElementById('cust_phone').value.trim(),
                address: document.getElementById('cust_address').value.trim(),
                status: document.getElementById('cust_status').value
            };

            if (!payload.full_name || !payload.email) {
                errorBox.textContent = 'Name and email are required.';
                return;
            }

            const saveBtn = document.getElementById('customerSaveBtn');
            saveBtn.disabled = true;
            try {
                const res = await fetch('../api/admin/customers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': PDR_CSRF_TOKEN
                    },
                    body: JSON.stringify(payload)
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.ok) {
                    const messages = {
                        EMAIL_EXISTS: 'That email address already belongs to another customer.',
                        INVALID: 'Please check the name and email address.',
                        FORBIDDEN: 'Your role cannot manage customers.'
                    };
                    errorBox.textContent = messages[data.reason] || 'Could not save the customer.';
                    return;
                }
                closeCustomerModal();
                showAlert(editingId ? 'Customer updated.' : 'Customer added.', 'success');
                await loadCustomers();
            } catch (err) {
                errorBox.textContent = 'Network error while saving.';
            } finally {
                saveBtn.disabled = false;
            }
        });

        async function archiveCustomer(id) {
            const c = state.customers.find(function (row) { return Number(row.id) === id; });
            if (!confirm('Archive ' + (c ? c.full_name : 'this customer') + '?\n\nYou can restore them from History → Archived Records.')) return;

            try {
                const res = await fetch('../api/admin/customers.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': PDR_CSRF_TOKEN
                    },
                    body: JSON.stringify({ action: 'archive', id: id, reason: 'Archived from Customers list' })
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data.ok) {
                    showAlert('Could not archive the customer.', 'error');
                    return;
                }
                showAlert('Customer archived.', 'success');
                await loadCustomers();
            } catch (err) {
                showAlert('Network error while archiving.', 'error');
            }
        }

        document.getElementById('customerFilters').addEventListener('submit', function (ev) {
            ev.preventDefault();
            state.q = document.getElementById('customerSearch').value.trim();
            state.page = 1;
            loadCustomers();
        });
        function resetCustomerSearch() {
            document.getElementById('customerSearch').value = '';
            state.q = '';
            state.page = 1;
            loadCustomers();
        }
        document.getElementById('customerPrev').onclick = function () {
            if (state.page > 1) { state.page--; loadCustomers(); }
        };
        document.getElementById('customerNext').onclick = function () {
            if (state.page < state.totalPages) { state.page++; loadCustomers(); }
        };

        document.addEventListener('DOMContentLoaded', loadCustomers);
    </script>
<script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
<script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
<script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>
