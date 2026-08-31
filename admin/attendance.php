<?php
/**
 * ATZ Fitness Gym Management System
 * Daily Attendance Module
 */

$page_title = "Daily Attendance";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

$action = $_POST['action'] ?? '';

// Check-in or Check-out Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'process_attendance') {
    verify_csrf();
    $code_or_id = sanitize($_POST['member_code'] ?? '');

    if (!empty($code_or_id)) {
        // Search member
        $stmt = mysqli_prepare($conn, "SELECT id, member_code, first_name, last_name, status FROM members WHERE member_code = ? OR id = ?");
        mysqli_stmt_bind_param($stmt, "ss", $code_or_id, $code_or_id);
        mysqli_stmt_execute($stmt);
        $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$member) {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "Member not found. Please check Member Code.";
            $_SESSION['swal_type'] = "error";
        } else {
            // Check active membership
            $m_id = $member['id'];
            $mship_chk = mysqli_query($conn, "SELECT end_date FROM memberships WHERE member_id = $m_id AND status = 'Active' AND end_date >= CURDATE() ORDER BY end_date DESC LIMIT 1");

            if (mysqli_num_rows($mship_chk) == 0) {
                $_SESSION['swal_title'] = "Membership Expired / Invalid!";
                $_SESSION['swal_msg'] = "Member {$member['first_name']} {$member['last_name']} does not have an active membership. Please renew first!";
                $_SESSION['swal_type'] = "warning";
            } else {
                // Check if already checked in today without checking out
                $today_chk = mysqli_query($conn, "SELECT id, status FROM attendance WHERE member_id = $m_id AND date = CURDATE() ORDER BY id DESC LIMIT 1");
                $existing = mysqli_fetch_assoc($today_chk);

                if ($existing && $existing['status'] === 'Checked-In') {
                    // Perform Check-Out
                    $att_id = $existing['id'];
                    $now = date('Y-m-d H:i:s');
                    mysqli_query($conn, "UPDATE attendance SET check_out_time = '$now', status = 'Checked-Out' WHERE id = $att_id");

                    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Attendance Check-Out', "Checked out member {$member['member_code']}");

                    $_SESSION['swal_title'] = "Checked Out!";
                    $_SESSION['swal_msg'] = "Member {$member['first_name']} {$member['last_name']} successfully checked out at " . date('h:i A');
                    $_SESSION['swal_type'] = "info";
                } else if ($existing && $existing['status'] === 'Checked-Out') {
                    // Already completed one check-in/out cycle today — only one attendance per day allowed
                    $_SESSION['swal_title'] = "Already Recorded!";
                    $_SESSION['swal_msg'] = "Member {$member['first_name']} {$member['last_name']} has already checked in and out today. Only one attendance per day is allowed.";
                    $_SESSION['swal_type'] = "warning";
                } else {
                    // Perform Check-In
                    $now = date('Y-m-d H:i:s');
                    $today = date('Y-m-d');
                    $att_stmt = mysqli_prepare($conn, "INSERT INTO attendance (member_id, check_in_time, date, status) VALUES (?, ?, ?, 'Checked-In')");
                    mysqli_stmt_bind_param($att_stmt, "iss", $m_id, $now, $today);
                    mysqli_stmt_execute($att_stmt);
                    mysqli_stmt_close($att_stmt);

                    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Attendance Check-In', "Checked in member {$member['member_code']}");

                    $_SESSION['swal_title'] = "Checked In!";
                    $_SESSION['swal_msg'] = "Welcome {$member['first_name']} {$member['last_name']}! Checked in at " . date('h:i A');
                    $_SESSION['swal_type'] = "success";
                }
            }
        }
    }
    header("Location: attendance.php");
    exit();
}

// Fetch Attendance Roster for selected date (defaults to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}

$roster_stmt = mysqli_prepare($conn, "SELECT a.*, m.member_code, m.first_name, m.last_name, m.member_type FROM attendance a JOIN members m ON a.member_id = m.id WHERE a.date = ? ORDER BY a.id DESC");
mysqli_stmt_bind_param($roster_stmt, "s", $selected_date);
mysqli_stmt_execute($roster_stmt);
$today_roster = mysqli_stmt_get_result($roster_stmt);
$is_today = ($selected_date === date('Y-m-d'));

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-person-check-fill text-warning me-2"></i> Daily Attendance</h1>
    <form method="GET" action="attendance.php" class="d-flex align-items-center gap-2">
        <label class="fw-semibold small text-muted mb-0">Viewing:</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?php echo sanitize($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
        <?php if (!$is_today): ?>
            <a href="attendance.php" class="btn btn-sm btn-outline-dark">Today</a>
        <?php endif; ?>
    </form>
</div>

<div class="row g-4 mb-4">
    <?php if ($is_today): ?>
    <div class="col-md-5">
        <div class="card p-4 shadow-sm border-top border-4 border-warning">
            <h5 class="fw-bold mb-3"><i class="bi bi-keyboard-fill text-warning me-2"></i> Enter Member Code</h5>
            <form method="POST" action="attendance.php">
            <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="process_attendance">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Member Code or ID </label>
                    <input type="text" name="member_code" class="form-control form-control-lg font-monospace text-uppercase" placeholder="e.g. ATZ-12345" required autofocus>
                    <div class="form-text">Type the member's code or ID, then press Enter or click submit.</div>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm text-dark fs-5">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Process Check-In / Out
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-md-<?php echo $is_today ? '7' : '12'; ?>">
        <div class="card p-3 shadow-sm">
            <h5 class="fw-bold mb-3"><i class="bi bi-card-checklist me-2"></i> Attendance Roster — <?php echo date('F d, Y', strtotime($selected_date)); ?></h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($today_roster) > 0): ?>
                            <?php while ($a = mysqli_fetch_assoc($today_roster)): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo sanitize($a['first_name'] . ' ' . $a['last_name']); ?></div>
                                        <span class="badge bg-dark text-warning font-monospace small"><?php echo sanitize($a['member_code']); ?></span>
                                    </td>
                                    <td class="small text-muted"><i class="bi bi-clock-fill me-1"></i><?php echo date('h:i A', strtotime($a['check_in_time'])); ?></td>
                                    <td class="small text-muted">
                                        <?php echo $a['check_out_time'] ? date('h:i A', strtotime($a['check_out_time'])) : '<span class="text-warning fw-bold">Active in Gym</span>'; ?>
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
                                <td colspan="4" class="text-center py-4 text-muted">No attendance recorded for this date.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>