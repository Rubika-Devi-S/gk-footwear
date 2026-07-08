<?php
/**
 * GK Footwear POS - Payment Ledger Report API
 * Place this file at: api/payment-ledger-report-api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../controllers/PaymentLedgerReportController.php';

function plr_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function plr_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int)current_user_id();
    }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function plr_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int)current_business_id();
    }
    return (int)($_SESSION['business_id'] ?? 0);
}

function plr_is_admin(mysqli $conn): bool
{
    if (function_exists('is_business_admin')) {
        return (bool)is_business_admin($conn);
    }
    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));
    return in_array($roleName, array('admin', 'business admin', 'branch admin'), true) || (int)($_SESSION['role_id'] ?? 0) === 1;
}

try {
    if (function_exists('require_business_login')) {
        require_business_login();
    }
    if (function_exists('require_page_access')) {
        require_page_access($conn, 'payment-ledger-report.php');
    }

    $businessId = plr_business_id();
    if ($businessId <= 0) {
        plr_json(array('success' => false, 'message' => 'Business session missing. Please login again.'), 401);
    }

    $controller = new PaymentLedgerReportController($conn, $businessId, plr_user_id(), plr_is_admin($conn));
    $action = (string)($_GET['action'] ?? 'init');

    if ($action === 'init') {
        plr_json($controller->init($_GET));
    }
    if ($action === 'summary') {
        plr_json($controller->summary($_GET));
    }
    if ($action === 'payments') {
        plr_json($controller->payments($_GET));
    }
    if ($action === 'ledger') {
        plr_json($controller->ledger($_GET));
    }
    if ($action === 'outstanding') {
        plr_json($controller->outstanding($_GET));
    }
    if ($action === 'daily_summary') {
        plr_json($controller->daily($_GET));
    }
    if ($action === 'method_summary') {
        plr_json($controller->method($_GET));
    }
    if ($action === 'cashier_summary') {
        plr_json($controller->cashier($_GET));
    }
    if ($action === 'history') {
        plr_json($controller->history($_GET));
    }
    if ($action === 'export') {
        $report = (string)($_GET['report'] ?? $_GET['type'] ?? 'payments');
        $format = strtolower((string)($_GET['format'] ?? 'csv'));
        $controller->export($report, $format, $_GET);
    }

    plr_json(array('success' => false, 'message' => 'Invalid payment ledger API action.'), 400);
} catch (Throwable $e) {
    plr_json(array(
        'success' => false,
        'message' => 'Payment Ledger API error: ' . $e->getMessage(),
    ), 500);
}
