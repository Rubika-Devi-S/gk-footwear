<?php
/**
 * Universal Footwear POS - Return & Exchange Module
 * Place at: business/return-exchange.php
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

if (function_exists('require_business_login')) {
    require_business_login();
}

if (function_exists('require_page_access')) {
    require_page_access($conn, 'return-exchange.php');
}

$pageTitle = 'Return & Exchange';
$businessId = function_exists('current_business_id') ? (int) current_business_id() : (int)($_SESSION['business_id'] ?? 0);

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function re_csrf_field() {
    if (function_exists('csrf_field')) {
        return csrf_field();
    }

    if (function_exists('csrf_token')) {
        return '<input type="hidden" name="csrf_token" id="returnExchangeCsrfToken" value="' . e(csrf_token()) . '">';
    }

    return '<input type="hidden" name="csrf_token" id="returnExchangeCsrfToken" value="">';
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
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>

    <style>
    .master-page{font-family:"Inter","Segoe UI",Arial,sans-serif;font-size:12px;font-weight:500}
    .mp-hero{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.06);padding:14px 16px}
    .mp-hero h1{font-size:20px;font-weight:800;margin:0 0 3px;letter-spacing:-.02em;color:var(--text-main,#0f172a)}
    .mp-hero p{font-size:11px;line-height:1.35;margin:0;color:var(--text-muted,#64748b);font-weight:500}
    .mp-hero .btn{font-size:11px;padding:7px 11px;min-height:32px;border-radius:999px;font-weight:700}
    .mp-card{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.06);overflow:hidden}
    .mp-card-head{padding:12px 14px;border-bottom:1px solid var(--border-soft,#dbe4f0)}
    .mp-card-title{font-size:15px;font-weight:800;color:var(--text-main,#0f172a);margin:0 0 2px}
    .mp-card-sub,.mp-sub{font-size:11px;color:var(--text-muted,#64748b);margin:0}
    .mp-sub{font-size:10px;line-height:1.25}
    .mp-filter-input,.mp-filter-select{min-height:34px;font-size:12px;border-radius:999px;padding:6px 12px}
    .mp-table th{font-size:10px;font-weight:750;padding:9px 10px;white-space:nowrap;background:#f1f5f9;color:#0f172a;text-transform:uppercase;letter-spacing:.04em;border-bottom:0}
    .mp-table td{font-size:11px;padding:9px 10px;vertical-align:middle}
    .mp-title{font-size:12px;font-weight:800;color:var(--text-main,#0f172a);line-height:1.2}
    .mp-badge{border-radius:999px;padding:5px 8px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:4px;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .badge-code{background:#dbeafe;color:#1d4ed8}.badge-count{background:#fef3c7;color:#b45309}.badge-type{background:#ede9fe;color:#6d28d9}.badge-branch{background:#ecfeff;color:#0e7490}.badge-money{background:#dcfce7;color:#15803d}.badge-due{background:#fee2e2;color:#b91c1c}.status-active{background:#dcfce7;color:#15803d}.status-partial{background:#fef3c7;color:#b45309}.status-returned{background:#fee2e2;color:#b91c1c}.status-paid{background:#dcfce7;color:#15803d}.status-pending{background:#e0f2fe;color:#0369a1}
    .amount-positive{color:#15803d;font-weight:800}.amount-due{color:#b91c1c;font-weight:800}.amount-dark{color:#0f172a;font-weight:800}
    .return-tabs{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
    .return-tab-btn{border:1px solid var(--border-soft,#dbe4f0);background:var(--card-bg,#fff);color:var(--text-main,#0f172a);border-radius:999px;padding:8px 12px;font-size:11px;font-weight:800;display:inline-flex;align-items:center;gap:5px;box-shadow:0 5px 12px rgba(15,23,42,.04)}
    .return-tab-btn.active{background:#0f172a;border-color:#0f172a;color:#fff}
    .bill-detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .bill-detail-box{border:1px solid var(--border-soft,#dbe4f0);background:#f8fafc;border-radius:14px;padding:10px}
    .work-panel{border:1px dashed #bfdbfe;background:#eff6ff;border-radius:16px;padding:12px}
    .qty-input{width:90px;min-height:32px;border:1px solid #cbd5e1;border-radius:10px;padding:5px 8px;font-weight:800;text-align:right}
    .search-product-row{display:grid;grid-template-columns:1fr auto;gap:8px}
    .selected-product-box{border:1px solid #dbe4f0;background:#fff;border-radius:12px;padding:8px;margin-top:8px}
    .history-card{border:1px solid #dbe4f0;border-radius:14px;padding:10px;background:#fff}
    .live-note{border:1px dashed #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:14px;padding:9px 11px;font-size:11px;font-weight:700}
    @media(max-width:991px){.bill-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:767px){.bill-detail-grid{grid-template-columns:1fr}.search-product-row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) { include __DIR__ . '/includes/common-toast.php'; } ?>
<form id="returnExchangeSecurityForm" class="d-none"><?= re_csrf_field() ?></form>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>

        <section class="page-section master-page p-3 p-lg-3">
            <div class="mp-hero mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div>
                        <h1>Return & Exchange</h1>
                        <p>Separate module for bill barcode/bill number search, product return, exchange, refund/store credit, stock update and invoices.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="resetPage" class="btn btn-outline-primary">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <section class="mp-card mb-3">
                <div class="mp-card-head">
                    <div class="d-flex flex-column flex-xl-row gap-2 justify-content-xl-between align-items-xl-center">
                        <div>
                            <h2 class="mp-card-title">Find Original Bill</h2>
                            <p class="mp-card-sub">Scan Bill Barcode or manually enter Bill Number.</p>
                        </div>
                        <form id="searchBillForm" class="d-flex flex-column flex-md-row gap-2">
                            <input type="text" id="billSearch" class="form-control mp-filter-input" placeholder="Scan / enter Bill Barcode or Bill No" autocomplete="off">
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">
                                <i data-lucide="scan-barcode" style="width:14px;height:14px;"></i> Search
                            </button>
                        </form>
                    </div>
                </div>
                <div class="p-3" id="billResult">
                    <div class="text-center text-muted py-4">Scan bill barcode or enter bill number to start return/exchange.</div>
                </div>
            </section>

            <div id="workArea" class="d-none">
                <div class="return-tabs">
                    <button class="return-tab-btn active" type="button" data-tab="return" onclick="changeWorkTab('return')">
                        <i data-lucide="undo-2" style="width:14px;height:14px"></i> Return
                    </button>
                    <button class="return-tab-btn" type="button" data-tab="exchange" onclick="changeWorkTab('exchange')">
                        <i data-lucide="repeat-2" style="width:14px;height:14px"></i> Exchange
                    </button>
                    <button class="return-tab-btn" type="button" data-tab="history" onclick="changeWorkTab('history')">
                        <i data-lucide="history" style="width:14px;height:14px"></i> History
                    </button>
                </div>

                <section class="mp-card" id="returnPanel">
                    <div class="mp-card-head">
                        <h2 class="mp-card-title">Return Products</h2>
                        <p class="mp-card-sub">Select one or more products, enter return quantity and choose refund option.</p>
                    </div>
                    <div class="p-3">
                        <div class="work-panel mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-bold small">Refund Option</label>
                                    <select id="returnRefundOption" class="form-select mp-filter-select">
                                        <option value="cash_refund">Cash Refund</option>
                                        <option value="store_credit">Store Credit</option>
                                        <option value="adjust_balance">Adjust Customer Balance</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-bold small">Notes</label>
                                    <input type="text" id="returnNotes" class="form-control mp-filter-input" placeholder="Reason / notes">
                                </div>
                                <div class="col-12 col-md-3 text-md-end">
                                    <div class="mp-sub">Refund Amount</div>
                                    <div class="fs-5 fw-bold amount-due" id="returnTotal">₹0.00</div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table mp-table mb-0">
                                <thead><tr><th>Select</th><th>Product</th><th>Size / Color</th><th>Sold Qty</th><th>Returnable</th><th>Rate</th><th>Return Qty</th><th>Refund</th></tr></thead>
                                <tbody id="returnItemsBody"></tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-danger rounded-pill fw-bold px-4" onclick="submitReturn()">
                                <i data-lucide="receipt-text" style="width:14px;height:14px"></i> Confirm Return
                            </button>
                        </div>
                    </div>
                </section>

                <section class="mp-card d-none" id="exchangePanel">
                    <div class="mp-card-head">
                        <h2 class="mp-card-title">Exchange Product</h2>
                        <p class="mp-card-sub">Select original product, search/select new product, verify stock and confirm exchange.</p>
                    </div>
                    <div class="p-3">
                        <div class="work-panel mb-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-bold small">Refund Option if New Product is Lower</label>
                                    <select id="exchangeRefundOption" class="form-select mp-filter-select">
                                        <option value="cash_refund">Cash Refund</option>
                                        <option value="store_credit">Store Credit</option>
                                        <option value="adjust_balance">Adjust Customer Balance</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-bold small">Balance Collection if Higher</label>
                                    <select id="exchangeCollectOption" class="form-select mp-filter-select">
                                        <option value="cash">Cash Collected</option>
                                        <option value="upi">UPI Collected</option>
                                        <option value="card">Card Collected</option>
                                        <option value="credit">Add to Customer Balance</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-bold small">Notes</label>
                                    <input type="text" id="exchangeNotes" class="form-control mp-filter-input" placeholder="Reason / notes">
                                </div>
                                <div class="col-12 col-md-2 text-md-end">
                                    <div class="mp-sub">Difference</div>
                                    <div class="fs-5 fw-bold" id="exchangeDiff">₹0.00</div>
                                </div>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table mp-table mb-0">
                                <thead><tr><th>Select</th><th>Old Product</th><th>Old Rate</th><th>Exchange Qty</th><th>New Product Search</th><th>New Qty</th><th>New Rate</th><th>Difference</th></tr></thead>
                                <tbody id="exchangeItemsBody"></tbody>
                            </table>
                        </div>
                        <div class="mt-3 text-end">
                            <button type="button" class="btn btn-primary rounded-pill fw-bold px-4" onclick="submitExchange()">
                                <i data-lucide="repeat-2" style="width:14px;height:14px"></i> Confirm Exchange
                            </button>
                        </div>
                    </div>
                </section>

                <section class="mp-card d-none" id="historyPanel">
                    <div class="mp-card-head">
                        <h2 class="mp-card-title">Return & Exchange History</h2>
                        <p class="mp-card-sub">Complete history with user, date and invoice links.</p>
                    </div>
                    <div class="p-3" id="historyBody">
                        <div class="text-center text-muted py-4">No history loaded.</div>
                    </div>
                </section>
            </div>

            <div class="live-note mt-3">Return & Exchange is separate from Create Bill and Collection. Stock movements, customer ledger and invoices are handled here.</div>
        </section>
    </main>
</div>

<?php if (file_exists(__DIR__ . '/includes/script.php')) { include __DIR__ . '/includes/script.php'; } ?>

<script>
(function(){
    'use strict';

    window.returnExchangeState = {
        bill: null,
        items: [],
        history: [],
        tab: 'return',
        selectedProducts: {}
    };

    const apiUrl = 'api/return-exchange-api.php';
    const money = new Intl.NumberFormat('en-IN', { style:'currency', currency:'INR' });

    function csrfToken() {
        const input = document.querySelector('#returnExchangeSecurityForm input[name="csrf_token"], #returnExchangeSecurityForm input[name="_token"], #returnExchangeSecurityForm input[type="hidden"]');
        return input ? input.value : '';
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
    }

    function toNumber(value) {
        const n = parseFloat(value || 0);
        return isNaN(n) ? 0 : n;
    }

    function showMessage(type, message) {
        if (window.showToast) {
            window.showToast(message, type === 'error' ? 'danger' : type);
            return;
        }
        if (window.AppToast && typeof window.AppToast.show === 'function') {
            window.AppToast.show(type === 'error' ? 'error' : type, message);
            return;
        }
        if (window.Swal) {
            window.Swal.fire(type === 'error' ? 'Error' : 'Success', message, type === 'error' ? 'error' : 'success');
            return;
        }
        alert(message);
    }

    function buildQuery(params) {
        const q = new URLSearchParams();
        Object.keys(params || {}).forEach(function(key){
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') q.append(key, params[key]);
        });
        return q.toString();
    }

    async function apiGet(params) {
        const response = await fetch(apiUrl + '?' + buildQuery(params), { credentials:'same-origin', headers:{'Accept':'application/json'} });
        return await response.json();
    }

    async function apiPost(payload) {
        const form = new FormData();
        Object.keys(payload || {}).forEach(function(key){
            form.append(key, typeof payload[key] === 'object' ? JSON.stringify(payload[key]) : payload[key]);
        });
        form.append('csrf_token', csrfToken());
        const response = await fetch(apiUrl, { method:'POST', credentials:'same-origin', headers:{'Accept':'application/json'}, body:form });
        return await response.json();
    }

    function badge(text, cls) {
        return '<span class="mp-badge '+cls+'">'+escapeHtml(text)+'</span>';
    }

    function billInfoBox(label, value) {
        return '<div class="bill-detail-box"><div class="mp-sub">'+escapeHtml(label)+'</div><div class="mp-title mt-1">'+value+'</div></div>';
    }

    function paymentStatusBadge(value) {
        const s = String(value || '-').toLowerCase();
        let cls = 'status-pending';
        if (s === 'paid') cls = 'status-paid';
        if (s === 'partial') cls = 'status-partial';
        if (s === 'cancelled') cls = 'status-returned';
        return badge(s.charAt(0).toUpperCase()+s.slice(1), cls);
    }

    function renderBill() {
        const bill = returnExchangeState.bill;
        const items = returnExchangeState.items;

        if (!bill) {
            document.getElementById('billResult').innerHTML = '<div class="text-center text-muted py-4">Scan bill barcode or enter bill number to start return/exchange.</div>';
            document.getElementById('workArea').classList.add('d-none');
            return;
        }

        const branch = (bill.branch_name || '-') + (bill.floor_name ? ' / ' + bill.floor_name : '');
        const returnStatus = bill.return_status || 'no_return';

        document.getElementById('billResult').innerHTML =
            '<div class="bill-detail-grid">' +
                billInfoBox('Customer Name', escapeHtml(bill.customer_name || 'Walk-in Customer') + '<div class="mp-sub">' + escapeHtml(bill.customer_mobile || '-') + '</div>') +
                billInfoBox('Bill Number', escapeHtml(bill.bill_no || '-') + '<div class="mp-sub">Barcode: ' + escapeHtml(bill.barcode_value || '-') + '</div>') +
                billInfoBox('Bill Date', escapeHtml(bill.bill_date || '-') + (bill.bill_time ? '<div class="mp-sub">' + escapeHtml(bill.bill_time) + '</div>' : '')) +
                billInfoBox('Payment Status', paymentStatusBadge(bill.payment_status) + '<div class="mp-sub mt-1">Return: ' + escapeHtml(returnStatus.replaceAll('_',' ')) + '</div>') +
                billInfoBox('Branch / User', escapeHtml(branch) + '<div class="mp-sub">' + escapeHtml(bill.created_by_name || '-') + '</div>') +
                billInfoBox('Products', '<span class="amount-dark">' + parseInt(items.length || 0, 10) + '</span> items') +
                billInfoBox('Net Amount', '<span class="amount-dark">' + money.format(toNumber(bill.net_amount)) + '</span>') +
                billInfoBox('Paid / Due', '<span class="amount-positive">' + money.format(toNumber(bill.paid_amount)) + '</span><div class="mp-sub amount-due">Due ' + money.format(toNumber(bill.balance_amount)) + '</div>') +
            '</div>';

        document.getElementById('workArea').classList.remove('d-none');
        renderReturnItems();
        renderExchangeItems();
        renderHistory();
        if (window.lucide) window.lucide.createIcons();
    }

    function returnableQty(item) {
        return Math.max(0, toNumber(item.qty) - toNumber(item.returned_qty));
    }

    function lineRate(item) {
        const qty = Math.max(1, toNumber(item.qty));
        return toNumber(item.amount) / qty;
    }

    function renderReturnItems() {
        const rows = returnExchangeState.items.map(function(item){
            const rq = returnableQty(item);
            const rate = lineRate(item);
            return '<tr>' +
                '<td><input type="checkbox" class="form-check-input js-return-select" data-id="'+parseInt(item.bill_item_id,10)+'" '+(rq<=0?'disabled':'')+'></td>' +
                '<td><div class="mp-title">'+escapeHtml(item.article_no || '-')+'</div><div class="mp-sub">'+escapeHtml(item.article_name || '-')+' · '+escapeHtml(item.brand_name || '-')+'</div></td>' +
                '<td>'+escapeHtml(item.size || '-')+'<div class="mp-sub">'+escapeHtml(item.color || '-')+'</div></td>' +
                '<td>'+toNumber(item.qty).toFixed(2)+'</td>' +
                '<td><span class="mp-badge '+(rq>0?'badge-money':'badge-due')+'">'+rq.toFixed(2)+'</span></td>' +
                '<td>'+money.format(rate)+'</td>' +
                '<td><input type="number" min="0" max="'+rq+'" step="0.01" value="'+(rq>0?rq.toFixed(2):'0.00')+'" class="qty-input js-return-qty" data-id="'+parseInt(item.bill_item_id,10)+'" '+(rq<=0?'disabled':'')+'></td>' +
                '<td class="amount-due js-return-line" data-id="'+parseInt(item.bill_item_id,10)+'">₹0.00</td>' +
            '</tr>';
        }).join('');

        document.getElementById('returnItemsBody').innerHTML = rows || '<tr><td colspan="8" class="text-center text-muted py-4">No returnable items.</td></tr>';
        calculateReturnTotal();
    }

    function renderExchangeItems() {
        const rows = returnExchangeState.items.map(function(item){
            const rq = returnableQty(item);
            const rate = lineRate(item);
            const id = parseInt(item.bill_item_id,10);
            const selected = returnExchangeState.selectedProducts[id];
            return '<tr>' +
                '<td><input type="checkbox" class="form-check-input js-exchange-select" data-id="'+id+'" '+(rq<=0?'disabled':'')+'></td>' +
                '<td><div class="mp-title">'+escapeHtml(item.article_no || '-')+'</div><div class="mp-sub">'+escapeHtml(item.article_name || '-')+' · '+escapeHtml(item.size || '-')+' / '+escapeHtml(item.color || '-')+'</div></td>' +
                '<td>'+money.format(rate)+'</td>' +
                '<td><input type="number" min="0" max="'+rq+'" step="0.01" value="'+(rq>0?'1.00':'0.00')+'" class="qty-input js-exchange-old-qty" data-id="'+id+'" '+(rq<=0?'disabled':'')+'></td>' +
                '<td>' +
                    '<div class="search-product-row"><input type="text" class="form-control mp-filter-input js-product-search" data-id="'+id+'" placeholder="Scan barcode / article and press Enter"><button type="button" class="btn btn-outline-primary btn-sm rounded-pill js-find-product" data-id="'+id+'">Find</button></div>' +
                    '<div class="selected-product-box js-selected-product" data-id="'+id+'">'+(selected ? selectedProductHtml(selected) : '<span class="mp-sub">No new product selected.</span>')+'</div>' +
                '</td>' +
                '<td><input type="number" min="0" step="0.01" value="'+(selected ? '1.00' : '0.00')+'" class="qty-input js-exchange-new-qty" data-id="'+id+'"></td>' +
                '<td class="amount-dark js-new-rate" data-id="'+id+'">'+(selected ? money.format(toNumber(selected.selling_rate)) : '₹0.00')+'</td>' +
                '<td class="js-exchange-line" data-id="'+id+'">₹0.00</td>' +
            '</tr>';
        }).join('');

        document.getElementById('exchangeItemsBody').innerHTML = rows || '<tr><td colspan="8" class="text-center text-muted py-4">No exchangeable items.</td></tr>';
        calculateExchangeTotal();
    }

    function selectedProductHtml(product) {
        return '<div class="mp-title">'+escapeHtml(product.article_no || '-')+' · '+escapeHtml(product.article_name || '-')+'</div>' +
            '<div class="mp-sub">'+escapeHtml(product.brand_name || '-')+' · Size '+escapeHtml(product.size || '-')+' · '+escapeHtml(product.color || '-')+' · Stock '+toNumber(product.available_qty).toFixed(2)+' · '+money.format(toNumber(product.selling_rate))+'</div>' +
            '<span class="mp-badge badge-code mt-1">'+escapeHtml(product.barcode_value || ('#'+product.stock_item_id))+'</span>';
    }

    function itemById(id) {
        return returnExchangeState.items.find(function(item){ return parseInt(item.bill_item_id,10) === parseInt(id,10); });
    }

    function calculateReturnTotal() {
        let total = 0;
        document.querySelectorAll('.js-return-select').forEach(function(box){
            const id = box.dataset.id;
            const item = itemById(id);
            const qtyInput = document.querySelector('.js-return-qty[data-id="'+id+'"]');
            const line = document.querySelector('.js-return-line[data-id="'+id+'"]');
            let amount = 0;
            if (box.checked && item && qtyInput) {
                const maxQty = returnableQty(item);
                const qty = Math.max(0, Math.min(toNumber(qtyInput.value), maxQty));
                qtyInput.value = qty.toFixed(2);
                amount = qty * lineRate(item);
                total += amount;
            }
            if (line) line.textContent = money.format(amount);
        });
        document.getElementById('returnTotal').textContent = money.format(total);
        return total;
    }

    function calculateExchangeTotal() {
        let diff = 0;
        document.querySelectorAll('.js-exchange-select').forEach(function(box){
            const id = box.dataset.id;
            const item = itemById(id);
            const oldQtyInput = document.querySelector('.js-exchange-old-qty[data-id="'+id+'"]');
            const newQtyInput = document.querySelector('.js-exchange-new-qty[data-id="'+id+'"]');
            const line = document.querySelector('.js-exchange-line[data-id="'+id+'"]');
            const selected = returnExchangeState.selectedProducts[id];
            let lineDiff = 0;

            if (box.checked && item && selected && oldQtyInput && newQtyInput) {
                const oldQty = Math.max(0, Math.min(toNumber(oldQtyInput.value), returnableQty(item)));
                const newQty = Math.max(0, Math.min(toNumber(newQtyInput.value), toNumber(selected.available_qty)));
                oldQtyInput.value = oldQty.toFixed(2);
                newQtyInput.value = newQty.toFixed(2);
                lineDiff = (newQty * toNumber(selected.selling_rate)) - (oldQty * lineRate(item));
                diff += lineDiff;
            }

            if (line) {
                line.textContent = money.format(lineDiff);
                line.className = 'js-exchange-line ' + (lineDiff > 0 ? 'amount-due' : (lineDiff < 0 ? 'amount-positive' : 'amount-dark'));
            }
        });

        const el = document.getElementById('exchangeDiff');
        el.textContent = money.format(diff);
        el.className = 'fs-5 fw-bold ' + (diff > 0 ? 'amount-due' : (diff < 0 ? 'amount-positive' : 'amount-dark'));
        return diff;
    }

    function renderHistory() {
        const history = returnExchangeState.history || [];
        if (!history.length) {
            document.getElementById('historyBody').innerHTML = '<div class="text-center text-muted py-4">No return/exchange history for this bill.</div>';
            return;
        }

        document.getElementById('historyBody').innerHTML = '<div class="d-grid gap-2">' + history.map(function(row){
            return '<div class="history-card">' +
                '<div class="d-flex flex-column flex-md-row justify-content-md-between gap-2">' +
                    '<div><div class="mp-title">'+escapeHtml(row.transaction_no || '-')+' · '+escapeHtml(row.transaction_type || '-')+'</div><div class="mp-sub">'+escapeHtml(row.created_at || '-')+' · By '+escapeHtml(row.created_by_name || '-')+'</div></div>' +
                    '<div class="text-md-end"><div class="amount-due">Refund '+money.format(toNumber(row.refund_amount))+'</div><div class="amount-positive">Extra '+money.format(toNumber(row.extra_collect_amount))+'</div></div>' +
                '</div>' +
                '<div class="mt-2"><a class="btn btn-outline-dark btn-sm rounded-pill fw-bold" target="_blank" rel="noopener" href="return-exchange-print.php?id='+parseInt(row.return_exchange_id,10)+'"><i data-lucide="printer" style="width:13px;height:13px"></i> Print Invoice</a></div>' +
            '</div>';
        }).join('') + '</div>';
    }

    async function searchBill(term) {
        document.getElementById('billResult').innerHTML = '<div class="text-center text-muted py-4">Loading bill details...</div>';
        try {
            const data = await apiGet({ action:'search_bill', search: term });
            if (!data.success) {
                returnExchangeState.bill = null;
                returnExchangeState.items = [];
                returnExchangeState.history = [];
                renderBill();
                showMessage('error', data.message || 'Bill not found.');
                return;
            }

            returnExchangeState.bill = data.bill || {};
            returnExchangeState.items = data.items || [];
            returnExchangeState.history = data.history || [];
            returnExchangeState.selectedProducts = {};
            renderBill();
            showMessage('success', 'Bill loaded successfully.');
        } catch (error) {
            showMessage('error', 'Unable to connect Return & Exchange API.');
        }
    }

    function selectExchangeProduct(id, product) {
        id = String(id || '');
        if (!id || !product || !parseInt(product.stock_item_id || 0, 10)) {
            return null;
        }

        returnExchangeState.selectedProducts[id] = product;

        const checkBox = document.querySelector('.js-exchange-select[data-id="'+id+'"]');
        if (checkBox && !checkBox.disabled) {
            checkBox.checked = true;
        }

        const oldQty = document.querySelector('.js-exchange-old-qty[data-id="'+id+'"]');
        const item = itemById(id);
        if (oldQty && item) {
            const maxOldQty = returnableQty(item);
            const oldValue = toNumber(oldQty.value);
            oldQty.value = (oldValue > 0 ? Math.min(oldValue, maxOldQty) : Math.min(1, maxOldQty)).toFixed(2);
        }

        const newQty = document.querySelector('.js-exchange-new-qty[data-id="'+id+'"]');
        if (newQty) {
            const stockQty = toNumber(product.available_qty);
            const newValue = toNumber(newQty.value);
            newQty.max = stockQty.toFixed(2);
            newQty.value = (newValue > 0 ? Math.min(newValue, stockQty) : Math.min(1, stockQty)).toFixed(2);
        }

        const box = document.querySelector('.js-selected-product[data-id="'+id+'"]');
        if (box) box.innerHTML = selectedProductHtml(product);

        const newRate = document.querySelector('.js-new-rate[data-id="'+id+'"]');
        if (newRate) newRate.textContent = money.format(toNumber(product.selling_rate));

        calculateExchangeTotal();
        if (window.lucide) window.lucide.createIcons();

        return product;
    }

    async function findProduct(id, silent) {
        id = String(id || '');
        const input = document.querySelector('.js-product-search[data-id="'+id+'"]');
        const term = input ? input.value.trim() : '';

        if (!term) {
            if (!silent) showMessage('warning', 'Enter barcode/article/brand to find new product.');
            return null;
        }

        try {
            const data = await apiGet({ action:'search_products', search: term });
            if (!data.success || !data.products || !data.products.length) {
                if (!silent) showMessage('error', data.message || 'No available stock found.');
                return null;
            }

            const product = selectExchangeProduct(id, data.products[0]);
            if (!silent) showMessage('success', 'New product selected and old product checked.');
            return product;
        } catch (error) {
            if (!silent) showMessage('error', 'Unable to search new product.');
            return null;
        }
    }

    async function submitReturn() {
        if (!returnExchangeState.bill) return;

        const items = [];
        document.querySelectorAll('.js-return-select').forEach(function(box){
            if (!box.checked) return;
            const id = box.dataset.id;
            const item = itemById(id);
            const qty = toNumber((document.querySelector('.js-return-qty[data-id="'+id+'"]') || {}).value);
            if (item && qty > 0) {
                items.push({ bill_item_id: parseInt(id,10), return_qty: qty });
            }
        });

        if (!items.length) {
            showMessage('warning', 'Select at least one product to return.');
            return;
        }

        if (!window.confirm('Confirm selected product return?')) return;

        try {
            const data = await apiPost({
                action: 'create_return',
                bill_id: parseInt(returnExchangeState.bill.bill_id, 10),
                refund_option: document.getElementById('returnRefundOption').value,
                notes: document.getElementById('returnNotes').value,
                items: items
            });

            if (!data.success) {
                showMessage('error', data.message || 'Unable to create return.');
                return;
            }

            showMessage('success', data.message || 'Return completed.');
            window.open('return-exchange-print.php?id=' + parseInt(data.return_exchange_id,10), '_blank', 'noopener');
            searchBill(returnExchangeState.bill.bill_no);
        } catch (error) {
            showMessage('error', 'Unable to connect Return & Exchange API.');
        }
    }

    async function submitExchange() {
        if (!returnExchangeState.bill) return;

        /*
         * Exchange validation fix:
         * 1. If user searches/scans a new product and clicks Confirm without pressing Find,
         *    system will find/select the new product automatically.
         * 2. When new product is selected, the old product checkbox is checked automatically.
         * 3. User gets exact missing-field warning instead of generic error.
         */
        const checkedRows = Array.from(document.querySelectorAll('.js-exchange-select')).filter(function(box){ return box.checked; });

        for (const box of checkedRows) {
            const id = String(box.dataset.id || '');
            const input = document.querySelector('.js-product-search[data-id="'+id+'"]');
            if (!returnExchangeState.selectedProducts[id] && input && input.value.trim()) {
                await findProduct(id, true);
            }
        }

        const items = [];
        let hasOldProduct = false;
        let missingNewProduct = false;
        let invalidQty = false;

        document.querySelectorAll('.js-exchange-select').forEach(function(box){
            if (!box.checked) return;
            hasOldProduct = true;

            const id = String(box.dataset.id || '');
            const item = itemById(id);
            const product = returnExchangeState.selectedProducts[id];
            const oldQtyInput = document.querySelector('.js-exchange-old-qty[data-id="'+id+'"]');
            const newQtyInput = document.querySelector('.js-exchange-new-qty[data-id="'+id+'"]');

            const oldQty = toNumber((oldQtyInput || {}).value);
            const newQty = toNumber((newQtyInput || {}).value);

            if (!product) {
                missingNewProduct = true;
                return;
            }

            if (!item || oldQty <= 0 || newQty <= 0) {
                invalidQty = true;
                return;
            }

            const maxOldQty = returnableQty(item);
            const maxNewQty = toNumber(product.available_qty);

            if (oldQty > maxOldQty || newQty > maxNewQty) {
                invalidQty = true;
                return;
            }

            items.push({
                bill_item_id: parseInt(id,10),
                return_qty: oldQty,
                new_stock_item_id: parseInt(product.stock_item_id,10),
                new_barcode_id: parseInt(product.barcode_id || 0,10),
                new_qty: newQty
            });
        });

        if (!hasOldProduct) {
            showMessage('warning', 'Please tick/select the old product to exchange.');
            return;
        }

        if (missingNewProduct) {
            showMessage('warning', 'Please select the new product. Enter barcode/article and click Find, or press Enter.');
            return;
        }

        if (invalidQty || !items.length) {
            showMessage('warning', 'Please enter valid old quantity and new quantity for exchange.');
            return;
        }

        if (!window.confirm('Confirm selected product exchange?')) return;

        try {
            const data = await apiPost({
                action: 'create_exchange',
                bill_id: parseInt(returnExchangeState.bill.bill_id, 10),
                refund_option: document.getElementById('exchangeRefundOption').value,
                collect_option: document.getElementById('exchangeCollectOption').value,
                notes: document.getElementById('exchangeNotes').value,
                items: items
            });

            if (!data.success) {
                showMessage('error', data.message || 'Unable to create exchange.');
                return;
            }

            showMessage('success', data.message || 'Exchange completed.');
            window.open('return-exchange-print.php?id=' + parseInt(data.return_exchange_id,10), '_blank', 'noopener');
            searchBill(returnExchangeState.bill.bill_no);
        } catch (error) {
            showMessage('error', 'Unable to connect Return & Exchange API.');
        }
    }

    window.changeWorkTab = function(tab) {
        returnExchangeState.tab = tab;
        document.querySelectorAll('.return-tab-btn').forEach(function(btn){ btn.classList.toggle('active', btn.dataset.tab === tab); });
        document.getElementById('returnPanel').classList.toggle('d-none', tab !== 'return');
        document.getElementById('exchangePanel').classList.toggle('d-none', tab !== 'exchange');
        document.getElementById('historyPanel').classList.toggle('d-none', tab !== 'history');
        if (window.lucide) window.lucide.createIcons();
    };

    window.submitReturn = submitReturn;
    window.submitExchange = submitExchange;

    document.getElementById('searchBillForm').addEventListener('submit', function(e){
        e.preventDefault();
        const term = document.getElementById('billSearch').value.trim();
        if (!term) {
            showMessage('warning', 'Scan/enter bill barcode or bill number.');
            return;
        }
        searchBill(term);
    });

    document.getElementById('resetPage').addEventListener('click', function(){
        document.getElementById('billSearch').value = '';
        returnExchangeState.bill = null;
        returnExchangeState.items = [];
        returnExchangeState.history = [];
        returnExchangeState.selectedProducts = {};
        renderBill();
    });

    document.addEventListener('input', function(e){
        if (e.target.matches('.js-return-qty')) calculateReturnTotal();
        if (e.target.matches('.js-exchange-old-qty,.js-exchange-new-qty')) calculateExchangeTotal();
    });

    document.addEventListener('keydown', function(e){
        if (e.target.matches('.js-product-search') && e.key === 'Enter') {
            e.preventDefault();
            findProduct(e.target.dataset.id);
        }
    });

    document.addEventListener('change', function(e){
        if (e.target.matches('.js-return-select')) calculateReturnTotal();
        if (e.target.matches('.js-exchange-select')) {
            const id = String(e.target.dataset.id || '');
            const input = document.querySelector('.js-product-search[data-id="'+id+'"]');
            if (e.target.checked && input && !returnExchangeState.selectedProducts[id]) {
                input.focus();
            }
            calculateExchangeTotal();
        }
    });

    document.addEventListener('click', function(e){
        const findBtn = e.target.closest('.js-find-product');
        if (findBtn) {
            e.preventDefault();
            findProduct(findBtn.dataset.id);
        }
    });

    renderBill();
    if (window.lucide) window.lucide.createIcons();
})();
</script>
</body>
</html>
