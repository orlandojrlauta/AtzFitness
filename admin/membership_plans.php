<?php
/**
 * ATZ Fitness Gym Management System
 * Membership Plans Module
 */

$page_title = "Membership Plans";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Only Administrators may create or delete plans
    if (($action === 'create_plan' || $action === 'delete_plan') && $_SESSION['role'] !== 'Administrator') {
        $_SESSION['swal_title'] = "Access Denied";
        $_SESSION['swal_msg'] = "Only Administrators can manage membership plans.";
        $_SESSION['swal_type'] = "error";
        header("Location: membership_plans.php");
        exit();
    }

    if ($action === 'create_plan') {
        $plan_name = sanitize($_POST['plan_name'] ?? '');
        $category = sanitize($_POST['category'] ?? 'Regular');
        $duration_days = intval($_POST['duration_days'] ?? 30);
        $price = floatval($_POST['price'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');

        if (!empty($plan_name) && $duration_days > 0 && $price > 0) {
            $stmt = mysqli_prepare($conn, "INSERT INTO membership_plans (plan_name, category, duration_days, price, description, status) VALUES (?, ?, ?, ?, ?, 'Active')");
            mysqli_stmt_bind_param($stmt, "ssids", $plan_name, $category, $duration_days, $price, $description);
            
            if (mysqli_stmt_execute($stmt)) {
                log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Add Plan', "Created membership plan: {$plan_name} (₱{$price})");
                $_SESSION['swal_title'] = "Success!";
                $_SESSION['swal_msg'] = "Membership plan added successfully!";
                $_SESSION['swal_type'] = "success";
            }
            mysqli_stmt_close($stmt);
        }
        header("Location: membership_plans.php");
        exit();
    }

    if ($action === 'edit_plan') {
        $plan_id = intval($_POST['plan_id'] ?? 0);
        $plan_name = sanitize($_POST['plan_name'] ?? '');
        $category = sanitize($_POST['category'] ?? 'Regular');
        $duration_days = intval($_POST['duration_days'] ?? 30);
        $price = floatval($_POST['price'] ?? 0);
        $description = sanitize($_POST['description'] ?? '');
        $status = sanitize($_POST['status'] ?? 'Active');

        if ($plan_id > 0 && !empty($plan_name) && $duration_days > 0 && $price > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE membership_plans SET plan_name = ?, category = ?, duration_days = ?, price = ?, description = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssidssi", $plan_name, $category, $duration_days, $price, $description, $status, $plan_id);

            if (mysqli_stmt_execute($stmt)) {
                log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Edit Plan', "Updated membership plan: {$plan_name} (₱{$price})");
                $_SESSION['swal_title'] = "Success!";
                $_SESSION['swal_msg'] = "Membership plan updated successfully!";
                $_SESSION['swal_type'] = "success";
            } else {
                $_SESSION['swal_title'] = "Error!";
                $_SESSION['swal_msg'] = "Something went wrong. Please try again.";
                $_SESSION['swal_type'] = "error";
            }
            mysqli_stmt_close($stmt);
        } else {
            $_SESSION['swal_title'] = "Error!";
            $_SESSION['swal_msg'] = "Please check all fields and try again.";
            $_SESSION['swal_type'] = "error";
        }
        header("Location: membership_plans.php");
        exit();
    }

    if ($action === 'delete_plan') {
        $plan_id = intval($_POST['plan_id'] ?? 0);

        if ($plan_id > 0) {
            // Get plan name first for the activity log
            $name_stmt = mysqli_prepare($conn, "SELECT plan_name FROM membership_plans WHERE id = ?");
            mysqli_stmt_bind_param($name_stmt, "i", $plan_id);
            mysqli_stmt_execute($name_stmt);
            $name_row = mysqli_fetch_assoc(mysqli_stmt_get_result($name_stmt));
            mysqli_stmt_close($name_stmt);

            $stmt = mysqli_prepare($conn, "DELETE FROM membership_plans WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $plan_id);

            if (mysqli_stmt_execute($stmt)) {
                $plan_name = $name_row['plan_name'] ?? "ID {$plan_id}";
                log_activity($conn, $_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], 'Delete Plan', "Deleted membership plan: {$plan_name}");
                $_SESSION['swal_title'] = "Deleted!";
                $_SESSION['swal_msg'] = "Membership plan removed successfully!";
                $_SESSION['swal_type'] = "success";
            } else {
                // Most likely a foreign key restriction: memberships still reference this plan
                $_SESSION['swal_title'] = "Cannot Delete";
                $_SESSION['swal_msg'] = "This plan can't be deleted because members are currently enrolled in it. Set its status to Inactive instead, or reassign those members first.";
                $_SESSION['swal_type'] = "error";
            }
            mysqli_stmt_close($stmt);
        }
        header("Location: membership_plans.php");
        exit();
    }
}

$plans_res = mysqli_query($conn, "SELECT * FROM membership_plans ORDER BY id ASC");

require_once '../includes/header.php';
require_once '../includes/sidebar.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">
    <h1 class="h2 fw-bold text-dark"><i class="bi bi-card-checklist text-warning me-2"></i> Membership Plans</h1>
    <?php if ($_SESSION['role'] === 'Administrator'): ?>
    <button class="btn btn-warning text-dark fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addPlanModal">
        <i class="bi bi-plus-circle-fill me-1"></i> Create New Plan
    </button>
    <?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <?php while ($plan = mysqli_fetch_assoc($plans_res)): ?>
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 p-3 border-top border-4 <?php echo $plan['category'] == 'VIP' ? 'border-primary' : ($plan['category'] == 'Student' ? 'border-info' : 'border-warning'); ?>">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-dark text-warning"><?php echo sanitize($plan['category']); ?></span>
                    <span class="badge <?php echo $plan['status'] == 'Active' ? 'bg-success' : 'bg-secondary'; ?>"><?php echo sanitize($plan['status']); ?></span>
                </div>
                <h4 class="fw-bold mb-1"><?php echo sanitize($plan['plan_name']); ?></h4>
                <div class="my-3">
                    <span class="display-6 fw-bold text-dark">₱<?php echo number_format($plan['price'], 2); ?></span>
                    <span class="text-muted small">/ <?php echo $plan['duration_days']; ?> Days</span>
                </div>
                <p class="text-muted small flex-grow-1"><?php echo sanitize($plan['description']); ?></p>
                <div class="pt-2 border-top mt-2 text-center">
                    <span class="badge bg-light text-dark border w-100 py-2"><i class="bi bi-calendar-check me-1"></i> Validity: <?php echo $plan['duration_days']; ?> Days</span>
                </div>
                <?php if ($_SESSION['role'] === 'Administrator'): ?>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-outline-warning btn-sm w-50 edit-plan-btn"
                            data-id="<?php echo $plan['id']; ?>"
                            data-plan-name="<?php echo sanitize($plan['plan_name']); ?>"
                            data-category="<?php echo sanitize($plan['category']); ?>"
                            data-duration-days="<?php echo (int)$plan['duration_days']; ?>"
                            data-price="<?php echo (float)$plan['price']; ?>"
                            data-description="<?php echo sanitize($plan['description']); ?>"
                            data-status="<?php echo sanitize($plan['status']); ?>">
                        <i class="bi bi-pencil-fill me-1"></i> Edit
                    </button>
                    <form method="POST" action="membership_plans.php" class="w-50 confirm-submit"
                          data-confirm-title="Delete this plan?"
                          data-confirm-text="This cannot be undone."
                          data-confirm-icon="warning"
                          data-confirm-button="Yes, delete it">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_plan">
                        <input type="hidden" name="plan_id" value="<?php echo $plan['id']; ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="bi bi-trash3-fill me-1"></i> Delete
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-card-checklist me-2"></i> Create Membership Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="membership_plans.php">
            <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_plan">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Plan Name </label>
                        <input type="text" name="plan_name" class="form-control" placeholder="e.g. Monthly Student Saver" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category </label>
                        <select name="category" class="form-select" required>
                            <option value="Regular">Regular</option>
                            <option value="Student">Student</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Duration (Days) </label>
                            <input type="number" name="duration_days" class="form-control" value="30" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Price (PHP) </label>
                            <input type="number" step="0.01" name="price" class="form-control" placeholder="1000.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Plan inclusions and benefits..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4">Create Plan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-warning">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-fill me-2"></i> Edit Membership Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="membership_plans.php">
            <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="edit_plan">
                <input type="hidden" name="plan_id" id="edit_plan_id" value="">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Plan Name</label>
                        <input type="text" name="plan_name" id="edit_plan_name" class="form-control" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Category</label>
                            <select name="category" id="edit_plan_category" class="form-select" required>
                                <option value="Regular">Regular</option>
                                <option value="Student">Student</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="edit_plan_status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">Duration (Days)</label>
                            <input type="number" name="duration_days" id="edit_plan_duration" class="form-control" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">Price (PHP)</label>
                            <input type="number" step="0.01" name="price" id="edit_plan_price" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="edit_plan_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="alert alert-secondary small mb-0">
                        <i class="bi bi-info-circle me-1"></i> Changes only affect new sign-ups and renewals — active memberships already purchased under this plan keep their original price and terms.
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold text-dark px-4"><i class="bi bi-save-fill me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    document.querySelectorAll('.edit-plan-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('edit_plan_id').value = btn.dataset.id;
            document.getElementById('edit_plan_name').value = btn.dataset.planName;
            document.getElementById('edit_plan_category').value = btn.dataset.category;
            document.getElementById('edit_plan_status').value = btn.dataset.status;
            document.getElementById('edit_plan_duration').value = btn.dataset.durationDays;
            document.getElementById('edit_plan_price').value = btn.dataset.price;
            document.getElementById('edit_plan_description').value = btn.dataset.description;
            new bootstrap.Modal(document.getElementById('editPlanModal')).show();
        });
    });
})();
</script>

<?php require_once '../includes/footer.php'; ?>