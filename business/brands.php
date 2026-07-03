<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'brands.php');

$pageTitle = 'Brands';
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

$permissions = master_page_permissions($conn, 'brands.php');

$hasCategoryId = table_has_column($conn, 'brands', 'category_id');
$hasStatus = table_has_column($conn, 'brands', 'status');
$hasCreatedAt = table_has_column($conn, 'brands', 'created_at');

$statusSelect = $hasStatus ? 'b.status' : '1 AS status';
$categoryIdSelect = $hasCategoryId ? 'b.category_id' : 'NULL AS category_id';
$createdSelect = $hasCreatedAt ? 'b.created_at' : 'NULL AS created_at';

$categories = [];
$stmt = mysqli_prepare($conn, "
    SELECT category_id, category_name
    FROM categories
    WHERE business_id = ?
    ORDER BY category_name ASC
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    $categories[] = $row;
}

mysqli_stmt_close($stmt);

$totalBrands = 0;
$activeBrands = 0;
$inactiveBrands = 0;
$totalCategories = count($categories);

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM brands WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$totalBrands = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
mysqli_stmt_close($stmt);

if ($hasStatus) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM brands WHERE business_id = ? AND status = 1");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $activeBrands = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
    mysqli_stmt_close($stmt);
    $inactiveBrands = max(0, $totalBrands - $activeBrands);
} else {
    $activeBrands = $totalBrands;
}

$brands = [];
$categoryJoin = $hasCategoryId ? "LEFT JOIN categories c ON c.category_id = b.category_id AND c.business_id = b.business_id" : "";
$categoryNameSelect = $hasCategoryId ? "c.category_name" : "NULL AS category_name";

