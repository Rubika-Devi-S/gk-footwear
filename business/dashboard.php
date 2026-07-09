<?php
/**
 * GK Footwear POS - Modern ERP Dashboard
 * Place this file at: dashboard.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

if (function_exists('require_business_login')) {
    require_business_login();
}
if (function_exists('require_page_access')) {
    require_page_access($conn, 'dashboard.php');
}

$pageTitle = 'ERP Dashboard';
$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);

function erp_dash_e($value): string
{
    if (function_exists('e')) {
        return e((string)$value);
    }
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
    <title><?= erp_dash_e($pageTitle) ?> - GK Footwear POS</title>
    <?php if (file_exists(__DIR__ . '/includes/links.php')) { include __DIR__ . '/includes/links.php'; } ?>

    <style>
    .erp-dashboard {
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
        font-size: 12px;
        font-weight: 500;
        color: var(--text-main, #0f172a);
    }
    .dash-hero {
        background:
            radial-gradient(circle at top left, rgba(37, 99, 235, .12), transparent 35%),
            radial-gradient(circle at top right, rgba(6, 182, 212, .12), transparent 30%),
            var(--card-bg, #fff);
        border: 1px solid var(--border-soft, #dbe4f0);
        border-radius: 18px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .07);
        padding: 16px;
    }
    .dash-hero h1 {
        font-size: 22px;
        font-weight: 850;
        letter-spacing: -.03em;
        margin: 0 0 4px;
    }
    .dash-hero p {
        margin: 0;
        color: var(--text-muted, #64748b);
        font-size: 11.5px;
        line-height: 1.45;
        max-width: 820px;
    }
    .dash-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }
    .dash-control {
        min-height: 34px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        padding: 6px 12px;
    }
    .dash-btn {
        min-height: 34px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
        padding: 6px 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .dash-grid {
        display: grid;
        gap: 12px;
    }
    .kpi-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }
    .dash-card {
        background: var(--card-bg, #fff);
        border: 1px solid var(--border-soft, #dbe4f0);
        border-radius: 18px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
        overflow: hidden;
        min-width: 0;
    }
    .dash-card-head {
        padding: 13px 14px;
        border-bottom: 1px solid var(--border-soft, #dbe4f0);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .dash-card-title {
        font-size: 14.5px;
        font-weight: 850;
        margin: 0;
        letter-spacing: -.01em;
    }
    .dash-card-sub {
        margin: 2px 0 0;
        color: var(--text-muted, #64748b);
        font-size: 10.5px;
        line-height: 1.25;
    }
    .dash-card-body {
        padding: 14px;
    }
    .kpi-card {
        padding: 13px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        min-height: 104px;
    }
    .kpi-icon {
        width: 42px;
        height: 42px;
        border-radius: 14px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
    }
    .kpi-icon svg { width: 18px; height: 18px; }
    .kpi-label {
        color: var(--text-muted, #64748b);
        font-weight: 800;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: .04em;
        line-height: 1.2;
    }
    .kpi-value {
        font-size: 18px;
        font-weight: 900;
        line-height: 1.1;
        margin: 5px 0 3px;
        white-space: nowrap;
    }
    .kpi-meta {
        color: var(--text-muted, #64748b);
        font-size: 10.5px;
        line-height: 1.25;
    }
    .chart-grid {
        grid-template-columns: minmax(0, 1.6fr) minmax(280px, .85fr);
    }
    .ops-grid {
        grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr) minmax(0, 1fr);
    }
    .bottom-grid {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    }
    .quick-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;
    }
    .quick-link {
        text-decoration: none;
        color: inherit;
        border: 1px solid var(--border-soft, #dbe4f0);
        background: #f8fafc;
        border-radius: 16px;
        padding: 11px 10px;
        min-height: 82px;
        display: flex;
        flex-direction: column;
        gap: 7px;
        transition: .18s ease;
    }
    .quick-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(15,23,42,.08);
        border-color: #bfdbfe;
        color: #0f172a;
    }
    .quick-icon {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        background: #e0f2fe;
        color: #0369a1;
    }
    .quick-icon svg { width: 16px; height: 16px; }
    .quick-title {
        font-size: 11.5px;
        font-weight: 850;
        line-height: 1.2;
    }
    .quick-sub {
        color: var(--text-muted, #64748b);
        font-size: 10px;
        line-height: 1.2;
    }
    .line-chart-wrap {
        min-height: 265px;
        display: grid;
        align-items: end;
    }
    .svg-chart {
        width: 100%;
        height: 252px;
        overflow: visible;
    }
    .bar-list {
        display: grid;
        gap: 9px;
    }
    .bar-row {
        display: grid;
        grid-template-columns: minmax(90px, 120px) minmax(0, 1fr) auto;
        align-items: center;
        gap: 8px;
    }
    .bar-label {
        color: var(--text-muted, #64748b);
        font-size: 10.5px;
        font-weight: 750;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .bar-track {
        height: 9px;
        border-radius: 999px;
        background: #e2e8f0;
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #2563eb, #06b6d4);
        min-width: 4px;
    }
    .bar-value {
        font-size: 10.5px;
        font-weight: 850;
        white-space: nowrap;
    }
    .dash-table {
        width: 100%;
        margin: 0;
    }
    .dash-table th {
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #64748b;
        background: #f8fafc;
        padding: 8px;
        border-bottom: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    .dash-table td {
        font-size: 10.5px;
        padding: 9px 8px;
        border-bottom: 1px solid #eef2f7;
        vertical-align: middle;
    }
    .dash-title {
        font-size: 11.5px;
        font-weight: 850;
        line-height: 1.2;
    }
    .dash-sub {
        color: var(--text-muted, #64748b);
        font-size: 10px;
        line-height: 1.25;
    }
    .pill {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 4px 7px;
        font-size: 9.5px;
        font-weight: 850;
        white-space: nowrap;
    }
    .pill-blue { background:#dbeafe; color:#1d4ed8; }
    .pill-green { background:#dcfce7; color:#15803d; }
    .pill-red { background:#fee2e2; color:#b91c1c; }
    .pill-yellow { background:#fef3c7; color:#b45309; }
    .pill-cyan { background:#ecfeff; color:#0e7490; }
    .amount-good { color:#15803d; font-weight:900; }
    .amount-due { color:#b91c1c; font-weight:900; }
    .amount-dark { color:#0f172a; font-weight:900; }
    .empty-box {
        padding: 28px 12px;
        text-align: center;
        color: #64748b;
        font-size: 11px;
        font-weight: 700;
    }
    .activity-item {
        display: flex;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #eef2f7;
    }
    .activity-dot {
        width: 32px;
        height: 32px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        background: #eef2ff;
        color: #4f46e5;
        flex: 0 0 auto;
    }
    .activity-dot svg { width: 15px; height: 15px; }
    .scroll-area {
        max-height: 336px;
        overflow: auto;
    }


    /* Equal fixed-height card UI for Stock Overview, Pending Payments and Recent Activities */
    .erp-dashboard {
        --ops-card-height: 430px;
        --ops-head-height: 66px;
    }
    .ops-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        align-items: stretch;
    }
    .ops-card {
        height: var(--ops-card-height);
        min-height: var(--ops-card-height);
        max-height: var(--ops-card-height);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .ops-card .dash-card-head {
        min-height: var(--ops-head-height);
        height: var(--ops-head-height);
        flex: 0 0 var(--ops-head-height);
        align-items: center;
    }
    .ops-card .dash-card-body {
        flex: 1 1 auto;
        height: calc(var(--ops-card-height) - var(--ops-head-height));
        min-height: 0;
        display: flex;
        flex-direction: column;
        padding: 14px;
        overflow: hidden;
    }
    .ops-card .ops-scroll {
        flex: 1 1 auto;
        min-height: 0;
        max-height: 100%;
        overflow-y: auto;
        overflow-x: hidden;
        border-radius: 12px;
        scrollbar-width: thin;
    }
    .stock-ops-card #stockOverviewBars {
        flex: 0 0 auto;
        margin-bottom: 12px !important;
    }
    .stock-ops-card .ops-scroll,
    .pending-ops-card .ops-scroll,
    .activity-ops-card .ops-scroll {
        height: 100%;
    }
    .ops-card .table-responsive.ops-scroll {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
    }
    .ops-card .dash-table {
        width: 100%;
        min-width: 0;
        table-layout: fixed;
    }
    .ops-card .dash-table th,
    .ops-card .dash-table td {
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: normal;
        vertical-align: top;
    }
    .ops-card .dash-table th {
        line-height: 1.15;
    }
    .pending-table th:nth-child(1),
    .pending-table td:nth-child(1) { width: 42%; }
    .pending-table th:nth-child(2),
    .pending-table td:nth-child(2) { width: 38%; }
    .pending-table th:nth-child(3),
    .pending-table td:nth-child(3) { width: 20%; }
    .pending-table .dash-title,
    .pending-table .dash-sub {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .activity-list {
        padding-right: 4px;
    }
    .activity-list .activity-item:first-child { padding-top: 0; }
    .activity-list .activity-item:last-child { border-bottom: 0; }
    .activity-list .activity-item > div:last-child { min-width: 0; }
    .activity-list .dash-title,
    .activity-list .dash-sub {
        white-space: normal;
        overflow-wrap: anywhere;
    }
    #stockOverviewBars .bar-fill {
        min-width: 0;
        border-radius: 3px;
    }
    #stockOverviewBars .bar-track {
        border-radius: 4px;
    }
    @media (max-width: 1399px) {
        .ops-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .activity-ops-card { grid-column: 1 / -1; }
    }
    @media (max-width: 991px) {
        .erp-dashboard { --ops-card-height: 390px; --ops-head-height: 64px; }
        .ops-grid { grid-template-columns: minmax(0, 1fr); }
        .activity-ops-card { grid-column: auto; }
    }
    @media (max-width: 575px) {
        .erp-dashboard { --ops-card-height: 360px; --ops-head-height: 58px; }
        .ops-card .dash-card-head { min-height: var(--ops-head-height); height: var(--ops-head-height); }
        .pending-table th:nth-child(1),
        .pending-table td:nth-child(1) { width: 44%; }
        .pending-table th:nth-child(2),
        .pending-table td:nth-child(2) { width: 36%; }
        .pending-table th:nth-child(3),
        .pending-table td:nth-child(3) { width: 20%; }
    }

    .dashboard-live-note {
        border: 1px dashed #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 14px;
        padding: 9px 11px;
        font-size: 11px;
        font-weight: 750;
    }
    @media (max-width: 1450px) {
        .kpi-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .quick-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    }
    @media (max-width: 1199px) {
        .chart-grid, .bottom-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 767px) {
        .erp-dashboard { font-size: 11px; }
        .dash-hero { padding: 13px; }
        .dash-hero h1 { font-size: 19px; }
        .dash-toolbar { justify-content: flex-start; }
        .kpi-grid { grid-template-columns: 1fr; }
        .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .kpi-card { min-height: 88px; }
        .bar-row { grid-template-columns: 86px 1fr; }
        .bar-value { grid-column: 2; }
    }

    /* Responsive dashboard fixes: cards wrap cleanly and tables scroll only inside cards */
    html, body { max-width: 100%; overflow-x: hidden; }
    #main, .erp-dashboard { min-width: 0; max-width: 100%; }
    .kpi-grid { grid-template-columns: repeat(auto-fit, minmax(185px, 1fr)); }
    .quick-grid { grid-template-columns: repeat(auto-fit, minmax(145px, 1fr)); }
    .dash-card .table-responsive { width: 100%; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .dash-table { min-width: 520px; }
    .dash-toolbar .dash-control { max-width: 100%; }
    .kpi-card { min-width: 0; }
    .kpi-card > div:last-child { min-width: 0; }
    .kpi-value { overflow: hidden; text-overflow: ellipsis; }
    @media (max-width: 1199px) {
        .dash-hero .dash-toolbar { justify-content: flex-start; }
        .chart-grid, .bottom-grid { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 767px) {
        .erp-dashboard { padding-left: 10px !important; padding-right: 10px !important; }
        .dash-hero { border-radius: 16px; }
        .dash-toolbar { width: 100%; display: grid; grid-template-columns: 1fr; }
        .dash-toolbar .dash-control, .dash-toolbar .dash-btn, .dash-toolbar select, .dash-toolbar input { width: 100% !important; }
        .kpi-grid { grid-template-columns: 1fr 1fr; gap: 9px; }
        .kpi-card { padding: 10px; min-height: 86px; gap: 8px; }
        .kpi-icon { width: 34px; height: 34px; border-radius: 12px; }
        .kpi-value { font-size: 15px; white-space: normal; }
        .quick-grid { grid-template-columns: 1fr 1fr; gap: 8px; }
        .quick-link { min-height: 74px; padding: 9px; }
        .dash-card-head { align-items: flex-start; flex-direction: column; }
        .line-chart-wrap { min-height: 210px; overflow-x: auto; }
        .svg-chart { min-width: 640px; }
        .bar-row { grid-template-columns: minmax(78px, 100px) minmax(0, 1fr); }
        .bar-value { grid-column: 2; text-align: right; }
    }
    @media (max-width: 430px) {
        .kpi-grid, .quick-grid { grid-template-columns: 1fr; }
        .dash-table { min-width: 480px; }
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

        <section class="page-section erp-dashboard p-3 p-lg-3">
            <div class="dash-hero mb-3">
                <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-3">
                    <div>
                        <h1>ERP Dashboard</h1>
                        <p>Live footwear business summary covering sales, collections, stock, customer/vendor dues, branch performance, user performance and recent activities.</p>
                    </div>
                    <div class="dash-toolbar">
                        <select id="branchFilter" class="form-select dash-control" style="width:190px;">
                            <option value="">All Branches / Firms</option>
                        </select>
                        <select id="periodFilter" class="form-select dash-control" style="width:140px;">
                            <option value="today">Today</option>
                            <option value="month" selected>This Month</option>
                            <option value="30">Last 30 Days</option>
                            <option value="custom">Custom</option>
                        </select>
                        <input type="date" id="dateFrom" class="form-control dash-control d-none" style="width:142px;">
                        <input type="date" id="dateTo" class="form-control dash-control d-none" style="width:142px;">
                        <button type="button" id="refreshDashboard" class="btn btn-dark dash-btn">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>

            <div class="dash-grid kpi-grid mb-3" id="kpiGrid">
                <div class="dash-card kpi-card"><div class="kpi-icon" style="background:#dbeafe;color:#1d4ed8;"><i data-lucide="receipt-text"></i></div><div><div class="kpi-label">Total sales</div><div class="kpi-value" id="kpiNetSales">₹0.00</div><div class="kpi-meta" id="kpiBillCount">0 bills</div></div></div>
                <div class="dash-card kpi-card"><div class="kpi-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="wallet-cards"></i></div><div><div class="kpi-label">Collected</div><div class="kpi-value" id="kpiCollected">₹0.00</div><div class="kpi-meta">Bill payments</div></div></div>
                <div class="dash-card kpi-card"><div class="kpi-icon" style="background:#fee2e2;color:#b91c1c;"><i data-lucide="badge-alert"></i></div><div><div class="kpi-label">Pending payments</div><div class="kpi-value" id="kpiPending">₹0.00</div><div class="kpi-meta" id="kpiPendingCount">0 pending bills</div></div></div>
                <div class="dash-card kpi-card"><div class="kpi-icon" style="background:#ecfeff;color:#0e7490;"><i data-lucide="boxes"></i></div><div><div class="kpi-label">Available stock</div><div class="kpi-value" id="kpiStockQty">0</div><div class="kpi-meta" id="kpiLowStock">0 low stock</div></div></div>
                <div class="dash-card kpi-card"><div class="kpi-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="users"></i></div><div><div class="kpi-label">Customer due</div><div class="kpi-value" id="kpiCustomerDue">₹0.00</div><div class="kpi-meta" id="kpiCustomerCount">0 customers</div></div></div>
                <div class="dash-card kpi-card"><div class="kpi-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="truck"></i></div><div><div class="kpi-label">Supplier due</div><div class="kpi-value" id="kpiSupplierDue">₹0.00</div><div class="kpi-meta" id="kpiSupplierCount">0 suppliers</div></div></div>
            </div>

            <div class="dash-card mb-3">
                <div class="dash-card-head">
                    <div><h2 class="dash-card-title">Quick Actions</h2><p class="dash-card-sub">Fast access to frequently used ERP screens.</p></div>
                </div>
                <div class="dash-card-body">
                    <div class="quick-grid" id="quickActions"></div>
                </div>
            </div>

            <div class="dash-grid chart-grid mb-3">
                <div class="dash-card">
                    <div class="dash-card-head">
                        <div><h2 class="dash-card-title">Sales Trend</h2><p class="dash-card-sub">Day-wise bill value and collection movement.</p></div>
                        <span class="pill pill-blue" id="trendLabel">This Month</span>
                    </div>
                    <div class="dash-card-body">
                        <div class="line-chart-wrap" id="salesTrendChart"><div class="empty-box">Loading sales trend...</div></div>
                    </div>
                </div>
                <div class="dash-card">
                    <div class="dash-card-head">
                        <div><h2 class="dash-card-title">Payment Method Mix</h2><p class="dash-card-sub">Collected amount by payment method.</p></div>
                    </div>
                    <div class="dash-card-body">
                        <div class="bar-list" id="paymentMixChart"><div class="empty-box">Loading payments...</div></div>
                    </div>
                </div>
            </div>

            <div class="dash-grid ops-grid mb-3">
                <div class="dash-card ops-card stock-ops-card">
                    <div class="dash-card-head"><div><h2 class="dash-card-title">Stock Overview</h2><p class="dash-card-sub">Available, sold, low stock and stock value.</p></div><a href="stock-list.php" class="pill pill-cyan text-decoration-none">View Stock</a></div>
                    <div class="dash-card-body">
                        <div class="bar-list mb-3" id="stockOverviewBars"></div>
                        <div class="table-responsive scroll-area ops-scroll">
                            <table class="dash-table stock-low-table">
                                <thead><tr><th>Low stock item</th><th>Branch</th><th class="text-end">Qty</th></tr></thead>
                                <tbody id="lowStockRows"><tr><td colspan="3" class="empty-box">Loading stock alerts...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="dash-card ops-card pending-ops-card">
                    <div class="dash-card-head"><div><h2 class="dash-card-title">Pending Payments</h2><p class="dash-card-sub">Bills still pending collection.</p></div><a href="cashier-pending-bills.php" class="pill pill-red text-decoration-none">Collect</a></div>
                    <div class="dash-card-body">
                        <div class="table-responsive scroll-area ops-scroll">
                            <table class="dash-table pending-table">
                                <thead><tr><th>Bill</th><th>Customer</th><th class="text-end">Due</th></tr></thead>
                                <tbody id="pendingPaymentRows"><tr><td colspan="3" class="empty-box">Loading pending bills...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="dash-card ops-card activity-ops-card">
                    <div class="dash-card-head"><div><h2 class="dash-card-title">Recent Activities</h2><p class="dash-card-sub">Latest activity logs from all modules.</p></div><a href="activity-logs.php" class="pill pill-blue text-decoration-none">Logs</a></div>
                    <div class="dash-card-body">
                        <div class="scroll-area ops-scroll activity-list" id="activityRows"><div class="empty-box">Loading activities...</div></div>
                    </div>
                </div>
            </div>

            <div class="dash-grid bottom-grid mb-3">
                <div class="dash-card">
                    <div class="dash-card-head"><div><h2 class="dash-card-title">Branch Performance</h2><p class="dash-card-sub">Sales, collection and due amount by firm / floor.</p></div><a href="sales-report.php" class="pill pill-blue text-decoration-none">Report</a></div>
                    <div class="dash-card-body">
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead><tr><th>Branch / Firm</th><th class="text-end">Bills</th><th class="text-end">Sales</th><th class="text-end">Due</th></tr></thead>
                                <tbody id="branchRows"><tr><td colspan="4" class="empty-box">Loading branch performance...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="dash-card">
                    <div class="dash-card-head"><div><h2 class="dash-card-title">User Performance</h2><p class="dash-card-sub">Sales user wise billing and collection.</p></div><a href="users.php" class="pill pill-cyan text-decoration-none">Users</a></div>
                    <div class="dash-card-body">
                        <div class="table-responsive">
                            <table class="dash-table">
                                <thead><tr><th>User</th><th class="text-end">Bills</th><th class="text-end">Sales</th><th class="text-end">Collected</th></tr></thead>
                                <tbody id="userRows"><tr><td colspan="4" class="empty-box">Loading user performance...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dash-grid bottom-grid mb-3">
                <div class="dash-card">
                    <div class="dash-card-head"><div><h2 class="dash-card-title">Customer Outstanding</h2><p class="dash-card-sub">Top customers with pending balance.</p></div><a href="customer-ledger-report.php" class="pill pill-yellow text-decoration-none">Ledger</a></div>
                    <div class="dash-card-body">
                        <div class="table-responsive scroll-area">
                            <table class="dash-table">
                                <thead><tr><th>Customer</th><th>Mobile</th><th class="text-end">Balance</th></tr></thead>
                                <tbody id="customerDueRows"><tr><td colspan="3" class="empty-box">Loading customer dues...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="dash-card">
                    <div class="dash-card-head"><div><h2 class="dash-card-title">Supplier Outstanding</h2><p class="dash-card-sub">Top suppliers / vendors with pending balance.</p></div><a href="suppiler-ledger-report.php" class="pill pill-yellow text-decoration-none">Ledger</a></div>
                    <div class="dash-card-body">
                        <div class="table-responsive scroll-area">
                            <table class="dash-table">
                                <thead><tr><th>Supplier</th><th>Mobile</th><th class="text-end">Balance</th></tr></thead>
                                <tbody id="supplierDueRows"><tr><td colspan="3" class="empty-box">Loading supplier dues...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dashboard-live-note">
                Dashboard uses only your existing live database tables. No dummy data is inserted or shown; all statistics are fetched dynamically from bills, collections, stock, customer, supplier, branch, user and activity tables.
            </div>

            <?php if (file_exists(__DIR__ . '/includes/footer.php')) { include __DIR__ . '/includes/footer.php'; } ?>
        </section>
    </main>
</div>

<?php if (file_exists(__DIR__ . '/includes/script.php')) { include __DIR__ . '/includes/script.php'; } ?>
<script>
(function () {
    'use strict';

    const apiUrl = 'api/erp-dashboard-api.php';
    const moneyFormatter = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 2 });
    const numberFormatter = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });
    let reloadTimer = null;

    function money(value) {
        return moneyFormatter.format(toNumber(value));
    }
    function qty(value) {
        return numberFormatter.format(toNumber(value));
    }
    function toNumber(value) {
        const n = parseFloat(value || 0);
        return isNaN(n) ? 0 : n;
    }
    function esc(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (s) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s];
        });
    }
    function showMessage(type, message) {
        if (window.AppToast && typeof window.AppToast.show === 'function') {
            window.AppToast.show(type === 'success' ? 'success' : 'error', message);
            return;
        }
        if (window.showToast) {
            window.showToast(message, type === 'error' ? 'danger' : type);
            return;
        }
        alert(message);
    }
    function refreshIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }
    function buildQuery(params) {
        const qs = new URLSearchParams();
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                qs.append(key, params[key]);
            }
        });
        return qs.toString();
    }
    async function apiGet(params) {
        const response = await fetch(apiUrl + '?' + buildQuery(params), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        });
        return await response.json();
    }
    function currentParams() {
        const period = document.getElementById('periodFilter').value;
        const branch = document.getElementById('branchFilter').value;
        return {
            action: 'summary',
            period: period,
            branch_id: branch,
            date_from: document.getElementById('dateFrom').value,
            date_to: document.getElementById('dateTo').value,
            _: Date.now()
        };
    }
    function setText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    }
    function fillBranches(rows) {
        const select = document.getElementById('branchFilter');
        const selected = select.value;
        select.innerHTML = '<option value="">All Branches / Firms</option>' + (rows || []).map(function (row) {
            const name = (row.branch_name || '-') + (row.floor_name ? ' - ' + row.floor_name : '');
            return '<option value="' + esc(row.branch_id) + '">' + esc(name) + '</option>';
        }).join('');
        select.value = selected;
    }
    function renderKpis(k) {
        setText('kpiNetSales', money(k.net_sales));
        setText('kpiBillCount', parseInt(k.bill_count || 0, 10) + ' bills');
        setText('kpiCollected', money(k.collected_amount));
        setText('kpiPending', money(k.pending_amount));
        setText('kpiPendingCount', parseInt(k.pending_bill_count || 0, 10) + ' pending bills');
        setText('kpiStockQty', qty(k.available_stock_qty));
        setText('kpiLowStock', parseInt(k.low_stock_count || 0, 10) + ' low · ' + parseInt(k.out_of_stock_count || 0, 10) + ' out');
        setText('kpiCustomerDue', money(k.customer_outstanding));
        setText('kpiCustomerCount', parseInt(k.outstanding_customer_count || 0, 10) + ' customers');
        setText('kpiSupplierDue', money(k.supplier_outstanding));
        setText('kpiSupplierCount', parseInt(k.outstanding_supplier_count || 0, 10) + ' suppliers');
    }
    function renderQuickActions(rows) {
        rows = rows && rows.length ? rows : [];
        const target = document.getElementById('quickActions');
        if (!target) { return; }
        if (!rows.length) {
            target.innerHTML = '<div class="empty-box" style="grid-column:1/-1;">No quick actions configured for this role.</div>';
            return;
        }
        target.innerHTML = rows.map(function (r) {
            return '<a class="quick-link" href="' + esc(r.url || '#') + '">' +
                '<span class="quick-icon"><i data-lucide="' + esc(r.icon || 'circle-dot') + '"></i></span>' +
                '<span class="quick-title">' + esc(r.title || 'Open') + '</span>' +
                '<span class="quick-sub">' + esc(r.subtitle || 'Open module') + '</span>' +
            '</a>';
        }).join('');
    }

    function renderLineChart(rows) {
        const target = document.getElementById('salesTrendChart');
        if (!rows || !rows.length) {
            target.innerHTML = '<div class="empty-box">No sales trend for selected period.</div>';
            return;
        }

        const width = 820, height = 250, pad = 34;
        const maxSales = Math.max(1, ...rows.map(function (r) { return toNumber(r.net_amount); }));
        const maxCollected = Math.max(1, ...rows.map(function (r) { return toNumber(r.paid_amount); }));
        const max = Math.max(maxSales, maxCollected);
        const step = rows.length <= 1 ? 0 : (width - pad * 2) / (rows.length - 1);

        function point(row, i, key) {
            const x = pad + (step * i);
            const y = height - pad - ((toNumber(row[key]) / max) * (height - pad * 2));
            return [x, y];
        }
        function path(key) {
            return rows.map(function (r, i) {
                const p = point(r, i, key);
                return (i === 0 ? 'M' : 'L') + p[0].toFixed(1) + ' ' + p[1].toFixed(1);
            }).join(' ');
        }
        const labels = rows.map(function (r, i) {
            if (rows.length > 12 && i % Math.ceil(rows.length / 8) !== 0 && i !== rows.length - 1) return '';
            const x = pad + (step * i);
            return '<text x="' + x.toFixed(1) + '" y="' + (height - 8) + '" text-anchor="middle" font-size="10" fill="#64748b">' + esc(r.label || r.bill_date || '') + '</text>';
        }).join('');
        const dots = rows.map(function (r, i) {
            const p1 = point(r, i, 'net_amount');
            const p2 = point(r, i, 'paid_amount');
            return '<circle cx="' + p1[0].toFixed(1) + '" cy="' + p1[1].toFixed(1) + '" r="3" fill="#2563eb"><title>' + esc(r.label) + ' Sales: ' + money(r.net_amount) + '</title></circle>' +
                   '<circle cx="' + p2[0].toFixed(1) + '" cy="' + p2[1].toFixed(1) + '" r="3" fill="#16a34a"><title>' + esc(r.label) + ' Collected: ' + money(r.paid_amount) + '</title></circle>';
        }).join('');
        target.innerHTML =
            '<svg class="svg-chart" viewBox="0 0 ' + width + ' ' + height + '" preserveAspectRatio="none">' +
                '<line x1="' + pad + '" y1="' + (height - pad) + '" x2="' + (width - pad) + '" y2="' + (height - pad) + '" stroke="#e2e8f0" stroke-width="1"/>' +
                '<line x1="' + pad + '" y1="' + pad + '" x2="' + pad + '" y2="' + (height - pad) + '" stroke="#e2e8f0" stroke-width="1"/>' +
                '<path d="' + path('net_amount') + '" fill="none" stroke="#2563eb" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>' +
                '<path d="' + path('paid_amount') + '" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>' +
                dots + labels +
            '</svg>' +
            '<div class="d-flex flex-wrap gap-2 mt-2"><span class="pill pill-blue">Sales</span><span class="pill pill-green">Collected</span><span class="dash-sub">Hover points for values</span></div>';
    }
    function renderBars(id, rows, labelKey, valueKey, emptyText, valueFormat) {
        const target = document.getElementById(id);
        if (!rows || !rows.length) {
            target.innerHTML = '<div class="empty-box">' + esc(emptyText || 'No data available.') + '</div>';
            return;
        }
        const max = Math.max(1, ...rows.map(function (r) { return toNumber(r[valueKey]); }));
        target.innerHTML = rows.map(function (r) {
            const pct = Math.max(3, (toNumber(r[valueKey]) / max) * 100);
            const value = valueFormat === 'qty' ? qty(r[valueKey]) : money(r[valueKey]);
            return '<div class="bar-row">' +
                '<div class="bar-label" title="' + esc(r[labelKey] || '-') + '">' + esc(r[labelKey] || '-') + '</div>' +
                '<div class="bar-track"><div class="bar-fill" style="width:' + pct.toFixed(2) + '%"></div></div>' +
                '<div class="bar-value">' + value + '</div>' +
            '</div>';
        }).join('');
    }
    function renderStockOverview(data) {
        const totalQty = Math.max(1, toNumber(data.total_stock_qty || 0));
        const rows = [
            { label: 'Total inward qty', value: data.total_stock_qty || 0, pct: 100 },
            { label: 'Available qty', value: data.available_stock_qty || 0, pct: Math.min(100, (toNumber(data.available_stock_qty || 0) / totalQty) * 100) },
            { label: 'Sold qty', value: data.sold_stock_qty || 0, pct: Math.min(100, (toNumber(data.sold_stock_qty || 0) / totalQty) * 100) },
            { label: 'Stock value', value: data.available_stock_value || 0, money: true, pct: 100 }
        ];
        const target = document.getElementById('stockOverviewBars');
        target.innerHTML = rows.map(function (r) {
            const pct = Math.max(0, Math.min(100, toNumber(r.pct)));
            return '<div class="bar-row">' +
                '<div class="bar-label">' + esc(r.label) + '</div>' +
                '<div class="bar-track"><div class="bar-fill" style="width:' + pct.toFixed(2) + '%"></div></div>' +
                '<div class="bar-value">' + (r.money ? money(r.value) : qty(r.value)) + '</div>' +
            '</div>';
        }).join('');
    }
    function renderLowStock(rows) {
        const target = document.getElementById('lowStockRows');
        if (!rows || !rows.length) {
            target.innerHTML = '<tr><td colspan="3" class="empty-box">No low stock alerts.</td></tr>';
            return;
        }
        target.innerHTML = rows.map(function (r) {
            const branch = (r.branch_name || '-') + (r.floor_name ? ' / ' + r.floor_name : '');
            return '<tr><td><div class="dash-title">' + esc(r.article_name || r.article_no || '-') + '</div><div class="dash-sub">' + esc(r.article_no || '-') + ' · Size ' + esc(r.size || '-') + ' · ' + esc(r.color || '-') + '</div></td><td><span class="pill pill-cyan">' + esc(branch) + '</span></td><td class="text-end"><span class="pill ' + (toNumber(r.available_qty) <= 0 ? 'pill-red' : 'pill-yellow') + '">' + qty(r.available_qty) + '</span></td></tr>';
        }).join('');
    }
    function renderPendingPayments(rows) {
        const target = document.getElementById('pendingPaymentRows');
        if (!target) { return; }
        if (!rows || !rows.length) {
            target.innerHTML = '<tr><td colspan="3" class="empty-box">No pending bills for selected filter.</td></tr>';
            return;
        }
        target.innerHTML = rows.map(function (r) {
            const branch = (r.branch_name && r.branch_name !== '-') ? (' · ' + r.branch_name + (r.floor_name ? ' / ' + r.floor_name : '')) : '';
            return '<tr><td><div class="dash-title">' + esc(r.bill_no || '-') + '</div><div class="dash-sub">' + esc(r.bill_date || '-') + (r.bill_time ? ' ' + esc(r.bill_time) : '') + branch + '</div></td><td><div class="dash-title">' + esc(r.customer_name || 'Walk-in Customer') + '</div><div class="dash-sub">' + esc(r.customer_mobile || 'No mobile') + '</div></td><td class="text-end amount-due">' + money(r.balance_amount) + '</td></tr>';
        }).join('');
    }
    function renderOutstanding(id, rows, nameKey) {
        const target = document.getElementById(id);
        if (!rows || !rows.length) {
            target.innerHTML = '<tr><td colspan="3" class="empty-box">No outstanding records.</td></tr>';
            return;
        }
        target.innerHTML = rows.map(function (r) {
            return '<tr><td><div class="dash-title">' + esc(r[nameKey] || '-') + '</div><div class="dash-sub">ID: ' + esc(r.id || '-') + '</div></td><td>' + esc(r.mobile || '-') + '</td><td class="text-end amount-due">' + money(r.balance_amount) + '</td></tr>';
        }).join('');
    }
    function renderBranchRows(rows) {
        const target = document.getElementById('branchRows');
        if (!rows || !rows.length) {
            target.innerHTML = '<tr><td colspan="4" class="empty-box">No branch sales found.</td></tr>';
            return;
        }
        target.innerHTML = rows.map(function (r) {
            const branch = (r.branch_name || '-') + (r.floor_name ? ' / ' + r.floor_name : '');
            return '<tr><td><span class="pill pill-cyan">' + esc(branch) + '</span></td><td class="text-end">' + parseInt(r.bill_count || 0, 10) + '</td><td class="text-end amount-dark">' + money(r.net_amount) + '</td><td class="text-end amount-due">' + money(r.balance_amount) + '</td></tr>';
        }).join('');
    }
    function renderUserRows(rows) {
        const target = document.getElementById('userRows');
        if (!rows || !rows.length) {
            target.innerHTML = '<tr><td colspan="4" class="empty-box">No user performance found.</td></tr>';
            return;
        }
        target.innerHTML = rows.map(function (r) {
            return '<tr><td><div class="dash-title">' + esc(r.user_name || '-') + '</div><div class="dash-sub">' + esc(r.role_name || '') + '</div></td><td class="text-end">' + parseInt(r.bill_count || 0, 10) + '</td><td class="text-end amount-dark">' + money(r.net_amount) + '</td><td class="text-end amount-good">' + money(r.paid_amount) + '</td></tr>';
        }).join('');
    }
    function renderActivities(rows) {
        const target = document.getElementById('activityRows');
        if (!rows || !rows.length) {
            target.innerHTML = '<div class="empty-box">No recent activities.</div>';
            return;
        }
        target.innerHTML = rows.map(function (r) {
            return '<div class="activity-item">' +
                '<div class="activity-dot"><i data-lucide="activity"></i></div>' +
                '<div><div class="dash-title">' + esc(r.module_name || 'System') + ' · ' + esc(r.action_type || '-') + '</div>' +
                '<div class="dash-sub">' + esc(r.user_name || 'System') + ' · ' + esc(r.created_at || '-') + '</div></div>' +
            '</div>';
        }).join('');
    }
    function renderAll(data) {
        fillBranches(data.branches || []);
        renderKpis(data.kpis || {});
        renderQuickActions(data.quick_actions || []);
        renderLineChart(data.sales_trend || []);
        renderBars('paymentMixChart', data.payment_mix || [], 'payment_method_name', 'paid_amount', 'No payment collections.', 'money');
        renderStockOverview(data.stock_overview || {});
        renderLowStock(data.low_stock_alerts || []);
        renderPendingPayments(data.pending_payments || []);
        renderOutstanding('customerDueRows', data.customer_outstanding || [], 'customer_name');
        renderOutstanding('supplierDueRows', data.supplier_outstanding || [], 'supplier_name');
        renderBranchRows(data.branch_performance || []);
        renderUserRows(data.user_performance || []);
        renderActivities(data.recent_activities || []);
        setText('trendLabel', data.period_label || 'Selected Period');
        refreshIcons();
    }
    async function loadDashboard() {
        try {
            const data = await apiGet(currentParams());
            if (!data.success) {
                showMessage('error', data.message || 'Unable to load dashboard.');
                return;
            }
            renderAll(data);
        } catch (error) {
            showMessage('error', 'Unable to connect to dashboard API.');
        }
    }
    function toggleDateInputs() {
        const custom = document.getElementById('periodFilter').value === 'custom';
        document.getElementById('dateFrom').classList.toggle('d-none', !custom);
        document.getElementById('dateTo').classList.toggle('d-none', !custom);
    }

    document.getElementById('periodFilter').addEventListener('change', function () {
        toggleDateInputs();
        loadDashboard();
    });
    ['branchFilter','dateFrom','dateTo'].forEach(function (id) {
        document.getElementById(id).addEventListener('change', loadDashboard);
    });
    document.getElementById('refreshDashboard').addEventListener('click', loadDashboard);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) loadDashboard();
    });
    window.addEventListener('focus', loadDashboard);

    toggleDateInputs();
    loadDashboard();
    reloadTimer = window.setInterval(function () {
        if (!document.hidden) loadDashboard();
    }, 60000);
})();
</script>
</body>
</html>
