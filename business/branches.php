<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'branches.php');

$pageTitle = 'Branches / Firms';
$businessId = current_business_id();

$hasFloorName = table_has_column($conn, 'branches', 'floor_name');
$hasAddress = table_has_column($conn, 'branches', 'address');
$hasMobile = table_has_column($conn, 'branches', 'mobile');
$hasStatus = table_has_column($conn, 'branches', 'status');
$hasCreatedAt = table_has_column($conn, 'branches', 'created_at');

function branch_page_permissions(mysqli $conn, string $pageUrl): array
{
    $default = [
        'can_view' => true,
        'can_create' => false,
        'can_edit' => false,
        'can_delete' => false,
        'can_approve' => false,
    ];

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
    $pageUrl = basename($pageUrl);

    if ($businessId <= 0 || $roleId <= 0) {
        return $default;
    }

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
        return $default;
    }

    return [
        'can_view' => (int)($row['can_view'] ?? 0) === 1,
        'can_create' => (int)($row['can_create'] ?? 0) === 1,
        'can_edit' => (int)($row['can_edit'] ?? 0) === 1,
        'can_delete' => (int)($row['can_delete'] ?? 0) === 1,
        'can_approve' => (int)($row['can_approve'] ?? 0) === 1,
    ];
}

$permissions = branch_page_permissions($conn, 'branches.php');

$statusSelect = $hasStatus ? 'status' : '1 AS status';
$floorSelect = $hasFloorName ? 'floor_name' : 'NULL AS floor_name';
$addressSelect = $hasAddress ? 'address' : 'NULL AS address';
$mobileSelect = $hasMobile ? 'mobile' : 'NULL AS mobile';
$createdSelect = $hasCreatedAt ? 'created_at' : 'NULL AS created_at';

$totalBranches = 0;
$activeBranches = 0;
$inactiveBranches = 0;
$totalUsersAssigned = 0;

$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM branches WHERE business_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$totalBranches = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
mysqli_stmt_close($stmt);

if ($hasStatus) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM branches WHERE business_id = ? AND status = 1");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $activeBranches = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
    mysqli_stmt_close($stmt);

    $inactiveBranches = max(0, $totalBranches - $activeBranches);
} else {
    $activeBranches = $totalBranches;
}

if (table_has_column($conn, 'users', 'default_branch_id')) {
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM users
        WHERE business_id = ?
          AND default_branch_id IS NOT NULL
    ");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $totalUsersAssigned = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'] ?? 0);
    mysqli_stmt_close($stmt);
}

