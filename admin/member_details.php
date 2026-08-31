<?php
/**
 * ATZ Fitness Gym Management System
 * Member Profile Details View
 */

$page_title = "Member Details";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

$member_id = intval($_GET['id'] ?? 0);

if ($member_id <= 0) {
    header("Location: members.php");
    exit();
}

// Toggle Active / Inactive status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verify_csrf();
    $current_stmt = mysqli_prepare($conn, "SELECT status, first_name, last_name FROM members WHERE id = ?");
    mysqli_stmt_bind_param($current_stmt, "i", $member_id);
    mysqli_stmt_execute($current_stmt);
    $current = mysqli_fetch_assoc(mysqli_stmt_get_result($current_stmt));
    mysqli_stmt_close($current_stmt);

    if ($current) {
        $new_status = ($current['status'] === 'Active') ? 'Inactive' : 'Active';
        $upd_stmt = mysqli_prepare($conn, "UPDATE members SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, "si", $new_status, $member_id);
        if (mysqli_stmt_execute($upd_stmt)) {

            $membership_note = '';

            if ($new_status === 'Inactive') {
                // Member deactivated -> pause their current active membership.
                // Freeze the remaining days and stop the expiration countdown.
                $mship_stmt = mysqli_prepare($conn, "SELECT id, end_date FROM memberships WHERE member_id = ? AND status = 'Active' ORDER BY end_date DESC LIMIT 1");
                mysqli_stmt_bind_param($mship_stmt, "i", $member_id);
                mysqli_stmt_execute($mship_stmt);
                $mship = mysqli_fetch_assoc(mysqli_stmt_get_result($mship_stmt));
                mysqli_stmt_close($mship_stmt);

                if ($mship) {
                    $remaining_days = (int) floor((strtotime($mship['end_date']) - strtotime(date('Y-m-d'))) / 86400);
                    if ($remaining_days < 0) $remaining_days = 0;

                    $pause_stmt = mysqli_prepare($conn, "UPDATE memberships SET status = 'Inactive', paused_remaining_days = ?, paused_at = CURDATE() WHERE id = ?");
                    mysqli_stmt_bind_param($pause_stmt, "ii", $remaining_days, $mship['id']);
                    mysqli_stmt_execute($pause_stmt);
                    mysqli_stmt_close($pause_stmt);

                    $membership_note = " Membership paused with {$remaining_days} day(s) remaining.";
                }
            } else {
                // Member reactivated -> resume the paused membership.
                // Push the expiration date forward by the frozen remaining days.
                $mship_stmt = mysqli_prepare($conn, "SELECT id, paused_remaining_days FROM memberships WHERE member_id = ? AND status = 'Inactive' AND paused_remaining_days IS NOT NULL ORDER BY id DESC LIMIT 1");
                mysqli_stmt_bind_param($mship_stmt, "i", $member_id);
                mysqli_stmt_execute($mship_stmt);
                $mship = mysqli_fetch_assoc(mysqli_stmt_get_result($mship_stmt));
                mysqli_stmt_close($mship_stmt);

                if ($mship) {
                    $new_end_date = date('Y-m-d', strtotime("+{$mship['paused_remaining_days']} days"));

                    $resume_stmt = mysqli_prepare($conn, "UPDATE memberships SET status = 'Active', end_date = ?, paused_remaining_days = NULL, paused_at = NULL WHERE id = ?");
                    mysqli_stmt_bind_param($resume_stmt, "si", $new_end_date, $mship['id']);
                    mysqli_stmt_execute($resume_stmt);
                    mysqli_stmt_close($resume_stmt);

                    $membership_note = " Membership resumed - new expiration date: {$new_end_date}.";
                }
            }

            log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Member Status Change', "Set {$current['first_name']} {$current['last_name']} to {$new_status}.{$membership_note}");
            $_SESSION['swal_title'] = "Updated!";
            $_SESSION['swal_msg'] = "Member marked as {$new_status}.{$membership_note}";
            $_SESSION['swal_type'] = "success";
        }
        mysqli_stmt_close($upd_stmt);
    }
    header("Location: member_details.php?id=" . $member_id);
    exit();
}

