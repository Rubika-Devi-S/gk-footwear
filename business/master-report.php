<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'master-report.php');

$pageTitle = 'Master Report';
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

$stats = [
    'menus' => 0,
    'roles' => 0,
    'users' => 0,
    'logs' => 0,
];

if (table_exists($conn, 'business_sidebar_menus')) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM business_sidebar_menus WHERE business_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $businessId);
    mysqli_stmt_execute($stmt);
    $stats['menus'] = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
    mysqli_stmt_close($stmt);
}

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM roles WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$stats['roles'] = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM users WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$stats['users'] = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
mysqli_stmt_close($stmt);

if (table_exists($conn, 'business_activity_logs')) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM business_activity_logs WHERE business_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $businessId);
    mysqli_stmt_execute($stmt);
    $stats['logs'] = (int)(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0);
    mysqli_stmt_close($stmt);
}

$roleRows = [];
$stmt = mysqli_prepare($conn, "
    SELECT r.role_name, r.role_type, COUNT(rsa.menu_id) AS menu_access
    FROM roles r
    LEFT JOIN business_role_sidebar_access rsa ON rsa.role_id = r.role_id AND rsa.business_id = r.business_id AND rsa.can_view = 1
    WHERE r.business_id = ?
    GROUP BY r.role_id
    ORDER BY r.role_id ASC
");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($rs = $stmt);
$rs = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($rs)) {
    $roleRows[] = $row;
}
mysqli_stmt_close($stmt);
?>

<div class="page-head-card mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Master Report</h1>
        <p class="text-muted-custom mb-0 small">Summary of sidebar, roles, users and activity logs.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ([['Menus','menus','panel-left'],['Roles','roles','shield'],['Users','users','users'],['Activity Logs','logs','history']] as $item): ?>
    <div class="col-12 col-sm-6 col-xl-3">
        <article class="kpi-card">
            <div class="kpi-icon text-white" style="background:linear-gradient(135deg,var(--brand-1),var(--brand-2));"><i data-lucide="<?= e($item[2]) ?>"></i></div>
            <div><div class="kpi-label"><?= e($item[0]) ?></div><p class="kpi-value"><?= (int)$stats[$item[1]] ?></p><p class="kpi-sub">Business control data</p></div>
        </article>
    </div>
    <?php endforeach; ?>
</div>

<section class="card-ui overflow-hidden">
    <div class="p-3 p-lg-4">
        <h2 class="fw-bold fs-6 mb-1">Role Access Summary</h2>
        <p class="text-muted-custom small mb-0">Menu access count for each business role.</p>
    </div>

    <div class="d-none d-md-block table-responsive px-3 px-lg-4 pb-3">
        <table class="table">
            <thead><tr><th>Role</th><th>Type</th><th>Menu Access</th></tr></thead>
            <tbody>
            <?php foreach ($roleRows as $row): ?>
                <tr><td><?= e($row['role_name']) ?></td><td><?= e($row['role_type']) ?></td><td><?= (int)$row['menu_access'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-md-none px-3 pb-3 d-grid gap-3">
        <?php foreach ($roleRows as $row): ?>
            <div class="mobile-data-card">
                <div class="fw-bold"><?= e($row['role_name']) ?></div>
                <small class="text-muted-custom"><?= e($row['role_type']) ?> · <?= (int)$row['menu_access'] ?> menu access</small>
            </div>
        <?php endforeach; ?>
    </div>
</section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>
<?php include __DIR__ . '/includes/script.php'; ?>
</body>
</html>
