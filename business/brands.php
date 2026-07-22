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

function brand_page_permissions(mysqli $conn): array
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

$permissions = brand_page_permissions($conn);
$hasStatus = table_has_column($conn, 'brands', 'status');
$hasCreatedAt = table_has_column($conn, 'brands', 'created_at');

$categories = [];
$stmt = mysqli_prepare($conn, "
    SELECT category_id, category_name, status
    FROM categories
    WHERE business_id = ?
    ORDER BY category_name
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($rs)) {
    $categories[] = $row;
}
mysqli_stmt_close($stmt);

$brandMappings = [];
if (table_exists($conn, 'brand_category_map')) {
    $stmt = mysqli_prepare($conn, "
        SELECT bcm.brand_id, c.category_id, c.category_name
        FROM brand_category_map bcm
        INNER JOIN categories c
            ON c.category_id = bcm.category_id
           AND c.business_id = bcm.business_id
        WHERE bcm.business_id = ?
        ORDER BY c.category_name
    ");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($rs)) {
        $brandId = (int)$row['brand_id'];
        if (!isset($brandMappings[$brandId])) {
            $brandMappings[$brandId] = [];
        }
        $brandMappings[$brandId][] = [
            'category_id' => (int)$row['category_id'],
            'category_name' => (string)$row['category_name'],
        ];
    }

    mysqli_stmt_close($stmt);
}

$statusSelect = $hasStatus ? 'status' : '1 AS status';
$createdSelect = $hasCreatedAt ? 'created_at' : 'NULL AS created_at';

$brands = [];
$stmt = mysqli_prepare($conn, "
    SELECT brand_id, business_id, brand_name, {$statusSelect}, {$createdSelect}
    FROM brands
    WHERE business_id = ?
    ORDER BY brand_id DESC
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    $brandId = (int)$row['brand_id'];
    $row['categories'] = $brandMappings[$brandId] ?? [];
    $row['category_ids'] = array_map(function ($category) {
        return (int)$category['category_id'];
    }, $row['categories']);
    $brands[] = $row;
}
mysqli_stmt_close($stmt);

