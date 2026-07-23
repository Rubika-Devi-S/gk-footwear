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
$cashierName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Cashier';

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
    .return-policy-note{border:1px solid #bbf7d0;background:#f0fdf4;color:#15803d;border-radius:14px;padding:10px 12px;font-size:11px;font-weight:800;line-height:1.4;margin-top:10px}
    .return-policy-note.expired{border-color:#fecaca;background:#fef2f2;color:#b91c1c}
    .return-policy-note.warning{border-color:#fde68a;background:#fffbeb;color:#92400e}
    .bill-search-wrap{display:grid;grid-template-columns:minmax(260px,1fr) auto auto;gap:8px;align-items:center}
    .scan-ready{box-shadow:0 0 0 .18rem rgba(37,99,235,.18)!important;border-color:#2563eb!important}
    .scan-helper{font-size:10px;font-weight:800;color:#1d4ed8;margin-top:6px}
    .search-history-wrap{display:flex;flex-wrap:wrap;gap:7px;align-items:center}
    .search-history-title{font-size:10px;font-weight:900;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-right:2px}
    .history-chip{border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:5px 9px;font-size:10px;font-weight:850;line-height:1;display:inline-flex;align-items:center;gap:5px;cursor:pointer;max-width:230px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .history-chip:hover{background:#dbeafe;border-color:#60a5fa;transform:translateY(-1px)}
    .product-results-box{border:1px solid #bfdbfe;background:#f8fbff;border-radius:14px;margin-top:8px;padding:8px;max-height:230px;overflow:auto}
    .product-result-title{font-size:10px;font-weight:900;color:#1e3a8a;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px}
    .product-result-item{width:100%;border:1px solid #dbe4f0;background:#fff;border-radius:12px;padding:8px;margin-bottom:7px;text-align:left;display:flex;justify-content:space-between;gap:10px;align-items:center;cursor:pointer;box-shadow:0 4px 12px rgba(15,23,42,.04)}
    .product-result-item:hover{border-color:#2563eb;background:#eff6ff;transform:translateY(-1px)}
    .product-result-main{min-width:0}.product-result-name{font-size:12px;font-weight:900;color:#0f172a;line-height:1.2}.product-result-meta{font-size:10px;color:#64748b;font-weight:700;margin-top:2px}.product-result-barcode{margin-top:5px}
    .product-result-price{font-size:11px;font-weight:900;color:#0f172a;text-align:right;white-space:nowrap}.product-result-stock{font-size:10px;font-weight:900;color:#15803d}
    .selected-product-box.is-empty{color:#64748b;background:#f8fafc;border-style:dashed}.selected-product-box.is-selected{border-color:#86efac;background:#f0fdf4}
    .scanner-box{border:1px dashed #93c5fd;background:#eff6ff;border-radius:18px;padding:12px}
    .scanner-video{width:100%;min-height:280px;max-height:360px;object-fit:cover;background:#020617;border-radius:14px;border:1px solid #cbd5e1}
    .scanner-status{font-size:11px;font-weight:750;border-radius:14px;padding:9px 10px;background:#f8fafc;border:1px solid #dbe4f0;color:#334155}
    .scanner-status.success{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}
    .scanner-status.error{background:#fef2f2;color:#b91c1c;border-color:#fecaca}
    .manual-scan-card{border:1px solid #e2e8f0;background:#f8fafc;border-radius:16px;padding:12px}
    .scan-result-value{font-size:11px;word-break:break-all;color:#475569}
    @media(max-width:991px){.bill-detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:767px){.bill-detail-grid{grid-template-columns:1fr}.search-product-row{grid-template-columns:1fr}.bill-search-wrap{grid-template-columns:1fr}.product-result-item{align-items:flex-start;flex-direction:column}.product-result-price{text-align:left}}
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
                        <button type="button" id="returnPeriodSettingsBtn" class="btn btn-outline-dark">
                            <i data-lucide="settings" style="width:14px;height:14px;"></i> Period Settings
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
                        <form id="searchBillForm" class="bill-search-wrap">
                            <div>
                                <input type="text" id="billSearch" class="form-control mp-filter-input" placeholder="Scan / enter Bill Barcode or Bill No" autocomplete="off">
                                <div id="billScanHint" class="scan-helper d-none">Scanner ready. Scan the bill barcode now, or type and press Enter.</div>
                            </div>
                            
                            <button type="button" id="scanBillCameraBtn" class="btn btn-outline-success btn-sm rounded-pill fw-bold px-3" title="Open camera scanner">
                                <i data-lucide="camera" style="width:14px;height:14px;"></i> Camera
                            </button>
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">
                                <i data-lucide="search" style="width:14px;height:14px;"></i> Search
                            </button>
                        </form>
                    </div>
                    <div id="billSearchHistory" class="search-history-wrap mt-2"></div>
                </div>
                <div class="p-3" id="billResult">
                    <div class="text-center text-muted py-4">Scan bill barcode or enter bill number to start return/exchange.</div>
                </div>
            </section>

            <section class="mp-card mb-3" id="pageHistorySection">
                <div class="mp-card-head">
                    <div class="d-flex flex-column flex-md-row gap-2 justify-content-md-between align-items-md-center">
                        <div>
                            <h2 class="mp-card-title">Return &amp; Exchange History</h2>
                            <p class="mp-card-sub">Latest return/exchange history is loaded automatically. Search a bill to view that bill's history.</p>
                        </div>
                        <button type="button" class="btn btn-light btn-sm rounded-pill fw-bold px-3" onclick="loadRecentReturnExchangeHistory()">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px"></i> Refresh History
                        </button>
                    </div>
                </div>
                <div class="p-3" id="pageHistoryBody">
                    <div class="text-center text-muted py-4">Loading latest return/exchange history...</div>
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
                        <p class="mp-card-sub">Eligibility is based on the configured Return & Exchange period from the original bill date and time. Select products, enter return quantity and choose refund option.</p>
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
                        <p class="mp-card-sub">Eligibility is based on the configured Return & Exchange period from the original bill date and time. Select the original and replacement products, then confirm exchange.</p>
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

<!-- ============================================ -->
<!-- RETURN & EXCHANGE PERIOD SETTINGS MODAL -->
<!-- ============================================ -->
<div class="modal fade" id="returnPeriodSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Return & Exchange Period Settings</h5>
                    <div class="mp-sub">Increase or reduce the allowed return and exchange period.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    The expiry time is calculated from the original bill date and bill time.
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label fw-bold">Allowed Days</label>
                        <input type="number" id="returnPeriodDays" class="form-control" min="0" max="365" step="1" value="3">
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-bold">Additional Hours</label>
                        <input type="number" id="returnPeriodHours" class="form-control" min="0" max="23" step="1" value="0">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="form-label fw-bold">Quick Period</label>
                    <select id="returnPeriodQuickSelect" class="form-select">
                        <option value="">Custom</option>
                        <option value="1">1 Day</option>
                        <option value="3">3 Days</option>
                        <option value="5">5 Days</option>
                        <option value="7">7 Days</option>
                        <option value="15">15 Days</option>
                        <option value="30">30 Days</option>
                    </select>
                </div>
                <div id="returnPeriodPreview" class="return-policy-note mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" id="resetReturnPeriodBtn" class="btn btn-outline-secondary">Reset to 3 Days</button>
                <button type="button" id="saveReturnPeriodBtn" class="btn btn-primary">Save Settings</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- CAMERA SCANNER MODAL -->
<!-- ============================================ -->
<div class="modal fade" id="scannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Scan Bill Barcode</h5>
                    <div class="mp-sub">Scan a bill barcode to load it for Return/Exchange.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="scanner-box mb-3">
                    <video id="qrScannerVideo" class="scanner-video" playsinline muted></video>
                </div>
                <div class="scanner-status mb-3" id="scannerStatus">Click Scan Bill Barcode to start the camera scanner.</div>
                <div class="manual-scan-card">
                    <div class="d-flex flex-column flex-md-row gap-2 align-items-md-end">
                        <div class="flex-grow-1">
                            <label class="form-label fw-bold small">Manual Barcode / Bill Number</label>
                            <input type="text" id="manualScanValue" class="form-control" placeholder="Enter bill barcode number, bill no or order no">
                        </div>
                        <button type="button" id="manualScanBtn" class="btn btn-dark rounded-pill fw-bold px-3">Find Bill</button>
                    </div>
                    <div class="scan-result-value mt-2" id="lastScannedValue"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="restartScannerBtn" class="btn btn-outline-primary">Restart Scanner</button>
                <button type="button" id="stopScannerBtn" class="btn btn-outline-secondary">Stop Camera</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/includes/script.php')) { include __DIR__ . '/includes/script.php'; } ?>

<script>
(function(){
    'use strict';

    window.returnExchangeState = {
        bill: null,
        items: [],
        history: [],
        recentHistory: [],
        recentHistoryLoaded: false,
        tab: 'return',
        selectedProducts: {},
        productSearchResults: {},
        productSearchTimers: {},
        eligibility: {
            allowed: false,
            ageDays: null,
            expiryDate: '',
            expiryDateTime: '',
            message: ''
        }
    };

    // ============================================
    // SCANNER VARIABLES
    // ============================================
    let scannerModal = null;
    let scanStream = null;
    let scanTimer = null;
    let scanBusy = false;

    const apiUrl = 'api/return-exchange-api.php';
    const printServiceUrl = 'http://127.0.0.1:17900/';
    const money = new Intl.NumberFormat('en-IN', { style:'currency', currency:'INR' });

    const RETURN_EXCHANGE_SETTINGS_KEY = 'gk_return_exchange_period_settings';
    const DEFAULT_RETURN_EXCHANGE_DAYS = 3;
    const DEFAULT_RETURN_EXCHANGE_HOURS = 0;

    function getReturnExchangePeriodSettings() {
        try {
            const saved = JSON.parse(localStorage.getItem(RETURN_EXCHANGE_SETTINGS_KEY) || '{}');
            const days = Math.max(0, Math.min(365, parseInt(saved.days, 10) || DEFAULT_RETURN_EXCHANGE_DAYS));
            const hours = Math.max(0, Math.min(23, parseInt(saved.hours, 10) || 0));
            return { days: days, hours: hours };
        } catch (error) {
            return { days: DEFAULT_RETURN_EXCHANGE_DAYS, hours: DEFAULT_RETURN_EXCHANGE_HOURS };
        }
    }

    function getReturnExchangeLimitMs() {
        const settings = getReturnExchangePeriodSettings();
        return ((settings.days * 24) + settings.hours) * 60 * 60 * 1000;
    }

    function getReturnExchangePeriodLabel() {
        const settings = getReturnExchangePeriodSettings();
        const parts = [];
        if (settings.days > 0) parts.push(settings.days + ' day' + (settings.days === 1 ? '' : 's'));
        if (settings.hours > 0) parts.push(settings.hours + ' hour' + (settings.hours === 1 ? '' : 's'));
        return parts.length ? parts.join(' ') : '0 hours';
    }

    function parseBillDateTimeLocal(bill) {
        bill = bill || {};

        const dateRaw = String(
            bill.bill_date ||
            bill.invoice_date ||
            bill.sale_date ||
            bill.created_date ||
            ''
        ).trim();

        const timeRaw = String(
            bill.bill_time ||
            bill.invoice_time ||
            bill.sale_time ||
            ''
        ).trim();

        let combinedRaw = dateRaw;
        if (!combinedRaw && bill.created_at) {
            combinedRaw = String(bill.created_at).trim();
        }

        if (!combinedRaw || combinedRaw === '0000-00-00' || combinedRaw === '0000-00-00 00:00:00') {
            return null;
        }

        let year, month, day, hour = 0, minute = 0, second = 0;
        let match = combinedRaw.match(
            /^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?/
        );

        if (match) {
            year = Number(match[1]);
            month = Number(match[2]);
            day = Number(match[3]);
            hour = Number(match[4] || 0);
            minute = Number(match[5] || 0);
            second = Number(match[6] || 0);
        } else {
            match = combinedRaw.match(
                /^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?)?/i
            );

            if (!match) {
                return null;
            }

            day = Number(match[1]);
            month = Number(match[2]);
            year = Number(match[3]);
            hour = Number(match[4] || 0);
            minute = Number(match[5] || 0);
            second = Number(match[6] || 0);

            const meridiem = String(match[7] || '').toUpperCase();
            if (meridiem === 'PM' && hour < 12) hour += 12;
            if (meridiem === 'AM' && hour === 12) hour = 0;
        }

        // Prefer the separate database bill_time when supplied.
        if (timeRaw) {
            const timeMatch = timeRaw.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)?$/i);
            if (timeMatch) {
                hour = Number(timeMatch[1] || 0);
                minute = Number(timeMatch[2] || 0);
                second = Number(timeMatch[3] || 0);

                const meridiem = String(timeMatch[4] || '').toUpperCase();
                if (meridiem === 'PM' && hour < 12) hour += 12;
                if (meridiem === 'AM' && hour === 12) hour = 0;
            }
        }

        const date = new Date(year, month - 1, day, hour, minute, second, 0);

        if (
            date.getFullYear() !== year ||
            date.getMonth() !== month - 1 ||
            date.getDate() !== day ||
            date.getHours() !== hour ||
            date.getMinutes() !== minute ||
            date.getSeconds() !== second
        ) {
            return null;
        }

        return date;
    }

    function formatPolicyDateTime(date) {
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '-';
        }

        return date.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    function formatRemainingPolicyTime(milliseconds) {
        const totalMinutes = Math.max(0, Math.ceil(milliseconds / 60000));
        const days = Math.floor(totalMinutes / 1440);
        const hours = Math.floor((totalMinutes % 1440) / 60);
        const minutes = totalMinutes % 60;

        const parts = [];
        if (days > 0) parts.push(days + ' day' + (days === 1 ? '' : 's'));
        if (hours > 0) parts.push(hours + ' hour' + (hours === 1 ? '' : 's'));
        if (minutes > 0 || !parts.length) parts.push(minutes + ' minute' + (minutes === 1 ? '' : 's'));

        return parts.join(' ');
    }

    function getReturnExchangeEligibility(bill) {
        bill = bill || {};

        const billDateTime = parseBillDateTimeLocal(bill);

        if (!billDateTime) {
            return {
                allowed: false,
                ageDays: null,
                expiryDate: '',
                expiryDateTime: '',
                message: 'Return and exchange are blocked because the original bill date or time is missing or invalid.'
            };
        }

        const now = new Date();
        const expiryDateTime = new Date(billDateTime.getTime() + getReturnExchangeLimitMs());
        const elapsedMs = now.getTime() - billDateTime.getTime();
        const ageDays = elapsedMs / 86400000;

        if (elapsedMs < 0) {
            return {
                allowed: false,
                ageDays: ageDays,
                expiryDate: formatPolicyDateTime(expiryDateTime),
                expiryDateTime: formatPolicyDateTime(expiryDateTime),
                message: 'Return and exchange are blocked because the bill date and time are in the future.'
            };
        }

        if (now.getTime() > expiryDateTime.getTime()) {
            return {
                allowed: false,
                ageDays: ageDays,
                expiryDate: formatPolicyDateTime(expiryDateTime),
                expiryDateTime: formatPolicyDateTime(expiryDateTime),
                message: 'Return and exchange period expired. Only bills within ' +
                    getReturnExchangePeriodLabel() +
                    ' are allowed. Expired on ' +
                    formatPolicyDateTime(expiryDateTime) +
                    '.'
            };
        }

        const remainingMs = expiryDateTime.getTime() - now.getTime();

        return {
            allowed: true,
            ageDays: ageDays,
            expiryDate: formatPolicyDateTime(expiryDateTime),
            expiryDateTime: formatPolicyDateTime(expiryDateTime),
            message: 'Eligible for return or exchange. ' +
                formatRemainingPolicyTime(remainingMs) +
                ' remaining. Expires on ' +
                formatPolicyDateTime(expiryDateTime) +
                '.'
        };
    }

    function ensureReturnExchangeEligible() {
        const eligibility = getReturnExchangeEligibility(returnExchangeState.bill);
        returnExchangeState.eligibility = eligibility;

        if (!eligibility.allowed) {
            showMessage('warning', eligibility.message);
            return false;
        }

        return true;
    }

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

    // ============================================
    // RETURN & EXCHANGE PERIOD SETTINGS
    // ============================================
    let returnPeriodSettingsModal = null;

    function getReturnPeriodSettingsModal() {
        if (!window.bootstrap) return null;
        if (!returnPeriodSettingsModal) {
            returnPeriodSettingsModal = new bootstrap.Modal(document.getElementById('returnPeriodSettingsModal'));
        }
        return returnPeriodSettingsModal;
    }

    function updateReturnPeriodPreview() {
        const daysInput = document.getElementById('returnPeriodDays');
        const hoursInput = document.getElementById('returnPeriodHours');
        const preview = document.getElementById('returnPeriodPreview');
        if (!daysInput || !hoursInput || !preview) return;

        const days = Math.max(0, Math.min(365, parseInt(daysInput.value, 10) || 0));
        const hours = Math.max(0, Math.min(23, parseInt(hoursInput.value, 10) || 0));
        const parts = [];

        if (days > 0) parts.push(days + ' day' + (days === 1 ? '' : 's'));
        if (hours > 0) parts.push(hours + ' hour' + (hours === 1 ? '' : 's'));

        preview.textContent = 'Current allowed period: ' + (parts.length ? parts.join(' ') : '0 hours') + '.';
    }

    function openReturnPeriodSettings() {
        const settings = getReturnExchangePeriodSettings();
        document.getElementById('returnPeriodDays').value = settings.days;
        document.getElementById('returnPeriodHours').value = settings.hours;
        document.getElementById('returnPeriodQuickSelect').value =
            settings.hours === 0 && [1,3,5,7,15,30].includes(settings.days)
                ? String(settings.days)
                : '';
        updateReturnPeriodPreview();

        const modal = getReturnPeriodSettingsModal();
        if (modal) modal.show();
    }

    function saveReturnPeriodSettings() {
        const days = Math.max(0, Math.min(365, parseInt(document.getElementById('returnPeriodDays').value, 10) || 0));
        const hours = Math.max(0, Math.min(23, parseInt(document.getElementById('returnPeriodHours').value, 10) || 0));

        if (days === 0 && hours === 0) {
            showMessage('warning', 'Return and exchange period must be at least 1 hour.');
            return;
        }

        localStorage.setItem(RETURN_EXCHANGE_SETTINGS_KEY, JSON.stringify({
            days: days,
            hours: hours
        }));

        const modal = getReturnPeriodSettingsModal();
        if (modal) modal.hide();

        showMessage('success', 'Return and exchange period updated to ' + getReturnExchangePeriodLabel() + '.');

        if (returnExchangeState.bill) {
            renderBill();
        }
    }

    function resetReturnPeriodSettings() {
        localStorage.setItem(RETURN_EXCHANGE_SETTINGS_KEY, JSON.stringify({
            days: DEFAULT_RETURN_EXCHANGE_DAYS,
            hours: DEFAULT_RETURN_EXCHANGE_HOURS
        }));

        document.getElementById('returnPeriodDays').value = DEFAULT_RETURN_EXCHANGE_DAYS;
        document.getElementById('returnPeriodHours').value = DEFAULT_RETURN_EXCHANGE_HOURS;
        document.getElementById('returnPeriodQuickSelect').value = String(DEFAULT_RETURN_EXCHANGE_DAYS);
        updateReturnPeriodPreview();
    }

    // ============================================
    // SCANNER FUNCTIONS
    // ============================================
    function getScannerModal() {
        if (window.bootstrap) {
            if (!scannerModal) scannerModal = new bootstrap.Modal(document.getElementById('scannerModal'));
            return scannerModal;
        }
        return null;
    }

    function setScannerStatus(message, type) {
        const box = document.getElementById('scannerStatus');
        if (!box) return;
        box.textContent = message || '';
        box.classList.remove('success', 'error');
        if (type) box.classList.add(type);
    }

    function stopScanner() {
        clearInterval(scanTimer);
        scanTimer = null;
        if (scanStream) {
            scanStream.getTracks().forEach(track => track.stop());
            scanStream = null;
        }
        const video = document.getElementById('qrScannerVideo');
        if (video) {
            video.pause();
            video.srcObject = null;
        }
        scanBusy = false;
    }

    async function startScanner() {
        const video = document.getElementById('qrScannerVideo');
        if (!video) { return; }
        if (scanBusy) { return; }
        scanBusy = true;
        setScannerStatus('Opening camera scanner...');

        try {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                throw new Error('Camera access is not supported in this browser.');
            }
            if (!('BarcodeDetector' in window)) {
                throw new Error('BarcodeDetector is not supported in this browser. Use Chrome/Android or enter the scanned value manually.');
            }

            scanStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false
            });

            video.srcObject = scanStream;
            await video.play();

            let formats = ['code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e'];
            if (BarcodeDetector.getSupportedFormats) {
                const supported = await BarcodeDetector.getSupportedFormats();
                formats = formats.filter(f => supported.includes(f));
                if (!formats.length) formats = ['code_128'];
            }

            const detector = new BarcodeDetector({ formats: formats });
            setScannerStatus('Scanner ready. Point the camera at the bill barcode.', 'success');

            clearInterval(scanTimer);
            scanTimer = setInterval(async function() {
                try {
                    if (!video.videoWidth || !video.videoHeight) return;
                    const codes = await detector.detect(video);
                    if (codes && codes.length) {
                        clearInterval(scanTimer);
                        scanTimer = null;
                        stopScanner();
                        const scannedValue = codes[0].rawValue || '';
                        document.getElementById('lastScannedValue').textContent = 'Scanned: ' + scannedValue;
                        const sm = getScannerModal();
                        if (sm) sm.hide();
                        openBillFromScan(scannedValue);
                    }
                } catch (err) {}
            }, 450);

        } catch (err) {
            setScannerStatus(err.message || 'Unable to open camera scanner.', 'error');
            scanBusy = false;
        }
    }

    function openScannerModal() {
        const sm = getScannerModal();
        if (sm) sm.show();
        setTimeout(startScanner, 350);
    }

    // ============================================
    // OPEN BILL FROM SCAN
    // ============================================
    async function openBillFromScan(value) {
        const scanned = String(value || '').trim();
        if (!scanned) {
            showMessage('error', 'Enter or scan a bill barcode number.');
            return;
        }

        document.getElementById('lastScannedValue').textContent = 'Scanned: ' + scanned;
        setScannerStatus('Finding bill for barcode: ' + scanned, 'success');

        try {
            const data = await apiGet({ action: 'search_bill', search: scanned });
            if (!data.success) {
                setScannerStatus('No bill found for this barcode.', 'error');
                showMessage('error', data.message || 'No bill found for this barcode.');
                return;
            }

            document.getElementById('billSearch').value = scanned;
            stopScanner();
            const sm = getScannerModal();
            if (sm) sm.hide();
            searchBill(scanned);

        } catch (e) {
            setScannerStatus('Unable to connect to API.', 'error');
            showMessage('error', 'Unable to identify the scanned barcode.');
        }
    }

    // ============================================
    // THERMAL PRINT FUNCTION FOR RETURN/EXCHANGE
    // ============================================
    async function sendThermalPrint(printData) {
        try {
            printData = printData || {};

            const originalBillNo = String(
                printData.BillNo ||
                printData.OriginalBillNo ||
                (returnExchangeState.bill && returnExchangeState.bill.bill_no) ||
                ''
            ).trim();

            if (!originalBillNo) {
                throw new Error('Original bill number is missing for thermal printing.');
            }

            /*
             * Pass the bill number in both properties for compatibility with
             * old and new ThermalPrinterInvoice builds.
             */
            printData.BillNo = originalBillNo;
            printData.OriginalBillNo = originalBillNo;

            console.log(
                'Sending Return/Exchange thermal print for bill:',
                originalBillNo,
                printData
            );

            const response = await fetch(printServiceUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(printData)
            });
            
            const result = await response.text();
            console.log('Print response:', result);
            return { success: true, message: result };
        } catch (error) {
            console.error('Print error:', error);
            return { success: false, message: error.message };
        }
    }

    // ============================================
    // BUILD PRINT DATA - FIXED for Return & Exchange
    // ============================================
    function buildPrintData(data, printType) {
        data = data || {};

        /*
         * Always keep the original sales bill number separate from the
         * return/exchange transaction number.
         *
         * The thermal service prints BillNo. Therefore BillNo must contain
         * the original bill selected in this module, not the generated
         * return/exchange transaction number.
         */
        const stateBill = returnExchangeState.bill || {};
        const bill = data.bill || data.original_bill || stateBill || {};
        const items = data.items || [];

        const originalBillNo = String(
            data.original_bill_no ||
            data.original_bill_number ||
            data.bill_no ||
            bill.bill_no ||
            stateBill.bill_no ||
            ''
        ).trim();

        const transactionNo = String(
            data.transaction_no ||
            data.return_no ||
            data.exchange_no ||
            data.return_exchange_no ||
            ''
        ).trim();
        
        // For Return: Use return_items from the response
        // For Exchange: Use exchange_items from the response
        let returnItems = data.return_items || data.returnItems || [];
        let exchangeItems = data.exchange_items || data.exchangeItems || [];
        
        // If these are empty, try to extract from the items array
        if (returnItems.length === 0 && data.items) {
            returnItems = data.items.filter(function(item) {
                return item.return_qty && parseFloat(item.return_qty) > 0;
            });
        }
        
        if (exchangeItems.length === 0 && data.items) {
            exchangeItems = data.items.filter(function(item) {
                return item.new_qty && parseFloat(item.new_qty) > 0;
            });
        }
        
        let printItems = [];
        let grandTotal = 0;
        let transactionType = '';
        
        if (printType === 'RETURN') {
            transactionType = 'Return';
            
            if (returnItems.length > 0) {
                printItems = returnItems.map(function(item) {
                    const qty = toNumber(item.return_qty || item.qty || 0);
                    const rate = toNumber(item.rate || item.old_rate || item.selling_rate || 0);
                    const amount = qty * rate;
                    grandTotal += amount;
                    
                    let description = '';
                    if (item.old_article_no || item.article_no) {
                        description += item.old_article_no || item.article_no;
                    }
                    if (item.old_article_name || item.article_name) {
                        description += (description ? ' / ' : '') + (item.old_article_name || item.article_name);
                    }
                    if (item.old_brand_name || item.brand_name) {
                        description += (description ? ' / ' : '') + (item.old_brand_name || item.brand_name);
                    }
                    if (item.old_size || item.size) {
                        description += (description ? ' / ' : '') + 'Size ' + (item.old_size || item.size);
                    }
                    if (item.old_color || item.color) {
                        description += (description ? ' / ' : '') + 'Color ' + (item.old_color || item.color);
                    }
                    
                    return {
                        Name: item.old_article_name || item.article_name || 'Returned Item',
                        Description: description || 'Returned product',
                        Qty: qty,
                        Rate: rate,
                        Amount: amount
                    };
                });
            } else {
                printItems = items.map(function(item) {
                    const qty = toNumber(item.qty || 0);
                    const rate = toNumber(item.rate || item.selling_rate || 0);
                    const amount = qty * rate;
                    grandTotal += amount;
                    
                    let description = '';
                    if (item.article_no) description += item.article_no;
                    if (item.brand_name) description += (description ? ' / ' : '') + item.brand_name;
                    if (item.size) description += (description ? ' / ' : '') + 'Size ' + item.size;
                    if (item.color) description += (description ? ' / ' : '') + 'Color ' + item.color;
                    
                    return {
                        Name: item.article_name || item.name || 'Item',
                        Description: description || 'Returned item',
                        Qty: qty,
                        Rate: rate,
                        Amount: amount
                    };
                });
            }
        } else if (printType === 'EXCHANGE') {
            transactionType = 'Exchange';
            
            if (exchangeItems.length > 0) {
                printItems = exchangeItems.map(function(item) {
                    const qty = toNumber(item.new_qty || item.qty || 0);
                    const rate = toNumber(item.new_rate || item.selling_rate || 0);
                    const amount = qty * rate;
                    grandTotal += amount;
                    
                    let description = '';
                    if (item.new_article_no || item.article_no) {
                        description += item.new_article_no || item.article_no;
                    }
                    if (item.new_article_name || item.article_name) {
                        description += (description ? ' / ' : '') + (item.new_article_name || item.article_name);
                    }
                    if (item.new_brand_name || item.brand_name) {
                        description += (description ? ' / ' : '') + (item.new_brand_name || item.brand_name);
                    }
                    if (item.new_size || item.size) {
                        description += (description ? ' / ' : '') + 'Size ' + (item.new_size || item.size);
                    }
                    if (item.new_color || item.color) {
                        description += (description ? ' / ' : '') + 'Color ' + (item.new_color || item.color);
                    }
                    
                    return {
                        Name: item.new_article_name || item.article_name || 'Exchange Item',
                        Description: description || 'Exchanged product',
                        Qty: qty,
                        Rate: rate,
                        Amount: amount
                    };
                });
            } else {
                printItems = items.map(function(item) {
                    const qty = toNumber(item.qty || 0);
                    const rate = toNumber(item.rate || item.selling_rate || 0);
                    const amount = qty * rate;
                    grandTotal += amount;
                    
                    let description = '';
                    if (item.article_no) description += item.article_no;
                    if (item.brand_name) description += (description ? ' / ' : '') + item.brand_name;
                    if (item.size) description += (description ? ' / ' : '') + 'Size ' + item.size;
                    if (item.color) description += (description ? ' / ' : '') + 'Color ' + item.color;
                    
                    return {
                        Name: item.article_name || item.name || 'Item',
                        Description: description || 'Exchanged item',
                        Qty: qty,
                        Rate: rate,
                        Amount: amount
                    };
                });
            }
        } else {
            transactionType = 'Sale';
            printItems = items.map(function(item) {
                const qty = toNumber(item.qty || 0);
                const rate = toNumber(item.rate || item.selling_rate || 0);
                const amount = qty * rate;
                grandTotal += amount;
                
                let description = '';
                if (item.article_no) description += item.article_no;
                if (item.brand_name) description += (description ? ' / ' : '') + item.brand_name;
                if (item.size) description += (description ? ' / ' : '') + 'Size ' + item.size;
                if (item.color) description += (description ? ' / ' : '') + 'Color ' + item.color;
                
                return {
                    Name: item.article_name || item.name || 'Item',
                    Description: description,
                    Qty: qty,
                    Rate: rate,
                    Amount: amount
                };
            });
        }

        let refundAmount = toNumber(data.refund_amount || 0);
        let extraCollect = toNumber(data.extra_collect_amount || 0);
        let paidAmount = refundAmount > 0 ? refundAmount : grandTotal;
        let balanceAmount = extraCollect > 0 ? extraCollect : 0;

        let paymentStatus = 'COMPLETED';
        let branchDisplay = bill.branch_name || '';
        if (bill.floor_name) branchDisplay += ' ' + bill.floor_name;

        let collectedBy = data.collected_by || '';

        return {
            "PrintType": printType,
            "ShopName": "GK FOOTWEAR",
            "Address": "Gandhi Nagar, Krishnagiri.",
            "InvoiceTitle": printType === 'RETURN' ? 'RETURN INVOICE' : (printType === 'EXCHANGE' ? 'EXCHANGE INVOICE' : 'BILL OF SUPPLY'),
            /*
             * BillNo is the field consumed and printed by the local .NET
             * thermal service. Keep the generated return/exchange number in
             * TransactionNo so the two references never get mixed.
             */
            "BillNo": originalBillNo,
            "OriginalBillNo": originalBillNo,
            "TransactionNo": transactionNo,
            "OrderNo": bill.order_no || 'ORD-' + originalBillNo,
            "Date": new Date().toLocaleDateString('en-IN', { day:'2-digit', month:'2-digit', year:'numeric' }),
            "Time": new Date().toLocaleTimeString('en-IN', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:true }),
            "Customer": bill.customer_name || 'Walk-in Customer',
            "Branch": branchDisplay || 'N/A',
            "Salesman": bill.created_by_name || 'Sales User',
            "CollectedBy": collectedBy || 'N/A',
            "TransactionType": transactionType,
            "PaymentStatus": paymentStatus,
            "PaymentMethod": data.payment_method || 'Cash',
            "GrandTotal": grandTotal,
            "Paid": paidAmount,
            "Balance": balanceAmount,
            "Barcode": data.barcode || bill.bill_barcode || bill.barcode_value || originalBillNo || transactionNo,
            "Items": printItems
        };
    }

    // ============================================
    // SUBMIT RETURN - UPDATED with proper return items
    // ============================================
    async function submitReturn() {
        if (!returnExchangeState.bill) {
            showMessage('warning', 'Please search and select a bill first.');
            return;
        }

        if (!ensureReturnExchangeEligible()) {
            return;
        }

        const items = [];
        const returnItems = [];
        document.querySelectorAll('.js-return-select').forEach(function(box){
            if (!box.checked) return;
            const id = box.dataset.id;
            const item = itemById(id);
            const qty = toNumber((document.querySelector('.js-return-qty[data-id="'+id+'"]') || {}).value);
            if (item && qty > 0) {
                const rate = lineRate(item);
                const amount = qty * rate;
                
                items.push({ 
                    bill_item_id: parseInt(id,10), 
                    return_qty: qty,
                    article_no: item.article_no,
                    article_name: item.article_name,
                    brand_name: item.brand_name,
                    size: item.size,
                    color: item.color,
                    selling_rate: rate
                });
                
                returnItems.push({
                    old_article_no: item.article_no,
                    old_article_name: item.article_name,
                    old_brand_name: item.brand_name,
                    old_size: item.size,
                    old_color: item.color,
                    return_qty: qty,
                    old_rate: rate,
                    return_amount: amount,
                    return_note: 'Returned item'
                });
            }
        });

        if (!items.length) {
            showMessage('warning', 'Select at least one product to return.');
            return;
        }

        if (!window.confirm('Confirm selected product return?')) return;

        try {
            const originalBillNo = String(
                returnExchangeState.bill.bill_no || ''
            ).trim();

            if (!originalBillNo) {
                showMessage('error', 'Original bill number is missing. Search the bill again.');
                return;
            }

            const data = await apiPost({
                action: 'create_return',
                bill_id: parseInt(returnExchangeState.bill.bill_id, 10),
                bill_no: originalBillNo,
                original_bill_no: originalBillNo,
                refund_option: document.getElementById('returnRefundOption').value,
                notes: document.getElementById('returnNotes').value,
                items: items,
                return_items: returnItems
            });

            if (!data.success) {
                showMessage('error', data.message || 'Unable to create return.');
                return;
            }

            showMessage('success', data.message || 'Return completed.');

            const printData = buildPrintData({
                ...data,
                bill: { ...returnExchangeState.bill },
                original_bill_no: originalBillNo,
                bill_no: originalBillNo,
                return_items: returnItems,
                collected_by: <?= json_encode($cashierName) ?>
            }, 'RETURN');

            /*
             * Final assignment guarantees the selected bill number reaches
             * the .NET service even when the API response omits bill details.
             */
            printData.BillNo = originalBillNo;
            printData.OriginalBillNo = originalBillNo;
            
            const printResult = await sendThermalPrint(printData);
            if (printResult.success) {
                console.log('Thermal print sent successfully for Return');
                showMessage('success', 'Return invoice sent to thermal printer.');
            } else {
                console.warn('Thermal print failed:', printResult.message);
                showMessage('warning', 'Return completed but thermal print failed. Please check printer connection.');
            }

            loadRecentHistory(true);
            searchBill(returnExchangeState.bill.bill_no);
        } catch (error) {
            showMessage('error', 'Unable to connect Return & Exchange API.');
        }
    }

    // ============================================
    // SUBMIT EXCHANGE - UPDATED with proper exchange items
    // ============================================
    async function submitExchange() {
        if (!returnExchangeState.bill) {
            showMessage('warning', 'Please search and select a bill first.');
            return;
        }

        if (!ensureReturnExchangeEligible()) {
            return;
        }

        const checkedRows = Array.from(document.querySelectorAll('.js-exchange-select')).filter(function(box){ return box.checked; });

        for (const box of checkedRows) {
            const id = String(box.dataset.id || '');
            const input = document.querySelector('.js-product-search[data-id="'+id+'"]');
            if (!returnExchangeState.selectedProducts[id] && input && input.value.trim()) {
                const products = await findProduct(id, true);
                if (products.length === 1) {
                    selectExchangeProduct(id, products[0]);
                }
            }
        }

        const items = [];
        const exchangeItems = [];
        let hasOldProduct = false;
        let missingNewProduct = false;
        let invalidQty = false;
        let missingActiveBarcode = false;

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

            if (!parseInt(product.barcode_id || 0, 10)) {
                missingActiveBarcode = true;
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

            const oldRate = lineRate(item);
            const newRate = toNumber(product.selling_rate);

            items.push({
                bill_item_id: parseInt(id,10),
                return_qty: oldQty,
                new_stock_item_id: parseInt(product.stock_item_id,10),
                new_barcode_id: parseInt(product.barcode_id || 0,10),
                new_qty: newQty
            });

            exchangeItems.push({
                old_article_no: item.article_no,
                old_article_name: item.article_name,
                old_brand_name: item.brand_name,
                old_size: item.size,
                old_color: item.color,
                old_rate: oldRate,
                old_qty: oldQty,
                new_article_no: product.article_no,
                new_article_name: product.article_name,
                new_brand_name: product.brand_name,
                new_size: product.size,
                new_color: product.color,
                new_rate: newRate,
                new_qty: newQty,
                exchange_note: 'Exchanged from ' + (item.article_no || '') + ' to ' + (product.article_no || '')
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

        if (missingActiveBarcode) {
            showMessage('warning', 'Selected new product has no active barcode. Please choose another product or generate barcode in Stock Inward / Stock List.');
            return;
        }

        if (invalidQty || !items.length) {
            showMessage('warning', 'Please enter valid old quantity and new quantity for exchange.');
            return;
        }

        if (!window.confirm('Confirm selected product exchange?')) return;

        try {
            const originalBillNo = String(
                returnExchangeState.bill.bill_no || ''
            ).trim();

            if (!originalBillNo) {
                showMessage('error', 'Original bill number is missing. Search the bill again.');
                return;
            }

            const data = await apiPost({
                action: 'create_exchange',
                bill_id: parseInt(returnExchangeState.bill.bill_id, 10),
                bill_no: originalBillNo,
                original_bill_no: originalBillNo,
                refund_option: document.getElementById('exchangeRefundOption').value,
                collect_option: document.getElementById('exchangeCollectOption').value,
                notes: document.getElementById('exchangeNotes').value,
                items: items,
                exchange_items: exchangeItems
            });

            if (!data.success) {
                showMessage('error', data.message || 'Unable to create exchange.');
                return;
            }

            showMessage('success', data.message || 'Exchange completed.');

            const printData = buildPrintData({
                ...data,
                bill: { ...returnExchangeState.bill },
                original_bill_no: originalBillNo,
                bill_no: originalBillNo,
                exchange_items: exchangeItems,
                collected_by: <?= json_encode($cashierName) ?>
            }, 'EXCHANGE');

            printData.BillNo = originalBillNo;
            printData.OriginalBillNo = originalBillNo;
            
            const printResult = await sendThermalPrint(printData);
            if (printResult.success) {
                console.log('Thermal print sent successfully for Exchange');
                showMessage('success', 'Exchange invoice sent to thermal printer.');
            } else {
                console.warn('Thermal print failed:', printResult.message);
                showMessage('warning', 'Exchange completed but thermal print failed. Please check printer connection.');
            }

            loadRecentHistory(true);
            searchBill(returnExchangeState.bill.bill_no);
        } catch (error) {
            showMessage('error', 'Unable to connect Return & Exchange API.');
        }
    }

    // ============================================
    // EXISTING FUNCTIONS
    // ============================================
    const billHistoryKey = 'gk_return_exchange_bill_search_history';
    const productHistoryKey = 'gk_return_exchange_product_search_history';

    function getLocalHistory(key) {
        try {
            const rows = JSON.parse(localStorage.getItem(key) || '[]');
            return Array.isArray(rows) ? rows : [];
        } catch (error) {
            return [];
        }
    }

    function saveLocalHistory(key, row, maxRows) {
        if (!row || !row.value) return;
        maxRows = maxRows || 8;
        const value = String(row.value || '').trim();
        if (!value) return;
        const list = getLocalHistory(key).filter(function(item){ return String(item.value || '').toLowerCase() !== value.toLowerCase(); });
        list.unshift({ value:value, label: row.label || value, sub: row.sub || '', saved_at: new Date().toISOString() });
        localStorage.setItem(key, JSON.stringify(list.slice(0, maxRows)));
    }

    function renderBillSearchHistory() {
        const box = document.getElementById('billSearchHistory');
        if (!box) return;
        const list = getLocalHistory(billHistoryKey);
        if (!list.length) {
            box.innerHTML = '';
            return;
        }
        box.innerHTML = '<span class="search-history-title">Recent Searches</span>' + list.map(function(item){
            return '<button type="button" class="history-chip js-bill-history" data-value="'+escapeHtml(item.value)+'" title="'+escapeHtml(item.sub || item.label)+'"><i data-lucide="history" style="width:12px;height:12px"></i>'+escapeHtml(item.label || item.value)+'</button>';
        }).join('');
        if (window.lucide) window.lucide.createIcons();
    }

    function productHistoryHtml(id) {
        const list = getLocalHistory(productHistoryKey);
        if (!list.length) return '';
        return '<div class="product-results-box">' +
            '<div class="product-result-title">Recent product searches</div>' +
            '<div class="search-history-wrap">' + list.map(function(item){
                return '<button type="button" class="history-chip js-product-history" data-id="'+escapeHtml(id)+'" data-value="'+escapeHtml(item.value)+'" title="Search again"><i data-lucide="history" style="width:12px;height:12px"></i>'+escapeHtml(item.label || item.value)+'</button>';
            }).join('') + '</div></div>';
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
            renderHistory();
            return;
        }

        const branch = (bill.branch_name || '-') + (bill.floor_name ? ' / ' + bill.floor_name : '');
        const returnStatus = bill.return_status || 'no_return';
        const eligibility = getReturnExchangeEligibility(bill);
        returnExchangeState.eligibility = eligibility;

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
            '</div>' +
            '<div class="return-policy-note ' + (eligibility.allowed ? '' : 'expired') + '">' +
                escapeHtml(eligibility.message) +
            '</div>';

        document.getElementById('workArea').classList.toggle('d-none', !eligibility.allowed);
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
                    '<div class="js-product-results" data-id="'+id+'">'+(!selected ? productHistoryHtml(id) : '')+'</div>' +
                    '<div class="selected-product-box js-selected-product '+(selected ? 'is-selected' : 'is-empty')+'" data-id="'+id+'">'+(selected ? selectedProductHtml(selected) : '<span class="mp-sub">No new product selected.</span>')+'</div>' +
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

    function historyEmptyHtml(message) {
        const recent = getLocalHistory(billHistoryKey);
        let html = '<div class="text-center text-muted py-4">' + escapeHtml(message) + '</div>';

        if (recent.length) {
            html += '<div class="border-top pt-3 mt-1">' +
                '<div class="search-history-wrap justify-content-center">' +
                '<span class="search-history-title">Recent bill searches</span>' +
                recent.slice(0, 8).map(function(item){
                    return '<button type="button" class="history-chip js-bill-history" data-value="'+escapeHtml(item.value)+'" title="'+escapeHtml(item.sub || item.label)+'"><i data-lucide="history" style="width:12px;height:12px"></i>'+escapeHtml(item.label || item.value)+'</button>';
                }).join('') +
                '</div></div>';
        }

        return html;
    }

    function historyCardsHtml(history) {
        return '<div class="d-grid gap-2">' + history.map(function(row){
            const billPart = row.bill_no ? 'Bill: ' + escapeHtml(row.bill_no) : '';
            const customerPart = row.customer_name ? ' · ' + escapeHtml(row.customer_name) : '';
            const mobilePart = row.customer_mobile ? ' / ' + escapeHtml(row.customer_mobile) : '';
            const typeLabel = String(row.transaction_type || '-').replaceAll('_', ' ');
            return '<div class="history-card">' +
                '<div class="d-flex flex-column flex-md-row justify-content-md-between gap-2">' +
                    '<div>' +
                        '<div class="mp-title">'+escapeHtml(row.transaction_no || '-')+' · '+escapeHtml(typeLabel)+'</div>' +
                        '<div class="mp-sub">'+escapeHtml(row.created_at || '-')+' · By '+escapeHtml(row.created_by_name || '-')+'</div>' +
                        (billPart ? '<div class="mp-sub mt-1">'+billPart+customerPart+mobilePart+'</div>' : '') +
                    '</div>' +
                    '<div class="text-md-end"><div class="amount-due">Refund '+money.format(toNumber(row.refund_amount))+'</div><div class="amount-positive">Extra '+money.format(toNumber(row.extra_collect_amount))+'</div></div>' +
                '</div>' +
                '<div class="mt-2"><button type="button" class="btn btn-outline-success btn-sm rounded-pill fw-bold js-reprint-history" data-id="'+parseInt(row.return_exchange_id,10)+'" data-type="'+escapeHtml(row.transaction_type || '')+'"><i data-lucide="printer" style="width:13px;height:13px"></i> Reprint</button></div>' +
            '</div>';
        }).join('') + '</div>';
    }

    // ============================================
    // REPRINT HISTORY FUNCTION - Direct thermal print
    // ============================================
    async function reprintHistory(returnExchangeId, type) {
        returnExchangeId = parseInt(returnExchangeId || 0, 10);
        if (!returnExchangeId) {
            showMessage('warning', 'Invalid return/exchange ID.');
            return;
        }

        try {
            const data = await apiGet({ 
                action: 'get_return_exchange', 
                id: returnExchangeId,
                _ts: Date.now()
            });

            if (!data.success) {
                showMessage('error', data.message || 'Unable to fetch return/exchange details.');
                return;
            }

            const printType = String(type || data.transaction_type || 'RETURN').toUpperCase();
            
            /*
             * History APIs may return the bill number under different keys.
             * Resolve it before building the thermal payload.
             */
            const historyBill = data.bill || data.original_bill || {};
            const historyBillNo = String(
                data.original_bill_no ||
                data.original_bill_number ||
                data.bill_no ||
                historyBill.bill_no ||
                (returnExchangeState.bill && returnExchangeState.bill.bill_no) ||
                ''
            ).trim();

            const printData = buildPrintData({
                ...data,
                bill: historyBill,
                original_bill_no: historyBillNo,
                bill_no: historyBillNo,
                collected_by: <?= json_encode($cashierName) ?>
            }, printType);

            if (historyBillNo) {
                printData.BillNo = historyBillNo;
                printData.OriginalBillNo = historyBillNo;
            }

            const result = await sendThermalPrint(printData);
            if (result.success) {
                showMessage('success', 'Return/Exchange invoice sent to thermal printer.');
            } else {
                showMessage('warning', 'Print failed: ' + result.message);
            }

        } catch (error) {
            showMessage('error', 'Unable to fetch return/exchange details for reprint.');
        }
    }

    function renderHistory() {
        const billHistory = returnExchangeState.history || [];
        const recentHistory = returnExchangeState.recentHistory || [];
        let html = '';

        if (returnExchangeState.bill) {
            html = billHistory.length ? historyCardsHtml(billHistory) : historyEmptyHtml('No return/exchange history for this bill.');
        } else if (recentHistory.length) {
            html = historyCardsHtml(recentHistory);
        } else if (!returnExchangeState.recentHistoryLoaded) {
            html = '<div class="text-center text-muted py-4">Loading latest return/exchange history...</div>';
        } else {
            html = historyEmptyHtml('No previous return/exchange history found. Search a bill to start return/exchange.');
        }

        const historyBody = document.getElementById('historyBody');
        if (historyBody) historyBody.innerHTML = html;

        const pageHistoryBody = document.getElementById('pageHistoryBody');
        if (pageHistoryBody) pageHistoryBody.innerHTML = html;

        if (window.lucide) window.lucide.createIcons();
    }

    async function loadRecentHistory(silent) {
        if (!returnExchangeState.bill) {
            returnExchangeState.recentHistoryLoaded = false;
            renderHistory();
        }

        try {
            const data = await apiGet({ action:'recent_history', limit:20, _ts: Date.now() });
            if (!data.success) {
                returnExchangeState.recentHistory = [];
                returnExchangeState.recentHistoryLoaded = true;
                renderHistory();
                if (!silent) showMessage('error', data.message || 'Unable to load return/exchange history.');
                return;
            }

            returnExchangeState.recentHistory = data.history || [];
            returnExchangeState.recentHistoryLoaded = true;
            if (!returnExchangeState.bill) renderHistory();
            if (!silent) showMessage('success', 'Latest return/exchange history loaded.');
        } catch (error) {
            returnExchangeState.recentHistory = [];
            returnExchangeState.recentHistoryLoaded = true;
            renderHistory();
            if (!silent) showMessage('error', 'Unable to connect Return & Exchange API for history.');
        }
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
                renderHistory();
                showMessage('error', data.message || 'Bill not found.');
                return;
            }

            returnExchangeState.bill = data.bill || {};
            returnExchangeState.items = data.items || [];
            returnExchangeState.history = data.history || [];
            returnExchangeState.selectedProducts = {};
            returnExchangeState.productSearchResults = {};
            saveLocalHistory(billHistoryKey, {
                value: data.bill && data.bill.bill_no ? data.bill.bill_no : term,
                label: data.bill && data.bill.bill_no ? data.bill.bill_no : term,
                sub: (data.bill && data.bill.customer_name ? data.bill.customer_name : 'Bill search')
            }, 8);
            renderBillSearchHistory();
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
        if (box) {
            box.classList.remove('is-empty');
            box.classList.add('is-selected');
            box.innerHTML = selectedProductHtml(product);
        }

        const resultBox = document.querySelector('.js-product-results[data-id="'+id+'"]');
        if (resultBox) resultBox.innerHTML = '';

        saveLocalHistory(productHistoryKey, {
            value: product.barcode_value || product.article_no || product.article_name || String(product.stock_item_id || ''),
            label: (product.article_no || '-') + ' · ' + (product.article_name || '-'),
            sub: (product.brand_name || '-') + ' · Size ' + (product.size || '-')
        }, 10);

        const newRate = document.querySelector('.js-new-rate[data-id="'+id+'"]');
        if (newRate) newRate.textContent = money.format(toNumber(product.selling_rate));

        calculateExchangeTotal();
        if (window.lucide) window.lucide.createIcons();

        return product;
    }

    function resetSelectedProductForSearch(id) {
        id = String(id || '');
        if (!id) return;

        delete returnExchangeState.selectedProducts[id];
        const box = document.querySelector('.js-selected-product[data-id="'+id+'"]');
        if (box) {
            box.classList.remove('is-selected');
            box.classList.add('is-empty');
            box.innerHTML = '<span class="mp-sub">No new product selected.</span>';
        }

        const newQty = document.querySelector('.js-exchange-new-qty[data-id="'+id+'"]');
        if (newQty) {
            newQty.value = '0.00';
            newQty.removeAttribute('max');
        }

        const newRate = document.querySelector('.js-new-rate[data-id="'+id+'"]');
        if (newRate) newRate.textContent = '₹0.00';

        calculateExchangeTotal();
    }

    function renderProductResults(id, products, term) {
        id = String(id || '');
        const resultBox = document.querySelector('.js-product-results[data-id="'+id+'"]');
        if (!resultBox) return;

        products = Array.isArray(products) ? products : [];
        returnExchangeState.productSearchResults[id] = products;

        if (!products.length) {
            resultBox.innerHTML = '<div class="product-results-box"><div class="text-muted small fw-bold">No matching product found for "'+escapeHtml(term || '')+'".</div>' + productHistoryHtml(id) + '</div>';
            if (window.lucide) window.lucide.createIcons();
            return;
        }

        resultBox.innerHTML = '<div class="product-results-box">' +
            '<div class="product-result-title">Matching products · '+products.length+'</div>' +
            products.map(function(product, index){
                return '<button type="button" class="product-result-item js-product-pick" data-id="'+escapeHtml(id)+'" data-index="'+index+'">' +
                    '<span class="product-result-main">' +
                        '<span class="product-result-name">'+escapeHtml(product.article_no || '-')+' · '+escapeHtml(product.article_name || '-')+'</span>' +
                        '<span class="product-result-meta">'+escapeHtml(product.brand_name || '-')+' · Size '+escapeHtml(product.size || '-')+' · '+escapeHtml(product.color || '-')+'</span>' +
                        '<span class="product-result-barcode">'+badge(product.barcode_value || ('#'+product.stock_item_id), 'badge-code')+'</span>' +
                    '</span>' +
                    '<span class="product-result-price"><span class="product-result-stock">Stock '+toNumber(product.available_qty).toFixed(2)+'</span><br>'+money.format(toNumber(product.selling_rate))+'</span>' +
                '</button>';
            }).join('') +
            '</div>';
        if (window.lucide) window.lucide.createIcons();
    }

    async function findProduct(id, silent) {
        id = String(id || '');
        const input = document.querySelector('.js-product-search[data-id="'+id+'"]');
        const term = input ? input.value.trim() : '';

        if (!term) {
            if (!silent) showMessage('warning', 'Enter barcode/article/brand to find new product.');
            const resultBox = document.querySelector('.js-product-results[data-id="'+id+'"]');
            if (resultBox) resultBox.innerHTML = productHistoryHtml(id);
            if (window.lucide) window.lucide.createIcons();
            return [];
        }

        const resultBox = document.querySelector('.js-product-results[data-id="'+id+'"]');
        if (resultBox) resultBox.innerHTML = '<div class="product-results-box"><div class="text-muted small fw-bold">Searching products...</div></div>';

        try {
            const data = await apiGet({ action:'search_products', search: term, _ts: Date.now() });
            const products = (data.success && Array.isArray(data.products)) ? data.products : [];
            renderProductResults(id, products, term);

            if (!products.length) {
                if (!silent) showMessage('error', data.message || 'No available stock found.');
                return [];
            }

            saveLocalHistory(productHistoryKey, { value: term, label: term }, 10);
            if (!silent) showMessage('success', products.length + ' matching product(s) found. Select the required product.');
            return products;
        } catch (error) {
            if (resultBox) resultBox.innerHTML = '<div class="product-results-box"><div class="text-danger small fw-bold">Unable to search new product.</div></div>';
            if (!silent) showMessage('error', 'Unable to search new product.');
            return [];
        }
    }

    // ============================================
    // WINDOW FUNCTIONS
    // ============================================
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
    window.loadRecentReturnExchangeHistory = function(){
        if (returnExchangeState.bill && returnExchangeState.bill.bill_no) {
            searchBill(returnExchangeState.bill.bill_no);
            return;
        }
        loadRecentHistory(false);
    };
    window.reprintHistory = reprintHistory;

    // ============================================
    // EVENT LISTENERS
    // ============================================
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
        returnExchangeState.productSearchResults = {};
        renderBill();
        renderBillSearchHistory();
        loadRecentHistory(true);
    });

    // ============================================
    // SCANNER BUTTON EVENT
    // ============================================
    document.getElementById('scanBillCameraBtn').addEventListener('click', openScannerModal);

    // ============================================
    // MANUAL SCAN
    // ============================================
    document.getElementById('manualScanBtn').addEventListener('click', function(){
        const value = document.getElementById('manualScanValue').value.trim();
        if (!value) {
            showMessage('warning', 'Enter or scan a bill barcode number.');
            return;
        }
        const sm = getScannerModal();
        if (sm) sm.hide();
        openBillFromScan(value);
    });

    document.getElementById('manualScanValue').addEventListener('keydown', function(e){
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('manualScanBtn').click();
        }
    });

    // ============================================
    // SCANNER MODAL EVENTS
    // ============================================
    document.getElementById('scannerModal').addEventListener('hidden.bs.modal', function(){
        stopScanner();
    });

    document.getElementById('restartScannerBtn').addEventListener('click', function(){
        stopScanner();
        setTimeout(startScanner, 300);
    });

    document.getElementById('stopScannerBtn').addEventListener('click', function(){
        stopScanner();
        setScannerStatus('Camera stopped. Click Restart Scanner to scan again.');
    });

    // ============================================
    // OTHER EVENT LISTENERS
    // ============================================
    document.addEventListener('input', function(e){
        if (e.target.matches('.js-return-qty')) calculateReturnTotal();
        if (e.target.matches('.js-exchange-old-qty,.js-exchange-new-qty')) calculateExchangeTotal();
        if (e.target.matches('.js-product-search')) {
            const id = String(e.target.dataset.id || '');
            resetSelectedProductForSearch(id);
            window.clearTimeout(returnExchangeState.productSearchTimers[id]);
            const term = e.target.value.trim();
            const resultBox = document.querySelector('.js-product-results[data-id="'+id+'"]');
            if (!term) {
                if (resultBox) resultBox.innerHTML = productHistoryHtml(id);
                if (window.lucide) window.lucide.createIcons();
                return;
            }
            if (resultBox) resultBox.innerHTML = '<div class="product-results-box"><div class="text-muted small fw-bold">Press Enter or click Find to search "'+escapeHtml(term)+'".</div></div>';
        }
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
        const billHistoryBtn = e.target.closest('.js-bill-history');
        if (billHistoryBtn) {
            e.preventDefault();
            const value = billHistoryBtn.dataset.value || '';
            document.getElementById('billSearch').value = value;
            searchBill(value);
            return;
        }

        const productHistoryBtn = e.target.closest('.js-product-history');
        if (productHistoryBtn) {
            e.preventDefault();
            const id = String(productHistoryBtn.dataset.id || '');
            const input = document.querySelector('.js-product-search[data-id="'+id+'"]');
            if (input) {
                input.value = productHistoryBtn.dataset.value || '';
                resetSelectedProductForSearch(id);
                findProduct(id);
            }
            return;
        }

        const productPick = e.target.closest('.js-product-pick');
        if (productPick) {
            e.preventDefault();
            const id = String(productPick.dataset.id || '');
            const index = parseInt(productPick.dataset.index || -1, 10);
            const product = (returnExchangeState.productSearchResults[id] || [])[index];
            if (product) {
                selectExchangeProduct(id, product);
                showMessage('success', 'New product selected.');
            }
            return;
        }

        const findBtn = e.target.closest('.js-find-product');
        if (findBtn) {
            e.preventDefault();
            findProduct(findBtn.dataset.id);
        }

        const reprintBtn = e.target.closest('.js-reprint-history');
        if (reprintBtn) {
            e.preventDefault();
            const id = parseInt(reprintBtn.dataset.id, 10);
            const type = reprintBtn.dataset.type || 'RETURN';
            reprintHistory(id, type);
            return;
        }
    });

    // ============================================
    // SCAN BILL BARCODE (Focus Input)
    // ============================================
    const scanBillBarcodeBtn = document.getElementById('scanBillBarcode');
    if (scanBillBarcodeBtn) {
        scanBillBarcodeBtn.addEventListener('click', function(){
            const input = document.getElementById('billSearch');
            const hint = document.getElementById('billScanHint');
            input.classList.add('scan-ready');
            if (hint) hint.classList.remove('d-none');
            input.focus();
            input.select();
            showMessage('success', 'Scanner ready. Scan bill barcode now.');
            window.setTimeout(function(){
                input.classList.remove('scan-ready');
                if (hint) hint.classList.add('d-none');
            }, 9000);
        });
    }

    // ============================================
    // INITIALIZATION
    // ============================================
    renderBill();
    renderBillSearchHistory();
    loadRecentHistory(true);
    if (window.lucide) window.lucide.createIcons();

    document.getElementById('returnPeriodSettingsBtn')?.addEventListener('click', openReturnPeriodSettings);
    document.getElementById('saveReturnPeriodBtn')?.addEventListener('click', saveReturnPeriodSettings);
    document.getElementById('resetReturnPeriodBtn')?.addEventListener('click', resetReturnPeriodSettings);
    document.getElementById('returnPeriodDays')?.addEventListener('input', updateReturnPeriodPreview);
    document.getElementById('returnPeriodHours')?.addEventListener('input', updateReturnPeriodPreview);
    document.getElementById('returnPeriodQuickSelect')?.addEventListener('change', function() {
        if (this.value !== '') {
            document.getElementById('returnPeriodDays').value = this.value;
            document.getElementById('returnPeriodHours').value = 0;
            updateReturnPeriodPreview();
        }
    });

})();
</script>
</body>
</html>