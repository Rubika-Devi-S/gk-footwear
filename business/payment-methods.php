<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
if (function_exists('require_page_access')) {
    require_page_access($conn, 'payment-methods.php');
}

$pageTitle = 'Payment Methods';
$businessId = function_exists('current_business_id') ? (int) current_business_id() : (int)($_SESSION['business_id'] ?? 0);

function payment_method_csrf_field(): string
{
    if (function_exists('csrf_field')) {
        return csrf_field();
    }

    if (function_exists('csrf_token')) {
        return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
    }

    return '';
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
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-soft, #dbe4f0);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 14px 16px;
    }
    .mp-hero h1 {
        font-size: 20px;
        font-weight: 800;
        margin: 0 0 3px;
        letter-spacing: -.02em;
        color: var(--text-main, #0f172a);
    }
    .mp-hero p {
        font-size: 11px;
        line-height: 1.35;
        margin: 0;
        color: var(--text-muted, #64748b);
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
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-soft, #dbe4f0);
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
        color: var(--text-muted, #64748b);
        font-weight: 700;
        line-height: 1.15;
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .mp-stat-value {
        font-size: 18px;
        color: var(--text-main, #0f172a);
        font-weight: 800;
        margin: 1px 0;
        line-height: 1.05;
    }
    .mp-stat-sub,
    .mp-sub {
        font-size: 10px;
        color: var(--text-muted, #64748b);
        line-height: 1.25;
    }
    .mp-card {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-soft, #dbe4f0);
        border-radius: 16px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        overflow: hidden;
    }
    .mp-card-head {
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-soft, #dbe4f0);
    }
    .mp-card-title {
        font-size: 15px;
        font-weight: 800;
        color: var(--text-main, #0f172a);
        margin: 0 0 2px;
    }
    .mp-card-sub {
        font-size: 11px;
        color: var(--text-muted, #64748b);
        margin: 0;
    }
    .mp-filter-input,
    .mp-filter-select {
        min-height: 32px;
        font-size: 11px;
        border-radius: 999px;
        padding: 5px 10px;
        font-weight: 650;
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
    .payment-list-card .table-responsive { width: 100%; max-width: 100%; overflow-x: hidden; }
    .payment-table { width: 100%; min-width: 0 !important; table-layout: fixed; }
    .payment-table th,
    .payment-table td { white-space: normal; word-break: break-word; overflow-wrap: anywhere; padding: 8px 7px; }
    .payment-table th { font-size: 9.5px; line-height: 1.15; }
    .payment-table td { font-size: 10.5px; }
    .mp-avatar {
        width: 34px;
        height: 34px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, var(--brand-1, #2563eb), var(--brand-2, #7c3aed));
        color: #fff;
        font-size: 13px;
        font-weight: 800;
        flex: 0 0 auto;
    }
    .mp-title {
        font-size: 12px;
        font-weight: 800;
        color: var(--text-main, #0f172a);
        line-height: 1.2;
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
    .status-active { background:#dcfce7; color:#15803d; }
    .status-inactive { background:#fee2e2; color:#b91c1c; }
    .badge-cash { background:#dcfce7; color:#15803d; }
    .badge-upi { background:#dbeafe; color:#1d4ed8; }
    .badge-card { background:#ecfeff; color:#0e7490; }
    .badge-cheque { background:#fef3c7; color:#b45309; }
    .badge-credit { background:#fee2e2; color:#b91c1c; }
    .badge-split { background:#ede9fe; color:#6d28d9; }
    .badge-count { background:#fef3c7; color:#b45309; }
    .payment-method-box {
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 0;
    }
    .payment-method-box .mp-title,
    .payment-method-box .mp-sub {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .payment-action-wrap {
        display: grid;
        grid-template-columns: repeat(3, 32px);
        gap: 6px;
        justify-content: end;
        align-items: center;
        min-width: 108px;
    }
    .payment-action-btn {
        width: 32px;
        height: 32px;
        border-radius: 11px;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        cursor: pointer;
        transition: all .18s ease;
        box-shadow: 0 4px 10px rgba(15, 23, 42, .05);
        background: #f8fafc;
    }
    .payment-action-btn svg {
        width: 15px;
        height: 15px;
        stroke-width: 2.4;
    }
    .payment-action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, .12);
    }
    .payment-action-btn.action-edit { background:#eef2ff; color:#4f46e5; border-color:#c7d2fe; }
    .payment-action-btn.action-edit:hover { background:#e0e7ff; }
    .payment-action-btn.action-toggle-active { background:#fff7ed; color:#ea580c; border-color:#fed7aa; }
    .payment-action-btn.action-toggle-active:hover { background:#ffedd5; }
    .payment-action-btn.action-toggle-inactive { background:#ecfdf5; color:#16a34a; border-color:#bbf7d0; }
    .payment-action-btn.action-toggle-inactive:hover { background:#dcfce7; }
    .payment-action-btn.action-delete { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
    .payment-action-btn.action-delete:hover { background:#fee2e2; }
    .payment-action-text { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    .mp-mobile-card {
        background: var(--card-bg, #ffffff);
        border: 1px solid var(--border-soft, #dbe4f0);
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 10px;
    }
    .modal-title { font-size: 15px; font-weight: 750; }
    .modal .form-label { font-size: 11px; font-weight: 700; margin-bottom: 4px; }
    .modal .form-control,
    .modal .form-select { min-height: 34px; font-size: 12px; border-radius: 12px; padding: 6px 10px; }
    .modal-footer .btn { font-size: 12px; padding: 7px 12px; border-radius: 12px; font-weight: 700; }
    .live-note {
        border: 1px dashed #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 14px;
        padding: 9px 11px;
        font-size: 11px;
        font-weight: 700;
    }
    @media (max-width: 767px) {
        .mp-hero { padding: 12px; }
        .mp-hero h1 { font-size: 19px; }
        .mp-stat-card { min-height: 64px; padding: 9px 10px; }
        .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; }
        .mp-stat-value { font-size: 16px; }
        .payment-action-wrap { justify-content: start; }
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
                        <h1>Payment Methods</h1>
                        <p>Customer-style payment method master for Cash, UPI, Card, Cheque, Credit and Split Payment.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="openPaymentMethodModalBtn" class="btn brand-gradient">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Method
                        </button>

                        <button type="button" id="seedPaymentMethodsBtn" class="btn btn-outline-primary">
                            <i data-lucide="list-plus" style="width:14px;height:14px;"></i> Seed Defaults
                        </button>

                        <button type="button" id="resetPaymentMethodPage" class="btn btn-outline-secondary">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="credit-card"></i></div>
                        <div><div class="mp-stat-label">Total Methods</div><div class="mp-stat-value" id="totalMethods">0</div><div class="mp-stat-sub">Payment master</div></div>
                    </article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="check-circle"></i></div>
                        <div><div class="mp-stat-label">Active Methods</div><div class="mp-stat-value" id="activeMethods">0</div><div class="mp-stat-sub">Currently enabled</div></div>
                    </article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="pause-circle"></i></div>
                        <div><div class="mp-stat-label">Inactive Methods</div><div class="mp-stat-value" id="inactiveMethods">0</div><div class="mp-stat-sub">Currently disabled</div></div>
                    </article>
                </div>
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="receipt"></i></div>
                        <div><div class="mp-stat-label">Used Methods</div><div class="mp-stat-value" id="usedMethods">0</div><div class="mp-stat-sub">Bills / ledgers</div></div>
                    </article>
                </div>
            </div>

            <section class="mp-card payment-list-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                            <div>
                                <h2 class="mp-card-title">Payment Method List</h2>
                                <p class="mp-card-sub">Search, filter, edit, activate/deactivate or delete payment methods.</p>
                            </div>

                            <form method="get" id="paymentMethodFilterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                                <input type="text" name="search" id="search" class="form-control mp-filter-input" placeholder="Search method / type">
                                <select name="method_type_filter" id="method_type_filter" class="form-select mp-filter-select">
                                    <option value="">All Types</option>
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI</option>
                                    <option value="card">Card</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="credit">Credit</option>
                                    <option value="split">Split Payment</option>
                                </select>
                                <select name="status" id="status" class="form-select mp-filter-select">
                                    <option value="">All Status</option>
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                                <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">Filter</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table payment-table mb-0">
                        <colgroup>
                            <col style="width:24%;">
                            <col style="width:15%;">
                            <col style="width:15%;">
                            <col style="width:15%;">
                            <col style="width:16%;">
                            <col style="width:15%;">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>Payment Method</th>
                            <th>Type</th>
                            <th>Usage</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody id="paymentMethodTableBody">
                        <tr><td colspan="6" class="text-center text-muted py-4">Loading payment methods...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="paymentMethodMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading payment methods...</div>
                </div>
            </section>

            <div class="live-note mt-3">This page now follows the Customer module UI method: compact cards, master-style table, mobile cards and icon action buttons.</div>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<div class="modal fade" id="paymentMethodModal" tabindex="-1" aria-labelledby="paymentMethodFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">
        <form method="post" class="modal-content rounded-4" id="paymentMethodForm" autocomplete="off">
            <?= payment_method_csrf_field() ?>

            <input type="hidden" name="action" value="save_payment_method">
            <input type="hidden" name="payment_method_id" id="payment_method_id" value="0">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="paymentMethodFormTitle">Add Payment Method</h5>
                    <div class="mp-sub">Used in POS bills, cashier collections, split payments and payment ledger.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="paymentMethodModalCloseBtn"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Payment Method Name *</label>
                        <input type="text" name="payment_method_name" id="payment_method_name" class="form-control" required maxlength="100" placeholder="Example: Cash / PhonePe UPI / HDFC Card">
                        <div class="mp-sub mt-1">Only letters, numbers, spaces and simple symbols are allowed.</div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Method Type *</label>
                        <select name="method_type" id="method_type" class="form-select" required>
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="card">Card</option>
                            <option value="cheque">Cheque</option>
                            <option value="credit">Credit</option>
                            <option value="split">Split Payment</option>
                        </select>
                        <div class="mp-sub mt-1">Supported methods: Cash, UPI, Card, Cheque, Credit and Split Payment.</div>
                    </div>
                </div>

                <div class="live-note mt-3">Payment method master controls payment options shown in billing and pending collection screens.</div>
            </div>

            <div class="modal-footer">
                <button type="button" id="cancelEditBtn" class="btn btn-light d-none">Cancel Edit</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="paymentMethodCloseFooterBtn">Close</button>
                <button type="submit" id="paymentMethodSubmitBtn" class="btn brand-gradient">Save Method</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>

<script>
(function () {
    'use strict';

    const apiUrl = 'api/payment-methods-api.php';
    const paymentMethodForm = document.getElementById('paymentMethodForm');
    const filterForm = document.getElementById('paymentMethodFilterForm');
    const tableBody = document.getElementById('paymentMethodTableBody');
    const mobileCards = document.getElementById('paymentMethodMobileCards');
    const paymentMethodModalEl = document.getElementById('paymentMethodModal');
    const openPaymentMethodModalBtn = document.getElementById('openPaymentMethodModalBtn');
    let paymentMethodModalInstance = null;
    let searchTimer = null;

    const typeLabels = {
        cash: 'Cash',
        upi: 'UPI',
        card: 'Card',
        cheque: 'Cheque',
        credit: 'Credit',
        split: 'Split Payment'
    };

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
        if (window.showToast) {
            window.showToast(message, toastType === 'error' ? 'danger' : toastType);
            return;
        }
        alert(message);
    }

    function getPaymentMethodModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!paymentMethodModalInstance) {
                paymentMethodModalInstance = new window.bootstrap.Modal(paymentMethodModalEl, {
                    backdrop: 'static',
                    keyboard: false
                });
            }
            return paymentMethodModalInstance;
        }
        return null;
    }

    function openPaymentMethodModal() {
        const modal = getPaymentMethodModal();
        if (modal) {
            modal.show();
        } else {
            paymentMethodModalEl.classList.add('show');
            paymentMethodModalEl.style.display = 'block';
            paymentMethodModalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        setTimeout(function () {
            const firstInput = document.getElementById('payment_method_name');
            if (firstInput) {
                firstInput.focus();
            }
        }, 250);
    }

    function closePaymentMethodModal() {
        const modal = getPaymentMethodModal();
        if (modal) {
            modal.hide();
        } else {
            paymentMethodModalEl.classList.remove('show');
            paymentMethodModalEl.style.display = 'none';
            paymentMethodModalEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }
    }

    function csrfAppend(formData) {
        const csrfInput = paymentMethodForm.querySelector('input[type="hidden"][name*="csrf"], input[type="hidden"][name="_token"]');
        if (csrfInput && !formData.has(csrfInput.name)) {
            formData.append(csrfInput.name, csrfInput.value);
        }
    }

    function statusBadge(status) {
        return parseInt(status, 10) === 1
            ? '<span class="mp-badge status-active">Active</span>'
            : '<span class="mp-badge status-inactive">Inactive</span>';
    }

    function typeBadge(methodType) {
        const label = typeLabels[methodType] || methodType || '-';
        const cls = 'badge-' + escapeHtml(methodType || 'cash');
        return '<span class="mp-badge ' + cls + '">' + escapeHtml(label) + '</span>';
    }

    function paymentIcon(methodType) {
        const iconMap = {
            cash: '₹',
            upi: 'U',
            card: 'C',
            cheque: 'Q',
            credit: 'Cr',
            split: 'S'
        };

        return escapeHtml(iconMap[methodType] || 'P');
    }

    function methodActionButtons(method) {
        const id = parseInt(method.payment_method_id || 0, 10);
        const isActive = parseInt(method.status, 10) === 1;
        const toggleText = isActive ? 'Deactivate' : 'Activate';
        const toggleClass = isActive ? 'action-toggle-active' : 'action-toggle-inactive';
        const toggleIcon = isActive ? 'pause-circle' : 'play-circle';

        return '<div class="payment-action-wrap">' +
            '<button type="button" class="payment-action-btn action-edit js-edit" data-id="' + id + '" title="Edit"><i data-lucide="pencil"></i><span class="payment-action-text">Edit</span></button>' +
            '<button type="button" class="payment-action-btn ' + toggleClass + ' js-toggle" data-id="' + id + '" title="' + toggleText + '"><i data-lucide="' + toggleIcon + '"></i><span class="payment-action-text">' + toggleText + '</span></button>' +
            '<button type="button" class="payment-action-btn action-delete js-delete" data-id="' + id + '" title="Delete"><i data-lucide="trash-2"></i><span class="payment-action-text">Delete</span></button>' +
        '</div>';
    }

    function setButtonLoading(button, isLoading, textWhenReady) {
        if (!button) {
            return;
        }

        button.disabled = isLoading;
        button.innerHTML = isLoading ? 'Please wait...' : textWhenReady;
    }

    function formatDate(value) {
        if (!value) {
            return '-';
        }

        const date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString('en-IN');
    }

    function resetPaymentMethodForm() {
        paymentMethodForm.reset();
        document.getElementById('payment_method_id').value = '0';
        document.getElementById('method_type').value = 'cash';
        document.getElementById('paymentMethodFormTitle').textContent = 'Add Payment Method';
        document.getElementById('paymentMethodSubmitBtn').innerHTML = 'Save Method';
        document.getElementById('cancelEditBtn').classList.add('d-none');
    }

    function renderStats(stats) {
        document.getElementById('totalMethods').textContent = parseInt(stats.total_methods || 0, 10);
        document.getElementById('activeMethods').textContent = parseInt(stats.active_methods || 0, 10);
        document.getElementById('inactiveMethods').textContent = parseInt(stats.inactive_methods || 0, 10);
        document.getElementById('usedMethods').textContent = parseInt(stats.used_methods || 0, 10);
    }

    function emptyStateHtml() {
        return '<tr><td colspan="6"><div class="text-center text-muted py-4"><div class="fw-bold text-dark">No payment methods found</div><div class="small text-muted">Click Add Method or Seed Defaults to create payment options.</div></div></td></tr>';
    }

    function renderPaymentMethods(paymentMethods) {
        if (!paymentMethods.length) {
            tableBody.innerHTML = emptyStateHtml();
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No payment methods found. Click Add Method.</div>';
            return;
        }

        tableBody.innerHTML = paymentMethods.map((method) => {
            const usageCount = parseInt(method.used_count || 0, 10);

            return '<tr>' +
                '<td>' +
                    '<div class="payment-method-box">' +
                        '<div class="mp-avatar">' + paymentIcon(method.method_type) + '</div>' +
                        '<div class="min-w-0">' +
                            '<div class="mp-title">' + escapeHtml(method.payment_method_name) + '</div>' +
                            '<div class="mp-sub">ID: ' + escapeHtml(method.payment_method_id) + '</div>' +
                        '</div>' +
                    '</div>' +
                '</td>' +
                '<td>' + typeBadge(method.method_type) + '</td>' +
                '<td><span class="mp-badge badge-count">' + usageCount + ' transactions</span></td>' +
                '<td>' + statusBadge(method.status) + '</td>' +
                '<td><div class="mp-sub">' + escapeHtml(formatDate(method.created_at)) + '</div></td>' +
                '<td>' + methodActionButtons(method) + '</td>' +
            '</tr>';
        }).join('');

        mobileCards.innerHTML = paymentMethods.map((method) => {
            const usageCount = parseInt(method.used_count || 0, 10);

            return '<div class="mp-mobile-card">' +
                '<div class="d-flex justify-content-between gap-2 align-items-start">' +
                    '<div class="payment-method-box">' +
                        '<div class="mp-avatar">' + paymentIcon(method.method_type) + '</div>' +
                        '<div>' +
                            '<div class="mp-title">' + escapeHtml(method.payment_method_name) + '</div>' +
                            '<div class="mp-sub">' + escapeHtml(typeLabels[method.method_type] || method.method_type) + ' · ID: ' + escapeHtml(method.payment_method_id) + '</div>' +
                        '</div>' +
                    '</div>' +
                    '<div>' + statusBadge(method.status) + '</div>' +
                '</div>' +
                '<div class="d-flex flex-wrap gap-2 mt-2">' +
                    '<span class="mp-badge badge-count">' + usageCount + ' transactions</span>' +
                    '<span class="mp-badge badge-cash">Created ' + escapeHtml(formatDate(method.created_at)) + '</span>' +
                '</div>' +
                '<div class="mt-3">' + methodActionButtons(method) + '</div>' +
            '</div>';
        }).join('');
    }

    async function apiGet(params) {
        const query = new URLSearchParams(params);
        const response = await fetch(apiUrl + '?' + query.toString(), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });

        return response.json();
    }

    async function apiPost(formData) {
        csrfAppend(formData);

        const response = await fetch(apiUrl, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });

        return response.json();
    }

    async function loadPaymentMethods() {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading payment methods...</td></tr>';
        mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">Loading payment methods...</div>';

        try {
            const data = await apiGet({
                action: 'list',
                search: document.getElementById('search').value,
                method_type: document.getElementById('method_type_filter').value,
                status: document.getElementById('status').value
            });

            if (!data.success) {
                showMessage('error', data.message || 'Unable to load payment methods.');
                return;
            }

            renderStats(data.stats || {});
            renderPaymentMethods(data.payment_methods || []);

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            showMessage('error', 'Unable to connect to payment methods API.');
        }
    }

    async function editPaymentMethod(paymentMethodId) {
        try {
            const data = await apiGet({ action: 'get', payment_method_id: paymentMethodId });

            if (!data.success) {
                showMessage('error', data.message || 'Payment method not found.');
                return;
            }

            const method = data.payment_method;
            document.getElementById('payment_method_id').value = method.payment_method_id || 0;
            document.getElementById('payment_method_name').value = method.payment_method_name || '';
            document.getElementById('method_type').value = method.method_type || 'cash';

            document.getElementById('paymentMethodFormTitle').textContent = 'Edit Payment Method';
            document.getElementById('paymentMethodSubmitBtn').innerHTML = 'Update Method';
            document.getElementById('cancelEditBtn').classList.remove('d-none');
            openPaymentMethodModal();
        } catch (error) {
            showMessage('error', 'Unable to fetch payment method details.');
        }
    }

    async function togglePaymentMethodStatus(paymentMethodId) {
        if (!confirm('Change payment method status?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('payment_method_id', paymentMethodId);

        const data = await apiPost(formData);
        showMessage(data.success ? 'success' : 'error', data.message || 'Status update failed.');

        if (data.success) {
            await loadPaymentMethods();
        }
    }

    async function deletePaymentMethod(paymentMethodId) {
        if (!confirm('Delete this payment method? If it is used in bills, cashier collection, or ledger, delete will be blocked.')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_payment_method');
        formData.append('payment_method_id', paymentMethodId);

        const data = await apiPost(formData);
        showMessage(data.success ? 'success' : 'error', data.message || 'Delete failed.');

        if (data.success) {
            resetPaymentMethodForm();
            closePaymentMethodModal();
            await loadPaymentMethods();
        }
    }

    async function seedDefaultPaymentMethods() {
        if (!confirm('Create default methods: Cash, UPI, Card, Cheque, Credit, and Split Payment? Existing methods will not be duplicated.')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'seed_defaults');

        const data = await apiPost(formData);
        showMessage(data.success ? 'success' : 'error', data.message || 'Default seed failed.');

        if (data.success) {
            await loadPaymentMethods();
        }
    }

    function validatePaymentMethodForm() {
        const methodNameInput = document.getElementById('payment_method_name');
        const methodTypeInput = document.getElementById('method_type');
        const methodName = methodNameInput.value.trim();
        const methodType = methodTypeInput.value;

        methodNameInput.value = methodName;

        if (!methodName) {
            showMessage('error', 'Payment method name is required.');
            methodNameInput.focus();
            return false;
        }

        if (methodName.length > 100) {
            showMessage('error', 'Payment method name should not exceed 100 characters.');
            methodNameInput.focus();
            return false;
        }

        if (!/^[A-Za-z0-9 ._\-\/&()]+$/.test(methodName)) {
            showMessage('error', 'Payment method name contains invalid characters.');
            methodNameInput.focus();
            return false;
        }

        if (!typeLabels[methodType]) {
            showMessage('error', 'Select a valid payment method type.');
            methodTypeInput.focus();
            return false;
        }

        return true;
    }

    paymentMethodForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (!validatePaymentMethodForm()) {
            return;
        }

        const submitBtn = document.getElementById('paymentMethodSubmitBtn');
        const readyText = document.getElementById('payment_method_id').value !== '0' ? 'Update Method' : 'Save Method';
        setButtonLoading(submitBtn, true, readyText);

        try {
            const formData = new FormData(paymentMethodForm);
            const data = await apiPost(formData);

            showMessage(data.success ? 'success' : 'error', data.message || 'Payment method save failed.');

            if (data.success) {
                resetPaymentMethodForm();
                closePaymentMethodModal();
                await loadPaymentMethods();
            }
        } catch (error) {
            showMessage('error', 'Unable to save payment method.');
        } finally {
            setButtonLoading(submitBtn, false, readyText);
        }
    });

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        loadPaymentMethods();
    });

    document.getElementById('search').addEventListener('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadPaymentMethods, 350);
    });

    document.getElementById('method_type_filter').addEventListener('change', loadPaymentMethods);
    document.getElementById('status').addEventListener('change', loadPaymentMethods);
    document.getElementById('cancelEditBtn').addEventListener('click', function () {
        resetPaymentMethodForm();
    });

    openPaymentMethodModalBtn.addEventListener('click', function () {
        resetPaymentMethodForm();
        openPaymentMethodModal();
    });

    document.getElementById('seedPaymentMethodsBtn').addEventListener('click', seedDefaultPaymentMethods);

    document.getElementById('resetPaymentMethodPage').addEventListener('click', function () {
        document.getElementById('search').value = '';
        document.getElementById('method_type_filter').value = '';
        document.getElementById('status').value = '';
        resetPaymentMethodForm();
        loadPaymentMethods();
    });

    document.addEventListener('click', function (event) {
        const editBtn = event.target.closest('.js-edit');
        const toggleBtn = event.target.closest('.js-toggle');
        const deleteBtn = event.target.closest('.js-delete');

        if (editBtn) {
            editPaymentMethod(editBtn.dataset.id);
        }

        if (toggleBtn) {
            togglePaymentMethodStatus(toggleBtn.dataset.id);
        }

        if (deleteBtn) {
            deletePaymentMethod(deleteBtn.dataset.id);
        }
    });

    loadPaymentMethods();
})();
</script>
</body>
</html>
