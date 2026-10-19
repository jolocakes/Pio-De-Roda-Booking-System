<?php

$reservations = [
    [
        'id' => 'PDR-2026-0098',
        'customer' => 'Jolo Cruzada',
        'phone' => '+63 000 000 0000',
        'email' => 'jolo,cruzada@email.com',
        'service' => 'Cafe',
        'date' => '2026-09-20',
        'time' => '10:00 AM',
        'guests' => 2,
        'status' => 'Confirmed',
        'payment' => 'Cash',
        'amount' => '₱900.00',
    ],
    [
        'id' => 'PDR-2026-0097',
        'customer' => 'Khean Digno',
        'phone' => '+63 000 000 0000',
        'email' => 'khean.digno@email.com',
        'service' => 'Restaurant',
        'date' => '2026-09-21',
        'time' => '02:00 PM',
        'guests' => 3,
        'status' => 'Confirmed',
        'payment' => 'GCash',
        'amount' => '₱1,500.00',
    ],
    [
        'id' => 'PDR-2026-0096',
        'customer' => 'Ruzelle Peji',
        'phone' => '+63 000 000 0000',
        'email' => 'ruzelle.peji@email.com',
        'service' => 'Catering',
        'date' => '2026-09-22',
        'time' => '04:00 PM',
        'guests' => 1,
        'status' => 'Cancelled',
        'payment' => 'Cash',
        'amount' => '₱10,000.00',
    ],
    [
        'id' => 'PDR-2026-0095',
        'customer' => 'Puso Dimaranan',
        'phone' => '+63 000 000 0000',
        'email' => 'puso.dimaranan@email.com',
        'service' => 'Events Place',
        'date' => '2026-09-23',
        'time' => '06:00 PM',
        'guests' => 100,
        'status' => 'Pending',
        'payment' => 'Cash',
        'amount' => '₱20,000.00',
    ]
];
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
            <li class="nav-item" style="list-style: none;"><a href="#"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
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
                                    <td><strong><?php echo $row['id']; ?></strong></td>
                                    <td><?php echo $row['customer']; ?></td>
                                    <td><?php echo $row['service']; ?></td>
                                    <td><?php echo $row['date'] . ' ' .$row['time']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['status']); ?>" id="status-badge-<?php echo $row['id']; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['payment']; ?></td>
                                    <td class="action-cell">
                                        <!-- Approve -->
                                        <button class="btn btn-approve" title="Approve" onclick="updateStatus('<?php echo $row['id']; ?>', 'Confirmed')">
                                            <i class="fa-solid fa-check"></i>
                                        </button>

                                        <!-- Reject -->
                                        <button class="btn btn-reject" title="Reject" onclick="updateStatus('<?php echo $row['id']; ?>', 'Rejected')">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>

                                        <!-- Reschedule / Edit -->
                                        <button class="btn btn-edit" title="Edit / Reschedule" onclick="openEditModal('<?php echo $row['id']; ?>', '<?php echo$row['service']; ?>', '<?php echo $row['date']; ?>', '<?php echo$row['time']; ?>', '<?php echo $row['guests']; ?>', '<?php echo$row['status']; ?>')">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>

                                        <!-- Cancel -->
                                        <button class="btn btn-cancel" title="Cancel Booking" onclick="updateStatus('<?php echo $row['id']; ?>', 'Cancelled')">
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
        function updateStatus(id, newStatus) {
            if (confirm(`Are you sure you want to change status to ${newStatus}?`)) {
                const badge = document.getElementById(`status-badge-${id}`);
                badge.innerText = newStatus;
                badge.className = `badge badge-${newStatus.toLowerCase()}`;
            }
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
        function saveEdit() {
            const id = document.getElementById('editResId').value;
            const newStatus = document.getElementById('editStatus').value;
            
            updateStatus(id, newStatus);
            closeModal('editModal');
            alert('Reservation updated successfully!');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
    </script>
</body>
</html>