// Fetch member data
$stmt = mysqli_prepare($conn, "SELECT * FROM members WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $member_id);
mysqli_stmt_execute($stmt);
$member = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$member) {
    header("Location: members.php");
    exit();
}

// Fetch memberships history
$memberships_res = mysqli_query($conn, "SELECT m.*, p.plan_name FROM memberships m JOIN membership_plans p ON m.plan_id = p.id WHERE m.member_id = $member_id ORDER BY m.id DESC");

// Fetch payment history
$payments_res = mysqli_query($conn, "SELECT * FROM payments WHERE member_id = $member_id ORDER BY id DESC");

// Fetch attendance history
$attendance_res = mysqli_query($conn, "SELECT * FROM attendance WHERE member_id = $member_id ORDER BY id DESC LIMIT 10");

// Fetch student documents if any
$docs_res = mysqli_query($conn, "SELECT * FROM student_documents WHERE member_id = $member_id");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="mb-4">
    <a href="members.php" class="btn btn-outline-dark btn-sm mb-3"><i class="bi bi-arrow-left me-1"></i> Back to Members Directory</a>
    <div class="d-flex justify-content-between align-items-center">
        <h2 class="fw-bold text-dark mb-0">Member Profile: <?php echo sanitize($member['first_name'] . ' ' . $member['last_name']); ?></h2>
        <span class="badge bg-dark text-warning fs-5 px-3 py-2 font-monospace"><?php echo sanitize($member['member_code']); ?></span>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card p-4 text-center">
            <?php if (!empty($member['profile_picture']) && file_exists('../uploads/profile/' . $member['profile_picture'])): ?>
                <img src="../uploads/profile/<?php echo sanitize($member['profile_picture']); ?>" alt="Profile Photo" class="rounded-circle mx-auto mb-3 d-block border" style="width: 100px; height: 100px; object-fit: cover;">
            <?php else: ?>
                <div class="bg-secondary rounded-circle text-white d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2.5rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
            <?php endif; ?>
            <h4 class="fw-bold mb-1"><?php echo sanitize($member['first_name'] . ' ' . $member['last_name']); ?></h4>
            <div class="mb-3">
                <span class="badge bg-warning text-dark px-3 py-1 fw-bold me-1"><?php echo sanitize($member['member_type']); ?></span>
                <?php
                    $status_badge = 'bg-success';
                    if ($member['status'] === 'Inactive') $status_badge = 'bg-secondary';
                    if ($member['status'] === 'Expired') $status_badge = 'bg-danger';
                ?>
                <span class="badge <?php echo $status_badge; ?> px-3 py-1"><?php echo sanitize($member['status']); ?></span>
            </div>

            <form method="POST" action="member_details.php?id=<?php echo $member_id; ?>" class="confirm-submit"
                  data-confirm-title="<?php echo $member['status'] === 'Active' ? 'Deactivate this member?' : 'Reactivate this member?'; ?>"
                  data-confirm-text="<?php echo $member['status'] === 'Active' ? 'They will be marked Inactive and filtered out of active member views.' : 'They will be marked Active again.'; ?>"
                  data-confirm-button="Yes, continue">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="toggle_status">
                <?php if ($member['status'] === 'Active'): ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm fw-bold w-100 mb-3">
                        <i class="bi bi-person-dash-fill me-1"></i> Deactivate Member
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn btn-outline-success btn-sm fw-bold w-100 mb-3">
                        <i class="bi bi-person-check-fill me-1"></i> Reactivate Member
                    </button>
                <?php endif; ?>
            </form>

            <a href="edit_member.php?id=<?php echo $member_id; ?>" class="btn btn-warning text-dark fw-bold w-100 mb-3">
                <i class="bi bi-pencil-square me-1"></i> Edit Member
            </a>
            
            <hr>

            <div class="text-start fs-6">
                <p class="mb-2"><strong><i class="bi bi-envelope me-2 text-warning"></i> Email:</strong> <?php echo sanitize($member['email']); ?></p>
                <p class="mb-2"><strong><i class="bi bi-telephone me-2 text-warning"></i> Contact:</strong> <?php echo sanitize($member['contact_no']); ?></p>
                <p class="mb-2"><strong><i class="bi bi-calendar me-2 text-warning"></i> Age / DOB:</strong> <?php echo $member['age']; ?> yrs (<?php echo $member['birthdate']; ?>)</p>
                <p class="mb-2"><strong><i class="bi bi-gender-ambiguous me-2 text-warning"></i> Gender:</strong> <?php echo sanitize($member['gender']); ?></p>
                <p class="mb-0"><strong><i class="bi bi-clock-history me-2 text-warning"></i> Registered:</strong> <?php echo date('M d, Y', strtotime($member['created_at'])); ?></p>
            </div>
        </div>

        <?php if ($member['member_type'] === 'Student' && mysqli_num_rows($docs_res) > 0): ?>
            <div class="card p-3 mt-3">
                <h6 class="fw-bold text-dark mb-2"><i class="bi bi-file-earmark-check-fill text-primary me-2"></i> Student Verification Proof</h6>
                <?php while ($doc = mysqli_fetch_assoc($docs_res)): ?>
                    <div class="border rounded p-2 bg-light d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold small"><?php echo sanitize($doc['file_name']); ?></div>
                            <span class="badge bg-success fs-7">Status: <?php echo sanitize($doc['status']); ?></span>
                        </div>
                        <?php
                            $proof_disk_path = "../uploads/student_proofs/" . $doc['file_path'];
                            $proof_exists = is_file($proof_disk_path);
                        ?>
                        <?php if ($proof_exists): ?>
                            <a href="../uploads/student_proofs/<?php echo sanitize($doc['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> View</a>
                        <?php else: ?>
                            <span class="btn btn-sm btn-outline-danger disabled" title="The file is missing from the server's uploads folder"><i class="bi bi-exclamation-triangle"></i> File Missing</span>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-8">
        <!-- Membership History Card -->
        <div class="card p-3 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-vcard text-warning me-2"></i> Membership Plans History</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>Days Remaining</th>
                            <th>Price Paid</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($memberships_res) > 0): ?>
                            <?php while ($mship = mysqli_fetch_assoc($memberships_res)): ?>
                                <?php
                                    $ms_badge = 'bg-success';
                                    if ($mship['status'] === 'Inactive') $ms_badge = 'bg-secondary';
                                    if ($mship['status'] === 'Expired') $ms_badge = 'bg-danger';
                                    if ($mship['status'] === 'Cancelled') $ms_badge = 'bg-dark';
                                    if ($mship['status'] === 'Expiring Soon') $ms_badge = 'bg-warning text-dark';
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo sanitize($mship['plan_name']); ?></td>
                                    <td><?php echo $mship['start_date']; ?></td>
                                    <td><?php echo $mship['end_date']; ?></td>
                                    <td>
                                        <?php if ($mship['status'] === 'Inactive' && $mship['paused_remaining_days'] !== null): ?>
                                            <span class="text-muted"><i class="bi bi-pause-circle-fill me-1"></i><?php echo $mship['paused_remaining_days']; ?> Days (Paused)</span>
                                        <?php else: ?>
                                            <?php $ms_days = (int) floor((strtotime($mship['end_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
                                            <?php echo $ms_days >= 0 ? $ms_days . ' Days' : 'Expired'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>₱<?php echo number_format($mship['price_paid'], 2); ?></td>
                                    <td><span class="badge <?php echo $ms_badge; ?>"><?php echo sanitize($mship['status']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-muted text-center py-3">No active membership plans logged yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments Card -->
        <div class="card p-3 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-credit-card text-success me-2"></i> Payment Records</h5>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Transaction No</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($payments_res) > 0): ?>
                            <?php while ($pay = mysqli_fetch_assoc($payments_res)): ?>
                                <tr>
                                    <td class="font-monospace fw-bold text-primary"><?php echo sanitize($pay['transaction_no']); ?></td>
                                    <td class="fw-bold text-success">₱<?php echo number_format($pay['amount'], 2); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo sanitize($pay['payment_method']); ?></span></td>
                                    <td class="small text-muted"><?php echo date('M d, Y', strtotime($pay['payment_date'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-muted text-center py-3">No payment records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>