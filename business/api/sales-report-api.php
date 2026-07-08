<?php
/**
 * Universal Footwear POS - Sales Report API
 * Place at: api/sales-report-api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../controllers/SalesReportController.php';

date_default_timezone_set('Asia/Kolkata');
if (isset($conn) && $conn instanceof mysqli) {
    @mysqli_query($conn, "SET time_zone = '+05:30'");
}

function sr_api_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sr_api_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int)current_user_id();
    }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function sr_api_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int)current_business_id();
    }
    return (int)($_SESSION['business_id'] ?? 0);
}

function sr_api_is_admin(mysqli $conn): bool
{
    if (function_exists('is_business_admin')) {
        return (bool)is_business_admin($conn);
    }
    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));
    return in_array($roleName, array('admin', 'business admin', 'branch admin'), true) || (int)($_SESSION['role_id'] ?? 0) === 1;
}

function sr_export_filename(string $prefix, string $ext): string
{
    return $prefix . '-' . date('Ymd-His') . '.' . $ext;
}

function sr_send_csv(array $headings, array $rows, string $filename): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headings);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

function sr_send_excel(array $headings, array $rows, string $filename): void
{
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    echo "<table border=\"1\"><thead><tr>";
    foreach ($headings as $heading) {
        echo '<th>' . htmlspecialchars((string)$heading, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    echo "</tr></thead><tbody>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars((string)$cell, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
    exit;
}

try {
    if (function_exists('require_business_login')) {
        require_business_login();
    }
    if (function_exists('require_page_access')) {
        require_page_access($conn, 'sales-report.php');
    }

    $businessId = sr_api_business_id();
    if ($businessId <= 0) {
        sr_api_json(array('success' => false, 'message' => 'Business session missing. Please login again.'), 401);
    }

    $controller = new SalesReportController($conn, $businessId, sr_api_user_id(), sr_api_is_admin($conn));
    $action = (string)($_GET['action'] ?? 'init');

    if ($action === 'export') {
        $type = (string)($_GET['export_type'] ?? $_GET['type'] ?? 'bills');
        $format = strtolower((string)($_GET['export_format'] ?? 'csv'));
        $matrix = $controller->exportMatrix($type, $_GET);
        if ($format === 'excel' || $format === 'xls') {
            sr_send_excel($matrix['headings'], $matrix['rows'], sr_export_filename('sales-report-' . $type, 'xls'));
        }
        sr_send_csv($matrix['headings'], $matrix['rows'], sr_export_filename('sales-report-' . $type, 'csv'));
    }

    switch ($action) {
        case 'init':
            sr_api_json($controller->init($_GET));
            break;
        case 'summary':
            sr_api_json($controller->summary($_GET));
            break;
        case 'list':
            sr_api_json($controller->list($_GET));
            break;
        case 'items':
            sr_api_json($controller->items($_GET));
            break;
        case 'daily_trend':
            sr_api_json($controller->dailyTrend($_GET));
            break;
        case 'branch_summary':
            sr_api_json($controller->branchSummary($_GET));
            break;
        case 'payment_summary':
            sr_api_json($controller->paymentSummary($_GET));
            break;
        case 'top_products':
            sr_api_json($controller->topProducts($_GET));
            break;
        case 'customer_summary':
            sr_api_json($controller->customerSummary($_GET));
            break;
        case 'sales_user_summary':
            sr_api_json($controller->salesUserSummary($_GET));
            break;
        case 'category_summary':
            sr_api_json($controller->categorySummary($_GET));
            break;
        case 'hourly_summary':
            sr_api_json($controller->hourlySummary($_GET));
            break;
        case 'analytics':
            sr_api_json($controller->analytics($_GET));
            break;
        default:
            sr_api_json(array('success' => false, 'message' => 'Invalid sales report API action.'), 400);
    }
} catch (Throwable $e) {
    sr_api_json(array('success' => false, 'message' => 'Sales report API error: ' . $e->getMessage()), 500);
}
