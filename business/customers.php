<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'customers.php');

$pageTitle = 'Customers';
$businessId = (int) current_business_id();

if (!function_exists('customer_table_has_column')) {
    function customer_table_has_column(mysqli $conn, string $tableName, string $columnName): bool
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

function customer_page_permissions(mysqli $conn, string $pageUrl): array
{
    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return [
            'can_view' => true,
            'can_create' => true,
            'can_edit' => true,
            'can_delete' => true,
            'can_print' => true,
            'can_export' => true,
        ];
    }

    $businessId = (int) current_business_id();
    $roleId = function_exists('current_role_id') ? (int) current_role_id() : (int)($_SESSION['role_id'] ?? 0);

    if ($businessId <= 0 || $roleId <= 0) {
        return ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true, 'can_export' => true];
    }

    if (!function_exists('table_exists') || !table_exists($conn, 'business_sidebar_menus') || !table_exists($conn, 'business_role_sidebar_access')) {
        return ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true, 'can_export' => true];
    }

    $cols = ['can_view'];
    foreach (['can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'] as $col) {
        $cols[] = customer_table_has_column($conn, 'business_role_sidebar_access', $col) ? $col : '0 AS ' . $col;
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
        return ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_print' => true, 'can_export' => true];
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

function customer_csrf_field(): string
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

$permissions = customer_page_permissions($conn, 'customers.php');
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

    .mp-stat-icon svg {
        width: 17px;
        height: 17px;
    }

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
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .status-active { background: #dcfce7; color: #15803d; }
    .status-inactive { background: #fee2e2; color: #b91c1c; }
    .badge-code { background: #dbeafe; color: #1d4ed8; }
    .badge-count { background: #fef3c7; color: #b45309; }
    .badge-type { background: #ede9fe; color: #6d28d9; }
    .badge-stock { background: #dcfce7; color: #15803d; }
    .badge-stock-empty { background: #fee2e2; color: #b91c1c; }
    .badge-qr { background: #ecfeff; color: #0e7490; }

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

    .mp-mobile-card {
        background: var(--card-bg);
        border: 1px solid var(--border-soft);
        border-radius: 14px;
        box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        padding: 10px;
    }

    .modal-title {
        font-size: 15px;
        font-weight: 750;
    }

    .modal .form-label {
        font-size: 11px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .modal .form-control,
    .modal .form-select {
        min-height: 34px;
        font-size: 12px;
        border-radius: 12px;
        padding: 6px 10px;
    }

    .modal-footer .btn {
        font-size: 12px;
        padding: 7px 12px;
        border-radius: 12px;
        font-weight: 700;
    }

    .qr-mini {
        width: 26px;
        height: 26px;
        border-radius: 7px;
        display: inline-grid;
        place-items: center;
        background: repeating-linear-gradient(45deg,#0f172a 0 2px,#fff 2px 4px);
        border: 1px solid #cbd5e1;
        overflow: hidden;
    }

    @media (max-width: 767px) {
        .mp-hero { padding: 12px; }
        .mp-hero h1 { font-size: 19px; }
        .mp-stat-card { min-height: 64px; padding: 9px 10px; }
        .mp-stat-icon { width: 34px; height: 34px; border-radius: 11px; }
        .mp-stat-value { font-size: 16px; }
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
                        <h1>Customers</h1>
                        <p>Manage customer files, GSTIN, article/colour details, loyalty points, and ledger balances.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" id="openCustomerModalBtn" class="btn brand-gradient">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i>
                            Add Customer
                        </button>
                        <button type="button" id="resetCustomerPage" class="btn btn-outline-primary">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="users"></i></div>
                        <div>
                            <div class="mp-stat-label">Total Customers</div>
                            <div class="mp-stat-value" id="totalCustomers">0</div>
                            <div class="mp-stat-sub">Customer master</div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="user-check"></i></div>
                        <div>
                            <div class="mp-stat-label">Active</div>
                            <div class="mp-stat-value" id="activeCustomers">0</div>
                            <div class="mp-stat-sub">Visible customers</div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="wallet"></i></div>
                        <div>
                            <div class="mp-stat-label">Outstanding</div>
                            <div class="mp-stat-value" id="currentOutstandingTotal">₹0.00</div>
                            <div class="mp-stat-sub">Customer balance</div>
                        </div>
                    </article>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="mp-stat-card">
                        <div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="gift"></i></div>
                        <div>
                            <div class="mp-stat-label">Loyalty Points</div>
                            <div class="mp-stat-value" id="loyaltyPointsTotal">0.00</div>
                            <div class="mp-stat-sub">Available points</div>
                        </div>
                    </article>
                </div>
            </div>

            <section class="mp-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                        <div>
                            <h2 class="mp-card-title">Customer List</h2>
                            <p class="mp-card-sub">Role based actions are controlled from Roles permission modal.</p>
                        </div>

                        <form method="get" id="customerFilterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                            <input type="text" name="search" id="search" class="form-control mp-filter-input" placeholder="Search customer / mobile / GSTIN / article / colour">
                            <select name="status" id="status" class="form-select mp-filter-select">
                                <option value="">All</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">Filter</button>
                        </form>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table mb-0">
                        <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Mobile</th>
                            <th>GSTIN</th>
                            <th>Colour</th>
                            <th>Article</th>
                            <th>Available Stock</th>
                            <th>QR Code</th>
                            <th>Outstanding</th>
                            <th>Loyalty</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th style="width: 230px;">Action</th>
                        </tr>
                        </thead>
                        <tbody id="customerTableBody">
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">Loading customers...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="customerMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading customers...</div>
                </div>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content" id="customerForm" autocomplete="off">
            <?= customer_csrf_field() ?>
            <input type="hidden" name="action" value="save_customer">
            <input type="hidden" name="customer_id" id="customer_id" value="0">

            <div class="modal-header">
                <h5 class="modal-title" id="customerFormTitle">Add Customer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control" required maxlength="200" placeholder="Enter customer name">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Mobile</label>
                        <input type="text" name="mobile" id="mobile" class="form-control" maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" placeholder="Enter 10 digit mobile number">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" maxlength="150" placeholder="Enter email address">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">GSTIN</label>
                        <input type="text" name="gstin" id="gstin" class="form-control text-uppercase" maxlength="15" placeholder="Sample: 33ABCDE1234F1Z5">
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Colour</label>
                        <input type="text" name="color" id="color" class="form-control" maxlength="80" placeholder="Example: Black">
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Article</label>
                        <input type="text" name="article_no" id="article_no" class="form-control" maxlength="100" placeholder="Example: A105">
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Available Stock</label>
                        <input type="number" name="available_stock" id="available_stock" class="form-control" step="0.01" min="0" value="0.00">
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">QR Code</label>
                        <input type="text" name="qr_code" id="qr_code" class="form-control" maxlength="150" placeholder="QR / barcode value">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Opening Outstanding</label>
                        <input type="number" step="0.01" min="0" name="opening_outstanding" id="opening_outstanding" class="form-control" value="0.00">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Loyalty Points</label>
                        <input type="number" step="0.01" min="0" name="loyalty_points" id="loyalty_points" class="form-control" value="0.00">
                    </div>

                    <div class="col-12 col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" id="customer_status" class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" id="address" rows="2" class="form-control" placeholder="Enter customer address"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="customerSubmitBtn" class="btn brand-gradient">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>

<script>
(function () {
    'use strict';

    const apiUrl = 'api/customers-api.php';
    const canCreate = <?= $permissions['can_create'] ? 'true' : 'false' ?>;
    const canEdit = <?= $permissions['can_edit'] ? 'true' : 'false' ?>;
    const canDelete = <?= $permissions['can_delete'] ? 'true' : 'false' ?>;

    const customerForm = document.getElementById('customerForm');
    const filterForm = document.getElementById('customerFilterForm');
    const tableBody = document.getElementById('customerTableBody');
    const mobileCards = document.getElementById('customerMobileCards');
    const mobileInput = document.getElementById('mobile');
    const gstinInput = document.getElementById('gstin');
    const customerModalEl = document.getElementById('customerModal');
    let customerModalInstance = null;
    let searchTimer = null;

    const money = new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2
    });

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
        alert(message);
    }

    function normalizeMobile(value) {
        return String(value || '').replace(/[^0-9]/g, '').slice(0, 10);
    }

    function normalizeGstin(value) {
        return String(value || '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 15);
    }

    function getCustomerModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
            if (!customerModalInstance) {
                customerModalInstance = new window.bootstrap.Modal(customerModalEl, {
                    backdrop: 'static',
                    keyboard: false
                });
            }
            return customerModalInstance;
        }
        return null;
    }

    function openCustomerModal() {
        const modal = getCustomerModal();
        if (modal) {
            modal.show();
        } else {
            customerModalEl.classList.add('show');
            customerModalEl.style.display = 'block';
            customerModalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }
        setTimeout(function () {
            const firstInput = document.getElementById('customer_name');
            if (firstInput) firstInput.focus();
        }, 250);
    }

    function closeCustomerModal() {
        const modal = getCustomerModal();
        if (modal) {
            modal.hide();
        } else {
            customerModalEl.classList.remove('show');
            customerModalEl.style.display = 'none';
            customerModalEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
        }
    }

    function csrfAppend(formData) {
        const csrfInput = customerForm.querySelector('input[type="hidden"][name*="csrf"], input[type="hidden"][name="_token"]');
        if (csrfInput && !formData.has(csrfInput.name)) {
            formData.append(csrfInput.name, csrfInput.value);
        }
    }

    function statusBadge(status) {
        return parseInt(status, 10) === 1
            ? '<span class="mp-badge status-active">Active</span>'
            : '<span class="mp-badge status-inactive">Inactive</span>';
    }

    function customerInitial(name) {
        const cleanName = String(name || 'C').trim();
        return escapeHtml(cleanName.substring(0, 1).toUpperCase() || 'C');
    }

    function qrContent(qrCode) {
        const value = String(qrCode || '').trim();
        if (!value) {
            return '<span class="mp-badge badge-qr">No QR</span>';
        }
        return '<span class="mp-badge badge-qr" title="' + escapeHtml(value) + '"><span class="qr-mini"></span> ' + escapeHtml(value) + '</span>';
    }

    function stockBadge(value) {
        const stock = parseFloat(value || 0);
        const badgeClass = stock > 0 ? 'badge-stock' : 'badge-stock-empty';
        return '<span class="mp-badge ' + badgeClass + '">' + stock.toFixed(2) + '</span>';
    }

    function resetCustomerForm() {
        customerForm.reset();
        document.getElementById('customer_id').value = '0';
        document.getElementById('opening_outstanding').value = '0.00';
        document.getElementById('loyalty_points').value = '0.00';
        document.getElementById('available_stock').value = '0.00';
        document.getElementById('customer_status').value = '1';
        document.getElementById('customerFormTitle').textContent = 'Add Customer';
        document.getElementById('customerSubmitBtn').innerHTML = 'Save Customer';
    }

    function validateCustomerForm() {
        const customerName = document.getElementById('customer_name').value.trim();
        const mobile = normalizeMobile(mobileInput.value);
        const email = document.getElementById('email').value.trim();
        const gstin = normalizeGstin(gstinInput.value);
        const openingOutstanding = parseFloat(document.getElementById('opening_outstanding').value || '0');
        const loyaltyPoints = parseFloat(document.getElementById('loyalty_points').value || '0');
        const availableStock = parseFloat(document.getElementById('available_stock').value || '0');
        const gstinPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;

        mobileInput.value = mobile;
        gstinInput.value = gstin;

        if (!customerName) {
            showMessage('error', 'Customer name is required.');
            document.getElementById('customer_name').focus();
            return false;
        }

        if (mobile && !/^[6-9][0-9]{9}$/.test(mobile)) {
            showMessage('error', 'Mobile number must be exactly 10 digits and start with 6, 7, 8, or 9.');
            mobileInput.focus();
            return false;
        }

        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showMessage('error', 'Enter a valid email address.');
            document.getElementById('email').focus();
            return false;
        }

        if (gstin && !gstinPattern.test(gstin)) {
            showMessage('error', 'Enter a valid GSTIN. Sample: 33ABCDE1234F1Z5.');
            gstinInput.focus();
            return false;
        }

        if (Number.isNaN(openingOutstanding) || openingOutstanding < 0) {
            showMessage('error', 'Opening outstanding cannot be negative.');
            document.getElementById('opening_outstanding').focus();
            return false;
        }

        if (Number.isNaN(loyaltyPoints) || loyaltyPoints < 0) {
            showMessage('error', 'Loyalty points cannot be negative.');
            document.getElementById('loyalty_points').focus();
            return false;
        }

        if (Number.isNaN(availableStock) || availableStock < 0) {
            showMessage('error', 'Available stock cannot be negative.');
            document.getElementById('available_stock').focus();
            return false;
        }

        return true;
    }

    function fillCustomerForm(customer) {
        if (!customer) return;
        document.getElementById('customer_id').value = customer.customer_id || 0;
        document.getElementById('customer_name').value = customer.customer_name || '';
        document.getElementById('mobile').value = normalizeMobile(customer.mobile || '');
        document.getElementById('email').value = customer.email || '';
        document.getElementById('gstin').value = normalizeGstin(customer.gstin || '');
        document.getElementById('address').value = customer.address || '';
        document.getElementById('color').value = customer.color || customer.colour || '';
        document.getElementById('article_no').value = customer.article_no || customer.article || '';
        document.getElementById('available_stock').value = parseFloat(customer.available_stock || customer.available_qty || 0).toFixed(2);
        document.getElementById('qr_code').value = customer.qr_code || customer.qr_code_path || '';
        document.getElementById('opening_outstanding').value = parseFloat(customer.opening_outstanding || 0).toFixed(2);
        document.getElementById('loyalty_points').value = parseFloat(customer.loyalty_points || 0).toFixed(2);
        document.getElementById('customer_status').value = String(customer.status ?? 1);
        document.getElementById('customerFormTitle').textContent = 'Edit Customer';
        document.getElementById('customerSubmitBtn').innerHTML = 'Update Customer';
        openCustomerModal();
    }

    function renderStats(stats) {
        document.getElementById('totalCustomers').textContent = parseInt(stats.total_customers || 0, 10);
        document.getElementById('activeCustomers').textContent = parseInt(stats.active_customers || 0, 10);
        document.getElementById('currentOutstandingTotal').textContent = money.format(parseFloat(stats.current_total || 0));
        document.getElementById('loyaltyPointsTotal').textContent = parseFloat(stats.loyalty_total || 0).toFixed(2);
    }

    function renderCustomers(customers) {
        if (!customers.length) {
            tableBody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">No customers found.</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No customers found.</div>';
            return;
        }

        tableBody.innerHTML = customers.map(function (customer) {
            const customerId = parseInt(customer.customer_id || 0, 10);
            const currentOutstanding = money.format(parseFloat(customer.current_outstanding || 0));
            const loyaltyPoints = parseFloat(customer.loyalty_points || 0).toFixed(2);
            const toggleText = parseInt(customer.status, 10) === 1 ? 'Deactivate' : 'Activate';
            const createdAt = customer.created_at ? new Date(String(customer.created_at).replace(' ', 'T')) : null;
            const createdDate = createdAt && !Number.isNaN(createdAt.getTime()) ? createdAt.toLocaleDateString('en-GB') : '-';
            const color = customer.color || customer.colour || '-';
            const article = customer.article_no || customer.article || '-';
            const availableStock = customer.available_stock || customer.available_qty || 0;
            const actions = `
                <div class="d-inline-flex flex-wrap gap-2">
                    ${canEdit ? `<button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-edit" data-id="${customerId}">Edit</button>` : ''}
                    ${canEdit ? `<button type="button" class="btn btn-sm btn-outline-warning mp-action-btn js-toggle" data-id="${customerId}">${toggleText}</button>` : ''}
                    ${canDelete ? `<button type="button" class="btn btn-sm btn-outline-danger mp-action-btn js-delete" data-id="${customerId}">Delete</button>` : ''}
                </div>
            `;

            return `
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="mp-avatar">${customerInitial(customer.customer_name)}</div>
                            <div>
                                <div class="mp-title">${escapeHtml(customer.customer_name)}</div>
                                <div class="mp-sub">ID: ${customerId}</div>
                                <div class="mp-sub">${escapeHtml(customer.email || '-')}</div>
                            </div>
                        </div>
                    </td>
                    <td>${escapeHtml(customer.mobile || '-')}</td>
                    <td>${escapeHtml(customer.gstin || '-')}</td>
                    <td><span class="mp-badge badge-type">${escapeHtml(color)}</span></td>
                    <td><span class="mp-badge badge-code">${escapeHtml(article)}</span></td>
                    <td>${stockBadge(availableStock)}</td>
                    <td>${qrContent(customer.qr_code || customer.qr_code_path || '')}</td>
                    <td><div class="fw-bold">${currentOutstanding}</div><small class="text-muted-custom">Opening: ${money.format(parseFloat(customer.opening_outstanding || 0))}</small></td>
                    <td class="fw-bold">${loyaltyPoints}</td>
                    <td>${statusBadge(customer.status)}</td>
                    <td>${escapeHtml(createdDate)}</td>
                    <td>${actions}</td>
                </tr>
            `;
        }).join('');

        mobileCards.innerHTML = customers.map(function (customer) {
            const customerId = parseInt(customer.customer_id || 0, 10);
            const currentOutstanding = money.format(parseFloat(customer.current_outstanding || 0));
            const loyaltyPoints = parseFloat(customer.loyalty_points || 0).toFixed(2);
            const toggleText = parseInt(customer.status, 10) === 1 ? 'Deactivate' : 'Activate';
            const color = customer.color || customer.colour || '-';
            const article = customer.article_no || customer.article || '-';
            const availableStock = customer.available_stock || customer.available_qty || 0;

            return `
                <div class="mp-mobile-card">
                    <div class="d-flex gap-2">
                        <div class="mp-avatar">${customerInitial(customer.customer_name)}</div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="mp-title">${escapeHtml(customer.customer_name)}</div>
                                    <div class="mp-sub">${escapeHtml(customer.mobile || '-')}</div>
                                </div>
                                ${statusBadge(customer.status)}
                            </div>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                <span class="mp-badge badge-type">Colour: ${escapeHtml(color)}</span>
                                <span class="mp-badge badge-code">Article: ${escapeHtml(article)}</span>
                                ${stockBadge(availableStock)}
                                ${qrContent(customer.qr_code || customer.qr_code_path || '')}
                            </div>
                            <div class="mp-sub mt-2">GSTIN: ${escapeHtml(customer.gstin || '-')}</div>
                            <div class="fw-bold mt-1">Outstanding: ${currentOutstanding}</div>
                            <div class="mp-sub">Loyalty: ${loyaltyPoints}</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                ${canEdit ? `<button type="button" class="btn btn-sm btn-outline-primary mp-action-btn js-edit" data-id="${customerId}">Edit</button>` : ''}
                                ${canEdit ? `<button type="button" class="btn btn-sm btn-outline-warning mp-action-btn js-toggle" data-id="${customerId}">${toggleText}</button>` : ''}
                                ${canDelete ? `<button type="button" class="btn btn-sm btn-outline-danger mp-action-btn js-delete" data-id="${customerId}">Delete</button>` : ''}
                            </div>
                        </div>
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

    async function loadCustomers() {
        tableBody.innerHTML = '<tr><td colspan="12" class="text-center text-muted py-4">Loading customers...</td></tr>';
        mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">Loading customers...</div>';
        try {
            const data = await apiGet({
                action: 'list',
                search: document.getElementById('search').value,
                status: document.getElementById('status').value
            });

            if (!data.success) {
                showMessage('error', data.message || 'Unable to load customers.');
                return;
            }

            renderStats(data.stats || {});
            renderCustomers(data.customers || []);

            if (window.lucide) {
                window.lucide.createIcons();
            }
        } catch (error) {
            showMessage('error', 'Unable to connect to customer API.');
        }
    }

    async function editCustomer(customerId) {
        try {
            const data = await apiGet({ action: 'get', customer_id: customerId });
            if (!data.success) {
                showMessage('error', data.message || 'Customer not found.');
                return;
            }
            fillCustomerForm(data.customer);
        } catch (error) {
            showMessage('error', 'Unable to fetch customer details.');
        }
    }

    async function toggleCustomerStatus(customerId) {
        if (!confirm('Change customer status?')) return;
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('customer_id', customerId);
        const data = await apiPost(formData);
        showMessage(data.success ? 'success' : 'error', data.message || 'Status update failed.');
        if (data.success) await loadCustomers();
    }

    async function deleteCustomer(customerId) {
        if (!confirm('Delete this customer? If it is used in bills or ledger, delete will be blocked.')) return;
        const formData = new FormData();
        formData.append('action', 'delete_customer');
        formData.append('customer_id', customerId);
        const data = await apiPost(formData);
        showMessage(data.success ? 'success' : 'error', data.message || 'Delete failed.');
        if (data.success) await loadCustomers();
    }

    document.getElementById('openCustomerModalBtn').addEventListener('click', function () {
        if (!canCreate) {
            showMessage('error', 'You do not have permission to create customers.');
            return;
        }
        resetCustomerForm();
        openCustomerModal();
    });

    document.getElementById('resetCustomerPage').addEventListener('click', function () {
        document.getElementById('search').value = '';
        document.getElementById('status').value = '';
        resetCustomerForm();
        loadCustomers();
    });

    filterForm.addEventListener('submit', function (event) {
        event.preventDefault();
        loadCustomers();
    });

    document.getElementById('search').addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(loadCustomers, 300);
    });

    document.getElementById('status').addEventListener('change', loadCustomers);

    if (mobileInput) {
        mobileInput.addEventListener('input', function () {
            this.value = normalizeMobile(this.value);
        });
    }

    if (gstinInput) {
        gstinInput.addEventListener('input', function () {
            this.value = normalizeGstin(this.value);
        });
    }

    customerForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!validateCustomerForm()) return;

        const submitBtn = document.getElementById('customerSubmitBtn');
        const readyText = document.getElementById('customer_id').value !== '0' ? 'Update Customer' : 'Save Customer';
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Please wait...';

        try {
            const formData = new FormData(customerForm);
            const data = await apiPost(formData);
            showMessage(data.success ? 'success' : 'error', data.message || 'Customer save failed.');
            if (data.success) {
                resetCustomerForm();
                closeCustomerModal();
                await loadCustomers();
            }
        } catch (error) {
            showMessage('error', 'Unable to save customer.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = readyText;
        }
    });

    document.addEventListener('click', function (event) {
        const editBtn = event.target.closest('.js-edit');
        const toggleBtn = event.target.closest('.js-toggle');
        const deleteBtn = event.target.closest('.js-delete');

        if (editBtn) editCustomer(editBtn.dataset.id);
        if (toggleBtn) toggleCustomerStatus(toggleBtn.dataset.id);
        if (deleteBtn) deleteCustomer(deleteBtn.dataset.id);
    });

    loadCustomers();
})();
</script>
</body>
</html>
