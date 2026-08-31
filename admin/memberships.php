<?php
/**
 * ATZ Fitness Gym Management System
 * Active Memberships & Assignment Module
 */

$page_title = "Active Memberships";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

$action = $_POST['action'] ?? '';

// Assign New Membership / Renewal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'assign_membership') {
    verify_csrf();
    $member_id = intval($_POST['member_id'] ?? 0);
    $plan_id = intval($_POST['plan_id'] ?? 0);
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $gcash_ref_no = sanitize($_POST['gcash_ref_no'] ?? '');
    $amount_tendered_input = $_POST['amount_tendered'] ?? '';

    if ($member_id > 0 && $plan_id > 0) {
        // Fetch Plan details
        $p_stmt = mysqli_prepare($conn, "SELECT price, duration_days, plan_name FROM membership_plans WHERE id = ?");
        mysqli_stmt_bind_param($p_stmt, "i", $plan_id);
        mysqli_stmt_execute($p_stmt);
        $plan = mysqli_fetch_assoc(mysqli_stmt_get_result($p_stmt));
        mysqli_stmt_close($p_stmt);

        if ($plan) {
            $price_paid = $plan['price'];

            // Determine amount tendered & change (Cash only — GCash is always exact, no change)
            if ($payment_method === 'Cash') {
                $amount_tendered = is_numeric($amount_tendered_input) ? floatval($amount_tendered_input) : 0;
                if ($amount_tendered < $price_paid) {
                    // Guard against underpayment / tampered input — fall back to exact price
                    $amount_tendered = $price_paid;
                }
            } else {
                $amount_tendered = $price_paid; // GCash: exact amount, no change
            }
            $change_amount = round($amount_tendered - $price_paid, 2);

            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime("+{$plan['duration_days']} days"));

            // Insert Membership
            $m_stmt = mysqli_prepare($conn, "INSERT INTO memberships (member_id, plan_id, start_date, end_date, price_paid, status) VALUES (?, ?, ?, ?, ?, 'Active')");
            mysqli_stmt_bind_param($m_stmt, "iissd", $member_id, $plan_id, $start_date, $end_date, $price_paid);
            
            if (mysqli_stmt_execute($m_stmt)) {
                $membership_id = mysqli_insert_id($conn);

                // Update Member status to Active
                mysqli_query($conn, "UPDATE members SET status = 'Active' WHERE id = $member_id");

                // Record Payment Transaction
                $tx_no = "TXN-" . time() . "-" . rand(100, 999);
                $p_type = 'Membership';
                $processed_by = $_SESSION['user_id'];

                $pay_stmt = mysqli_prepare($conn, "INSERT INTO payments (transaction_no, member_id, membership_id, amount, amount_tendered, change_amount, payment_method, gcash_ref_no, payment_for, status, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Paid', ?)");
                mysqli_stmt_bind_param($pay_stmt, "siidddsssi", $tx_no, $member_id, $membership_id, $price_paid, $amount_tendered, $change_amount, $payment_method, $gcash_ref_no, $p_type, $processed_by);
                mysqli_stmt_execute($pay_stmt);
                mysqli_stmt_close($pay_stmt);

                log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Assign Membership', "Assigned plan {$plan['plan_name']} to Member ID #{$member_id}");

                $_SESSION['receipt_txn'] = $tx_no;
                $_SESSION['swal_title'] = "Success!";
                if ($payment_method === 'Cash' && $change_amount > 0) {
                    $_SESSION['swal_msg'] = "Membership assigned! Amount Received: ₱" . number_format($amount_tendered, 2) . " — Change Due: ₱" . number_format($change_amount, 2);
                } else {
                    $_SESSION['swal_msg'] = "Membership successfully assigned and payment recorded!";
                }
                $_SESSION['swal_type'] = "success";
            }
            mysqli_stmt_close($m_stmt);
        }
    }
    header("Location: memberships.php");
    exit();
}

// Fetch Active Memberships
$memberships_res = mysqli_query($conn, "SELECT ms.*, m.member_code, m.first_name, m.last_name, m.email, p.plan_name, DATEDIFF(ms.end_date, CURDATE()) as days_left, pay.transaction_no FROM memberships ms JOIN members m ON ms.member_id = m.id JOIN membership_plans p ON ms.plan_id = p.id LEFT JOIN payments pay ON pay.membership_id = ms.id ORDER BY ms.id DESC");

