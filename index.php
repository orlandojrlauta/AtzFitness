<?php
/**
 * ATZ Fitness Gym Management System
 * Main Entry Point
 *
 * This file only determines where the user should go.
 * reCAPTCHA should be implemented in login.php or register.php,
 * where the actual form submission occurs.
 */

session_start();

/*
|--------------------------------------------------------------------------
| CHECK IF USER IS LOGGED IN
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    $role = $_SESSION['role'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | ADMINISTRATOR
    |--------------------------------------------------------------------------
    */

    if ($role === 'Administrator') {
        header('Location: admin/index.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | STAFF
    |--------------------------------------------------------------------------
    */

    if ($role === 'Staff') {
        header('Location: staff/index.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | INVALID / UNKNOWN ROLE
    |--------------------------------------------------------------------------
    */

    session_unset();
    session_destroy();

    header('Location: login.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| NOT LOGGED IN
|--------------------------------------------------------------------------
|
| Send the user to the login page.
|
*/

header('Location: login.php');
exit;