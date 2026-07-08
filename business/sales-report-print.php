<?php
/**
 * Universal Footwear POS - Sales Report Print
 * Place at project root / business folder: sales-report-print.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/controllers/SalesReportController.php';

date_default_timezone_set('Asia/Kolkata');
if (isset($conn) && $conn instanceof mysqli) {
    @mysqli_query($conn, "SET time_zone = '+05:30'");
}

if (function_exists('require_business_login')) { require_business_login(); }
if (function_exists('require_page_access')) { require_page_access($conn, 'sales-report.php'); }

function srp_print_e($value): string
{
    if (function_exists('e')) { return e((string)$value); }
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function srp_print_user_id(): int
{
    if (function_exists('current_user_id')) { return (int)current_user_id(); }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}
function srp_print_business_id(): int
{
    if (function_exists('current_business_id')) { return (int)current_business_id(); }
    return (int)($_SESSION['business_id'] ?? 0);
}
function srp_print_is_admin(mysqli $conn): bool
{
    if (function_exists('is_business_admin')) { return (bool)is_business_admin($conn); }
    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));
    return in_array($roleName, array('admin', 'business admin', 'branch admin'), true) || (int)($_SESSION['role_id'] ?? 0) === 1;
}
function srp_money($value): string
{
    return '₹' . number_format((float)$value, 2);
}

$businessId = srp_print_business_id();
if ($businessId <= 0) { die('Business session missing. Please login again.'); }

$type = (string)($_GET['type'] ?? 'bills');
$controller = new SalesReportController($conn, $businessId, srp_print_user_id(), srp_print_is_admin($conn));
$matrix = $controller->exportMatrix($type, $_GET);
$summary = $controller->summary($_GET)['summary'] ?? array();

$titleMap = array(
    'bills' => 'Bill-wise Sales Report',
    'items' => 'Item Sales Report',
    'trend' => 'Daily Sales Trend',
    'branch' => 'Branch Sales Summary',
    'payment' => 'Payment Collection Summary',
    'top_products' => 'Top Products Report',
    'customer' => 'Customer Sales Report',
    'user' => 'Sales User Report',
    'category' => 'Category Sales Report',
    'hourly' => 'Hourly Sales Report',
);
$title = $titleMap[$type] ?? 'Sales Report';
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= srp_print_e($title) ?></title>
    <style>
        body{font-family:Arial, sans-serif;color:#111827;margin:18px;font-size:11px}
        .head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111827;padding-bottom:10px;margin-bottom:12px}
        h1{font-size:20px;margin:0 0 4px}
        .muted{color:#64748b}
        .summary{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin:12px 0}
        .box{border:1px solid #cbd5e1;border-radius:8px;padding:8px}
        .box small{display:block;color:#64748b;font-weight:bold;font-size:9px;text-transform:uppercase}
        .box b{display:block;font-size:13px;margin-top:2px}
        table{width:100%;border-collapse:collapse;margin-top:10px}
        th{background:#e2e8f0;color:#0f172a;font-size:9px;text-transform:uppercase}
        th,td{border:1px solid #cbd5e1;padding:5px;vertical-align:top}
        td.num{text-align:right}
        @media print{.no-print{display:none} body{margin:8px}}
    </style>
</head>
<body>
    <div class="no-print" style="text-align:right;margin-bottom:10px">
        <button onclick="window.print()">Print</button>
        <button onclick="window.close()">Close</button>
    </div>

    <div class="head">
        <div>
            <h1><?= srp_print_e($title) ?></h1>
            <div class="muted">Universal Footwear POS · Generated: <?= date('d-m-Y h:i A') ?></div>
        </div>
        <div class="muted">
            From: <?= srp_print_e($_GET['from_date'] ?? '-') ?><br>
            To: <?= srp_print_e($_GET['to_date'] ?? '-') ?>
        </div>
    </div>

    <div class="summary">
        <div class="box"><small>Bills</small><b><?= number_format((float)($summary['total_bills'] ?? 0)) ?></b></div>
        <div class="box"><small>Net Sales</small><b><?= srp_money($summary['net_sales'] ?? 0) ?></b></div>
        <div class="box"><small>Paid</small><b><?= srp_money($summary['paid_amount'] ?? 0) ?></b></div>
        <div class="box"><small>Balance</small><b><?= srp_money($summary['balance_amount'] ?? 0) ?></b></div>
        <div class="box"><small>Discount</small><b><?= srp_money($summary['discount_total'] ?? 0) ?></b></div>
        <div class="box"><small>Avg Bill</small><b><?= srp_money($summary['average_bill_value'] ?? 0) ?></b></div>
    </div>

    <table>
        <thead>
            <tr>
                <?php foreach ($matrix['headings'] as $heading): ?>
                    <th><?= srp_print_e($heading) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($matrix['rows'])): ?>
                <tr><td colspan="<?= count($matrix['headings']) ?>" style="text-align:center;color:#64748b;padding:20px">No records found.</td></tr>
            <?php else: ?>
                <?php foreach ($matrix['rows'] as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?= srp_print_e($cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <script>window.addEventListener('load', function(){ setTimeout(function(){ window.print(); }, 400); });</script>
</body>
</html>
