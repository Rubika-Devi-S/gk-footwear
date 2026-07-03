<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'master-control.php');

$pageTitle = 'Master Control';
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

<div class="page-head-card mb-3">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1">Master Control</h1>
            <p class="text-muted-custom mb-0 small">Manage business configuration, sidebar, theme, reports and activity logs.</p>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php
    $cards = [
        ['System Config', 'Configure GST, invoice, barcode and business settings.', 'system-config.php', 'settings-2', 'linear-gradient(135deg,#818cf8,#2563eb)'],
        ['Manage Sidebar', 'Create sidebar menus and assign role-based access.', 'manage-sidebar.php', 'panel-left', 'linear-gradient(135deg,#0f766e,#2563eb)'],
        ['Theme', 'Update business panel colors with live preview.', 'theme.php', 'palette', 'linear-gradient(135deg,#f59e0b,#ef4444)'],
        ['Master Report', 'View master control summary and access report.', 'master-report.php', 'file-bar-chart', 'linear-gradient(135deg,#8b5cf6,#6366f1)'],
        ['Activity Logs', 'Track create, update, login and access actions.', 'activity-logs.php', 'history', 'linear-gradient(135deg,#334155,#0f172a)'],
    ];
    ?>
    <?php foreach ($cards as $card): ?>
        <div class="col-12 col-md-6 col-xl-4">
            <a href="<?= e($card[2]) ?>" class="text-decoration-none">
                <article class="kpi-card h-100">
                    <div class="kpi-icon text-white" style="background:<?= e($card[4]) ?>;"><i data-lucide="<?= e($card[3]) ?>"></i></div>
                    <div>
                        <div class="kpi-value fs-5"><?= e($card[0]) ?></div>
                        <p class="kpi-sub"><?= e($card[1]) ?></p>
                    </div>
                </article>
            </a>
        </div>
    <?php endforeach; ?>
</div>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>
<?php include __DIR__ . '/includes/script.php'; ?>
</body>
</html>
