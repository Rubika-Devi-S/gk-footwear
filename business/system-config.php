<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'system-config.php');

$pageTitle = 'System Config';
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>
        <section class="page-section p-3 p-lg-3">

<?php
$businessId = current_business_id();

$stmt = mysqli_prepare($conn, "
    SELECT 
        b.business_name,
        b.business_code,
        b.gst_type_key,
        b.gstin,
        bs.invoice_prefix,
        bs.barcode_prefix,
        bs.tax_columns_enabled,
        bs.purchase_rate_required,
        bs.partial_payment_enabled,
        inv.invoice_title,
        inv.paper_size,
        inv.show_composition_note,
        inv.show_barcode,
        inv.footer_text
    FROM businesses b
    LEFT JOIN business_settings bs ON bs.business_id = b.business_id
    LEFT JOIN invoice_settings inv ON inv.business_id = b.business_id AND inv.branch_id IS NULL
    WHERE b.business_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$config = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
?>

<div class="page-head-card mb-3">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1">System Config</h1>
            <p class="text-muted-custom mb-0 small">Business GST, invoice, barcode and billing settings.</p>
        </div>
    </div>
</div>

<form method="post" action="api/system-config-api.php" class="card-ui p-3 p-lg-4">
    <?= csrf_field(); ?>

    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label fw-bold">Business Name</label>
            <input type="text" class="form-control rounded-4" value="<?= e($config['business_name'] ?? '') ?>" readonly>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">Business Code</label>
            <input type="text" class="form-control rounded-4" value="<?= e($config['business_code'] ?? '') ?>" readonly>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">GST Type</label>
            <select name="gst_type_key" class="form-select rounded-4">
                <option value="gst_composition" <?= ($config['gst_type_key'] ?? '') === 'gst_composition' ? 'selected' : '' ?>>GST Composition - Bill of Supply</option>
                <option value="gst_regular" <?= ($config['gst_type_key'] ?? '') === 'gst_regular' ? 'selected' : '' ?>>GST Regular - Tax Invoice</option>
                <option value="non_gst" <?= ($config['gst_type_key'] ?? '') === 'non_gst' ? 'selected' : '' ?>>Non GST - Retail Bill</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">GSTIN</label>
            <input type="text" name="gstin" class="form-control rounded-4 text-uppercase" value="<?= e($config['gstin'] ?? '') ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">Invoice Prefix</label>
            <input type="text" name="invoice_prefix" class="form-control rounded-4 text-uppercase" value="<?= e($config['invoice_prefix'] ?? 'BILL') ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">Barcode Prefix</label>
            <input type="text" name="barcode_prefix" class="form-control rounded-4 text-uppercase" value="<?= e($config['barcode_prefix'] ?? 'GK') ?>">
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">Paper Size</label>
            <select name="paper_size" class="form-select rounded-4">
                <option value="3-inch" <?= ($config['paper_size'] ?? '') === '3-inch' ? 'selected' : '' ?>>3-inch Thermal</option>
                <option value="A4" <?= ($config['paper_size'] ?? '') === 'A4' ? 'selected' : '' ?>>A4</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">Purchase Rate Required?</label>
            <select name="purchase_rate_required" class="form-select rounded-4">
                <option value="0" <?= (int)($config['purchase_rate_required'] ?? 0) === 0 ? 'selected' : '' ?>>No - Optional</option>
                <option value="1" <?= (int)($config['purchase_rate_required'] ?? 0) === 1 ? 'selected' : '' ?>>Yes - Required</option>
            </select>
            <small class="text-muted-custom">GK Footwear: keep Optional.</small>
        </div>

        <div class="col-md-4">
            <label class="form-label fw-bold">Partial Payment</label>
            <select name="partial_payment_enabled" class="form-select rounded-4">
                <option value="1" <?= (int)($config['partial_payment_enabled'] ?? 1) === 1 ? 'selected' : '' ?>>Enabled</option>
                <option value="0" <?= (int)($config['partial_payment_enabled'] ?? 1) === 0 ? 'selected' : '' ?>>Disabled</option>
            </select>
        </div>

        <div class="col-12">
            <label class="form-label fw-bold">Invoice Footer</label>
            <textarea name="footer_text" class="form-control rounded-4" rows="3"><?= e($config['footer_text'] ?? '') ?></textarea>
        </div>

        <div class="col-12 d-flex justify-content-end gap-2">
            <a href="master-control.php" class="btn btn-outline-secondary rounded-4 fw-bold">Cancel</a>
            <button type="submit" class="btn brand-gradient rounded-4 fw-bold px-4">Save Config</button>
        </div>
    </div>
</form>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>
<?php include __DIR__ . '/includes/script.php'; ?>
</body>
</html>
