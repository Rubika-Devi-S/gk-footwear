<?php
/**
 * GK Footwear POS - Payment Ledger Report Print
 * Place at project root / business folder: payment-ledger-report-print.php
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/controllers/PaymentLedgerReportController.php';

if (function_exists('require_business_login')) { require_business_login(); }
if (function_exists('require_page_access')) { require_page_access($conn, 'payment-ledger-report.php'); }

function plrp_e($value): string
{
    if (function_exists('e')) { return e((string)$value); }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function plrp_user_id(): int
{
    if (function_exists('current_user_id')) { return (int)current_user_id(); }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}
function plrp_business_id(): int
{
    if (function_exists('current_business_id')) { return (int)current_business_id(); }
    return (int)($_SESSION['business_id'] ?? 0);
}
function plrp_is_admin(mysqli $conn): bool
{
    if (function_exists('is_business_admin')) { return (bool)is_business_admin($conn); }
    return ((int)($_SESSION['role_id'] ?? 0) === 1);
}
function plrp_money($value): string
{
    return '₹' . number_format((float)$value, 2);
}

$businessId = plrp_business_id();
if ($businessId <= 0) { die('Business session missing.'); }

$type = (string)($_GET['type'] ?? 'payments');
$valid = array('payments','ledger','outstanding','daily','method','cashier');
if (!in_array($type, $valid, true)) { $type = 'payments'; }

$controller = new PaymentLedgerReportController($conn, $businessId, plrp_user_id(), plrp_is_admin($conn));
$result = $controller->rowsForReport($type, $_GET, false);
$rows = $controller->enrichRowsForExport($result['rows'] ?? array());
$columns = $controller->exportColumns($type);
$summary = $controller->summary($_GET)['summary'] ?? array();

$titleMap = array(
    'payments' => 'Payment Transactions',
    'ledger' => 'Payment Ledger',
    'outstanding' => 'Customer Outstanding',
    'daily' => 'Daily Collection',
    'method' => 'Payment Method Summary',
    'cashier' => 'Cashier Summary',
);
$title = $titleMap[$type] ?? 'Payment Ledger Report';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= plrp_e($title) ?></title>
    <style>
        body{font-family:Arial,sans-serif;font-size:11px;color:#111827;margin:18px}
        .head{display:flex;justify-content:space-between;gap:15px;border-bottom:2px solid #111827;padding-bottom:10px;margin-bottom:12px}
        h1{font-size:20px;margin:0 0 4px}
        .muted{color:#64748b}
        .summary{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:12px 0}
        .card{border:1px solid #cbd5e1;border-radius:8px;padding:8px;background:#f8fafc}
        .card small{display:block;color:#64748b;font-weight:bold;text-transform:uppercase}
        .card b{font-size:15px}
        table{width:100%;border-collapse:collapse}
        th,td{border:1px solid #cbd5e1;padding:6px;vertical-align:top}
        th{background:#f1f5f9;text-transform:uppercase;font-size:10px}
        td.num{text-align:right;white-space:nowrap}
        @media print{.no-print{display:none} body{margin:8px}}
    </style>
</head>
<body>
<div class="no-print" style="text-align:right;margin-bottom:10px"><button onclick="window.print()">Print</button></div>
<div class="head">
    <div>
        <h1><?= plrp_e($title) ?></h1>
        <div class="muted">From <?= plrp_e($_GET['from_date'] ?? '') ?> to <?= plrp_e($_GET['to_date'] ?? '') ?></div>
    </div>
    <div class="muted">Generated: <?= date('d-m-Y h:i A') ?><br>Rows: <?= count($rows) ?></div>
</div>

<div class="summary">
    <div class="card"><small>Transactions</small><b><?= (int)($summary['total_transactions'] ?? 0) ?></b></div>
    <div class="card"><small>Collected</small><b><?= plrp_money($summary['total_collected'] ?? 0) ?></b></div>
    <div class="card"><small>Bill Outstanding</small><b><?= plrp_money($summary['bill_outstanding'] ?? 0) ?></b></div>
    <div class="card"><small>Customer Outstanding</small><b><?= plrp_money($summary['customer_outstanding'] ?? 0) ?></b></div>
</div>

<table>
    <thead><tr><?php foreach ($columns as $titleText => $key): ?><th><?= plrp_e($titleText) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php if (!$rows): ?>
        <tr><td colspan="<?= count($columns) ?>" style="text-align:center;padding:20px">No records found.</td></tr>
    <?php else: ?>
        <?php foreach ($rows as $row): ?>
            <tr>
            <?php foreach ($columns as $titleText => $key): ?>
                <?php $value = $row[$key] ?? ''; $isNum = preg_match('/amount|balance|debit|credit|collected|cash|upi|card|paid|opening|bills|payments|customers|count/i', $titleText); ?>
                <td class="<?= $isNum ? 'num' : '' ?>"><?= plrp_e($value) ?></td>
            <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print();},350);});</script>
</body>
</html>