// Fetch active members & active plans for dropdown
$members_dropdown = mysqli_query($conn, "SELECT id, member_code, first_name, last_name, member_type FROM members WHERE status != 'Inactive' ORDER BY first_name ASC");
$plans_dropdown = mysqli_query($conn, "SELECT id, plan_name, price, duration_days FROM membership_plans WHERE status = 'Active' ORDER BY price ASC");

// Fetch Admin GCash QR setting
$gcash_qr = get_setting($conn, 'gcash_qr_image', 'gcash_qr_default.png');
$gcash_name = get_setting($conn, 'gcash_account_name', 'ATZ FITNESS GYM');
$gcash_no = get_setting($conn, 'gcash_account_no', '09171234567');

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-person-vcard-fill text-warning me-2"></i> Active Memberships</h1>
    <button class="btn btn-warning text-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#assignMembershipModal">
        <i class="bi bi-plus-circle-fill me-1"></i> Assign / Renew Plan
    </button>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Member</th>
                    <th>Plan Name</th>
                    <th>Start Date</th>
                    <th>Expiration Date</th>
                    <th>Days Remaining</th>
                    <th>Price Paid</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($memberships_res) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($memberships_res)): ?>
                        <?php
                            $is_paused = ($row['status'] === 'Inactive' && $row['paused_remaining_days'] !== null);
                            $days = $is_paused ? (int) $row['paused_remaining_days'] : $row['days_left'];
                            $badge_class = 'bg-success';
                            $status_label = 'Active';
                            if ($is_paused) {
                                $badge_class = 'bg-secondary';
                                $status_label = 'Paused (Member Inactive)';
                            } else if ($days < 0) {
                                $badge_class = 'bg-danger';
                                $status_label = 'Expired';
                            } else if ($days <= 7) {
                                $badge_class = 'bg-warning text-dark';
                                $status_label = 'Expiring Soon (' . $days . ' days)';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo sanitize($row['first_name'] . ' ' . $row['last_name']); ?></div>
                                <span class="badge bg-dark text-warning font-monospace small"><?php echo sanitize($row['member_code']); ?></span>
                            </td>
                            <td class="fw-bold text-primary"><?php echo sanitize($row['plan_name']); ?></td>
                            <td class="small"><?php echo $row['start_date']; ?></td>
                            <td class="small fw-bold"><?php echo $row['end_date']; ?></td>
                            <td>
                                <?php if ($is_paused): ?>
                                    <span class="fw-bold text-muted"><i class="bi bi-pause-circle-fill me-1"></i><?php echo $days; ?> Days (Paused)</span>
                                <?php elseif ($days >= 0): ?>
                                    <span class="fw-bold text-dark"><?php echo $days; ?> Days</span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold">Expired</span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-success">₱<?php echo number_format($row['price_paid'], 2); ?></td>
                            <td><span class="badge <?php echo $badge_class; ?> px-3 py-1"><?php echo $status_label; ?></span></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No memberships recorded.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Membership Modal -->
<div class="modal fade" id="assignMembershipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-vcard me-2"></i> Assign / Renew Membership</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="memberships.php">
            <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="assign_membership">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Member </label>
                            <select name="member_id" class="form-select" required>
                                <option value="">-- Choose Member --</option>
                                <?php while ($m = mysqli_fetch_assoc($members_dropdown)): ?>
                                    <option value="<?php echo $m['id']; ?>">[<?php echo $m['member_code']; ?>] <?php echo $m['first_name'] . ' ' . $m['last_name']; ?> (<?php echo $m['member_type']; ?>)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Membership Plan </label>
                            <select name="plan_id" id="plan_id" class="form-select" required>
                                <option value="">-- Choose Plan --</option>
                                <?php while ($p = mysqli_fetch_assoc($plans_dropdown)): ?>
                                    <option value="<?php echo $p['id']; ?>" data-price="<?php echo $p['price']; ?>"><?php echo $p['plan_name']; ?> - ₱<?php echo number_format($p['price'], 2); ?> (<?php echo $p['duration_days']; ?> Days)</option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Method </label>
                            <select name="payment_method" id="payment_method" class="form-select" required>
                                <option value="Cash">Cash</option>
                                <option value="GCash">GCash</option>
                            </select>
                        </div>

                        <!-- Cash: Amount Received / Change -->
                        <div class="col-md-6" id="amount_tendered_container">
                            <label class="form-label fw-semibold">Amount Received (₱) </label>
                            <input type="number" step="0.01" min="0" name="amount_tendered" id="amount_tendered" class="form-control" placeholder="e.g. 500.00">
                            <div class="form-text text-muted" id="plan_price_hint">Select a plan to see the price due.</div>
                        </div>
                        <div class="col-md-6" id="change_due_container">
                            <label class="form-label fw-semibold">Change Due (₱)</label>
                            <input type="text" id="change_due" class="form-control fw-bold text-success" value="0.00" readonly>
                        </div>

                        <!-- GCash QR Display Box -->
                        <div class="col-12 d-none" id="gcash_qr_container">
                            <div class="qr-code-box">
                                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-qr-code me-1 text-primary"></i> Scan GCash QR Code</h6>
                                <p class="text-muted small mb-2">Account: <strong><?php echo sanitize($gcash_name); ?></strong> (<?php echo sanitize($gcash_no); ?>)</p>
                                <div class="bg-light p-2 d-inline-block border rounded">
                                    <?php if ($gcash_qr && $gcash_qr !== 'gcash_qr_default.png' && file_exists("../uploads/gcash_qr/" . $gcash_qr)): ?>
                                        <img src="../uploads/gcash_qr/<?php echo sanitize($gcash_qr); ?>" alt="GCash QR Code" class="img-fluid" style="max-width: 180px;">
                                    <?php else: ?>
                                        <p class="text-danger small mb-0 p-3">No QR code uploaded yet. Go to <strong>System Settings</strong> to upload one.</p>
                                    <?php endif; ?>
                                </div>
                                <p class="text-muted small mt-2 mb-0">GCash payments are exact — no change is given.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4"><i class="bi bi-check-circle-fill me-1"></i> Process & Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var planSelect       = document.getElementById('plan_id');
    var paymentSelect    = document.getElementById('payment_method');
    var amountInput      = document.getElementById('amount_tendered');
    var changeInput      = document.getElementById('change_due');
    var priceHint        = document.getElementById('plan_price_hint');
    var amountContainer  = document.getElementById('amount_tendered_container');
    var changeContainer  = document.getElementById('change_due_container');
    var gcashQrBox       = document.getElementById('gcash_qr_container');

    function getPlanPrice() {
        if (!planSelect || planSelect.selectedIndex < 0) return 0;
        var opt = planSelect.options[planSelect.selectedIndex];
        var price = parseFloat(opt.getAttribute('data-price'));
        return isNaN(price) ? 0 : price;
    }

    function recalcChange() {
        var price = getPlanPrice();
        var tendered = parseFloat(amountInput.value);

        if (price > 0) {
            priceHint.textContent = 'Amount due: ₱' + price.toFixed(2);
        } else {
            priceHint.textContent = 'Select a plan to see the price due.';
        }

        if (!isNaN(tendered) && price > 0) {
            var change = tendered - price;
            changeInput.value = change >= 0 ? change.toFixed(2) : '0.00';
            changeInput.style.color = tendered < price ? '#dc3545' : '#198754';
        } else {
            changeInput.value = '0.00';
        }
    }

    function togglePaymentMethod() {
        if (paymentSelect.value === 'GCash') {
            gcashQrBox.classList.remove('d-none');

            amountContainer.classList.add('d-none');
            changeContainer.classList.add('d-none');
            amountInput.required = false;
        } else {
            gcashQrBox.classList.add('d-none');

            amountContainer.classList.remove('d-none');
            changeContainer.classList.remove('d-none');
            amountInput.required = true;
            recalcChange();
        }
    }

    if (planSelect)    planSelect.addEventListener('change', recalcChange);
    if (amountInput)   { amountInput.addEventListener('input', recalcChange); amountInput.addEventListener('keyup', recalcChange); }
    if (paymentSelect) paymentSelect.addEventListener('change', togglePaymentMethod);

    // Run once on load in case a plan/method is already selected
    togglePaymentMethod();
    recalcChange();
})();
</script>

<?php require_once '../includes/footer.php'; ?>