<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../controllers/StockInwardController.php';

require_business_login();
require_page_access($conn, 'stock-inward.php');

header('Content-Type: application/json; charset=utf-8');

$businessId = (int) current_business_id();

function stock_inward_api_response(bool $success, string $message = '', array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function stock_inward_api_csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
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

if ($businessId <= 0) {
    stock_inward_api_response(false, 'Business session missing. Please login again.', [], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$controller = new StockInwardController($conn, $businessId);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        stock_inward_api_csrf_check();
    }

    if ($action === 'masters') {
        stock_inward_api_response(true, '', $controller->masters());
    }

    if ($action === 'list') {
        stock_inward_api_response(true, '', $controller->list($_GET));
    }

    if ($action === 'get') {
        stock_inward_api_response(true, '', $controller->get($_GET));
    }

    if ($action === 'save_stock_inward') {
        $result = $controller->save($_POST);
        stock_inward_api_response(true, $result['message'], $result);
    }

    if ($action === 'cancel_stock_inward') {
        $result = $controller->changeStatus($_POST, 'cancelled');
        stock_inward_api_response(true, $result['message'], $result);
    }

    if ($action === 'delete_stock_inward') {
        $result = $controller->changeStatus($_POST, 'deleted');
        stock_inward_api_response(true, $result['message'], $result);
    }

    stock_inward_api_response(false, 'Invalid stock inward API action.', [], 400);
} catch (InvalidArgumentException $e) {
    stock_inward_api_response(false, $e->getMessage(), [], 422);
} catch (RuntimeException $e) {
    stock_inward_api_response(false, $e->getMessage(), [], 409);
} catch (Throwable $e) {
    stock_inward_api_response(false, 'Server error: ' . $e->getMessage(), [], 500);
}
