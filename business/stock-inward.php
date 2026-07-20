<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'stock-inward.php');

$pageTitle = 'Stock Inward';
$businessId = (int) current_business_id();
$today = (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');

function stock_inward_csrf_field(): string
{
    if (function_exists('csrf_field')) {
        return csrf_field();
    }
    if (function_exists('csrf_token')) {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }
    return '';
}

function stock_inward_page_permissions(mysqli $conn, string $pageUrl): array
{
    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true, 'can_export' => true];
    }
    if (!function_exists('table_has_column') || !function_exists('current_role_id')) {
        return ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true, 'can_export' => true];
    }

    $businessId = (int) current_business_id();
    $roleId = (int) current_role_id();
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
        return ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true, 'can_export' => true];
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

$permissions = stock_inward_page_permissions($conn, 'stock-inward.php');

if ($businessId <= 0) {
    die('Business session missing. Please login again.');
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
        .master-page { font-family: "Inter", "Segoe UI", Arial, sans-serif; font-size: 12px; font-weight: 500; }
        .mp-hero { background: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 16px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); padding: 14px 16px; }
        .mp-hero h1 { font-size: 20px; font-weight: 800; margin: 0 0 3px; letter-spacing: -.02em; color: var(--text-main); }
        .mp-hero p { font-size: 11px; line-height: 1.35; margin: 0; color: var(--text-muted); font-weight: 500; }
        .mp-hero .btn { font-size: 11px; padding: 7px 11px; min-height: 32px; border-radius: 999px; font-weight: 700; }
        .mp-stat-card { min-height: 72px; background: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 15px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); padding: 11px 12px; display: flex; align-items: center; gap: 10px; }
        .mp-stat-icon { width: 40px; height: 40px; border-radius: 13px; display: grid; place-items: center; color: #fff; flex: 0 0 auto; }
        .mp-stat-icon svg { width: 17px; height: 17px; }
        .mp-stat-label { font-size: 10.5px; color: var(--text-muted); font-weight: 700; line-height: 1.15; }
        .mp-stat-value { font-size: 18px; color: var(--text-main); font-weight: 800; margin: 1px 0; line-height: 1.05; }
        .mp-stat-sub { font-size: 10px; color: var(--text-muted); font-weight: 550; line-height: 1.15; }
        .mp-card { background: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 16px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); overflow: hidden; }
        .mp-card-head { padding: 12px 14px; border-bottom: 1px solid var(--border-soft); }
        .mp-card-title { font-size: 15px; font-weight: 800; color: var(--text-main); margin: 0 0 2px; }
        .mp-card-sub { font-size: 11px; color: var(--text-muted); margin: 0; }
        .mp-filter { background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%); padding: 12px 14px; border-bottom: 1px solid var(--border-soft); }
        .mp-filter .form-label { font-size: 10px; margin-bottom: 3px; color: #475569; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .mp-filter .form-control, .mp-filter .form-select { min-height: 34px; font-size: 11.5px; border-radius: 12px; padding: 6px 9px; }
        .mp-table th { font-size: 10px; font-weight: 750; padding: 9px 10px; white-space: nowrap; background: #f1f5f9; color: #0f172a; text-transform: uppercase; }
        .mp-table td { font-size: 11px; padding: 9px 10px; vertical-align: middle; }
        .mp-table tbody tr:hover { background: #f8fafc; }
        .mp-avatar { width: 34px; height: 34px; border-radius: 12px; display: grid; place-items: center; background: linear-gradient(135deg, var(--brand-1), var(--brand-2)); color: #fff; font-size: 13px; font-weight: 800; flex: 0 0 auto; }
        .mp-title { font-size: 12px; font-weight: 800; color: var(--text-main); line-height: 1.2; }
        .mp-sub { font-size: 10px; color: var(--text-muted); line-height: 1.25; }
        .mp-badge { border-radius: 999px; padding: 5px 8px; font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
        .status-active { background: #dcfce7; color: #15803d; }
        .status-inactive { background: #fee2e2; color: #b91c1c; }
        .badge-code { background: #dbeafe; color: #1d4ed8; }
        .badge-count { background: #fef3c7; color: #b45309; }
        .badge-type { background: #ede9fe; color: #6d28d9; }
        .mp-action-btn { border-radius: 999px; font-size: 10.5px; font-weight: 700; padding: 5px 8px; display: inline-flex; align-items: center; gap: 4px; line-height: 1; }
        .barcode-print-btn { border-radius: 999px; font-size: 10px; font-weight: 800; padding: 5px 8px; display: inline-flex; align-items: center; justify-content: center; gap: 4px; line-height: 1; color:#047857; border:1px solid #a7f3d0; background:#ecfdf5; text-decoration:none; white-space:nowrap; }
        .barcode-print-btn:hover { color:#ffffff; background:#059669; border-color:#059669; text-decoration:none; }
        .barcode-print-btn svg { width:13px; height:13px; stroke-width:2.4; }

        .stock-action-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(68px, 1fr));
            gap: 5px;
            width: 150px;
        }

        .stock-action-grid .mp-action-btn,
        .stock-action-grid .barcode-print-btn {
            width: 100%;
            min-height: 29px;
            justify-content: center;
            text-align: center;
            margin: 0;
        }

        @media (max-width: 767px) {
            .stock-action-grid {
                width: 100%;
                max-width: 220px;
                grid-template-columns: repeat(2, minmax(82px, 1fr));
            }
        }

        .mp-mobile-card { background: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 14px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); padding: 10px; }
        .modal-title { font-size: 15px; font-weight: 750; }
        .modal .form-label { font-size: 11px; font-weight: 700; margin-bottom: 4px; }
        .modal .form-control, .modal .form-select { min-height: 34px; font-size: 12px; border-radius: 12px; padding: 6px 10px; }
        .modal-footer .btn { font-size: 12px; padding: 7px 12px; border-radius: 12px; font-weight: 700; }
        .stock-modal-xl { max-width: 1200px; }
        .stock-line-card { border: 1px solid var(--border-soft); border-radius: 14px; background: #fff; padding: 10px; box-shadow: 0 6px 16px rgba(15, 23, 42, .04); }
        .stock-line-card .form-label { font-size: 9.5px; line-height: 1; text-transform: uppercase; letter-spacing: .04em; color: #475569; font-weight: 800; }
        .stock-line-card .form-control, .stock-line-card .form-select { min-height: 32px; height: 32px; font-size: 11.5px; padding: 5px 8px; border-radius: 11px; }
        .stock-summary-box { border: 1px solid var(--border-soft); border-radius: 14px; background: #f8fafc; padding: 8px 10px; display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 11px; color: #64748b; }
        .stock-summary-box b { color: #0f172a; font-size: 13px; }
        .batch-rule { border: 1px solid #67e8f9; background: #ecfeff; color: #155e75; border-radius: 14px; padding: 9px 11px; font-size: 11px; font-weight: 700; }
        .detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .detail-box { background: #f8fafc; border: 1px solid var(--border-soft); border-radius: 14px; padding: 10px; }
        .detail-label { font-size: 10px; color: #64748b; font-weight: 800; text-transform: uppercase; }
        .detail-value { font-size: 12px; color: #0f172a; font-weight: 800; margin-top: 2px; }
        @media (max-width: 767px) { .mp-hero { padding: 12px; } .mp-hero h1 { font-size: 19px; } .mp-stat-card { min-height: 64px; padding: 9px 10px; } .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; } .mp-stat-value { font-size: 16px; } .stock-modal-xl { max-width: calc(100vw - 16px); margin: 8px auto; } .detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
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
                        <h1>Stock Inward</h1>
                        <p>Firm-wise stock entry with purchase date, supplier invoice, barcode, and vendor ledger posting.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="stock-list.php" class="btn btn-outline-primary"><i data-lucide="boxes" style="width:14px;height:14px;"></i> Stock List</a>
                        <?php if ($permissions['can_create']): ?>
                        <button type="button" class="btn brand-gradient" id="addStockBtn"><i data-lucide="plus" style="width:14px;height:14px;"></i> Add Stock Inward</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="package-plus"></i></div><div><div class="mp-stat-label">Total Batches</div><div class="mp-stat-value" id="totalBatches">0</div><div class="mp-stat-sub">Batch-wise inward</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="list-checks"></i></div><div><div class="mp-stat-label">Total Items</div><div class="mp-stat-value" id="totalItems">0</div><div class="mp-stat-sub">Line items</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="layers"></i></div><div><div class="mp-stat-label">Total Quantity</div><div class="mp-stat-value" id="totalQty">0.00</div><div class="mp-stat-sub">Available stock entries</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);"><i data-lucide="indian-rupee"></i></div><div><div class="mp-stat-label">Total Purchase Value</div><div class="mp-stat-value" id="stockValue">₹0.00</div><div class="mp-stat-sub">Supplier payable value</div></div></article></div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column flex-md-row justify-content-md-between gap-2">
                        <div><h2 class="mp-card-title">Stock Inward List</h2><p class="mp-card-sub">Every inward entry creates its own batch. Same article is not merged with older stock.</p></div>
                        <button type="button" class="btn btn-light btn-sm rounded-pill fw-bold" id="resetFilterBtn"><i data-lucide="refresh-cw" style="width:13px;height:13px;"></i> Reset</button>
                    </div>
                </div>

                <div class="mp-filter">
                    <form id="stockFilterForm" class="row g-2 align-items-end">
                        <div class="col-12 col-md-3"><label class="form-label">Search</label><input type="text" id="search" class="form-control" placeholder="Batch / supplier / article"></div>
                        <div class="col-6 col-md-2"><label class="form-label">Branch / Firm</label><select id="filterBranch" class="form-select"><option value="">All</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label">Supplier</label><select id="filterSupplier" class="form-select"><option value="">All</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label">Brand</label><select id="filterBrand" class="form-select"><option value="">All</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label">Category</label><select id="filterCategory" class="form-select"><option value="">All</option></select></div>
                        <div class="col-6 col-md-1"><label class="form-label">Status</label><select id="filterStatus" class="form-select"><option value="active">Active</option><option value="cancelled">Cancelled</option><option value="deleted">Deleted</option><option value="">All</option></select></div>
                        <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" id="dateFrom" class="form-control"></div>
                        <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" id="dateTo" class="form-control"></div>
                        <div class="col-6 col-md-2"><button class="btn btn-dark w-100 rounded-pill fw-bold" type="submit">Filter</button></div>
                    </form>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table mb-0">
                        <thead><tr><th>Batch Number</th><th>Stock Entry Date</th><th>Branch / Firm</th><th>Supplier</th><th>Total Items</th><th>Total Quantity</th><th>Purchase Value</th><th>Created By</th><th>Status</th><th style="width:175px;">Actions</th></tr></thead>
                        <tbody id="stockTableBody"><tr><td colspan="10" class="text-center text-muted py-4">Loading stock inward batches...</td></tr></tbody>
                    </table>
                </div>
                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="stockMobileCards"><div class="mp-mobile-card text-center text-muted">Loading stock inward batches...</div></div>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<div class="modal fade" id="stockModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog stock-modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content" id="stockForm" autocomplete="off">
            <?= stock_inward_csrf_field() ?>
            <input type="hidden" name="action" value="save_stock_inward">
            <input type="hidden" name="batch_id" id="batchId" value="0">
            <input type="hidden" name="items_json" id="itemsJson">
            <div class="modal-header">
                <div><h5 class="modal-title" id="stockModalTitle">Add Stock Inward</h5><div class="mp-sub">Business ID is linked automatically. Stock entry date is saved with the batch and linked to every item. Batch number and barcodes auto-generate on save.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3"><label class="form-label">Business ID</label><input type="text" class="form-control" value="<?= (int)$businessId ?>" readonly></div>
                    <div class="col-12 col-md-3"><label class="form-label">Branch / Firm <span class="text-danger">*</span></label><select name="branch_id" id="branchId" class="form-select" required></select></div>
                    <div class="col-12 col-md-3"><label class="form-label">Stock Entry Date <span class="text-danger">*</span></label><input type="date" name="inward_date" id="inwardDate" class="form-control" max="<?= e($today) ?>" value="<?= e($today) ?>" required></div>
                    <div class="col-12 col-md-3"><label class="form-label">Batch Number</label><input type="text" id="batchNoPreview" class="form-control" value="Auto Generate" readonly></div>
                    <div class="col-12 col-md-3"><label class="form-label">Supplier <span class="text-danger">*</span></label><select name="supplier_id" id="supplierId" class="form-select" required></select></div>
                    <div class="col-12 col-md-3"><label class="form-label">Invoice Number</label><input type="text" name="invoice_number" id="invoiceNumber" class="form-control" maxlength="80" placeholder="Supplier invoice no"></div>
                    <div class="col-12 col-md-3"><label class="form-label">Invoice Date</label><input type="date" name="invoice_date" id="invoiceDate" class="form-control" max="<?= e($today) ?>"></div>
                    <div class="col-12 col-md-3"><label class="form-label">Remarks</label><input type="text" name="remarks" id="remarks" class="form-control" maxlength="1000" placeholder="Optional remarks"></div>
                </div>

                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                    <div><h6 class="fw-bold mb-0">Product Entry Section</h6><div class="mp-sub">Selling Rate = MRP - Product Discount. Add multiple products in the same batch.</div></div>
                    <button type="button" class="btn btn-light btn-sm rounded-pill fw-bold" id="addItemBtn"><i data-lucide="plus" style="width:13px;height:13px;"></i> Add Item</button>
                </div>
                <div id="itemRows" class="d-grid gap-2"></div>

                <div class="row g-2 mt-3">
                    <div class="col-6 col-lg-3"><div class="stock-summary-box"><span>Total Items</span><b id="summaryItems">0</b></div></div>
                    <div class="col-6 col-lg-3"><div class="stock-summary-box"><span>Total Qty</span><b id="summaryQty">0.00</b></div></div>
                    <div class="col-6 col-lg-3"><div class="stock-summary-box"><span>Purchase Value</span><b id="summaryPurchase">₹0.00</b></div></div>
                    <div class="col-6 col-lg-3"><div class="stock-summary-box"><span>Selling Value</span><b id="summarySelling">₹0.00</b></div></div>
                </div>
                <div class="batch-rule mt-3">Batch rule: Same article can be entered multiple times. On save, the total purchase value is automatically posted to the selected supplier account.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="submit" id="saveStockBtn" class="btn brand-gradient">Save Stock Inward</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Stock Inward Batch View</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="viewBatchBody"><div class="text-center text-muted py-4">Loading...</div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>


<div class="modal fade" id="barcodeDesignerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Barcode Label Settings</h5>
                    <div class="mp-sub">Adjust label size, rows, columns, spacing and label content before printing.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="barcode-designer-layout">
                    <aside class="barcode-settings-panel">
                        <div class="barcode-settings-scroll">
                            <div class="barcode-setting-card">
                                <div class="barcode-setting-title">Label Size & Grid</div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">Width (mm)</label><input type="number" id="bdLabelWidth" class="form-control" value="50" min="15" max="150" step="0.5"></div>
                                    <div class="col-6"><label class="form-label">Height (mm)</label><input type="number" id="bdLabelHeight" class="form-control" value="30" min="10" max="100" step="0.5"></div>
                                    <div class="col-6"><label class="form-label">Columns</label><input type="number" id="bdColumns" class="form-control" value="3" min="1" max="8"></div>
                                    <div class="col-6"><label class="form-label">Rows / Page</label><input type="number" id="bdRows" class="form-control" value="8" min="1" max="30"></div>
                                    <div class="col-6"><label class="form-label">Horizontal Gap</label><input type="number" id="bdGapX" class="form-control" value="2" min="0" max="20" step="0.5"></div>
                                    <div class="col-6"><label class="form-label">Vertical Gap</label><input type="number" id="bdGapY" class="form-control" value="2" min="0" max="20" step="0.5"></div>
                                    <div class="col-6"><label class="form-label">Side Margin</label><input type="number" id="bdMarginX" class="form-control" value="2" min="0" max="20" step="0.5"></div>
                                    <div class="col-6"><label class="form-label">Top Margin</label><input type="number" id="bdMarginY" class="form-control" value="2" min="0" max="20" step="0.5"></div>
                                </div>
                            </div>

                            <div class="barcode-setting-card">
                                <div class="barcode-setting-title">Barcode & Quantity</div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">Bar Width</label><input type="number" id="bdBarWidth" class="form-control" value="1.7" min="1" max="4" step="0.1"></div>
                                    <div class="col-6"><label class="form-label">Quantity Mode</label>
                                        <select id="bdQtyMode" class="form-select">
                                            <option value="custom">Custom Qty</option>
                                            <option value="available">Available Stock</option>
                                            <option value="one">One Each</option>
                                        </select>
                                    </div>
                                    <div class="col-6"><label class="form-label">Label Qty / Product</label><input type="number" id="bdDefaultQty" class="form-control" value="1" min="1" max="10000"></div>
                                    <div class="col-6"><label class="form-label">Start Position</label><input type="number" id="bdStartPosition" class="form-control" value="1" min="1" max="500"></div>
                                    <div class="col-12"><label class="form-label">Company Name</label><input type="text" id="bdCompanyName" class="form-control" value="GK FOOTWEAR"></div>
                                    <div class="col-6"><label class="form-label">Price Prefix</label><input type="text" id="bdPricePrefix" class="form-control" value="MRP ₹"></div>
                                    <div class="col-6"><label class="form-label">Price Source</label><select id="bdPriceSource" class="form-select"><option value="mrp_rate">MRP</option><option value="selling_rate">Selling</option></select></div>
                                    <div class="col-12"><label class="form-label">Other Details</label><input type="text" id="bdOtherTemplate" class="form-control" value="{brand} | {category} | {color}"></div>
                                </div>
                            </div>

                            <div class="barcode-setting-card">
                                <div class="barcode-setting-title">Label Print Options</div>
                                <div class="row g-2">
                                    <div class="col-12"><label class="form-label">Label Preset</label>
                                        <select id="bdLabelPreset" class="form-select">
                                            <option value="custom">Custom Size</option>
                                            <option value="50x30" selected>50 × 30 mm</option>
                                            <option value="38x25">38 × 25 mm</option>
                                            <option value="40x20">40 × 20 mm</option>
                                            <option value="75x50">75 × 50 mm</option>
                                            <option value="100x50">100 × 50 mm</option>
                                        </select>
                                    </div>
                                    <div class="col-6"><label class="form-label">Orientation</label>
                                        <select id="bdOrientation" class="form-select">
                                            <option value="portrait">Portrait</option>
                                            <option value="landscape">Landscape</option>
                                        </select>
                                    </div>
                                    <div class="col-6"><label class="form-label">Label Border</label>
                                        <select id="bdBorderMode" class="form-select">
                                            <option value="guide">Cut Guide</option>
                                            <option value="solid">Solid Border</option>
                                            <option value="none">No Border</option>
                                        </select>
                                    </div>
                                    <div class="col-6"><label class="form-label">Print Scale (%)</label><input type="number" id="bdPrintScale" class="form-control" value="100" min="50" max="150" step="1"></div>
                                    <div class="col-6"><label class="form-label">Barcode Height (%)</label><input type="number" id="bdBarcodeHeight" class="form-control" value="36" min="10" max="70" step="1"></div>
                                    <div class="col-12">
                                        <label class="d-flex align-items-center gap-2 border rounded-3 bg-white p-2">
                                            <input type="checkbox" id="bdRepeatProducts" checked>
                                            <span class="fw-bold">Apply selected quantity to every product</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="barcode-setting-card">
                                <div class="barcode-setting-title">Visible Sections</div>
                                <div class="barcode-toggle-grid">
                                    <label><input type="checkbox" data-bd-toggle="company" checked> Company</label>
                                    <label><input type="checkbox" data-bd-toggle="product" checked> Product</label>
                                    <label><input type="checkbox" data-bd-toggle="barcode" checked> Barcode</label>
                                    <label><input type="checkbox" data-bd-toggle="number" checked> Barcode No</label>
                                    <label><input type="checkbox" data-bd-toggle="price" checked> Price</label>
                                    <label><input type="checkbox" data-bd-toggle="size" checked> Size</label>
                                    <label><input type="checkbox" data-bd-toggle="other" checked> Other Details</label>
                                </div>
                            </div>

                            <div class="barcode-setting-card">
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <div class="barcode-setting-title mb-0">Multi Section Manager</div>
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill" id="bdAddCustomSection">
                                        <i data-lucide="plus" style="width:13px;height:13px;"></i> Add Section
                                    </button>
                                </div>
                                <div class="mp-sub mb-2">Create any number of custom text sections inside the label.</div>
                                <div id="bdCustomSectionList" class="barcode-custom-list">
                                    <div class="barcode-editor-note">No custom sections added.</div>
                                </div>
                            </div>

                            <div class="barcode-setting-card">
                                <div class="barcode-setting-title">Section Position</div>
                                <div class="mp-sub mb-2">Click a section in the first label, then adjust its position and size.</div>
                                <div id="bdEditorEmpty" class="barcode-editor-note">Select a label section.</div>
                                <div id="bdSectionEditor" class="row g-2" style="display:none;">
                                    <div class="col-12"><label class="form-label">Section</label><input type="text" id="bdSectionName" class="form-control" readonly></div>
                                    <div class="col-12" id="bdCustomContentWrap" style="display:none;">
                                        <label class="form-label">Custom Content</label>
                                        <textarea id="bdSectionContent" class="form-control" rows="2" placeholder="Example: Offer: {selling} | Code: {article}"></textarea>
                                        <div class="mp-sub mt-1">Available placeholders: {company}, {product}, {article}, {barcode}, {mrp}, {selling}, {size}, {color}, {brand}, {category}</div>
                                    </div>
                                    <div class="col-6"><label class="form-label">X (%)</label><input type="number" id="bdSectionX" class="form-control" step="0.1"></div>
                                    <div class="col-6"><label class="form-label">Y (%)</label><input type="number" id="bdSectionY" class="form-control" step="0.1"></div>
                                    <div class="col-6"><label class="form-label">Width (%)</label><input type="number" id="bdSectionW" class="form-control" step="0.1"></div>
                                    <div class="col-6"><label class="form-label">Height (%)</label><input type="number" id="bdSectionH" class="form-control" step="0.1"></div>
                                    <div class="col-6"><label class="form-label">Font (px)</label><input type="number" id="bdSectionFont" class="form-control" step="0.5"></div>
                                    <div class="col-6"><label class="form-label">Align</label><select id="bdSectionAlign" class="form-select"><option value="left">Left</option><option value="center">Center</option><option value="right">Right</option></select></div>
                                    <div class="col-6"><button type="button" id="bdDuplicateSection" class="btn btn-outline-primary w-100">Duplicate</button></div>
                                    <div class="col-6"><button type="button" id="bdDeleteSection" class="btn btn-outline-danger w-100">Delete</button></div>
                                </div>
                            </div>
                        </div>
                    </aside>

                    <section class="barcode-preview-panel">
                        <div class="barcode-preview-toolbar">
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="mp-badge badge-code" id="bdProductCount">0 Products</span>
                                <span class="mp-badge badge-count" id="bdLabelCount">0 Labels</span>
                                <span class="mp-badge badge-type" id="bdPageCount">0 Pages</span>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="bdResetLayout">Reset Layout</button>
                                <button type="button" class="btn btn-outline-primary btn-sm" id="bdSaveLayout">Save Layout</button>
                                <button type="button" class="btn btn-dark btn-sm" id="bdGenerateLabels">Generate Labels</button>
                                <button type="button" class="btn btn-success btn-sm" id="bdPrintLabels"><i data-lucide="printer" style="width:14px;height:14px;"></i> Print Labels</button>
                            </div>
                        </div>
                        <div class="barcode-preview-canvas">
                            <div id="bdPrintSheet" class="bd-print-sheet">
                                <div class="text-center text-muted py-5">Select a batch or product to generate barcode labels.</div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.barcode-designer-layout{height:calc(100vh - 58px);display:grid;grid-template-columns:350px minmax(0,1fr);background:#eef3fb}
.barcode-settings-panel{background:#fff;border-right:1px solid #dbe4f0;overflow:hidden}
.barcode-settings-scroll{height:100%;overflow:auto;padding:12px;display:grid;gap:12px}
.barcode-setting-card{border:1px solid #dbe4f0;border-radius:14px;background:#f8fafc;padding:11px}
.barcode-setting-title{font-size:12px;font-weight:900;margin-bottom:9px}
.barcode-toggle-grid{display:grid;grid-template-columns:1fr 1fr;gap:7px}
.barcode-toggle-grid label{display:flex;align-items:center;gap:6px;border:1px solid #dbe4f0;background:#fff;border-radius:10px;padding:7px;font-size:10.5px;font-weight:750}
.barcode-editor-note{font-size:10px;color:#64748b;border:1px dashed #cbd5e1;background:#fff;border-radius:10px;padding:8px}
.barcode-preview-panel{min-width:0;display:flex;flex-direction:column}
.barcode-preview-toolbar{background:#fff;border-bottom:1px solid #dbe4f0;padding:10px 12px;display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}
.barcode-preview-canvas{flex:1;min-height:0;overflow:auto;background:#cbd5e1;padding:18px}
.bd-print-sheet{--bd-lw:50mm;--bd-lh:30mm;--bd-gx:2mm;--bd-gy:2mm;--bd-mx:2mm;--bd-my:2mm;--bd-cols:3;display:grid;grid-template-columns:repeat(var(--bd-cols),var(--bd-lw));grid-auto-rows:var(--bd-lh);gap:var(--bd-gy) var(--bd-gx);width:max-content;min-width:100%;padding:var(--bd-my) var(--bd-mx);background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.18)}
.bd-label{position:relative;width:var(--bd-lw);height:var(--bd-lh);overflow:hidden;background:#fff;border:1px dashed #94a3b8;break-inside:avoid;page-break-inside:avoid}
.bd-label.bd-border-solid{border-style:solid;border-color:#111827}
.bd-label.bd-border-none{border-color:transparent}
.bd-label.bd-placeholder{background:#f8fafc;border-color:#cbd5e1}
.bd-label.bd-placeholder::after{content:'Start';position:absolute;inset:0;display:grid;place-items:center;color:#94a3b8;font-size:8px;font-weight:800}

.bd-section{position:absolute;display:flex;align-items:center;justify-content:center;text-align:center;overflow:hidden;line-height:1.05;padding:1mm;border:1px dashed transparent;cursor:pointer}
.bd-label.bd-design .bd-section:hover,.bd-label.bd-design .bd-section.selected{border-color:#2563eb;background:rgba(219,234,254,.2)}
.bd-barcode svg{width:100%;height:100%;display:block}
.bd-company,.bd-product,.bd-price,.bd-size,.bd-number,.bd-other{font-weight:850}
.bd-custom{font-weight:750}
.barcode-custom-list{display:grid;gap:7px}
.barcode-custom-item{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:6px;align-items:center;border:1px solid #dbe4f0;background:#fff;border-radius:10px;padding:7px}
.barcode-custom-item button{border:0;border-radius:8px;width:28px;height:28px;display:grid;place-items:center;font-size:12px;font-weight:900}
.barcode-custom-edit{background:#dbeafe;color:#1d4ed8}
.barcode-custom-delete{background:#fee2e2;color:#b91c1c}
.barcode-custom-meta{font-size:9px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

@media(max-width:991px){.barcode-designer-layout{grid-template-columns:1fr;height:auto}.barcode-settings-panel{border-right:0;border-bottom:1px solid #dbe4f0}.barcode-settings-scroll{max-height:45vh}}
@media print{
 body *{visibility:hidden!important}
 #bdPrintSheet,#bdPrintSheet *{visibility:visible!important}
 #bdPrintSheet{position:absolute;left:0;top:0;box-shadow:none;min-width:0}
 .bd-label{border:0}
 .bd-section{border:0!important;background:transparent!important}
 @page{margin:0}
}
</style>

<?php include __DIR__ . '/includes/script.php'; ?>
<script>
(function () {
    'use strict';

    const apiUrl = 'api/stock-inward-api.php';
    const permissions = <?= json_encode($permissions) ?>;
    const today = '<?= e($today) ?>';
    const stockForm = document.getElementById('stockForm');
    const stockModalEl = document.getElementById('stockModal');
    const viewModalEl = document.getElementById('viewModal');
    let stockModal = null;
    let viewModal = null;
    let masters = { branches: [], suppliers: [], categories: [], brands: [], discount_types: [] };
    let itemIndex = 0;
    let searchTimer = null;

    const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 });

    function escapeHtml(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function showToast(type, message) {
        if (window.AppToast && typeof window.AppToast.show === 'function') {
            window.AppToast.show(type, message);
            return;
        }
        alert(message);
    }

    function getStockModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!stockModal) stockModal = new window.bootstrap.Modal(stockModalEl, { backdrop: 'static' });
            return stockModal;
        }
        return null;
    }

    function getViewModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!viewModal) viewModal = new window.bootstrap.Modal(viewModalEl);
            return viewModal;
        }
        return null;
    }

    function openModal() { const m = getStockModal(); if (m) m.show(); }
    function closeModal() { const m = getStockModal(); if (m) m.hide(); }
    function openViewModal() { const m = getViewModal(); if (m) m.show(); }

    function csrfAppend(formData) {
        const csrfInput = stockForm.querySelector('input[type="hidden"][name*="csrf"], input[type="hidden"][name="_token"]');
        if (csrfInput && !formData.has(csrfInput.name)) formData.append(csrfInput.name, csrfInput.value);
    }

    async function apiGet(params) {
        const query = new URLSearchParams(params);
        const response = await fetch(apiUrl + '?' + query.toString(), { headers: { 'Accept': 'application/json' } });
        return response.json();
    }

    async function apiPost(formData) {
        csrfAppend(formData);
        const response = await fetch(apiUrl, { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
        return response.json();
    }

    function optionHtml(items, valueKey, labelKey, selectedValue, emptyLabel) {
        let html = emptyLabel ? '<option value="">' + escapeHtml(emptyLabel) + '</option>' : '';
        html += items.map(item => {
            const value = item[valueKey];
            const selected = String(value) === String(selectedValue || '') ? 'selected' : '';
            return '<option value="' + escapeHtml(value) + '" ' + selected + '>' + escapeHtml(item[labelKey] || '-') + '</option>';
        }).join('');
        return html;
    }

    function branchLabel(branch) {
        return [branch.branch_name, branch.floor_name].filter(Boolean).join(' - ') || ('Branch #' + branch.branch_id);
    }

    function populateMasters() {
        document.getElementById('filterBranch').innerHTML = '<option value="">All</option>' + masters.branches.map(b => '<option value="' + b.branch_id + '">' + escapeHtml(branchLabel(b)) + '</option>').join('');
        document.getElementById('filterSupplier').innerHTML = optionHtml(masters.suppliers, 'supplier_id', 'supplier_name', '', 'All');
        document.getElementById('filterBrand').innerHTML = optionHtml(masters.brands, 'brand_id', 'brand_name', '', 'All');
        document.getElementById('filterCategory').innerHTML = optionHtml(masters.categories, 'category_id', 'category_name', '', 'All');
        document.getElementById('branchId').innerHTML = '<option value="">Select Branch / Firm</option>' + masters.branches.map(b => '<option value="' + b.branch_id + '">' + escapeHtml(branchLabel(b)) + '</option>').join('');
        document.getElementById('supplierId').innerHTML = optionHtml(masters.suppliers, 'supplier_id', 'supplier_name', '', 'Select Supplier');
    }

    function statusBadge(status) {
        if (status === 'active') return '<span class="mp-badge status-active">Active</span>';
        return '<span class="mp-badge status-inactive">' + escapeHtml(status || 'Inactive') + '</span>';
    }

    function renderStats(stats) {
        document.getElementById('totalBatches').textContent = parseInt(stats.total_batches || 0, 10);
        document.getElementById('totalItems').textContent = parseInt(stats.total_items || 0, 10);
        document.getElementById('totalQty').textContent = parseFloat(stats.total_qty || 0).toFixed(2);
        document.getElementById('stockValue').textContent = money.format(parseFloat(stats.purchase_total || 0));
    }

    function renderBatches(batches) {
        const tableBody = document.getElementById('stockTableBody');
        const mobileCards = document.getElementById('stockMobileCards');
        if (!batches.length) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No stock inward batches found.</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No stock inward batches found.</div>';
            return;
        }

        tableBody.innerHTML = batches.map(batch => {
            const value = money.format(parseFloat(batch.purchase_total_value || 0));
            const qty = parseFloat(batch.total_qty || 0).toFixed(2);
            const actions = actionButtons(batch.batch_id, batch.batch_status);
            return `<tr>
                <td><div class="d-flex align-items-center gap-2"><div class="mp-avatar">${escapeHtml((batch.batch_no || 'B').substring(0, 1))}</div><div><div class="mp-title">${escapeHtml(batch.batch_no)}</div><div class="mp-sub">ID: ${escapeHtml(batch.batch_id)}</div></div></div></td>
                <td>${formatDate(batch.inward_date)}</td>
                <td>${escapeHtml(batch.branch_name || '-')}</td>
                <td>${escapeHtml(batch.supplier_name || '-')}</td>
                <td><span class="mp-badge badge-count">${parseInt(batch.item_count || 0, 10)} Items</span></td>
                <td>${qty}</td>
                <td><b>${value}</b></td>
                <td>${escapeHtml(batch.created_by_name || '-')}</td>
                <td>${statusBadge(batch.batch_status)}</td>
                <td>${actions}</td>
            </tr>`;
        }).join('');

        mobileCards.innerHTML = batches.map(batch => `
            <div class="mp-mobile-card">
                <div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${escapeHtml(batch.batch_no)}</div><div class="mp-sub">${formatDate(batch.inward_date)} · ${escapeHtml(batch.branch_name || '-')}</div></div>${statusBadge(batch.batch_status)}</div>
                <div class="small text-muted-custom mt-2">Supplier: ${escapeHtml(batch.supplier_name || '-')}</div>
                <div class="fw-bold mt-2">${parseFloat(batch.total_qty || 0).toFixed(2)} Qty · ${money.format(parseFloat(batch.purchase_total_value || 0))}</div>
                <div class="d-flex flex-wrap gap-2 mt-3">${actionButtons(batch.batch_id, batch.batch_status)}</div>
            </div>`).join('');

        if (window.lucide) window.lucide.createIcons();
    }

    function barcodePrintButton(item) {
        const stockItemId = parseInt(item.stock_item_id || 0, 10);
        const availableQty = Math.max(0, Math.floor(parseFloat(item.available_qty || item.qty || 0)));

        if (!stockItemId || availableQty <= 0) {
            return '<span class="mp-sub">No stock</span>';
        }

        return '<button type="button" class="barcode-print-btn js-barcode-item"' +
            ' data-stock-item-id="' + stockItemId + '"' +
            ' data-qty="' + availableQty + '"' +
            ' data-barcode="' + escapeHtml(item.barcode_value || '') + '"' +
            ' data-article-no="' + escapeHtml(item.article_no || '') + '"' +
            ' data-product-name="' + escapeHtml(item.article_name || item.article_no || '') + '"' +
            ' data-category="' + escapeHtml(item.category_name || '') + '"' +
            ' data-brand="' + escapeHtml(item.brand_name || '') + '"' +
            ' data-size="' + escapeHtml(item.size || '') + '"' +
            ' data-color="' + escapeHtml(item.color || '') + '"' +
            ' data-mrp="' + parseFloat(item.mrp_rate || 0).toFixed(2) + '"' +
            ' data-selling="' + parseFloat(item.selling_rate || 0).toFixed(2) + '"' +
            ' title="Open barcode label settings">' +
            '<i data-lucide="barcode"></i> Print Barcode</button>';
    }

    function actionButtons(batchId, status) {
        let buttons = '';

        buttons += '<button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-view" data-id="' + batchId + '">' +
            '<i data-lucide="eye" style="width:12px;height:12px;"></i> View</button>';

        if (permissions.can_print) {
            buttons += '<button type="button" class="barcode-print-btn js-barcode-batch" data-id="' + batchId + '" title="Open barcode label settings">' +
                '<i data-lucide="barcode"></i> Barcode</button>';

            buttons += '<button type="button" class="btn btn-sm btn-outline-dark mp-action-btn js-print" data-id="' + batchId + '">' +
                '<i data-lucide="printer" style="width:12px;height:12px;"></i> Print</button>';
        }

        return '<div class="stock-action-grid">' + buttons + '</div>';
    }

    function formatDate(value) {
        if (!value) return '-';
        const parts = String(value).split('-');
        if (parts.length === 3) return parts[2] + '-' + parts[1] + '-' + parts[0];
        return escapeHtml(value);
    }

    async function loadMasters() {
        const data = await apiGet({ action: 'masters' });
        if (data.success) {
            masters = data.masters || masters;
            populateMasters();
        }
    }

    async function loadBatches() {
        document.getElementById('stockTableBody').innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Loading stock inward batches...</td></tr>';
        const data = await apiGet({
            action: 'list',
            search: document.getElementById('search').value,
            branch_id: document.getElementById('filterBranch').value,
            supplier_id: document.getElementById('filterSupplier').value,
            brand_id: document.getElementById('filterBrand').value,
            category_id: document.getElementById('filterCategory').value,
            status: document.getElementById('filterStatus').value,
            date_from: document.getElementById('dateFrom').value,
            date_to: document.getElementById('dateTo').value
        });
        if (!data.success) {
            showToast('error', data.message || 'Unable to load stock inward list.');
            return;
        }
        renderStats(data.stats || {});
        renderBatches(data.batches || []);
    }

    function resetForm() {
        stockForm.reset();
        document.getElementById('batchId').value = '0';
        document.getElementById('batchNoPreview').value = 'Auto Generate';
        document.getElementById('inwardDate').value = today;
        document.getElementById('invoiceDate').value = '';
        document.getElementById('stockModalTitle').textContent = 'Add Stock Inward';
        itemIndex = 0;
        document.getElementById('itemRows').innerHTML = '';
        addItemRow();
        calculateSummary();
    }

    function addItemRow(item) {
        itemIndex++;
        const data = item || {};
        const row = document.createElement('div');
        row.className = 'stock-line-card';
        row.innerHTML = `
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-2"><label class="form-label">Category</label><select class="form-select js-category" required>${optionHtml(masters.categories, 'category_id', 'category_name', data.category_id, 'Category')}</select></div>
                <div class="col-12 col-md-2"><label class="form-label">Brand</label><select class="form-select js-brand" required>${optionHtml(masters.brands, 'brand_id', 'brand_name', data.brand_id, 'Brand')}</select></div>
                <div class="col-6 col-md-1"><label class="form-label">Article No *</label><input type="text" class="form-control js-article-no" maxlength="100" value="${escapeHtml(data.article_no || '')}" required></div>
                <div class="col-6 col-md-2"><label class="form-label">Article Name</label><input type="text" class="form-control js-article-name" maxlength="200" value="${escapeHtml(data.article_name || '')}"></div>
                <div class="col-6 col-md-1"><label class="form-label">Size</label><input type="text" class="form-control js-size" maxlength="50" value="${escapeHtml(data.size || '')}" required></div>
                <div class="col-6 col-md-1"><label class="form-label">Color</label><input type="text" class="form-control js-color" maxlength="80" value="${escapeHtml(data.color || '')}"></div>
                <div class="col-6 col-md-1"><label class="form-label">Qty *</label><input type="number" class="form-control js-qty" step="0.01" min="0.01" value="${escapeHtml(data.qty || '1.00')}" required></div>
                <div class="col-6 col-md-1"><label class="form-label">Purchase *</label><input type="number" class="form-control js-purchase" step="0.01" min="0.01" value="${escapeHtml(data.purchase_rate || '0.00')}" required></div>
                <div class="col-6 col-md-1"><label class="form-label">MRP *</label><input type="number" class="form-control js-mrp" step="0.01" min="0.01" value="${escapeHtml(data.mrp_rate || '0.00')}" required></div>
                <div class="col-6 col-md-1"><label class="form-label">Disc Type</label><select class="form-select js-discount-type"><option value="none">None</option><option value="percent">Percent</option><option value="amount">Amount</option></select></div>
                <div class="col-6 col-md-1"><label class="form-label">Disc</label><input type="number" class="form-control js-discount-value" step="0.01" min="0" value="${escapeHtml(data.product_discount_value || '0.00')}"></div>
                <div class="col-6 col-md-1"><label class="form-label">Selling</label><input type="number" class="form-control js-selling" readonly value="${escapeHtml(data.selling_rate || '0.00')}"></div>
                <div class="col-6 col-md-1"><label class="form-label">Barcode</label><div class="form-check mt-1"><input class="form-check-input js-barcode" type="checkbox" ${parseInt(data.barcode_required || 1, 10) === 1 ? 'checked' : ''}></div></div>
                <div class="col-6 col-md-1"><button type="button" class="btn btn-danger btn-sm rounded-pill js-remove-item w-100">Remove</button></div>
            </div>`;
        document.getElementById('itemRows').appendChild(row);
        row.querySelector('.js-discount-type').value = data.product_discount_type || 'none';
        row.querySelectorAll('input, select').forEach(el => el.addEventListener('input', calculateSummary));
        row.querySelector('.js-remove-item').addEventListener('click', function () {
            if (document.querySelectorAll('.stock-line-card').length <= 1) {
                showToast('warning', 'At least one item is required.');
                return;
            }
            row.remove(); calculateSummary();
        });
        calculateSummary();
    }

    function getItemDataFromRow(row, index) {
        const qty = parseFloat(row.querySelector('.js-qty').value || '0');
        const purchase = parseFloat(row.querySelector('.js-purchase').value || '0');
        const mrp = parseFloat(row.querySelector('.js-mrp').value || '0');
        const discountType = row.querySelector('.js-discount-type').value || 'none';
        let discountValue = parseFloat(row.querySelector('.js-discount-value').value || '0');
        let discountAmount = 0;
        if (discountType === 'percent') discountAmount = (mrp * discountValue) / 100;
        if (discountType === 'amount') discountAmount = discountValue;
        if (discountType === 'none') discountValue = 0;
        const selling = Math.max(0, mrp - discountAmount);
        row.querySelector('.js-selling').value = selling.toFixed(2);
        return {
            row_no: index + 1,
            category_id: row.querySelector('.js-category').value,
            brand_id: row.querySelector('.js-brand').value,
            article_no: row.querySelector('.js-article-no').value.trim(),
            article_name: row.querySelector('.js-article-name').value.trim(),
            size: row.querySelector('.js-size').value.trim(),
            color: row.querySelector('.js-color').value.trim(),
            qty: qty,
            purchase_rate: purchase,
            mrp_rate: mrp,
            product_discount_type: discountType,
            product_discount_value: discountValue,
            selling_rate: selling,
            barcode_required: row.querySelector('.js-barcode').checked ? 1 : 0
        };
    }

    function collectItems() {
        return Array.from(document.querySelectorAll('.stock-line-card')).map(getItemDataFromRow);
    }

    function calculateSummary() {
        const items = collectItems();
        let qty = 0, purchase = 0, selling = 0;
        items.forEach(item => { qty += item.qty || 0; purchase += (item.qty || 0) * (item.purchase_rate || 0); selling += (item.qty || 0) * (item.selling_rate || 0); });
        document.getElementById('summaryItems').textContent = items.length;
        document.getElementById('summaryQty').textContent = qty.toFixed(2);
        document.getElementById('summaryPurchase').textContent = money.format(purchase);
        document.getElementById('summarySelling').textContent = money.format(selling);
    }

    function validateForm() {
        if (!document.getElementById('branchId').value) { showToast('error', 'Branch / Firm is required.'); return false; }
        if (!document.getElementById('supplierId').value) { showToast('error', 'Supplier is required.'); return false; }
        const inwardDate = document.getElementById('inwardDate').value;
        if (!inwardDate) { showToast('error', 'Inward date is required.'); return false; }
        if (inwardDate > today) { showToast('error', 'Inward date cannot be a future date.'); return false; }
        const invoiceDate = document.getElementById('invoiceDate').value;
        if (invoiceDate && invoiceDate > today) { showToast('error', 'Invoice date cannot be a future date.'); return false; }
        const items = collectItems();
        for (let i = 0; i < items.length; i++) {
            const item = items[i];
            if (!item.category_id) { showToast('error', 'Row ' + item.row_no + ': Category is required.'); return false; }
            if (!item.brand_id) { showToast('error', 'Row ' + item.row_no + ': Brand is required.'); return false; }
            if (!item.article_no) { showToast('error', 'Row ' + item.row_no + ': Article number is required.'); return false; }
            if (!item.size) { showToast('error', 'Row ' + item.row_no + ': Size is required.'); return false; }
            if (item.qty <= 0) { showToast('error', 'Row ' + item.row_no + ': Quantity must be greater than zero.'); return false; }
            if (item.purchase_rate <= 0) { showToast('error', 'Row ' + item.row_no + ': Purchase rate must be greater than zero.'); return false; }
            if (item.mrp_rate < item.purchase_rate) { showToast('error', 'Row ' + item.row_no + ': MRP must be greater than or equal to purchase rate.'); return false; }
            if (item.selling_rate <= 0) { showToast('error', 'Row ' + item.row_no + ': Selling rate must be greater than zero.'); return false; }
        }
        document.getElementById('itemsJson').value = JSON.stringify(items);
        return true;
    }


    async function viewBatch(batchId) {
        document.getElementById('viewBatchBody').innerHTML = '<div class="text-center text-muted py-4">Loading...</div>';
        openViewModal();
        const data = await apiGet({ action: 'get', batch_id: batchId });
        if (!data.success) { document.getElementById('viewBatchBody').innerHTML = '<div class="text-danger">' + escapeHtml(data.message || 'Unable to load batch.') + '</div>'; return; }
        const b = data.batch;
        const itemRows = (b.items || []).map(item => `<tr><td>${escapeHtml(item.category_name || '-')}</td><td>${escapeHtml(item.brand_name || '-')}</td><td>${escapeHtml(item.article_no || '-')}</td><td>${escapeHtml(item.article_name || '-')}</td><td>${escapeHtml(item.size || '-')}</td><td>${escapeHtml(item.color || '-')}</td><td>${parseFloat(item.available_qty || item.qty || 0).toFixed(2)}</td><td>${money.format(parseFloat(item.purchase_rate || 0))}</td><td>${money.format(parseFloat(item.mrp_rate || 0))}</td><td>${money.format(parseFloat(item.selling_rate || 0))}</td><td>${escapeHtml(item.barcode_value || 'Auto')}</td><td>${barcodePrintButton(item)}</td></tr>`).join('');
        document.getElementById('viewBatchBody').innerHTML = `
            <div class="detail-grid mb-3">
                <div class="detail-box"><div class="detail-label">Batch Number</div><div class="detail-value">${escapeHtml(b.batch_no)}</div></div>
                <div class="detail-box"><div class="detail-label">Stock Entry Date</div><div class="detail-value">${formatDate(b.inward_date)}</div></div>
                <div class="detail-box"><div class="detail-label">Branch Details</div><div class="detail-value">${escapeHtml(b.branch_name || '-')}</div></div>
                <div class="detail-box"><div class="detail-label">Supplier Details</div><div class="detail-value">${escapeHtml(b.supplier_name || '-')}</div><div class="mp-sub">${escapeHtml(b.supplier_mobile || '')} ${escapeHtml(b.supplier_gstin || '')}</div></div>
                <div class="detail-box"><div class="detail-label">Invoice</div><div class="detail-value">${escapeHtml(b.invoice_number || '-')}</div><div class="mp-sub">${formatDate(b.invoice_date)}</div></div>
                <div class="detail-box"><div class="detail-label">Total Quantity</div><div class="detail-value">${parseFloat(b.total_qty || 0).toFixed(2)}</div></div>
                <div class="detail-box"><div class="detail-label">Purchase Value</div><div class="detail-value">${money.format(parseFloat(b.purchase_total_value || 0))}</div></div>
                <div class="detail-box"><div class="detail-label">Created By</div><div class="detail-value">${escapeHtml(b.created_by_name || '-')}</div></div>
            </div>
            <div class="table-responsive"><table class="table mp-table"><thead><tr><th>Category</th><th>Brand</th><th>Article No</th><th>Article Name</th><th>Size</th><th>Color</th><th>Available Qty</th><th>Purchase</th><th>MRP</th><th>Selling</th><th>Barcode</th><th>Action</th></tr></thead><tbody>${itemRows}</tbody></table></div>`;
        if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
    }



    let barcodeDesignerModal = null;
    let bdItems = [];
    let bdLabels = [];

    const bdPrintPresets = {
        '50x30': { width:50, height:30, columns:3, rows:8 },
        '38x25': { width:38, height:25, columns:4, rows:10 },
        '40x20': { width:40, height:20, columns:4, rows:12 },
        '75x50': { width:75, height:50, columns:2, rows:5 },
        '100x50': { width:100, height:50, columns:2, rows:5 }
    };
    let bdSelectedSection = '';

    const bdDefaults = {
        company:{x:3,y:2,w:94,h:12,font:10,align:'center'},
        product:{x:3,y:14,w:94,h:13,font:9,align:'center'},
        barcode:{x:6,y:28,w:88,h:36,font:8,align:'center'},
        number:{x:3,y:64,w:94,h:9,font:8,align:'center'},
        price:{x:3,y:74,w:45,h:18,font:11,align:'left'},
        size:{x:52,y:74,w:45,h:18,font:9,align:'right'},
        other:{x:3,y:91,w:94,h:7,font:7.5,align:'center'}
    };

    let bdTemplate = JSON.parse(localStorage.getItem('gk_stock_barcode_template') || 'null') || JSON.parse(JSON.stringify(bdDefaults));
    let bdCustomSections = JSON.parse(localStorage.getItem('gk_stock_barcode_custom_sections') || 'null') || [];
    let bdCustomCounter = bdCustomSections.reduce((max, section) => {
        const match = String(section.key || '').match(/custom_(\d+)/);
        return Math.max(max, match ? parseInt(match[1], 10) : 0);
    }, 0);

    function getBarcodeDesignerModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!barcodeDesignerModal) barcodeDesignerModal = new window.bootstrap.Modal(document.getElementById('barcodeDesignerModal'));
            return barcodeDesignerModal;
        }
        return null;
    }

    function bdNum(v) {
        const n = parseFloat(v || 0);
        return Number.isNaN(n) ? 0 : n;
    }

    function bdNormalize(item) {
        return {
            stock_item_id: parseInt(item.stock_item_id || 0, 10),
            barcode_value: String(item.barcode_value || item.article_no || ''),
            article_no: String(item.article_no || ''),
            article_name: String(item.article_name || item.product_name || item.article_no || ''),
            category_name: String(item.category_name || ''),
            brand_name: String(item.brand_name || ''),
            size: String(item.size || ''),
            color: String(item.color || ''),
            mrp_rate: bdNum(item.mrp_rate || item.mrp),
            selling_rate: bdNum(item.selling_rate || item.selling),
            available_qty: Math.max(0, Math.floor(bdNum(item.available_qty || item.qty || 0)))
        };
    }

    function bdShowModal() {
        const m = getBarcodeDesignerModal();
        if (m) m.show();
        setTimeout(bdRenderLabels, 100);
    }

    async function openBatchBarcodeDesigner(batchId) {
        const data = await apiGet({ action: 'get', batch_id: batchId });
        if (!data.success) {
            showToast('error', data.message || 'Unable to load batch items.');
            return;
        }
        bdItems = ((data.batch && data.batch.items) || []).map(bdNormalize).filter(item => item.available_qty > 0);
        document.getElementById('bdDefaultQty').value = '1';
        bdShowModal();
    }

    function openItemBarcodeDesigner(button) {
        bdItems = [bdNormalize({
            stock_item_id: button.dataset.stockItemId,
            barcode_value: button.dataset.barcode,
            article_no: button.dataset.articleNo,
            article_name: button.dataset.productName,
            category_name: button.dataset.category,
            brand_name: button.dataset.brand,
            size: button.dataset.size,
            color: button.dataset.color,
            mrp_rate: button.dataset.mrp,
            selling_rate: button.dataset.selling,
            available_qty: button.dataset.qty
        })];
        if (document.getElementById('bdQtyMode').value !== 'custom') {
            document.getElementById('bdDefaultQty').value = '1';
        }
        bdShowModal();
    }

    const bdPatterns = ["11011001100","11001101100","11001100110","10010011000","10010001100","10001001100","10011001000","10011000100","10001100100","11001001000","11001000100","11000100100","10110011100","10011011100","10011001110","10111001100","10011101100","10011100110","11001110010","11001011100","11001001110","11011100100","11001110100","11101101110","11101001100","11100101100","11100100110","11101100100","11100110100","11100110010","11011011000","11011000110","11000110110","10100011000","10001011000","10001000110","10110001000","10001101000","10001100010","11010001000","11000101000","11000100010","10110111000","10110001110","10001101110","10111011000","10111000110","10001110110","11101110110","11010001110","11000101110","11011101000","11011100010","11011101110","11101011000","11101000110","11100010110","11101101000","11101100010","11100011010","11101111010","11001000010","11110001010","10100110000","10100001100","10010110000","10010000110","10000101100","10000100110","10110010000","10110000100","10011010000","10011000010","10000110100","10000110010","11000010010","11001010000","11110111010","11000010100","10001111010","10100111100","10010111100","10010011110","10111100100","10011110100","10011110010","11110100100","11110010100","11110010010","11011011110","11011110110","11110110110","10101111000","10100011110","10001011110","10111101000","10111100010","11110101000","11110100010","10111011110","10111101110","11101011110","11110101110","11010000100","11010010000","11010011100","1100011101011"];

    function bdBarcodeSvg(value) {
        let text = '';
        for (const ch of String(value || '')) {
            const code = ch.charCodeAt(0);
            if (code >= 32 && code <= 126) text += ch;
        }
        if (!text) text = '000000000000';

        const values = [104, ...[...text].map(ch => ch.charCodeAt(0) - 32)];
        let checksum = 104;
        for (let i = 1; i < values.length; i++) checksum += values[i] * i;
        values.push(checksum % 103, 106);

        const moduleWidth = Math.max(1, bdNum(document.getElementById('bdBarWidth').value || 1.7));
        const bits = values.map(v => bdPatterns[v]).join('');
        let rects = '', active = false, start = 0;

        for (let i = 0; i <= bits.length; i++) {
            const bit = bits[i] === '1';
            if (bit && !active) { active = true; start = i; }
            if ((!bit || i === bits.length) && active) {
                rects += '<rect x="' + (start * moduleWidth) + '" y="0" width="' + ((i - start) * moduleWidth) + '" height="100"></rect>';
                active = false;
            }
        }
        return '<svg viewBox="0 0 ' + (bits.length * moduleWidth) + ' 100" preserveAspectRatio="none">' + rects + '</svg>';
    }

    function bdVisible(key) {
        const input = document.querySelector('[data-bd-toggle="' + key + '"]');
        return !input || input.checked;
    }

    function bdGetSection(key) {
        if (bdTemplate[key]) return bdTemplate[key];
        return bdCustomSections.find(section => section.key === key) || null;
    }

    function bdSectionStyle(key) {
        const s = bdGetSection(key);
        if (!s) return 'display:none';
        return [
            'left:' + s.x + '%',
            'top:' + s.y + '%',
            'width:' + s.w + '%',
            'height:' + s.h + '%',
            'font-size:' + s.font + 'px',
            'justify-content:' + (s.align === 'left' ? 'flex-start' : (s.align === 'right' ? 'flex-end' : 'center')),
            'text-align:' + s.align,
            bdVisible(key) ? '' : 'display:none'
        ].join(';');
    }

    function bdOther(item) {
        return String(document.getElementById('bdOtherTemplate').value || '')
            .replaceAll('{brand}', item.brand_name)
            .replaceAll('{category}', item.category_name)
            .replaceAll('{color}', item.color)
            .replaceAll('{article}', item.article_no)
            .replaceAll('{size}', item.size);
    }

    function bdFormatCustomContent(section, item) {
        return String(section.content || '')
            .replaceAll('{company}', document.getElementById('bdCompanyName').value || 'GK FOOTWEAR')
            .replaceAll('{product}', item.article_name || item.article_no || '')
            .replaceAll('{article}', item.article_no || '')
            .replaceAll('{barcode}', item.barcode_value || '')
            .replaceAll('{mrp}', bdNum(item.mrp_rate).toFixed(2))
            .replaceAll('{selling}', bdNum(item.selling_rate).toFixed(2))
            .replaceAll('{size}', item.size || '')
            .replaceAll('{color}', item.color || '')
            .replaceAll('{brand}', item.brand_name || '')
            .replaceAll('{category}', item.category_name || '');
    }

    function bdCustomSectionsHtml(item) {
        return bdCustomSections.map(section =>
            '<div class="bd-section bd-custom" data-key="' + escapeHtml(section.key) + '" style="' + bdSectionStyle(section.key) + '">' +
                escapeHtml(bdFormatCustomContent(section, item)) +
            '</div>'
        ).join('');
    }

    function bdLabelHtml(item, index) {
        const priceSource = document.getElementById('bdPriceSource').value || 'mrp_rate';
        return '<article class="bd-label ' + (index === 0 ? 'bd-design' : '') + '">' +
            '<div class="bd-section bd-company" data-key="company" style="' + bdSectionStyle('company') + '">' + escapeHtml(document.getElementById('bdCompanyName').value || 'GK FOOTWEAR') + '</div>' +
            '<div class="bd-section bd-product" data-key="product" style="' + bdSectionStyle('product') + '">' + escapeHtml(item.article_name || item.article_no) + '</div>' +
            '<div class="bd-section bd-barcode" data-key="barcode" style="' + bdSectionStyle('barcode') + '">' + bdBarcodeSvg(item.barcode_value) + '</div>' +
            '<div class="bd-section bd-number" data-key="number" style="' + bdSectionStyle('number') + '">' + escapeHtml(item.barcode_value) + '</div>' +
            '<div class="bd-section bd-price" data-key="price" style="' + bdSectionStyle('price') + '">' + escapeHtml(document.getElementById('bdPricePrefix').value || 'MRP ₹') + escapeHtml(bdNum(item[priceSource]).toFixed(2)) + '</div>' +
            '<div class="bd-section bd-size" data-key="size" style="' + bdSectionStyle('size') + '">Size: ' + escapeHtml(item.size || '-') + '</div>' +
            '<div class="bd-section bd-other" data-key="other" style="' + bdSectionStyle('other') + '">' + escapeHtml(bdOther(item)) + '</div>' +
            bdCustomSectionsHtml(item) +
        '</article>';
    }


    function bdRenderCustomSectionList() {
        const list = document.getElementById('bdCustomSectionList');
        if (!bdCustomSections.length) {
            list.innerHTML = '<div class="barcode-editor-note">No custom sections added.</div>';
            return;
        }

        list.innerHTML = bdCustomSections.map(section =>
            '<div class="barcode-custom-item">' +
                '<div>' +
                    '<div class="mp-title">' + escapeHtml(section.name || section.key) + '</div>' +
                    '<div class="barcode-custom-meta">' + escapeHtml(section.content || 'Empty content') + '</div>' +
                '</div>' +
                '<button type="button" class="barcode-custom-edit" data-edit-custom="' + escapeHtml(section.key) + '" title="Edit">✎</button>' +
                '<button type="button" class="barcode-custom-delete" data-delete-custom="' + escapeHtml(section.key) + '" title="Delete">×</button>' +
            '</div>'
        ).join('');
    }

    function bdAddCustomSection(sourceSection) {
        bdCustomCounter++;
        const key = 'custom_' + bdCustomCounter;
        const section = sourceSection ? {
            ...sourceSection,
            key:key,
            name:(sourceSection.name || 'Custom Section') + ' Copy',
            x:Math.min(95, bdNum(sourceSection.x) + 2),
            y:Math.min(95, bdNum(sourceSection.y) + 2)
        } : {
            key:key,
            name:'Custom Section ' + bdCustomCounter,
            content:'Custom Text',
            x:5,
            y:82,
            w:90,
            h:10,
            font:8,
            align:'center'
        };
        bdCustomSections.push(section);
        bdRenderCustomSectionList();
        bdRenderLabels();
        bdSelectSection(key);
    }

    function bdDeleteCustomSection(key) {
        const index = bdCustomSections.findIndex(section => section.key === key);
        if (index < 0) return;
        bdCustomSections.splice(index, 1);
        if (bdSelectedSection === key) {
            bdSelectedSection = '';
            document.getElementById('bdSectionEditor').style.display = 'none';
            document.getElementById('bdEditorEmpty').style.display = 'block';
        }
        bdRenderCustomSectionList();
        bdRenderLabels();
    }

    function bdRenderLabels() {
        const sheet = document.getElementById('bdPrintSheet');
        const width = bdNum(document.getElementById('bdLabelWidth').value || 50);
        const height = bdNum(document.getElementById('bdLabelHeight').value || 30);
        const columns = Math.max(1, parseInt(document.getElementById('bdColumns').value || 1, 10));
        const rows = Math.max(1, parseInt(document.getElementById('bdRows').value || 1, 10));

        sheet.style.setProperty('--bd-lw', width + 'mm');
        sheet.style.setProperty('--bd-lh', height + 'mm');
        sheet.style.setProperty('--bd-gx', bdNum(document.getElementById('bdGapX').value || 0) + 'mm');
        sheet.style.setProperty('--bd-gy', bdNum(document.getElementById('bdGapY').value || 0) + 'mm');
        sheet.style.setProperty('--bd-mx', bdNum(document.getElementById('bdMarginX').value || 0) + 'mm');
        sheet.style.setProperty('--bd-my', bdNum(document.getElementById('bdMarginY').value || 0) + 'mm');
        sheet.style.setProperty('--bd-cols', columns);

        const qtyMode = document.getElementById('bdQtyMode').value || 'custom';
        const customQty = Math.max(1, parseInt(document.getElementById('bdDefaultQty').value || 1, 10));
        const repeatProducts = document.getElementById('bdRepeatProducts').checked;
        const startPosition = Math.max(1, parseInt(document.getElementById('bdStartPosition').value || 1, 10));
        const borderMode = document.getElementById('bdBorderMode').value || 'guide';
        const printScale = Math.max(50, Math.min(150, bdNum(document.getElementById('bdPrintScale').value || 100)));
        const barcodeHeight = Math.max(10, Math.min(70, bdNum(document.getElementById('bdBarcodeHeight').value || 36)));

        bdTemplate.barcode.h = barcodeHeight;
        sheet.style.transformOrigin = 'top left';
        sheet.style.transform = 'scale(' + (printScale / 100) + ')';

        bdLabels = [];
        bdItems.forEach((item, itemIndex) => {
            let copies = 1;
            if (qtyMode === 'available') {
                copies = Math.max(1, parseInt(item.available_qty || 1, 10));
            } else if (qtyMode === 'one') {
                copies = 1;
            } else {
                copies = repeatProducts || itemIndex === 0 ? customQty : 1;
            }

            for (let i = 0; i < copies; i++) {
                bdLabels.push(item);
            }
        });

        const placeholders = Array.from({ length: Math.max(0, startPosition - 1) }, () =>
            '<div class="bd-label bd-placeholder ' +
            (borderMode === 'solid' ? 'bd-border-solid' : borderMode === 'none' ? 'bd-border-none' : '') +
            '"></div>'
        ).join('');

        const labelHtml = bdLabels.map(item => {
            let html = bdLabelHtml(item);
            const borderClass = borderMode === 'solid'
                ? 'bd-border-solid'
                : borderMode === 'none'
                    ? 'bd-border-none'
                    : '';
            return html.replace('class="bd-label', 'class="bd-label ' + borderClass);
        }).join('');

        sheet.innerHTML = bdLabels.length
            ? placeholders + labelHtml
            : '<div class="text-center text-muted py-5">No barcode labels available.</div>';

        const occupiedLabels = bdLabels.length + Math.max(0, startPosition - 1);
        document.getElementById('bdProductCount').textContent = bdItems.length + ' Products';
        document.getElementById('bdLabelCount').textContent = bdLabels.length + ' Labels';
        document.getElementById('bdPageCount').textContent = Math.max(1, Math.ceil(occupiedLabels / (columns * rows))) + ' Pages';

        document.querySelectorAll('.bd-label.bd-design .bd-section').forEach(section => {
            section.addEventListener('click', function (event) {
                event.stopPropagation();
                bdSelectSection(section.dataset.key);
            });
        });

        if (window.lucide) window.lucide.createIcons();
    }

    function bdSelectSection(key) {
        bdSelectedSection = key;
        document.querySelectorAll('.bd-section').forEach(el => el.classList.toggle('selected', el.dataset.key === key && el.closest('.bd-design')));
        const s = bdGetSection(key);
        if (!s) return;

        const custom = key.indexOf('custom_') === 0;
        document.getElementById('bdCustomContentWrap').style.display = custom ? 'block' : 'none';
        document.getElementById('bdDeleteSection').style.display = custom ? 'block' : 'none';
        document.getElementById('bdDuplicateSection').style.display = 'block';
        if (custom) document.getElementById('bdSectionContent').value = s.content || '';

        document.getElementById('bdEditorEmpty').style.display = 'none';
        document.getElementById('bdSectionEditor').style.display = 'flex';
        document.getElementById('bdSectionName').value = key;
        document.getElementById('bdSectionX').value = s.x;
        document.getElementById('bdSectionY').value = s.y;
        document.getElementById('bdSectionW').value = s.w;
        document.getElementById('bdSectionH').value = s.h;
        document.getElementById('bdSectionFont').value = s.font;
        document.getElementById('bdSectionAlign').value = s.align;
    }

    function bdUpdateSection() {
        if (!bdSelectedSection) return;
        const s = bdGetSection(bdSelectedSection);
        if (!s) return;
        s.x = Math.max(0, bdNum(document.getElementById('bdSectionX').value));
        s.y = Math.max(0, bdNum(document.getElementById('bdSectionY').value));
        s.w = Math.max(2, bdNum(document.getElementById('bdSectionW').value));
        s.h = Math.max(2, bdNum(document.getElementById('bdSectionH').value));
        s.font = Math.max(5, bdNum(document.getElementById('bdSectionFont').value));
        s.align = document.getElementById('bdSectionAlign').value || 'center';
        if (bdSelectedSection.indexOf('custom_') === 0) {
            s.content = document.getElementById('bdSectionContent').value || '';
        }
        bdRenderLabels();
        bdSelectSection(bdSelectedSection);
    }

    ['bdLabelWidth','bdLabelHeight','bdColumns','bdRows','bdGapX','bdGapY','bdMarginX','bdMarginY','bdBarWidth','bdQtyMode','bdDefaultQty','bdStartPosition','bdCompanyName','bdPricePrefix','bdPriceSource','bdOtherTemplate','bdOrientation','bdBorderMode','bdPrintScale','bdBarcodeHeight','bdRepeatProducts']
        .forEach(id => {
            document.getElementById(id).addEventListener('input', bdRenderLabels);
            document.getElementById(id).addEventListener('change', bdRenderLabels);
        });

    document.querySelectorAll('[data-bd-toggle]').forEach(el => el.addEventListener('change', bdRenderLabels));

    ['bdSectionX','bdSectionY','bdSectionW','bdSectionH','bdSectionFont','bdSectionAlign','bdSectionContent']
        .forEach(id => {
            document.getElementById(id).addEventListener('input', bdUpdateSection);
            document.getElementById(id).addEventListener('change', bdUpdateSection);
        });

    document.getElementById('bdLabelPreset').addEventListener('change', function () {
        const preset = bdPrintPresets[this.value];
        if (!preset) return;
        document.getElementById('bdLabelWidth').value = preset.width;
        document.getElementById('bdLabelHeight').value = preset.height;
        document.getElementById('bdColumns').value = preset.columns;
        document.getElementById('bdRows').value = preset.rows;
        bdRenderLabels();
    });

    document.getElementById('bdOrientation').addEventListener('change', function () {
        const orientation = this.value;
        const widthInput = document.getElementById('bdLabelWidth');
        const heightInput = document.getElementById('bdLabelHeight');
        const width = bdNum(widthInput.value);
        const height = bdNum(heightInput.value);

        if (orientation === 'landscape' && width < height) {
            widthInput.value = height;
            heightInput.value = width;
        } else if (orientation === 'portrait' && width > height) {
            widthInput.value = height;
            heightInput.value = width;
        }
        bdRenderLabels();
    });

    document.getElementById('bdQtyMode').addEventListener('change', function () {
        const custom = this.value === 'custom';
        document.getElementById('bdDefaultQty').disabled = !custom;
        document.getElementById('bdRepeatProducts').disabled = !custom;
        bdRenderLabels();
    });

    document.getElementById('bdGenerateLabels').addEventListener('click', bdRenderLabels);
    document.getElementById('bdPrintLabels').addEventListener('click', function () {
        document.querySelectorAll('.bd-section').forEach(el => el.classList.remove('selected'));
        window.print();
    });
    document.getElementById('bdResetLayout').addEventListener('click', function () {
        bdTemplate = JSON.parse(JSON.stringify(bdDefaults));
        bdCustomSections = [];
        bdCustomCounter = 0;
        localStorage.removeItem('gk_stock_barcode_template');
        localStorage.removeItem('gk_stock_barcode_custom_sections');
        localStorage.removeItem('gk_stock_barcode_print_options');
        bdSelectedSection = '';
        document.getElementById('bdSectionEditor').style.display = 'none';
        document.getElementById('bdEditorEmpty').style.display = 'block';
        bdRenderCustomSectionList();
        bdRenderLabels();
    });
    function bdCollectPrintOptions() {
        const ids = [
            'bdLabelPreset','bdLabelWidth','bdLabelHeight','bdColumns','bdRows',
            'bdGapX','bdGapY','bdMarginX','bdMarginY','bdBarWidth','bdQtyMode',
            'bdDefaultQty','bdStartPosition','bdCompanyName','bdPricePrefix',
            'bdPriceSource','bdOtherTemplate','bdOrientation','bdBorderMode',
            'bdPrintScale','bdBarcodeHeight'
        ];
        const options = {};
        ids.forEach(id => {
            const element = document.getElementById(id);
            if (element) options[id] = element.value;
        });
        options.bdRepeatProducts = document.getElementById('bdRepeatProducts').checked;
        options.toggles = {};
        document.querySelectorAll('[data-bd-toggle]').forEach(element => {
            options.toggles[element.dataset.bdToggle] = element.checked;
        });
        return options;
    }

    function bdRestorePrintOptions() {
        let options = null;
        try {
            options = JSON.parse(localStorage.getItem('gk_stock_barcode_print_options') || 'null');
        } catch (error) {}

        if (!options) return;

        Object.keys(options).forEach(id => {
            if (id === 'toggles' || id === 'bdRepeatProducts') return;
            const element = document.getElementById(id);
            if (element) element.value = options[id];
        });

        if (typeof options.bdRepeatProducts !== 'undefined') {
            document.getElementById('bdRepeatProducts').checked = !!options.bdRepeatProducts;
        }

        Object.keys(options.toggles || {}).forEach(key => {
            const element = document.querySelector('[data-bd-toggle="' + key + '"]');
            if (element) element.checked = !!options.toggles[key];
        });
    }

    document.getElementById('bdSaveLayout').addEventListener('click', function () {
        localStorage.setItem('gk_stock_barcode_template', JSON.stringify(bdTemplate));
        localStorage.setItem('gk_stock_barcode_custom_sections', JSON.stringify(bdCustomSections));
        localStorage.setItem('gk_stock_barcode_print_options', JSON.stringify(bdCollectPrintOptions()));
        showToast('success', 'Barcode layout, label sections and print options saved.');
    });


    document.getElementById('bdAddCustomSection').addEventListener('click', function () {
        bdAddCustomSection();
    });

    document.getElementById('bdDuplicateSection').addEventListener('click', function () {
        if (!bdSelectedSection) return;
        const source = bdGetSection(bdSelectedSection);
        if (!source) return;
        bdAddCustomSection({
            ...source,
            name: bdSelectedSection.indexOf('custom_') === 0 ? (source.name || 'Custom Section') : bdSelectedSection.charAt(0).toUpperCase() + bdSelectedSection.slice(1),
            content: bdSelectedSection.indexOf('custom_') === 0 ? (source.content || '') : '{product}'
        });
    });

    document.getElementById('bdDeleteSection').addEventListener('click', function () {
        if (bdSelectedSection.indexOf('custom_') !== 0) {
            showToast('warning', 'Default sections cannot be deleted. Hide them using Visible Sections.');
            return;
        }
        bdDeleteCustomSection(bdSelectedSection);
    });

    document.getElementById('bdCustomSectionList').addEventListener('click', function (event) {
        const editBtn = event.target.closest('[data-edit-custom]');
        const deleteBtn = event.target.closest('[data-delete-custom]');

        if (editBtn) {
            bdSelectSection(editBtn.dataset.editCustom);
            return;
        }

        if (deleteBtn) {
            bdDeleteCustomSection(deleteBtn.dataset.deleteCustom);
        }
    });

    bdRestorePrintOptions();
    bdRenderCustomSectionList();

    document.getElementById('addStockBtn')?.addEventListener('click', function () { resetForm(); openModal(); });
    document.getElementById('addItemBtn').addEventListener('click', function () { addItemRow(); });
    document.getElementById('stockFilterForm').addEventListener('submit', function (e) { e.preventDefault(); loadBatches(); });
    document.getElementById('search').addEventListener('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(loadBatches, 350); });
    ['filterBranch','filterSupplier','filterBrand','filterCategory','filterStatus','dateFrom','dateTo'].forEach(id => document.getElementById(id).addEventListener('change', loadBatches));
    document.getElementById('resetFilterBtn').addEventListener('click', function () { document.getElementById('stockFilterForm').reset(); document.getElementById('filterStatus').value = 'active'; loadBatches(); });

    document.addEventListener('click', function (event) {
        const viewBtn = event.target.closest('.js-view');
        const printBtn = event.target.closest('.js-print');
        const barcodeBatchBtn = event.target.closest('.js-barcode-batch');
        const barcodeItemBtn = event.target.closest('.js-barcode-item');

        if (viewBtn) viewBatch(viewBtn.dataset.id);
        if (printBtn) window.open('stock-inward-print.php?batch_id=' + encodeURIComponent(printBtn.dataset.id), '_blank');
        if (barcodeBatchBtn) {
            event.preventDefault();
            openBatchBarcodeDesigner(barcodeBatchBtn.dataset.id);
        }
        if (barcodeItemBtn) {
            event.preventDefault();
            openItemBarcodeDesigner(barcodeItemBtn);
        }
    });

    stockForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        calculateSummary();
        if (!validateForm()) return;
        const btn = document.getElementById('saveStockBtn');
        btn.disabled = true; btn.textContent = 'Please wait...';
        try {
            const fd = new FormData(stockForm);
            const data = await apiPost(fd);
            showToast(data.success ? 'success' : 'error', data.message || 'Stock inward save failed.');
            if (data.success) { closeModal(); loadBatches(); }
        } catch (error) {
            showToast('error', 'Unable to connect to stock inward API.');
        } finally {
            btn.disabled = false; btn.textContent = 'Save Stock Inward';
        }
    });

    Promise.resolve().then(loadMasters).then(loadBatches).then(function () { resetForm(); if (window.lucide) window.lucide.createIcons(); }).catch(function () { showToast('error', 'Unable to initialize stock inward module.'); });
})();
</script>
</body>
</html>
