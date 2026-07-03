<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

if (!empty($_SESSION['super_admin_id'])) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $stmt = $pdo->prepare("
            SELECT * 
            FROM super_admins 
            WHERE username = ? AND status = 1 
            LIMIT 1
        ");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['super_admin_id'] = (int)$admin['id'];
            $_SESSION['super_admin_name'] = $admin['name'];
            $_SESSION['super_admin_username'] = $admin['username'];

            redirect('dashboard.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Super Admin Login | GK Footwear POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 20% 20%, rgba(255,255,255,.20), transparent 25%),
                linear-gradient(135deg, #0f3c88, #0b1f52);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 430px;
            background: rgba(255,255,255,.94);
            border-radius: 28px;
            padding: 34px;
            box-shadow: 0 28px 80px rgba(0,0,0,.28);
        }
        .form-control {
            min-height: 56px;
            border-radius: 16px;
        }
        .btn {
            min-height: 54px;
            border-radius: 16px;
            font-weight: 800;
        }
    </style>
</head>
<body>

<?php if ($error): ?>
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
        <div class="toast align-items-center border-0 shadow-lg text-bg-danger" role="alert" data-bs-delay="3500">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>Error</strong><br>
                    <?= e($error) ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="login-card">
    <h3 class="mb-1 fw-bold">Super Admin</h3>
    <p class="text-muted mb-4">GK Footwear Universal Billing POS</p>

    <form method="post">
        <?= csrf_field(); ?>

        <div class="mb-3">
            <label class="form-label fw-bold">Username</label>
            <input type="text" name="username" class="form-control" required autofocus>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <button class="btn btn-primary w-100" type="submit">Login</button>
    </form>

    <div class="mt-4 text-muted small">
        Default: <b>superadmin</b> / <b>sadmin@123</b>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.toast').forEach(toastEl => {
    const toast = new bootstrap.Toast(toastEl);
    toast.show();
});
</script>
</body>
</html>
