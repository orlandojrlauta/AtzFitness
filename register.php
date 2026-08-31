<?php
/**
 * ATZ Fitness Gym Management System
 * Account Registration (Administrator + Staff, one page)
 *
 * How the "only one Administrator" rule is enforced here:
 *   - admin_already_exists() checks the DATABASE, not a flag or a file.
 *   - While no Administrator exists yet, the form shows a role choice
 *     (Administrator / Staff). Once an Administrator exists, the
 *     Administrator option is removed from the page entirely and the
 *     server ignores/overrides any role value it's sent — every
 *     submission from that point on is forced to 'Staff', no matter
 *     what the request contains.
 *   - The check is repeated again immediately before the INSERT, so two
 *     people submitting the form in the same instant can't both become
 *     Administrator.
 *
 * Account activation:
 *   - The founding Administrator is Active immediately — there's no one
 *     else yet to approve them.
 *   - Every Staff registration (before or after the admin exists) is
 *     created Inactive. login.php already blocks non-Active accounts,
 *     and the Administrator approves new staff from admin/staff.php
 *     using the same Activate/Deactivate control already used to manage
 *     existing staff.
 */

require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'Administrator' ? 'admin/index.php' : 'staff/index.php'));
    exit();
}

function admin_already_exists($conn) {
    $res = mysqli_query($conn, "SELECT id FROM users WHERE role = 'Administrator' LIMIT 1");
    return $res && mysqli_num_rows($res) > 0;
}

/*
|--------------------------------------------------------------------------
| GOOGLE reCAPTCHA SETTINGS
|--------------------------------------------------------------------------
| Replace these with your actual Google reCAPTCHA keys.
|
| Site Key   = used in the HTML form
| Secret Key = used for server-side verification
|
*/
define('RECAPTCHA_SITE_KEY', 'YOUR_RECAPTCHA_SITE_KEY');
define('RECAPTCHA_SECRET_KEY', 'YOUR_RECAPTCHA_SECRET_KEY');

