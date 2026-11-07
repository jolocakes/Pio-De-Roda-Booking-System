<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';

function h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatPeso($amount) {
    return '₱' . number_format((float) $amount, 2);
}

function formatEventTime($time) {
    $time = trim((string) $time);
    if ($time === '') return 'All day';

    $timestamp = strtotime('1970-01-01 ' . $time);
    return $timestamp ? date('g:i A', $timestamp) : $time;
}

function formatEventDateTime($date, $time) {
    $timestamp = strtotime((string) $date);
    $dateLabel = $timestamp ? date('M j, Y', $timestamp) : (string) $date;
    return trim($dateLabel . ' ' . formatEventTime($time));
}

$admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin User';
$admin_role = $_SESSION['admin_role'] ?? "Administrator";
$current_date = date('F j, Y');
$current_time = date('h:i:s A');
$today = date('Y-m-d');

// Safe defaults keep the page usable if a query fails.
$totalReservations = 0;
$statusCounts = ['Pending' => 0, 'Confirmed' => 0, 'Cancelled' => 0, 'Completed' => 0];
$todayReservations = 0;
$upcomingReservations = 0;
$totalCustomers = 0;
$estimatedRevenue = 0.0;
$topService = '';
$topServiceCount = 0;
$recentReservations = [];
$upcomingEventsList = [];
$weekLabels = [];
$weekSeries = [
    'total' => [],
    'confirmed' => [],
    'pending' => [],
    'cancelled' => []
];
$serviceLabels = [];
$serviceCounts = [];
$serviceColors = [];

