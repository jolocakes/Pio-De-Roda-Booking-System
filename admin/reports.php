<?php
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../config/db.php';

/**
 * Escape helper for this page.
 */
function reportsH($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function reportsMoney($value): string
{
    return '₱' . number_format((float) $value, 2);
}

// T-44: export options
$export = (string) ($_GET['export'] ?? '');
$exportCsv = $export === 'csv';
$exportPdf = $export === 'pdf';

$canManageReviews = pdrCan('manage_reviews');
$canExport = pdrCan('export_reports');

// ---------------------------------------------------------------------------
// Data. Every number on this page now comes from the database. Each query is
// wrapped so a missing table (fresh clone, migration not yet run) degrades to
// an empty report instead of a fatal error.
// ---------------------------------------------------------------------------
$reviews = [];
$averageRating = null;
$metrics = [
    'totalCustomers'     => 0,
    'newCustomers'       => 0,
    'returningCustomers' => 0,
    'outstanding'        => 0.0,
];
$reservationReports = [
    'Daily'        => [],
    'Weekly'       => [],
    'Monthly'      => [],
    'By Service'   => [],
    'Cancelled'    => [],
];
$revenueReports = [
    'Daily'      => [],
    'Weekly'     => [],
    'Monthly'    => [],
    'By Service' => [],
];

try {
    // ---- Reviews ----------------------------------------------------------
    $reviews = $pdo->query(
        "SELECT id, reservation_id, customer_name, rating, feedback_text,
                status, admin_note, moderated_at, created_at
           FROM reviews
          WHERE COALESCE(archived, 0) = 0
          ORDER BY FIELD(status, 'Pending', 'Approved', 'Hidden'), created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $avg = $pdo->query(
        "SELECT AVG(rating) FROM reviews
          WHERE COALESCE(archived, 0) = 0 AND status = 'Approved'"
    )->fetchColumn();
    $averageRating = ($avg === null || $avg === false) ? null : (float) $avg;

    // ---- Customer metrics -------------------------------------------------
    $metrics['totalCustomers'] = (int) $pdo->query(
        "SELECT COUNT(DISTINCT email) FROM reservations"
    )->fetchColumn();

    $newStmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT email) FROM reservations WHERE submitted_at >= ?"
    );
    $newStmt->execute([date('Y-m-01 00:00:00')]);
    $metrics['newCustomers'] = (int) $newStmt->fetchColumn();

    $metrics['returningCustomers'] = (int) $pdo->query(
        "SELECT COUNT(*) FROM (
             SELECT email FROM reservations GROUP BY email HAVING COUNT(*) >= 2
         ) AS repeat_customers"
    )->fetchColumn();

    $metrics['outstanding'] = (float) $pdo->query(
        "SELECT COALESCE(SUM(GREATEST(
                    COALESCE(estimated_total, 0) - COALESCE(amount_paid, 0), 0
                )), 0)
           FROM reservations
          WHERE status NOT IN ('Cancelled', 'Rejected')"
    )->fetchColumn();

    // ---- Reservation report ----------------------------------------------
    // Groups bookings by the period they were SUBMITTED (not the event date),
    // which is what "daily / weekly / monthly bookings" normally means.
    $reservationGroup = static function (string $groupExpr, string $labelExpr, string $where, int $limit) use ($pdo): array {
        $sql = "SELECT $labelExpr AS period,
                       COUNT(*) AS total,
                       SUM(status = 'Completed') AS completed,
                       SUM(status IN ('Cancelled', 'Rejected')) AS cancelled,
                       GROUP_CONCAT(DISTINCT COALESCE(NULLIF(main_service, ''), 'Unassigned')
                                    ORDER BY main_service SEPARATOR ', ') AS service
                  FROM reservations
                 WHERE $where
                 GROUP BY $groupExpr
                 ORDER BY MIN(submitted_at) DESC
                 LIMIT $limit";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    };

    $reservationReports['Daily'] = $reservationGroup(
        "DATE(submitted_at)",
        "DATE_FORMAT(submitted_at, '%b %d, %Y')",
        "submitted_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
        14
    );
    $reservationReports['Weekly'] = $reservationGroup(
        "YEARWEEK(submitted_at, 1)",
        "CONCAT('Week ', WEEK(submitted_at, 1), ', ', YEAR(submitted_at))",
        "submitted_at >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)",
        8
    );
    $reservationReports['Monthly'] = $reservationGroup(
        "DATE_FORMAT(submitted_at, '%Y-%m')",
        "DATE_FORMAT(submitted_at, '%M %Y')",
        "submitted_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)",
        12
    );
    $reservationReports['By Service'] = $reservationGroup(
        "COALESCE(NULLIF(main_service, ''), 'Unassigned')",
        "COALESCE(NULLIF(main_service, ''), 'Unassigned')",
        "1 = 1",
        25
    );
    $reservationReports['Cancelled'] = $reservationGroup(
        "DATE(submitted_at)",
        "DATE_FORMAT(submitted_at, '%b %d, %Y')",
        "status IN ('Cancelled', 'Rejected')",
        30
    );

    // ---- Revenue report ---------------------------------------------------
    // "Total payments" is money actually received (amount_paid); the balance is
    // what is still owed. Cancelled/rejected bookings are excluded.
    $revenueGroup = static function (string $groupExpr, string $labelExpr, int $limit) use ($pdo): array {
        $sql = "SELECT $labelExpr AS period,
                       COALESCE(SUM(amount_paid), 0) AS collected,
                       COALESCE(SUM(GREATEST(
                           COALESCE(estimated_total, 0) - COALESCE(amount_paid, 0), 0
                       )), 0) AS balance,
                       COUNT(*) AS bookings
                  FROM reservations
                 WHERE status NOT IN ('Cancelled', 'Rejected')
                 GROUP BY $groupExpr
                 ORDER BY MIN(submitted_at) DESC
                 LIMIT $limit";
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $balance = (float) $row['balance'];
            $collected = (float) $row['collected'];
            $row['status'] = $balance <= 0.009
                ? 'Settled'
                : ($collected > 0 ? 'Partial' : 'Unpaid');
        }
        unset($row);
        return $rows;
    };

    $revenueReports['Daily'] = $revenueGroup(
        "DATE(submitted_at)",
        "DATE_FORMAT(submitted_at, '%b %d, %Y')",
        14
    );
    $revenueReports['Weekly'] = $revenueGroup(
        "YEARWEEK(submitted_at, 1)",
        "CONCAT('Week ', WEEK(submitted_at, 1), ', ', YEAR(submitted_at))",
        8
    );
    $revenueReports['Monthly'] = $revenueGroup(
        "DATE_FORMAT(submitted_at, '%Y-%m')",
        "DATE_FORMAT(submitted_at, '%M %Y')",
        12
    );
    $revenueReports['By Service'] = $revenueGroup(
        "COALESCE(NULLIF(main_service, ''), 'Unassigned')",
        "COALESCE(NULLIF(main_service, ''), 'Unassigned')",
        25
    );
} catch (Throwable $e) {
    // Keep the page usable; report the failure in the server log only.
    error_log('Reports data error: ' . $e->getMessage());
}

