<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Dashboard';

$totalBusinesses = (int)$pdo->query("SELECT COUNT(*) FROM businesses")->fetchColumn();
$activeBusinesses = (int)$pdo->query("SELECT COUNT(*) FROM businesses WHERE status = 1")->fetchColumn();
$totalBranches = (int)$pdo->query("SELECT COUNT(*) FROM branches")->fetchColumn();
$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

$hasLogo = column_exists($pdo, 'businesses', 'logo_path');
$logoSelect = $hasLogo ? "b.logo_path" : "NULL AS logo_path";

$recent = $pdo->query("
    SELECT 
        b.business_id, b.business_code, b.business_name, b.owner_name, b.mobile, b.email, b.gst_type_key, b.status, b.created_at,
        {$logoSelect},
        COALESCE(br.total_branches, 0) AS total_branches,
        COALESCE(u.total_users, 0) AS total_users
    FROM businesses b
    LEFT JOIN (
        SELECT business_id, COUNT(*) AS total_branches
        FROM branches
        GROUP BY business_id
    ) br ON br.business_id = b.business_id
    LEFT JOIN (
        SELECT business_id, COUNT(*) AS total_users
        FROM users
        GROUP BY business_id
    ) u ON u.business_id = b.business_id
    ORDER BY b.business_id DESC
    LIMIT 8
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="glass-card p-4">
            <div class="text-muted">Total Businesses</div>
            <h2 class="fw-bold mb-0"><?= $totalBusinesses ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card p-4">
            <div class="text-muted">Active Businesses</div>
            <h2 class="fw-bold text-success mb-0"><?= $activeBusinesses ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card p-4">
            <div class="text-muted">Total Branches</div>
            <h2 class="fw-bold mb-0"><?= $totalBranches ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="glass-card p-4">
            <div class="text-muted">Business Users</div>
            <h2 class="fw-bold mb-0"><?= $totalUsers ?></h2>
        </div>
    </div>
</div>

<div class="page-hero">
    <div>
        <h2 class="page-title">Businesses</h2>
        <div class="page-subtitle">Registered businesses and quick activity overview.</div>
    </div>
    <a href="business-create.php" class="btn btn-primary">+ Register Business</a>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Business</th>
                    <th>Contact</th>
                    <th>Branches</th>
                    <th>Users</th>
                    <th>GST Type</th>
                    <th>Activity</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No businesses registered.</td></tr>
            <?php endif; ?>

            <?php foreach ($recent as $row): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="business-avatar">
                                <?php if (!empty($row['logo_path'])): ?>
                                    <img src="<?= e(logo_url($row['logo_path'])) ?>" alt="">
                                <?php else: ?>
                                    <?= e(business_initial($row['business_name'])) ?>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="fw-black"><?= e($row['business_name']) ?></div>
                                <div class="muted">ID: <?= e($row['business_code']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?= e($row['mobile'] ?: '-') ?></div>
                        <div class="muted"><?= e($row['email'] ?: '-') ?></div>
                    </td>
                    <td><?= (int)$row['total_branches'] ?></td>
                    <td><?= (int)$row['total_users'] ?></td>
                    <td><?= e(str_replace('_', ' ', strtoupper($row['gst_type_key']))) ?></td>
                    <td>
                        <?php if ((int)$row['status'] === 1): ?>
                            <span class="status-pill status-active">Active</span>
                        <?php else: ?>
                            <span class="status-pill status-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="action-btn" href="business-view.php?id=<?= (int)$row['business_id'] ?>"><i class="bi bi-eye"></i></a>
                        <a class="action-btn" href="business-edit.php?id=<?= (int)$row['business_id'] ?>"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
