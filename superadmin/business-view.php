<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'View Business';

$businessId = (int)($_GET['id'] ?? 0);

if ($businessId <= 0) {
    flash('danger', 'Invalid business.');
    redirect('businesses.php');
}

$hasLogo = column_exists($pdo, 'businesses', 'logo_path');
$logoSelect = $hasLogo ? "b.logo_path" : "NULL AS logo_path";

$stmt = $pdo->prepare("
    SELECT 
        b.*,
        {$logoSelect},
        blc.username AS admin_username,
        bs.purchase_rate_required,
        bs.tax_columns_enabled,
        inv.invoice_title,
        inv.paper_size,
        inv.show_composition_note
    FROM businesses b
    LEFT JOIN business_login_credentials blc ON blc.business_id = b.business_id
    LEFT JOIN business_settings bs ON bs.business_id = b.business_id
    LEFT JOIN invoice_settings inv ON inv.business_id = b.business_id AND inv.branch_id IS NULL
    WHERE b.business_id = ?
    LIMIT 1
");
$stmt->execute([$businessId]);
$business = $stmt->fetch();

if (!$business) {
    flash('danger', 'Business not found.');
    redirect('businesses.php');
}

$branches = (int)$pdo->prepare("SELECT COUNT(*) FROM branches WHERE business_id = ?");
$stmt = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE business_id = ?");
$stmt->execute([$businessId]);
$branchCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE business_id = ?");
$stmt->execute([$businessId]);
$userCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bills WHERE business_id = ?");
$stmt->execute([$businessId]);
$billCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(net_amount),0) FROM bills WHERE business_id = ?");
$stmt->execute([$businessId]);
$totalIncome = (float)$stmt->fetchColumn();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Business View</h2>
        <div class="page-subtitle">View profile, settings and current status.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="businesses.php" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i> Back</a>
        <a href="business-edit.php?id=<?= (int)$businessId ?>" class="btn btn-primary"><i class="bi bi-pencil me-1"></i> Edit</a>
    </div>
</div>

<div class="glass-card mb-4">
    <div class="card-section-head">
        <div class="business-avatar" style="width:70px;height:70px;border-radius:22px;">
            <?php if (!empty($business['logo_path'])): ?>
                <img src="<?= e(logo_url($business['logo_path'])) ?>" alt="">
            <?php else: ?>
                <?= e(business_initial($business['business_name'])) ?>
            <?php endif; ?>
        </div>
        <div>
            <h3 class="section-head-title"><?= e($business['business_name']) ?></h3>
            <div class="section-head-sub">
                ID: <?= e($business['business_code']) ?> |
                <?= e(str_replace('_', ' ', strtoupper($business['gst_type_key']))) ?> |
                <?= (int)$business['status'] === 1 ? 'Active' : 'Inactive' ?>
            </div>
        </div>
    </div>

    <div class="card-body-custom">
        <div class="row g-4">
            <div class="col-md-3">
                <div class="p-4 bg-white rounded-4">
                    <div class="text-muted">Branches</div>
                    <h3 class="fw-bold mb-0"><?= $branchCount ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 bg-white rounded-4">
                    <div class="text-muted">Users</div>
                    <h3 class="fw-bold mb-0"><?= $userCount ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 bg-white rounded-4">
                    <div class="text-muted">Bills</div>
                    <h3 class="fw-bold mb-0"><?= $billCount ?></h3>
                </div>
            </div>
            <div class="col-md-3">
                <div class="p-4 bg-white rounded-4">
                    <div class="text-muted">Income</div>
                    <h3 class="fw-bold mb-0">₹<?= number_format($totalIncome, 2) ?></h3>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div class="row g-4">
            <div class="col-md-4">
                <label class="text-muted small">Owner</label>
                <div class="fw-bold"><?= e($business['owner_name'] ?: '-') ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Mobile</label>
                <div class="fw-bold"><?= e($business['mobile'] ?: '-') ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Email</label>
                <div class="fw-bold"><?= e($business['email'] ?: '-') ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">GSTIN</label>
                <div class="fw-bold"><?= e($business['gstin'] ?: '-') ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Invoice Title</label>
                <div class="fw-bold"><?= e($business['invoice_title'] ?: '-') ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Purchase Rate</label>
                <div class="fw-bold"><?= (int)$business['purchase_rate_required'] === 1 ? 'Required' : 'Optional' ?></div>
            </div>
            <div class="col-12">
                <label class="text-muted small">Address</label>
                <div class="fw-bold"><?= nl2br(e($business['address'] ?: '-')) ?></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
