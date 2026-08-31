/**
 * ATZ Fitness Gym Management System
 * Global JavaScript Helpers & Form Validationid=las
 */

$(document.documentElement).ready(function() {

    // Reusable styled confirmation for forms that would otherwise use the
    // plain browser confirm() dialog. Usage: add class "confirm-submit"
    // to the <form>, plus data-confirm-title / data-confirm-text /
    // data-confirm-icon attributes for the message.
    $('form.confirm-submit').on('submit', function(e) {
        const form = this;
        if (form.dataset.confirmed === 'true') {
            return true;
        }
        e.preventDefault();

        Swal.fire({
            title: form.dataset.confirmTitle || 'Are you sure?',
            text: form.dataset.confirmText || '',
            icon: form.dataset.confirmIcon || 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d81324',
            confirmButtonText: form.dataset.confirmButton || 'Yes, continue',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                form.dataset.confirmed = 'true';
                form.submit();
            }
        });

        return false;
    });

    // Form validation helpers
    // Note: Contact No. and Date of Birth are validated live (is-invalid class +
    // setCustomValidity) directly on their fields, the same way Name and Email
    // are — see the inline scripts on members.php / staff.php. That live
    // validation plus the pattern/required attributes on those inputs already
    // blocks submission via the browser's native constraint validation, so no
    // switch-to-alert popup is needed for them here.
    $('form[data-validate="true"]').on('submit', function(e) {
        let isValid = true;

        // Validate names (letters only)
        const nameInputs = $(this).find('input[name="first_name"], input[name="last_name"], input[name="full_name"]');
        nameInputs.each(function() {
            const val = $(this).val().trim();
            const nameRegex = /^[a-zA-Z\s\.\-\']+$/;
            if (val && !nameRegex.test(val)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Name Format',
                    text: 'Names must contain letters, spaces, hyphens, or periods only.'
                });
                isValid = false;
                return false;
            }
        });

        // Validate email (Gmail-only fields, opted in via data-gmail-only)
        const emailInput = $(this).find('input[name="email"][data-gmail-only="true"]');
        if (emailInput.length > 0 && emailInput.val()) {
            const emailVal = emailInput.val().trim();
            const emailRegex = /^[a-zA-Z0-9._%+-]+@gmail\.com$/;
            if (!emailRegex.test(emailVal)) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Email Address',
                    text: 'Email address must be a valid @gmail.com address.'
                });
                return false;
            }
        }

        return isValid;
    });

    // Toggle GCash QR Box / Cash Amount-Received fields based on payment method
    function getSelectedPlanPrice() {
        const price = parseFloat($('#plan_id option:selected').data('price'));
        return isNaN(price) ? 0 : price;
    }

    function updateChangeDue() {
        const price = getSelectedPlanPrice();
        const tendered = parseFloat($('#amount_tendered').val());
        if (!isNaN(tendered) && price > 0) {
            const change = tendered - price;
            $('#change_due').val(change >= 0 ? change.toFixed(2) : '0.00');
            $('#change_due').toggleClass('text-danger', tendered < price).toggleClass('text-success', tendered >= price);
        } else {
            $('#change_due').val('0.00');
        }
    }

    function updatePlanPriceHint() {
        const price = getSelectedPlanPrice();
        if (price > 0) {
            $('#plan_price_hint').text('Amount due: ₱' + price.toFixed(2));
            $('#amount_tendered').attr('min', price);
        } else {
            $('#plan_price_hint').text('Select a plan to see the price due.');
        }
    }

    $('#plan_id').on('change', function() {
        updatePlanPriceHint();
        updateChangeDue();
    });

    $('#amount_tendered').on('input', updateChangeDue);

    $('#payment_method').on('change', function() {
        if ($(this).val() === 'GCash') {
            $('#gcash_qr_container').removeClass('d-none').show();
            $('#gcash_ref_no').prop('required', true);

            // GCash is exact — no Amount Received / Change fields needed
            $('#amount_tendered_container, #change_due_container').addClass('d-none').hide();
            $('#amount_tendered').prop('required', false);
        } else {
            $('#gcash_qr_container').addClass('d-none').hide();
            $('#gcash_ref_no').prop('required', false);

            $('#amount_tendered_container, #change_due_container').removeClass('d-none').show();
            $('#amount_tendered').prop('required', true);
            updatePlanPriceHint();
            updateChangeDue();
        }
    });

    // Initialize on page load (Cash is the default selected method)
    $('#amount_tendered_container, #change_due_container').removeClass('d-none').show();
    $('#amount_tendered').prop('required', true);

    // Toggle Student Proof Upload container
    $('#member_type').on('change', function() {
        if ($(this).val() === 'Student') {
            $('#student_proof_container').removeClass('d-none').show();
            $('#student_proof_file').prop('required', true);
        } else {
            $('#student_proof_container').addClass('d-none').hide();
            $('#student_proof_file').prop('required', false);
        }
    });
});