<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';

// T-36: every admin page states the capability it needs, so a role that is not
// allowed cannot reach a page just by typing its URL.
pdrRequirePageCapability('view');

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

$admin_name = pdrAdminDisplayName();
$admin_role = pdrAdminDisplayRole();
$current_date = date('F j, Y');
$current_time = date('h:i:s A');
$today = date('Y-m-d');
$todayDt = new DateTimeImmutable('today');

// Period filter: recent 12 months + full year options. All dashboard
// metrics/charts/lists follow the selected period.
$periodRaw = (string) ($_GET['period'] ?? '');
$periodType = 'month';
$periodValue = $todayDt->format('Y-m');
if (preg_match('/^month:(\d{4})-(\d{2})$/', $periodRaw, $m)) {
    $y = (int) $m[1]; $mo = (int) $m[2];
    if ($y >= 2020 && $y <= 2100 && $mo >= 1 && $mo <= 12) {
        $periodType = 'month';
        $periodValue = sprintf('%04d-%02d', $y, $mo);
    }
} elseif (preg_match('/^year:(\d{4})$/', $periodRaw, $m)) {
    $y = (int) $m[1];
    if ($y >= 2020 && $y <= 2100) {
        $periodType = 'year';
        $periodValue = (string) $y;
    }
}

if ($periodType === 'year') {
    $rangeStart = new DateTimeImmutable($periodValue . '-01-01');
    $rangeEnd = new DateTimeImmutable($periodValue . '-12-31');
    $periodLabel = 'Year ' . $periodValue;
} else {
    $rangeStart = new DateTimeImmutable($periodValue . '-01');
    $rangeEnd = $rangeStart->modify('last day of this month');
    $periodLabel = $rangeStart->format('F Y');
}
$rangeStartStr = $rangeStart->format('Y-m-d');
$rangeEndStr = $rangeEnd->format('Y-m-d');

$periodOptions = [];
for ($i = 0; $i < 12; $i++) {
    $d = $todayDt->modify('first day of this month')->modify("-{$i} months");
    $val = 'month:' . $d->format('Y-m');
    $periodOptions[] = ['value' => $val, 'label' => $d->format('F Y')];
}
$yearOptions = [];
for ($y = (int) $todayDt->format('Y'); $y >= (int) $todayDt->format('Y') - 2; $y--) {
    $yearOptions[] = ['value' => 'year:' . $y, 'label' => 'Full Year ' . $y];
}

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

// T-42: dashboard alerts.
$stalePendingCount = 0;
$next7DaysCount = 0;
$paymentsForReviewCount = 0;

