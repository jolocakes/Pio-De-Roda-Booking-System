<?php
/**
 * Shared admin sidebar (T-60).
 *
 * Every admin page used to carry its own ~80-line copy of this markup, which
 * drifted (some used <aside>, some <div>, active states and whitespace all
 * differed). Set $activeNav before requiring this file:
 *
 *     <?php $activeNav = 'reservation'; require __DIR__ . '/_sidebar.php'; ?>
 *
 * Valid keys: dashboard, reservation, calendar, service, event, customer,
 * payment, reports, archive, audit, staff, profile.
 */

if (!isset($activeNav)) {
    $activeNav = '';
}
$activeNav = (string) $activeNav;

$pdrSidebarItems = [
    'dashboard'   => ['dashboard.php', 'fa-house', 'Dashboard'],
    'reservation' => ['reservation.php', 'fa-calendar-check', 'Reservations'],
    'calendar'    => ['calendar.php', 'fa-calendar-days', 'Calendar'],
    'service'     => ['service.php', 'fa-concierge-bell', 'Services'],
    'event'       => ['event.php', 'fa-calendar-days', 'Events'],
    'customer'    => ['customer.php', 'fa-users', 'Customers'],
    'payment'     => ['payment.php', 'fa-money-bill-wave', 'Payment'],
    'reports'     => ['reports.php', 'fa-chart-column', 'Reports'],
    'archive'     => ['archive.php', 'fa-clock-rotate-left', 'History'],
];

// Audit log is admin-only (T-35 / T-36).
if (pdrCan('view_audit')) {
    $pdrSidebarItems['audit'] = ['audit.php', 'fa-clipboard-list', 'Audit Log'];
}

// Staff is admin-only; keep it last and out of the array for other roles.
if (pdrCan('manage_staff')) {
    $pdrSidebarItems['staff'] = ['staff.php', 'fa-user-shield', 'Staff'];
}
?>
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
                <?php foreach ($pdrSidebarItems as $key => $item): ?>
                    <li class="nav-item<?php echo $key === $activeNav ? ' active' : ''; ?>">
                        <a href="<?php echo $item[0]; ?>"><i class="fa-solid <?php echo $item[1]; ?>"></i> <?php echo $item[2]; ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="sidebar-footer">
            <li class="nav-item<?php echo $activeNav === 'profile' ? ' active' : ''; ?>" style="list-style: none;">
                <a href="profile.php"><i class="fa-solid fa-id-badge"></i> My Account</a>
            </li>
            <li class="nav-item" style="list-style: none;">
                <form method="POST" action="logout.php" class="logout-form">
                    <?php echo pdrCsrfField(); ?>
                    <button type="submit" class="logout-link"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
                </form>
            </li>
        </div>
    </aside>
