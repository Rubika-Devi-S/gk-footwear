<?php
/**
 * Universal Footwear POS - Supplier Ledger Report Print
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/controllers/SupplierLedgerReportController.php';

if (function_exists('require_business_login')) { require_business_login(); }
if (function_exists('require_page_access')) { require_page_access($conn, 'supplier-ledger-report.php'); }

function pr_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function pr_money($v): string { return '₹' . number_format((float)$v, 2); }
function pr_user_id(): int { if (function_exists('current_user_id')) return (int)current_user_id(); return (int)($_SESSION['user_id'] ?? 0); }
function pr_business_id(): int { if (function_exists('current_business_id')) return (int)current_business_id(); return (int)($_SESSION['business_id'] ?? 0); }
function pr_is_admin($conn): bool { if (function_exists('is_business_admin')) return (bool)is_business_admin($conn); return (int)($_SESSION['role_id'] ?? 0) === 1; }

$businessId = pr_business_id();
$controller = new SupplierLedgerReportController($conn, $businessId, pr_user_id(), pr_is_admin($conn));
$type = $_GET['type'] ?? 'suppliers';

if ($type === 'statement') {
    $data = $controller->statement($_GET); $title = 'Supplier Statement'; $rows = $data['rows'] ?? [];
} elseif ($type === 'ledger') {
    $data = $controller->ledger(array_merge($_GET, ['per_page' => 200])); $title = 'Supplier Ledger Entries'; $rows = $data['rows'] ?? [];
} elseif ($type === 'purchases') {
    $data = $controller->purchases(array_merge($_GET, ['per_page' => 200])); $title = 'Supplier Purchase History'; $rows = $data['rows'] ?? [];
} else {
    $data = $controller->suppliers(array_merge($_GET, ['per_page' => 200], $type === 'outstanding' ? ['ledger_status' => 'outstanding'] : []));
    $title = $type === 'outstanding' ? 'Supplier Outstanding Report' : 'Supplier Ledger Summary'; $rows = $data['rows'] ?? [];
}
$summary = $data['summary'] ?? [];
?>
<!doctype html><html><head><meta charset="utf-8"><title><?= pr_e($title) ?></title><style>
body{font-family:Arial,sans-serif;font-size:12px;color:#111827;margin:18px}.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #111827;padding-bottom:10px;margin-bottom:12px}h1{font-size:22px;margin:0}.muted{color:#64748b}.summary{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-bottom:12px}.card{border:1px solid #dbe4f0;border-radius:10px;padding:8px}.card small{display:block;color:#64748b;font-weight:700;text-transform:uppercase}.card b{font-size:15px}table{width:100%;border-collapse:collapse}th{background:#f1f5f9;text-align:left;font-size:10px;text-transform:uppercase}th,td{border:1px solid #e5e7eb;padding:6px;vertical-align:top}.right{text-align:right}.due{color:#b91c1c;font-weight:700}.good{color:#15803d;font-weight:700}@media print{button{display:none}body{margin:8px}.summary{grid-template-columns:repeat(5,1fr)}}
</style></head><body>
<div class="head"><div><h1><?= pr_e($title) ?></h1><div class="muted">From <?= pr_e($_GET['from_date'] ?? '-') ?> to <?= pr_e($_GET['to_date'] ?? '-') ?></div></div><button onclick="window.print()">Print</button></div>
<?php if ($type !== 'statement'): ?><div class="summary"><div class="card"><small>Suppliers</small><b><?= pr_e($summary['total_suppliers'] ?? 0) ?></b></div><div class="card"><small>Opening</small><b><?= pr_money($summary['opening_outstanding'] ?? 0) ?></b></div><div class="card"><small>Purchase</small><b><?= pr_money($summary['total_purchase_amount'] ?? 0) ?></b></div><div class="card"><small>Paid</small><b><?= pr_money($summary['total_paid_amount'] ?? 0) ?></b></div><div class="card"><small>Outstanding</small><b><?= pr_money($summary['total_balance_amount'] ?? 0) ?></b></div></div><?php endif; ?>
<?php if ($type === 'statement'): $supplier = $data['supplier'] ?? []; $s = $data['summary'] ?? []; ?>
<h3><?= pr_e($supplier['supplier_name'] ?? 'Supplier') ?> <?= !empty($supplier['mobile']) ? '(' . pr_e($supplier['mobile']) . ')' : '' ?></h3>
<div class="summary"><div class="card"><small>Opening</small><b><?= pr_money($s['opening_balance'] ?? 0) ?></b></div><div class="card"><small>Debit</small><b><?= pr_money($s['total_debit'] ?? 0) ?></b></div><div class="card"><small>Credit</small><b><?= pr_money($s['total_credit'] ?? 0) ?></b></div><div class="card"><small>Closing</small><b><?= pr_money($s['closing_balance'] ?? 0) ?></b></div><div class="card"><small>Current</small><b><?= pr_money($s['current_balance'] ?? 0) ?></b></div></div>
<table><thead><tr><th>Date</th><th>Type</th><th>Purpose</th><th>Ref No</th><th>Branch</th><th>Description</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= pr_e($r['entry_display'] ?? '') ?></td><td><?= pr_e($r['reference_type'] ?? '') ?></td><td><?= pr_e($r['purpose'] ?? '') ?></td><td><?= pr_e($r['reference_no'] ?? '') ?></td><td><?= pr_e(trim(($r['branch_name'] ?? '') . ' ' . ($r['floor_name'] ?? ''))) ?></td><td><?= pr_e($r['remarks'] ?? '') ?></td><td class="right due"><?= pr_money($r['debit'] ?? 0) ?></td><td class="right good"><?= pr_money($r['credit'] ?? 0) ?></td><td class="right"><?= pr_money($r['balance'] ?? 0) ?></td></tr><?php endforeach; ?></tbody></table>
<?php elseif ($type === 'ledger'): ?>
<table><thead><tr><th>Date</th><th>Supplier</th><th>Type</th><th>Purpose</th><th>Reference</th><th>Branch</th><th class="right">Debit</th><th class="right">Credit</th><th class="right">Balance</th><th>Remarks</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= pr_e($r['entry_display'] ?? '') ?></td><td><?= pr_e($r['supplier_name'] ?? '') ?><br><span class="muted"><?= pr_e($r['mobile'] ?? '') ?></span></td><td><?= pr_e($r['reference_type'] ?? '') ?></td><td><?= pr_e($r['purpose'] ?? '') ?></td><td><?= pr_e($r['reference_no'] ?? '') ?></td><td><?= pr_e(trim(($r['branch_name'] ?? '') . ' ' . ($r['floor_name'] ?? ''))) ?></td><td class="right due"><?= pr_money($r['debit'] ?? 0) ?></td><td class="right good"><?= pr_money($r['credit'] ?? 0) ?></td><td class="right"><?= pr_money($r['balance'] ?? 0) ?></td><td><?= pr_e($r['remarks'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table>
<?php elseif ($type === 'purchases'): ?>
<table><thead><tr><th>Date</th><th>Batch</th><th>Invoice</th><th>Supplier</th><th>Branch</th><th class="right">Qty</th><th class="right">Purchase</th><th class="right">MRP</th><th>Status</th><th>Created By</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= pr_e($r['inward_display'] ?? '') ?></td><td><?= pr_e($r['batch_no'] ?? '') ?></td><td><?= pr_e($r['invoice_number'] ?? '') ?></td><td><?= pr_e($r['supplier_name'] ?? '') ?></td><td><?= pr_e(trim(($r['branch_name'] ?? '') . ' ' . ($r['floor_name'] ?? ''))) ?></td><td class="right"><?= pr_e($r['total_qty'] ?? 0) ?></td><td class="right"><?= pr_money($r['purchase_total_value'] ?? 0) ?></td><td class="right"><?= pr_money($r['mrp_total_value'] ?? 0) ?></td><td><?= pr_e($r['batch_status'] ?? '') ?></td><td><?= pr_e($r['created_by_name'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table>
<?php else: ?>
<table><thead><tr><th>Supplier</th><th>Mobile</th><th>GSTIN</th><th class="right">Opening</th><th class="right">Purchases</th><th class="right">Purchase Value</th><th class="right">Paid</th><th class="right">Outstanding</th><th>Last Purchase</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?= pr_e($r['supplier_name'] ?? '') ?></td><td><?= pr_e($r['mobile'] ?? '') ?></td><td><?= pr_e($r['gstin'] ?? '') ?></td><td class="right"><?= pr_money($r['opening_outstanding'] ?? 0) ?></td><td class="right"><?= pr_e($r['purchase_count'] ?? 0) ?></td><td class="right"><?= pr_money($r['total_purchase_amount'] ?? 0) ?></td><td class="right good"><?= pr_money($r['total_paid_amount'] ?? 0) ?></td><td class="right due"><?= pr_money($r['balance_amount'] ?? 0) ?></td><td><?= pr_e($r['last_purchase_display'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table>
<?php endif; ?>
<script>window.addEventListener('load',function(){setTimeout(function(){window.print()},300)});</script></body></html>
