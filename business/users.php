<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'users.php');

$pageTitle = 'Users';
$businessId = current_business_id();
$isAdmin = is_business_admin($conn);

$hasUserEmail = table_has_column($conn, 'users', 'email');
$hasUserMobile = table_has_column($conn, 'users', 'mobile');
$hasUserStatus = table_has_column($conn, 'users', 'status');
$hasUserCreatedAt = table_has_column($conn, 'users', 'created_at');
$hasUserLastLogin = table_has_column($conn, 'users', 'last_login_at');

$userEmailSelect = $hasUserEmail ? 'u.email' : 'NULL AS email';
$userMobileSelect = $hasUserMobile ? 'u.mobile' : 'NULL AS mobile';
$userStatusSelect = $hasUserStatus ? 'u.status' : '1 AS status';
$userCreatedSelect = $hasUserCreatedAt ? 'u.created_at' : 'NULL AS created_at';
$userLastLoginSelect = $hasUserLastLogin ? 'u.last_login_at' : 'NULL AS last_login_at';


function ur_role_type_label(string $type): string
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

function ur_role_type_badge(string $type): string
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

function ur_count_scalar(mysqli $conn, string $sql, int $businessId): int
{
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row[0] ?? 0);
}


$totalUsers = ur_count_scalar($conn, "SELECT COUNT(*) FROM users WHERE business_id = ?", $businessId);
$activeUsers = $hasUserStatus
    ? ur_count_scalar($conn, "SELECT COUNT(*) FROM users WHERE business_id = ? AND status = 1", $businessId)
    : $totalUsers;
$totalRoles = ur_count_scalar($conn, "SELECT COUNT(*) FROM roles WHERE business_id = ?", $businessId);

$branches = [];
if (table_exists($conn, 'branches')) {
    $stmt = mysqli_prepare($conn, "
        SELECT branch_id, branch_code, branch_name, floor_name
        FROM branches
        WHERE business_id = ?
        ORDER BY branch_id ASC
    ");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($rs)) {
        $branches[] = $row;
    }

    mysqli_stmt_close($stmt);
}

$roles = [];
$stmt = mysqli_prepare($conn, "
    SELECT role_id, role_name, role_type
    FROM roles
    WHERE business_id = ?
    ORDER BY role_id ASC
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    $roles[] = $row;
}

mysqli_stmt_close($stmt);

