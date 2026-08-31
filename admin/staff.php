<?php
/**
 * ATZ Fitness Gym Management System
 * Staff Accounts Management Module (Admin Only)
 */

$page_title = "Staff Management";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin(); // Strict Admin Authorization

$action = $_POST['action'] ?? '';

// Edit Staff Account, Reset Staff Password, and Register Staff Account are
// intentionally NOT handled here. Staff self-register via register.php and
// manage their own profile/password via staff/profile.php and
// forgot_password.php. Admin's role for staff accounts is limited to
// approving/suspending access (Toggle Status, below) and removing accounts
// flagged as suspicious (Delete, below).

// Toggle Staff Status
if (isset($_GET['toggle_status'])) {
    verify_csrf();
    $s_id = intval($_GET['toggle_status']);
    mysqli_query($conn, "UPDATE users SET status = IF(status='Active','Inactive','Active') WHERE id = $s_id AND role = 'Staff'");
    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Toggle Staff Status', "Updated status for Staff ID #{$s_id}");
    header("Location: staff.php");
    exit();
}

// Delete Staff Account (e.g. an account flagged as suspicious)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete_staff') {
    verify_csrf();
    $s_id = intval($_POST['staff_id'] ?? 0);

    $chk = mysqli_prepare($conn, "SELECT username, full_name FROM users WHERE id = ? AND role = 'Staff'");
    mysqli_stmt_bind_param($chk, "i", $s_id);
    mysqli_stmt_execute($chk);
    $target = mysqli_fetch_assoc(mysqli_stmt_get_result($chk));
    mysqli_stmt_close($chk);

    if ($target) {
        $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ? AND role = 'Staff'");
        mysqli_stmt_bind_param($stmt, "i", $s_id);

        if (mysqli_stmt_execute($stmt)) {
            // Logged under the admin performing the action — the target's own
            // past activity log entries store their own username/role as text,
            // so that history stays intact even after the account is gone.
            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Delete Staff', "Deleted staff account {$target['username']} ({$target['full_name']})");
            $_SESSION['swal_title'] = "Deleted!";
            $_SESSION['swal_msg'] = "Staff account for {$target['full_name']} ({$target['username']}) has been permanently removed.";
            $_SESSION['swal_type'] = "success";
        } else {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "Could not delete staff account. Please try again.";
            $_SESSION['swal_type'] = "error";
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Staff account not found.";
        $_SESSION['swal_type'] = "error";
    }
    header("Location: staff.php");
    exit();
}

// Fetch Staff Users
$staff_res = mysqli_query($conn, "SELECT * FROM users WHERE role = 'Staff' ORDER BY id DESC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-person-gear text-warning me-2"></i> Staff Accounts Management</h1>
</div>


<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Staff</th>
                    <th>Contact & Email</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($staff_res) > 0): ?>
                    <?php while ($s = mysqli_fetch_assoc($staff_res)): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($s['profile_picture']) && file_exists('../uploads/profile/' . $s['profile_picture'])): ?>
                                        <img src="../uploads/profile/<?php echo sanitize($s['profile_picture']); ?>" alt="Profile Photo" class="rounded-circle me-2" style="width: 38px; height: 38px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary me-2 text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold"><?php echo sanitize($s['full_name']); ?></div>
                                        <span class="small text-primary font-monospace">@<?php echo sanitize($s['username']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold"><i class="bi bi-telephone-fill text-success me-1"></i><?php echo sanitize($s['contact_no']); ?></div>
                                <div class="small text-muted"><i class="bi bi-envelope-fill text-primary me-1"></i><?php echo sanitize($s['email']); ?></div>
                            </td>
                            <td class="small text-muted"><?php echo date('M d, Y', strtotime($s['created_at'])); ?></td>
                            <td>
                                <span class="badge <?php echo $s['status'] === 'Active' ? 'bg-success' : 'bg-secondary'; ?> px-2 py-1">
                                    <?php echo sanitize($s['status']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="staff.php?toggle_status=<?php echo $s['id']; ?>&csrf_token=<?php echo urlencode(csrf_token()); ?>" class="btn btn-sm btn-outline-dark">
                                    <?php echo $s['status'] === 'Active' ? '<i class="bi bi-person-x me-1"></i> Deactivate' : '<i class="bi bi-person-check me-1"></i> Activate'; ?>
                                </a>
                                <form method="POST" action="staff.php" class="d-inline confirm-submit"
                                      data-confirm-title="Delete Staff Account?"
                                      data-confirm-text="This will permanently delete the account for <?php echo sanitize($s['username']); ?>. This cannot be undone."
                                      data-confirm-icon="warning"
                                      data-confirm-button="Yes, delete it">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_staff">
                                    <input type="hidden" name="staff_id" value="<?php echo $s['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash3-fill me-1"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <i class="bi bi-person-gear d-block mb-2" style="font-size: 2rem;"></i>
                            No staff accounts registered yet.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>