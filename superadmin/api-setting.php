<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_super_admin();

$pageTitle = 'API Setting';

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-hero">
    <div>
        <h2 class="page-title">API Setting</h2>
        <div class="page-subtitle">Integration configuration.</div>
    </div>
</div>

<div class="glass-card p-5 text-center">
    <h4 class="fw-bold">Coming Next</h4>
    <p class="text-muted mb-0">This page can manage WhatsApp, barcode, payment gateway and API settings.</p>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
