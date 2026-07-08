<?php
/**
 * Universal Footwear POS - Sales Report PDF Export
 * Place at project root / business folder: sales-report-pdf.php
 *
 * Requires FPDF. Supported locations:
 * - vendor/autoload.php
 * - libs/fpdf.php
 * - admin/libs/fpdf.php
 * - business/libs/fpdf.php
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

function srp_pdf_user_id(): int
{
    if (function_exists('current_user_id')) { return (int)current_user_id(); }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}
function srp_pdf_business_id(): int
{
    if (function_exists('current_business_id')) { return (int)current_business_id(); }
    return (int)($_SESSION['business_id'] ?? 0);
}
function srp_pdf_is_admin(mysqli $conn): bool
{
    if (function_exists('is_business_admin')) { return (bool)is_business_admin($conn); }
    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));
    return in_array($roleName, array('admin', 'business admin', 'branch admin'), true) || (int)($_SESSION['role_id'] ?? 0) === 1;
}
function srp_pdf_text($value): string
{
    $value = strip_tags((string)$value);
    $value = str_replace(array('₹', "\xE2\x82\xB9"), 'Rs. ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $value);
}
function srp_load_fpdf(): bool
{
    $paths = array(
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/libs/fpdf.php',
        __DIR__ . '/admin/libs/fpdf.php',
        __DIR__ . '/business/libs/fpdf.php',
    );
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            if (class_exists('FPDF')) {
                return true;
            }
        }
    }
    return class_exists('FPDF');
}

$businessId = srp_pdf_business_id();
if ($businessId <= 0) { die('Business session missing. Please login again.'); }

$type = (string)($_GET['type'] ?? 'bills');
$controller = new SalesReportController($conn, $businessId, srp_pdf_user_id(), srp_pdf_is_admin($conn));
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

if (!srp_load_fpdf()) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h3>FPDF library not found</h3>';
    echo '<p>Place fpdf.php in /libs/fpdf.php or install setasign/fpdf. You can still use Print and Excel export.</p>';
    echo '<p><a href="sales-report-print.php?' . htmlspecialchars(http_build_query($_GET), ENT_QUOTES, 'UTF-8') . '">Open printable report</a></p>';
    exit;
}

class SalesReportPdf extends FPDF
{
    public $reportTitle = 'Sales Report';

    public function Header()
    {
        $this->SetFont('Arial', 'B', 13);
        $this->Cell(0, 7, srp_pdf_text($this->reportTitle), 0, 1, 'L');
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, srp_pdf_text('Universal Footwear POS | Generated: ' . date('d-m-Y h:i A')), 0, 1, 'L');
        $this->Ln(2);
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', '', 7);
        $this->Cell(0, 6, 'Page ' . $this->PageNo(), 0, 0, 'C');
    }

    public function Row(array $cells, array $widths, int $height = 6)
    {
        $nb = 1;
        for ($i = 0; $i < count($cells); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], srp_pdf_text($cells[$i])));
        }
        $h = $height * $nb;
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
        }
        for ($i = 0; $i < count($cells); $i++) {
            $w = $widths[$i];
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, $height, srp_pdf_text($cells[$i]), 0, 'L');
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    public function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if($w==0) {
            $w=$this->w-$this->rMargin-$this->x;
        }
        $wmax=($w-2*$this->cMargin)*1000/$this->FontSize;
        $s=str_replace("\r",'',(string)$txt);
        $nb=strlen($s);
        if($nb>0 && $s[$nb-1]=="\n") {
            $nb--;
        }
        $sep=-1;
        $i=0;
        $j=0;
        $l=0;
        $nl=1;
        while($i<$nb) {
            $c=$s[$i];
            if($c=="\n") {
                $i++;
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
                continue;
            }
            if($c==' ') {
                $sep=$i;
            }
            $l += $cw[$c] ?? 0;
            if($l>$wmax) {
                if($sep==-1) {
                    if($i==$j) {
                        $i++;
                    }
                } else {
                    $i=$sep+1;
                }
                $sep=-1;
                $j=$i;
                $l=0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }
}

$pdf = new SalesReportPdf('L', 'mm', 'A4');
$pdf->reportTitle = $title;
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 8);

$pdf->SetFillColor(241, 245, 249);
$pdf->Cell(46, 7, srp_pdf_text('Bills: ' . number_format((float)($summary['total_bills'] ?? 0))), 1, 0, 'L', true);
$pdf->Cell(46, 7, srp_pdf_text('Net: Rs. ' . number_format((float)($summary['net_sales'] ?? 0), 2)), 1, 0, 'L', true);
$pdf->Cell(46, 7, srp_pdf_text('Paid: Rs. ' . number_format((float)($summary['paid_amount'] ?? 0), 2)), 1, 0, 'L', true);
$pdf->Cell(46, 7, srp_pdf_text('Balance: Rs. ' . number_format((float)($summary['balance_amount'] ?? 0), 2)), 1, 0, 'L', true);
$pdf->Cell(46, 7, srp_pdf_text('Discount: Rs. ' . number_format((float)($summary['discount_total'] ?? 0), 2)), 1, 0, 'L', true);
$pdf->Cell(46, 7, srp_pdf_text('Avg: Rs. ' . number_format((float)($summary['average_bill_value'] ?? 0), 2)), 1, 1, 'L', true);
$pdf->Ln(4);

$headings = $matrix['headings'];
$rows = $matrix['rows'];
$usableWidth = 277;
$colCount = max(1, count($headings));
$w = max(16, $usableWidth / $colCount);
$widths = array_fill(0, $colCount, $w);

$pdf->SetFont('Arial', 'B', 6);
$pdf->SetFillColor(226, 232, 240);
foreach ($headings as $i => $heading) {
    $pdf->Cell($widths[$i], 6, srp_pdf_text($heading), 1, 0, 'L', true);
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 6);
if (empty($rows)) {
    $pdf->Cell(0, 8, 'No records found.', 1, 1, 'C');
} else {
    foreach ($rows as $row) {
        $pdf->Row($row, $widths, 4);
    }
}

$pdf->Output('I', 'sales-report-' . $type . '-' . date('Ymd-His') . '.pdf');
exit;
