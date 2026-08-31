<?php
/**
 * ATZ Fitness Gym Management System
 * Database Connection & Global Configuration
 * PHP 8 Procedural MySQLi Setup
 */

// --------------------------------------------------------------------
// Production error handling: never show raw PHP errors/stack traces to
// the browser (they can leak file paths, SQL, or config). Real errors
// still go to the server's PHP error log via log_errors, so nothing is
// actually hidden from the developer — only from an outside visitor.
// To debug locally, temporarily set display_errors back to 1 in your
// own php.ini rather than editing this file.
// --------------------------------------------------------------------
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// --------------------------------------------------------------------
// Session hardening (must run BEFORE session_start()):
//   - HttpOnly: JavaScript can't read the session cookie (mitigates XSS
//     cookie theft).
//   - SameSite=Lax: the cookie isn't sent on most cross-site requests
//     (mitigates CSRF/session riding).
//   - Secure: only set automatically when the site is actually served
//     over HTTPS, so this doesn't break local HTTP development.
//   - A custom session name avoids advertising "this is PHP" via the
//     default PHPSESSID cookie name.
// --------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('atzfitness_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Idle session timeout: 30 minutes of inactivity on the login session
// logs the user out automatically. Applies once a user is actually
// logged in (unauthenticated visitors don't get bounced by this).
define('SESSION_IDLE_TIMEOUT', 1800); // seconds
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_IDLE_TIMEOUT) {
        $expired_user = $_SESSION['username'] ?? null;
        $expired_role = $_SESSION['role'] ?? null;
        $expired_id   = $_SESSION['user_id'] ?? null;
        session_unset();
        session_destroy();
        // Best-effort log — a fresh session/connection isn't open yet at
        // this point, so this is intentionally skipped; the next login
        // and prior actions remain in activity_logs regardless.
        $is_ajax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
        if (!$is_ajax) {
            header("Location: " . (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/staff/') !== false ? '../login.php' : 'login.php') . "?error=" . urlencode("Your session expired due to inactivity. Please log in again."));
            exit();
        }
    }
    $_SESSION['last_activity'] = time();
}

// --------------------------------------------------------------------
// Security response headers (sent on every page that loads db.php,
// i.e. virtually every page in the app).
// --------------------------------------------------------------------
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    // Bootstrap/Chart.js/jQuery/SweetAlert2/Inter font are all now
    // self-hosted under assets/vendor/, and a number of pages use small
    // inline <script> blocks, so a strict CSP with no 'unsafe-inline'
    // would break the UI. Since everything loads from this origin now,
    // the policy no longer needs to whitelist any external hosts.
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self' data:; frame-ancestors 'none'; object-src 'none'; base-uri 'self'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Without this, PHP's date()/time() fall back to the server's default
// timezone (often UTC), which is why check-in/out and walk-in times were
// showing hours off from the actual local time. Every page loads this
// file first, so setting it here fixes it everywhere at once.
date_default_timezone_set('Asia/Manila');

define('DB_HOST', 'sql101.infinityfree.com');
define('DB_USER', 'if0_42242608');
define('DB_PASS', 'JmfY7NpPTJJR5');
define('DB_NAME', 'if0_42242608_atz_fitness_db');

// Establish MySQLi Connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Database Connection Error: " . mysqli_connect_error());
}

mysqli_set_charset($conn, "utf8mb4");

// Keep MySQL's NOW()/CURDATE() (used for filtering today's attendance,
// walk-ins, and membership expiry) in sync with PHP's Asia/Manila clock
// above — otherwise the two can disagree on what "today" is, especially
// close to midnight.
mysqli_query($conn, "SET time_zone = '+08:00'");

/**
 * CSRF Protection Helpers
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    $submitted = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Security check failed (invalid or expired form token). Please go back, refresh the page, and try again.');
    }
}

/**
 * XSS Clean Input Helper
 */
function sanitize($data) {
    if (is_null($data)) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Activity Log Helper
 */
function log_activity($conn, $user_id, $username, $role, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = mysqli_prepare($conn, "INSERT INTO activity_logs (user_id, username, role, action, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isssss", $user_id, $username, $role, $action, $description, $ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Fetch System Setting Helper
 */
function get_setting($conn, $key, $default = '') {
    $stmt = mysqli_prepare($conn, "SELECT setting_value FROM settings WHERE setting_key = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $key);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            mysqli_stmt_close($stmt);
            return $row['setting_value'];
        }
        mysqli_stmt_close($stmt);
    }
    return $default;
}

/**
 * Member Validation Helpers
 */
function validate_member_age($birthdate) {
    $dob = new DateTime($birthdate);
    $today = new DateTime('today');
    $age = $dob->diff($today)->y;
    return ($age >= 13 && $age <= 80) ? $age : false;
}

function validate_letters_only($name) {
    return preg_match('/^[a-zA-Z\s\.\-\']+$/', $name);
}

// Same as validate_letters_only, but also requires the name start with a capital letter.
// Used for member first/last name so entries are stored consistently (e.g. "John", not "john").
function validate_capitalized_name($name) {
    return preg_match('/^[A-Z][a-zA-Z\s\.\-\']*$/', $name);
}

/**
 * Verify an uploaded file is actually an image of an allowed type
 * (checks real content via getimagesize, not just the filename extension).
 * PDFs are handled separately since getimagesize() doesn't cover them.
 */
function is_valid_image_upload($tmp_path, $allowed_ext = ['jpg', 'jpeg', 'png']) {
    $info = @getimagesize($tmp_path);
    if ($info === false) return false;
    $allowed_mimes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'
    ];
    foreach ($allowed_ext as $ext) {
        if (isset($allowed_mimes[$ext]) && $info['mime'] === $allowed_mimes[$ext]) {
            return true;
        }
    }
    return false;
}

/**
 * Verify an uploaded file with extension jpg/jpeg/png/pdf is genuinely
 * that type of file, checking real content, not just the filename.
 */
function is_valid_image_or_pdf_upload($tmp_path, $ext) {
    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        return is_valid_image_upload($tmp_path, [$ext]);
    }
    if ($ext === 'pdf') {
        $handle = @fopen($tmp_path, 'rb');
        if (!$handle) return false;
        $header = fread($handle, 5);
        fclose($handle);
        return $header === '%PDF-';
    }
    return false;
}

