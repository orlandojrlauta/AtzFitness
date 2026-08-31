<?php
/**
 * ATZ Fitness Gym Management System
 * Main Entry Point
 */

session_start();

// Check if the user is logged in
if (isset($_SESSION['user_id'])) {

    $role = $_SESSION['role'] ?? '';

    // Administrator
    if ($role === 'Administrator') {
        header('Location: admin/index.php');
        exit;
    }

    // Staff
    if ($role === 'Staff') {
        header('Location: staff/index.php');
        exit;
    }

    // Invalid or unknown role
    session_unset();
    session_destroy();

    header('Location: login.php');
    exit;
}

// Not logged in → Login page
header('Location: login.php');
exit;