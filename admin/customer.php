<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Management - Pio De Roda Cafe</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
    <link rel="stylesheet" href="customer.css" />

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
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> calendar</a>
                </li>

                <li class="nav-item">
                    <a href="service.php"><i class="fa-solid fa-concierge-bell"></i> Services</a>
                </li>

                <li class="nav-item">
                    <a href="event.php"><i class="fa-solid fa-calendar-days"></i> Events</a>
                </li>

                <li class="nav-item active">
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
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <div class="top-navbar">
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Customers Management</span>
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
                    <p>Manage customer profiles, reservation history, payment status, and account remarks.</p>
                </div>
                
            </div>

            <!-- Customers Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Address</th>
                            <th>Account Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <!-- Rendered dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add New Customer Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <h3>Add New Customer</h3>
            <form id="addCustomerForm" onsubmit="handleAddCustomer(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" id="cust_name" required placeholder="e.g. Maria Santos">
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" id="cust_email" required placeholder="e.g. maria@email.com">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" id="cust_phone" required placeholder="e.g. 09123456789">
                    </div>
                    <div class="form-group">
                        <label>Account Status</label>
                        <select id="cust_status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label>Address</label>
                        <input type="text" id="cust_address" required placeholder="e.g. Indang, Cavite">
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-add-new">Save Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-Side JavaScript Logic -->
    <script>
        let customersData = [
            {
                id: 1,
                name: 'Jolo Cruzada',
                email: 'xxxxx@email.com',
                phone: '09000000000',
                address: 'Alfonso, Cavite',
                status: 'Active'
            },
            {
                id: 2,
                name: 'Khean Digno',
                email: 'xxxxx@email.com',
                phone: '09000000000',
                address: 'Alfonso, Cavite',
                status: 'Active'
            },
            {
                id: 3,
                name: 'Ruzelle Peji',
                email: 'xxxxx@email.com',
                phone: '09000000000',
                address: 'Mendez, Cavite',
                status: 'Active'
            },
            {
                id: 4,
                name: 'Puso Dimaranan',
                email: 'xxxxx@email.com',
                phone: '09000000000',
                address: 'Alfonso, Cavite',
                status: 'Active'
            }
        ];

        function renderCustomersTable() {
            const tbody = document.getElementById('customersTableBody');
            tbody.innerHTML = '';

            if (customersData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color: var(--text-muted); padding:30px;">No customers found.</td></tr>';
                return;
            }

            customersData.forEach((cust, index) => {
                const statusClass = cust.status === 'Active' ? 'status-active' : 'status-inactive';
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${cust.name}</strong></td>
                    <td>${cust.email}</td>
                    <td>${cust.phone}</td>
                    <td>${cust.address}</td>
                    <td><span class="status-badge ${statusClass}">${cust.status}</span></td>
                    <td>
                        <button class="btn-delete-sm" onclick="deleteCustomer(${index})"><i class="fa-solid fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function handleAddCustomer(e) {
            e.preventDefault();
            
            const newCustomer = {
                id: Date.now(),
                name: document.getElementById('cust_name').value,
                email: document.getElementById('cust_email').value,
                phone: document.getElementById('cust_phone').value,
                address: document.getElementById('cust_address').value,
                status: document.getElementById('cust_status').value
            };

            customersData.push(newCustomer);
            renderCustomersTable();
            closeModal();
            document.getElementById('addCustomerForm').reset();
        }

        function deleteCustomer(index) {
            if (confirm('Are you sure you want to delete this customer?')) {
                customersData.splice(index, 1);
                renderCustomersTable();
            }
        }

        function openModal() { document.getElementById('addModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('addModal').style.display = 'none'; }

        document.addEventListener('DOMContentLoaded', renderCustomersTable);
    </script>
</body>
</html>

