<?php
/**
 * ATZ Fitness Gym Management System
 * Change Password Page
 */

require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| GOOGLE reCAPTCHA SETTINGS
|--------------------------------------------------------------------------
| Replace these with your actual Google reCAPTCHA keys.
|
| Site Key   = used in the HTML form
| Secret Key = used for server-side verification
|
*/
define('RECAPTCHA_SITE_KEY', 'YOUR_RECAPTCHA_SITE_KEY');
define('RECAPTCHA_SECRET_KEY', 'YOUR_RECAPTCHA_SECRET_KEY');

$reason = $_GET['reason'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    
    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA Verification
    |--------------------------------------------------------------------------
    */
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $recaptcha_result = verify_recaptcha($recaptcha_response, RECAPTCHA_SECRET_KEY);
    
    if (!$recaptcha_result['success']) {
        $error = $recaptcha_result['error'];
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "All fields are required.";
        } else if ($new_password !== $confirm_password) {
            $error = "New password and confirmation password do not match.";
        } else if (!validate_strong_password($new_password)) {
            $error = "New password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.";
        } else if ($new_password === $current_password) {
            $error = "New password must be different from your current password.";
        } else {
        $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $u = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if ($u && password_verify($current_password, $u['password'])) {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, force_password_change = 0 WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "si", $hashed, $_SESSION['user_id']);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);

            $_SESSION['force_password_change'] = 0;
            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Password Change', 'User changed password');

            $_SESSION['swal_title'] = "Success!";
            $_SESSION['swal_msg'] = "Password changed successfully!";
            $_SESSION['swal_type'] = "success";

            if ($_SESSION['role'] === 'Administrator') {
                header("Location: admin/index.php");
            } else {
                header("Location: staff/index.php");
            }
            exit();
        } else {
            $error = "Incorrect current password.";
        }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - ATZ Fitness</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css?v=4" rel="stylesheet">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body class="auth-shell">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow border-0">
                <div class="card-header bg-dark text-warning p-3 text-center">
                    <h4 class="fw-bold mb-0"><i class="bi bi-shield-lock-fill me-2"></i> Security Update</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($reason === 'forced'): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            For security reasons, you must change your initial password before proceeding.
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo sanitize($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="change_password.php">
                    <?php echo csrf_field(); ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="current_password" id="curPassword" class="form-control" required>
                                <span class="input-group-text bg-light" role="button" id="toggleCurPassword" style="cursor:pointer;">
                                    <i class="bi bi-eye-slash" id="toggleCurPasswordIcon"></i>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newPassword" class="form-control" minlength="8"
                                       pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                                       title="At least 8 characters, with an uppercase letter, a lowercase letter, a number, and a special character."
                                       required>
                                <span class="input-group-text bg-light" role="button" id="toggleNewPassword" style="cursor:pointer;">
                                    <i class="bi bi-eye-slash" id="toggleNewPasswordIcon"></i>
                                </span>
                            </div>
                            <div class="form-text">At least 8 characters, with an uppercase letter, a lowercase letter, a number, and a special character (e.g. ! @ # $ %).</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirmNewPassword" class="form-control" required>
                                <span class="input-group-text bg-light" role="button" id="toggleConfirmNewPassword" style="cursor:pointer;">
                                    <i class="bi bi-eye-slash" id="toggleConfirmNewPasswordIcon"></i>
                                </span>
                            </div>
                        </div>
                        <!-- GOOGLE reCAPTCHA -->
                        <div class="mb-4">
                            <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold text-dark py-2">
                            <i class="bi bi-check-circle me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    function wireToggle(toggleId, inputId, iconId) {
        var toggle = document.getElementById(toggleId);
        var input = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        if (toggle && input && icon) {
            toggle.addEventListener('click', function() {
                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('bi-eye-slash', !isPassword);
                icon.classList.toggle('bi-eye', isPassword);
            });
        }
    }
    wireToggle('toggleCurPassword', 'curPassword', 'toggleCurPasswordIcon');
    wireToggle('toggleNewPassword', 'newPassword', 'toggleNewPasswordIcon');
    wireToggle('toggleConfirmNewPassword', 'confirmNewPassword', 'toggleConfirmNewPasswordIcon');
})();
</script>
</body>
</html>