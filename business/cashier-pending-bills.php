<?php
/**
 * GK Footwear POS - Pending Collections
 * Shows only pending/partial customer invoices and allows payment collection.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';


require_business_login();
if (function_exists('require_page_access')) {
    require_page_access($conn, 'pending-collections.php');
}

$pageTitle = 'Pending Collections';
$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);
$cashierName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Cashier';
$directBillId = isset($_GET['bill_id']) ? max(0, (int)$_GET['bill_id']) : 0;

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

function pending_collections_csrf_field() {
    if (function_exists('csrf_field')) { return csrf_field(); }
    if (function_exists('csrf_token')) {
        return '<input type="hidden" name="csrf_token" id="pendingCollectionsCsrfToken" value="' . e(csrf_token()) . '">';
    }
    return '<input type="hidden" name="csrf_token" id="pendingCollectionsCsrfToken" value="">';
}

if ($businessId <= 0) { die('Business session missing. Please login again.'); }
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>
    <style>
    .master-page{font-family:"Inter","Segoe UI",Arial,sans-serif;font-size:12px;font-weight:500}.mp-hero,.mp-card,.mp-stat-card,.detail-box{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.06)}.mp-hero{padding:14px 16px}.mp-hero h1{font-size:20px;font-weight:800;margin:0 0 3px;letter-spacing:-.02em;color:var(--text-main,#0f172a)}.mp-hero p{font-size:11px;line-height:1.35;margin:0;color:var(--text-muted,#64748b);font-weight:500}.mp-hero .btn{font-size:11px;padding:7px 11px;min-height:32px;border-radius:999px;font-weight:700}.mp-stat-card{min-height:72px;padding:11px 12px;display:flex;align-items:center;gap:10px}.mp-stat-icon{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;flex:0 0 auto}.mp-stat-icon svg{width:17px;height:17px}.mp-stat-label{font-size:10.5px;color:var(--text-muted,#64748b);font-weight:700;line-height:1.15;text-transform:uppercase;letter-spacing:.04em}.mp-stat-value{font-size:18px;color:var(--text-main,#0f172a);font-weight:800;margin:1px 0;line-height:1.05}.mp-stat-sub,.mp-sub{font-size:10px;color:var(--text-muted,#64748b);line-height:1.25}.mp-card{overflow:hidden}.mp-card-head{padding:12px 14px;border-bottom:1px solid var(--border-soft,#dbe4f0)}.mp-card-title{font-size:15px;font-weight:800;color:var(--text-main,#0f172a);margin:0 0 2px}.mp-card-sub{font-size:11px;color:var(--text-muted,#64748b);margin:0}.mp-filter-input,.mp-filter-select{min-height:32px;font-size:11px;border-radius:999px;padding:5px 10px;font-weight:650}.mp-table th{font-size:10px;font-weight:750;padding:9px 10px;white-space:nowrap;background:#f1f5f9;color:#0f172a;text-transform:uppercase;letter-spacing:.04em;border-bottom:0}.mp-table td{font-size:11px;padding:9px 10px;vertical-align:middle}.mp-avatar{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,var(--brand-1,#2563eb),var(--brand-2,#7c3aed));color:#fff;font-size:13px;font-weight:800;flex:0 0 auto}.mp-title{font-size:12px;font-weight:800;color:var(--text-main,#0f172a);line-height:1.2}.mp-badge{border-radius:999px;padding:5px 8px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:4px;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.status-paid{background:#dcfce7;color:#15803d}.status-partial{background:#fef3c7;color:#b45309}.status-pending{background:#fee2e2;color:#b91c1c}.badge-code{background:#dbeafe;color:#1d4ed8}.badge-branch{background:#ecfeff;color:#0e7490}.badge-money{background:#dcfce7;color:#15803d}.badge-due{background:#fee2e2;color:#b91c1c}.mp-action-btn{border-radius:999px;font-size:10.5px;font-weight:700;padding:5px 8px;display:inline-flex;align-items:center;justify-content:center;gap:4px;line-height:1}.auto-print-frame{position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0;pointer-events:none}.mp-mobile-card{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:14px;box-shadow:0 8px 20px rgba(15,23,42,.06);padding:10px}.detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.detail-box{background:#f8fafc;padding:10px;min-height:70px}.modal-title{font-size:15px;font-weight:750}.modal .form-label{font-size:11px;font-weight:700;margin-bottom:4px}.modal .form-control,.modal .form-select{min-height:34px;font-size:12px;border-radius:12px;padding:6px 10px}.modal-footer .btn{font-size:12px;padding:7px 12px;border-radius:12px;font-weight:700}.amount-positive{color:#15803d;font-weight:800}.amount-due{color:#b91c1c;font-weight:800}.pay-chip{border:1px solid var(--border-soft,#dbe4f0);border-radius:999px;padding:8px 12px;background:#fff;font-size:11px;font-weight:850;cursor:pointer}.pay-chip.active{color:#fff;border-color:transparent;background:linear-gradient(135deg,var(--brand-1,#2563eb),var(--brand-2,#7c3aed));box-shadow:0 8px 18px rgba(37,99,235,.22)}.payment-method-chips{display:flex;flex-wrap:wrap;gap:8px}.mixed-row{display:grid;grid-template-columns:1fr 130px 1fr;gap:8px;align-items:center;margin-bottom:8px}.scan-qr-btn{border-radius:999px;font-size:11px;font-weight:800;padding:7px 11px;display:inline-flex;align-items:center;gap:5px;line-height:1;background:#0f766e;color:#fff;border:1px solid #0f766e}.scan-qr-btn:hover{background:#115e59;color:#fff}.scanner-box{border:1px dashed #93c5fd;background:#eff6ff;border-radius:18px;padding:12px}.scanner-video{width:100%;min-height:280px;max-height:360px;object-fit:cover;background:#020617;border-radius:14px;border:1px solid #cbd5e1}.scanner-status{font-size:11px;font-weight:750;border-radius:14px;padding:9px 10px;background:#f8fafc;border:1px solid #dbe4f0;color:#334155}.scanner-status.success{background:#f0fdf4;color:#15803d;border-color:#bbf7d0}.scanner-status.error{background:#fef2f2;color:#b91c1c;border-color:#fecaca}.manual-scan-card{border:1px solid #e2e8f0;background:#f8fafc;border-radius:16px;padding:12px}.scan-result-value{font-size:11px;word-break:break-all;color:#475569}.clickable-row{cursor:pointer}.clickable-row:hover{background:#f8fafc}@media(max-width:991px){.detail-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:767px){.mp-hero{padding:12px}.mp-hero h1{font-size:19px}.mp-stat-card{min-height:64px;padding:9px 10px}.mp-stat-icon{width:34px;height:34px;border-radius:11px}.mp-stat-value{font-size:16px}.detail-grid,.mixed-row{grid-template-columns:1fr}}
    
    .collection-gst-card{border:1px dashed #93c5fd;background:linear-gradient(180deg,#eff6ff,#fff);box-shadow:0 8px 18px rgba(37,99,235,.08)}
    .gst-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px}
    .gst-toggle-wrap{display:inline-flex;align-items:center;gap:7px;font-size:10px;font-weight:900;color:#334155;white-space:nowrap;cursor:pointer}
    .gst-toggle-wrap input{width:17px;height:17px;accent-color:#2563eb;cursor:pointer}
    .gst-status-pill{border-radius:999px;padding:5px 8px;font-size:10px;font-weight:900;background:#fee2e2;color:#b91c1c;min-width:72px;text-align:center}
    .gst-status-pill.on{background:#dcfce7;color:#15803d}
    .gst-tax-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin:7px 0}
    .gst-tax-row .form-control,.gst-tax-row .form-select{font-size:11px;min-height:34px}
    .gst-line{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:4px 0;font-size:11px;color:#334155}
    .gst-line strong{font-size:11px;color:#0f172a}
    .gst-total{border-top:1px dashed #cbd5e1;margin-top:5px;padding-top:8px;font-weight:900}
    .gst-hidden{display:none!important}

    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) { include __DIR__ . '/includes/common-toast.php'; } ?>
<form id="pendingCollectionsSecurityForm" class="d-none"><?= pending_collections_csrf_field() ?></form>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>
        <section class="page-section master-page p-3 p-lg-3">
            <div class="mp-hero mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div>
                        <h1>Pending Collections</h1>
                        <p>Only unpaid and partially paid customer invoices with pending balance are shown here.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="scanQrBtn" class="scan-qr-btn"><i data-lucide="scan-line" style="width:14px;height:14px;"></i> Scan Barcode</button>
                        <a href="collections.php" class="btn btn-outline-success"><i data-lucide="wallet-cards" style="width:14px;height:14px;"></i> Collections</a>
                        <a href="bill-list.php" class="btn btn-outline-primary"><i data-lucide="list" style="width:14px;height:14px;"></i> Bill List</a>
                        <button type="button" id="resetPage" class="btn btn-outline-secondary"><i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Reset</button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c;"><i data-lucide="circle-alert"></i></div><div><div class="mp-stat-label">Pending Invoices</div><div class="mp-stat-value" id="pendingInvoices">0</div><div class="mp-stat-sub">Unpaid + partial</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="split"></i></div><div><div class="mp-stat-label">Partial Invoices</div><div class="mp-stat-value" id="partialInvoices">0</div><div class="mp-stat-sub">Balance pending</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#e0f2fe;color:#0369a1;"><i data-lucide="indian-rupee"></i></div><div><div class="mp-stat-label">Pending Due</div><div class="mp-stat-value" id="pendingDue">₹0.00</div><div class="mp-stat-sub">Receivable amount</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="user-check"></i></div><div><div class="mp-stat-label">Cashier</div><div class="mp-stat-value" style="font-size:15px;"><?= e($cashierName) ?></div><div class="mp-stat-sub">Logged-in user</div></div></article></div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div><h2 class="mp-card-title">Pending Customer Invoices</h2><p class="mp-card-sub">This list excludes paid, cancelled, deleted and zero-balance invoices.</p></div>
                            <form method="get" id="filterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                                <input type="text" id="search" class="form-control mp-filter-input" placeholder="Search bill / order / customer / mobile / barcode">
                                <select id="payment_status" class="form-select mp-filter-select"><option value="">Pending + Partial</option><option value="pending">Only Pending</option><option value="partial">Only Partial</option></select>
                                <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">Filter</button>
                            </form>
                        </div>
                        <div class="row g-2">
                            <div class="col-12 col-md-6 col-xl-3"><select id="branch_id" class="form-select mp-filter-select"><option value="">All Branch/Firm</option></select></div>
                            <div class="col-12 col-md-6 col-xl-2"><input type="date" id="date_from" class="form-control mp-filter-input"></div>
                            <div class="col-12 col-md-6 col-xl-2"><input type="date" id="date_to" class="form-control mp-filter-input"></div>
                            <div class="col-12 col-md-6 col-xl-2 d-grid"><button type="button" id="todayBtn" class="btn btn-outline-secondary btn-sm rounded-pill fw-bold">Today</button></div>
                        </div>
                    </div>
                </div>
                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table mb-0"><thead><tr><th>Invoice</th><th>Customer</th><th>Branch</th><th>Items</th><th>Bill Amount</th><th>Paid</th><th>Due</th><th>Status</th><th style="width:190px;">Action</th></tr></thead><tbody id="pendingTableBody"><tr><td colspan="9" class="text-center text-muted py-4">Loading pending collections...</td></tr></tbody></table>
                </div>
                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="pendingMobileCards"><div class="mp-mobile-card text-center text-muted">Loading pending collections...</div></div>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 py-2 border-top"><div class="mp-sub" id="paginationInfo">Page 1 of 1 · Total 0 invoices</div><div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="prevPage">Previous</button><button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="nextPage">Next</button></div></div>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header"><div><h5 class="modal-title">Collect Amount & Auto Thermal Print</h5><div class="mp-sub" id="modalSubTitle">Loading...</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="paymentModalBody"><div class="text-center text-muted py-4">Loading invoice...</div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button type="button" id="collectPaymentBtn" class="btn btn-success"><i data-lucide="printer-check" style="width:14px;height:14px;"></i> Collect Payment & Auto Print</button></div>
        </div>
    </div>
</div>


<div class="modal fade" id="scanQrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Scan Bill Barcode</h5>
                    <div class="mp-sub">Scan a bill barcode to open Collect Amount directly.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="scanner-box mb-3">
                    <video id="qrScannerVideo" class="scanner-video" playsinline muted></video>
                </div>
                <div class="scanner-status mb-3" id="scannerStatus">Click Scan Barcode to start the camera scanner.</div>
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

<iframe id="autoThermalPrintFrame" class="auto-print-frame" title="Auto thermal receipt print"></iframe>

<?php include __DIR__ . '/includes/script.php'; ?>
<script>
(function(){
'use strict';
const apiUrl='api/collections-api.php';
const directBillId=<?= (int)$directBillId ?>;
const money=new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'});
let currentPage=1,totalPages=1,searchTimer=null,modal=null,scannerModal=null,scanStream=null,scanTimer=null,scanBusy=false;
let state={branches:[],methods:[],bill:null,items:[],selectedMethodId:0};
const $=id=>document.getElementById(id);
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function num(v){const n=parseFloat(v||0);return Number.isNaN(n)?0:n;}
function csrfToken(){const input=document.querySelector('#pendingCollectionsSecurityForm input[name="csrf_token"],#pendingCollectionsSecurityForm input[name="_token"],#pendingCollectionsSecurityForm input[type="hidden"]');return input?input.value:'';}
function showMessage(type,msg){if(window.AppToast&&window.AppToast.show){window.AppToast.show(type==='error'?'error':type,msg);return;} if(window.showToast){window.showToast(msg,type==='error'?'danger':type);return;} if(window.Swal){window.Swal.fire(type==='error'?'Error':'Success',msg,type==='error'?'error':'success');return;} alert(msg);}
function buildQuery(params){const q=new URLSearchParams();Object.keys(params||{}).forEach(k=>{if(params[k]!==''&&params[k]!==null&&params[k]!==undefined)q.append(k,params[k]);});return q.toString();}
async function apiGet(params){const r=await fetch(apiUrl+'?'+buildQuery(params),{credentials:'same-origin',headers:{Accept:'application/json'}});return await r.json();}
async function apiPost(payload){const f=new FormData();Object.keys(payload||{}).forEach(k=>f.append(k,payload[k]));f.append('csrf_token',csrfToken());const r=await fetch(apiUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:f});return await r.json();}
function badge(s){s=String(s||'pending').toLowerCase();const cls=s==='paid'?'status-paid':(s==='partial'?'status-partial':'status-pending');return '<span class="mp-badge '+cls+'">'+esc(s.charAt(0).toUpperCase()+s.slice(1))+'</span>';}
function isPendingBill(row){const billStatus=String(row&&row.bill_status||'active').toLowerCase();const payStatus=String(row&&row.payment_status||'pending').toLowerCase();const balance=num(row&&row.balance_amount);const net=num(row&&row.net_amount);const paid=num(row&&row.paid_amount);return billStatus==='active'&&['pending','partial'].includes(payStatus)&&balance>0.0001&&paid<(net-0.0001);}
function renderStats(stats){$('pendingInvoices').textContent=parseInt(stats.pending_invoices||0,10);$('partialInvoices').textContent=parseInt(stats.partial_invoices||0,10);$('pendingDue').textContent=money.format(num(stats.pending_due));}
function fillBranches(){const sel=$('branch_id');sel.innerHTML='<option value="">All Branch/Firm</option>'+state.branches.map(b=>'<option value="'+esc(b.branch_id)+'">'+esc((b.branch_name||'-')+(b.floor_name?' / '+b.floor_name:''))+'</option>').join('');}
function params(action){return {action:action||'pending_list',search:$('search').value,branch_id:$('branch_id').value,payment_status:$('payment_status').value,date_from:$('date_from').value,date_to:$('date_to').value,page:currentPage,per_page:20};}
function avatar(row){const base=(row.customer_name||row.bill_no||'B').substring(0,1).toUpperCase();return '<div class="mp-avatar">'+esc(base)+'</div>';}
function rowActions(row){return '<button type="button" class="btn btn-outline-success btn-sm mp-action-btn js-pay" data-id="'+parseInt(row.bill_id||0,10)+'"><i data-lucide="printer-check" style="width:13px;height:13px;"></i> Collect & Auto Print</button>';}
function setScannerStatus(message,type){const box=$('scannerStatus');if(!box)return;box.textContent=message||'';box.classList.remove('success','error');if(type)box.classList.add(type);}
function getScannerModal(){if(window.bootstrap){if(!scannerModal)scannerModal=new bootstrap.Modal($('scanQrModal'));return scannerModal;}return null;}
function parseBillIdFromScan(value){const raw=String(value||'').trim();if(!raw)return 0;try{const url=new URL(raw,window.location.origin);const billId=parseInt(url.searchParams.get('bill_id')||url.searchParams.get('bill')||url.searchParams.get('id')||'0',10);if(billId>0)return billId;}catch(e){}const match=raw.match(/(?:bill_id|bill|id)\s*[=:]\s*(\d+)/i);return match?parseInt(match[1],10):0;}
function cleanScanValue(value){let raw=String(value||'').trim();if(!raw)return '';try{const url=new URL(raw,window.location.origin);return (url.searchParams.get('barcode')||url.searchParams.get('bill_no')||url.searchParams.get('order_no')||url.searchParams.get('q')||raw).trim();}catch(e){return raw;}}
async function openPaymentFromScan(value){
    const scanned=String(value||'').trim();
    if(!scanned){showMessage('error','Enter or scan a bill barcode number.');return;}
    const last=$('lastScannedValue');
    if(last)last.textContent='Scanned: '+scanned;
    setScannerStatus('Finding pending bill for barcode: '+scanned,'success');
    try{
        const data=await apiGet({action:'find_pending_by_barcode',barcode:scanned});
        if(!data.success){
            const msg=data.message||'No pending bill found for this barcode.';
            setScannerStatus(msg,'error');
            showMessage('error',msg);
            return;
        }
        const billId=parseInt(data.bill_id||(data.bill&&data.bill.bill_id)||0,10);
        if(!billId){
            setScannerStatus('Barcode matched, but bill id is missing.','error');
            showMessage('error','Barcode matched, but bill id is missing.');
            return;
        }
        stopScanner();
        const sm=getScannerModal();
        if(sm)sm.hide();
        const url=new URL(window.location.href);
        url.searchParams.set('bill_id',billId);
        url.searchParams.set('scan','barcode');
        window.history.replaceState({},document.title,url.toString());
        setTimeout(()=>openPayment(billId),120);
    }catch(e){
        setScannerStatus('Unable to connect to collections API.','error');
        showMessage('error','Unable to identify the scanned barcode.');
    }
}
async function startScanner(){const video=$('qrScannerVideo');if(!video){return;}if(scanBusy){return;}scanBusy=true;setScannerStatus('Opening camera scanner...');try{if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){throw new Error('Camera access is not supported in this browser.');}if(!('BarcodeDetector' in window)){throw new Error('BarcodeDetector is not supported in this browser. Use Chrome/Android or enter the scanned value manually.');}scanStream=await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});video.srcObject=scanStream;await video.play();let formats=['code_128','code_39','code_93','ean_13','ean_8','upc_a','upc_e'];if(BarcodeDetector.getSupportedFormats){const supported=await BarcodeDetector.getSupportedFormats();formats=formats.filter(f=>supported.includes(f));if(!formats.length)formats=['code_128'];}const detector=new BarcodeDetector({formats:formats});setScannerStatus('Scanner ready. Point the camera at the bill barcode.','success');clearInterval(scanTimer);scanTimer=setInterval(async()=>{try{if(!video.videoWidth||!video.videoHeight)return;const codes=await detector.detect(video);if(codes&&codes.length){clearInterval(scanTimer);scanTimer=null;await openPaymentFromScan(codes[0].rawValue||'');}}catch(err){}},450);}catch(err){setScannerStatus(err.message||'Unable to open camera scanner.','error');}finally{scanBusy=false;}}
function stopScanner(){clearInterval(scanTimer);scanTimer=null;if(scanStream){scanStream.getTracks().forEach(track=>track.stop());scanStream=null;}const video=$('qrScannerVideo');if(video){video.pause();video.srcObject=null;}scanBusy=false;}
function openScannerModal(){const sm=getScannerModal();if(sm)sm.show();setTimeout(startScanner,350);}
function renderRows(rows){
    rows=(rows||[]).filter(isPendingBill);
    if(!rows.length){
        $('pendingTableBody').innerHTML='<tr><td colspan="9" class="text-center text-muted py-4">No pending collections found.</td></tr>';
        $('pendingMobileCards').innerHTML='<div class="mp-mobile-card text-center text-muted">No pending collections found.</div>';
        return;
    }
    $('pendingTableBody').innerHTML=rows.map(r=>{
        const branch=(r.branch_name||'-')+(r.floor_name?' / '+r.floor_name:'');
        return '<tr class="clickable-row js-open-bill" data-id="'+parseInt(r.bill_id||0,10)+'"><td><div class="d-flex align-items-center gap-2">'+avatar(r)+'<div><div class="mp-title">'+esc(r.bill_no||'-')+'</div><div class="mp-sub">'+esc((r.bill_date||'-')+(r.bill_time?' · '+r.bill_time:''))+'</div><span class="mp-badge badge-code mt-1">'+esc(r.barcode_value||r.order_no||'-')+'</span></div></div></td><td><div class="mp-title">'+esc(r.customer_name||'Walk-in Customer')+'</div><div class="mp-sub">'+esc(r.customer_mobile||'No mobile')+'</div></td><td><span class="mp-badge badge-branch">'+esc(branch)+'</span></td><td><span class="mp-badge badge-code">'+parseInt(r.item_count||0,10)+' items</span><div class="mp-sub">Qty '+num(r.total_qty).toFixed(2)+'</div></td><td><strong>'+money.format(num(r.net_amount))+'</strong></td><td><span class="amount-positive">'+money.format(num(r.paid_amount))+'</span></td><td><span class="amount-due">'+money.format(num(r.balance_amount))+'</span></td><td>'+badge(r.payment_status)+'</td><td>'+rowActions(r)+'</td></tr>';
    }).join('');
    $('pendingMobileCards').innerHTML=rows.map(r=>'<div class="mp-mobile-card js-open-bill" data-id="'+parseInt(r.bill_id||0,10)+'"><div class="d-flex gap-2"><div class="mp-avatar">'+esc((r.customer_name||'C').substring(0,1).toUpperCase())+'</div><div class="flex-grow-1"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">'+esc(r.bill_no||'-')+'</div><div class="mp-sub">'+esc(r.customer_name||'Walk-in Customer')+'</div></div>'+badge(r.payment_status)+'</div><div class="d-flex flex-wrap gap-2 mt-2"><span class="mp-badge badge-money">Bill '+money.format(num(r.net_amount))+'</span><span class="mp-badge badge-due">Due '+money.format(num(r.balance_amount))+'</span></div><div class="d-flex flex-wrap gap-2 mt-2">'+rowActions(r)+'</div></div></div></div>').join('');
}
function renderPagination(p){currentPage=parseInt(p.page||1,10);totalPages=parseInt(p.total_pages||1,10);$('paginationInfo').textContent='Page '+currentPage+' of '+totalPages+' · Total '+parseInt(p.total||0,10)+' invoices';$('prevPage').disabled=currentPage<=1;$('nextPage').disabled=currentPage>=totalPages;}
async function loadPending(init){
    try{
        const data=await apiGet(params(init?'init_pending':'pending_list'));
        if(!data.success){showMessage('error',data.message||'Unable to load pending collections.');return;}
        if(init){state.branches=data.branches||[];state.methods=data.payment_methods||[];fillBranches();}
        const safeBills=(data.bills||[]).filter(isPendingBill);
        renderStats(data.stats||{});
        renderRows(safeBills);
        renderPagination(data.pagination||{});
        if(window.lucide)window.lucide.createIcons();
    }catch(e){showMessage('error','Unable to connect to collections API.');}
}
function paymentMethodChips(){return (state.methods||[]).map(m=>'<button type="button" class="pay-chip '+(parseInt(m.payment_method_id,10)===state.selectedMethodId?'active':'')+'" data-id="'+m.payment_method_id+'" data-type="'+esc(m.method_type||'cash')+'">'+esc(m.payment_method_name||'-')+'</button>').join('');}
function selectedMethod(){return (state.methods||[]).find(m=>parseInt(m.payment_method_id,10)===state.selectedMethodId)||{};}
function isSplit(){return String(selectedMethod().method_type||'').toLowerCase()==='split';}
function renderMixed(){const wrap=$('mixedRows');if(!wrap)return;wrap.innerHTML=(state.methods||[]).filter(m=>String(m.method_type).toLowerCase()!=='split').map(m=>'<div class="mixed-row"><div><strong>'+esc(m.payment_method_name||'-')+'</strong><div class="mp-sub">'+esc(m.method_type||'')+'</div></div><input type="number" min="0" step="0.01" class="form-control mixed-amount" data-method-id="'+m.payment_method_id+'" placeholder="0.00"><input type="text" class="form-control mixed-ref" data-method-id="'+m.payment_method_id+'" placeholder="Ref optional"></div>').join('');document.querySelectorAll('.mixed-amount').forEach(x=>x.addEventListener('input',calculatePayment));}
function tendered(){if(isSplit()){let total=0;document.querySelectorAll('.mixed-amount').forEach(x=>total+=num(x.value));return total;}return num(($('amount_collected')||{}).value);}

function gstOffKeys(){return ['non_gst','no_gst','gst_off','off','none','disabled','0'];}
function gstSystemEnabled(b){
    b=b||{};
    const keys=[b.gst_type_key,b.business_gst_type_key,b.gst_status,b.gst_module_status].map(v=>String(v||'').toLowerCase().trim());
    if(keys.some(k=>gstOffKeys().includes(k)))return false;
    if(parseInt(b.gst_enabled_setting||b.gst_module_enabled||1,10)===0)return false;
    return true;
}
function gstDefaultMode(b){
    b=b||{};
    if(String(b.gst_mode||'').toLowerCase()==='inter'||num(b.igst_amount)>0)return 'inter';
    return 'intra';
}
function gstDefaultRate(b){
    b=b||{};
    const saved=num(b.gst_rate||b.tax_rate||0);
    if(saved>0)return saved;
    const taxable=num(b.taxable_amount||b.net_before_tax||0);
    const tax=num(b.tax_amount||0);
    if(taxable>0&&tax>0)return Math.round((tax*10000/taxable))/100;
    return 18;
}
function gstDefaultTaxable(b){
    b=b||{};
    const taxable=num(b.taxable_amount||b.net_before_tax||0);
    if(taxable>0)return taxable;
    const tax=num(b.tax_amount||0);
    const net=num(b.net_amount||0);
    if(tax>0&&net>tax)return net-tax;
    return num(b.balance_amount||b.net_amount||0);
}
function billGstDefaultOn(b){
    b=b||{};
    return gstSystemEnabled(b)&&(num(b.tax_amount)>0||String(b.gst_type_key||'').toLowerCase()==='gst_regular'||parseInt(b.gst_enabled||0,10)===1);
}
function collectionGstSummary(){
    const b=state.bill||{};
    const systemOn=gstSystemEnabled(b);
    const enabled=systemOn&&!!($('gstEnabled')&&$('gstEnabled').checked);
    const mode=$('gstMode')?String($('gstMode').value||gstDefaultMode(b)):gstDefaultMode(b);
    const rate=enabled?Math.max(0,Math.min(100,num(($('gstRate')||{}).value||gstDefaultRate(b)))):0;
    const taxable=enabled?Math.max(0,num(($('gstTaxableAmount')||{}).value||gstDefaultTaxable(b))):0;
    const gstAmount=enabled?Math.round((taxable*rate/100)*100)/100:0;
    const cgst=enabled&&mode==='intra'?Math.round((gstAmount/2)*100)/100:0;
    const sgst=enabled&&mode==='intra'?Math.round((gstAmount-cgst)*100)/100:0;
    const igst=enabled&&mode==='inter'?gstAmount:0;
    return {systemOn:systemOn,enabled:enabled,mode:mode,rate:rate,taxable:taxable,cgst:cgst,sgst:sgst,igst:igst,gstAmount:gstAmount};
}
function renderCollectionGst(){
    const g=collectionGstSummary();
    const card=$('collectionGstCard');
    if(card)card.classList.toggle('gst-hidden',!g.systemOn);
    document.querySelectorAll('.gst-details').forEach(x=>x.classList.toggle('gst-hidden',!g.enabled));
    if($('gstStatusText')){
        $('gstStatusText').textContent=g.enabled?'GST ON':'GST OFF';
        $('gstStatusText').classList.toggle('on',g.enabled);
    }
    if($('sumCgst'))$('sumCgst').textContent=money.format(g.cgst);
    if($('sumSgst'))$('sumSgst').textContent=money.format(g.sgst);
    if($('sumIgst'))$('sumIgst').textContent=money.format(g.igst);
    if($('sumGst'))$('sumGst').textContent=money.format(g.gstAmount);
    if($('gstPayableTotal'))$('gstPayableTotal').textContent=money.format(g.taxable+g.gstAmount);
    if($('gstCollectionTotal'))$('gstCollectionTotal').textContent=money.format(collectionDueTotal(g));
    if($('gstRate'))$('gstRate').disabled=!g.enabled;
    if($('gstMode'))$('gstMode').disabled=!g.enabled;
    if($('gstTaxableAmount'))$('gstTaxableAmount').disabled=!g.enabled;
    return g;
}
function collectionDueTotal(g){g=g||collectionGstSummary();return num(state.bill&&state.bill.balance_amount)+(g.enabled?num(g.gstAmount):0);}
function autoFillCollectionAmount(g){if(isSplit())return;const input=$('amount_collected');if(!input)return;input.value=collectionDueTotal(g).toFixed(2);}
function calculatePayment(){if(!state.bill)return;const g=renderCollectionGst();autoFillCollectionAmount(g);const due=collectionDueTotal(g);const amount=tendered();$('changeAmount').textContent=money.format(Math.max(0,amount-due));$('dueAfterPay').textContent=money.format(Math.max(0,due-amount));$('statusAfterPay').textContent=amount<=0?'Pending':(amount>=due?'Paid':'Partial');}
function renderPaymentModal(){
    const b=state.bill;if(!b)return;
    const branch=(b.branch_name||'-')+(b.floor_name?' / '+b.floor_name:'');
    $('modalSubTitle').textContent=(b.bill_no||'-')+' · '+(b.customer_name||'Walk-in Customer');
    if(!state.selectedMethodId&&state.methods.length)state.selectedMethodId=parseInt(state.methods[0].payment_method_id,10);
    const itemRows=(state.items||[]).map(i=>'<tr><td><div class="mp-title">'+esc(i.article_no||'-')+'</div><div class="mp-sub">'+esc(i.article_name||'-')+'</div></td><td>'+esc(i.size||'-')+'</td><td>'+esc(i.color||'-')+'</td><td>'+num(i.qty).toFixed(2)+'</td><td>'+money.format(num(i.selling_rate))+'</td><td><strong>'+money.format(num(i.amount))+'</strong></td></tr>').join('')||'<tr><td colspan="6" class="text-center text-muted py-4">No items found.</td></tr>';
    const gstSystemOn=gstSystemEnabled(b);
    const gstChecked=billGstDefaultOn(b)?'checked':'';
    const gstMode=gstDefaultMode(b);
    const gstRate=gstDefaultRate(b).toFixed(2);
    const gstTaxable=gstDefaultTaxable(b).toFixed(2);

    $('paymentModalBody').innerHTML=
        '<div class="detail-grid mb-3">'+
            '<div class="detail-box"><div class="mp-stat-label">Bill No</div><div class="mp-title mt-1">'+esc(b.bill_no||'-')+'</div><div class="mp-sub">'+esc(b.order_no||'-')+'</div></div>'+
            '<div class="detail-box"><div class="mp-stat-label">Customer</div><div class="mp-title mt-1">'+esc(b.customer_name||'Walk-in Customer')+'</div><div class="mp-sub">'+esc(b.customer_mobile||'No mobile')+'</div></div>'+
            '<div class="detail-box"><div class="mp-stat-label">Branch</div><div class="mp-title mt-1">'+esc(branch)+'</div></div>'+
            '<div class="detail-box"><div class="mp-stat-label">Due Amount</div><div class="mp-title amount-due mt-1">'+money.format(num(b.balance_amount))+'</div></div>'+
        '</div>'+
        '<div class="table-responsive mb-3"><table class="table mp-table mb-0"><thead><tr><th>Product</th><th>Size</th><th>Colour</th><th>Qty</th><th>Rate</th><th>Total</th></tr></thead><tbody>'+itemRows+'</tbody></table></div>'+
        '<div class="row g-3">'+
            '<div class="col-12 col-lg-5">'+
                '<div class="detail-box">'+
                    '<div class="d-flex justify-content-between py-1"><span>Grand Total</span><strong>'+money.format(num(b.net_amount))+'</strong></div>'+
                    '<div class="d-flex justify-content-between py-1"><span>Already Paid</span><strong class="amount-positive">'+money.format(num(b.paid_amount))+'</strong></div>'+
                    '<div class="d-flex justify-content-between py-1"><span>Pending Due</span><strong class="amount-due">'+money.format(num(b.balance_amount))+'</strong></div>'+
                '</div>'+
                '<div id="collectionGstCard" class="detail-box collection-gst-card mt-3 '+(gstSystemOn?'':'gst-hidden')+'">'+
                    '<div class="gst-head">'+
                        '<div><div class="mp-stat-label">GST Options</div><div class="mp-sub">Auto calculate SGST/CGST or IGST.</div></div>'+
                        '<label class="gst-toggle-wrap"><input type="checkbox" id="gstEnabled" '+gstChecked+' '+(gstSystemOn?'':'disabled')+'><span id="gstStatusText" class="gst-status-pill">GST OFF</span></label>'+
                    '</div>'+
                    '<div class="gst-tax-row gst-details">'+
                        '<select id="gstMode" class="form-select"><option value="intra" '+(gstMode==='intra'?'selected':'')+'>SGST + CGST</option><option value="inter" '+(gstMode==='inter'?'selected':'')+'>IGST</option></select>'+
                        '<input type="number" min="0" max="100" step="0.01" id="gstRate" class="form-control" value="'+gstRate+'" placeholder="GST %">'+
                        '<input type="number" min="0" step="0.01" id="gstTaxableAmount" class="form-control" value="'+gstTaxable+'" placeholder="Taxable">'+
                    '</div>'+
                    '<div class="gst-line gst-details"><span>CGST</span><strong id="sumCgst">₹0.00</strong></div>'+
                    '<div class="gst-line gst-details"><span>SGST</span><strong id="sumSgst">₹0.00</strong></div>'+
                    '<div class="gst-line gst-details"><span>IGST</span><strong id="sumIgst">₹0.00</strong></div>'+
                    '<div class="gst-line gst-total gst-details"><span>Total GST</span><strong id="sumGst">₹0.00</strong></div>'+
                    '<div class="gst-line gst-total gst-details"><span>Taxable + GST</span><strong id="gstPayableTotal">₹0.00</strong></div>'+
                    '<div class="gst-line gst-total gst-details"><span>Total To Collect</span><strong id="gstCollectionTotal">₹0.00</strong></div>'+
                '</div>'+
            '</div>'+
            '<div class="col-12 col-lg-7">'+
                '<label class="form-label">Payment Method</label><div class="payment-method-chips mb-3" id="methodChips">'+paymentMethodChips()+'</div>'+
                '<div id="singlePaymentBox"><div class="row g-2"><div class="col-12 col-md-6"><label class="form-label">Amount Collected</label><input type="number" step="0.01" min="0" id="amount_collected" class="form-control" value="'+num(b.balance_amount).toFixed(2)+'"></div><div class="col-12 col-md-6"><label class="form-label">Reference No</label><input type="text" id="reference_no" class="form-control" placeholder="UPI/Card ref optional"></div></div></div>'+
                '<div id="mixedPaymentBox" style="display:none;"><div class="mp-sub mb-2">Enter split amount by payment method.</div><div id="mixedRows"></div></div>'+
                '<label class="form-label mt-3">Payment Note</label><input type="text" id="payment_note" class="form-control" placeholder="Optional note">'+
                '<div class="row g-2 mt-3"><div class="col-6"><div class="detail-box"><div class="mp-stat-label">Change</div><div class="mp-title amount-positive" id="changeAmount">₹0.00</div></div></div><div class="col-6"><div class="detail-box"><div class="mp-stat-label">Due After Pay</div><div class="mp-title amount-due" id="dueAfterPay">₹0.00</div><div class="mp-sub" id="statusAfterPay">Pending</div></div></div></div>'+
            '</div>'+
        '</div>';
    bindPaymentEvents();calculatePayment();if(window.lucide)window.lucide.createIcons();
}
function bindPaymentEvents(){document.querySelectorAll('.pay-chip').forEach(btn=>btn.addEventListener('click',()=>{state.selectedMethodId=parseInt(btn.dataset.id,10);document.querySelectorAll('.pay-chip').forEach(x=>x.classList.remove('active'));btn.classList.add('active');const split=isSplit();$('singlePaymentBox').style.display=split?'none':'block';$('mixedPaymentBox').style.display=split?'block':'none';if(split)renderMixed();calculatePayment();}));const amount=$('amount_collected');if(amount)amount.addEventListener('input',calculatePayment);['gstEnabled','gstMode','gstRate','gstTaxableAmount'].forEach(id=>{const node=$(id);if(node){node.addEventListener('input',calculatePayment);node.addEventListener('change',calculatePayment);}});}
async function openPayment(id){
    try{
        const data=await apiGet({action:'get_pending_bill',bill_id:id});
        if(!data.success){
            showMessage('error',data.message||'Pending bill not found or already collected.');
            await loadPending(false);
            return;
        }
        if(!isPendingBill(data.bill||{})){
            showMessage('error','This bill is already paid. It will be available only in Collections / Paid Bills.');
            await loadPending(false);
            return;
        }
        state.bill=data.bill;state.items=data.items||[];renderPaymentModal();if(!modal&&window.bootstrap)modal=new bootstrap.Modal($('paymentModal'));if(modal)modal.show();
    }catch(e){showMessage('error','Unable to fetch invoice.');}
}

function autoPrintThermalReceipt(billId,gst,collectionAmount){
    billId=parseInt(billId||0,10);
    if(!billId){return;}

    gst=gst||collectionGstSummary();
    const params=new URLSearchParams();
    params.set('bill_id',billId);
    params.set('auto_print','1');
    params.set('source','pending_collections');
    params.set('collection_amount',num(collectionAmount||0).toFixed(2));
    params.set('base_due_amount',num(state.bill&&state.bill.balance_amount).toFixed(2));
    params.set('collection_total_amount',collectionDueTotal(gst).toFixed(2));
    params.set('payment_method_name',selectedMethod().payment_method_name||selectedMethod().method_type||'Payment');
    params.set('gst_enabled',gst.enabled?1:0);
    params.set('gst_mode',gst.mode||'intra');
    params.set('gst_rate',num(gst.rate).toFixed(2));
    params.set('taxable_amount',num(gst.taxable).toFixed(2));
    params.set('cgst_amount',num(gst.cgst).toFixed(2));
    params.set('sgst_amount',num(gst.sgst).toFixed(2));
    params.set('igst_amount',num(gst.igst).toFixed(2));
    params.set('tax_amount',num(gst.gstAmount).toFixed(2));

    const printUrl='cashier-thermal-receipt.php?'+params.toString();
    const frame=$('autoThermalPrintFrame');

    if(frame){
        let printed=false;
        frame.onload=function(){
            if(printed){return;}
            printed=true;
            try{
                if(frame.contentWindow){
                    frame.contentWindow.focus();
                    setTimeout(()=>frame.contentWindow.print(),500);
                    return;
                }
            }catch(e){}
            window.open(printUrl,'_blank','noopener');
        };
        frame.src=printUrl+'&t='+(Date.now());
        setTimeout(function(){if(!printed){window.open(printUrl,'_blank','noopener');}},2200);
        return;
    }

    window.open(printUrl,'_blank','noopener');
}

function mixedPayload(){if(!isSplit())return null;const rows=[];document.querySelectorAll('.mixed-amount').forEach(input=>{const methodId=parseInt(input.dataset.methodId,10);const amount=num(input.value);const ref=document.querySelector('.mixed-ref[data-method-id="'+methodId+'"]');if(amount>0)rows.push({payment_method_id:methodId,amount_collected:amount,reference_no:ref?ref.value:'',payment_note:($('payment_note')||{}).value||''});});return rows;}
function removeBillFromPendingView(billId){document.querySelectorAll('[data-id="'+parseInt(billId||0,10)+'"]').forEach(elm=>{if(elm.classList.contains('js-open-bill')||elm.classList.contains('mp-mobile-card'))elm.remove();});}
async function collectPayment(printAfter){
    if(!state.bill){showMessage('error','Please select an invoice.');return;}
    if(!isPendingBill(state.bill)){showMessage('error','This bill is already paid or not available for pending collection.');await loadPending(false);return;}

    const gst=collectionGstSummary();
    autoFillCollectionAmount(gst);
    const amount=tendered();
    const collectTotal=collectionDueTotal(gst);
    if(amount<=0){showMessage('error','Enter amount collected.');return;}
    if(amount+0.009<collectTotal){showMessage('error','Collect full amount including GST: '+money.format(collectTotal));return;}

    const btn=$('collectPaymentBtn');
    if(btn){
        btn.disabled=true;
        btn.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span> Collecting & Printing...';
    }

    const payload={action:'collect_payment',bill_id:state.bill.bill_id,payment_method_id:state.selectedMethodId,amount_collected:amount,collection_total_amount:collectTotal,base_due_amount:num(state.bill.balance_amount),reference_no:($('reference_no')||{}).value||'',payment_note:($('payment_note')||{}).value||'',gst_enabled:gst.enabled?1:0,gst_type_key:gst.enabled?'gst_regular':'non_gst',gst_mode:gst.mode,gst_rate:gst.rate,taxable_amount:gst.taxable,cgst_amount:gst.cgst,sgst_amount:gst.sgst,igst_amount:gst.igst,tax_amount:gst.gstAmount};
    const mixed=mixedPayload();
    if(mixed){
        if(!mixed.length){
            showMessage('error','Enter at least one split amount.');
            if(btn){btn.disabled=false;btn.innerHTML='<i data-lucide="printer-check" style="width:14px;height:14px;"></i> Collect Payment & Auto Print';if(window.lucide)window.lucide.createIcons();}
            return;
        }
        payload.payments_json=JSON.stringify(mixed);
    }

    try{
        const billId=parseInt(state.bill.bill_id,10);
        const data=await apiPost(payload);
        if(!data.success){
            showMessage('error',data.message||'Collection failed.');
            if(btn){btn.disabled=false;btn.innerHTML='<i data-lucide="printer-check" style="width:14px;height:14px;"></i> Collect Payment & Auto Print';if(window.lucide)window.lucide.createIcons();}
            return;
        }

        showMessage('success',data.message||'Payment collected successfully. Printing final thermal invoice...');
        autoPrintThermalReceipt(billId,gst,amount);

        if(modal)modal.hide();

        const cleanUrl=new URL(window.location.href);
        if(cleanUrl.searchParams.has('bill_id')){
            cleanUrl.searchParams.delete('bill_id');
            cleanUrl.searchParams.delete('scan');
            window.history.replaceState({},document.title,cleanUrl.toString());
        }

        state.bill=null;

        if(String(data.payment_status||'').toLowerCase()==='paid'||num(data.bill&&data.bill.balance_amount)<=0.0001){
            removeBillFromPendingView(billId);
        }

        await loadPending(false);
    }catch(e){
        showMessage('error','Unable to collect payment.');
    }finally{
        if(btn){
            btn.disabled=false;
            btn.innerHTML='<i data-lucide="printer-check" style="width:14px;height:14px;"></i> Collect Payment & Auto Print';
            if(window.lucide)window.lucide.createIcons();
        }
    }
}
$('filterForm').addEventListener('submit',e=>{e.preventDefault();currentPage=1;loadPending(false);});$('search').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{currentPage=1;loadPending(false);},300);});['branch_id','payment_status','date_from','date_to'].forEach(id=>$(id).addEventListener('change',()=>{currentPage=1;loadPending(false);}));$('todayBtn').addEventListener('click',()=>{const d=new Date().toISOString().slice(0,10);$('date_from').value=d;$('date_to').value=d;currentPage=1;loadPending(false);});$('resetPage').addEventListener('click',()=>{$('search').value='';$('payment_status').value='';$('branch_id').value='';$('date_from').value='';$('date_to').value='';currentPage=1;loadPending(false);});$('prevPage').addEventListener('click',()=>{if(currentPage>1){currentPage--;loadPending(false);}});$('nextPage').addEventListener('click',()=>{if(currentPage<totalPages){currentPage++;loadPending(false);}});document.addEventListener('click',e=>{const pay=e.target.closest('.js-pay');if(pay){e.preventDefault();openPayment(pay.dataset.id);return;}const row=e.target.closest('.js-open-bill');if(row && !e.target.closest('button,a,input,select,textarea')){openPayment(row.dataset.id);}});$('collectPaymentBtn').addEventListener('click',()=>collectPayment(true));$('scanQrBtn').addEventListener('click',openScannerModal);$('restartScannerBtn').addEventListener('click',startScanner);$('stopScannerBtn').addEventListener('click',()=>{stopScanner();setScannerStatus('Camera stopped. Click Restart Scanner to scan again.');});$('manualScanBtn').addEventListener('click',()=>openPaymentFromScan(($('manualScanValue')||{}).value||''));$('manualScanValue').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();openPaymentFromScan(e.target.value);}});$('scanQrModal').addEventListener('hidden.bs.modal',stopScanner);async function startPage(){await loadPending(true);if(directBillId>0){setTimeout(()=>openPayment(directBillId),250);}}startPage();
})();
</script>
</body>
</html>
