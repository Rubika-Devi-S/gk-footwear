<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

require_business_login();
require_page_access($conn, 'stock-inward.php');

$businessId = (int) current_business_id();
$batchId = (int)($_GET['batch_id'] ?? 0);
if ($businessId <= 0 || $batchId <= 0) {
    die('Invalid stock inward batch.');
}

function si_col_exists(mysqli $conn, string $table, string $column): bool
{
    if (function_exists('table_has_column')) {
        return table_has_column($conn, $table, $column);
    }
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ((int)($row['total'] ?? 0)) > 0;
}

$invoiceNoSelect = si_col_exists($conn, 'stock_inward_batches', 'invoice_number') ? 'b.invoice_number' : 'NULL AS invoice_number';
$invoiceDateSelect = si_col_exists($conn, 'stock_inward_batches', 'invoice_date') ? 'b.invoice_date' : 'NULL AS invoice_date';
$stmt = mysqli_prepare($conn, "
    SELECT b.*, {$invoiceNoSelect}, {$invoiceDateSelect}, br.branch_name, br.floor_name, s.supplier_name, s.mobile AS supplier_mobile,
           COALESCE(u.name, 'System') AS created_by_name
    FROM stock_inward_batches b
    LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
    LEFT JOIN suppliers s ON s.supplier_id = b.supplier_id AND s.business_id = b.business_id
    LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id
    WHERE b.business_id = ? AND b.batch_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'ii', $businessId, $batchId);
mysqli_stmt_execute($stmt);
$batch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$batch) {
    die('Stock inward batch not found.');
}

$stmt = mysqli_prepare($conn, "
    SELECT i.*, c.category_name, bd.brand_name, sb.barcode_value
    FROM stock_inward_items i
    LEFT JOIN categories c ON c.category_id = i.category_id AND c.business_id = i.business_id
    LEFT JOIN brands bd ON bd.brand_id = i.brand_id AND bd.business_id = i.business_id
    LEFT JOIN stock_barcodes sb ON sb.stock_item_id = i.stock_item_id AND sb.barcode_status <> 'deleted'
    WHERE i.business_id = ? AND i.batch_id = ?
    ORDER BY i.stock_item_id ASC
");
mysqli_stmt_bind_param($stmt, 'ii', $businessId, $batchId);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);
$items = [];
while ($row = mysqli_fetch_assoc($rs)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock Inward Print - <?= e($batch['batch_no']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; color:#111827; margin:18px; font-size:12px; }
        h1 { font-size:20px; margin:0 0 4px; }
        .muted { color:#64748b; }
        .grid { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; margin:12px 0; }
        .box { border:1px solid #e2e8f0; border-radius:10px; padding:8px; }
        .label { font-size:10px; color:#64748b; font-weight:bold; text-transform:uppercase; }
        .value { font-size:13px; font-weight:bold; margin-top:2px; }
        table { width:100%; border-collapse:collapse; margin-top:12px; }
        th, td { border:1px solid #e2e8f0; padding:7px; text-align:left; }
        th { background:#f1f5f9; font-size:10px; text-transform:uppercase; }
        .text-end { text-align:right; }
        @media print { .no-print { display:none; } body { margin:0; } }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print</button>
    <h1>Stock Inward Batch</h1>
    <div class="muted">Batch-wise inward report for supplier stock entry.</div>
    <div class="grid">
        <div class="box"><div class="label">Batch Number</div><div class="value"><?= e($batch['batch_no']) ?></div></div>
        <div class="box"><div class="label">Inward Date</div><div class="value"><?= e(date('d-m-Y', strtotime($batch['inward_date']))) ?></div></div>
        <div class="box"><div class="label">Branch / Firm</div><div class="value"><?= e($batch['branch_name'] ?? '-') ?></div></div>
        <div class="box"><div class="label">Supplier</div><div class="value"><?= e($batch['supplier_name'] ?? '-') ?></div></div>
        <div class="box"><div class="label">Invoice Number</div><div class="value"><?= e($batch['invoice_number'] ?: '-') ?></div></div>
        <div class="box"><div class="label">Invoice Date</div><div class="value"><?= !empty($batch['invoice_date']) ? e(date('d-m-Y', strtotime($batch['invoice_date']))) : '-' ?></div></div>
        <div class="box"><div class="label">Total Quantity</div><div class="value"><?= number_format((float)$batch['total_qty'], 2) ?></div></div>
        <div class="box"><div class="label">Total Value</div><div class="value">₹<?= number_format((float)$batch['selling_total_value'], 2) ?></div></div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Category</th><th>Brand</th><th>Article No</th><th>Article Name</th><th>Size</th><th>Color</th><th>Qty</th><th>Purchase</th><th>MRP</th><th>Selling</th><th>Barcode</th></tr></thead>
        <tbody>
        <?php foreach ($items as $i => $item): ?>
            <tr>
                <td><?= $i + 1 ?></td><td><?= e($item['category_name'] ?? '-') ?></td><td><?= e($item['brand_name'] ?? '-') ?></td><td><?= e($item['article_no']) ?></td><td><?= e($item['article_name']) ?></td><td><?= e($item['size']) ?></td><td><?= e($item['color']) ?></td><td class="text-end"><?= number_format((float)$item['qty'], 2) ?></td><td class="text-end">₹<?= number_format((float)$item['purchase_rate'], 2) ?></td><td class="text-end">₹<?= number_format((float)$item['mrp_rate'], 2) ?></td><td class="text-end">₹<?= number_format((float)$item['selling_rate'], 2) ?></td><td><?= e($item['barcode_value'] ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
