<?php
/**
 * ATZ Fitness Gym Management System
 * Members Management Module
 */

$page_title = "Members Management";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

$action = $_POST['action'] ?? '';
$error = '';
$success = '';

// Handle Add / Edit Member
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if ($action === 'create_member') {
        $first_name = sanitize($_POST['first_name'] ?? '');
        $first_name = trim(preg_replace('/\s+/', ' ', $first_name));
        $last_name = sanitize($_POST['last_name'] ?? '');
        $last_name = trim(preg_replace('/\s+/', ' ', $last_name));
        $gender = sanitize($_POST['gender'] ?? 'Male');
        $birthdate = sanitize($_POST['birthdate'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $contact_no = sanitize($_POST['contact_no'] ?? '');
        $member_type = sanitize($_POST['member_type'] ?? 'Regular');

        // Validation Checks
        $age = validate_member_age($birthdate);
        
        if (!$age) {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "Member age must be between 13 and 80 years old.";
            $_SESSION['swal_type'] = "error";
        } else if (!validate_capitalized_name($first_name) || !validate_capitalized_name($last_name)) {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "First and last name must start with a capital letter and contain letters only.";
            $_SESSION['swal_type'] = "error";
        } else if (!validate_ph_contact($contact_no)) {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "Contact number must start with 09 and contain 11 digits.";
            $_SESSION['swal_type'] = "error";
        } else if (!validate_gmail_email($email)) {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "Email address must be a valid @gmail.com address (e.g. juan@gmail.com).";
            $_SESSION['swal_type'] = "error";
        } else {
            // Check unique email
            $check_email = mysqli_prepare($conn, "SELECT id FROM members WHERE email = ?");
            mysqli_stmt_bind_param($check_email, "s", $email);
            mysqli_stmt_execute($check_email);
            $email_res = mysqli_stmt_get_result($check_email);

            if (mysqli_num_rows($email_res) > 0) {
                $_SESSION['swal_title'] = "Error!";
                $_SESSION['swal_msg'] = "The email address is already registered.";
                $_SESSION['swal_type'] = "error";
            } else {
                // Upload Profile Photo if provided
                $profile_picture = 'default_avatar.png';
                if (!empty($_FILES['profile_picture']['name'])) {
                    $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png']) && is_valid_image_upload($_FILES['profile_picture']['tmp_name'])) {
                        $profile_picture = "profile_" . time() . "_" . rand(1000, 9999) . "." . $ext;
                        move_uploaded_file($_FILES['profile_picture']['tmp_name'], "../uploads/profile/" . $profile_picture);
                    }
                }

                // Student proof validation
                $has_student_proof = true;
                $student_proof_path = '';
                if ($member_type === 'Student') {
                    if (empty($_FILES['student_proof']['name'])) {
                        $has_student_proof = false;
                        $_SESSION['swal_title'] = "Error!";
                        $_SESSION['swal_msg'] = "Student members require a valid proof document (JPG/PNG/JPEG/PDF).";
                        $_SESSION['swal_type'] = "error";
                    } else {
                        $ext = strtolower(pathinfo($_FILES['student_proof']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf']) && is_valid_image_or_pdf_upload($_FILES['student_proof']['tmp_name'], $ext)) {
                            $student_proof_path = "student_" . time() . "_" . rand(1000, 9999) . "." . $ext;
                            $upload_dir = "../uploads/student_proofs/";
                            if (!is_dir($upload_dir)) {
                                @mkdir($upload_dir, 0755, true);
                            }
                            $moved = move_uploaded_file($_FILES['student_proof']['tmp_name'], $upload_dir . $student_proof_path);
                            if (!$moved) {
                                $has_student_proof = false;
                                $_SESSION['swal_title'] = "Error!";
                                $_SESSION['swal_msg'] = "Failed to save the student proof file. Please check that the uploads/student_proofs folder exists and is writable, then try again.";
                                $_SESSION['swal_type'] = "error";
                            }
                        } else {
                            $has_student_proof = false;
                            $_SESSION['swal_title'] = "Error!";
                            $_SESSION['swal_msg'] = "Invalid student proof file format. Only JPG, JPEG, PNG, or PDF allowed.";
                            $_SESSION['swal_type'] = "error";
                        }
                    }
                }

                if ($has_student_proof) {
                    $member_code = "ATZ-" . rand(10000, 99999);
                    $stmt = mysqli_prepare($conn, "INSERT INTO members (member_code, first_name, last_name, gender, birthdate, age, email, contact_no, member_type, profile_picture, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active')");
                    mysqli_stmt_bind_param($stmt, "sssssissss", $member_code, $first_name, $last_name, $gender, $birthdate, $age, $email, $contact_no, $member_type, $profile_picture);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $new_member_id = mysqli_insert_id($conn);

                        // Insert student doc record if student
                        if ($member_type === 'Student') {
                            $doc_stmt = mysqli_prepare($conn, "INSERT INTO student_documents (member_id, document_type, file_path, file_name, status) VALUES (?, 'School ID', ?, ?, 'Approved')");
                            mysqli_stmt_bind_param($doc_stmt, "iss", $new_member_id, $student_proof_path, $_FILES['student_proof']['name']);
                            mysqli_stmt_execute($doc_stmt);
                            mysqli_stmt_close($doc_stmt);
                        }

                        log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Add Member', "Registered new member {$member_code} ({$first_name} {$last_name})");

                        $_SESSION['swal_title'] = "Success!";
                        $_SESSION['swal_msg'] = "Member successfully registered with Code: " . $member_code;
                        $_SESSION['swal_type'] = "success";
                        header("Location: members.php");
                        exit();
                    }
                    mysqli_stmt_close($stmt);
                }
            }
            mysqli_stmt_close($check_email);
        }
    }

}

