<?php
/**
 * ATZ Fitness Gym Management System
 * System Settings & Admin GCash QR Upload Module
 */

$page_title = "System Settings";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_settings') {
    verify_csrf();
    // Update basic settings
    $settings_to_update = [
        'gym_name' => sanitize($_POST['gym_name'] ?? ''),
        'gym_tagline' => sanitize($_POST['gym_tagline'] ?? ''),
        'gym_address' => sanitize($_POST['gym_address'] ?? ''),
        'gym_contact' => sanitize($_POST['gym_contact'] ?? ''),
        'gym_email' => sanitize($_POST['gym_email'] ?? ''),
        'operating_hours' => sanitize($_POST['operating_hours'] ?? ''),
        'gcash_account_name' => sanitize($_POST['gcash_account_name'] ?? ''),
        'gcash_account_no' => sanitize($_POST['gcash_account_no'] ?? ''),
        'walkin_rate' => sanitize($_POST['walkin_rate'] ?? '150.00'),
    ];

    foreach ($settings_to_update as $key => $val) {
        $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        mysqli_stmt_bind_param($stmt, "sss", $key, $val, $val);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Handle Admin GCash QR Image Upload
    if (!empty($_FILES['gcash_qr_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['gcash_qr_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png']) && is_valid_image_upload($_FILES['gcash_qr_image']['tmp_name'])) {
            $qr_filename = "gcash_qr_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['gcash_qr_image']['tmp_name'], "../uploads/gcash_qr/" . $qr_filename)) {
                $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_key, setting_value) VALUES ('gcash_qr_image', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                mysqli_stmt_bind_param($stmt, "ss", $qr_filename, $qr_filename);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }

    log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Update Settings', "Updated gym settings and GCash parameters");

    $_SESSION['swal_title'] = "Success!";
    $_SESSION['swal_msg'] = "System settings and GCash QR updated successfully!";
    $_SESSION['swal_type'] = "success";

    header("Location: settings.php");
    exit();
}

// Update Admin's Own Profile (Name, Email, Contact, Profile Photo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_profile') {
    verify_csrf();
    $new_full_name = sanitize($_POST['profile_full_name'] ?? '');
    $new_email = sanitize($_POST['profile_email'] ?? '');
    $new_contact = sanitize($_POST['profile_contact'] ?? '');

    if (!empty($new_full_name) && !empty($new_email) && validate_gmail_email($new_email) && validate_ph_contact($new_contact)) {
        // Optional profile photo replacement
        $new_photo = null;
        if (!empty($_FILES['profile_picture']['name'])) {
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png']) && is_valid_image_upload($_FILES['profile_picture']['tmp_name'])) {
                $new_photo = "admin_" . $_SESSION['user_id'] . "_" . time() . "." . $ext;
                move_uploaded_file($_FILES['profile_picture']['tmp_name'], "../uploads/profile/" . $new_photo);
            }
        }

        if ($new_photo) {
            $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, contact_no = ?, profile_picture = ? WHERE id = ? AND role = 'Administrator'");
            mysqli_stmt_bind_param($stmt, "ssssi", $new_full_name, $new_email, $new_contact, $new_photo, $_SESSION['user_id']);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, contact_no = ? WHERE id = ? AND role = 'Administrator'");
            mysqli_stmt_bind_param($stmt, "sssi", $new_full_name, $new_email, $new_contact, $_SESSION['user_id']);
        }

        if (mysqli_stmt_execute($stmt)) {
            // Keep the session in sync so the new name/photo show immediately
            $_SESSION['full_name'] = $new_full_name;
            if ($new_photo) {
                $_SESSION['profile_picture'] = $new_photo;
            }

            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Update Profile', "Updated own profile details");

            $_SESSION['swal_title'] = "Success!";
            $_SESSION['swal_msg'] = "Your profile has been updated!";
            $_SESSION['swal_type'] = "success";
        }
        mysqli_stmt_close($stmt);
    } else if (!validate_gmail_email($new_email)) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Email address must be a valid @gmail.com address (e.g. juan@gmail.com).";
        $_SESSION['swal_type'] = "error";
    } else {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Please check your inputs. Contact number must be 11 digits starting with 09.";
        $_SESSION['swal_type'] = "error";
    }

    header("Location: settings.php");
    exit();
}

