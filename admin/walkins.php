<?php
/**
 * ATZ Fitness Gym Management System
 * Walk-in Customers Module
 */

$page_title = "Walk-in Guests";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

$action = $_POST['action'] ?? '';
$default_rate = get_setting($conn, 'walkin_rate', '150.00');

// Record Walk-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'record_walkin') {
    verify_csrf();
    $guest_name = sanitize($_POST['guest_name'] ?? '');
    $guest_name = trim(preg_replace('/\s+/', ' ', $guest_name));
    $contact_no = '';
    $rate = floatval($_POST['rate'] ?? $default_rate);
    $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
    $gcash_ref_no = sanitize($_POST['gcash_ref_no'] ?? '');
    $amount_tendered_input = $_POST['amount_tendered'] ?? '';

    if (!empty($guest_name) && !validate_capitalized_name($guest_name)) {
        $_SESSION['swal_title'] = "Validation Error!";
        $_SESSION['swal_msg'] = "Guest name must start with a capital letter and can only contain letters, spaces, and . - ' characters (no numbers or symbols).";
        $_SESSION['swal_type'] = "error";
        header("Location: walkins.php");
        exit();
    }

    if (!empty($guest_name) && $rate > 0) {
        // Determine amount tendered & change (Cash only — GCash is always exact, no change)
        if ($payment_method === 'Cash') {
            $amount_tendered = is_numeric($amount_tendered_input) ? floatval($amount_tendered_input) : 0;
            if ($amount_tendered < $rate) {
                $amount_tendered = $rate;
            }
        } else {
            $amount_tendered = $rate; // GCash: exact amount, no change
        }
        $change_amount = round($amount_tendered - $rate, 2);

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');

        $stmt = mysqli_prepare($conn, "INSERT INTO walkin_customers (guest_name, contact_no, rate, payment_method, gcash_ref_no, check_in_time, date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssdssss", $guest_name, $contact_no, $rate, $payment_method, $gcash_ref_no, $now, $today);

        if (mysqli_stmt_execute($stmt)) {
            $walkin_id = mysqli_insert_id($conn);

            // Insert Payment
            $tx_no = "TXN-WALK-" . time() . "-" . rand(100, 999);
            $processed_by = $_SESSION['user_id'];

            $pay_stmt = mysqli_prepare($conn, "INSERT INTO payments (transaction_no, walkin_id, amount, amount_tendered, change_amount, payment_method, gcash_ref_no, payment_for, status, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?, 'Walk-in', 'Paid', ?)");
            mysqli_stmt_bind_param($pay_stmt, "sidddssi", $tx_no, $walkin_id, $rate, $amount_tendered, $change_amount, $payment_method, $gcash_ref_no, $processed_by);
            mysqli_stmt_execute($pay_stmt);
            mysqli_stmt_close($pay_stmt);

            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Walk-in Entry', "Registered walk-in guest: {$guest_name} (₱{$rate})");

            $_SESSION['receipt_txn'] = $tx_no;
            $_SESSION['swal_title'] = "Success!";
            $_SESSION['swal_msg'] = "Walk-in guest successfully logged with Receipt TXN: " . $tx_no;
            if ($payment_method === 'Cash' && $change_amount > 0) {
                $_SESSION['swal_msg'] .= " — Change Due: ₱" . number_format($change_amount, 2);
            }
            $_SESSION['swal_type'] = "success";
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['swal_title'] = "Validation Error!";
        $_SESSION['swal_msg'] = "Please check inputs. Guest name and a valid rate are required.";
        $_SESSION['swal_type'] = "error";
    }
    header("Location: walkins.php");
    exit();
}

// Fetch Admin GCash QR setting
$gcash_qr = get_setting($conn, 'gcash_qr_image', 'gcash_qr_default.png');
$gcash_name = get_setting($conn, 'gcash_account_name', 'ATZ FITNESS GYM');
$gcash_no = get_setting($conn, 'gcash_account_no', '09171234567');

// Fetch Walk-ins for selected date (defaults to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    $selected_date = date('Y-m-d');
}
$is_today = ($selected_date === date('Y-m-d'));

