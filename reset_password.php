<?php
/**
 * ATZ Fitness Gym Management System
 * Reset Password — Consume Reset Link
 *
 * Works for both Administrator and Staff accounts. The raw token from
 * the emailed link is only ever compared as a SHA-256 hash against what
 * was stored (the plaintext token is never persisted in the database).
 */

require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'Administrator' ? 'admin/index.php' : 'staff/index.php'));
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

/**
 * Looks up a still-valid (unused, unexpired) reset token row + owning user.
 */
function find_valid_reset_token($conn, $raw_token) {
    if (empty($raw_token)) return null;
    $token_hash = hash('sha256', $raw_token);

    $stmt = mysqli_prepare($conn, "SELECT t.id as token_id, t.expires_at, t.used, u.id as user_id, u.username, u.full_name, u.email, u.role, u.status
        FROM password_reset_tokens t
        JOIN users u ON u.id = t.user_id
        WHERE t.token_hash = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $token_hash);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$row) return null;
    if ((int)$row['used'] === 1) return null;
    if (strtotime($row['expires_at']) < time()) return null;
    if ($row['status'] !== 'Active') return null;

    return $row;
}

$raw_token = $_GET['token'] ?? $_POST['token'] ?? '';
$token_row = find_valid_reset_token($conn, $raw_token);
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
    } else if (!$token_row) {
        $error = "This reset link is invalid or has expired. Please request a new one.";
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in both password fields.";
        } else if ($new_password !== $confirm_password) {
            $error = "New password and confirmation do not match.";
        } else if (!validate_strong_password($new_password)) {
            $error = "Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.";
        } else {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);

            $upd = mysqli_prepare($conn, "UPDATE users SET password = ?, force_password_change = 0, failed_login_attempts = 0, locked_until = NULL WHERE id = ?");
            mysqli_stmt_bind_param($upd, "si", $hashed, $token_row['user_id']);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            // Single-use: mark this token used, and invalidate any other
            // outstanding tokens for the same account.
            $inv = mysqli_prepare($conn, "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
            mysqli_stmt_bind_param($inv, "i", $token_row['user_id']);
            mysqli_stmt_execute($inv);
            mysqli_stmt_close($inv);

            log_activity($conn, $token_row['user_id'], $token_row['username'], $token_row['role'], 'Password Reset', 'Password was reset via the Forgot Password link.');

            // Notify the account holder their password changed, in case
            // the reset wasn't actually them.
            $subject = "ATZ Fitness - Your Password Was Changed";
            $html = "
                <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
                    <h2 style='color:#c1121f;'>ATZ Fitness</h2>
                    <p>Hi " . htmlspecialchars($token_row['full_name'], ENT_QUOTES, 'UTF-8') . ",</p>
                    <p>This confirms the password for your <strong>" . htmlspecialchars($token_row['username'], ENT_QUOTES, 'UTF-8') . "</strong> account was just changed via the Forgot Password link.</p>
                    <p>If you did not do this, please contact your gym administrator immediately.</p>
                    <hr style='border:none;border-top:1px solid #eee;'>
                    <p style='color:#888;font-size:12px;'>ATZ Fitness Gym Management System</p>
                </div>";
            send_email($token_row['email'], $token_row['full_name'], $subject, $html);

            $success = "Your password has been reset successfully. You can now log in.";
            $token_row = null; // hide the form now that it's used
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATZ Fitness - Reset Password</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css?v=4" rel="stylesheet">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

<div class="auth-shell">
<div class="login-card">
    <div class="login-header">
        <img src="assets/img/logo.jpg" alt="ATZ Fitness Logo" class="login-logo mb-2" width="80" height="80">
        <h3 class="fw-bold mt-2 text-warning">ATZ FITNESS</h3>
        <p class="text-white-50 mb-0 small">Reset Password</p>
    </div>
    <div class="p-4 p-md-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?php echo sanitize($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?php echo sanitize($success); ?></div>
            </div>
            <a href="login.php" class="btn btn-dark w-100 fw-bold py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Back to Login
            </a>
        <?php elseif (!$token_row): ?>
            <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div>This reset link is invalid, expired, or already used.</div>
            </div>
            <a href="forgot_password.php" class="btn btn-warning w-100 fw-bold text-dark py-2">
                <i class="bi bi-arrow-repeat me-1"></i> Request a New Link
            </a>
        <?php else: ?>
            <p class="text-muted small">Resetting password for <strong><?php echo sanitize($token_row['username']); ?></strong>.</p>
            <form method="POST" action="reset_password.php">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo sanitize($raw_token); ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" id="resetNewPassword" class="form-control" minlength="8"
                               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                               title="At least 8 characters, with an uppercase letter, a lowercase letter, a number, and a special character."
                               placeholder="At least 8 characters" required>
                        <span class="input-group-text bg-light" role="button" id="toggleResetNewPassword" style="cursor:pointer;">
                            <i class="bi bi-eye-slash" id="toggleResetNewPasswordIcon"></i>
                        </span>
                    </div>
                    <div class="form-text">Must include an uppercase letter, a lowercase letter, a number, and a special character (e.g. ! @ # $ %).</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="resetConfirmPassword" class="form-control" minlength="8" placeholder="Re-enter password" required>
                        <span class="input-group-text bg-light" role="button" id="toggleResetConfirmPassword" style="cursor:pointer;">
                            <i class="bi bi-eye-slash" id="toggleResetConfirmPasswordIcon"></i>
                        </span>
                    </div>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm text-dark fs-5">
                    <i class="bi bi-check-circle me-1"></i> Reset Password
                </button>
            </form>
        <?php endif; ?>
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
    wireToggle('toggleResetNewPassword', 'resetNewPassword', 'toggleResetNewPasswordIcon');
    wireToggle('toggleResetConfirmPassword', 'resetConfirmPassword', 'toggleResetConfirmPasswordIcon');
})();
</script>
</body>
</html>