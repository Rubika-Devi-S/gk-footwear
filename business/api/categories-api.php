<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../categories.php');
}

verify_csrf();

$businessId = current_business_id();
$action = $_POST['action'] ?? '';

$hasStatus = table_has_column($conn, 'categories', 'status');
$hasUpdatedAt = table_has_column($conn, 'categories', 'updated_at');

function category_api_permissions(mysqli $conn): array
{
    if (is_business_admin($conn)) {
        return ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true,
            'can_export' => true];
    }

    $businessId = current_business_id();
    $roleId = current_role_id();

    $cols = ['can_view'];
    foreach (['can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'] as $col) {
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
          AND sm.menu_url = 'categories.php'
          AND sm.is_active = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $roleId, $businessId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        return ['can_view' => false, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'can_print' => false,
            'can_export' => false];
    }

    return [
        'can_view' => (int)($row['can_view'] ?? 0) === 1,
        'can_create' => (int)($row['can_create'] ?? 0) === 1,
        'can_edit' => (int)($row['can_edit'] ?? 0) === 1,
        'can_delete' => (int)($row['can_delete'] ?? 0) === 1,
        'can_print' => (int)($row['can_print'] ?? 0) === 1,
        'can_export' => (int)($row['can_export'] ?? 0) === 1,
    ];
}

function category_name_exists(mysqli $conn, int $businessId, string $categoryName, ?int $excludeCategoryId = null): bool
{
    if ($excludeCategoryId) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM categories WHERE business_id = ? AND category_name = ? AND category_id <> ?");
        mysqli_stmt_bind_param($stmt, 'isi', $businessId, $categoryName, $excludeCategoryId);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM categories WHERE business_id = ? AND category_name = ?");
        mysqli_stmt_bind_param($stmt, 'is', $businessId, $categoryName);
    }

    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function category_has_dependency(mysqli $conn, int $businessId, int $categoryId): bool
{
    $checks = [];

    foreach (['brands', 'products', 'stock_inward_items'] as $table) {
        if (table_exists($conn, $table) && table_has_column($conn, $table, 'category_id')) {
            $checks[] = $table;
        }
    }

    foreach ($checks as $table) {
        $safe = str_replace('`', '', $table);
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM `{$safe}` WHERE business_id = ? AND category_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $categoryId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

$permissions = category_api_permissions($conn);

try {
    if ($action === 'toggle_status') {
        if (!$permissions['can_edit']) {
            throw new RuntimeException('You do not have permission to change category status.');
        }

        if (!$hasStatus) {
            throw new RuntimeException('Category status column is missing.');
        }

        $categoryId = (int)($_POST['category_id'] ?? 0);

        $stmt = mysqli_prepare($conn, "UPDATE categories SET status = IF(status = 1, 0, 1) WHERE business_id = ? AND category_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $categoryId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Categories', 'toggle_status', $categoryId);
        flash('success', 'Category status updated successfully.');
        redirect('../categories.php');
    }

    if ($action === 'delete_category') {
        if (!$permissions['can_delete']) {
            throw new RuntimeException('You do not have permission to delete categories.');
        }

        $categoryId = (int)($_POST['category_id'] ?? 0);

        if ($categoryId <= 0) {
            throw new RuntimeException('Invalid category selected.');
        }

        if (category_has_dependency($conn, $businessId, $categoryId)) {
            throw new RuntimeException('This category is already used. Deactivate it instead of deleting.');
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE business_id = ? AND category_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $categoryId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Categories', 'delete', $categoryId);
        flash('success', 'Category deleted successfully.');
        redirect('../categories.php');
    }

    $categoryId = (int)($_POST['category_id'] ?? 0);
    $categoryName = trim($_POST['category_name'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($categoryName === '') {
        throw new RuntimeException('Category name is required.');
    }

    if ($action === 'create_category') {
        if (!$permissions['can_create']) {
            throw new RuntimeException('You do not have permission to create categories.');
        }

        if (category_name_exists($conn, $businessId, $categoryName)) {
            throw new RuntimeException('Category name already exists.');
        }

        $columns = ['business_id', 'category_name'];
        $placeholders = ['?', '?'];
        $types = 'is';
        $values = [$businessId, $categoryName];

        if ($hasStatus) {
            $columns[] = 'status';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $status;
        }

        $sql = "INSERT INTO categories (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Categories', 'create', $newId, null, ['category_name' => $categoryName]);
        flash('success', 'Category created successfully.');
        redirect('../categories.php');
    }

    if ($action === 'update_category') {
        if (!$permissions['can_edit']) {
            throw new RuntimeException('You do not have permission to edit categories.');
        }

        if ($categoryId <= 0) {
            throw new RuntimeException('Invalid category selected.');
        }

        if (category_name_exists($conn, $businessId, $categoryName, $categoryId)) {
            throw new RuntimeException('Category name already exists.');
        }

        $sets = ['category_name = ?'];
        $types = 's';
        $values = [$categoryName];

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
        $values[] = $categoryId;

        $sql = "UPDATE categories SET " . implode(', ', $sets) . " WHERE business_id = ? AND category_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Categories', 'update', $categoryId, null, ['category_name' => $categoryName]);
        flash('success', 'Category updated successfully.');
        redirect('../categories.php');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('../categories.php');
}
?>
