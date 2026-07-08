<?php
/**
 * Universal Footwear POS - Customer Ledger Report API
 * Place at: business/api/customer-ledger-report-api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../controllers/CustomerLedgerReportController.php';

function clr_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function clr_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int)current_user_id();
    }

    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function clr_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int)current_business_id();
    }

    return (int)($_SESSION['business_id'] ?? 0);
}

function clr_is_admin(mysqli $conn): bool
{
    if (function_exists('is_business_admin')) {
        return (bool)is_business_admin($conn);
    }

    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));

    return in_array($roleName, ['admin', 'business admin', 'branch admin'], true)
        || (int)($_SESSION['role_id'] ?? 0) === 1;
}

function clr_export_csv(array $export, string $format = 'csv'): void
{
    $filename = $export['filename'] ?? 'customer-ledger-report.csv';

    if ($format === 'excel') {
        $filename = preg_replace('/\.csv$/', '.xls', $filename);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
    }

    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, $export['headers'] ?? []);

    foreach (($export['rows'] ?? []) as $r) {
        $type = $export['type'] ?? 'customers';

        if ($type === 'ledger' || $type === 'statement') {
            fputcsv($out, [
                $r['entry_display'] ?? $r['entry_datetime'] ?? '',
                $r['customer_name'] ?? '',
                $r['mobile'] ?? '',
                $r['display_type'] ?? $r['reference_type'] ?? '',
                $r['reference_no'] ?? '',
                trim(($r['branch_name'] ?? '') . ' ' . ($r['floor_name'] ?? '')),
                $r['debit'] ?? '0.00',
                $r['credit'] ?? '0.00',
                $r['balance'] ?? '0.00',
                $r['remarks'] ?? '',
            ]);
            continue;
        }

        if ($type === 'bills') {
            fputcsv($out, [
                $r['bill_display'] ?? $r['created_at'] ?? '',
                $r['bill_no'] ?? '',
                $r['order_no'] ?? '',
                $r['customer_name'] ?? '',
                trim(($r['branch_name'] ?? '') . ' ' . ($r['floor_name'] ?? '')),
                $r['net_amount'] ?? '0.00',
                $r['paid_amount'] ?? '0.00',
                $r['balance_amount'] ?? '0.00',
                $r['payment_status'] ?? '',
                $r['created_by_name'] ?? '',
            ]);
            continue;
        }

        fputcsv($out, [
            $r['customer_name'] ?? '',
            $r['mobile'] ?? '',
            $r['gstin'] ?? '',
            $r['opening_outstanding'] ?? '0.00',
            $r['bill_count'] ?? '0',
            $r['total_bill_amount'] ?? '0.00',
            $r['total_paid_amount'] ?? '0.00',
            $r['balance_amount'] ?? '0.00',
            $r['last_bill_display'] ?? '',
        ]);
    }

    fclose($out);
    exit;
}

try {
    if (function_exists('require_business_login')) {
        require_business_login();
    }

    if (function_exists('require_page_access')) {
        require_page_access($conn, 'customer-ledger-report.php');
    }

    $businessId = clr_business_id();

    if ($businessId <= 0) {
        clr_json(['success' => false, 'message' => 'Business session missing. Please login again.'], 401);
    }

    $controller = new CustomerLedgerReportController($conn, $businessId, clr_user_id(), clr_is_admin($conn));
    $action = (string)($_GET['action'] ?? 'init');

    if ($action === 'init') { clr_json($controller->init($_GET)); }
    if ($action === 'summary') { clr_json($controller->summary($_GET)); }
    if ($action === 'customers') { clr_json($controller->customers($_GET)); }
    if ($action === 'ledger') { clr_json($controller->ledger($_GET)); }
    if ($action === 'bills') { clr_json($controller->bills($_GET)); }
    if ($action === 'outstanding') { clr_json($controller->outstanding($_GET)); }
    if ($action === 'statement') { clr_json($controller->statement($_GET)); }
    if ($action === 'verify') { clr_json($controller->verify($_GET)); }

    if ($action === 'export') {
        $export = $controller->export($_GET);
        clr_export_csv($export, (string)($_GET['format'] ?? 'csv'));
    }

    clr_json(['success' => false, 'message' => 'Invalid customer ledger report action.'], 400);
} catch (Throwable $e) {
    clr_json(['success' => false, 'message' => 'Customer Ledger Report API error: ' . $e->getMessage()], 500);
}
