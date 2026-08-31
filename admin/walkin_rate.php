<?php
/**
 * ATZ Fitness Gym Management System
 * Walk-in Rate Settings
 */

$page_title = "Walk-in Rate Settings";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_walkin_rate') {
    verify_csrf();
    $walkin_rate = sanitize($_POST['walkin_rate'] ?? '100.00');
    $rate_valid = is_numeric($walkin_rate) && (float) $walkin_rate > 0 && (float) $walkin_rate <= 100;

    if (!$rate_valid) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Walk-in Rate must be a valid amount greater than 0 and no more than 100.";
        $_SESSION['swal_type'] = "error";
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('walkin_rate', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        mysqli_stmt_bind_param($stmt, "ss", $walkin_rate, $walkin_rate);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Update Settings', "Updated standard walk-in rate");

        $_SESSION['swal_title'] = "Success!";
        $_SESSION['swal_msg'] = "Walk-in rate updated successfully!";
        $_SESSION['swal_type'] = "success";
    }

    header("Location: walkin_rate.php");
    exit();
}

$walkin_rate = get_setting($conn, 'walkin_rate', '100.00');

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-cash-stack text-warning me-2"></i> Walk-in Rate Settings</h1>
</div>

<form method="POST" action="walkin_rate.php">
<?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_walkin_rate">
    <div class="card p-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Standard Walk-in Rate (PHP)</label>
                <input type="number" step="0.01" min="0.01" max="100" name="walkin_rate" id="walkin_rate" class="form-control" value="<?php echo sanitize($walkin_rate); ?>" required>
                <div></div>
                <div class="form-text">This is the default rate charged for walk-in gym sessions.</div>
            </div>
        </div>
        <div class="text-end mt-4">
            <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark px-5 shadow-sm"><i class="bi bi-save-fill me-2"></i> Save Changes</button>
        </div>
    </div>
</form>

<script>
(function() {
    // Walk-in Rate: live validation, must be a positive number no greater than 100
    var rateInput = document.getElementById('walkin_rate');
    if (rateInput) {
        function validateRate(isBlur) {
            var raw = rateInput.value.trim();
            var val = parseFloat(raw);
            var valid = !isNaN(val) && val > 0 && val <= 100;
            var tooHigh = !isNaN(val) && val > 100;

            // Show an error the instant it goes over 100, or once the
            // field was left / has a non-empty invalid value.
            var showError = raw.length > 0 && !valid && (tooHigh || isBlur === true);

            rateInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                rateInput.setCustomValidity('');
            } else if (tooHigh) {
                rateInput.setCustomValidity('Walk-in Rate must not exceed 100.');
            } else {
                rateInput.setCustomValidity('Enter a valid amount greater than 0 and no more than 100.');
            }

            if (showError && !isBlur) {
                rateInput.reportValidity();
            }
        }
        rateInput.addEventListener('input', function() { validateRate(false); });
        rateInput.addEventListener('blur', function() { validateRate(true); });
        validateRate(true); // flag an already-bad stored value as soon as the page loads
    }

    // Final safety net: re-check on submit so an invalid value can never
    // slip through (e.g. pasted text, autofill).
    var rateForm = rateInput ? rateInput.closest('form') : null;
    if (rateForm) {
        rateForm.addEventListener('submit', function(e) {
            if (rateInput) validateRate(true);

            var firstInvalid = rateForm.querySelector('.is-invalid');
            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.reportValidity();
                firstInvalid.focus();
            }
        });
    }
})();
</script>

<?php require_once '../includes/footer.php'; ?>