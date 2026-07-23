<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'suppliers.php');

$pageTitle = 'Suppliers';
$businessId = (int) current_business_id();
$branchId = function_exists('current_branch_id') ? (int) current_branch_id() : (int)($_SESSION['branch_id'] ?? $_SESSION['default_branch_id'] ?? 0);

if (!function_exists('supplier_table_has_column')) {
    function supplier_table_has_column(mysqli $conn, string $tableName, string $columnName): bool
    {
        if (function_exists('table_has_column')) {
            return table_has_column($conn, $tableName, $columnName);
        }

        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }
}

function supplier_page_permissions(mysqli $conn, string $pageUrl): array
{
    $all = ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true, 'can_export' => true];

    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return $all;
    }

    $businessId = (int) current_business_id();
    $roleId = function_exists('current_role_id') ? (int) current_role_id() : (int)($_SESSION['role_id'] ?? 0);

    if ($businessId <= 0 || $roleId <= 0) {
        return $all;
    }

    if (!function_exists('table_exists') || !table_exists($conn, 'business_sidebar_menus') || !table_exists($conn, 'business_role_sidebar_access')) {
        return $all;
    }

    $cols = ['can_view'];
    foreach (['can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'] as $col) {
        $cols[] = supplier_table_has_column($conn, 'business_role_sidebar_access', $col) ? $col : '0 AS ' . $col;
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
        return $all;
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

function supplier_csrf_field(): string
{
    if (function_exists('csrf_field')) {
        return csrf_field();
    }
    if (function_exists('csrf_token')) {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
    return '';
}

if ($businessId <= 0) {
    die('Business session missing. Please login again.');
}

$permissions = supplier_page_permissions($conn, 'suppliers.php');
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>

    <style>
    .master-page { font-family: "Inter", "Segoe UI", Arial, sans-serif; font-size: 12px; font-weight: 500; }
    .mp-hero,.mp-card,.mp-stat-card,.supplier-detail-card { background: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 16px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); }
    .mp-hero { padding: 14px 16px; }
    .mp-hero h1 { font-size: 20px; font-weight: 800; margin: 0 0 3px; letter-spacing: -.02em; color: var(--text-main); }
    .mp-hero p { font-size: 11px; line-height: 1.35; margin: 0; color: var(--text-muted); font-weight: 500; }
    .mp-hero .btn { font-size: 11px; padding: 7px 11px; min-height: 32px; border-radius: 999px; font-weight: 700; }
    .mp-stat-card { min-height: 72px; padding: 11px 12px; display: flex; align-items: center; gap: 10px; }
    .mp-stat-icon { width: 40px; height: 40px; border-radius: 13px; display: grid; place-items: center; color: #fff; flex: 0 0 auto; }
    .mp-stat-icon svg { width: 17px; height: 17px; }
    .mp-stat-label { font-size: 10.5px; color: var(--text-muted); font-weight: 700; line-height: 1.15; }
    .mp-stat-value { font-size: 18px; color: var(--text-main); font-weight: 800; margin: 1px 0; line-height: 1.05; }
    .mp-stat-sub,.mp-sub { font-size: 10px; color: var(--text-muted); line-height: 1.25; }
    .mp-card { overflow: hidden; }
    .mp-card-head { padding: 12px 14px; border-bottom: 1px solid var(--border-soft); }
    .mp-card-title { font-size: 15px; font-weight: 800; color: var(--text-main); margin: 0 0 2px; }
    .mp-card-sub { font-size: 11px; color: var(--text-muted); margin: 0; }
    .mp-filter-input,.mp-filter-select { min-height: 32px; font-size: 11px; border-radius: 999px; padding: 5px 10px; font-weight: 650; }
    .mp-table th { font-size: 10px; font-weight: 750; padding: 9px 10px; white-space: nowrap; background: #f1f5f9; color: #0f172a; text-transform: uppercase; letter-spacing: .04em; border-bottom: 0; }
    .mp-table td { font-size: 11px; padding: 9px 10px; vertical-align: middle; }
    .mp-avatar { width: 34px; height: 34px; border-radius: 12px; display: grid; place-items: center; background: linear-gradient(135deg, var(--brand-1), var(--brand-2)); color: #fff; font-size: 13px; font-weight: 800; flex: 0 0 auto; }
    .mp-title { font-size: 12px; font-weight: 800; color: var(--text-main); line-height: 1.2; }
    .mp-badge { border-radius: 999px; padding: 5px 8px; font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .status-active { background: #dcfce7; color: #15803d; }
    .status-inactive { background: #fee2e2; color: #b91c1c; }
    .badge-code { background: #dbeafe; color: #1d4ed8; }
    .badge-money { background: #fef3c7; color: #b45309; }
    .badge-ledger { background: #ede9fe; color: #6d28d9; }
    .badge-credit { background: #fee2e2; color: #b91c1c; }
    .badge-debit { background: #dcfce7; color: #15803d; }
    .badge-purpose { background: #ecfeff; color: #0e7490; }
    .mp-action-btn { border-radius: 999px; font-size: 10.5px; font-weight: 700; padding: 5px 8px; display: inline-flex; align-items: center; justify-content:center; gap: 4px; line-height: 1; }
    .mp-action-btn svg { width: 13px; height: 13px; }

    /* Customer module method applied to Supplier action buttons */
    .supplier-action-wrap {
        display: grid;
        grid-template-columns: repeat(5, 32px);
        gap: 6px;
        justify-content: center;
        align-items: center;
        min-width: 172px;
    }

    .supplier-action-btn {
        width: 32px;
        height: 32px;
        border-radius: 11px;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        cursor: pointer;
        transition: all .18s ease;
        box-shadow: 0 4px 10px rgba(15, 23, 42, .05);
        background: #f8fafc;
        text-decoration: none;
    }

    .supplier-action-btn i,
    .supplier-action-btn svg {
        width: 15px;
        height: 15px;
        stroke-width: 2.4;
    }

    .supplier-action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, .12);
        text-decoration: none;
    }

    .supplier-action-btn:active {
        transform: translateY(0);
        box-shadow: none;
    }

    .supplier-action-btn.action-view {
        background: #eff6ff;
        color: #1d4ed8;
        border-color: #bfdbfe;
    }

    .supplier-action-btn.action-view:hover {
        background: #dbeafe;
    }

    .supplier-action-btn.action-transaction {
        background: #ecfdf5;
        color: #16a34a;
        border-color: #bbf7d0;
    }

    .supplier-action-btn.action-transaction:hover {
        background: #dcfce7;
    }

    .supplier-action-btn.action-edit {
        background: #eef2ff;
        color: #4f46e5;
        border-color: #c7d2fe;
    }

    .supplier-action-btn.action-edit:hover {
        background: #e0e7ff;
    }

    .supplier-action-btn.action-toggle-active {
        background: #fff7ed;
        color: #ea580c;
        border-color: #fed7aa;
    }

    .supplier-action-btn.action-toggle-active:hover {
        background: #ffedd5;
    }

    .supplier-action-btn.action-toggle-inactive {
        background: #ecfdf5;
        color: #16a34a;
        border-color: #bbf7d0;
    }

    .supplier-action-btn.action-toggle-inactive:hover {
        background: #dcfce7;
    }

    .supplier-action-btn.action-delete {
        background: #fef2f2;
        color: #dc2626;
        border-color: #fecaca;
    }

    .supplier-action-btn.action-delete:hover {
        background: #fee2e2;
    }

    .supplier-action-btn.action-delete.blocked-delete {
        opacity: .72;
        cursor: not-allowed !important;
    }

    .supplier-action-btn.action-delete.blocked-delete:hover {
        transform: none;
        box-shadow: 0 4px 10px rgba(15, 23, 42, .05);
        background: #fef2f2;
        cursor: not-allowed !important;
    }

    .supplier-action-text {
        position: absolute;
        width: 1px;
        height: 1px;
        padding: 0;
        margin: -1px;
        overflow: hidden;
        clip: rect(0,0,0,0);
        white-space: nowrap;
        border: 0;
    }
    .statement-opening-row td { background:#fff7ed!important; color:#9a3412; font-weight:800; }
    .statement-opening-row .mp-badge { background:#fed7aa; color:#c2410c; }
    .mp-mobile-card { background: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 14px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); padding: 10px; }
    .supplier-detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .supplier-detail-box { border: 1px solid var(--border-soft); background: #f8fafc; border-radius: 14px; padding: 10px; }
    .purpose-card { border: 1px solid var(--border-soft); border-radius: 16px; padding: 12px; background: #f8fafc; }
    .purpose-card.credit { background: linear-gradient(135deg, #fff1f2, #fff7ed); border-color: #fecdd3; }
    .purpose-card.debit { background: linear-gradient(135deg, #ecfdf5, #f0fdf4); border-color: #bbf7d0; }
    .purpose-help { font-size: 10.5px; color: var(--text-muted); line-height: 1.35; }
    .modal-title { font-size: 15px; font-weight: 750; }
    .modal .form-label { font-size: 11px; font-weight: 700; margin-bottom: 4px; }
    .modal .form-control,.modal .form-select { min-height: 34px; font-size: 12px; border-radius: 12px; padding: 6px 10px; }
    .modal-footer .btn { font-size: 12px; padding: 7px 12px; border-radius: 12px; font-weight: 700; }
    @media (max-width: 991px) { .supplier-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) { .mp-hero { padding: 12px; } .mp-hero h1 { font-size: 19px; } .mp-stat-card { min-height: 64px; padding: 9px 10px; } .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; } .mp-stat-value { font-size: 16px; } .supplier-detail-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) { include __DIR__ . '/includes/common-toast.php'; } ?>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>

        <section class="page-section master-page p-3 p-lg-3">
            <div class="mp-hero mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div>
                        <h1>Suppliers</h1>
                        <p>Manage supplier master, GSTIN, vendor outstanding, ledger, purpose-based debit/credit transactions and stock inward payment integration.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($permissions['can_create']): ?>
                        <button type="button" id="openSupplierModalBtn" class="btn brand-gradient">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Supplier
                        </button>
                        <button type="button" id="openTransactionModalBtn" class="btn btn-outline-success">
                            <i data-lucide="wallet-cards" style="width:14px;height:14px;"></i> Add Transaction
                        </button>
                        <?php endif; ?>
                        <button type="button" id="resetSupplierPage" class="btn btn-outline-primary">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="truck"></i></div><div><div class="mp-stat-label">Total Suppliers</div><div class="mp-stat-value" id="totalSuppliers">0</div><div class="mp-stat-sub">Vendor master</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="badge-check"></i></div><div><div class="mp-stat-label">Active</div><div class="mp-stat-value" id="activeSuppliers">0</div><div class="mp-stat-sub">Available vendors</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="wallet"></i></div><div><div class="mp-stat-label">Outstanding Suppliers</div><div class="mp-stat-value" id="outstandingSuppliers">0</div><div class="mp-stat-sub">Balance greater than zero</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="indian-rupee"></i></div><div><div class="mp-stat-label">Calculated Balance</div><div class="mp-stat-value" id="currentOutstandingTotal">₹0.00</div><div class="mp-stat-sub">Opening + additions - decreases</div></div></article></div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                        <div>
                            <h2 class="mp-card-title">Supplier List</h2>
                            <p class="mp-card-sub">Search, view, edit, deactivate, delete and track purpose-wise supplier ledger.</p>
                        </div>
                        <form method="get" id="supplierFilterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                            <input type="text" name="search" id="search" class="form-control mp-filter-input" placeholder="Search supplier / mobile / email / GSTIN">
                            <select name="status" id="status" class="form-select mp-filter-select">
                                <option value="">All</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">Filter</button>
                        </form>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table mb-0">
                        <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Mobile / Email</th>
                            <th>GSTIN</th>
                            <th>Opening</th>
                            <th>Calculated Balance</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="width: 190px;">Action</th>
                        </tr>
                        </thead>
                        <tbody id="supplierTableBody"><tr><td colspan="8" class="text-center text-muted py-4">Loading suppliers...</td></tr></tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="supplierMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading suppliers...</div>
                </div>
            </section>

            <div class="mt-3 p-2 rounded-4 border text-primary small fw-bold" style="background:#eff6ff;border-color:#bfdbfe!important;">Supplier balance rule: Stock Inward Purchase/Credit adds amount. Payment/Debit/Reversal decreases amount. Supplier master and Supplier Ledger Report use the same transaction history.</div>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-labelledby="supplierFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content" id="supplierForm" autocomplete="off">
            <?= supplier_csrf_field() ?>
            <input type="hidden" name="action" value="save_supplier">
            <input type="hidden" name="supplier_id" id="supplier_id" value="0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="supplierFormTitle">Add Supplier</h5>
                    <div class="mp-sub">Basic supplier master with opening outstanding.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label">Supplier Name *</label><input type="text" name="supplier_name" id="supplier_name" class="form-control" required maxlength="200" placeholder="Enter supplier name"></div>
                    <div class="col-12 col-md-6"><label class="form-label">Mobile</label><input type="text" name="mobile" id="mobile" class="form-control" maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" placeholder="10 digit mobile"></div>
                    <div class="col-12 col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="email" class="form-control" maxlength="150" placeholder="supplier@example.com"></div>
                    <div class="col-12 col-md-6"><label class="form-label">GSTIN</label><input type="text" name="gstin" id="gstin" class="form-control text-uppercase" maxlength="15" placeholder="33ABCDE1234F1Z5"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Opening Outstanding</label><input type="number" step="0.01" min="0" name="opening_outstanding" id="opening_outstanding" class="form-control" value="0.00"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Current Outstanding</label><input type="number" step="0.01" id="current_outstanding" class="form-control" value="0.00" readonly></div>
                    <div class="col-12 col-md-4"><label class="form-label">Status</label><select name="status" id="supplier_status" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="address" rows="2" class="form-control" placeholder="Supplier address"></textarea></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="supplierSubmitBtn" class="btn brand-gradient">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="supplierTransactionModal" tabindex="-1" aria-labelledby="supplierTransactionTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content" id="supplierTransactionForm" autocomplete="off">
            <?= supplier_csrf_field() ?>
            <input type="hidden" name="action" value="save_transaction">
            <input type="hidden" name="branch_id" value="<?= (int)$branchId ?>">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="supplierTransactionTitle">Supplier Transaction</h5>
                    <div class="mp-sub">Use purpose to post Credit, Debit, Advance Payment, Purchase Return, or Other.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Supplier *</label>
                        <select name="supplier_id" id="txn_supplier_id" class="form-select" required>
                            <option value="">Select supplier</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Purpose *</label>
                        <select name="purpose" id="txn_purpose" class="form-select" required>
                            <option value="credit">Credit - Goods supplied on credit</option>
                            <option value="debit">Debit - Payment to supplier</option>
                            <option value="advance_payment">Advance Payment</option>
                            <option value="purchase_return">Purchase Return</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6" id="txnOtherDirectionWrap" style="display:none;">
                        <label class="form-label">Other Entry Type *</label>
                        <select name="other_direction" id="txn_other_direction" class="form-select">
                            <option value="debit">Debit - Reduce supplier payable</option>
                            <option value="credit">Credit - Increase supplier payable</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Amount *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="txn_amount" class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Reference Type</label>
                        <input type="text" name="reference_type" id="txn_reference_type" class="form-control" maxlength="80" placeholder="purchase / payment / return / adjustment">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Reference ID / No.</label>
                        <input type="text" name="reference_no" id="txn_reference_no" class="form-control" maxlength="120" placeholder="Bill / inward / payment reference">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" id="txn_remarks" rows="2" class="form-control" placeholder="Transaction note"></textarea>
                    </div>
                    <div class="col-12">
                        <div id="purposePreview" class="purpose-card credit">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="mp-title" id="purposePreviewTitle">Credit Transaction</div>
                                    <div class="purpose-help" id="purposePreviewHelp">Supplier supplied goods on credit. This will increase supplier payable/outstanding.</div>
                                </div>
                                <span class="mp-badge badge-credit" id="purposePreviewBadge">Credit</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="supplierTransactionSubmitBtn" class="btn brand-gradient">Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="supplierDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title">Supplier Details</h5><div class="mp-sub">Outstanding, purpose-wise ledger and supplier master information.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="supplierDetailBody"><div class="text-center text-muted py-4">Loading details...</div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>

<script>
(function () {
    'use strict';

    const apiUrl = 'api/suppliers-api.php';
    const canCreate = <?= $permissions['can_create'] ? 'true' : 'false' ?>;
    const canEdit = <?= $permissions['can_edit'] ? 'true' : 'false' ?>;
    const canDelete = <?= $permissions['can_delete'] ? 'true' : 'false' ?>;

    const supplierForm = document.getElementById('supplierForm');
    const supplierTransactionForm = document.getElementById('supplierTransactionForm');
    const filterForm = document.getElementById('supplierFilterForm');
    const tableBody = document.getElementById('supplierTableBody');
    const mobileCards = document.getElementById('supplierMobileCards');
    const mobileInput = document.getElementById('mobile');
    const gstinInput = document.getElementById('gstin');
    const supplierModalEl = document.getElementById('supplierModal');
    const supplierTransactionModalEl = document.getElementById('supplierTransactionModal');
    const supplierDetailModalEl = document.getElementById('supplierDetailModal');
    const supplierDetailBody = document.getElementById('supplierDetailBody');
    const txnSupplierSelect = document.getElementById('txn_supplier_id');
    const txnPurpose = document.getElementById('txn_purpose');
    const txnOtherDirection = document.getElementById('txn_other_direction');
    let supplierModalInstance = null;
    let supplierTransactionModalInstance = null;
    let supplierDetailModalInstance = null;
    let searchTimer = null;
    let cachedSuppliers = [];

    const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 });

    const purposeMeta = {
        credit: { label: 'Credit', direction: 'credit', badge: 'badge-credit', title: 'Credit Transaction', help: 'Supplier supplied goods on credit. This increases supplier payable/outstanding.' },
        debit: { label: 'Debit', direction: 'debit', badge: 'badge-debit', title: 'Debit Transaction', help: 'Payment made to supplier. This reduces supplier payable/outstanding.' },
        advance_payment: { label: 'Advance Payment', direction: 'debit', badge: 'badge-debit', title: 'Advance Payment', help: 'Advance paid before purchase. This reduces payable or creates advance balance.' },
        purchase_return: { label: 'Purchase Return', direction: 'debit', badge: 'badge-debit', title: 'Purchase Return', help: 'Goods returned to supplier. This reduces supplier payable/outstanding.' },
        other: { label: 'Other', direction: 'debit', badge: 'badge-purpose', title: 'Other Transaction', help: 'Choose Debit or Credit entry type for this manual adjustment.' }
    };

    function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
    function showMessage(type, message) { const toastType = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'error'); if (window.AppToast && typeof window.AppToast.show === 'function') { window.AppToast.show(toastType, message); return; } if (window.showToast) { window.showToast(message, toastType === 'error' ? 'danger' : toastType); return; } alert(message); }
    function normalizeMobile(value) { return String(value || '').replace(/[^0-9]/g, '').slice(0, 10); }
    function normalizeGstin(value) { return String(value || '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 15); }
    function supplierInitial(name) { const cleanName = String(name || 'S').trim(); return escapeHtml(cleanName.substring(0, 1).toUpperCase() || 'S'); }
    function statusBadge(status) { return parseInt(status, 10) === 1 ? '<span class="mp-badge status-active">Active</span>' : '<span class="mp-badge status-inactive">Inactive</span>'; }
    function purposeLabel(purpose) { const meta = purposeMeta[String(purpose || '').toLowerCase()] || purposeMeta.other; return meta.label; }
    function transactionBadge(direction) { const value = String(direction || '').toLowerCase() === 'credit' ? 'credit' : 'debit'; return '<span class="mp-badge ' + (value === 'credit' ? 'badge-credit' : 'badge-debit') + '">' + (value === 'credit' ? 'Credit' : 'Debit') + '</span>'; }

    function getSupplierModal() { if (window.bootstrap && window.bootstrap.Modal) { if (!supplierModalInstance) supplierModalInstance = new window.bootstrap.Modal(supplierModalEl, { backdrop: 'static', keyboard: false }); return supplierModalInstance; } return null; }
    function getSupplierTransactionModal() { if (window.bootstrap && window.bootstrap.Modal) { if (!supplierTransactionModalInstance) supplierTransactionModalInstance = new window.bootstrap.Modal(supplierTransactionModalEl, { backdrop: 'static', keyboard: false }); return supplierTransactionModalInstance; } return null; }
    function getSupplierDetailModal() { if (window.bootstrap && window.bootstrap.Modal) { if (!supplierDetailModalInstance) supplierDetailModalInstance = new window.bootstrap.Modal(supplierDetailModalEl); return supplierDetailModalInstance; } return null; }
    function openSupplierModal() { const modal = getSupplierModal(); if (modal) modal.show(); setTimeout(function () { document.getElementById('supplier_name').focus(); }, 250); }
    function closeSupplierModal() { const modal = getSupplierModal(); if (modal) modal.hide(); }
    function openSupplierTransactionModal() { const modal = getSupplierTransactionModal(); if (modal) modal.show(); }
    function closeSupplierTransactionModal() { const modal = getSupplierTransactionModal(); if (modal) modal.hide(); }
    function openSupplierDetailModal() { const modal = getSupplierDetailModal(); if (modal) modal.show(); }

    function csrfAppend(formData, sourceForm) {
        const form = sourceForm || supplierForm;
        const csrfInput = form.querySelector('input[type="hidden"][name*="csrf"], input[type="hidden"][name="_token"]');
        if (csrfInput && !formData.has(csrfInput.name)) formData.append(csrfInput.name, csrfInput.value);
    }

    async function apiGet(params) { const response = await fetch(apiUrl + '?' + new URLSearchParams(params).toString(), { headers: { 'Accept': 'application/json' } }); return response.json(); }
    async function apiPost(formData, sourceForm) { csrfAppend(formData, sourceForm); const response = await fetch(apiUrl, { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } }); return response.json(); }

    function resetSupplierForm() {
        supplierForm.reset();
        document.getElementById('supplier_id').value = '0';
        document.getElementById('opening_outstanding').value = '0.00';
        document.getElementById('current_outstanding').value = '0.00';
        document.getElementById('supplier_status').value = '1';
        document.getElementById('supplierFormTitle').textContent = 'Add Supplier';
        document.getElementById('supplierSubmitBtn').innerHTML = 'Save Supplier';
    }

    function resetTransactionForm(supplierId) {
        supplierTransactionForm.reset();
        populateSupplierOptions(supplierId || '');
        document.getElementById('txn_purpose').value = 'credit';
        document.getElementById('txn_reference_type').value = 'supplier_transaction';
        document.getElementById('txn_amount').value = '';
        updatePurposePreview();
    }

    function populateSupplierOptions(selectedId) {
        const selected = String(selectedId || '');
        txnSupplierSelect.innerHTML = '<option value="">Select supplier</option>' + cachedSuppliers.map(function (supplier) {
            const id = String(supplier.supplier_id || '');
            const label = (supplier.supplier_name || '-') + ' · Balance ' + money.format(parseFloat(supplier.calculated_balance ?? supplier.current_outstanding ?? 0));
            return '<option value="' + escapeHtml(id) + '" ' + (id === selected ? 'selected' : '') + '>' + escapeHtml(label) + '</option>';
        }).join('');
    }

    function validateSupplierForm() {
        const supplierName = document.getElementById('supplier_name').value.trim();
        const mobile = normalizeMobile(mobileInput.value);
        const email = document.getElementById('email').value.trim();
        const gstin = normalizeGstin(gstinInput.value);
        const openingOutstanding = parseFloat(document.getElementById('opening_outstanding').value || '0');
        const gstinPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;
        mobileInput.value = mobile;
        gstinInput.value = gstin;
        if (!supplierName) { showMessage('error', 'Supplier name is required.'); document.getElementById('supplier_name').focus(); return false; }
        if (mobile && !/^[6-9][0-9]{9}$/.test(mobile)) { showMessage('error', 'Mobile number must be exactly 10 digits and start with 6, 7, 8, or 9.'); mobileInput.focus(); return false; }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showMessage('error', 'Enter a valid email address.'); document.getElementById('email').focus(); return false; }
        if (gstin && !gstinPattern.test(gstin)) { showMessage('error', 'Enter a valid GSTIN. Sample: 33ABCDE1234F1Z5.'); gstinInput.focus(); return false; }
        if (Number.isNaN(openingOutstanding) || openingOutstanding < 0) { showMessage('error', 'Opening outstanding cannot be negative.'); document.getElementById('opening_outstanding').focus(); return false; }
        return true;
    }

    function validateTransactionForm() {
        const supplierId = parseInt(document.getElementById('txn_supplier_id').value || '0', 10);
        const amount = parseFloat(document.getElementById('txn_amount').value || '0');
        const purpose = document.getElementById('txn_purpose').value;
        if (!supplierId) { showMessage('error', 'Please select supplier.'); document.getElementById('txn_supplier_id').focus(); return false; }
        if (!purpose) { showMessage('error', 'Please select purpose.'); document.getElementById('txn_purpose').focus(); return false; }
        if (!amount || amount <= 0) { showMessage('error', 'Enter valid transaction amount.'); document.getElementById('txn_amount').focus(); return false; }
        return true;
    }

    function fillSupplierForm(supplier) {
        if (!supplier) return;
        document.getElementById('supplier_id').value = supplier.supplier_id || 0;
        document.getElementById('supplier_name').value = supplier.supplier_name || '';
        document.getElementById('mobile').value = normalizeMobile(supplier.mobile || '');
        document.getElementById('email').value = supplier.email || '';
        document.getElementById('gstin').value = normalizeGstin(supplier.gstin || '');
        document.getElementById('address').value = supplier.address || '';
        document.getElementById('opening_outstanding').value = parseFloat(supplier.opening_outstanding || 0).toFixed(2);
        document.getElementById('current_outstanding').value = parseFloat(supplier.calculated_balance ?? supplier.current_outstanding ?? 0).toFixed(2);
        document.getElementById('supplier_status').value = String(supplier.status ?? 1);
        document.getElementById('supplierFormTitle').textContent = 'Edit Supplier';
        document.getElementById('supplierSubmitBtn').innerHTML = 'Update Supplier';
        openSupplierModal();
    }

    function updatePurposePreview() {
        const purpose = txnPurpose.value || 'credit';
        const otherDirection = txnOtherDirection.value || 'debit';
        let meta = purposeMeta[purpose] || purposeMeta.other;
        let direction = purpose === 'other' ? otherDirection : meta.direction;
        const wrap = document.getElementById('txnOtherDirectionWrap');
        if (wrap) wrap.style.display = purpose === 'other' ? '' : 'none';
        const card = document.getElementById('purposePreview');
        const badge = document.getElementById('purposePreviewBadge');
        card.className = 'purpose-card ' + direction;
        document.getElementById('purposePreviewTitle').textContent = meta.title;
        document.getElementById('purposePreviewHelp').textContent = meta.help;
        badge.className = 'mp-badge ' + (direction === 'credit' ? 'badge-credit' : 'badge-debit');
        badge.textContent = direction === 'credit' ? 'Credit' : 'Debit';
    }

    function renderStats(stats) {
        document.getElementById('totalSuppliers').textContent = parseInt(stats.total_suppliers || 0, 10);
        document.getElementById('activeSuppliers').textContent = parseInt(stats.active_suppliers || 0, 10);
        document.getElementById('outstandingSuppliers').textContent = parseInt(stats.outstanding_suppliers || 0, 10);
        document.getElementById('currentOutstandingTotal').textContent = money.format(parseFloat(stats.calculated_balance_total || stats.current_outstanding_total || 0));
    }

    function supplierActions(supplier, toggleText) {
        const supplierId = parseInt(supplier.supplier_id || 0, 10);
        const isActive = String(toggleText || '').toLowerCase() === 'deactivate';
        const deleteEligible = parseInt(supplier.delete_eligible || 0, 10) === 1;
        const deleteReason = String(
            supplier.delete_block_reason ||
            'Delete is allowed only for inactive suppliers with zero calculated balance.'
        );
        const toggleIcon = isActive ? 'user-x' : 'user-check';
        const toggleClass = isActive ? 'action-toggle-active' : 'action-toggle-inactive';

        return `<div class="supplier-action-wrap" role="group" aria-label="Supplier actions">
            <button type="button" class="supplier-action-btn action-view js-view" data-id="${supplierId}" title="View supplier" aria-label="View supplier">
                <i data-lucide="eye"></i><span class="supplier-action-text">View</span>
            </button>
            ${canCreate ? `<button type="button" class="supplier-action-btn action-transaction js-txn" data-id="${supplierId}" title="Add transaction" aria-label="Add transaction">
                <i data-lucide="wallet-cards"></i><span class="supplier-action-text">Transaction</span>
            </button>` : ''}
            ${canEdit ? `<button type="button" class="supplier-action-btn action-edit js-edit" data-id="${supplierId}" title="Edit supplier" aria-label="Edit supplier">
                <i data-lucide="pencil"></i><span class="supplier-action-text">Edit</span>
            </button>` : ''}
            ${canEdit ? `<button type="button" class="supplier-action-btn ${toggleClass} js-toggle" data-id="${supplierId}" title="${toggleText} supplier" aria-label="${toggleText} supplier">
                <i data-lucide="${toggleIcon}"></i><span class="supplier-action-text">${toggleText}</span>
            </button>` : ''}
            ${canDelete ? `<button type="button"
                class="supplier-action-btn action-delete js-delete ${deleteEligible ? '' : 'blocked-delete'}"
                data-id="${supplierId}"
                data-delete-eligible="${deleteEligible ? '1' : '0'}"
                data-delete-reason="${escapeHtml(deleteReason)}"
                title="${deleteEligible ? 'Delete supplier' : escapeHtml(deleteReason)}"
                aria-label="${deleteEligible ? 'Delete supplier' : escapeHtml(deleteReason)}"
                ${deleteEligible ? '' : 'aria-disabled="true"'}>
                <i data-lucide="trash-2"></i>
                <span class="supplier-action-text">${deleteEligible ? 'Delete' : 'Delete blocked'}</span>
            </button>` : ''}
        </div>`;
    }

    function renderSuppliers(suppliers) {
        cachedSuppliers = suppliers || [];
        populateSupplierOptions('');
        if (!suppliers.length) {
            tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">No suppliers found.</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No suppliers found.</div>';
            return;
        }
        tableBody.innerHTML = suppliers.map(function (supplier) {
            const supplierId = parseInt(supplier.supplier_id || 0, 10);
            const createdAt = supplier.created_at ? new Date(String(supplier.created_at).replace(' ', 'T')) : null;
            const createdDate = createdAt && !Number.isNaN(createdAt.getTime()) ? createdAt.toLocaleDateString('en-GB') : '-';
            const toggleText = parseInt(supplier.status, 10) === 1 ? 'Deactivate' : 'Activate';
            return `<tr>
                <td><div class="d-flex align-items-center gap-2"><div class="mp-avatar">${supplierInitial(supplier.supplier_name)}</div><div><div class="mp-title">${escapeHtml(supplier.supplier_name)}</div><div class="mp-sub">ID: ${supplierId}</div></div></div></td>
                <td><div class="fw-bold">${escapeHtml(supplier.mobile || '-')}</div><div class="mp-sub">${escapeHtml(supplier.email || '-')}</div></td>
                <td>${escapeHtml(supplier.gstin || '-')}</td>
                <td><span class="mp-badge badge-money">${money.format(parseFloat(supplier.opening_outstanding || 0))}</span></td>
                <td><div class="fw-bold">${money.format(parseFloat(supplier.calculated_balance ?? supplier.current_outstanding ?? 0))}</div></td>
                <td>${statusBadge(supplier.status)}</td>
                <td>${escapeHtml(createdDate)}</td>
                <td>${supplierActions(supplier, toggleText)}</td>
            </tr>`;
        }).join('');

        mobileCards.innerHTML = suppliers.map(function (supplier) {
            const supplierId = parseInt(supplier.supplier_id || 0, 10);
            const toggleText = parseInt(supplier.status, 10) === 1 ? 'Deactivate' : 'Activate';
            return `<div class="mp-mobile-card"><div class="d-flex gap-2"><div class="mp-avatar">${supplierInitial(supplier.supplier_name)}</div><div class="flex-grow-1"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${escapeHtml(supplier.supplier_name)}</div><div class="mp-sub">${escapeHtml(supplier.mobile || '-')}</div></div>${statusBadge(supplier.status)}</div><div class="mp-sub mt-2">GSTIN: ${escapeHtml(supplier.gstin || '-')}</div><div class="fw-bold mt-1">Balance: ${money.format(parseFloat(supplier.calculated_balance ?? supplier.current_outstanding ?? 0))}</div><div class="mt-2">${supplierActions(supplier, toggleText)}</div></div></div></div>`;
        }).join('');
    }

    function detailBox(label, value) { return '<div class="supplier-detail-box"><div class="mp-stat-label">' + escapeHtml(label) + '</div><div class="mp-title mt-1">' + value + '</div></div>'; }
    function renderLedger(ledger) {
        if (!ledger || !ledger.length) {
            return '<div class="text-muted small">No ledger transactions found.</div>';
        }

        return `<div class="table-responsive mt-3">
            <table class="table mp-table mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Purpose</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Balance</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    ${ledger.map(function (row) {
                        const isOpening = parseInt(row.is_opening || 0, 10) === 1;
                        const direction = parseFloat(row.credit || 0) > 0 ? 'credit' : 'debit';
                        const typeLabel = row.display_type || row.purpose_label || purposeLabel(row.purpose || row.reference_type);
                        return `<tr class="${isOpening ? 'statement-opening-row' : ''}">
                            <td>${escapeHtml(row.entry_display || row.created_at || '-')}</td>
                            <td><span class="mp-badge badge-purpose">${escapeHtml(typeLabel)}</span></td>
                            <td>${isOpening ? '<span class="mp-badge badge-money">Opening</span>' : transactionBadge(row.transaction_direction || direction)}</td>
                            <td>${escapeHtml(row.reference_no || row.reference_type || '-')}</td>
                            <td>${money.format(parseFloat(row.debit || 0))}</td>
                            <td>${money.format(parseFloat(row.credit || 0))}</td>
                            <td class="fw-bold">${money.format(parseFloat(row.balance || 0))}</td>
                            <td>${escapeHtml(row.remarks || '-')}</td>
                        </tr>`;
                    }).join('')}
                </tbody>
            </table>
        </div>`;
    }

    async function loadSuppliers() {
        tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4">Loading suppliers...</td></tr>';
        mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">Loading suppliers...</div>';
        try {
            const data = await apiGet({ action: 'list', search: document.getElementById('search').value, status: document.getElementById('status').value });
            if (!data.success) { showMessage('error', data.message || 'Unable to load suppliers.'); return; }
            renderStats(data.stats || {});
            renderSuppliers(data.suppliers || []);
            if (window.lucide) window.lucide.createIcons();
        } catch (error) { showMessage('error', 'Unable to connect to supplier API.'); }
    }

    async function viewSupplier(supplierId) {
        supplierDetailBody.innerHTML = '<div class="text-center text-muted py-4">Loading details...</div>';
        openSupplierDetailModal();
        try {
            const data = await apiGet({ action: 'get', supplier_id: supplierId });
            if (!data.success) {
                supplierDetailBody.innerHTML = '<div class="text-danger">' + escapeHtml(data.message || 'Supplier not found.') + '</div>';
                return;
            }

            const s = data.supplier || {};
            const summary = data.summary || {};
            const calculatedBalance = parseFloat(summary.closing_balance ?? s.calculated_balance ?? s.current_outstanding ?? 0);
            const dbBalance = parseFloat(s.db_current_balance ?? s.current_outstanding ?? 0);
            const txnButton = canCreate ? `<button type="button" class="btn btn-sm btn-outline-success rounded-pill js-txn" data-id="${parseInt(s.supplier_id || 0, 10)}">Add Transaction</button>` : '';

            supplierDetailBody.innerHTML =
                `<div class="supplier-detail-grid">
                    ${detailBox('Supplier', escapeHtml(s.supplier_name || '-'))}
                    ${detailBox('Mobile', escapeHtml(s.mobile || '-'))}
                    ${detailBox('Email', escapeHtml(s.email || '-'))}
                    ${detailBox('GSTIN', escapeHtml(s.gstin || '-'))}
                    ${detailBox('Opening Outstanding', money.format(parseFloat(summary.opening_balance ?? s.opening_outstanding ?? 0)))}
                    ${detailBox('Calculated Balance', money.format(calculatedBalance))}
                    ${detailBox('DB Current', money.format(dbBalance))}
                    ${detailBox('Status', statusBadge(s.status))}
                </div>
                <div class="mt-3 p-3 rounded-4 border bg-light">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <div class="mp-stat-label mb-1">Address</div>
                            <div>${escapeHtml(s.address || '-')}</div>
                            <div class="mp-sub mt-2">Rule: Opening + Purchase/Credit additions - Payment/Debit/Reversal decreases.</div>
                        </div>
                        ${txnButton}
                    </div>
                </div>
                <h6 class="fw-bold mt-4 mb-2">Purpose-wise Supplier Ledger</h6>
                ${renderLedger(data.ledger || [])}`;

            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            supplierDetailBody.innerHTML = '<div class="text-danger">Unable to fetch supplier details.</div>';
        }
    }

    async function editSupplier(supplierId) {
        try { const data = await apiGet({ action: 'get', supplier_id: supplierId }); if (!data.success) { showMessage('error', data.message || 'Supplier not found.'); return; } fillSupplierForm(data.supplier); } catch (error) { showMessage('error', 'Unable to fetch supplier details.'); }
    }
    async function toggleSupplierStatus(supplierId) {
        if (!confirm('Change supplier status?')) return;
        const formData = new FormData(); formData.append('action', 'toggle_status'); formData.append('supplier_id', supplierId);
        const data = await apiPost(formData); showMessage(data.success ? 'success' : 'error', data.message || 'Status update failed.'); if (data.success) await loadSuppliers();
    }
    async function deleteSupplier(supplierId) {
        if (!confirm('Delete this supplier permanently? Delete is allowed only when the supplier is INACTIVE and the calculated balance is ZERO.')) return;
        const formData = new FormData(); formData.append('action', 'delete_supplier'); formData.append('supplier_id', supplierId);
        const data = await apiPost(formData); showMessage(data.success ? 'success' : 'error', data.message || 'Delete failed.'); if (data.success) await loadSuppliers();
    }
    function openTransactionForSupplier(supplierId) {
        if (!canCreate) { showMessage('error', 'You do not have permission to create supplier transactions.'); return; }
        resetTransactionForm(supplierId || '');
        openSupplierTransactionModal();
    }

    const addBtn = document.getElementById('openSupplierModalBtn');
    if (addBtn) addBtn.addEventListener('click', function () { if (!canCreate) { showMessage('error', 'You do not have permission to create suppliers.'); return; } resetSupplierForm(); openSupplierModal(); });
    const txnBtn = document.getElementById('openTransactionModalBtn');
    if (txnBtn) txnBtn.addEventListener('click', function () { openTransactionForSupplier(''); });
    document.getElementById('resetSupplierPage').addEventListener('click', function () { document.getElementById('search').value = ''; document.getElementById('status').value = ''; resetSupplierForm(); loadSuppliers(); });
    filterForm.addEventListener('submit', function (event) { event.preventDefault(); loadSuppliers(); });
    document.getElementById('search').addEventListener('input', function () { window.clearTimeout(searchTimer); searchTimer = window.setTimeout(loadSuppliers, 300); });
    document.getElementById('status').addEventListener('change', loadSuppliers);
    if (mobileInput) mobileInput.addEventListener('input', function () { this.value = normalizeMobile(this.value); });
    if (gstinInput) gstinInput.addEventListener('input', function () { this.value = normalizeGstin(this.value); });
    document.getElementById('opening_outstanding').addEventListener('input', function () { if (document.getElementById('supplier_id').value === '0') document.getElementById('current_outstanding').value = parseFloat(this.value || 0).toFixed(2); });
    txnPurpose.addEventListener('change', updatePurposePreview);
    txnOtherDirection.addEventListener('change', updatePurposePreview);

    supplierForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!validateSupplierForm()) return;
        const submitBtn = document.getElementById('supplierSubmitBtn');
        const readyText = document.getElementById('supplier_id').value !== '0' ? 'Update Supplier' : 'Save Supplier';
        submitBtn.disabled = true; submitBtn.innerHTML = 'Please wait...';
        try { const formData = new FormData(supplierForm); const data = await apiPost(formData, supplierForm); showMessage(data.success ? 'success' : 'error', data.message || 'Supplier save failed.'); if (data.success) { resetSupplierForm(); closeSupplierModal(); await loadSuppliers(); } } catch (error) { showMessage('error', 'Unable to save supplier.'); } finally { submitBtn.disabled = false; submitBtn.innerHTML = readyText; }
    });

    supplierTransactionForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!validateTransactionForm()) return;
        const submitBtn = document.getElementById('supplierTransactionSubmitBtn');
        submitBtn.disabled = true; submitBtn.innerHTML = 'Saving...';
        try {
            const formData = new FormData(supplierTransactionForm);
            const data = await apiPost(formData, supplierTransactionForm);
            showMessage(data.success ? 'success' : 'error', data.message || 'Transaction save failed.');
            if (data.success) { closeSupplierTransactionModal(); await loadSuppliers(); }
        } catch (error) { showMessage('error', 'Unable to save supplier transaction.'); }
        finally { submitBtn.disabled = false; submitBtn.innerHTML = 'Save Transaction'; }
    });

    document.addEventListener('click', function (event) {
        const viewBtn = event.target.closest('.js-view');
        const editBtn = event.target.closest('.js-edit');
        const toggleBtn = event.target.closest('.js-toggle');
        const deleteBtn = event.target.closest('.js-delete');
        const txnBtn = event.target.closest('.js-txn');

        if (viewBtn) {
            event.preventDefault();
            viewSupplier(viewBtn.dataset.id);
        }

        if (editBtn) {
            event.preventDefault();
            editSupplier(editBtn.dataset.id);
        }

        if (toggleBtn) {
            event.preventDefault();
            toggleSupplierStatus(toggleBtn.dataset.id);
        }

        if (deleteBtn) {
            event.preventDefault();

            if (deleteBtn.dataset.deleteEligible !== '1') {
                showMessage(
                    'warning',
                    deleteBtn.dataset.deleteReason ||
                    'Delete is allowed only for inactive suppliers with zero calculated balance.'
                );
                return;
            }

            deleteSupplier(deleteBtn.dataset.id);
        }

        if (txnBtn) {
            event.preventDefault();
            openTransactionForSupplier(txnBtn.dataset.id);
        }
    });

    loadSuppliers();
})();
</script>
</body>
</html>
