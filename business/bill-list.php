<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
if (function_exists('require_page_access')) {
    require_page_access($conn, 'bill-list.php');
}

$pageTitle = 'Bill List';
$businessId = function_exists('current_business_id') ? (int) current_business_id() : (int)($_SESSION['business_id'] ?? 0);

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function bill_list_csrf_field() {
    if (function_exists('csrf_field')) {
        return csrf_field();
    }
    if (function_exists('csrf_token')) {
        return '<input type="hidden" name="csrf_token" id="billListCsrfToken" value="' . e(csrf_token()) . '">';
    }
    return '<input type="hidden" name="csrf_token" id="billListCsrfToken" value="">';
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
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .status-active { background: #dcfce7; color: #15803d; }
    .status-paid { background: #dcfce7; color: #15803d; }
    .status-partial { background: #fef3c7; color: #b45309; }
    .status-pending { background: #e0f2fe; color: #0369a1; }
    .status-cancelled { background: #fee2e2; color: #b91c1c; }
    .status-deleted { background: #f1f5f9; color: #475569; }
    .badge-code { background: #dbeafe; color: #1d4ed8; }
    .badge-count { background: #fef3c7; color: #b45309; }
    .badge-type { background: #ede9fe; color: #6d28d9; }
    .badge-branch { background: #ecfeff; color: #0e7490; }
    .badge-money { background: #dcfce7; color: #15803d; }
    .badge-due { background: #fee2e2; color: #b91c1c; }
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

    .bill-action-group {
        display: inline-flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
    }

    .bill-action-icon {
        min-width: 34px;
        min-height: 34px;
        padding: 7px 9px;
        border-radius: 12px;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        font-size: 10px;
        font-weight: 800;
        line-height: 1;
        text-decoration: none;
        transition: .16s ease;
        box-shadow: 0 6px 14px rgba(15, 23, 42, .08);
        white-space: nowrap;
    }

    .bill-action-icon svg {
        width: 14px;
        height: 14px;
        stroke-width: 2.4;
        flex: 0 0 auto;
    }

    .bill-action-icon:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, .13);
        text-decoration: none;
    }

    .bill-action-view {
        color: #1d4ed8;
        background: #eff6ff;
        border-color: #bfdbfe;
    }

    .bill-action-view:hover {
        color: #ffffff;
        background: #2563eb;
        border-color: #2563eb;
    }

    .bill-action-print {
        color: #047857;
        background: #ecfdf5;
        border-color: #a7f3d0;
    }

    .bill-action-print:hover {
        color: #ffffff;
        background: #059669;
        border-color: #059669;
    }

    .bill-action-cancel {
        color: #b91c1c;
        background: #fef2f2;
        border-color: #fecaca;
    }

    .bill-action-cancel:hover {
        color: #ffffff;
        background: #dc2626;
        border-color: #dc2626;
    }

    /* NEW: Print button with spinner */
    .bill-action-print-active {
        color: #ffffff;
        background: #059669;
        border-color: #059669;
        cursor: wait;
        opacity: 0.8;
    }

    .mp-mobile-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 10px;
    }
    .modal-title { font-size: 15px; font-weight: 750; }
    .modal .form-label { font-size: 11px; font-weight: 700; margin-bottom: 4px; }
    .modal .form-control, .modal .form-select { min-height: 34px; font-size: 12px; border-radius: 12px; padding: 6px 10px; }
    .modal-footer .btn { font-size: 12px; padding: 7px 12px; border-radius: 12px; font-weight: 700; }
    .bill-detail-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    .bill-detail-box {
        border: 1px solid var(--border-soft);
        background: #f8fafc;
        border-radius: 14px;
        padding: 10px;
    }
    .amount-positive { color:#15803d; font-weight:800; }
    .amount-due { color:#b91c1c; font-weight:800; }

    /* Print toast notification */
    .print-toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        min-width: 280px;
        max-width: 400px;
        padding: 14px 18px;
        border-radius: 12px;
        background: #ffffff;
        border: 1px solid #dbe4f0;
        box-shadow: 0 12px 30px rgba(15, 23, 42, .15);
        z-index: 9999;
        display: none;
        animation: slideUp 0.3s ease;
    }

    .print-toast.show {
        display: block;
    }

    .print-toast.success {
        border-left: 4px solid #16a34a;
    }

    .print-toast.error {
        border-left: 4px solid #dc2626;
    }

    .print-toast .toast-title {
        font-weight: 700;
        font-size: 12px;
        color: #0f172a;
    }

    .print-toast .toast-message {
        font-size: 11px;
        color: #64748b;
        margin-top: 3px;
    }

    .print-toast .toast-close {
        float: right;
        background: none;
        border: none;
        font-size: 16px;
        cursor: pointer;
        color: #94a3b8;
        padding: 0 4px;
    }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 991px) {
        .bill-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .mp-hero { padding: 12px; }
        .mp-hero h1 { font-size: 19px; }
        .mp-stat-card { min-height: 64px; padding: 9px 10px; }
        .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; }
        .mp-stat-value { font-size: 16px; }
        .bill-detail-grid { grid-template-columns: 1fr; }
        .print-toast { left: 20px; right: 20px; min-width: auto; }
    }
    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) { include __DIR__ . '/includes/common-toast.php'; } ?>
<form id="billListSecurityForm" class="d-none"><?= bill_list_csrf_field() ?></form>

<!-- Print Toast Notification -->
<div id="printToast" class="print-toast">
    <button class="toast-close" onclick="closePrintToast()">×</button>
    <div class="toast-title" id="printToastTitle">Printing...</div>
    <div class="toast-message" id="printToastMessage">Sending to printer service.</div>
</div>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>

        <section class="page-section master-page p-3 p-lg-3">
            <div class="mp-hero mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div>
                        <h1>Bill List</h1>
                        <p>View POS bills, payment status, customer details, bill items, payments and print/cancel actions.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="bill-create.php" class="btn brand-gradient">
                            <i data-lucide="plus-circle" style="width:14px;height:14px;"></i>
                            Create Bill
                        </a>
                        <button type="button" id="resetBillPage" class="btn btn-outline-primary">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="receipt-text"></i></div>
                        <div>
                            <div class="mp-stat-label">Total Bills</div>
                            <div class="mp-stat-value" id="totalBills">0</div>
                            <div class="mp-stat-sub">Current filter result</div>
                        </div>
                    </article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="indian-rupee"></i></div>
                        <div>
                            <div class="mp-stat-label">Net Sales</div>
                            <div class="mp-stat-value" id="totalNet">₹0.00</div>
                            <div class="mp-stat-sub">Active bill value</div>
                        </div>
                    </article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#e0f2fe;color:#0369a1;"><i data-lucide="wallet-cards"></i></div>
                        <div>
                            <div class="mp-stat-label">Collected</div>
                            <div class="mp-stat-value" id="totalPaid">₹0.00</div>
                            <div class="mp-stat-sub">Received amount</div>
                        </div>
                    </article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#fee2e2;color:#b91c1c;"><i data-lucide="circle-alert"></i></div>
                        <div>
                            <div class="mp-stat-label">Due / Cancelled</div>
                            <div class="mp-stat-value"><span id="totalDue">₹0.00</span></div>
                            <div class="mp-stat-sub"><span id="cancelledBills">0</span> cancelled bills</div>
                        </div>
                    </article>
                </div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div>
                                <h2 class="mp-card-title">POS Bill List</h2>
                                <p class="mp-card-sub">Role based branch visibility is applied automatically from assigned firm access.</p>
                            </div>

                            <form method="get" id="billFilterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                                <input type="text" name="search" id="search" class="form-control mp-filter-input" placeholder="Search bill no / order no / customer / mobile">
                                <select name="bill_status" id="bill_status" class="form-select mp-filter-select">
                                    <option value="">All Bills</option>
                                    <option value="active">Active</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="deleted">Deleted</option>
                                </select>
                                <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">Filter</button>
                            </form>
                        </div>

                        <div class="row g-2">
                            <div class="col-12 col-md-6 col-xl-3">
                                <select name="branch_id" id="branch_id" class="form-select mp-filter-select">
                                    <option value="">All Branch/Firm</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <select name="payment_status" id="payment_status" class="form-select mp-filter-select">
                                    <option value="">All Payments</option>
                                    <option value="paid">Paid</option>
                                    <option value="partial">Partial</option>
                                    <option value="pending">Pending</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <select name="payment_method_id" id="payment_method_id" class="form-select mp-filter-select">
                                    <option value="">All Methods</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <input type="date" name="date_from" id="date_from" class="form-control mp-filter-input">
                            </div>
                            <div class="col-12 col-md-6 col-xl-2">
                                <input type="date" name="date_to" id="date_to" class="form-control mp-filter-input">
                            </div>
                            <div class="col-12 col-md-6 col-xl-1 d-grid">
                                <button type="button" id="todayBills" class="btn btn-outline-secondary btn-sm rounded-pill fw-bold">Today</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table mb-0">
                        <thead>
                        <tr>
                            <th>Bill</th>
                            <th>Customer</th>
                            <th>Branch / User</th>
                            <th>Items</th>
                            <th>Net</th>
                            <th>Paid / Due</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th style="width: 180px;">Action</th>
                        </tr>
                        </thead>
                        <tbody id="billTableBody">
                        <tr><td colspan="9" class="text-center text-muted py-4">Loading bills...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="billMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading bills...</div>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 py-2 border-top">
                    <div class="mp-sub" id="paginationInfo">Page 1 of 1 · Total 0 bills</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="prevPage">Previous</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="nextPage">Next</button>
                    </div>
                </div>
            </section>
        </section>
    </main>
</div>

<div class="modal fade" id="billDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Bill Details</h5>
                    <div class="mp-sub" id="detailBillSub">Loading...</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="billDetailBody">
                <div class="text-center text-muted py-4">Loading bill details...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success bill-action-icon bill-action-print" id="detailPrintBtn">
                    <i data-lucide="printer-check"></i><span>Print</span>
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php if (file_exists(__DIR__ . '/includes/script.php')) { include __DIR__ . '/includes/script.php'; } ?>

<script>
(function () {
    'use strict';

    const apiUrl = 'api/bill-list-api.php';
    const printerUrl = 'http://127.0.0.1:17900/';
    const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });
    let currentPage = 1;
    let totalPages = 1;
    let searchTimer = null;
    let detailModal = null;
    let printingBillId = null;

    const tableBody = document.getElementById('billTableBody');
    const mobileCards = document.getElementById('billMobileCards');
    const filterForm = document.getElementById('billFilterForm');
    const detailModalEl = document.getElementById('billDetailModal');
    const detailBody = document.getElementById('billDetailBody');
    const detailBillSub = document.getElementById('detailBillSub');
    const detailPrintBtn = document.getElementById('detailPrintBtn');
    const printToast = document.getElementById('printToast');

    function csrfToken() {
        const input = document.querySelector('#billListSecurityForm input[name="csrf_token"], #billListSecurityForm input[name="_token"], #billListSecurityForm input[type="hidden"]');
        return input ? input.value : '';
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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
        if (window.Swal) {
            window.Swal.fire(type === 'error' ? 'Error' : 'Success', message, type === 'error' ? 'error' : 'success');
            return;
        }
        alert(message);
    }

    // ============================================
    // PRINT TOAST NOTIFICATIONS
    // ============================================
    function showPrintToast(title, message, type) {
        const toast = printToast;
        if (!toast) return;
        document.getElementById('printToastTitle').textContent = title;
        document.getElementById('printToastMessage').textContent = message;
        toast.className = 'print-toast show ' + (type || 'success');
        clearTimeout(toast._hideTimer);
        toast._hideTimer = setTimeout(function() {
            toast.classList.remove('show');
        }, 5000);
    }

    function closePrintToast() {
        const toast = printToast;
        if (toast) {
            toast.classList.remove('show');
            clearTimeout(toast._hideTimer);
        }
    }

    window.closePrintToast = closePrintToast;

    // ============================================
    // PRINTER SERVICE FUNCTIONS
    // ============================================
    async function printBillViaService(billData, printType) {
        printType = printType || 'CREATE_BILL';

        // Show printing toast
        showPrintToast('🖨️ Printing...', 'Sending bill ' + billData.BillNo + ' to thermal printer.', 'success');

        try {
            const response = await fetch(printerUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(billData)
            });

            const result = await response.text();
            console.log('Printer response:', result);

            if (result === 'PRINT_SUCCESS') {
                showPrintToast('✅ Print Successful', 'Bill ' + billData.BillNo + ' sent to printer.', 'success');
                return true;
            } else {
                showPrintToast('❌ Print Failed', result || 'Unknown error from printer service.', 'error');
                return false;
            }
        } catch (error) {
            console.error('Print error:', error);
            showPrintToast('❌ Print Error', 'Cannot connect to printer service. Please ensure the service is running.', 'error');
            return false;
        }
    }

    // ============================================
    // BUILD BILL DATA FOR PRINTER SERVICE
    // ============================================
    function buildBillDataForPrinter(bill, items, payments, printType) {
        printType = printType || 'CREATE_BILL';

        // Calculate totals
        let mrpTotal = 0;
        let totalQty = 0;
        const billItems = (items || []).map(function(item) {
            const qty = toNumber(item.qty);
            const rate = toNumber(item.selling_rate || item.mrp_rate || 0);
            const amount = qty * rate;
            mrpTotal += amount;
            totalQty += qty;
            return {
                Name: (item.article_no || '') + ' ' + (item.article_name || ''),
                Description: 'Size: ' + (item.size || '-') + ' | Color: ' + (item.color || '-'),
                Qty: qty,
                Rate: rate,
                Amount: amount
            };
        });

        // Get payment summary
        let paymentMethod = 'Cash';
        let paidAmount = toNumber(bill.paid_amount);
        let grandTotal = toNumber(bill.net_amount);

        if (payments && payments.length > 0) {
            const firstPayment = payments[0];
            paymentMethod = firstPayment.payment_method_name || 'Cash';
            paidAmount = toNumber(firstPayment.paid_amount);
        } else if (paidAmount <= 0 && bill.payment_status === 'paid') {
            paidAmount = grandTotal;
        }

        // Determine print type based on return/exchange
        let finalPrintType = printType;
        if (bill.return_exchange_id && bill.transaction_type) {
            finalPrintType = bill.transaction_type.toUpperCase() === 'RETURN' ? 'RETURN' : 'EXCHANGE';
        }

        // Build the bill data structure expected by C# service
        return {
            ShopName: 'GK FOOTWEAR',
            Address: 'Gandhi Nagar, Krishnagiri.',
            InvoiceTitle: bill.invoice_title || 'Bill of Supply',
            Phone: '',
            GSTIN: bill.gstin || '',

            BillNo: bill.bill_no || 'BILL-' + bill.bill_id,
            Date: bill.bill_date || new Date().toISOString().slice(0, 10),
            Time: bill.bill_time || new Date().toTimeString().slice(0, 8),

            Customer: bill.customer_name || 'Walk-in Customer',
            Branch: bill.branch_name || '',
            Salesman: bill.created_by_name || '',
            CollectedBy: payments && payments.length > 0 ? payments[0].cashier_name || '' : '',
            OrderNo: bill.order_no || '',

            PaymentStatus: bill.payment_status || 'PENDING',
            PaymentMethod: paymentMethod,
            TransactionType: finalPrintType === 'RETURN' ? 'Return' : (finalPrintType === 'EXCHANGE' ? 'Exchange' : 'Sale'),

            TotalQty: totalQty,
            GrandTotal: grandTotal,
            Paid: paidAmount,
            Balance: Math.max(0, grandTotal - paidAmount),

            Barcode: bill.barcode_value || 'BILL-' + bill.bill_id,

            PrintType: finalPrintType,

            Items: billItems
        };
    }

    // ============================================
    // FETCH BILL DATA AND PRINT
    // ============================================
    async function fetchAndPrintBill(billId, printType) {
        printType = printType || 'CREATE_BILL';

        // Disable the print button
        const printBtn = document.querySelector('.js-print[data-id="' + billId + '"]');
        if (printBtn) {
            printBtn.classList.add('bill-action-print-active');
            printBtn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width:12px;height:12px;"></span> Printing...';
        }

        try {
            const data = await apiGet({ action: 'get', bill_id: billId });
            if (!data.success) {
                showPrintToast('❌ Error', data.message || 'Unable to fetch bill data.', 'error');
                return false;
            }

            const bill = data.bill || {};
            const items = data.items || [];
            const payments = data.payments || [];

            // Build bill data for printer
            const billData = buildBillDataForPrinter(bill, items, payments, printType);

            // Send to printer service
            const result = await printBillViaService(billData, printType);

            return result;
        } catch (error) {
            console.error('Fetch/Print error:', error);
            showPrintToast('❌ Error', 'Unable to fetch bill data for printing.', 'error');
            return false;
        } finally {
            // Re-enable the print button
            if (printBtn) {
                printBtn.classList.remove('bill-action-print-active');
                printBtn.innerHTML = '<i data-lucide="printer-check"></i><span>Print</span>';
                if (window.lucide) window.lucide.createIcons();
            }
        }
    }

    // ============================================
    // API FUNCTIONS
    // ============================================
    function buildQuery(params) {
        const query = new URLSearchParams();
        Object.keys(params || {}).forEach(function (key) {
            if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                query.append(key, params[key]);
            }
        });
        return query.toString();
    }

    async function apiGet(params) {
        const response = await fetch(apiUrl + '?' + buildQuery(params), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        return await response.json();
    }

    async function apiPost(payload) {
        const form = new FormData();
        Object.keys(payload || {}).forEach(function (key) {
            form.append(key, payload[key]);
        });
        form.append('csrf_token', csrfToken());
        const response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: form
        });
        return await response.json();
    }

    // ============================================
    // UI RENDER FUNCTIONS
    // ============================================
    function fillSelect(id, rows, valueKey, labelCallback, placeholder) {
        const select = document.getElementById(id);
        if (!select) return;
        const first = '<option value="">' + escapeHtml(placeholder || 'All') + '</option>';
        select.innerHTML = first + (rows || []).map(function (row) {
            return '<option value="' + escapeHtml(row[valueKey]) + '">' + escapeHtml(labelCallback(row)) + '</option>';
        }).join('');
    }

    function statusBadge(status, type) {
        const value = String(status || '-').toLowerCase();
        let cls = 'status-pending';
        if (value === 'active') cls = 'status-active';
        if (value === 'paid') cls = 'status-paid';
        if (value === 'partial') cls = 'status-partial';
        if (value === 'pending' || value === 'unpaid') cls = 'status-pending';
        if (value === 'cancelled') cls = 'status-cancelled';
        if (value === 'deleted') cls = 'status-deleted';
        return '<span class="mp-badge ' + cls + '">' + escapeHtml(type ? type + ': ' : '') + escapeHtml(value.charAt(0).toUpperCase() + value.slice(1)) + '</span>';
    }

    function renderStats(stats) {
        document.getElementById('totalBills').textContent = parseInt(stats.total_bills || 0, 10);
        document.getElementById('totalNet').textContent = money.format(toNumber(stats.total_net_amount));
        document.getElementById('totalPaid').textContent = money.format(toNumber(stats.total_paid_amount));
        document.getElementById('totalDue').textContent = money.format(toNumber(stats.total_balance_amount));
        document.getElementById('cancelledBills').textContent = parseInt(stats.cancelled_bills || 0, 10);
    }

    function billAvatar(bill) {
        const base = (bill.customer_name || bill.bill_no || 'B').substring(0, 1).toUpperCase();
        return '<div class="mp-avatar">' + escapeHtml(base) + '</div>';
    }

    function paymentSummary(bill) {
        const summary = bill.payment_summary || '-';
        return '<span class="mp-badge badge-type" title="' + escapeHtml(summary) + '">' + escapeHtml(summary) + '</span>';
    }

    // ============================================
    // ACTION BUTTONS WITH PRINT FUNCTION
    // ============================================
    function actionButtons(bill) {
        const billId = parseInt(bill.bill_id || 0, 10);
        const printType = 'CREATE_BILL';

        let html = '<div class="bill-action-group">';
        html += '<button type="button" class="bill-action-icon bill-action-view js-view" data-id="' + billId + '" title="View Bill">' +
                '<i data-lucide="scan-eye"></i><span>View</span></button>';

        // NEW: Print button with direct printer service call
        html += '<button type="button" class="bill-action-icon bill-action-print js-print" data-id="' + billId + '" data-print-type="' + printType + '" title="Print Bill">' +
                '<i data-lucide="printer-check"></i><span>Print</span></button>';

        if (String(bill.bill_status || '').toLowerCase() === 'active') {
            html += '<button type="button" class="bill-action-icon bill-action-cancel js-cancel" data-id="' + billId + '" title="Cancel Bill">' +
                    '<i data-lucide="circle-x"></i><span>Cancel</span></button>';
        }

        html += '</div>';
        return html;
    }

    function renderBills(bills) {
        if (!bills || !bills.length) {
            tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">No bills found.</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No bills found.</div>';
            return;
        }

        tableBody.innerHTML = bills.map(function (bill) {
            const billId = parseInt(bill.bill_id || 0, 10);
            const dateTime = (bill.bill_date || '-') + (bill.bill_time ? ' · ' + bill.bill_time : '');
            const branch = (bill.branch_name || '-') + (bill.floor_name ? ' / ' + bill.floor_name : '');
            const itemText = parseInt(bill.item_count || 0, 10) + ' items · Qty ' + toNumber(bill.total_qty).toFixed(2);
            return '<tr>' +
                '<td><div class="d-flex align-items-center gap-2">' + billAvatar(bill) + '<div><div class="mp-title">' + escapeHtml(bill.bill_no || '-') + '</div><div class="mp-sub">' + escapeHtml(dateTime) + '</div><span class="mp-badge badge-code mt-1">' + escapeHtml(bill.order_no || ('#' + billId)) + '</span></div></div></td>' +
                '<td><div class="mp-title">' + escapeHtml(bill.customer_name || 'Walk-in Customer') + '</div><div class="mp-sub">' + escapeHtml(bill.customer_mobile || 'No mobile') + '</div></td>' +
                '<td><span class="mp-badge badge-branch">' + escapeHtml(branch) + '</span><div class="mp-sub mt-1">' + escapeHtml(bill.created_by_name || '-') + '</div></td>' +
                '<td><span class="mp-badge badge-count">' + escapeHtml(itemText) + '</span><div class="mp-sub mt-1" title="' + escapeHtml(bill.article_summary || '') + '">' + escapeHtml(bill.article_summary || '-') + '</div></td>' +
                '<td><strong>' + money.format(toNumber(bill.net_amount)) + '</strong><div class="mp-sub">Savings ' + money.format(toNumber(bill.today_savings_amount)) + '</div></td>' +
                '<td><span class="amount-positive">' + money.format(toNumber(bill.paid_amount)) + '</span><div class="mp-sub amount-due">Due ' + money.format(toNumber(bill.balance_amount)) + '</div></td>' +
                '<td>' + paymentSummary(bill) + '</td>' +
                '<td><div class="d-grid gap-1">' + statusBadge(bill.bill_status, 'Bill') + statusBadge(bill.payment_status, 'Pay') + '</div></td>' +
                '<td>' + actionButtons(bill) + '</td>' +
            '</tr>';
        }).join('');

        mobileCards.innerHTML = bills.map(function (bill) {
            const billId = parseInt(bill.bill_id || 0, 10);
            const dateTime = (bill.bill_date || '-') + (bill.bill_time ? ' · ' + bill.bill_time : '');
            const branch = (bill.branch_name || '-') + (bill.floor_name ? ' / ' + bill.floor_name : '');
            return '<div class="mp-mobile-card">' +
                '<div class="d-flex gap-2 align-items-start">' + billAvatar(bill) +
                    '<div class="flex-grow-1 min-width-0">' +
                        '<div class="d-flex justify-content-between gap-2">' +
                            '<div><div class="mp-title">' + escapeHtml(bill.bill_no || '-') + '</div><div class="mp-sub">' + escapeHtml(dateTime) + '</div></div>' +
                            '<div class="text-end"><strong>' + money.format(toNumber(bill.net_amount)) + '</strong><div class="mp-sub amount-due">Due ' + money.format(toNumber(bill.balance_amount)) + '</div></div>' +
                        '</div>' +
                        '<div class="mt-2 mp-title">' + escapeHtml(bill.customer_name || 'Walk-in Customer') + '</div>' +
                        '<div class="mp-sub">' + escapeHtml(bill.customer_mobile || 'No mobile') + ' · ' + escapeHtml(branch) + '</div>' +
                        '<div class="d-flex flex-wrap gap-1 mt-2">' + statusBadge(bill.bill_status, 'Bill') + statusBadge(bill.payment_status, 'Pay') + '<span class="mp-badge badge-count">' + parseInt(bill.item_count || 0, 10) + ' items</span></div>' +
                        '<div class="d-flex flex-wrap gap-1 mt-2">' + actionButtons(bill) + '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    }

    // ============================================
    // FILTER AND LOAD FUNCTIONS
    // ============================================
    function filterParams() {
        return {
            action: 'list',
            search: document.getElementById('search').value,
            bill_status: document.getElementById('bill_status').value,
            payment_status: document.getElementById('payment_status').value,
            payment_method_id: document.getElementById('payment_method_id').value,
            branch_id: document.getElementById('branch_id').value,
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
        document.getElementById('paginationInfo').textContent = 'Page ' + currentPage + ' of ' + totalPages + ' · Total ' + total + ' bills';
        document.getElementById('prevPage').disabled = currentPage <= 1;
        document.getElementById('nextPage').disabled = currentPage >= totalPages;
    }

    async function loadBills() {
        tableBody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading bills...</td></tr>';
        mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">Loading bills...</div>';
        try {
            const data = await apiGet(filterParams());
            if (!data.success) {
                showMessage('error', data.message || 'Unable to load bill list.');
                return;
            }
            renderStats(data.stats || {});
            renderBills((data.bills && data.bills.items) ? data.bills.items : []);
            setPagination((data.bills && data.bills.pagination) ? data.bills.pagination : {});
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showMessage('error', 'Unable to connect to bill list API.');
        }
    }

    async function loadInit() {
        try {
            const data = await apiGet({ action: 'init' });
            if (!data.success) {
                showMessage('error', data.message || 'Unable to initialize bill list.');
                return;
            }
            fillSelect('branch_id', data.branches || [], 'branch_id', function (row) {
                return (row.branch_name || '-') + (row.floor_name ? ' / ' + row.floor_name : '');
            }, 'All Branch/Firm');
            fillSelect('payment_method_id', data.payment_methods || [], 'payment_method_id', function (row) {
                return row.payment_method_name || '-';
            }, 'All Methods');
            renderStats(data.stats || {});
            renderBills((data.bills && data.bills.items) ? data.bills.items : []);
            setPagination((data.bills && data.bills.pagination) ? data.bills.pagination : {});
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showMessage('error', 'Unable to initialize bill list page.');
        }
    }

    // ============================================
    // MODAL FUNCTIONS
    // ============================================
    function getDetailModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!detailModal) detailModal = new window.bootstrap.Modal(detailModalEl);
            return detailModal;
        }
        return null;
    }

    function openDetailModal() {
        const modal = getDetailModal();
        if (modal) {
            modal.show();
            return;
        }
        detailModalEl.classList.add('show');
        detailModalEl.style.display = 'block';
        detailModalEl.removeAttribute('aria-hidden');
        detailModalEl.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open');
        if (!document.getElementById('billDetailModalBackdrop')) {
            const backdrop = document.createElement('div');
            backdrop.id = 'billDetailModalBackdrop';
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    }

    function closeDetailModalFallback() {
        if (window.bootstrap && window.bootstrap.Modal) {
            return;
        }
        detailModalEl.classList.remove('show');
        detailModalEl.style.display = 'none';
        detailModalEl.setAttribute('aria-hidden', 'true');
        detailModalEl.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open');
        const backdrop = document.getElementById('billDetailModalBackdrop');
        if (backdrop) backdrop.remove();
    }

    detailModalEl.addEventListener('click', function (event) {
        if (event.target === detailModalEl || event.target.closest('[data-bs-dismiss="modal"]')) {
            closeDetailModalFallback();
        }
    });

    function detailBox(label, value) {
        return '<div class="bill-detail-box"><div class="mp-stat-label">' + escapeHtml(label) + '</div><div class="mp-title mt-1">' + value + '</div></div>';
    }

    function renderItems(items) {
        if (!items || !items.length) return '<div class="text-muted small">No bill items found.</div>';
        return '<div class="table-responsive mt-3"><table class="table mp-table mb-0"><thead><tr><th>Article</th><th>Brand</th><th>Size / Color</th><th>Qty</th><th>MRP</th><th>Discount</th><th>Selling</th><th>Amount</th></tr></thead><tbody>' +
            items.map(function (item) {
                return '<tr>' +
                    '<td><div class="mp-title">' + escapeHtml(item.article_no || '-') + '</div><div class="mp-sub">' + escapeHtml(item.article_name || '-') + '</div></td>' +
                    '<td>' + escapeHtml(item.brand_name || '-') + '</td>' +
                    '<td>' + escapeHtml(item.size || '-') + '<div class="mp-sub">' + escapeHtml(item.color || '-') + '</div></td>' +
                    '<td>' + toNumber(item.qty).toFixed(2) + '</td>' +
                    '<td>' + money.format(toNumber(item.mrp_rate)) + '</td>' +
                    '<td>' + money.format(toNumber(item.discount_amount)) + '<div class="mp-sub">' + escapeHtml(item.discount_type || 'none') + ' ' + toNumber(item.discount_value).toFixed(2) + '</div></td>' +
                    '<td>' + money.format(toNumber(item.selling_rate)) + '</td>' +
                    '<td><strong>' + money.format(toNumber(item.amount)) + '</strong></td>' +
                '</tr>';
            }).join('') + '</tbody></table></div>';
    }

    function renderPayments(payments) {
        if (!payments || !payments.length) return '<div class="text-muted small">No payment entries found.</div>';
        return '<div class="table-responsive mt-3"><table class="table mp-table mb-0"><thead><tr><th>Method</th><th>Amount</th><th>Reference</th><th>Status</th><th>Collected By</th><th>Date</th></tr></thead><tbody>' +
            payments.map(function (p) {
                return '<tr>' +
                    '<td>' + escapeHtml(p.payment_method_name || '-') + '<div class="mp-sub">' + escapeHtml(p.method_type || '-') + '</div></td>' +
                    '<td><strong>' + money.format(toNumber(p.paid_amount)) + '</strong></td>' +
                    '<td>' + escapeHtml(p.reference_no || '-') + '</td>' +
                    '<td>' + statusBadge(p.payment_status || '-', '') + '</td>' +
                    '<td>' + escapeHtml(p.cashier_name || '-') + '</td>' +
                    '<td>' + escapeHtml(p.collected_at || '-') + '</td>' +
                '</tr>';
            }).join('') + '</tbody></table></div>';
    }

    // ============================================
    // VIEW BILL DETAILS
    // ============================================
    async function viewBill(billId) {
        detailBody.innerHTML = '<div class="text-center text-muted py-4">Loading bill details...</div>';
        detailBillSub.textContent = 'Bill #' + billId;

        // Update print button to use printer service
        detailPrintBtn.onclick = function() {
            fetchAndPrintBill(billId, 'CREATE_BILL');
        };

        openDetailModal();

        try {
            const data = await apiGet({ action: 'get', bill_id: billId });
            if (!data.success) {
                detailBody.innerHTML = '<div class="text-danger">' + escapeHtml(data.message || 'Unable to load bill details.') + '</div>';
                return;
            }

            const bill = data.bill || {};
            detailBillSub.textContent = (bill.bill_no || ('Bill #' + billId)) + ' · ' + (bill.bill_date || '-') + (bill.bill_time ? ' · ' + bill.bill_time : '');

            const branch = (bill.branch_name || '-') + (bill.floor_name ? ' / ' + bill.floor_name : '');

            // Store bill ID for print button
            detailPrintBtn.dataset.billId = billId;

            detailBody.innerHTML =
                '<div class="bill-detail-grid">' +
                    detailBox('Bill No', escapeHtml(bill.bill_no || '-') + '<div class="mp-sub">' + escapeHtml(bill.order_no || '-') + '</div>') +
                    detailBox('Customer', escapeHtml(bill.customer_name || 'Walk-in Customer') + '<div class="mp-sub">' + escapeHtml(bill.customer_mobile || 'No mobile') + '</div>') +
                    detailBox('Branch / User', escapeHtml(branch) + '<div class="mp-sub">' + escapeHtml(bill.created_by_name || '-') + '</div>') +
                    detailBox('Barcode', escapeHtml(bill.barcode_value || 'No barcode')) +
                    detailBox('MRP / Savings', money.format(toNumber(bill.mrp_total)) + '<div class="mp-sub">Savings ' + money.format(toNumber(bill.today_savings_amount)) + '</div>') +
                    detailBox('Net Amount', money.format(toNumber(bill.net_amount))) +
                    detailBox('Paid Amount', '<span class="amount-positive">' + money.format(toNumber(bill.paid_amount)) + '</span>') +
                    detailBox('Due Amount', '<span class="amount-due">' + money.format(toNumber(bill.balance_amount)) + '</span>') +
                '</div>' +
                '<div class="d-flex flex-wrap gap-2 mt-3">' + statusBadge(bill.bill_status, 'Bill') + statusBadge(bill.payment_status, 'Payment') + '<span class="mp-badge badge-code">Prints ' + parseInt(bill.print_count || 0, 10) + '</span></div>' +
                '<h6 class="fw-bold mt-4 mb-2">Bill Items</h6>' + renderItems(data.items || []) +
                '<h6 class="fw-bold mt-4 mb-2">Payments</h6>' + renderPayments(data.payments || []);

            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            detailBody.innerHTML = '<div class="text-danger">Unable to fetch bill details.</div>';
        }
    }

    window.billListViewBill = viewBill;

    // ============================================
    // CANCEL BILL
    // ============================================
    async function cancelBill(billId) {
        const reason = window.prompt('Reason for cancelling this bill?', 'Customer cancelled / Wrong bill');
        if (reason === null) return;
        if (!window.confirm('Cancel this bill and restore stock?')) return;
        try {
            const data = await apiPost({ action: 'cancel_bill', bill_id: billId, reason: reason });
            if (!data.success) {
                showMessage('error', data.message || 'Unable to cancel bill.');
                return;
            }
            showMessage('success', data.message || 'Bill cancelled successfully.');
            loadBills();
        } catch (error) {
            showMessage('error', 'Unable to connect to bill list API.');
        }
    }

    // ============================================
    // EVENT LISTENERS
    // ============================================
    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        currentPage = 1;
        loadBills();
    });

    ['bill_status','payment_status','payment_method_id','branch_id','date_from','date_to'].forEach(function (id) {
        const input = document.getElementById(id);
        if (input) input.addEventListener('change', function () { currentPage = 1; loadBills(); });
    });

    document.getElementById('search').addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(function () { currentPage = 1; loadBills(); }, 300);
    });

    document.getElementById('resetBillPage').addEventListener('click', function () {
        ['search','bill_status','payment_status','payment_method_id','branch_id','date_from','date_to'].forEach(function (id) {
            const input = document.getElementById(id);
            if (input) input.value = '';
        });
        currentPage = 1;
        loadBills();
    });

    document.getElementById('todayBills').addEventListener('click', function () {
        const today = new Date().toISOString().slice(0, 10);
        document.getElementById('date_from').value = today;
        document.getElementById('date_to').value = today;
        currentPage = 1;
        loadBills();
    });

    document.getElementById('prevPage').addEventListener('click', function () {
        if (currentPage > 1) { currentPage--; loadBills(); }
    });

    document.getElementById('nextPage').addEventListener('click', function () {
        if (currentPage < totalPages) { currentPage++; loadBills(); }
    });

    // ============================================
    // EVENT DELEGATION FOR DYNAMIC BUTTONS
    // ============================================
    document.addEventListener('click', function (event) {
        const viewBtn = event.target.closest('.js-view');
        const cancelBtn = event.target.closest('.js-cancel');
        const printBtn = event.target.closest('.js-print');

        if (viewBtn) {
            event.preventDefault();
            viewBill(viewBtn.dataset.id);
        }

        if (cancelBtn) {
            event.preventDefault();
            cancelBill(cancelBtn.dataset.id);
        }

        // NEW: Handle print button click
        if (printBtn) {
            event.preventDefault();
            const billId = parseInt(printBtn.dataset.id, 10);
            const printType = printBtn.dataset.printType || 'CREATE_BILL';
            if (billId > 0) {
                fetchAndPrintBill(billId, printType);
            } else {
                showPrintToast('❌ Error', 'Invalid bill ID for printing.', 'error');
            }
        }
    });

    // ============================================
    // TOAST AUTO-CLOSE ON CLICK OUTSIDE
    // ============================================
    document.addEventListener('click', function(event) {
        const toast = printToast;
        if (toast && toast.classList.contains('show')) {
            if (!toast.contains(event.target)) {
                // Don't auto-close, user must click close button
            }
        }
    });

    // ============================================
    // KEYBOARD SHORTCUTS
    // ============================================
    document.addEventListener('keydown', function(event) {
        // Ctrl+F to focus search
        if (event.ctrlKey && event.key === 'f') {
            event.preventDefault();
            document.getElementById('search').focus();
        }
        // Escape to close toast
        if (event.key === 'Escape') {
            closePrintToast();
        }
    });

    // ============================================
    // INITIALIZE
    // ============================================
    loadInit();

})();
</script>
</body>
</html>