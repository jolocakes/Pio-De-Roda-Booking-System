<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Tracking - Pio De Roda Cafe</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
    <link rel="stylesheet" href="payment.css" />


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

                <li class="nav-item ">
                    <a href="customer.php"><i class="fa-solid fa-users"></i> Customers</a>
                </li>

                <li class="nav-item active">
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
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <div class="top-navbar">
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Payment Tracking</span>
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
                    <p>Monitor customer transaction records, deposits, remaining balances, and payment status.</p>
                </div>
                <button class="btn-add-new" onclick="openModal()"><i class="fa-solid fa-plus"></i> Record Payment</button>
            </div>

            <!-- Summary Metrics -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <h4>Total Collected</h4>
                    <div class="metric-value" id="m_collected">₱00.00</div>
                </div>
                <div class="metric-card">
                    <h4>Outstanding Balances</h4>
                    <div class="metric-value" id="m_outstanding" style="color: #d93025;">₱00.00</div>
                </div>
                <div class="metric-card">
                    <h4>Fully Paid Records</h4>
                    <div class="metric-value" id="m_paid_count">0</div>
                </div>
                <div class="metric-card">
                    <h4>Partial Settlements</h4>
                    <div class="metric-value" id="m_partial_count">0</div>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Reservation</th>
                            <th>Customer</th>
                            <th>Total Amount</th>
                            <th>Deposit</th>
                            <th>Remaining Balance</th>
                            <th>Payment Method</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="paymentsTableBody">
                        <!-- Rendered dynamically via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Payment Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <h3>Record Payment</h3>
            <form id="addPaymentForm" onsubmit="handleAddPayment(event)">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Reservation Name</label>
                        <input type="text" id="p_reservation" required placeholder="e.g. Santos Wedding Reception">
                    </div>
                    <div class="form-group">
                        <label>Customer Name</label>
                        <input type="text" id="p_customer" required placeholder="e.g. Maria Santos">
                    </div>
                    <div class="form-group">
                        <label>Total Amount (₱)</label>
                        <input type="number" id="p_total" required placeholder="00.00" oninput="calculateBalance()">
                    </div>
                    <div class="form-group">
                        <label>Deposit Paid (₱)</label>
                        <input type="number" id="p_deposit" required placeholder="00.00" oninput="calculateBalance()">
                    </div>
                    <div class="form-group">
                        <label>Remaining Balance (₱)</label>
                        <input type="number" id="p_balance" readonly placeholder="00.00" style="background:#f4efe8;">
                    </div>
                    <div class="form-group">
                        <label>Payment Method</label>
                        <select id="p_method" required>
                            <option value="GCash">GCash</option>
                            <option value="Cash">Cash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Date</label>
                        <input type="date" id="p_date" required>
                    </div>
                    <div class="form-group">
                        <label>Payment Status</label>
                        <select id="p_status" required>
                            <option value="Partial">Partial</option>
                            <option value="Fully Paid">Fully Paid</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-add-new">Save Transaction</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-Side JavaScript Logic -->
    <script>
        let paymentsData = [
            {
                id: 1,
                reservation: 'Cruzada Wedding Reception',
                customer: 'Jolo Cruzada',
                total_amount: 0,
                deposit: 0,
                remaining_balance: 0,
                payment_method: 'Cash',
                payment_date: '2026-10-18',
                payment_status: 'Fully Paid'
            },
            {
                id: 2,
                reservation: 'Ruzelle 18th Debut',
                customer: 'Ruzelle Peji',
                total_amount: 0,
                deposit: 0,
                remaining_balance: 0,
                payment_method: 'Cash',
                payment_date: '2026-12-6',
                payment_status: 'Fully Paid'
            },
            {
                id: 3,
                reservation: 'Dimaranans Reunion',
                customer: 'Puso Dimaranan',
                total_amount: 0,
                deposit: 0,
                remaining_balance: 0,
                payment_method: 'Bank Transfer',
                payment_date: '2026-10-25',
                payment_status: 'Fully Paid'
            },
            {
                id: 4,
                reservation: 'Digno Family Dinner',
                customer: 'Khean Digno',
                total_amount: 0,
                deposit: 0,
                remaining_balance: 0,
                payment_method: 'Bank Transfer',
                payment_date: '2026-11-05',
                payment_status: 'Fully Paid'
            }
        ];

        function formatCurrency(amount) {
            return '₱' + Number(amount).toLocaleString('en-US');
        }

        function calculateBalance() {
            const total = parseFloat(document.getElementById('p_total').value) || 0;
            const deposit = parseFloat(document.getElementById('p_deposit').value) || 0;
            const balance = Math.max(0, total - deposit);
            document.getElementById('p_balance').value = balance;

            // Auto status selection hint
            const statusSelect = document.getElementById('p_status');
            if(balance === 0 && total > 0) {
                statusSelect.value = 'Fully Paid';
            } else if(deposit > 0 && balance > 0) {
                statusSelect.value = 'Partial';
            } else if(deposit === 0) {
                statusSelect.value = 'Unpaid';
            }
        }

        function renderPaymentsTable() {
            const tbody = document.getElementById('paymentsTableBody');
            tbody.innerHTML = '';

            if (paymentsData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; color: var(--text-muted); padding:30px;">No payment records found.</td></tr>';
                return;
            }

            paymentsData.forEach((item, index) => {
                let badgeClass = 'status-unpaid';
                if(item.payment_status === 'Fully Paid') badgeClass = 'status-paid';
                if(item.payment_status === 'Partial') badgeClass = 'status-partial';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${item.reservation}</strong></td>
                    <td>${item.customer}</td>
                    <td>${formatCurrency(item.total_amount)}</td>
                    <td style="color:#137333; font-weight:600;">${formatCurrency(item.deposit)}</td>
                    <td style="color:#d93025; font-weight:600;">${formatCurrency(item.remaining_balance)}</td>
                    <td>${item.payment_method}</td>
                    <td>${item.payment_date}</td>
                    <td><span class="status-badge ${badgeClass}">${item.payment_status}</span></td>
                    <td>
                        <button class="btn-delete-sm" onclick="deletePayment(${index})"><i class="fa-solid fa-trash"></i></button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function handleAddPayment(e) {
            e.preventDefault();

            const total = parseFloat(document.getElementById('p_total').value);
            const deposit = parseFloat(document.getElementById('p_deposit').value);

            const newPayment = {
                id: Date.now(),
                reservation: document.getElementById('p_reservation').value,
                customer: document.getElementById('p_customer').value,
                total_amount: total,
                deposit: deposit,
                remaining_balance: Math.max(0, total - deposit),
                payment_method: document.getElementById('p_method').value,
                payment_date: document.getElementById('p_date').value,
                payment_status: document.getElementById('p_status').value
            };

            paymentsData.push(newPayment);
            renderPaymentsTable();
            closeModal();
            document.getElementById('addPaymentForm').reset();
        }

        function deletePayment(index) {
            if(confirm('Are you sure you want to delete this payment transaction?')) {
                paymentsData.splice(index, 1);
                renderPaymentsTable();
            }
        }

        function openModal() { document.getElementById('addModal').style.display = 'flex'; }
        function closeModal() { document.getElementById('addModal').style.display = 'none'; }

        document.addEventListener('DOMContentLoaded', renderPaymentsTable);
    </script>
</body>
</html>