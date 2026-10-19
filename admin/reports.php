<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews & Reports - Pio De Roda Cafe</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="reports.css" />

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

                <li class="nav-item">
                    <a href="payment.php"><i class="fa-solid fa-money-bill-wave"></i> Payment</a>
                </li>
                
                <li class="nav-item active">
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
           <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Reviews & Reports</span>
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
                    
                    <p>Monitor client feedback, reservation trends, revenue analytics, and customer acquisition metrics.</p>
                </div>
            </div>

            <!-- Customer Report Summary Cards -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <h4>Total Customers</h4>
                    <div class="metric-value">142</div>
                    <div class="metric-sub">Active database records</div>
                </div>
                <div class="metric-card">
                    <h4>New Customers</h4>
                    <div class="metric-value">28</div>
                    <div class="metric-sub">Registered this month</div>
                </div>
                <div class="metric-card">
                    <h4>Returning Customers</h4>
                    <div class="metric-value">114</div>
                    <div class="metric-sub">With 2+ reservations</div>
                </div>
                <div class="metric-card">
                    <h4>Outstanding Balances</h4>
                    <div class="metric-value" style="color: var(--crimson);">₱12,500</div>
                    <div class="metric-sub">Pending final settlement</div>
                </div>
            </div>

            <!-- Reviews & Feedback Section -->
            <div class="section-container">
                <div class="section-header">
                    <h3>Reviews & Feedback</h3>
                    <span style="font-size: 13px; color: var(--text-muted);">Overall Rating: <strong style="color: var(--maroon);">4.8 / 5.0</strong></span>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Ratings</th>
                                <th>Related Reservation</th>
                                <th>Feedback / Reviews</th>
                                <th>Manage / Action</th>
                            </tr>
                        </thead>
                        <tbody id="reviewsTableBody">
                            <!-- Dynamic JS Render -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Reservation Report Section -->
            <div class="section-container">
                <div class="section-header">
                    <h3>Reservation Report</h3>
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterReservation('Daily', event)">Daily</button>
                        <button class="tab-btn" onclick="filterReservation('Weekly', event)">Weekly</button>
                        <button class="tab-btn" onclick="filterReservation('Monthly', event)">Monthly</button>
                        <button class="tab-btn" onclick="filterReservation('By Service', event)">By Service</button>
                        <button class="tab-btn" onclick="filterReservation('Cancelled', event)">Cancelled Reservations</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Period / Category</th>
                                <th>Total Bookings</th>
                                <th>Completed</th>
                                <th>Cancelled</th>
                                <th>Primary Service Assigned</th>
                            </tr>
                        </thead>
                        <tbody id="reservationReportBody">
                            <!-- Dynamic JS Render -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Revenue / Payment Report Section -->
            <div class="section-container">
                <div class="section-header">
                    <h3>Revenue / Payment Report</h3>
                    <div class="filter-tabs">
                        <button class="tab-btn active" onclick="filterRevenue('Daily', event)">Daily</button>
                        <button class="tab-btn" onclick="filterRevenue('Weekly', event)">Weekly</button>
                        <button class="tab-btn" onclick="filterRevenue('Monthly', event)">Monthly</button>
                        <button class="tab-btn" onclick="filterRevenue('By Service', event)">By Service</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Category / Duration</th>
                                <th>Total Payments</th>
                                <th>Outstanding Balances</th>
                                <th>Payment Status</th>
                            </tr>
                        </thead>
                        <tbody id="revenueReportBody">
                            <!-- Dynamic JS Render -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side JavaScript Logic -->
    <script>
        let reviewsData = [
            { id: 1, customer: 'Khean Digno', rating: 4, reservation: 'Dignos Family Dinner', comment: 'Exceptional service and wonderful setup! All our guests loved the catering.' },
            { id: 2, customer: 'Puso Dimaranan', rating: 4.5, reservation: 'Dimaranans Reunion', comment: 'Very professional staff and delicious food. Thank you Pio De Roda!' },
            { id: 3, customer: 'Jolo Cruzada', rating: 5, reservation: 'Cruzada Wedding', comment: 'Smooth booking process and breathtaking venue styling.' }
        ];

        function renderReviews() {
            const tbody = document.getElementById('reviewsTableBody');
            tbody.innerHTML = '';
            
            if(reviewsData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; color: var(--text-muted); padding:30px;">No reviews found.</td></tr>';
                return;
            }

            reviewsData.forEach((rev, index) => {
                let stars = '<i class="fa-solid fa-star"></i>'.repeat(Math.floor(rev.rating));
                if(rev.rating % 1 !== 0) stars += '<i class="fa-solid fa-star-half-stroke"></i>';
                
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${rev.customer}</strong></td>
                    <td><span class="star-rating">${stars}</span> (${rev.rating})</td>
                    <td>${rev.reservation}</td>
                    <td>"${rev.comment}"</td>
                    <td><button class="btn-delete-sm" onclick="deleteReview(${index})"><i class="fa-solid fa-trash"></i> Remove</button></td>
                `;
                tbody.appendChild(tr);
            });
        }

        function deleteReview(index) {
            if(confirm('Manage feedback: Are you sure you want to remove this inappropriate feedback?')) {
                reviewsData.splice(index, 1);
                renderReviews();
            }
        }

        const reservationReports = {
            'Daily': [
                { period: 'Today (Oct 18, 2026)', total: 3, completed: 2, cancelled: 0, service: 'Catering Full Course' },
                { period: 'Yesterday (Oct 17, 2026)', total: 5, completed: 5, cancelled: 0, service: 'Snack Station' }
            ],
            'Weekly': [
                { period: 'This Week (Oct 12 - Oct 18)', total: 18, completed: 16, cancelled: 1, service: 'Full Catering & Venue' },
                { period: 'Last Week (Oct 5 - Oct 11)', total: 14, completed: 13, cancelled: 1, service: 'Intimate Celebration' }
            ],
            'Monthly': [
                { period: 'October 2026', total: 45, completed: 40, cancelled: 2, service: 'Multiple Packages' },
                { period: 'September 2026', total: 38, completed: 35, cancelled: 3, service: 'Multiple Packages' }
            ],
            'By Service': [
                { period: 'Catering with Menu (Full Course)', total: 25, completed: 24, cancelled: 0, service: 'Main Package' },
                { period: 'Coffee Booth Package', total: 19, completed: 18, cancelled: 1, service: 'Add-on Service' },
                { period: 'Intimate Celebration Package', total: 12, completed: 11, cancelled: 1, service: 'Event Package' }
            ],
            'Cancelled': [
                { period: 'Cancelled Bookings Log', total: 5, completed: 0, cancelled: 5, service: 'Various (Rescheduled/Declined)' }
            ]
        };

        function filterReservation(type, event) {
            event.target.parentElement.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');

            const tbody = document.getElementById('reservationReportBody');
            tbody.innerHTML = '';
            
            reservationReports[type].forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${row.period}</strong></td>
                    <td>${row.total}</td>
                    <td><span style="color:var(--sage); font-weight:600;">${row.completed}</span></td>
                    <td><span style="color:var(--crimson); font-weight:600;">${row.cancelled}</span></td>
                    <td>${row.service}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        const revenueReports = {
            'Daily': [
                { duration: 'Today (Oct 18, 2026)', collected: '₱15,000', balance: '₱2,500', status: 'Partial Settlement' },
                { duration: 'Yesterday (Oct 17, 2026)', collected: '₱28,400', balance: '₱0', status: 'Fully Paid' }
            ],
            'Weekly': [
                { duration: 'This Week (Oct 12 - Oct 18)', collected: '₱98,000', balance: '₱8,500', status: 'Mostly Settled' },
                { duration: 'Last Week (Oct 5 - Oct 11)', collected: '₱76,500', balance: '₱4,000', status: 'Settled' }
            ],
            'Monthly': [
                { duration: 'October 2026', collected: '₱245,000', balance: '₱12,500', status: 'Active Collection' },
                { duration: 'September 2026', collected: '₱210,000', balance: '₱0', status: 'Closed' }
            ],
            'By Service': [
                { duration: 'Catering Full Course', collected: '₱180,000', balance: '₱10,000', status: 'High Revenue' },
                { duration: 'Coffee Booth Add-on', collected: '₱45,000', balance: '₱2,500', status: 'Stable' },
                { duration: 'Venue Rental', collected: '₱75,000', balance: '₱0', status: 'Fully Settled' }
            ]
        };

        function filterRevenue(type, event) {
            event.target.parentElement.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');

            const tbody = document.getElementById('revenueReportBody');
            tbody.innerHTML = '';
            
            revenueReports[type].forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${row.duration}</strong></td>
                    <td style="color:var(--sage); font-weight:600;">${row.collected}</td>
                    <td style="color:var(--crimson); font-weight:600;">${row.balance}</td>
                    <td>${row.status}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderReviews();
            filterReservation('Daily', { target: document.querySelector('.section-container:nth-of-type(2) .tab-btn') });
            filterRevenue('Daily', { target: document.querySelector('.section-container:nth-of-type(3) .tab-btn') });
        });
    </script>
</body>
</html>