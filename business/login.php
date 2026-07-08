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
        .password-wrap {
            position: relative;
        }
        .password-wrap .form-control {
            padding-right: 48px;
        }
        .password-toggle-btn {
            position: absolute;
            top: 50%;
            right: 10px;
            transform: translateY(-50%);
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 12px;
            background: transparent;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 3;
        }
        .password-toggle-btn:hover {
            background: rgba(15, 23, 42, .08);
            color: #0f172a;
        }
        .password-toggle-btn i {
            width: 18px;
            height: 18px;
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
            <input type="text" name="username" class="form-control rounded-4" placeholder="Enter username" required autofocus>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Password</label>
            <div class="password-wrap">
                <input type="password" name="password" id="password" class="form-control rounded-4" placeholder="Enter password" required>
                <button type="button" class="password-toggle-btn" id="passwordToggleBtn" aria-label="Show password">
                    <i data-lucide="eye" id="passwordToggleIcon"></i>
                </button>
            </div>
        </div>

        <button class="btn brand-gradient w-100 rounded-4 fw-bold py-3">Login</button>
    </form>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('passwordToggleBtn');
    const toggleIcon = document.getElementById('passwordToggleIcon');

    if (!passwordInput || !toggleBtn) {
        return;
    }

    toggleBtn.addEventListener('click', function () {
        const isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');

        if (toggleIcon) {
            toggleIcon.setAttribute('data-lucide', isHidden ? 'eye-off' : 'eye');
        }

        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    });

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
});
</script>
</body>
</html>
