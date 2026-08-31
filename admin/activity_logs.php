<?php
/**
 * ATZ Fitness Gym Management System
 * System Audit Activity Logs Module (Admin Only)
 */

$page_title = "Activity Audit Logs";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$search = sanitize($_GET['search'] ?? '');
$filter_role = sanitize($_GET['role'] ?? '');

$query = "SELECT * FROM activity_logs WHERE 1=1";
$types = "";
$params = [];

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR action LIKE ? OR description LIKE ?)";
    $like = "%$search%";
    $types .= "sss";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (!empty($filter_role)) {
    $query .= " AND role = ?";
    $types .= "s";
    $params[] = $filter_role;
}

$query .= " ORDER BY id DESC LIMIT 100";

$stmt = mysqli_prepare($conn, $query);
if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$logs_res = mysqli_stmt_get_result($stmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-journal-text text-warning me-2"></i> System Activity Audit Logs</h1>
</div>

<!-- Filter controls -->
<div class="card p-3 mb-4">
    <form method="GET" action="activity_logs.php" class="row g-3">
        <div class="col-md-7">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by Username, Action, or Description..." value="<?php echo sanitize($search); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select">
                <option value="">All Roles</option>
                <option value="Administrator" <?php echo $filter_role == 'Administrator' ? 'selected' : ''; ?>>Administrator</option>
                <option value="Staff" <?php echo $filter_role == 'Staff' ? 'selected' : ''; ?>>Staff</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-dark w-100"><i class="bi bi-filter me-1"></i> Filter</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Description Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($logs_res) > 0): ?>
                    <?php while ($log = mysqli_fetch_assoc($logs_res)): ?>
                        <tr>
                            <td class="small text-muted fw-bold"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                            <td class="fw-bold text-primary"><?php echo sanitize($log['username']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo sanitize($log['role']); ?></span></td>
                            <td><span class="badge bg-dark"><?php echo sanitize($log['action']); ?></span></td>
                            <td class="small text-dark"><?php echo sanitize($log['description']); ?></td>
                            <td class="small font-monospace text-muted"><?php echo sanitize($log['ip_address']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No activity logs recorded.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