$totalBrands = count($brands);
$activeBrands = 0;
foreach ($brands as $brand) {
    if ((int)$brand['status'] === 1) {
        $activeBrands++;
    }
}
$inactiveBrands = max(0, $totalBrands - $activeBrands);
$totalMappings = 0;
foreach ($brandMappings as $mappedCategories) {
    $totalMappings += count($mappedCategories);
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>
    <style>
        .master-page{font-family:"Inter","Segoe UI",Arial,sans-serif;font-size:12px;font-weight:500}
        .mp-hero,.mp-card,.mp-stat-card{background:var(--card-bg);border:1px solid var(--border-soft);box-shadow:0 8px 20px rgba(15,23,42,.06)}
        .mp-hero{border-radius:16px;padding:14px 16px}
        .mp-hero h1{font-size:20px;font-weight:800;margin:0 0 3px;color:var(--text-main)}
        .mp-hero p,.mp-card-sub,.mp-sub{font-size:10.5px;color:var(--text-muted);margin:0}
        .mp-stat-card{min-height:72px;border-radius:15px;padding:11px 12px;display:flex;align-items:center;gap:10px}
        .mp-stat-icon{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;flex:0 0 auto}
        .mp-stat-value{font-size:18px;color:var(--text-main);font-weight:800;line-height:1.05}
        .mp-stat-label{font-size:10.5px;color:var(--text-muted);font-weight:700}
        .mp-card{border-radius:16px;overflow:hidden}
        .mp-card-head{padding:12px 14px;border-bottom:1px solid var(--border-soft)}
        .mp-card-title{font-size:15px;font-weight:800;color:var(--text-main);margin:0}
        .mp-table th{font-size:10px;font-weight:750;padding:9px 10px;white-space:nowrap}
        .mp-table td{font-size:11px;padding:9px 10px;vertical-align:middle}
        .mp-avatar{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,var(--brand-1),var(--brand-2));color:#fff;font-weight:800}
        .mp-title{font-size:12px;font-weight:800;color:var(--text-main)}
        .mp-badge,.category-chip{border-radius:999px;padding:5px 8px;font-size:10px;font-weight:700;display:inline-flex;align-items:center}
        .status-active{background:#dcfce7;color:#15803d}.status-inactive{background:#fee2e2;color:#b91c1c}
        .category-chip{background:#ede9fe;color:#6d28d9;border:1px solid #ddd6fe;margin:2px}
        .category-empty{font-size:10px;color:var(--text-muted);font-style:italic}
        .category-count-wrap{display:flex;align-items:center;gap:7px;flex-wrap:wrap}
        .category-count-badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:800;background:#fef3c7;color:#b45309}
        .category-view-btn{border-radius:999px;font-size:10px;font-weight:800;padding:4px 8px}
        .view-category-list{display:flex;flex-wrap:wrap;gap:7px}
        .view-category-item{display:inline-flex;align-items:center;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:750;background:#ede9fe;color:#6d28d9;border:1px solid #ddd6fe}
        .mp-action-btn{border-radius:999px;font-size:10.5px;font-weight:700;padding:5px 8px}
        .category-check-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;max-height:260px;overflow:auto;padding:2px}
        .category-check{border:1px solid var(--border-soft);border-radius:12px;padding:9px;background:rgba(148,163,184,.05)}
        .category-check label{cursor:pointer;width:100%;margin:0;font-size:11px;font-weight:700}
        @media(max-width:575px){.category-check-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>
        <section class="page-section master-page p-3">
            <div class="mp-hero mb-3">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h1>Brands</h1>
                        <p>Create brands and map each brand to one or more footwear categories.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="categories.php" class="btn btn-outline-primary btn-sm rounded-pill fw-bold">Categories</a>
                        <?php if ($permissions['can_create']): ?>
                            <button class="btn brand-gradient btn-sm rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#brandModal" onclick="openBrandModal()">Add Brand</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3"><div class="mp-stat-card"><div class="mp-stat-icon" style="background:#dbeafe;color:#1d4ed8"><i data-lucide="badge"></i></div><div><div class="mp-stat-label">Total Brands</div><div class="mp-stat-value"><?= $totalBrands ?></div></div></div></div>
                <div class="col-6 col-xl-3"><div class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d"><i data-lucide="badge-check"></i></div><div><div class="mp-stat-label">Active</div><div class="mp-stat-value"><?= $activeBrands ?></div></div></div></div>
                <div class="col-6 col-xl-3"><div class="mp-stat-card"><div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c"><i data-lucide="ban"></i></div><div><div class="mp-stat-label">Inactive</div><div class="mp-stat-value"><?= $inactiveBrands ?></div></div></div></div>
                <div class="col-6 col-xl-3"><div class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309"><i data-lucide="link"></i></div><div><div class="mp-stat-label">Category Mappings</div><div class="mp-stat-value"><?= $totalMappings ?></div></div></div></div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <h2 class="mp-card-title">Brand List</h2>
                    <p class="mp-card-sub">Category mappings update automatically whenever a brand is saved.</p>
                </div>
                <div class="table-responsive p-3">
                    <table class="table mp-table mb-0">
                        <thead><tr><th>Brand</th><th>Mapped Categories</th><th>Status</th><th>Created</th><?php if ($permissions['can_edit'] || $permissions['can_delete']): ?><th>Action</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php if (!$brands): ?>
                            <tr><td colspan="<?= ($permissions['can_edit'] || $permissions['can_delete']) ? 5 : 4 ?>" class="text-center text-muted py-4">No brands found.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($brands as $brand): ?>
                            <?php $initial = strtoupper(substr(trim((string)$brand['brand_name']), 0, 1)) ?: 'B'; ?>
                            <tr>
                                <td><div class="d-flex align-items-center gap-2"><div class="mp-avatar"><?= e($initial) ?></div><div><div class="mp-title"><?= e($brand['brand_name']) ?></div><div class="mp-sub">ID: <?= (int)$brand['brand_id'] ?></div></div></div></td>
                                <td>
                                    <?php $mappedCategoryCount = count($brand['categories']); ?>
                                    <span class="category-count-badge">
                                        <?= $mappedCategoryCount ?> categor<?= $mappedCategoryCount === 1 ? 'y' : 'ies' ?>
                                    </span>
                                </td>
                                <td><span class="mp-badge <?= (int)$brand['status'] === 1 ? 'status-active' : 'status-inactive' ?>"><?= (int)$brand['status'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                                <td><?= !empty($brand['created_at']) ? e(date('d-m-Y', strtotime($brand['created_at']))) : '-' ?></td>
                                <?php if ($permissions['can_edit'] || $permissions['can_delete']): ?>
                                    <td>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-info mp-action-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewCategoriesModal"
                                                onclick='viewBrandCategories(
                                                    <?= json_encode($brand['brand_name'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                                                    <?= json_encode($brand['categories'], JSON_HEX_APOS | JSON_HEX_QUOT) ?>
                                                )'>
                                            View
                                        </button>

                                        <?php if ($permissions['can_edit']): ?>
                                            <button class="btn btn-sm btn-outline-primary mp-action-btn" data-bs-toggle="modal" data-bs-target="#brandModal" onclick='editBrand(<?= json_encode($brand, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                            <form method="post" action="api/brands-api.php" class="d-inline">
                                                <?= csrf_field() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="brand_id" value="<?= (int)$brand['brand_id'] ?>">
                                                <button class="btn btn-sm btn-outline-warning mp-action-btn"><?= (int)$brand['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($permissions['can_delete']): ?>
                                            <form method="post" action="api/brands-api.php" class="d-inline" onsubmit="return confirm('Delete this brand?');">
                                                <?= csrf_field() ?><input type="hidden" name="action" value="delete_brand"><input type="hidden" name="brand_id" value="<?= (int)$brand['brand_id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger mp-action-btn">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<div class="modal fade" id="viewCategoriesModal" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Mapped Categories</h5>
                    <div class="mp-sub" id="viewCategoriesBrandName"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="viewCategoriesList" class="view-category-list"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="brandModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="post" action="api/brands-api.php" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="brandAction" value="create_brand">
            <input type="hidden" name="brand_id" id="brandId">
            <div class="modal-header"><h5 class="modal-title">Brand</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Brand Name *</label><input type="text" name="brand_name" id="brandName" class="form-control" required></div>
                    <?php if ($hasStatus): ?><div class="col-md-4"><label class="form-label">Status</label><select name="status" id="brandStatus" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div><?php endif; ?>
                    <div class="col-12">
                        <label class="form-label fw-bold">Categories *</label>
                        <div class="category-check-grid">
                            <?php foreach ($categories as $category): ?>
                                <div class="category-check">
                                    <label class="d-flex align-items-center gap-2">
                                        <input type="checkbox" class="form-check-input brand-category-checkbox" name="category_ids[]" value="<?= (int)$category['category_id'] ?>">
                                        <span><?= e($category['category_name']) ?></span>
                                        <?php if ((int)$category['status'] !== 1): ?><small class="text-danger">(Inactive)</small><?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Select one or more categories for this brand.</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn brand-gradient">Save Brand</button></div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>
<script>
function escapeBrandCategoryText(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (character) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[character];
    });
}

function viewBrandCategories(brandName, categories) {
    document.getElementById('viewCategoriesBrandName').textContent =
        brandName ? 'Brand: ' + brandName : '';

    const list = document.getElementById('viewCategoriesList');
    const mappedCategories = Array.isArray(categories) ? categories : [];

    if (!mappedCategories.length) {
        list.innerHTML = '<div class="category-empty">No categories mapped.</div>';
        return;
    }

    list.innerHTML = mappedCategories.map(function (category) {
        return '<span class="view-category-item">' +
            escapeBrandCategoryText(category.category_name || '-') +
            '</span>';
    }).join('');
}

function resetBrandCategories() {
    document.querySelectorAll('.brand-category-checkbox').forEach(function (checkbox) {
        checkbox.checked = false;
    });
}
function openBrandModal() {
    document.getElementById('brandAction').value = 'create_brand';
    document.getElementById('brandId').value = '';
    document.getElementById('brandName').value = '';
    if (document.getElementById('brandStatus')) document.getElementById('brandStatus').value = '1';
    resetBrandCategories();
}
function editBrand(brand) {
    document.getElementById('brandAction').value = 'update_brand';
    document.getElementById('brandId').value = brand.brand_id || '';
    document.getElementById('brandName').value = brand.brand_name || '';
    if (document.getElementById('brandStatus')) document.getElementById('brandStatus').value = String(brand.status ?? 1);
    resetBrandCategories();
    (brand.category_ids || []).forEach(function (categoryId) {
        var checkbox = document.querySelector('.brand-category-checkbox[value="' + categoryId + '"]');
        if (checkbox) checkbox.checked = true;
    });
}
</script>
</body>
</html>