<?php
/**
 * GK Footwear POS - Printable Bill with invoice barcode.
 * Upload at project root: bill-print.php
 *
 * Modified to follow Create Bill GST method:
 * - Show GSTIN / CGST / SGST / IGST only when GST is enabled.
 * - Hide complete GST module when GST is OFF.
 * - Keep barcode and barcode number for lookup / return / exchange.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

/*
 * Billing print must always use India time.
 * This also keeps MySQL NOW()/created_at helper queries in the same timezone
 * for this request.
 */
if (!defined('BP_TIMEZONE')) {
    define('BP_TIMEZONE', 'Asia/Kolkata');
}
date_default_timezone_set(BP_TIMEZONE);

if (isset($conn) && $conn instanceof mysqli) {
    @mysqli_query($conn, "SET time_zone = '+05:30'");
}

if (function_exists('require_business_login')) {
    require_business_login();
}

function bp_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function bp_money($value)
{
    return '₹' . number_format((float)$value, 2);
}

function bp_num($value)
{
    return round((float)$value, 2);
}

function bp_normalize_time_value($time)
{
    $time = trim((string)$time);

    if ($time === '' || $time === '00:00:00' || $time === '0000-00-00 00:00:00') {
        return '';
    }

    if (preg_match('/^(\d{1,2}:\d{2})(:\d{2})?/', $time, $m)) {
        return $m[1] . (isset($m[2]) && $m[2] !== '' ? $m[2] : ':00');
    }

    return $time;
}

function bp_bill_print_datetime(array $bill)
{
    $tz = new DateTimeZone(BP_TIMEZONE);
    $date = trim((string)($bill['bill_date'] ?? ''));
    $time = bp_normalize_time_value($bill['bill_time'] ?? '');

    if ($date !== '' && $date !== '0000-00-00') {
        $value = $date . ($time !== '' ? ' ' . $time : '');

        try {
            $dt = new DateTimeImmutable($value, $tz);
            return $dt->format('d-m-Y h:i:s A');
        } catch (Throwable $e) {}
    }

    $createdAt = trim((string)($bill['created_at'] ?? ''));

    if ($createdAt !== '' && $createdAt !== '0000-00-00 00:00:00') {
        try {
            $dt = new DateTimeImmutable($createdAt, $tz);
            return $dt->format('d-m-Y h:i:s A');
        } catch (Throwable $e) {}
    }

    return '-';
}

function bp_current_business_id()
{
    if (function_exists('current_business_id')) {
        return (int)current_business_id();
    }

    return (int)($_SESSION['business_id'] ?? 0);
}

