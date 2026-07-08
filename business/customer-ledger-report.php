<?php
/**
 * Universal Footwear POS - Customer Ledger Report
 * Place at project root / business folder: customer-ledger-report.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

if (function_exists('require_business_login')) { require_business_login(); }
if (function_exists('require_page_access')) { require_page_access($conn, 'customer-ledger-report.php'); }

$pageTitle = 'Customer Ledger Report';
$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);

function clr_e($value): string
{
    if (function_exists('e')) { return e((string)$value); }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($businessId <= 0) { die('Business session missing. Please login again.'); }
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= clr_e($pageTitle) ?> - GK Footwear POS</title>
    <?php if (file_exists(__DIR__ . '/includes/links.php')) { include __DIR__ . '/includes/links.php'; } ?>
    <style>
        .master-page{font-family:"Inter","Segoe UI",Arial,sans-serif;font-size:12px;font-weight:500}
        .mp-hero{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.06);padding:14px 16px}
        .mp-hero h1{font-size:20px;font-weight:800;margin:0 0 3px;letter-spacing:-.02em;color:var(--text-main,#0f172a)}
        .mp-hero p,.mp-card-sub,.mp-sub{color:var(--text-muted,#64748b)}
        .mp-hero p{font-size:11px;line-height:1.35;margin:0;font-weight:500}
        .mp-hero .btn{font-size:11px;padding:7px 11px;min-height:32px;border-radius:999px;font-weight:700}
        .mp-stat-card{min-height:72px;background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:15px;box-shadow:0 8px 20px rgba(15,23,42,.06);padding:11px 12px;display:flex;align-items:center;gap:10px}
        .mp-stat-icon{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;color:#fff;flex:0 0 auto}.mp-stat-icon svg{width:17px;height:17px}
        .mp-stat-label{font-size:10.5px;color:var(--text-muted,#64748b);font-weight:700;line-height:1.15}
        .mp-stat-value{font-size:18px;color:var(--text-main,#0f172a);font-weight:800;margin:1px 0;line-height:1.05}
        .mp-stat-sub{font-size:10px;color:var(--text-muted,#64748b);font-weight:550;line-height:1.15}
        .mp-card{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.06);overflow:hidden}
        .mp-card-head{padding:12px 14px;border-bottom:1px solid var(--border-soft,#dbe4f0)}
        .mp-card-title{font-size:15px;font-weight:800;color:var(--text-main,#0f172a);margin:0 0 2px}
        .mp-card-sub{font-size:11px;margin:0}.mp-sub{font-size:10px;line-height:1.25}
        .mp-filter-input,.mp-filter-select{min-height:32px;font-size:11px;border-radius:999px;padding:5px 10px}
        .ledger-filter-grid{display:grid;grid-template-columns:.9fr .9fr 1.1fr 1.25fr 1.05fr 1.05fr 1.2fr .8fr auto;gap:8px;align-items:end}
        .ledger-filter-grid label{font-size:10px;font-weight:750;color:var(--text-muted,#64748b);margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
        .ledger-tabs{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:12px}
        .ledger-tab-btn{border:1px solid var(--border-soft,#dbe4f0);background:var(--card-bg,#fff);color:var(--text-main,#0f172a);border-radius:999px;padding:7px 11px;font-size:10.5px;font-weight:750;display:inline-flex;align-items:center;gap:5px;box-shadow:0 5px 12px rgba(15,23,42,.04)}
        .ledger-tab-btn.active{background:#0f172a;border-color:#0f172a;color:#fff}
        .mp-table th{font-size:9.5px;font-weight:750;padding:8px 9px;white-space:nowrap;background:#f1f5f9;color:#0f172a;text-transform:uppercase;letter-spacing:.04em;border-bottom:0;text-align:left}
        .mp-table td{font-size:10.5px;padding:8px 9px;vertical-align:middle;line-height:1.2;text-align:left}
        .ledger-table{width:100%;min-width:1120px!important;table-layout:auto}
        .ledger-table th,.ledger-table td{white-space:nowrap;word-break:normal;overflow-wrap:normal}
        .ledger-table td .mp-title,.ledger-table td .mp-sub{white-space:normal}
        .ledger-table th:nth-child(1),.ledger-table td:nth-child(1){min-width:155px;width:18%}
        .ledger-table th:nth-child(2),.ledger-table td:nth-child(2){min-width:115px;width:10%}
        .ledger-table th:nth-child(3),.ledger-table td:nth-child(3){min-width:105px;width:9%;text-align:right}
        .ledger-table th:nth-child(4),.ledger-table td:nth-child(4){min-width:75px;width:7%;text-align:center}
        .ledger-table th:nth-child(5),.ledger-table td:nth-child(5){min-width:125px;width:11%;text-align:right}
        .ledger-table th:nth-child(6),.ledger-table td:nth-child(6){min-width:115px;width:10%;text-align:right}
        .ledger-table th:nth-child(7),.ledger-table td:nth-child(7){min-width:125px;width:11%;text-align:right}
        .ledger-table th:nth-child(8),.ledger-table td:nth-child(8){min-width:140px;width:13%;text-align:left}
        .ledger-table th:nth-child(9),.ledger-table td:nth-child(9){min-width:120px;width:10%;text-align:center}
        .ledger-table .customer-cell{min-width:155px}
        .ledger-table .mobile-cell{min-width:115px;text-align:left}
        .ledger-table .count-cell{text-align:center}
        .ledger-table .amount-cell{text-align:right;font-variant-numeric:tabular-nums}
        .ledger-table .date-cell{min-width:140px;text-align:left}
        .ledger-table .action-cell{text-align:center}
        .mp-avatar{width:30px;height:30px;border-radius:10px;display:grid;place-items:center;background:linear-gradient(135deg,var(--brand-1,#2563eb),var(--brand-2,#7c3aed));color:#fff;font-size:12px;font-weight:800;flex:0 0 auto}
        .mp-title{font-size:11px;font-weight:800;color:var(--text-main,#0f172a);line-height:1.2}
        .mp-badge{border-radius:999px;padding:4px 7px;font-size:9.5px;font-weight:700;display:inline-flex;align-items:center;gap:4px;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .status-active,.badge-money{background:#dcfce7;color:#15803d}.status-cancelled,.badge-due{background:#fee2e2;color:#b91c1c}
        .status-deleted{background:#f1f5f9;color:#475569}.badge-count{background:#fef3c7;color:#b45309}.badge-code{background:#dbeafe;color:#1d4ed8}.badge-type{background:#ede9fe;color:#6d28d9}.badge-branch{background:#ecfeff;color:#0e7490}.badge-muted{background:#f1f5f9;color:#475569}
        .amount-good{color:#15803d;font-weight:800}.amount-due{color:#b91c1c;font-weight:800}.amount-dark{color:#0f172a;font-weight:800}.right{text-align:right}.center{text-align:center}
        .mp-action-btn{border-radius:999px;font-size:10px;font-weight:700;padding:5px 8px;display:inline-flex;align-items:center;gap:4px;line-height:1}
        .mp-mobile-card{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:14px;box-shadow:0 8px 20px rgba(15,23,42,.06);padding:10px}
        .chart-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:12px;margin-bottom:14px}
        .chart-box{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.06);padding:12px}
        .chart-title{font-size:13px;font-weight:850;margin:0 0 8px}
        .bar-row{display:grid;grid-template-columns:75px 1fr 90px;gap:8px;align-items:center;margin:7px 0}.mini-bar{height:8px;border-radius:999px;background:#e2e8f0;overflow:hidden}.mini-bar span{display:block;height:100%;background:linear-gradient(90deg,#2563eb,#06b6d4)}
        .statement-opening-row td{background:#fff7ed!important;color:#9a3412;font-weight:800}.statement-opening-row .mp-badge{background:#fed7aa;color:#c2410c}.statement-opening-row .badge-branch{background:#ffedd5;color:#c2410c}
        .statement-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px;margin-bottom:12px}
        .statement-mini-card{border:1px solid var(--border-soft,#dbe4f0);background:#f8fafc;border-radius:14px;padding:10px}
        .statement-mini-card small{display:block;font-size:10px;font-weight:750;color:var(--text-muted,#64748b);text-transform:uppercase;letter-spacing:.04em}.statement-mini-card b{display:block;font-size:16px;font-weight:850;margin-top:3px}
        .statement-table-wrap{max-height:58vh;overflow:auto}.empty{padding:35px;text-align:center;color:var(--text-muted,#64748b);font-weight:700}
        .live-note{border:1px dashed #bfdbfe;background:#eff6ff;color:#1d4ed8;border-radius:14px;padding:9px 11px;font-size:11px;font-weight:700}
        html,body{max-width:100%;overflow-x:hidden}#main{min-width:0;max-width:100%;overflow-x:hidden}.master-page,.mp-card,.ledger-list-card{max-width:100%;min-width:0}
        .ledger-list-card .table-responsive{width:100%;max-width:100%;overflow-x:auto;overflow-y:visible}.pagination-pill{border:1px solid #dbe4f0;background:#fff;border-radius:999px;padding:5px 10px;font-size:11px;font-weight:750}.pagination-pill:disabled{opacity:.45}
        .sortable{cursor:pointer;user-select:none}.sortable:after{content:'↕';font-size:8px;margin-left:4px;color:#94a3b8}
        @media(max-width:1400px){.ledger-filter-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.chart-grid{grid-template-columns:1fr}}
        @media(max-width:767px){.mp-hero{padding:12px}.mp-hero h1{font-size:19px}.mp-stat-card{min-height:64px;padding:9px 10px}.mp-stat-icon{width:34px;height:34px;border-radius:11px}.mp-stat-value{font-size:16px}.ledger-filter-grid{grid-template-columns:1fr}.statement-summary-grid{grid-template-columns:1fr 1fr}.chart-grid{grid-template-columns:1fr}}
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
                        <h1>Customer Ledger Report</h1>
                        <p>Customer-wise opening balance, bill/debit additions, payment/credit decreases and accurate closing balance.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" onclick="resetFilters()"><i data-lucide="refresh-cw" style="width:14px;height:14px"></i> Reset</button>
                        <button type="button" class="btn btn-success" onclick="exportExcel()"><i data-lucide="file-spreadsheet" style="width:14px;height:14px"></i> Excel</button>
                        <button type="button" class="btn btn-primary" onclick="exportCsv()"><i data-lucide="download" style="width:14px;height:14px"></i> CSV</button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb)"><i data-lucide="users"></i></div><div><div class="mp-stat-label">Customers</div><div class="mp-stat-value" id="totalCustomers">0</div><div class="mp-stat-sub">Ledger customers</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9"><i data-lucide="wallet-cards"></i></div><div><div class="mp-stat-label">Opening</div><div class="mp-stat-value" id="openingBalance">₹0.00</div><div class="mp-stat-sub">Opening balance</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#e0f2fe;color:#0369a1"><i data-lucide="receipt"></i></div><div><div class="mp-stat-label">Bills</div><div class="mp-stat-value" id="totalBillAmount">₹0.00</div><div class="mp-stat-sub">Bill value</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d"><i data-lucide="arrow-down-left"></i></div><div><div class="mp-stat-label">Paid</div><div class="mp-stat-value" id="totalPaidAmount">₹0.00</div><div class="mp-stat-sub">Paid amount</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c"><i data-lucide="badge-alert"></i></div><div><div class="mp-stat-label">Balance</div><div class="mp-stat-value" id="totalBalanceAmount">₹0.00</div><div class="mp-stat-sub"><span id="outstandingCustomers">0</span> due customers</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-2"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309"><i data-lucide="check-circle"></i></div><div><div class="mp-stat-label">Clear</div><div class="mp-stat-value" id="clearCustomers">0</div><div class="mp-stat-sub">No balance</div></div></article></div>
            </div>

            <div class="chart-grid" id="chartGrid">
                <div class="chart-box"><h3 class="chart-title">Daily Bill Trend</h3><div id="dailyTrendChart" class="mp-sub">Loading trend...</div></div>
                <div class="chart-box"><h3 class="chart-title">Ledger Type Mix</h3><div id="purposeMixChart" class="mp-sub">Loading type mix...</div></div>
            </div>

            <div class="ledger-tabs">
                <button class="ledger-tab-btn active" type="button" data-tab="customers" onclick="changeTab('customers')"><i data-lucide="users" style="width:13px;height:13px"></i> Customer Summary</button>
                <button class="ledger-tab-btn" type="button" data-tab="ledger" onclick="changeTab('ledger')"><i data-lucide="book-open" style="width:13px;height:13px"></i> Ledger Entries</button>
                <button class="ledger-tab-btn" type="button" data-tab="bills" onclick="changeTab('bills')"><i data-lucide="receipt" style="width:13px;height:13px"></i> Bill History</button>
                <button class="ledger-tab-btn" type="button" data-tab="outstanding" onclick="changeTab('outstanding')"><i data-lucide="badge-alert" style="width:13px;height:13px"></i> Balance</button>
            </div>

            <section class="mp-card ledger-list-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div>
                                <h2 class="mp-card-title" id="tableTitle">Customer Ledger Summary</h2>
                                <p class="mp-card-sub" id="tableSub">Customer-wise bills, payments and current outstanding position.</p>
                            </div>
                            <div class="mp-sub" id="rowCount">0 rows</div>
                        </div>

                        <form method="get" id="filterForm" class="ledger-filter-grid">
                            <div><label>From</label><input type="date" class="form-control mp-filter-input" id="from_date" name="from_date"></div>
                            <div><label>To</label><input type="date" class="form-control mp-filter-input" id="to_date" name="to_date"></div>
                            <div><label>Branch / Firm</label><select class="form-select mp-filter-select" id="branch_id" name="branch_id"><option value="">All</option></select></div>
                            <div><label>Customer</label><select class="form-select mp-filter-select" id="customer_id" name="customer_id"><option value="">All</option></select></div>
                            <div><label>Balance</label><select class="form-select mp-filter-select" id="ledger_status" name="ledger_status"><option value="">All</option><option value="outstanding">Balance</option><option value="clear">Clear</option></select></div>
                            <div><label>Payment</label><select class="form-select mp-filter-select" id="payment_status" name="payment_status"><option value="">All</option><option value="pending">Pending</option><option value="partial">Partial</option><option value="paid">Paid</option><option value="cancelled">Cancelled</option></select></div>
                            <div><label>Reference</label><select class="form-select mp-filter-select" id="reference_type" name="reference_type"><option value="">All</option></select></div>
                            <div><label>Rows</label><select class="form-select mp-filter-select" id="per_page" name="per_page"><option>25</option><option>50</option><option>100</option><option>200</option></select></div>
                            <div><button class="btn btn-dark btn-sm rounded-pill fw-bold px-3 w-100" type="submit">Filter</button></div>
                            <div style="grid-column:1/-1"><input type="text" class="form-control mp-filter-input w-100" id="search" name="search" placeholder="Search customer / mobile / bill no / remarks"></div>
                            <input type="hidden" name="page" id="page" value="1">
                            <input type="hidden" name="sort_by" id="sort_by" value="customer_name">
                            <input type="hidden" name="sort_dir" id="sort_dir" value="asc">
                        </form>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table ledger-table mb-0">
                        <thead id="tableHead"></thead>
                        <tbody id="tableBody"><tr><td class="text-center text-muted py-4">Loading customer ledger...</td></tr></tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="ledgerMobileCards"><div class="mp-mobile-card text-center text-muted">Loading customer ledger...</div></div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 py-2 border-top">
                    <div class="mp-sub" id="paginationInfo">Page 1 of 1</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="pagination-pill" id="prevPage">Previous</button>
                        <button type="button" class="pagination-pill" id="nextPage">Next</button>
                    </div>
                </div>
            </section>

            <div class="live-note mt-3">Customer ledger report rule: Bill/Debit adds outstanding amount, Payment/Credit decreases outstanding amount. Columns are aligned using inner value width.</div>

            <?php if (file_exists(__DIR__ . '/includes/footer.php')) { include __DIR__ . '/includes/footer.php'; } ?>
        </section>
    </main>
</div>

<div class="modal fade" id="statementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <div><h5 class="modal-title" id="statementTitle">Customer Statement</h5><div class="mp-sub" id="statementSub">Loading statement...</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="statement-summary-grid" id="statementSummary"></div>
                <div class="mp-card">
                    <div class="table-responsive statement-table-wrap">
                        <table class="table mp-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th><th>Type</th><th>Ref No</th><th>Branch</th><th>Description</th><th class="right">Bill </th><th class="right">Credit</th><th class="right">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="statementBody"><tr><td colspan="8" class="text-center text-muted py-4">Select a customer.</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success btn-sm rounded-pill px-3" type="button" onclick="exportStatement('excel')"><i data-lucide="file-spreadsheet" style="width:13px;height:13px"></i> Excel</button>
                <button class="btn btn-primary btn-sm rounded-pill px-3" type="button" onclick="exportStatement('csv')"><i data-lucide="download" style="width:13px;height:13px"></i> CSV</button>
                <button class="btn btn-light btn-sm rounded-pill px-3" type="button" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/includes/script.php')) { include __DIR__ . '/includes/script.php'; } ?>

<script>
(function(){
    'use strict';
    window.ledgerState={tab:'customers',masters:{},rows:[],pagination:{page:1,total_pages:1,total:0},statementCustomerId:0,statementModal:null};
    const today=new Date(), first=new Date(today.getFullYear(), today.getMonth(), 1);
    document.getElementById('from_date').value=formatDateLocal(first);
    document.getElementById('to_date').value=formatDateLocal(today);
    document.getElementById('filterForm').addEventListener('submit',function(e){e.preventDefault();setPage(1);loadCurrent();});
    document.getElementById('search').addEventListener('input',debounce(function(){setPage(1);loadCurrent();},450));
    document.getElementById('per_page').addEventListener('change',function(){setPage(1);loadCurrent();});
    document.getElementById('prevPage').addEventListener('click',function(){if(ledgerState.pagination.page>1){setPage(ledgerState.pagination.page-1);loadCurrent();}});
    document.getElementById('nextPage').addEventListener('click',function(){if(ledgerState.pagination.page<ledgerState.pagination.total_pages){setPage(ledgerState.pagination.page+1);loadCurrent();}});
    ['branch_id','customer_id','ledger_status','payment_status','reference_type'].forEach(id=>document.getElementById(id).addEventListener('change',function(){setPage(1);loadCurrent();}));
    init();
})();

function formatDateLocal(date){return date.getFullYear()+'-'+String(date.getMonth()+1).padStart(2,'0')+'-'+String(date.getDate()).padStart(2,'0')}
function money(v){v=parseFloat(v||0);return '₹'+v.toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function num(v){v=parseFloat(v||0);return v.toLocaleString('en-IN',{maximumFractionDigits:2})}
function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(s){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]})}
function debounce(fn,ms){let t;return function(){clearTimeout(t);t=setTimeout(fn,ms)}}
function showMessage(type,message){const tt=type==='success'?'success':(type==='warning'?'warning':'error');if(window.AppToast&&typeof window.AppToast.show==='function'){window.AppToast.show(tt,message);return}alert(message)}
function refreshIcons(){if(window.lucide&&typeof window.lucide.createIcons==='function')window.lucide.createIcons()}
function customerInitial(name){const n=String(name||'C').trim();return esc(n.substring(0,1).toUpperCase()||'C')}

function qs(extra){
    const fd=new FormData(document.getElementById('filterForm')), p=new URLSearchParams();
    fd.forEach((v,k)=>{if(v!==''&&v!=null)p.append(k,v)});
    Object.keys(extra||{}).forEach(k=>p.set(k,extra[k]));
    return p.toString();
}

async function api(action,extra){
    const res=await fetch('api/customer-ledger-report-api.php?'+qs(Object.assign({action:action},extra||{})),{credentials:'same-origin',headers:{'Accept':'application/json'}});
    return await res.json();
}

async function init(){
    try{
        const data=await api('init');
        if(!data.success){showMessage('error',data.message||'Unable to load report.');return}
        ledgerState.masters=data.masters||{};
        fillSelect('branch_id',ledgerState.masters.branches||[],'branch_id',r=>(r.branch_name||'')+(r.floor_name?' - '+r.floor_name:''));
        fillSelect('customer_id',ledgerState.masters.customers||[],'customer_id',r=>(r.customer_name||'')+(r.mobile?' - '+r.mobile:''));
        fillSelect('reference_type',ledgerState.masters.reference_types||[],'value',r=>r.label||r.value);
        renderKpis(data.summary||{});
        renderCharts(data.summary||{});
        await loadCurrent();
        refreshIcons();
    }catch(e){showMessage('error','Customer ledger API error. Please check API/model files.')}
}

function fillSelect(id,rows,key,labelFn){const s=document.getElementById(id),first=s.options[0].outerHTML;s.innerHTML=first;rows.forEach(r=>{const o=document.createElement('option');o.value=r[key];o.textContent=labelFn(r);s.appendChild(o)})}

function renderKpis(s){
    document.getElementById('totalCustomers').textContent=parseInt(s.total_customers||0,10);
    document.getElementById('openingBalance').textContent=money(s.opening_outstanding);
    document.getElementById('totalBillAmount').textContent=money(s.total_bill_amount);
    document.getElementById('totalPaidAmount').textContent=money(s.total_paid_amount);
    document.getElementById('totalBalanceAmount').textContent=money(s.total_balance_amount);
    document.getElementById('outstandingCustomers').textContent=parseInt(s.outstanding_customers||0,10);
    document.getElementById('clearCustomers').textContent=parseInt(s.clear_customers||0,10);
}

function renderCharts(s){
    const trend=s.daily_trend||[], mix=s.purpose_mix||[];
    let max=0; trend.forEach(r=>max=Math.max(max,parseFloat(r.bill_amount||0)));
    document.getElementById('dailyTrendChart').innerHTML=trend.length?trend.map(r=>`<div class="bar-row"><div class="mp-sub">${esc(r.display_date||r.entry_date)}</div><div class="mini-bar"><span style="width:${max?Math.max(5,parseFloat(r.bill_amount||0)/max*100):0}%"></span></div><div class="mp-title right">${money(r.bill_amount)}</div></div>`).join(''):'<div class="empty">No bill data.</div>';
    let max2=0; mix.forEach(r=>max2=Math.max(max2,parseFloat(r.total_amount||0)));
    document.getElementById('purposeMixChart').innerHTML=mix.length?mix.map(r=>`<div class="bar-row"><div class="mp-sub">${esc(r.purpose_name)}</div><div class="mini-bar"><span style="width:${max2?Math.max(5,parseFloat(r.total_amount||0)/max2*100):0}%"></span></div><div class="mp-title right">${money(r.total_amount)}</div></div>`).join(''):'<div class="empty">No ledger data.</div>';
}

function changeTab(tab){
    ledgerState.tab=tab;
    document.querySelectorAll('.ledger-tab-btn').forEach(b=>b.classList.toggle('active',b.dataset.tab===tab));
    document.getElementById('ledger_status').disabled=(tab==='ledger'||tab==='bills');
    document.getElementById('payment_status').disabled=(tab!=='bills');
    document.getElementById('reference_type').disabled=(tab!=='ledger');
    setPage(1);
    loadCurrent();
    refreshIcons();
}

function actionForTab(){return {customers:'customers',ledger:'ledger',bills:'bills',outstanding:'outstanding'}[ledgerState.tab]||'customers'}
function setPage(page){document.getElementById('page').value=page}

async function loadCurrent(){
    try{
        const data=await api(actionForTab());
        if(!data.success){showMessage('error',data.message||'Unable to load data.');return}
        if(data.summary){renderKpis(data.summary);renderCharts(data.summary)}
        ledgerState.rows=data.rows||[];
        ledgerState.pagination=data.pagination||{page:1,total_pages:1,total:ledgerState.rows.length};
        renderTable(ledgerState.tab,ledgerState.rows);
        renderPagination();
        refreshIcons();
    }catch(e){showMessage('error','Unable to load customer ledger report.')}
}

function renderPagination(){
    const p=ledgerState.pagination||{};
    document.getElementById('paginationInfo').textContent='Page '+(p.page||1)+' of '+(p.total_pages||1)+' · Total '+(p.total||0)+' rows';
    document.getElementById('prevPage').disabled=(p.page||1)<=1;
    document.getElementById('nextPage').disabled=(p.page||1)>=(p.total_pages||1);
}

function cfgFor(tab){
    return {
        customers:{
            title:'Customer Ledger Summary',
            sub:'Customer-wise bills, payments and current balance with aligned columns.',
            head:[['customer_name','Customer'],['mobile','Mobile'],['opening_outstanding','Opening'],['bill_count','Bills'],['total_bill_amount','Bill Value'],['total_paid_amount','Paid'],['balance_amount','Balance'],['last_bill','Last Bill'],['','Action']],
            row:r=>[
                `<div class="d-flex align-items-center gap-2"><div class="mp-avatar">${customerInitial(r.customer_name)}</div><div><div class="mp-title">${esc(r.customer_name)}</div><div class="mp-sub">ID: ${esc(r.customer_id)} · GST: ${esc(r.gstin||'-')}</div></div></div>`,
                esc(r.mobile||'-'),money(r.opening_outstanding),`<span class="mp-badge badge-count">${num(r.bill_count)}</span>`,money(r.total_bill_amount),`<span class="amount-good">${money(r.total_paid_amount)}</span>`,`<span class="${parseFloat(r.balance_amount||0)>0?'amount-due':'amount-good'}">${money(r.balance_amount)}</span>`,esc(r.last_bill_display||'-'),statementBtn(r.customer_id)
            ],
            mobile:r=>mobileCustomer(r)
        },
        ledger:{
            title:'Customer Ledger Entries',
            sub:'Detailed debit, credit and running balance ledger records.',
            head:[['date','Date'],['customer_name','Customer'],['reference_type','Type'],['','Reference'],['','Branch'],['debit','Debit'],['credit','Credit'],['balance','Balance'],['','Remarks']],
            row:r=>[esc(r.entry_display||'-'),`<div class="mp-title">${esc(r.customer_name)}</div><div class="mp-sub">${esc(r.mobile||'')}</div>`,`<span class="mp-badge badge-type">${esc(r.reference_type||'-')}</span>`,esc(r.reference_no||'-'),branchBadge(r),money(r.debit),`<span class="amount-good">${money(r.credit)}</span>`,`<span class="${parseFloat(r.balance||0)>0?'amount-due':'amount-good'}">${money(r.balance)}</span>`,esc(r.remarks||'')],
            mobile:r=>mobileLedger(r)
        },
        bills:{
            title:'Customer Bill History',
            sub:'Customer-wise bill generation and collection status.',
            head:[['date','Date'],['bill_no','Bill No'],['customer_name','Customer'],['','Branch'],['net_amount','Bill Amount'],['paid_amount','Paid'],['balance_amount','Balance'],['payment_status','Payment'],['','Created By']],
            row:r=>[esc(r.bill_display||'-'),`<span class="mp-badge badge-code">${esc(r.bill_no||'-')}</span>`,`<div class="mp-title">${esc(r.customer_name||'-')}</div><div class="mp-sub">${esc(r.mobile||'')}</div>`,branchBadge(r),`<span class="amount-dark">${money(r.net_amount)}</span>`,`<span class="amount-good">${money(r.paid_amount)}</span>`,`<span class="${parseFloat(r.balance_amount||0)>0?'amount-due':'amount-good'}">${money(r.balance_amount)}</span>`,statusBadge(r.payment_status),esc(r.created_by_name||'-')],
            mobile:r=>mobileBill(r)
        },
        outstanding:{
            title:'Customer Balance',
            sub:'Customers with pending balance amount.',
            head:[['customer_name','Customer'],['mobile','Mobile'],['opening_outstanding','Opening'],['bill_count','Bills'],['total_bill_amount','Bill Value'],['total_paid_amount','Paid'],['balance_amount','Balance'],['last_bill','Last Bill'],['','Action']],
            row:r=>[
                `<div class="d-flex align-items-center gap-2"><div class="mp-avatar">${customerInitial(r.customer_name)}</div><div><div class="mp-title">${esc(r.customer_name)}</div><div class="mp-sub">ID: ${esc(r.customer_id)} · GST: ${esc(r.gstin||'-')}</div></div></div>`,
                esc(r.mobile||'-'),money(r.opening_outstanding),`<span class="mp-badge badge-count">${num(r.bill_count)}</span>`,money(r.total_bill_amount),`<span class="amount-good">${money(r.total_paid_amount)}</span>`,`<span class="amount-due">${money(r.balance_amount)}</span>`,esc(r.last_bill_display||'-'),statementBtn(r.customer_id)
            ],
            mobile:r=>mobileCustomer(r)
        }
    }[tab];
}

function renderTable(tab,rows){
    const cfg=cfgFor(tab);
    document.getElementById('tableTitle').textContent=cfg.title;
    document.getElementById('tableSub').textContent=cfg.sub;
    document.getElementById('rowCount').textContent=rows.length+' rows';
    document.getElementById('tableHead').innerHTML='<tr>'+cfg.head.map(h=>`<th class="${h[0]?'sortable':''}" onclick="sortBy('${h[0]}')">${esc(h[1])}</th>`).join('')+'</tr>';
    if(!rows.length){
        document.getElementById('tableBody').innerHTML='<tr><td colspan="'+cfg.head.length+'" class="text-center text-muted py-4">No records found.</td></tr>';
        document.getElementById('ledgerMobileCards').innerHTML='<div class="mp-mobile-card text-center text-muted">No records found.</div>';
        return;
    }
    document.getElementById('tableBody').innerHTML=rows.map(r=>'<tr>'+cfg.row(r).map((c,i)=>'<td class="'+cellClass(tab,i)+'">'+c+'</td>').join('')+'</tr>').join('');
    document.getElementById('ledgerMobileCards').innerHTML=rows.map(r=>cfg.mobile(r)).join('');
}

function sortBy(key){if(!key)return;const sb=document.getElementById('sort_by'),sd=document.getElementById('sort_dir');if(sb.value===key){sd.value=sd.value==='asc'?'desc':'asc'}else{sb.value=key;sd.value=key==='customer_name'?'asc':'desc'}setPage(1);loadCurrent()}
function cellClass(tab,i){const map={customers:['customer-cell','mobile-cell','amount-cell','count-cell','amount-cell','amount-cell','amount-cell','date-cell','action-cell'],balance:['customer-cell','mobile-cell','amount-cell','count-cell','amount-cell','amount-cell','amount-cell','date-cell','action-cell'],outstanding:['customer-cell','mobile-cell','amount-cell','count-cell','amount-cell','amount-cell','amount-cell','date-cell','action-cell'],ledger:['date-cell','customer-cell','type-cell','purpose-cell','reference-cell','branch-cell','amount-cell','amount-cell','amount-cell','remarks-cell'],bills:['date-cell','bill-cell','customer-cell','branch-cell','count-cell','amount-cell','amount-cell','status-cell','action-cell']};return (map[tab]&&map[tab][i])?map[tab][i]:numericClass(tab,i)}
function numericClass(tab,i){return ({customers:[2,3,4,5,6],balance:[2,3,4,5,6],outstanding:[2,3,4,5,6],ledger:[6,7,8],bills:[4,5,6]}[tab]||[]).includes(i)?'right amount-cell':''}
function branchBadge(r){const v=((r.branch_name||'')+(r.floor_name?' - '+r.floor_name:''))||'-';return `<span class="mp-badge badge-branch">${esc(v)}</span>`}
function statusBadge(v){const s=String(v||'-').toLowerCase();const cls=s==='paid'?'status-active':(s==='pending'||s==='partial'?'status-cancelled':'badge-muted');return `<span class="mp-badge ${cls}">${esc(s.charAt(0).toUpperCase()+s.slice(1))}</span>`}
function statementBtn(id){return `<button type="button" class="btn btn-sm btn-outline-primary mp-action-btn" onclick="openStatement(${parseInt(id,10)})"><i data-lucide="book-open" style="width:12px;height:12px"></i> Statement</button>`}

function mobileCustomer(r){
    const bal=parseFloat(r.balance_amount||0);
    return `<div class="mp-mobile-card"><div class="d-flex justify-content-between gap-2"><div class="d-flex gap-2"><div class="mp-avatar">${customerInitial(r.customer_name)}</div><div><div class="mp-title">${esc(r.customer_name)}</div><div class="mp-sub">${esc(r.mobile||'-')}</div></div></div><span class="mp-badge ${bal>0?'badge-due':'badge-money'}">${money(bal)}</span></div><div class="row g-2 mt-2"><div class="col-6"><div class="mp-sub">Bills</div><div class="mp-title">${num(r.bill_count)}</div></div><div class="col-6"><div class="mp-sub">Paid</div><div class="mp-title amount-good">${money(r.total_paid_amount)}</div></div><div class="col-6"><div class="mp-sub">Bill Value</div><div class="mp-title">${money(r.total_bill_amount)}</div></div><div class="col-6"><div class="mp-sub">Opening</div><div class="mp-title">${money(r.opening_outstanding)}</div></div></div><div class="mt-2">${statementBtn(r.customer_id)}</div></div>`;
}
function mobileLedger(r){return `<div class="mp-mobile-card"><div class="d-flex justify-content-between"><div><div class="mp-title">${esc(r.customer_name)}</div><div class="mp-sub">${esc(r.entry_display||'-')}</div></div><span class="mp-badge badge-type">${esc(r.reference_type||'-')}</span></div><div class="row g-2 mt-2"><div class="col-4"><div class="mp-sub">Debit</div><div class="mp-title amount-due">${money(r.debit)}</div></div><div class="col-4"><div class="mp-sub">Credit</div><div class="mp-title amount-good">${money(r.credit)}</div></div><div class="col-4"><div class="mp-sub">Balance</div><div class="mp-title">${money(r.balance)}</div></div></div></div>`}
function mobileBill(r){return `<div class="mp-mobile-card"><div class="d-flex justify-content-between"><div><div class="mp-title">${esc(r.bill_no||'-')}</div><div class="mp-sub">${esc(r.bill_display||'-')} · ${esc(r.customer_name||'-')}</div></div><span class="mp-badge badge-money">${money(r.net_amount)}</span></div><div class="mp-sub mt-2">Paid ${money(r.paid_amount)} · Balance ${money(r.balance_amount)} · ${esc(r.payment_status||'')}</div></div>`}

function getStatementModal(){const el=document.getElementById('statementModal');if(window.bootstrap&&window.bootstrap.Modal){if(!ledgerState.statementModal)ledgerState.statementModal=new window.bootstrap.Modal(el);return ledgerState.statementModal}return null}

async function openStatement(customerId){
    ledgerState.statementCustomerId=customerId;
    document.getElementById('statementTitle').textContent='Customer Statement';
    document.getElementById('statementSub').textContent='Loading statement...';
    document.getElementById('statementSummary').innerHTML='';
    document.getElementById('statementBody').innerHTML='<tr><td colspan="8" class="text-center text-muted py-4">Loading statement...</td></tr>';
    const m=getStatementModal(); if(m)m.show();

    try{
        const data=await api('statement',{customer_id:customerId});
        if(!data.success){showMessage('error',data.message||'Unable to load statement.');return}
        const c=data.customer||{}, sum=data.summary||{}, rows=data.rows||[];
        document.getElementById('statementTitle').textContent=c.customer_name||'Customer Statement';
        document.getElementById('statementSub').textContent=(c.mobile?c.mobile+' · ':'')+'Calculated current: '+money(sum.current_balance);
        document.getElementById('statementSummary').innerHTML=[
            ['Opening',money(sum.opening_balance),'Opening / previous'],
            ['Debit',money(sum.total_debit),'Bill / debit adds'],
            ['Credit',money(sum.total_credit),'Payment / credit decreases'],
            ['Closing',money(sum.closing_balance),'Opening + Debit - Credit'],
            ['Current',money(sum.current_balance),'Calculated total']
        ].map(x=>`<div class="statement-mini-card"><small>${esc(x[0])}</small><b>${esc(x[1])}</b><small>${esc(x[2])}</small></div>`).join('');

        document.getElementById('statementBody').innerHTML=rows.length?rows.map(r=>`<tr class="${parseInt(r.is_opening||0,10)===1?'statement-opening-row':''}"><td>${esc(r.entry_display||r.entry_datetime||'-')}</td><td><span class="mp-badge badge-type">${esc(r.display_type||r.reference_type||'-')}</span></td><td>${esc(r.reference_no||'-')}</td><td>${branchBadge(r)}</td><td>${esc(r.remarks||'')}</td><td class="right amount-due">${money(r.debit)}</td><td class="right amount-good">${money(r.credit)}</td><td class="right">${money(r.balance)}</td></tr>`).join(''):'<tr><td colspan="8" class="text-center text-muted py-4">No statement entries.</td></tr>';
        refreshIcons();
    }catch(e){showMessage('error','Unable to load customer statement.')}
}

function resetFilters(){
    document.getElementById('filterForm').reset();
    const today=new Date(), first=new Date(today.getFullYear(),today.getMonth(),1);
    document.getElementById('from_date').value=formatDateLocal(first);
    document.getElementById('to_date').value=formatDateLocal(today);
    document.getElementById('per_page').value='25';
    document.getElementById('sort_by').value='customer_name';
    document.getElementById('sort_dir').value='asc';
    setPage(1); loadCurrent();
}
function exportType(){return ledgerState.tab==='outstanding'?'outstanding':ledgerState.tab}
function exportCsv(){window.location.href='api/customer-ledger-report-api.php?'+qs({action:'export',export_type:exportType(),format:'csv'})}
function exportExcel(){window.location.href='api/customer-ledger-report-api.php?'+qs({action:'export',export_type:exportType(),format:'excel'})}
function exportStatement(format){if(!ledgerState.statementCustomerId)return;window.location.href='api/customer-ledger-report-api.php?'+qs({action:'export',export_type:'statement',customer_id:ledgerState.statementCustomerId,format:format})}
</script>
</body>
</html>
