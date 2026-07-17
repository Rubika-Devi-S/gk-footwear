<?php
/**
 * GK Footwear POS - 3 Inch Thermal Receipt Print
 * Place this file as: bill-print.php
 * Called from Create Bill: bill-print.php?bill_id=ID&auto_print=1
 * Fetches only existing bill data. No dummy data is inserted.
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (function_exists('require_business_login')) {
    require_business_login();
}

if (!defined('GK_THERMAL_TZ')) {
    define('GK_THERMAL_TZ', 'Asia/Kolkata');
}
date_default_timezone_set(GK_THERMAL_TZ);
if (isset($conn) && $conn instanceof mysqli) {
    @mysqli_query($conn, "SET time_zone = '+05:30'");
}

function th_e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function th_money($value)
{
    return '₹' . number_format((float)$value, 2, '.', ',');
}

function th_money_plain($value)
{
    return number_format((float)$value, 2, '.', ',');
}

function th_qty($value)
{
    $n = (float)$value;
    return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
}

function th_current_business_id()
{
    if (function_exists('current_business_id')) {
        return (int)current_business_id();
    }
    return (int)($_SESSION['business_id'] ?? 0);
}

function th_bind(mysqli_stmt $stmt, $types, array $params)
{
    if ($types === '') { return; }
    $bind = array($types);
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind);
}

function th_fetch_one(mysqli $conn, $sql, $types = '', array $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('SQL prepare failed: ' . mysqli_error($conn));
    }
    th_bind($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('SQL execute failed: ' . mysqli_stmt_error($stmt));
    }
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function th_fetch_all(mysqli $conn, $sql, $types = '', array $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('SQL prepare failed: ' . mysqli_error($conn));
    }
    th_bind($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('SQL execute failed: ' . mysqli_stmt_error($stmt));
    }
    $result = mysqli_stmt_get_result($stmt);
    $rows = array();
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function th_table_exists(mysqli $conn, $table)
{
    $row = th_fetch_one(
        $conn,
        "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        's',
        array($table)
    );
    return (int)($row['total'] ?? 0) > 0;
}

function th_column_exists(mysqli $conn, $table, $column)
{
    $row = th_fetch_one(
        $conn,
        "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        'ss',
        array($table, $column)
    );
    return (int)($row['total'] ?? 0) > 0;
}

function th_first_not_empty(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
            return trim((string)$row[$key]);
        }
    }
    return $default;
}

function th_datetime(array $bill)
{
    $tz = new DateTimeZone(GK_THERMAL_TZ);
    $date = trim((string)($bill['bill_date'] ?? ''));
    $time = trim((string)($bill['bill_time'] ?? ''));
    if ($time === '00:00:00') { $time = ''; }

    if ($date !== '' && $date !== '0000-00-00') {
        try {
            return new DateTimeImmutable($date . ($time !== '' ? ' ' . $time : ''), $tz);
        } catch (Throwable $e) {}
    }

    $createdAt = trim((string)($bill['created_at'] ?? ''));
    if ($createdAt !== '' && $createdAt !== '0000-00-00 00:00:00') {
        try {
            return new DateTimeImmutable($createdAt, $tz);
        } catch (Throwable $e) {}
    }

    return new DateTimeImmutable('now', $tz);
}

function th_bill_date(array $bill)
{
    return th_datetime($bill)->format('d-m-Y');
}

function th_bill_time(array $bill)
{
    return th_datetime($bill)->format('h:i:s A');
}

function th_bill_status($status)
{
    $status = strtolower(trim((string)$status));
    if ($status === 'paid') { return 'PAID'; }
    if ($status === 'partial') { return 'PARTIAL'; }
    if ($status === 'cancelled') { return 'CANCELLED'; }
    return 'PENDING';
}

function th_yes_amount($value)
{
    return abs((float)$value) > 0.0001;
}

function th_bill_barcode(mysqli $conn, array $bill)
{
    $businessId = (int)($bill['business_id'] ?? 0);
    $branchId = (int)($bill['branch_id'] ?? 0);
    $billId = (int)($bill['bill_id'] ?? 0);

    $candidates = array(
        $bill['bill_barcode'] ?? '',
        $bill['barcode_value'] ?? '',
        $bill['barcode'] ?? '',
        $bill['qr_code'] ?? '',
        $bill['bill_no'] ?? ''
    );

    if (th_table_exists($conn, 'bill_barcodes')) {
        $row = th_fetch_one(
            $conn,
            "SELECT barcode_value FROM bill_barcodes WHERE business_id = ? AND branch_id = ? AND bill_id = ? AND barcode_status <> 'deleted' ORDER BY bill_barcode_id DESC LIMIT 1",
            'iii',
            array($businessId, $branchId, $billId)
        );
        if ($row && trim((string)($row['barcode_value'] ?? '')) !== '') {
            array_unshift($candidates, $row['barcode_value']);
        }
    }

    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '' && strtolower($candidate) !== 'no qr') {
            return $candidate;
        }
    }

    return 'BILL-' . str_pad((string)$billId, 6, '0', STR_PAD_LEFT);
}

function th_qr_image_value(mysqli $conn, array $bill)
{
    $fields = array('qr_image', 'qr_image_path', 'upi_qr_image', 'invoice_qr_image', 'qr_code_image');
    foreach ($fields as $field) {
        if (isset($bill[$field]) && trim((string)$bill[$field]) !== '') {
            return trim((string)$bill[$field]);
        }
    }
    return '';
}

function th_image_src($value)
{
    $value = trim((string)$value);
    if ($value === '') { return ''; }
    if (preg_match('/^(https?:|data:image\/|\/)/i', $value)) { return $value; }
    return $value;
}

function th_code128_svg($value, $height = 46)
{
    $value = trim((string)$value);
    if ($value === '') { return ''; }

    $patterns = array(
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
    );

    $codes = array(104);
    $checksum = 104;
    $position = 1;
    $len = strlen($value);
    for ($i = 0; $i < $len; $i++) {
        $ord = ord($value[$i]);
        if ($ord < 32 || $ord > 126) { $ord = 32; }
        $code = $ord - 32;
        $codes[] = $code;
        $checksum += $code * $position;
        $position++;
    }
    $codes[] = $checksum % 103;
    $codes[] = 106;

    $moduleWidth = 1.35;
    $quiet = 8;
    $x = $quiet;
    $bars = '';
    foreach ($codes as $code) {
        $pattern = $patterns[$code] ?? $patterns[0];
        $black = true;
        $plen = strlen($pattern);
        for ($i = 0; $i < $plen; $i++) {
            $w = ((int)$pattern[$i]) * $moduleWidth;
            if ($black) {
                $bars .= '<rect x="' . number_format($x, 2, '.', '') . '" y="0" width="' . number_format($w, 2, '.', '') . '" height="' . (int)$height . '" fill="#000"/>';
            }
            $x += $w;
            $black = !$black;
        }
    }
    $width = $x + $quiet;
    return '<svg class="barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . number_format($width, 2, '.', '') . ' ' . (int)$height . '" preserveAspectRatio="none" role="img" aria-label="Barcode"><rect x="0" y="0" width="100%" height="100%" fill="#fff"/>' . $bars . '</svg>';
}

$businessId = th_current_business_id();
$billId = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$autoPrint = isset($_GET['auto_print']) && (string)$_GET['auto_print'] === '1';

if ($businessId <= 0 || $billId <= 0) {
    http_response_code(400);
    die('Invalid bill print request.');
}

try {
    $bill = th_fetch_one($conn, "
        SELECT
            b.*,
            br.branch_code,
            br.branch_name,
            br.floor_name,
            br.address AS branch_address,
            br.mobile AS branch_mobile,
            bs.business_code,
            bs.business_name,
            bs.owner_name,
            bs.mobile AS business_mobile,
            bs.email AS business_email,
            bs.address AS business_address,
            bs.gstin,
            COALESCE(u.full_name, u.name, u.username, '') AS sales_user_name
        FROM bills b
        LEFT JOIN branches br
               ON br.business_id = b.business_id
              AND br.branch_id = b.branch_id
        LEFT JOIN businesses bs
               ON bs.business_id = b.business_id
        LEFT JOIN users u
               ON u.business_id = b.business_id
              AND u.user_id = b.created_by
        WHERE b.business_id = ?
          AND b.bill_id = ?
          AND b.bill_status <> 'deleted'
        LIMIT 1
    ", 'ii', array($businessId, $billId));

    if (!$bill) {
        http_response_code(404);
        die('Bill not found.');
    }

    $items = th_fetch_all($conn, "
        SELECT
            bi.*,
            COALESCE(br.brand_name, '') AS brand_name,
            COALESCE(sii.color, '') AS color,
            COALESCE(sb.barcode_value, '') AS item_barcode
        FROM bill_items bi
        LEFT JOIN brands br
               ON br.business_id = bi.business_id
              AND br.brand_id = bi.brand_id
        LEFT JOIN stock_inward_items sii
               ON sii.business_id = bi.business_id
              AND sii.branch_id = bi.branch_id
              AND sii.stock_item_id = bi.stock_item_id
        LEFT JOIN stock_barcodes sb
               ON sb.business_id = bi.business_id
              AND sb.branch_id = bi.branch_id
              AND sb.barcode_id = bi.barcode_id
        WHERE bi.business_id = ?
          AND bi.branch_id = ?
          AND bi.bill_id = ?
        ORDER BY bi.bill_item_id ASC
    ", 'iii', array($businessId, (int)$bill['branch_id'], $billId));

    $payments = array();
    if (th_table_exists($conn, 'bill_payments')) {
        $payments = th_fetch_all($conn, "
            SELECT
                bp.payment_id,
                bp.paid_amount,
                bp.reference_no,
                bp.payment_note,
                bp.collected_at,
                COALESCE(pm.payment_method_name, pm.method_type, 'Payment') AS payment_method_name
            FROM bill_payments bp
            LEFT JOIN payment_methods pm
                   ON pm.business_id = bp.business_id
                  AND pm.payment_method_id = bp.payment_method_id
            WHERE bp.business_id = ?
              AND bp.branch_id = ?
              AND bp.bill_id = ?
              AND bp.payment_status = 'received'
            ORDER BY bp.payment_id ASC
        ", 'iii', array($businessId, (int)$bill['branch_id'], $billId));
    }

    if (!$payments && th_table_exists($conn, 'cashier_collections')) {
        $payments = th_fetch_all($conn, "
            SELECT
                cc.collection_id AS payment_id,
                cc.collected_amount AS paid_amount,
                '' AS reference_no,
                '' AS payment_note,
                cc.collected_at,
                COALESCE(pm.payment_method_name, pm.method_type, 'Payment') AS payment_method_name
            FROM cashier_collections cc
            LEFT JOIN payment_methods pm
                   ON pm.business_id = cc.business_id
                  AND pm.payment_method_id = cc.payment_method_id
            WHERE cc.business_id = ?
              AND cc.branch_id = ?
              AND cc.bill_id = ?
              AND cc.collection_status IN ('paid','partial')
            ORDER BY cc.collection_id ASC
        ", 'iii', array($businessId, (int)$bill['branch_id'], $billId));
    }

    $barcodeValue = th_bill_barcode($conn, $bill);
    $qrImage = th_qr_image_value($conn, $bill);
} catch (Throwable $e) {
    http_response_code(500);
    die('Print error: ' . th_e($e->getMessage()));
}

$businessName = th_first_not_empty($bill, array('business_name'), 'GK FOOTWEAR');
$address = th_first_not_empty($bill, array('branch_address', 'business_address'), '');
$mobile = th_first_not_empty($bill, array('branch_mobile', 'business_mobile'), '');
$email = th_first_not_empty($bill, array('business_email'), '');
$gstin = th_first_not_empty($bill, array('gstin'), '');
$branchName = trim(trim((string)($bill['branch_name'] ?? '')) . ((trim((string)($bill['floor_name'] ?? '')) !== '') ? ' / ' . trim((string)$bill['floor_name']) : ''));
$invoiceTitle = th_first_not_empty($bill, array('invoice_title'), 'Invoice');
$customerName = th_first_not_empty($bill, array('customer_name'), 'Walk-in Customer');
$customerMobile = th_first_not_empty($bill, array('customer_mobile'), '');
$salesUser = th_first_not_empty($bill, array('sales_user_name'), '');
$printedAt = (new DateTimeImmutable('now', new DateTimeZone(GK_THERMAL_TZ)))->format('d-m-Y h:i:s A');
$totalQty = 0;
foreach ($items as $item) {
    $totalQty += (float)($item['qty'] ?? 0);
}

$summaryLineCount = 4;
foreach (array('mrp_total','item_discount_total','bill_discount_amount','loyalty_redeem_amount','round_off') as $key) {
    if (th_yes_amount($bill[$key] ?? 0)) { $summaryLineCount++; }
}
if (th_yes_amount($bill['tax_amount'] ?? 0)) {
    $summaryLineCount += 2;
    if (th_yes_amount($bill['cgst_amount'] ?? 0)) { $summaryLineCount++; }
    if (th_yes_amount($bill['sgst_amount'] ?? 0)) { $summaryLineCount++; }
    if (th_yes_amount($bill['igst_amount'] ?? 0)) { $summaryLineCount++; }
}
$paymentLineCount = max(1, count($payments)) + 1;
$paperHeightMm = 74 + (count($items) * 12) + ($summaryLineCount * 4.3) + ($paymentLineCount * 4.2);
if ((float)($bill['balance_amount'] ?? 0) > 0) { $paperHeightMm += 9; }
if ($barcodeValue !== '') { $paperHeightMm += 26; }
if ($qrImage !== '') { $paperHeightMm += 26; }
$paperHeightMm = max(130, min(950, (int)ceil($paperHeightMm)));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= th_e($bill['bill_no'] ?? 'Bill') ?> - 80mm Thermal Receipt</title>
<style>
@page {
    size: 80mm <?= (int)$paperHeightMm ?>mm;
    margin: 0;
}
* { box-sizing: border-box; }
html, body {
    width: 80mm;
    margin: 0;
    padding: 0;
    background: #ffffff;
}
body {
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9.2px;
    line-height: 1.14;
    font-weight: 600;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
}
.print-actions {
    display: flex;
    justify-content: center;
    gap: 8px;
    width: 80mm;
    padding: 8px 4px;
    background: #eef2f7;
}
.print-btn {
    border: 0;
    border-radius: 999px;
    padding: 7px 12px;
    font-size: 11px;
    font-weight: 800;
    cursor: pointer;
    color: #fff;
    background: #111827;
}
.print-btn.light { color: #111827; background: #e5e7eb; }
.receipt {
    width: 80mm;
    max-width: 80mm;
    margin: 0;
    background: #fff;
    padding: 2.2mm 3.2mm 2.8mm;
    border: 0;
    overflow: hidden;
}
.center { text-align: center; }
.right { text-align: right; }
.bold { font-weight: 800; }
.shop-name {
    font-size: 13.2px;
    font-weight: 900;
    text-transform: uppercase;
    line-height: 1.02;
    letter-spacing: .01em;
}
.shop-line {
    font-size: 8.2px;
    color: #000;
    margin-top: 1px;
    word-break: break-word;
    font-weight: 600;
}
.invoice-title {
    font-size: 9.2px;
    font-weight: 900;
    text-transform: uppercase;
    margin-top: 3px;
}
.status {
    display: inline-block;
    border: 1px solid #000;
    border-radius: 999px;
    padding: 1px 5px;
    font-size: 7.5px;
    font-weight: 900;
    margin-top: 2px;
}
.hr { border-top: 1px dashed #000; height: 0; margin: 3.2px 0; }
.info-table, .total-table, .payment-table { width: 100%; border-collapse: collapse; }
.info-table td, .payment-table td { padding: 1px 0; vertical-align: top; }
.info-table td:first-child { width: 31%; color: #000; font-weight: 600; }
.info-table td:last-child { width: 69%; font-weight: 800; }
.items { width: 100%; border-collapse: collapse; table-layout: fixed; }
.items th {
    border-top: 1px dashed #000;
    border-bottom: 1px dashed #000;
    padding: 2px 1px;
    font-size: 7.7px;
    text-transform: uppercase;
    text-align: left;
    font-weight: 900;
}
.items td {
    border-bottom: 1px dashed #cfcfcf;
    padding: 2.6px 1px;
    vertical-align: top;
    font-size: 8.4px;
}
.items .item-col { width: 45%; }
.items .qty-col { width: 11%; text-align: right; }
.items .rate-col { width: 20%; text-align: right; }
.items .amt-col { width: 24%; text-align: right; }
.item-name { font-weight: 900; font-size: 8.7px; word-break: break-word; }
.item-meta { font-size: 7.4px; color: #111; margin-top: 1px; word-break: break-word; font-weight: 600; }
.discount-line { color: #000; font-size: 7.5px; font-weight: 700; }
.total-table td { padding: 1.4px 0; font-size: 8.7px; }
.total-table td:first-child { color: #000; }
.total-table .grand td {
    border-top: 1px dashed #000;
    padding-top: 4px;
    font-weight: 900;
    font-size: 12px;
}
.total-table .balance td { font-weight: 900; }
.payment-table td:first-child { width: 60%; }
.note { font-size: 7.8px; line-height: 1.18; font-weight: 700; }
.barcode-box {
    text-align: center;
    padding: 3px 0 2px;
    margin-top: 3px;
    border-top: 1px dashed #000;
    border-bottom: 1px dashed #000;
    page-break-inside: avoid;
}
.barcode-svg {
    width: 68mm;
    max-width: 68mm;
    height: 14mm;
    display: block;
    margin: 0 auto 1px;
    background: #fff;
}
.barcode-text { font-size: 8.8px; font-weight: 900; letter-spacing: .04em; word-break: break-all; }
.qr-img { width: 20mm; height: 20mm; object-fit: contain; display: block; margin: 3px auto 1px; }
.footer {
    text-align: center;
    font-size: 7.8px;
    color: #000;
    margin-top: 3px;
    line-height: 1.18;
    font-weight: 650;
}
@media screen {
    body { background: #64748b; width: 100%; }
    .receipt { box-shadow: 0 0 0 1px #d1d5db; margin: 0 auto 12px; }
    .print-actions { width: 100%; }
}
@media print {
    html, body {
        width: 80mm !important;
        min-width: 80mm !important;
        max-width: 80mm !important;
        height: auto !important;
        background: #fff !important;
        overflow: visible !important;
    }
    body { margin: 0 !important; padding: 0 !important; }
    .print-actions { display: none !important; }
    .receipt {
        width: 80mm !important;
        max-width: 80mm !important;
        margin: 0 !important;
        padding: 2mm 3mm 2mm !important;
        border: 0 !important;
        box-shadow: none !important;
        page-break-after: avoid;
        page-break-before: avoid;
    }
    a[href]:after { content: none !important; }
}
</style>
</head>
<body>
<div class="print-actions">
    <button type="button" class="print-btn" onclick="window.print()">Print</button>
    <button type="button" class="print-btn light" onclick="window.close()">Close</button>
</div>

<section class="receipt">
    <div class="center">
        <div class="shop-name"><?= th_e($businessName) ?></div>
        <?php if ($address !== ''): ?><div class="shop-line"><?= nl2br(th_e($address)) ?></div><?php endif; ?>
        <?php if ($mobile !== ''): ?><div class="shop-line">Ph: <?= th_e($mobile) ?></div><?php endif; ?>
        <?php if ($email !== ''): ?><div class="shop-line"><?= th_e($email) ?></div><?php endif; ?>
        <?php if ($gstin !== ''): ?><div class="shop-line">GSTIN: <?= th_e($gstin) ?></div><?php endif; ?>
        <div class="invoice-title"><?= th_e($invoiceTitle) ?></div>
        <div class="status"><?= th_e(th_bill_status($bill['payment_status'] ?? 'pending')) ?></div>
    </div>

    <div class="hr"></div>

    <table class="info-table">
        <tr><td>Bill No</td><td class="right"><?= th_e($bill['bill_no'] ?? '-') ?></td></tr>
        <tr><td>Date</td><td class="right"><?= th_e(th_bill_date($bill)) ?></td></tr>
        <tr><td>Time</td><td class="right"><?= th_e(th_bill_time($bill)) ?></td></tr>
        <tr><td>Customer</td><td class="right"><?= th_e($customerName) ?><?= $customerMobile !== '' ? '<br>' . th_e($customerMobile) : '' ?></td></tr>
        <?php if ($branchName !== ''): ?><tr><td>Branch</td><td class="right"><?= th_e($branchName) ?></td></tr><?php endif; ?>
        <?php if ($salesUser !== ''): ?><tr><td>Salesman</td><td class="right"><?= th_e($salesUser) ?></td></tr><?php endif; ?>
        <?php if (trim((string)($bill['order_no'] ?? '')) !== ''): ?><tr><td>Order No</td><td class="right"><?= th_e($bill['order_no']) ?></td></tr><?php endif; ?>
    </table>

    <div class="hr"></div>

    <table class="items">
        <thead>
            <tr>
                <th class="item-col">Item</th>
                <th class="qty-col">Qty</th>
                <th class="rate-col">Rate</th>
                <th class="amt-col">Amt</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$items): ?>
            <tr><td colspan="4" class="center">No items found</td></tr>
        <?php endif; ?>
        <?php foreach ($items as $item): ?>
            <?php
                $itemName = th_first_not_empty($item, array('article_name', 'article_no'), 'Item');
                $metaParts = array();
                if (trim((string)($item['article_no'] ?? '')) !== '') { $metaParts[] = $item['article_no']; }
                if (trim((string)($item['brand_name'] ?? '')) !== '') { $metaParts[] = $item['brand_name']; }
                if (trim((string)($item['size'] ?? '')) !== '') { $metaParts[] = 'Size ' . $item['size']; }
                if (trim((string)($item['color'] ?? '')) !== '') { $metaParts[] = $item['color']; }
                $itemDiscount = (float)($item['discount_amount'] ?? 0);
                $itemTax = (float)($item['tax_amount'] ?? 0);
            ?>
            <tr>
                <td class="item-col">
                    <div class="item-name"><?= th_e($itemName) ?></div>
                    <?php if ($metaParts): ?><div class="item-meta"><?= th_e(implode(' / ', $metaParts)) ?></div><?php endif; ?>
                    <?php if ($itemDiscount > 0): ?><div class="discount-line">Disc: <?= th_money($itemDiscount) ?></div><?php endif; ?>
                    <?php if ($itemTax > 0): ?><div class="discount-line">GST: <?= th_money($itemTax) ?></div><?php endif; ?>
                </td>
                <td class="qty-col"><?= th_e(th_qty($item['qty'] ?? 0)) ?></td>
                <td class="rate-col"><?= th_e(th_money_plain($item['selling_rate'] ?? 0)) ?></td>
                <td class="amt-col"><?= th_e(th_money_plain($item['amount'] ?? 0)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="hr"></div>

    <table class="total-table">
        <tr><td>Total Qty</td><td class="right"><?= th_e(th_qty($totalQty)) ?></td></tr>
        <?php if (th_yes_amount($bill['mrp_total'] ?? 0)): ?><tr><td>MRP Total</td><td class="right"><?= th_money($bill['mrp_total']) ?></td></tr><?php endif; ?>
        <?php if (th_yes_amount($bill['item_discount_total'] ?? 0)): ?><tr><td>Product Discount</td><td class="right">-<?= th_money($bill['item_discount_total']) ?></td></tr><?php endif; ?>
        <?php if (th_yes_amount($bill['bill_discount_amount'] ?? 0)): ?><tr><td>Bill Discount</td><td class="right">-<?= th_money($bill['bill_discount_amount']) ?></td></tr><?php endif; ?>
        <?php if (th_yes_amount($bill['loyalty_redeem_amount'] ?? 0)): ?><tr><td>Loyalty Redeem</td><td class="right">-<?= th_money($bill['loyalty_redeem_amount']) ?></td></tr><?php endif; ?>
        <?php if (th_yes_amount($bill['tax_amount'] ?? 0)): ?>
            <tr><td>Taxable</td><td class="right"><?= th_money($bill['taxable_amount'] ?? 0) ?></td></tr>
            <?php if (th_yes_amount($bill['cgst_amount'] ?? 0)): ?><tr><td>CGST</td><td class="right"><?= th_money($bill['cgst_amount']) ?></td></tr><?php endif; ?>
            <?php if (th_yes_amount($bill['sgst_amount'] ?? 0)): ?><tr><td>SGST</td><td class="right"><?= th_money($bill['sgst_amount']) ?></td></tr><?php endif; ?>
            <?php if (th_yes_amount($bill['igst_amount'] ?? 0)): ?><tr><td>IGST</td><td class="right"><?= th_money($bill['igst_amount']) ?></td></tr><?php endif; ?>
            <tr><td>Total GST</td><td class="right"><?= th_money($bill['tax_amount']) ?></td></tr>
        <?php endif; ?>
        <?php if (th_yes_amount($bill['round_off'] ?? 0)): ?><tr><td>Round Off</td><td class="right"><?= th_money($bill['round_off']) ?></td></tr><?php endif; ?>
        <tr class="grand"><td>Grand Total</td><td class="right"><?= th_money($bill['net_amount'] ?? 0) ?></td></tr>
        <tr><td>Paid</td><td class="right"><?= th_money($bill['paid_amount'] ?? 0) ?></td></tr>
        <tr class="balance"><td>Balance</td><td class="right"><?= th_money($bill['balance_amount'] ?? 0) ?></td></tr>
    </table>

    <div class="hr"></div>
    <table class="payment-table">
        <?php if ($payments): ?>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= th_e($payment['payment_method_name'] ?: 'Payment') ?></td>
                    <td class="right"><?= th_money($payment['paid_amount'] ?? 0) ?></td>
                </tr>
                <?php if (trim((string)($payment['reference_no'] ?? '')) !== ''): ?><tr><td colspan="2" class="right note">Ref: <?= th_e($payment['reference_no']) ?></td></tr><?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td>Payment Method</td><td class="right"><?= ((float)($bill['paid_amount'] ?? 0) > 0) ? 'Collected' : 'Pending' ?></td></tr>
        <?php endif; ?>
        <tr><td>Payment Status</td><td class="right bold"><?= th_e(th_bill_status($bill['payment_status'] ?? 'pending')) ?></td></tr>
    </table>

    <?php if ((float)($bill['balance_amount'] ?? 0) > 0): ?>
        <div class="hr"></div>
        <div class="center note"><b>Pending Payment:</b> Scan this bill barcode in Cashier Collection / Pending Bills to collect balance.</div>
    <?php endif; ?>

    <?php if ($barcodeValue !== ''): ?>
        <div class="barcode-box">
            <?= th_code128_svg($barcodeValue, 46) ?>
            <div class="barcode-text"><?= th_e($barcodeValue) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($qrImage !== ''): ?>
        <div class="center">
            <img class="qr-img" src="<?= th_e(th_image_src($qrImage)) ?>" alt="QR Code">
        </div>
    <?php endif; ?>

    <div class="footer">
        Barcode for collection, return &amp; exchange.<br>
        Printed: <?= th_e($printedAt) ?><br>
        Thank you. Visit again.
    </div>
</section>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 350);
});
</script>
<?php endif; ?>
</body>
</html>
