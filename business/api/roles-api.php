<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

if (!is_business_admin($conn)) {
    flash('error', 'Only Admin can manage roles.');
    redirect('../roles.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../roles.php');
}

verify_csrf();

$businessId = current_business_id();
$action = $_POST['action'] ?? '';

$hasStatus = table_has_column($conn, 'roles', 'status');
$hasUpdatedAt = table_has_column($conn, 'roles', 'updated_at');

$permissionColumns = ['can_view', 'can_create', 'can_edit', 'can_delete', 'can_approve'];

function role_name_exists_for_business(mysqli $conn, int $businessId, string $roleName, ?int $excludeRoleId = null): bool
{
    if ($excludeRoleId) {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM roles
            WHERE business_id = ?
              AND role_name = ?
              AND role_id <> ?
        ");
        mysqli_stmt_bind_param($stmt, 'isi', $businessId, $roleName, $excludeRoleId);
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM roles
            WHERE business_id = ?
              AND role_name = ?
        ");
        mysqli_stmt_bind_param($stmt, 'is', $businessId, $roleName);
    }

    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

try {
    if ($action === 'save_permissions') {
        $roleId = (int)($_POST['role_id'] ?? 0);
        $permissions = $_POST['permissions'] ?? [];

        if ($roleId <= 0) {
            throw new RuntimeException('Invalid role selected.');
        }

        $roleCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM roles WHERE business_id = ? AND role_id = ?");
        mysqli_stmt_bind_param($roleCheck, 'ii', $businessId, $roleId);
        mysqli_stmt_execute($roleCheck);
        $roleRow = mysqli_fetch_assoc(mysqli_stmt_get_result($roleCheck));
        mysqli_stmt_close($roleCheck);

        if ((int)($roleRow['total'] ?? 0) === 0) {
            throw new RuntimeException('Invalid role selected.');
        }

        mysqli_begin_transaction($conn);

        $stmt = mysqli_prepare($conn, "
            DELETE FROM business_role_sidebar_access
            WHERE business_id = ? AND role_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $roleId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $insert = mysqli_prepare($conn, "
            INSERT INTO business_role_sidebar_access
            (business_id, role_id, menu_id, can_view, can_create, can_edit, can_delete, can_approve)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($permissions as $menuId => $row) {
            $menuId = (int)$menuId;

            $menuCheck = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM business_sidebar_menus WHERE business_id = ? AND id = ?");
            mysqli_stmt_bind_param($menuCheck, 'ii', $businessId, $menuId);
            mysqli_stmt_execute($menuCheck);
            $menuRow = mysqli_fetch_assoc(mysqli_stmt_get_result($menuCheck));
            mysqli_stmt_close($menuCheck);

            if ((int)($menuRow['total'] ?? 0) === 0) {
                continue;
            }

            $canView = !empty($row['can_view']) ? 1 : 0;
            $canCreate = !empty($row['can_create']) ? 1 : 0;
            $canEdit = !empty($row['can_edit']) ? 1 : 0;
            $canDelete = !empty($row['can_delete']) ? 1 : 0;
            $canApprove = !empty($row['can_approve']) ? 1 : 0;

            if (($canView + $canCreate + $canEdit + $canDelete + $canApprove) === 0) {
                continue;
            }

            /*
             * Important:
             * can_view controls sidebar visibility.
             * If any action is selected, view should also be enabled automatically.
             */
            if ($canCreate || $canEdit || $canDelete || $canApprove) {
                $canView = 1;
            }

            mysqli_stmt_bind_param(
                $insert,
                'iiiiiiii',
                $businessId,
                $roleId,
                $menuId,
                $canView,
                $canCreate,
                $canEdit,
                $canDelete,
                $canApprove
            );
            mysqli_stmt_execute($insert);
        }

        mysqli_stmt_close($insert);

        log_activity($conn, 'Role Permissions', 'update', $roleId, null, $permissions);
        mysqli_commit($conn);

        flash('success', 'Sidebar access permissions updated successfully.');
        redirect('../roles.php');
    }

    if ($action === 'toggle_role') {
        $roleId = (int)($_POST['role_id'] ?? 0);

        if ($roleId <= 0) {
            throw new RuntimeException('Invalid role selected.');
        }

        if (!$hasStatus) {
            throw new RuntimeException('Roles status column is missing.');
        }

        $stmt = mysqli_prepare($conn, "
            UPDATE roles
            SET status = IF(status = 1, 0, 1)
            WHERE business_id = ? AND role_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $roleId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Roles', 'toggle_status', $roleId);
        flash('success', 'Role status updated successfully.');
        redirect('../roles.php');
    }

    $roleId = (int)($_POST['role_id'] ?? 0);
    $roleName = trim($_POST['role_name'] ?? '');
    $roleType = trim($_POST['role_type'] ?? 'custom');
    $status = (int)($_POST['status'] ?? 1);

    $allowedTypes = ['admin', 'sales', 'cashier', 'stock_manager', 'branch_manager', 'custom'];

    if ($roleName === '') {
        throw new RuntimeException('Role name is required.');
    }

    if (!in_array($roleType, $allowedTypes, true)) {
        $roleType = 'custom';
    }

    if ($action === 'create_role') {
        if (role_name_exists_for_business($conn, $businessId, $roleName)) {
            throw new RuntimeException('Role name already exists.');
        }

        $columns = ['business_id', 'role_name', 'role_type'];
        $placeholders = ['?', '?', '?'];
        $types = 'iss';
        $values = [$businessId, $roleName, $roleType];

        if ($hasStatus) {
            $columns[] = 'status';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $status;
        }

        $sql = "INSERT INTO roles (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        $newRoleId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if ($roleType === 'admin' && table_exists($conn, 'business_sidebar_menus') && table_exists($conn, 'business_role_sidebar_access')) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO business_role_sidebar_access
                (business_id, role_id, menu_id, can_view, can_create, can_edit, can_delete, can_approve)
                SELECT ?, ?, id, 1, 1, 1, 1, 1
                FROM business_sidebar_menus
                WHERE business_id = ?
                ON DUPLICATE KEY UPDATE
                    can_view = 1,
                    can_create = 1,
                    can_edit = 1,
                    can_delete = 1,
                    can_approve = 1
            ");
            mysqli_stmt_bind_param($stmt, 'iii', $businessId, $newRoleId, $businessId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        log_activity($conn, 'Roles', 'create', $newRoleId, null, ['role_name' => $roleName, 'role_type' => $roleType]);
        flash('success', 'Role created successfully.');
        redirect('../roles.php');
    }

    if ($action === 'update_role') {
        if ($roleId <= 0) {
            throw new RuntimeException('Invalid role selected.');
        }

        if (role_name_exists_for_business($conn, $businessId, $roleName, $roleId)) {
            throw new RuntimeException('Role name already exists.');
        }

        $sets = ['role_name = ?', 'role_type = ?'];
        $types = 'ss';
        $values = [$roleName, $roleType];

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
        $values[] = $roleId;

        $sql = "UPDATE roles SET " . implode(', ', $sets) . " WHERE business_id = ? AND role_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Roles', 'update', $roleId, null, ['role_name' => $roleName, 'role_type' => $roleType]);
        flash('success', 'Role updated successfully.');
        redirect('../roles.php');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    if (mysqli_errno($conn)) {
        @mysqli_rollback($conn);
    }
    flash('error', $e->getMessage());
    redirect('../roles.php');
}
?>