// ---------------------------------------------------------------------------
// T-44: CSV export — full report (summary, reservations, revenue, reviews).
// ---------------------------------------------------------------------------
if ($exportCsv) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pdr-report-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: max-age=0, no-store');

    $output = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens the file with the correct encoding.
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Pio De Roda — Reports Export', date('Y-m-d H:i:s')]);
    fputcsv($output, []);

    fputcsv($output, ['Summary']);
    fputcsv($output, ['Metric', 'Value']);
    fputcsv($output, ['Total Customers', $metrics['totalCustomers']]);
    fputcsv($output, ['New Customers (this month)', $metrics['newCustomers']]);
    fputcsv($output, ['Returning Customers (2+ bookings)', $metrics['returningCustomers']]);
    fputcsv($output, ['Outstanding Balances', number_format($metrics['outstanding'], 2, '.', '')]);
    fputcsv($output, ['Average Rating', $averageRating === null ? '' : number_format($averageRating, 2, '.', '')]);
    fputcsv($output, []);

    fputcsv($output, ['Reservation Report']);
    fputcsv($output, ['Category', 'Period', 'Total Bookings', 'Completed', 'Cancelled', 'Primary Service']);
    foreach ($reservationReports as $category => $rows) {
        foreach ($rows as $row) {
            fputcsv($output, [
                $category,
                $row['period'],
                $row['total'],
                $row['completed'],
                $row['cancelled'],
                $row['service'],
            ]);
        }
    }
    fputcsv($output, []);

    fputcsv($output, ['Revenue Report']);
    fputcsv($output, ['Category', 'Period', 'Total Payments', 'Outstanding Balance', 'Status']);
    foreach ($revenueReports as $category => $rows) {
        foreach ($rows as $row) {
            fputcsv($output, [
                $category,
                $row['period'],
                number_format((float) $row['collected'], 2, '.', ''),
                number_format((float) $row['balance'], 2, '.', ''),
                $row['status'],
            ]);
        }
    }
    fputcsv($output, []);

    fputcsv($output, ['Reviews']);
    fputcsv($output, ['ID', 'Reservation ID', 'Customer Name', 'Rating', 'Feedback', 'Status', 'Created At']);
    foreach ($reviews as $row) {
        fputcsv($output, [
            $row['id'],
            $row['reservation_id'],
            $row['customer_name'],
            $row['rating'],
            $row['feedback_text'],
            $row['status'],
            $row['created_at'],
        ]);
    }
    fclose($output);
    exit();
}

