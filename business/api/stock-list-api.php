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
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function stock_list_current_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int) current_user_id();
    }

    return (int) ($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function stock_list_posted_csrf_token(): string
{
    return trim((string) (
        $_POST['csrf_token']
        ?? $_POST['_token']
        ?? $_POST['csrf']
        ?? $_SERVER['HTTP_X_CSRF_TOKEN']
        ?? ''
    ));
}

/**
 * Validate the actual token sent by stock-list.php.
 *
 * The previous API called verify_csrf()/csrf_verify() without passing the
 * submitted token. In installations where the helper expects a token
 * argument, that always produced "Invalid security token".
 */
function stock_list_verify_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }

    $token = stock_list_posted_csrf_token();

    if ($token === '') {
        throw new RuntimeException('Security token is missing. Please refresh the page and try again.');
    }

    // Make the token available under every common key before calling helpers
    // that read directly from $_POST.
    $_POST['csrf_token'] = $token;
    $_POST['_token'] = $token;
    $_POST['csrf'] = $token;

    if (function_exists('verify_csrf_token')) {
        if (!verify_csrf_token($token)) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }
        return;
    }

    if (function_exists('validate_csrf_token')) {
        if (!validate_csrf_token($token)) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }
        return;
    }

    if (function_exists('csrf_validate')) {
        if (!csrf_validate($token)) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }
        return;
    }

    if (function_exists('csrf_verify')) {
        try {
            $valid = csrf_verify($token);
        } catch (ArgumentCountError $e) {
            $valid = csrf_verify();
        }

        if ($valid === false) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }
        return;
    }

    if (function_exists('verify_csrf')) {
        try {
            $valid = verify_csrf($token);
        } catch (ArgumentCountError $e) {
            $valid = verify_csrf();
        }

        if ($valid === false) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }
        return;
    }

    if (function_exists('check_csrf')) {
        try {
            $valid = check_csrf($token);
        } catch (ArgumentCountError $e) {
            $valid = check_csrf();
        }

        if ($valid === false) {
            throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
        }
        return;
    }

    // Safe fallback for older installations.
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
        throw new RuntimeException('Invalid security token. Please refresh the page and try again.');
    }
}

function stock_list_table_exists(mysqli $conn, string $table): bool
{
    $safe = mysqli_real_escape_string($conn, $table);
    $result = mysqli_query($conn, "SHOW TABLES LIKE '{$safe}'");
    return $result instanceof mysqli_result && mysqli_num_rows($result) > 0;
}

function stock_list_column_exists(mysqli $conn, string $table, string $column): bool
{
    $safeTable = mysqli_real_escape_string($conn, $table);
    $safeColumn = mysqli_real_escape_string($conn, $column);
    $result = mysqli_query($conn, "SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    return $result instanceof mysqli_result && mysqli_num_rows($result) > 0;
}

function stock_list_delete_rows_by_stock_item(
    mysqli $conn,
    string $table,
    int $businessId,
    int $stockItemId
): void {
    if (
        !stock_list_table_exists($conn, $table)
        || !stock_list_column_exists($conn, $table, 'stock_item_id')
    ) {
        return;
    }

    $hasBusinessId = stock_list_column_exists($conn, $table, 'business_id');

    if ($hasBusinessId) {
        $sql = "DELETE FROM `{$table}` WHERE business_id = ? AND stock_item_id = ?";
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new RuntimeException(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $stockItemId);
    } else {
        $sql = "DELETE FROM `{$table}` WHERE stock_item_id = ?";
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new RuntimeException(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, 'i', $stockItemId);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function stock_list_delete_out_of_stock(
    mysqli $conn,
    int $businessId,
    int $stockItemId
): array {
    if ($stockItemId <= 0) {
        throw new RuntimeException('Invalid stock product selected.');
    }

    mysqli_begin_transaction($conn);

    try {
        $stmt = mysqli_prepare($conn, "
            SELECT
                stock_item_id,
                article_no,
                article_name,
                COALESCE(available_qty, 0) AS available_qty
            FROM stock_inward_items
            WHERE business_id = ?
              AND stock_item_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        if (!$stmt) {
            throw new RuntimeException(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $stockItemId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $item = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$item) {
            throw new RuntimeException('The selected product was not found.');
        }

        if ((float)($item['available_qty'] ?? 0) > 0.000001) {
            throw new RuntimeException(
                'Delete is not allowed because this product still has available stock.'
            );
        }

        // Child rows must be removed before the stock item to satisfy foreign keys.
        stock_list_delete_rows_by_stock_item($conn, 'stock_barcodes', $businessId, $stockItemId);
        stock_list_delete_rows_by_stock_item($conn, 'stock_movements', $businessId, $stockItemId);
        stock_list_delete_rows_by_stock_item($conn, 'stock_adjustments', $businessId, $stockItemId);

        $stmt = mysqli_prepare($conn, "
            DELETE FROM stock_inward_items
            WHERE business_id = ?
              AND stock_item_id = ?
              AND COALESCE(available_qty, 0) <= 0
        ");

        if (!$stmt) {
            throw new RuntimeException(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $stockItemId);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected !== 1) {
            throw new RuntimeException(
                'The product could not be deleted because its stock status changed.'
            );
        }

        mysqli_commit($conn);

        $label = trim(
            (string)($item['article_no'] ?? '') . ' ' .
            (string)($item['article_name'] ?? '')
        );

        return [
            'success' => true,
            'message' => ($label !== '' ? $label : 'Out-of-stock product')
                . ' was permanently deleted.'
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
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
    $isAdmin = function_exists('is_business_admin')
        ? (bool) is_business_admin($conn)
        : true;

    $controller = new StockListController(
        $conn,
        $businessId,
        $userId,
        $isAdmin
    );

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $method === 'POST'
        ? (string)($_POST['action'] ?? '')
        : (string)($_GET['action'] ?? 'list');

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
            stock_list_json(
                $controller->get((int)($_GET['stock_item_id'] ?? 0))
            );
            break;

        case 'barcode_lookup':
            stock_list_json(
                $controller->barcodeLookup(
                    (int)($_GET['branch_id'] ?? 0),
                    (string)($_GET['barcode'] ?? '')
                )
            );
            break;

        case 'delete_out_of_stock':
            if ($method !== 'POST') {
                throw new RuntimeException('Delete must be submitted using POST.');
            }

            stock_list_json(
                stock_list_delete_out_of_stock(
                    $conn,
                    $businessId,
                    (int)($_POST['stock_item_id'] ?? 0)
                )
            );
            break;

        default:
            stock_list_json([
                'success' => false,
                'message' => 'Invalid stock list API action.'
            ], 400);
    }
} catch (Throwable $e) {
    stock_list_json([
        'success' => false,
        'message' => 'Stock list API error: ' . $e->getMessage(),
    ], 500);
}
