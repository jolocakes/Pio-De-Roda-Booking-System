<?php
require_once __DIR__ . '/auth_check.php';

$admin_name = "Admin User";
$admin_role = "Administrator";
$current_date = "September 19, 2026";
$current_time = "07:51:25 PM";


$metrics = [
    [
        'title' => 'Total Reservations', 
        'value' => '0', 
        'icon' => 'fa-calendar-check', 
        'bg' => '#fbe8eb', 
        'color' => '#6b1224'
        
    ],
    [
        'title' => 'Pending Reservations', 
        'value' => '0', 'icon' => 'fa-clock', 
        'bg' => '#fef3d6', 
        'color' => '#d97706'
        
    ],
    [
        'title' => 'Confirmed Reservations', 
        'value' => '0', 'icon' => 
        'fa-check-circle', 
        'bg' => '#dcfce7', 
        'color' => '#16a34a'
        
    ],
    [
        'title' => 'Cancelled Reservations', 
        'value' => '0', 
        'icon' => 'fa-times-circle', 
        'bg' => '#fee2e2', 
        'color' => '#dc2626'
        
    ],
    [
        'title' => "Today's Reservations", 
        'value' => '0', 
        'icon' => 'fa-calendar-day', 
        'bg' => '#e0f2fe', 
        'color' => '#0284c7'
        
    ],
    [
        'title' => 'Upcoming Reservations', 
        'value' => '0', 
        'icon' => 'fa-calendar-alt', 
        'bg' => '#f3e8ff', 
        'color' => '#9333ea'
        
    ],
    [
        'title' => 'Total Customers', 
        'value' => '0', 
        'icon' => 'fa-users', 
        'bg' => '#e0e7ff', 
        'color' => '#4f46e5'
        
    ],
    [
        'title' => 'Estimated Revenue', 
        'value' => '₱00.00', 
        'icon' => 'fa-ruble-sign', 
        'bg' => '#fce7f3', 
        'color' => '#db2777'
        
    ],
    [
        'title' => 'Most Reserved Service', 
        'value' => 'Events Place', 
        'sub' => '(10 reservations)', 
        'icon' => 'fa-star', 
        'bg' => '#f3e8ff', 
        'color' => '#7e22ce'
        
    ],
    [
        'title' => 'Upcoming Events', 
        'value' => '0', 
        'icon' => 'fa-hands-helping', 
        'bg' => '#ffedd5', 
        'color' => '#ea580c'
        
    ]
];

$events = [
    ['day' => '23', 'month' => 'SEP', 'title' => 'Debut', 'time' => '6:00 PM - 11:00 PM', 'location' => 'Events Place'],
    ['day' => '24', 'month' => 'SEP', 'title' => 'Reunion', 'time' => '10:00 AM - 5:00 PM', 'location' => 'Events Place'],
    ['day' => '35', 'month' => 'SEP', 'title' => 'Family Dinner', 'time' => '7:00 PM - 10:00 PM', 'location' => 'Restaurant dining']
];