$w_stmt = mysqli_prepare($conn, "SELECT w.*, pay.transaction_no FROM walkin_customers w LEFT JOIN payments pay ON pay.walkin_id = w.id WHERE w.date = ? ORDER BY w.id DESC");
mysqli_stmt_bind_param($w_stmt, "s", $selected_date);
mysqli_stmt_execute($w_stmt);
$walkins_res = mysqli_stmt_get_result($w_stmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-person-walking text-warning me-2"></i> Walk-in Guests Management</h1>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="walkins.php" class="d-flex align-items-center gap-2">
            <label class="fw-semibold small text-muted mb-0">Viewing:</label>
            <input type="date" name="date" class="form-control form-control-sm" value="<?php echo sanitize($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" onchange="this.form.submit()">
            <?php if (!$is_today): ?>
                <a href="walkins.php" class="btn btn-sm btn-outline-dark">Today</a>
            <?php endif; ?>
        </form>
        <button class="btn btn-warning text-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addWalkinModal">
            <i class="bi bi-plus-circle-fill me-1"></i> Register Walk-in Guest
        </button>
    </div>
</div>

<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Guest Name</th>
                    <th>Rate Paid</th>
                    <th>Payment Method</th>
                    <th>Check-in Time</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($walkins_res) > 0): ?>
                    <?php while ($w = mysqli_fetch_assoc($walkins_res)): ?>
                        <tr>
                            <td class="fw-bold text-dark"><?php echo sanitize($w['guest_name']); ?></td>
                            <td class="fw-bold text-success">₱<?php echo number_format($w['rate'], 2); ?></td>
                            <td><span class="badge bg-secondary"><?php echo sanitize($w['payment_method']); ?></span></td>
                            <td class="small"><?php echo date('h:i A', strtotime($w['check_in_time'])); ?></td>
                            <td>
                                <?php if (!empty($w['transaction_no'])): ?>
                                    <a href="receipt.php?txn=<?php echo urlencode($w['transaction_no']); ?>" target="_blank" class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-receipt"></i> View
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No walk-in guests registered on this date.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Walk-in Modal -->
<div class="modal fade" id="addWalkinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-walking me-2"></i> Register Walk-in Guest</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="walkins.php" data-validate="true">
            <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="record_walkin">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Guest Full Name </label>
                            <input type="text" name="guest_name" id="guest_name" class="form-control" pattern="[A-Z][A-Za-z\s\.\-']*" minlength="2" maxlength="100" title="Must start with a capital letter — letters, spaces, and . - ' only." required>
                            <div></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Daily Pass Fee (PHP)</label>
                            <input type="text" class="form-control bg-light fw-bold" value="₱<?php echo number_format((float)$default_rate, 2); ?>" readonly tabindex="-1">
                            <input type="hidden" name="rate" id="rate" value="<?php echo $default_rate; ?>">
                            
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
                            <input type="number" step="0.01" min="0" name="amount_tendered" id="amount_tendered" class="form-control" placeholder="e.g. 100.00">
                            <div class="form-text text-muted" id="rate_price_hint">Amount due: ₱<?php echo number_format((float)$default_rate, 2); ?></div>
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
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4"><i class="bi bi-check-circle-fill me-1"></i> Register & Collect Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var rateInput        = document.getElementById('rate');
    var paymentSelect    = document.getElementById('payment_method');
    var amountInput      = document.getElementById('amount_tendered');
    var changeInput      = document.getElementById('change_due');
    var priceHint        = document.getElementById('rate_price_hint');
    var amountContainer  = document.getElementById('amount_tendered_container');
    var changeContainer  = document.getElementById('change_due_container');
    var gcashQrBox       = document.getElementById('gcash_qr_container');
    var guestNameInput   = document.getElementById('guest_name');

    // Guest Name: live validation (same approach used in members.php) —
    // is-invalid toggle, setCustomValidity() message, and reportValidity()
    // while typing (never on blur, so the field doesn't refocus itself
    // and trap the user).
    var guestNameInvalidCharPattern = /[^A-Za-z\s.]/;

    function validateGuestName(isBlur) {
        var val = guestNameInput.value.trim();
        var valid = /^[A-Z][A-Za-z\s.]{1,99}$/.test(val);
        var startsLower = val.length > 0 && /^[a-z]/.test(val);
        var hasInvalidChar = guestNameInvalidCharPattern.test(val);

        var showError = val.length > 0 && !valid && (hasInvalidChar || val.length > 4 || isBlur === true);

        guestNameInput.classList.toggle('is-invalid', showError);

        if (!showError) {
            guestNameInput.setCustomValidity('');
        } else if (hasInvalidChar) {
            guestNameInput.setCustomValidity("Only letters, spaces, and . are allowed — no numbers or symbols.");
        } else if (startsLower) {
            guestNameInput.setCustomValidity('Must start with an uppercase letter.');
        } else {
            guestNameInput.setCustomValidity('Enter a valid name of more than one character, using letters only.');
        }

        if (showError && !isBlur) {
            guestNameInput.reportValidity();
        }

        return valid;
    }

    if (guestNameInput) {
        guestNameInput.addEventListener('input', function() { validateGuestName(false); });
        guestNameInput.addEventListener('blur', function() { validateGuestName(true); });
    }

    function getRate() {
        var price = parseFloat(rateInput.value);
        return isNaN(price) ? 0 : price;
    }

    function recalcChange() {
        var price = getRate();
        var tendered = parseFloat(amountInput.value);

        priceHint.textContent = price > 0 ? ('Amount due: ₱' + price.toFixed(2)) : 'Enter the daily pass fee.';

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

    if (rateInput)      { rateInput.addEventListener('input', recalcChange); }
    if (amountInput)    { amountInput.addEventListener('input', recalcChange); amountInput.addEventListener('keyup', recalcChange); }
    if (paymentSelect)  paymentSelect.addEventListener('change', togglePaymentMethod);

    togglePaymentMethod();

    // Final safety net: re-check everything on submit so an incomplete or
    // invalid entry (empty field, pasted text, autofill, etc.) can never
    // slip through — nothing saves until every field is actually filled
    // in and valid.
    var addWalkinForm = document.querySelector('#addWalkinModal form');
    if (addWalkinForm) {
        addWalkinForm.addEventListener('submit', function(e) {
            if (guestNameInput) guestNameInput.dispatchEvent(new Event('blur'));

            var firstInvalid = addWalkinForm.querySelector('.is-invalid');
            if (!addWalkinForm.checkValidity() || firstInvalid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var target = firstInvalid || addWalkinForm.querySelector(':invalid');
                if (target) {
                    target.reportValidity();
                    target.focus();
                }
            }
        });
    }
})();
</script>

<?php require_once '../includes/footer.php'; ?>