<?php
/**
 * ATZ Fitness Gym Management System
 * Authentication & Authorization Middleware
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: ../login.php?error=" . urlencode("Please log in to access the system."));
    exit();
}

// Check forced password change requirement
$current_page = basename($_SERVER['PHP_SELF']);
if (isset($_SESSION['force_password_change']) && $_SESSION['force_password_change'] == 1 && $current_page !== 'change_password.php') {
    header("Location: ../change_password.php?reason=forced");
    exit();
}

/**
 * Require specific role access
 */
function require_role($allowed_roles = ['Administrator', 'Staff']) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: ../login.php?error=" . urlencode("Unauthorized access attempt."));
        exit();
    }
}

/**
 * Require Administrator role ONLY
 */
function require_admin() {
    require_role(['Administrator']);
}
?>
