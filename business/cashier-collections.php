<?php
/**
 * GK Footwear POS - Collections
 * Shows only already collected customer payments and collection history.
 * UI and page method aligned with customers.php module template.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
if (function_exists('require_page_access')) {
    require_page_access($conn, 'collections.php');
}

$pageTitle = 'Collections';
$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);

if (!function_exists('e')) {
    function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}


if (!function_exists('collections_table_has_column')) {
    function collections_table_has_column(mysqli $conn, string $tableName, string $columnName): bool
    {
        if (function_exists('table_has_column')) {
            return table_has_column($conn, $tableName, $columnName);
        }

        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }
}

function collections_page_permissions(mysqli $conn, string $pageUrl): array
{
    $all = ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true, 'can_export' => true];

    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return $all;
    }

    $businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);
    $roleId = function_exists('current_role_id') ? (int)current_role_id() : (int)($_SESSION['role_id'] ?? 0);

    if ($businessId <= 0 || $roleId <= 0) {
        return $all;
    }

    if (!function_exists('table_exists') || !table_exists($conn, 'business_sidebar_menus') || !table_exists($conn, 'business_role_sidebar_access')) {
        return $all;
    }

    $cols = ['can_view'];
    foreach (['can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'] as $col) {
        $cols[] = collections_table_has_column($conn, 'business_role_sidebar_access', $col) ? $col : '0 AS ' . $col;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT " . implode(', ', $cols) . "
        FROM business_sidebar_menus sm
        INNER JOIN business_role_sidebar_access rsa
            ON rsa.menu_id = sm.id
           AND rsa.business_id = sm.business_id
           AND rsa.role_id = ?
        WHERE sm.business_id = ?
          AND sm.menu_url = ?
          AND sm.is_active = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'iis', $roleId, $businessId, $pageUrl);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        return $all;
    }

    return [
        'can_view' => (int)($row['can_view'] ?? 0) === 1,
        'can_create' => (int)($row['can_create'] ?? 0) === 1,
        'can_edit' => (int)($row['can_edit'] ?? 0) === 1,
        'can_delete' => (int)($row['can_delete'] ?? 0) === 1,
        'can_print' => (int)($row['can_print'] ?? 0) === 1,
        'can_export' => (int)($row['can_export'] ?? 0) === 1,
    ];
}

if ($businessId <= 0) { die('Business session missing. Please login again.'); }

$permissions = collections_page_permissions($conn, 'collections.php');
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>
    <style>
    .master-page{font-family:"Inter","Segoe UI",Arial,sans-serif;font-size:12px;font-weight:500}.mp-hero,.mp-card,.mp-stat-card{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:16px;box-shadow:0 8px 20px rgba(15,23,42,.06)}.mp-hero{padding:14px 16px}.mp-hero h1{font-size:20px;font-weight:800;margin:0 0 3px;letter-spacing:-.02em;color:var(--text-main,#0f172a)}.mp-hero p{font-size:11px;line-height:1.35;margin:0;color:var(--text-muted,#64748b);font-weight:500}.mp-hero .btn{font-size:11px;padding:7px 11px;min-height:32px;border-radius:999px;font-weight:700}.mp-stat-card{min-height:72px;padding:11px 12px;display:flex;align-items:center;gap:10px}.mp-stat-icon{width:40px;height:40px;border-radius:13px;display:grid;place-items:center;flex:0 0 auto}.mp-stat-icon svg{width:17px;height:17px}.mp-stat-label{font-size:10.5px;color:var(--text-muted,#64748b);font-weight:700;line-height:1.15;text-transform:uppercase;letter-spacing:.04em}.mp-stat-value{font-size:18px;color:var(--text-main,#0f172a);font-weight:800;margin:1px 0;line-height:1.05}.mp-stat-sub,.mp-sub{font-size:10px;color:var(--text-muted,#64748b);line-height:1.25}.mp-card{overflow:hidden}.mp-card-head{padding:12px 14px;border-bottom:1px solid var(--border-soft,#dbe4f0)}.mp-card-title{font-size:15px;font-weight:800;color:var(--text-main,#0f172a);margin:0 0 2px}.mp-card-sub{font-size:11px;color:var(--text-muted,#64748b);margin:0}.mp-filter-input,.mp-filter-select{min-height:32px;font-size:11px;border-radius:999px;padding:5px 10px;font-weight:650}.mp-table th{font-size:10px;font-weight:750;padding:9px 10px;white-space:nowrap;background:#f1f5f9;color:#0f172a;text-transform:uppercase;letter-spacing:.04em;border-bottom:0}.mp-table td{font-size:11px;padding:9px 10px;vertical-align:middle}.mp-avatar{width:34px;height:34px;border-radius:12px;display:grid;place-items:center;background:linear-gradient(135deg,var(--brand-1,#2563eb),var(--brand-2,#7c3aed));color:#fff;font-size:13px;font-weight:800;flex:0 0 auto}.mp-title{font-size:12px;font-weight:800;color:var(--text-main,#0f172a);line-height:1.2}.mp-badge{border-radius:999px;padding:5px 8px;font-size:10px;font-weight:700;display:inline-flex;align-items:center;gap:4px;max-width:190px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.status-paid{background:#dcfce7;color:#15803d}.status-partial{background:#fef3c7;color:#b45309}.status-pending{background:#fee2e2;color:#b91c1c}.badge-code{background:#dbeafe;color:#1d4ed8}.badge-branch{background:#ecfeff;color:#0e7490}.badge-money{background:#dcfce7;color:#15803d}.badge-type{background:#ede9fe;color:#6d28d9}.mp-mobile-card{background:var(--card-bg,#fff);border:1px solid var(--border-soft,#dbe4f0);border-radius:14px;box-shadow:0 8px 20px rgba(15,23,42,.06);padding:10px}.amount-positive{color:#15803d;font-weight:800}.amount-due{color:#b91c1c;font-weight:800}@media(max-width:767px){.mp-hero{padding:12px}.mp-hero h1{font-size:19px}.mp-stat-card{min-height:64px;padding:9px 10px}.mp-stat-icon{width:34px;height:34px;border-radius:11px}.mp-stat-value{font-size:16px}}
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
                        <h1>Collections</h1>
                        <p>Only successfully collected customer payments and collection history are shown here.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="pending-collections.php" class="btn brand-gradient"><i data-lucide="circle-alert" style="width:14px;height:14px;"></i> Pending Collections</a>
                        <a href="bill-list.php" class="btn btn-outline-primary"><i data-lucide="list" style="width:14px;height:14px;"></i> Bill List</a>
                        <button type="button" id="resetPage" class="btn btn-outline-secondary"><i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Reset</button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);color:#fff;"><i data-lucide="receipt-text"></i></div><div><div class="mp-stat-label">Collection Entries</div><div class="mp-stat-value" id="totalCollections">0</div><div class="mp-stat-sub">Payment rows</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="indian-rupee"></i></div><div><div class="mp-stat-label">Total Collected</div><div class="mp-stat-value" id="totalCollected">₹0.00</div><div class="mp-stat-sub">Current filters</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#e0f2fe;color:#0369a1;"><i data-lucide="wallet-cards"></i></div><div><div class="mp-stat-label">Today Collection</div><div class="mp-stat-value" id="todayCollected">₹0.00</div><div class="mp-stat-sub">Received today</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="file-check-2"></i></div><div><div class="mp-stat-label">Collected Invoices</div><div class="mp-stat-value" id="collectedInvoices">0</div><div class="mp-stat-sub">Unique bills</div></div></article></div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div><h2 class="mp-card-title">Customer Collection History</h2><p class="mp-card-sub">Paid payment entries only. Pending invoices are not shown on this page.</p></div>
                            <form method="get" id="filterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                                <input type="text" id="search" class="form-control mp-filter-input" placeholder="Search bill / customer / ref / method">
                                <select id="payment_method_id" class="form-select mp-filter-select"><option value="">All Methods</option></select>
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
                    <table class="table mp-table mb-0"><thead><tr><th>Payment</th><th>Customer</th><th>Invoice</th><th>Branch</th><th>Method</th><th>Collected</th><th>Bill Paid / Due</th><th>Cashier / Time</th></tr></thead><tbody id="collectionTableBody"><tr><td colspan="8" class="text-center text-muted py-4">Loading collections...</td></tr></tbody></table>
                </div>
                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="collectionMobileCards"><div class="mp-mobile-card text-center text-muted">Loading collections...</div></div>
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 py-2 border-top"><div class="mp-sub" id="paginationInfo">Page 1 of 1 · Total 0 collections</div><div class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="prevPage">Previous</button><button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="nextPage">Next</button></div></div>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>
<script>
(function(){
'use strict';
const apiUrl='api/collections-api.php';
const canExport = <?= $permissions['can_export'] ? 'true' : 'false' ?>;
const money=new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR'});
let currentPage=1,totalPages=1,searchTimer=null,state={branches:[],methods:[]};
const $=id=>document.getElementById(id);
function esc(v){return String(v??'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');}
function num(v){const n=parseFloat(v||0);return Number.isNaN(n)?0:n;}
function showMessage(type,msg){if(window.AppToast&&window.AppToast.show){window.AppToast.show(type==='error'?'error':type,msg);return;} if(window.showToast){window.showToast(msg,type==='error'?'danger':type);return;} if(window.Swal){window.Swal.fire(type==='error'?'Error':'Success',msg,type==='error'?'error':'success');return;} alert(msg);}
function buildQuery(params){const q=new URLSearchParams();Object.keys(params||{}).forEach(k=>{if(params[k]!==''&&params[k]!==null&&params[k]!==undefined)q.append(k,params[k]);});return q.toString();}
async function apiGet(params){const r=await fetch(apiUrl+'?'+buildQuery(params),{credentials:'same-origin',headers:{Accept:'application/json'}});return await r.json();}
function badge(status){const s=String(status||'received').toLowerCase();let cls='status-paid';if(s==='partial')cls='status-partial';if(s==='pending')cls='status-pending';return '<span class="mp-badge '+cls+'">'+esc(s.charAt(0).toUpperCase()+s.slice(1))+'</span>';}
function fillSelect(id,rows,valueKey,labelFn,placeholder){const el=$(id);el.innerHTML='<option value="">'+esc(placeholder||'All')+'</option>'+(rows||[]).map(r=>'<option value="'+esc(r[valueKey])+'">'+esc(labelFn(r))+'</option>').join('');}
function renderStats(stats){$('totalCollections').textContent=parseInt(stats.total_collections||0,10);$('totalCollected').textContent=money.format(num(stats.total_collected));$('todayCollected').textContent=money.format(num(stats.today_collected));$('collectedInvoices').textContent=parseInt(stats.collected_invoices||0,10);}
function params(action){return {action:action||'collection_list',search:$('search').value,branch_id:$('branch_id').value,payment_method_id:$('payment_method_id').value,date_from:$('date_from').value,date_to:$('date_to').value,page:currentPage,per_page:20};}
function avatar(row){const base=(row.customer_name||row.bill_no||'C').substring(0,1).toUpperCase();return '<div class="mp-avatar">'+esc(base)+'</div>';}
function renderRows(rows){if(!rows||!rows.length){$('collectionTableBody').innerHTML='<tr><td colspan="8" class="text-center text-muted py-4">No collection history found.</td></tr>';$('collectionMobileCards').innerHTML='<div class="mp-mobile-card text-center text-muted">No collection history found.</div>';return;}
$('collectionTableBody').innerHTML=rows.map(r=>{const branch=(r.branch_name||'-')+(r.floor_name?' / '+r.floor_name:'');return '<tr><td><div class="d-flex align-items-center gap-2">'+avatar(r)+'<div><div class="mp-title">PAY #'+esc(r.payment_id||'-')+'</div><div class="mp-sub">Ref: '+esc(r.reference_no||'-')+'</div></div></div></td><td><div class="mp-title">'+esc(r.customer_name||'Walk-in Customer')+'</div><div class="mp-sub">'+esc(r.customer_mobile||'No mobile')+'</div></td><td><div class="mp-title">'+esc(r.bill_no||'-')+'</div><div class="mp-sub">'+esc(r.order_no||'-')+'</div></td><td><span class="mp-badge badge-branch">'+esc(branch)+'</span></td><td><span class="mp-badge badge-type">'+esc(r.payment_method_name||'-')+'</span><div class="mp-sub">'+esc(r.method_type||'-')+'</div></td><td><strong class="amount-positive">'+money.format(num(r.paid_amount))+'</strong><div class="mp-sub">'+esc(r.payment_note||'')+'</div></td><td><span class="amount-positive">'+money.format(num(r.bill_paid_amount))+'</span><div class="mp-sub amount-due">Due '+money.format(num(r.balance_amount))+'</div>'+badge(r.bill_payment_status)+'</td><td>'+esc(r.cashier_name||'-')+'<div class="mp-sub">'+esc(r.collected_at||'-')+'</div></td></tr>';}).join('');
$('collectionMobileCards').innerHTML=rows.map(r=>'<div class="mp-mobile-card"><div class="d-flex gap-2">'+avatar(r)+'<div class="flex-grow-1"><div class="d-flex justify-content-between gap-2"><div><div class="mp-title">'+esc(r.bill_no||'-')+'</div><div class="mp-sub">'+esc(r.customer_name||'Walk-in Customer')+'</div></div><strong class="amount-positive">'+money.format(num(r.paid_amount))+'</strong></div><div class="d-flex flex-wrap gap-2 mt-2"><span class="mp-badge badge-type">'+esc(r.payment_method_name||'-')+'</span><span class="mp-badge badge-code">PAY #'+esc(r.payment_id||'-')+'</span>'+badge(r.bill_payment_status)+'</div><div class="mp-sub mt-2">'+esc(r.cashier_name||'-')+' · '+esc(r.collected_at||'-')+'</div></div></div></div>').join('');}
function renderPagination(p){currentPage=parseInt(p.page||1,10);totalPages=parseInt(p.total_pages||1,10);$('paginationInfo').textContent='Page '+currentPage+' of '+totalPages+' · Total '+parseInt(p.total||0,10)+' collections';$('prevPage').disabled=currentPage<=1;$('nextPage').disabled=currentPage>=totalPages;}
async function loadCollections(init){try{const data=await apiGet(params(init?'init_collections':'collection_list'));if(!data.success){showMessage('error',data.message||'Unable to load collections.');return;}if(init){state.branches=data.branches||[];state.methods=data.payment_methods||[];fillSelect('branch_id',state.branches,'branch_id',b=>(b.branch_name||'-')+(b.floor_name?' / '+b.floor_name:''),'All Branch/Firm');fillSelect('payment_method_id',state.methods,'payment_method_id',m=>(m.payment_method_name||'-'),'All Methods');}renderStats(data.stats||{});renderRows(data.collections||[]);renderPagination(data.pagination||{});if(window.lucide)window.lucide.createIcons();}catch(e){showMessage('error','Unable to connect to collections API.');}}
$('filterForm').addEventListener('submit',e=>{e.preventDefault();currentPage=1;loadCollections(false);});$('search').addEventListener('input',()=>{clearTimeout(searchTimer);searchTimer=setTimeout(()=>{currentPage=1;loadCollections(false);},300);});['branch_id','payment_method_id','date_from','date_to'].forEach(id=>$(id).addEventListener('change',()=>{currentPage=1;loadCollections(false);}));$('todayBtn').addEventListener('click',()=>{const d=new Date().toISOString().slice(0,10);$('date_from').value=d;$('date_to').value=d;currentPage=1;loadCollections(false);});$('resetPage').addEventListener('click',()=>{$('search').value='';$('branch_id').value='';$('payment_method_id').value='';$('date_from').value='';$('date_to').value='';currentPage=1;loadCollections(false);});$('prevPage').addEventListener('click',()=>{if(currentPage>1){currentPage--;loadCollections(false);}});$('nextPage').addEventListener('click',()=>{if(currentPage<totalPages){currentPage++;loadCollections(false);}});loadCollections(true);
})();
</script>
</body>
</html>
