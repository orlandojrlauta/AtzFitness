<?php
/**
 * ATZ Fitness Gym Management System
 * Header Navigation Partial
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? sanitize($page_title) . " - ATZ Fitness" : "ATZ Fitness Gym Management System"; ?></title>
    <!-- Bootstrap 5 CSS (local) -->
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (local) -->
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <!-- SweetAlert2 CSS (local) -->
    <link href="../assets/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet">
    <!-- Custom Styles -->
    <link href="../assets/css/style.css?v=4" rel="stylesheet">
    <!-- jQuery (local) -->
    <script src="../assets/vendor/jquery/jquery.min.js"></script>
    <!-- Chart.js (local) -->
    <script src="../assets/vendor/chartjs/chart.js"></script>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark sticky-top shadow-sm">
    <div class="container-fluid d-flex align-items-center justify-content-between flex-nowrap px-2 px-md-3 py-2">

        <!-- Left: sidebar toggle (mobile only) + brand -->
        <div class="d-flex align-items-center gap-2 flex-shrink-1 min-w-0">
            <button class="btn btn-outline-light border-0 d-md-none px-2 flex-shrink-0" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu"
                    aria-label="Toggle navigation menu">
                <i class="bi bi-list fs-3 lh-1"></i>
            </button>
            <a class="navbar-brand d-flex align-items-center fw-bold text-warning mb-0 text-truncate" href="#">
                <img src="../assets/img/logo.jpg" alt="ATZ Fitness Logo" class="navbar-logo me-2 flex-shrink-0" width="38" height="38">
                <span class="d-none d-sm-inline fs-5 text-truncate">ATZ FITNESS</span>
            </a>
            <span class="navbar-text d-none d-lg-inline text-light opacity-75 small border-start border-secondary ps-3 ms-1 text-truncate">
                Gym Management System
            </span>
        </div>

        <!-- Right: role badge + user dropdown (always visible, compact) -->
        <div class="d-flex align-items-center gap-2 gap-md-3 flex-shrink-0 ms-2">
            <span class="badge bg-warning text-dark px-2 px-md-3 py-2 rounded-pill d-none d-sm-inline-flex align-items-center">
                <i class="bi bi-person-badge-fill me-1"></i>
                <?php echo sanitize($_SESSION['role'] ?? 'Staff'); ?>
            </span>
            <div class="dropdown">
                <a class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if (!empty($_SESSION['profile_picture']) && file_exists(__DIR__ . '/../uploads/profile/' . $_SESSION['profile_picture'])): ?>
                        <img src="../uploads/profile/<?php echo sanitize($_SESSION['profile_picture']); ?>" alt="Profile Photo" class="rounded-circle flex-shrink-0" style="width: 34px; height: 34px; object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 34px; height: 34px;">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    <?php endif; ?>
                    <span class="d-none d-md-inline ms-2 text-truncate" style="max-width: 140px;"><?php echo sanitize($_SESSION['full_name'] ?? 'User'); ?></span>
                </a>
                <?php
                    $__current_page = basename($_SERVER['PHP_SELF']);
                    // Same "active" look the sidebar uses: warning-colored pill, bold dark text.
                    $__dd_active = 'bg-warning text-dark fw-bold rounded';
                ?>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li class="dropdown-header d-md-none text-truncate"><?php echo sanitize($_SESSION['full_name'] ?? 'User'); ?></li>
                    <li class="d-md-none"><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item <?php echo $__current_page === 'profile.php' ? $__dd_active : ''; ?>" href="<?php echo ($_SESSION['role'] ?? '') === 'Administrator' ? '../admin/profile.php' : '../staff/profile.php'; ?>"><i class="bi bi-person-circle me-2 <?php echo $__current_page === 'profile.php' ? '' : 'text-warning'; ?>"></i> My Profile</a></li>
                    <?php if (($_SESSION['role'] ?? '') === 'Administrator'): ?>
                    <li><a class="dropdown-item <?php echo $__current_page === 'branding.php' ? $__dd_active : ''; ?>" href="../admin/branding.php"><i class="bi bi-building me-2 <?php echo $__current_page === 'branding.php' ? '' : 'text-warning'; ?>"></i> Gym Branding & Information</a></li>
                    <li><a class="dropdown-item <?php echo $__current_page === 'walkin_rate.php' ? $__dd_active : ''; ?>" href="../admin/walkin_rate.php"><i class="bi bi-cash-stack me-2 <?php echo $__current_page === 'walkin_rate.php' ? '' : 'text-primary'; ?>"></i> Walk-in Rate Settings</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item <?php echo $__current_page === 'change_password.php' ? $__dd_active : ''; ?>" href="../change_password.php"><i class="bi bi-key-fill me-2 <?php echo $__current_page === 'change_password.php' ? '' : 'text-primary'; ?>"></i> Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Log Out</a></li>
                </ul>
            </div>
        </div>

    </div>
</nav>

<div class="container-fluid">
    <div class="row">