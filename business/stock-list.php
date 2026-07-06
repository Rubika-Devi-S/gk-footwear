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

    .qr-mini {
        width: 26px;
        height: 26px;
        border-radius: 7px;
        display: inline-grid;
        place-items: center;
        background: repeating-linear-gradient(45deg,#0f172a 0 2px,#fff 2px 4px);
        border: 1px solid #cbd5e1;
        overflow: hidden;
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
                        <p>View firm-wise footwear stock, article, colour, barcode, available quantity, and batch details.</p>
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
                            <th style="width: 160px;">Action</th>
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
    const tableBody = document.getElementById('stockTableBody');
    const mobileCards = document.getElementById('stockMobileCards');
    const filterForm = document.getElementById('stockFilterForm');
    const detailModalEl = document.getElementById('stockDetailModal');
    const detailBody = document.getElementById('stockDetailBody');
    let detailModal = null;
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

    function apiGet(params) {
        const query = new URLSearchParams(params);
        return fetch(apiUrl + '?' + query.toString(), {
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

    function renderStats(stats) {
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

    function qrContent(value) {
        const text = String(value || '').trim();
        if (!text) return '<span class="mp-badge badge-qr">No QR</span>';
        const first = text.split(',')[0].trim();
        return '<span class="mp-badge badge-qr" title="' + escapeHtml(text) + '"><span class="qr-mini"></span> ' + escapeHtml(first) + '</span>';
    }

    function renderStock(items) {
        if (!items.length) {
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">No stock found.</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No stock found.</div>';
            return;
        }

        tableBody.innerHTML = items.map(function (item) {
            const branchName = item.branch_name || item.floor_name || '-';
            const articleName = item.article_name || '-';
            const itemId = parseInt(item.stock_item_id || 0, 10);
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
                    <td>${qrContent(item.barcode_values || '')}</td>
                    <td>${statusBadge(item.item_status)}</td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-view" data-id="${itemId}">View</button>
                    </td>
                </tr>
            `;
        }).join('');

        mobileCards.innerHTML = items.map(function (item) {
            const itemId = parseInt(item.stock_item_id || 0, 10);
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
                                ${qrContent(item.barcode_values || '')}
                            </div>
                            <div class="fw-bold mt-2">MRP: ${money.format(parseFloat(item.mrp_rate || 0))} · Selling: ${money.format(parseFloat(item.selling_rate || 0))}</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-view" data-id="${itemId}">View</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
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

    async function loadStock() {
        tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Loading stock...</td></tr>';
        mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">Loading stock...</div>';

        try {
            const data = await apiGet(filterParams());
            if (!data.success) {
                showMessage('error', data.message || 'Unable to load stock list.');
                return;
            }
            renderStats(data.stats || {});
            renderStock((data.stock && data.stock.items) ? data.stock.items : []);
            setPagination((data.stock && data.stock.pagination) ? data.stock.pagination : {});
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showMessage('error', 'Unable to connect to stock list API.');
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

            renderStats(data.stats || {});
            renderStock((data.stock && data.stock.items) ? data.stock.items : []);
            setPagination((data.stock && data.stock.pagination) ? data.stock.pagination : {});
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

    function renderMovements(movements) {
        if (!movements || !movements.length) {
            return '<div class="text-muted small">No movements found.</div>';
        }

        return `
            <div class="table-responsive mt-3">
                <table class="table mp-table mb-0">
                    <thead><tr><th>Type</th><th>Reference</th><th>In</th><th>Out</th><th>Balance</th><th>Date</th></tr></thead>
                    <tbody>
                        ${movements.map(function (m) {
                            return `<tr>
                                <td>${escapeHtml(m.movement_type || '-')}</td>
                                <td>${escapeHtml(m.reference_type || '-')} #${escapeHtml(m.reference_id || '-')}</td>
                                <td>${parseFloat(m.qty_in || 0).toFixed(2)}</td>
                                <td>${parseFloat(m.qty_out || 0).toFixed(2)}</td>
                                <td>${parseFloat(m.balance_qty || 0).toFixed(2)}</td>
                                <td>${escapeHtml(m.created_at || '-')}</td>
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

            const item = data.item || {};
            const barcode = item.barcode_values || '';
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
                <div class="mt-3 p-3 rounded-4 border bg-light">
                    <div class="mp-stat-label mb-1">Barcode / QR</div>
                    ${qrContent(barcode)}
                    <div class="mp-sub mt-2">${escapeHtml(barcode || 'No barcode generated')}</div>
                </div>
                <h6 class="fw-bold mt-4 mb-2">Stock Movement History</h6>
                ${renderMovements(data.movements || [])}
            `;
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            detailBody.innerHTML = '<div class="text-danger">Unable to fetch stock details.</div>';
        }
    }

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        currentPage = 1;
        loadStock();
    });

    ['stock_status','branch_id','supplier_id','category_id','brand_id','date_from','date_to'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', function () {
                currentPage = 1;
                loadStock();
            });
        }
    });

    document.getElementById('search').addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () {
            currentPage = 1;
            loadStock();
        }, 300);
    });

    document.getElementById('resetStockPage').addEventListener('click', function () {
        ['search','stock_status','branch_id','supplier_id','category_id','brand_id','date_from','date_to'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        currentPage = 1;
        loadStock();
    });

    document.getElementById('prevPage').addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            loadStock();
        }
    });

    document.getElementById('nextPage').addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            loadStock();
        }
    });

    document.addEventListener('click', function (event) {
        const viewBtn = event.target.closest('.js-view');
        if (viewBtn) {
            viewStock(viewBtn.dataset.id);
        }
    });

    loadInit();
})();
</script>
</body>
</html>
