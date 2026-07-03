<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

if (!is_business_admin($conn)) {
    flash('error', 'Only Admin can manage users.');
    redirect('../users.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../users.php');
}

verify_csrf();

$businessId = current_business_id();
$action = $_POST['action'] ?? '';

$hasEmail = table_has_column($conn, 'users', 'email');
$hasMobile = table_has_column($conn, 'users', 'mobile');
$hasStatus = table_has_column($conn, 'users', 'status');
$hasUpdatedAt = table_has_column($conn, 'users', 'updated_at');

function username_exists_for_business(mysqli $conn, int $businessId, string $username, ?int $excludeUserId = null): bool
{
    if ($excludeUserId) {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM users
            WHERE business_id = ?
              AND username = ?
              AND user_id <> ?
        ");
        mysqli_stmt_bind_param($stmt, 'isi', $businessId, $username, $excludeUserId);
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM users
            WHERE business_id = ?
              AND username = ?
        ");
        mysqli_stmt_bind_param($stmt, 'is', $businessId, $username);
    }

    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

try {
    if ($action === 'toggle_user') {
        $userId = (int)($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            throw new RuntimeException('Invalid user selected.');
        }

        if (!$hasStatus) {
            throw new RuntimeException('Users status column is missing.');
        }

        if ($userId === current_user_id()) {
            throw new RuntimeException('You cannot deactivate your own login.');
        }

        $stmt = mysqli_prepare($conn, "
            UPDATE users
            SET status = IF(status = 1, 0, 1)
            WHERE business_id = ? AND user_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Users', 'toggle_status', $userId);
        flash('success', 'User status updated successfully.');
        redirect('../users.php');
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $roleId = (int)($_POST['role_id'] ?? 0);
    $branchId = !empty($_POST['default_branch_id']) ? (int)$_POST['default_branch_id'] : null;
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($name === '' || $username === '' || $roleId <= 0) {
        throw new RuntimeException('Name, username and role are required.');
    }

    if ($action === 'create_user' && strlen($password) < 6) {
        throw new RuntimeException('Password must be minimum 6 characters for new user.');
    }

    if ($action === 'update_user' && $password !== '' && strlen($password) < 6) {
        throw new RuntimeException('Password must be minimum 6 characters.');
    }

    $roleCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM roles WHERE business_id = ? AND role_id = ?");
    mysqli_stmt_bind_param($roleCheck, 'ii', $businessId, $roleId);
    mysqli_stmt_execute($roleCheck);
    $roleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($roleCheck));
    mysqli_stmt_close($roleCheck);

    if ((int)($roleRow['total'] ?? 0) === 0) {
        throw new RuntimeException('Invalid role selected.');
    }

    if ($branchId) {
        $branchCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM branches WHERE business_id = ? AND branch_id = ?");
        mysqli_stmt_bind_param($branchCheck, 'ii', $businessId, $branchId);
        mysqli_stmt_execute($branchCheck);
        $branchRow = mysqli_fetch_assoc(mysqli_stmt_get_result($branchCheck));
        mysqli_stmt_close($branchCheck);

        if ((int)($branchRow['total'] ?? 0) === 0) {
            throw new RuntimeException('Invalid branch selected.');
        }
    }

    if ($action === 'create_user') {
        if (username_exists_for_business($conn, $businessId, $username)) {
            throw new RuntimeException('Username already exists.');
        }

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $columns = ['business_id', 'default_branch_id', 'role_id', 'name', 'username', 'password', 'password_reset_required'];
        $placeholders = ['?', '?', '?', '?', '?', '?', '0'];
        $types = 'iiisss';
        $values = [$businessId, $branchId, $roleId, $name, $username, $passwordHash];

        if ($hasMobile) {
            $columns[] = 'mobile';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $mobile ?: null;
        }

        if ($hasEmail) {
            $columns[] = 'email';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $email ?: null;
        }

        if ($hasStatus) {
            $columns[] = 'status';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $status;
        }

        $sql = "INSERT INTO users (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        $newUserId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Users', 'create', $newUserId, null, ['name' => $name, 'username' => $username, 'role_id' => $roleId]);
        flash('success', 'User created successfully.');
        redirect('../users.php');
    }

    if ($action === 'update_user') {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user selected.');
        }

        if (username_exists_for_business($conn, $businessId, $username, $userId)) {
            throw new RuntimeException('Username already exists.');
        }

        $sets = ['default_branch_id = ?', 'role_id = ?', 'name = ?', 'username = ?'];
        $types = 'iiss';
        $values = [$branchId, $roleId, $name, $username];

        if ($password !== '') {
            $sets[] = 'password = ?';
            $types .= 's';
            $values[] = password_hash($password, PASSWORD_BCRYPT);
        }

        if ($hasMobile) {
            $sets[] = 'mobile = ?';
            $types .= 's';
            $values[] = $mobile ?: null;
        }

        if ($hasEmail) {
            $sets[] = 'email = ?';
            $types .= 's';
            $values[] = $email ?: null;
        }

        if ($hasStatus) {
            $sets[] = 'status = ?';
            $types .= 'i';
            $values[] = $status;
        }

        if ($hasUpdatedAt) {
            $sets[] = 'updated_at = NOW()';
        }

        $types .= 'ii';
        $values[] = $businessId;
        $values[] = $userId;

        $sql = "UPDATE users SET " . implode(', ', $sets) . " WHERE business_id = ? AND user_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Users', 'update', $userId, null, ['name' => $name, 'username' => $username, 'role_id' => $roleId]);
        flash('success', 'User updated successfully.');
        redirect('../users.php');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('../users.php');
}
?>
