<?php
/**
 * ATZ Fitness Gym Management System
 * Footer Partial
 */
?>
</main>
</div>
</div>

<!-- Bootstrap 5 JS Bundle (local) -->
<script src="../assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 JS (local) -->
<script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/main.js"></script>

<?php if (isset($_SESSION['swal_msg'])):
    $receipt_txn = $_SESSION['receipt_txn'] ?? '';
?>
<script>
    Swal.fire({
        title: "<?php echo sanitize($_SESSION['swal_title'] ?? 'Notice'); ?>",
        text: "<?php echo sanitize($_SESSION['swal_msg']); ?>",
        icon: "<?php echo sanitize($_SESSION['swal_type'] ?? 'info'); ?>",
        confirmButtonColor: '#d81324',
        confirmButtonText: 'OK'<?php if ($receipt_txn): ?>,
        showDenyButton: true,
        denyButtonText: 'Print Receipt',
        denyButtonColor: '#198754'<?php endif; ?>
    })<?php if ($receipt_txn): ?>.then(function(result) {
        if (result.isDenied) {
            window.open('receipt.php?txn=<?php echo rawurlencode($receipt_txn); ?>', '_blank');
        }
    })<?php endif; ?>;
</script>
<?php 
    unset($_SESSION['swal_msg']);
    unset($_SESSION['swal_title']);
    unset($_SESSION['swal_type']);
    unset($_SESSION['receipt_txn']);
endif; 
?>

</body>
</html>