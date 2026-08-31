<?php
/**
 * ATZ Fitness Gym Management System
 * Daily Report — Member Attendance + Walk-ins + Revenue for a chosen date
 */

$page_title = "Daily Report";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

// Selected date (defaults to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}
$is_today = ($selected_date === date('Y-m-d'));

// Member attendance for the day
$att_stmt = mysqli_prepare($conn, "SELECT a.*, m.member_code, m.first_name, m.last_name, m.member_type FROM attendance a JOIN members m ON a.member_id = m.id WHERE a.date = ? ORDER BY a.check_in_time ASC");
mysqli_stmt_bind_param($att_stmt, "s", $selected_date);
mysqli_stmt_execute($att_stmt);
$attendance_res = mysqli_stmt_get_result($att_stmt);
$attendance_count = mysqli_num_rows($attendance_res);

// Walk-in guests for the day
$w_stmt = mysqli_prepare($conn, "SELECT * FROM walkin_customers WHERE date = ? ORDER BY check_in_time ASC");
mysqli_stmt_bind_param($w_stmt, "s", $selected_date);
mysqli_stmt_execute($w_stmt);
$walkins_res = mysqli_stmt_get_result($w_stmt);
$walkins_count = mysqli_num_rows($walkins_res);

// Revenue collected for the day
$rev_stmt = mysqli_prepare($conn, "SELECT
        COALESCE(SUM(amount), 0) as total,
        COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) as cash_total,
        COALESCE(SUM(CASE WHEN payment_method = 'GCash' THEN amount ELSE 0 END), 0) as gcash_total
    FROM payments WHERE status = 'Paid' AND DATE(payment_date) = ?");
mysqli_stmt_bind_param($rev_stmt, "s", $selected_date);
mysqli_stmt_execute($rev_stmt);
$revenue = mysqli_fetch_assoc(mysqli_stmt_get_result($rev_stmt));

$total_visits = $attendance_count + $walkins_count;

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom d-print-none">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-calendar2-check-fill text-warning me-2"></i> Daily Report</h1>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="daily_report.php" class="d-flex align-items-center gap-2">
            <label class="fw-semibold small text-muted mb-0">Date:</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo sanitize($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
            <?php if (!$is_today): ?>
                <a href="daily_report.php" class="btn btn-sm btn-outline-dark">Today</a>
            <?php endif; ?>
        </form>
        <button type="button" class="btn btn-dark" onclick="window.print()">
            <i class="bi bi-printer-fill me-1"></i> Print Report
        </button>
    </div>
</div>

<div class="print-report-area">
<div class="print-letterhead">
    <img src="../assets/img/logo.jpg" alt="ATZ Fitness Gym Logo" class="pl-logo">
    <div>
        <h2>ATZ FITNESS GYM</h2>
        <div class="pl-meta">Daily Report &mdash; <?php echo date('l, F d, Y', strtotime($selected_date)); ?></div>
        <div class="pl-meta">Generated on <?php echo date('F d, Y \a\t h:i A'); ?> by <?php echo sanitize($_SESSION['full_name'] ?? 'System User'); ?></div>
    </div>
</div>

<div class="mb-3">
    <span class="badge bg-dark text-warning fs-6 px-3 py-2"><i class="bi bi-calendar3 me-1"></i> <?php echo date('l, F d, Y', strtotime($selected_date)); ?></span>
</div>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card bg-dark text-white p-3 h-100">
            <h6 class="text-uppercase text-warning fw-bold mb-2 small">Total Visits</h6>
            <h3 class="fw-bold mb-0"><?php echo $total_visits; ?></h3>
            <div class="small text-white-50 mt-1">Members + Walk-ins</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 h-100 border-start border-4 border-success">
            <h6 class="text-uppercase text-muted fw-bold mb-2 small">Member Check-ins</h6>
            <h3 class="fw-bold mb-0 text-success"><?php echo $attendance_count; ?></h3>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 h-100 border-start border-4 border-dark">
            <h6 class="text-uppercase text-muted fw-bold mb-2 small">Walk-in Guests</h6>
            <h3 class="fw-bold mb-0 text-dark"><?php echo $walkins_count; ?></h3>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card p-3 h-100 border-start border-4 border-warning">
            <h6 class="text-uppercase text-muted fw-bold mb-2 small">Revenue Collected</h6>
            <h3 class="fw-bold mb-0">₱<?php echo number_format($revenue['total'], 2); ?></h3>
            <div class="small text-muted mt-1">Cash ₱<?php echo number_format($revenue['cash_total'], 2); ?> · GCash ₱<?php echo number_format($revenue['gcash_total'], 2); ?></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Member Attendance -->
    <div class="col-md-6">
        <div class="card p-3 shadow-sm h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-people-fill text-success me-2"></i> Member Attendance</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Member</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($attendance_count > 0): ?>
                            <?php while ($a = mysqli_fetch_assoc($attendance_res)): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo sanitize($a['first_name'] . ' ' . $a['last_name']); ?></div>
                                        <span class="badge bg-dark text-warning font-monospace small"><?php echo sanitize($a['member_code']); ?></span>
                                    </td>
                                    <td class="fw-bold text-success"><?php echo date('h:i A', strtotime($a['check_in_time'])); ?></td>
                                    <td class="small text-muted">
                                        <?php echo $a['check_out_time'] ? date('h:i A', strtotime($a['check_out_time'])) : '<span class="text-warning fw-bold">Still In</span>'; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $a['status'] === 'Checked-In' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo sanitize($a['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No member attendance recorded on this date.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Walk-in Guests -->
    <div class="col-md-6">
        <div class="card p-3 shadow-sm h-100">
            <h5 class="fw-bold mb-3"><i class="bi bi-person-walking text-dark me-2"></i> Walk-in Guests</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Guest Name</th>
                            <th>Time In</th>
                            <th>Rate Paid</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($walkins_count > 0): ?>
                            <?php while ($w = mysqli_fetch_assoc($walkins_res)): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo sanitize($w['guest_name']); ?></td>
                                    <td class="small"><?php echo date('h:i A', strtotime($w['check_in_time'])); ?></td>
                                    <td class="fw-bold text-success">₱<?php echo number_format($w['rate'], 2); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo sanitize($w['payment_method']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No walk-in guests recorded on this date.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div><!-- /.print-report-area -->

<?php require_once '../includes/footer.php'; ?>