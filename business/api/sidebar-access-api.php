<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

if (!is_business_admin($conn)) {
    flash('error', 'Only Admin can update role access.');
    redirect('../manage-sidebar.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../manage-sidebar.php');
}

verify_csrf();

$businessId = current_business_id();
$roleId = (int)($_POST['role_id'] ?? 0);
$menuIds = array_map('intval', $_POST['menu_ids'] ?? []);

if ($roleId <= 0) {
    flash('error', 'Invalid role selected.');
    redirect('../manage-sidebar.php');
}

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare($conn, "
        DELETE FROM business_role_sidebar_access
        WHERE business_id = ? AND role_id = ?
    ");
    mysqli_stmt_bind_param($stmt, "ii", $businessId, $roleId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($menuIds) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO business_role_sidebar_access
            (business_id, role_id, menu_id, can_view)
            VALUES (?, ?, ?, 1)
        ");

        foreach ($menuIds as $menuId) {
            mysqli_stmt_bind_param($stmt, "iii", $businessId, $roleId, $menuId);
            mysqli_stmt_execute($stmt);
        }

        mysqli_stmt_close($stmt);
    }

    log_activity($conn, 'Role Sidebar Access', 'update', $roleId, null, $menuIds);
    mysqli_commit($conn);

    flash('success', 'Role based sidebar access updated successfully.');
} catch (Throwable $e) {
    mysqli_rollback($conn);
    flash('error', 'Access update failed: ' . $e->getMessage());
}

redirect('../manage-sidebar.php?role_id=' . $roleId);
?>
