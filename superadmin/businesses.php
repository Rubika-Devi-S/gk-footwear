<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Businesses';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $businessId = (int)($_POST['business_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($businessId > 0 && $action === 'toggle_status') {
        $stmt = $pdo->prepare("SELECT status FROM businesses WHERE business_id = ?");
        $stmt->execute([$businessId]);
        $current = $stmt->fetchColumn();

        if ($current !== false) {
            $newStatus = (int)$current === 1 ? 0 : 1;

            $update = $pdo->prepare("UPDATE businesses SET status = ? WHERE business_id = ?");
            $update->execute([$newStatus, $businessId]);

            insert_activity_log(
                $pdo,
                $businessId,
                null,
                null,
                null,
                'Super Admin - Business',
                $newStatus === 1 ? 'business_activated' : 'business_deactivated',
                $businessId,
                ['status' => (int)$current],
                ['status' => $newStatus]
            );

            flash('success', 'Business status updated successfully.');
        }
    }

    redirect('businesses.php');
}

$search = trim($_GET['search'] ?? '');
$params = [];
$where = "WHERE 1=1";

if ($search !== '') {
    $where .= " AND (b.business_code LIKE ? OR b.business_name LIKE ? OR b.owner_name LIKE ? OR b.mobile LIKE ? OR b.email LIKE ?)";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like, $like];
}

$hasLogo = column_exists($pdo, 'businesses', 'logo_path');
$logoSelect = $hasLogo ? "b.logo_path" : "NULL AS logo_path";

$stmt = $pdo->prepare("
    SELECT 
        b.business_id, b.business_code, b.business_name, b.owner_name, b.mobile, b.email, b.gst_type_key, b.status, b.created_at,
        {$logoSelect},
        COALESCE(br.total_branches, 0) AS total_branches,
        COALESCE(u.total_users, 0) AS total_users,
        COALESCE(inv.invoices_30d, 0) AS invoices_30d,
        COALESCE(inv.income_30d, 0) AS income_30d,
        inv.last_invoice
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
    LEFT JOIN (
        SELECT 
            business_id,
            SUM(CASE WHEN bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS invoices_30d,
            SUM(CASE WHEN bill_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN net_amount ELSE 0 END) AS income_30d,
            MAX(bill_date) AS last_invoice
        FROM bills
        GROUP BY business_id
    ) inv ON inv.business_id = b.business_id
    {$where}
    ORDER BY b.business_id DESC
");
$stmt->execute($params);
$businesses = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Businesses</h2>
        <div class="page-subtitle">Manage registered businesses, GST type, business login and access.</div>
    </div>
    <a href="business-create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> Add Business
    </a>
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
                    <th>Invoices<br>30D</th>
                    <th>Income 30D</th>
                    <th>Last Invoice</th>
                    <th>Activity</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$businesses): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">No businesses found.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($businesses as $row): ?>
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
                                <div class="muted">ID: <?= e($row['business_code']) ?> | Plan: Default</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div><?= e($row['mobile'] ?: '-') ?></div>
                        <div class="muted"><?= e($row['email'] ?: '-') ?></div>
                    </td>
                    <td><?= (int)$row['total_branches'] ?></td>
                    <td><?= (int)$row['total_users'] ?></td>
                    <td><?= (int)$row['invoices_30d'] ?></td>
                    <td>₹<?= number_format((float)$row['income_30d'], 2) ?></td>
                    <td><?= $row['last_invoice'] ? e(date('d-m-Y', strtotime($row['last_invoice']))) : '-' ?></td>
                    <td>
                        <?php if ((int)$row['status'] === 1): ?>
                            <span class="status-pill status-active">Active</span>
                        <?php else: ?>
                            <span class="status-pill status-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="action-btn" title="View" href="business-view.php?id=<?= (int)$row['business_id'] ?>">
                            <i class="bi bi-eye"></i>
                        </a>

                        <a class="action-btn" title="Edit" href="business-edit.php?id=<?= (int)$row['business_id'] ?>">
                            <i class="bi bi-pencil"></i>
                        </a>

                        <form method="post" class="d-inline" data-confirm="Change business active status?">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="business_id" value="<?= (int)$row['business_id'] ?>">
                            <input type="hidden" name="action" value="toggle_status">
                            <button class="action-btn" title="Activate / Deactivate" type="submit">
                                <i class="bi bi-power"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
