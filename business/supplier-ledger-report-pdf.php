<?php
/**
 * Universal Footwear POS - Supplier Ledger Report PDF Export
 * Uses FPDF when available; falls back to printable HTML if FPDF is not installed.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/controllers/SupplierLedgerReportController.php';

if (function_exists('require_business_login')) { require_business_login(); }
if (function_exists('require_page_access')) { require_page_access($conn, 'supplier-ledger-report.php'); }

function pdf_user_id(): int { if (function_exists('current_user_id')) return (int)current_user_id(); return (int)($_SESSION['user_id'] ?? 0); }
function pdf_business_id(): int { if (function_exists('current_business_id')) return (int)current_business_id(); return (int)($_SESSION['business_id'] ?? 0); }
function pdf_is_admin($conn): bool { if (function_exists('is_business_admin')) return (bool)is_business_admin($conn); return (int)($_SESSION['role_id'] ?? 0) === 1; }
function pdf_money($v): string { return number_format((float)$v, 2); }
function pdf_clean($v): string { return preg_replace('/[^\x20-\x7E]/', '', (string)$v); }

if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; }
if (!class_exists('FPDF')) {
    if (file_exists(__DIR__ . '/libs/fpdf.php')) { require_once __DIR__ . '/libs/fpdf.php'; }
    elseif (file_exists(__DIR__ . '/fpdf.php')) { require_once __DIR__ . '/fpdf.php'; }
}
if (!class_exists('FPDF')) { require __DIR__ . '/supplier-ledger-report-print.php'; exit; }

$controller = new SupplierLedgerReportController($conn, pdf_business_id(), pdf_user_id(), pdf_is_admin($conn));
$type = $_GET['type'] ?? 'suppliers';
if ($type === 'statement') { $data = $controller->statement($_GET); $rows = $data['rows'] ?? []; $title = 'Supplier Statement'; }
elseif ($type === 'ledger') { $data = $controller->ledger(array_merge($_GET, ['per_page'=>200])); $rows = $data['rows'] ?? []; $title = 'Supplier Ledger Entries'; }
elseif ($type === 'purchases') { $data = $controller->purchases(array_merge($_GET, ['per_page'=>200])); $rows = $data['rows'] ?? []; $title = 'Supplier Purchase History'; }
else { $data = $controller->suppliers(array_merge($_GET, ['per_page'=>200], $type === 'outstanding' ? ['ledger_status'=>'outstanding'] : [])); $rows = $data['rows'] ?? []; $title = $type === 'outstanding' ? 'Supplier Outstanding Report' : 'Supplier Ledger Summary'; }

class SupplierLedgerPDF extends FPDF
{
    public $reportTitle = 'Supplier Ledger Report';
    function Header(){ $this->SetFont('Arial','B',13); $this->Cell(0,8,pdf_clean($this->reportTitle),0,1,'L'); $this->SetFont('Arial','',8); $this->Cell(0,5,'Generated: '.date('d-m-Y h:i A'),0,1,'L'); $this->Ln(2); }
    function Footer(){ $this->SetY(-12); $this->SetFont('Arial','I',8); $this->Cell(0,8,'Page '.$this->PageNo().'/{nb}',0,0,'C'); }
    function row(array $cells, array $widths, float $h=6): void { foreach($cells as $i=>$txt){ $this->Cell($widths[$i],$h,pdf_clean($txt),1,0,($i>=count($cells)-3?'R':'L')); } $this->Ln(); }
}

$pdf = new SupplierLedgerPDF('L','mm','A4');
$pdf->AliasNbPages();
$pdf->reportTitle = $title;
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

if ($type === 'statement') {
    $supplier = $data['supplier'] ?? []; $s = $data['summary'] ?? [];
    $pdf->SetFont('Arial','B',10); $pdf->Cell(0,7,pdf_clean(($supplier['supplier_name'] ?? 'Supplier') . ' ' . ($supplier['mobile'] ?? '')),0,1);
    $pdf->SetFont('Arial','',8); $pdf->Cell(0,5,'Opening: '.pdf_money($s['opening_balance'] ?? 0).' | Debit: '.pdf_money($s['total_debit'] ?? 0).' | Credit: '.pdf_money($s['total_credit'] ?? 0).' | Closing: '.pdf_money($s['closing_balance'] ?? 0).' | Current: '.pdf_money($s['current_balance'] ?? 0),0,1);
    $widths=[30,25,25,26,34,65,24,24,24]; $heads=['Date','Type','Purpose','Ref No','Branch','Description','Debit','Credit','Balance'];
    $pdf->SetFont('Arial','B',7); $pdf->row($heads,$widths,6); $pdf->SetFont('Arial','',7);
    foreach($rows as $r){ $pdf->row([$r['entry_display']??'', $r['reference_type']??'', $r['purpose']??'', $r['reference_no']??'', trim(($r['branch_name']??'').' '.($r['floor_name']??'')), $r['remarks']??'', pdf_money($r['debit']??0), pdf_money($r['credit']??0), pdf_money($r['balance']??0)], $widths, 6); }
} elseif ($type === 'ledger') {
    $widths=[30,42,24,25,26,34,24,24,24,45]; $heads=['Date','Supplier','Type','Purpose','Ref','Branch','Debit','Credit','Balance','Remarks'];
    $pdf->SetFont('Arial','B',7); $pdf->row($heads,$widths,6); $pdf->SetFont('Arial','',7);
    foreach($rows as $r){ $pdf->row([$r['entry_display']??'', $r['supplier_name']??'', $r['reference_type']??'', $r['purpose']??'', $r['reference_no']??'', trim(($r['branch_name']??'').' '.($r['floor_name']??'')), pdf_money($r['debit']??0), pdf_money($r['credit']??0), pdf_money($r['balance']??0), $r['remarks']??''], $widths, 6); }
} elseif ($type === 'purchases') {
    $widths=[26,30,30,48,38,18,30,28,24,38]; $heads=['Date','Batch','Invoice','Supplier','Branch','Qty','Purchase','MRP','Status','Created By'];
    $pdf->SetFont('Arial','B',7); $pdf->row($heads,$widths,6); $pdf->SetFont('Arial','',7);
    foreach($rows as $r){ $pdf->row([$r['inward_display']??'', $r['batch_no']??'', $r['invoice_number']??'', $r['supplier_name']??'', trim(($r['branch_name']??'').' '.($r['floor_name']??'')), $r['total_qty']??'0', pdf_money($r['purchase_total_value']??0), pdf_money($r['mrp_total_value']??0), $r['batch_status']??'', $r['created_by_name']??''], $widths, 6); }
} else {
    $widths=[50,28,28,25,20,32,30,34,44]; $heads=['Supplier','Mobile','GSTIN','Opening','Purchases','Purchase Value','Paid','Outstanding','Last Purchase'];
    $pdf->SetFont('Arial','B',7); $pdf->row($heads,$widths,6); $pdf->SetFont('Arial','',7);
    foreach($rows as $r){ $pdf->row([$r['supplier_name']??'', $r['mobile']??'', $r['gstin']??'', pdf_money($r['opening_outstanding']??0), $r['purchase_count']??0, pdf_money($r['total_purchase_amount']??0), pdf_money($r['total_paid_amount']??0), pdf_money($r['balance_amount']??0), $r['last_purchase_display']??''], $widths, 6); }
}

$pdf->Output('I', preg_replace('/\s+/', '-', strtolower($title)).'.pdf');
exit;