function validate_ph_contact($contact) {
    return preg_match('/^09\d{9}$/', $contact);
}

/**
 * Restrict member email registration to Gmail addresses only
 * (e.g. rejects typos like "name@gmail.comm" or other providers).
 */
function validate_gmail_email($email) {
    return preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/', trim($email));
}

/**
 * General email format check (any provider) — used where the account
 * isn't restricted to Gmail, e.g. Staff accounts.
 */
function validate_email_format($email) {
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Password strength check used by register.php, change_password.php,
 * and reset_password.php: at least 8 characters, with at least one
 * uppercase letter, one lowercase letter, one number, and one special
 * character (anything that isn't a letter or digit, e.g. !@#$%^&*).
 */
function validate_strong_password($password) {
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
}

/**
 * Brute-Force Protection (Login)
 * ------------------------------
 * After MAX_LOGIN_ATTEMPTS failed attempts for an account, that account
 * is locked for LOGIN_LOCKOUT_MINUTES. Tracked in users.failed_login_attempts
 * / users.locked_until so it survives across requests without needing a
 * separate attempts table.
 */
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

/**
 * Returns remaining lockout minutes (int > 0) if this username is
 * currently locked out, or 0 if it isn't (including "no such user" —
 * callers should not use this to infer whether an account exists).
 */
function get_login_lockout_minutes_remaining($conn, $username) {
    $stmt = mysqli_prepare($conn, "SELECT locked_until FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($row && !empty($row['locked_until'])) {
        $remaining = (strtotime($row['locked_until']) - time()) / 60;
        if ($remaining > 0) {
            return (int) ceil($remaining);
        }
    }
    return 0;
}

/**
 * Records a failed login attempt for a username and locks the account
 * once MAX_LOGIN_ATTEMPTS is reached. Silently does nothing if the
 * username doesn't exist (so this can't be used to enumerate accounts).
 */
function register_failed_login($conn, $username) {
    $stmt = mysqli_prepare($conn, "SELECT id, role, failed_login_attempts FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$user) return;

    $attempts = (int) $user['failed_login_attempts'] + 1;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $locked_until = date('Y-m-d H:i:s', time() + (LOGIN_LOCKOUT_MINUTES * 60));
        $upd = mysqli_prepare($conn, "UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "isi", $attempts, $locked_until, $user['id']);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
        log_activity($conn, $user['id'], $username, $user['role'], 'Account Locked', "Account locked for " . LOGIN_LOCKOUT_MINUTES . " minutes after {$attempts} failed login attempts.");
    } else {
        $upd = mysqli_prepare($conn, "UPDATE users SET failed_login_attempts = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "ii", $attempts, $user['id']);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
}

/**
 * Clears failed-login tracking for a username after a successful login.
 */
function reset_failed_login($conn, $username) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Verify Google reCAPTCHA response
 * 
 * @param string $recaptcha_response The g-recaptcha-response token from the form
 * @param string $secret_key The reCAPTCHA secret key
 * @return array ['success' => bool, 'error' => string|null]
 */
function verify_recaptcha($recaptcha_response, $secret_key) {
    if (empty($recaptcha_response)) {
        return [
            'success' => false,
            'error' => 'Please complete the reCAPTCHA verification.'
        ];
    }

    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';

    $post_data = [
        'secret'   => $secret_key,
        'response' => $recaptcha_response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    $ch = curl_init($verify_url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $verify_result = curl_exec($ch);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($verify_result === false || !empty($curl_error)) {
        return [
            'success' => false,
            'error' => 'Unable to verify reCAPTCHA. Please try again.'
        ];
    }

    $captcha_result = json_decode($verify_result, true);

    if (empty($captcha_result['success'])) {
        return [
            'success' => false,
            'error' => 'reCAPTCHA verification failed. Please try again.'
        ];
    }

    return [
        'success' => true,
        'error' => null
    ];
}

// Email sending (Forgot Password, etc.) — see includes/mailer.php for
// the Gmail SMTP configuration this depends on.
require_once __DIR__ . '/mailer.php';
?>