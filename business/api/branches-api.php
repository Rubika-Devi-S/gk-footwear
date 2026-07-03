<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../branches.php');
}

verify_csrf();

$businessId = current_business_id();
$action = $_POST['action'] ?? '';

$hasFloorName = table_has_column($conn, 'branches', 'floor_name');
$hasAddress = table_has_column($conn, 'branches', 'address');
$hasMobile = table_has_column($conn, 'branches', 'mobile');
$hasStatus = table_has_column($conn, 'branches', 'status');
$hasUpdatedAt = table_has_column($conn, 'branches', 'updated_at');
$hasCreatedBy = table_has_column($conn, 'branches', 'created_by');
$hasUpdatedBy = table_has_column($conn, 'branches', 'updated_by');

function branch_api_permissions(mysqli $conn, string $pageUrl): array
{
    if (is_business_admin($conn)) {
        return [
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_delete' => true,
            'can_approve' => true,
        ];
    }

    $businessId = current_business_id();
    $roleId = current_role_id();

    $cols = ['can_view'];
    foreach (['can_create', 'can_edit', 'can_delete', 'can_approve'] as $col) {
        $cols[] = table_has_column($conn, 'business_role_sidebar_access', $col) ? $col : '0 AS ' . $col;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT " . implode(', ', $cols) . "
        FROM business_sidebar_menus sm
        INNER JOIN business_role_sidebar_access rsa
            ON rsa.menu_id = sm.id
           AND rsa.business_id = sm.business_id
           AND rsa.role_id = ?
        WHERE sm.business_id = ?
          AND sm.menu_url = ?
          AND sm.is_active = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'iis', $roleId, $businessId, $pageUrl);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        return [
            'can_view' => false,
            'can_create' => false,
            'can_edit' => false,
            'can_delete' => false,
            'can_approve' => false,
        ];
    }

    return [
        'can_view' => (int)($row['can_view'] ?? 0) === 1,
        'can_create' => (int)($row['can_create'] ?? 0) === 1,
        'can_edit' => (int)($row['can_edit'] ?? 0) === 1,
        'can_delete' => (int)($row['can_delete'] ?? 0) === 1,
        'can_approve' => (int)($row['can_approve'] ?? 0) === 1,
    ];
}

