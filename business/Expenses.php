<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
if (function_exists('require_page_access')) require_page_access($conn, 'expenses.php');

$pageTitle='Expenses';
$businessId=function_exists('current_business_id')?(int)current_business_id():(int)($_SESSION['business_id']??0);
$currentBranchId=function_exists('current_branch_id')?(int)current_branch_id():(int)($_SESSION['branch_id']??0);
if($businessId<=0) die('Business session missing. Please login again.');

function expense_e($v):string{ return function_exists('e')?e((string)$v):htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function expense_csrf_field():string{
    if(function_exists('csrf_field')) return csrf_field();
    if(function_exists('csrf_token')) return '<input type="hidden" name="csrf_token" value="'.expense_e(csrf_token()).'">';
    return '';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=expense_e($pageTitle)?> - Shop Admin</title>
<?php include __DIR__.'/includes/links.php'; ?>
<style>
.expense-page{font-family:Inter,"Segoe UI",Arial,sans-serif;font-size:12px}.ex-card,.ex-hero,.ex-stat{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:16px;box-shadow:0 8px 22px rgba(15,23,42,.06)}.ex-hero{padding:14px 16px}.ex-hero h1{font-size:20px;font-weight:850;margin:0 0 3px}.ex-hero p,.ex-sub{font-size:10px;color:#64748b;margin:0}.ex-stat{min-height:76px;padding:12px;display:flex;align-items:center;gap:10px}.ex-stat-icon{width:42px;height:42px;border-radius:14px;display:grid;place-items:center}.ex-stat-label{font-size:10px;font-weight:800;text-transform:uppercase;color:#64748b}.ex-stat-value{font-size:19px;font-weight:900}.ex-head{padding:12px 14px;border-bottom:1px solid #dbe4f0}.ex-title{font-size:15px;font-weight:850;margin:0}.ex-filter{padding:12px 14px;background:#f8fafc;border-bottom:1px solid #dbe4f0}.ex-filter label,.modal .form-label{font-size:9px;font-weight:850;text-transform:uppercase;color:#475569;margin-bottom:3px}.ex-filter .form-control,.ex-filter .form-select,.modal .form-control,.modal .form-select{font-size:11px;min-height:34px;border-radius:11px}.ex-table th{font-size:9px;text-transform:uppercase;background:#f1f5f9;padding:9px;white-space:nowrap}.ex-table td{font-size:11px;padding:9px;vertical-align:middle}.pill{display:inline-flex;border-radius:999px;padding:5px 8px;font-size:9.5px;font-weight:800}.pill-active{background:#dcfce7;color:#15803d}.pill-cancelled{background:#fee2e2;color:#b91c1c}.pill-method{background:#dbeafe;color:#1d4ed8}.pill-category{background:#ede9fe;color:#6d28d9}.pill-branch{background:#ecfeff;color:#0e7490}.action-btn{border-radius:999px;font-size:10px;font-weight:800;padding:5px 8px}.detail-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.detail-box{border:1px solid #e2e8f0;background:#f8fafc;border-radius:13px;padding:10px}.detail-label{font-size:9px;text-transform:uppercase;color:#64748b;font-weight:850}.detail-value{font-size:12px;font-weight:800;margin-top:3px}.report-card{border:1px solid #e2e8f0;border-radius:14px;padding:11px}.report-value{font-size:17px;font-weight:900}.mobile-card{border:1px solid #dbe4f0;background:#fff;border-radius:14px;padding:10px}.expense-mode-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.expense-mode-card{border:1px solid #dbe4f0;background:linear-gradient(180deg,#fff,#f8fafc);border-radius:18px;padding:18px;cursor:pointer;transition:.18s ease;min-height:160px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}.expense-mode-card:hover{transform:translateY(-2px);box-shadow:0 14px 28px rgba(15,23,42,.10);border-color:#93c5fd}.expense-mode-icon{width:54px;height:54px;border-radius:18px;display:grid;place-items:center;margin-bottom:10px}.expense-mode-title{font-size:15px;font-weight:900}.expense-mode-sub{font-size:10px;color:#64748b;margin-top:4px}.expense-lines-wrap{display:grid;gap:9px}.expense-line{border:1px solid #dbe4f0;background:#f8fafc;border-radius:14px;padding:10px}.expense-line-grid{display:grid;grid-template-columns:minmax(180px,1.3fr) minmax(220px,1.5fr) minmax(120px,.7fr) minmax(180px,1fr) 42px;gap:8px;align-items:end}.expense-line-name{grid-column:auto}.expense-line-description{grid-column:auto}.expense-line-amount{grid-column:auto}.expense-line-file{grid-column:auto}.expense-line-action{grid-column:auto}.expense-line-remove{width:36px;height:36px;border-radius:11px;display:grid;place-items:center}.expense-summary{border:1px solid #bbf7d0;background:#f0fdf4;border-radius:13px;padding:10px;display:flex;justify-content:space-between;gap:10px;font-weight:850}.transaction-note{border:1px dashed #93c5fd;background:#eff6ff;color:#1d4ed8;border-radius:13px;padding:9px 10px;font-size:10px;font-weight:750}.mode-back-btn{border-radius:999px;font-size:10px;font-weight:800}@media(max-width:991px){.expense-line-grid{grid-template-columns:1fr 1fr}.expense-line-action{grid-column:span 2}.expense-line-remove{width:100%}}@media(max-width:767px){.expense-mode-grid{grid-template-columns:1fr}.expense-line-grid{grid-template-columns:1fr}.expense-line-action{grid-column:span 1}}.expense-line-grid{grid-template-columns:1fr}.expense-line-category,.expense-line-name,.expense-line-description,.expense-line-amount,.expense-line-payment,.expense-line-branch,.expense-line-file,.expense-line-action{grid-column:span 1}}@media(max-width:991px){.detail-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:767px){.detail-grid{grid-template-columns:1fr}}@media print{body *{visibility:hidden!important}#printArea,#printArea *{visibility:visible!important}#printArea{display:block!important;position:absolute;left:0;top:0;width:100%;padding:20px}}
</style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035"></div>
<?php include __DIR__.'/includes/page-message.php'; ?>
<?php if(file_exists(__DIR__.'/includes/common-toast.php')) include __DIR__.'/includes/common-toast.php'; ?>
<div class="min-vh-100 d-flex">
<?php include __DIR__.'/includes/sidebar.php'; ?>
<main id="main"><?php include __DIR__.'/includes/nav.php'; ?>
<section class="page-section expense-page p-3">
<div class="ex-hero mb-3"><div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center gap-3"><div><h1>Shop Admin Expenses</h1><p>Manage expenses, receipts, branches, payment methods and reports.</p></div><div class="d-flex gap-2 flex-wrap"><button class="btn btn-outline-success btn-sm rounded-pill fw-bold" id="exportExcelBtn">Excel</button><button class="btn btn-outline-danger btn-sm rounded-pill fw-bold" id="exportPdfBtn">PDF</button><button class="btn brand-gradient btn-sm rounded-pill fw-bold" id="addExpenseBtn">Add Expense</button></div></div></div>
<div class="row g-3 mb-3">
<div class="col-12 col-sm-6 col-xl-3"><div class="ex-stat"><div class="ex-stat-icon" style="background:#fee2e2;color:#b91c1c"><i data-lucide="calendar-days"></i></div><div><div class="ex-stat-label">Today's Expenses</div><div class="ex-stat-value" id="todayExpense">₹0.00</div><div class="ex-sub" id="todayCount">0 records</div></div></div></div>
<div class="col-12 col-sm-6 col-xl-3"><div class="ex-stat"><div class="ex-stat-icon" style="background:#fef3c7;color:#b45309"><i data-lucide="calendar-range"></i></div><div><div class="ex-stat-label">Monthly Expenses</div><div class="ex-stat-value" id="monthlyExpense">₹0.00</div><div class="ex-sub" id="monthlyCount">0 records</div></div></div></div>
<div class="col-12 col-sm-6 col-xl-3"><div class="ex-stat"><div class="ex-stat-icon" style="background:#dbeafe;color:#1d4ed8"><i data-lucide="indian-rupee"></i></div><div><div class="ex-stat-label">Total Expenses</div><div class="ex-stat-value" id="totalExpense">₹0.00</div><div class="ex-sub" id="totalCount">0 records</div></div></div></div>
<div class="col-12 col-sm-6 col-xl-3"><div class="ex-stat"><div class="ex-stat-icon" style="background:#dcfce7;color:#15803d"><i data-lucide="circle-check"></i></div><div><div class="ex-stat-label">Active Expenses</div><div class="ex-stat-value" id="activeExpense">₹0.00</div><div class="ex-sub" id="activeCount">0 active</div></div></div></div>
</div>
<div class="ex-card mb-3"><div class="ex-head"><h2 class="ex-title">Expense Reports</h2></div><div class="p-3"><div class="row g-3">
<div class="col-12 col-md-6 col-xl-3"><div class="report-card"><div class="fw-bold">Selected Range</div><div class="report-value" id="rangeReport">₹0.00</div><div class="ex-sub" id="rangeReportCount">0 records</div></div></div>
<div class="col-12 col-md-6 col-xl-3"><div class="report-card"><div class="fw-bold">Top Category</div><div class="report-value" id="topCategory">-</div><div class="ex-sub" id="topCategoryAmount">₹0.00</div></div></div>
<div class="col-12 col-md-6 col-xl-3"><div class="report-card"><div class="fw-bold">Top Branch</div><div class="report-value" id="topBranch">-</div><div class="ex-sub" id="topBranchAmount">₹0.00</div></div></div>
<div class="col-12 col-md-6 col-xl-3"><div class="report-card"><div class="fw-bold">Top Payment Method</div><div class="report-value" id="topMethod">-</div><div class="ex-sub" id="topMethodAmount">₹0.00</div></div></div>
</div></div></div>
<div class="ex-card"><div class="ex-head"><div class="d-flex justify-content-between"><div><h2 class="ex-title">Expense List</h2><div class="ex-sub">Search, filter, view, edit, print and delete.</div></div><button class="btn btn-light btn-sm rounded-pill" id="resetFiltersBtn">Reset</button></div></div>
<div class="ex-filter"><form id="filterForm" class="row g-2 align-items-end">
<div class="col-12 col-md-3"><label>Search</label><input id="search" class="form-control" placeholder="Expense no / name / paid to"></div>
<div class="col-6 col-md-2"><label>From</label><input type="date" id="dateFrom" class="form-control"></div><div class="col-6 col-md-2"><label>To</label><input type="date" id="dateTo" class="form-control"></div>
<div class="col-6 col-md-2"><label>Branch</label><select id="filterBranch" class="form-select"><option value="">All</option></select></div>
<div class="col-6 col-md-1"><label>Category</label><select id="filterCategory" class="form-select"><option value="">All</option></select></div>
<div class="col-6 col-md-1"><label>Payment</label><select id="filterMethod" class="form-select"><option value="">All</option><option>Cash</option><option>UPI</option><option>Card</option><option>Bank</option></select></div>
<div class="col-6 col-md-1"><label>Status</label><select id="filterStatus" class="form-select"><option value="">All</option><option value="active">Active</option><option value="cancelled">Cancelled</option></select></div>
</form></div>
<div class="d-none d-md-block table-responsive px-3 pb-3"><table class="table ex-table mb-0"><thead><tr><th>No</th><th>Date</th><th>Branch</th><th>Category</th><th>Expense</th><th>Amount</th><th>Payment</th><th>Paid To</th><th>Status</th><th>Created By</th><th>Actions</th></tr></thead><tbody id="expenseTableBody"><tr><td colspan="11" class="text-center py-4 text-muted">Loading...</td></tr></tbody></table></div>
<div class="d-md-none d-grid gap-2 px-3 pb-3" id="expenseMobileCards"></div>
<div class="d-flex justify-content-between align-items-center px-3 py-2 border-top"><div class="ex-sub" id="paginationInfo">Page 1 of 1</div><div class="d-flex gap-2"><button class="btn btn-outline-secondary btn-sm rounded-pill" id="prevPage">Previous</button><button class="btn btn-outline-secondary btn-sm rounded-pill" id="nextPage">Next</button></div></div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
</section></main></div>


<div class="modal fade" id="expenseModeModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title">Choose Expense Entry Method</h5><div class="ex-sub">Select how you want to record the expense.</div></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="expense-mode-grid">
                    <button type="button" class="expense-mode-card" id="singleExpenseModeBtn">
                        <span class="expense-mode-icon" style="background:#dbeafe;color:#1d4ed8"><i data-lucide="receipt"></i></span>
                        <span class="expense-mode-title">Add Single Expense</span>
                        <span class="expense-mode-sub">Record one expense under one category.</span>
                    </button>
                    <button type="button" class="expense-mode-card" id="multiExpenseModeBtn">
                        <span class="expense-mode-icon" style="background:#dcfce7;color:#15803d"><i data-lucide="list-plus"></i></span>
                        <span class="expense-mode-title">Add Multiple Expenses</span>
                        <span class="expense-mode-sub">Record multiple expenses across different categories in one transaction.</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content" id="expenseForm" enctype="multipart/form-data">
            <?=expense_csrf_field()?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="expense_id" id="expenseId" value="0">
            <input type="hidden" name="entry_mode" id="entryMode" value="single">
            <input type="hidden" name="items_json" id="expenseItemsJson" value="[]">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="expenseModalTitle">Add Expense</h5>
                    <div class="ex-sub" id="expenseModalSub">Expense number is generated automatically.</div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm mode-back-btn" id="changeExpenseModeBtn">Change Method</button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>

            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-3"><label class="form-label">Expense / Transaction Number</label><input id="expenseNo" class="form-control" value="Auto Generate" readonly></div>
                    <div class="col-12 col-md-3"><label class="form-label">Expense Date *</label><input type="date" name="expense_date" id="expenseDate" class="form-control" required></div>
                    <div class="col-12 col-md-3 single-common-field"><label class="form-label">Branch / Firm *</label><select name="branch_id" id="branchId" class="form-select"></select></div>
                    <div class="col-12 col-md-3 single-common-field"><label class="form-label">Payment Method *</label><select name="payment_method" id="paymentMethod" class="form-select"><option value="">Select</option><option>Cash</option><option>UPI</option><option>Card</option><option>Bank</option></select></div>

                    <div class="col-12 col-md-4"><label class="form-label">Paid To</label><input name="paid_to" id="paidTo" class="form-control"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Reference Number</label><input name="reference_number" id="referenceNumber" class="form-control"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Status</label><select name="status" id="status" class="form-select"><option value="active">Active</option><option value="cancelled">Cancelled</option></select></div>

                    <div class="col-12 col-md-6 single-common-field"><label class="form-label">Receipt / Bill Upload</label><input type="file" name="receipt_file" id="receiptFile" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf"><div class="ex-sub mt-1" id="currentReceipt"></div></div>
                    <div class="col-12 col-md-6"><label class="form-label">Remarks</label><textarea name="remarks" id="remarks" class="form-control" rows="2"></textarea></div>
                </div>

                <div id="singleExpenseSection">
                    <div class="row g-3">
                        <div class="col-12 col-md-4"><label class="form-label">Category *</label><select id="singleCategoryId" class="form-select"></select></div>
                        <div class="col-12 col-md-4"><label class="form-label">Expense Name *</label><input id="singleExpenseName" class="form-control"></div>
                        <div class="col-12 col-md-4"><label class="form-label">Amount *</label><input type="number" step="0.01" min="0.01" id="singleAmount" class="form-control"></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea id="singleDescription" class="form-control" rows="3"></textarea></div>
                    </div>
                </div>

                <div id="multiExpenseSection" class="d-none">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Category *</label>
                            <select id="multiCategoryId" class="form-select"></select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Payment Method *</label>
                            <select id="multiPaymentMethod" class="form-select">
                                <option value="">Select Payment</option>
                                <option>Cash</option>
                                <option>UPI</option>
                                <option>Card</option>
                                <option>Bank</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Branch / Firm *</label>
                            <select id="multiBranchId" class="form-select"></select>
                        </div>
                    </div>
                    <div class="transaction-note mb-3">Choose Category, Payment Method and Branch/Firm once. Every added expense line will use those shared details.</div>
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <div><h6 class="fw-bold mb-0">Expense Line Items</h6><div class="ex-sub">Each line requires only Expense Name, Description, Amount and optional Attachment.</div></div>
                        <button type="button" class="btn btn-outline-primary btn-sm rounded-pill fw-bold" id="addExpenseLineBtn">+ Add Expense Line</button>
                    </div>
                    <div id="expenseLines" class="expense-lines-wrap"></div>
                    <div class="expense-summary mt-3"><span>Total Expenses: <strong id="lineCount">0</strong></span><span>Transaction Total: <strong id="lineTotal">₹0.00</strong></span></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button class="btn brand-gradient" id="saveExpenseBtn">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="viewModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Expense Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body" id="viewExpenseBody"></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button><button class="btn btn-dark" id="printViewBtn">Print</button></div></div></div></div>
<div id="printArea" class="d-none"></div>

<?php include __DIR__.'/includes/script.php'; ?>
<script>
(function(){
'use strict';
const apiUrl='api/expenses-api.php',today=new Date().toISOString().slice(0,10),currentBranchId=<?= (int)$currentBranchId ?>,money=new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'}),$=id=>document.getElementById(id);
let masters={branches:[],categories:[]},expenseModeModal=null,expenseModal=null,viewModal=null,currentPage=1,totalPages=1,currentView=null,timer=null,expenseLineIndex=0;
function esc(v){return String(v??'').replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]))}
function num(v){const n=parseFloat(v||0);return Number.isNaN(n)?0:n}
function csrf(){const x=document.querySelector('#expenseForm input[name="csrf_token"],#expenseForm input[name="_token"]');return x?x.value:''}
function toast(t,m){if(window.AppToast&&window.AppToast.show)window.AppToast.show(t,m);else alert(m)}
async function parseJsonResponse(r){const raw=await r.text();let data;try{data=JSON.parse(raw)}catch(e){throw new Error('API returned invalid response: '+raw.substring(0,180))}if(!r.ok)throw new Error(data.message||('HTTP '+r.status));return data}async function get(p){const r=await fetch(apiUrl+'?'+new URLSearchParams(p),{credentials:'same-origin',headers:{Accept:'application/json'}});return parseJsonResponse(r)}
async function post(fd){if(!fd.has('csrf_token'))fd.append('csrf_token',csrf());const r=await fetch(apiUrl,{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:fd});return parseJsonResponse(r)}
function mm(){if(!expenseModeModal&&window.bootstrap)expenseModeModal=new bootstrap.Modal($('expenseModeModal'));return expenseModeModal}function m(){if(!expenseModal&&window.bootstrap)expenseModal=new bootstrap.Modal($('expenseModal'));return expenseModal}
function vm(){if(!viewModal&&window.bootstrap)viewModal=new bootstrap.Modal($('viewModal'));return viewModal}
function d(v){if(!v)return'-';const p=String(v).split('-');return p.length===3?p[2]+'-'+p[1]+'-'+p[0]:v}
function status(v){return '<span class="pill '+(v==='active'?'pill-active':'pill-cancelled')+'">'+esc(String(v||'').toUpperCase())+'</span>'}
function categoryOptions(selected){return '<option value="">Select Category</option>'+masters.categories.map(x=>'<option value="'+x.category_id+'" '+(String(x.category_id)===String(selected||'')?'selected':'')+'>'+esc(x.category_name)+'</option>').join('')}function fill(){ $('filterBranch').innerHTML='<option value="">All</option>'+masters.branches.map(x=>'<option value="'+x.branch_id+'">'+esc(x.branch_name)+'</option>').join('');$('branchId').innerHTML='<option value="">Select</option>'+masters.branches.map(x=>'<option value="'+x.branch_id+'">'+esc(x.branch_name)+'</option>').join('');$('filterCategory').innerHTML='<option value="">All</option>'+masters.categories.map(x=>'<option value="'+x.category_id+'">'+esc(x.category_name)+'</option>').join('');$('singleCategoryId').innerHTML=categoryOptions('');$('multiCategoryId').innerHTML=categoryOptions('');$('multiBranchId').innerHTML=branchOptions('') }
function p(a){return{action:a||'list',search:$('search').value,date_from:$('dateFrom').value,date_to:$('dateTo').value,branch_id:$('filterBranch').value,category_id:$('filterCategory').value,payment_method:$('filterMethod').value,status:$('filterStatus').value,page:currentPage,per_page:20}}
function stats(s){$('todayExpense').textContent=money.format(num(s.today_amount));$('todayCount').textContent=(s.today_count||0)+' records';$('monthlyExpense').textContent=money.format(num(s.month_amount));$('monthlyCount').textContent=(s.month_count||0)+' records';$('totalExpense').textContent=money.format(num(s.total_amount));$('totalCount').textContent=(s.total_count||0)+' records';$('activeExpense').textContent=money.format(num(s.active_amount));$('activeCount').textContent=(s.active_count||0)+' active'}
function reports(r){$('rangeReport').textContent=money.format(num(r.range_amount));$('rangeReportCount').textContent=(r.range_count||0)+' records';$('topCategory').textContent=r.top_category_name||'-';$('topCategoryAmount').textContent=money.format(num(r.top_category_amount));$('topBranch').textContent=r.top_branch_name||'-';$('topBranchAmount').textContent=money.format(num(r.top_branch_amount));$('topMethod').textContent=r.top_method_name||'-';$('topMethodAmount').textContent=money.format(num(r.top_method_amount))}
function actions(r){return '<div class="d-flex flex-wrap gap-1"><button class="btn btn-outline-primary btn-sm action-btn js-view" data-id="'+r.expense_id+'">View</button><button class="btn btn-outline-warning btn-sm action-btn js-edit" data-id="'+r.expense_id+'">Edit</button><button class="btn btn-outline-dark btn-sm action-btn js-print" data-id="'+r.expense_id+'">Print</button><button class="btn btn-outline-danger btn-sm action-btn js-delete" data-id="'+r.expense_id+'">Delete</button></div>'}
function rows(rs){const tb=$('expenseTableBody'),mc=$('expenseMobileCards');if(!rs.length){tb.innerHTML='<tr><td colspan="11" class="text-center py-4">No expenses found.</td></tr>';mc.innerHTML='';return}tb.innerHTML=rs.map(r=>'<tr><td><b>'+esc(r.expense_no)+'</b></td><td>'+d(r.expense_date)+'</td><td><span class="pill pill-branch">'+esc(r.branch_name||'-')+'</span></td><td><span class="pill pill-category">'+esc(r.category_name||'-')+'</span></td><td><b>'+esc(r.expense_name)+'</b><div class="ex-sub">'+esc(r.description||'')+'</div></td><td><b>'+money.format(num(r.amount))+'</b></td><td><span class="pill pill-method">'+esc(r.payment_method)+'</span></td><td>'+esc(r.paid_to||'-')+'</td><td>'+status(r.status)+'</td><td>'+esc(r.created_by_name||'-')+'<div class="ex-sub">'+esc(r.created_at||'')+'</div></td><td>'+actions(r)+'</td></tr>').join('');mc.innerHTML=rs.map(r=>'<div class="mobile-card"><div class="d-flex justify-content-between"><b>'+esc(r.expense_no)+'</b>'+status(r.status)+'</div><div class="mt-2 fw-bold">'+esc(r.expense_name)+'</div><div>'+money.format(num(r.amount))+'</div><div class="mt-2">'+actions(r)+'</div></div>').join('')}
function pag(x){currentPage=parseInt(x.page||1);totalPages=parseInt(x.total_pages||1);$('paginationInfo').textContent='Page '+currentPage+' of '+totalPages+' · '+(x.total||0)+' records';$('prevPage').disabled=currentPage<=1;$('nextPage').disabled=currentPage>=totalPages}
async function load(init){try{const x=await get(p(init?'init':'list'));if(!x.success)throw new Error(x.message||'Unable to load expenses');if(init){masters=x.masters||masters;fill()}stats(x.stats||{});reports(x.reports||{});rows(x.expenses||[]);pag(x.pagination||{})}catch(e){$('expenseTableBody').innerHTML='<tr><td colspan="11" class="text-center text-danger py-4">'+esc(e.message)+'</td></tr>';toast('error',e.message)}}
function branchOptions(selected){return '<option value="">Select Branch / Firm</option>'+masters.branches.map(x=>'<option value="'+x.branch_id+'" '+(String(x.branch_id)===String(selected||'')?'selected':'')+'>'+esc(x.branch_name)+'</option>').join('')}
function paymentOptions(selected){return '<option value="">Select Payment</option>'+['Cash','UPI','Card','Bank'].map(x=>'<option '+(x===String(selected||'')?'selected':'')+'>'+x+'</option>').join('')}
function addExpenseLine(item){
 item=item||{};expenseLineIndex++;
 const key='expense_file_'+expenseLineIndex;
 const line=document.createElement('div');
 line.className='expense-line';
 line.dataset.lineIndex=expenseLineIndex;
 line.innerHTML='<div class="expense-line-grid">'+
 '<div class="expense-line-name"><label class="form-label">Expense Name *</label><input class="form-control js-line-name" value="'+esc(item.expense_name||'')+'"></div>'+
 '<div class="expense-line-description"><label class="form-label">Description</label><input class="form-control js-line-description" value="'+esc(item.description||'')+'"></div>'+
 '<div class="expense-line-amount"><label class="form-label">Amount *</label><input type="number" min="0.01" step="0.01" class="form-control js-line-amount" value="'+esc(item.amount||'')+'"></div>'+
 '<div class="expense-line-file"><label class="form-label">Attachment</label><input type="file" class="form-control js-line-file" name="'+key+'" accept=".jpg,.jpeg,.png,.webp,.pdf"></div>'+
 '<div class="expense-line-action"><button type="button" class="btn btn-outline-danger expense-line-remove js-remove-line">×</button></div>'+
 '</div>';
 $('expenseLines').appendChild(line);
 line.querySelectorAll('input').forEach(el=>el.addEventListener('input',calculateExpenseLines));
 line.querySelector('.js-remove-line').onclick=()=>{if(document.querySelectorAll('.expense-line').length<=1){toast('warning','At least one expense line is required.');return}line.remove();calculateExpenseLines()};
 calculateExpenseLines();
}
function collectExpenseLines(){
 const commonCategory=parseInt($('multiCategoryId').value||0);
 const commonPayment=$('multiPaymentMethod').value;
 const commonBranch=parseInt($('multiBranchId').value||0);
 return Array.from(document.querySelectorAll('.expense-line')).map((line,index)=>({
  row_no:index+1,
  line_index:parseInt(line.dataset.lineIndex||0),
  category_id:commonCategory,
  expense_name:line.querySelector('.js-line-name').value.trim(),
  description:line.querySelector('.js-line-description').value.trim(),
  amount:num(line.querySelector('.js-line-amount').value),
  payment_method:commonPayment,
  branch_id:commonBranch,
  file_field_name:'expense_file_'+line.dataset.lineIndex
 }));
}
function calculateExpenseLines(){const items=collectExpenseLines();$('lineCount').textContent=items.length;$('lineTotal').textContent=money.format(items.reduce((s,x)=>s+x.amount,0))}
function prepareItems(){
 const mode=$('entryMode').value;
 let items=[];
 if(mode==='single'){items=[{row_no:1,category_id:parseInt($('singleCategoryId').value||0),expense_name:$('singleExpenseName').value.trim(),description:$('singleDescription').value.trim(),amount:num($('singleAmount').value),payment_method:$('paymentMethod').value,branch_id:parseInt($('branchId').value||0),file_field_name:'receipt_file'}]}
 else items=collectExpenseLines();
 if(!items.length){toast('error','Add at least one expense.');return false}
 for(const item of items){if(item.category_id<=0){toast('error','Line '+item.row_no+': category is required.');return false}if(!item.expense_name){toast('error','Line '+item.row_no+': expense name is required.');return false}if(item.amount<=0){toast('error','Line '+item.row_no+': amount must be greater than zero.');return false}if(mode==='multiple'&&!['Cash','UPI','Card','Bank'].includes(item.payment_method)){toast('error','Line '+item.row_no+': payment method is required.');return false}if(mode==='multiple'&&item.branch_id<=0){toast('error','Line '+item.row_no+': branch / firm is required.');return false}}
 $('expenseItemsJson').value=JSON.stringify(items);return true
}
function resetCommon(){ $('expenseForm').reset();$('expenseId').value=0;$('expenseNo').value='Auto Generate';$('expenseDate').value=today;$('status').value='active';$('currentReceipt').innerHTML='';$('expenseLines').innerHTML='';expenseLineIndex=0;if(currentBranchId>0)$('branchId').value=String(currentBranchId)}
function openExpenseMode(mode){resetCommon();$('entryMode').value=mode;const multi=mode==='multiple';$('singleExpenseSection').classList.toggle('d-none',multi);$('multiExpenseSection').classList.toggle('d-none',!multi);document.querySelectorAll('.single-common-field').forEach(el=>el.classList.toggle('d-none',multi));$('expenseModalTitle').textContent=multi?'Add Multiple Expenses':'Add Single Expense';$('expenseModalSub').textContent=multi?'Record multiple categories in one transaction.':'Record one expense under one category.';$('saveExpenseBtn').textContent=multi?'Save Expenses':'Save Expense';if(multi){$('multiCategoryId').value='';$('multiPaymentMethod').value='';$('multiBranchId').value=currentBranchId>0?String(currentBranchId):'';addExpenseLine()}mm().hide();setTimeout(()=>m().show(),150)}
function reset(){openExpenseMode('single')}
async function one(id){const x=await get({action:'get',expense_id:id});if(!x.success)throw new Error(x.message);return x.expense}
async function edit(id){try{const e=await one(id);resetCommon();$('entryMode').value='single';$('singleExpenseSection').classList.remove('d-none');$('multiExpenseSection').classList.add('d-none');document.querySelectorAll('.single-common-field').forEach(el=>el.classList.remove('d-none'));$('expenseModalTitle').textContent='Edit Expense';$('expenseModalSub').textContent='Update the selected expense.';$('saveExpenseBtn').textContent='Update Expense';$('changeExpenseModeBtn').classList.add('d-none');$('expenseId').value=e.expense_id;$('expenseNo').value=e.expense_no;$('expenseDate').value=e.expense_date;$('branchId').value=e.branch_id;$('paymentMethod').value=e.payment_method;$('paidTo').value=e.paid_to||'';$('referenceNumber').value=e.reference_number||'';$('remarks').value=e.remarks||'';$('status').value=e.status;$('singleCategoryId').value=e.category_id;$('singleExpenseName').value=e.expense_name;$('singleDescription').value=e.description||'';$('singleAmount').value=e.amount;$('currentReceipt').innerHTML=e.receipt_path?'<a href="'+esc(e.receipt_path)+'" target="_blank">View current receipt</a>':'';m().show()}catch(e){toast('error',e.message)}}
function detail(e){return '<div class="detail-grid">'+[['Expense Number',e.expense_no],['Expense Date',d(e.expense_date)],['Branch',e.branch_name],['Category',e.category_name],['Expense Name',e.expense_name],['Amount',money.format(num(e.amount))],['Payment',e.payment_method],['Paid To',e.paid_to||'-'],['Reference',e.reference_number||'-'],['Status',e.status],['Created By',e.created_by_name||'-'],['Created At',e.created_at||'-']].map(x=>'<div class="detail-box"><div class="detail-label">'+esc(x[0])+'</div><div class="detail-value">'+esc(x[1])+'</div></div>').join('')+'</div><div class="detail-box mt-3"><div class="detail-label">Description</div><div class="detail-value">'+esc(e.description||'-')+'</div></div><div class="detail-box mt-3"><div class="detail-label">Remarks</div><div class="detail-value">'+esc(e.remarks||'-')+'</div></div>'+(e.receipt_path?'<div class="mt-3"><a href="'+esc(e.receipt_path)+'" target="_blank" class="btn btn-outline-primary btn-sm">Open Receipt</a></div>':'')}
async function view(id){try{currentView=await one(id);$('viewExpenseBody').innerHTML=detail(currentView);vm().show()}catch(e){toast('error',e.message)}}
function printE(e){$('printArea').classList.remove('d-none');$('printArea').innerHTML='<h2 style="text-align:center">Expense Voucher</h2>'+detail(e);setTimeout(()=>{window.print();$('printArea').classList.add('d-none')},100)}
async function del(id){if(!confirm('Delete this expense?'))return;const fd=new FormData();fd.append('action','delete');fd.append('expense_id',id);const x=await post(fd);toast(x.success?'success':'error',x.message);if(x.success)load(false)}
$('addExpenseBtn').onclick=()=>{$('changeExpenseModeBtn').classList.remove('d-none');mm().show()};
$('singleExpenseModeBtn').onclick=()=>openExpenseMode('single');
$('multiExpenseModeBtn').onclick=()=>openExpenseMode('multiple');
$('changeExpenseModeBtn').onclick=()=>{m().hide();setTimeout(()=>mm().show(),150)};
$('addExpenseLineBtn').onclick=()=>addExpenseLine();
['multiCategoryId','multiPaymentMethod','multiBranchId'].forEach(id=>$(id).addEventListener('change',calculateExpenseLines));
$('expenseForm').onsubmit=async e=>{e.preventDefault();if(!prepareItems())return;const b=$('saveExpenseBtn');b.disabled=true;const old=b.textContent;b.textContent='Saving...';try{const x=await post(new FormData(e.target));toast(x.success?'success':'error',x.message||'Save failed');if(x.success){m().hide();load(false)}}catch(err){toast('error',err.message)}finally{b.disabled=false;b.textContent=old}};
['filterBranch','filterCategory','filterMethod','filterStatus','dateFrom','dateTo'].forEach(id=>$(id).onchange=()=>{currentPage=1;load(false)});
$('search').oninput=()=>{clearTimeout(timer);timer=setTimeout(()=>{currentPage=1;load(false)},300)};
$('resetFiltersBtn').onclick=()=>{$('filterForm').reset();currentPage=1;load(false)};
$('prevPage').onclick=()=>{if(currentPage>1){currentPage--;load(false)}};
$('nextPage').onclick=()=>{if(currentPage<totalPages){currentPage++;load(false)}};
$('exportExcelBtn').onclick=()=>location=apiUrl+'?'+new URLSearchParams({...p('export_excel'),page:'',per_page:''});
$('exportPdfBtn').onclick=async()=>{const x=await get({...p('report_data'),page:'',per_page:''});if(!x.success)return;const rs=x.expenses||[];$('printArea').classList.remove('d-none');$('printArea').innerHTML='<h2 style="text-align:center">Expense Report</h2><table border="1" cellpadding="6" style="width:100%;border-collapse:collapse"><tr><th>No</th><th>Date</th><th>Branch</th><th>Category</th><th>Expense</th><th>Amount</th><th>Payment</th><th>Status</th></tr>'+rs.map(r=>'<tr><td>'+esc(r.expense_no)+'</td><td>'+d(r.expense_date)+'</td><td>'+esc(r.branch_name||'-')+'</td><td>'+esc(r.category_name||'-')+'</td><td>'+esc(r.expense_name)+'</td><td>'+money.format(num(r.amount))+'</td><td>'+esc(r.payment_method)+'</td><td>'+esc(r.status)+'</td></tr>').join('')+'</table>';setTimeout(()=>{window.print();$('printArea').classList.add('d-none')},100)};
$('printViewBtn').onclick=()=>currentView&&printE(currentView);
document.addEventListener('click',async e=>{const v=e.target.closest('.js-view'),ed=e.target.closest('.js-edit'),pr=e.target.closest('.js-print'),de=e.target.closest('.js-delete');if(v)view(v.dataset.id);if(ed)edit(ed.dataset.id);if(pr)printE(await one(pr.dataset.id));if(de)del(de.dataset.id)});
load(true);
})();
</script>
</body></html>
