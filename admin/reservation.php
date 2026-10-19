<?php
require_once __DIR__ . '/../includes/admin_auth.php';
$reservations = [];
try { $reservations = db()->query('SELECT reservation_code AS id,customer_name AS customer,contact_number AS phone,email,main_service AS service,event_date AS date,event_time AS time,guests,status,estimated_total AS amount FROM reservations ORDER BY created_at DESC')->fetchAll(); }
catch (Throwable $e) { http_response_code(503); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Management - Pio De Roda</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="reservation.css" />

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
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> calendar</a>
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
            <li class="nav-item" style="list-style: none;"><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8')?>"><button type="submit" style="background:none;border:0;color:inherit;cursor:pointer"><i class="fa-solid fa-right-from-bracket"></i> Logout</button></form></li>
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
            <!-- Search & Filter Controls -->
            <div class="filter-bar">
                <input type="text" id="searchInput" class="search-input" placeholder="Search by customer name or reservation ID..." onkeyup="filterTable()">
                <select id="statusFilter" class="filter-select" onchange="filterTable()">
                    <option value="">All Statuses</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Rejected">Rejected</option>
                </select>
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
                            <?php foreach($reservations as$row): ?>
                                <tr id="row-<?php echo $row['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['customer'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['service'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo htmlspecialchars($row['date'] . ' ' .$row['time'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower(htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8')); ?>" id="status-badge-<?php echo htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($row['status'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td>—</td>
                                    <td class="action-cell">
                                        <!-- Approve -->
                                        <button class="btn btn-approve" title="Approve" onclick="updateStatus(<?php echo json_encode($row['id']); ?>, 'Confirmed')">
                                            <i class="fa-solid fa-check"></i>
                                        </button>

                                        <!-- Reject -->
                                        <button class="btn btn-reject" title="Reject" onclick="updateStatus(<?php echo json_encode($row['id']); ?>, 'Rejected')">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>

                                        <!-- Reschedule / Edit -->
                                        <button class="btn btn-edit" title="Edit / Reschedule" onclick="openEditModal(<?php echo json_encode($row['id'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($row['service'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($row['date']); ?>, <?php echo json_encode(substr((string)$row['time'], 0, 5)); ?>, <?php echo (int)$row['guests']; ?>, <?php echo json_encode($row['status']); ?>)">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>

                                        <!-- Cancel -->
                                        <button class="btn btn-cancel" title="Cancel Booking" onclick="updateStatus(<?php echo json_encode($row['id']); ?>, 'Cancelled')">
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
                <label>Service</label>
                <input type="text" id="editService">
                
                <label>Date</label>
                <input type="date" id="editDate">
                
                <label>Time</label>
                <input type="text" id="editTime">

                <label>Guests</label>
                <input type="number" id="editGuests">

                <label>Update Status</label>
                <select id="editStatus">
                    <option value="Confirmed">Confirmed</option>
                    <option value="Pending">Pending</option>
                    <option value="Cancelled">Cancelled</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveEdit()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // Search & Filter Logic
        function filterTable() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value.toLowerCase();
            const rows = document.querySelectorAll('#reservationsTable tbody tr');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                const matchesSearch = text.includes(search);
                const matchesStatus = status === '' || text.includes(status);

                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }

        // View Reservation Details Modal
        function viewDetails(id, customer, phone, email, service, date, time, guests, amount, notes) {
            const content = `
                <p><strong>Reservation ID:</strong> ${id}</p>
                <p><strong>Customer Name:</strong> ${customer}</p>
                <p><strong>Phone:</strong> ${phone}</p>
                <p><strong>Email:</strong> ${email}</p>
                <p><strong>Service:</strong> ${service}</p>
                <p><strong>Date & Time:</strong> ${date} at ${time}</p>
                <p><strong>Guests:</strong> ${guests}</p>
                <p><strong>Total Amount:</strong> ${amount}</p>
                <p><strong>Notes:</strong> ${notes}</p>
            `;
            document.getElementById('viewModalContent').innerHTML = content;
            document.getElementById('viewModal').style.display = 'flex';
        }

        // Update Reservation Status (Approve, Reject, Cancel)
        async function updateStatus(id, newStatus) {
            if (!confirm(`Are you sure you want to change status to ${newStatus}?`)) return;
            try {
                const res = await fetch('api.php?action=status', {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':<?=json_encode($_SESSION['csrf'])?>},body:JSON.stringify({reservationId:id,status:newStatus})});
                const data = await res.json(); if (!res.ok || !data.ok) throw new Error();
                const badge = document.getElementById(`status-badge-${id}`); badge.innerText = data.status; badge.className = `badge badge-${data.status.toLowerCase()}`;
            } catch(e) { alert('Status update failed. Refresh the page and try again.'); }
        }

        // Edit / Reschedule Modal Open
        function openEditModal(id, service, date, time, guests, status) {
            document.getElementById('editResId').value = id;
            document.getElementById('editService').value = service;
            document.getElementById('editDate').value = date;
            document.getElementById('editTime').value = time;
            document.getElementById('editGuests').value = guests;
            document.getElementById('editStatus').value = status;
            document.getElementById('editModal').style.display = 'flex';
        }

        // Save Edit / Reschedule
        async function saveEdit() {
            const id = document.getElementById('editResId').value;
            const payload = {reservationId:id,eventDate:document.getElementById('editDate').value,eventTime:document.getElementById('editTime').value,guests:document.getElementById('editGuests').value,status:document.getElementById('editStatus').value};
            try {
                const res=await fetch('api.php?action=edit',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':<?=json_encode($_SESSION['csrf'])?>},body:JSON.stringify(payload)});
                const data=await res.json(); if(!res.ok||!data.ok) throw new Error(data.reason||'NETWORK');
                location.reload();
            } catch(e) { alert(e.message==='DATE_TAKEN'?'That date is no longer available. Choose another date.':'The reservation could not be updated. Check the date, time, and guest count.'); }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
</body>
</html>
