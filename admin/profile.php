<?php
/**
 * ATZ Fitness Gym Management System
 * My Profile + Admin GCash QR Settings
 */

$page_title = "My Profile";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$action = $_POST['action'] ?? '';

// Update Admin's Own Profile (Name, Email, Contact, Profile Photo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_profile') {
    verify_csrf();
    $new_full_name = sanitize($_POST['profile_full_name'] ?? '');
    $new_email = sanitize($_POST['profile_email'] ?? '');
    $new_contact = sanitize($_POST['profile_contact'] ?? '');

    if (!validate_capitalized_name($new_full_name) || strlen($new_full_name) > 100) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Full name must start with a capital letter, contain letters only, and be at most 100 characters.";
        $_SESSION['swal_type'] = "error";
    } else if (!validate_gmail_email($new_email)) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Email address must be a valid @gmail.com address (e.g. juan@gmail.com).";
        $_SESSION['swal_type'] = "error";
    } else if (!validate_ph_contact($new_contact)) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Contact number must start with 09 and contain 11 digits.";
        $_SESSION['swal_type'] = "error";
    } else {
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
        } else {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "Something went wrong. Please try again.";
            $_SESSION['swal_type'] = "error";
        }
        mysqli_stmt_close($stmt);
    }

    header("Location: profile.php");
    exit();
}

// Update Admin GCash QR Settings (Account Name/Number, QR Image)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_gcash') {
    verify_csrf();
    $gcash_account_name = sanitize($_POST['gcash_account_name'] ?? '');
    $gcash_account_no = sanitize($_POST['gcash_account_no'] ?? '');

    if ($gcash_account_name === '') {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "GCash Account Name is required.";
        $_SESSION['swal_type'] = "error";
    } else if (strlen($gcash_account_name) > 100) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "GCash Account Name must be at most 100 characters.";
        $_SESSION['swal_type'] = "error";
    } else if (!validate_ph_contact($gcash_account_no)) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "GCash Account Number must start with 09 and contain 11 digits.";
        $_SESSION['swal_type'] = "error";
    } else {
        $settings_to_update = [
            'gcash_account_name' => $gcash_account_name,
            'gcash_account_no' => $gcash_account_no,
        ];

        foreach ($settings_to_update as $key => $val) {
            $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            mysqli_stmt_bind_param($stmt, "sss", $key, $val, $val);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

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

        log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Update Settings', "Updated Admin GCash QR settings");

        $_SESSION['swal_title'] = "Success!";
        $_SESSION['swal_msg'] = "Admin GCash QR settings updated successfully!";
        $_SESSION['swal_type'] = "success";
    }

    header("Location: profile.php#gcash");
    exit();
}

