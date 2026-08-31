<?php
/**
 * ATZ Fitness Gym Management System
 * Payment Receipt (Thermal Slip, Printable)
 */

$page_title = "Payment Receipt";
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_role(['Administrator', 'Staff']);

$txn = sanitize($_GET['txn'] ?? '');

$payment = null;
if ($txn !== '') {
    $stmt = mysqli_prepare($conn, "SELECT p.*,
                m.member_code, m.first_name AS m_first, m.last_name AS m_last,
                m.member_type, m.contact_no AS m_contact,
                w.guest_name, w.contact_no AS w_contact,
                pl.plan_name, pl.duration_days,
                ms.start_date, ms.end_date,
                u.full_name AS processed_by_name
            FROM payments p
            LEFT JOIN members m ON p.member_id = m.id
            LEFT JOIN walkin_customers w ON p.walkin_id = w.id
            LEFT JOIN memberships ms ON p.membership_id = ms.id
            LEFT JOIN membership_plans pl ON ms.plan_id = pl.id
            LEFT JOIN users u ON p.processed_by = u.id
            WHERE p.transaction_no = ?
            LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $txn);
    mysqli_stmt_execute($stmt);
    $payment = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

// Gym info for the letterhead
$gym_name    = get_setting($conn, 'gym_name', 'ATZ FITNESS');
$gym_tagline = get_setting($conn, 'gym_tagline', '');
$gym_address = get_setting($conn, 'gym_address', '');
$gym_contact = get_setting($conn, 'gym_contact', '');
$gym_email   = get_setting($conn, 'gym_email', '');
$currency    = get_setting($conn, 'currency_symbol', '₱');

if ($payment) {
    $customer_name = $payment['m_first']
        ? ($payment['m_first'] . ' ' . $payment['m_last'])
        : ($payment['guest_name'] ?? 'Guest');
    $customer_contact = $payment['m_first'] ? ($payment['m_contact'] ?? '') : ($payment['w_contact'] ?? '');

    $item_label = 'Walk-in Day Pass';
    if ($payment['payment_for'] !== 'Walk-in' && !empty($payment['plan_name'])) {
        $item_label = $payment['plan_name'] . ' (' . $payment['payment_for'] . ')';
    } elseif ($payment['payment_for'] !== 'Walk-in') {
        $item_label = $payment['payment_for'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $payment ? "Receipt " . sanitize($txn) : "Receipt Not Found"; ?> - ATZ Fitness</title>
    <link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=4" rel="stylesheet">
    <style>
        body {
            background-color: var(--ink-900, #14161c);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .page-actions { max-width: 320px; margin: 20px auto 12px; }

        /* ---------- Thermal slip ---------- */
        .slip-wrap { max-width: 320px; margin: 0 auto 40px; }
        .slip {
            background: #fff;
            width: 302px; /* ~80mm at 96dpi */
            margin: 0 auto;
            padding: 14px 14px 22px;
            font-family: 'Courier New', 'Consolas', monospace;
            font-size: 12.5px;
            line-height: 1.5;
            color: #000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.35);
            /* jagged bottom edge like a torn paper roll */
            -webkit-mask-image: linear-gradient(#000, #000);
        }
        .slip-jagged-bottom {
            width: 302px;
            margin: -1px auto 0;
            height: 10px;
            background:
                linear-gradient(135deg, #fff 50%, transparent 50%) 0 0,
                linear-gradient(45deg, #fff 50%, transparent 50%) 0 0;
            background-size: 10px 10px;
            background-repeat: repeat-x;
            filter: drop-shadow(0 2px 3px rgba(0,0,0,0.25));
        }

        .r-center { text-align: center; }
        .r-gymname { font-size: 15px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .r-sub { font-size: 11px; }

        .r-divider { border: none; border-top: 1px dashed #000; margin: 8px 0; }
        .r-divider.solid { border-top: 1px solid #000; }

        .r-line {
            display: flex;
            align-items: baseline;
            white-space: nowrap;
            overflow: hidden;
            margin: 2px 0;
        }
        .r-label { flex-shrink: 0; }
        .r-value { flex-shrink: 0; margin-left: 4px; font-weight: 700; }
        .r-dots {
            flex-grow: 1;
            border-bottom: 1px dotted #999;
            margin: 0 2px 3px;
        }
        .r-line.plain .r-dots { display: none; }
        .r-line.plain .r-value { margin-left: auto; }

        .r-big-total {
            display: flex; justify-content: space-between;
            font-size: 15px; font-weight: 700;
            margin: 6px 0 2px;
        }

        .r-tag {
            display: inline-block;
            border: 1px solid #000;
            padding: 3px 10px;
            font-weight: 700;
            letter-spacing: 2px;
            font-size: 11px;
        }

        .r-barcode {
            text-align: center;
            font-family: 'Libre Barcode 39', 'Courier New', monospace;
            font-size: 34px;
            line-height: 1;
            letter-spacing: 2px;
            margin: 10px 0 2px;
        }
        .r-txn-text { text-align: center; font-size: 10.5px; letter-spacing: 1px; }

        .r-footer { text-align: center; font-size: 11px; margin-top: 8px; }
        .r-scissors { text-align: center; font-size: 11px; color: #666; margin: 14px 0 2px; }

        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .slip-wrap { max-width: 100%; margin: 0; }
            .slip { box-shadow: none; width: 100%; margin: 0; padding: 6px 10px 16px; }
            .slip-jagged-bottom { display: none; }
        }
    </style>
</head>
<body>

<div class="page-actions no-print d-flex justify-content-between align-items-center">
    <button type="button" id="backBtn" class="btn btn-outline-light btn-sm">
        <i class="bi bi-arrow-left me-1"></i> Back
    </button>
    <?php if ($payment): ?>
    <button onclick="window.print()" class="btn btn-warning fw-bold btn-sm">
        <i class="bi bi-printer-fill me-1"></i> Print Receipt
    </button>
    <?php endif; ?>
</div>

<div class="slip-wrap">
    <?php if (!$payment): ?>
        <div class="slip r-center">
            <i class="bi bi-receipt fs-1"></i>
            <div class="fw-bold mt-2">RECEIPT NOT FOUND</div>
            <div class="r-sub mt-1">No payment record matches this transaction number.</div>
        </div>
    <?php else: ?>
        <div class="slip">
            <div class="r-center">
                <div class="r-gymname"><?php echo sanitize($gym_name); ?></div>
                <?php if ($gym_tagline): ?><div class="r-sub"><?php echo sanitize($gym_tagline); ?></div><?php endif; ?>
                <?php if ($gym_address): ?><div class="r-sub"><?php echo sanitize($gym_address); ?></div><?php endif; ?>
                <?php if ($gym_contact): ?><div class="r-sub">Tel: <?php echo sanitize($gym_contact); ?></div><?php endif; ?>
                <?php if ($gym_email): ?><div class="r-sub"><?php echo sanitize($gym_email); ?></div><?php endif; ?>
            </div>

            <hr class="r-divider solid">

            <div class="r-center mb-1">
                <span class="r-tag">OFFICIAL RECEIPT</span>
            </div>

            <div class="r-line plain"><span class="r-label">Txn No:</span><span class="r-value"><?php echo sanitize($payment['transaction_no']); ?></span></div>
            <div class="r-line plain"><span class="r-label">Date:</span><span class="r-value"><?php echo date('M d, Y h:i A', strtotime($payment['payment_date'])); ?></span></div>
            <div class="r-line plain"><span class="r-label">Status:</span><span class="r-value"><?php echo strtoupper(sanitize($payment['status'])); ?></span></div>
            <div class="r-line plain"><span class="r-label">Cashier:</span><span class="r-value"><?php echo sanitize($payment['processed_by_name'] ?? 'System Admin'); ?></span></div>

            <hr class="r-divider">

            <div class="r-line plain"><span class="r-label">Billed To:</span><span class="r-value"><?php echo sanitize($customer_name); ?></span></div>
            <?php if (!empty($payment['member_code'])): ?>
                <div class="r-line plain"><span class="r-label">Member No:</span><span class="r-value"><?php echo sanitize($payment['member_code']); ?></span></div>
                <div class="r-line plain"><span class="r-label">Type:</span><span class="r-value"><?php echo sanitize($payment['member_type']); ?></span></div>
            <?php endif; ?>
            <?php if (!empty($customer_contact)): ?>
                <div class="r-line plain"><span class="r-label">Contact:</span><span class="r-value"><?php echo sanitize($customer_contact); ?></span></div>
            <?php endif; ?>

            <hr class="r-divider">

            <div class="r-line"><span class="r-label"><?php echo sanitize($item_label); ?></span><span class="r-dots"></span><span class="r-value"><?php echo sanitize($currency); ?><?php echo number_format($payment['amount'], 2); ?></span></div>
            <?php if (!empty($payment['start_date']) && !empty($payment['end_date'])): ?>
                <div class="r-sub" style="margin-left:2px;">Coverage: <?php echo date('M d, Y', strtotime($payment['start_date'])); ?> - <?php echo date('M d, Y', strtotime($payment['end_date'])); ?></div>
            <?php endif; ?>

            <hr class="r-divider">

            <div class="r-big-total"><span>TOTAL</span><span><?php echo sanitize($currency); ?><?php echo number_format($payment['amount'], 2); ?></span></div>

            <hr class="r-divider">

            <div class="r-line plain"><span class="r-label">Payment Method:</span><span class="r-value"><?php echo strtoupper(sanitize($payment['payment_method'])); ?></span></div>
            <?php if ($payment['payment_method'] === 'Cash'): ?>
                <div class="r-line plain"><span class="r-label">Cash Tendered:</span><span class="r-value"><?php echo sanitize($currency); ?><?php echo number_format($payment['amount_tendered'] ?? $payment['amount'], 2); ?></span></div>
                <div class="r-line plain"><span class="r-label">Change:</span><span class="r-value"><?php echo sanitize($currency); ?><?php echo number_format($payment['change_amount'] ?? 0, 2); ?></span></div>
            <?php endif; ?>

            <hr class="r-divider">

            <div class="r-barcode">*<?php echo sanitize($payment['transaction_no']); ?>*</div>
            <div class="r-txn-text"><?php echo sanitize($payment['transaction_no']); ?></div>

            <div class="r-footer mt-2">
                THANK YOU FOR CHOOSING<br>
                <strong><?php echo strtoupper(sanitize($gym_name)); ?></strong>!<br>
                This is a computer-generated receipt.
            </div>

            <div class="r-scissors">&#9986;- - - - - - - - - - - - - - - - - - - - -</div>
        </div>
        <div class="slip-jagged-bottom"></div>
    <?php endif; ?>
</div>

<script>
    // The receipt is usually opened in a new tab (target="_blank"), so plain
    // history.back() often has nothing to go back to. Prefer closing the tab
    // when it was opened from another page; otherwise fall back to real
    // in-tab history, and finally to the payments log as a safe default.
    document.getElementById('backBtn').addEventListener('click', function () {
        if (window.opener && !window.opener.closed) {
            window.close();
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = 'payments.php';
        }
    });
</script>

</body>
</html>