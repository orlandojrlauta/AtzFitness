<?php
/**
 * ATZ Fitness Gym Management System
 * Edit Member Module
 */

$page_title = "Edit Member";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

$member_id = (int)($_GET['id'] ?? $_POST['member_id'] ?? 0);
if ($member_id <= 0) {
    header('Location: members.php');
    exit();
}

function get_member(mysqli $conn, int $id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM members WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $member;
}

$member = get_member($conn, $member_id);
if (!$member) {
    $_SESSION['swal_title'] = 'Not Found';
    $_SESSION['swal_msg'] = 'Member record was not found.';
    $_SESSION['swal_type'] = 'error';
    header('Location: members.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $first_name = trim(preg_replace('/\s+/', ' ', sanitize($_POST['first_name'] ?? '')));
    $last_name = trim(preg_replace('/\s+/', ' ', sanitize($_POST['last_name'] ?? '')));
    $gender = sanitize($_POST['gender'] ?? 'Male');
    $birthdate = sanitize($_POST['birthdate'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $contact_no = sanitize($_POST['contact_no'] ?? '');
    $member_type = sanitize($_POST['member_type'] ?? 'Regular');

    $age = validate_member_age($birthdate);
    $error = '';

    if (!$age) {
        $error = 'Member age must be between 13 and 80 years old.';
    } elseif (!validate_capitalized_name($first_name) || !validate_capitalized_name($last_name)) {
        $error = 'First and last name must start with a capital letter and contain letters only.';
    } elseif (!validate_ph_contact($contact_no)) {
        $error = 'Contact number must start with 09 and contain 11 digits.';
    } elseif (!validate_gmail_email($email)) {
        $error = 'Email address must be a valid @gmail.com address.';
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $error = 'Invalid gender selected.';
    } elseif (!in_array($member_type, ['Regular', 'Student'], true)) {
        $error = 'Invalid member type selected.';
    }

    // Email must remain unique except for the member currently being edited.
    if ($error === '') {
        $stmt = mysqli_prepare($conn, "SELECT id FROM members WHERE email = ? AND id <> ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'si', $email, $member_id);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($exists) {
            $error = 'The email address is already registered to another member.';
        }
    }

    $existing_doc = null;
    if ($error === '') {
        $doc_stmt = mysqli_prepare($conn, "SELECT * FROM student_documents WHERE member_id = ? ORDER BY id DESC LIMIT 1");
        mysqli_stmt_bind_param($doc_stmt, 'i', $member_id);
        mysqli_stmt_execute($doc_stmt);
        $existing_doc = mysqli_fetch_assoc(mysqli_stmt_get_result($doc_stmt));
        mysqli_stmt_close($doc_stmt);

        if ($member_type === 'Student' && !$existing_doc && empty($_FILES['student_proof']['name'])) {
            $error = 'Student members require a valid proof document (JPG/PNG/PDF).';
        }
    }

    $new_profile_picture = $member['profile_picture'];
    $new_student_file = null;
    $new_student_original = null;

    if ($error === '' && !empty($_FILES['profile_picture']['name'])) {
        if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $error = 'The profile photo could not be uploaded.';
        } else {
            $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png'], true) || !is_valid_image_upload($_FILES['profile_picture']['tmp_name'])) {
                $error = 'Invalid profile photo. Only JPG, JPEG, or PNG image files are allowed.';
            } else {
                $new_profile_picture = 'profile_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
                if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], '../uploads/profile/' . $new_profile_picture)) {
                    $error = 'Failed to save the new profile photo.';
                    $new_profile_picture = $member['profile_picture'];
                }
            }
        }
    }

    if ($error === '' && $member_type === 'Student' && !$existing_doc && !empty($_FILES['student_proof']['name'])) {
        if ($_FILES['student_proof']['error'] !== UPLOAD_ERR_OK) {
            $error = 'The student proof could not be uploaded.';
        } else {
            $ext = strtolower(pathinfo($_FILES['student_proof']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true) || !is_valid_image_or_pdf_upload($_FILES['student_proof']['tmp_name'], $ext)) {
                $error = 'Invalid student proof. Only JPG, JPEG, PNG, or PDF files are allowed.';
            } else {
                $new_student_file = 'student_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
                $upload_dir = '../uploads/student_proofs/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                if (!move_uploaded_file($_FILES['student_proof']['tmp_name'], $upload_dir . $new_student_file)) {
                    $error = 'Failed to save the student proof file.';
                    $new_student_file = null;
                } else {
                    $new_student_original = $_FILES['student_proof']['name'];
                }
            }
        }
    }

    if ($error === '') {
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "UPDATE members SET first_name = ?, last_name = ?, gender = ?, birthdate = ?, age = ?, email = ?, contact_no = ?, member_type = ?, profile_picture = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ssssissssi', $first_name, $last_name, $gender, $birthdate, $age, $email, $contact_no, $member_type, $new_profile_picture, $member_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to update the member record.');
            }
            mysqli_stmt_close($stmt);

            if ($member_type === 'Student') {
                if ($new_student_file) {
                    if ($existing_doc) {
                        $doc_stmt = mysqli_prepare($conn, "UPDATE student_documents SET file_path = ?, file_name = ?, status = 'Approved' WHERE id = ?");
                        mysqli_stmt_bind_param($doc_stmt, 'ssi', $new_student_file, $new_student_original, $existing_doc['id']);
                    } else {
                        $doc_stmt = mysqli_prepare($conn, "INSERT INTO student_documents (member_id, document_type, file_path, file_name, status) VALUES (?, 'School ID', ?, ?, 'Approved')");
                        mysqli_stmt_bind_param($doc_stmt, 'iss', $member_id, $new_student_file, $new_student_original);
                    }
                    if (!mysqli_stmt_execute($doc_stmt)) {
                        mysqli_stmt_close($doc_stmt);
                        throw new Exception('Failed to save the student proof record.');
                    }
                    mysqli_stmt_close($doc_stmt);
                }
            } else {
                // Switching Student -> Regular removes the student proof record and file.
                $docs_stmt = mysqli_prepare($conn, "SELECT file_path FROM student_documents WHERE member_id = ?");
                mysqli_stmt_bind_param($docs_stmt, 'i', $member_id);
                mysqli_stmt_execute($docs_stmt);
                $docs = mysqli_stmt_get_result($docs_stmt);
                while ($doc = mysqli_fetch_assoc($docs)) {
                    $path = '../uploads/student_proofs/' . basename($doc['file_path']);
                    if (is_file($path)) @unlink($path);
                }
                mysqli_stmt_close($docs_stmt);

                $delete_docs = mysqli_prepare($conn, "DELETE FROM student_documents WHERE member_id = ?");
                mysqli_stmt_bind_param($delete_docs, 'i', $member_id);
                mysqli_stmt_execute($delete_docs);
                mysqli_stmt_close($delete_docs);
            }

            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Update Member', "Updated member {$member['member_code']} ({$first_name} {$last_name})");
            mysqli_commit($conn);

            // Remove old profile photo only after successful database update.
            if ($new_profile_picture !== $member['profile_picture'] && $member['profile_picture'] && $member['profile_picture'] !== 'default_avatar.png') {
                $old_photo = '../uploads/profile/' . basename($member['profile_picture']);
                if (is_file($old_photo)) @unlink($old_photo);
            }

            $_SESSION['swal_title'] = 'Updated!';
            $_SESSION['swal_msg'] = 'Member information was successfully updated.';
            $_SESSION['swal_type'] = 'success';
            header('Location: member_details.php?id=' . $member_id);
            exit();
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            if ($new_profile_picture !== $member['profile_picture']) {
                $new_photo = '../uploads/profile/' . basename($new_profile_picture);
                if (is_file($new_photo)) @unlink($new_photo);
            }
            if ($new_student_file) {
                $new_doc = '../uploads/student_proofs/' . basename($new_student_file);
                if (is_file($new_doc)) @unlink($new_doc);
            }
            $error = $e->getMessage();
        }
    }

    if ($error !== '') {
        $_SESSION['swal_title'] = 'Error!';
        $_SESSION['swal_msg'] = $error;
        $_SESSION['swal_type'] = 'error';
        $member = array_merge($member, [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'gender' => $gender,
            'birthdate' => $birthdate,
            'age' => $age ?: $member['age'],
            'email' => $email,
            'contact_no' => $contact_no,
            'member_type' => $member_type,
            'profile_picture' => $new_profile_picture
        ]);
    }
}

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between align-items-center pt-2 pb-3 mb-4 border-bottom">
    <div>
        <a href="member_details.php?id=<?php echo $member_id; ?>" class="btn btn-outline-dark btn-sm mb-2">
            <i class="bi bi-arrow-left me-1"></i> Back to Member Profile
        </a>
        <h1 class="h2 fw-bold text-dark mb-0"><i class="bi bi-pencil-square text-warning me-2"></i> Edit Member</h1>
    </div>
    <span class="badge bg-dark text-warning fs-6 px-3 py-2 font-monospace"><?php echo sanitize($member['member_code']); ?></span>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-dark text-warning fw-bold">Update Member Information</div>
    <form method="POST" action="edit_member.php?id=<?php echo $member_id; ?>" enctype="multipart/form-data" data-validate="true">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
        <div class="card-body p-4">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">First Name</label>
                    <input type="text" name="first_name" id="edit_first_name" class="form-control" required maxlength="50" pattern="[A-Z][A-Za-z\s\.\-']*" title="Must start with a capital letter — letters, spaces, and . - ' only." value="<?php echo sanitize($member['first_name']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Last Name</label>
                    <input type="text" name="last_name" id="edit_last_name" class="form-control" required maxlength="50" pattern="[A-Z][A-Za-z\s\.\-']*" title="Must start with a capital letter — letters, spaces, and . - ' only." value="<?php echo sanitize($member['last_name']); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Gender</label>
                    <select name="gender" class="form-select" required>
                        <?php foreach (['Male','Female','Other'] as $g): ?>
                            <option value="<?php echo $g; ?>" <?php echo $member['gender'] === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Date of Birth</label>
                    <input type="date" name="birthdate" id="edit_birthdate" class="form-control" required value="<?php echo sanitize($member['birthdate']); ?>">
                    <div class="form-text">Age must be between 13 and 80.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Member Type</label>
                    <select name="member_type" id="edit_member_type" class="form-select" required>
                        <option value="Regular" <?php echo $member['member_type'] === 'Regular' ? 'selected' : ''; ?>>Regular</option>
                        <option value="Student" <?php echo $member['member_type'] === 'Student' ? 'selected' : ''; ?>>Student</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Gmail Address</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required value="<?php echo sanitize($member['email']); ?>" pattern="[a-zA-Z0-9._%+\-]+@gmail\.com" title="Enter a valid @gmail.com address (e.g. juan@gmail.com).">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Contact Number</label>
                    <input type="text" name="contact_no" id="edit_contact_no" class="form-control" required maxlength="11" inputmode="numeric" pattern="09[0-9]{9}" title="Contact number must start with 09 and contain 11 digits." value="<?php echo sanitize($member['contact_no']); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Replace Profile Photo (Optional)</label>
                    <input type="file" name="profile_picture" class="form-control" accept="image/png,image/jpeg,image/jpg">
                </div>
                <div class="col-md-6 <?php echo $member['member_type'] === 'Student' ? '' : 'd-none'; ?>" id="edit_student_proof_container">
                    <label class="form-label fw-semibold text-danger">Replace Student Proof (Optional if proof already exists)</label>
                    <input type="file" name="student_proof" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    <div class="form-text">Required only when changing to Student and no existing proof is on file.</div>
                </div>
            </div>
        </div>
        <div class="card-footer bg-light d-flex justify-content-end gap-2">
            <a href="member_details.php?id=<?php echo $member_id; ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-warning text-dark fw-bold"><i class="bi bi-save-fill me-1"></i> Save Changes</button>
        </div>
    </form>
