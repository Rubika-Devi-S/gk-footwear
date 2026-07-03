<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../brands.php');
}

verify_csrf();

$businessId = current_business_id();
$action = $_POST['action'] ?? '';

$hasCategoryId = table_has_column($conn, 'brands', 'category_id');
$hasStatus = table_has_column($conn, 'brands', 'status');
$hasUpdatedAt = table_has_column($conn, 'brands', 'updated_at');

function brand_api_permissions(mysqli $conn): array
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
          AND sm.menu_url = 'brands.php'
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

function brand_name_exists(mysqli $conn, int $businessId, string $brandName, ?int $excludeBrandId = null): bool
{
    if ($excludeBrandId) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM brands WHERE business_id = ? AND brand_name = ? AND brand_id <> ?");
        mysqli_stmt_bind_param($stmt, 'isi', $businessId, $brandName, $excludeBrandId);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM brands WHERE business_id = ? AND brand_name = ?");
        mysqli_stmt_bind_param($stmt, 'is', $businessId, $brandName);
    }

    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function brand_has_dependency(mysqli $conn, int $businessId, int $brandId): bool
{
    foreach (['products', 'stock_inward_items'] as $table) {
        if (table_exists($conn, $table) && table_has_column($conn, $table, 'brand_id')) {
            $safe = str_replace('`', '', $table);
            $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM `{$safe}` WHERE business_id = ? AND brand_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $businessId, $brandId);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ((int)($row['total'] ?? 0) > 0) {
                return true;
            }
        }
    }

    return false;
}

$permissions = brand_api_permissions($conn);

try {
    if ($action === 'toggle_status') {
        if (!$permissions['can_edit']) {
            throw new RuntimeException('You do not have permission to change brand status.');
        }

        if (!$hasStatus) {
            throw new RuntimeException('Brand status column is missing.');
        }

        $brandId = (int)($_POST['brand_id'] ?? 0);

        $stmt = mysqli_prepare($conn, "UPDATE brands SET status = IF(status = 1, 0, 1) WHERE business_id = ? AND brand_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $brandId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Brands', 'toggle_status', $brandId);
        flash('success', 'Brand status updated successfully.');
        redirect('../brands.php');
    }

    if ($action === 'delete_brand') {
        if (!$permissions['can_delete']) {
            throw new RuntimeException('You do not have permission to delete brands.');
        }

        $brandId = (int)($_POST['brand_id'] ?? 0);

        if ($brandId <= 0) {
            throw new RuntimeException('Invalid brand selected.');
        }

        if (brand_has_dependency($conn, $businessId, $brandId)) {
            throw new RuntimeException('This brand is already used. Deactivate it instead of deleting.');
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM brands WHERE business_id = ? AND brand_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $brandId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Brands', 'delete', $brandId);
        flash('success', 'Brand deleted successfully.');
        redirect('../brands.php');
    }

    $brandId = (int)($_POST['brand_id'] ?? 0);
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $brandName = trim($_POST['brand_name'] ?? '');
    $status = (int)($_POST['status'] ?? 1);

    if ($brandName === '') {
        throw new RuntimeException('Brand name is required.');
    }

    if ($categoryId && table_exists($conn, 'categories')) {
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM categories WHERE business_id = ? AND category_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $categoryId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)($row['total'] ?? 0) === 0) {
            throw new RuntimeException('Invalid category selected.');
        }
    }

    if ($action === 'create_brand') {
        if (!$permissions['can_create']) {
            throw new RuntimeException('You do not have permission to create brands.');
        }

        if (brand_name_exists($conn, $businessId, $brandName)) {
            throw new RuntimeException('Brand name already exists.');
        }

        $columns = ['business_id', 'brand_name'];
        $placeholders = ['?', '?'];
        $types = 'is';
        $values = [$businessId, $brandName];

        if ($hasCategoryId) {
            $columns[] = 'category_id';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $categoryId;
        }

        if ($hasStatus) {
            $columns[] = 'status';
            $placeholders[] = '?';
            $types .= 'i';
            $values[] = $status;
        }

        $sql = "INSERT INTO brands (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Brands', 'create', $newId, null, ['brand_name' => $brandName]);
        flash('success', 'Brand created successfully.');
        redirect('../brands.php');
    }

    if ($action === 'update_brand') {
        if (!$permissions['can_edit']) {
            throw new RuntimeException('You do not have permission to edit brands.');
        }

        if ($brandId <= 0) {
            throw new RuntimeException('Invalid brand selected.');
        }

        if (brand_name_exists($conn, $businessId, $brandName, $brandId)) {
            throw new RuntimeException('Brand name already exists.');
        }

        $sets = ['brand_name = ?'];
        $types = 's';
        $values = [$brandName];

        if ($hasCategoryId) {
            $sets[] = 'category_id = ?';
            $types .= 'i';
            $values[] = $categoryId;
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
        $values[] = $brandId;

        $sql = "UPDATE brands SET " . implode(', ', $sets) . " WHERE business_id = ? AND brand_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Brands', 'update', $brandId, null, ['brand_name' => $brandName]);
        flash('success', 'Brand updated successfully.');
        redirect('../brands.php');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('../brands.php');
}
?>
