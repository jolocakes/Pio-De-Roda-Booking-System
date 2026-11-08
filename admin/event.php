<?php 
require_once __DIR__ . '/auth_check.php'; 
require_once __DIR__ . '/../config/db.php';

$reservations = [];
try {
    $stmt = $pdo->query(
        "SELECT
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
         ORDER BY event_date ASC, event_time ASC"
    );
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $reservations = [];
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
    
    <link rel="stylesheet" href="event.css" />
    <link rel="stylesheet" href="responsive.css" />


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

            <!-- Events Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
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
            if (['pending', 'confirmed', 'cancelled', 'completed'].indexOf(value) !== -1) {
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
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; color: var(--text-muted); padding:30px;">No events available.</td></tr>';
                return;
            }

            eventsData.forEach(function (event, index) {
                const tr = document.createElement('tr');
                const guests = Number(event.guests) || 0;

                tr.innerHTML = `
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

        document.addEventListener('DOMContentLoaded', renderEventsTable);
    </script>
<script src="responsive.js"></script>
</body>
</html>
