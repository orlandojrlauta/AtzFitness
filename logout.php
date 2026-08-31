<?php
/**
 * ATZ Fitness Gym Management System
 * Logout Script
 */

require_once 'includes/db.php';

// ---- Log user activity before destroying the session ----
if (isset($_SESSION['user_id'])) {
    log_activity(
        $conn,
        $_SESSION['user_id'],
        $_SESSION['username'] ?? '',
        $_SESSION['role'] ?? '',
        'User Logout',
        'User logged out of the system'
    );
}

// ---- Remove all session data ----
$_SESSION = [];

// ---- Delete the session cookie itself ----
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// ---- Destroy the session on the server ----
session_destroy();

// ---- Redirect to login page ----
header("Location: login.php?msg=" . urlencode("Logged out successfully."));
exit;
?>