</div>

<script>
(function() {
    const type = document.getElementById('edit_member_type');
    const proof = document.getElementById('edit_student_proof_container');
    if (type && proof) {
        function toggleProof() {
            proof.classList.toggle('d-none', type.value !== 'Student');
        }
        type.addEventListener('change', toggleProof);
        toggleProof();
    }

    // ---- Live validation (same approach used in staff/profile.php and
    // the Add Member form in members.php) ----
    // Each field gets: an is-invalid toggle, a setCustomValidity()
    // message, and reportValidity() while typing (never on blur, so the
    // field doesn't refocus itself and trap the user).

    // First / Last Name: must start with a capital letter and contain
    // letters only (spaces, . - ' allowed as separators).
    var nameFields = [document.getElementById('edit_first_name'), document.getElementById('edit_last_name')];
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
                input.setCustomValidity('Only letters, spaces, and . are allowed — no numbers or symbols.');
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
    var birthdateInput = document.getElementById('edit_birthdate');
    if (birthdateInput) {
        var today = new Date();
        var maxDob = new Date(today.getFullYear() - 13, today.getMonth(), today.getDate());
        var minDob = new Date(today.getFullYear() - 80, today.getMonth(), today.getDate());
        var maxDobStr = maxDob.toISOString().split('T')[0];
        var minDobStr = minDob.toISOString().split('T')[0];
        birthdateInput.max = maxDobStr;
        birthdateInput.min = minDobStr;

        function toastRangeFix(msg) {
            if (window.Swal) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'warning',
                    title: msg,
                    showConfirmButton: false,
                    timer: 2500,
                    timerProgressBar: true
                });
            }
        }

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

            // A date can only be typed out-of-range here (the calendar
            // picker already disables anything outside min/max) — so as
            // soon as a full invalid date lands, snap it straight to the
            // nearest valid boundary instead of letting it sit there red.
            if (!valid) {
                if (age > 80) {
                    birthdateInput.value = minDobStr;
                    toastRangeFix('That date makes the member over 80 — set to the oldest allowed birthdate.');
                } else {
                    birthdateInput.value = maxDobStr;
                    toastRangeFix('That date makes the member under 13 — set to the youngest allowed birthdate.');
                }
                valid = true;
            }

            birthdateInput.classList.toggle('is-invalid', !valid);
            birthdateInput.setCustomValidity(valid ? '' : 'Member age must be between 13 and 80 years old.');
        }
        // Only correct once the date is actually settled — on 'change'
        // (fires when a full date is picked or typing moves off the
        // field) and 'blur'. NOT on 'input': that fires on every
        // keystroke as each segment fills in, which would yank the value
        // out from under someone still in the middle of typing a normal
        // date like 09/08/2006.
        birthdateInput.addEventListener('change', validateBirthdate);
        birthdateInput.addEventListener('blur', validateBirthdate);
    }

    // Email: live validation, must be a valid @gmail.com address
    var emailInput = document.getElementById('edit_email');
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
        validateEmail(true); // flag an already-bad stored value as soon as the page loads
    }

    // Contact No: live validation, must start with 09 and have exactly 11 digits
    var contactInput = document.getElementById('edit_contact_no');
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
        validateContact(true); // flag an already-bad stored value as soon as the page loads
    }

    // Final safety net: re-check everything on submit so an incomplete or
    // invalid entry (empty field, pasted text, autofill, etc.) can never
    // slip through — nothing saves until every field is actually filled
    // in and valid.
    var editMemberForm = document.querySelector('form[action^="edit_member.php"]');
    if (editMemberForm) {
        editMemberForm.addEventListener('submit', function(e) {
            nameFields.forEach(function(input) {
                if (input) input.dispatchEvent(new Event('blur'));
            });
            if (birthdateInput) birthdateInput.dispatchEvent(new Event('blur'));
            if (emailInput) emailInput.dispatchEvent(new Event('blur'));
            if (contactInput) contactInput.dispatchEvent(new Event('blur'));

            var firstInvalid = editMemberForm.querySelector('.is-invalid');
            if (!editMemberForm.checkValidity() || firstInvalid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var target = firstInvalid || editMemberForm.querySelector(':invalid');
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