$admin_exists = admin_already_exists($conn);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA Verification
    |--------------------------------------------------------------------------
    */
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    $recaptcha_result = verify_recaptcha($recaptcha_response, RECAPTCHA_SECRET_KEY);
    
    if (!$recaptcha_result['success']) {
        $error = $recaptcha_result['error'];
    } else {
        // Decide the role server-side. Only honor a submitted "Administrator"
        // choice if no admin exists RIGHT NOW — re-checked here, not trusted
        // from when the page was first loaded.
        $requested_role = ($_POST['role'] ?? '') === 'Administrator' ? 'Administrator' : 'Staff';
        $creating_admin = ($requested_role === 'Administrator') && !admin_already_exists($conn);

    $username    = sanitize($_POST['username'] ?? '');
    $full_name   = sanitize($_POST['full_name'] ?? '');
    $full_name   = trim(preg_replace('/\s+/', ' ', $full_name));
    $email       = sanitize($_POST['email'] ?? '');
    $contact_no  = sanitize($_POST['contact_no'] ?? '');
    $password    = $_POST['password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($full_name) || empty($email) || empty($contact_no) || empty($password)) {
        $error = "Please fill in all fields.";
    } else if (!validate_capitalized_name($full_name)) {
        $error = "Full name must start with a capital letter and contain letters only.";
    } else if ($creating_admin ? !validate_email_format($email) : !validate_gmail_email($email)) {
        $error = $creating_admin
            ? "Please enter a valid email address."
            : "Email address must be a valid @gmail.com address (e.g. juan@gmail.com).";
    } else if (!validate_ph_contact($contact_no)) {
        $error = "Contact number must start with 09 and contain 11 digits.";
    } else if (strlen($username) < 4) {
        $error = "Username must be at least 4 characters.";
    } else if (!validate_strong_password($password)) {
        $error = "Password must be at least 8 characters and include an uppercase letter, a lowercase letter, a number, and a special character.";
    } else if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $chk = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ? OR email = ?");
        mysqli_stmt_bind_param($chk, "ss", $username, $email);
        mysqli_stmt_execute($chk);
        $chk_res = mysqli_stmt_get_result($chk);

        if (mysqli_num_rows($chk_res) > 0) {
            $error = "Username or email address is already taken.";
        } else {
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            $role   = $creating_admin ? 'Administrator' : 'Staff';
            $status = $creating_admin ? 'Active' : 'Inactive';

            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, password, full_name, email, contact_no, role, status, force_password_change) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
            mysqli_stmt_bind_param($stmt, "sssssss", $username, $hashed, $full_name, $email, $contact_no, $role, $status);

            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                log_activity($conn, $new_id, $username, $role,
                    $creating_admin ? 'Initial Admin Setup' : 'Self-Registration',
                    $creating_admin ? 'Administrator account created' : 'Staff account registered, pending admin approval');

                $success = $creating_admin
                    ? "Administrator account created. You can log in now."
                    : "Account created! An administrator needs to approve it before you can log in.";
                $admin_exists = $admin_exists || $creating_admin;
            } else {
                $error = "Something went wrong. Please try again.";
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($chk);
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ATZ Fitness - Create Account</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/style.css?v=4" rel="stylesheet">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>
<body>

<div class="auth-shell">
<div class="login-card login-card-lg">
    <div class="login-header">
        <img src="assets/img/logo.jpg" alt="ATZ Fitness Logo" class="login-logo mb-2" width="80" height="80">
        <h3 class="fw-bold mt-2 text-warning">ATZ FITNESS</h3>
        <p class="text-white-50 mb-0 small">Create Account</p>
    </div>
    <div class="p-4 p-md-5">
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?php echo sanitize($error); ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?php echo sanitize($success); ?></div>
            </div>
            <a href="login.php" class="btn btn-dark w-100 fw-bold py-2">
                <i class="bi bi-box-arrow-in-right me-1"></i> Back to Login
            </a>
        <?php else: ?>
            <form method="POST" action="register.php" data-validate="true">
                <?php echo csrf_field(); ?>

                <?php if (!$admin_exists): ?>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Account Type </label>
                    <div class="role-toggle">
                        <input type="radio" name="role" id="roleStaff" value="Staff" checked>
                        <label for="roleStaff"><i class="bi bi-person-badge me-1"></i> Staff</label>

                        <input type="radio" name="role" id="roleAdmin" value="Administrator">
                        <label for="roleAdmin"><i class="bi bi-shield-fill-check me-1"></i> Administrator</label>
                    </div>
                    <div class="form-text">No administrator exists yet — the first one can be created here, once.</div>
                </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Username </label>
                    <input type="text" name="username" id="reg_username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name </label>
                    <input type="text" name="full_name" id="full_name" class="form-control"
                           pattern="[A-Z][A-Za-z\s\.\-']*" minlength="2" maxlength="100"
                           title="Must start with a capital letter — letters, spaces, and . - ' only."
                           required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email Address </label>
                    <input type="email" name="email" id="reg_email" class="form-control"
                           pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                           title="Enter a valid @gmail.com address (e.g. juan@gmail.com)."
                           data-gmail-only="true"
                           required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Contact No.</label>
                    <input type="text" name="contact_no" id="reg_contact_no" class="form-control" maxlength="11"
                           inputmode="numeric" pattern="09[0-9]{9}"
                           title="Contact number must start with 09 and contain 11 digits."
                           required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password </label>
                    <div class="input-group">
                        <input type="password" name="password" id="regPassword" class="form-control" minlength="8"
                               pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}"
                               title="At least 8 characters, with an uppercase letter, a lowercase letter, a number, and a special character."
                               placeholder="At least 8 characters" required>
                        <span class="input-group-text bg-light" role="button" id="toggleRegPassword" style="cursor:pointer;">
                            <i class="bi bi-eye-slash" id="toggleRegPasswordIcon"></i>
                        </span>
                    </div>
                    <div class="form-text">Must include an uppercase letter, a lowercase letter, a number, and a special character(e.g. ! @ # $ %).</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm Password </label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="regConfirmPassword" class="form-control" minlength="8" placeholder="Re-enter password" required>
                        <span class="input-group-text bg-light" role="button" id="toggleRegConfirmPassword" style="cursor:pointer;">
                            <i class="bi bi-eye-slash" id="toggleRegConfirmPasswordIcon"></i>
                        </span>
                    </div>
                </div>
                <!-- GOOGLE reCAPTCHA -->
                <div class="mb-4">
                    <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY); ?>"></div>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm text-dark fs-5">
                    <i class="bi bi-person-plus-fill me-1"></i> Create Account
                </button>
                <p class="text-muted small mt-3 mb-0 text-center" id="approvalNote">
                    Staff accounts need admin approval before you can log in.
                </p>
            </form>
            <div class="text-center mt-4">
                <a href="login.php" class="text-decoration-none small">
                    <i class="bi bi-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
