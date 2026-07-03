<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../login.php');
}

verify_csrf();

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    flash('error', 'Username and password are required.');
    redirect('../login.php');
}

$stmt = mysqli_prepare($conn, "
    SELECT 
        u.user_id,
        u.business_id,
        u.default_branch_id,
        u.role_id,
        u.name,
        u.email,
        u.username,
        u.password,
        u.status,
        b.business_name,
        b.business_code,
        b.status AS business_status,
        r.role_name,
        r.role_type
    FROM users u
    INNER JOIN businesses b ON b.business_id = u.business_id
    INNER JOIN roles r ON r.role_id = u.role_id
    WHERE u.username = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$user || !password_verify($password, $user['password'])) {
    flash('error', 'Invalid username or password.');
    redirect('../login.php');
}

if ((int)$user['status'] !== 1 || (int)$user['business_status'] !== 1) {
    flash('error', 'Your account or business is inactive.');
    redirect('../login.php');
}

$_SESSION['business_id'] = (int)$user['business_id'];
$_SESSION['business_code'] = $user['business_code'];
$_SESSION['business_name'] = $user['business_name'];
$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['role_id'] = (int)$user['role_id'];
$_SESSION['role_name'] = $user['role_name'];
$_SESSION['role_type'] = $user['role_type'];
$_SESSION['name'] = $user['name'];
$_SESSION['email'] = $user['email'] ?? '';
$_SESSION['username'] = $user['username'];
$_SESSION['branch_id'] = $user['default_branch_id'] ? (int)$user['default_branch_id'] : null;

$update = mysqli_prepare($conn, "UPDATE users SET last_login_at = NOW() WHERE user_id = ?");
mysqli_stmt_bind_param($update, "i", $_SESSION['user_id']);
mysqli_stmt_execute($update);
mysqli_stmt_close($update);

log_activity($conn, 'Login', 'login_success', $_SESSION['user_id']);

flash('success', 'Login successful. Welcome ' . $user['name'] . '.');
redirect('../dashboard.php');
?>
