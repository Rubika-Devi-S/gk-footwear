<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Business Sidebar Access';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Business Sidebar Access</h2>
        <div class="page-subtitle">Business-wise sidebar/menu access.</div>
    </div>
</div>

<div class="glass-card p-5 text-center">
    <h4 class="fw-bold">Coming Next</h4>
    <p class="text-muted mb-0">This page can assign which menu is visible for each business.</p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
