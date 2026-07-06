<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'payment-methods.php');

$pageTitle = 'Payment Methods';
$businessId = (int) current_business_id();

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
        .payment-page-actions .btn,
        .payment-filter-card .btn,
        .payment-modal-content .btn {
            min-height: 42px;
        }

        .payment-list-card {
            border: 1px solid rgba(148, 163, 184, 0.24);
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }

        .payment-filter-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
        }

        .payment-table thead th {
            background: #f1f5f9;
            color: #0f172a;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .04em;
            border-bottom: 0;
            white-space: nowrap;
        }

        .payment-table tbody tr {
            transition: background-color .18s ease;
        }

        .payment-table tbody tr:hover {
            background: #f8fafc;
        }

        .payment-avatar {
            width: 42px;
            height: 42px;
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #dbeafe, #e0e7ff);
            color: #1d4ed8;
            font-weight: 800;
            flex: 0 0 auto;
        }

        .payment-search-input {
            min-height: 44px;
            padding-left: 2.65rem;
        }

        .payment-search-wrap {
            position: relative;
        }

        .payment-search-wrap .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            color: #64748b;
            z-index: 2;
        }

        .payment-modal-content {
            border: 0;
            border-radius: 28px;
            box-shadow: 0 30px 90px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }

        .payment-modal-head {
            background: linear-gradient(135deg, rgba(37, 99, 235, .10), rgba(99, 102, 241, .12));
            border-bottom: 1px solid rgba(148, 163, 184, 0.20);
        }

        .payment-field-note {
            font-size: 11px;
            color: #64748b;
        }

        .payment-empty-state {
            min-height: 190px;
            display: grid;
            place-items: center;
            color: #64748b;
        }

        .payment-empty-icon {
            width: 58px;
            height: 58px;
            border-radius: 22px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eff6ff;
            color: #2563eb;
            margin-bottom: 12px;
        }

        .payment-mobile-actions .btn {
            min-height: 36px;
        }

        @media (max-width: 767.98px) {
            .payment-page-actions {
                width: 100%;
            }

            .payment-page-actions .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>

<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php include __DIR__ . '/includes/common-toast.php'; ?>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>

        <section class="page-section p-3 p-lg-3">

            <div class="page-head-card mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div>
                        <h1 class="h4 fw-bold mb-1">Payment Methods File List</h1>
                        <p class="text-muted-custom mb-0 small">
                            Manage Cash, UPI, Card, Cheque, Credit, and Split Payment methods for billing and cashier collection.
                        </p>
                    </div>

                    <div class="payment-page-actions d-flex gap-2 flex-wrap">
                        <button type="button" id="openPaymentMethodModalBtn" class="btn brand-gradient rounded-4 fw-bold btn-sm px-3">
                            <i data-lucide="plus" class="me-1" style="width:16px;height:16px;"></i>
                            Add Payment Method
                        </button>

                        <button type="button" id="seedPaymentMethodsBtn" class="btn btn-light rounded-4 fw-bold btn-sm px-3">
                            <i data-lucide="list-plus" class="me-1" style="width:16px;height:16px;"></i>
                            Seed Defaults
                        </button>

                        <button type="button" id="resetPaymentMethodPage" class="btn btn-light rounded-4 fw-bold btn-sm px-3">
                            <i data-lucide="refresh-cw" class="me-1" style="width:16px;height:16px;"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="kpi-card">
                        <div class="kpi-icon text-white" style="background:linear-gradient(135deg,#818cf8,#2563eb);">
                            <i data-lucide="credit-card"></i>
                        </div>
                        <div>
                            <div class="kpi-label">Total Methods</div>
                            <p class="kpi-value" id="totalMethods">0</p>
                            <p class="kpi-sub">Payment master</p>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="kpi-card">
                        <div class="kpi-icon bg-success-subtle text-success">
                            <i data-lucide="check-circle"></i>
                        </div>
                        <div>
                            <div class="kpi-label">Active Methods</div>
                            <p class="kpi-value" id="activeMethods">0</p>
                            <p class="kpi-sub">Currently enabled</p>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="kpi-card">
                        <div class="kpi-icon bg-warning-subtle text-warning">
                            <i data-lucide="pause-circle"></i>
                        </div>
                        <div>
                            <div class="kpi-label">Inactive Methods</div>
                            <p class="kpi-value" id="inactiveMethods">0</p>
                            <p class="kpi-sub">Currently disabled</p>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="kpi-card">
                        <div class="kpi-icon text-white" style="background:linear-gradient(135deg,#8b5cf6,#6366f1);">
                            <i data-lucide="receipt"></i>
                        </div>
                        <div>
                            <div class="kpi-label">Used Methods</div>
                            <p class="kpi-value" id="usedMethods">0</p>
                            <p class="kpi-sub">Bills / ledgers</p>
                        </div>
                    </article>
                </div>
            </div>

            <section class="card-ui payment-list-card overflow-hidden">
                <div class="payment-filter-card p-3 p-lg-4">
                    <form method="get" class="row g-2 align-items-end" id="paymentMethodFilterForm">
                        <div class="col-12 col-lg-5">
                            <label class="form-label small fw-semibold">Search Payment Method</label>
                            <div class="payment-search-wrap">
                                <i data-lucide="search" class="search-icon"></i>
                                <input
                                    type="text"
                                    name="search"
                                    id="search"
                                    class="form-control rounded-4 payment-search-input"
                                    placeholder="Type method name / type">
                            </div>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label small fw-semibold">Method Type</label>
                            <select name="method_type_filter" id="method_type_filter" class="form-select rounded-4">
                                <option value="">All Types</option>
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                                <option value="credit">Credit</option>
                                <option value="split">Split Payment</option>
                            </select>
                        </div>

                        <div class="col-12 col-sm-6 col-lg-2">
                            <label class="form-label small fw-semibold">Status</label>
                            <select name="status" id="status" class="form-select rounded-4">
                                <option value="">All</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                        <div class="col-12 col-lg-2">
                            <button type="submit" class="btn btn-dark rounded-4 fw-bold w-100">
                                Filter
                            </button>
                        </div>
                    </form>
                </div>

                <div class="d-none d-md-block table-responsive px-3 px-lg-4 pb-3 pt-3">
                    <table class="table align-middle payment-table mb-0">
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
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Loading payment methods...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 pt-3 d-grid gap-3" id="paymentMethodMobileCards">
                    <div class="mobile-data-card text-center text-muted">Loading payment methods...</div>
                </div>
            </section>

            <div class="modal fade" id="paymentMethodModal" tabindex="-1" aria-labelledby="paymentMethodFormTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-md modal-dialog-scrollable">
                    <div class="modal-content payment-modal-content">
                        <div class="payment-modal-head p-3 p-lg-4">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <h2 class="fw-bold fs-5 mb-1" id="paymentMethodFormTitle">Add Payment Method</h2>
                                    <p class="text-muted-custom small mb-0">
                                        Used in POS bills, cashier collections, split payments, and payment ledger.
                                    </p>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="paymentMethodModalCloseBtn"></button>
                            </div>
                        </div>

                        <form method="post" class="p-3 p-lg-4" id="paymentMethodForm" autocomplete="off">
                            <?= payment_method_csrf_field() ?>

                            <input type="hidden" name="action" value="save_payment_method">
                            <input type="hidden" name="payment_method_id" id="payment_method_id" value="0">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">
                                    Payment Method Name <span class="text-danger">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="payment_method_name"
                                    id="payment_method_name"
                                    class="form-control rounded-4"
                                    required
                                    maxlength="100"
                                    placeholder="Example: Cash / PhonePe UPI / HDFC Card">
                                <div class="payment-field-note mt-1">Only letters, numbers, spaces and simple symbols are allowed.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">
                                    Method Type <span class="text-danger">*</span>
                                </label>
                                <select name="method_type" id="method_type" class="form-select rounded-4" required>
                                    <option value="cash">Cash</option>
                                    <option value="upi">UPI</option>
                                    <option value="card">Card</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="credit">Credit</option>
                                    <option value="split">Split Payment</option>
                                </select>
                                <div class="payment-field-note mt-1">Supported methods: Cash, UPI, Card, Cheque, Credit, and Split Payment.</div>
                            </div>

                            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-4">
                                <button type="button" id="cancelEditBtn" class="btn btn-light rounded-4 fw-bold btn-sm px-4 d-none">
                                    Cancel Edit
                                </button>

                                <button type="button" class="btn btn-outline-secondary rounded-4 fw-bold btn-sm px-4" data-bs-dismiss="modal" id="paymentMethodCloseFooterBtn">
                                    Close
                                </button>

                                <button type="submit" id="paymentMethodSubmitBtn" class="btn brand-gradient rounded-4 fw-bold btn-sm px-4">
                                    Save Method
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
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
        if (window.AppToast && typeof window.AppToast.show === 'function') {
            window.AppToast.show(type === 'success' ? 'success' : 'error', message);
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
            ? '<span class="badge bg-success-subtle text-success rounded-pill px-3">Active</span>'
            : '<span class="badge bg-danger-subtle text-danger rounded-pill px-3">Inactive</span>';
    }

    function typeBadge(methodType) {
        const label = typeLabels[methodType] || methodType;
        const classMap = {
            cash: 'bg-success-subtle text-success',
            upi: 'bg-primary-subtle text-primary',
            card: 'bg-info-subtle text-info',
            cheque: 'bg-warning-subtle text-warning',
            credit: 'bg-danger-subtle text-danger',
            split: 'bg-secondary-subtle text-secondary'
        };

        return `<span class="badge ${classMap[methodType] || 'bg-light text-dark'} rounded-pill px-3">${escapeHtml(label)}</span>`;
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
        return `
            <tr>
                <td colspan="6">
                    <div class="payment-empty-state text-center">
                        <div>
                            <div class="payment-empty-icon mx-auto"><i data-lucide="folder-search"></i></div>
                            <div class="fw-bold text-dark">No payment methods found</div>
                            <div class="small text-muted-custom">Click Add Payment Method or Seed Defaults to create payment options.</div>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }

    function renderPaymentMethods(paymentMethods) {
        if (!paymentMethods.length) {
            tableBody.innerHTML = emptyStateHtml();
            mobileCards.innerHTML = '<div class="mobile-data-card text-center text-muted">No payment methods found. Click Add Payment Method.</div>';
            return;
        }

        tableBody.innerHTML = paymentMethods.map((method) => {
            const toggleText = parseInt(method.status, 10) === 1 ? 'Deactivate' : 'Activate';
            const usageCount = parseInt(method.used_count || 0, 10);

            return `
                <tr>
                    <td>
                        <div class="d-flex align-items-start gap-3">
                            <div class="payment-avatar">${paymentIcon(method.method_type)}</div>
                            <div>
                                <div class="fw-bold">${escapeHtml(method.payment_method_name)}</div>
                                <small class="text-muted-custom">ID: ${escapeHtml(method.payment_method_id)}</small>
                            </div>
                        </div>
                    </td>
                    <td>${typeBadge(method.method_type)}</td>
                    <td><span class="fw-bold">${usageCount}</span><small class="text-muted-custom d-block">transactions</small></td>
                    <td>${statusBadge(method.status)}</td>
                    <td>${escapeHtml(formatDate(method.created_at))}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2">
                            <button type="button" class="btn btn-light btn-sm rounded-4 fw-bold js-edit" data-id="${method.payment_method_id}">Edit</button>
                            <button type="button" class="btn btn-warning btn-sm rounded-4 fw-bold js-toggle" data-id="${method.payment_method_id}">${toggleText}</button>
                            <button type="button" class="btn btn-danger btn-sm rounded-4 fw-bold js-delete" data-id="${method.payment_method_id}">Delete</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        mobileCards.innerHTML = paymentMethods.map((method) => {
            const toggleText = parseInt(method.status, 10) === 1 ? 'Deactivate' : 'Activate';
            const usageCount = parseInt(method.used_count || 0, 10);

            return `
                <div class="mobile-data-card">
                    <div class="d-flex justify-content-between gap-2">
                        <div class="d-flex align-items-start gap-2">
                            <div class="payment-avatar">${paymentIcon(method.method_type)}</div>
                            <div>
                                <div class="fw-bold">${escapeHtml(method.payment_method_name)}</div>
                                <small class="text-muted-custom">${escapeHtml(typeLabels[method.method_type] || method.method_type)}</small>
                            </div>
                        </div>
                        <div>${statusBadge(method.status)}</div>
                    </div>
                    <div class="small text-muted-custom mt-2">Created: ${escapeHtml(formatDate(method.created_at))}</div>
                    <div class="small text-muted-custom mt-1">Usage: ${usageCount} transactions</div>
                    <div class="payment-mobile-actions d-flex gap-2 mt-3">
                        <button type="button" class="btn btn-light btn-sm rounded-4 fw-bold flex-fill js-edit" data-id="${method.payment_method_id}">Edit</button>
                        <button type="button" class="btn btn-warning btn-sm rounded-4 fw-bold flex-fill js-toggle" data-id="${method.payment_method_id}">${toggleText}</button>
                        <button type="button" class="btn btn-danger btn-sm rounded-4 fw-bold flex-fill js-delete" data-id="${method.payment_method_id}">Delete</button>
                    </div>
                </div>
            `;
        }).join('');
    }

    async function apiGet(params) {
        const query = new URLSearchParams(params);
        const response = await fetch(`${apiUrl}?${query.toString()}`, {
            headers: { 'Accept': 'application/json' }
        });

        return response.json();
    }

    async function apiPost(formData) {
        csrfAppend(formData);

        const response = await fetch(apiUrl, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'application/json' }
        });

        return response.json();
    }

    async function loadPaymentMethods() {
        tableBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading payment methods...</td></tr>';
        mobileCards.innerHTML = '<div class="mobile-data-card text-center text-muted">Loading payment methods...</div>';

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
