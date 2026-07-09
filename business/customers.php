<?php
/**
 * GK Footwear POS - Customers
 * Live customer purchase view using existing DB schema and api/customers-api.php.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
if (function_exists('require_page_access')) {
    require_page_access($conn, 'customers.php');
}

$pageTitle = 'Customers';
$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('customer_table_has_column')) {
    function customer_table_has_column(mysqli $conn, string $tableName, string $columnName): bool
    {
        if (function_exists('table_has_column')) {
            return table_has_column($conn, $tableName, $columnName);
        }
        $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }
}

function customer_page_permissions(mysqli $conn, string $pageUrl): array
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
    }
    

    /* Customer module additions kept on top of the Bill List template */
    .status-inactive { background: #fee2e2; color: #b91c1c; }
    .badge-stock { background: #dcfce7; color: #15803d; }
    .badge-stock-low { background: #fef3c7; color: #b45309; }
    .badge-stock-empty { background: #fee2e2; color: #b91c1c; }
    .badge-qr { background: #ecfeff; color: #0e7490; }
    /* Match Supplier module inner-page method: keep the table inside the card and avoid full-page horizontal scroll */
    html, body { max-width: 100%; overflow-x: hidden; }
    #main { min-width: 0; max-width: 100%; overflow-x: hidden; }
    .master-page, .mp-card, .customer-list-card { max-width: 100%; min-width: 0; }
    .customer-list-card .table-responsive { width: 100%; max-width: 100%; overflow-x: hidden; }
    .customer-table { width: 100%; min-width: 0 !important; table-layout: fixed; }
    .customer-table th,
    .customer-table td { white-space: normal; word-break: break-word; overflow-wrap: anywhere; padding: 8px 7px; }
    .customer-table th { font-size: 9.5px; line-height: 1.15; }
    .customer-table td { font-size: 10.5px; }
    .customer-table .mp-avatar { width: 30px; height: 30px; border-radius: 10px; font-size: 12px; }
    .customer-table .mp-title { font-size: 11px; }
    .customer-table .mp-sub { font-size: 9.5px; }
    .customer-table .mp-badge { max-width: 100%; font-size: 9.5px; padding: 4px 6px; line-height: 1.1; white-space: normal; }
    .customer-table .qr-mini { width: 20px; height: 20px; border-radius: 6px; }
    .customer-table th:last-child,
    .customer-table td:last-child { text-align: center; vertical-align: middle; }
    /* Modern compact customer action buttons */
    .customer-action-wrap {
        display: grid;
        grid-template-columns: repeat(2, 32px);
        gap: 6px;
        justify-content: center;
        align-items: center;
        min-width: 72px;
    }
    .customer-action-btn {
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
    .customer-action-btn i,
    .customer-action-btn svg {
        width: 15px;
        height: 15px;
        stroke-width: 2.4;
    }
    .customer-action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(15, 23, 42, .12);
    }
    .customer-action-btn:active { transform: translateY(0); box-shadow: none; }
    .customer-action-btn.action-view { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .customer-action-btn.action-view:hover { background: #dbeafe; }
    .customer-action-btn.action-edit { background: #eef2ff; color: #4f46e5; border-color: #c7d2fe; }
    .customer-action-btn.action-edit:hover { background: #e0e7ff; }
    .customer-action-btn.action-toggle-active { background: #fff7ed; color: #ea580c; border-color: #fed7aa; }
    .customer-action-btn.action-toggle-active:hover { background: #ffedd5; }
    .customer-action-btn.action-toggle-inactive { background: #ecfdf5; color: #16a34a; border-color: #bbf7d0; }
    .customer-action-btn.action-toggle-inactive:hover { background: #dcfce7; }
    .customer-action-btn.action-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
    .customer-action-btn.action-delete:hover { background: #fee2e2; }
    .customer-action-btn.action-delete.blocked-delete { opacity: .72; cursor: not-allowed; }
    .customer-action-btn.action-delete.blocked-delete:hover { transform: none; box-shadow: 0 4px 10px rgba(15, 23, 42, .05); background: #fef2f2; }
    .customer-action-text { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    .customer-bill-box {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
        min-width: 0;
        max-width: 100%;
        overflow: hidden;
    }
    .customer-bill-box .mp-badge,
    .customer-bill-box .mp-sub,
    .customer-bill-box .pending-bill-chip {
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .customer-bill-box .mp-badge {
        width: auto;
        align-self: flex-start;
        white-space: nowrap;
    }
    .customer-bill-box .mp-sub {
        display: block;
        white-space: nowrap;
        line-height: 1.15;
    }
    .pending-bill-stack {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
        width: 100%;
        min-width: 0;
        margin-top: 2px;
    }
    .pending-bill-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        max-width: 100%;
        border-radius: 999px;
        padding: 3px 6px;
        font-size: 9px;
        line-height: 1.05;
        font-weight: 800;
        color: #b91c1c;
        background: #fee2e2;
        border: 1px solid #fecaca;
        white-space: nowrap;
    }
    .pending-bill-ref { display: none; }
    .delete-rule-note {
        display:block;
        margin-top:3px;
        color:#b45309;
        font-size:9px;
        font-weight:700;
        line-height:1.15;
    }
    .customer-detail-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    .customer-detail-box {
        border: 1px solid var(--border-soft);
        background: #f8fafc;
        border-radius: 14px;
        padding: 10px;
    }
    .purchase-scroll { max-height: 430px; overflow: auto; }
    .ledger-scroll { max-height: 260px; overflow: auto; }
    /* Real barcode preview for Customer module - same method used in Stock List */
    .customer-barcode-cell { min-width: 0; }
    .customer-barcode-chip {
        width: 100%;
        max-width: 154px;
        border: 1px solid #bae6fd;
        background: linear-gradient(135deg, #f8fbff, #ecfeff);
        color: #0f172a;
        border-radius: 12px;
        padding: 5px 6px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        box-shadow: 0 6px 14px rgba(14, 116, 144, .08);
        overflow: hidden;
        vertical-align: middle;
    }
    .customer-barcode-preview {
        flex: 1 1 auto;
        min-width: 58px;
        max-width: 86px;
        overflow: hidden;
        background: #ffffff;
        border-radius: 7px;
        padding: 2px 3px;
        border: 1px solid #dbeafe;
    }
    .customer-barcode-svg-mini,
    .customer-barcode-svg-card {
        width: 100%;
        display: block;
    }
    .customer-barcode-svg-mini { height: 22px; }
    .customer-barcode-code-wrap {
        flex: 0 0 auto;
        min-width: 46px;
        max-width: 60px;
        overflow: hidden;
    }
    .customer-barcode-code {
        display: block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        font-size: 9px;
        font-weight: 900;
        letter-spacing: .01em;
        color: #0f172a;
        line-height: 1.1;
    }
    .customer-barcode-extra {
        display: inline-flex;
        margin-top: 3px;
        font-size: 8.5px;
        font-weight: 850;
        border-radius: 999px;
        padding: 2px 5px;
        background: #dbeafe;
        color: #1d4ed8;
        line-height: 1;
    }
    .customer-barcode-empty {
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        color: #64748b;
        border-radius: 999px;
        padding: 5px 8px;
        font-size: 10px;
        font-weight: 750;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        white-space: nowrap;
    }
    .customer-barcode-card {
        min-width: 190px;
        max-width: 240px;
        border: 1px solid #bae6fd;
        background: linear-gradient(135deg, #f0f9ff, #ecfeff);
        border-radius: 14px;
        padding: 7px 8px;
        display: grid;
        gap: 4px;
        box-shadow: 0 6px 14px rgba(14, 116, 144, .08);
    }
    .customer-barcode-card .customer-barcode-svg-card {
        height: 34px;
        background: #fff;
        border: 1px solid #dbeafe;
        border-radius: 8px;
        padding: 2px 3px;
    }
    .customer-barcode-card .customer-barcode-text {
        display: block;
        text-align: center;
        font-size: 11px;
        font-weight: 900;
        letter-spacing: .03em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #0f172a;
    }
    .customer-barcode-card .customer-barcode-note {
        display: block;
        text-align: center;
        font-size: 9px;
        color: #0369a1;
        font-weight: 800;
    }
    .live-note {
        border: 1px dashed #bfdbfe;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 14px;
        padding: 9px 11px;
        font-size: 11px;
        font-weight: 700;
    }
    .amount-good { color: #15803d; font-weight: 800; }
    @media (max-width: 991px) {
        .customer-detail-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 767px) {
        .customer-detail-grid { grid-template-columns: 1fr; }
    }

    </style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) { include __DIR__ . '/includes/common-toast.php'; } ?>
<form id="customerSecurityForm" class="d-none"><?= customer_csrf_field() ?></form>

<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>

        <section class="page-section master-page p-3 p-lg-3">
            <div class="mp-hero mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
                    <div>
                        <h1>Customers</h1>
                        <p>Live customer master with purchase history, article, colour, size, stock balance and product QR/barcode details.</p>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <?php if ($permissions['can_create']): ?>
                        <button type="button" id="openCustomerModalBtn" class="btn brand-gradient">
                            <i data-lucide="plus" style="width:14px;height:14px;"></i> Add Customer
                        </button>
                        <?php endif; ?>
                        <button type="button" id="resetCustomerPage" class="btn btn-outline-primary">
                            <i data-lucide="refresh-cw" style="width:14px;height:14px;"></i> Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:linear-gradient(135deg,#818cf8,#2563eb);"><i data-lucide="users"></i></div><div><div class="mp-stat-label">Total Customers</div><div class="mp-stat-value" id="totalCustomers">0</div><div class="mp-stat-sub">Customer master</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#dcfce7;color:#15803d;"><i data-lucide="user-check"></i></div><div><div class="mp-stat-label">Active Customers</div><div class="mp-stat-value" id="activeCustomers">0</div><div class="mp-stat-sub">Visible customers</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#fef3c7;color:#b45309;"><i data-lucide="wallet"></i></div><div><div class="mp-stat-label">Outstanding</div><div class="mp-stat-value" id="currentOutstandingTotal">₹0.00</div><div class="mp-stat-sub"><span id="outstandingCustomers">0</span> customer balances</div></div></article></div>
                <div class="col-12 col-sm-6 col-xl-3"><article class="mp-stat-card"><div class="mp-stat-icon" style="background:#ede9fe;color:#6d28d9;"><i data-lucide="gift"></i></div><div><div class="mp-stat-label">Loyalty Points</div><div class="mp-stat-value" id="loyaltyPointsTotal">0.00</div><div class="mp-stat-sub">Available points</div></div></article></div>
            </div>

            <section class="mp-card customer-list-card">
                <div class="mp-card-head">
                    <div class="d-flex flex-column gap-2">
                        <div class="d-flex flex-column flex-xl-row justify-content-xl-between gap-2">
                        <div>
                            <h2 class="mp-card-title">Customer List</h2>
                            <p class="mp-card-sub">Search customer, mobile, GSTIN, purchased article, colour, size or QR/barcode value.</p>
                        </div>

                        <form method="get" id="customerFilterForm" class="d-flex flex-column flex-md-row gap-2 align-items-md-center">
                            <input type="text" name="search" id="search" class="form-control mp-filter-input" placeholder="Search customer / article / colour / size / QR">
                            <select name="status" id="status" class="form-select mp-filter-select">
                                <option value="">All</option>
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold px-3">Filter</button>
                        </form>
                        </div>
                    </div>
                </div>

                <div class="d-none d-md-block table-responsive px-3 pb-3">
                    <table class="table mp-table customer-table mb-0">
                        <colgroup>
                            <col style="width:12%;">
                            <col style="width:9%;">
                            <col style="width:8%;">
                            <col style="width:9%;">
                            <col style="width:11%;">
                            <col style="width:7%;">
                            <col style="width:12%;">
                            <col style="width:10%;">
                            <col style="width:6%;">
                            <col style="width:7%;">
                            <col style="width:9%;">
                        </colgroup>
                        <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Mobile / GSTIN</th>
                            <th>Bills</th>
                            <th>Purchased Articles</th>
                            <th>Latest Article</th>
                            <th>Latest Stock</th>
                            <th>Latest Barcode</th>
                            <th>Outstanding</th>
                            <th>Loyalty</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                        </thead>
                        <tbody id="customerTableBody"><tr><td colspan="11" class="text-center text-muted py-4">Loading customers...</td></tr></tbody>
                    </table>
                </div>

                <div class="d-md-none px-3 pb-3 d-grid gap-3" id="customerMobileCards">
                    <div class="mp-mobile-card text-center text-muted">Loading customers...</div>
                </div>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-3 py-2 border-top">
                    <div class="mp-sub" id="paginationInfo">Page 1 of 1 · Total 0 customers</div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="prevPage">Previous</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" id="nextPage">Next</button>
                    </div>
                </div>
            </section>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-labelledby="customerFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="post" class="modal-content rounded-4" id="customerForm" autocomplete="off">
            <?= customer_csrf_field() ?>
            <input type="hidden" name="action" value="save_customer">
            <input type="hidden" name="customer_id" id="customer_id" value="0">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="customerFormTitle">Add Customer</h5>
                    <div class="mp-sub">Customer master only. Article, colour, stock and QR data are fetched live from bills and stock tables.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6"><label class="form-label">Customer Name *</label><input type="text" name="customer_name" id="customer_name" class="form-control" required maxlength="200" placeholder="Enter customer name"></div>
                    <div class="col-12 col-md-6"><label class="form-label">Mobile</label><input type="text" name="mobile" id="mobile" class="form-control" maxlength="10" inputmode="numeric" pattern="[6-9][0-9]{9}" placeholder="Enter 10 digit mobile number"></div>
                    <div class="col-12 col-md-6"><label class="form-label">Email</label><input type="email" name="email" id="email" class="form-control" maxlength="150" placeholder="Enter email address"></div>
                    <div class="col-12 col-md-6"><label class="form-label">GSTIN</label><input type="text" name="gstin" id="gstin" class="form-control text-uppercase" maxlength="15" placeholder="Sample: 33ABCDE1234F1Z5"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Opening Outstanding</label><input type="number" step="0.01" min="0" name="opening_outstanding" id="opening_outstanding" class="form-control" value="0.00"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Loyalty Points</label><input type="number" step="0.01" min="0" name="loyalty_points" id="loyalty_points" class="form-control" value="0.00"></div>
                    <div class="col-12 col-md-4"><label class="form-label">Status</label><select name="status" id="customer_status" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea name="address" id="address" rows="2" class="form-control" placeholder="Enter customer address"></textarea></div>
                </div>
                <div class="live-note mt-3">Product article, colour, size, quantity, live available stock and QR/barcode are not stored manually in the customer master. They are loaded from bills, bill items, stock inward items and stock barcode records.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" id="customerSubmitBtn" class="btn brand-gradient">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="customerDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Customer Purchase Details</h5>
                    <div class="mp-sub" id="detailSubTitle">Loading live data...</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="customerDetailBody"><div class="text-center text-muted py-4">Loading customer details...</div></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button></div>
        </div>
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
    const detailModalEl = document.getElementById('customerDetailModal');
    const detailBody = document.getElementById('customerDetailBody');
    const detailSubTitle = document.getElementById('detailSubTitle');
    let customerModalInstance = null;
    let detailModalInstance = null;
    let searchTimer = null;
    let currentPage = 1;
    let totalPages = 1;
    const perPage = 20;

    const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 });

    function escapeHtml(value) { return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }
    function toNumber(value) { const n = parseFloat(value || 0); return Number.isNaN(n) ? 0 : n; }
    function showMessage(type, message) { const toastType = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'error'); if (window.AppToast && typeof window.AppToast.show === 'function') { window.AppToast.show(toastType, message); return; } alert(message); }
    function normalizeMobile(value) { return String(value || '').replace(/[^0-9]/g, '').slice(0, 10); }
    function normalizeGstin(value) { return String(value || '').toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 15); }
    function customerInitial(name) { const cleanName = String(name || 'C').trim(); return escapeHtml(cleanName.substring(0, 1).toUpperCase() || 'C'); }
    function statusBadge(status) { return parseInt(status, 10) === 1 ? '<span class="mp-badge status-active">Active</span>' : '<span class="mp-badge status-inactive">Inactive</span>'; }
    function paymentBadge(status) { const s = String(status || 'pending').toLowerCase(); const cls = s === 'paid' ? 'status-active' : (s === 'partial' ? 'badge-count' : 'status-inactive'); return '<span class="mp-badge ' + cls + '">' + escapeHtml(s.charAt(0).toUpperCase() + s.slice(1)) + '</span>'; }
    function stockBadge(value) { const stock = toNumber(value); let cls = 'badge-stock-empty'; if (stock > 5) cls = 'badge-stock'; else if (stock > 0) cls = 'badge-stock-low'; return '<span class="mp-badge ' + cls + '">' + stock.toFixed(2) + '</span>'; }
    function normalizeBarcodeList(value) {
        const text = String(value || '').trim();
        if (!text || text === '-' || text.toLowerCase() === 'no qr' || text.toLowerCase() === 'no barcode') return [];
        const seen = {};
        return text
            .split(/[|,\n]+/)
            .map(function (v) { return v.trim(); })
            .filter(function (v) {
                if (!v || seen[v]) return false;
                seen[v] = true;
                return true;
            });
    }

    function customerBarcodeValue(item) {
        item = item || {};
        const candidates = [
            item.latest_qr_code,
            item.qr_code,
            item.barcode_values,
            item.barcode_value,
            item.stock_barcode,
            item.generated_barcode
        ];
        for (let i = 0; i < candidates.length; i++) {
            const value = String(candidates[i] || '').trim();
            if (value && value !== '-' && value.toLowerCase() !== 'no qr' && value.toLowerCase() !== 'no barcode') return value;
        }
        return '';
    }

    function code128Svg(value, className, height) {
        value = String(value || '').trim();
        if (!value) return '';

        const patterns = [
            '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
            '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
            '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
            '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
            '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
            '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
            '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
            '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
            '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
            '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
            '114131','311141','411131','211412','211214','211232','2331112'
        ];

        const codes = [104];
        let checksum = 104;
        let position = 1;

        for (let i = 0; i < value.length; i++) {
            let ord = value.charCodeAt(i);
            if (ord < 32 || ord > 126) ord = 32;
            const code = ord - 32;
            codes.push(code);
            checksum += code * position;
            position++;
        }

        codes.push(checksum % 103);
        codes.push(106);

        const moduleWidth = 1.45;
        const quiet = 10;
        let x = quiet;
        let bars = '';

        codes.forEach(function (code) {
            const pattern = patterns[code] || patterns[0];
            let black = true;
            for (let i = 0; i < pattern.length; i++) {
                const width = parseInt(pattern.charAt(i), 10) * moduleWidth;
                if (black) {
                    bars += '<rect x="' + x.toFixed(2) + '" y="0" width="' + width.toFixed(2) + '" height="' + height + '" fill="#000"/>';
                }
                x += width;
                black = !black;
            }
        });

        const totalWidth = x + quiet;
        return '<svg class="' + escapeHtml(className || 'customer-barcode-svg-mini') + '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + totalWidth.toFixed(2) + ' ' + height + '" preserveAspectRatio="none">' + bars + '</svg>';
    }

    function qrContent(qrCode) {
        const list = normalizeBarcodeList(qrCode);
        if (!list.length) return '<span class="customer-barcode-empty"><i data-lucide="barcode" style="width:12px;height:12px"></i> No Barcode</span>';
        const first = list[0];
        const all = list.join(', ');
        const extra = list.length > 1 ? '<span class="customer-barcode-extra">+' + (list.length - 1) + '</span>' : '';
        return '<span class="customer-barcode-chip" title="' + escapeHtml(all) + '">' +
            '<span class="customer-barcode-preview">' + code128Svg(first, 'customer-barcode-svg-mini', 26) + '</span>' +
            '<span class="customer-barcode-code-wrap"><span class="customer-barcode-code">' + escapeHtml(first) + '</span>' + extra + '</span>' +
            '</span>';
    }

    function qrCard(qrCode) {
        const list = normalizeBarcodeList(qrCode);
        if (!list.length) return '<span class="customer-barcode-empty"><i data-lucide="barcode" style="width:12px;height:12px"></i> No Barcode</span>';
        const first = list[0];
        const extraText = list.length > 1 ? ' + ' + (list.length - 1) + ' more' : '';
        return '<div class="customer-barcode-card" title="' + escapeHtml(list.join(', ')) + '">' +
            code128Svg(first, 'customer-barcode-svg-card', 38) +
            '<span class="customer-barcode-text">' + escapeHtml(first) + '</span>' +
            '<span class="customer-barcode-note">From stock_barcodes' + escapeHtml(extraText) + '</span>' +
            '</div>';
    }

    function getCustomerModal() { if (window.bootstrap && window.bootstrap.Modal) { if (!customerModalInstance) customerModalInstance = new window.bootstrap.Modal(customerModalEl, { backdrop: 'static', keyboard: false }); return customerModalInstance; } return null; }
    function getDetailModal() { if (window.bootstrap && window.bootstrap.Modal) { if (!detailModalInstance) detailModalInstance = new window.bootstrap.Modal(detailModalEl); return detailModalInstance; } return null; }
    function openCustomerModal() { const modal = getCustomerModal(); if (modal) modal.show(); setTimeout(function () { const firstInput = document.getElementById('customer_name'); if (firstInput) firstInput.focus(); }, 250); }
    function closeCustomerModal() { const modal = getCustomerModal(); if (modal) modal.hide(); }
    function openDetailModal() { const modal = getDetailModal(); if (modal) modal.show(); }

    function csrfAppend(formData) { const csrfInput = customerForm.querySelector('input[type="hidden"][name*="csrf"], input[type="hidden"][name="_token"]'); if (csrfInput && !formData.has(csrfInput.name)) formData.append(csrfInput.name, csrfInput.value); }

    function resetCustomerForm() {
        customerForm.reset();
        document.getElementById('customer_id').value = '0';
        document.getElementById('opening_outstanding').value = '0.00';
        document.getElementById('loyalty_points').value = '0.00';
        document.getElementById('customer_status').value = '1';
        document.getElementById('customerFormTitle').textContent = 'Add Customer';
        document.getElementById('customerSubmitBtn').innerHTML = 'Save Customer';
    }

    function validateCustomerForm() {
        const customerName = document.getElementById('customer_name').value.trim();
        const mobile = normalizeMobile(mobileInput.value);
        const email = document.getElementById('email').value.trim();
        const gstin = normalizeGstin(gstinInput.value);
        const openingOutstanding = toNumber(document.getElementById('opening_outstanding').value);
        const loyaltyPoints = toNumber(document.getElementById('loyalty_points').value);
        const gstinPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;
        mobileInput.value = mobile;
        gstinInput.value = gstin;
        if (!customerName) { showMessage('error', 'Customer name is required.'); document.getElementById('customer_name').focus(); return false; }
        if (mobile && !/^[6-9][0-9]{9}$/.test(mobile)) { showMessage('error', 'Mobile number must be exactly 10 digits and start with 6, 7, 8, or 9.'); mobileInput.focus(); return false; }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showMessage('error', 'Enter a valid email address.'); document.getElementById('email').focus(); return false; }
        if (gstin && !gstinPattern.test(gstin)) { showMessage('error', 'Enter a valid GSTIN. Sample: 33ABCDE1234F1Z5.'); gstinInput.focus(); return false; }
        if (openingOutstanding < 0 || loyaltyPoints < 0) { showMessage('error', 'Opening outstanding and loyalty points cannot be negative.'); return false; }
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
        document.getElementById('opening_outstanding').value = toNumber(customer.opening_outstanding).toFixed(2);
        document.getElementById('loyalty_points').value = toNumber(customer.loyalty_points).toFixed(2);
        document.getElementById('customer_status').value = String(customer.status ?? 1);
        document.getElementById('customerFormTitle').textContent = 'Edit Customer';
        document.getElementById('customerSubmitBtn').innerHTML = 'Update Customer';
        openCustomerModal();
    }

    function csrfToken() {
        const input = document.querySelector('#customerSecurityForm input[name="csrf_token"], #customerSecurityForm input[name="_token"], #customerSecurityForm input[type="hidden"], #customerForm input[name*="csrf"], #customerForm input[name="_token"]');
        return input ? input.value : '';
    }

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
        const formData = payload instanceof FormData ? payload : new FormData();
        if (!(payload instanceof FormData)) {
            Object.keys(payload || {}).forEach(function (key) {
                formData.append(key, payload[key]);
            });
        }
        if (csrfToken() && !formData.has('csrf_token')) {
            formData.append('csrf_token', csrfToken());
        }
        csrfAppend(formData);
        const response = await fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            body: formData
        });
        return await response.json();
    }

    function renderStats(stats) {
        document.getElementById('totalCustomers').textContent = parseInt(stats.total_customers || 0, 10);
        document.getElementById('activeCustomers').textContent = parseInt(stats.active_customers || 0, 10);
        document.getElementById('currentOutstandingTotal').textContent = money.format(toNumber(stats.current_total));
        document.getElementById('loyaltyPointsTotal').textContent = toNumber(stats.loyalty_total).toFixed(2);
        document.getElementById('outstandingCustomers').textContent = parseInt(stats.outstanding_customers || 0, 10);
    }

    function pendingBillArray(customer) {
        if (Array.isArray(customer.pending_bills)) return customer.pending_bills;
        const refs = String(customer.pending_bill_refs || '').trim();
        if (!refs) return [];
        return refs.split('||').map(function (part) {
            const pieces = part.split('::');
            return {
                bill_id: pieces[0] || '',
                bill_no: pieces[1] || '',
                bill_date: pieces[2] || '',
                balance_amount: pieces[3] || 0
            };
        }).filter(function (row) { return row.bill_no || row.bill_id; });
    }

    function pendingBillCount(customer) {
        const direct = parseInt(customer.pending_bill_count || 0, 10);
        if (direct > 0) return direct;
        return pendingBillArray(customer).length;
    }

    function pendingBillAmount(customer) {
        const direct = toNumber(customer.pending_bill_amount);
        if (direct > 0) return direct;
        return pendingBillArray(customer).reduce(function (sum, row) { return sum + toNumber(row.balance_amount); }, 0);
    }

    function billsCell(customer) {
        const bills = parseInt(customer.bill_count || 0, 10);
        const totalSales = money.format(toNumber(customer.total_purchase_amount));
        const pendingCount = pendingBillCount(customer);
        const pendingAmount = pendingBillAmount(customer);
        let html = '<div class="customer-bill-box">' +
            '<span class="mp-badge badge-count">' + bills + ' Bills</span>' +
            '<div class="mp-sub">Sales ' + totalSales + '</div>';

        // Display only the status inside the Bills column. Amount and bill numbers are hidden
        // to keep the content inside the same column without overflowing into the next column.
        if (pendingCount > 0 || pendingAmount > 0) {
            html += '<div class="pending-bill-stack">' +
                '<span class="pending-bill-chip" title="This customer has pending bill(s)">Status: Pending</span>' +
            '</div>';
        }

        return html + '</div>';
    }

    function customerDeleteBlockReason(status, pendingAmount, pendingCount) {
        if (parseInt(status, 10) === 1) {
            return 'Please mark this customer as Inactive before deleting permanently.';
        }
        if (toNumber(pendingAmount) > 0 || parseInt(pendingCount || 0, 10) > 0) {
            return 'This customer cannot be deleted because there are pending bills.';
        }
        return '';
    }

    function actionButtons(customerId, status, pendingAmount, pendingCount) {
        const isActive = parseInt(status, 10) === 1;
        const pending = toNumber(pendingAmount);
        const pCount = parseInt(pendingCount || 0, 10);
        const toggleText = isActive ? 'Deactivate' : 'Activate';
        const toggleIcon = isActive ? 'user-x' : 'user-check';
        const toggleClass = isActive ? 'action-toggle-active' : 'action-toggle-inactive';
        const deleteReason = customerDeleteBlockReason(status, pending, pCount);
        const deleteBlocked = deleteReason !== '';
        const deleteTitle = deleteBlocked ? deleteReason : 'Delete customer';
        return `<div class="customer-action-wrap" role="group" aria-label="Customer actions">
            <button type="button" class="customer-action-btn action-view js-view" data-id="${customerId}" title="View purchases" aria-label="View purchases">
                <i data-lucide="eye"></i><span class="customer-action-text">View</span>
            </button>
            ${canEdit ? `<button type="button" class="customer-action-btn action-edit js-edit" data-id="${customerId}" title="Edit customer" aria-label="Edit customer">
                <i data-lucide="pencil"></i><span class="customer-action-text">Edit</span>
            </button>` : ''}
            ${canEdit ? `<button type="button" class="customer-action-btn ${toggleClass} js-toggle" data-id="${customerId}" title="${toggleText} customer" aria-label="${toggleText} customer">
                <i data-lucide="${toggleIcon}"></i><span class="customer-action-text">${toggleText}</span>
            </button>` : ''}
            ${canDelete ? `<button type="button" class="customer-action-btn action-delete js-delete ${deleteBlocked ? 'blocked-delete' : ''}" data-id="${customerId}" data-status="${parseInt(status, 10)}" data-pending="${pending}" data-pending-bills="${pCount}" title="${deleteTitle}" aria-label="${deleteTitle}">
                <i data-lucide="trash-2"></i><span class="customer-action-text">Delete</span>
            </button>` : ''}
        </div>`;
    }

    function renderCustomers(customers) {
        if (!customers || !customers.length) {
            tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">No customers found.</td></tr>';
            mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">No customers found.</div>';
            return;
        }
        tableBody.innerHTML = customers.map(function (customer) {
            const customerId = parseInt(customer.customer_id || 0, 10);
            const currentOutstanding = money.format(toNumber(customer.current_outstanding));
            const loyaltyPoints = toNumber(customer.loyalty_points).toFixed(2);
            const bills = parseInt(customer.bill_count || 0, 10);
            const articleCount = parseInt(customer.purchased_article_count || 0, 10);
            const qty = toNumber(customer.total_purchased_qty).toFixed(2);
            const latest = (customer.latest_article || '-') + (customer.latest_size ? ' / Size ' + customer.latest_size : '') + (customer.latest_color ? ' / ' + customer.latest_color : '');
            return `<tr>
                <td><div class="d-flex align-items-center gap-2"><div class="mp-avatar">${customerInitial(customer.customer_name)}</div><div><div class="mp-title">${escapeHtml(customer.customer_name)}</div><div class="mp-sub">ID: ${customerId}</div><div class="mp-sub">${escapeHtml(customer.email || '-')}</div></div></div></td>
                <td><div class="fw-bold">${escapeHtml(customer.mobile || '-')}</div><div class="mp-sub">${escapeHtml(customer.gstin || '-')}</div></td>
                <td>${billsCell(customer)}</td>
                <td><span class="mp-badge badge-code">${articleCount} Articles</span><div class="mp-sub">Qty ${qty}</div></td>
                <td><span class="mp-badge badge-type" title="${escapeHtml(latest)}">${escapeHtml(latest)}</span></td>
                <td>${stockBadge(customer.latest_available_qty)}</td>
                <td>${qrContent(customerBarcodeValue(customer))}</td>
                <td><div class="fw-bold ${toNumber(customer.current_outstanding) > 0 ? 'amount-due' : 'amount-good'}">${currentOutstanding}</div><div class="mp-sub">Opening ${money.format(toNumber(customer.opening_outstanding))}</div></td>
                <td class="fw-bold">${loyaltyPoints}</td>
                <td>${statusBadge(customer.status)}</td>
                <td>${actionButtons(customerId, customer.status, pendingBillAmount(customer), pendingBillCount(customer))}</td>
            </tr>`;
        }).join('');

        mobileCards.innerHTML = customers.map(function (customer) {
            const customerId = parseInt(customer.customer_id || 0, 10);
            const latest = (customer.latest_article || '-') + (customer.latest_size ? ' / Size ' + customer.latest_size : '') + (customer.latest_color ? ' / ' + customer.latest_color : '');
            return `<div class="mp-mobile-card">
                <div class="d-flex gap-2"><div class="mp-avatar">${customerInitial(customer.customer_name)}</div><div class="flex-grow-1 min-width-0">
                    <div class="d-flex justify-content-between gap-2"><div><div class="mp-title">${escapeHtml(customer.customer_name)}</div><div class="mp-sub">${escapeHtml(customer.mobile || '-')}</div></div>${statusBadge(customer.status)}</div>
                    <div class="d-flex flex-wrap gap-1 mt-2"><span class="mp-badge badge-count">${parseInt(customer.bill_count || 0, 10)} Bills</span>${pendingBillCount(customer) > 0 ? '<span class="pending-bill-chip">Status: Pending</span>' : ''}<span class="mp-badge badge-code">${parseInt(customer.purchased_article_count || 0, 10)} Articles</span>${stockBadge(customer.latest_available_qty)}${qrContent(customerBarcodeValue(customer))}</div>
                    <div class="mp-sub mt-2" title="${escapeHtml(latest)}">Latest: ${escapeHtml(latest)}</div>
                    <div class="fw-bold mt-1">Outstanding: ${money.format(toNumber(customer.current_outstanding))}</div>
                    <div class="mp-sub">Qty Purchased: ${toNumber(customer.total_purchased_qty).toFixed(2)} · Loyalty: ${toNumber(customer.loyalty_points).toFixed(2)}</div>
                    <div class="d-flex flex-wrap gap-2 mt-2">${actionButtons(customerId, customer.status, pendingBillAmount(customer), pendingBillCount(customer))}</div>
                </div></div>
            </div>`;
        }).join('');
    }

    function detailBox(label, value) { return '<div class="customer-detail-box"><div class="mp-stat-label">' + escapeHtml(label) + '</div><div class="mp-title mt-1">' + value + '</div></div>'; }

    function renderPurchaseRows(rows) {
        if (!rows || !rows.length) return '<div class="text-muted small p-3">No purchased articles found for this customer.</div>';
        return '<div class="purchase-scroll table-responsive"><table class="table mp-table mb-0"><thead><tr><th>Bill</th><th>Article / Product</th><th>Brand</th><th>Colour</th><th>Size</th><th>Qty Purchased</th><th>Current Available</th><th>Remaining After Sales</th><th>Barcode</th><th>Amount</th></tr></thead><tbody>' +
            rows.map(function (row) {
                const billText = (row.bill_no || '-') + '<div class="mp-sub">' + escapeHtml(row.bill_date || '-') + (row.bill_time ? ' · ' + escapeHtml(row.bill_time) : '') + '</div>';
                const product = '<div class="mp-title">' + escapeHtml(row.article_no || '-') + '</div><div class="mp-sub">' + escapeHtml(row.article_name || '-') + '</div>';
                return '<tr>' +
                    '<td>' + billText + paymentBadge(row.payment_status) + '</td>' +
                    '<td>' + product + '</td>' +
                    '<td>' + escapeHtml(row.brand_name || '-') + '</td>' +
                    '<td><span class="mp-badge badge-type">' + escapeHtml(row.color || '-') + '</span></td>' +
                    '<td><span class="mp-badge badge-code">' + escapeHtml(row.size || '-') + '</span></td>' +
                    '<td class="fw-bold">' + toNumber(row.purchased_qty).toFixed(2) + '</td>' +
                    '<td>' + stockBadge(row.current_available_stock) + '</td>' +
                    '<td><div class="fw-bold">' + toNumber(row.remaining_stock_after_previous_sales).toFixed(2) + '</div><div class="mp-sub">Sold from batch ' + toNumber(row.sold_from_stock_qty).toFixed(2) + ' / Original ' + toNumber(row.original_stock_qty).toFixed(2) + '</div></td>' +
                    '<td>' + qrCard(customerBarcodeValue(row)) + '</td>' +
                    '<td><strong>' + money.format(toNumber(row.amount)) + '</strong><div class="mp-sub">Rate ' + money.format(toNumber(row.selling_rate)) + '</div></td>' +
                '</tr>';
            }).join('') + '</tbody></table></div>';
    }

    function renderBillRows(rows) {
        if (!rows || !rows.length) return '<div class="text-muted small">No bills found.</div>';
        return '<div class="table-responsive mt-3"><table class="table mp-table mb-0"><thead><tr><th>Bill No</th><th>Date</th><th>Net</th><th>Paid</th><th>Due</th><th>Status</th></tr></thead><tbody>' + rows.map(function (b) {
            return '<tr><td><span class="mp-badge badge-code">' + escapeHtml(b.bill_no || '-') + '</span></td><td>' + escapeHtml(b.bill_date || '-') + '</td><td>' + money.format(toNumber(b.net_amount)) + '</td><td class="amount-good">' + money.format(toNumber(b.paid_amount)) + '</td><td class="amount-due">' + money.format(toNumber(b.balance_amount)) + '</td><td>' + paymentBadge(b.payment_status) + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }

    function renderLedgerRows(rows) {
        if (!rows || !rows.length) return '<div class="text-muted small">No ledger entries found.</div>';
        return '<div class="ledger-scroll table-responsive mt-3"><table class="table mp-table mb-0"><thead><tr><th>Type</th><th>Bill/Ref</th><th>Debit</th><th>Credit</th><th>Balance</th><th>Remarks</th><th>Date</th></tr></thead><tbody>' + rows.map(function (r) {
            return '<tr><td><span class="mp-badge badge-type">' + escapeHtml(r.reference_type || '-') + '</span></td><td>' + escapeHtml(r.bill_no || r.reference_id || '-') + '</td><td>' + money.format(toNumber(r.debit)) + '</td><td>' + money.format(toNumber(r.credit)) + '</td><td class="fw-bold">' + money.format(toNumber(r.balance)) + '</td><td>' + escapeHtml(r.remarks || '-') + '</td><td>' + escapeHtml(r.created_at || '-') + '</td></tr>';
        }).join('') + '</tbody></table></div>';
    }

    function filterParams() {
        return {
            action: 'list',
            search: document.getElementById('search').value,
            status: document.getElementById('status').value,
            page: currentPage,
            per_page: perPage
        };
    }

    function normalizeCustomerPayload(data) {
        const raw = data && data.customers !== undefined ? data.customers : [];
        if (raw && Array.isArray(raw.items)) {
            return {
                items: raw.items,
                pagination: raw.pagination || { total: raw.items.length, page: currentPage, total_pages: 1 }
            };
        }
        const rows = Array.isArray(raw) ? raw : [];
        const total = rows.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        if (currentPage > pages) currentPage = pages;
        const start = (currentPage - 1) * perPage;
        return {
            items: rows.slice(start, start + perPage),
            pagination: { total: total, page: currentPage, total_pages: pages }
        };
    }

    function setPagination(pagination) {
        const total = parseInt(pagination.total || 0, 10);
        currentPage = parseInt(pagination.page || currentPage || 1, 10);
        totalPages = parseInt(pagination.total_pages || 1, 10);
        if (totalPages < 1) totalPages = 1;
        document.getElementById('paginationInfo').textContent = 'Page ' + currentPage + ' of ' + totalPages + ' · Total ' + total + ' customers';
        document.getElementById('prevPage').disabled = currentPage <= 1;
        document.getElementById('nextPage').disabled = currentPage >= totalPages;
    }

    async function loadCustomers() {
        tableBody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-4">Loading customers...</td></tr>';
        mobileCards.innerHTML = '<div class="mp-mobile-card text-center text-muted">Loading customers...</div>';
        try {
            const data = await apiGet(filterParams());
            if (!data.success) {
                showMessage('error', data.message || 'Unable to load customers.');
                return;
            }
            const customerPayload = normalizeCustomerPayload(data);
            renderStats(data.stats || {});
            renderCustomers(customerPayload.items || []);
            setPagination(customerPayload.pagination || {});
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {
            showMessage('error', 'Unable to connect to customer API.');
        }
    }

    async function viewCustomer(customerId) {
        detailSubTitle.textContent = 'Customer #' + customerId;
        detailBody.innerHTML = '<div class="text-center text-muted py-4">Loading live customer purchase data...</div>';
        openDetailModal();
        try {
            const data = await apiGet({ action: 'get', customer_id: customerId });
            if (!data.success) { detailBody.innerHTML = '<div class="text-danger">' + escapeHtml(data.message || 'Customer not found.') + '</div>'; return; }
            const c = data.customer || {};
            const purchases = data.purchased_articles || [];
            const totalQty = purchases.reduce(function (sum, row) { return sum + toNumber(row.purchased_qty); }, 0);
            const totalAmount = purchases.reduce(function (sum, row) { return sum + toNumber(row.amount); }, 0);
            detailSubTitle.textContent = (c.customer_name || '-') + ' · ' + (c.mobile || 'No mobile') + ' · ' + purchases.length + ' purchased item rows';
            detailBody.innerHTML = '<div class="customer-detail-grid">' +
                detailBox('Customer', escapeHtml(c.customer_name || '-')) +
                detailBox('Mobile / Email', escapeHtml(c.mobile || '-') + '<div class="mp-sub">' + escapeHtml(c.email || '-') + '</div>') +
                detailBox('GSTIN', escapeHtml(c.gstin || '-')) +
                detailBox('Outstanding', '<span class="' + (toNumber(c.current_outstanding) > 0 ? 'amount-due' : 'amount-good') + '">' + money.format(toNumber(c.current_outstanding)) + '</span>') +
                detailBox('Pending Bills', '<span class="' + (pendingBillCount(c) > 0 ? 'amount-due' : 'amount-good') + '">' + pendingBillCount(c) + ' / ' + money.format(pendingBillAmount(c)) + '</span>') +
                detailBox('Bills', String((data.bills || []).length)) +
                detailBox('Purchased Qty', totalQty.toFixed(2)) +
                detailBox('Purchase Value', money.format(totalAmount)) +
                detailBox('Loyalty Points', toNumber(c.loyalty_points).toFixed(2)) +
            '</div>' +
            '<div class="live-note mt-3">This section is loaded live from bills, bill_items, stock_inward_items, stock_barcodes and customer ledger. It does not use manual or dummy customer product data.</div>' +
            '<h6 class="fw-bold mt-4 mb-2">Purchased Articles, Colour, Size, Quantity, Stock and Barcode</h6>' + renderPurchaseRows(purchases) +
            '<h6 class="fw-bold mt-4 mb-2">Customer Bills</h6>' + renderBillRows(data.bills || []) +
            '<h6 class="fw-bold mt-4 mb-2">Customer Ledger</h6>' + renderLedgerRows(data.ledger || []);
            if (window.lucide) window.lucide.createIcons();
        } catch (error) { detailBody.innerHTML = '<div class="text-danger">Unable to fetch customer purchase details.</div>'; }
    }

    async function editCustomer(customerId) { try { const data = await apiGet({ action: 'get', customer_id: customerId }); if (!data.success) { showMessage('error', data.message || 'Customer not found.'); return; } fillCustomerForm(data.customer); } catch (error) { showMessage('error', 'Unable to fetch customer details.'); } }
    async function toggleCustomerStatus(customerId) { if (!confirm('Change customer status?')) return; const formData = new FormData(); formData.append('action', 'toggle_status'); formData.append('customer_id', customerId); const data = await apiPost(formData); showMessage(data.success ? 'success' : 'error', data.message || 'Status update failed.'); if (data.success) await loadCustomers(); }
    async function deleteCustomer(customerId, pendingAmount, status, pendingBills) {
        let pending = toNumber(pendingAmount);
        let customerStatus = parseInt(status || 1, 10);
        let pBills = parseInt(pendingBills || 0, 10);

        let blockReason = customerDeleteBlockReason(customerStatus, pending, pBills);
        if (blockReason) {
            showMessage('error', blockReason);
            return;
        }

        // Re-check from backend before showing confirmation, because table data may be old.
        try {
            const check = await apiGet({ action: 'get', customer_id: customerId });
            if (!check.success) {
                showMessage('error', check.message || 'Unable to verify customer balance.');
                return;
            }
            const c = check.customer || {};
            customerStatus = parseInt(c.status || customerStatus, 10);
            pending = pendingBillAmount(c);
            pBills = pendingBillCount(c);
            blockReason = customerDeleteBlockReason(customerStatus, pending, pBills);
            if (blockReason) {
                showMessage('error', blockReason);
                await loadCustomers();
                return;
            }
        } catch (error) {
            showMessage('error', 'Unable to verify customer pending bills.');
            return;
        }

        if (!confirm('Delete this inactive customer permanently? Pending bills are cleared/cancelled. This action cannot be undone.')) return;

        const formData = new FormData();
        formData.append('action', 'delete_customer');
        formData.append('customer_id', customerId);
        const data = await apiPost(formData);
        showMessage(data.success ? 'success' : 'error', data.message || 'Delete failed.');
        if (data.success) await loadCustomers();
    }

    const addBtn = document.getElementById('openCustomerModalBtn');
    if (addBtn) addBtn.addEventListener('click', function () { if (!canCreate) { showMessage('error', 'You do not have permission to create customers.'); return; } resetCustomerForm(); openCustomerModal(); });
    document.getElementById('resetCustomerPage').addEventListener('click', function () {
        document.getElementById('search').value = '';
        document.getElementById('status').value = '';
        resetCustomerForm();
        currentPage = 1;
        loadCustomers();
    });
    filterForm.addEventListener('submit', function (event) { event.preventDefault(); currentPage = 1; loadCustomers(); });
    document.getElementById('search').addEventListener('input', function () { window.clearTimeout(searchTimer); searchTimer = window.setTimeout(function () { currentPage = 1; loadCustomers(); }, 300); });
    document.getElementById('status').addEventListener('change', function () { currentPage = 1; loadCustomers(); });
    document.getElementById('prevPage').addEventListener('click', function () { if (currentPage > 1) { currentPage--; loadCustomers(); } });
    document.getElementById('nextPage').addEventListener('click', function () { if (currentPage < totalPages) { currentPage++; loadCustomers(); } });
    if (mobileInput) mobileInput.addEventListener('input', function () { this.value = normalizeMobile(this.value); });
    if (gstinInput) gstinInput.addEventListener('input', function () { this.value = normalizeGstin(this.value); });

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
            if (data.success) { resetCustomerForm(); closeCustomerModal(); await loadCustomers(); }
        } catch (error) { showMessage('error', 'Unable to save customer.'); }
        finally { submitBtn.disabled = false; submitBtn.innerHTML = readyText; }
    });

    document.addEventListener('click', function (event) {
        const viewBtn = event.target.closest('.js-view');
        const editBtn = event.target.closest('.js-edit');
        const toggleBtn = event.target.closest('.js-toggle');
        const deleteBtn = event.target.closest('.js-delete');
        if (viewBtn) viewCustomer(viewBtn.dataset.id);
        if (editBtn) editCustomer(editBtn.dataset.id);
        if (toggleBtn) toggleCustomerStatus(toggleBtn.dataset.id);
        if (deleteBtn) deleteCustomer(deleteBtn.dataset.id, deleteBtn.dataset.pending, deleteBtn.dataset.status, deleteBtn.dataset.pendingBills);
    });

    loadCustomers();
})();
</script>
</body>
</html>
