<?php
/**
 * Universal Footwear POS - Payment Ledger Report
 * UI follows the first file / Sales Report inner-page template method.
 * Place at project root / business folder: payment-ledger-report.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

if (function_exists('require_business_login')) { require_business_login(); }
if (function_exists('require_page_access')) { require_page_access($conn, 'payment-ledger-report.php'); }

$pageTitle = 'Payment Ledger Report';
$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);

function plr_e($value): string
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
    <title><?= plr_e($pageTitle) ?> - GK Footwear POS</title>
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
    .payment-filter-grid {
        display: grid;
        grid-template-columns: .85fr .85fr 1.05fr 1.05fr 1.15fr 1fr 1fr 1fr 1.25fr auto;
        gap: 8px;
        align-items: end;
    }
    .payment-filter-grid label {
        font-size: 10px;
        font-weight: 750;
        color: var(--text-muted, #64748b);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .advanced-filter-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr 1fr;
        gap: 8px;
        margin-top: 8px;
    }
    .payment-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        margin-bottom: 12px;
    }
    .payment-tab-btn {
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
    .payment-tab-btn.active {
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
    .badge-code { background: #dbeafe; color: #1d4ed8; }
    .badge-type { background: #ede9fe; color: #6d28d9; }
    .badge-branch { background: #ecfeff; color: #0e7490; }
    .badge-muted { background: #f1f5f9; color: #475569; }
    .amount-good { color:#15803d; font-weight:800; }
    .amount-due { color:#b91c1c; font-weight:800; }
    .amount-dark { color:#0f172a; font-weight:800; }
    .payment-table { width: 100%; min-width: 1080px; table-layout: fixed; }
    .payment-table th,
    .payment-table td {
        white-space: normal;
        word-break: normal;
        overflow-wrap: break-word;
        padding: 8px 7px;
        vertical-align: middle;
    }
    .payment-table th { font-size: 9.5px; line-height: 1.14; }
    .payment-table td { font-size: 10.5px; line-height: 1.18; }
    .payment-table .mp-badge { max-width: 100%; font-size: 9.5px; padding: 4px 6px; line-height: 1.1; }
    .payment-table .payment-branch-cell,
    .payment-table .payment-customer-cell { display:block; min-width:0; max-width:100%; }
    .payment-table .payment-customer-cell .mp-title,
    .payment-table .payment-customer-cell .mp-sub { white-space:normal; overflow-wrap:anywhere; line-height:1.15; }
    .payment-table .amount-col { white-space: nowrap; text-align: right; }
    .payment-table.tab-payments th:nth-child(1), .payment-table.tab-payments td:nth-child(1) { width: 8%; }
    .payment-table.tab-payments th:nth-child(2), .payment-table.tab-payments td:nth-child(2) { width: 10%; }
    .payment-table.tab-payments th:nth-child(3), .payment-table.tab-payments td:nth-child(3) { width: 9%; }
    .payment-table.tab-payments th:nth-child(4), .payment-table.tab-payments td:nth-child(4) { width: 12%; }
    .payment-table.tab-payments th:nth-child(5), .payment-table.tab-payments td:nth-child(5) { width: 9%; }
    .payment-table.tab-payments th:nth-child(6), .payment-table.tab-payments td:nth-child(6) { width: 8%; }
    .payment-table.tab-payments th:nth-child(7), .payment-table.tab-payments td:nth-child(7) { width: 9%; }
    .payment-table.tab-payments th:nth-child(8), .payment-table.tab-payments td:nth-child(8) { width: 8%; }
    .payment-table.tab-payments th:nth-child(9), .payment-table.tab-payments td:nth-child(9) { width: 8%; }
    .payment-table.tab-payments th:nth-child(10), .payment-table.tab-payments td:nth-child(10) { width: 8%; }
    .payment-table.tab-payments th:nth-child(11), .payment-table.tab-payments td:nth-child(11) { width: 6%; text-align:center; }
    .payment-table.tab-payments th:nth-child(12), .payment-table.tab-payments td:nth-child(12) { width: 5%; text-align:center; }
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
    .pagination-pill {
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 10.5px;
        font-weight: 750;
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
    .history-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        margin-bottom: 12px;
    }
    .history-mini-card {
        border: 1px solid var(--border-soft, #dbe4f0);
        background: #f8fafc;
        border-radius: 14px;
        padding: 10px;
    }
    .history-mini-card small {
        display:block;
        font-size:10px;
        font-weight:750;
        color:var(--text-muted, #64748b);
        text-transform:uppercase;
        letter-spacing:.04em;
    }
    .history-mini-card b {
        display:block;
        font-size:16px;
        font-weight:850;
        margin-top:3px;
    }
    .history-table-wrap { max-height: 58vh; overflow:auto; }
    html, body { max-width: 100%; overflow-x: hidden; }
    #main { min-width: 0; max-width: 100%; overflow-x: hidden; }
    .master-page, .mp-card, .payment-list-card { max-width: 100%; min-width: 0; }
    .payment-list-card .table-responsive { width: 100%; max-width: 100%; overflow-x: auto; overflow-y: visible; }
    @media (max-width: 1500px) {
        .payment-filter-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        .advanced-filter-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    }
    @media (max-width: 991px) {
        .payment-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .advanced-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .history-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .mp-hero { padding: 12px; }
        .mp-hero h1 { font-size: 19px; }
        .mp-stat-card { min-height: 64px; padding: 9px 10px; }
        .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; }
        .mp-stat-value { font-size: 16px; }
        .payment-filter-grid,
        .advanced-filter-grid,
        .history-summary-grid { grid-template-columns: 1fr; }
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
                        <h1>Payment Ledger Report</h1>
                        <p>Payment transactions, ledger entries, customer outstanding, cashier collections and payment method analytics.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" onclick="resetFilters()">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Reset
                        </button>
                        <button type="button" class="btn btn-success" onclick="exportExcel()">
                            <i data-lucide="file-spreadsheet" style="width:14px;height:14px;"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-success" onclick="exportCsv()">
                            <i data-lucide="download" style="width:14px;height:14px;"></i> CSV
                        </button>
                        <button type="button" class="btn btn-danger" onclick="exportPdf()">
                            <i data-lucide="file-text" style="width:14px;height:14px;"></i> PDF
                        </button>
                        <button type="button" class="btn btn-dark" onclick="openPrint()">
                            <i data-lucide="printer" style="width:14px;height:14px;"></i> Print
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="receipt-text"></i></div><div><div class="mp-stat-label">Transactions</div><div class="mp-stat-value" id="totalTransactions">0</div><div class="mp-stat-sub">Payment records</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="indian-rupee"></i></div><div><div class="mp-stat-label">Collected</div><div class="mp-stat-value" id="totalCollected">₹0.00</div><div class="mp-stat-sub">Received amount</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#e0f2fe;color:#0369a1;"><i data-lucide="banknote"></i></div><div><div class="mp-stat-label">Cash / UPI</div><div class="mp-stat-value" id="cashUpiAmount">₹0.00</div><div class="mp-stat-sub">Cash + UPI</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="users"></i></div><div><div class="mp-stat-label">Customers</div><div class="mp-stat-value" id="uniqueCustomers">0</div><div class="mp-stat-sub">Payment customers</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c;"><i data-lucide="badge-alert"></i></div><div><div class="mp-stat-label">Bill Outstanding</div><div class="mp-stat-value" id="billOutstanding">₹0.00</div><div class="mp-stat-sub">Pending bills</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="wallet-cards"></i></div><div><div class="mp-stat-label">Customer Outstanding</div><div class="mp-stat-value" id="customerOutstanding">₹0.00</div><div class="mp-stat-sub">Ledger balance</div></div></article></div>
            </div>

            <div class="payment-tabs">
                <button class="payment-tab-btn active" type="button" data-tab="payments" onclick="changeTab('payments')"><i data-lucide="wallet" style="width:13px;height:13px;"></i> Payment Transactions</button>
                <button class="payment-tab-btn" type="button" data-tab="ledger" onclick="changeTab('ledger')"><i data-lucide="book-open" style="width:13px;height:13px;"></i> Payment Ledger</button>
                <button class="payment-tab-btn" type="button" data-tab="outstanding" onclick="changeTab('outstanding')"><i data-lucide="badge-alert" style="width:13px;height:13px;"></i> Outstanding</button>
                <button class="payment-tab-btn" type="button" data-tab="daily" onclick="changeTab('daily')"><i data-lucide="calendar-days" style="width:13px;height:13px;"></i> Daily Collection</button>
                <button class="payment-tab-btn" type="button" data-tab="method" onclick="changeTab('method')"><i data-lucide="credit-card" style="width:13px;height:13px;"></i> Method Summary</button>
                <button class="payment-tab-btn" type="button" data-tab="cashier" onclick="changeTab('cashier')"><i data-lucide="user-check" style="width:13px;height:13px;"></i> Cashier Summary</button>
            </div>

            <section class="mp-card payment-list-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div>
                                <h2 class="mp-card-title" id="tableTitle">Payment Transactions</h2>
                                <p class="mp-card-sub" id="tableSub">Live payment collection records from bills and cashier collections.</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <select id="per_page" name="per_page" class="form-select mp-filter-select" style="width:110px" onchange="changePerPage()">
                                    <option value="10">10 rows</option>
                                    <option value="25" selected>25 rows</option>
                                    <option value="50">50 rows</option>
                                    <option value="100">100 rows</option>
                                    <option value="250">250 rows</option>
                                </select>
                                <span class="mp-sub" id="rowCount">0 rows</span>
                            </div>
                        </div>

                        <form method="get" id="filterForm">
                            <div class="payment-filter-grid">
                                <div><label>From</label><input type="date" class="form-control mp-filter-input" id="from_date" name="from_date"></div>
                                <div><label>To</label><input type="date" class="form-control mp-filter-input" id="to_date" name="to_date"></div>
                                <div><label>Branch / Firm</label><select class="form-select mp-filter-select" id="branch_id" name="branch_id"><option value="">All</option></select></div>
                                <div><label>Cashier / User</label><select class="form-select mp-filter-select" id="cashier_id" name="cashier_id"><option value="">All</option></select></div>
                                <div><label>Customer</label><select class="form-select mp-filter-select" id="customer_id" name="customer_id"><option value="">All</option></select></div>
                                <div><label>Method</label><select class="form-select mp-filter-select" id="payment_method_id" name="payment_method_id"><option value="">All</option></select></div>
                                <div><label>Bill Status</label><select class="form-select mp-filter-select" id="payment_status" name="payment_status"><option value="">All</option><option value="pending">Pending</option><option value="partial">Partial</option><option value="paid">Paid</option><option value="cancelled">Cancelled</option></select></div>
                                <div><label>Record Status</label><select class="form-select mp-filter-select" id="record_status" name="record_status"><option value="">All</option><option value="received">Received</option><option value="reversed">Reversed</option><option value="cancelled">Cancelled</option></select></div>
                                <div><label>Search</label><input type="text" class="form-control mp-filter-input" id="search" name="search" placeholder="Bill / customer / ref no"></div>
                                <div><button class="btn btn-dark btn-sm rounded-pill fw-bold px-3 w-100" type="submit">Filter</button></div>
                            </div>
                            <div class="advanced-filter-grid">
                                <div><label>Transaction Type</label><select class="form-select mp-filter-select" id="transaction_type" name="transaction_type"><option value="">All</option><option value="bill">Bill</option><option value="payment">Payment</option><option value="partial_payment">Partial Payment</option><option value="reverse">Reverse</option><option value="adjustment">Adjustment</option><option value="opening">Opening</option></select></div>
                                <div><label>Min Amount</label><input type="number" step="0.01" min="0" class="form-control mp-filter-input" id="min_amount" name="min_amount" placeholder="0.00"></div>
                                <div><label>Max Amount</label><input type="number" step="0.01" min="0" class="form-control mp-filter-input" id="max_amount" name="max_amount" placeholder="0.00"></div>
                                <div><label>Sort</label><div class="d-flex gap-2"><select class="form-select mp-filter-select" id="sort_by" name="sort_by"><option value="collected_at">Date</option><option value="bill_no">Bill No</option><option value="customer_name">Customer</option><option value="paid_amount">Amount</option><option value="balance_amount">Balance</option><option value="payment_method_name">Method</option><option value="cashier_name">Cashier</option></select><select class="form-select mp-filter-select" id="sort_order" name="sort_order" style="max-width:90px"><option value="desc">DESC</option><option value="asc">ASC</option></select></div></div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table payment-table mb-0 tab-payments">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"><tr><td class="text-center text-muted py-4">Loading payment ledger report...</td></tr></tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="paymentMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading payment ledger report...</div>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 py-2 border-top">
                    <div class="mp-sub">Current view: <span id="currentViewName">Payment Transactions</span></div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm pagination-pill" onclick="goPage('prev')">Previous</button>
                        <span class="mp-sub" id="paginationInfo">Page 1 of 1</span>
                        <button type="button" class="btn btn-outline-secondary btn-sm pagination-pill" onclick="goPage('next')">Next</button>
                    </div>
                </div>
            </section>

            <div class="live-note mt-3">This module uses your existing payment tables only: bill_payments, payment_ledger, cashier_collections, bills, customers, branches, payment_methods and users. No database schema change is required.</div>

            <?php if (file_exists(__DIR__ . '/includes/footer.php')) { include __DIR__ . '/includes/footer.php'; } ?>
        </section>
    </main>
</div>

<div class="modal fade" id="customerHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="historyTitle">Customer Payment History</h5>
                    <div class="mp-sub" id="historySub">Loading history...</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="history-summary-grid" id="historySummary"></div>
                <div class="mp-card">
                    <div class="table-responsive history-table-wrap">
                        <table class="table mp-table mb-0">
                            <thead>
                                <tr><th>Date</th><th>Type</th><th>Bill No</th><th>Branch</th><th>Method</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th><th>Remarks</th></tr>
                            </thead>
                            <tbody id="historyBody"><tr><td colspan="9" class="text-center text-muted py-4">Select a customer.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light btn-sm rounded-pill px-3" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/includes/script.php')) { include __DIR__ . '/includes/script.php'; } ?>

<script>
(function(){
    'use strict';
    window.paymentReportState = { tab: 'payments', masters: {}, rows: [], page: 1, totalPages: 1, total: 0, historyModal: null };
    const today = new Date();
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from_date').value = formatDateLocal(first);
    document.getElementById('to_date').value = formatDateLocal(today);
    document.getElementById('filterForm').addEventListener('submit', function(e){ e.preventDefault(); paymentReportState.page = 1; loadCurrent(); });
    const searchInput = document.getElementById('search');
    let searchTimer = null;
    searchInput.addEventListener('input', function(){ clearTimeout(searchTimer); searchTimer = setTimeout(function(){ paymentReportState.page = 1; loadCurrent(); }, 450); });
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
    p.set('per_page', document.getElementById('per_page').value || '25');
    p.set('page', paymentReportState.page || 1);
    Object.keys(extra||{}).forEach(k=>p.set(k,extra[k]));
    return p.toString();
}
async function api(action, extra){
    const res=await fetch('api/payment-ledger-report-api.php?'+qs(Object.assign({action:action}, extra||{})),{credentials:'same-origin', headers:{'Accept':'application/json'}});
    return await res.json();
}
function refreshIcons(){ if(window.lucide && typeof window.lucide.createIcons === 'function'){ window.lucide.createIcons(); } }
function displayBranch(r){ return (r.branch_name||'') + (r.floor_name ? ' - '+r.floor_name : ''); }
function cleanStatus(v){ return String(v || '').toLowerCase().replace(/[^a-z0-9_-]/g, ''); }
function statusBadge(v){
    const s = cleanStatus(v || '-');
    let cls = 'badge-muted';
    if (s === 'paid' || s === 'received' || s === 'active') cls = 'status-paid';
    else if (s === 'partial' || s === 'partial_payment') cls = 'status-partial';
    else if (s === 'pending') cls = 'status-pending';
    else if (s === 'cancelled' || s === 'reversed' || s === 'reverse' || s === 'deleted') cls = 'status-cancelled';
    return '<span class="mp-badge ' + cls + '">' + esc(String(v || '-').replace(/_/g,' ').replace(/\b\w/g, m=>m.toUpperCase())) + '</span>';
}
function branchBadge(r){ return '<span class="mp-badge badge-branch">' + esc(displayBranch(r) || '-') + '</span>'; }
function customerCell(r){
    const name = r.customer_name || 'Walk-in Customer';
    const mobile = r.customer_mobile || r.mobile || '';
    return '<div class="payment-customer-cell"><div class="mp-title">'+esc(name)+'</div>'+(mobile?'<div class="mp-sub">'+esc(mobile)+'</div>':'')+'</div>';
}

async function init(){
    try {
        const data = await api('init');
        if(!data.success){ showMessage('error', data.message || 'Unable to load payment ledger.'); return; }
        paymentReportState.masters = data.masters || {};
        fillSelect('branch_id', paymentReportState.masters.branches || [], 'branch_id', function(r){return displayBranch(r);});
        fillSelect('cashier_id', paymentReportState.masters.users || [], 'user_id', function(r){return r.user_name || r.name || r.username || 'User';});
        fillSelect('customer_id', paymentReportState.masters.customers || [], 'customer_id', function(r){return (r.customer_name||'') + (r.mobile ? ' - '+r.mobile : '');});
        fillSelect('payment_method_id', paymentReportState.masters.payment_methods || [], 'payment_method_id', function(r){return (r.payment_method_name||'') + (r.method_type ? ' - '+r.method_type : '');});
        renderKpis(data.summary || {});
        await loadCurrent();
        refreshIcons();
    } catch (error) {
        showMessage('error', 'Payment ledger API error. Please check API file.');
    }
}

function fillSelect(id, rows, key, labelFn){
    const s=document.getElementById(id); const first=s.options[0].outerHTML; s.innerHTML=first;
    rows.forEach(r=>{ const o=document.createElement('option'); o.value=r[key]; o.textContent=labelFn(r); s.appendChild(o); });
}

function renderKpis(s){
    document.getElementById('totalTransactions').textContent = parseInt(s.total_transactions || 0, 10);
    document.getElementById('totalCollected').textContent = money(s.total_collected);
    document.getElementById('cashUpiAmount').textContent = money((parseFloat(s.cash_amount||0) + parseFloat(s.upi_amount||0)));
    document.getElementById('uniqueCustomers').textContent = parseInt(s.unique_customers || 0, 10);
    document.getElementById('billOutstanding').textContent = money(s.bill_outstanding);
    document.getElementById('customerOutstanding').textContent = money(s.customer_outstanding);
}

function changeTab(tab){
    paymentReportState.tab=tab;
    paymentReportState.page=1;
    document.querySelectorAll('.payment-tab-btn').forEach(b=>b.classList.toggle('active', b.dataset.tab===tab));
    loadCurrent();
    refreshIcons();
}

function actionForTab(tab){
    const map = {payments:'payments', ledger:'ledger', outstanding:'outstanding', daily:'daily_summary', method:'method_summary', cashier:'cashier_summary'};
    return map[tab] || 'payments';
}

async function loadCurrent(){
    try {
        const data=await api(actionForTab(paymentReportState.tab));
        if(!data.success){ showMessage('error', data.message || 'Unable to load data.'); return; }
        if(data.summary) renderKpis(data.summary);
        paymentReportState.rows=data.rows || [];
        paymentReportState.total=parseInt(data.total || paymentReportState.rows.length || 0, 10);
        paymentReportState.totalPages=parseInt(data.total_pages || 1, 10);
        paymentReportState.page=parseInt(data.page || paymentReportState.page || 1, 10);
        renderTable(paymentReportState.tab, paymentReportState.rows);
        refreshIcons();
    } catch (error) {
        showMessage('error', 'Unable to load payment ledger report.');
    }
}

function cfgFor(tab){
    return {
        payments: {
            title:'Payment Transactions', sub:'Bill payment records collected by cashier / user.',
            head:['Payment ID','Collected At','Bill No','Branch','Customer','Method','Cashier','Paid','Bill Net','Balance','Bill Status','Record'],
            row:r=>[
                '<span class="mp-badge badge-code">#'+esc(r.payment_id || '-')+'</span>',
                esc(r.collected_datetime || '-'),
                '<span class="mp-badge badge-code">'+esc(r.bill_no || '-')+'</span>',
                branchBadge(r),
                customerCell(r),
                '<span class="mp-badge badge-money">'+esc(r.payment_method_name || '-')+'</span><div class="mp-sub">'+esc(r.method_type || '')+'</div>',
                esc(r.cashier_name || '-'),
                '<span class="amount-good">'+money(r.paid_amount)+'</span>',
                '<span class="amount-dark">'+money(r.net_amount)+'</span>',
                '<span class="'+(parseFloat(r.balance_amount||0)>0?'amount-due':'amount-good')+'">'+money(r.balance_amount)+'</span>',
                statusBadge(r.bill_payment_status),
                statusBadge(r.record_status)
            ],
            mobile:r=>mobilePayment(r)
        },
        ledger: {
            title:'Payment Ledger', sub:'Debit, credit and running balance transaction ledger.',
            head:['Ledger ID','Date','Type','Bill No','Branch','Customer','Method','Debit','Credit','Balance','Created By','Remarks'],
            row:r=>[
                '<span class="mp-badge badge-code">#'+esc(r.ledger_id || '-')+'</span>',
                esc(r.entry_datetime || '-'),
                statusBadge(r.transaction_type),
                '<span class="mp-badge badge-code">'+esc(r.bill_no || '-')+'</span>',
                branchBadge(r),
                customerCell(r),
                esc(r.payment_method_name || '-'),
                '<span class="amount-due">'+money(r.debit)+'</span>',
                '<span class="amount-good">'+money(r.credit)+'</span>',
                '<span class="amount-dark">'+money(r.balance)+'</span>',
                esc(r.created_by_name || '-'),
                esc(r.remarks || '')
            ],
            mobile:r=>mobileLedger(r)
        },
        outstanding: {
            title:'Customer Outstanding', sub:'Customer-wise bill, paid and balance tracking.',
            head:['Customer','Mobile','Opening','Bills','Bill Amount','Paid Amount','Balance','Last Bill','Action'],
            row:r=>[
                customerCell(r),
                esc(r.mobile || '-'),
                money(r.opening_outstanding),
                '<span class="mp-badge badge-count">'+num(r.bill_count)+'</span>',
                '<span class="amount-dark">'+money(r.total_bill_amount)+'</span>',
                '<span class="amount-good">'+money(r.total_paid_amount)+'</span>',
                '<span class="'+(parseFloat(r.balance_amount||0)>0?'amount-due':'amount-good')+'">'+money(r.balance_amount)+'</span>',
                esc(r.last_bill_date || '-'),
                '<button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold" onclick="openHistory('+parseInt(r.customer_id||0,10)+')">History</button>'
            ],
            mobile:r=>mobileOutstanding(r)
        },
        daily: {
            title:'Daily Collection', sub:'Day-wise payment collection, method split and cancelled amount.',
            head:['Date','Payments','Bills','Customers','Collected','Cash','UPI','Card','Cancelled','Trend'],
            row:r=>[
                esc(r.display_date || '-'),
                '<span class="mp-badge badge-count">'+num(r.payment_count)+'</span>',
                num(r.bill_count),
                num(r.customer_count),
                '<span class="amount-good">'+money(r.total_collected)+'</span>',
                money(r.cash_amount),
                money(r.upi_amount),
                money(r.card_amount),
                '<span class="amount-due">'+money(r.cancelled_amount)+'</span>',
                bar(r.total_collected)
            ],
            mobile:r=>mobileDaily(r)
        },
        method: {
            title:'Payment Method Summary', sub:'Payment method-wise collection performance.',
            head:['Payment Method','Type','Payments','Bills','Collected','Cancelled','Share'],
            row:r=>[
                '<span class="mp-badge badge-money">'+esc(r.payment_method_name || '-')+'</span>',
                esc(r.method_type || '-'),
                '<span class="mp-badge badge-count">'+num(r.payment_count)+'</span>',
                num(r.bill_count),
                '<span class="amount-good">'+money(r.collected_amount)+'</span>',
                '<span class="amount-due">'+money(r.cancelled_amount)+'</span>',
                bar(r.collected_amount)
            ],
            mobile:r=>mobileMethod(r)
        },
        cashier: {
            title:'Cashier Summary', sub:'Cashier / user-wise collection performance.',
            head:['Cashier','Payments','Bills','Customers','Collected','Cancelled','Share'],
            row:r=>[
                esc(r.cashier_name || '-'),
                '<span class="mp-badge badge-count">'+num(r.payment_count)+'</span>',
                num(r.bill_count),
                num(r.customer_count),
                '<span class="amount-good">'+money(r.collected_amount)+'</span>',
                '<span class="amount-due">'+money(r.cancelled_amount)+'</span>',
                bar(r.collected_amount)
            ],
            mobile:r=>mobileCashier(r)
        }
    }[tab];
}

function renderTable(tab, rows){
    const cfg = cfgFor(tab);
    const tableEl = document.querySelector('.payment-table');
    if (tableEl) tableEl.className = 'table mp-table payment-table mb-0 tab-' + tab;
    document.getElementById('tableTitle').textContent=cfg.title;
    document.getElementById('tableSub').textContent=cfg.sub;
    document.getElementById('currentViewName').textContent=cfg.title;
    document.getElementById('rowCount').textContent=paymentReportState.total+' rows';
    document.getElementById('paginationInfo').textContent='Page '+paymentReportState.page+' of '+paymentReportState.totalPages;
    document.getElementById('tableHead').innerHTML='<tr>'+cfg.head.map(h=>'<th>'+esc(h)+'</th>').join('')+'</tr>';
    if(!rows.length){
        document.getElementById('tableBody').innerHTML='<tr><td colspan="'+cfg.head.length+'" class="text-center text-muted py-4">No records found.</td></tr>';
        document.getElementById('paymentMobileCards').innerHTML='<div class="mp-mobile-card text-center text-muted">No records found.</div>';
        return;
    }
    document.getElementById('tableBody').innerHTML=rows.map(r=>'<tr>'+cfg.row(r).map((c,i)=>'<td class="'+numericClass(tab, i)+'">'+c+'</td>').join('')+'</tr>').join('');
    document.getElementById('paymentMobileCards').innerHTML=rows.map(r=>cfg.mobile(r)).join('');
}

function numericClass(tab, index){
    const numericMap = {
        payments: [7,8,9],
        ledger: [7,8,9],
        outstanding: [2,3,4,5,6],
        daily: [1,2,3,4,5,6,7,8],
        method: [2,3,4,5],
        cashier: [1,2,3,4,5]
    };
    return (numericMap[tab] || []).includes(index) ? 'text-end amount-col' : '';
}

function mobilePayment(r){
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.bill_no || '-')}</div><div class="mp-sub">${esc(r.collected_datetime || '-')} · ${esc(displayBranch(r) || '-')}</div></div>${statusBadge(r.record_status)}</div><div class="mp-sub mt-2">${esc(r.customer_name || 'Walk-in')} ${r.customer_mobile ? '· '+esc(r.customer_mobile) : ''}</div><div class="row g-2 mt-1"><div class="col-6"><div class="mp-sub">Paid</div><div class="mp-title amount-good">${money(r.paid_amount)}</div></div><div class="col-6"><div class="mp-sub">Balance</div><div class="mp-title ${parseFloat(r.balance_amount||0)>0?'amount-due':'amount-good'}">${money(r.balance_amount)}</div></div><div class="col-6"><div class="mp-sub">Method</div><div class="mp-title">${esc(r.payment_method_name || '-')}</div></div><div class="col-6"><div class="mp-sub">Cashier</div><div class="mp-title">${esc(r.cashier_name || '-')}</div></div></div></div>`;
}
function mobileLedger(r){
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.bill_no || '-')}</div><div class="mp-sub">${esc(r.entry_datetime || '-')} · ${esc(displayBranch(r) || '-')}</div></div>${statusBadge(r.transaction_type)}</div><div class="mp-sub mt-2">${esc(r.customer_name || 'Walk-in')}</div><div class="row g-2 mt-1"><div class="col-4"><div class="mp-sub">Debit</div><div class="mp-title amount-due">${money(r.debit)}</div></div><div class="col-4"><div class="mp-sub">Credit</div><div class="mp-title amount-good">${money(r.credit)}</div></div><div class="col-4"><div class="mp-sub">Balance</div><div class="mp-title">${money(r.balance)}</div></div></div></div>`;
}
function mobileOutstanding(r){
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.customer_name || '-')}</div><div class="mp-sub">${esc(r.mobile || '-')}</div></div><span class="mp-badge ${parseFloat(r.balance_amount||0)>0?'badge-due':'badge-money'}">${money(r.balance_amount)}</span></div><div class="row g-2 mt-2"><div class="col-6"><div class="mp-sub">Bill Amount</div><div class="mp-title">${money(r.total_bill_amount)}</div></div><div class="col-6"><div class="mp-sub">Paid</div><div class="mp-title amount-good">${money(r.total_paid_amount)}</div></div><div class="col-6"><div class="mp-sub">Bills</div><div class="mp-title">${num(r.bill_count)}</div></div><div class="col-6"><div class="mp-sub">Last Bill</div><div class="mp-title">${esc(r.last_bill_date || '-')}</div></div></div><button class="btn btn-sm btn-outline-primary rounded-pill mt-2" onclick="openHistory(${parseInt(r.customer_id||0,10)})">History</button></div>`;
}
function mobileDaily(r){
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.display_date || '-')}</div><div class="mp-sub">${num(r.payment_count)} payments · ${num(r.bill_count)} bills</div></div><span class="mp-badge badge-money">${money(r.total_collected)}</span></div><div class="row g-2 mt-2"><div class="col-4"><div class="mp-sub">Cash</div><div class="mp-title">${money(r.cash_amount)}</div></div><div class="col-4"><div class="mp-sub">UPI</div><div class="mp-title">${money(r.upi_amount)}</div></div><div class="col-4"><div class="mp-sub">Card</div><div class="mp-title">${money(r.card_amount)}</div></div><div class="col-12">${bar(r.total_collected)}</div></div></div>`;
}
function mobileMethod(r){
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.payment_method_name || '-')}</div><div class="mp-sub">${esc(r.method_type || '-')} · ${num(r.payment_count)} payments</div></div><span class="mp-badge badge-money">${money(r.collected_amount)}</span></div><div class="mp-sub mt-2">Cancelled / Reversed: ${money(r.cancelled_amount)}</div>${bar(r.collected_amount)}</div>`;
}
function mobileCashier(r){
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${esc(r.cashier_name || '-')}</div><div class="mp-sub">${num(r.payment_count)} payments · ${num(r.bill_count)} bills</div></div><span class="mp-badge badge-money">${money(r.collected_amount)}</span></div><div class="mp-sub mt-2">Customers: ${num(r.customer_count)} · Cancelled: ${money(r.cancelled_amount)}</div>${bar(r.collected_amount)}</div>`;
}

function bar(v){
    v=parseFloat(v||0);
    let max=0; (paymentReportState.rows||[]).forEach(r=>{max=Math.max(max,parseFloat(r.total_collected||r.collected_amount||r.paid_amount||r.credit||0));});
    const p=max?Math.max(4,Math.min(100, v/max*100)):0;
    return `<div class="mini-bar"><span style="width:${p}%"></span></div>`;
}

function changePerPage(){
    paymentReportState.page = 1;
    loadCurrent();
}
function goPage(dir){
    if(dir === 'prev' && paymentReportState.page > 1){ paymentReportState.page--; loadCurrent(); }
    if(dir === 'next' && paymentReportState.page < paymentReportState.totalPages){ paymentReportState.page++; loadCurrent(); }
}
function resetFilters(){
    document.getElementById('filterForm').reset();
    document.getElementById('per_page').value = '25';
    const today = new Date(); const first = new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from_date').value = formatDateLocal(first);
    document.getElementById('to_date').value = formatDateLocal(today);
    paymentReportState.page = 1;
    loadCurrent();
}
function exportCsv(){ window.location.href='api/payment-ledger-report-api.php?'+qs({action:'export', report:paymentReportState.tab, format:'csv'}); }
function exportExcel(){ window.location.href='api/payment-ledger-report-api.php?'+qs({action:'export', report:paymentReportState.tab, format:'excel'}); }
function exportPdf(){ window.open('payment-ledger-report-pdf.php?'+qs({type:paymentReportState.tab}), '_blank', 'noopener'); }
function openPrint(){ window.open('payment-ledger-report-print.php?'+qs({type:paymentReportState.tab}), '_blank', 'noopener'); }

function getHistoryModal(){
    const modalEl = document.getElementById('customerHistoryModal');
    if (window.bootstrap && window.bootstrap.Modal) {
        if (!paymentReportState.historyModal) paymentReportState.historyModal = new window.bootstrap.Modal(modalEl);
        return paymentReportState.historyModal;
    }
    return null;
}
async function openHistory(customerId){
    if(!customerId){ return; }
    document.getElementById('historyTitle').textContent = 'Customer Payment History';
    document.getElementById('historySub').textContent = 'Loading history...';
    document.getElementById('historySummary').innerHTML = '';
    document.getElementById('historyBody').innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading history...</td></tr>';
    const modal = getHistoryModal();
    if (modal) modal.show();

    try {
        const data = await api('history', {customer_id:customerId});
        if(!data.success){ showMessage('error', data.message || 'Unable to load history.'); return; }
        const c = data.customer || {}, s = data.summary || {}, rows = data.rows || [];
        document.getElementById('historyTitle').textContent = c.customer_name || 'Customer Payment History';
        document.getElementById('historySub').textContent = (c.mobile ? c.mobile + ' · ' : '') + 'Current balance: ' + money(s.current_balance);
        document.getElementById('historySummary').innerHTML = [
            ['Debit', money(s.total_debit), 'Bills / opening'],
            ['Credit', money(s.total_credit), 'Payments'],
            ['Closing', money(s.closing_balance), 'Ledger closing'],
            ['Current', money(s.current_balance), 'Outstanding']
        ].map(x=>`<div class="history-mini-card"><small>${esc(x[0])}</small><b>${esc(x[1])}</b><small>${esc(x[2])}</small></div>`).join('');
        document.getElementById('historyBody').innerHTML = rows.length ? rows.map(r=>`<tr>
            <td>${esc(r.entry_datetime || '-')}</td>
            <td>${statusBadge(r.transaction_type)}</td>
            <td>${esc(r.bill_no || '-')}</td>
            <td>${branchBadge(r)}</td>
            <td>${esc(r.payment_method_name || '-')}</td>
            <td class="text-end amount-due">${money(r.debit)}</td>
            <td class="text-end amount-good">${money(r.credit)}</td>
            <td class="text-end amount-dark">${money(r.balance)}</td>
            <td>${esc(r.remarks || '')}</td>
        </tr>`).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">No history found.</td></tr>';
        refreshIcons();
    } catch(error) {
        showMessage('error', 'Unable to load customer history.');
    }
}
</script>
</body>
</html>
