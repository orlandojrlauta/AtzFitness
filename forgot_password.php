<?php
/**
 * ATZ Fitness Gym Management System
 * Forgot Password — Request Reset Link
 *
 * Shared by both Administrator and Staff accounts, since both log in
 * through the same `users` table / login.php. The user enters their
 * username or email; if it matches an Active account, a single-use,
 * time-limited reset link is emailed to the address on file.
 *
 * Security notes (Checklist 1.4):
 *   - The response message is IDENTICAL whether or not the account
 *     exists, so this page can't be used to check who has an account.
 *   - The reset token is cryptographically random, stored only as a
 *     SHA-256 hash (never in plaintext), single-use, and expires after
 *     30 minutes.
 *   - Any earlier unused tokens for the account are invalidated when a
 *     new one is requested.
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

define('RESET_TOKEN_MINUTES', 30);

$notice = '';
$error = '';

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
        // Simple per-session throttle: one request every 60 seconds, so the
        // form can't be used to hammer the mail server / DB.
        $now = time();
        if (!empty($_SESSION['last_reset_request']) && ($now - $_SESSION['last_reset_request']) < 60) {
            $error = "Please wait a moment before requesting another reset link.";
        } else {
            $identifier = sanitize($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            $error = "Please enter your username or email address.";
        } else {
            $_SESSION['last_reset_request'] = $now;

            $stmt = mysqli_prepare($conn, "SELECT id, username, full_name, email, status FROM users WHERE (username = ? OR email = ?) LIMIT 1");
            mysqli_stmt_bind_param($stmt, "ss", $identifier, $identifier);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($user && $user['status'] === 'Active' && !empty($user['email'])) {
                // Invalidate any earlier unused reset tokens for this account.
                $inv = mysqli_prepare($conn, "UPDATE password_reset_tokens SET used = 1 WHERE user_id = ? AND used = 0");
                mysqli_stmt_bind_param($inv, "i", $user['id']);
                mysqli_stmt_execute($inv);
                mysqli_stmt_close($inv);

                $raw_token   = bin2hex(random_bytes(32));
                $token_hash  = hash('sha256', $raw_token);
                $expires_at  = date('Y-m-d H:i:s', $now + (RESET_TOKEN_MINUTES * 60));
                $ip          = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

                $ins = mysqli_prepare($conn, "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, ip_address) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($ins, "isss", $user['id'], $token_hash, $expires_at, $ip);
                mysqli_stmt_execute($ins);
                mysqli_stmt_close($ins);

                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
                $reset_link = "$scheme://$host$base/reset_password.php?token=" . $raw_token;

                $subject = "ATZ Fitness - Password Reset Request";
                $html = "
                    <div style='font-family:Arial,sans-serif;max-width:520px;margin:auto;'>
                        <h2 style='color:#c1121f;'>ATZ Fitness</h2>
                        <p>Hi " . htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') . ",</p>
                        <p>We received a request to reset the password for your <strong>" . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . "</strong> account.</p>
                        <p><a href='" . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . "' style='background:#c1121f;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;'>Reset My Password</a></p>
                        <p>This link expires in " . RESET_TOKEN_MINUTES . " minutes and can only be used once.</p>
                        <p>If you didn't request this, you can safely ignore this email — your password will not be changed.</p>
                        <hr style='border:none;border-top:1px solid #eee;'>
                        <p style='color:#888;font-size:12px;'>ATZ Fitness Gym Management System</p>
                    </div>";

                send_email($user['email'], $user['full_name'], $subject, $html);

                log_activity($conn, $user['id'], $user['username'], $user['role'] ?? 'N/A', 'Password Reset Request', 'A password reset link was requested.');
            }

            // Identical message whether or not the account/email matched.
            $notice = "If an account matches that username or email, a password reset link has been sent to the address on file. The link expires in " . RESET_TOKEN_MINUTES . " minutes.";
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
    <title>ATZ Fitness - Forgot Password</title>
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
        <p class="text-white-50 mb-0 small">Forgot Password</p>
    </div>
    <div class="p-4 p-md-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?php echo sanitize($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($notice)): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-envelope-check-fill me-2 fs-5"></i>
                <div><?php echo sanitize($notice); ?></div>
            </div>
            <a href="login.php" class="btn btn-dark w-100 fw-bold py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Back to Login
            </a>
        <?php else: ?>
            <p class="text-muted small">Enter the username or email address on your Admin or Staff account. We'll email you a link to reset your password.</p>
            <form method="POST" action="forgot_password.php">
                <?php echo csrf_field(); ?>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                        <input type="text" name="identifier" class="form-control" placeholder="Enter username or email" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm text-dark fs-5">
                    <i class="bi bi-send-fill me-1"></i> Send Reset Link
                </button>
            </form>
            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none small">
                    <i class="bi bi-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