// Fetch the admin's current profile details fresh from the database
$profile_stmt = mysqli_prepare($conn, "SELECT full_name, email, contact_no, profile_picture FROM users WHERE id = ?");
mysqli_stmt_bind_param($profile_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($profile_stmt);
$my_profile = mysqli_fetch_assoc(mysqli_stmt_get_result($profile_stmt));
mysqli_stmt_close($profile_stmt);

$gcash_account_name = get_setting($conn, 'gcash_account_name', 'ATZ FITNESS GYM');
$gcash_account_no = get_setting($conn, 'gcash_account_no', '09171234567');
$gcash_qr_image = get_setting($conn, 'gcash_qr_image', 'gcash_qr_default.png');

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-person-circle text-warning me-2"></i> My Profile</h1>
</div>

<!-- My Profile -->
<div class="card p-4 mb-4">
    <h5 class="fw-bold mb-3"><i class="bi bi-person-vcard me-2 text-warning"></i>Account Details</h5>
    <form method="POST" action="profile.php" enctype="multipart/form-data">
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
                <input type="text" name="profile_full_name" id="profile_full_name" class="form-control"
                       pattern="[A-Z][A-Za-z\s\.\-']*" minlength="2" maxlength="100"
                       title="Must start with a capital letter — letters, spaces, and . - ' only."
                       value="<?php echo sanitize($my_profile['full_name']); ?>" required>
                <div></div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Email Address</label>
                <input type="email" name="profile_email" id="profile_email" class="form-control"
                       pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                       title="Enter a valid @gmail.com address (e.g. juan@gmail.com)."
                       data-gmail-only="true"
                       value="<?php echo sanitize($my_profile['email']); ?>" required>
                <div></div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Contact No.</label>
                <input type="text" name="profile_contact" id="profile_contact" class="form-control" maxlength="11"
                       inputmode="numeric" pattern="09[0-9]{9}"
                       title="Contact number must start with 09 and contain 11 digits."
                       value="<?php echo sanitize($my_profile['contact_no']); ?>" required>
                <div></div>
            </div>
        </div>
        <div class="text-end mt-3">
            <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark px-5 shadow-sm"><i class="bi bi-save-fill me-2"></i> Update Profile</button>
        </div>
    </form>
</div>

<!-- Admin GCash QR Settings -->
<div class="card p-4" id="gcash">
    <h5 class="fw-bold mb-3"><i class="bi bi-qr-code me-2 text-warning"></i>Admin GCash QR Settings</h5>
    <form method="POST" action="profile.php" enctype="multipart/form-data">
    <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="save_gcash">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">GCash Account Name</label>
                <input type="text" name="gcash_account_name" id="gcash_account_name" class="form-control" maxlength="100" value="<?php echo sanitize($gcash_account_name); ?>" required>
                <div class="invalid-feedback" id="gcash_account_name_error">GCash Account Name is required.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">GCash Account Number</label>
                <input type="text" name="gcash_account_no" id="gcash_account_no" class="form-control" maxlength="11"
                       inputmode="numeric" pattern="09[0-9]{9}"
                       title="Account number must start with 09 and contain 11 digits."
                       value="<?php echo sanitize($gcash_account_no); ?>" required>
                <div class="invalid-feedback" id="gcash_account_no_error">Account number must start with 09 and contain 11 digits (e.g. 09171234567).</div>
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
            <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark px-5 shadow-sm"><i class="bi bi-save-fill me-2"></i> Save GCash Settings</button>
        </div>
    </form>
</div>

<script>
(function() {
    // Full Name: live validation, must start with a capital letter and
    // contain letters only (spaces, . - ' allowed as separators), max 100 chars.
    var nameInput = document.getElementById('profile_full_name');
    if (nameInput) {
        // Matches any character that is NOT allowed anywhere in the name
        // (letters, spaces, dot, hyphen, apostrophe). A digit or symbol
        // typed at any point trips this immediately.
        var invalidCharPattern = /[^A-Za-z\s.]/;

        function validateName(isBlur) {
            var val = nameInput.value.trim();
            var valid = /^[A-Z][A-Za-z\s.]{1,99}$/.test(val);
            var startsLower = val.length > 0 && /^[a-z]/.test(val);
            var hasInvalidChar = invalidCharPattern.test(val);

            // Show an error as soon as there's a disallowed character
            // (e.g. a digit), or once the field is long enough / blurred
            // to judge the capitalization/length rules fairly. Never flag
            // an empty field here — required handles that on submit.
            var showError = val.length > 0 && !valid && (hasInvalidChar || val.length > 4 || isBlur === true);

            nameInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                nameInput.setCustomValidity('');
            } else if (hasInvalidChar) {
                nameInput.setCustomValidity('Only letters, spaces, and . are allowed — no numbers or symbols.');
            } else if (startsLower) {
                nameInput.setCustomValidity('Must start with an uppercase letter.');
            } else {
                nameInput.setCustomValidity('Enter a valid name of more than one character, using letters only.');
            }

            // Surface the browser's native validation bubble immediately
            // while the user is still typing. Never do this on blur —
            // reportValidity() forces the field to refocus itself, which
            // would trap the user and block clicking into the next field.
            if (showError && !isBlur) {
                nameInput.reportValidity();
            }
        }
        nameInput.addEventListener('input', function() { validateName(false); });
        nameInput.addEventListener('blur', function() { validateName(true); });
        validateName(true); // flag an already-bad stored value as soon as the page loads
    }

    // Contact No: live validation, must start with 09 and have exactly 11 digits
    var contactInput = document.getElementById('profile_contact');
    if (contactInput) {
        // Matches anything that isn't a digit — contact numbers are
        // digits only, so any letter/symbol trips this immediately.
        var contactInvalidCharPattern = /[^0-9]/;

        function validateContact(isBlur) {
            var val = contactInput.value.trim();
            var valid = /^09\d{9}$/.test(val);
            var hasInvalidChar = contactInvalidCharPattern.test(val);

            var showError = val.length > 0 && !valid && (hasInvalidChar || val.length >= 11 || isBlur === true);

            contactInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                contactInput.setCustomValidity('');
            } else if (hasInvalidChar) {
                contactInput.setCustomValidity('Only numbers are allowed.');
            } else {
                contactInput.setCustomValidity('Contact number must start with 09 and contain 11 digits.');
            }

            if (showError && !isBlur) {
                contactInput.reportValidity();
            }
        }
        contactInput.addEventListener('input', function() { validateContact(false); });
        contactInput.addEventListener('blur', function() { validateContact(true); });
        validateContact(true); // flag an already-bad stored value as soon as the page loads
    }

    // Email: live validation, must be a valid @gmail.com address
    var emailInput = document.getElementById('profile_email');
    if (emailInput) {
        // Matches any character not allowed anywhere in a gmail address
        // (letters, digits, . _ % + - and the @ symbol itself).
        var emailInvalidCharPattern = /[^a-zA-Z0-9._%+\-@]/;

        function validateEmail(isBlur) {
            var val = emailInput.value.trim();
            var valid = /^[a-zA-Z0-9._%+-]+@gmail\.com$/.test(val);
            var hasInvalidChar = emailInvalidCharPattern.test(val);

            var showError = !valid && (hasInvalidChar || val.indexOf('@') !== -1 || isBlur === true) && val.length > 0;

            emailInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                emailInput.setCustomValidity('');
            } else if (hasInvalidChar) {
                emailInput.setCustomValidity("Only letters, numbers, and . _ % + - are allowed, plus one @.");
            } else {
                emailInput.setCustomValidity('Enter a valid @gmail.com address.');
            }

            if (showError && !isBlur) {
                emailInput.reportValidity();
            }
        }
        emailInput.addEventListener('input', function() { validateEmail(false); });
        emailInput.addEventListener('blur', function() { validateEmail(true); });
        validateEmail(true); // flag an already-bad stored value as soon as the page loads
    }

    // Final safety net: re-check name/email/contact on submit so an invalid
    // value can never slip through (e.g. pasted text, autofill).
    var profileForm = nameInput ? nameInput.closest('form') : null;
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            if (nameInput) validateName(true);
            if (emailInput) validateEmail(true);
            if (contactInput) validateContact(true);

            var firstInvalid = profileForm.querySelector('.is-invalid');
            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.reportValidity();
                firstInvalid.focus();
            }
        });
    }

    // GCash Account Name: live validation, must not be empty, max 100 chars
    var gcashNameInput = document.getElementById('gcash_account_name');
    if (gcashNameInput) {
        function validateGcashName(isBlur) {
            var val = gcashNameInput.value.trim();
            var valid = val.length > 0 && val.length <= 100;
            var tooLong = val.length > 100;
            var showError = !valid && (tooLong || isBlur === true) && !(val.length === 0 && !isBlur);

            gcashNameInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                gcashNameInput.setCustomValidity('');
            } else if (tooLong) {
                gcashNameInput.setCustomValidity('GCash Account Name must be at most 100 characters.');
            } else {
                gcashNameInput.setCustomValidity('GCash Account Name is required.');
            }

            if (showError && !isBlur) {
                gcashNameInput.reportValidity();
            }
        }
        gcashNameInput.addEventListener('input', function() { validateGcashName(false); });
        gcashNameInput.addEventListener('blur', function() { validateGcashName(true); });
        validateGcashName(true);
    }

    // GCash Account Number: live validation, must start with 09 and have exactly 11 digits
    var gcashNoInput = document.getElementById('gcash_account_no');
    if (gcashNoInput) {
        var gcashNoInvalidCharPattern = /[^0-9]/;

        function validateGcashNo(isBlur) {
            var val = gcashNoInput.value.trim();
            var valid = /^09\d{9}$/.test(val);
            var hasInvalidChar = gcashNoInvalidCharPattern.test(val);

            var showError = val.length > 0 && !valid && (hasInvalidChar || val.length >= 11 || isBlur === true);

            gcashNoInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                gcashNoInput.setCustomValidity('');
            } else if (hasInvalidChar) {
                gcashNoInput.setCustomValidity('Only numbers are allowed.');
            } else {
                gcashNoInput.setCustomValidity('Account number must start with 09 and contain 11 digits.');
            }

            if (showError && !isBlur) {
                gcashNoInput.reportValidity();
            }
        }
        gcashNoInput.addEventListener('input', function() { validateGcashNo(false); });
        gcashNoInput.addEventListener('blur', function() { validateGcashNo(true); });
        validateGcashNo(true);
    }

    // Final safety net for the GCash form too.
    var gcashForm = gcashNameInput ? gcashNameInput.closest('form') : null;
    if (gcashForm) {
        gcashForm.addEventListener('submit', function(e) {
            if (gcashNameInput) validateGcashName(true);
            if (gcashNoInput) validateGcashNo(true);

            var firstInvalid = gcashForm.querySelector('.is-invalid');
            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.reportValidity();
                firstInvalid.focus();
            }
        });
    }

    // Jump straight to the GCash card if the page was opened with #gcash
    // (e.g. from the header's "Admin GCash QR Settings" dropdown link, or
    // after a GCash form save redirects back here).
    if (window.location.hash === '#gcash') {
        var gcashCard = document.getElementById('gcash');
        if (gcashCard) {
            gcashCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }
})();
</script>

<?php require_once '../includes/footer.php'; ?>