$branches = [];
$stmt = mysqli_prepare($conn, "
    SELECT
        branch_id,
        business_id,
        branch_code,
        branch_name,
        {$floorSelect},
        {$addressSelect},
        {$mobileSelect},
        {$statusSelect},
        {$createdSelect}
    FROM branches
    WHERE business_id = ?
    ORDER BY branch_id DESC
");
mysqli_stmt_bind_param($stmt, 'i', $businessId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($rs)) {
    $branches[] = $row;
}

mysqli_stmt_close($stmt);

$userCounts = [];
if (table_has_column($conn, 'users', 'default_branch_id')) {
    $stmt = mysqli_prepare($conn, "
        SELECT default_branch_id, COUNT(*) AS total
        FROM users
        WHERE business_id = ?
          AND default_branch_id IS NOT NULL
        GROUP BY default_branch_id
    ");
    mysqli_stmt_bind_param($stmt, 'i', $businessId);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($rs)) {
        $userCounts[(int)$row['default_branch_id']] = (int)$row['total'];
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
    .branches-page {
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
        font-size: 12px;
        font-weight: 500;
    }

    .br-hero {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 14px 16px;
    }

    .br-hero h1 {
        font-size: 20px;
        font-weight: 800;
        margin: 0 0 3px;
        letter-spacing: -.02em;
        color: var(--text-main);
    }

    .br-hero p {
        font-size: 11px;
        line-height: 1.35;
        margin: 0;
        color: var(--text-muted);
        font-weight: 500;
    }

    .br-hero .btn {
        font-size: 11px;
        padding: 7px 11px;
        min-height: 32px;
        border-radius: 999px;
        font-weight: 700;
    }

    .br-stat-card {
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

    .br-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 13px;
        display: grid;
        place-items: center;
        color: #fff;
        flex: 0 0 auto;
    }

    .br-stat-icon svg {
        width: 17px;
        height: 17px;
    }

    .br-stat-label {
        font-size: 10.5px;
        color: var(--text-muted);
        font-weight: 700;
        line-height: 1.15;
    }

    .br-stat-value {
        font-size: 18px;
        color: var(--text-main);
        font-weight: 800;
        margin: 1px 0;
        line-height: 1.05;
    }

    .br-stat-sub {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 550;
        line-height: 1.15;
    }

    .br-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .br-card-head {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-soft);
    }

    .br-card-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text-main);
        margin: 0 0 2px;
    }

    .br-card-sub {
        font-size: 11px;
        color: var(--text-muted);
        margin: 0;
    }

    .br-table th {
        font-size: 10px;
        font-weight: 750;
        padding: 9px 10px;
        white-space: nowrap;
    }

    .br-table td {
        font-size: 11px;
        padding: 9px 10px;
        vertical-align: middle;
    }

    .br-avatar {
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

    .br-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.2;
    }

    .br-sub {
        font-size: 10px;
        color: var(--text-muted);
        line-height: 1.25;
    }

    .br-badge {
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

    .badge-users {
        background: #fef3c7;
        color: #b45309;
    }

    .br-action-btn {
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        padding: 5px 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        line-height: 1;
    }

    .br-mobile-card {
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
        .br-hero {
            padding: 12px;
        }

        .br-hero h1 {
            font-size: 19px;
        }

        .br-stat-card {
            min-height: 64px;
            padding: 9px 10px;
        }

        .br-stat-icon {
            width: 34px;
            height: 34px;
            border-radius: 11px;
        }

        .br-stat-value {
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

            <section class="page-section branches-page p-3 p-lg-3">
                <div class="br-hero mb-3">
                    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                        <div>
                            <h1>Branches / Firms</h1>
                            <p>Manage GK Footwear branch/firm details and control actions based on role permissions.</p>
                        </div>


                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="br-stat-card">
                            <div class="br-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i
                                    data-lucide="store"></i></div>
                            <div>
                                <div class="br-stat-label">Total Branches</div>
                                <div class="br-stat-value"><?= (int)$totalBranches ?></div>
                                <div class="br-stat-sub">Firm / floor wise</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="br-stat-card">
                            <div class="br-stat-icon" style="background:#dcfce7;color:#15803d;"><i
                                    data-lucide="badge-check"></i></div>
                            <div>
                                <div class="br-stat-label">Active</div>
                                <div class="br-stat-value"><?= (int)$activeBranches ?></div>
                                <div class="br-stat-sub">Open for billing</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="br-stat-card">
                            <div class="br-stat-icon" style="background:#fee2e2;color:#b91c1c;"><i
                                    data-lucide="ban"></i></div>
                            <div>
                                <div class="br-stat-label">Inactive</div>
                                <div class="br-stat-value"><?= (int)$inactiveBranches ?></div>
                                <div class="br-stat-sub">Temporarily hidden</div>
                            </div>
                        </article>
                    </div>

                    <div class="col-12 col-sm-6 col-xl-3">
                        <article class="br-stat-card">
                            <div class="br-stat-icon" style="background:#fef3c7;color:#b45309;"><i
                                    data-lucide="users"></i></div>
                            <div>
                                <div class="br-stat-label">Assigned Users</div>
                                <div class="br-stat-value"><?= (int)$totalUsersAssigned ?></div>
                                <div class="br-stat-sub">Branch login users</div>
                            </div>
                        </article>
                    </div>
                </div>

                <section class="br-card">
                    <div class="br-card-head">
                        <div class="d-flex flex-column flex-md-row justify-content-md-between gap-2">
                            <div>
                                <h2 class="br-card-title">Branch / Firm List</h2>
                                <p class="br-card-sub">Role based actions: View, Create, Edit, Delete and Approve
                                    permissions are respected here.</p>
                            </div>

                            <?php if ($permissions['can_create']): ?>
                            <button type="button" class="btn brand-gradient btn-sm rounded-pill fw-bold"
                                data-bs-toggle="modal" data-bs-target="#branchModal" onclick="openBranchModal()">
                                <i data-lucide="plus" style="width:13px;height:13px;"></i>
                                Add Branch
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="d-none d-md-block table-responsive px-3 pb-3">
                        <table class="table br-table mb-0">
                            <thead>
                                <tr>
                                    <th>Branch / Firm</th>
                                    <th>Code</th>
                                    <th>Floor</th>
                                    <th>Contact</th>
                                    <th>Users</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <?php if ($permissions['can_edit'] || $permissions['can_delete'] || $permissions['can_approve']): ?>
                                    <th style="width: 240px;">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$branches): ?>
                                <tr>
                                    <td colspan="<?= ($permissions['can_edit'] || $permissions['can_delete'] || $permissions['can_approve']) ? 8 : 7 ?>"
                                        class="text-center text-muted py-4">No branches found.</td>
                                </tr>
                                <?php endif; ?>

                                <?php foreach ($branches as $branch): ?>
                                <?php
                                $branchId = (int)$branch['branch_id'];
                                $initial = strtoupper(substr(trim((string)$branch['branch_name']), 0, 1)) ?: 'B';
                                $assignedUsers = $userCounts[$branchId] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="br-avatar"><?= e($initial) ?></div>
                                            <div>
                                                <div class="br-title"><?= e($branch['branch_name']) ?></div>
                                                <div class="br-sub">ID: <?= (int)$branchId ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="br-badge badge-code"><?= e($branch['branch_code']) ?></span></td>
                                    <td><?= e($branch['floor_name'] ?: '-') ?></td>
                                    <td>
                                        <div class="br-title"><?= e($branch['mobile'] ?: '-') ?></div>
                                        <div class="br-sub"><?= e($branch['address'] ?: '-') ?></div>
                                    </td>
                                    <td><span class="br-badge badge-users"><?= (int)$assignedUsers ?> users</span></td>
                                    <td>
                                        <?php if ((int)$branch['status'] === 1): ?>
                                        <span class="br-badge status-active">Active</span>
                                        <?php else: ?>
                                        <span class="br-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($branch['created_at']) ? e(date('d-m-Y', strtotime($branch['created_at']))) : '-' ?>
                                    </td>

                                    <?php if ($permissions['can_edit'] || $permissions['can_delete'] || $permissions['can_approve']): ?>
                                    <td>
                                        <?php if ($permissions['can_edit']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary br-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#branchModal"
                                            onclick='editBranch(<?= json_encode($branch, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                        <form method="post" action="api/branches-api.php" class="d-inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning br-action-btn"><?= (int)$branch['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($permissions['can_delete']): ?>
                                        <form method="post" action="api/branches-api.php" class="d-inline"
                                            onsubmit="return confirm('Delete this branch?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_branch">
                                            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger br-action-btn mt-1">Delete</button>
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
                        <?php if (!$branches): ?>
                        <div class="br-mobile-card text-center text-muted">No branches found.</div>
                        <?php endif; ?>

                        <?php foreach ($branches as $branch): ?>
                        <?php
                            $branchId = (int)$branch['branch_id'];
                            $initial = strtoupper(substr(trim((string)$branch['branch_name']), 0, 1)) ?: 'B';
                            $assignedUsers = $userCounts[$branchId] ?? 0;
                            ?>
                        <div class="br-mobile-card">
                            <div class="d-flex gap-2">
                                <div class="br-avatar"><?= e($initial) ?></div>

                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div>
                                            <div class="br-title"><?= e($branch['branch_name']) ?></div>
                                            <div class="br-sub"><?= e($branch['branch_code']) ?> ·
                                                <?= e($branch['floor_name'] ?: '-') ?></div>
                                        </div>

                                        <?php if ((int)$branch['status'] === 1): ?>
                                        <span class="br-badge status-active">Active</span>
                                        <?php else: ?>
                                        <span class="br-badge status-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <span class="br-badge badge-code"><?= e($branch['branch_code']) ?></span>
                                        <span class="br-badge badge-users"><?= (int)$assignedUsers ?> users</span>
                                    </div>

                                    <div class="br-sub mt-2"><?= e($branch['mobile'] ?: '-') ?> ·
                                        <?= e($branch['address'] ?: '-') ?></div>

                                    <?php if ($permissions['can_edit'] || $permissions['can_delete']): ?>
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <?php if ($permissions['can_edit']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary br-action-btn"
                                            data-bs-toggle="modal" data-bs-target="#branchModal"
                                            onclick='editBranch(<?= json_encode($branch, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>

                                        <form method="post" action="api/branches-api.php">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-warning br-action-btn"><?= (int)$branch['status'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($permissions['can_delete']): ?>
                                        <form method="post" action="api/branches-api.php"
                                            onsubmit="return confirm('Delete this branch?');">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_branch">
                                            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
                                            <button type="submit"
                                                class="btn btn-sm btn-outline-danger br-action-btn">Delete</button>
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

    <div class="modal fade" id="branchModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="post" action="api/branches-api.php" class="modal-content">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" id="branchAction" value="create_branch">
                <input type="hidden" name="branch_id" id="branchId">

                <div class="modal-header">
                    <h5 class="modal-title">Branch / Firm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Branch Code *</label>
                            <input type="text" name="branch_code" id="branchCode" class="form-control text-uppercase"
                                required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Branch / Firm Name *</label>
                            <input type="text" name="branch_name" id="branchName" class="form-control" required>
                        </div>

                        <?php if ($hasFloorName): ?>
                        <div class="col-md-4">
                            <label class="form-label">Floor / Location</label>
                            <input type="text" name="floor_name" id="floorName" class="form-control"
                                placeholder="Ground Floor / First Floor">
                        </div>
                        <?php endif; ?>

                        <?php if ($hasMobile): ?>
                        <div class="col-md-4">
                            <label class="form-label">Mobile</label>
                            <input type="text" name="mobile" id="mobile" class="form-control">
                        </div>
                        <?php endif; ?>

                        <?php if ($hasStatus): ?>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="branchStatus" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                        <?php endif; ?>

                        <?php if ($hasAddress): ?>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" id="address" class="form-control" rows="3"></textarea>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn brand-gradient">Save Branch</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/includes/script.php'; ?>

    <script>
    function openBranchModal() {
        document.getElementById("branchAction").value = "create_branch";
        document.getElementById("branchId").value = "";
        document.getElementById("branchCode").value = "";
        document.getElementById("branchName").value = "";

        if (document.getElementById("floorName")) document.getElementById("floorName").value = "";
        if (document.getElementById("mobile")) document.getElementById("mobile").value = "";
        if (document.getElementById("address")) document.getElementById("address").value = "";
        if (document.getElementById("branchStatus")) document.getElementById("branchStatus").value = "1";
    }

    function editBranch(branch) {
        document.getElementById("branchAction").value = "update_branch";
        document.getElementById("branchId").value = branch.branch_id || "";
        document.getElementById("branchCode").value = branch.branch_code || "";
        document.getElementById("branchName").value = branch.branch_name || "";

        if (document.getElementById("floorName")) document.getElementById("floorName").value = branch.floor_name || "";
        if (document.getElementById("mobile")) document.getElementById("mobile").value = branch.mobile || "";
        if (document.getElementById("address")) document.getElementById("address").value = branch.address || "";
        if (document.getElementById("branchStatus")) document.getElementById("branchStatus").value = branch.status ||
            "1";
    }
    </script>
</body>

</html>