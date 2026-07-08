<?php
/**
 * GK Footwear POS - Payment Ledger Report PDF Export
 * Place at project root / business folder: payment-ledger-report-pdf.php
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/controllers/PaymentLedgerReportController.php';

if (function_exists('require_business_login')) { require_business_login(); }
if (function_exists('require_page_access')) { require_page_access($conn, 'payment-ledger-report.php'); }

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}
if (!class_exists('FPDF')) {
    if (file_exists(__DIR__ . '/libs/fpdf.php')) {
        require_once __DIR__ . '/libs/fpdf.php';
    } elseif (file_exists(__DIR__ . '/fpdf.php')) {
        require_once __DIR__ . '/fpdf.php';
    }
}

function plpdf_user_id(): int
{
    if (function_exists('current_user_id')) { return (int)current_user_id(); }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}
function plpdf_business_id(): int
{
    if (function_exists('current_business_id')) { return (int)current_business_id(); }
    return (int)($_SESSION['business_id'] ?? 0);
}
function plpdf_is_admin(mysqli $conn): bool
{
    if (function_exists('is_business_admin')) { return (bool)is_business_admin($conn); }
    return ((int)($_SESSION['role_id'] ?? 0) === 1);
}
function plpdf_text($value): string
{
    $value = str_replace(array('₹', "\r", "\n", "\t"), array('Rs.', ' ', ' ', ' '), (string)$value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

if (!class_exists('FPDF')) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>FPDF library not found.</h3><p>Install FPDF or place fpdf.php in /libs/fpdf.php.</p>';
    exit;
}

$businessId = plpdf_business_id();
if ($businessId <= 0) { die('Business session missing.'); }

$type = (string)($_GET['type'] ?? 'payments');
$valid = array('payments','ledger','outstanding','daily','method','cashier');
if (!in_array($type, $valid, true)) { $type = 'payments'; }

$controller = new PaymentLedgerReportController($conn, $businessId, plpdf_user_id(), plpdf_is_admin($conn));
$result = $controller->rowsForReport($type, $_GET, false);
$rows = $controller->enrichRowsForExport($result['rows'] ?? array());
$columns = $controller->exportColumns($type);

$titleMap = array(
    'payments' => 'Payment Transactions',
    'ledger' => 'Payment Ledger',
    'outstanding' => 'Customer Outstanding',
    'daily' => 'Daily Collection',
    'method' => 'Payment Method Summary',
    'cashier' => 'Cashier Summary',
);
$title = $titleMap[$type] ?? 'Payment Ledger Report';

class PaymentLedgerPDF extends FPDF
{
    public $titleText = 'Payment Ledger Report';

    public function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 7, $this->titleText, 0, 1, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, 'Generated: ' . date('d-m-Y h:i A'), 0, 1, 'L');
        $this->Ln(2);
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 8, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PaymentLedgerPDF('L', 'mm', 'A4');
$pdf->titleText = plpdf_text($title);
$pdf->SetMargins(7, 10, 7);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 7);

$allColumns = $columns;
if (count($allColumns) > 9) {
    $allColumns = array_slice($allColumns, 0, 9, true);
}

$pageWidth = 297 - 14;
$colCount = max(1, count($allColumns));
$widths = array();
foreach ($allColumns as $label => $key) {
    $widths[$key] = $pageWidth / $colCount;
}

$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(15, 23, 42);
$pdf->SetFont('Arial', 'B', 7);
foreach ($allColumns as $label => $key) {
    $pdf->Cell($widths[$key], 7, plpdf_text($label), 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 6.7);
$maxRows = 1500;
$i = 0;
foreach ($rows as $row) {
    if ($i >= $maxRows) {
        break;
    }
    $i++;
    if ($pdf->GetY() > 190) {
        $pdf->AddPage();
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetFont('Arial', 'B', 7);
        foreach ($allColumns as $label => $key) {
            $pdf->Cell($widths[$key], 7, plpdf_text($label), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 6.7);
    }
    foreach ($allColumns as $label => $key) {
        $txt = substr(plpdf_text($row[$key] ?? ''), 0, 42);
        $align = preg_match('/amount|balance|debit|credit|collected|cash|upi|card|paid|opening|bills|payments|customers|count/i', $label) ? 'R' : 'L';
        $pdf->Cell($widths[$key], 6, $txt, 1, 0, $align);
    }
    $pdf->Ln();
}

if (count($rows) > $maxRows) {
    $pdf->Ln(2);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 6, 'Only first ' . $maxRows . ' rows are shown in PDF. Use Excel export for full data.', 0, 1);
}

$pdf->Output('I', 'payment-ledger-' . $type . '-' . date('Ymd-His') . '.pdf');
exit;