$stmt = mysqli_prepare($conn, "
    SELECT
        b.brand_id,
        b.business_id,
        b.brand_name,
        {$categoryIdSelect},
        {$categoryNameSelect},
        {$statusSelect},
        {$createdSelect}
    FROM brands b
    {$categoryJoin}
    WHERE b.business_id = ?
    ORDER BY b.brand_id DESC
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    $brands[] = $row;
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
                            <h1>Brands</h1>
                            <p>Manage footwear brands and map them under categories.</p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="categories.php" class="btn btn-outline-primary">
                                <i data-lucide="tags" style="width:14px;height:14px;"></i>
                                Categories
                            </a>

                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="mp-stat-card">
                            <div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i
                                    data-lucide="badge"></i></div>
                            <div>
                                <div class="mp-stat-label">Total Brands</div>
                                <div class="mp-stat-value"><?= (int)$totalBrands ?></div>
                                <div class="mp-stat-sub">Brand master</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="mp-stat-card">
                            <div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i
                                    data-lucide="badge-check"></i></div>
                            <div>
                                <div class="mp-stat-label">Active</div>
                                <div class="mp-stat-value"><?= (int)$activeBrands ?></div>
                                <div class="mp-stat-sub">Visible brands</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="mp-stat-card">
                            <div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c;"><i
                                    data-lucide="ban"></i></div>
                            <div>
                                <div class="mp-stat-label">Inactive</div>
                                <div class="mp-stat-value"><?= (int)$inactiveBrands ?></div>
                                <div class="mp-stat-sub">Disabled brands</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="mp-stat-card">
                            <div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i
                                    data-lucide="tags"></i></div>
                            <div>
                                <div class="mp-stat-label">Categories</div>
                                <div class="mp-stat-value"><?= (int)$totalCategories ?></div>
                                <div class="mp-stat-sub">Linked groups</div>
                            </div>
                        </article>
                    </div>
                </div>

                <section class="mp-card">
                    <div class="mp-card-head">
                        <div class="d-flex flex-column flex-md-row justify-content-md-between gap-2">
                            <div>
                                <h2 class="mp-card-title">Brand List</h2>
                                <p class="mp-card-sub">Role based actions are controlled from Roles permission modal.
                                </p>
                            </div>

                            <?php if ($permissions['can_create']): ?>
                            <button type="button" class="btn brand-gradient btn-sm rounded-pill fw-bold"
                                data-bs-toggle="modal" data-bs-target="#brandModal" onclick="openBrandModal()">
                                <i data-lucide="plus" style="width:13px;height:13px;"></i>
                                Add Brand
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-none d-md-block table-responsive px-3 pb-3">
                        <table class="table mp-table mb-0">
                            <thead>
                                <tr>
                                    <th>Brand</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <?php if ($permissions['can_edit'] || $permissions['can_delete']): ?>
                                    <th style="width: 230px;">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$brands): ?>
                                <tr>
                                    <td colspan="<?= ($permissions['can_edit'] || $permissions['can_delete']) ? 5 : 4 ?>"
                                        class="text-center text-muted py-4">No brands found.</td>
                                </tr>
                                <?php endif; ?>

                                <?php foreach ($brands as $brand): ?>
                                <?php
                                $brandId = (int)$brand['brand_id'];
                                $initial = strtoupper(substr(trim((string)$brand['brand_name']), 0, 1)) ?: 'B';
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="mp-avatar"><?= e($initial) ?></div>
                                            <div>
                                                <div class="mp-title"><?= e($brand['brand_name']) ?></div>
                                                <div class="mp-sub">ID: <?= (int)$brandId ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span
                                            class="mp-badge badge-type"><?= e($brand['category_name'] ?: 'No Category') ?></span>
                                    </td>
                                    <td>
                                        <?php if ((int)$brand['status'] === 1): ?>
                                        <span class="mp-badge status-active">Active</span>
                                        <?php else: ?>
                                        <span class="mp-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($brand['created_at']) ? e(date('d-m-Y', strtotime($brand['created_at']))) : '-' ?>
                                    </td>

                                    <?php if ($permissions['can_edit'] || $permissions['can_delete']): ?>
                                    <td>
                                        <?php if ($permissions['can_edit']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#brandModal"
                                            onclick='editBrand(<?= json_encode($brand, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                        <form method="post" action="api/brands-api.php" class="d-inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="brand_id" value="<?= (int)$brandId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning mp-action-btn"><?= (int)$brand['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($permissions['can_delete']): ?>
                                        <form method="post" action="api/brands-api.php" class="d-inline"
                                            onsubmit="return confirm('Delete this brand?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_brand">
                                            <input type="hidden" name="brand_id" value="<?= (int)$brandId ?>">
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
                        <?php if (!$brands): ?>
                        <div class="mp-mobile-card text-center text-muted">No brands found.</div>
                        <?php endif; ?>

                        <?php foreach ($brands as $brand): ?>
                        <?php
                            $brandId = (int)$brand['brand_id'];
                            $initial = strtoupper(substr(trim((string)$brand['brand_name']), 0, 1)) ?: 'B';
                            ?>
                        <div class="mp-mobile-card">
                            <div class="d-flex gap-2">
                                <div class="mp-avatar"><?= e($initial) ?></div>

                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <div class="mp-title"><?= e($brand['brand_name']) ?></div>
                                            <div class="mp-sub"><?= e($brand['category_name'] ?: 'No Category') ?></div>
                                        </div>

                                        <?php if ((int)$brand['status'] === 1): ?>
                                        <span class="mp-badge status-active">Active</span>
                                        <?php else: ?>
                                        <span class="mp-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($permissions['can_edit'] || $permissions['can_delete']): ?>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <?php if ($permissions['can_edit']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#brandModal"
                                            onclick='editBrand(<?= json_encode($brand, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                        <form method="post" action="api/brands-api.php">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="brand_id" value="<?= (int)$brandId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning mp-action-btn"><?= (int)$brand['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($permissions['can_delete']): ?>
                                        <form method="post" action="api/brands-api.php"
                                            onsubmit="return confirm('Delete this brand?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_brand">
                                            <input type="hidden" name="brand_id" value="<?= (int)$brandId ?>">
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

    <div class="modal fade" id="brandModal" tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <form method="post" action="api/brands-api.php" class="modal-content">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" id="brandAction" value="create_brand">
                <input type="hidden" name="brand_id" id="brandId">

                <div class="modal-header">
                    <h5 class="modal-title">Brand</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <?php if ($hasCategoryId): ?>
                        <div class="col-12">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="categoryId" class="form-select">
                                <option value="">No Category</option>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['category_id'] ?>">
                                    <?= e($category['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-12">
                            <label class="form-label">Brand Name *</label>
                            <input type="text" name="brand_name" id="brandName" class="form-control" required>
                        </div>

                        <?php if ($hasStatus): ?>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" id="brandStatus" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn brand-gradient">Save Brand</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/script.php'; ?>

    <script>
    function openBrandModal() {
        document.getElementById("brandAction").value = "create_brand";
        document.getElementById("brandId").value = "";
        document.getElementById("brandName").value = "";
        if (document.getElementById("categoryId")) document.getElementById("categoryId").value = "";
        if (document.getElementById("brandStatus")) document.getElementById("brandStatus").value = "1";
    }

    function editBrand(brand) {
        document.getElementById("brandAction").value = "update_brand";
        document.getElementById("brandId").value = brand.brand_id || "";
        document.getElementById("brandName").value = brand.brand_name || "";
        if (document.getElementById("categoryId")) document.getElementById("categoryId").value = brand.category_id ||
            "";
        if (document.getElementById("brandStatus")) document.getElementById("brandStatus").value = brand.status || "1";
    }
    </script>
</body>

</html>