$users = [];
$stmt = mysqli_prepare($conn, "
    SELECT
        u.user_id,
        u.name,
        u.username,
        u.default_branch_id,
        u.role_id,
        {$userEmailSelect},
        {$userMobileSelect},
        {$userStatusSelect},
        {$userCreatedSelect},
        {$userLastLoginSelect},
        r.role_name,
        r.role_type,
        b.branch_name,
        b.floor_name
    FROM users u
    LEFT JOIN roles r ON r.role_id = u.role_id
    LEFT JOIN branches b ON b.branch_id = u.default_branch_id
    WHERE u.business_id = ?
    ORDER BY u.user_id DESC
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    $users[] = $row;
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
    /* ============================================================
   GK FOOTWEAR - Users / Roles / Permissions Compact UI
   File: assets/css/users-roles.css
   ============================================================ */

    .users-module {
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
        font-size: 12px;
        font-weight: 500;
    }

    .ur-hero {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 14px 16px;
    }

    .ur-hero h1 {
        font-size: 20px;
        font-weight: 800;
        margin: 0 0 3px;
        letter-spacing: -.02em;
        color: var(--text-main);
    }

    .ur-hero p {
        font-size: 11px;
        line-height: 1.35;
        margin: 0;
        color: var(--text-muted);
        font-weight: 500;
    }

    .ur-hero .btn {
        font-size: 11px;
        padding: 7px 11px;
        min-height: 32px;
        border-radius: 999px;
        font-weight: 700;
    }

    .ur-stat-card {
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

    .ur-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 13px;
        display: grid;
        place-items: center;
        color: #fff;
        flex: 0 0 auto;
    }

    .ur-stat-icon svg {
        width: 17px;
        height: 17px;
    }

    .ur-stat-label {
        font-size: 10.5px;
        color: var(--text-muted);
        font-weight: 700;
        line-height: 1.15;
    }

    .ur-stat-value {
        font-size: 18px;
        color: var(--text-main);
        font-weight: 800;
        margin: 1px 0;
        line-height: 1.05;
    }

    .ur-stat-sub {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 550;
        line-height: 1.15;
    }

    .ur-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .ur-card-head {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-soft);
    }

    .ur-card-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text-main);
        margin: 0 0 2px;
    }

    .ur-card-sub {
        font-size: 11px;
        color: var(--text-muted);
        margin: 0;
    }

    .ur-table th {
        font-size: 10px;
        font-weight: 750;
        padding: 9px 10px;
        white-space: nowrap;
    }

    .ur-table td {
        font-size: 11px;
        padding: 9px 10px;
        vertical-align: middle;
    }

    .ur-avatar {
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

    .ur-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.2;
    }

    .ur-sub {
        font-size: 10px;
        color: var(--text-muted);
        line-height: 1.25;
    }

    .ur-badge {
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 10px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .role-admin {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .role-sales {
        background: #dcfce7;
        color: #15803d;
    }

    .role-cashier {
        background: #fef3c7;
        color: #b45309;
    }

    .role-stock {
        background: #ede9fe;
        color: #6d28d9;
    }

    .role-branch {
        background: #ccfbf1;
        color: #0f766e;
    }

    .role-custom,
    .role-default {
        background: #f1f5f9;
        color: #475569;
    }

    .status-active {
        background: #dcfce7;
        color: #15803d;
    }

    .status-inactive {
        background: #fee2e2;
        color: #b91c1c;
    }

    .ur-action-btn {
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        padding: 5px 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        line-height: 1;
    }

    .ur-mobile-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 10px;
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

    .permission-group {
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        overflow: hidden;
        background: var(--card-bg);
    }

    .permission-group-head {
        padding: 10px 12px;
        background: rgba(148, 163, 184, .08);
        border-bottom: 1px solid var(--border-soft);
    }

    .permission-row {
        padding: 9px 12px;
        border-bottom: 1px solid var(--border-soft);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .permission-row:last-child {
        border-bottom: 0;
    }

    .permission-title {
        font-size: 12px;
        font-weight: 750;
        color: var(--text-main);
    }

    .permission-url {
        font-size: 10px;
        color: var(--text-muted);
    }

    .permission-check {
        width: 18px;
        height: 18px;
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
        .ur-hero {
            padding: 12px;
        }

        .ur-hero h1 {
            font-size: 19px;
        }

        .ur-stat-card {
            min-height: 64px;
            padding: 9px 10px;
        }

        .ur-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 11px;
        }

        .ur-stat-value {
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

            <section class="page-section users-module p-3 p-lg-3">
                <div class="ur-hero mb-3">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                        <div>
                            <h1>Users</h1>
                            <p>Manage admin, sales, cashier, stock manager and branch manager logins for this business.
                            </p>
                        </div>

                        <?php if ($isAdmin): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="roles.php" class="btn btn-outline-primary">
                                <i data-lucide="shield-check" style="width:14px;height:14px;"></i>
                                Roles
                            </a>

                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="ur-stat-card">
                            <div class="ur-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i
                                    data-lucide="users"></i></div>
                            <div>
                                <div class="ur-stat-label">Total Users</div>
                                <div class="ur-stat-value"><?= (int)$totalUsers ?></div>
                                <div class="ur-stat-sub"><?= (int)$activeUsers ?> active users</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="ur-stat-card">
                            <div class="ur-stat-icon" style="background:#dcfce7;color:#15803d;"><i
                                    data-lucide="user-check"></i></div>
                            <div>
                                <div class="ur-stat-label">Active Users</div>
                                <div class="ur-stat-value"><?= (int)$activeUsers ?></div>
                                <div class="ur-stat-sub">Login enabled</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="ur-stat-card">
                            <div class="ur-stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);"><i
                                    data-lucide="shield-check"></i></div>
                            <div>
                                <div class="ur-stat-label">Roles</div>
                                <div class="ur-stat-value"><?= (int)$totalRoles ?></div>
                                <div class="ur-stat-sub">User role groups</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="ur-stat-card">
                            <div class="ur-stat-icon" style="background:#fef3c7;color:#b45309;"><i
                                    data-lucide="store"></i></div>
                            <div>
                                <div class="ur-stat-label">Branches</div>
                                <div class="ur-stat-value"><?= (int)count($branches) ?></div>
                                <div class="ur-stat-sub">Firm-wise access</div>
                            </div>
                        </article>
                    </div>
                </div>

                <section class="ur-card">
                    <div class="ur-card-head">
                        <div class="d-flex flex-column flex-md-row justify-content-md-between gap-2">
                            <div>
                                <h2 class="ur-card-title">Business Users</h2>
                                <p class="ur-card-sub">Create login credentials and assign default branch/role.</p>
                            </div>

                            <?php if ($isAdmin): ?>
                            <button type="button" class="btn brand-gradient btn-sm rounded-pill fw-bold"
                                data-bs-toggle="modal" data-bs-target="#userModal" onclick="openUserModal()">
                                <i data-lucide="user-plus" style="width:13px;height:13px;"></i>
                                Add User
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-none d-md-block table-responsive px-3 pb-3">
                        <table class="table ur-table mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Branch / Firm</th>
                                    <th>Contact</th>
                                    <th>Last Login</th>
                                    <th>Status</th>
                                    <?php if ($isAdmin): ?><th style="width: 210px;">Action</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$users): ?>
                                <tr>
                                    <td colspan="<?= $isAdmin ? 7 : 6 ?>" class="text-center text-muted py-4">No users
                                        found.</td>
                                </tr>
                                <?php endif; ?>

                                <?php foreach ($users as $user): ?>
                                <?php $initial = strtoupper(substr(trim((string)$user['name']), 0, 1)) ?: 'U'; ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="ur-avatar"><?= e($initial) ?></div>
                                            <div>
                                                <div class="ur-title"><?= e($user['name']) ?></div>
                                                <div class="ur-sub">@<?= e($user['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="ur-badge <?= e(ur_role_type_badge((string)$user['role_type'])) ?>"><?= e($user['role_name']) ?></span>
                                    </td>
                                    <td>
                                        <div class="ur-title"><?= e($user['branch_name'] ?: 'All / Not Assigned') ?>
                                        </div>
                                        <div class="ur-sub"><?= e($user['floor_name'] ?: '') ?></div>
                                    </td>
                                    <td>
                                        <div class="ur-title"><?= e($user['mobile'] ?: '-') ?></div>
                                        <div class="ur-sub"><?= e($user['email'] ?: '-') ?></div>
                                    </td>
                                    <td><?= !empty($user['last_login_at']) ? e(date('d-m-Y h:i A', strtotime($user['last_login_at']))) : '-' ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$user['status'] === 1): ?>
                                        <span class="ur-badge status-active">Active</span>
                                        <?php else: ?>
                                        <span class="ur-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>

                                    <?php if ($isAdmin): ?>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary ur-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#userModal"
                                            onclick='editUser(<?= json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                        <form method="post" action="api/users-api.php" class="d-inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_user">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning ur-action-btn"><?= (int)$user['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-md-none px-3 pb-3 d-grid gap-3">
                        <?php if (!$users): ?>
                        <div class="ur-mobile-card text-center text-muted">No users found.</div>
                        <?php endif; ?>

                        <?php foreach ($users as $user): ?>
                        <?php $initial = strtoupper(substr(trim((string)$user['name']), 0, 1)) ?: 'U'; ?>
                        <div class="ur-mobile-card">
                            <div class="d-flex gap-2">
                                <div class="ur-avatar"><?= e($initial) ?></div>

                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <div class="ur-title"><?= e($user['name']) ?></div>
                                            <div class="ur-sub">@<?= e($user['username']) ?></div>
                                        </div>
                                        <?php if ((int)$user['status'] === 1): ?>
                                        <span class="ur-badge status-active">Active</span>
                                        <?php else: ?>
                                        <span class="ur-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <span
                                            class="ur-badge <?= e(ur_role_type_badge((string)$user['role_type'])) ?>"><?= e($user['role_name']) ?></span>
                                        <span
                                            class="ur-badge role-default"><?= e($user['branch_name'] ?: 'No Branch') ?></span>
                                    </div>

                                    <div class="ur-sub mt-2"><?= e($user['mobile'] ?: '-') ?> ·
                                        <?= e($user['email'] ?: '-') ?></div>

                                    <?php if ($isAdmin): ?>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary ur-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#userModal"
                                            onclick='editUser(<?= json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                        <form method="post" action="api/users-api.php">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_user">
                                            <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning ur-action-btn"><?= (int)$user['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
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

    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="post" action="api/users-api.php" class="modal-content">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" id="userAction" value="create_user">
                <input type="hidden" name="user_id" id="userId">

                <div class="modal-header">
                    <h5 class="modal-title">Business User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" id="userName" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" id="username" class="form-control" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" id="password" class="form-control"
                                placeholder="Required for new user">
                        </div>

                        <?php if ($hasUserMobile): ?>
                        <div class="col-md-4">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile" id="userMobile" class="form-control">
                        </div>
                        <?php endif; ?>

                        <?php if ($hasUserEmail): ?>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="userEmail" class="form-control">
                        </div>
                        <?php endif; ?>

                        <div class="col-md-4">
                            <label class="form-label">Role *</label>
                            <select name="role_id" id="userRoleId" class="form-select" required>
                                <option value="">Select Role</option>
                                <?php foreach ($roles as $role): ?>
                                <option value="<?= (int)$role['role_id'] ?>"><?= e($role['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Default Branch / Firm</label>
                            <select name="default_branch_id" id="userBranchId" class="form-select">
                                <option value="">All / Not Assigned</option>
                                <?php foreach ($branches as $branch): ?>
                                <option value="<?= (int)$branch['branch_id'] ?>">
                                    <?= e($branch['branch_name']) ?><?= !empty($branch['floor_name']) ? ' - ' . e($branch['floor_name']) : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php if ($hasUserStatus): ?>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="userStatus" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn brand-gradient">Save User</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/script.php'; ?>

    <script>
    function openUserModal() {
        document.getElementById("userAction").value = "create_user";
        document.getElementById("userId").value = "";
        document.getElementById("userName").value = "";
        document.getElementById("username").value = "";
        document.getElementById("password").value = "";
        document.getElementById("password").placeholder = "Required for new user";
        document.getElementById("userRoleId").value = "";
        document.getElementById("userBranchId").value = "";

        if (document.getElementById("userMobile")) document.getElementById("userMobile").value = "";
        if (document.getElementById("userEmail")) document.getElementById("userEmail").value = "";
        if (document.getElementById("userStatus")) document.getElementById("userStatus").value = "1";
    }

    function editUser(user) {
        document.getElementById("userAction").value = "update_user";
        document.getElementById("userId").value = user.user_id || "";
        document.getElementById("userName").value = user.name || "";
        document.getElementById("username").value = user.username || "";
        document.getElementById("password").value = "";
        document.getElementById("password").placeholder = "Leave empty if no change";
        document.getElementById("userRoleId").value = user.role_id || "";
        document.getElementById("userBranchId").value = user.default_branch_id || "";

        if (document.getElementById("userMobile")) document.getElementById("userMobile").value = user.mobile || "";
        if (document.getElementById("userEmail")) document.getElementById("userEmail").value = user.email || "";
        if (document.getElementById("userStatus")) document.getElementById("userStatus").value = user.status || "1";
    }
    </script>
</body>

</html>