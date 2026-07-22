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
$hasStatus = table_has_column($conn, 'brands', 'status');
$hasUpdatedAt = table_has_column($conn, 'brands', 'updated_at');

function brand_api_permissions(mysqli $conn): array
{
    if (is_business_admin($conn)) {
        return [
            'can_view' => true, 'can_create' => true, 'can_edit' => true,
            'can_delete' => true, 'can_print' => true, 'can_export' => true
        ];
    }

    $businessId = current_business_id();
    $roleId = current_role_id();
    $cols = ['can_view'];

    foreach (['can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'] as $col) {
        $cols[] = table_has_column($conn, 'business_role_sidebar_access', $col)
            ? $col
            : '0 AS ' . $col;
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
        return [
            'can_view' => false, 'can_create' => false, 'can_edit' => false,
            'can_delete' => false, 'can_print' => false, 'can_export' => false
        ];
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
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM brands
            WHERE business_id = ? AND brand_name = ? AND brand_id <> ?
        ");
        mysqli_stmt_bind_param($stmt, 'isi', $businessId, $brandName, $excludeBrandId);
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM brands
            WHERE business_id = ? AND brand_name = ?
        ");
        mysqli_stmt_bind_param($stmt, 'is', $businessId, $brandName);
    }

    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function valid_brand_category_ids(mysqli $conn, int $businessId, array $categoryIds): array
{
    $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds), function ($id) {
        return $id > 0;
    })));

    if (!$categoryIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $types = 'i' . str_repeat('i', count($categoryIds));
    $values = array_merge([$businessId], $categoryIds);

    $stmt = mysqli_prepare($conn, "
        SELECT category_id
        FROM categories
        WHERE business_id = ?
          AND category_id IN ({$placeholders})
    ");
    mysqli_stmt_bind_param($stmt, $types, ...$values);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $valid = [];

    while ($row = mysqli_fetch_assoc($rs)) {
        $valid[] = (int)$row['category_id'];
    }

    mysqli_stmt_close($stmt);
    sort($valid);
    return $valid;
}

function sync_brand_categories(mysqli $conn, int $businessId, int $brandId, array $categoryIds): void
{
    $stmt = mysqli_prepare($conn, "
        DELETE FROM brand_category_map
        WHERE business_id = ? AND brand_id = ?
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $businessId, $brandId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$categoryIds) {
        return;
    }

    $stmt = mysqli_prepare($conn, "
        INSERT INTO brand_category_map
        (business_id, brand_id, category_id)
        VALUES (?, ?, ?)
    ");

    foreach ($categoryIds as $categoryId) {
        mysqli_stmt_bind_param($stmt, 'iii', $businessId, $brandId, $categoryId);
        mysqli_stmt_execute($stmt);
    }

    mysqli_stmt_close($stmt);
}

function brand_has_dependency(mysqli $conn, int $businessId, int $brandId): bool
{
    foreach (['products', 'stock_inward_items'] as $table) {
        if (!table_exists($conn, $table) || !table_has_column($conn, $table, 'brand_id')) {
            continue;
        }

        $safeTable = str_replace('`', '', $table);
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM `{$safeTable}`
            WHERE business_id = ? AND brand_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $brandId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

$permissions = brand_api_permissions($conn);

try {
    if (!table_exists($conn, 'brand_category_map')) {
        throw new RuntimeException('brand_category_map table is missing. Run the included SQL patch first.');
    }

    if ($action === 'toggle_status') {
        if (!$permissions['can_edit']) {
            throw new RuntimeException('You do not have permission to change brand status.');
        }

        $brandId = (int)($_POST['brand_id'] ?? 0);
        if ($brandId <= 0) {
            throw new RuntimeException('Invalid brand selected.');
        }

        $stmt = mysqli_prepare($conn, "
            UPDATE brands
            SET status = IF(status = 1, 0, 1)
            WHERE business_id = ? AND brand_id = ?
        ");
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
            throw new RuntimeException('This brand is already used in products or stock. Deactivate it instead.');
        }

        mysqli_begin_transaction($conn);

        $stmt = mysqli_prepare($conn, "
            DELETE FROM brand_category_map
            WHERE business_id = ? AND brand_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $brandId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "
            DELETE FROM brands
            WHERE business_id = ? AND brand_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $brandId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);

        log_activity($conn, 'Brands', 'delete', $brandId);
        flash('success', 'Brand deleted successfully.');
        redirect('../brands.php');
    }

    $brandId = (int)($_POST['brand_id'] ?? 0);
    $brandName = trim($_POST['brand_name'] ?? '');
    $status = (int)($_POST['status'] ?? 1);
    $categoryIds = valid_brand_category_ids(
        $conn,
        $businessId,
        is_array($_POST['category_ids'] ?? null) ? $_POST['category_ids'] : []
    );

    if ($brandName === '') {
        throw new RuntimeException('Brand name is required.');
    }

    if (!$categoryIds) {
        throw new RuntimeException('Select at least one category for the brand.');
    }

    if ($action === 'create_brand') {
        if (!$permissions['can_create']) {
            throw new RuntimeException('You do not have permission to create brands.');
        }

        if (brand_name_exists($conn, $businessId, $brandName)) {
            throw new RuntimeException('Brand name already exists.');
        }

        mysqli_begin_transaction($conn);

        if ($hasStatus) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO brands (business_id, brand_name, status)
                VALUES (?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, 'isi', $businessId, $brandName, $status);
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO brands (business_id, brand_name)
                VALUES (?, ?)
            ");
            mysqli_stmt_bind_param($stmt, 'is', $businessId, $brandName);
        }

        mysqli_stmt_execute($stmt);
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        sync_brand_categories($conn, $businessId, $newId, $categoryIds);
        mysqli_commit($conn);

        log_activity($conn, 'Brands', 'create', $newId, null, [
            'brand_name' => $brandName,
            'category_ids' => $categoryIds,
        ]);

        flash('success', 'Brand created and categories mapped successfully.');
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

        mysqli_begin_transaction($conn);

        $sets = ['brand_name = ?'];
        $types = 's';
        $values = [$brandName];

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

        $stmt = mysqli_prepare($conn, "
            UPDATE brands
            SET " . implode(', ', $sets) . "
            WHERE business_id = ? AND brand_id = ?
        ");
        mysqli_stmt_bind_param($stmt, $types, ...$values);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        sync_brand_categories($conn, $businessId, $brandId, $categoryIds);
        mysqli_commit($conn);

        log_activity($conn, 'Brands', 'update', $brandId, null, [
            'brand_name' => $brandName,
            'category_ids' => $categoryIds,
        ]);

        flash('success', 'Brand and category mappings updated successfully.');
        redirect('../brands.php');
    }

    throw new RuntimeException('Invalid action.');
} catch (Throwable $e) {
    if (isset($conn) && mysqli_errno($conn) !== 0) {
        @mysqli_rollback($conn);
    }
    flash('error', $e->getMessage());
    redirect('../brands.php');
}
?>