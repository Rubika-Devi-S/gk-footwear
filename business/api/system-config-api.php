<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

if (!is_business_admin($conn)) {
    flash('error', 'Only business admin can update system config.');
    redirect('../system-config.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../system-config.php');
}

verify_csrf();

$businessId = current_business_id();
$gstType = $_POST['gst_type_key'] ?? 'gst_composition';
$gstin = trim($_POST['gstin'] ?? '');
$invoicePrefix = strtoupper(trim($_POST['invoice_prefix'] ?? 'BILL'));
$barcodePrefix = strtoupper(trim($_POST['barcode_prefix'] ?? 'GK'));
$paperSize = $_POST['paper_size'] ?? '3-inch';
$purchaseRequired = (int)($_POST['purchase_rate_required'] ?? 0);
$partialPayment = (int)($_POST['partial_payment_enabled'] ?? 1);
$footerText = trim($_POST['footer_text'] ?? '');

$invoiceTitle = 'Bill of Supply';
$showTax = 0;
$composition = 1;

if ($gstType === 'gst_regular') {
    $invoiceTitle = 'Tax Invoice';
    $showTax = 1;
    $composition = 0;
} elseif ($gstType === 'non_gst') {
    $invoiceTitle = 'Retail Bill';
    $showTax = 0;
    $composition = 0;
}

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare($conn, "
        UPDATE businesses
        SET gst_type_key = ?, gstin = ?, composition_status = ?
        WHERE business_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ssii", $gstType, $gstin, $composition, $businessId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "
        UPDATE business_settings
        SET gst_type_key = ?,
            gstin = ?,
            composition_status = ?,
            invoice_prefix = ?,
            barcode_prefix = ?,
            tax_columns_enabled = ?,
            purchase_rate_required = ?,
            partial_payment_enabled = ?
        WHERE business_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ssissiiii", $gstType, $gstin, $composition, $invoicePrefix, $barcodePrefix, $showTax, $purchaseRequired, $partialPayment, $businessId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "
        UPDATE invoice_settings
        SET gst_type_key = ?,
            invoice_title = ?,
            paper_size = ?,
            show_composition_note = ?,
            show_tax_columns = ?,
            show_cgst_sgst_igst = ?,
            footer_text = ?
        WHERE business_id = ? AND branch_id IS NULL
    ");
    mysqli_stmt_bind_param($stmt, "sssiiisi", $gstType, $invoiceTitle, $paperSize, $composition, $showTax, $showTax, $footerText, $businessId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    log_activity($conn, 'System Config', 'update', $businessId, null, $_POST);
    mysqli_commit($conn);

    flash('success', 'System configuration updated successfully.');
} catch (Throwable $e) {
    mysqli_rollback($conn);
    flash('error', 'Update failed: ' . $e->getMessage());
}

redirect('../system-config.php');
?>
