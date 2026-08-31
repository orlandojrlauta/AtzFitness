<?php
/**
 * ATZ Fitness Gym Management System
 * Gym Branding & Information
 */

$page_title = "Gym Branding & Information";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_admin();

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_branding') {
    verify_csrf();
    $gym_name = sanitize($_POST['gym_name'] ?? '');
    $gym_tagline = sanitize($_POST['gym_tagline'] ?? '');
    $gym_address = sanitize($_POST['gym_address'] ?? '');
    $gym_contact = sanitize($_POST['gym_contact'] ?? '');
    $gym_email = sanitize($_POST['gym_email'] ?? '');

    // A gym contact number can be a mobile (09xxxxxxxx) or a landline with
    // an area code, so this is deliberately looser than the personal
    // profile's mobile-only check — just digits/spaces/+/-/() within a
    // sane length.
    $contact_valid = (bool) preg_match('/^[0-9+\-() ]{7,20}$/', $gym_contact);

    if ($gym_name === '') {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Gym Name is required.";
        $_SESSION['swal_type'] = "error";
    } else if (strlen($gym_name) > 100) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Gym Name must be at most 100 characters.";
        $_SESSION['swal_type'] = "error";
    } else if (strlen($gym_tagline) > 100) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Tagline must be at most 100 characters.";
        $_SESSION['swal_type'] = "error";
    } else if ($gym_address === '') {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Address is required.";
        $_SESSION['swal_type'] = "error";
    } else if (strlen($gym_address) > 100) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Address must be at most 100 characters.";
        $_SESSION['swal_type'] = "error";
    } else if (!$contact_valid) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Contact Phone must be a valid phone number (digits only, 7-20 characters; spaces, +, -, and () are allowed).";
        $_SESSION['swal_type'] = "error";
    } else if (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/', $gym_email)) {
        $_SESSION['swal_title'] = "Error!";
        $_SESSION['swal_msg'] = "Contact Email must be a valid email address.";
        $_SESSION['swal_type'] = "error";
    } else {
        $settings_to_update = [
            'gym_name' => $gym_name,
            'gym_tagline' => $gym_tagline,
            'gym_address' => $gym_address,
            'gym_contact' => $gym_contact,
            'gym_email' => $gym_email,
        ];

        foreach ($settings_to_update as $key => $val) {
            $stmt = mysqli_prepare($conn, "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            mysqli_stmt_bind_param($stmt, "sss", $key, $val, $val);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Update Settings', "Updated gym branding & information");

        $_SESSION['swal_title'] = "Success!";
        $_SESSION['swal_msg'] = "Gym branding & information updated successfully!";
        $_SESSION['swal_type'] = "success";
    }

    header("Location: branding.php");
    exit();
}

$gym_name = get_setting($conn, 'gym_name', 'ATZ FITNESS');
$gym_tagline = get_setting($conn, 'gym_tagline', 'Transform Your Body, Elevate Your Life');
$gym_address = get_setting($conn, 'gym_address', '123 Fitness Ave, Metro Manila, Philippines');
$gym_contact = get_setting($conn, 'gym_contact', '09171234567');
$gym_email = get_setting($conn, 'gym_email', 'contact@atzfitness.com');

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-building text-warning me-2"></i> Gym Branding & Information</h1>
</div>

<form method="POST" action="branding.php" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="save_branding">
    <div class="card p-4">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Gym Name</label>
                <input type="text" name="gym_name" id="gym_name" class="form-control" maxlength="100" value="<?php echo sanitize($gym_name); ?>" required>
                <div class="invalid-feedback" id="gym_name_error">Gym Name is required and must be at most 100 characters.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Tagline</label>
                <input type="text" name="gym_tagline" id="gym_tagline" class="form-control" maxlength="100" value="<?php echo sanitize($gym_tagline); ?>">
                <div class="invalid-feedback" id="gym_tagline_error">Tagline must be at most 100 characters.</div>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <input type="text" name="gym_address" id="gym_address" class="form-control" maxlength="100" value="<?php echo sanitize($gym_address); ?>" required>
                <div class="invalid-feedback" id="gym_address_error">Address is required and must be at most 100 characters.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Phone</label>
                <input type="text" name="gym_contact" id="gym_contact" class="form-control"
                       pattern="[0-9+\-() ]{7,20}"
                       title="Digits only, 7-20 characters; spaces, +, -, and () are allowed."
                       value="<?php echo sanitize($gym_contact); ?>" required>
                <div></div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Email</label>
                <input type="email" name="gym_email" id="gym_email" class="form-control" maxlength="100"
                       pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9\-]+(\.[a-zA-Z0-9\-]+)*\.[a-zA-Z]{2,}"
                       title="Enter a valid email address with a proper domain (e.g. name@example.com)."
                       value="<?php echo sanitize($gym_email); ?>" required>
                <div ></div>
            </div>
        </div>
        <div class="text-end mt-4">
            <button type="submit" class="btn btn-warning btn-lg fw-bold text-dark px-5 shadow-sm"><i class="bi bi-save-fill me-2"></i> Save Changes</button>
        </div>
    </div>
</form>

