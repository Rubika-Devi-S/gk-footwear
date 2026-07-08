<?php
/**
 * Universal Footwear POS - Return / Exchange Invoice Print
 * Place at: business/return-exchange-print.php
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

if (function_exists('require_business_login')) {
    require_business_login();
}

if (function_exists('require_page_access')) {
    require_page_access($conn, 'return-exchange.php');
}

if (!function_exists('e')) {
    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function rei_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int) current_business_id();
    }

    return (int)($_SESSION['business_id'] ?? 0);
}

function rei_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException(mysqli_error($conn));
    }

    if ($types !== '' && $params) {
        $refs = [$stmt, $types];
        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', $refs);
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function rei_one(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = rei_rows($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

$businessId = rei_business_id();
$id = (int)($_GET['id'] ?? 0);

if ($businessId <= 0 || $id <= 0) {
    die('Invalid return/exchange invoice.');
}

$nameExpr = "COALESCE(NULLIF(u.full_name,''), NULLIF(u.name,''), NULLIF(u.username,''), 'System')";

$header = rei_one($conn, "
    SELECT
        reh.*,
        b.bill_date,
        b.bill_time,
        br.branch_name,
        br.floor_name,
        $nameExpr AS created_by_name
    FROM return_exchange_headers reh
    LEFT JOIN bills b
        ON b.business_id = reh.business_id
       AND b.bill_id = reh.bill_id
    LEFT JOIN branches br
        ON br.business_id = reh.business_id
       AND br.branch_id = reh.branch_id
    LEFT JOIN users u
        ON u.business_id = reh.business_id
       AND u.user_id = reh.created_by
    WHERE reh.business_id = ?
      AND reh.return_exchange_id = ?
    LIMIT 1
", 'ii', [$businessId, $id]);

if (!$header) {
    die('Return/exchange invoice not found.');
}

$items = rei_rows($conn, "
    SELECT *
    FROM return_exchange_items
    WHERE business_id = ?
      AND return_exchange_id = ?
    ORDER BY return_exchange_item_id ASC
", 'ii', [$businessId, $id]);

$title = strtoupper((string)$header['transaction_type']) . ' INVOICE';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($title) ?> - <?= e($header['transaction_no']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        *{box-sizing:border-box}body{font-family:Arial,sans-serif;margin:0;background:#f8fafc;color:#111827}.page{width:210mm;min-height:297mm;margin:0 auto;background:#fff;padding:12mm}.header{display:flex;justify-content:space-between;gap:15px;border-bottom:2px solid #111827;padding-bottom:10px}.brand h1{font-size:22px;margin:0}.brand p,.meta p{font-size:12px;margin:3px 0;color:#475569}.title{text-align:right}.title h2{font-size:20px;margin:0}.box-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:12px 0}.box{border:1px solid #dbe4f0;border-radius:10px;padding:8px}.label{font-size:10px;color:#64748b;font-weight:bold;text-transform:uppercase}.value{font-size:13px;font-weight:bold;margin-top:3px}table{width:100%;border-collapse:collapse;margin-top:10px}th{background:#f1f5f9;text-align:left;font-size:11px;text-transform:uppercase;padding:8px;border:1px solid #dbe4f0}td{font-size:12px;padding:8px;border:1px solid #dbe4f0;vertical-align:top}.right{text-align:right}.summary{width:85mm;margin-left:auto;margin-top:14px}.summary td{border:0;border-bottom:1px solid #e2e8f0}.footer{margin-top:25px;display:flex;justify-content:space-between;font-size:12px}.toolbar{position:sticky;top:0;background:#fff;border-bottom:1px solid #e2e8f0;padding:10px;display:flex;gap:8px;justify-content:flex-end}.btn{border:0;background:#111827;color:#fff;border-radius:999px;padding:8px 14px;font-weight:bold;cursor:pointer}@media print{.toolbar{display:none}.page{margin:0;padding:10mm;box-shadow:none}@page{size:A4;margin:0}}
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn" onclick="window.print()">Print</button>
        <button class="btn" onclick="window.close()">Close</button>
    </div>
    <div class="page">
        <div class="header">
            <div class="brand">
                <h1>GK FOOTWEAR</h1>
                <p>Business POS Panel</p>
                <p><?= e(($header['branch_name'] ?? '-') . (($header['floor_name'] ?? '') ? ' / ' . $header['floor_name'] : '')) ?></p>
            </div>
            <div class="title">
                <h2><?= e($title) ?></h2>
                <p><b><?= e($header['transaction_no']) ?></b></p>
                <p><?= e($header['created_at']) ?></p>
            </div>
        </div>

        <div class="box-grid">
            <div class="box"><div class="label">Customer</div><div class="value"><?= e($header['customer_name'] ?: 'Walk-in Customer') ?></div><div><?= e($header['customer_mobile'] ?: '-') ?></div></div>
            <div class="box"><div class="label">Original Bill</div><div class="value"><?= e($header['bill_no']) ?></div><div><?= e($header['bill_date'] ?? '-') ?> <?= e($header['bill_time'] ?? '') ?></div></div>
            <div class="box"><div class="label">Processed By</div><div class="value"><?= e($header['created_by_name'] ?? '-') ?></div><div><?= e($header['refund_option'] ?? '-') ?></div></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Returned Product</th>
                    <th class="right">Return Qty</th>
                    <th class="right">Return Amount</th>
                    <th>New Product</th>
                    <th class="right">New Qty</th>
                    <th class="right">New Amount</th>
                    <th class="right">Difference</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e(ucfirst((string)$item['item_type'])) ?></td>
                        <td>
                            <b><?= e($item['old_article_no']) ?></b><br>
                            <?= e($item['old_article_name']) ?><br>
                            Size: <?= e($item['old_size']) ?> / <?= e($item['old_color'] ?: '-') ?>
                        </td>
                        <td class="right"><?= number_format((float)$item['return_qty'], 2) ?></td>
                        <td class="right">₹<?= number_format((float)$item['return_amount'], 2) ?></td>
                        <td>
                            <?php if ($item['item_type'] === 'exchange'): ?>
                                <b><?= e($item['new_article_no']) ?></b><br>
                                <?= e($item['new_article_name']) ?><br>
                                Size: <?= e($item['new_size']) ?> / <?= e($item['new_color'] ?: '-') ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="right"><?= $item['item_type'] === 'exchange' ? number_format((float)$item['new_qty'], 2) : '-' ?></td>
                        <td class="right"><?= $item['item_type'] === 'exchange' ? '₹' . number_format((float)$item['new_amount'], 2) : '-' ?></td>
                        <td class="right">₹<?= number_format((float)$item['price_difference'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table class="summary">
            <tr><td>Returned Amount</td><td class="right">₹<?= number_format((float)$header['return_amount'], 2) ?></td></tr>
            <tr><td>New Product Amount</td><td class="right">₹<?= number_format((float)$header['new_amount'], 2) ?></td></tr>
            <tr><td>Refund Amount</td><td class="right">₹<?= number_format((float)$header['refund_amount'], 2) ?></td></tr>
            <tr><td>Extra Collected</td><td class="right">₹<?= number_format((float)$header['extra_collect_amount'], 2) ?></td></tr>
            <tr><td>Store Credit</td><td class="right">₹<?= number_format((float)$header['store_credit_amount'], 2) ?></td></tr>
            <tr><td><b>Net Difference</b></td><td class="right"><b>₹<?= number_format((float)$header['net_difference'], 2) ?></b></td></tr>
        </table>

        <div class="footer">
            <div>Customer Signature</div>
            <div>Authorized Signature</div>
        </div>
    </div>
</body>
</html>
