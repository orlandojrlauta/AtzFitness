<?php
/**
 * ATZ Fitness Gym Management System
 * Reports & Analytics Module
 */

$page_title = "Reports & Analytics";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

// Revenue breakdown
$total_rev = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE status = 'Paid'"))['total'] ?? 0;
$cash_rev = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE status = 'Paid' AND payment_method = 'Cash'"))['total'] ?? 0;
$gcash_rev = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM payments WHERE status = 'Paid' AND payment_method = 'GCash'"))['total'] ?? 0;

$m_regular = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM members WHERE member_type = 'Regular'"))['cnt'] ?? 0;
$m_student = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM members WHERE member_type = 'Student'"))['cnt'] ?? 0;


// Yearly revenue totals (all years that have Paid payments)
$yearly_rev = [];
$year_res = mysqli_query($conn, "SELECT YEAR(payment_date) as yr, SUM(amount) as total, COUNT(*) as cnt
    FROM payments WHERE status = 'Paid' GROUP BY yr ORDER BY yr DESC");
while ($row = mysqli_fetch_assoc($year_res)) {
    $yearly_rev[] = $row;
}

// Selected year for monthly breakdown (defaults to current year, or latest year with data)
$available_years = array_column($yearly_rev, 'yr');
$default_year = $available_years ? $available_years[0] : date('Y');
$selected_year = isset($_GET['year']) && in_array($_GET['year'], $available_years) ? (int)$_GET['year'] : (int)$default_year;

// Monthly revenue breakdown for the selected year (includes months with zero revenue)
$monthly_rev = [];
for ($m = 1; $m <= 12; $m++) {
    $monthly_rev[$m] = ['label' => date('F', mktime(0, 0, 0, $m, 1)), 'total' => 0.0, 'cnt' => 0];
}
$month_stmt = mysqli_prepare($conn, "SELECT MONTH(payment_date) as mo, SUM(amount) as total, COUNT(*) as cnt
    FROM payments WHERE status = 'Paid' AND YEAR(payment_date) = ? GROUP BY mo");
mysqli_stmt_bind_param($month_stmt, "i", $selected_year);
mysqli_stmt_execute($month_stmt);
$month_res = mysqli_stmt_get_result($month_stmt);
while ($row = mysqli_fetch_assoc($month_res)) {
    $monthly_rev[(int)$row['mo']]['total'] = (float)$row['total'];
    $monthly_rev[(int)$row['mo']]['cnt'] = (int)$row['cnt'];
}
$selected_year_total = array_sum(array_column($monthly_rev, 'total'));

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom d-print-none">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-bar-chart-line-fill text-warning me-2"></i> Analytics & Financial Reports</h1>
    <button type="button" class="btn btn-dark" onclick="window.print()">
        <i class="bi bi-printer-fill me-1"></i> Print Report
    </button>
</div>

<div class="print-report-area">
<div class="print-letterhead">
    <img src="../assets/img/logo.jpg" alt="ATZ Fitness Gym Logo" class="pl-logo">
    <div>
        <h2>ATZ FITNESS GYM</h2>
        <div class="pl-meta">Analytics & Financial Report</div>
        <div class="pl-meta">Generated on <?php echo date('F d, Y \a\t h:i A'); ?> by <?php echo sanitize($_SESSION['full_name'] ?? 'System User'); ?></div>
    </div>
</div>

<!-- Revenue Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card bg-dark text-white p-4 h-100">
            <h6 class="text-uppercase text-warning fw-bold mb-2">Total Gross Revenue</h6>
            <h2 class="fw-bold mb-0">₱<?php echo number_format($total_rev, 2); ?></h2>
            <div class="small text-white-50 mt-2">All-time lifetime earnings</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left border-4 border-success p-4 h-100">
            <h6 class="text-uppercase text-muted fw-bold mb-2">Total Cash Collections</h6>
            <h2 class="fw-bold mb-0 text-success">₱<?php echo number_format($cash_rev, 2); ?></h2>
            <div class="small text-muted mt-2">Over-the-counter payments</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-left border-4 border-primary p-4 h-100">
            <h6 class="text-uppercase text-muted fw-bold mb-2">Total GCash Collections</h6>
            <h2 class="fw-bold mb-0 text-primary">₱<?php echo number_format($gcash_rev, 2); ?></h2>
            <div class="small text-muted mt-2">Digital QR payments</div>
        </div>
    </div>
</div>

<!-- Yearly Revenue -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card p-3">
            <h5 class="fw-bold mb-3"><i class="bi bi-calendar-range text-warning me-2"></i> Yearly Revenue</h5>
            <?php if (empty($yearly_rev)): ?>
                <p class="text-muted mb-0">No paid payments recorded yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-uppercase small text-muted">
                            <th>Year</th>
                            <th>Payments</th>
                            <th class="text-end">Total Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($yearly_rev as $yr): ?>
                        <tr class="<?php echo ((int)$yr['yr'] === $selected_year) ? 'table-warning' : ''; ?>">
                            <td class="fw-bold">
                                <a href="reports.php?year=<?php echo (int)$yr['yr']; ?>" class="text-decoration-none text-dark">
                                    <?php echo (int)$yr['yr']; ?>
                                </a>
                            </td>
                            <td><?php echo (int)$yr['cnt']; ?></td>
                            <td class="text-end fw-bold text-success">₱<?php echo number_format((float)$yr['total'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Monthly Revenue Breakdown -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card p-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-graph-up text-warning me-2"></i> Monthly Revenue &mdash; <?php echo $selected_year; ?></h5>
                <?php if (!empty($available_years)): ?>
                <form method="get" class="d-print-none">
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <?php foreach ($available_years as $yr): ?>
                            <option value="<?php echo (int)$yr; ?>" <?php echo ((int)$yr === $selected_year) ? 'selected' : ''; ?>>
                                <?php echo (int)$yr; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr class="text-uppercase small text-muted">
                            <th>Month</th>
                            <th>Payments</th>
                            <th class="text-end">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_rev as $mo): ?>
                        <tr>
                            <td><?php echo $mo['label']; ?></td>
                            <td><?php echo $mo['cnt']; ?></td>
                            <td class="text-end <?php echo $mo['total'] > 0 ? 'fw-bold text-success' : 'text-muted'; ?>">
                                ₱<?php echo number_format($mo['total'], 2); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-top border-2">
                            <th>Total</th>
                            <th><?php echo array_sum(array_column($monthly_rev, 'cnt')); ?></th>
                            <th class="text-end">₱<?php echo number_format($selected_year_total, 2); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Metrics Row -->
<div class="row g-4 mb-4">
    <div class="col-md-8 mx-auto">
        <div class="card p-3">
            <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart text-warning me-2"></i> Member Distribution by Category</h5>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-person me-2 text-success"></i> Regular Members</span>
                    <span class="badge bg-success rounded-pill fs-6"><?php echo $m_regular; ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-mortarboard me-2 text-primary"></i> Student Members (Verified)</span>
                    <span class="badge bg-primary rounded-pill fs-6"><?php echo $m_student; ?></span>
                </li>
            </ul>
        </div>
    </div>
</div>

</div><!-- /.print-report-area -->

<?php require_once '../includes/footer.php'; ?>