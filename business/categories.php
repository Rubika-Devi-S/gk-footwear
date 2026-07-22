<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'categories.php');

$pageTitle = 'Categories';
$businessId = current_business_id();

function master_page_permissions(mysqli $conn, string $pageUrl): array
{
    if (is_business_admin($conn)) {
        return [
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_delete' => true,
            'can_print' => true,
            'can_export' => true,
        ];
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
          AND sm.menu_url = ?
          AND sm.is_active = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'iis', $roleId, $businessId, $pageUrl);
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

$permissions = master_page_permissions($conn, 'categories.php');

$hasStatus = table_has_column($conn, 'categories', 'status');
$hasCreatedAt = table_has_column($conn, 'categories', 'created_at');

$statusSelect = $hasStatus ? 'status' : '1 AS status';
$createdSelect = $hasCreatedAt ? 'created_at' : 'NULL AS created_at';

$totalCategories = 0;
$activeCategories = 0;
$inactiveCategories = 0;
$totalBrands = 0;

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM categories WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$totalCategories = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
mysqli_stmt_close($stmt);

if ($hasStatus) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM categories WHERE business_id = ? AND status = 1");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $activeCategories = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
    mysqli_stmt_close($stmt);
    $inactiveCategories = max(0, $totalCategories - $activeCategories);
} else {
    $activeCategories = $totalCategories;
}

if (table_exists($conn, 'brands')) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM brands WHERE business_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $totalBrands = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
    mysqli_stmt_close($stmt);
}

$brandsByCategory = [];
$brandCounts = [];

if (table_exists($conn, 'brand_category_map')) {
    $stmt = mysqli_prepare($conn, "
        SELECT
            bcm.category_id,
            b.brand_id,
            b.brand_name,
            b.status
        FROM brand_category_map bcm
        INNER JOIN brands b
            ON b.brand_id = bcm.brand_id
           AND b.business_id = bcm.business_id
        WHERE bcm.business_id = ?
        ORDER BY b.brand_name ASC
    ");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($rs)) {
        $categoryId = (int)$row['category_id'];

        if (!isset($brandsByCategory[$categoryId])) {
            $brandsByCategory[$categoryId] = [];
        }

        $brandsByCategory[$categoryId][] = [
            'brand_id' => (int)$row['brand_id'],
            'brand_name' => (string)$row['brand_name'],
            'status' => (int)$row['status'],
        ];
    }

    mysqli_stmt_close($stmt);

    foreach ($brandsByCategory as $categoryId => $mappedBrands) {
        $brandCounts[(int)$categoryId] = count($mappedBrands);
    }
}

$categories = [];
$stmt = mysqli_prepare($conn, "
    SELECT category_id, business_id, category_name, {$statusSelect}, {$createdSelect}
    FROM categories
    WHERE business_id = ?
    ORDER BY category_id DESC
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    $categories[] = $row;
}

mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html lang="en" class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>

    <style>
    .master-page {
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
        font-size: 12px;
        font-weight: 500;
    }

    .mp-hero {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 14px 16px;
    }

    .mp-hero h1 {
        font-size: 20px;
        font-weight: 800;
        margin: 0 0 3px;
        letter-spacing: -.02em;
        color: var(--text-main);
    }

    .mp-hero p {
        font-size: 11px;
        line-height: 1.35;
        margin: 0;
        color: var(--text-muted);
        font-weight: 500;
    }

    .mp-hero .btn {
        font-size: 11px;
        padding: 7px 11px;
        min-height: 32px;
        border-radius: 999px;
        font-weight: 700;
    }

    .mp-stat-card {
        min-height: 72px;
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 15px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 11px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .mp-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 13px;
        display: grid;
        place-items: center;
        color: #fff;
        flex: 0 0 auto;
    }

    .mp-stat-icon svg {
        width: 17px;
        height: 17px;
    }

    .mp-stat-label {
        font-size: 10.5px;
        color: var(--text-muted);
        font-weight: 700;
        line-height: 1.15;
    }

    .mp-stat-value {
        font-size: 18px;
        color: var(--text-main);
        font-weight: 800;
        margin: 1px 0;
        line-height: 1.05;
    }

    .mp-stat-sub {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 550;
        line-height: 1.15;
    }

    .mp-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .mp-card-head {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-soft);
    }

    .mp-card-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text-main);
        margin: 0 0 2px;
    }

    .mp-card-sub {
        font-size: 11px;
        color: var(--text-muted);
        margin: 0;
    }

    .mp-table th {
        font-size: 10px;
        font-weight: 750;
        padding: 9px 10px;
        white-space: nowrap;
    }

    .mp-table td {
        font-size: 11px;
        padding: 9px 10px;
        vertical-align: middle;
    }

    .mp-avatar {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        flex: 0 0 auto;
    }

    .mp-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.2;
    }

    .mp-sub {
        font-size: 10px;
        color: var(--text-muted);
        line-height: 1.25;
    }

    .mp-badge {
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 10px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .status-active {
        background: #dcfce7;
        color: #15803d;
    }

    .status-inactive {
        background: #fee2e2;
        color: #b91c1c;
    }

    .badge-code {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .badge-count {
        background: #fef3c7;
        color: #b45309;
    }

    .badge-type {
        background: #ede9fe;
        color: #6d28d9;
    }

    .brand-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        max-width: 440px;
    }

    .brand-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 4px 7px;
        font-size: 9.5px;
        font-weight: 750;
        background: #e0f2fe;
        color: #075985;
        border: 1px solid #bae6fd;
        line-height: 1;
    }

    .brand-chip.inactive {
        background: #f1f5f9;
        color: #64748b;
        border-color: #cbd5e1;
    }

    .brand-empty {
        font-size: 10px;
        color: var(--text-muted);
        font-style: italic;
    }

    .brand-count-wrap {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .brand-view-modal-list {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    .brand-view-modal-item {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        border-radius: 999px;
        padding: 7px 10px;
        font-size: 11px;
        font-weight: 750;
        background: #e0f2fe;
        color: #075985;
        border: 1px solid #bae6fd;
    }

    .brand-view-modal-item.inactive {
        background: #f1f5f9;
        color: #64748b;
        border-color: #cbd5e1;
    }

    .mp-action-btn {
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        padding: 5px 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        line-height: 1;
    }

    .mp-mobile-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 10px;
    }

    .modal-title {
        font-size: 15px;
        font-weight: 750;
    }

    .modal .form-label {
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .modal .form-control,
    .modal .form-select {
        min-height: 34px;
        font-size: 12px;
        border-radius: 12px;
        padding: 6px 10px;
    }

    .modal-footer .btn {
        font-size: 12px;
        padding: 7px 12px;
        border-radius: 12px;
        font-weight: 700;
    }

    @media (max-width: 767px) {
        .mp-hero {
            padding: 12px;
        }

        .mp-hero h1 {
            font-size: 19px;
        }

        .mp-stat-card {
            min-height: 64px;
            padding: 9px 10px;
        }

        .mp-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 11px;
        }

        .mp-stat-value {
            font-size: 16px;
        }
    }
    </style>
</head>

<body>
    <div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50"
        style="z-index:1035;"></div>
    <?php include __DIR__ . '/includes/page-message.php'; ?>

    <div class="min-vh-100 d-flex">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main id="main">
            <?php include __DIR__ . '/includes/nav.php'; ?>

            <section class="page-section master-page p-3 p-lg-3">
                <div class="mp-hero mb-3">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                        <div>
                            <h1>Categories</h1>
                            <p>Manage footwear categories like Men, Women, Kids, Accessories and Socks.</p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="brands.php" class="btn btn-outline-primary">
                                <i data-lucide="badge" style="width:14px;height:14px;"></i>
                                Brands
                            </a>

                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="mp-stat-card">
                            <div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i
                                    data-lucide="tags"></i></div>
                            <div>
                                <div class="mp-stat-label">Total Categories</div>
                                <div class="mp-stat-value"><?= (int)$totalCategories ?></div>
                                <div class="mp-stat-sub">Footwear groups</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="mp-stat-card">
                            <div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i
                                    data-lucide="badge-check"></i></div>
                            <div>
                                <div class="mp-stat-label">Active</div>
                                <div class="mp-stat-value"><?= (int)$activeCategories ?></div>
                                <div class="mp-stat-sub">Visible categories</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="mp-stat-card">
                            <div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c;"><i
                                    data-lucide="ban"></i></div>
                            <div>
                                <div class="mp-stat-label">Inactive</div>
                                <div class="mp-stat-value"><?= (int)$inactiveCategories ?></div>
                                <div class="mp-stat-sub">Disabled categories</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="mp-stat-card">
                            <div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i
                                    data-lucide="badge"></i></div>
                            <div>
                                <div class="mp-stat-label">Brands</div>
                                <div class="mp-stat-value"><?= (int)$totalBrands ?></div>
                                <div class="mp-stat-sub">Linked brands</div>
                            </div>
                        </article>
                    </div>
                </div>

                <section class="mp-card">
                    <div class="mp-card-head">
                        <div class="d-flex flex-column flex-md-row justify-content-md-between gap-2">
                            <div>
                                <h2 class="mp-card-title">Category List</h2>
                                <p class="mp-card-sub">Role based actions are controlled from Roles permission modal.
                                </p>
                            </div>

                            <?php if ($permissions['can_create']): ?>
                            <button type="button" class="btn brand-gradient btn-sm rounded-pill fw-bold"
                                data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCategoryModal()">
                                <i data-lucide="plus" style="width:13px;height:13px;"></i>
                                Add Category
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-none d-md-block table-responsive px-3 pb-3">
                        <table class="table mp-table mb-0">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Brands</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <?php if ($permissions['can_edit'] || $permissions['can_delete']): ?>
                                    <th style="width: 230px;">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$categories): ?>
                                <tr>
                                    <td colspan="<?= ($permissions['can_edit'] || $permissions['can_delete']) ? 5 : 4 ?>"
                                        class="text-center text-muted py-4">No categories found.</td>
                                </tr>
                                <?php endif; ?>

                                <?php foreach ($categories as $category): ?>
                                <?php
                                $categoryId = (int)$category['category_id'];
                                $initial = strtoupper(substr(trim((string)$category['category_name']), 0, 1)) ?: 'C';
                                $brandCount = $brandCounts[$categoryId] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="mp-avatar"><?= e($initial) ?></div>
                                            <div>
                                                <div class="mp-title"><?= e($category['category_name']) ?></div>
                                                <div class="mp-sub">ID: <?= (int)$categoryId ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php $mappedBrands = $brandsByCategory[$categoryId] ?? []; ?>
                                        <span class="mp-badge badge-count">
                                            <?= count($mappedBrands) ?> brand<?= count($mappedBrands) === 1 ? '' : 's' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ((int)$category['status'] === 1): ?>
                                        <span class="mp-badge status-active">Active</span>
                                        <?php else: ?>
                                        <span class="mp-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($category['created_at']) ? e(date('d-m-Y', strtotime($category['created_at']))) : '-' ?>
                                    </td>

                                    <?php if ($permissions['can_edit'] || $permissions['can_delete']): ?>
                                    <td>
                                        <?php $mappedBrands = $brandsByCategory[$categoryId] ?? []; ?>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-info mp-action-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewBrandsModal"
                                                onclick='viewCategoryBrands(
                                                    <?= json_encode($category['category_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                    <?= json_encode($mappedBrands, JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                                                )'>
                                            View
                                        </button>

                                        <?php if ($permissions['can_edit']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#categoryModal"
                                            onclick='editCategory(<?= json_encode($category, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                        <form method="post" action="api/categories-api.php" class="d-inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning mp-action-btn"><?= (int)$category['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($permissions['can_delete']): ?>
                                        <form method="post" action="api/categories-api.php" class="d-inline"
                                            onsubmit="return confirm('Delete this category?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger mp-action-btn mt-1">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-md-none px-3 pb-3 d-grid gap-3">
                        <?php if (!$categories): ?>
                        <div class="mp-mobile-card text-center text-muted">No categories found.</div>
                        <?php endif; ?>

                        <?php foreach ($categories as $category): ?>
                        <?php
                            $categoryId = (int)$category['category_id'];
                            $initial = strtoupper(substr(trim((string)$category['category_name']), 0, 1)) ?: 'C';
                            $brandCount = $brandCounts[$categoryId] ?? 0;
                            ?>
                        <div class="mp-mobile-card">
                            <div class="d-flex gap-2">
                                <div class="mp-avatar"><?= e($initial) ?></div>

                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <div class="mp-title"><?= e($category['category_name']) ?></div>
                                            <div class="mp-sub">ID: <?= (int)$categoryId ?></div>
                                        </div>

                                        <?php if ((int)$category['status'] === 1): ?>
                                        <span class="mp-badge status-active">Active</span>
                                        <?php else: ?>
                                        <span class="mp-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <?php $mappedBrands = $brandsByCategory[$categoryId] ?? []; ?>
                                        <span class="mp-badge badge-count">
                                            <?= count($mappedBrands) ?> brand<?= count($mappedBrands) === 1 ? '' : 's' ?>
                                        </span>
                                    </div>

                                    <?php if ($permissions['can_edit'] || $permissions['can_delete']): ?>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <?php $mappedBrands = $brandsByCategory[$categoryId] ?? []; ?>

                                        <button type="button"
                                                class="btn btn-sm btn-outline-info mp-action-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewBrandsModal"
                                                onclick='viewCategoryBrands(
                                                    <?= json_encode($category['category_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                    <?= json_encode($mappedBrands, JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                                                )'>
                                            View
                                        </button>

                                        <?php if ($permissions['can_edit']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#categoryModal"
                                            onclick='editCategory(<?= json_encode($category, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                        <form method="post" action="api/categories-api.php">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning mp-action-btn"><?= (int)$category['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($permissions['can_delete']): ?>
                                        <form method="post" action="api/categories-api.php"
                                            onsubmit="return confirm('Delete this category?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= (int)$categoryId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger mp-action-btn">Delete</button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <?php include __DIR__ . '/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <div class="modal fade" id="viewBrandsModal" tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Mapped Brands</h5>
                        <div class="mp-sub" id="viewBrandsCategoryName"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="viewBrandsList" class="brand-view-modal-list"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="categoryModal" tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <form method="post" action="api/categories-api.php" class="modal-content">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" id="categoryAction" value="create_category">
                <input type="hidden" name="category_id" id="categoryId">

                <div class="modal-header">
                    <h5 class="modal-title">Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Category Name *</label>
                            <input type="text" name="category_name" id="categoryName" class="form-control" required>
                        </div>

                        <?php if ($hasStatus): ?>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" id="categoryStatus" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn brand-gradient">Save Category</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/script.php'; ?>

    <script>
    function escapeBrandText(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function viewCategoryBrands(categoryName, brands) {
        document.getElementById('viewBrandsCategoryName').textContent =
            categoryName ? 'Category: ' + categoryName : '';

        const list = document.getElementById('viewBrandsList');
        const mappedBrands = Array.isArray(brands) ? brands : [];

        if (!mappedBrands.length) {
            list.innerHTML = '<div class="brand-empty">No brands mapped to this category.</div>';
            return;
        }

        list.innerHTML = mappedBrands.map(function(brand) {
            const inactiveClass = Number(brand.status) === 1 ? '' : ' inactive';
            const statusText = Number(brand.status) === 1 ? '' : ' (Inactive)';

            return '<span class="brand-view-modal-item' + inactiveClass + '">' +
                escapeBrandText(brand.brand_name || '-') +
                escapeBrandText(statusText) +
                '</span>';
        }).join('');
    }

    function openCategoryModal() {
        document.getElementById("categoryAction").value = "create_category";
        document.getElementById("categoryId").value = "";
        document.getElementById("categoryName").value = "";
        if (document.getElementById("categoryStatus")) document.getElementById("categoryStatus").value = "1";
    }

    function editCategory(category) {
        document.getElementById("categoryAction").value = "update_category";
        document.getElementById("categoryId").value = category.category_id || "";
        document.getElementById("categoryName").value = category.category_name || "";
        if (document.getElementById("categoryStatus")) document.getElementById("categoryStatus").value = category
            .status || "1";
    }
    </script>
</body>

</html>