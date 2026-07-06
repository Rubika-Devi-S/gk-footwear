<?php
/**
 * GK Footwear - FPDF Thermal Bill Print
 * -------------------------------------
 * URL: bill-print.php?bill_id=1&auto_print=1
 * Supports Composer: composer require setasign/fpdf
 * Fallbacks: /libs/fpdf.php, /includes/fpdf.php, /fpdf.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

require_business_login();
if (function_exists('require_page_access')) {
    require_page_access($conn, 'pos-create-bill.php');
}

$businessId = function_exists('current_business_id') ? (int) current_business_id() : (int)($_SESSION['business_id'] ?? 0);
$sessionBranchId = function_exists('current_branch_id') ? (int) current_branch_id() : (int)($_SESSION['branch_id'] ?? 0);
$userId = function_exists('current_user_id') ? (int) current_user_id() : (int)($_SESSION['user_id'] ?? 0);
$roleId = function_exists('current_role_id') ? (int) current_role_id() : (int)($_SESSION['role_id'] ?? 0);
$billId = (int)($_GET['bill_id'] ?? 0);
$autoPrint = (int)($_GET['auto_print'] ?? $_GET['auto'] ?? 0) === 1;

if ($businessId <= 0 || $billId <= 0) {
    die('Invalid bill request.');
}

function bp_table_exists(mysqli $conn, string $tableName): bool
{
    if (function_exists('table_exists')) {
        return table_exists($conn, $tableName);
    }
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    mysqli_stmt_bind_param($stmt, 's', $tableName);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ((int)($row['total'] ?? 0)) > 0;
}

function bp_table_has_column(mysqli $conn, string $tableName, string $columnName): bool
{
    if (function_exists('table_has_column')) {
        return table_has_column($conn, $tableName, $columnName);
    }
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ((int)($row['total'] ?? 0)) > 0;
}

function bp_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException(mysqli_error($conn));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function bp_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException(mysqli_error($conn));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function bp_exec(mysqli $conn, string $sql, string $types = '', array $params = []): void
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function bp_money($value): string
{
    return 'Rs. ' . number_format((float)$value, 2, '.', ',');
}

function bp_text($value): string
{
    $value = trim((string)$value);
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    return preg_replace('/\s+/', ' ', $value) ?: '-';
}

function bp_load_fpdf(): void
{
    $candidates = [
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/libs/fpdf.php',
        __DIR__ . '/includes/fpdf.php',
        __DIR__ . '/fpdf.php',
    ];

    foreach ($candidates as $file) {
        if (file_exists($file)) {
            require_once $file;
            if (class_exists('FPDF')) {
                return;
            }
        }
    }

    die('FPDF library not found. Run: composer require setasign/fpdf OR place fpdf.php inside /libs/fpdf.php');
}

bp_load_fpdf();

$bill = bp_one($conn, "
    SELECT b.*, br.branch_name, br.branch_code, br.address AS branch_address, br.mobile AS branch_mobile,
           bus.business_name, bus.owner_name, bus.address AS business_address, bus.mobile AS business_mobile,
           bus.gstin AS business_gstin, bus.gst_type_key AS business_gst_type_key,
           bb.barcode_value AS bill_barcode
    FROM bills b
    INNER JOIN businesses bus ON bus.business_id = b.business_id
    INNER JOIN branches br ON br.branch_id = b.branch_id
    LEFT JOIN bill_barcodes bb ON bb.bill_id = b.bill_id AND bb.barcode_status <> 'deleted'
    WHERE b.business_id = ? AND b.bill_id = ? AND b.bill_status <> 'deleted'
    LIMIT 1
", 'ii', [$businessId, $billId]);

if (!$bill) {
    die('Bill not found.');
}

$billBranchId = (int)$bill['branch_id'];
$isBusinessAdmin = function_exists('is_business_admin') ? (bool) is_business_admin($conn) : false;
if ($sessionBranchId > 0 && $sessionBranchId !== $billBranchId && !$isBusinessAdmin) {
    die('You do not have permission to print this branch bill.');
}

$invoice = bp_one($conn, "
    SELECT *
    FROM invoice_settings
    WHERE business_id = ?
      AND (branch_id = ? OR branch_id IS NULL)
      AND status = 1
    ORDER BY branch_id IS NULL ASC
    LIMIT 1
", 'ii', [$businessId, $billBranchId]);

$items = bp_all($conn, "
    SELECT bi.*, brd.brand_name, sii.color, sii.available_qty, sii.category_id, cat.category_name
    FROM bill_items bi
    LEFT JOIN brands brd ON brd.brand_id = bi.brand_id
    LEFT JOIN stock_inward_items sii ON sii.stock_item_id = bi.stock_item_id
    LEFT JOIN categories cat ON cat.category_id = sii.category_id
    WHERE bi.business_id = ? AND bi.branch_id = ? AND bi.bill_id = ?
    ORDER BY bi.bill_item_id ASC
", 'iii', [$businessId, $billBranchId, $billId]);

$payments = bp_all($conn, "
    SELECT bp.*, pm.payment_method_name, pm.method_type
    FROM bill_payments bp
    LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id
    WHERE bp.business_id = ? AND bp.branch_id = ? AND bp.bill_id = ? AND bp.payment_status = 'received'
    ORDER BY bp.payment_id ASC
", 'iii', [$businessId, $billBranchId, $billId]);

$paperSize = strtolower((string)($invoice['paper_size'] ?? '3-inch'));
$isA4 = strpos($paperSize, 'a4') !== false;

class GKBillPDF extends FPDF
{
    public $autoPrint = false;

    function Footer()
    {
        // Thermal receipts normally do not need page numbers.
    }

    function AutoPrint($dialog = true)
    {
        $param = $dialog ? 'true' : 'false';
        $script = "print($param);";
        $this->IncludeJS($script);
    }

    protected $javascript;
    protected $n_js;

    function IncludeJS($script)
    {
        $this->javascript = $script;
    }

    function _putjavascript()
    {
        $this->_newobj();
        $this->n_js = $this->n;
        $this->_put('<<');
        $this->_put('/Names [(EmbeddedJS) '.($this->n + 1).' 0 R]');
        $this->_put('>>');
        $this->_put('endobj');
        $this->_newobj();
        $this->_put('<<');
        $this->_put('/S /JavaScript');
        $this->_put('/JS '.$this->_textstring($this->javascript));
        $this->_put('>>');
        $this->_put('endobj');
    }

    function _putresources()
    {
        parent::_putresources();
        if (!empty($this->javascript)) {
            $this->_putjavascript();
        }
    }

    function _putcatalog()
    {
        parent::_putcatalog();
        if (!empty($this->javascript)) {
            $this->_put('/Names <</JavaScript '.($this->n_js).' 0 R>>');
        }
    }
}

$itemHeight = max(8, count($items) * 9);
$thermalHeight = 92 + $itemHeight + (count($payments) * 5) + 28;
$pdf = $isA4 ? new GKBillPDF('P', 'mm', 'A4') : new GKBillPDF('P', 'mm', [80, max(180, $thermalHeight)]);
$pdf->SetMargins($isA4 ? 12 : 4, $isA4 ? 10 : 5, $isA4 ? 12 : 4);
$pdf->SetAutoPageBreak(true, $isA4 ? 12 : 5);
$pdf->AddPage();
$pdf->SetTitle('Bill ' . (string)$bill['bill_no']);
$pdf->SetAuthor('GK Footwear POS');

$pageWidth = $pdf->GetPageWidth();
$left = $isA4 ? 12 : 4;
$contentWidth = $pageWidth - ($left * 2);

$pdf->SetFont('Arial', 'B', $isA4 ? 18 : 15);
$pdf->Cell($contentWidth, 6, bp_text($bill['business_name']), 0, 1, 'C');
$pdf->SetFont('Arial', '', $isA4 ? 10 : 8);
$pdf->MultiCell($contentWidth, 4, bp_text($bill['business_address'] ?: $bill['branch_address']), 0, 'C');
$invoiceTitle = bp_text($bill['invoice_title'] ?: ($invoice['invoice_title'] ?? 'Bill of Supply'));
$gstLine = $invoiceTitle;
if (!empty($bill['business_gstin']) && (int)($invoice['show_gstin'] ?? 1) === 1) {
    $gstLine .= ' - GSTIN: ' . bp_text($bill['business_gstin']);
}
$pdf->MultiCell($contentWidth, 4, $gstLine, 0, 'C');
$pdf->Ln(2);
$pdf->Line($left, $pdf->GetY(), $pageWidth - $left, $pdf->GetY());
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', $isA4 ? 10 : 8.5);
$rowH = $isA4 ? 6 : 5;
function bp_info_row($pdf, $label, $value, $width, $rowH)
{
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell($width * 0.36, $rowH, $label, 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell($width * 0.64, $rowH, bp_text($value), 0, 1, 'R');
}

$billDateTime = bp_text($bill['bill_date'] . ' ' . ($bill['bill_time'] ?? ''));
bp_info_row($pdf, 'Bill No', $bill['bill_no'], $contentWidth, $rowH);
bp_info_row($pdf, 'Date', $billDateTime, $contentWidth, $rowH);
bp_info_row($pdf, 'Branch', $bill['branch_name'], $contentWidth, $rowH);
bp_info_row($pdf, 'Customer', $bill['customer_name'] ?: 'Walk-in Customer', $contentWidth, $rowH);
if (!empty($bill['customer_mobile'])) {
    bp_info_row($pdf, 'Mobile', $bill['customer_mobile'], $contentWidth, $rowH);
}
$pdf->Ln(2);
$pdf->Line($left, $pdf->GetY(), $pageWidth - $left, $pdf->GetY());
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', $isA4 ? 9 : 8);
$itemW = $contentWidth - 35;
$pdf->Cell($itemW, 5, 'Item', 0, 0, 'L');
$pdf->Cell(8, 5, 'Qty', 0, 0, 'R');
$pdf->Cell(13, 5, 'Rate', 0, 0, 'R');
$pdf->Cell(14, 5, 'Amt', 0, 1, 'R');
$pdf->Line($left, $pdf->GetY(), $pageWidth - $left, $pdf->GetY());
$pdf->Ln(2);

$pdf->SetFont('Arial', '', $isA4 ? 8.5 : 7.5);
foreach ($items as $item) {
    $nameParts = [];
    $nameParts[] = $item['article_name'] ?: $item['article_no'];
    if (!empty($item['size'])) { $nameParts[] = $item['size']; }
    if (!empty($item['color'])) { $nameParts[] = $item['color']; }
    $nameLine = bp_text(implode(' / ', $nameParts));
    $articleLine = bp_text($item['article_no'] . (!empty($item['brand_name']) ? ' - ' . $item['brand_name'] : ''));
    $y = $pdf->GetY();
    $pdf->SetFont('Arial', 'B', $isA4 ? 8.5 : 7.5);
    $pdf->MultiCell($itemW, 4, $nameLine, 0, 'L');
    $pdf->SetX($left);
    $pdf->SetFont('Arial', '', $isA4 ? 8 : 7);
    $pdf->Cell($itemW, 4, $articleLine, 0, 0, 'L');
    $lineEndY = $pdf->GetY() + 4;
    $pdf->SetXY($left + $itemW, $y);
    $pdf->SetFont('Arial', 'B', $isA4 ? 8.5 : 7.5);
    $pdf->Cell(8, 5, rtrim(rtrim(number_format((float)$item['qty'], 2, '.', ''), '0'), '.'), 0, 0, 'R');
    $pdf->Cell(13, 5, number_format((float)$item['selling_rate'], 2), 0, 0, 'R');
    $pdf->Cell(14, 5, number_format((float)$item['amount'], 2), 0, 1, 'R');
    $pdf->SetY(max($lineEndY, $pdf->GetY()) + 1);
}

$pdf->Ln(1);
$pdf->Line($left, $pdf->GetY(), $pageWidth - $left, $pdf->GetY());
$pdf->Ln(4);

function bp_amount_row($pdf, $label, $value, $width, $bold = false)
{
    $pdf->SetFont('Arial', $bold ? 'B' : '', $bold ? 10 : 8.2);
    $pdf->Cell($width * 0.55, 5, $label, 0, 0, 'L');
    $pdf->Cell($width * 0.45, 5, bp_money($value), 0, 1, 'R');
}

bp_amount_row($pdf, 'MRP Total', $bill['mrp_total'], $contentWidth);
bp_amount_row($pdf, 'Product Discount', $bill['item_discount_total'], $contentWidth);
if ((float)$bill['bill_discount_amount'] > 0) {
    bp_amount_row($pdf, 'Bill Discount', $bill['bill_discount_amount'], $contentWidth);
}
if ((float)$bill['loyalty_redeem_amount'] > 0) {
    bp_amount_row($pdf, 'Loyalty Redeem', $bill['loyalty_redeem_amount'], $contentWidth);
}
if ((int)($invoice['show_today_savings'] ?? 1) === 1) {
    bp_amount_row($pdf, "Today's Savings", $bill['today_savings_amount'], $contentWidth);
}
bp_amount_row($pdf, 'Net Amount', $bill['net_amount'], $contentWidth);
bp_amount_row($pdf, 'Round Off', $bill['round_off'], $contentWidth);
$pdf->Line($left, $pdf->GetY(), $pageWidth - $left, $pdf->GetY());
bp_amount_row($pdf, 'Grand Total', $bill['net_amount'], $contentWidth, true);

$pdf->SetFont('Arial', '', $isA4 ? 8.5 : 7.5);
foreach ($payments as $payment) {
    $label = 'Paid ' . bp_text($payment['payment_method_name'] ?: $payment['method_type']);
    bp_amount_row($pdf, $label, $payment['paid_amount'], $contentWidth);
}
bp_amount_row($pdf, 'Balance', $bill['balance_amount'], $contentWidth);

$pdf->Ln(2);
if (!empty($bill['bill_barcode']) && (int)($invoice['show_barcode'] ?? 1) === 1) {
    $pdf->SetFont('Arial', 'B', $isA4 ? 8 : 7.5);
    $pdf->Cell($contentWidth, 5, 'Barcode: ' . bp_text($bill['bill_barcode']), 0, 1, 'C');
}

if ((int)($invoice['show_composition_note'] ?? 1) === 1 && !empty($invoice['composition_note'])) {
    $pdf->SetFont('Arial', '', $isA4 ? 8 : 7);
    $pdf->MultiCell($contentWidth, 4, bp_text($invoice['composition_note']), 0, 'C');
}
if (!empty($invoice['footer_text'])) {
    $pdf->Ln(1);
    $pdf->SetFont('Arial', 'B', $isA4 ? 8 : 7);
    $pdf->MultiCell($contentWidth, 4, bp_text($invoice['footer_text']), 0, 'C');
}
$pdf->Ln(1);
$pdf->SetFont('Arial', '', $isA4 ? 7.5 : 6.5);
$pdf->Cell($contentWidth, 4, 'Thank you. Visit again.', 0, 1, 'C');

bp_exec($conn, "UPDATE bills SET print_count = print_count + 1 WHERE bill_id = ? AND business_id = ?", 'ii', [$billId, $businessId]);
if (bp_table_exists($conn, 'business_activity_logs')) {
    $newValue = json_encode(['bill_id' => $billId, 'bill_no' => $bill['bill_no'], 'print_count_incremented' => true]);
    bp_exec($conn, "
        INSERT INTO business_activity_logs
            (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details)
        VALUES
            (?, ?, ?, ?, 'POS Billing', 'bill_print', ?, NULL, ?, ?, ?)
    ", 'iiiiisss', [
        $businessId,
        $billBranchId,
        $userId ?: null,
        $roleId ?: null,
        $billId,
        $newValue,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);
}

if ($autoPrint) {
    $pdf->AutoPrint(true);
}

$pdf->Output('I', 'Bill-' . preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$bill['bill_no']) . '.pdf');
exit;
