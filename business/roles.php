<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'roles.php');

$pageTitle = 'Roles';
$businessId = current_business_id();
$isAdmin = is_business_admin($conn);

$hasRoleStatus = table_has_column($conn, 'roles', 'status');
$hasRoleCreatedAt = table_has_column($conn, 'roles', 'created_at');

$roleStatusSelect = $hasRoleStatus ? 'r.status' : '1 AS status';
$roleCreatedSelect = $hasRoleCreatedAt ? 'r.created_at' : 'NULL AS created_at';

function rp_role_type_label(string $type): string
{
    $labels = [
        'admin' => 'Admin',
        'sales' => 'Sales',
        'cashier' => 'Cashier',
        'stock_manager' => 'Stock Manager',
        'branch_manager' => 'Branch Manager',
        'custom' => 'Custom',
    ];

    return $labels[$type] ?? ucwords(str_replace('_', ' ', $type));
}

function rp_role_type_badge(string $type): string
{
    return match ($type) {
        'admin' => 'role-admin',
        'sales' => 'role-sales',
        'cashier' => 'role-cashier',
        'stock_manager' => 'role-stock',
        'branch_manager' => 'role-branch',
        'custom' => 'role-custom',
        default => 'role-default',
    };
}

function rp_count_scalar(mysqli $conn, string $sql, int $businessId): int
{
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row[0] ?? 0);
}

$totalRoles = rp_count_scalar($conn, "SELECT COUNT(*) FROM roles WHERE business_id = ?", $businessId);
$activeRoles = $hasRoleStatus
    ? rp_count_scalar($conn, "SELECT COUNT(*) FROM roles WHERE business_id = ? AND status = 1", $businessId)
    : $totalRoles;
$totalUsers = rp_count_scalar($conn, "SELECT COUNT(*) FROM users WHERE business_id = ?", $businessId);

$roles = [];
$stmt = mysqli_prepare($conn, "
    SELECT
        r.role_id,
        r.role_name,
        r.role_type,
        {$roleStatusSelect},
        {$roleCreatedSelect},
        COUNT(u.user_id) AS total_users
    FROM roles r
    LEFT JOIN users u
        ON u.role_id = r.role_id
       AND u.business_id = r.business_id
    WHERE r.business_id = ?
    GROUP BY r.role_id
    ORDER BY r.role_id ASC
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    $roles[] = $row;
}

mysqli_stmt_close($stmt);

$menus = [];
if (table_exists($conn, 'business_sidebar_menus')) {
    $stmt = mysqli_prepare($conn, "
        SELECT
            id,
            parent_id,
            menu_title,
            menu_slug,
            menu_url,
            icon,
            sort_order,
            is_active
        FROM business_sidebar_menus
        WHERE business_id = ?
          AND is_active = 1
        ORDER BY
            CASE WHEN parent_id IS NULL THEN sort_order ELSE 999999 END,
            COALESCE(parent_id, id),
            parent_id IS NOT NULL,
            sort_order,
            id
    ");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($rs)) {
        $menus[] = $row;
    }

    mysqli_stmt_close($stmt);
}

$rolePermissions = [];
$permissionColumns = ['can_view', 'can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'];