try {
    $statusStmt = $pdo->query("SELECT status, COUNT(*) AS total FROM reservations GROUP BY status");
    foreach ($statusStmt->fetchAll() as $row) {
        $status = (string) $row['status'];
        if (isset($statusCounts[$status])) {
            $statusCounts[$status] = (int) $row['total'];
        }
    }
    $totalReservations = array_sum($statusCounts);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE event_date = ?");
    $stmt->execute([$today]);
    $todayReservations = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
          WHERE event_date >= ? AND status IN ('Pending', 'Confirmed')"
    );
    $stmt->execute([$today]);
    $upcomingReservations = (int) $stmt->fetchColumn();

    $totalCustomers = (int) $pdo->query("SELECT COUNT(DISTINCT email) FROM reservations")->fetchColumn();

    $estimatedRevenue = (float) $pdo->query(
        "SELECT COALESCE(SUM(estimated_total), 0) FROM reservations WHERE status <> 'Cancelled'"
    )->fetchColumn();

    $topStmt = $pdo->query(
        "SELECT main_service, COUNT(*) AS total
           FROM reservations
          GROUP BY main_service
          ORDER BY total DESC, main_service ASC
          LIMIT 1"
    );
    $topRow = $topStmt->fetch();
    if ($topRow) {
        $topService = (string) $topRow['main_service'];
        $topServiceCount = (int) $topRow['total'];
    }

    $stmt = $pdo->prepare(
        "SELECT reservation_id, customer_name, main_service, event_type, event_date, event_time, status
           FROM reservations
          WHERE event_date >= ? AND status IN ('Pending', 'Confirmed')
          ORDER BY event_date ASC, event_time ASC
          LIMIT 3"
    );
    $stmt->execute([$today]);
    $upcomingEventsList = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        "SELECT reservation_id, customer_name, main_service, event_date, event_time, status, payment_method, estimated_total
           FROM reservations
          ORDER BY submitted_at DESC, id DESC
          LIMIT 5"
    );
    $stmt->execute();
    $recentReservations = $stmt->fetchAll();

    // Reservations overview: current week, Monday through Sunday.
    $weekStart = new DateTimeImmutable('today');
    $dayOfWeek = (int) $weekStart->format('N');
    $weekStart = $weekStart->modify('-' . ($dayOfWeek - 1) . ' days');
    $weekEnd = $weekStart->modify('+6 days');

    $weekDates = [];
    $weekSeries = [
        'total' => array_fill(0, 7, 0),
        'confirmed' => array_fill(0, 7, 0),
        'pending' => array_fill(0, 7, 0),
        'cancelled' => array_fill(0, 7, 0)
    ];
    for ($i = 0; $i < 7; $i++) {
        $day = $weekStart->modify("+$i days");
        $weekDates[] = $day->format('Y-m-d');
        $weekLabels[] = $day->format('D');
    }

    $stmt = $pdo->prepare(
        "SELECT event_date, status, COUNT(*) AS total
           FROM reservations
          WHERE event_date BETWEEN ? AND ?
          GROUP BY event_date, status"
    );
    $stmt->execute([$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')]);
    foreach ($stmt->fetchAll() as $row) {
        $index = array_search($row['event_date'], $weekDates, true);
        if ($index === false) continue;

        $count = (int) $row['total'];
        $weekSeries['total'][$index] += $count;

        $statusKey = strtolower((string) $row['status']);
        if (isset($weekSeries[$statusKey])) {
            $weekSeries[$statusKey][$index] += $count;
        }
    }

    $serviceStmt = $pdo->query(
        "SELECT main_service, COUNT(*) AS total
           FROM reservations
          GROUP BY main_service
          ORDER BY total DESC, main_service ASC"
    );
    $palette = ['#500a18', '#f43f5e', '#3b82f6', '#06b6d4', '#d97706', '#16a34a', '#7e22ce', '#64748b'];
    $colorIndex = 0;
    foreach ($serviceStmt->fetchAll() as $row) {
        $serviceLabels[] = (string) $row['main_service'];
        $serviceCounts[] = (int) $row['total'];
        $serviceColors[] = $palette[$colorIndex % count($palette)];
        $colorIndex++;
    }

    if (count($serviceLabels) === 0) {
        $serviceLabels = ['No reservations'];
        $serviceCounts = [1];
        $serviceColors = ['#e5e7eb'];
    }
} catch (Throwable $e) {
    error_log('Dashboard data error: ' . $e->getMessage());
}

$metrics = [
    [
        'title' => 'Total Reservations',
        'value' => number_format($totalReservations),
        'icon' => 'fa-calendar-check',
        'bg' => '#fbe8eb',
        'color' => '#6b1224'
    ],
    [
        'title' => 'Pending Reservations',
        'value' => number_format($statusCounts['Pending']),
        'icon' => 'fa-clock',
        'bg' => '#fef3d6',
        'color' => '#d97706'
    ],
    [
        'title' => 'Confirmed Reservations',
        'value' => number_format($statusCounts['Confirmed']),
        'icon' => 'fa-check-circle',
        'bg' => '#dcfce7',
        'color' => '#16a34a'
    ],
    [
        'title' => 'Cancelled Reservations',
        'value' => number_format($statusCounts['Cancelled']),
        'icon' => 'fa-times-circle',
        'bg' => '#fee2e2',
        'color' => '#dc2626'
    ],
    [
        'title' => "Today's Reservations",
        'value' => number_format($todayReservations),
        'icon' => 'fa-calendar-day',
        'bg' => '#e0f2fe',
        'color' => '#0284c7'
    ],
    [
        'title' => 'Upcoming Reservations',
        'value' => number_format($upcomingReservations),
        'icon' => 'fa-calendar-alt',
        'bg' => '#f3e8ff',
        'color' => '#9333ea'
    ],
    [
        'title' => 'Total Customers',
        'value' => number_format($totalCustomers),
        'icon' => 'fa-users',
        'bg' => '#e0e7ff',
        'color' => '#4f46e5'
    ],
    [
        'title' => 'Estimated Revenue',
        'value' => formatPeso($estimatedRevenue),
        'icon' => 'fa-peso-sign',
        'bg' => '#fce7f3',
        'color' => '#db2777'
    ],
    [
        'title' => 'Most Reserved Service',
        'value' => $topService !== '' ? $topService : '—',
        'sub' => '(' . number_format($topServiceCount) . ' ' . ($topServiceCount === 1 ? 'reservation' : 'reservations') . ')',
        'icon' => 'fa-star',
        'bg' => '#f3e8ff',
        'color' => '#7e22ce'
    ],
    [
        'title' => 'Upcoming Events',
        'value' => number_format($upcomingReservations),
        'icon' => 'fa-hands-helping',
        'bg' => '#ffedd5',
        'color' => '#ea580c'
    ]
];

$chartView = $_GET['chartView'] ?? 'week';

// Compute overview chart data based on selected view period
$overviewChart = ['labels' => [], 'total' => [], 'confirmed' => [], 'pending' => [], 'cancelled' => []];

// Determine date range and SQL condition for the selected view
$dateStart = null;
$dateEnd = null;
$dateCondition = '';

switch ($chartView) {
    case 'week':
        // Current week: Monday through Sunday
        $w = new DateTimeImmutable('monday this week');
        $e = clone $w;
        $e->modify('+6 days');
        $dateStart = $w->format('Y-m-d');
        $dateEnd = $e->format('Y-m-d');
        $dateCondition = "event_date BETWEEN '$dateStart' AND '$dateEnd'";
        break;

    case 'month':
        // Current month: 1st to last day
        $dateStart = date('Y-m-01');
        $dateEnd = date('Y-m-t');
        $dateCondition = "event_date BETWEEN '$dateStart' AND '$dateEnd'";
        break;

    case 'last_week':
        // Previous week: Monday-Sunday before current week
        $w = new DateTimeImmutable('monday last week');
        $e = clone $w;
        $e->modify('+6 days');
        $dateStart = $w->format('Y-m-d');
        $dateEnd = $e->format('Y-m-d');
        $dateCondition = "event_date BETWEEN '$dateStart' AND '$dateEnd'";
        break;

    case 'last_month':
        // Previous month: 1st to last day
        $dateStart = date('Y-m-01', strtotime('first day of last month'));
        $dateEnd = date('Y-m-t', strtotime('first day of last month'));
        $dateCondition = "event_date BETWEEN '$dateStart' AND '$dateEnd'";
        break;

    case 'this_year':
        // Jan 1 to today
        $dateStart = date('Y-01-01');
        $dateEnd = date('Y-m-d');
        $dateCondition = "event_date BETWEEN '$dateStart' AND '$dateEnd'";
        break;
}

// Build label display based on view
$viewLabel = '';
switch ($chartView) {
    case 'week': $viewLabel = 'This Week'; break;
    case 'month': $viewLabel = 'This Month'; break;
    case 'last_week': $viewLabel = 'Last Week'; break;
    case 'last_month': $viewLabel = 'Last Month'; break;
    case 'this_year': $viewLabel = 'This Year'; break;
}

// Query reservations in the selected period, broken down by status
$seriesData = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0];

