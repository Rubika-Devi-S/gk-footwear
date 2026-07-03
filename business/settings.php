<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';

require_business_login();

$pageTitle = 'Settings';
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
<div id="themeToastWrap" class="theme-toast-wrap" aria-live="polite" aria-atomic="true"></div>
<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>
        <section class="page-section p-3 p-lg-3">

<div class="page-head mb-3">
    <div>
        <h1 class="h4 fw-bold mb-1">Settings</h1>
        <p class="text-muted-custom mb-0 small">This page will be developed using the same API + toast + responsive table/card structure.</p>
    </div>
</div>

<div class="card-ui p-4 text-center">
    <h2 class="h5 fw-bold mb-2">Coming Next</h2>
    <p class="text-muted-custom mb-0">After confirming the dashboard, branches, and categories pages, we can build Settings module.</p>
</div>

        <?php include __DIR__ . '/includes/footer.php'; ?>
