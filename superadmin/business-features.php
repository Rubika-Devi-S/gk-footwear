<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Business Features';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Business Features</h2>
        <div class="page-subtitle">Business-wise feature access control.</div>
    </div>
</div>

<div class="glass-card p-5 text-center">
    <h4 class="fw-bold">Coming Next</h4>
    <p class="text-muted mb-0">This page can enable or disable billing, stock, reports and cashier modules per business.</p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