if ($dateCondition) {
    $stmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS total
           FROM reservations
          WHERE $dateCondition
       GROUP BY status"
    );
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $s = strtolower((string) $row['status']);
        if (isset($seriesData[$s])) {
            $seriesData[$s] = (int) $row['total'];
        }
    }
    $seriesData['total'] = array_sum($seriesData);
}

// Set chart labels and data based on view type
// For week view: show 7 individual days (Mon-Sun)
// For other views: show the aggregate total
if ($chartView === 'week') {
    // Build array of 7 day labels (Mon-Sun) with counts
    $weekLabels = [];
    $weekData = ['total' => [], 'confirmed' => [], 'pending' => [], 'cancelled' => []];
    for ($i = 0; $i < 7; $i++) {
        $d = $dateStart;
        // Advance $i days from $dateStart
        $dayDate = date('Y-m-d', strtotime($dateStart . ' +' . $i . ' days'));
        $weekLabels[] = date('M j', strtotime($dayDate));
        
        // Count reservations on this specific day
        $stmtDay = $pdo->prepare(
            "SELECT status, COUNT(*) AS total
               FROM reservations
              WHERE event_date = '$dayDate'
           GROUP BY status"
        );
        $stmtDay->execute();
        $dayCounts = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'cancelled' => 0];
        foreach ($stmtDay->fetchAll() as $row) {
            $s = strtolower((string) $row['status']);
            if (isset($dayCounts[$s])) {
                $dayCounts[$s] = (int) $row['total'];
            }
        }
        $weekData['total'][$i] = $dayCounts['total'];
        $weekData['confirmed'][$i] = $dayCounts['confirmed'];
        $weekData['pending'][$i] = $dayCounts['pending'];
        $weekData['cancelled'][$i] = $dayCounts['cancelled'];
    }

    $overviewChart = [
        'labels' => $weekLabels,
        'total' => array_values($weekData['total']),
        'confirmed' => array_values($weekData['confirmed']),
        'pending' => array_values($weekData['pending']),
        'cancelled' => array_values($weekData['cancelled'])
    ];
} else {
    $overviewChart = [
        'labels' => [$viewLabel],
        'total' => [$seriesData['total']],
        'confirmed' => [$seriesData['confirmed']],
        'pending' => [$seriesData['pending']],
        'cancelled' => [$seriesData['cancelled']]
    ];
}

