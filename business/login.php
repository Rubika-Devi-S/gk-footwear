<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

if (!empty($_SESSION['business_id']) && !empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Business Login - GK Footwear POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/includes/links.php'; ?>
    <style>
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, #0f172a, #2563eb);
        }
        .login-card {
            width: min(430px, calc(100vw - 28px));
            background: var(--card-bg);
            border-radius: 26px;
            box-shadow: 0 28px 80px rgba(0,0,0,.28);
            padding: 30px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/page-message.php'; ?>

<div class="login-card">
    <h3 class="fw-bold mb-1">Business Login</h3>
    <p class="text-muted-custom mb-4">GK Footwear Universal Billing POS</p>

    <form method="post" action="api/login-api.php">
        <?= csrf_field(); ?>

        <div class="mb-3">
            <label class="form-label fw-bold">Username</label>
            <input type="text" name="username" class="form-control rounded-4" required autofocus>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Password</label>
            <input type="password" name="password" class="form-control rounded-4" required>
        </div>

        <button class="btn brand-gradient w-100 rounded-4 fw-bold py-3">Login</button>
    </form>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>
</body>
</html>
