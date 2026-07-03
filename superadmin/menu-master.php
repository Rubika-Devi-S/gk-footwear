<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Menu Master';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Menu Master</h2>
        <div class="page-subtitle">Super Admin menu control.</div>
    </div>
</div>

<div class="glass-card p-5 text-center">
    <h4 class="fw-bold">Coming Next</h4>
    <p class="text-muted mb-0">This page can manage sidebar modules and page URLs.</p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