function branch_code_exists(mysqli $conn, int $businessId, string $branchCode, ?int $excludeBranchId = null): bool
{
    if ($excludeBranchId) {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM branches
            WHERE business_id = ?
              AND branch_code = ?
              AND branch_id <> ?
        ");
        mysqli_stmt_bind_param($stmt, 'isi', $businessId, $branchCode, $excludeBranchId);
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM branches
            WHERE business_id = ?
              AND branch_code = ?
        ");
        mysqli_stmt_bind_param($stmt, 'is', $businessId, $branchCode);
    }

    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function branch_has_dependency(mysqli $conn, int $businessId, int $branchId): bool
{
    $checks = [];

    if (table_has_column($conn, 'users', 'default_branch_id')) {
        $checks[] = ['users', 'default_branch_id'];
    }

    foreach ([
        'bills',
        'stock_inward',
        'stock_inward_items',
        'bill_items',
        'payments',
        'cashier_collections',
    ] as $table) {
        if (table_exists($conn, $table) && table_has_column($conn, $table, 'branch_id')) {
            $checks[] = [$table, 'branch_id'];
        }
    }

    foreach ($checks as [$table, $column]) {
        $tableSafe = str_replace('`', '', $table);
        $columnSafe = str_replace('`', '', $column);

        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM `{$tableSafe}`
            WHERE business_id = ?
              AND `{$columnSafe}` = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $branchId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

$permissions = branch_api_permissions($conn, 'branches.php');

try {
    if ($action === 'toggle_status') {
        if (!$permissions['can_edit']) {
            throw new RuntimeException('You do not have permission to change branch status.');
        }

        if (!$hasStatus) {
            throw new RuntimeException('Branch status column is missing.');
        }

        $branchId = (int)($_POST['branch_id'] ?? 0);

        if ($branchId <= 0) {
            throw new RuntimeException('Invalid branch selected.');
        }

        $stmt = mysqli_prepare($conn, "
            UPDATE branches
            SET status = IF(status = 1, 0, 1)
            WHERE business_id = ? AND branch_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $branchId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Branches', 'toggle_status', $branchId);
        flash('success', 'Branch status updated successfully.');
        redirect('../branches.php');
    }

    if ($action === 'delete_branch') {
        if (!$permissions['can_delete']) {
            throw new RuntimeException('You do not have permission to delete branches.');
        }

        $branchId = (int)($_POST['branch_id'] ?? 0);

        if ($branchId <= 0) {
            throw new RuntimeException('Invalid branch selected.');
        }

        if (branch_has_dependency($conn, $businessId, $branchId)) {
            throw new RuntimeException('This branch is already used in users/bills/stock. Deactivate it instead of deleting.');
        }

        $stmt = mysqli_prepare($conn, "
            DELETE FROM branches
            WHERE business_id = ? AND branch_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $branchId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Branches', 'delete', $branchId);
        flash('success', 'Branch deleted successfully.');
        redirect('../branches.php');
    }

    $branchId = (int)($_POST['branch_id'] ?? 0);
    $branchCode = strtoupper(trim($_POST['branch_code'] ?? ''));
    $branchName = trim($_POST['branch_name'] ?? '');
    $floorName = trim($_POST['floor_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($branchCode === '' || $branchName === '') {
        throw new RuntimeException('Branch code and branch name are required.');
    }

    if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $branchCode)) {
        throw new RuntimeException('Branch code must be 2-30 characters using letters, numbers, underscore or hyphen.');
    }

    if ($action === 'create_branch') {
        if (!$permissions['can_create']) {
            throw new RuntimeException('You do not have permission to create branches.');
        }

        if (branch_code_exists($conn, $businessId, $branchCode)) {
            throw new RuntimeException('Branch code already exists.');
        }

        $columns = ['business_id', 'branch_code', 'branch_name'];
        $placeholders = ['?', '?', '?'];
        $types = 'iss';
        $values = [$businessId, $branchCode, $branchName];

        if ($hasFloorName) {
            $columns[] = 'floor_name';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $floorName ?: null;
        }

        if ($hasMobile) {
            $columns[] = 'mobile';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $mobile ?: null;
        }

        if ($hasAddress) {
            $columns[] = 'address';
            $placeholders[] = '?';
            $types .= 's';
            $values[] = $address ?: null;
        }

        if ($hasStatus) {
            $columns[] = 'status';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $status;
        }

        if ($hasCreatedBy) {
            $columns[] = 'created_by';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = current_user_id();
        }

        $sql = "INSERT INTO branches (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        $newBranchId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Branches', 'create', $newBranchId, null, [
            'branch_code' => $branchCode,
            'branch_name' => $branchName,
        ]);

        flash('success', 'Branch created successfully.');
        redirect('../branches.php');
    }

    if ($action === 'update_branch') {
        if (!$permissions['can_edit']) {
            throw new RuntimeException('You do not have permission to edit branches.');
        }

        if ($branchId <= 0) {
            throw new RuntimeException('Invalid branch selected.');
        }

        if (branch_code_exists($conn, $businessId, $branchCode, $branchId)) {
            throw new RuntimeException('Branch code already exists.');
        }

        $sets = ['branch_code = ?', 'branch_name = ?'];
        $types = 'ss';
        $values = [$branchCode, $branchName];

        if ($hasFloorName) {
            $sets[] = 'floor_name = ?';
            $types .= 's';
            $values[] = $floorName ?: null;
        }

        if ($hasMobile) {
            $sets[] = 'mobile = ?';
            $types .= 's';
            $values[] = $mobile ?: null;
        }

        if ($hasAddress) {
            $sets[] = 'address = ?';
            $types .= 's';
            $values[] = $address ?: null;
        }

        if ($hasStatus) {
            $sets[] = 'status = ?';
            $types .= 'i';
            $values[] = $status;
        }

        if ($hasUpdatedBy) {
            $sets[] = 'updated_by = ?';
            $types .= 'i';
            $values[] = current_user_id();
        }

        if ($hasUpdatedAt) {
            $sets[] = 'updated_at = NOW()';
        }

        $types .= 'ii';
        $values[] = $businessId;
        $values[] = $branchId;

        $sql = "UPDATE branches SET " . implode(', ', $sets) . " WHERE business_id = ? AND branch_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Branches', 'update', $branchId, null, [
            'branch_code' => $branchCode,
            'branch_name' => $branchName,
        ]);

        flash('success', 'Branch updated successfully.');
        redirect('../branches.php');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('../branches.php');
}
?>