$serviceChart = [
    'labels' => $serviceLabels,
    'data' => $serviceCounts,
    'colors' => $serviceColors
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
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="dashboard.css" />
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
                <li class="nav-item active">
                    <a href="dashboard.php"><i class="fa-solid fa-house"></i> Dashboard</a>
                </li>

                <li class="nav-item">
                    <a href="reservation.php"><i class="fa-solid fa-calendar-check"></i> Reservations</a>
                </li>
                
                <li class="nav-item">
                    <a href="calendar.php"><i class="fa-solid fa-calendar-days"></i> Calendar</a>
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
            <li class="nav-item" style="list-style: none;"><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Logout</a></li>
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
                    <p><?php echo h($admin_name); ?></p>
                    <span><?php echo h($admin_role); ?></span>
                </div>
                <i class="fa-solid fa-chevron-down" style="font-size: 10px; color: #718096; margin-left: 5px;"></i>
            </div>
        </header>

        <div class="content-body">
            <div class="welcome-header">
                <div>
                    <h2>Welcome back, <?php echo h($admin_name); ?>!</h2>
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
                            <h4><?php echo h($item['title']); ?></h4>
                            <h3><?php echo h($item['value']); ?></h3>
                            <?php if(isset($item['sub'])): ?>
                                <span><?php echo h($item['sub']); ?></span>
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
                        <form name="viewForm" method="get">
                            <select name="chartView" onchange="this.form.submit()">
                                <option value="week" <?= ($chartView ?? 'week') === 'week' ? 'selected' : '' ?>>This Week</option>
                                <option value="month" <?= ($chartView ?? 'week') === 'month' ? 'selected' : '' ?>>This Month</option>
                                <option value="last_week" <?= ($chartView ?? 'week') === 'last_week' ? 'selected' : '' ?>>Last Week</option>
                                <option value="last_month" <?= ($chartView ?? 'week') === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                                <option value="this_year" <?= ($chartView ?? 'week') === 'this_year' ? 'selected' : '' ?>>This Year</option>
                            </select>
                        </form>
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
                        <?php if (empty($upcomingEventsList)): ?>
                            <p class="empty-state">No upcoming events.</p>
                        <?php else: ?>
                            <?php foreach ($upcomingEventsList as $event): ?>
                                <?php
                                    $eventTimestamp = strtotime($event['event_date']);
                                    $eventDay = $eventTimestamp ? date('j', $eventTimestamp) : '—';
                                    $eventMonth = $eventTimestamp ? strtoupper(date('M', $eventTimestamp)) : '—';
                                    $eventTitle = $event['event_type'] ?: $event['main_service'];
                                    $eventStatusClass = strtolower((string) $event['status']);
                                ?>
                                <div class="event-item">
                                    <div class="event-date">
                                        <span class="month"><?php echo h($eventMonth); ?></span>
                                        <span class="day"><?php echo h($eventDay); ?></span>
                                    </div>
                                    <div class="event-details">
                                        <h4><?php echo h($eventTitle); ?></h4>
                                        <p><i class="fa-regular fa-clock"></i> <?php echo h(formatEventTime($event['event_time'])); ?></p>
                                        <p><i class="fa-solid fa-location-dot"></i> <?php echo h($event['main_service']); ?></p>
                                    </div>
                                    <span class="event-status badge badge-<?php echo h($eventStatusClass); ?>"><?php echo h($event['status']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
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
                            <?php if (empty($recentReservations)): ?>
                                <tr>
                                    <td colspan="7" class="empty-state">No reservations yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentReservations as $row): ?>
                                    <?php $rowStatusClass = strtolower((string) $row['status']); ?>
                                    <tr>
                                        <td><?php echo h($row['reservation_id']); ?></td>
                                        <td><?php echo h($row['customer_name']); ?></td>
                                        <td><?php echo h($row['main_service']); ?></td>
                                        <td><?php echo h(formatEventDateTime($row['event_date'], $row['event_time'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo h($rowStatusClass); ?>">
                                                <?php echo h($row['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo h($row['payment_method'] ?: '—'); ?></td>
                                        <td><?php echo h(formatPeso($row['estimated_total'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>

    <script>
        const overviewChartData = <?php echo json_encode($overviewChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        const serviceChartData = <?php echo json_encode($serviceChart, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        // Line Chart Config
        const ctxLine = document.getElementById('reservationsOverviewChart').getContext('2d');
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: overviewChartData.labels,
                datasets: [
                    {
                        label: 'Total',
                        data: overviewChartData.total,
                        borderColor: '#500a18',
                        backgroundColor: '#500a181a',
                        tension: 0.3,
                        fill: true
                    },
                    {
                        label: 'Confirmed',
                        data: overviewChartData.confirmed,
                        borderColor: '#16a34a',
                        tension: 0.3
                    },
                    {
                        label: 'Pending',
                        data: overviewChartData.pending,
                        borderColor: '#d97706',
                        tension: 0.3
                    },
                    {
                        label: 'Cancelled',
                        data: overviewChartData.cancelled,
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
                labels: serviceChartData.labels,
                datasets: [{
                    data: serviceChartData.data,
                    backgroundColor: serviceChartData.colors
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
<script src="responsive.js"></script>
</body>
</html>