// Search & Filter Query Construction
$search = sanitize($_GET['search'] ?? '');
$filter_type = sanitize($_GET['filter_type'] ?? '');
$filter_status = sanitize($_GET['filter_status'] ?? '');

$query = "SELECT m.*, (SELECT end_date FROM memberships WHERE member_id = m.id ORDER BY end_date DESC LIMIT 1) as end_date FROM members m WHERE 1=1";
$types = "";
$params = [];

if (!empty($search)) {
    $query .= " AND (m.member_code LIKE ? OR m.first_name LIKE ? OR m.last_name LIKE ? OR m.email LIKE ?)";
    $like = "%$search%";
    $types .= "ssss";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if (!empty($filter_type)) {
    $query .= " AND m.member_type = ?";
    $types .= "s";
    $params[] = $filter_type;
}
if (!empty($filter_status)) {
    $query .= " AND m.status = ?";
    $types .= "s";
    $params[] = $filter_status;
}

$query .= " ORDER BY m.id DESC";

$stmt = mysqli_prepare($conn, $query);
if ($types !== "") {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$members_res = mysqli_stmt_get_result($stmt);

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <h1 class="h2 fw-bold text-dark mb-1"><i class="bi bi-people-fill text-warning me-2"></i> Members Directory</h1>
        <p class="text-muted mb-0 small"><?php echo number_format(mysqli_num_rows($members_res)); ?> member<?php echo mysqli_num_rows($members_res) == 1 ? '' : 's'; ?> found</p>
    </div>
    <button class="btn btn-warning fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addMemberModal">
        <i class="bi bi-person-plus-fill me-1"></i> Add New Member
    </button>
</div>

<!-- Search & Filter Controls -->
<div class="card p-3 mb-4">
    <form method="GET" action="members.php" class="row g-3 align-items-end">
        <div class="col-md-5">
            <label class="form-label fw-semibold text-muted small mb-1">Search</label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by Code, Name, or Email..." value="<?php echo sanitize($search); ?>">
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold text-muted small mb-1">Member Type</label>
            <select name="filter_type" class="form-select">
                <option value="">All Member Types</option>
                <option value="Regular" <?php echo $filter_type == 'Regular' ? 'selected' : ''; ?>>Regular</option>
                <option value="Student" <?php echo $filter_type == 'Student' ? 'selected' : ''; ?>>Student</option>
                
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold text-muted small mb-1">Status</label>
            <select name="filter_status" class="form-select">
                <option value="">All Statuses</option>
                <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                <option value="Expired" <?php echo $filter_status == 'Expired' ? 'selected' : ''; ?>>Expired</option>
                <option value="Inactive" <?php echo $filter_status == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-dark w-100" title="Apply filters"><i class="bi bi-filter"></i></button>
        </div>
    </form>
</div>

<!-- Members Table -->
<div class="card p-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Member Code</th>
                    <th>Member Name</th>
                    <th>Contact & Email</th>
                    <th>Age / Gender</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($members_res) > 0): ?>
                    <?php while ($m = mysqli_fetch_assoc($members_res)): ?>
                        <tr>
                            <td class="fw-bold text-warning bg-dark rounded px-2 py-1 text-center font-monospace" style="display: inline-block;">
                                <?php echo sanitize($m['member_code']); ?>
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php if (!empty($m['profile_picture']) && file_exists('../uploads/profile/' . $m['profile_picture'])): ?>
                                        <img src="../uploads/profile/<?php echo sanitize($m['profile_picture']); ?>" alt="Profile Photo" class="rounded-circle me-2" style="width: 38px; height: 38px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary me-2 text-white d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                                            <i class="bi bi-person-fill"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold"><?php echo sanitize($m['first_name'] . ' ' . $m['last_name']); ?></div>
                                        <span class="text-muted small">ID #<?php echo $m['id']; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="small fw-semibold"><i class="bi bi-telephone-fill text-success me-1"></i><?php echo sanitize($m['contact_no']); ?></div>
                                <div class="small text-muted"><i class="bi bi-envelope-fill text-primary me-1"></i><?php echo sanitize($m['email']); ?></div>
                            </td>
                            <td>
                                <div><?php echo $m['age']; ?> yrs old</div>
                                <span class="badge bg-light text-dark border"><?php echo sanitize($m['gender']); ?></span>
                            </td>
                            <td>
                                <?php 
                                    $type_badge = 'badge-regular';
                                    if ($m['member_type'] === 'Student') $type_badge = 'badge-student';
                                    
                                ?>
                                <span class="badge <?php echo $type_badge; ?> px-3 py-1"><?php echo sanitize($m['member_type']); ?></span>
                            </td>
                            <td>
                                <?php 
                                    $st_badge = 'bg-success';
                                    if ($m['status'] === 'Expired') $st_badge = 'bg-danger';
                                    if ($m['status'] === 'Inactive') $st_badge = 'bg-secondary';
                                ?>
                                <span class="badge <?php echo $st_badge; ?> px-2 py-1"><?php echo sanitize($m['status']); ?></span>
                            </td>
                            <td class="text-end">
                                <a href="member_details.php?id=<?php echo $m['id']; ?>" class="btn btn-sm btn-outline-dark" title="View Full Profile">
                                    <i class="bi bi-eye-fill me-1"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            <i class="bi bi-people fs-2 d-block mb-2 text-body-tertiary"></i>
                            No members found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i> Register New Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="members.php" enctype="multipart/form-data" data-validate="true">
            <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_member">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">First Name </label>
                            <input type="text" name="first_name" id="first_name" class="form-control" d  pattern="[A-Z][A-Za-z\s\.\-']*" minlength="2" maxlength="50" title="Must start with a capital letter — letters, spaces, and . - ' only.">
                            <div ></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Last Name </label>
                            <input type="text" name="last_name" id="last_name" class="form-control" required placeholder="e.g. Pacinio" pattern="[A-Z][A-Za-z\s\.\-']*" minlength="2" maxlength="50" title="Must start with a capital letter — letters, spaces, and . - ' only.">
                            <div></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender </label>
                            <select name="gender" class="form-select" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth (13-80 yrs) </label>
                            <input type="date" name="birthdate" id="birthdate" class="form-control" required>
                            <div class="invalid-feedback" id="birthdate_error">Member age must be between 13 and 80 years old.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Member Type </label>
                            <select name="member_type" id="member_type" class="form-select" required>
                                <option value="Regular">Regular</option>
                                <option value="Student">Student (Requires Proof)</option>
                                
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email Address </label>
                            <input type="email" name="email" id="member_email" class="form-control" required
                                   pattern="[a-zA-Z0-9._%+\-]+@gmail\.com"
                                   title="Enter a valid @gmail.com address (e.g. johnloyd@gmail.com)."
                                   data-gmail-only="true"
                                   >
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contact No. </label>
                            <input type="text" name="contact_no" id="contact_no" class="form-control" maxlength="11"
                                   inputmode="numeric" pattern="09[0-9]{9}"
                                   title="Contact number must start with 09 and contain 11 digits."
                                   required>
                            <div class="invalid-feedback" id="contact_no_error">Contact number must start with 09 and contain 11 digits (e.g. 09123456789).</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Profile Photo (Optional)</label>
                            <input type="file" name="profile_picture" class="form-control" accept="image/png, image/jpeg, image/jpg">
                        </div>
                        <div class="col-md-6 d-none" id="student_proof_container">
                            <label class="form-label fw-semibold text-danger">Student Proof (School ID/Registration) </label>
                            <input type="file" name="student_proof" id="student_proof_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                            <div class="form-text text-muted">Required for Student rate verification (JPG/PNG/PDF).</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold px-4"><i class="bi bi-save-fill me-1"></i> Save Member</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    // ---- Live validation (same approach used in staff/profile.php) ----
    // Each field gets: an is-invalid toggle, a setCustomValidity() message,
    // and reportValidity() while typing (never on blur, so the field
    // doesn't refocus itself and trap the user).

    // First / Last Name: must start with a capital letter and contain
    // letters only (spaces, . - ' allowed as separators).
    var nameFields = [document.getElementById('first_name'), document.getElementById('last_name')];
    nameFields.forEach(function(input) {
        if (!input) return;
        var invalidCharPattern = /[^A-Za-z\s.]/;

        function validateName(isBlur) {
            var val = input.value.trim();
            var valid = /^[A-Z][A-Za-z\s.]{1,49}$/.test(val);
            var startsLower = val.length > 0 && /^[a-z]/.test(val);
            var hasInvalidChar = invalidCharPattern.test(val);

            var showError = val.length > 0 && !valid && (hasInvalidChar || val.length > 4 || isBlur === true);

            input.classList.toggle('is-invalid', showError);

            if (!showError) {
                input.setCustomValidity('');
            } else if (hasInvalidChar) {
                input.setCustomValidity('Only letters, spaces, and . - \' are allowed — no numbers or symbols.');
            } else if (startsLower) {
                input.setCustomValidity('Must start with an uppercase letter.');
            } else {
                input.setCustomValidity('Enter a valid name of more than one character, using letters only.');
            }

            if (showError && !isBlur) {
                input.reportValidity();
            }
        }
        input.addEventListener('input', function() { validateName(false); });
        input.addEventListener('blur', function() { validateName(true); });
    });

    // Date of Birth: live validation, age must be between 13 and 80 years old
    var birthdateInput = document.getElementById('birthdate');
    if (birthdateInput) {
        var today = new Date();
        var maxDob = new Date(today.getFullYear() - 13, today.getMonth(), today.getDate());
        var minDob = new Date(today.getFullYear() - 80, today.getMonth(), today.getDate());
        birthdateInput.max = maxDob.toISOString().split('T')[0];
        birthdateInput.min = minDob.toISOString().split('T')[0];

        function validateBirthdate() {
            var val = birthdateInput.value;
            if (!val) {
                birthdateInput.classList.remove('is-invalid');
                birthdateInput.setCustomValidity('');
                return;
            }
            var dob = new Date(val);
            var now = new Date();
            var age = now.getFullYear() - dob.getFullYear();
            var m = now.getMonth() - dob.getMonth();
            if (m < 0 || (m === 0 && now.getDate() < dob.getDate())) age--;
            var valid = age >= 13 && age <= 80;
            birthdateInput.classList.toggle('is-invalid', !valid);
            birthdateInput.setCustomValidity(valid ? '' : 'Member age must be between 13 and 80 years old.');
            if (!valid) {
                birthdateInput.reportValidity();
            }
        }
        // A date field is only ever "complete" once a full date is picked
        // (there's no partial-typing state like text fields), so validate
        // on change/blur only — not on every keystroke/input event.
        birthdateInput.addEventListener('change', validateBirthdate);
        birthdateInput.addEventListener('blur', validateBirthdate);
    }

    // Email: live validation, must be a valid @gmail.com address
    var emailInput = document.getElementById('member_email');
    if (emailInput) {
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
                emailInput.setCustomValidity('Enter a valid @gmail.com address (e.g. juan@gmail.com).');
            }

            if (showError && !isBlur) {
                emailInput.reportValidity();
            }
        }
        emailInput.addEventListener('input', function() { validateEmail(false); });
        emailInput.addEventListener('blur', function() { validateEmail(true); });
    }

    // Contact No: live validation, must start with 09 and have exactly 11 digits
    var contactInput = document.getElementById('contact_no');
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

    // Final safety net: re-check everything on submit so an incomplete or
    // invalid entry (empty field, pasted text, autofill, etc.) can never
    // slip through — nothing saves until every field is actually filled
    // in and valid.
    var addMemberForm = document.querySelector('#addMemberModal form');
    if (addMemberForm) {
        addMemberForm.addEventListener('submit', function(e) {
            nameFields.forEach(function(input) {
                if (input) input.dispatchEvent(new Event('blur'));
            });
            if (birthdateInput) birthdateInput.dispatchEvent(new Event('blur'));
            if (emailInput) emailInput.dispatchEvent(new Event('blur'));
            if (contactInput) contactInput.dispatchEvent(new Event('blur'));

            var firstInvalid = addMemberForm.querySelector('.is-invalid');
            if (!addMemberForm.checkValidity() || firstInvalid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var target = firstInvalid || addMemberForm.querySelector(':invalid');
                if (target) {
                    target.reportValidity();
                    target.focus();
                }
            }
        });
    }
})();
</script>

<?php require_once '../includes/footer.php'; ?>