// T-44: PDF export is a print-friendly render of this page; the browser's
// "Save as PDF" produces the file, so no server-side PDF library is required.
$printMode = $exportPdf;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews & Reports - Pio De Roda Cafe</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,600;0,9..144,700;1,9..144,600&family=Work+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="reports.css" />
    <link rel="stylesheet" href="responsive.css" />

    <style>
        @media print {
            .sidebar, .top-navbar, .filter-tabs, .export-actions, .no-print { display: none !important; }
            .main-wrapper { margin-left: 0 !important; }
            body { background: #fff !important; }
            .content-body { padding: 0 !important; }
            .section-container, .metric-card { box-shadow: none !important; break-inside: avoid; }
        }
    </style>
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
                    <a href="event.php"><i class="fa-solid fa-calendar-days"></i> Events</a>
                </li>

                <li class="nav-item">
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

                <?php if (pdrCan('manage_staff')): ?>
                <li class="nav-item">
                    <a href="staff.php"><i class="fa-solid fa-user-shield"></i> Staff</a>
                </li>
                <?php endif; ?>
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
            <span style="font-size: 18px; color: var(--text-dark); font-weight: 700;">Reviews &amp; Reports</span>
            <div class="user-profile">
                <div class="user-avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-info">
                    <p><?php echo reportsH((string) ($_SESSION['admin_name'] ?? 'Admin User')); ?></p>
                    <span><?php echo reportsH(pdrRoleLabel()); ?></span>
                </div>
            </div>
        </div>

        <div class="content-body">
            <div class="page-header-container">
                <div class="page-title-group">
                    <p>Monitor client feedback, reservation trends, revenue analytics, and customer acquisition metrics.</p>
                </div>
                <?php if (!$printMode): ?>
                <div class="export-actions">
                    <?php if ($canExport): ?>
                    <button type="button" class="btn btn-approve" onclick="window.location='reports.php?export=csv'">
                        <i class="fa-solid fa-download"></i> Export CSV
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-reject" onclick="window.open('reports.php?export=pdf','_blank')">
                        <i class="fa-solid fa-file-pdf"></i> Print PDF
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Customer Report Summary Cards -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <h4>Total Customers</h4>
                    <div class="metric-value"><?php echo (int) $metrics['totalCustomers']; ?></div>
                    <div class="metric-sub">Distinct customers in the database</div>
                </div>
                <div class="metric-card">
                    <h4>New Customers</h4>
                    <div class="metric-value"><?php echo (int) $metrics['newCustomers']; ?></div>
                    <div class="metric-sub">First booking this month</div>
                </div>
                <div class="metric-card">
                    <h4>Returning Customers</h4>
                    <div class="metric-value"><?php echo (int) $metrics['returningCustomers']; ?></div>
                    <div class="metric-sub">With 2+ reservations</div>
                </div>
                <div class="metric-card">
                    <h4>Outstanding Balances</h4>
                    <div class="metric-value" style="color: var(--crimson);"><?php echo reportsH(reportsMoney($metrics['outstanding'])); ?></div>
                    <div class="metric-sub">Unpaid on active bookings</div>
                </div>
            </div>

            <!-- Reviews & Feedback Section (T-33: real data + moderation) -->
            <div class="section-container">
                <div class="section-header">
                    <h3>Reviews &amp; Feedback</h3>
                    <span style="font-size: 13px; color: var(--text-muted);">
                        Overall Rating:
                        <strong style="color: var(--maroon);">
                            <?php echo $averageRating === null ? '—' : reportsH(number_format($averageRating, 1)); ?> / 5.0
                        </strong>
                        <span style="margin-left:8px;">(<?php echo count($reviews); ?> total)</span>
                    </span>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Ratings</th>
                                <th>Related Reservation</th>
                                <th>Feedback / Reviews</th>
                                <th>Status</th>
                                <th>Manage / Action</th>
                            </tr>
                        </thead>
                        <tbody id="reviewsTableBody">
                            <?php if (empty($reviews)): ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; color: var(--text-muted); padding:30px;">
                                        No reviews yet. Customers can submit one from the Booking Status page after their event is completed.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reviews as $rev): ?>
                                    <?php
                                    $rating = max(0, min(5, (int) $rev['rating']));
                                    $badge = $rev['status'] === 'Approved'
                                        ? 'background:#dcfce7;color:#166534;'
                                        : ($rev['status'] === 'Hidden'
                                            ? 'background:#fee2e2;color:#991b1b;'
                                            : 'background:#fef3c7;color:#92400e;');
                                    ?>
                                    <tr id="review-row-<?php echo (int) $rev['id']; ?>">
                                        <td><strong><?php echo reportsH($rev['customer_name']); ?></strong></td>
                                        <td>
                                            <span class="star-rating">
                                                <?php echo str_repeat('<i class="fa-solid fa-star"></i>', $rating); ?>
                                            </span>
                                            (<?php echo $rating; ?>)
                                        </td>
                                        <td><?php echo reportsH($rev['reservation_id'] ?? '—'); ?></td>
                                        <td style="max-width:420px;"><?php echo nl2br(reportsH($rev['feedback_text'])); ?></td>
                                        <td>
                                            <span class="review-status-badge" data-review-status
                                                style="display:inline-block;padding:3px 9px;border-radius:999px;font-size:12px;font-weight:600;<?php echo $badge; ?>">
                                                <?php echo reportsH($rev['status']); ?>
                                            </span>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <?php if ($canManageReviews): ?>
                                            <button type="button" class="btn-approve-sm" data-review-action="approve"
                                                data-review-id="<?php echo (int) $rev['id']; ?>"
                                                style="padding:5px 9px;border:0;border-radius:6px;background:#dcfce7;color:#166534;cursor:pointer;margin-right:4px;">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn-delete-sm" data-review-action="hide"
                                                data-review-id="<?php echo (int) $rev['id']; ?>"
                                                style="padding:5px 9px;border:0;border-radius:6px;background:#fee2e2;color:#991b1b;cursor:pointer;">
                                                <i class="fa-solid fa-eye-slash"></i> Hide
                                            </button>
                                            <?php else: ?>
                                                <span style="font-size:12px;color:#9ca3af;">Read-only</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
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
                            <!-- Rendered from live data below -->
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
                            <!-- Rendered from live data below -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side JavaScript Logic -->
    <script>
        const PDR_CSRF_TOKEN = <?php echo json_encode(
            pdrCsrfToken(),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>;

        const reservationReports = <?php echo json_encode(
            $reservationReports,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>;

        const revenueReports = <?php echo json_encode(
            $revenueReports,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ); ?>;

        function escapeHtml(value) {
            return String(value === null || value === undefined ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function peso(value) {
            const n = Number(value);
            if (!Number.isFinite(n)) return '₱0.00';
            return '₱' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // T-33: approve / hide a review, then reflect the change in place.
        document.querySelectorAll('[data-review-action]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.dataset.reviewId;
                const action = button.dataset.reviewAction;
                if (!id || !action) return;
                if (!confirm('Are you sure you want to ' + action + ' this review?')) return;

                button.disabled = true;
                try {
                    const res = await fetch('../api/admin/reviews.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': PDR_CSRF_TOKEN
                        },
                        body: JSON.stringify({ id: Number(id), action: action })
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok || !data.ok) {
                        alert('Could not update the review. Please refresh and try again.');
                        return;
                    }

                    const row = document.getElementById('review-row-' + id);
                    const badge = row ? row.querySelector('[data-review-status]') : null;
                    if (badge) {
                        badge.textContent = data.status;
                        if (data.status === 'Approved') {
                            badge.style.background = '#dcfce7';
                            badge.style.color = '#166534';
                        } else if (data.status === 'Hidden') {
                            badge.style.background = '#fee2e2';
                            badge.style.color = '#991b1b';
                        } else {
                            badge.style.background = '#fef3c7';
                            badge.style.color = '#92400e';
                        }
                    }
                } catch (err) {
                    alert('Network error while updating the review.');
                } finally {
                    button.disabled = false;
                }
            });
        });

        function setActiveTab(button) {
            if (!button || !button.parentElement) return;
            button.parentElement.querySelectorAll('.tab-btn').forEach((b) => b.classList.remove('active'));
            button.classList.add('active');
        }

        function renderEmptyRow(tbody, columns) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = columns;
            td.textContent = 'No data for this period yet.';
            td.style.textAlign = 'center';
            td.style.color = 'var(--text-muted)';
            td.style.padding = '24px';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }

        function filterReservation(type, event) {
            if (event && event.target) setActiveTab(event.target);

            const tbody = document.getElementById('reservationReportBody');
            const rows = reservationReports[type] || [];
            tbody.innerHTML = '';
            if (!rows.length) {
                renderEmptyRow(tbody, 5);
                return;
            }

            rows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${escapeHtml(row.period)}</strong></td>
                    <td>${escapeHtml(row.total)}</td>
                    <td><span style="color:var(--sage); font-weight:600;">${escapeHtml(row.completed)}</span></td>
                    <td><span style="color:var(--crimson); font-weight:600;">${escapeHtml(row.cancelled)}</span></td>
                    <td>${escapeHtml(row.service)}</td>
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

        function filterRevenue(type, event) {
            if (event && event.target) setActiveTab(event.target);

            const tbody = document.getElementById('revenueReportBody');
            const rows = revenueReports[type] || [];
            tbody.innerHTML = '';

            if (!rows.length) {
                renderEmptyRow(tbody, 4);
                return;
            }

            rows.forEach((row) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong>${escapeHtml(row.period)}</strong></td>
                    <td style="color:var(--sage); font-weight:600;">${peso(row.collected)}</td>
                    <td style="color:var(--crimson); font-weight:600;">${peso(row.balance)}</td>
                    <td>${escapeHtml(row.status)}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderReviews();

            const resSection = document.getElementById('reservationReportBody');
            const revSection = document.getElementById('revenueReportBody');

            filterReservation(
                'Daily',
                {
                    target: resSection?.closest('.section-container')?.querySelector('.tab-btn') ?? null
                }
            );

            filterRevenue(
                'Daily',
                {
                    target: revSection?.closest('.section-container')?.querySelector('.tab-btn') ?? null
                }
            );
        });
</body>
</html>
