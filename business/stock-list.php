<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'stock-list.php');

$pageTitle = 'Stock List';
$businessId = (int) current_business_id();

if ($businessId <= 0) {
    die('Business session missing. Please login again.');
}

$stockListCsrfToken = '';

if (function_exists('csrf_token')) {
    $stockListCsrfToken = (string) csrf_token();
} elseif (!empty($_SESSION['csrf_token'])) {
    $stockListCsrfToken = (string) $_SESSION['csrf_token'];
} else {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $stockListCsrfToken = (string) $_SESSION['csrf_token'];
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
    .master-page {
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

    .mp-hero h1 {
        font-size: 20px;
        font-weight: 800;
        margin: 0 0 3px;
        letter-spacing: -.02em;
        color: var(--text-main);
    }

    .mp-hero p {
        font-size: 11px;
        line-height: 1.35;
        margin: 0;
        color: var(--text-muted);
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
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
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
        color: var(--text-muted);
        font-weight: 700;
        line-height: 1.15;
    }

    .mp-stat-value {
        font-size: 18px;
        color: var(--text-main);
        font-weight: 800;
        margin: 1px 0;
        line-height: 1.05;
    }

    .mp-stat-sub {
        font-size: 10px;
        color: var(--text-muted);
        font-weight: 550;
        line-height: 1.15;
    }

    .mp-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        overflow: hidden;
    }

    .mp-card-head {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-soft);
    }

    .mp-card-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text-main);
        margin: 0 0 2px;
    }

    .mp-card-sub {
        font-size: 11px;
        color: var(--text-muted);
        margin: 0;
    }

    .mp-filter-input,
    .mp-filter-select {
        min-height: 32px;
        font-size: 11px;
        border-radius: 999px;
        padding: 5px 10px;
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
        background: linear-gradient(135deg, var(--brand-1), var(--brand-2));
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        flex: 0 0 auto;
    }

    .mp-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-main);
        line-height: 1.2;
    }

    .mp-sub {
        font-size: 10px;
        color: var(--text-muted);
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
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .status-active { background: #dcfce7; color: #15803d; }
    .status-inactive { background: #fee2e2; color: #b91c1c; }
    .status-low { background: #fef3c7; color: #b45309; }
    .badge-code { background: #dbeafe; color: #1d4ed8; }
    .badge-count { background: #fef3c7; color: #b45309; }
    .badge-type { background: #ede9fe; color: #6d28d9; }
    .badge-branch { background: #ecfeff; color: #0e7490; }
    .badge-stock { background: #dcfce7; color: #15803d; }
    .badge-stock-empty { background: #fee2e2; color: #b91c1c; }
    .badge-qr { background: #ecfeff; color: #0e7490; }

    .mp-action-btn {
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 700;
        padding: 5px 8px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        line-height: 1;
    }

    .mp-delete-btn {
        border-color: #fecaca;
        color: #b91c1c;
        background: #fff;
    }

    .mp-delete-btn:hover,
    .mp-delete-btn:focus {
        border-color: #dc2626;
        color: #fff;
        background: #dc2626;
    }

    .mp-mobile-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 10px;
    }

    .modal-title { font-size: 15px; font-weight: 750; }

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

    /* Real barcode preview used in Stock List and Stock Details modal.
       This matches the barcode generated in Stock Inward / barcode-print.php. */
    .barcode-cell { min-width: 180px; max-width: 230px; }
    .barcode-chip {
        width: 100%;
        max-width: 220px;
        border: 1px solid #bae6fd;
        background: linear-gradient(135deg, #f8fbff, #ecfeff);
        color: #0f172a;
        border-radius: 13px;
        padding: 6px 8px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 14px rgba(14, 116, 144, .08);
        overflow: hidden;
    }
    .barcode-preview {
        flex: 1 1 auto;
        min-width: 92px;
        max-width: 132px;
        overflow: hidden;
        background: #ffffff;
        border-radius: 8px;
        padding: 3px 4px;
        border: 1px solid #dbeafe;
    }
    .barcode-svg-mini,
    .barcode-svg-modal {
        width: 100%;
        display: block;
    }
    .barcode-svg-mini { height: 24px; }
    .barcode-code-wrap {
        flex: 0 0 auto;
        min-width: 58px;
        max-width: 80px;
        overflow: hidden;
    }
    .barcode-chip .barcode-code {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .01em;
        color: #0f172a;
        line-height: 1.1;
    }
    .barcode-chip .barcode-extra {
        display: inline-flex;
        margin-top: 3px;
        font-size: 9px;
        font-weight: 850;
        border-radius: 999px;
        padding: 2px 5px;
        background: #dbeafe;
        color: #1d4ed8;
        line-height: 1;
    }
    .barcode-empty {
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        color: #64748b;
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 10px;
        font-weight: 750;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .barcode-modal-card {
        border: 1px solid #bae6fd;
        background: linear-gradient(135deg, #f0f9ff, #ecfeff);
        border-radius: 18px;
        padding: 12px;
    }
    .barcode-modal-title {
        font-size: 10px;
        color: #0369a1;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: 8px;
    }
    .barcode-modal-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 10px;
    }
    .barcode-modal-code {
        border: 1px solid #93c5fd;
        background: #ffffff;
        color: #0f172a;
        border-radius: 14px;
        padding: 8px 10px;
        box-shadow: 0 5px 14px rgba(15,23,42,.06);
        overflow: hidden;
    }
    .barcode-modal-code .barcode-svg-modal { height: 40px; margin-bottom: 5px; }
    .barcode-modal-code .barcode-text {
        display:block;
        text-align:center;
        font-size:12px;
        font-weight:900;
        letter-spacing:.04em;
        white-space:nowrap;
        overflow:hidden;
        text-overflow:ellipsis;
    }

    .stock-detail-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    .stock-detail-box {
        border: 1px solid var(--border-soft);
        background: #f8fafc;
        border-radius: 14px;
        padding: 10px;
    }


    .live-refresh-pill {
        pointer-events: none;
        opacity: .95;
        border-style: dashed;
        font-size: 11px !important;
        min-width: 142px;
        justify-content: center;
    }
    .live-refresh-pill.is-syncing {
        color: #0369a1 !important;
        border-color: #7dd3fc !important;
        background: #e0f2fe !important;
    }
    .live-refresh-pill.is-ok {
        color: #15803d !important;
        border-color: #86efac !important;
        background: #dcfce7 !important;
    }
    .live-refresh-pill.is-error {
        color: #b91c1c !important;
        border-color: #fecaca !important;
        background: #fee2e2 !important;
    }

    @media (max-width: 991px) {
        .stock-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }

    @media (max-width: 767px) {
        .mp-hero { padding: 12px; }
        .mp-hero h1 { font-size: 19px; }
        .mp-stat-card { min-height: 64px; padding: 9px 10px; }
        .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; }
        .mp-stat-value { font-size: 16px; }
        .stock-detail-grid { grid-template-columns: 1fr; }
    }
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
                        <h1>Stock List</h1>
                        <p>View current firm-wise footwear stock, article, colour, available quantity, pricing, and batch details.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <a href="stock-inward.php" class="btn brand-gradient">
                            <i data-lucide="package-plus" style="width:14px;height:14px;"></i>
                            Stock Inward
                        </a>
                        <button type="button" id="resetStockPage" class="btn btn-outline-primary">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i>
                            Reset
                        </button>
                        <button type="button" id="stockLiveStatus" class="btn btn-outline-success live-refresh-pill" disabled>
                            <i data-lucide="radio" style="width:14px;height:14px;"></i>
                            Live: Starting
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="boxes"></i></div>
                        <div>
                            <div class="mp-stat-label">Total Items</div>
                            <div class="mp-stat-value" id="totalItems">0</div>
                            <div class="mp-stat-sub">Stock item rows</div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="package-check"></i></div>
                        <div>
                            <div class="mp-stat-label">Available Qty</div>
                            <div class="mp-stat-value" id="totalQty">0.00</div>
                            <div class="mp-stat-sub">Ready to bill</div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="indian-rupee"></i></div>
                        <div>
                            <div class="mp-stat-label">Stock Value</div>
                            <div class="mp-stat-value" id="stockValue">₹0.00</div>
                            <div class="mp-stat-sub">Selling value</div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c;"><i data-lucide="triangle-alert"></i></div>
                        <div>
                            <div class="mp-stat-label">Low / Out</div>
                            <div class="mp-stat-value"><span id="lowStockItems">0</span> / <span id="outStockItems">0</span></div>
                            <div class="mp-stat-sub">Needs attention</div>
                        </div>
                    </article>
                </div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div>
                                <h2 class="mp-card-title">Stock Item List</h2>
                                <p class="mp-card-sub">Role based branch visibility is applied automatically from assigned firm access.</p>
                            </div>

                            <form method="get" id="stockFilterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                                <input type="text" name="search" id="search" class="form-control mp-filter-input" placeholder="Search article / barcode / colour / batch">
                                <select name="stock_status" id="stock_status" class="form-select mp-filter-select">
                                    <option value="">All Stock</option>
                                    <option value="available">Available</option>
                                    <option value="low">Low Stock</option>
                                    <option value="out">Out of Stock</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                                <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">Filter</button>
                            </form>
                        </div>

                        <div class="row g-2">
                            <div class="col-12 col-md-6 col-xl-2">
                                <select name="branch_id" id="branch_id" class="form-select mp-filter-select">
                                    <option value="">All Branch/Firm</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <select name="supplier_id" id="supplier_id" class="form-select mp-filter-select">
                                    <option value="">All Suppliers</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <select name="category_id" id="category_id" class="form-select mp-filter-select">
                                    <option value="">All Categories</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <select name="brand_id" id="brand_id" class="form-select mp-filter-select">
                                    <option value="">All Brands</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <input type="date" name="date_from" id="date_from" class="form-control mp-filter-input">
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <input type="date" name="date_to" id="date_to" class="form-control mp-filter-input">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table mb-0">
                        <thead>
                        <tr>
                            <th>Article</th>
                            <th>Branch / Batch</th>
                            <th>Category / Brand</th>
                            <th>Colour / Size</th>
                            <th>Available</th>
                            <th>MRP</th>
                            <th>Selling</th>
                            <th>QR / Barcode</th>
                            <th>Status</th>
                            <th style="width: 120px;">Action</th>
                        </tr>
                        </thead>
                        <tbody id="stockTableBody">
                        <tr>
                            <td colspan="10" class="text-center text-muted py-4">Loading stock...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="stockMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading stock...</div>
                </div>

                <div class="px-3 pb-3 d-flex flex-column flex-md-row justify-content-md-between gap-2 align-items-md-center">
                    <div class="mp-sub" id="paginationInfo">Showing 0 items</div>
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

<div class="modal fade" id="stockDetailModal" tabindex="-1" aria-labelledby="stockDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="stockDetailTitle">Stock Details</h5>
                    <div class="mp-sub">Batch, supplier, barcode, and movement information.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="stockDetailBody">
                <div class="text-center text-muted py-4">Loading details...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>

<script>
(function () {
    'use strict';

    const apiUrl = 'api/stock-list-api.php';
    const deleteEndpoint = apiUrl;
    const csrfToken = <?= json_encode($stockListCsrfToken, JSON_UNESCAPED_SLASHES) ?>;
    const tableBody = document.getElementById('stockTableBody');
    const mobileCards = document.getElementById('stockMobileCards');
    const filterForm = document.getElementById('stockFilterForm');
    const detailModalEl = document.getElementById('stockDetailModal');
    const detailBody = document.getElementById('stockDetailBody');
    let detailModal = null;
    let searchTimer = null;
    let currentPage = 1;
    let totalPages = 1;
    let stockLoading = false;
    let autoRefreshTimer = null;
    const autoRefreshMs = 10000;
    const liveStatus = document.getElementById('stockLiveStatus');

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

    function apiGet(params) {
        const requestParams = Object.assign({}, params || {}, { _live_ts: Date.now() });
        const query = new URLSearchParams(requestParams);
        return fetch(apiUrl + '?' + query.toString(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        }).then(async function (response) {
            const raw = await response.text();
            let data;

            try {
                data = JSON.parse(raw);
            } catch (error) {
                throw new Error('Invalid stock API response: ' + raw.substring(0, 180));
            }

            if (!response.ok) {
                throw new Error(data.message || ('Stock API HTTP ' + response.status));
            }

            return data;
        });
    }

    function setLiveStatus(state, message) {
        if (!liveStatus) return;
        liveStatus.classList.remove('is-syncing', 'is-ok', 'is-error');
        liveStatus.classList.add(state === 'error' ? 'is-error' : (state === 'syncing' ? 'is-syncing' : 'is-ok'));
        liveStatus.innerHTML = '<i data-lucide="' + (state === 'error' ? 'wifi-off' : (state === 'syncing' ? 'refresh-cw' : 'radio')) + '" style="width:14px;height:14px;"></i> ' + escapeHtml(message);
        if (window.lucide) window.lucide.createIcons();
    }

    function liveTimeText() {
        const now = new Date();
        return now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    function startAutoRefresh() {
        if (autoRefreshTimer) {
            window.clearInterval(autoRefreshTimer);
        }

        autoRefreshTimer = window.setInterval(function () {
            if (document.hidden) return;
            if (detailModalEl && detailModalEl.classList.contains('show')) return;
            loadStock(true);
        }, autoRefreshMs);
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

    function firstValue(source, keys, fallback) {
        source = source || {};
        for (let i = 0; i < keys.length; i++) {
            const value = source[keys[i]];
            if (value !== undefined && value !== null && String(value).trim() !== '') {
                return value;
            }
        }
        return fallback;
    }

    function numericValue(source, keys, fallback) {
        const value = firstValue(source, keys, fallback ?? 0);
        const number = parseFloat(value);
        return Number.isFinite(number) ? number : parseFloat(fallback || 0);
    }

    function normalizeStockItem(raw) {
        raw = raw || {};

        const normalized = Object.assign({}, raw, {
            stock_item_id: parseInt(firstValue(raw, ['stock_item_id','item_id','id'], 0), 10) || 0,
            business_id: parseInt(firstValue(raw, ['business_id'], 0), 10) || 0,
            branch_id: parseInt(firstValue(raw, ['branch_id','shop_id'], 0), 10) || 0,
            batch_id: parseInt(firstValue(raw, ['batch_id','stock_batch_id'], 0), 10) || 0,
            supplier_id: parseInt(firstValue(raw, ['supplier_id','vendor_id'], 0), 10) || 0,
            category_id: parseInt(firstValue(raw, ['category_id'], 0), 10) || 0,
            brand_id: parseInt(firstValue(raw, ['brand_id'], 0), 10) || 0,

            article_no: String(firstValue(raw, ['article_no','product_code','article_code','sku','item_code'], '-')),
            article_name: String(firstValue(raw, ['article_name','product_name','item_name','name'], '-')),
            category_name: String(firstValue(raw, ['category_name','category'], '-')),
            brand_name: String(firstValue(raw, ['brand_name','brand'], '-')),
            size: String(firstValue(raw, ['size','product_size','variant_size'], '-')),
            color: String(firstValue(raw, ['color','colour','product_color','product_colour'], '-')),

            branch_name: String(firstValue(raw, ['branch_name','shop_name','firm_name'], '-')),
            floor_name: String(firstValue(raw, ['floor_name','branch_floor','shop_code'], '')),
            batch_no: String(firstValue(raw, ['batch_no','inward_no','stock_inward_no'], '-')),
            inward_no: String(firstValue(raw, ['inward_no','batch_no','stock_inward_no'], '-')),
            inward_date: String(firstValue(raw, ['inward_date','stock_date','created_date','created_at'], '-')),
            invoice_no: String(firstValue(raw, ['invoice_no','supplier_invoice_no','bill_no'], '-')),
            supplier_name: String(firstValue(raw, ['supplier_name','vendor_name'], '-')),
            supplier_mobile: String(firstValue(raw, ['supplier_mobile','supplier_phone','vendor_mobile'], '-')),

            qty: numericValue(raw, ['qty','inward_qty','quantity','stock_qty','total_qty'], 0),
            available_qty: numericValue(raw, ['available_qty','current_stock','available_stock','balance_qty','stock_balance','closing_stock'], 0),
            purchase_rate: numericValue(raw, ['purchase_rate','purchase_price','cost_price','unit_cost'], 0),
            mrp_rate: numericValue(raw, ['mrp_rate','mrp','maximum_retail_price'], 0),
            discount_value: numericValue(raw, ['discount_value','discount','product_discount'], 0),
            selling_rate: numericValue(raw, ['selling_rate','selling_price','sale_price','unit_price','price'], 0),

            item_status: String(firstValue(raw, ['item_status','stock_status','status'], 'active')).toLowerCase(),
            barcode_values: String(firstValue(raw, ['barcode_values','barcodes','barcode_value','barcode','qr_code'], '')),
            barcode_value: String(firstValue(raw, ['barcode_value','barcode','qr_code','latest_qr_code'], ''))
        });

        if (normalized.selling_rate <= 0 && normalized.mrp_rate > 0) {
            normalized.selling_rate = Math.max(0, normalized.mrp_rate - normalized.discount_value);
        }

        if (normalized.available_qty <= 0 && normalized.item_status === 'active' && normalized.qty > 0) {
            const soldQty = numericValue(raw, ['sold_qty','total_sold_qty','out_qty'], 0);
            normalized.available_qty = Math.max(0, normalized.qty - soldQty);
        }

        if (normalized.available_qty <= 0 && normalized.item_status === 'active') {
            normalized.item_status = 'out_of_stock';
        }

        return normalized;
    }

    function normalizeStats(stats) {
        stats = stats || {};
        return {
            total_items: numericValue(stats, ['total_items','total_records','item_count','products_count'], 0),
            total_qty: numericValue(stats, ['total_qty','available_qty','current_stock','total_available_qty'], 0),
            total_stock_value: numericValue(stats, ['total_stock_value','stock_value','selling_stock_value','inventory_value'], 0),
            low_stock_items: numericValue(stats, ['low_stock_items','low_stock_count'], 0),
            out_stock_items: numericValue(stats, ['out_stock_items','out_of_stock_items','out_stock_count'], 0)
        };
    }

    function renderStats(stats) {
        stats = normalizeStats(stats);
        document.getElementById('totalItems').textContent = parseInt(stats.total_items || 0, 10);
        document.getElementById('totalQty').textContent = parseFloat(stats.total_qty || 0).toFixed(2);
        document.getElementById('stockValue').textContent = money.format(parseFloat(stats.total_stock_value || 0));
        document.getElementById('lowStockItems').textContent = parseInt(stats.low_stock_items || 0, 10);
        document.getElementById('outStockItems').textContent = parseInt(stats.out_stock_items || 0, 10);
    }

    function itemInitial(item) {
        const text = String(item.article_no || item.article_name || 'S').trim();
        return escapeHtml(text.substring(0, 1).toUpperCase() || 'S');
    }

    function stockBadge(item) {
        const qty = parseFloat(item.available_qty || 0);
        if (qty <= 0 || item.item_status === 'out_of_stock') {
            return '<span class="mp-badge badge-stock-empty">Out</span>';
        }
        if (qty <= 5) {
            return '<span class="mp-badge status-low">' + qty.toFixed(2) + '</span>';
        }
        return '<span class="mp-badge badge-stock">' + qty.toFixed(2) + '</span>';
    }

    function statusBadge(status) {
        if (status === 'active') return '<span class="mp-badge status-active">Active</span>';
        if (status === 'out_of_stock') return '<span class="mp-badge badge-stock-empty">Out of Stock</span>';
        return '<span class="mp-badge status-inactive">' + escapeHtml(status || '-') + '</span>';
    }

    function normalizeBarcodeList(value) {
        const text = String(value || '').trim();
        if (!text || text === '-' || text.toLowerCase() === 'no qr') return [];
        const seen = {};
        return text
            .split(/[|,\n]+/)
            .map(function (v) { return v.trim(); })
            .filter(function (v) {
                if (!v || seen[v]) return false;
                seen[v] = true;
                return true;
            });
    }

    function stockBarcodeValue(item) {
        item = item || {};
        const candidates = [
            item.barcode_values,
            item.barcode_value,
            item.qr_code,
            item.latest_qr_code,
            item.stock_barcode,
            item.generated_barcode
        ];
        for (let i = 0; i < candidates.length; i++) {
            const value = String(candidates[i] || '').trim();
            if (value && value !== '-' && value.toLowerCase() !== 'no qr') return value;
        }
        return '';
    }

    function code128Svg(value, className, height) {
        value = String(value || '').trim();
        if (!value) return '';

        const patterns = [
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232','2331112'
        ];

        const codes = [104];
        let checksum = 104;
        let position = 1;

        for (let i = 0; i < value.length; i++) {
            let ord = value.charCodeAt(i);
            if (ord < 32 || ord > 126) ord = 32;
            const code = ord - 32;
            codes.push(code);
            checksum += code * position;
            position++;
        }

        codes.push(checksum % 103);
        codes.push(106);

        const moduleWidth = 1.45;
        const quiet = 10;
        let x = quiet;
        let bars = '';

        codes.forEach(function (code) {
            const pattern = patterns[code] || patterns[0];
            let black = true;
            for (let i = 0; i < pattern.length; i++) {
                const width = parseInt(pattern.charAt(i), 10) * moduleWidth;
                if (black) {
                    bars += '<rect x="' + x.toFixed(2) + '" y="0" width="' + width.toFixed(2) + '" height="' + height + '" fill="#000"/>';
                }
                x += width;
                black = !black;
            }
        });

        const totalWidth = x + quiet;
        return '<svg class="' + escapeHtml(className || 'barcode-svg-mini') + '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + totalWidth.toFixed(2) + ' ' + height + '" preserveAspectRatio="none">' + bars + '</svg>';
    }

    function qrContent(value) {
        const list = normalizeBarcodeList(value);
        if (!list.length) {
            return '<span class="barcode-empty"><i data-lucide="barcode" style="width:12px;height:12px"></i> No Barcode</span>';
        }
        const first = list[0];
        const all = list.join(', ');
        const extra = list.length > 1 ? '<span class="barcode-extra">+' + (list.length - 1) + '</span>' : '';
        return '<span class="barcode-chip" title="' + escapeHtml(all) + '">' +
            '<span class="barcode-preview">' + code128Svg(first, 'barcode-svg-mini', 28) + '</span>' +
            '<span class="barcode-code-wrap"><span class="barcode-code">' + escapeHtml(first) + '</span>' + extra + '</span>' +
            '</span>';
    }

    function barcodeModalContent(value) {
        const list = normalizeBarcodeList(value);
        if (!list.length) {
            return '<div class="barcode-modal-card"><div class="barcode-modal-title">Barcode / QR Code</div><span class="barcode-empty"><i data-lucide="barcode" style="width:12px;height:12px"></i> No barcode generated</span></div>';
        }
        return '<div class="barcode-modal-card">' +
            '<div class="barcode-modal-title">Barcode / QR Code</div>' +
            '<div class="barcode-modal-list">' +
            list.map(function (code) {
                return '<div class="barcode-modal-code">' + code128Svg(code, 'barcode-svg-modal', 44) + '<span class="barcode-text">' + escapeHtml(code) + '</span></div>';
            }).join('') +
            '</div></div>';
    }

    function renderStock(items) {
        items = Array.isArray(items) ? items.map(normalizeStockItem) : [];
        if (!items.length) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No stock found.</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No stock found.</div>';
            return;
        }

        tableBody.innerHTML = items.map(function (item) {
            const branchName = item.branch_name || item.floor_name || '-';
            const articleName = item.article_name || '-';
            const itemId = parseInt(item.stock_item_id || 0, 10);
            const availableQty = parseFloat(item.available_qty || 0);
            const canDelete = availableQty <= 0;
            const deleteButton = canDelete
                ? '<button type="button" class="btn btn-sm mp-action-btn mp-delete-btn js-delete" data-id="' + itemId + '" data-label="' + escapeHtml(item.article_no || item.article_name || 'Product') + '"><i data-lucide="trash-2" style="width:12px;height:12px;"></i> Delete</button>'
                : '';
            return `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="mp-avatar">${itemInitial(item)}</div>
                            <div>
                                <div class="mp-title">${escapeHtml(item.article_no || '-')}</div>
                                <div class="mp-sub">${escapeHtml(articleName)}</div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="mp-badge badge-branch">${escapeHtml(branchName)}</span>
                        <div class="mp-sub mt-1">${escapeHtml(item.batch_no || '-')}</div>
                    </td>
                    <td>
                        <span class="mp-badge badge-type">${escapeHtml(item.category_name || 'No Category')}</span>
                        <div class="mp-sub mt-1">${escapeHtml(item.brand_name || 'No Brand')}</div>
                    </td>
                    <td>
                        <span class="mp-badge badge-code">${escapeHtml(item.color || '-')}</span>
                        <div class="mp-sub mt-1">Size: ${escapeHtml(item.size || '-')}</div>
                    </td>
                    <td>${stockBadge(item)}</td>
                    <td class="fw-bold">${money.format(parseFloat(item.mrp_rate || 0))}</td>
                    <td class="fw-bold">${money.format(parseFloat(item.selling_rate || 0))}</td>
                    <td class="barcode-cell">${qrContent(stockBarcodeValue(item))}</td>
                    <td>${statusBadge(item.item_status)}</td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-view" data-id="${itemId}">View</button>
                            ${deleteButton}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        mobileCards.innerHTML = items.map(function (item) {
            const itemId = parseInt(item.stock_item_id || 0, 10);
            const availableQty = parseFloat(item.available_qty || 0);
            const canDelete = availableQty <= 0;
            const deleteButton = canDelete
                ? '<button type="button" class="btn btn-sm mp-action-btn mp-delete-btn js-delete" data-id="' + itemId + '" data-label="' + escapeHtml(item.article_no || item.article_name || 'Product') + '"><i data-lucide="trash-2" style="width:12px;height:12px;"></i> Delete</button>'
                : '';
            return `
                <div class="mp-mobile-card">
                    <div class="d-flex gap-2">
                        <div class="mp-avatar">${itemInitial(item)}</div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="mp-title">${escapeHtml(item.article_no || '-')} - ${escapeHtml(item.article_name || '-')}</div>
                                    <div class="mp-sub">${escapeHtml(item.branch_name || '-')} · ${escapeHtml(item.batch_no || '-')}</div>
                                </div>
                                ${statusBadge(item.item_status)}
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="mp-badge badge-type">${escapeHtml(item.category_name || 'No Category')}</span>
                                <span class="mp-badge badge-code">${escapeHtml(item.brand_name || 'No Brand')}</span>
                                <span class="mp-badge badge-code">Colour: ${escapeHtml(item.color || '-')}</span>
                                ${stockBadge(item)}
                                ${qrContent(stockBarcodeValue(item))}
                            </div>
                            <div class="fw-bold mt-2">MRP: ${money.format(parseFloat(item.mrp_rate || 0))} · Selling: ${money.format(parseFloat(item.selling_rate || 0))}</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-view" data-id="${itemId}">View</button>
                                ${deleteButton}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function extractStockItems(data) {
        const candidates = [
            data && data.stock && data.stock.items,
            data && data.stock && data.stock.rows,
            data && data.items,
            data && data.rows,
            data && data.data && data.data.items,
            data && data.data && data.data.rows
        ];

        for (let i = 0; i < candidates.length; i++) {
            if (Array.isArray(candidates[i])) {
                return candidates[i].map(normalizeStockItem);
            }
        }

        return [];
    }

    function extractPagination(data) {
        return (data && data.stock && data.stock.pagination)
            || (data && data.pagination)
            || (data && data.data && data.data.pagination)
            || {};
    }

    function extractStats(data) {
        return normalizeStats(
            (data && data.stats)
            || (data && data.stock && data.stock.stats)
            || (data && data.data && data.data.stats)
            || {}
        );
    }

    function filterParams() {
        return {
            action: 'list',
            search: document.getElementById('search').value,
            stock_status: document.getElementById('stock_status').value,
            branch_id: document.getElementById('branch_id').value,
            supplier_id: document.getElementById('supplier_id').value,
            category_id: document.getElementById('category_id').value,
            brand_id: document.getElementById('brand_id').value,
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
        document.getElementById('paginationInfo').textContent = 'Page ' + currentPage + ' of ' + totalPages + ' · Total ' + total + ' items';
        document.getElementById('prevPage').disabled = currentPage <= 1;
        document.getElementById('nextPage').disabled = currentPage >= totalPages;
    }

    async function loadStock(silent) {
        silent = Boolean(silent);
        if (stockLoading) return;
        stockLoading = true;

        if (!silent) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Loading stock...</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">Loading stock...</div>';
        }

        setLiveStatus('syncing', silent ? 'Live: Checking' : 'Loading');

        try {
            const data = await apiGet(filterParams());
            if (!data.success) {
                if (!silent) {
                    showMessage('error', data.message || 'Unable to load stock list.');
                }
                setLiveStatus('error', 'Live: API Error');
                return;
            }

            renderStats(extractStats(data));
            renderStock(extractStockItems(data));
            setPagination(extractPagination(data));
            setLiveStatus('ok', 'Live: ' + liveTimeText());
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            if (!silent) {
                showMessage('error', 'Unable to connect to stock list API.');
            }
            setLiveStatus('error', 'Live: Offline');
        } finally {
            stockLoading = false;
        }
    }

    async function loadInit() {
        try {
            const data = await apiGet({ action: 'init' });
            if (!data.success) {
                showMessage('error', data.message || 'Unable to initialize stock list.');
                return;
            }

            fillSelect('branch_id', data.branches || [], 'branch_id', function (row) {
                return (row.branch_name || '-') + (row.floor_name ? ' / ' + row.floor_name : '');
            }, 'All Branch/Firm');
            fillSelect('supplier_id', data.suppliers || [], 'supplier_id', function (row) { return row.supplier_name || '-'; }, 'All Suppliers');
            fillSelect('category_id', data.categories || [], 'category_id', function (row) { return row.category_name || '-'; }, 'All Categories');
            fillSelect('brand_id', data.brands || [], 'brand_id', function (row) { return row.brand_name || '-'; }, 'All Brands');

            renderStats(extractStats(data));
            renderStock(extractStockItems(data));
            setPagination(extractPagination(data));
            setLiveStatus('ok', 'Live: ' + liveTimeText());
            startAutoRefresh();
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showMessage('error', 'Unable to initialize stock list page.');
        }
    }

    function getDetailModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!detailModal) {
                detailModal = new window.bootstrap.Modal(detailModalEl);
            }
            return detailModal;
        }
        return null;
    }

    function openDetailModal() {
        const modal = getDetailModal();
        if (modal) modal.show();
    }

    function detailBox(label, value) {
        return '<div class="stock-detail-box"><div class="mp-stat-label">' + escapeHtml(label) + '</div><div class="mp-title mt-1">' + value + '</div></div>';
    }

    function movementTypeLabel(type) {
        const key = String(type || '').toLowerCase().trim();
        const labels = {
            inward: 'Stock Inward',
            stock_inward: 'Stock Inward',
            reference: 'Stock Entry',
            purchase: 'Purchase Stock',
            purchase_inward: 'Purchase Stock',
            opening: 'Opening Stock',
            opening_stock: 'Opening Stock',
            sale: 'Sale',
            sales: 'Sale',
            bill: 'Sale Bill',
            sales_return: 'Sales Return',
            return: 'Return',
            adjustment: 'Adjustment',
            stock_adjustment: 'Stock Adjustment',
            transfer: 'Stock Transfer',
            cancelled: 'Cancelled'
        };
        return labels[key] || (type ? String(type).replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }) : '-');
    }

    function movementSource(m) {
        const refType = String(m.reference_type || '').toLowerCase().trim();
        const refNo = m.reference_no || m.inward_no || m.invoice_no || m.bill_no || m.batch_no || '';
        const refId = m.reference_id || '';

        let source = movementTypeLabel(refType || m.movement_type || '');
        if (!source || source === '-') source = 'Stock Entry';

        const referenceText = refNo ? escapeHtml(refNo) : (refId ? '#' + escapeHtml(refId) : '');
        const userName = m.created_by_name || m.user_name || m.created_by || '';

        return '<div class="fw-bold">' + escapeHtml(source) + '</div>' +
            (referenceText ? '<div class="mp-sub">' + referenceText + '</div>' : '') +
            (userName ? '<div class="mp-sub">By: ' + escapeHtml(userName) + '</div>' : '');
    }

    function renderMovements(movements) {
        if (!movements || !movements.length) {
            return '<div class="text-muted small">No movements found.</div>';
        }

        return `
            <div class="table-responsive mt-3">
                <table class="table mp-table mb-0">
                    <thead><tr><th>Type</th><th>Stock Entry</th><th>In</th><th>Out</th><th>Balance</th><th>Date</th></tr></thead>
                    <tbody>
                        ${movements.map(function (m) {
                            return `<tr>
                                <td><span class="mp-badge badge-type">${escapeHtml(movementTypeLabel(m.movement_type || m.reference_type || '-'))}</span></td>
                                <td>${movementSource(m)}</td>
                                <td>${parseFloat(m.qty_in || 0).toFixed(2)}</td>
                                <td>${parseFloat(m.qty_out || 0).toFixed(2)}</td>
                                <td>${parseFloat(m.balance_qty || 0).toFixed(2)}</td>
                                <td>${escapeHtml(m.created_at || m.entry_date || '-')}</td>
                            </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    async function viewStock(stockItemId) {
        detailBody.innerHTML = '<div class="text-center text-muted py-4">Loading details...</div>';
        openDetailModal();

        try {
            const data = await apiGet({ action: 'get', stock_item_id: stockItemId });
            if (!data.success) {
                detailBody.innerHTML = '<div class="text-danger">' + escapeHtml(data.message || 'Unable to load details.') + '</div>';
                return;
            }

            const item = normalizeStockItem(data.item || {});
            const barcode = stockBarcodeValue(item);
            detailBody.innerHTML = `
                <div class="stock-detail-grid">
                    ${detailBox('Article', escapeHtml(item.article_no || '-') + '<div class="mp-sub">' + escapeHtml(item.article_name || '-') + '</div>')}
                    ${detailBox('Branch / Firm', escapeHtml(item.branch_name || '-') + '<div class="mp-sub">' + escapeHtml(item.floor_name || '-') + '</div>')}
                    ${detailBox('Batch', escapeHtml(item.batch_no || '-') + '<div class="mp-sub">' + escapeHtml(item.inward_date || '-') + '</div>')}
                    ${detailBox('Supplier', escapeHtml(item.supplier_name || '-') + '<div class="mp-sub">' + escapeHtml(item.supplier_mobile || '-') + '</div>')}
                    ${detailBox('Category / Brand', escapeHtml(item.category_name || '-') + '<div class="mp-sub">' + escapeHtml(item.brand_name || '-') + '</div>')}
                    ${detailBox('Size / Colour', escapeHtml(item.size || '-') + '<div class="mp-sub">' + escapeHtml(item.color || '-') + '</div>')}
                    ${detailBox('Qty / Available', parseFloat(item.qty || 0).toFixed(2) + '<div class="mp-sub">Available: ' + parseFloat(item.available_qty || 0).toFixed(2) + '</div>')}
                    ${detailBox('MRP / Selling', money.format(parseFloat(item.mrp_rate || 0)) + '<div class="mp-sub">Selling: ' + money.format(parseFloat(item.selling_rate || 0)) + '</div>')}
                </div>
                <div class="mt-3">
                    ${barcodeModalContent(barcode)}
                </div>
                <h6 class="fw-bold mt-4 mb-2">Stock Movement History</h6>
                ${renderMovements(data.movements || [])}
            `;
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            detailBody.innerHTML = '<div class="text-danger">Unable to fetch stock details.</div>';
        }
    }

    async function deleteOutOfStockProduct(button) {
        const stockItemId = parseInt(button.dataset.id || '0', 10);
        const productLabel = String(button.dataset.label || 'this product');

        if (stockItemId <= 0) {
            showMessage('error', 'Invalid product selected.');
            return;
        }

        const confirmed = window.confirm(
            'Permanently delete "' + productLabel + '"?\n\n'
            + 'This option is available only because its stock is zero. '
            + 'This action cannot be undone.'
        );

        if (!confirmed) return;

        const originalHtml = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Deleting';

        try {
            const formData = new FormData();
            formData.append('action', 'delete_out_of_stock');
            formData.append('stock_item_id', String(stockItemId));

            // Support all CSRF field names used across the existing project.
            formData.append('csrf_token', csrfToken);
            formData.append('_token', csrfToken);
            formData.append('csrf', csrfToken);

            const response = await fetch(deleteEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.message || 'Unable to delete the product.');
            }

            showMessage('success', data.message || 'Out-of-stock product permanently deleted.');

            if (detailModalEl && detailModalEl.classList.contains('show')) {
                const modal = getDetailModal();
                if (modal) modal.hide();
            }

            await loadStock(false);
        } catch (error) {
            showMessage('error', error.message || 'Unable to delete the product.');
            button.disabled = false;
            button.innerHTML = originalHtml;
            if (window.lucide) window.lucide.createIcons();
        }
    }

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        currentPage = 1;
        loadStock(false);
    });

    ['stock_status','branch_id','supplier_id','category_id','brand_id','date_from','date_to'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function () {
                currentPage = 1;
                loadStock(false);
            });
        }
    });

    document.getElementById('search').addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            currentPage = 1;
            loadStock(false);
        }, 300);
    });

    document.getElementById('resetStockPage').addEventListener('click', function () {
        ['search','stock_status','branch_id','supplier_id','category_id','brand_id','date_from','date_to'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        currentPage = 1;
        loadStock(false);
    });

    document.getElementById('prevPage').addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadStock(false);
        }
    });

    document.getElementById('nextPage').addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            loadStock(false);
        }
    });

    document.addEventListener('click', function (event) {
        const viewBtn = event.target.closest('.js-view');
        if (viewBtn) {
            viewStock(viewBtn.dataset.id);
            return;
        }

        const deleteBtn = event.target.closest('.js-delete');
        if (deleteBtn) {
            deleteOutOfStockProduct(deleteBtn);
        }
    });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            loadStock(true);
        }
    });

    window.addEventListener('focus', function () {
        loadStock(true);
    });

    window.addEventListener('beforeunload', function () {
        if (autoRefreshTimer) {
            window.clearInterval(autoRefreshTimer);
        }
    });

    loadInit();
})();
</script>
</body>
</html>
