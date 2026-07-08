<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../controllers/StockListController.php';

header('Content-Type: application/json; charset=utf-8');

function stock_list_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function stock_list_current_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int) current_user_id();
    }

    return (int) ($_SESSION['user_id'] ?? 0);
}

function stock_list_verify_csrf(): void
{
    if (function_exists('verify_csrf')) {
        verify_csrf();
        return;
    }

    if (function_exists('csrf_verify')) {
        csrf_verify();
        return;
    }

    if (function_exists('check_csrf')) {
        check_csrf();
        return;
    }
}

try {
    require_business_login();
    require_page_access($conn, 'stock-list.php');

    $businessId = (int) current_business_id();
    if ($businessId <= 0) {
        stock_list_json([
            'success' => false,
            'message' => 'Business session missing. Please login again.',
        ], 401);
    }

    $userId = stock_list_current_user_id();
    $isAdmin = function_exists('is_business_admin') ? (bool) is_business_admin($conn) : true;
    $controller = new StockListController($conn, $businessId, $userId, $isAdmin);

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? 'list');

    if ($method === 'POST') {
        stock_list_verify_csrf();
    }

    switch ($action) {
        case 'init':
            stock_list_json($controller->init($_GET));
            break;

        case 'list':
            stock_list_json($controller->list($_GET));
            break;

        case 'get':
            stock_list_json($controller->get((int) ($_GET['stock_item_id'] ?? 0)));
            break;

        case 'barcode_lookup':
            stock_list_json($controller->barcodeLookup((int) ($_GET['branch_id'] ?? 0), (string) ($_GET['barcode'] ?? '')));
            break;

        default:
            stock_list_json(['success' => false, 'message' => 'Invalid stock list API action.'], 400);
    }
} catch (Throwable $e) {
    stock_list_json([
        'success' => false,
        'message' => 'Stock list API error: ' . $e->getMessage(),
    ], 500);
}
