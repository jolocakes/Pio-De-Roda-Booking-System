<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';

/** Escape every value rendered into HTML. */
function pdrH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$reservations = [];
try {
    $stmt = $pdo->query("SELECT * FROM reservations ORDER BY id DESC");
    $rows = $stmt->fetchAll();
    foreach ($rows as $r) {
        $reservations[] = [
            'id' => $r['reservation_id'],
            'customer' => $r['customer_name'],
            'phone' => $r['contact_number'],
            'email' => $r['email'],
            'address' => $r['address'] ?? null,
            'service' => $r['main_service'],
            'date' => $r['event_date'],
            'time' => $r['event_time'],
            'guests' => $r['guests'],
            'status' => $r['status'],
            'payment' => $r['payment_method'],
            'amount' => '₱' . number_format($r['estimated_total'], 2),
        ];
    }
} catch (Exception $e) {
    $reservations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management - Pio De Roda</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="reservation.css" />
    <link rel="stylesheet" href="responsive.css" />

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
                    <a href="archive.php"><i class="fa-solid fa-box-archive"></i> Archive</a>
                </li>
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
                <i class="fa-solid fa-bars" style="color: #718096; cursor: pointer;"></i>
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

            <!-- Search & Filter Controls -->
            <div class="filter-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Search by customer name or reservation ID..." onkeyup="filterTable()">
                
                <input type="date" id="dateFilter" class="filter-date" onchange="filterTable()">

                <select id="statusFilter" class="filter-select" onchange="filterTable()">
                    <option value="">All Statuses</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Completed">Completed</option>

                </select>
                <button type="button" class="btn-reset" onclick="resetFilters()">Reset</button>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3>All Reservations</h3>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="reservationsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer Name</th>
                                <th>Service</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($reservations as $row): ?>
                                <tr id="row-<?php echo pdrH($row['id']); ?>">
                                    <td><strong><?php echo pdrH($row['id']); ?></strong></td>
                                    <td><?php echo pdrH($row['customer']); ?></td>
                                    <td><?php echo pdrH($row['service']); ?></td>
                                    <td><?php echo pdrH($row['date'] . ' ' . $row['time']); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo pdrH(strtolower((string) $row['status'])); ?>" id="status-badge-<?php echo pdrH($row['id']); ?>">
                                            <?php echo pdrH($row['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo pdrH($row['payment']); ?></td>
                                    <td class="action-cell">
                                        <!-- Approve -->
                                        <button type="button" class="btn btn-approve" title="Approve"
                                            data-status-action data-id="<?php echo pdrH($row['id']); ?>" data-status="Confirmed">
                                            <i class="fa-solid fa-check"></i>
                                        </button>

                                        <!-- Reject -->
                                        <button type="button" class="btn btn-reject" title="Reject"
                                            data-status-action data-id="<?php echo pdrH($row['id']); ?>" data-status="Cancelled">
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
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

        // Search & Filter Logic
        function filterTable() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const dateFilter = document.getElementById('dateFilter').value;
    const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
    
    const rows = document.querySelectorAll('#reservationsTable tbody tr');

    rows.forEach(row => {
        // Kunin ang text mula sa mga angkop na column (halimbawa: Name/ID, Date, Status)
        const rowText = row.innerText.toLowerCase();
        
        // I-check kung nag-a-match ang search input (pangalan o ID)
        const matchesSearch = rowText.includes(searchInput);
        
        // I-check ang date filter (siguraduhing meron kang data attribute o saktong cell para sa date)
        // Halimbawa kung ang date ay nasa partikular na cell ng row:
        const rowDate = row.cells[3] ? row.cells[3].innerText.trim() : ''; 
        const matchesDate = !dateFilter || rowDate.includes(dateFilter);

        // I-check ang status filter
        const rowStatus = row.cells[4] ? row.cells[4].innerText.toLowerCase() : '';
        const matchesStatus = !statusFilter || rowStatus.includes(statusFilter);

        // Pagsabayin silang lahat (lahat ay dapat mag-true)
        if (matchesSearch && matchesDate && matchesStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Function para sa pag-reset ng mga filters
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('dateFilter').value = '';
    document.getElementById('statusFilter').value = '';
    filterTable(); // I-refresh ang table para lumabas ulit ang lahat
}

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
        function viewDetails(id, customer, phone, email, service, date, time, guests, amount, notes) {
            const content = document.getElementById('viewModalContent');
            if (!content) return;

            const fields = [
                ['Reservation ID', id],
                ['Customer Name', customer],
                ['Phone', phone],
                ['Email', email],
                ['Service', service],
                ['Date & Time', date + ' at ' + time],
                ['Guests', guests],
                ['Total Amount', amount],
                ['Notes', notes],
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
           
            const validStatuses = ['Pending', 'Confirmed', 'Cancelled', 'Completed'];
            if (!validStatuses.includes(newStatus)) {
                alert('Invalid status: ' + newStatus);
                return false;
            }

            if (!confirm(`Are you sure you want to change status to ${newStatus}?`)) {
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

    </script>
<script src="responsive.js"></script>
</body>
</html>