try {
    $statusStmt = $pdo->prepare(
        "SELECT status, COUNT(*) AS total
           FROM reservations
          WHERE event_date BETWEEN ? AND ?
          GROUP BY status"
    );
    $statusStmt->execute([$rangeStartStr, $rangeEndStr]);
    foreach ($statusStmt->fetchAll() as $row) {
        $status = (string) $row['status'];
        if (isset($statusCounts[$status])) {
            $statusCounts[$status] = (int) $row['total'];
        }
    }
    $totalReservations = array_sum($statusCounts);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE event_date = ? AND event_date BETWEEN ? AND ?");
    $stmt->execute([$today, $rangeStartStr, $rangeEndStr]);
    $todayReservations = (int) $stmt->fetchColumn();

    $upcomingFrom = max($today, $rangeStartStr);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM reservations
          WHERE event_date BETWEEN ? AND ? AND status IN ('Pending', 'Confirmed')"
    );
    $stmt->execute([$upcomingFrom, $rangeEndStr]);
    $upcomingReservations = ($upcomingFrom <= $rangeEndStr) ? (int) $stmt->fetchColumn() : 0;

    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT email) FROM reservations WHERE event_date BETWEEN ? AND ?");
    $stmt->execute([$rangeStartStr, $rangeEndStr]);
    $totalCustomers = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(estimated_total), 0) FROM reservations
          WHERE status <> 'Cancelled' AND event_date BETWEEN ? AND ?"
    );
    $stmt->execute([$rangeStartStr, $rangeEndStr]);
    $estimatedRevenue = (float) $stmt->fetchColumn();

    $topStmt = $pdo->prepare(
        "SELECT main_service, COUNT(*) AS total
           FROM reservations
          WHERE event_date BETWEEN ? AND ?
          GROUP BY main_service
          ORDER BY total DESC, main_service ASC
          LIMIT 1"
    );
    $topStmt->execute([$rangeStartStr, $rangeEndStr]);
    $topRow = $topStmt->fetch();
    if ($topRow) {
        $topService = (string) $topRow['main_service'];
        $topServiceCount = (int) $topRow['total'];
    }

    $stmt = $pdo->prepare(
        "SELECT reservation_id, customer_name, main_service, event_type, event_date, event_time, status
           FROM reservations
          WHERE event_date BETWEEN ? AND ? AND status IN ('Pending', 'Confirmed')
          ORDER BY event_date ASC, event_time ASC
          LIMIT 3"
    );
    $stmt->execute([$upcomingFrom, $rangeEndStr]);
    $upcomingEventsList = ($upcomingFrom <= $rangeEndStr) ? $stmt->fetchAll() : [];

    $stmt = $pdo->prepare(
        "SELECT reservation_id, customer_name, main_service, event_date, event_time, status, payment_method, estimated_total
           FROM reservations
          WHERE event_date BETWEEN ? AND ?
          ORDER BY submitted_at DESC, id DESC
          LIMIT 5"
    );
    $stmt->execute([$rangeStartStr, $rangeEndStr]);
    $recentReservations = $stmt->fetchAll();

    // T-42: actionable notifications. Each query is guarded so an older schema
    // (e.g. before the payments review status) never breaks the dashboard.
    try {
        $staleStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reservations
              WHERE status = 'Pending' AND submitted_at < ?"
        );
        $staleStmt->execute([date('Y-m-d H:i:s', time() - 86400)]);
        $stalePendingCount = (int) $staleStmt->fetchColumn();
    } catch (Throwable $e) {
        $stalePendingCount = 0;
    }

    try {
        $nextStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM reservations
              WHERE status IN ('Pending', 'Confirmed')
                AND event_date BETWEEN ? AND ?"
        );
        $nextStmt->execute([$today, date('Y-m-d', strtotime('+7 days'))]);
        $next7DaysCount = (int) $nextStmt->fetchColumn();
    } catch (Throwable $e) {
        $next7DaysCount = 0;
    }

    try {
        $reviewStmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'For Review'");
        $paymentsForReviewCount = (int) $reviewStmt->fetchColumn();
    } catch (Throwable $e) {
        $paymentsForReviewCount = 0;
    }

    $serviceStmt = $pdo->prepare(
        "SELECT main_service, COUNT(*) AS total
           FROM reservations
          WHERE event_date BETWEEN ? AND ?
          GROUP BY main_service
          ORDER BY total DESC, main_service ASC"
    );
    $serviceStmt->execute([$rangeStartStr, $rangeEndStr]);
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

// Overview chart follows the selected period.
$overviewChart = ['labels' => [], 'total' => [], 'confirmed' => [], 'pending' => [], 'cancelled' => []];

// Build one x-axis bucket per day (or per month for the yearly view) so every
// range produces a real line graph rather than a single aggregate point.
$buckets = [];
if ($periodType === 'year') {
    for ($month = 1; $month <= 12; $month++) {
        $key = sprintf('%04d-%02d', (int) $rangeStart->format('Y'), $month);
        $monthDate = new DateTimeImmutable($key . '-01');
        $buckets[$key] = [
            'label' => $monthDate->format('M'),
            'total' => 0,
            'confirmed' => 0,
            'pending' => 0,
            'cancelled' => 0,
        ];
    }
    $groupExpression = "DATE_FORMAT(event_date, '%Y-%m')";
} else {
    for ($day = $rangeStart; $day <= $rangeEnd; $day = $day->modify('+1 day')) {
        $key = $day->format('Y-m-d');
        $buckets[$key] = [
            'label' => $day->format('M j'),
            'total' => 0,
            'confirmed' => 0,
            'pending' => 0,
            'cancelled' => 0,
        ];
    }
    $groupExpression = 'event_date';
}

