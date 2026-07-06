<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'bill-list.php');

$pageTitle = 'Bill List';
$businessId = (int) current_business_id();


if (!function_exists('bill_list_table_has_column')) {
    function bill_list_table_has_column(mysqli $conn, string $tableName, string $columnName): bool
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

function bill_list_page_permissions(mysqli $conn, string $pageUrl): array
{
    $defaults = [
        'can_view' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => true,
        'can_print' => true,
        'can_export' => true,
    ];

    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return $defaults;
    }

    $businessId = (int) current_business_id();
    $roleId = function_exists('current_role_id') ? (int) current_role_id() : (int)($_SESSION['role_id'] ?? 0);

    if ($businessId <= 0 || $roleId <= 0) {
        return $defaults;
    }

    if (!function_exists('table_exists') || !table_exists($conn, 'business_sidebar_menus') || !table_exists($conn, 'business_role_sidebar_access')) {
        return $defaults;
    }

    $cols = ['can_view'];
    foreach (['can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'] as $col) {
        $cols[] = bill_list_table_has_column($conn, 'business_role_sidebar_access', $col) ? $col : '0 AS ' . $col;
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
        return $defaults;
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

function bill_list_csrf_field(): string
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

$permissions = bill_list_page_permissions($conn, 'bill-list.php');
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>

    <style>
    .master-page,
    .bill-page {
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

    .mp-hero h1 { font-size: 20px; font-weight: 800; margin: 0 0 3px; letter-spacing: -.02em; color: var(--text-main); }
    .mp-hero p { font-size: 11px; line-height: 1.35; margin: 0; color: var(--text-muted); font-weight: 500; }
    .mp-hero .btn { font-size: 11px; padding: 7px 11px; min-height: 32px; border-radius: 999px; font-weight: 700; }

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

    .mp-stat-icon { width: 40px; height: 40px; border-radius: 13px; display: grid; place-items: center; color: #fff; flex: 0 0 auto; }
    .mp-stat-icon svg { width: 17px; height: 17px; }
    .mp-stat-label { font-size: 10.5px; color: var(--text-muted); font-weight: 700; line-height: 1.15; }
    .mp-stat-value { font-size: 18px; color: var(--text-main); font-weight: 800; margin: 1px 0; line-height: 1.05; }
    .mp-stat-sub { font-size: 10px; color: var(--text-muted); font-weight: 550; line-height: 1.15; }

    .mp-card { background: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 16px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); overflow: hidden; }
    .mp-card-head { padding: 12px 14px; border-bottom: 1px solid var(--border-soft); }
    .mp-card-title { font-size: 15px; font-weight: 800; color: var(--text-main); margin: 0 0 2px; }
    .mp-card-sub { font-size: 11px; color: var(--text-muted); margin: 0; }

    .mp-filter-input,
    .mp-filter-select { min-height: 32px; font-size: 11px; border-radius: 999px; padding: 5px 10px; font-weight: 650; }

    .mp-table th { font-size: 10px; font-weight: 750; padding: 9px 10px; white-space: nowrap; background: #f1f5f9; color: #0f172a; text-transform: uppercase; letter-spacing: .04em; border-bottom: 0; }
    .mp-table td { font-size: 11px; padding: 9px 10px; vertical-align: middle; }
    .mp-avatar { width: 34px; height: 34px; border-radius: 12px; display: grid; place-items: center; background: linear-gradient(135deg, var(--brand-1), var(--brand-2)); color: #fff; font-size: 13px; font-weight: 800; flex: 0 0 auto; }
    .mp-title { font-size: 12px; font-weight: 800; color: var(--text-main); line-height: 1.2; }
    .mp-sub { font-size: 10px; color: var(--text-muted); line-height: 1.25; }
    .mp-badge { border-radius: 999px; padding: 5px 8px; font-size: 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .status-active { background: #dcfce7; color: #15803d; }
    .status-inactive { background: #fee2e2; color: #b91c1c; }
    .status-pending { background:#fee2e2; color:#b91c1c; }
    .status-partial { background:#fef3c7; color:#b45309; }
    .status-paid { background:#dcfce7; color:#15803d; }
    .status-cancelled { background:#e2e8f0; color:#475569; }
    .status-deleted { background:#f3f4f6; color:#6b7280; }
    .badge-code { background: #dbeafe; color: #1d4ed8; }
    .badge-count { background: #fef3c7; color: #b45309; }
    .badge-type { background: #ede9fe; color: #6d28d9; }
    .badge-branch { background: #ecfeff; color: #0e7490; }
    .badge-money { background: #fef3c7; color: #b45309; }

    .mp-action-btn { border-radius: 999px; font-size: 10.5px; font-weight: 700; padding: 5px 8px; display: inline-flex; align-items: center; justify-content:center; gap: 4px; line-height: 1; }
    .mp-action-btn svg { width: 13px; height: 13px; }
    .mp-mobile-card { background: var(--card-bg); border: 1px solid var(--border-soft); border-radius: 14px; box-shadow: 0 8px 20px rgba(15, 23, 42, .06); padding: 10px; }

    .bill-detail-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    .bill-detail-box { border: 1px solid var(--border-soft); background: #f8fafc; border-radius: 14px; padding: 10px; }
    .modal-title { font-size: 15px; font-weight: 750; }
    .modal .form-label { font-size: 11px; font-weight: 700; margin-bottom: 4px; }
    .modal .form-control,
    .modal .form-select { min-height: 34px; font-size: 12px; border-radius: 12px; padding: 6px 10px; }
    .modal-footer .btn { font-size: 12px; padding: 7px 12px; border-radius: 12px; font-weight: 700; }

    @media (max-width: 991px) { .bill-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 767px) {
        .mp-hero { padding: 12px; }
        .mp-hero h1 { font-size: 19px; }
        .mp-stat-card { min-height: 64px; padding: 9px 10px; }
        .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; }
        .mp-stat-value { font-size: 16px; }
        .bill-detail-grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) { include __DIR__ . '/includes/common-toast.php'; } ?>
<form id="billListCsrfForm" class="d-none"><?= bill_list_csrf_field() ?></form>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>

        <section class="page-section master-page bill-page p-3 p-lg-3">
            <div class="mp-hero mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div>
                        <h1>Bill List</h1>
                        <p>View, filter, print, cancel, and manage POS bills created from branch-wise stock.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($permissions['can_create']): ?>
                        <a href="bill-create.php" class="btn brand-gradient">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i>
                            Create Bill
                        </a>
                        <?php endif; ?>
                        <button type="button" id="resetBillPage" class="btn btn-outline-primary">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dbeafe;color:#1d4ed8;"><i data-lucide="receipt"></i></div><div><div class="mp-stat-label">Total Bills</div><div class="mp-stat-value" id="totalBills">0</div><div class="mp-stat-sub">Bill records</div></div></article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="indian-rupee"></i></div><div><div class="mp-stat-label">Net Amount</div><div class="mp-stat-value" id="netTotal">₹0.00</div><div class="mp-stat-sub">Active bills</div></div></article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="wallet"></i></div><div><div class="mp-stat-label">Balance</div><div class="mp-stat-value" id="balanceTotal">₹0.00</div><div class="mp-stat-sub">Pending collection</div></div></article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card"><div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="badge-check"></i></div><div><div class="mp-stat-label">Paid / Pending</div><div class="mp-stat-value"><span id="paidBills">0</span> / <span id="pendingBills">0</span></div><div class="mp-stat-sub">Payment status</div></div></article>
                </div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div>
                                <h2 class="mp-card-title">POS Bill List</h2>
                                <p class="mp-card-sub">Bills are shown based on assigned branch / firm access.</p>
                            </div>

                            <form method="get" id="billFilterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                                <input type="text" name="search" id="search" class="form-control mp-filter-input" placeholder="Search bill / order / customer / mobile">
                                <select name="payment_status" id="payment_status" class="form-select mp-filter-select">
                                    <option value="">All Payment</option>
                                    <option value="pending">Pending</option>
                                    <option value="partial">Partial</option>
                                    <option value="paid">Paid</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">Filter</button>
                            </form>
                        </div>

                        <div class="row g-2">
                            <div class="col-12 col-md-6 col-xl-3">
                                <select name="branch_id" id="branch_id" class="form-select mp-filter-select">
                                    <option value="">All Branch/Firm</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <select name="bill_status" id="bill_status" class="form-select mp-filter-select">
                                    <option value="active">Active Bills</option>
                                    <option value="">All Except Deleted</option>
                                    <option value="cancelled">Cancelled Bills</option>
                                    <option value="deleted">Deleted Bills</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <input type="date" name="date_from" id="date_from" class="form-control mp-filter-input">
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <input type="date" name="date_to" id="date_to" class="form-control mp-filter-input">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table mb-0">
                        <thead>
                        <tr>
                            <th>Bill</th>
                            <th>Date / Time</th>
                            <th>Customer</th>
                            <th>Branch</th>
                            <th>Items</th>
                            <th>MRP</th>
                            <th>Discount</th>
                            <th>Net</th>
                            <th>Paid / Balance</th>
                            <th>Status</th>
                            <th style="width: 250px;">Action</th>
                        </tr>
                        </thead>
                        <tbody id="billTableBody">
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">Loading bills...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="billMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading bills...</div>
                </div>

                <div class="px-3 pb-3 d-flex flex-column flex-md-row justify-content-md-between gap-2 align-items-md-center">
                    <div class="mp-sub" id="paginationInfo">Showing 0 bills</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light btn-sm rounded-pill fw-bold" id="prevPage">Previous</button>
                        <button type="button" class="btn btn-light btn-sm rounded-pill fw-bold" id="nextPage">Next</button>
                    </div>
                </div>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<div class="modal fade" id="billDetailModal" tabindex="-1" aria-labelledby="billDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="billDetailTitle">Bill Details</h5>
                    <div class="mp-sub">Bill items, payment details, and barcode information.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="billDetailBody">
                <div class="text-center text-muted py-4">Loading details...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <form class="modal-content" id="reasonForm">
            <div class="modal-header">
                <h5 class="modal-title" id="reasonTitle">Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reasonAction">
                <input type="hidden" id="reasonBillId">
                <label class="form-label">Reason</label>
                <textarea class="form-control" id="reasonText" rows="3" placeholder="Enter reason"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn brand-gradient" id="reasonSubmitBtn">Submit</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>

<script>
(function () {
    'use strict';

    const apiUrl = 'api/bill-list-api.php';
    const canCreate = <?= $permissions['can_create'] ? 'true' : 'false' ?>;
    const canEdit = <?= $permissions['can_edit'] ? 'true' : 'false' ?>;
    const canDelete = <?= $permissions['can_delete'] ? 'true' : 'false' ?>;
    const canPrint = <?= $permissions['can_print'] ? 'true' : 'false' ?>;
    const tableBody = document.getElementById('billTableBody');
    const mobileCards = document.getElementById('billMobileCards');
    const filterForm = document.getElementById('billFilterForm');
    const detailModalEl = document.getElementById('billDetailModal');
    const detailBody = document.getElementById('billDetailBody');
    const reasonModalEl = document.getElementById('reasonModal');
    let detailModal = null;
    let reasonModal = null;
    let searchTimer = null;
    let currentPage = 1;
    let totalPages = 1;

    const money = new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2
    });

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showMessage(type, message) {
        const toastType = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'error');
        if (window.AppToast && typeof window.AppToast.show === 'function') {
            window.AppToast.show(toastType, message);
            return;
        }
        alert(message);
    }

    function csrfAppend(formData) {
        const csrfInput = document.querySelector('#billListCsrfForm input[type="hidden"]');
        if (csrfInput && !formData.has(csrfInput.name)) {
            formData.append(csrfInput.name, csrfInput.value);
        }
    }

    function apiGet(params) {
        return fetch(apiUrl + '?' + new URLSearchParams(params).toString(), {
            headers: { 'Accept': 'application/json' }
        }).then(function (response) { return response.json(); });
    }

    function apiPost(formData) {
        csrfAppend(formData);
        return fetch(apiUrl, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        }).then(function (response) { return response.json(); });
    }

    function fillSelect(id, rows, valueKey, labelCallback, emptyText) {
        const select = document.getElementById(id);
        if (!select) return;
        const current = select.value;
        select.innerHTML = '<option value="">' + escapeHtml(emptyText) + '</option>';
        rows.forEach(function (row) {
            const option = document.createElement('option');
            option.value = row[valueKey] || '';
            option.textContent = labelCallback(row);
            select.appendChild(option);
        });
        select.value = current;
    }

    function billInitial(bill) {
        const text = String(bill.bill_no || 'B').trim();
        return escapeHtml(text.substring(0, 1).toUpperCase() || 'B');
    }

    function statusBadge(status, prefix) {
        const clean = String(status || '-').toLowerCase();
        const cls = ['active', 'pending', 'partial', 'paid', 'cancelled', 'deleted'].includes(clean) ? clean : 'inactive';
        return '<span class="mp-badge status-' + cls + '">' + escapeHtml(prefix ? prefix + ': ' + clean : clean) + '</span>';
    }

    function renderStats(stats) {
        document.getElementById('totalBills').textContent = parseInt(stats.total_bills || 0, 10);
        document.getElementById('netTotal').textContent = money.format(parseFloat(stats.net_total || 0));
        document.getElementById('balanceTotal').textContent = money.format(parseFloat(stats.balance_total || 0));
        document.getElementById('paidBills').textContent = parseInt(stats.paid_bills || 0, 10);
        document.getElementById('pendingBills').textContent = parseInt(stats.pending_bills || 0, 10);
    }

    function filterParams() {
        return {
            action: 'list',
            search: document.getElementById('search').value,
            branch_id: document.getElementById('branch_id').value,
            payment_status: document.getElementById('payment_status').value,
            bill_status: document.getElementById('bill_status').value,
            date_from: document.getElementById('date_from').value,
            date_to: document.getElementById('date_to').value,
            page: currentPage,
            per_page: 20
        };
    }

    function setPagination(pagination) {
        const total = parseInt(pagination.total || 0, 10);
        currentPage = parseInt(pagination.page || 1, 10);
        totalPages = parseInt(pagination.total_pages || 1, 10);
        document.getElementById('paginationInfo').textContent = 'Page ' + currentPage + ' of ' + totalPages + ' · Total ' + total + ' bills';
        document.getElementById('prevPage').disabled = currentPage <= 1;
        document.getElementById('nextPage').disabled = currentPage >= totalPages;
    }

    function renderBills(bills) {
        if (!bills.length) {
            tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">No bills found.</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No bills found.</div>';
            return;
        }

        tableBody.innerHTML = bills.map(function (bill) {
            const billId = parseInt(bill.bill_id || 0, 10);
            const branch = bill.branch_name || bill.floor_name || '-';
            const discount = parseFloat(bill.item_discount_total || 0) + parseFloat(bill.bill_discount_amount || 0) + parseFloat(bill.loyalty_redeem_amount || 0);
            const canManage = String(bill.bill_status || '') !== 'deleted';
            return `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="mp-avatar">${billInitial(bill)}</div>
                            <div>
                                <div class="mp-title">${escapeHtml(bill.bill_no || '-')}</div>
                                <div class="mp-sub">${escapeHtml(bill.order_no || '-')}</div>
                            </div>
                        </div>
                    </td>
                    <td><div class="fw-bold">${escapeHtml(bill.bill_date || '-')}</div><div class="mp-sub">${escapeHtml(bill.bill_time || '-')}</div></td>
                    <td><div class="mp-title">${escapeHtml(bill.customer_name || 'Walk-in Customer')}</div><div class="mp-sub">${escapeHtml(bill.customer_mobile || '-')}</div></td>
                    <td><span class="mp-badge badge-branch">${escapeHtml(branch)}</span></td>
                    <td><span class="mp-badge badge-type">${parseFloat(bill.total_qty || 0).toFixed(2)} Qty</span><div class="mp-sub">${parseInt(bill.item_count || 0, 10)} rows</div></td>
                    <td class="fw-bold">${money.format(parseFloat(bill.mrp_total || 0))}</td>
                    <td><span class="mp-badge badge-money">${money.format(discount)}</span></td>
                    <td class="fw-bold text-success">${money.format(parseFloat(bill.net_amount || 0))}</td>
                    <td><div class="fw-bold">${money.format(parseFloat(bill.paid_amount || 0))}</div><div class="mp-sub">Bal: ${money.format(parseFloat(bill.balance_amount || 0))}</div></td>
                    <td>${statusBadge(bill.payment_status, 'Pay')}<div class="mt-1">${statusBadge(bill.bill_status, 'Bill')}</div></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-view" data-id="${billId}">View</button>
                            ${canPrint ? `<a class="btn btn-sm btn-outline-success mp-action-btn" target="_blank" href="bill-print.php?bill_id=${billId}">Print</a>` : ''}
                            ${canEdit && canManage && bill.bill_status === 'active' ? `<button type="button" class="btn btn-sm btn-outline-warning mp-action-btn js-cancel" data-id="${billId}">Cancel</button>` : ''}
                            ${canDelete && canManage && bill.bill_status !== 'active' ? `<button type="button" class="btn btn-sm btn-outline-danger mp-action-btn js-delete" data-id="${billId}">Delete</button>` : ''}
                        </div>
                    </td>
                </tr>`;
        }).join('');

        mobileCards.innerHTML = bills.map(function (bill) {
            const billId = parseInt(bill.bill_id || 0, 10);
            const branch = bill.branch_name || bill.floor_name || '-';
            const canManage = String(bill.bill_status || '') !== 'deleted';
            return `
                <div class="mp-mobile-card">
                    <div class="d-flex gap-2">
                        <div class="mp-avatar">${billInitial(bill)}</div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="mp-title">${escapeHtml(bill.bill_no || '-')}</div>
                                    <div class="mp-sub">${escapeHtml(bill.customer_name || 'Walk-in Customer')} · ${escapeHtml(branch)}</div>
                                </div>
                                ${statusBadge(bill.payment_status, '')}
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="mp-badge badge-type">${parseFloat(bill.total_qty || 0).toFixed(2)} Qty</span>
                                <span class="mp-badge badge-money">${money.format(parseFloat(bill.net_amount || 0))}</span>
                                ${statusBadge(bill.bill_status, 'Bill')}
                            </div>
                            <div class="mp-sub mt-2">${escapeHtml(bill.bill_date || '-')} ${escapeHtml(bill.bill_time || '')}</div>
                            <div class="fw-bold mt-1">Paid: ${money.format(parseFloat(bill.paid_amount || 0))} · Balance: ${money.format(parseFloat(bill.balance_amount || 0))}</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-view" data-id="${billId}">View</button>
                                ${canPrint ? `<a class="btn btn-sm btn-outline-success mp-action-btn" target="_blank" href="bill-print.php?bill_id=${billId}">Print</a>` : ''}
                                ${canEdit && canManage && bill.bill_status === 'active' ? `<button type="button" class="btn btn-sm btn-outline-warning mp-action-btn js-cancel" data-id="${billId}">Cancel</button>` : ''}
                                ${canDelete && canManage && bill.bill_status !== 'active' ? `<button type="button" class="btn btn-sm btn-outline-danger mp-action-btn js-delete" data-id="${billId}">Delete</button>` : ''}
                            </div>
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    async function loadBills() {
        tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Loading bills...</td></tr>';
        mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">Loading bills...</div>';

        try {
            const data = await apiGet(filterParams());
            if (!data.success) {
                showMessage('error', data.message || 'Unable to load bill list.');
                return;
            }
            renderStats(data.stats || {});
            renderBills((data.bills && data.bills.items) ? data.bills.items : []);
            setPagination((data.bills && data.bills.pagination) ? data.bills.pagination : {});
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showMessage('error', 'Unable to connect to bill list API.');
        }
    }

    async function loadInit() {
        try {
            const data = await apiGet({ action: 'init', bill_status: document.getElementById('bill_status').value, page: 1, per_page: 20 });
            if (!data.success) {
                showMessage('error', data.message || 'Unable to initialize bill list.');
                return;
            }

            fillSelect('branch_id', data.branches || [], 'branch_id', function (row) {
                return (row.branch_name || '-') + (row.floor_name ? ' / ' + row.floor_name : '');
            }, 'All Branch/Firm');
            renderStats(data.stats || {});
            renderBills((data.bills && data.bills.items) ? data.bills.items : []);
            setPagination((data.bills && data.bills.pagination) ? data.bills.pagination : {});
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showMessage('error', 'Unable to initialize bill list page.');
        }
    }

    function getDetailModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!detailModal) detailModal = new window.bootstrap.Modal(detailModalEl);
            return detailModal;
        }
        return null;
    }

    function openDetailModal() {
        const modal = getDetailModal();
        if (modal) modal.show();
    }

    function getReasonModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!reasonModal) reasonModal = new window.bootstrap.Modal(reasonModalEl);
            return reasonModal;
        }
        return null;
    }

    function openReasonModal(action, billId) {
        document.getElementById('reasonAction').value = action;
        document.getElementById('reasonBillId').value = billId;
        document.getElementById('reasonText').value = '';
        document.getElementById('reasonTitle').textContent = action === 'cancel_bill' ? 'Cancel Bill' : 'Delete Bill';
        document.getElementById('reasonSubmitBtn').textContent = action === 'cancel_bill' ? 'Cancel Bill' : 'Delete Bill';
        const modal = getReasonModal();
        if (modal) modal.show();
    }

    function detailBox(label, value) {
        return '<div class="bill-detail-box"><div class="mp-stat-label">' + escapeHtml(label) + '</div><div class="mp-title mt-1">' + value + '</div></div>';
    }

    function renderBillItems(items) {
        if (!items || !items.length) return '<div class="text-muted small">No items found.</div>';
        return `
            <div class="table-responsive mt-3">
                <table class="table mp-table mb-0">
                    <thead><tr><th>Article</th><th>Brand / Size</th><th>Qty</th><th>MRP</th><th>Discount</th><th>Selling</th><th>Amount</th></tr></thead>
                    <tbody>
                        ${items.map(function (item) {
                            return `<tr>
                                <td><div class="mp-title">${escapeHtml(item.article_no || '-')}</div><div class="mp-sub">${escapeHtml(item.article_name || '-')}</div></td>
                                <td>${escapeHtml(item.brand_name || 'No Brand')}<div class="mp-sub">Size: ${escapeHtml(item.size || '-')}</div></td>
                                <td>${parseFloat(item.qty || 0).toFixed(2)}</td>
                                <td>${money.format(parseFloat(item.mrp_rate || 0))}</td>
                                <td>${money.format(parseFloat(item.discount_amount || 0))}<div class="mp-sub">${escapeHtml(item.discount_type || 'none')} ${parseFloat(item.discount_value || 0).toFixed(2)}</div></td>
                                <td>${money.format(parseFloat(item.selling_rate || 0))}</td>
                                <td class="fw-bold">${money.format(parseFloat(item.amount || 0))}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    function renderPayments(payments) {
        if (!payments || !payments.length) return '<div class="text-muted small">No payment entries found.</div>';
        return `
            <div class="table-responsive mt-3">
                <table class="table mp-table mb-0">
                    <thead><tr><th>Method</th><th>Amount</th><th>Reference</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                        ${payments.map(function (p) {
                            return `<tr>
                                <td>${escapeHtml(p.payment_method_name || '-')}</td>
                                <td class="fw-bold">${money.format(parseFloat(p.paid_amount || 0))}</td>
                                <td>${escapeHtml(p.reference_no || '-')}</td>
                                <td>${statusBadge(p.payment_status || '-', '')}</td>
                                <td>${escapeHtml(p.collected_at || '-')}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>`;
    }

    async function viewBill(billId) {
        detailBody.innerHTML = '<div class="text-center text-muted py-4">Loading details...</div>';
        openDetailModal();

        try {
            const data = await apiGet({ action: 'get', bill_id: billId });
            if (!data.success) {
                detailBody.innerHTML = '<div class="text-danger">' + escapeHtml(data.message || 'Unable to load bill details.') + '</div>';
                return;
            }

            const bill = data.bill || {};
            const branch = bill.branch_name || bill.floor_name || '-';
            const barcode = (data.barcodes && data.barcodes.length) ? data.barcodes.map(function (b) { return b.barcode_value; }).join(', ') : 'No bill barcode';
            detailBody.innerHTML = `
                <div class="bill-detail-grid">
                    ${detailBox('Bill No', escapeHtml(bill.bill_no || '-') + '<div class="mp-sub">' + escapeHtml(bill.order_no || '-') + '</div>')}
                    ${detailBox('Customer', escapeHtml(bill.customer_name || 'Walk-in Customer') + '<div class="mp-sub">' + escapeHtml(bill.customer_mobile || '-') + '</div>')}
                    ${detailBox('Branch / Firm', escapeHtml(branch) + '<div class="mp-sub">' + escapeHtml(bill.branch_code || '-') + '</div>')}
                    ${detailBox('Date / User', escapeHtml(bill.bill_date || '-') + ' ' + escapeHtml(bill.bill_time || '') + '<div class="mp-sub">' + escapeHtml(bill.created_by_name || '-') + '</div>')}
                    ${detailBox('MRP Total', money.format(parseFloat(bill.mrp_total || 0)) + '<div class="mp-sub">Savings: ' + money.format(parseFloat(bill.today_savings_amount || 0)) + '</div>')}
                    ${detailBox('Net Amount', money.format(parseFloat(bill.net_amount || 0)) + '<div class="mp-sub">Paid: ' + money.format(parseFloat(bill.paid_amount || 0)) + '</div>')}
                    ${detailBox('Balance', money.format(parseFloat(bill.balance_amount || 0)) + '<div class="mp-sub">' + escapeHtml(bill.payment_status || '-') + '</div>')}
                    ${detailBox('Bill Status', statusBadge(bill.bill_status, '') + '<div class="mp-sub mt-1">Print: ' + escapeHtml(bill.print_count || '0') + '</div>')}
                </div>
                <div class="mt-3 p-3 rounded-4 border bg-light">
                    <div class="mp-stat-label mb-1">Bill Barcode</div>
                    <div class="mp-title">${escapeHtml(barcode)}</div>
                </div>
                <h6 class="fw-bold mt-4 mb-2">Bill Items</h6>
                ${renderBillItems(data.items || [])}
                <h6 class="fw-bold mt-4 mb-2">Payment Details</h6>
                ${renderPayments(data.payments || [])}
            `;
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            detailBody.innerHTML = '<div class="text-danger">Unable to fetch bill details.</div>';
        }
    }

    async function submitReason(action, billId, reason) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('bill_id', billId);
        formData.append('reason', reason || '');

        try {
            const data = await apiPost(formData);
            showMessage(data.success ? 'success' : 'error', data.message || 'Action failed.');
            if (data.success) {
                const modal = getReasonModal();
                if (modal) modal.hide();
                await loadBills();
            }
        } catch (error) {
            showMessage('error', 'Unable to connect to bill action API.');
        }
    }

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        currentPage = 1;
        loadBills();
    });

    ['branch_id','payment_status','bill_status','date_from','date_to'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function () {
                currentPage = 1;
                loadBills();
            });
        }
    });

    document.getElementById('search').addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            currentPage = 1;
            loadBills();
        }, 300);
    });

    document.getElementById('resetBillPage').addEventListener('click', function () {
        ['search','branch_id','payment_status','date_from','date_to'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.getElementById('bill_status').value = 'active';
        currentPage = 1;
        loadBills();
    });

    document.getElementById('prevPage').addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadBills();
        }
    });

    document.getElementById('nextPage').addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            loadBills();
        }
    });

    document.addEventListener('click', function (event) {
        const viewBtn = event.target.closest('.js-view');
        const cancelBtn = event.target.closest('.js-cancel');
        const deleteBtn = event.target.closest('.js-delete');

        if (viewBtn) viewBill(viewBtn.dataset.id);
        if (cancelBtn) openReasonModal('cancel_bill', cancelBtn.dataset.id);
        if (deleteBtn) openReasonModal('delete_bill', deleteBtn.dataset.id);
    });

    document.getElementById('reasonForm').addEventListener('submit', function (event) {
        event.preventDefault();
        const action = document.getElementById('reasonAction').value;
        const billId = document.getElementById('reasonBillId').value;
        const reason = document.getElementById('reasonText').value.trim();
        submitReason(action, billId, reason);
    });

    loadInit();
})();
</script>
</body>
</html>
