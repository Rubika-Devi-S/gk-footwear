<?php
/**
 * Product Sales Ranking Report
 * File: business/product-sales-ranking-report.php
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
if (function_exists('require_page_access')) {
    require_page_access($conn, 'product-sales-ranking-report.php');
}

$pageTitle = 'Product Sales Ranking Report';

function psr_e($value): string
{
    return function_exists('e')
        ? e((string)$value)
        : htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en" class="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= psr_e($pageTitle) ?> - GK Footwear POS</title>
<?php include __DIR__ . '/includes/links.php'; ?>
<style>
:root{
    --psr-bg:var(--page-bg,#f1f5f9);
    --psr-card:var(--card-bg,#fff);
    --psr-border:var(--border-soft,#dbe4f0);
    --psr-text:var(--text-main,#0f172a);
    --psr-muted:var(--text-muted,#64748b)
}
.product-ranking-page{
    width:100%;
    max-width:100%;
    min-width:0;
    overflow-x:hidden;
    font-family:Inter,"Segoe UI",Arial,sans-serif;
    font-size:12px;
    color:var(--psr-text)
}
#main{width:100%;min-width:0;max-width:100%;overflow-x:hidden}
.page-section{min-width:0;max-width:100%}
.psr-card{min-width:0;max-width:100%;overflow:hidden}
.psr-card,.psr-hero,.psr-stat{background:var(--psr-card);border:1px solid var(--psr-border);border-radius:16px;box-shadow:0 8px 22px rgba(15,23,42,.06)}
.psr-hero{padding:14px 16px}.psr-hero h1{font-size:20px;font-weight:900;margin:0 0 3px}.psr-sub{font-size:10px;color:var(--psr-muted)}
.psr-stat{min-height:76px;padding:12px;display:flex;align-items:center;gap:10px}.psr-stat-icon{width:42px;height:42px;border-radius:14px;display:grid;place-items:center}.psr-stat-label{font-size:9.5px;font-weight:850;text-transform:uppercase;color:var(--psr-muted);letter-spacing:.04em}.psr-stat-value{font-size:18px;font-weight:900;line-height:1.1}
.psr-head{padding:12px 14px;border-bottom:1px solid var(--psr-border);display:flex;justify-content:space-between;align-items:center;gap:10px}.psr-title{font-size:15px;font-weight:900;margin:0}
.psr-filter{
    padding:10px 12px;
    background:color-mix(in srgb,var(--psr-card) 92%,#e2e8f0 8%);
    border-bottom:1px solid var(--psr-border);
    overflow:hidden
}
.psr-filter .row{
    display:grid;
    grid-template-columns:minmax(210px,2.2fr) repeat(9,minmax(82px,1fr));
    gap:7px;
    margin:0
}
.psr-filter .row>[class*="col-"]{width:auto;max-width:none;padding:0}
.psr-filter label{font-size:8px;font-weight:850;text-transform:uppercase;color:var(--psr-muted);margin-bottom:2px}
.psr-filter .form-control,.psr-filter .form-select{
    width:100%;
    min-width:0;
    font-size:9.5px;
    min-height:32px;
    height:32px;
    padding:4px 7px;
    border-radius:9px;
    background:var(--psr-card);
    color:var(--psr-text);
    border-color:var(--psr-border)
}
.psr-table{
    width:100%!important;
    max-width:100%;
    table-layout:fixed;
    margin:0
}
.psr-table th{
    font-size:7.7px;
    line-height:1.05;
    text-transform:uppercase;
    letter-spacing:.015em;
    background:color-mix(in srgb,var(--psr-card) 86%,#cbd5e1 14%);
    color:var(--psr-text);
    padding:7px 4px;
    white-space:normal;
    overflow-wrap:anywhere;
    position:sticky;
    top:0;
    z-index:2
}
.psr-table td{
    font-size:8.8px;
    line-height:1.15;
    padding:7px 4px;
    vertical-align:middle;
    color:var(--psr-text);
    white-space:normal;
    overflow-wrap:anywhere
}
.psr-table tbody tr{cursor:pointer}
.psr-table tbody tr:hover{background:color-mix(in srgb,var(--psr-card) 90%,#dbeafe 10%)}
.psr-table th:nth-child(1),.psr-table td:nth-child(1){width:42px;text-align:center}
.psr-table th:nth-child(2),.psr-table td:nth-child(2){width:72px}
.psr-table th:nth-child(3),.psr-table td:nth-child(3){width:72px}
.psr-table th:nth-child(4),.psr-table td:nth-child(4){width:150px}
.psr-table th:nth-child(5),.psr-table td:nth-child(5){width:72px}
.psr-table th:nth-child(6),.psr-table td:nth-child(6){width:70px}
.psr-table th:nth-child(7),.psr-table td:nth-child(7){width:42px}
.psr-table th:nth-child(8),.psr-table td:nth-child(8){width:54px}
.psr-table th:nth-child(9),.psr-table td:nth-child(9){width:67px;text-align:right}
.psr-table th:nth-child(10),.psr-table td:nth-child(10){width:58px;text-align:right}
.psr-table th:nth-child(11),.psr-table td:nth-child(11){width:82px;text-align:right}
.psr-table th:nth-child(12),.psr-table td:nth-child(12){width:72px;text-align:right}
.psr-table th:nth-child(13),.psr-table td:nth-child(13){width:82px;text-align:right}
.psr-table th:nth-child(14),.psr-table td:nth-child(14){width:76px;text-align:right}
.psr-table th:nth-child(15),.psr-table td:nth-child(15){width:64px;text-align:center}
.rank-badge{width:29px;height:29px;border-radius:10px;display:grid;place-items:center;font-weight:900;background:#e2e8f0;color:#334155}.rank-1{background:#fef3c7;color:#a16207}.rank-2{background:#e2e8f0;color:#475569}.rank-3{background:#fed7aa;color:#c2410c}
.product-cell{display:flex;align-items:center;gap:6px;min-width:0;max-width:100%}
.product-avatar{width:28px;height:28px;border-radius:9px;background:linear-gradient(135deg,#2563eb,#06b6d4);color:#fff;display:grid;place-items:center;font-size:9px;font-weight:900;flex:0 0 28px}
.product-cell>div:last-child{min-width:0}
.product-name{font-size:9px;font-weight:850;white-space:normal;overflow-wrap:anywhere}
.product-code{font-size:7.5px;color:var(--psr-muted)}
.metric-qty{font-weight:900;color:#1d4ed8}.metric-sales{font-weight:850;color:#0f766e}.metric-profit{font-weight:900;color:#15803d}.metric-loss{font-weight:900;color:#b91c1c}.zero-sales{opacity:.72}
.psr-pill{display:inline-flex;border-radius:999px;padding:4px 7px;font-size:9px;font-weight:800;background:#eef2ff;color:#4338ca}
.psr-mobile-card{border:1px solid var(--psr-border);background:var(--psr-card);border-radius:14px;padding:10px;cursor:pointer}
.history-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.history-stat{border:1px solid var(--psr-border);border-radius:12px;padding:9px;background:color-mix(in srgb,var(--psr-card) 94%,#e2e8f0 6%)}.history-label{font-size:9px;text-transform:uppercase;color:var(--psr-muted);font-weight:850}.history-value{font-size:13px;font-weight:900;margin-top:2px}
.table-scroll{
    width:100%;
    max-width:100%;
    max-height:62vh;
    overflow-y:auto;
    overflow-x:hidden;
    overscroll-behavior:contain
}
body.dark,.dark body,[data-theme="dark"] body{--psr-card:#111827;--psr-border:#334155;--psr-text:#f8fafc;--psr-muted:#94a3b8;--psr-bg:#0f172a}
@media(max-width:1399px){
    .psr-filter .row{grid-template-columns:minmax(190px,2fr) repeat(5,minmax(95px,1fr))}
    .psr-table th{font-size:7.2px;padding:6px 3px}
    .psr-table td{font-size:8.2px;padding:6px 3px}
    .product-avatar{width:25px;height:25px;flex-basis:25px}
}
@media(max-width:1199px){
    .psr-filter .row{grid-template-columns:repeat(4,minmax(0,1fr))}
    .psr-filter .row>div:first-child{grid-column:span 2}
    .table-scroll{overflow-x:auto}
    .psr-table{min-width:1120px}
}
@media(max-width:991px){
    .product-ranking-page{padding:10px!important}
    .psr-hero .d-flex{align-items:flex-start!important}
    .psr-filter .row{grid-template-columns:repeat(3,minmax(0,1fr))}
    .psr-filter .row>div:first-child{grid-column:span 3}
}
@media(max-width:767px){
    .history-grid{grid-template-columns:repeat(2,1fr)}
    .psr-hero{padding:12px}
    .psr-stat{min-height:66px}
    .psr-stat-value{font-size:16px}
    .psr-filter .row{grid-template-columns:repeat(2,minmax(0,1fr))}
    .psr-filter .row>div:first-child{grid-column:span 2}
}.psr-hero{padding:12px}.psr-stat{min-height:66px}.psr-stat-value{font-size:16px}}
@media print{
    body *{visibility:hidden!important}
    #reportPrintArea,#reportPrintArea *{visibility:visible!important}
    #reportPrintArea{display:block!important;position:absolute;left:0;top:0;width:100%;padding:12px;background:#fff;color:#000}
    #reportPrintArea table{width:100%;border-collapse:collapse;font-size:8px}
    #reportPrintArea th,#reportPrintArea td{border:1px solid #777;padding:4px}
    @page{size:landscape;margin:8mm}
}
</style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) include __DIR__ . '/includes/common-toast.php'; ?>

<div class="min-vh-100 d-flex">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main id="main">
<?php include __DIR__ . '/includes/nav.php'; ?>
<section class="page-section product-ranking-page p-3">
    <div class="psr-hero mb-3">
        <div class="d-flex flex-column flex-lg-row justify-content-lg-between align-items-lg-center gap-3">
            <div>
                <h1>Product Sales Ranking Report</h1>
                <div class="psr-sub">Highest-selling products first, zero-sale products last, calculated dynamically without duplicate sales rows.</div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-success btn-sm rounded-pill fw-bold" id="excelBtn">Excel</button>
                <button class="btn btn-outline-primary btn-sm rounded-pill fw-bold" id="csvBtn">CSV</button>
                <button class="btn btn-outline-danger btn-sm rounded-pill fw-bold" id="pdfBtn">PDF</button>
                <button class="btn btn-dark btn-sm rounded-pill fw-bold" id="printBtn"><i data-lucide="printer" style="width:13px"></i> Print</button>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="psr-stat"><div class="psr-stat-icon" style="background:#dbeafe;color:#1d4ed8"><i data-lucide="package-search"></i></div><div><div class="psr-stat-label">Products</div><div class="psr-stat-value" id="statProducts">0</div><div class="psr-sub">Matching products</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="psr-stat"><div class="psr-stat-icon" style="background:#ede9fe;color:#6d28d9"><i data-lucide="shopping-bag"></i></div><div><div class="psr-stat-label">Quantity Sold</div><div class="psr-stat-value" id="statQty">0</div><div class="psr-sub">Total units</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="psr-stat"><div class="psr-stat-icon" style="background:#dcfce7;color:#15803d"><i data-lucide="indian-rupee"></i></div><div><div class="psr-stat-label">Net Sales</div><div class="psr-stat-value" id="statSales">₹0.00</div><div class="psr-sub">After product discount</div></div></div></div>
        <div class="col-6 col-xl-3"><div class="psr-stat"><div class="psr-stat-icon" style="background:#fef3c7;color:#b45309"><i data-lucide="chart-no-axes-combined"></i></div><div><div class="psr-stat-label">Profit</div><div class="psr-stat-value" id="statProfit">₹0.00</div><div class="psr-sub">Net sales − cost</div></div></div></div>
    </div>

    <div class="psr-card">
        <div class="psr-head">
            <div><h2 class="psr-title">Ranked Products</h2><div class="psr-sub">Click any product row to view its complete sales history.</div></div>
            <button type="button" class="btn btn-light btn-sm rounded-pill" id="resetBtn">Reset Filters</button>
        </div>

        <div class="psr-filter">
            <form id="filterForm" class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-xl-3"><label>Search</label><input type="text" id="search" class="form-control" placeholder="Product, code or barcode"></div>
                <div class="col-6 col-md-2 col-xl-1"><label>Period</label><select id="period" class="form-select"><option value="today">Today</option><option value="yesterday">Yesterday</option><option value="week">This Week</option><option value="month" selected>This Month</option><option value="custom">Custom</option><option value="all">All Time</option></select></div>
                <div class="col-6 col-md-2 col-xl-1"><label>From</label><input type="date" id="dateFrom" class="form-control"></div>
                <div class="col-6 col-md-2 col-xl-1"><label>To</label><input type="date" id="dateTo" class="form-control"></div>
                <div class="col-6 col-md-2 col-xl-1"><label>Branch</label><select id="branchId" class="form-select"><option value="">All</option></select></div>
                <div class="col-6 col-md-2 col-xl-1"><label>Category</label><select id="categoryId" class="form-select"><option value="">All</option></select></div>
                <div class="col-6 col-md-2 col-xl-1"><label>Brand</label><select id="brandId" class="form-select"><option value="">All</option></select></div>
                <div class="col-6 col-md-2 col-xl-1"><label>Sort By</label><select id="sortBy" class="form-select"><option value="quantity">Quantity Sold</option><option value="sales">Sales Amount</option><option value="profit">Profit</option><option value="name">Product Name</option></select></div>
                <div class="col-6 col-md-2 col-xl-1"><label>Order</label><select id="sortDir" class="form-select"><option value="desc">High to Low</option><option value="asc">Low to High</option></select></div>
                <div class="col-6 col-md-2 col-xl-1"><label>Rows</label><select id="perPage" class="form-select"><option>20</option><option>50</option><option>100</option></select></div>
            </form>
        </div>

        <div class="d-none d-lg-block table-scroll">
            <table class="table psr-table mb-0">
                <thead><tr>
                    <th>Rank</th><th>Product Code</th><th>Barcode</th><th>Product Name</th><th>Category</th><th>Brand</th><th>Size</th><th>Color</th><th>Current Stock</th><th>Qty Sold</th><th>Total Sales</th><th>Discount</th><th>Net Sales</th><th>Profit</th><th>Last Sold</th>
                </tr></thead>
                <tbody id="reportBody"><tr><td colspan="15" class="text-center text-muted py-4">Loading ranking report...</td></tr></tbody>
            </table>
        </div>
        <div class="d-lg-none d-grid gap-2 p-3" id="mobileCards"></div>

        <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2 border-top">
            <div class="psr-sub" id="paginationInfo">Page 1 of 1</div>
            <div class="d-flex gap-2"><button class="btn btn-outline-secondary btn-sm rounded-pill" id="prevBtn">Previous</button><button class="btn btn-outline-secondary btn-sm rounded-pill" id="nextBtn">Next</button></div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
</section>
</main>
</div>

<div class="modal fade" id="historyModal" tabindex="-1">
<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">
    <div class="modal-header"><div><h5 class="modal-title" id="historyTitle">Product Sales History</h5><div class="psr-sub" id="historySub"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="history-grid mb-3">
            <div class="history-stat"><div class="history-label">Current Stock</div><div class="history-value" id="historyStock">0</div></div>
            <div class="history-stat"><div class="history-label">Quantity Sold</div><div class="history-value" id="historyQty">0</div></div>
            <div class="history-stat"><div class="history-label">Net Sales</div><div class="history-value" id="historySales">₹0.00</div></div>
            <div class="history-stat"><div class="history-label">Profit</div><div class="history-value" id="historyProfit">₹0.00</div></div>
        </div>
        <div class="table-responsive"><table class="table psr-table"><thead><tr><th>Date</th><th>Bill No</th><th>Branch</th><th>Customer</th><th>Qty</th><th>MRP</th><th>Selling</th><th>Discount</th><th>Net Amount</th><th>Profit</th></tr></thead><tbody id="historyBody"></tbody></table></div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
</div>
</div>
</div>

<div id="reportPrintArea" class="d-none"></div>

<?php include __DIR__ . '/includes/script.php'; ?>
<script>
(function(){
'use strict';
const apiUrl='api/product-sales-ranking-report-api.php';
const $=id=>document.getElementById(id);
const money=new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'});
let currentPage=1,totalPages=1,rows=[],historyModal=null,searchTimer=null;

function esc(v){return String(v??'').replace(/[&<>"']/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]))}
function num(v){const n=parseFloat(v||0);return Number.isNaN(n)?0:n}
function toast(type,msg){if(window.AppToast&&window.AppToast.show)window.AppToast.show(type,msg);else alert(msg)}
async function parseResponse(r){const raw=await r.text();let data;try{data=JSON.parse(raw)}catch(e){throw new Error('Invalid API response: '+raw.substring(0,180))}if(!r.ok||!data.success)throw new Error(data.message||('HTTP '+r.status));return data}
async function get(params){return parseResponse(await fetch(apiUrl+'?'+new URLSearchParams(params),{credentials:'same-origin',headers:{Accept:'application/json'}}))}
function modal(){if(!historyModal&&window.bootstrap)historyModal=new bootstrap.Modal($('historyModal'));return historyModal}
function dateText(v){if(!v)return'-';const p=String(v).split('-');return p.length===3?p[2]+'-'+p[1]+'-'+p[0]:v}
function params(action){return{action:action||'list',search:$('search').value,period:$('period').value,date_from:$('dateFrom').value,date_to:$('dateTo').value,branch_id:$('branchId').value,category_id:$('categoryId').value,brand_id:$('brandId').value,sort_by:$('sortBy').value,sort_dir:$('sortDir').value,page:currentPage,per_page:$('perPage').value}}
function fillMasters(m){
 $('branchId').innerHTML='<option value="">All</option>'+(m.branches||[]).map(x=>'<option value="'+x.branch_id+'">'+esc(x.branch_name)+'</option>').join('');
 $('categoryId').innerHTML='<option value="">All</option>'+(m.categories||[]).map(x=>'<option value="'+x.category_id+'">'+esc(x.category_name)+'</option>').join('');
 $('brandId').innerHTML='<option value="">All</option>'+(m.brands||[]).map(x=>'<option value="'+x.brand_id+'">'+esc(x.brand_name)+'</option>').join('');
}
function rankClass(rank){return rank===1?'rank-1':rank===2?'rank-2':rank===3?'rank-3':''}
function rowHtml(r){
 const profit=num(r.total_profit),zero=num(r.total_qty_sold)<=0;
 return '<tr class="'+(zero?'zero-sales':'')+'" data-key="'+esc(r.product_key)+'">'+
 '<td><span class="rank-badge '+rankClass(parseInt(r.rank_no))+'">'+r.rank_no+'</span></td>'+
 '<td><strong>'+esc(r.product_code)+'</strong></td><td>'+esc(r.barcode||'-')+'</td>'+
 '<td><div class="product-cell"><div class="product-avatar">'+esc((r.product_name||r.product_code||'P').slice(0,2).toUpperCase())+'</div><div><div class="product-name">'+esc(r.product_name||'-')+'</div></div></div></td>'+
 '<td><span class="psr-pill">'+esc(r.category_name||'-')+'</span></td><td>'+esc(r.brand_name||'-')+'</td><td>'+esc(r.size||'-')+'</td><td>'+esc(r.color||'-')+'</td>'+
 '<td><strong>'+num(r.current_stock).toFixed(2)+'</strong></td><td class="metric-qty">'+num(r.total_qty_sold).toFixed(2)+'</td>'+
 '<td class="metric-sales">'+money.format(num(r.total_sales_amount))+'</td><td>'+money.format(num(r.total_discount))+'</td><td><strong>'+money.format(num(r.net_sales))+'</strong></td>'+
 '<td class="'+(profit>=0?'metric-profit':'metric-loss')+'">'+money.format(profit)+'</td><td>'+dateText(r.last_sold_date)+'</td></tr>';
}
function mobileHtml(r){
 const profit=num(r.total_profit);
 return '<article class="psr-mobile-card '+(num(r.total_qty_sold)<=0?'zero-sales':'')+'" data-key="'+esc(r.product_key)+'"><div class="d-flex justify-content-between gap-2"><div class="product-cell"><span class="rank-badge '+rankClass(parseInt(r.rank_no))+'">'+r.rank_no+'</span><div><div class="product-name">'+esc(r.product_name||'-')+'</div><div class="product-code">'+esc(r.product_code)+' · '+esc(r.barcode||'-')+'</div></div></div><span class="psr-pill">'+esc(r.category_name||'-')+'</span></div><div class="row g-2 mt-2"><div class="col-4"><div class="psr-sub">Sold</div><b class="metric-qty">'+num(r.total_qty_sold).toFixed(2)+'</b></div><div class="col-4"><div class="psr-sub">Net Sales</div><b>'+money.format(num(r.net_sales))+'</b></div><div class="col-4"><div class="psr-sub">Profit</div><b class="'+(profit>=0?'metric-profit':'metric-loss')+'">'+money.format(profit)+'</b></div></div></article>';
}
function render(data){
 rows=data.rows||[];
 $('reportBody').innerHTML=rows.length?rows.map(rowHtml).join(''):'<tr><td colspan="15" class="text-center text-muted py-4">No products found.</td></tr>';
 $('mobileCards').innerHTML=rows.length?rows.map(mobileHtml).join(''):'<div class="psr-mobile-card text-center text-muted">No products found.</div>';
 const s=data.summary||{};$('statProducts').textContent=s.total_products||0;$('statQty').textContent=num(s.total_qty_sold).toFixed(2);$('statSales').textContent=money.format(num(s.net_sales));$('statProfit').textContent=money.format(num(s.total_profit));
 const p=data.pagination||{};currentPage=parseInt(p.page||1);totalPages=parseInt(p.total_pages||1);$('paginationInfo').textContent='Page '+currentPage+' of '+totalPages+' · '+(p.total||0)+' products';$('prevBtn').disabled=currentPage<=1;$('nextBtn').disabled=currentPage>=totalPages;
 if(window.lucide)window.lucide.createIcons();
}
async function load(init){
 try{const data=await get(params(init?'init':'list'));if(init)fillMasters(data.masters||{});render(data)}
 catch(e){$('reportBody').innerHTML='<tr><td colspan="15" class="text-center text-danger py-4">'+esc(e.message)+'</td></tr>';toast('error',e.message)}
}
async function openHistory(key){
 try{
  const data=await get({...params('history'),product_key:key,page:1,per_page:500});
  const p=data.product||{},s=data.summary||{};
  $('historyTitle').textContent=p.product_name||'Product Sales History';$('historySub').textContent=[p.product_code,p.barcode,p.brand_name,p.size,p.color].filter(Boolean).join(' · ');
  $('historyStock').textContent=num(s.current_stock).toFixed(2);$('historyQty').textContent=num(s.total_qty_sold).toFixed(2);$('historySales').textContent=money.format(num(s.net_sales));$('historyProfit').textContent=money.format(num(s.total_profit));
  $('historyBody').innerHTML=(data.history||[]).map(h=>'<tr><td>'+dateText(h.bill_date)+'</td><td><b>'+esc(h.bill_no||'-')+'</b></td><td>'+esc(h.branch_name||'-')+'</td><td>'+esc(h.customer_name||'Walk-in')+'</td><td>'+num(h.qty).toFixed(2)+'</td><td>'+money.format(num(h.mrp_rate))+'</td><td>'+money.format(num(h.selling_rate))+'</td><td>'+money.format(num(h.discount_amount_total))+'</td><td>'+money.format(num(h.net_amount))+'</td><td class="'+(num(h.profit)>=0?'metric-profit':'metric-loss')+'">'+money.format(num(h.profit))+'</td></tr>').join('')||'<tr><td colspan="10" class="text-center text-muted py-4">No sales history for the selected period.</td></tr>';
  modal().show();
 }catch(e){toast('error',e.message)}
}
function exportUrl(action){const q=params(action);delete q.page;delete q.per_page;return apiUrl+'?'+new URLSearchParams(q)}
function printReport(title){
 const header='<h2 style="text-align:center;margin:0 0 4px">'+esc(title)+'</h2><div style="text-align:center;margin-bottom:10px">Period: '+esc($('period').selectedOptions[0].text)+' | Generated: '+new Date().toLocaleString('en-IN')+'</div>';
 const table='<table><thead><tr><th>Rank</th><th>Code</th><th>Barcode</th><th>Product</th><th>Category</th><th>Brand</th><th>Size</th><th>Color</th><th>Stock</th><th>Qty Sold</th><th>Total Sales</th><th>Discount</th><th>Net Sales</th><th>Profit</th><th>Last Sold</th></tr></thead><tbody>'+rows.map(r=>'<tr><td>'+r.rank_no+'</td><td>'+esc(r.product_code)+'</td><td>'+esc(r.barcode||'-')+'</td><td>'+esc(r.product_name||'-')+'</td><td>'+esc(r.category_name||'-')+'</td><td>'+esc(r.brand_name||'-')+'</td><td>'+esc(r.size||'-')+'</td><td>'+esc(r.color||'-')+'</td><td>'+num(r.current_stock).toFixed(2)+'</td><td>'+num(r.total_qty_sold).toFixed(2)+'</td><td>'+money.format(num(r.total_sales_amount))+'</td><td>'+money.format(num(r.total_discount))+'</td><td>'+money.format(num(r.net_sales))+'</td><td>'+money.format(num(r.total_profit))+'</td><td>'+dateText(r.last_sold_date)+'</td></tr>').join('')+'</tbody></table>';
 $('reportPrintArea').classList.remove('d-none');$('reportPrintArea').innerHTML=header+table;setTimeout(()=>{window.print();$('reportPrintArea').classList.add('d-none')},100);
}
$('filterForm').onsubmit=e=>e.preventDefault();
['period','dateFrom','dateTo','branchId','categoryId','brandId','sortBy','sortDir','perPage'].forEach(id=>$(id).onchange=()=>{currentPage=1;load(false)});
$('search').oninput=()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{currentPage=1;load(false)},300)};
$('resetBtn').onclick=()=>{$('filterForm').reset();$('period').value='month';$('sortBy').value='quantity';$('sortDir').value='desc';currentPage=1;load(false)};
$('prevBtn').onclick=()=>{if(currentPage>1){currentPage--;load(false)}};$('nextBtn').onclick=()=>{if(currentPage<totalPages){currentPage++;load(false)}};
$('excelBtn').onclick=()=>location=exportUrl('export_excel');$('csvBtn').onclick=()=>location=exportUrl('export_csv');$('printBtn').onclick=()=>printReport('Product Sales Ranking Report');$('pdfBtn').onclick=()=>printReport('Product Sales Ranking Report - Save as PDF');
document.addEventListener('click',e=>{const row=e.target.closest('[data-key]');if(row)openHistory(row.dataset.key)});
load(true);
})();
</script>
</body>
</html>
