<?php
/**
 * ATZ Fitness Gym Management System
 * Payments & Financial Logs Module
 */

$page_title = "Payments & Receipts";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

// Filters
$method = sanitize($_GET['method'] ?? '');
$for = sanitize($_GET['payment_for'] ?? '');

$query = "SELECT p.*, 
            m.first_name as m_first, m.last_name as m_last, m.member_code,
            w.guest_name,
            u.full_name as processed_by_name
          FROM payments p
          LEFT JOIN members m ON p.member_id = m.id
          LEFT JOIN walkin_customers w ON p.walkin_id = w.id
          LEFT JOIN users u ON p.processed_by = u.id
          WHERE 1=1";

$types = "";
$params = [];

if (!empty($method)) {
    $query .= " AND p.payment_method = ?";
    $types .= "s";
    $params[] = $method;
}
if (!empty($for)) {
    $query .= " AND p.payment_for = ?";
    $types .= "s";
    $params[] = $for;
}

$query .= " ORDER BY p.id DESC";

$stmt = mysqli_prepare($conn, $query);
if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$payments_res = mysqli_stmt_get_result($stmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-cash-coin text-warning me-2"></i> Payment History & Receipts</h1>
</div>

<!-- Filter Box -->
<div class="card p-3 mb-4">
    <form method="GET" action="payments.php" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label fw-semibold">Payment Method</label>
            <select name="method" class="form-select">
                <option value="">All Methods (Cash & GCash)</option>
                <option value="Cash" <?php echo $method == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                <option value="GCash" <?php echo $method == 'GCash' ? 'selected' : ''; ?>>GCash</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label fw-semibold">Payment Purpose</label>
            <select name="payment_for" class="form-select">
                <option value="">All Purposes</option>
                <option value="Membership" <?php echo $for == 'Membership' ? 'selected' : ''; ?>>Membership Pass</option>
                <option value="Walk-in" <?php echo $for == 'Walk-in' ? 'selected' : ''; ?>>Walk-in Fee</option>
                <option value="Renewal" <?php echo $for == 'Renewal' ? 'selected' : ''; ?>>Plan Renewal</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-dark w-100"><i class="bi bi-filter me-1"></i> Filter Logs</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Transaction No</th>
                    <th>Customer Name</th>
                    <th>Payment Purpose</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Processed By</th>
                    <th>Date & Time</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($payments_res) > 0): ?>
                    <?php while ($p = mysqli_fetch_assoc($payments_res)): ?>
                        <?php 
                            $c_name = $p['m_first'] ? ($p['m_first'] . ' ' . $p['m_last']) : ($p['guest_name'] ?? 'Guest');
                        ?>
                        <tr>
                            <td class="font-monospace fw-bold text-primary"><?php echo sanitize($p['transaction_no']); ?></td>
                            <td class="fw-bold"><?php echo sanitize($c_name); ?></td>
                            <td><span class="badge bg-dark"><?php echo sanitize($p['payment_for']); ?></span></td>
                            <td class="fw-bold text-success">₱<?php echo number_format($p['amount'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo $p['payment_method'] === 'GCash' ? 'bg-primary' : 'bg-success'; ?>">
                                    <?php echo sanitize($p['payment_method']); ?>
                                </span>
                            </td>
                            <td class="small"><?php echo sanitize($p['processed_by_name'] ?? 'System Admin'); ?></td>
                            <td class="small text-muted"><?php echo date('M d, Y h:i A', strtotime($p['payment_date'])); ?></td>
                            <td>
                                <a href="receipt.php?txn=<?php echo urlencode($p['transaction_no']); ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                    <i class="bi bi-receipt"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">No payment transaction logs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>