<script>
(function() {
    // Admin role doesn't require a @gmail.com-only address, and doesn't
    // need the "needs approval" note. Toggle those when the role choice
    // (only present before any admin exists) changes.
    var roleAdmin = document.getElementById('roleAdmin');
    var roleStaff = document.getElementById('roleStaff');
    var emailInput = document.getElementById('reg_email');
    var approvalNote = document.getElementById('approvalNote');
    function isAdminRole() {
        return !!(roleAdmin && roleAdmin.checked);
    }
    function applyRoleUI() {
        var isAdmin = isAdminRole();
        if (emailInput) {
            if (isAdmin) {
                emailInput.removeAttribute('pattern');
                emailInput.removeAttribute('data-gmail-only');
                emailInput.placeholder = 'e.g. admin@yourgym.com';
            } else {
                emailInput.setAttribute('pattern', '[a-zA-Z0-9._%+\\-]+@gmail\\.com');
                emailInput.setAttribute('data-gmail-only', 'true');
                emailInput.placeholder = 'e.g. juan@gmail.com';
            }
        }
        if (approvalNote) {
            approvalNote.style.visibility = isAdmin ? 'hidden' : 'visible';
        }
        // Re-run email validation since the rule just changed.
        if (typeof validateEmail === 'function') validateEmail(true);
    }
    if (roleAdmin && roleStaff) {
        roleAdmin.addEventListener('change', applyRoleUI);
        roleStaff.addEventListener('change', applyRoleUI);
        applyRoleUI();
    }

    // Show/hide password toggles
    function wireToggle(toggleId, inputId, iconId) {
        var toggle = document.getElementById(toggleId);
        var input = document.getElementById(inputId);
        var icon = document.getElementById(iconId);
        if (toggle && input && icon) {
            toggle.addEventListener('click', function() {
                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                icon.classList.toggle('bi-eye-slash', !isPassword);
                icon.classList.toggle('bi-eye', isPassword);
            });
        }
    }
    wireToggle('toggleRegPassword', 'regPassword', 'toggleRegPasswordIcon');
    wireToggle('toggleRegConfirmPassword', 'regConfirmPassword', 'toggleRegConfirmPasswordIcon');

    // ---- Live validation (same approach used in staff/profile.php) ----
    // Each field gets: an is-invalid toggle, a setCustomValidity() message,
    // and reportValidity() while typing (never on blur, so the field
    // doesn't refocus itself and trap the user).

    // Username: at least 4 characters (matches the server-side check).
    var usernameInput = document.getElementById('reg_username');
    if (usernameInput) {
        function validateUsername(isBlur) {
            var val = usernameInput.value.trim();
            var valid = val.length >= 4;
            var showError = val.length > 0 && !valid && (val.length >= 2 || isBlur === true);

            usernameInput.classList.toggle('is-invalid', showError);
            usernameInput.setCustomValidity(showError ? 'Username must be at least 4 characters.' : '');

            if (showError && !isBlur) {
                usernameInput.reportValidity();
            }
        }
        usernameInput.addEventListener('input', function() { validateUsername(false); });
        usernameInput.addEventListener('blur', function() { validateUsername(true); });
    }

    // Full Name: must start with a capital letter and contain letters only
    // (spaces, . - ' allowed as separators).
    var nameInput = document.getElementById('full_name');
    if (nameInput) {
        var invalidCharPattern = /[^A-Za-z\s.]/;

        function validateName(isBlur) {
            var val = nameInput.value.trim();
            var valid = /^[A-Z][A-Za-z\s.]{1,99}$/.test(val);
            var startsLower = val.length > 0 && /^[a-z]/.test(val);
            var hasInvalidChar = invalidCharPattern.test(val);

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

            if (showError && !isBlur) {
                nameInput.reportValidity();
            }
        }
        nameInput.addEventListener('input', function() { validateName(false); });
        nameInput.addEventListener('blur', function() { validateName(true); });
    }

    // Email: gmail.com only for Staff; any well-formed address for the
    // founding Administrator (mirrors the server-side rule).
    function validateEmail(isBlur) {
        if (!emailInput) return;
        var val = emailInput.value.trim();
        var admin = isAdminRole();
        var emailInvalidCharPattern = /[^a-zA-Z0-9._%+\-@]/;
        var hasInvalidChar = !admin && emailInvalidCharPattern.test(val);
        var valid = admin
            ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)
            : /^[a-zA-Z0-9._%+-]+@gmail\.com$/.test(val);

        var showError = !valid && val.length > 0 && (hasInvalidChar || val.indexOf('@') !== -1 || isBlur === true);

        emailInput.classList.toggle('is-invalid', showError);

        if (!showError) {
            emailInput.setCustomValidity('');
        } else if (hasInvalidChar) {
            emailInput.setCustomValidity("Only letters, numbers, and . _ % + - are allowed, plus one @.");
        } else if (admin) {
            emailInput.setCustomValidity('Enter a valid email address.');
        } else {
            emailInput.setCustomValidity('Enter a valid @gmail.com address (e.g. juan@gmail.com).');
        }

        if (showError && !isBlur) {
            emailInput.reportValidity();
        }
    }
    if (emailInput) {
        emailInput.addEventListener('input', function() { validateEmail(false); });
        emailInput.addEventListener('blur', function() { validateEmail(true); });
    }

    // Contact No: must start with 09 and have exactly 11 digits.
    var contactInput = document.getElementById('reg_contact_no');
    if (contactInput) {
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
    }

    // Password: at least 8 characters, with an uppercase letter, a
    // lowercase letter, a number, and a special character.
    var pw = document.getElementById('regPassword');
    var confirm = document.getElementById('regConfirmPassword');
    function validatePassword(isBlur) {
        if (!pw) return;
        var val = pw.value;
        var valid = val.length >= 8 && /[A-Z]/.test(val) && /[a-z]/.test(val) && /[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val);
        var showError = val.length > 0 && !valid && (val.length >= 8 || isBlur === true);

        pw.classList.toggle('is-invalid', showError);
        pw.setCustomValidity(showError ? 'Must be at least 8 characters, with an uppercase letter, a lowercase letter, a number, and a special character.' : '');

        if (showError && !isBlur) {
            pw.reportValidity();
        }

        // Password strength affects the match check too.
        if (confirm) validateConfirm(true);
    }
    function validateConfirm(isBlur) {
        if (!confirm) return;
        var val = confirm.value;
        var matches = val === (pw ? pw.value : '');
        var showError = val.length > 0 && !matches;

        confirm.classList.toggle('is-invalid', showError);
        confirm.setCustomValidity(showError ? 'Passwords do not match.' : '');

        if (showError && !isBlur) {
            confirm.reportValidity();
        }
    }
    if (pw) {
        pw.addEventListener('input', function() { validatePassword(false); });
        pw.addEventListener('blur', function() { validatePassword(true); });
    }
    if (confirm) {
        confirm.addEventListener('input', function() { validateConfirm(false); });
        confirm.addEventListener('blur', function() { validateConfirm(true); });
    }

    // Final safety net: re-check everything on submit so an invalid value
    // (pasted text, autofill, etc.) can never slip through, and nothing
    // saves until every field is actually filled in and valid.
    var regForm = nameInput ? nameInput.closest('form') : (usernameInput ? usernameInput.closest('form') : null);
    if (regForm) {
        regForm.addEventListener('submit', function(e) {
            if (usernameInput) validateUsername(true);
            if (nameInput) validateName(true);
            if (emailInput) validateEmail(true);
            if (contactInput) validateContact(true);
            if (pw) validatePassword(true);
            if (confirm) validateConfirm(true);

            var firstInvalid = regForm.querySelector('.is-invalid');
            if (firstInvalid) {
                e.preventDefault();
                firstInvalid.reportValidity();
                firstInvalid.focus();
            }
        });
    }
})();
</script>
</body>
</html>