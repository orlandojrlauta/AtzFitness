<?php
/**
 * ATZ Fitness Gym Management System
 * Sidebar Navigation
 */
$user_role = $_SESSION['role'] ?? 'Staff';
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF'])); // 'admin' or 'staff'

// Members/Attendance/etc. live only in admin/. The prefix must be based on
// which folder we're CURRENTLY in, not on role — otherwise a staff user who
// is already inside admin/ (e.g. admin/members.php) ends up with a link
// like ../admin/index.php, which cancels back out to admin/index.php
// instead of staying relative.
$shared = ($current_dir === 'staff') ? '../admin/' : '';

// The Dashboard is the one page that ISN'T shared: staff have their own at
// staff/index.php, admins have admin/index.php. This must also be computed
// from the current directory so it points back to the right dashboard no
// matter which admin/*.php page staff navigated to.
if ($user_role === 'Staff') {
    $dashboard_link = ($current_dir === 'staff') ? 'index.php' : '../staff/index.php';
} else {
    $dashboard_link = ($current_dir === 'admin') ? 'index.php' : '../admin/index.php';
}
?>
<div class="offcanvas-md offcanvas-start col-md-3 col-lg-2 bg-dark sidebar shadow-sm p-0" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
    <div class="offcanvas-header border-bottom border-secondary-subtle d-md-none px-3">
        <h5 class="offcanvas-title text-warning fw-bold mb-0" id="sidebarMenuLabel">
            <i class="bi bi-list me-2"></i> Menu
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-md-flex flex-column p-0 py-md-3">
        <div class="position-sticky pt-2 px-2">
        <ul class="nav flex-column gap-1">
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'index.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $dashboard_link; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'membership_plans.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $shared; ?>membership_plans.php">
                    <i class="bi bi-card-checklist me-2"></i> Membership Plans
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'members.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $shared; ?>members.php">
                    <i class="bi bi-people-fill me-2"></i> Members
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'memberships.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $shared; ?>memberships.php">
                    <i class="bi bi-person-vcard-fill me-2"></i> Active Memberships
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'attendance.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $shared; ?>attendance.php">
                    <i class="bi bi-person-check-fill me-2"></i> Daily Attendance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'walkins.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $shared; ?>walkins.php">
                    <i class="bi bi-person-walking me-2"></i> Walk-ins
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'daily_report.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $shared; ?>daily_report.php">
                    <i class="bi bi-calendar2-check-fill me-2"></i> Daily Report
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'payments.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $shared; ?>payments.php">
                    <i class="bi bi-cash-coin me-2"></i> Payments & Receipts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'reports.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="<?php echo $shared; ?>reports.php">
                    <i class="bi bi-bar-chart-line-fill me-2"></i> Analytics & Reports
                </a>
            </li>

            <?php if ($user_role === 'Administrator'): ?>
            <div class="sidebar-heading text-uppercase text-secondary fs-7 fw-bold mt-4 mb-2 px-3">
                System Administration
            </div>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'staff.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="staff.php">
                    <i class="bi bi-person-gear me-2"></i> Staff Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link text-white rounded p-2.5 <?php echo $current_page == 'activity_logs.php' ? 'active bg-warning text-dark fw-bold' : 'hover-bg'; ?>" href="activity_logs.php">
                    <i class="bi bi-journal-text me-2"></i> Activity Audit Logs

                </a>
            </li>
            <?php endif; ?>
        </ul>
        </div>
    </div>
</div>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4 min-vh-100">