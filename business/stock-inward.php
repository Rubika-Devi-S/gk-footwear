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
                        <thead><tr><th>Batch Number</th><th>Stock Entry Date</th><th>Branch / Firm</th><th>Supplier</th><th>Total Items</th><th>Total Quantity</th><th>Purchase Value</th><th>Created By</th><th>Status</th><th style="width:210px;">Actions</th></tr></thead>
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

        const url = 'barcode-print.php?stock_item_id=' + encodeURIComponent(stockItemId) + '&qty=' + encodeURIComponent(availableQty);
        return '<a href="' + url + '" target="_blank" rel="noopener" class="barcode-print-btn" title="Print barcode labels">' +
            '<i data-lucide="barcode"></i> Print Barcode</a>';
    }

    function actionButtons(batchId, status) {
        let buttons = '<button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-view" data-id="' + batchId + '">View</button>';
        if (permissions.can_print) buttons += '<button type="button" class="barcode-print-btn js-view" data-id="' + batchId + '" title="Open products to print barcodes"><i data-lucide="barcode"></i> Barcode</button>';
        if (permissions.can_edit && status === 'active') buttons += '<button type="button" class="btn btn-sm btn-outline-warning mp-action-btn js-edit" data-id="' + batchId + '">Edit</button>';
        if (permissions.can_print) buttons += '<button type="button" class="btn btn-sm btn-outline-dark mp-action-btn js-print" data-id="' + batchId + '">Print</button>';
        if (permissions.can_delete && status === 'active') buttons += '<button type="button" class="btn btn-sm btn-outline-danger mp-action-btn js-cancel" data-id="' + batchId + '">Cancel</button>';
        return buttons;
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

    async function editBatch(batchId) {
        const data = await apiGet({ action: 'get', batch_id: batchId });
        if (!data.success) { showToast('error', data.message || 'Stock inward batch not found.'); return; }
        const batch = data.batch;
        resetForm();
        document.getElementById('batchId').value = batch.batch_id || 0;
        document.getElementById('batchNoPreview').value = batch.batch_no || 'Auto Generate';
        document.getElementById('branchId').value = batch.branch_id || '';
        document.getElementById('inwardDate').value = batch.inward_date || today;
        document.getElementById('supplierId').value = batch.supplier_id || '';
        document.getElementById('invoiceNumber').value = batch.invoice_number || '';
        document.getElementById('invoiceDate').value = batch.invoice_date || '';
        document.getElementById('remarks').value = batch.remarks || '';
        document.getElementById('stockModalTitle').textContent = 'Edit Stock Inward';
        document.getElementById('itemRows').innerHTML = '';
        (batch.items || []).forEach(addItemRow);
        openModal();
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

    async function changeStatus(batchId, action, message) {
        if (!confirm(message)) return;
        const fd = new FormData();
        fd.append('action', action);
        fd.append('batch_id', batchId);
        const data = await apiPost(fd);
        showToast(data.success ? 'success' : 'error', data.message || 'Action failed.');
        if (data.success) loadBatches();
    }

    document.getElementById('addStockBtn')?.addEventListener('click', function () { resetForm(); openModal(); });
    document.getElementById('addItemBtn').addEventListener('click', function () { addItemRow(); });
    document.getElementById('stockFilterForm').addEventListener('submit', function (e) { e.preventDefault(); loadBatches(); });
    document.getElementById('search').addEventListener('input', function () { clearTimeout(searchTimer); searchTimer = setTimeout(loadBatches, 350); });
    ['filterBranch','filterSupplier','filterBrand','filterCategory','filterStatus','dateFrom','dateTo'].forEach(id => document.getElementById(id).addEventListener('change', loadBatches));
    document.getElementById('resetFilterBtn').addEventListener('click', function () { document.getElementById('stockFilterForm').reset(); document.getElementById('filterStatus').value = 'active'; loadBatches(); });

    document.addEventListener('click', function (event) {
        const viewBtn = event.target.closest('.js-view');
        const editBtn = event.target.closest('.js-edit');
        const printBtn = event.target.closest('.js-print');
        const cancelBtn = event.target.closest('.js-cancel');
        if (viewBtn) viewBatch(viewBtn.dataset.id);
        if (editBtn) editBatch(editBtn.dataset.id);
        if (printBtn) window.open('stock-inward-print.php?batch_id=' + encodeURIComponent(printBtn.dataset.id), '_blank');
        if (cancelBtn) changeStatus(cancelBtn.dataset.id, 'cancel_stock_inward', 'Cancel this stock inward batch?');
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
