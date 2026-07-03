<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Add Business';

$gstTypes = $pdo->query("
    SELECT gst_type_key, gst_type_name, invoice_title 
    FROM gst_type_settings 
    WHERE status = 1 
    ORDER BY id ASC
")->fetchAll();

$errors = [];
$hasLogo = column_exists($pdo, 'businesses', 'logo_path');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $businessName = trim($_POST['business_name'] ?? '');
    $businessCodeInput = normalize_business_code($_POST['business_code'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gstTypeKey = trim($_POST['gst_type_key'] ?? 'gst_composition');
    $gstin = trim($_POST['gstin'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $adminPassword = $_POST['admin_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;

    if ($businessName === '') {
        $errors[] = 'Business name is required.';
    }

    $businessCode = $businessCodeInput ?: generate_business_code($pdo, $businessName);

    if ($businessCode === '') {
        $errors[] = 'Business code is required.';
    }

    if ($businessCode !== '' && business_code_exists($pdo, $businessCode)) {
        $errors[] = 'Business code already exists.';
    }

    if ($gstTypeKey === '') {
        $errors[] = 'GST type is required.';
    }

    if ($adminName === '') {
        $errors[] = 'Business admin name is required.';
    }

    if ($adminUsername === '') {
        $errors[] = 'Business admin username is required.';
    }

    if (strlen($adminPassword) < 6) {
        $errors[] = 'Business admin password must be minimum 6 characters.';
    }

    if ($adminPassword !== $confirmPassword) {
        $errors[] = 'Password and confirm password do not match.';
    }

    if ($adminUsername !== '' && username_exists($pdo, $adminUsername)) {
        $errors[] = 'This username already exists. Please choose another username.';
    }

    $gstCheck = $pdo->prepare("SELECT COUNT(*) FROM gst_type_settings WHERE gst_type_key = ? AND status = 1");
    $gstCheck->execute([$gstTypeKey]);

    if ((int)$gstCheck->fetchColumn() === 0) {
        $errors[] = 'Invalid GST type selected.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $gst = gst_config($pdo, $gstTypeKey);
            $compositionStatus = $gstTypeKey === 'gst_composition' ? 1 : 0;
            $taxColumnsEnabled = $gstTypeKey === 'gst_regular' ? 1 : 0;
            $passwordHash = password_hash($adminPassword, PASSWORD_BCRYPT);
            $logoPath = null;

            if ($hasLogo && isset($_FILES['business_logo'])) {
                $logoPath = upload_business_logo($_FILES['business_logo'], $businessCode);
            }

            if ($hasLogo) {
                $stmt = $pdo->prepare("
                    INSERT INTO businesses
                    (business_code, business_name, owner_name, mobile, email, address, logo_path, gst_type_key, gstin, composition_status, status, created_by)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $businessCode,
                    $businessName,
                    $ownerName ?: null,
                    $mobile ?: null,
                    $email ?: null,
                    $address ?: null,
                    $logoPath,
                    $gstTypeKey,
                    $gstin ?: null,
                    $compositionStatus,
                    $status,
                    $_SESSION['super_admin_id']
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO businesses
                    (business_code, business_name, owner_name, mobile, email, address, gst_type_key, gstin, composition_status, status, created_by)
                    VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $businessCode,
                    $businessName,
                    $ownerName ?: null,
                    $mobile ?: null,
                    $email ?: null,
                    $address ?: null,
                    $gstTypeKey,
                    $gstin ?: null,
                    $compositionStatus,
                    $status,
                    $_SESSION['super_admin_id']
                ]);
            }

            $businessId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO business_login_credentials
                (business_id, username, password, password_reset_required, status)
                VALUES
                (?, ?, ?, 0, 1)
            ");
            $stmt->execute([$businessId, $adminUsername, $passwordHash]);

            $stmt = $pdo->prepare("
                INSERT INTO business_settings
                (business_id, gst_type_key, gstin, composition_status, invoice_prefix, barcode_prefix, tax_columns_enabled, purchase_rate_required, partial_payment_enabled, walkin_credit_enabled, loyalty_enabled, status)
                VALUES
                (?, ?, ?, ?, 'BILL', 'GK', ?, 0, 1, 0, 0, 1)
            ");
            $stmt->execute([$businessId, $gstTypeKey, $gstin ?: null, $compositionStatus, $taxColumnsEnabled]);

            $stmt = $pdo->prepare("
                INSERT INTO invoice_settings
                (business_id, branch_id, gst_type_key, invoice_title, paper_size, show_gstin, show_composition_note, composition_note, show_tax_columns, show_cgst_sgst_igst, show_barcode, show_today_savings, show_loyalty_redeem, footer_text, status)
                VALUES
                (?, NULL, ?, ?, '3-inch', ?, ?, ?, ?, ?, 1, 1, 1, ?, 1)
            ");
            $stmt->execute([
                $businessId,
                $gstTypeKey,
                $gst['invoice_title'] ?? 'Bill of Supply',
                (int)($gst['show_gstin'] ?? 1),
                (int)($gst['show_composition_note'] ?? 1),
                $gst['composition_note'] ?? null,
                (int)($gst['show_tax_columns'] ?? 0),
                $gstTypeKey === 'gst_regular' ? 1 : 0,
                'Goods once sold cannot be taken back. Thank you for shopping with us.'
            ]);

            $stmt = $pdo->prepare("
                INSERT INTO barcode_settings
                (business_id, branch_id, barcode_type, stock_barcode_prefix, bill_barcode_prefix, running_number, status)
                VALUES
                (?, NULL, 'CODE128', 'STK', 'BILL', 1, 1)
            ");
            $stmt->execute([$businessId]);

            $stmt = $pdo->prepare("
                INSERT INTO theme_settings
                (business_id, primary_color, secondary_color, layout_style, status)
                VALUES
                (?, '#0d6efd', '#6610f2', 'default', 1)
            ");
            $stmt->execute([$businessId]);

            seed_default_business_data($pdo, $businessId, $adminName, $adminUsername, $passwordHash);

            $seqStmt = $pdo->prepare("
                INSERT INTO number_sequences
                (business_id, branch_id, sequence_key, prefix, current_number, padding_length, status)
                VALUES
                (?, NULL, ?, ?, 0, ?, 1)
            ");
            $seqStmt->execute([$businessId, 'bill_no', 'BILL', 4]);
            $seqStmt->execute([$businessId, 'batch_no', 'BATCH', 4]);
            $seqStmt->execute([$businessId, 'stock_barcode', 'STK', 6]);
            $seqStmt->execute([$businessId, 'bill_barcode', 'BILL', 6]);

            insert_activity_log(
                $pdo,
                $businessId,
                null,
                null,
                null,
                'Super Admin - Business Registration',
                'business_created',
                $businessId,
                null,
                [
                    'business_code' => $businessCode,
                    'business_name' => $businessName,
                    'gst_type_key' => $gstTypeKey,
                    'admin_username' => $adminUsername
                ]
            );

            $pdo->commit();

            flash('success', "Business registered successfully. Business Code: {$businessCode}");
            redirect('businesses.php');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

$suggestedCode = generate_business_code($pdo, $_POST['business_name'] ?? 'GK FOOTWEAR');

require_once __DIR__ . '/includes/header.php';
?>

<?php show_error_toasts($errors); ?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Add Business</h2>
        <div class="page-subtitle">Register new business and create business admin login.</div>
    </div>
    <a href="businesses.php" class="btn btn-light">
        <i class="bi bi-arrow-left me-1"></i> Back to Businesses
    </a>
</div>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field(); ?>

    <div class="glass-card mb-4">
        <div class="card-section-head">
            <div class="section-icon"><i class="bi bi-buildings"></i></div>
            <div>
                <h3 class="section-head-title">Business Information</h3>
                <div class="section-head-sub">Create main business details.</div>
            </div>
        </div>

        <div class="card-body-custom">
            <div class="row g-4">
                <div class="col-lg-4">
                    <label class="form-label">Business Name *</label>
                    <input type="text" name="business_name" class="form-control" value="<?= e($_POST['business_name'] ?? 'GK FOOTWEAR') ?>" required>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Business Code</label>
                    <input type="text" name="business_code" class="form-control text-uppercase" value="<?= e($_POST['business_code'] ?? $suggestedCode) ?>">
                    <small class="text-muted">Example: GKFOOT001 / JAIVID001</small>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Business Logo</label>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="logo-preview"><?= e(business_initial($_POST['business_name'] ?? 'G')) ?></div>
                        <div class="flex-grow-1">
                            <input type="file" name="business_logo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                            <small class="text-muted">PNG/JPG/WEBP, max 2 MB.</small>
                            <?php if (!$hasLogo): ?>
                                <br><small class="text-danger">Run logo_path SQL patch to save logo.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Owner Name</label>
                    <input type="text" name="owner_name" class="form-control" value="<?= e($_POST['owner_name'] ?? '') ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" name="mobile" class="form-control" value="<?= e($_POST['mobile'] ?? '') ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">GSTIN</label>
                    <input type="text" name="gstin" class="form-control text-uppercase" value="<?= e($_POST['gstin'] ?? '') ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">GST Type *</label>
                    <select name="gst_type_key" class="form-select" required>
                        <?php foreach ($gstTypes as $gst): ?>
                            <?php $selected = ($_POST['gst_type_key'] ?? 'gst_composition') === $gst['gst_type_key'] ? 'selected' : ''; ?>
                            <option value="<?= e($gst['gst_type_key']) ?>" <?= $selected ?>>
                                <?= e($gst['gst_type_name']) ?> - <?= e($gst['invoice_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">GK Footwear: GST Composition / Bill of Supply.</small>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Activity</label>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="status" checked>
                        <label class="form-check-label fw-bold">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"><?= e($_POST['address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card mb-4">
        <div class="card-section-head">
            <div class="section-icon"><i class="bi bi-person-badge"></i></div>
            <div>
                <h3 class="section-head-title">Business Admin Login</h3>
                <div class="section-head-sub">Login for the business panel.</div>
            </div>
        </div>

        <div class="card-body-custom">
            <div class="row g-4">
                <div class="col-lg-3">
                    <label class="form-label">Admin Name *</label>
                    <input type="text" name="admin_name" class="form-control" value="<?= e($_POST['admin_name'] ?? 'GK Admin') ?>" required>
                </div>

                <div class="col-lg-3">
                    <label class="form-label">Admin Username *</label>
                    <input type="text" name="admin_username" class="form-control" value="<?= e($_POST['admin_username'] ?? 'gkadmin') ?>" required>
                </div>

                <div class="col-lg-3">
                    <label class="form-label">Password *</label>
                    <input type="password" name="admin_password" class="form-control" required>
                </div>

                <div class="col-lg-3">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
        </div>
    </div>

    <div class="sticky-form-actions">
        <a href="businesses.php" class="btn btn-light">Cancel</a>
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-floppy me-1"></i> Register Business
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