$recent_reservations = [
    [
        'id' => 'RES-2026-0098', 
        'name' => 'Jolo Cruzada', 
        'service' => 'Cafe', 
        'datetime' => 'Sep 20, 2026 10:00 AM', 
        'status' => 'Confirmed', 
        'method' => 'Cash', 
        'amount' => '₱900.00'
        
    ],
    [
        'id' => 'RES-2026-0097', 
        'name' => 'Khean Digno', 
        'service' => 'Restaurant', 
        'datetime' => 'Sep 21, 2026 2:00 PM', 
        'status' => 'Confirmed', 
        'method' => 'GCash', 
        'amount' => '₱1,500.00'
        
    ],
    [
        'id' => 'RES-2026-0096', 
        'name' => 'Ruzelle Peji', 
        'service' => 'Catering', 
        'datetime' => 'Sep 22, 2026 4:00 PM', 
        'status' => 'Cancelled', 
        'method' => 'Cash', 
        'amount' => '₱10,000.00'
        
    ],
    [
        'id' => 'RES-2026-0095', 
        'name' => 'Puso Dimaranan', 
        'service' => 'Events Place', 
        'datetime' => 'Sep 23, 2026 6:00 PM', 
        'status' => 'Pending', 
        'method' => 'Cash', 
        'amount' => '₱20,000.00'
        
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pio De Roda Reservation System</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="dashboard.css" />

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
                <li class="nav-item active">
                    <a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
                </li>

                <li class="nav-item">
                    <a href="reservation.php"><i class="fa-solid fa-calendar-check"></i> Reservations</a>
                </li>
                
                <li class="nav-item">
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> calendar</a>
                </li>

                <li class="nav-item">
                    <a href="service.php"><i class="fa-solid fa-concierge-bell"></i> Services</a>
                </li>
                <li class="nav-item">
                    <a href="event.php"><i class="fa-solid fa-calendar-check"></i> Events</a>
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

    <!-- Main Content Area -->
    <main class="main-wrapper">
        <header class="top-navbar">
            <div class="header-title-section">
                <i class="fa-solid fa-bars" style="color: #718096; cursor: pointer;"></i>
                <h1>Dashboard</h1>
            </div>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p><?php echo $admin_name; ?></p>
                    <span><?php echo $admin_role; ?></span>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: #718096; margin-left: 5px;"></i>
            </div>
        </header>

        <div class="content-body">
            <div class="welcome-header">
                <div>
                    <h2>Welcome back, <?php echo $admin_name; ?>!</h2>
                    <p>Here's what's happening with your reservation system today.</p>
                </div>
                    <div class="time-display">
                    <span><i class="fa-regular fa-calendar"></i> <span id="live-date"><?php echo $current_date; ?></span></span>
                     <span><i class="fa-regular fa-clock"></i> <span id="live-time"><?php echo $current_time; ?></span></span>
                </div>   
            </div>

            <div class="metrics-grid">
                <?php foreach($metrics as $item): ?>
                    <div class="metric-card">
                        <div class="metric-icon" style="background-color: <?php echo $item['bg']; ?>; color: <?php echo $item['color']; ?>;">
                            <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                        </div>
                        <div class="metric-info">
                            <h4><?php echo $item['title']; ?></h4>
                            <h3><?php echo $item['value']; ?></h3>
                            <?php if(isset($item['sub'])): ?>
                                <span><?php echo $item['sub']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="dashboard-grid">
                <!-- Line Chart: Overview -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Reservations Overview</h3>
                        <select><option>This Week</option></select>
                    </div>
                    <canvas id="reservationsOverviewChart" height="150"></canvas>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Reservations by Service</h3>
                    </div>
                    <div class="chart-container-donut">
                        <canvas id="serviceDonutChart"></canvas>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <h3>Upcoming Events</h3>
                        <a href="#">View All</a>
                    </div>
                    <div class="events-list">
                        <?php foreach($events as $event): ?>
                            <div class="event-item">
                                <div class="event-date">
                                    <span class="month"><?php echo $event['month']; ?></span>
                                    <span class="day"><?php echo $event['day']; ?></span>
                                </div>
                                <div class="event-details">
                                    <h4><?php echo $event['title']; ?></h4>
                                    <p><i class="fa-regular fa-clock"></i> <?php echo $event['time']; ?></p>
                                    <p><i class="fa-solid fa-location-dot"></i> <?php echo $event['location']; ?></p>
                                </div>
                                <span class="event-status">Upcoming</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3>Recent Reservations</h3>
                    <a href="#">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Reservation ID</th>
                                <th>Customer Name</th>
                                <th>Service</th>
                                <th>Date & Time</th>
                                <th>Status</th>
                                <th>Payment Method</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recent_reservations as $row): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['service']; ?></td>
                                    <td><?php echo $row['datetime']; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo strtolower($row['status']); ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $row['method']; ?></td>
                                    <td><?php echo $row['amount']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        // Line Chart Config
        const ctxLine = document.getElementById('reservationsOverviewChart').getContext('2d');
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [
                    {
                        label: 'Total',
                        data: [23, 24, 32, 28, 38, 28, 29],
                        borderColor: '#500a18',
                        backgroundColor: '#500a181a',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Confirmed',
                        data: [12, 14, 20, 18, 24, 18, 18],
                        borderColor: '#16a34a',
                        tension: 0.3
                    },
                    {
                        label: 'Pending',
                        data: [8, 8, 8, 7, 10, 8, 8],
                        borderColor: '#d97706',
                        tension: 0.3
                    },
                    {
                        label: 'Cancelled',
                        data: [3, 4, 4, 3, 4, 3, 3],
                        borderColor: '#dc2626',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 8, font: { size: 10 } } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Chart Config
        const ctxDonut = document.getElementById('serviceDonutChart').getContext('2d');
        new Chart(ctxDonut, {
            type: 'doughnut',
            data: {
                labels: ['Cafe', 'Restaurant', 'Catering', 'Events place'],
                datasets: [{
                    data: [25, 25, 25, 25],
                    backgroundColor: ['#500a18', '#f43f5e', '#3b82f6', '#06b6d4']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 10, font: { size: 9 } } }
                },
                cutout: '70%'
            }
        });
        // Live Date & Time Updater
function updateLiveDateTime() {
    const now = new Date();

    const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
    const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };

    const dateEl = document.getElementById('live-date');
    const timeEl = document.getElementById('live-time');

    if (dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
    if (timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
}

// Run timer immediately and update every 1 second
updateLiveDateTime();
setInterval(updateLiveDateTime, 1000);

    </script>
</body>
</html>