<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'activity-logs.php');

$pageTitle = 'Activity Logs';
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
$logs = [];

if (table_exists($conn, 'business_activity_logs')) {
    $stmt = mysqli_prepare($conn, "
        SELECT 
            al.*,
            u.name AS user_name,
            r.role_name
        FROM business_activity_logs al
        LEFT JOIN users u ON u.user_id = al.user_id
        LEFT JOIN roles r ON r.role_id = al.role_id
        WHERE al.business_id = ?
        ORDER BY al.log_id DESC
        LIMIT 200
    ");
    mysqli_stmt_bind_param($stmt, "i", $businessId);
    mysqli_stmt_execute($rs = $stmt);
    $rs = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($rs)) {
        $logs[] = $row;
    }
    mysqli_stmt_close($stmt);
}
?>

<div class="page-head-card mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Activity Logs</h1>
        <p class="text-muted-custom mb-0 small">Track login, create, update, delete and access activities.</p>
    </div>
</div>

<section class="card-ui overflow-hidden">
    <div class="p-3 p-lg-4">
        <h2 class="fw-bold fs-6 mb-1">Recent Activity</h2>
        <p class="text-muted-custom small mb-0">Last 200 activities for this business only.</p>
    </div>

    <div class="d-none d-md-block table-responsive px-3 px-lg-4 pb-3">
        <table class="table">
            <thead><tr><th>Date</th><th>User</th><th>Module</th><th>Action</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No business activity logs found.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e(date('d-m-Y h:i A', strtotime($log['created_at']))) ?></td>
                    <td><?= e($log['user_name'] ?: 'System') ?><br><small class="text-muted-custom"><?= e($log['role_name'] ?: '-') ?></small></td>
                    <td><?= e($log['module_name']) ?></td>
                    <td><?= e($log['action_type']) ?></td>
                    <td><?= e($log['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-md-none px-3 pb-3 d-grid gap-3">
        <?php if (!$logs): ?>
            <div class="mobile-data-card text-center text-muted">No business activity logs found.</div>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
            <div class="mobile-data-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="fw-bold"><?= e($log['module_name']) ?></div>
                        <small class="text-muted-custom"><?= e($log['action_type']) ?></small>
                    </div>
                    <small class="text-muted-custom"><?= e(date('d-m h:i A', strtotime($log['created_at']))) ?></small>
                </div>
                <div class="small mt-2"><?= e($log['user_name'] ?: 'System') ?> · <?= e($log['ip_address']) ?></div>
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
