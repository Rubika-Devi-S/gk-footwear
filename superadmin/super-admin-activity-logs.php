<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Super Admin Activity Logs';

$logs = [];

try {
    $stmt = $pdo->query("
        SELECT 
            sal.*,
            sa.name AS super_admin_name,
            sa.username AS super_admin_username
        FROM super_admin_activity_logs sal
        LEFT JOIN super_admins sa ON sa.id = sal.super_admin_id
        ORDER BY sal.log_id DESC
        LIMIT 300
    ");
    $logs = $stmt->fetchAll();
} catch (Throwable $e) {
    $logs = [];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Super Admin Activity Logs</h2>
        <div class="page-subtitle">Only Super Admin platform actions are shown here.</div>
    </div>
</div>

<div class="glass-card">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Super Admin</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Record</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No Super Admin activity logs found.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e(date('d-m-Y h:i A', strtotime($log['created_at']))) ?></td>
                    <td>
                        <b><?= e($log['super_admin_name'] ?: 'System') ?></b><br>
                        <small class="text-muted"><?= e($log['super_admin_username'] ?: '-') ?></small>
                    </td>
                    <td><?= e($log['module_name']) ?></td>
                    <td><?= e($log['action_type']) ?></td>
                    <td><?= e($log['record_type'] ?: '-') ?> #<?= e($log['record_id'] ?: '-') ?></td>
                    <td><?= e($log['ip_address'] ?: '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
