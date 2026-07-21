<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'activity-logs.php');

$pageTitle = 'Activity Logs';

/*
|--------------------------------------------------------------------------
| Activity log timezone settings
|--------------------------------------------------------------------------
| Most hosting/database servers store CURRENT_TIMESTAMP values in UTC.
| The page converts each stored activity time to India Standard Time.
|
| Change ACTIVITY_LOG_SOURCE_TIMEZONE to 'Asia/Kolkata' only when your
| database already stores created_at values in India time.
*/
const ACTIVITY_LOG_SOURCE_TIMEZONE = 'UTC';
const ACTIVITY_LOG_DISPLAY_TIMEZONE = 'Asia/Kolkata';

date_default_timezone_set(ACTIVITY_LOG_DISPLAY_TIMEZONE);

if (!function_exists('format_activity_log_time')) {
    function format_activity_log_time($value, string $format = 'd-m-Y h:i A'): string
    {
        $raw = trim((string)$value);

        if (
            $raw === '' ||
            $raw === '0000-00-00 00:00:00' ||
            strtolower($raw) === 'null'
        ) {
            return '-';
        }

        try {
            $sourceTimezone = new DateTimeZone(ACTIVITY_LOG_SOURCE_TIMEZONE);
            $displayTimezone = new DateTimeZone(ACTIVITY_LOG_DISPLAY_TIMEZONE);

            $date = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $raw,
                $sourceTimezone
            );

            if (!$date) {
                $date = new DateTimeImmutable($raw, $sourceTimezone);
            }

            return $date
                ->setTimezone($displayTimezone)
                ->format($format);
        } catch (Throwable $exception) {
            return $raw;
        }
    }
}
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
$businessId = (int) current_business_id();
$logs = [];

if (table_exists($conn, 'business_activity_logs')) {
    $stmt = mysqli_prepare($conn, "
        SELECT
            al.*,
            u.name AS user_name,
            r.role_name
        FROM business_activity_logs al
        LEFT JOIN users u
            ON u.user_id = al.user_id
           AND u.business_id = al.business_id
        LEFT JOIN roles r
            ON r.role_id = al.role_id
        WHERE al.business_id = ?
        ORDER BY al.created_at DESC, al.log_id DESC
        LIMIT 200
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $businessId);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $logs[] = $row;
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<div class="page-head-card mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Activity Logs</h1>
        <p class="text-muted-custom mb-0 small">
            Track login, create, update, delete and access activities.
            Times are displayed in India Standard Time.
        </p>
    </div>
</div>

<section class="card-ui overflow-hidden">
    <div class="p-3 p-lg-4">
        <h2 class="fw-bold fs-6 mb-1">Recent Activity</h2>
        <p class="text-muted-custom small mb-0">
            Latest 200 activities for this business, displayed in IST.
        </p>
    </div>

    <div class="d-none d-md-block table-responsive px-3 px-lg-4 pb-3">
        <table class="table">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>User</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        No business activity logs found.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($logs as $log): ?>
                <tr>
                    <td>
                        <?= e(format_activity_log_time($log['created_at'] ?? null)) ?>
                    </td>
                    <td>
                        <?= e($log['user_name'] ?: 'System') ?>
                        <br>
                        <small class="text-muted-custom">
                            <?= e($log['role_name'] ?: '-') ?>
                        </small>
                    </td>
                    <td><?= e($log['module_name'] ?? '-') ?></td>
                    <td><?= e($log['action_type'] ?? '-') ?></td>
                    <td><?= e($log['ip_address'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="d-md-none px-3 pb-3 d-grid gap-3">
        <?php if (!$logs): ?>
            <div class="mobile-data-card text-center text-muted">
                No business activity logs found.
            </div>
        <?php endif; ?>

        <?php foreach ($logs as $log): ?>
            <div class="mobile-data-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <div class="fw-bold">
                            <?= e($log['module_name'] ?? '-') ?>
                        </div>
                        <small class="text-muted-custom">
                            <?= e($log['action_type'] ?? '-') ?>
                        </small>
                    </div>

                    <small class="text-muted-custom text-end">
                        <?= e(format_activity_log_time(
                            $log['created_at'] ?? null,
                            'd-m-Y h:i A'
                        )) ?>
                    </small>
                </div>

                <div class="small mt-2">
                    <?= e($log['user_name'] ?: 'System') ?>
                    ·
                    <?= e($log['ip_address'] ?? '-') ?>
                </div>
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
