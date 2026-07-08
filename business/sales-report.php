<?php
/**
 * Universal Footwear POS - Sales Report
 * UI modified to follow Payment/Customer Ledger page method/style.
 * Place at project root / business folder: sales-report.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

if (function_exists('require_business_login')) { require_business_login(); }
if (function_exists('require_page_access')) { require_page_access($conn, 'sales-report.php'); }

$pageTitle = 'Sales Report';
$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);

function srp_e($value): string
{
    if (function_exists('e')) { return e((string)$value); }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($businessId <= 0) {
    die('Business session missing. Please login again.');
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= srp_e($pageTitle) ?> - GK Footwear POS</title>
    <?php if (file_exists(__DIR__ . '/includes/links.php')) { include __DIR__ . '/includes/links.php'; } ?>

    <style>
    .master-page {
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
        font-size: 12px;
        font-weight: 500;
    }
    .mp-hero {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-soft, #dbe4f0);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 14px 16px;
    }
    .mp-hero h1 {
        font-size: 20px;
        font-weight: 800;
        margin: 0 0 3px;
        letter-spacing: -.02em;
        color: var(--text-main, #0f172a);
    }
    .mp-hero p {
        font-size: 11px;
        line-height: 1.35;
        margin: 0;
        color: var(--text-muted, #64748b);
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
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-soft, #dbe4f0);
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
    .mp-stat-icon svg { width: 17px; height: 17px; }
    .mp-stat-label {
        font-size: 10.5px;
        color: var(--text-muted, #64748b);
        font-weight: 700;
        line-height: 1.15;
    }
    .mp-stat-value {
        font-size: 18px;
        color: var(--text-main, #0f172a);
        font-weight: 800;
        margin: 1px 0;
        line-height: 1.05;
    }
    .mp-stat-sub {
        font-size: 10px;
        color: var(--text-muted, #64748b);
        font-weight: 550;
        line-height: 1.15;
    }
    .mp-card {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-soft, #dbe4f0);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        overflow: hidden;
    }
    .mp-card-head {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-soft, #dbe4f0);
    }
    .mp-card-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text-main, #0f172a);
        margin: 0 0 2px;
    }
    .mp-card-sub {
        font-size: 11px;
        color: var(--text-muted, #64748b);
        margin: 0;
    }
    .mp-filter-input,
    .mp-filter-select {
        min-height: 32px;
        font-size: 11px;
        border-radius: 999px;
        padding: 5px 10px;
    }
    .sales-filter-grid {
        display: grid;
        grid-template-columns: .9fr .9fr 1.15fr 1.15fr 1.25fr 1.1fr 1.35fr auto;
        gap: 8px;
        align-items: end;
    }
    .sales-filter-grid label {
        font-size: 10px;
        font-weight: 750;
        color: var(--text-muted, #64748b);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .sales-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-bottom: 12px;
    }
    .sales-tab-btn {
        border: 1px solid var(--border-soft, #dbe4f0);
        background: var(--card-bg, #ffffff);
        color: var(--text-main, #0f172a);
        border-radius: 999px;
        padding: 7px 11px;
        font-size: 10.5px;
        font-weight: 750;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 5px 12px rgba(15, 23, 42, .04);
    }
    .sales-tab-btn.active {
        background: #0f172a;
        border-color: #0f172a;
        color: #ffffff;
    }
    .mp-table th {
        font-size: 10px;
        font-weight: 750;
        padding: 9px 10px;
        white-space: nowrap;
        background: #f1f5f9;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: .04em;
        border-bottom: 0;
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
        background: linear-gradient(135deg, var(--brand-1, #2563eb), var(--brand-2, #7c3aed));
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        flex: 0 0 auto;
    }
    .mp-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-main, #0f172a);
        line-height: 1.2;
    }
    .mp-sub {
        font-size: 10px;
        color: var(--text-muted, #64748b);
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
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .status-paid, .badge-money { background: #dcfce7; color: #15803d; }
    .status-partial, .badge-count { background: #fef3c7; color: #b45309; }
    .status-pending { background: #e0f2fe; color: #0369a1; }
    .status-cancelled, .badge-due { background: #fee2e2; color: #b91c1c; }
    .status-active { background: #dcfce7; color: #15803d; }
    .status-deleted { background: #f1f5f9; color: #475569; }
    .badge-code { background: #dbeafe; color: #1d4ed8; }
    .badge-type { background: #ede9fe; color: #6d28d9; }
    .badge-branch { background: #ecfeff; color: #0e7490; }
    .badge-muted { background: #f1f5f9; color: #475569; }
    .amount-good { color:#15803d; font-weight:800; }
    .amount-due { color:#b91c1c; font-weight:800; }
    .amount-dark { color:#0f172a; font-weight:800; }
    /* Compact report table: keep all bill-wise columns inside the screen without text overlap. */
    .sales-table { width: 100%; min-width: 0 !important; table-layout: fixed; }
    .sales-table th,
    .sales-table td {
        white-space: normal;
        word-break: normal;
        overflow-wrap: break-word;
        padding: 7px 5px;
        vertical-align: middle;
    }
    .sales-table th { font-size: 9px; line-height: 1.12; }
    .sales-table td { font-size: 10px; line-height: 1.18; }

    /* Bill-wise column widths are tuned for 100% screen fit. */
    .sales-table.tab-bills { min-width: 0 !important; }
    .sales-table.tab-bills th:nth-child(1), .sales-table.tab-bills td:nth-child(1) { width: 7%; }
    .sales-table.tab-bills th:nth-child(2), .sales-table.tab-bills td:nth-child(2) { width: 8%; }
    .sales-table.tab-bills th:nth-child(3), .sales-table.tab-bills td:nth-child(3) { width: 10%; }
    .sales-table.tab-bills th:nth-child(4), .sales-table.tab-bills td:nth-child(4) { width: 12%; }
    .sales-table.tab-bills th:nth-child(5), .sales-table.tab-bills td:nth-child(5) { width: 8%; }
    .sales-table.tab-bills th:nth-child(6), .sales-table.tab-bills td:nth-child(6) { width: 5%; text-align:center; }
    .sales-table.tab-bills th:nth-child(7), .sales-table.tab-bills td:nth-child(7),
    .sales-table.tab-bills th:nth-child(8), .sales-table.tab-bills td:nth-child(8),
    .sales-table.tab-bills th:nth-child(9), .sales-table.tab-bills td:nth-child(9),
    .sales-table.tab-bills th:nth-child(10), .sales-table.tab-bills td:nth-child(10),
    .sales-table.tab-bills th:nth-child(11), .sales-table.tab-bills td:nth-child(11) { width: 7.2%; text-align:right; }
    .sales-table.tab-bills th:nth-child(12), .sales-table.tab-bills td:nth-child(12) { width: 6.4%; text-align:center; }
    .sales-table.tab-bills th:nth-child(13), .sales-table.tab-bills td:nth-child(13) { width: 6.4%; text-align:center; }

    .sales-branch-cell,
    .sales-customer-cell { display: block; width: 100%; min-width: 0; line-height: 1.18; }
    .sales-table .mp-badge { max-width: 100%; font-size: 9px; padding: 4px 6px; line-height: 1.1; }
    .sales-table .badge-code { display:inline-flex; max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .sales-branch-chip {
        display: block;
        max-width: 100%;
        white-space: normal;
        overflow: hidden;
        text-overflow: clip;
        line-height: 1.12;
        text-align: center;
        padding: 5px 5px;
    }
    .sales-branch-chip .branch-main,
    .sales-branch-chip .branch-floor { display:block; }
    .sales-branch-chip .branch-main { font-weight:850; }
    .sales-branch-chip .branch-floor { font-size:8.4px; margin-top:1px; }
    .sales-customer-cell .mp-title,
    .sales-customer-cell .mp-sub {
        display: block;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
        line-height: 1.15;
    }
    .sales-customer-cell .mp-title { margin-bottom: 1px; font-size: 10px; }
    .sales-customer-cell .mp-sub { font-size: 8.8px; }
    .sales-table.tab-bills td:nth-child(5) { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .sales-table.tab-bills td:nth-child(7),
    .sales-table.tab-bills td:nth-child(8),
    .sales-table.tab-bills td:nth-child(9),
    .sales-table.tab-bills td:nth-child(10),
    .sales-table.tab-bills td:nth-child(11) { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:9.7px; }
    .sales-table .mp-avatar { width: 30px; height: 30px; border-radius: 10px; font-size: 12px; }
    .sales-table .mp-title { font-size: 11px; }
    .sales-table .mp-sub { font-size: 9.5px; }
    .mp-mobile-card {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-soft, #dbe4f0);
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 10px;
    }
    .mini-bar {
        height: 8px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
        min-width: 90px;
    }
    .mini-bar span {
        display: block;
        height: 100%;
        background: linear-gradient(90deg, #2563eb, #06b6d4);
    }
    .empty { padding: 35px; text-align:center; color:var(--text-muted,#64748b); font-weight:700; }
    .live-note {
        border: 1px dashed #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 14px;
        padding: 9px 11px;
        font-size: 11px;
        font-weight: 700;
    }
    html, body { max-width: 100%; overflow-x: hidden; }
    #main { min-width: 0; max-width: 100%; overflow-x: hidden; }
    .master-page, .mp-card, .sales-list-card { max-width: 100%; min-width: 0; }
    .sales-list-card .table-responsive { width: 100%; max-width: 100%; overflow-x: hidden; overflow-y: visible; }
    @media (max-width: 1400px) {
        .sales-filter-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .mp-hero { padding: 12px; }
        .mp-hero h1 { font-size: 19px; }
        .mp-stat-card { min-height: 64px; padding: 9px 10px; }
        .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; }
        .mp-stat-value { font-size: 16px; }
        .sales-filter-grid { grid-template-columns: 1fr; }
    }
    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php if (file_exists(__DIR__ . '/includes/page-message.php')) { include __DIR__ . '/includes/page-message.php'; } ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) { include __DIR__ . '/includes/common-toast.php'; } ?>

<div class="min-vh-100 d-flex">
    <?php if (file_exists(__DIR__ . '/includes/sidebar.php')) { include __DIR__ . '/includes/sidebar.php'; } ?>

    <main id="main">
        <?php if (file_exists(__DIR__ . '/includes/nav.php')) { include __DIR__ . '/includes/nav.php'; } ?>

        <section class="page-section master-page p-3 p-lg-3">
            <div class="mp-hero mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div>
                        <h1>Sales Report</h1>
                        <p>Bill-wise, item-wise, branch-wise, daily trend and payment-wise sales analysis.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" onclick="resetFilters()">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Reset
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportCsv()">
                            <i data-lucide="download" style="width:14px;height:14px;"></i> Export CSV
                        </button>
                        <button type="button" class="btn btn-dark" onclick="openPrint()">
                            <i data-lucide="printer" style="width:14px;height:14px;"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="receipt"></i></div><div><div class="mp-stat-label">Bills</div><div class="mp-stat-value" id="totalBills">0</div><div class="mp-stat-sub">Total bills</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#e0f2fe;color:#0369a1;"><i data-lucide="trending-up"></i></div><div><div class="mp-stat-label">Net Sales</div><div class="mp-stat-value" id="netSales">₹0.00</div><div class="mp-stat-sub">Final payable</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="indian-rupee"></i></div><div><div class="mp-stat-label">Paid</div><div class="mp-stat-value" id="paidAmount">₹0.00</div><div class="mp-stat-sub">Collected</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c;"><i data-lucide="badge-alert"></i></div><div><div class="mp-stat-label">Balance</div><div class="mp-stat-value" id="balanceAmount">₹0.00</div><div class="mp-stat-sub">Customer pending</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="badge-percent"></i></div><div><div class="mp-stat-label">Discount</div><div class="mp-stat-value" id="discountTotal">₹0.00</div><div class="mp-stat-sub">Item + bill</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="calculator"></i></div><div><div class="mp-stat-label">Avg Bill</div><div class="mp-stat-value" id="averageBill">₹0.00</div><div class="mp-stat-sub">Average value</div></div></article></div>
            </div>

            <div class="sales-tabs">
                <button class="sales-tab-btn active" type="button" data-tab="bills" onclick="changeTab('bills')"><i data-lucide="receipt" style="width:13px;height:13px;"></i> Bill-wise Sales</button>
                <button class="sales-tab-btn" type="button" data-tab="items" onclick="changeTab('items')"><i data-lucide="boxes" style="width:13px;height:13px;"></i> Item Sales</button>
                <button class="sales-tab-btn" type="button" data-tab="trend" onclick="changeTab('trend')"><i data-lucide="line-chart" style="width:13px;height:13px;"></i> Daily Trend</button>
                <button class="sales-tab-btn" type="button" data-tab="branch" onclick="changeTab('branch')"><i data-lucide="building-2" style="width:13px;height:13px;"></i> Branch Summary</button>
                <button class="sales-tab-btn" type="button" data-tab="payment" onclick="changeTab('payment')"><i data-lucide="wallet" style="width:13px;height:13px;"></i> Payment Summary</button>
            </div>

            <section class="mp-card sales-list-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div>
                                <h2 class="mp-card-title" id="tableTitle">Bill-wise Sales</h2>
                                <p class="mp-card-sub" id="tableSub">Latest matching sales bills.</p>
                            </div>
                            <div class="mp-sub" id="rowCount">0 rows</div>
                        </div>

                        <form method="get" id="filterForm" class="sales-filter-grid">
                            <div><label>From</label><input type="date" class="form-control mp-filter-input" id="from_date" name="from_date"></div>
                            <div><label>To</label><input type="date" class="form-control mp-filter-input" id="to_date" name="to_date"></div>
                            <div><label>Branch / Firm</label><select class="form-select mp-filter-select" id="branch_id" name="branch_id"><option value="">All</option></select></div>
                            <div><label>Sales User</label><select class="form-select mp-filter-select" id="created_by" name="created_by"><option value="">All</option></select></div>
                            <div><label>Customer</label><select class="form-select mp-filter-select" id="customer_id" name="customer_id"><option value="">All</option></select></div>
                            <div><label>Payment</label><select class="form-select mp-filter-select" id="payment_status" name="payment_status"><option value="">All</option><option value="pending">Pending</option><option value="partial">Partial</option><option value="paid">Paid</option><option value="cancelled">Cancelled</option></select></div>
                            <div><label>Search</label><input type="text" class="form-control mp-filter-input" id="search" name="search" placeholder="Bill no / customer"></div>
                            <div><button class="btn btn-dark btn-sm rounded-pill fw-bold px-3 w-100" type="submit">Filter</button></div>
                        </form>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table sales-table mb-0">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"><tr><td class="text-center text-muted py-4">Loading sales report...</td></tr></tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="salesMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading sales report...</div>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 py-2 border-top">
                    <div class="mp-sub">Sales report uses live bills, bill items, payments and branch/user filters.</div>
                    <div class="mp-sub">Current view: <span id="currentViewName">Bill-wise Sales</span></div>
                </div>
            </section>

            <div class="live-note mt-3">This page follows the same inner-page UI method as the Payment/Customer Ledger module: hero card, compact filters, KPI cards, tabbed report views, desktop table and mobile cards.</div>

            <?php if (file_exists(__DIR__ . '/includes/footer.php')) { include __DIR__ . '/includes/footer.php'; } ?>
        </section>
    </main>
</div>

<?php if (file_exists(__DIR__ . '/includes/script.php')) { include __DIR__ . '/includes/script.php'; } ?>

<script>
(function(){
    'use strict';
    window.reportState = { tab: 'bills', masters: {}, rows: [] };
    const today = new Date();
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from_date').value = formatDateLocal(first);
    document.getElementById('to_date').value = formatDateLocal(today);
    document.getElementById('filterForm').addEventListener('submit', function(e){ e.preventDefault(); loadCurrent(); });
    const searchInput = document.getElementById('search');
    let searchTimer = null;
    searchInput.addEventListener('input', function(){ clearTimeout(searchTimer); searchTimer = setTimeout(loadCurrent, 450); });
    init();
})();

function formatDateLocal(date){
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
}
function money(v){ v=parseFloat(v||0); return '₹' + v.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2}); }
function num(v){ v=parseFloat(v||0); return v.toLocaleString('en-IN',{maximumFractionDigits:2}); }
function esc(v){ return String(v==null?'':v).replace(/[&<>"']/g,function(s){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s];}); }
function showMessage(type, message){
    const toastType = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'error');
    if (window.AppToast && typeof window.AppToast.show === 'function') { window.AppToast.show(toastType, message); return; }
    alert(message);
}
function qs(extra){
    const fd=new FormData(document.getElementById('filterForm'));
    const p=new URLSearchParams();
    fd.forEach((v,k)=>{ if(v!=='' && v!=null) p.append(k,v); });
    Object.keys(extra||{}).forEach(k=>p.set(k,extra[k]));
    return p.toString();
}
async function api(action){
    const res=await fetch('api/sales-report-api.php?'+qs({action:action}),{credentials:'same-origin', headers:{'Accept':'application/json'}});
    return await res.json();
}
function refreshIcons(){ if(window.lucide && typeof window.lucide.createIcons === 'function'){ window.lucide.createIcons(); } }
function displayBranch(r){ return (r.branch_name||'') + (r.floor_name ? ' - '+r.floor_name : ''); }
function branchCell(r){
    const branch = esc(r.branch_name || '-');
    const floor = esc(r.floor_name || '');
    return '<div class="sales-branch-cell"><span class="mp-badge badge-branch sales-branch-chip"><span class="branch-main">' + branch + '</span>' + (floor ? '<span class="branch-floor">' + floor + '</span>' : '') + '</span></div>';
}
function cleanStatus(v){ return String(v || '').toLowerCase().replace(/[^a-z0-9_-]/g, ''); }
function statusBadge(v){
    const s = cleanStatus(v || '-');
    let cls = 'badge-muted';
    if (s === 'paid' || s === 'active') cls = 'status-paid';
    else if (s === 'partial') cls = 'status-partial';
    else if (s === 'pending') cls = 'status-pending';
    else if (s === 'cancelled' || s === 'deleted') cls = 'status-cancelled';
    return '<span class="mp-badge ' + cls + '">' + esc(String(v || '-').charAt(0).toUpperCase() + String(v || '-').slice(1)) + '</span>';
}

async function init(){
    try {
        const data = await api('init');
        if(!data.success){ showMessage('error', data.message || 'Unable to load report.'); return; }
        reportState.masters = data.masters || {};
        fillSelect('branch_id', reportState.masters.branches || [], 'branch_id', function(r){return displayBranch(r);});
        fillSelect('created_by', reportState.masters.users || [], 'user_id', function(r){return r.user_name || r.name || r.username || 'User';});
        fillSelect('customer_id', reportState.masters.customers || [], 'customer_id', function(r){return (r.customer_name||'') + (r.mobile ? ' - '+r.mobile : '');});
        renderKpis(data.summary || {});
        await loadCurrent();
        refreshIcons();
    } catch (error) {
        showMessage('error', 'Sales report API error. Please check API file.');
    }
}

function fillSelect(id, rows, key, labelFn){
    const s=document.getElementById(id); const first=s.options[0].outerHTML; s.innerHTML=first;
    rows.forEach(r=>{ const o=document.createElement('option'); o.value=r[key]; o.textContent=labelFn(r); s.appendChild(o); });
}

function renderKpis(s){
    document.getElementById('totalBills').textContent = parseInt(s.total_bills || 0, 10);
    document.getElementById('netSales').textContent = money(s.net_sales);
    document.getElementById('paidAmount').textContent = money(s.paid_amount);
    document.getElementById('balanceAmount').textContent = money(s.balance_amount);
    document.getElementById('discountTotal').textContent = money(s.discount_total);
    document.getElementById('averageBill').textContent = money(s.average_bill_value);
}

function changeTab(tab){
    reportState.tab=tab;
    document.querySelectorAll('.sales-tab-btn').forEach(b=>b.classList.toggle('active', b.dataset.tab===tab));
    loadCurrent();
    refreshIcons();
}

async function loadCurrent(){
    try {
        const map={bills:'list',items:'items',trend:'daily_trend',branch:'branch_summary',payment:'payment_summary'};
        const data=await api(map[reportState.tab] || 'list');
        if(!data.success){ showMessage('error', data.message || 'Unable to load data.'); return; }
        if(data.summary) renderKpis(data.summary);
        reportState.rows=data.rows || [];
        renderTable(reportState.tab, reportState.rows);
        refreshIcons();
    } catch (error) {
        showMessage('error', 'Unable to load sales report.');
    }
}

function cfgFor(tab){
    return {
        bills: {
            title:'Bill-wise Sales', sub:'Latest matching sales bills.',
            head:['Bill No','Date & Time','Branch','Customer','Sales User','Items','MRP','Discount','Net','Paid','Balance','Payment','Bill Status'],
            row:r=>[
                '<span class="mp-badge badge-code">'+esc(r.bill_no || '-')+'</span>',
                esc(r.bill_datetime || '-'),
                branchCell(r),
                '<div class="sales-customer-cell"><div class="mp-title">'+esc(r.customer_name||'Walk-in')+'</div>'+(r.customer_mobile?'<div class="mp-sub">'+esc(r.customer_mobile)+'</div>':'')+'</div>',
                esc(r.created_by_name || '-'),
                '<span class="mp-badge badge-count">'+num(r.item_count)+' / '+num(r.total_qty)+'</span>',
                money(r.mrp_total),
                money((parseFloat(r.item_discount_total||0)+parseFloat(r.bill_discount_amount||0))),
                '<span class="amount-dark">'+money(r.net_amount)+'</span>',
                '<span class="amount-good">'+money(r.paid_amount)+'</span>',
                '<span class="'+(parseFloat(r.balance_amount||0)>0?'amount-due':'amount-good')+'">'+money(r.balance_amount)+'</span>',
                statusBadge(r.payment_status),
                statusBadge(r.bill_status)
            ],
            mobile:r=>mobileBill(r)
        },
        items: {
            title:'Item Sales', sub:'Article, size, brand and category level performance.',
            head:['Article','Product','Brand','Category','Size','Color','Bills','Qty','MRP Value','Discount','Sales Value','Avg Rate'],
            row:r=>[
                '<span class="mp-badge badge-code">'+esc(r.article_no || '-')+'</span>',
                '<div class="mp-title">'+esc(r.article_name || '-')+'</div>',
                esc(r.brand_name || '-'), esc(r.category_name || '-'), esc(r.size || '-'), esc(r.color || '-'),
                '<span class="mp-badge badge-count">'+num(r.bill_count)+'</span>', num(r.total_qty), money(r.mrp_value), money(r.item_discount), '<span class="amount-good">'+money(r.sales_value)+'</span>', money(r.average_selling_rate)
            ],
            mobile:r=>mobileItem(r)
        },
        trend: {
            title:'Daily Trend', sub:'Day-wise sales, collection and balance.',
            head:['Date','Bills','Net Sales','Paid','Balance','Trend'],
            row:r=>[esc(r.display_date || '-'), '<span class="mp-badge badge-count">'+num(r.bill_count)+'</span>', '<span class="amount-dark">'+money(r.net_sales)+'</span>', '<span class="amount-good">'+money(r.paid_amount)+'</span>', '<span class="'+(parseFloat(r.balance_amount||0)>0?'amount-due':'amount-good')+'">'+money(r.balance_amount)+'</span>', bar(r.net_sales)],
            mobile:r=>mobileTrend(r)
        },
        branch: {
            title:'Branch Summary', sub:'Firm / branch-wise sales result.',
            head:['Branch','Floor','Bills','Net Sales','Paid','Balance'],
            row:r=>['<span class="mp-badge badge-branch">'+esc(r.branch_name || '-')+'</span>', esc(r.floor_name || '-'), '<span class="mp-badge badge-count">'+num(r.bill_count)+'</span>', '<span class="amount-dark">'+money(r.net_sales)+'</span>', '<span class="amount-good">'+money(r.paid_amount)+'</span>', '<span class="'+(parseFloat(r.balance_amount||0)>0?'amount-due':'amount-good')+'">'+money(r.balance_amount)+'</span>'],
            mobile:r=>mobileBranch(r)
        },
        payment: {
            title:'Payment Summary', sub:'Payment method-wise collection result.',
            head:['Payment Method','Type','Count','Amount'],
            row:r=>['<span class="mp-badge badge-money">'+esc(r.payment_method_name || '-')+'</span>', esc(r.method_type || '-'), '<span class="mp-badge badge-count">'+num(r.payment_count)+'</span>', '<span class="amount-good">'+money(r.paid_amount)+'</span>'],
            mobile:r=>mobilePayment(r)
        }
    }[tab];
}

function renderTable(tab, rows){
    const cfg = cfgFor(tab);
    const tableEl = document.querySelector('.sales-table');
    if (tableEl) {
        tableEl.className = 'table mp-table sales-table mb-0 tab-' + tab;
    }
    document.getElementById('tableTitle').textContent=cfg.title;
    document.getElementById('tableSub').textContent=cfg.sub;
    document.getElementById('currentViewName').textContent=cfg.title;
    document.getElementById('rowCount').textContent=rows.length+' rows';
    document.getElementById('tableHead').innerHTML='<tr>'+cfg.head.map(h=>'<th>'+esc(h)+'</th>').join('')+'</tr>';
    if(!rows.length){
        document.getElementById('tableBody').innerHTML='<tr><td colspan="'+cfg.head.length+'" class="text-center text-muted py-4">No records found.</td></tr>';
        document.getElementById('salesMobileCards').innerHTML='<div class="mp-mobile-card text-center text-muted">No records found.</div>';
        return;
    }
    document.getElementById('tableBody').innerHTML=rows.map(r=>'<tr>'+cfg.row(r).map((c,i)=>'<td class="'+numericClass(tab, i, cfg.head.length)+'">'+c+'</td>').join('')+'</tr>').join('');
    document.getElementById('salesMobileCards').innerHTML=rows.map(r=>cfg.mobile(r)).join('');
}

function numericClass(tab, index, total){
    const numericMap = {
        bills: [5,6,7,8,9,10],
        items: [6,7,8,9,10,11],
        trend: [1,2,3,4],
        branch: [2,3,4,5],
        payment: [2,3]
    };
    return (numericMap[tab] || []).includes(index) ? 'text-end' : '';
}

function mobileBill(r){
    const bal = parseFloat(r.balance_amount||0);
    return `<div class="mp-mobile-card">
        <div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.bill_no || '-')}</div><div class="mp-sub">${esc(r.bill_datetime || '-')} · ${esc(displayBranch(r) || '-')}</div></div>${statusBadge(r.payment_status)}</div>
        <div class="mp-sub mt-2">${esc(r.customer_name || 'Walk-in')} ${r.customer_mobile ? '· '+esc(r.customer_mobile) : ''}</div>
        <div class="row g-2 mt-1"><div class="col-6"><div class="mp-sub">Net</div><div class="mp-title">${money(r.net_amount)}</div></div><div class="col-6"><div class="mp-sub">Paid</div><div class="mp-title amount-good">${money(r.paid_amount)}</div></div><div class="col-6"><div class="mp-sub">Balance</div><div class="mp-title ${bal>0?'amount-due':'amount-good'}">${money(r.balance_amount)}</div></div><div class="col-6"><div class="mp-sub">Items</div><div class="mp-title">${num(r.item_count)} / ${num(r.total_qty)}</div></div></div>
    </div>`;
}
function mobileItem(r){
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.article_name || '-')}</div><div class="mp-sub">Article: ${esc(r.article_no || '-')} · ${esc(r.brand_name || '-')}</div></div><span class="mp-badge badge-count">Qty ${num(r.total_qty)}</span></div><div class="row g-2 mt-2"><div class="col-6"><div class="mp-sub">Size / Color</div><div class="mp-title">${esc(r.size || '-')} / ${esc(r.color || '-')}</div></div><div class="col-6"><div class="mp-sub">Sales</div><div class="mp-title amount-good">${money(r.sales_value)}</div></div><div class="col-6"><div class="mp-sub">Bills</div><div class="mp-title">${num(r.bill_count)}</div></div><div class="col-6"><div class="mp-sub">Avg Rate</div><div class="mp-title">${money(r.average_selling_rate)}</div></div></div></div>`;
}
function mobileTrend(r){
    const bal = parseFloat(r.balance_amount||0);
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.display_date || '-')}</div><div class="mp-sub">Daily sales trend</div></div><span class="mp-badge badge-count">${num(r.bill_count)} Bills</span></div><div class="row g-2 mt-2"><div class="col-6"><div class="mp-sub">Net</div><div class="mp-title">${money(r.net_sales)}</div></div><div class="col-6"><div class="mp-sub">Paid</div><div class="mp-title amount-good">${money(r.paid_amount)}</div></div><div class="col-12"><div class="mp-sub">Balance</div><div class="mp-title ${bal>0?'amount-due':'amount-good'}">${money(r.balance_amount)}</div></div><div class="col-12">${bar(r.net_sales)}</div></div></div>`;
}
function mobileBranch(r){
    const bal = parseFloat(r.balance_amount||0);
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.branch_name || '-')}</div><div class="mp-sub">${esc(r.floor_name || '-')}</div></div><span class="mp-badge badge-count">${num(r.bill_count)} Bills</span></div><div class="row g-2 mt-2"><div class="col-6"><div class="mp-sub">Net</div><div class="mp-title">${money(r.net_sales)}</div></div><div class="col-6"><div class="mp-sub">Paid</div><div class="mp-title amount-good">${money(r.paid_amount)}</div></div><div class="col-12"><div class="mp-sub">Balance</div><div class="mp-title ${bal>0?'amount-due':'amount-good'}">${money(r.balance_amount)}</div></div></div></div>`;
}
function mobilePayment(r){
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.payment_method_name || '-')}</div><div class="mp-sub">${esc(r.method_type || '-')}</div></div><span class="mp-badge badge-money">${money(r.paid_amount)}</span></div><div class="mp-sub mt-2">Payment Count</div><div class="mp-title">${num(r.payment_count)}</div></div>`;
}

function bar(v){
    v=parseFloat(v||0);
    let max=0; (reportState.rows||[]).forEach(r=>{max=Math.max(max,parseFloat(r.net_sales||r.sales_value||r.paid_amount||0));});
    const p=max?Math.max(4,Math.min(100, v/max*100)):0;
    return `<div class="mini-bar"><span style="width:${p}%"></span></div>`;
}

function resetFilters(){
    document.getElementById('filterForm').reset();
    const today = new Date(); const first = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from_date').value = formatDateLocal(first);
    document.getElementById('to_date').value = formatDateLocal(today);
    loadCurrent();
}

function exportCsv(){
    const type = reportState.tab === 'items' ? 'items' : 'bills';
    window.location.href='api/sales-report-api.php?'+qs({action:'export', export_type:type});
}

function openPrint(){
    window.open('sales-report-print.php?'+qs({type:reportState.tab}), '_blank', 'noopener');
}
</script>
</body>
</html>
