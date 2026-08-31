<?php
/**
 * ATZ Fitness Gym Management System
 * Staff Self-Service Profile (Name, Email, Contact, Profile Photo)
 */

$page_title = "My Profile";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Staff']);

$action = $_POST['action'] ?? '';

// Update Own Profile (Name, Email, Contact, Profile Photo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_profile') {
    verify_csrf();
    $new_full_name = sanitize($_POST['profile_full_name'] ?? '');
    $new_email = sanitize($_POST['profile_email'] ?? '');
    $new_contact = sanitize($_POST['profile_contact'] ?? '');

    if (!validate_capitalized_name($new_full_name)) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Full name must start with a capital letter and contain letters only.";
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
        // Email must stay unique across all accounts, and so must full name
        // (case-insensitive), excluding this staff member's own row
        $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE (email = ? OR LOWER(full_name) = LOWER(?)) AND id != ?");
        mysqli_stmt_bind_param($chk, "ssi", $new_email, $new_full_name, $_SESSION['user_id']);
        mysqli_stmt_execute($chk);
        $res = mysqli_stmt_get_result($chk);

        if (mysqli_num_rows($res) > 0) {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "Email address or full name is already used by another account.";
            $_SESSION['swal_type'] = "error";
        } else {
            // Optional profile photo replacement
            $new_photo = null;
            if (!empty($_FILES['profile_picture']['name'])) {
                $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png']) && is_valid_image_upload($_FILES['profile_picture']['tmp_name'])) {
                    $new_photo = "staff_" . $_SESSION['user_id'] . "_" . time() . "." . $ext;
                    move_uploaded_file($_FILES['profile_picture']['tmp_name'], "../uploads/profile/" . $new_photo);
                }
            }

            if ($new_photo) {
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, contact_no = ?, profile_picture = ? WHERE id = ? AND role = 'Staff'");
                mysqli_stmt_bind_param($stmt, "ssssi", $new_full_name, $new_email, $new_contact, $new_photo, $_SESSION['user_id']);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE users SET full_name = ?, email = ?, contact_no = ? WHERE id = ? AND role = 'Staff'");
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
        mysqli_stmt_close($chk);
    }
    header("Location: profile.php");
    exit();
}

// Fetch the current profile details fresh from the database
$profile_stmt = mysqli_prepare($conn, "SELECT username, full_name, email, contact_no, profile_picture FROM users WHERE id = ?");
mysqli_stmt_bind_param($profile_stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($profile_stmt);
$my_profile = mysqli_fetch_assoc(mysqli_stmt_get_result($profile_stmt));
mysqli_stmt_close($profile_stmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-person-circle text-warning me-2"></i> My Profile</h1>
</div>

<div class="row g-4">
    <div class="col-lg-8 mx-auto">
        <div class="card p-4">
            <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-person-badge text-warning me-2"></i> Account Details</h5>
            <form method="POST" action="profile.php" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="row g-3">
                    <div class="col-md-3 text-center">
                        <?php if (!empty($my_profile['profile_picture']) && file_exists("../uploads/profile/" . $my_profile['profile_picture'])): ?>
                            <img src="../uploads/profile/<?php echo sanitize($my_profile['profile_picture']); ?>" alt="Profile Photo" class="rounded-circle border mb-2" style="width: 90px; height: 90px; object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2" style="width: 90px; height: 90px;">
                                <i class="bi bi-person-fill fs-1"></i>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="profile_picture" class="form-control form-control-sm" accept="image/png, image/jpeg, image/jpg">
                    </div>
                    <div class="col-md-9">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Username</label>
                                <input type="text" class="form-control" value="<?php echo sanitize($my_profile['username']); ?>" disabled>
                                <div class="form-text">Username can't be changed. Contact an Administrator if needed.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name</label>
                                <input type="text" name="profile_full_name" id="profile_full_name" class="form-control"
                                       pattern="[A-Z][A-Za-z\s\.\-']*" minlength="2" maxlength="100"
                                       title="Must start with a capital letter — letters, spaces, and . - ' only."
                                       value="<?php echo sanitize($my_profile['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address</label>
                                <input type="email" name="profile_email" id="profile_email" class="form-control"
                                       pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                                       title="Enter a valid @gmail.com address (e.g. juan@gmail.com)."
                                       data-gmail-only="true"
                                       value="<?php echo sanitize($my_profile['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contact No.</label>
                                <input type="text" name="profile_contact" id="profile_contact" class="form-control" maxlength="11"
                                       inputmode="numeric" pattern="09[0-9]{9}"
                                       title="Contact number must start with 09 and contain 11 digits."
                                       value="<?php echo sanitize($my_profile['contact_no']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4"><i class="bi bi-save-fill me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    // Full Name: live validation, must start with a capital letter and
    // contain letters only (spaces, . - ' allowed as separators).
    var nameInput = document.getElementById('profile_full_name');
    if (nameInput) {
        // Matches any character that is NOT allowed anywhere in the name
        // (letters, spaces, dot, hyphen, apostrophe). A digit or symbol
        // typed at any point trips this immediately.
        var invalidCharPattern = /[^A-Za-z\s.\-']/;

        function validateName(isBlur) {
            var val = nameInput.value.trim();
            var valid = /^[A-Z][A-Za-z\s.\-']{1,99}$/.test(val);
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
                nameInput.setCustomValidity('Only letters, spaces, and . - \' are allowed — no numbers or symbols.');
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

            // Show an error the instant a disallowed character is typed,
            // or once there's enough context (an @ sign, or the field
            // was left) to judge the full gmail.com format fairly.
            var showError = !valid && (hasInvalidChar || val.indexOf('@') !== -1 || isBlur === true) && val.length > 0;

            emailInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                emailInput.setCustomValidity('');
            } else if (hasInvalidChar) {
                emailInput.setCustomValidity("Only letters, numbers, and . _ % + - are allowed, plus one @.");
            } else {
                emailInput.setCustomValidity('Enter a valid @gmail.com address (e.g. juan@gmail.com).');
            }

            // Pop the native tooltip only while the user is still typing —
            // never on blur, since reportValidity() would refocus the
            // field and block clicking into the next one.
            if (showError && !isBlur) {
                emailInput.reportValidity();
            }
        }
        emailInput.addEventListener('input', function() { validateEmail(false); });
        emailInput.addEventListener('blur', function() { validateEmail(true); });
        validateEmail(true); // flag an already-bad stored value as soon as the page loads
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

            // Show an error the instant a non-digit is typed, or once
            // there are enough digits / the field was left to judge the
            // "starts with 09, 11 digits" rule fairly. Never flag an
            // empty field here — required handles that on submit.
            var showError = val.length > 0 && !valid && (hasInvalidChar || val.length >= 11 || isBlur === true);

            contactInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                contactInput.setCustomValidity('');
            } else if (hasInvalidChar) {
                contactInput.setCustomValidity('Only numbers are allowed.');
            } else {
                contactInput.setCustomValidity('Contact number must start with 09 and contain 11 digits.');
            }

            // Pop the native tooltip only while the user is still typing —
            // never on blur, since reportValidity() would refocus the
            // field and block clicking into the next one.
            if (showError && !isBlur) {
                contactInput.reportValidity();
            }
        }
        contactInput.addEventListener('input', function() { validateContact(false); });
        contactInput.addEventListener('blur', function() { validateContact(true); });
        validateContact(true); // flag an already-bad stored value as soon as the page loads
    }

    // Final safety net: re-check everything on submit so an invalid value
    // can never slip through (e.g. pasted text, autofill).
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
})();
</script>

<?php require_once '../includes/footer.php'; ?>