if (table_exists($conn, 'business_role_sidebar_access')) {
    $selectCols = ['business_id', 'role_id', 'menu_id'];

    foreach ($permissionColumns as $col) {
        $selectCols[] = table_has_column($conn, 'business_role_sidebar_access', $col)
            ? $col
            : ($col === 'can_view' ? 'can_view' : '0 AS ' . $col);
    }

    $stmt = mysqli_prepare($conn, "
        SELECT " . implode(', ', $selectCols) . "
        FROM business_role_sidebar_access
        WHERE business_id = ?
    ");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($rs)) {
        $roleId = (int)$row['role_id'];
        $menuId = (int)$row['menu_id'];

        if (!isset($rolePermissions[$roleId])) {
            $rolePermissions[$roleId] = [];
        }

        $rolePermissions[$roleId][$menuId] = [
            'can_view' => (int)($row['can_view'] ?? 0),
            'can_create' => (int)($row['can_create'] ?? 0),
            'can_edit' => (int)($row['can_edit'] ?? 0),
            'can_delete' => (int)($row['can_delete'] ?? 0),
            
        ];
    }

    mysqli_stmt_close($stmt);
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
    .roles-page {
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
        font-size: 12px;
        font-weight: 500;
    }

    .rp-hero {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 14px 16px;
    }

    .rp-hero h1 {
        font-size: 20px;
        font-weight: 800;
        margin: 0 0 3px;
        letter-spacing: -.02em;
        color: var(--text-main);
    }

    .rp-hero p {
        font-size: 11px;
        line-height: 1.35;
        margin: 0;
        color: var(--text-muted);
        font-weight: 500;
    }

    .rp-hero .btn {
        font-size: 11px;
        padding: 7px 11px;
        min-height: 32px;
        border-radius: 999px;
        font-weight: 700;
    }

    .rp-stat-card {
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

    .rp-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 13px;
        display: grid;
        place-items: center;
        color: #fff;
        flex: 0 0 auto;
    }

    .rp-stat-icon svg {
        width: 17px;
        height: 17px;
    }

    .rp-stat-label {
        font-size: 10.5px;
        color: var(--text-muted);
        font-weight: 700;
        line-height: 1.15;
    }

    .rp-stat-value {
        font-size: 18px;
        color: var(--text-main);
        font-weight: 800;
        margin: 1px 0;
        line-height: 1.05;
    }

    .rp-stat-sub {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 550;
        line-height: 1.15;
    }

    .rp-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .rp-card-head {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-soft);
    }

    .rp-card-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text-main);
        margin: 0 0 2px;
    }

    .rp-card-sub {
        font-size: 11px;
        color: var(--text-muted);
        margin: 0;
    }

    .role-card {
        background: rgba(148, 163, 184, .07);
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        padding: 11px;
        height: 100%;
    }

    .role-icon {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        color: #fff;
        flex: 0 0 auto;
        background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
    }

    .role-icon svg {
        width: 15px;
        height: 15px;
    }

    .role-name {
        font-size: 13px;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
    }

    .role-meta {
        font-size: 10px;
        color: var(--text-muted);
        margin: 2px 0 0;
        line-height: 1.25;
    }

    .rp-badge {
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 10px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .role-admin { background: #dbeafe; color: #1d4ed8; }
    .role-sales { background: #dcfce7; color: #15803d; }
    .role-cashier { background: #fef3c7; color: #b45309; }
    .role-stock { background: #ede9fe; color: #6d28d9; }
    .role-branch { background: #ccfbf1; color: #0f766e; }
    .role-custom, .role-default { background: #f1f5f9; color: #475569; }
    .status-active { background: #dcfce7; color: #15803d; }
    .status-inactive { background: #fee2e2; color: #b91c1c; }

    .rp-action-btn {
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        padding: 5px 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        line-height: 1;
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

    /* Permission modal reference format */
    .permission-modal .modal-dialog {
        max-width: 95vw;
    }

    .permission-modal .modal-content {
        border-radius: 22px;
        overflow: hidden;
        border: 0;
        box-shadow: 0 30px 80px rgba(15, 23, 42, .25);
    }

    .permission-modal .modal-header {
        padding: 22px 24px;
        border-bottom: 1px solid var(--border-soft);
        background: var(--card-bg);
    }

    .permission-modal .modal-title {
        font-size: 24px;
        font-weight: 850;
        color: var(--text-main);
        line-height: 1.15;
    }

    .permission-modal .permission-subtitle {
        font-size: 14px;
        color: var(--text-muted);
        margin-top: 6px;
        font-weight: 500;
    }

    .permission-modal .btn-close {
        transform: scale(1.25);
    }

    .permission-modal .modal-body {
        padding: 20px 24px;
        background: var(--card-bg);
    }

    .permission-modal .modal-footer {
        padding: 20px 24px;
        border-top: 1px solid var(--border-soft);
        background: var(--card-bg);
    }

    .permission-grid-wrap {
        border: 1px solid var(--text-main);
        border-radius: 20px;
        overflow: auto;
        max-height: 56vh;
        background: var(--card-bg);
    }

    .permission-top-actions {
        min-width: 980px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 16px 16px;
        border-bottom: 1px solid var(--text-main);
        background: rgba(148, 163, 184, .06);
    }

    .permission-top-actions label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 13px;
        font-weight: 800;
        color: var(--text-main);
        margin: 0;
        white-space: nowrap;
    }

    .permission-grid {
        min-width: 980px;
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .permission-grid th,
    .permission-grid td {
        border: 1px solid var(--text-main);
    }

    .permission-grid th {
        background: var(--card-bg);
        color: var(--text-main);
        font-size: 13px;
        font-weight: 850;
        text-transform: uppercase;
        padding: 14px 10px;
        text-align: center;
    }

    .permission-grid th:first-child {
        text-align: left;
    }

    .permission-grid td {
        background: rgba(148, 163, 184, .05);
        padding: 14px 10px;
        text-align: center;
        vertical-align: middle;
    }

    .permission-grid td:first-child {
        text-align: left;
        width: 42%;
    }

    .permission-menu-title {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        color: var(--text-main);
        font-size: 13px;
        font-weight: 850;
    }

    .permission-menu-title.child {
        padding-left: 20px;
    }

    .permission-menu-slug {
        display: block;
        margin-top: 6px;
        color: var(--text-muted);
        font-size: 12px;
        font-weight: 600;
    }

    .permission-checkbox {
        width: 20px;
        height: 20px;
        border-radius: 5px;
        cursor: pointer;
        accent-color: var(--brand-2);
    }

    .permission-modal .modal-footer .btn-light {
        border: 1px solid var(--text-muted);
        color: var(--text-muted);
        background: var(--card-bg);
        border-radius: 999px;
        padding: 11px 20px;
        font-weight: 750;
    }

    .permission-modal .modal-footer .brand-gradient {
        border-radius: 999px;
        padding: 11px 28px;
        font-weight: 800;
    }

    @media (max-width: 767px) {
        .rp-hero {
            padding: 12px;
        }

        .rp-hero h1 {
            font-size: 19px;
        }

        .rp-stat-card {
            min-height: 64px;
            padding: 9px 10px;
        }

        .rp-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 11px;
        }

        .rp-stat-value {
            font-size: 16px;
        }

        .permission-modal .modal-dialog {
            max-width: calc(100vw - 12px);
            margin: 6px;
        }

        .permission-modal .modal-title {
            font-size: 20px;
        }

        .permission-modal .permission-subtitle {
            font-size: 12px;
        }

        .permission-grid-wrap {
            max-height: 62vh;
        }
    }
    </style>
</head>

<body>
    <div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
    <?php include __DIR__ . '/includes/page-message.php'; ?>

    <div class="min-vh-100 d-flex">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main id="main">
            <?php include __DIR__ . '/includes/nav.php'; ?>

            <section class="page-section roles-page p-3 p-lg-3">
                <div class="rp-hero mb-3">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                        <div>
                            <h1>Roles</h1>
                            <p>Create roles and configure access permissions from the same page.</p>
                        </div>

                        <?php if ($isAdmin): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="users.php" class="btn btn-outline-primary">
                                <i data-lucide="users" style="width:14px;height:14px;"></i>
                                Users
                            </a>

                            <button type="button" class="btn brand-gradient" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="openRoleModal()">
                                <i data-lucide="shield-plus" style="width:14px;height:14px;"></i>
                                Add Role
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6 col-xl-4">
                        <article class="rp-stat-card">
                            <div class="rp-stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);"><i data-lucide="shield-check"></i></div>
                            <div><div class="rp-stat-label">Total Roles</div><div class="rp-stat-value"><?= (int)$totalRoles ?></div><div class="rp-stat-sub"><?= (int)$activeRoles ?> active roles</div></div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-4">
                        <article class="rp-stat-card">
                            <div class="rp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="badge-check"></i></div>
                            <div><div class="rp-stat-label">Active Roles</div><div class="rp-stat-value"><?= (int)$activeRoles ?></div><div class="rp-stat-sub">Permission enabled</div></div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-4">
                        <article class="rp-stat-card">
                            <div class="rp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="users"></i></div>
                            <div><div class="rp-stat-label">Assigned Users</div><div class="rp-stat-value"><?= (int)$totalUsers ?></div><div class="rp-stat-sub">Users with roles</div></div>
                        </article>
                    </div>
                </div>

                <section class="rp-card">
                    <div class="rp-card-head">
                        <div class="d-flex flex-column flex-md-row justify-content-md-between gap-2">
                            <div>
                                <h2 class="rp-card-title">Business Roles</h2>
                                <p class="rp-card-sub">Click Permissions to open the access matrix modal.</p>
                            </div>

                            <?php if ($isAdmin): ?>
                            <button type="button" class="btn brand-gradient btn-sm rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#roleModal" onclick="openRoleModal()">
                                <i data-lucide="shield-plus" style="width:13px;height:13px;"></i>
                                Add Role
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-3">
                        <div class="row g-3">
                            <?php if (!$roles): ?>
                                <div class="col-12"><div class="role-card text-center text-muted">No roles found.</div></div>
                            <?php endif; ?>

                            <?php foreach ($roles as $role): ?>
                                <div class="col-12 col-sm-6 col-xl-4">
                                    <div class="role-card">
                                        <div class="d-flex gap-2 align-items-start">
                                            <div class="role-icon"><i data-lucide="shield"></i></div>

                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-start justify-content-between gap-2">
                                                    <div>
                                                        <p class="role-name"><?= e($role['role_name']) ?></p>
                                                        <p class="role-meta"><?= e(rp_role_type_label((string)$role['role_type'])) ?> · <?= (int)$role['total_users'] ?> users</p>
                                                    </div>

                                                    <span class="rp-badge <?= e(rp_role_type_badge((string)$role['role_type'])) ?>"><?= e(rp_role_type_label((string)$role['role_type'])) ?></span>
                                                </div>

                                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                                    <?php if ((int)$role['status'] === 1): ?>
                                                        <span class="rp-badge status-active">Active</span>
                                                    <?php else: ?>
                                                        <span class="rp-badge status-inactive">Inactive</span>
                                                    <?php endif; ?>

                                                    <?php if ($isAdmin): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary rp-action-btn" data-bs-toggle="modal" data-bs-target="#roleModal" onclick='editRole(<?= json_encode($role, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                                        <button type="button" class="btn btn-sm btn-outline-secondary rp-action-btn" data-bs-toggle="modal" data-bs-target="#permissionModal" onclick='openPermissionModal(<?= json_encode($role, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                            Permissions
                                                        </button>

                                                        <form method="post" action="api/roles-api.php" class="d-inline">
                                                            <?= csrf_field(); ?>
                                                            <input type="hidden" name="action" value="toggle_role">
                                                            <input type="hidden" name="role_id" value="<?= (int)$role['role_id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning rp-action-btn"><?= (int)$role['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <?php include __DIR__ . '/includes/footer.php'; ?>
            </section>
        </main>
    </div>

    <div class="modal fade" id="roleModal" tabindex="-1">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <form method="post" action="api/roles-api.php" class="modal-content">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" id="roleAction" value="create_role">
                <input type="hidden" name="role_id" id="roleId">

                <div class="modal-header">
                    <h5 class="modal-title">Business Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Role Name *</label>
                            <input type="text" name="role_name" id="roleName" class="form-control" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Role Type *</label>
                            <select name="role_type" id="roleType" class="form-select" required>
                                <option value="admin">Admin</option>
                                <option value="sales">Sales</option>
                                <option value="cashier">Cashier</option>
                                <option value="stock_manager">Stock Manager</option>
                                <option value="branch_manager">Branch Manager</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>

                        <?php if ($hasRoleStatus): ?>
                        <div class="col-12">
                            <label class="form-label">Status</label>
                            <select name="status" id="roleStatus" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn brand-gradient">Save Role</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade permission-modal" id="permissionModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form method="post" action="api/roles-api.php" class="modal-content">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="save_permissions">
                <input type="hidden" name="role_id" id="permissionRoleId">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-0">Sidebar Access</h5>
                        <div class="permission-subtitle">Configure access for <span id="permissionRoleName">Role</span></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="permission-grid-wrap">
                        <div class="permission-top-actions">
                            <label>
                                <input type="checkbox" class="permission-checkbox" id="selectAllPermissions">
                                Select All Permissions
                            </label>

                            <div class="d-flex flex-wrap gap-4">
                                <label><input type="checkbox" class="permission-checkbox js-col-master" data-action="can_view"> All View</label>
                                <label><input type="checkbox" class="permission-checkbox js-col-master" data-action="can_create"> All Create</label>
                                <label><input type="checkbox" class="permission-checkbox js-col-master" data-action="can_edit"> All Edit</label>
                                <label><input type="checkbox" class="permission-checkbox js-col-master" data-action="can_delete"> All Delete</label>
                                <label><input type="checkbox" class="permission-checkbox js-col-master" data-action="can_print"> All Print</label>
                                <label><input type="checkbox" class="permission-checkbox js-col-master" data-action="can_export"> All Export</label>
                            </div>
                        </div>

                        <table class="permission-grid">
                            <thead>
                                <tr>
                                    <th>Menu / Section</th>
                                    <th>View</th>
                                    <th>Create</th>
                                    <th>Edit</th>
                                    <th>Delete</th>
                                    <th>Print</th>
                                    <th>Export</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php if (!$menus): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No menus found.</td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($menus as $menu): ?>
                                <?php $isChild = !empty($menu['parent_id']); ?>
                                <tr>
                                    <td>
                                        <div class="permission-menu-title <?= $isChild ? 'child' : '' ?>">
                                            <input type="checkbox" class="permission-checkbox js-row-master" data-menu-id="<?= (int)$menu['id'] ?>">
                                            <span>
                                                <?= $isChild ? '— ' : '' ?><?= e($menu['menu_title']) ?>
                                                <span class="permission-menu-slug"><?= e($menu['menu_slug']) ?></span>
                                            </span>
                                        </div>
                                    </td>

                                    <?php foreach ($permissionColumns as $col): ?>
                                        <td>
                                            <input
                                                type="checkbox"
                                                class="permission-checkbox js-permission-check"
                                                data-menu-id="<?= (int)$menu['id'] ?>"
                                                data-action="<?= e($col) ?>"
                                                name="permissions[<?= (int)$menu['id'] ?>][<?= e($col) ?>]"
                                                value="1">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn brand-gradient">Save Access</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/script.php'; ?>

    <script>
    const ROLE_PERMISSIONS = <?= json_encode($rolePermissions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const PERMISSION_COLUMNS = <?= json_encode($permissionColumns) ?>;

    function openRoleModal() {
        document.getElementById("roleAction").value = "create_role";
        document.getElementById("roleId").value = "";
        document.getElementById("roleName").value = "";
        document.getElementById("roleType").value = "custom";
        if (document.getElementById("roleStatus")) document.getElementById("roleStatus").value = "1";
    }

    function editRole(role) {
        document.getElementById("roleAction").value = "update_role";
        document.getElementById("roleId").value = role.role_id || "";
        document.getElementById("roleName").value = role.role_name || "";
        document.getElementById("roleType").value = role.role_type || "custom";
        if (document.getElementById("roleStatus")) document.getElementById("roleStatus").value = role.status || "1";
    }

    function openPermissionModal(role) {
        const roleId = String(role.role_id || "");
        document.getElementById("permissionRoleId").value = roleId;
        document.getElementById("permissionRoleName").textContent = role.role_name || "Role";

        document.querySelectorAll(".js-permission-check").forEach(function (checkbox) {
            checkbox.checked = false;
        });

        const roleAccess = ROLE_PERMISSIONS[roleId] || ROLE_PERMISSIONS[Number(roleId)] || {};

        document.querySelectorAll(".js-permission-check").forEach(function (checkbox) {
            const menuId = String(checkbox.dataset.menuId);
            const action = checkbox.dataset.action;
            const menuAccess = roleAccess[menuId] || roleAccess[Number(menuId)] || {};

            checkbox.checked = Number(menuAccess[action] || 0) === 1;
        });

        refreshRowMasters();
        refreshColumnMasters();
        refreshSelectAll();
    }

    function refreshRowMasters() {
        document.querySelectorAll(".js-row-master").forEach(function (master) {
            const menuId = master.dataset.menuId;
            const boxes = document.querySelectorAll('.js-permission-check[data-menu-id="' + menuId + '"]');
            const checked = Array.from(boxes).filter(cb => cb.checked).length;

            master.checked = boxes.length > 0 && checked === boxes.length;
            master.indeterminate = checked > 0 && checked < boxes.length;
        });
    }

    function refreshColumnMasters() {
        document.querySelectorAll(".js-col-master").forEach(function (master) {
            const action = master.dataset.action;
            const boxes = document.querySelectorAll('.js-permission-check[data-action="' + action + '"]');
            const checked = Array.from(boxes).filter(cb => cb.checked).length;

            master.checked = boxes.length > 0 && checked === boxes.length;
            master.indeterminate = checked > 0 && checked < boxes.length;
        });
    }

    function refreshSelectAll() {
        const all = document.querySelectorAll(".js-permission-check");
        const checked = Array.from(all).filter(cb => cb.checked).length;
        const master = document.getElementById("selectAllPermissions");

        master.checked = all.length > 0 && checked === all.length;
        master.indeterminate = checked > 0 && checked < all.length;
    }

    document.addEventListener("change", function (event) {
        if (event.target.id === "selectAllPermissions") {
            document.querySelectorAll(".js-permission-check").forEach(function (checkbox) {
                checkbox.checked = event.target.checked;
            });
            refreshRowMasters();
            refreshColumnMasters();
            refreshSelectAll();
        }

        if (event.target.classList.contains("js-col-master")) {
            const action = event.target.dataset.action;
            document.querySelectorAll('.js-permission-check[data-action="' + action + '"]').forEach(function (checkbox) {
                checkbox.checked = event.target.checked;
            });
            refreshRowMasters();
            refreshColumnMasters();
            refreshSelectAll();
        }

        if (event.target.classList.contains("js-row-master")) {
            const menuId = event.target.dataset.menuId;
            document.querySelectorAll('.js-permission-check[data-menu-id="' + menuId + '"]').forEach(function (checkbox) {
                checkbox.checked = event.target.checked;
            });
            refreshRowMasters();
            refreshColumnMasters();
            refreshSelectAll();
        }

        if (event.target.classList.contains("js-permission-check")) {
            refreshRowMasters();
            refreshColumnMasters();
            refreshSelectAll();
        }
    });
    </script>
</body>
</html>