// One grouped query fills every bucket: no per-day round trips. Guarded so a
// brief database outage degrades to zero counts instead of a fatal 500.
try {
    $stmt = $pdo->prepare(
        "SELECT $groupExpression AS bucket, status, COUNT(*) AS total
           FROM reservations
          WHERE event_date BETWEEN ? AND ?
       GROUP BY bucket, status"
    );
    $stmt->execute([$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')]);

    foreach ($stmt->fetchAll() as $row) {
        $bucketKey = (string) $row['bucket'];
        if (!isset($buckets[$bucketKey])) {
            continue;
        }
        $count = (int) $row['total'];
        // "Total" includes every status, including Completed and Rejected.
        $buckets[$bucketKey]['total'] += $count;

        $statusKey = strtolower((string) $row['status']);
        if (in_array($statusKey, ['confirmed', 'pending', 'cancelled'], true)) {
            $buckets[$bucketKey][$statusKey] += $count;
        }
    }
} catch (Throwable $e) {
    // Buckets keep their zero defaults; the rest of the dashboard still loads.
    error_log('Dashboard overview query failed: ' . $e->getMessage());
}

$overviewChart = [
    'labels' => [],
    'total' => [],
    'confirmed' => [],
    'pending' => [],
    'cancelled' => [],
];

foreach ($buckets as $bucket) {
    $overviewChart['labels'][] = $bucket['label'];
    $overviewChart['total'][] = $bucket['total'];
    $overviewChart['confirmed'][] = $bucket['confirmed'];
    $overviewChart['pending'][] = $bucket['pending'];
    $overviewChart['cancelled'][] = $bucket['cancelled'];
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
    <link rel="icon" href="../favicon.png" type="image/png">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo pdrAsset('dashboard.css'); ?>" />
        <link rel="stylesheet" href="<?php echo pdrAsset('admin-base.css'); ?>">
    <link rel="stylesheet" href="<?php echo pdrAsset('responsive.css'); ?>" />

</head>
<body>

    <!-- Left Navigation Bar -->
    <!-- Shared sidebar: admin/_sidebar.php (T-60) -->
    <?php $activeNav = 'dashboard'; require __DIR__ . '/_sidebar.php'; ?>

    <main class="main-wrapper">
        <header class="top-navbar">
            <div class="header-title-section">
                <h1>Dashboard</h1>
            </div>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p><?php echo $admin_name; ?></p>
                    <span><?php echo $admin_role; ?></span>
                </div>
            </div>
        </header>

        <div class="content-body">
            <?php if (isset($_GET['denied'])): ?>
                <div style="background:#fef2f2;border:1px solid #fecaca;border-left:4px solid #b91c1c;color:#991b1b;border-radius:8px;padding:12px 15px;margin-bottom:16px;font-size:14px;">
                    <i class="fa-solid fa-lock"></i>
                    Your role does not have access to that page.
                </div>
            <?php endif; ?>
            <div class="welcome-header">
                <div>
                    <h2>Welcome back, <?php echo $admin_name; ?>!</h2>
                    <p>Showing data for <strong><?php echo h($periodLabel); ?></strong>. All cards, charts, and lists below follow this period.</p>
                </div>
                    <div class="time-display">
                    <span><i class="fa-regular fa-calendar"></i> <span id="live-date"><?php echo $current_date; ?></span></span>
                     <span><i class="fa-regular fa-clock"></i> <span id="live-time"><?php echo $current_time; ?></span></span>
                </div>   
            </div>

            <?php
            // T-42: actionable alerts. Only shown when there is something to do.
            $alerts = [];
            if ($stalePendingCount > 0) {
                $alerts[] = [
                    'icon' => 'fa-hourglass-half',
                    'tone' => '#b45309',
                    'bg' => '#fef3c7',
                    'text' => $stalePendingCount . ' pending reservation' . ($stalePendingCount === 1 ? ' has' : 's have') . ' been waiting over 24 hours.',
                    'label' => 'Review pending',
                    'href' => 'reservation.php?status=Pending',
                ];
            }
            if ($next7DaysCount > 0) {
                $alerts[] = [
                    'icon' => 'fa-calendar-day',
                    'tone' => '#1d4ed8',
                    'bg' => '#dbeafe',
                    'text' => $next7DaysCount . ' event' . ($next7DaysCount === 1 ? '' : 's') . ' scheduled in the next 7 days.',
                    'label' => 'Open calendar',
                    'href' => 'calendar.php',
                ];
            }
            if ($paymentsForReviewCount > 0) {
                $alerts[] = [
                    'icon' => 'fa-receipt',
                    'tone' => '#9d174d',
                    'bg' => '#fce7f3',
                    'text' => $paymentsForReviewCount . ' payment' . ($paymentsForReviewCount === 1 ? '' : 's') . ' waiting for review.',
                    'label' => 'Review payments',
                    'href' => 'payment.php',
                ];
            }
            ?>
            <?php if ($alerts): ?>
                <div class="alert-stack" style="display:grid;gap:10px;margin:0 0 18px;">
                    <?php foreach ($alerts as $alert): ?>
                        <div style="display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e2e8f0;border-left:4px solid <?php echo h($alert['tone']); ?>;border-radius:8px;padding:12px 14px;">
                            <span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:<?php echo h($alert['bg']); ?>;color:<?php echo h($alert['tone']); ?>;flex:0 0 auto;">
                                <i class="fa-solid <?php echo h($alert['icon']); ?>"></i>
                            </span>
                            <span style="flex:1;font-size:14px;color:#2d3748;"><?php echo h($alert['text']); ?></span>
                            <a href="<?php echo h($alert['href']); ?>" style="font-size:13px;font-weight:600;color:<?php echo h($alert['tone']); ?>;text-decoration:none;white-space:nowrap;">
                                <?php echo h($alert['label']); ?> &rarr;
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

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
                            <select name="period" onchange="this.form.submit()" aria-label="Select dashboard period">
                                <optgroup label="By Month">
                                    <?php foreach ($periodOptions as $opt): ?>
                                        <option value="<?php echo h($opt['value']); ?>" <?php echo ($periodType === 'month' && 'month:' . $periodValue === $opt['value']) ? 'selected' : ''; ?>><?php echo h($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <optgroup label="By Year">
                                    <?php foreach ($yearOptions as $opt): ?>
                                        <option value="<?php echo h($opt['value']); ?>" <?php echo ($periodType === 'year' && 'year:' . $periodValue === $opt['value']) ? 'selected' : ''; ?>><?php echo h($opt['label']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </form>
                    </div>
                    <div class="chart-container-line" id="overviewChartWrap">
                        <canvas id="reservationsOverviewChart" height="150"></canvas>
                        <div class="chart-empty" id="overviewEmpty">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            <span>No reservations in this period.</span>
                        </div>
                    </div>
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
                        <a href="event.php">View All</a>
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
                    <a href="reservation.php">View All</a>
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

        // A period with no bookings would otherwise draw every line flat on the
        // axis, which looks like a broken chart. Show an explicit empty state.
        const overviewHasData = ['total', 'confirmed', 'pending', 'cancelled'].some(function (key) {
            return (overviewChartData[key] || []).some(function (value) {
                return Number(value) > 0;
            });
        });
        if (!overviewHasData) {
            document.getElementById('overviewChartWrap').classList.add('is-empty');
        }

        // Line Chart Config
        const ctxLine = document.getElementById('reservationsOverviewChart').getContext('2d');
        const lineSeries = [
            { label: 'Total', key: 'total', color: '#500a18', fill: true },
            { label: 'Confirmed', key: 'confirmed', color: '#16a34a', fill: false },
            { label: 'Pending', key: 'pending', color: '#d97706', fill: false },
            { label: 'Cancelled', key: 'cancelled', color: '#dc2626', fill: false }
        ];
        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: overviewChartData.labels,
                datasets: lineSeries.map(function (series) {
                    return {
                        label: series.label,
                        data: overviewChartData[series.key],
                        borderColor: series.color,
                        backgroundColor: series.color + '1a',
                        fill: series.fill,
                        tension: 0.3,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: series.color,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 1
                    };
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                resizeDelay: 80,
                animation: { duration: 500 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 8, usePointStyle: true, font: { size: 10 } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: '#f0f0f0' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 12, maxRotation: 0, autoSkip: true }
                    }
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
<script src="<?php echo pdrAsset('date-format.js'); ?>"></script>
<script src="<?php echo pdrAsset('responsive.js'); ?>"></script>
<script src="<?php echo pdrAsset('a11y.js'); ?>"></script>
</body>
</html>