<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Edit Business';

$businessId = (int)($_GET['id'] ?? 0);

if ($businessId <= 0) {
    flash('danger', 'Invalid business.');
    redirect('businesses.php');
}

$hasLogo = column_exists($pdo, 'businesses', 'logo_path');
$logoSelect = $hasLogo ? "b.logo_path" : "NULL AS logo_path";

$gstTypes = $pdo->query("
    SELECT gst_type_key, gst_type_name, invoice_title 
    FROM gst_type_settings 
    WHERE status = 1 
    ORDER BY id ASC
")->fetchAll();

$stmt = $pdo->prepare("
    SELECT 
        b.*,
        {$logoSelect},
        blc.username AS admin_username
    FROM businesses b
    LEFT JOIN business_login_credentials blc ON blc.business_id = b.business_id
    WHERE b.business_id = ?
    LIMIT 1
");
$stmt->execute([$businessId]);
$business = $stmt->fetch();

if (!$business) {
    flash('danger', 'Business not found.');
    redirect('businesses.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $businessName = trim($_POST['business_name'] ?? '');
    $businessCode = normalize_business_code($_POST['business_code'] ?? '');
    $ownerName = trim($_POST['owner_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $gstTypeKey = trim($_POST['gst_type_key'] ?? 'gst_composition');
    $gstin = trim($_POST['gstin'] ?? '');
    $adminUsername = trim($_POST['admin_username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $status = isset($_POST['status']) ? 1 : 0;

    if ($businessName === '') {
        $errors[] = 'Business name is required.';
    }

    if ($businessCode === '') {
        $errors[] = 'Business code is required.';
    }

    if ($businessCode !== '' && business_code_exists($pdo, $businessCode, $businessId)) {
        $errors[] = 'Business code already exists.';
    }

    if ($adminUsername === '') {
        $errors[] = 'Admin username is required.';
    }

    if ($newPassword !== '' && strlen($newPassword) < 6) {
        $errors[] = 'New password must be minimum 6 characters.';
    }

    if ($adminUsername !== '' && username_exists($pdo, $adminUsername, $businessId)) {
        $errors[] = 'This username already exists for another business.';
    }

    $gstCheck = $pdo->prepare("SELECT COUNT(*) FROM gst_type_settings WHERE gst_type_key = ? AND status = 1");
    $gstCheck->execute([$gstTypeKey]);

    if ((int)$gstCheck->fetchColumn() === 0) {
        $errors[] = 'Invalid GST type selected.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $oldData = $business;
            $gst = gst_config($pdo, $gstTypeKey);
            $compositionStatus = $gstTypeKey === 'gst_composition' ? 1 : 0;
            $taxColumnsEnabled = $gstTypeKey === 'gst_regular' ? 1 : 0;
            $logoPath = $business['logo_path'] ?? null;

            if ($hasLogo && isset($_FILES['business_logo'])) {
                $logoPath = upload_business_logo($_FILES['business_logo'], $businessCode, $logoPath);
            }

            if ($hasLogo) {
                $stmt = $pdo->prepare("
                    UPDATE businesses
                    SET business_code = ?,
                        business_name = ?,
                        owner_name = ?,
                        mobile = ?,
                        email = ?,
                        address = ?,
                        logo_path = ?,
                        gst_type_key = ?,
                        gstin = ?,
                        composition_status = ?,
                        status = ?
                    WHERE business_id = ?
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
                    $businessId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE businesses
                    SET business_code = ?,
                        business_name = ?,
                        owner_name = ?,
                        mobile = ?,
                        email = ?,
                        address = ?,
                        gst_type_key = ?,
                        gstin = ?,
                        composition_status = ?,
                        status = ?
                    WHERE business_id = ?
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
                    $businessId
                ]);
            }

            $passwordSql = '';
            $passwordParams = [];

            if ($newPassword !== '') {
                $passwordSql = ', password = ?, password_reset_required = 0, last_password_reset_at = NOW()';
                $passwordParams[] = password_hash($newPassword, PASSWORD_BCRYPT);
            }

            $credSql = "
                UPDATE business_login_credentials
                SET username = ? {$passwordSql}
                WHERE business_id = ?
            ";

            $credParams = [$adminUsername];
            $credParams = array_merge($credParams, $passwordParams);
            $credParams[] = $businessId;

            $stmt = $pdo->prepare($credSql);
            $stmt->execute($credParams);

            $adminRoleStmt = $pdo->prepare("
                SELECT role_id 
                FROM roles 
                WHERE business_id = ? AND role_type = 'admin' 
                ORDER BY role_id ASC 
                LIMIT 1
            ");
            $adminRoleStmt->execute([$businessId]);
            $adminRoleId = $adminRoleStmt->fetchColumn();

            if ($adminRoleId) {
                $adminUserStmt = $pdo->prepare("
                    SELECT user_id 
                    FROM users 
                    WHERE business_id = ? AND role_id = ?
                    ORDER BY user_id ASC 
                    LIMIT 1
                ");
                $adminUserStmt->execute([$businessId, $adminRoleId]);
                $adminUserId = $adminUserStmt->fetchColumn();

                if ($adminUserId) {
                    if ($newPassword !== '') {
                        $userUpdate = $pdo->prepare("
                            UPDATE users
                            SET username = ?, password = ?
                            WHERE user_id = ?
                        ");
                        $userUpdate->execute([$adminUsername, $passwordParams[0], $adminUserId]);
                    } else {
                        $userUpdate = $pdo->prepare("
                            UPDATE users
                            SET username = ?
                            WHERE user_id = ?
                        ");
                        $userUpdate->execute([$adminUsername, $adminUserId]);
                    }
                }
            }

            $stmt = $pdo->prepare("
                UPDATE business_settings
                SET gst_type_key = ?,
                    gstin = ?,
                    composition_status = ?,
                    tax_columns_enabled = ?,
                    purchase_rate_required = 0
                WHERE business_id = ?
            ");

            $stmt->execute([
                $gstTypeKey,
                $gstin ?: null,
                $compositionStatus,
                $taxColumnsEnabled,
                $businessId
            ]);

            $stmt = $pdo->prepare("
                UPDATE invoice_settings
                SET gst_type_key = ?,
                    invoice_title = ?,
                    show_gstin = ?,
                    show_composition_note = ?,
                    composition_note = ?,
                    show_tax_columns = ?,
                    show_cgst_sgst_igst = ?
                WHERE business_id = ? AND branch_id IS NULL
            ");

            $stmt->execute([
                $gstTypeKey,
                $gst['invoice_title'] ?? 'Bill of Supply',
                (int)($gst['show_gstin'] ?? 1),
                (int)($gst['show_composition_note'] ?? 1),
                $gst['composition_note'] ?? null,
                (int)($gst['show_tax_columns'] ?? 0),
                $gstTypeKey === 'gst_regular' ? 1 : 0,
                $businessId
            ]);

            insert_activity_log(
                $pdo,
                $businessId,
                null,
                null,
                null,
                'Super Admin - Business',
                'business_updated',
                $businessId,
                $oldData,
                [
                    'business_code' => $businessCode,
                    'business_name' => $businessName,
                    'gst_type_key' => $gstTypeKey,
                    'admin_username' => $adminUsername,
                    'password_changed' => $newPassword !== ''
                ]
            );

            $pdo->commit();

            flash('success', 'Business updated successfully.');
            redirect('business-edit.php?id=' . $businessId);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->prepare("
    SELECT 
        b.*,
        {$logoSelect},
        blc.username AS admin_username
    FROM businesses b
    LEFT JOIN business_login_credentials blc ON blc.business_id = b.business_id
    WHERE b.business_id = ?
    LIMIT 1
");
$stmt->execute([$businessId]);
$business = $stmt->fetch();

require_once __DIR__ . '/includes/header.php';
?>

<?php show_error_toasts($errors); ?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Edit Business</h2>
        <div class="page-subtitle">Update business profile and billing configuration.</div>
    </div>
    <a href="business-view.php?id=<?= (int)$businessId ?>" class="btn btn-light">
        <i class="bi bi-arrow-left me-1"></i> Back to View
    </a>
</div>

<form method="post" enctype="multipart/form-data">
    <?= csrf_field(); ?>

    <div class="glass-card mb-4">
        <div class="card-section-head">
            <div class="section-icon"><i class="bi bi-buildings"></i></div>
            <div>
                <h3 class="section-head-title">Business Information</h3>
                <div class="section-head-sub">Edit main business details.</div>
            </div>
        </div>

        <div class="card-body-custom">
            <div class="row g-4">
                <div class="col-lg-4">
                    <label class="form-label">Business Name *</label>
                    <input type="text" name="business_name" class="form-control" value="<?= e($business['business_name']) ?>" required>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Business Code</label>
                    <input type="text" name="business_code" class="form-control text-uppercase" value="<?= e($business['business_code']) ?>" required>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Business Logo</label>
                    <div class="d-flex gap-3 align-items-start">
                        <div class="logo-preview">
                            <?php if (!empty($business['logo_path'])): ?>
                                <img src="<?= e(logo_url($business['logo_path'])) ?>" alt="">
                            <?php else: ?>
                                <?= e(business_initial($business['business_name'])) ?>
                            <?php endif; ?>
                        </div>
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
                    <input type="text" name="owner_name" class="form-control" value="<?= e($business['owner_name']) ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Mobile Number</label>
                    <input type="text" name="mobile" class="form-control" value="<?= e($business['mobile']) ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($business['email']) ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">GSTIN</label>
                    <input type="text" name="gstin" class="form-control text-uppercase" value="<?= e($business['gstin']) ?>">
                </div>

                <div class="col-lg-4">
                    <label class="form-label">GST Type</label>
                    <select name="gst_type_key" class="form-select" required>
                        <?php foreach ($gstTypes as $gst): ?>
                            <option value="<?= e($gst['gst_type_key']) ?>" <?= $business['gst_type_key'] === $gst['gst_type_key'] ? 'selected' : '' ?>>
                                <?= e($gst['gst_type_name']) ?> - <?= e($gst['invoice_title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">GST Composition prints Bill of Supply.</small>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Activity</label>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="status" <?= (int)$business['status'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold">Active</label>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control"><?= e($business['address']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="glass-card mb-4">
        <div class="card-section-head">
            <div class="section-icon"><i class="bi bi-person-badge"></i></div>
            <div>
                <h3 class="section-head-title">Business Admin Login</h3>
                <div class="section-head-sub">Update admin login. Leave password empty if not changing.</div>
            </div>
        </div>

        <div class="card-body-custom">
            <div class="row g-4">
                <div class="col-lg-6">
                    <label class="form-label">Admin Username</label>
                    <input type="text" name="admin_username" class="form-control" value="<?= e($business['admin_username']) ?>" required>
                </div>

                <div class="col-lg-6">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Enter only if reset required">
                </div>
            </div>
        </div>
    </div>

    <div class="sticky-form-actions">
        <a href="businesses.php" class="btn btn-light">Cancel</a>
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-floppy me-1"></i> Update Business
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
