<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'Business Users';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div>
        <h2 class="page-title">Business Users</h2>
        <div class="page-subtitle">Business user management overview.</div>
    </div>
</div>

<div class="glass-card p-5 text-center">
    <h4 class="fw-bold">Coming Next</h4>
    <p class="text-muted mb-0">This page can show Admin, Sales, Cashier and Stock Manager users.</p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