<script>
(function() {
    // Gym Name: live validation, required, max 100 chars
    var gymNameInput = document.getElementById('gym_name');
    if (gymNameInput) {
        function validateGymName(isBlur) {
            var val = gymNameInput.value.trim();
            var valid = val.length > 0 && val.length <= 100;
            var tooLong = val.length > 100;

            // Show an error once it's too long (can happen via paste even
            // with maxlength set), or once the field was left empty on blur.
            var showError = !valid && (tooLong || isBlur === true) && !(val.length === 0 && !isBlur);

            gymNameInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                gymNameInput.setCustomValidity('');
            } else if (tooLong) {
                gymNameInput.setCustomValidity('Gym Name must be at most 100 characters.');
            } else {
                gymNameInput.setCustomValidity('Gym Name is required.');
            }

            if (showError && !isBlur) {
                gymNameInput.reportValidity();
            }
        }
        gymNameInput.addEventListener('input', function() { validateGymName(false); });
        gymNameInput.addEventListener('blur', function() { validateGymName(true); });
        validateGymName(true); // flag an already-bad stored value as soon as the page loads
    }

    // Tagline: live validation, optional, max 100 chars
    var taglineInput = document.getElementById('gym_tagline');
    if (taglineInput) {
        function validateTagline(isBlur) {
            var val = taglineInput.value.trim();
            var valid = val.length <= 100;
            var showError = !valid;

            taglineInput.classList.toggle('is-invalid', showError);
            taglineInput.setCustomValidity(valid ? '' : 'Tagline must be at most 100 characters.');

            if (showError && !isBlur) {
                taglineInput.reportValidity();
            }
        }
        taglineInput.addEventListener('input', function() { validateTagline(false); });
        taglineInput.addEventListener('blur', function() { validateTagline(true); });
        validateTagline(true);
    }

    // Address: live validation, required, max 100 chars
    var addressInput = document.getElementById('gym_address');
    if (addressInput) {
        function validateAddress(isBlur) {
            var val = addressInput.value.trim();
            var valid = val.length > 0 && val.length <= 100;
            var tooLong = val.length > 100;
            var showError = !valid && (tooLong || isBlur === true) && !(val.length === 0 && !isBlur);

            addressInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                addressInput.setCustomValidity('');
            } else if (tooLong) {
                addressInput.setCustomValidity('Address must be at most 100 characters.');
            } else {
                addressInput.setCustomValidity('Address is required.');
            }

            if (showError && !isBlur) {
                addressInput.reportValidity();
            }
        }
        addressInput.addEventListener('input', function() { validateAddress(false); });
        addressInput.addEventListener('blur', function() { validateAddress(true); });
        validateAddress(true);
    }

    // Contact Phone: live validation
    var contactInput = document.getElementById('gym_contact');
    if (contactInput) {
        // Matches any character not allowed anywhere in the gym contact
        // format (digits, spaces, +, -, and parentheses).
        var contactInvalidCharPattern = /[^0-9+\-() ]/;

        function validateContact(isBlur) {
            var val = contactInput.value.trim();
            var valid = /^[0-9+\-() ]{7,20}$/.test(val);
            var hasInvalidChar = contactInvalidCharPattern.test(val);

            // Show an error the instant a disallowed character is typed,
            // or once there's enough length / the field was left to judge
            // the 7-20 character rule fairly.
            var showError = val.length > 0 && !valid && (hasInvalidChar || val.length >= 7 || isBlur === true);

            contactInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                contactInput.setCustomValidity('');
            } else if (hasInvalidChar) {
                contactInput.setCustomValidity('Only digits, spaces, +, -, and () are allowed.');
            } else {
                contactInput.setCustomValidity('Enter a valid phone number (digits only, 7-20 characters; spaces, +, -, and () are allowed).');
            }

            if (showError && !isBlur) {
                contactInput.reportValidity();
            }
        }
        contactInput.addEventListener('input', function() { validateContact(false); });
        contactInput.addEventListener('blur', function() { validateContact(true); });
        validateContact(true); // flag an already-bad stored value as soon as the page loads
    }

    // Contact Email: live validation (general format, any provider — must
    // have a real domain with a dot, e.g. name@example.com)
    var emailInput = document.getElementById('gym_email');
    if (emailInput) {
        // Matches any character not allowed anywhere in a general email
        // address (letters, digits, common symbols, @, and a dot).
        var emailInvalidCharPattern = /[^a-zA-Z0-9._%+\-@]/;

        function validateEmail(isBlur) {
            var val = emailInput.value.trim();
            var valid = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*\.[a-zA-Z]{2,}$/.test(val);
            var hasInvalidChar = emailInvalidCharPattern.test(val);

            // Show an error the instant a disallowed character is typed, or
            // once there's enough context to judge the format fairly — an
            // @ sign, a decent amount of typed text (covers plain garbage
            // with no @ at all), or the field was left.
            var showError = val.length > 0 && !valid && (hasInvalidChar || val.indexOf('@') !== -1 || val.length > 6 || isBlur === true);

            emailInput.classList.toggle('is-invalid', showError);

            if (!showError) {
                emailInput.setCustomValidity('');
            } else if (hasInvalidChar) {
                emailInput.setCustomValidity('Only letters, numbers, and . _ % + - are allowed, plus one @.');
            } else {
                emailInput.setCustomValidity('Enter a valid email address with a proper domain (e.g. name@example.com).');
            }

            if (showError && !isBlur) {
                emailInput.reportValidity();
            }
        }
        emailInput.addEventListener('input', function() { validateEmail(false); });
        emailInput.addEventListener('blur', function() { validateEmail(true); });
        validateEmail(true); // flag an already-bad stored value as soon as the page loads
    }

    // Final safety net: re-check everything on submit so an invalid value
    // can never slip through (e.g. pasted text, autofill).
    var brandingForm = gymNameInput ? gymNameInput.closest('form') : null;
    if (brandingForm) {
        brandingForm.addEventListener('submit', function(e) {
            if (gymNameInput) validateGymName(true);
            if (taglineInput) validateTagline(true);
            if (addressInput) validateAddress(true);
            if (contactInput) validateContact(true);
            if (emailInput) validateEmail(true);

            var firstInvalid = brandingForm.querySelector('.is-invalid');
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