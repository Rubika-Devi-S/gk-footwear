<?php
/**
 * GK Footwear POS - Bill List API
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../controllers/BillListController.php';

function bill_list_json_response(array $payload)
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bill_list_validate_csrf()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (function_exists('csrf_verify')) {
        $token = $_POST['csrf_token'] ?? $_POST['_token'] ?? '';
        if (!csrf_verify($token)) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }
        return;
    }

    if (function_exists('verify_csrf_token')) {
        $token = $_POST['csrf_token'] ?? $_POST['_token'] ?? '';
        if (!verify_csrf_token($token)) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }
        return;
    }

    if (function_exists('validate_csrf_token')) {
        $token = $_POST['csrf_token'] ?? $_POST['_token'] ?? '';
        if (!validate_csrf_token($token)) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }
        return;
    }
}

try {
    require_business_login();

    $controller = new BillListController($conn);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $method === 'POST'
        ? (string)($_POST['action'] ?? '')
        : (string)($_GET['action'] ?? 'init');

    if ($method === 'POST') {
        bill_list_validate_csrf();
    }

    switch ($action) {
        case 'init':
            bill_list_json_response($controller->init($_GET));
            break;

        case 'list':
            bill_list_json_response($controller->list($_GET));
            break;

        case 'get':
            bill_list_json_response($controller->get($_GET));
            break;

        case 'cancel_bill':
            bill_list_json_response($controller->cancel($_POST));
            break;

        case 'delete_bill':
            bill_list_json_response($controller->delete($_POST));
            break;

        default:
            throw new Exception('Invalid API action.');
    }
} catch (Throwable $e) {
    http_response_code(200);
    bill_list_json_response(array(
        'success' => false,
        'message' => $e->getMessage()
    ));
}