function bp_table_exists(mysqli $conn, $table)
{
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) total
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function bp_column_exists(mysqli $conn, $table, $column)
{
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function bp_fetch_one(mysqli $conn, $sql, $types = '', array $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }

    if ($types !== '') {
        $bind = array($types);

        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }

        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

function bp_fetch_all(mysqli $conn, $sql, $types = '', array $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }

    if ($types !== '') {
        $bind = array($types);

        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }

        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    $rows = array();

    while ($row = mysqli_fetch_assoc($rs)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

function bp_execute(mysqli $conn, $sql, $types = '', array $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }

    if ($types !== '') {
        $bind = array($types);

        foreach ($params as $k => $v) {
            $bind[] = &$params[$k];
        }

        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function bp_barcode_prefix(mysqli $conn, $businessId, $branchId)
{
    $prefix = 'BILL';

    if (bp_table_exists($conn, 'barcode_settings')) {
        $row = bp_fetch_one(
            $conn,
            "SELECT bill_barcode_prefix
             FROM barcode_settings
             WHERE business_id = ?
               AND (branch_id = ? OR branch_id IS NULL)
               AND status = 1
             ORDER BY branch_id IS NULL ASC
             LIMIT 1",
            'ii',
            array($businessId, $branchId)
        );

        if ($row && trim((string)$row['bill_barcode_prefix']) !== '') {
            $prefix = trim((string)$row['bill_barcode_prefix']);
        }
    }

    $prefix = preg_replace('/[^A-Za-z0-9]/', '', $prefix);

    return $prefix !== '' ? strtoupper($prefix) : 'BILL';
}

function bp_ensure_bill_barcode(mysqli $conn, array $bill)
{
    $businessId = (int)$bill['business_id'];
    $branchId = (int)$bill['branch_id'];
    $billId = (int)$bill['bill_id'];

    if (bp_table_exists($conn, 'bill_barcodes')) {
        $row = bp_fetch_one(
            $conn,
            "SELECT barcode_value
             FROM bill_barcodes
             WHERE business_id = ?
               AND branch_id = ?
               AND bill_id = ?
             ORDER BY bill_barcode_id DESC
             LIMIT 1",
            'iii',
            array($businessId, $branchId, $billId)
        );

        if ($row && trim((string)$row['barcode_value']) !== '') {
            return $row['barcode_value'];
        }

        $barcode = bp_barcode_prefix($conn, $businessId, $branchId) . '-' . str_pad((string)$billId, 6, '0', STR_PAD_LEFT);

        bp_execute(
            $conn,
            "INSERT INTO bill_barcodes
                (business_id, branch_id, bill_id, barcode_value, barcode_status, created_at)
             VALUES
                (?, ?, ?, ?, 'active', NOW())",
            'iiis',
            array($businessId, $branchId, $billId, $barcode)
        );

        return $barcode;
    }

    return bp_barcode_prefix($conn, $businessId, $branchId) . '-' . str_pad((string)$billId, 6, '0', STR_PAD_LEFT);
}

function bp_is_system_gst_enabled(array $bill)
{
    $businessGstKey = strtolower(trim((string)($bill['business_gst_type_key'] ?? '')));
    $billGstKey = strtolower(trim((string)($bill['gst_type_key'] ?? '')));

    if (in_array($businessGstKey, array('non_gst', 'no_gst', 'gst_off', 'off', 'none', 'disabled'), true)) {
        return false;
    }

    return $businessGstKey === 'gst_regular'
        || $billGstKey === 'gst_regular'
        || bp_num($bill['tax_amount'] ?? 0) > 0;
}

function bp_is_bill_gst_applied(array $bill)
{
    return bp_is_system_gst_enabled($bill)
        && strtolower(trim((string)($bill['gst_type_key'] ?? ''))) === 'gst_regular'
        && bp_num($bill['tax_amount'] ?? 0) > 0;
}

$businessId = bp_current_business_id();
$billId = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;

if ($businessId <= 0 || $billId <= 0) {
    die('Invalid bill request.');
}

try {
    $businessGstSelect = bp_column_exists($conn, 'businesses', 'gst_type_key')
        ? "bs.gst_type_key AS business_gst_type_key,"
        : "'' AS business_gst_type_key,";

    $bill = bp_fetch_one(
        $conn,
        "SELECT
            b.*,
            br.branch_name,
            br.floor_name,
            br.address AS branch_address,
            br.mobile AS branch_mobile,
            bs.business_name,
            bs.address AS business_address,
            bs.mobile AS business_mobile,
            bs.gstin,
            {$businessGstSelect}
            bb.barcode_value AS saved_bill_barcode
         FROM bills b
         LEFT JOIN branches br
            ON br.branch_id = b.branch_id
           AND br.business_id = b.business_id
         LEFT JOIN businesses bs
            ON bs.business_id = b.business_id
         LEFT JOIN bill_barcodes bb
            ON bb.bill_id = b.bill_id
           AND bb.business_id = b.business_id
           AND bb.branch_id = b.branch_id
           AND bb.barcode_status IN ('active', 'scanned')
         WHERE b.business_id = ?
           AND b.bill_id = ?
           AND COALESCE(b.bill_status, 'active') <> 'deleted'
         ORDER BY bb.bill_barcode_id DESC
         LIMIT 1",
        'ii',
        array($businessId, $billId)
    );

    if (!$bill) {
        die('Bill not found.');
    }

    $items = bp_fetch_all(
        $conn,
        "SELECT
            bi.*,
            COALESCE(br.brand_name, '') AS brand_name
         FROM bill_items bi
         LEFT JOIN brands br
            ON br.brand_id = bi.brand_id
           AND br.business_id = bi.business_id
         WHERE bi.business_id = ?
           AND bi.branch_id = ?
           AND bi.bill_id = ?
         ORDER BY bi.bill_item_id ASC",
        'iii',
        array($businessId, (int)$bill['branch_id'], $billId)
    );

    $payments = bp_table_exists($conn, 'bill_payments')
        ? bp_fetch_all(
            $conn,
            "SELECT
                bp.*,
                pm.payment_method_name,
                pm.method_type
             FROM bill_payments bp
             LEFT JOIN payment_methods pm
                ON pm.payment_method_id = bp.payment_method_id
               AND pm.business_id = bp.business_id
             WHERE bp.business_id = ?
               AND bp.branch_id = ?
               AND bp.bill_id = ?
               AND bp.payment_status = 'received'
             ORDER BY bp.payment_id ASC",
            'iii',
            array($businessId, (int)$bill['branch_id'], $billId)
        )
        : array();

    $barcodeValue = trim((string)($bill['saved_bill_barcode'] ?? ''));
    if ($barcodeValue === '') {
        $barcodeValue = bp_ensure_bill_barcode($conn, $bill);
    }
} catch (Throwable $e) {
    die('Print error: ' . bp_e($e->getMessage()));
}

$autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';
$barcodeSrc = 'barcode-image.php?code=' . rawurlencode($barcodeValue);
$billPrintDateTime = bp_bill_print_datetime($bill);

$systemGstEnabled = bp_is_system_gst_enabled($bill);
$billGstApplied = bp_is_bill_gst_applied($bill);

$taxableAmount = bp_num($bill['taxable_amount'] ?? 0);
if ($taxableAmount <= 0 && $billGstApplied) {
    $taxableAmount = max(0, bp_num($bill['selling_amount'] ?? 0) - bp_num($bill['bill_discount_amount'] ?? 0) - bp_num($bill['loyalty_redeem_amount'] ?? 0));
}

$cgstAmount = $billGstApplied ? bp_num($bill['cgst_amount'] ?? 0) : 0;
$sgstAmount = $billGstApplied ? bp_num($bill['sgst_amount'] ?? 0) : 0;
$igstAmount = $billGstApplied ? bp_num($bill['igst_amount'] ?? 0) : 0;
$taxAmount = $billGstApplied ? bp_num($bill['tax_amount'] ?? 0) : 0;

$paidStatus = strtoupper((string)($bill['payment_status'] ?? 'pending'));
$invoiceTitle = trim((string)($bill['invoice_title'] ?? ''));
if ($invoiceTitle === '') {
    $invoiceTitle = $billGstApplied ? 'Tax Invoice' : 'Bill of Supply';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= bp_e($bill['bill_no']) ?> - Print</title>
<style>
body{margin:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#111827}
.print-wrap{width:78mm;max-width:100%;margin:12px auto;background:#fff;padding:10px 12px;border:1px solid #e5e7eb}
.center{text-align:center}.muted{color:#6b7280;font-size:10px}.title{font-size:17px;font-weight:800;margin:0}.sub{font-size:10px}
.line{border-top:1px dashed #9ca3af;margin:7px 0}
.info{width:100%;font-size:10.5px}.info td{padding:2px 0}.right{text-align:right}
.items{width:100%;border-collapse:collapse;font-size:10px}
.items th,.items td{padding:4px 2px;border-bottom:1px dashed #d1d5db;vertical-align:top}
.items th{text-align:left}
.total-row td{font-weight:800;font-size:12px}
.gst-row td{font-size:10.2px}
.status-pill{display:inline-block;border-radius:999px;padding:3px 8px;font-size:10px;font-weight:800;background:#dcfce7;color:#15803d}
.status-pill.pending{background:#fef3c7;color:#92400e}
.status-pill.partial{background:#dbeafe;color:#1d4ed8}
.status-pill.cancelled{background:#fee2e2;color:#b91c1c}
.barcode-box{margin:9px 0 3px;text-align:center;padding:7px 0;border-top:1px dashed #9ca3af;border-bottom:1px dashed #9ca3af}
.barcode-box img{width:64mm;max-width:100%;height:18mm;object-fit:contain}
.barcode-no{font-size:11px;font-weight:800;letter-spacing:.1em;margin-top:3px}
.actions{text-align:center;margin:10px}
.btn{border:0;border-radius:999px;padding:8px 13px;font-weight:700;background:#0f172a;color:#fff;cursor:pointer}
.btn.secondary{background:#e2e8f0;color:#0f172a}
@media print{
    @page{size:78mm auto;margin:0}
    body{background:#fff}
    .print-wrap{border:0;margin:0;width:78mm;padding:5px 7px}
    .actions{display:none}
    .barcode-box img{height:17mm}
}
</style>
</head>
<body>
<div class="actions">
    <button class="btn" onclick="window.print()">Print</button>
    <button class="btn secondary" onclick="window.close()">Close</button>
</div>

<section class="print-wrap">
    <div class="center">
        <h1 class="title"><?= bp_e($bill['business_name'] ?: 'GK FOOTWEAR') ?></h1>
        <div class="muted"><?= bp_e($bill['business_address'] ?: $bill['branch_address']) ?></div>
        <?php if ($systemGstEnabled && !empty($bill['gstin'])): ?>
            <div class="muted">GSTIN: <?= bp_e($bill['gstin']) ?></div>
        <?php endif; ?>
        <div class="sub"><b><?= bp_e($invoiceTitle) ?></b></div>
    </div>

    <div class="line"></div>

    <table class="info">
        <tr><td>Bill No</td><td class="right"><b><?= bp_e($bill['bill_no']) ?></b></td></tr>
        <tr><td>Order No</td><td class="right"><?= bp_e($bill['order_no']) ?></td></tr>
        <tr><td>Date & Time</td><td class="right"><?= bp_e($billPrintDateTime) ?></td></tr>
        <tr>
            <td>Customer</td>
            <td class="right">
                <?= bp_e($bill['customer_name'] ?: 'Walk-in Customer') ?>
                <?= $bill['customer_mobile'] ? '<br>' . bp_e($bill['customer_mobile']) : '' ?>
            </td>
        </tr>
        <tr><td>Branch</td><td class="right"><?= bp_e(trim(($bill['branch_name'] ?: '') . ' ' . ($bill['floor_name'] ?: ''))) ?></td></tr>
        <tr>
            <td>Status</td>
            <td class="right">
                <span class="status-pill <?= bp_e(strtolower($bill['payment_status'] ?? 'pending')) ?>"><?= bp_e($paidStatus) ?></span>
            </td>
        </tr>
    </table>

    <div class="line"></div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Amt</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
            <tr><td colspan="4" class="center muted">No items found.</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>
                    <b><?= bp_e($item['article_name'] ?: $item['article_no']) ?></b><br>
                    <span class="muted">
                        <?= bp_e($item['article_no']) ?>
                        <?= $item['size'] ? ' / Size ' . bp_e($item['size']) : '' ?>
                        <?= $item['brand_name'] ? ' / ' . bp_e($item['brand_name']) : '' ?>
                    </span>
                </td>
                <td class="right"><?= number_format((float)$item['qty'], 2) ?></td>
                <td class="right"><?= number_format((float)$item['selling_rate'], 2) ?></td>
                <td class="right"><?= number_format((float)$item['amount'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="info">
        <tr><td>MRP Total</td><td class="right"><?= bp_money($bill['mrp_total']) ?></td></tr>
        <?php if ((float)($bill['item_discount_total'] ?? 0) > 0): ?>
            <tr><td>Item Discount</td><td class="right">- <?= bp_money($bill['item_discount_total']) ?></td></tr>
        <?php endif; ?>
        <?php if ((float)($bill['bill_discount_amount'] ?? 0) > 0): ?>
            <tr><td>Bill Discount</td><td class="right">- <?= bp_money($bill['bill_discount_amount']) ?></td></tr>
        <?php endif; ?>

        <?php if ($billGstApplied): ?>
            <tr class="gst-row"><td>Taxable Amount</td><td class="right"><?= bp_money($taxableAmount) ?></td></tr>
            <?php if ($cgstAmount > 0): ?><tr class="gst-row"><td>CGST</td><td class="right"><?= bp_money($cgstAmount) ?></td></tr><?php endif; ?>
            <?php if ($sgstAmount > 0): ?><tr class="gst-row"><td>SGST</td><td class="right"><?= bp_money($sgstAmount) ?></td></tr><?php endif; ?>
            <?php if ($igstAmount > 0): ?><tr class="gst-row"><td>IGST</td><td class="right"><?= bp_money($igstAmount) ?></td></tr><?php endif; ?>
            <tr class="gst-row"><td>Total GST</td><td class="right"><?= bp_money($taxAmount) ?></td></tr>
        <?php endif; ?>

        <?php if ((float)($bill['round_off'] ?? 0) != 0): ?>
            <tr><td>Round Off</td><td class="right"><?= bp_money($bill['round_off']) ?></td></tr>
        <?php endif; ?>

        <tr class="total-row"><td>Grand Total</td><td class="right"><?= bp_money($bill['net_amount']) ?></td></tr>
        <tr><td>Paid</td><td class="right"><?= bp_money($bill['paid_amount']) ?></td></tr>
        <tr><td>Balance</td><td class="right"><?= bp_money($bill['balance_amount']) ?></td></tr>
    </table>

    <?php if ($payments): ?>
        <div class="line"></div>
        <table class="info">
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= bp_e($payment['payment_method_name'] ?: $payment['method_type']) ?></td>
                    <td class="right"><?= bp_money($payment['paid_amount']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <div class="barcode-box">
        <img src="<?= bp_e($barcodeSrc) ?>" alt="Bill barcode <?= bp_e($barcodeValue) ?>">
        <div class="barcode-no"><?= bp_e($barcodeValue) ?></div>
    </div>

    <div class="center muted">Use this barcode for bill lookup, collection, returns and verification.</div>
    <div class="center muted" style="margin-top:6px;">Thank you. Visit again.</div>
</section>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () {
        window.print();
    }, 350);
});
</script>
<?php endif; ?>
</body>
</html>
