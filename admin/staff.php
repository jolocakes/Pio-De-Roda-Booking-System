<?php
require_once __DIR__ . '/auth_check.php';
pdrRequirePageCapability('manage_staff');

$adminName = (string) ($_SESSION['admin_name'] ?? 'Administrator');
$adminRole = pdrRoleLabel();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Accounts - Pio De Roda Cafe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo pdrAsset('reservation.css'); ?>">
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>">
</head>
<body>
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
                <li class="nav-item"><a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a></li>
                <li class="nav-item"><a href="reservation.php"><i class="fa-solid fa-calendar-check"></i> Reservations</a></li>
                <li class="nav-item"><a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> Calendar</a></li>
                <li class="nav-item"><a href="service.php"><i class="fa-solid fa-concierge-bell"></i> Services</a></li>
                <li class="nav-item"><a href="event.php"><i class="fa-solid fa-calendar-days"></i> Events</a></li>
                <li class="nav-item"><a href="customer.php"><i class="fa-solid fa-users"></i> Customers</a></li>
                <li class="nav-item"><a href="payment.php"><i class="fa-solid fa-money-bill-wave"></i> Payment</a></li>
                <li class="nav-item"><a href="reports.php"><i class="fa-solid fa-chart-column"></i> Reports</a></li>
                <li class="nav-item"><a href="archive.php"><i class="fa-solid fa-clock-rotate-left"></i> History</a></li>
                <li class="nav-item active"><a href="staff.php"><i class="fa-solid fa-user-shield"></i> Staff</a></li>
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
                <h1>Staff Accounts</h1>
            </div>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></p>
                    <span><?php echo htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        </header>

        <div class="content-body">
            <div id="pageAlert" class="page-alert" role="status" aria-live="polite"></div>

            <div class="dashboard-card" style="margin-bottom:20px;">
                <div class="card-header">
                    <h3>Add account</h3>
                </div>
                <form id="createStaffForm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;padding:4px 0 8px;">
                    <label>Username
                        <input type="text" name="username" required minlength="3" maxlength="50"
                            pattern="[A-Za-z0-9_.\-]+" style="width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:6px;">
                    </label>
                    <label>Full name
                        <input type="text" name="full_name" required maxlength="120"
                            style="width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:6px;">
                    </label>
                    <label>Password
                        <input type="password" name="password" required minlength="8"
                            style="width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:6px;">
                    </label>
                    <label>Role
                        <select name="role" style="width:100%;padding:9px 11px;border:1px solid #cbd5e1;border-radius:6px;">
                            <option value="staff">Staff</option>
                            <option value="viewer">Viewer</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </label>
                    <button type="submit" class="btn btn-primary" style="padding:10px 18px;">Create account</button>
                </form>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3>All accounts</h3>
                    <span style="font-size:12.5px;color:#718096;">
                        Admin: full access · Staff: daily operations · Viewer: read-only
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Role</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="staffTableBody">
                            <tr><td colspan="5" style="text-align:center;padding:24px;color:#718096;">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const PDR_CSRF_TOKEN = <?php echo json_encode(
            pdrCsrfToken(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>;
        const PDR_CURRENT_ID = <?php echo (int) ($_SESSION['admin_id'] ?? 0); ?>;

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[ch]));
        }

        function alertBox(message, type) {
            const box = document.getElementById('pageAlert');
            box.textContent = message;
            box.className = 'page-alert ' + (type === 'error' ? 'error' : 'success');
            box.style.display = 'block';
            setTimeout(() => { box.style.display = 'none'; }, 6000);
        }

        async function callStaff(payload) {
            const res = await fetch('../api/admin/staff.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': PDR_CSRF_TOKEN
                },
                body: JSON.stringify(payload)
            });
            return { status: res.status, data: await res.json().catch(() => ({})) };
        }

        const reasonText = {
            DUPLICATE_USERNAME: 'That username is already taken.',
            LAST_ADMIN: 'You cannot remove or demote the last administrator.',
            CANNOT_DELETE_SELF: 'You cannot delete the account you are logged in with.',
            INVALID: 'Please check the values you entered.',
            FORBIDDEN: 'Your role cannot manage staff accounts.'
        };

        async function loadStaff() {
            const tbody = document.getElementById('staffTableBody');
            try {
                const res = await fetch('../api/admin/staff.php', { cache: 'no-store' });
                const data = await res.json();
                if (!data.ok) throw new Error(data.reason || 'load failed');

                tbody.innerHTML = '';
                data.accounts.forEach((acc) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${esc(acc.username)}</strong></td>
                        <td>${esc(acc.full_name)}</td>
                        <td>
                            <select data-role-for="${acc.id}" style="padding:5px 8px;border:1px solid #cbd5e1;border-radius:5px;">
                                <option value="admin" ${acc.role_normalized === 'admin' ? 'selected' : ''}>Administrator</option>
                                <option value="staff" ${acc.role_normalized === 'staff' ? 'selected' : ''}>Staff</option>
                                <option value="viewer" ${acc.role_normalized === 'viewer' ? 'selected' : ''}>Viewer</option>
                            </select>
                        </td>
                        <td>${esc(acc.created_at || '')}</td>
                        <td style="white-space:nowrap;">
                            <button type="button" class="btn btn-approve" data-save-role="${acc.id}" title="Save role">
                                <i class="fa-solid fa-floppy-disk"></i>
                            </button>
                            <button type="button" class="btn btn-reject" data-delete-staff="${acc.id}" title="Delete account"
                                ${Number(acc.id) === PDR_CURRENT_ID ? 'disabled' : ''}>
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>`;
                    tbody.appendChild(tr);
                });

                tbody.querySelectorAll('[data-save-role]').forEach((btn) => {
                    btn.addEventListener('click', async () => {
                        const id = btn.dataset.saveRole;
                        const row = btn.closest('tr');
                        const fullName = row.children[1].textContent.trim();
                        const role = row.querySelector('[data-role-for="' + id + '"]').value;
                        const { data } = await callStaff({ action: 'update', id: Number(id), full_name: fullName, role });
                        data.ok ? alertBox('Role updated.', 'success') : alertBox(reasonText[data.reason] || 'Could not update.', 'error');
                    });
                });

                tbody.querySelectorAll('[data-delete-staff]').forEach((btn) => {
                    btn.addEventListener('click', async () => {
                        const id = btn.dataset.deleteStaff;
                        if (!confirm('Delete this account? This cannot be undone.')) return;
                        const { data } = await callStaff({ action: 'delete', id: Number(id) });
                        if (data.ok) {
                            alertBox('Account deleted.', 'success');
                            loadStaff();
                        } else {
                            alertBox(reasonText[data.reason] || 'Could not delete.', 'error');
                        }
                    });
                });
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:24px;color:#991b1b;">Could not load accounts.</td></tr>';
            }
        }

        document.getElementById('createStaffForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const form = event.target;
            const payload = {
                action: 'create',
                username: form.username.value.trim(),
                full_name: form.full_name.value.trim(),
                password: form.password.value,
                role: form.role.value
            };
            const { data } = await callStaff(payload);
            if (data.ok) {
                form.reset();
                alertBox('Account created.', 'success');
                loadStaff();
            } else {
                alertBox(reasonText[data.reason] || 'Could not create the account.', 'error');
            }
        });

        loadStaff();
    </script>
    <script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
    <script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
    <script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>