$gym_name = get_setting($conn, 'gym_name', 'ATZ FITNESS');
$gym_tagline = get_setting($conn, 'gym_tagline', 'Transform Your Body, Elevate Your Life');
$gym_address = get_setting($conn, 'gym_address', '123 Fitness Ave, Metro Manila, Philippines');
$gym_contact = get_setting($conn, 'gym_contact', '09171234567');
$gym_email = get_setting($conn, 'gym_email', 'contact@atzfitness.com');
$operating_hours = get_setting($conn, 'operating_hours', '6:00 AM - 10:00 PM (Mon-Sat)');
$gcash_account_name = get_setting($conn, 'gcash_account_name', 'ATZ FITNESS GYM');
$gcash_account_no = get_setting($conn, 'gcash_account_no', '09171234567');
$gcash_qr_image = get_setting($conn, 'gcash_qr_image', 'gcash_qr_default.png');
$walkin_rate = get_setting($conn, 'walkin_rate', '150.00');

// Fetch the admin's current profile details fresh from the database
$profile_stmt = mysqli_prepare($conn, "SELECT full_name, email, contact_no, profile_picture FROM users WHERE id = ?");
mysqli_stmt_bind_param($profile_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($profile_stmt);
$my_profile = mysqli_fetch_assoc(mysqli_stmt_get_result($profile_stmt));
mysqli_stmt_close($profile_stmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-gear-wide-connected text-warning me-2"></i> System Settings & GCash Configuration</h1>
</div>

<!-- Settings Tabs -->
<ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-profile-btn" data-bs-toggle="tab" data-bs-target="#tab-profile" type="button" role="tab" aria-controls="tab-profile" aria-selected="true">
            <i class="bi bi-person-circle me-1"></i> My Profile
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-branding-btn" data-bs-toggle="tab" data-bs-target="#tab-branding" type="button" role="tab" aria-controls="tab-branding" aria-selected="false">
            <i class="bi bi-building me-1"></i> Gym Branding & Information
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-gcash-btn" data-bs-toggle="tab" data-bs-target="#tab-gcash" type="button" role="tab" aria-controls="tab-gcash" aria-selected="false">
            <i class="bi bi-qr-code me-1"></i> Admin GCash QR Settings
        </button>
    </li>
</ul>

<div class="tab-content" id="settingsTabsContent">

    <!-- My Profile -->
    <div class="tab-pane fade show active" id="tab-profile" role="tabpanel" aria-labelledby="tab-profile-btn">
        <div class="card p-4">
            <form method="POST" action="settings.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-2 text-center">
                        <?php if (!empty($my_profile['profile_picture']) && file_exists("../uploads/profile/" . $my_profile['profile_picture'])): ?>
                            <img src="../uploads/profile/<?php echo sanitize($my_profile['profile_picture']); ?>" alt="Profile Photo" class="rounded-circle border mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 80px; height: 80px;">
                                <i class="bi bi-person-fill fs-2"></i>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="profile_picture" class="form-control form-control-sm" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="profile_full_name" class="form-control" value="<?php echo sanitize($my_profile['full_name']); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="profile_email" id="profile_email" class="form-control"
                               pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                               title="Enter a valid @gmail.com address (e.g. juan@gmail.com)."
                               data-gmail-only="true"
                               value="<?php echo sanitize($my_profile['email']); ?>" required>
                        <div class="invalid-feedback" id="profile_email_error">Email address must be a valid @gmail.com address (e.g. juan@gmail.com).</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Contact No. (09xxxxxxxx)</label>
                        <input type="text" name="profile_contact" id="profile_contact" class="form-control" maxlength="11"
                               inputmode="numeric" pattern="09[0-9]{9}"
                               title="Contact number must start with 09 and contain 11 digits."
                               value="<?php echo sanitize($my_profile['contact_no']); ?>" required>
                        <div class="invalid-feedback" id="profile_contact_error">Contact number must start with 09 and contain 11 digits (e.g. 09171234567).</div>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-outline-dark fw-bold"><i class="bi bi-save-fill me-1"></i> Update Profile</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Gym Branding & GCash share one form so saving one section never wipes the other -->
    <form method="POST" action="settings.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_settings">

        <!-- Gym Branding & Information -->
        <div class="tab-pane fade" id="tab-branding" role="tabpanel" aria-labelledby="tab-branding-btn">
            <div class="card p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Gym Name</label>
                        <input type="text" name="gym_name" class="form-control" value="<?php echo sanitize($gym_name); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tagline</label>
                        <input type="text" name="gym_tagline" class="form-control" value="<?php echo sanitize($gym_tagline); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Address</label>
                        <input type="text" name="gym_address" class="form-control" value="<?php echo sanitize($gym_address); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Phone</label>
                        <input type="text" name="gym_contact" class="form-control" value="<?php echo sanitize($gym_contact); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Email</label>
                        <input type="email" name="gym_email" class="form-control" value="<?php echo sanitize($gym_email); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Operating Hours</label>
                        <input type="text" name="operating_hours" class="form-control" value="<?php echo sanitize($operating_hours); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Standard Walk-in Rate (PHP)</label>
                        <input type="number" step="0.01" name="walkin_rate" class="form-control" value="<?php echo sanitize($walkin_rate); ?>" required>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark px-5 shadow-sm"><i class="bi bi-save-fill me-2"></i> Save All Settings</button>
                </div>
            </div>
        </div>

        <!-- Admin GCash QR Settings -->
        <div class="tab-pane fade" id="tab-gcash" role="tabpanel" aria-labelledby="tab-gcash-btn">
            <div class="card p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">GCash Account Name</label>
                        <input type="text" name="gcash_account_name" class="form-control" value="<?php echo sanitize($gcash_account_name); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">GCash Account Number</label>
                        <input type="text" name="gcash_account_no" class="form-control" value="<?php echo sanitize($gcash_account_no); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Upload New Admin GCash QR Code (PNG/JPG)</label>
                        <input type="file" name="gcash_qr_image" class="form-control" accept="image/png, image/jpeg">
                    </div>
                    <div class="col-md-6">
                        <div class="text-center p-3 bg-light rounded border">
                            <span class="small text-muted fw-bold d-block mb-2">Current Active GCash QR Code</span>
                            <?php if ($gcash_qr_image && $gcash_qr_image !== 'gcash_qr_default.png' && file_exists("../uploads/gcash_qr/" . $gcash_qr_image)): ?>
                                <img src="../uploads/gcash_qr/<?php echo sanitize($gcash_qr_image); ?>" alt="Current QR" class="img-fluid border rounded p-1 bg-white" style="max-height: 180px;">
                            <?php else: ?>
                                <div class="text-muted small py-4">
                                    <i class="bi bi-qr-code-scan fs-1 d-block mb-2"></i>
                                    No QR code uploaded yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark px-5 shadow-sm"><i class="bi bi-save-fill me-2"></i> Save All Settings</button>
                </div>
            </div>
        </div>
    </form>

</div>


<script>
(function() {
    // Activate the correct tab if the page was opened with a #tab-... hash
    // (e.g. from the header's "My Profile" dropdown link).
    var hash = window.location.hash;
    if (hash) {
        var targetBtn = document.querySelector('#settingsTabs [data-bs-target="' + hash + '"]');
        if (targetBtn && window.bootstrap && window.bootstrap.Tab) {
            new bootstrap.Tab(targetBtn).show();
        }
    }

    // Contact No: live validation, must start with 09 and have exactly 11 digits
    var contactInput = document.getElementById('profile_contact');
    if (contactInput) {
        function validateContact() {
            var val = contactInput.value.trim();
            var valid = /^09\d{9}$/.test(val);
            contactInput.classList.toggle('is-invalid', !valid && val.length > 0);
            contactInput.setCustomValidity(valid ? '' : 'Contact number must start with 09 and contain 11 digits.');
        }
        contactInput.addEventListener('input', validateContact);
        contactInput.addEventListener('blur', validateContact);
        validateContact(); // flag an already-bad stored value as soon as the page loads
    }

    // Email: live validation, must be a valid @gmail.com address
    var emailInput = document.getElementById('profile_email');
    if (emailInput) {
        function validateEmail() {
            var val = emailInput.value.trim();
            var valid = /^[a-zA-Z0-9._%+-]+@gmail\.com$/.test(val);
            emailInput.classList.toggle('is-invalid', !valid && val.length > 0);
            emailInput.setCustomValidity(valid ? '' : 'Enter a valid @gmail.com address.');
        }
        emailInput.addEventListener('input', validateEmail);
        emailInput.addEventListener('blur', validateEmail);
        validateEmail(); // flag an already-bad stored value as soon as the page loads
    }
})();
</script>

<?php require_once '../includes/footer.php'; ?>