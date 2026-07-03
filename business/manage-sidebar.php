<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'manage-sidebar.php');

if (!is_business_admin($conn)) {
    http_response_code(403);
    die('Only Admin can manage sidebar.');
}

$pageTitle = 'Sidebar Control';
$businessId = current_business_id();

$hasShowInSidebar = table_has_column($conn, 'business_sidebar_menus', 'show_in_sidebar');
$filter = $_GET['filter'] ?? 'all';

$parents = [];
$allMenus = [];

$stmt = mysqli_prepare($conn, "
    SELECT *
    FROM business_sidebar_menus
    WHERE business_id = ?
    ORDER BY
        CASE WHEN parent_id IS NULL THEN sort_order ELSE 999999 END,
        COALESCE(parent_id, id),
        parent_id IS NOT NULL,
        sort_order,
        id
");
mysqli_stmt_bind_param($stmt, "i", $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    if (!$hasShowInSidebar) {
        $row['show_in_sidebar'] = 1;
    }

    $allMenus[] = $row;

    if (empty($row['parent_id'])) {
        $parents[] = $row;
    }
}

mysqli_stmt_close($stmt);

$menus = [];
foreach ($allMenus as $row) {
    if ($filter === 'all') {
        $menus[] = $row;
        continue;
    }

    if ((string)$row['menu_slug'] === (string)$filter) {
        $menus[] = $row;
        continue;
    }

    if (!empty($row['parent_id'])) {
        foreach ($parents as $parent) {
            if ((int)$parent['id'] === (int)$row['parent_id'] && (string)$parent['menu_slug'] === (string)$filter) {
                $menus[] = $row;
                break;
            }
        }
    }
}

$totalMenus = count($allMenus);
$mainCount = 0;
$subCount = 0;
$activeCount = 0;

foreach ($allMenus as $menu) {
    empty($menu['parent_id']) ? $mainCount++ : $subCount++;

    if ((int)$menu['is_active'] === 1) {
        $activeCount++;
    }
}

function sidebar_parent_name(array $parents, ?int $parentId): string
{
    if (!$parentId) {
        return '';
    }

    foreach ($parents as $parent) {
        if ((int)$parent['id'] === (int)$parentId) {
            return (string)$parent['menu_title'];
        }
    }

    return '';
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
    .sidebar-control-hero {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 22px;
        box-shadow: var(--shadow-card);
        padding: 20px;
    }

    .sidebar-control-hero h1 {
        font-size: 28px;
        font-weight: 950;
        margin: 0 0 6px;
        letter-spacing: -.03em;
    }

    .sidebar-stat-icon {
        width: 72px;
        height: 72px;
        border-radius: 26px;
        display: grid;
        place-items: center;
        color: #fff;
        flex: 0 0 auto;
    }

    .sidebar-filter-pill {
        border: 1px solid var(--border-soft);
        background: var(--card-bg);
        color: var(--text-main);
        border-radius: 999px;
        padding: 11px 17px;
        font-size: 13px;
        font-weight: 900;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: .18s ease;
    }

    .sidebar-filter-pill:hover {
        transform: translateY(-1px);
        color: var(--text-main);
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
    }

    .sidebar-filter-pill.active {
        border-color: transparent;
        background: linear-gradient(135deg, #5a24fb, #2563eb);
        color: #fff;
    }

    .sidebar-menu-icon {
        width: 44px;
        height: 44px;
        border-radius: 16px;
        background: rgba(148, 163, 184, .13);
        display: grid;
        place-items: center;
        color: var(--text-main);
        flex: 0 0 auto;
    }

    .sidebar-url-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid var(--border-soft);
        background: rgba(148, 163, 184, .08);
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 12px;
        color: var(--text-muted);
        font-weight: 900;
    }

    .sidebar-sort-input {
        width: 130px;
        max-width: 100%;
        text-align: center;
        border-radius: 18px;
        font-weight: 900;
        min-height: 46px;
    }

    .badge-soft {
        border-radius: 999px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 950;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }

    .badge-main {
        background: #dcfce7;
        color: #008a5b;
    }

    .badge-sub {
        background: #ede9fe;
        color: #6d28d9;
    }

    .badge-shown {
        background: #d1fae5;
        color: #008a5b;
    }

    .badge-hidden {
        background: #fee2e2;
        color: #b91c1c;
    }

    .badge-active {
        background: #dcfce7;
        color: #008a5b;
    }

    .badge-inactive {
        background: #f1f5f9;
        color: #64748b;
    }

    .sidebar-action-btn {
        border-radius: 999px;
        font-weight: 900;
        padding: 8px 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        line-height: 1;
    }

    .sidebar-mobile-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 20px;
        padding: 15px;
        box-shadow: var(--shadow-card);
    }

    .sidebar-table th {
        white-space: nowrap;
    }

    .sidebar-table td {
        vertical-align: middle;
    }

    @media (max-width: 767px) {
        .sidebar-control-hero {
            padding: 16px;
            border-radius: 18px;
        }

        .sidebar-control-hero h1 {
            font-size: 24px;
        }

        .sidebar-stat-icon {
            width: 58px;
            height: 58px;
            border-radius: 20px;
        }
    }

    /* ============================================================
       ULTRA COMPACT VIEW - Manage Sidebar
       Added to reduce card height, font size, spacing and table size
       ============================================================ */

    .page-section {
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
        font-size: 12px;
        font-weight: 500;
    }

    .sidebar-control-hero {
        padding: 12px 14px !important;
        border-radius: 16px !important;
        margin-bottom: 10px !important;
    }

    .sidebar-control-hero h1 {
        font-size: 20px !important;
        font-weight: 750 !important;
        line-height: 1.1 !important;
        margin-bottom: 3px !important;
        letter-spacing: -.02em !important;
    }

    .sidebar-control-hero p,
    .card-ui p,
    .text-muted-custom {
        font-size: 11px !important;
        line-height: 1.3 !important;
        font-weight: 500 !important;
    }

    .sidebar-control-hero .btn,
    .card-ui .btn {
        font-size: 11px !important;
        padding: 6px 10px !important;
        min-height: 30px !important;
        border-radius: 999px !important;
    }

    .kpi-card {
        min-height: 70px !important;
        padding: 10px 12px !important;
        gap: 10px !important;
        border-radius: 14px !important;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06) !important;
    }

    .sidebar-stat-icon {
        width: 38px !important;
        height: 38px !important;
        border-radius: 13px !important;
    }

    .sidebar-stat-icon svg {
        width: 17px !important;
        height: 17px !important;
    }

    .kpi-label {
        font-size: 10px !important;
        font-weight: 650 !important;
        line-height: 1.15 !important;
    }

    .kpi-value {
        font-size: 17px !important;
        font-weight: 750 !important;
        margin: 1px 0 !important;
        line-height: 1.05 !important;
    }

    .kpi-sub {
        font-size: 10px !important;
        font-weight: 550 !important;
        margin: 0 !important;
        line-height: 1.15 !important;
    }

    .card-ui {
        border-radius: 16px !important;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06) !important;
    }

    .card-ui>.p-3,
    .card-ui>.p-lg-4 {
        padding: 12px 14px !important;
    }

    .card-ui h2 {
        font-size: 15px !important;
        font-weight: 750 !important;
        margin-bottom: 3px !important;
    }

    .sidebar-filter-pill {
        padding: 6px 10px !important;
        font-size: 11px !important;
        font-weight: 650 !important;
        gap: 5px !important;
    }

    .sidebar-filter-pill svg {
        width: 13px !important;
        height: 13px !important;
    }

    .sidebar-table th {
        font-size: 10px !important;
        font-weight: 700 !important;
        padding: 8px 8px !important;
    }

    .sidebar-table td {
        font-size: 11px !important;
        padding: 8px 8px !important;
    }

    .sidebar-table .fw-bold,
    .sidebar-mobile-card .fw-bold {
        font-size: 12px !important;
        font-weight: 700 !important;
    }

    .sidebar-table small,
    .sidebar-mobile-card small {
        font-size: 10px !important;
    }

    .sidebar-menu-icon {
        width: 30px !important;
        height: 30px !important;
        border-radius: 10px !important;
    }

    .sidebar-menu-icon svg {
        width: 15px !important;
        height: 15px !important;
    }

    .sidebar-url-badge {
        padding: 5px 8px !important;
        font-size: 10px !important;
        font-weight: 600 !important;
        gap: 4px !important;
    }

    .badge-soft {
        padding: 5px 8px !important;
        font-size: 10px !important;
        font-weight: 650 !important;
    }

    .sidebar-sort-input {
        width: 76px !important;
        min-height: 30px !important;
        border-radius: 11px !important;
        font-size: 11px !important;
        font-weight: 650 !important;
        padding: 4px 6px !important;
    }

    .sidebar-action-btn {
        font-size: 10.5px !important;
        font-weight: 650 !important;
        padding: 5px 8px !important;
        gap: 4px !important;
        margin-top: 3px !important;
    }

    .sidebar-action-btn svg {
        width: 12px !important;
        height: 12px !important;
    }

    .sidebar-mobile-card {
        padding: 10px !important;
        border-radius: 14px !important;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06) !important;
    }

    .sidebar-mobile-card .mt-3 {
        margin-top: 8px !important;
    }

    .modal-title {
        font-size: 15px !important;
        font-weight: 700 !important;
    }

    .modal-body {
        font-size: 12px !important;
    }

    .modal .form-label {
        font-size: 11px !important;
        font-weight: 650 !important;
        margin-bottom: 4px !important;
    }

    .modal .form-control,
    .modal .form-select {
        min-height: 34px !important;
        font-size: 12px !important;
        border-radius: 12px !important;
        padding: 6px 10px !important;
    }

    .modal-footer .btn {
        font-size: 12px !important;
        padding: 7px 12px !important;
    }

    @media (max-width: 767px) {
        .sidebar-control-hero {
            padding: 11px 12px !important;
        }

        .sidebar-control-hero h1 {
            font-size: 19px !important;
        }

        .kpi-card {
            min-height: 64px !important;
            padding: 9px 10px !important;
        }

        .sidebar-stat-icon {
            width: 34px !important;
            height: 34px !important;
            border-radius: 11px !important;
        }

        .kpi-value {
            font-size: 16px !important;
        }
    }

    /* Button color changes */
    .sidebar-control-hero .btn-outline-primary,
    .card-ui .btn-outline-primary {
        color: #0F766E !important;
        border-color: #0F766E !important;
        background: #ffffff !important;
    }

    .sidebar-control-hero .btn-outline-primary:hover,
    .card-ui .btn-outline-primary:hover {
        color: #ffffff !important;
        background: #0F766E !important;
        border-color: #0F766E !important;
    }

    .sidebar-control-hero .btn-warning {
        color: #ffffff !important;
        background: #2563EB !important;
        border-color: #2563EB !important;
    }

    .sidebar-control-hero .btn-warning:hover {
        background: #1D4ED8 !important;
        border-color: #1D4ED8 !important;
    }

    .sidebar-filter-pill.active {
        border-color: transparent;
        background: linear-gradient(135deg, #244ffb, #0b88f5);
        color: #fff;
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
            <section class="page-section p-3 p-lg-3">

                <div class="sidebar-control-hero mb-3">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                        <div>
                            <h1>Sidebar Control</h1>
                            <p class="text-muted-custom mb-0">
                                Create main menus and assign submenus under the correct parent. Page files are created
                                automatically from the given URL.
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-primary rounded-pill fw-bold px-3"
                                data-bs-toggle="modal" data-bs-target="#bulkSortModal">
                                <i data-lucide="arrow-up-down" style="width:16px;height:16px;"></i>
                                Arrange Sort Order
                            </button>

                            <button type="button" class="btn btn-warning text-white rounded-pill fw-bold px-3"
                                data-bs-toggle="modal" data-bs-target="#menuModal" onclick="openMenuModal()">
                                <i data-lucide="plus" style="width:16px;height:16px;"></i>
                                Add Menu
                            </button>
                        </div>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="sidebar-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);">
                                <i data-lucide="panel-left"></i>
                            </div>
                            <div>
                                <div class="kpi-label">Total Menus <i data-lucide="info"
                                        style="width:12px;height:12px;"></i></div>
                                <p class="kpi-value"><?= (int)$totalMenus ?></p>
                                <p class="kpi-sub text-success">↑ <?= (int)$totalMenus ?> sidebar items</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="sidebar-stat-icon" style="background:#cce8dc;color:#008a5b;">
                                <i data-lucide="list-tree"></i>
                            </div>
                            <div>
                                <div class="kpi-label">Main Menus <i data-lucide="info"
                                        style="width:12px;height:12px;"></i></div>
                                <p class="kpi-value"><?= (int)$mainCount ?></p>
                                <p class="kpi-sub text-success">↑ <?= (int)$mainCount ?> parent menus</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="sidebar-stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);">
                                <i data-lucide="list-plus"></i>
                            </div>
                            <div>
                                <div class="kpi-label">Sub Menus <i data-lucide="info"
                                        style="width:12px;height:12px;"></i></div>
                                <p class="kpi-value"><?= (int)$subCount ?></p>
                                <p class="kpi-sub"><?= (int)$subCount ?> child menus</p>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="kpi-card">
                            <div class="sidebar-stat-icon" style="background:#fef3c7;color:#f59e0b;">
                                <i data-lucide="badge-check"></i>
                            </div>
                            <div>
                                <div class="kpi-label">Active <i data-lucide="info" style="width:12px;height:12px;"></i>
                                </div>
                                <p class="kpi-value"><?= (int)$activeCount ?></p>
                                <p class="kpi-sub text-success">↑ <?= (int)$activeCount ?> active items</p>
                            </div>
                        </article>
                    </div>
                </div>

                <section class="card-ui overflow-hidden">
                    <div class="p-3 p-lg-4">
                        <div
                            class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-start gap-3 mb-3">
                            <div>
                                <h2 class="fw-bold fs-5 mb-1">Manage Sidebar Menus</h2>
                                <p class="text-muted-custom mb-0">
                                    Click a main menu below to show only that menu and its assigned submenus. Use
                                    Sidebar Hide/Show separately from Active/Inactive status.
                                </p>
                            </div>

                            <button type="button" class="btn btn-outline-primary rounded-pill fw-bold btn-sm px-3"
                                data-bs-toggle="modal" data-bs-target="#bulkSortModal">
                                <i data-lucide="list-ordered" style="width:15px;height:15px;"></i>
                                Bulk Sort
                            </button>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mb-4">
                            <a href="manage-sidebar.php?filter=all"
                                class="sidebar-filter-pill <?= $filter === 'all' ? 'active' : '' ?>">
                                <i data-lucide="list"></i> All Menus
                            </a>

                            <?php foreach ($parents as $parent): ?>
                            <a href="manage-sidebar.php?filter=<?= e($parent['menu_slug']) ?>"
                                class="sidebar-filter-pill <?= $filter === $parent['menu_slug'] ? 'active' : '' ?>">
                                <i data-lucide="<?= e($parent['icon']) ?>"></i> <?= e($parent['menu_title']) ?>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="d-none d-md-block table-responsive px-3 px-lg-4 pb-3">
                        <table class="table sidebar-table">
                            <thead>
                                <tr>
                                    <th>Menu</th>
                                    <th>Type</th>
                                    <th>URL / Page</th>
                                    <th>Icon</th>
                                    <th>Sort</th>
                                    <th>Sidebar</th>
                                    <th>Status</th>
                                    <th style="width: 250px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$menus): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No menus found.</td>
                                </tr>
                                <?php endif; ?>

                                <?php foreach ($menus as $menu): ?>
                                <?php
                            $isSub = !empty($menu['parent_id']);
                            $parentName = sidebar_parent_name($parents, (int)($menu['parent_id'] ?? 0));
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3 <?= $isSub ? 'ps-4' : '' ?>">
                                            <div class="sidebar-menu-icon">
                                                <i data-lucide="<?= e($menu['icon']) ?>"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?= e($menu['menu_title']) ?></div>
                                                <small class="text-muted-custom">
                                                    <?= e($menu['menu_slug']) ?><?= $parentName ? ' · ' . e($parentName) : '' ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-soft <?= $isSub ? 'badge-sub' : 'badge-main' ?>">
                                            <?= $isSub ? 'Sub' : 'Main' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="sidebar-url-badge">
                                            <i data-lucide="file-code" style="width:13px;height:13px;"></i>
                                            <?= e($menu['menu_url']) ?>
                                        </span>
                                    </td>
                                    <td><?= e($menu['icon']) ?></td>
                                    <td>
                                        <form method="post" action="api/sidebar-menu-api.php">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="sort_one">
                                            <input type="hidden" name="menu_id" value="<?= (int)$menu['id'] ?>">
                                            <input type="number" name="sort_order"
                                                value="<?= (int)$menu['sort_order'] ?>"
                                                class="form-control sidebar-sort-input" onchange="this.form.submit()">
                                        </form>
                                    </td>
                                    <td>
                                        <?php if ((int)$menu['show_in_sidebar'] === 1): ?>
                                        <span class="badge-soft badge-shown">Shown</span>
                                        <?php else: ?>
                                        <span class="badge-soft badge-hidden">Hidden</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$menu['is_active'] === 1): ?>
                                        <span class="badge-soft badge-active">Active</span>
                                        <?php else: ?>
                                        <span class="badge-soft badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary sidebar-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#menuModal"
                                            onclick='editMenu(<?= json_encode($menu, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            Edit
                                        </button>

                                        <form method="post" action="api/sidebar-menu-api.php" class="d-inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="menu_id" value="<?= (int)$menu['id'] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning sidebar-action-btn">
                                                <i data-lucide="power" style="width:14px;height:14px;"></i>
                                                <?= (int)$menu['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="api/sidebar-menu-api.php" class="d-inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_sidebar">
                                            <input type="hidden" name="menu_id" value="<?= (int)$menu['id'] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-secondary sidebar-action-btn mt-1">
                                                <i data-lucide="<?= (int)$menu['show_in_sidebar'] === 1 ? 'eye-off' : 'eye' ?>"
                                                    style="width:14px;height:14px;"></i>
                                                Sidebar <?= (int)$menu['show_in_sidebar'] === 1 ? 'Hide' : 'Show' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="api/sidebar-menu-api.php" class="d-inline"
                                            onsubmit="return confirm('Delete this menu?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="menu_id" value="<?= (int)$menu['id'] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger sidebar-action-btn mt-1">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-md-none px-3 pb-3 d-grid gap-3">
                        <?php if (!$menus): ?>
                        <div class="sidebar-mobile-card text-center text-muted">No menus found.</div>
                        <?php endif; ?>

                        <?php foreach ($menus as $menu): ?>
                        <?php $isSub = !empty($menu['parent_id']); ?>
                        <div class="sidebar-mobile-card">
                            <div class="d-flex gap-3">
                                <div class="sidebar-menu-icon">
                                    <i data-lucide="<?= e($menu['icon']) ?>"></i>
                                </div>

                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <div class="fw-bold"><?= e($menu['menu_title']) ?></div>
                                            <small class="text-muted-custom"><?= e($menu['menu_slug']) ?></small>
                                        </div>
                                        <span class="badge-soft <?= $isSub ? 'badge-sub' : 'badge-main' ?>">
                                            <?= $isSub ? 'Sub' : 'Main' ?>
                                        </span>
                                    </div>

                                    <div class="small text-muted-custom mt-2">
                                        <?= e($menu['menu_url']) ?> · <?= e($menu['icon']) ?> · Sort
                                        <?= (int)$menu['sort_order'] ?>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <span
                                            class="badge-soft <?= (int)$menu['show_in_sidebar'] === 1 ? 'badge-shown' : 'badge-hidden' ?>">
                                            <?= (int)$menu['show_in_sidebar'] === 1 ? 'Shown' : 'Hidden' ?>
                                        </span>
                                        <span
                                            class="badge-soft <?= (int)$menu['is_active'] === 1 ? 'badge-active' : 'badge-inactive' ?>">
                                            <?= (int)$menu['is_active'] === 1 ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-primary rounded-pill fw-bold"
                                            data-bs-toggle="modal" data-bs-target="#menuModal"
                                            onclick='editMenu(<?= json_encode($menu, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            Edit
                                        </button>

                                        <form method="post" action="api/sidebar-menu-api.php">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_active">
                                            <input type="hidden" name="menu_id" value="<?= (int)$menu['id'] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning rounded-pill fw-bold">
                                                <?= (int)$menu['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form method="post" action="api/sidebar-menu-api.php">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_sidebar">
                                            <input type="hidden" name="menu_id" value="<?= (int)$menu['id'] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-secondary rounded-pill fw-bold">
                                                Sidebar <?= (int)$menu['show_in_sidebar'] === 1 ? 'Hide' : 'Show' ?>
                                            </button>
                                        </form>
                                    </div>
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

    <div class="modal fade" id="menuModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="post" action="api/sidebar-menu-api.php" class="modal-content">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" id="menuAction" value="create">
                <input type="hidden" name="menu_id" id="menuId">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Sidebar Menu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Menu Title</label>
                            <input type="text" name="menu_title" id="menuTitle" class="form-control rounded-4" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Menu Slug</label>
                            <input type="text" name="menu_slug" id="menuSlug" class="form-control rounded-4" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Parent Menu</label>
                            <select name="parent_id" id="parentId" class="form-select rounded-4">
                                <option value="">Main Menu</option>
                                <?php foreach ($parents as $parent): ?>
                                <option value="<?= (int)$parent['id'] ?>"><?= e($parent['menu_title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Menu URL / Page</label>
                            <input type="text" name="menu_url" id="menuUrl" class="form-control rounded-4" value="#">
                            <small class="text-muted-custom">Example: stock-list.php. Page will be created automatically
                                if missing.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Lucide Icon</label>
                            <input type="text" name="icon" id="menuIcon" class="form-control rounded-4" value="circle">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-bold">Sort</label>
                            <input type="number" name="sort_order" id="sortOrder" class="form-control rounded-4"
                                value="0">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Status</label>
                            <select name="is_active" id="isActive" class="form-select rounded-4">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Sidebar</label>
                            <select name="show_in_sidebar" id="showInSidebar" class="form-select rounded-4">
                                <option value="1">Shown</option>
                                <option value="0">Hidden</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-4 fw-bold"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn brand-gradient rounded-4 fw-bold px-4">Save Menu</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="bulkSortModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="post" action="api/sidebar-menu-api.php" class="modal-content">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="bulk_sort">

                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Arrange Sort Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <?php foreach ($allMenus as $menu): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold"><?= e($menu['menu_title']) ?></label>
                            <input type="number" name="sort_order[<?= (int)$menu['id'] ?>]"
                                value="<?= (int)$menu['sort_order'] ?>" class="form-control rounded-4">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-4 fw-bold"
                        data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn brand-gradient rounded-4 fw-bold px-4">Save Sort Order</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/script.php'; ?>

    <script>
    function openMenuModal() {
        document.getElementById("menuAction").value = "create";
        document.getElementById("menuId").value = "";
        document.getElementById("menuTitle").value = "";
        document.getElementById("menuSlug").value = "";
        document.getElementById("parentId").value = "";
        document.getElementById("menuUrl").value = "#";
        document.getElementById("menuIcon").value = "circle";
        document.getElementById("sortOrder").value = "0";
        document.getElementById("isActive").value = "1";
        document.getElementById("showInSidebar").value = "1";
    }

    function editMenu(menu) {
        document.getElementById("menuAction").value = "update";
        document.getElementById("menuId").value = menu.id || "";
        document.getElementById("menuTitle").value = menu.menu_title || "";
        document.getElementById("menuSlug").value = menu.menu_slug || "";
        document.getElementById("parentId").value = menu.parent_id || "";
        document.getElementById("menuUrl").value = menu.menu_url || "#";
        document.getElementById("menuIcon").value = menu.icon || "circle";
        document.getElementById("sortOrder").value = menu.sort_order || "0";
        document.getElementById("isActive").value = menu.is_active || "1";
        document.getElementById("showInSidebar").value = menu.show_in_sidebar || "1";
    }
    </script>
</body>

</html>