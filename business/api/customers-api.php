<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../controllers/CustomerController.php';

function customer_api_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function customer_api_bind(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || !$params) {
        return;
    }
    $refs = [$stmt, $types];
    foreach ($params as $key => $value) {
        $refs[] = &$params[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $refs);
}

function customer_api_table_exists(mysqli $conn, string $tableName): bool
{
    if (function_exists('table_exists')) {
        return (bool)table_exists($conn, $tableName);
    }
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 's', $tableName);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ((int)($row['total'] ?? 0)) > 0;
}

function customer_api_column_exists(mysqli $conn, string $tableName, string $columnName): bool
{
    if (function_exists('table_has_column')) {
        return (bool)table_has_column($conn, $tableName, $columnName);
    }
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ((int)($row['total'] ?? 0)) > 0;
}

function customer_api_verify_csrf_if_possible(): void
{
    if (function_exists('csrf_verify')) {
        if (!csrf_verify()) {
            customer_api_json(['success' => false, 'message' => 'Invalid security token. Refresh and try again.'], 419);
        }
        return;
    }
    if (function_exists('verify_csrf')) {
        verify_csrf();
        return;
    }
    if (function_exists('check_csrf')) {
        check_csrf();
        return;
    }
    if (function_exists('verify_csrf_token')) {
        $token = (string)($_POST['csrf_token'] ?? $_POST['_token'] ?? $_POST['_csrf'] ?? '');
        if (!verify_csrf_token($token)) {
            customer_api_json(['success' => false, 'message' => 'Invalid security token. Refresh and try again.'], 419);
        }
    }
}

function customer_api_current_role_id(): int
{
    if (function_exists('current_role_id')) {
        return (int)current_role_id();
    }
    return (int)($_SESSION['role_id'] ?? 0);
}

function customer_api_can_delete(mysqli $conn, int $businessId): bool
{
    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return true;
    }

    $roleId = customer_api_current_role_id();
    if ($businessId <= 0 || $roleId <= 0) {
        return true;
    }

    if (!customer_api_table_exists($conn, 'business_sidebar_menus') || !customer_api_table_exists($conn, 'business_role_sidebar_access')) {
        return true;
    }

    if (!customer_api_column_exists($conn, 'business_role_sidebar_access', 'can_delete')) {
        return true;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT rsa.can_delete
        FROM business_sidebar_menus sm
        INNER JOIN business_role_sidebar_access rsa
            ON rsa.menu_id = sm.id
           AND rsa.business_id = sm.business_id
           AND rsa.role_id = ?
        WHERE sm.business_id = ?
          AND sm.menu_url = 'customers.php'
          AND sm.is_active = 1
        LIMIT 1
    ");
    if (!$stmt) {
        return true;
    }

    mysqli_stmt_bind_param($stmt, 'ii', $roleId, $businessId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        return true;
    }

    return (int)($row['can_delete'] ?? 0) === 1;
}

function customer_api_scalar_decimal(mysqli $conn, string $sql, string $types, array $params): float
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0.0;
    }
    customer_api_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (float)($row['amount'] ?? 0);
}

function customer_api_bill_status_filter(mysqli $conn): string
{
    if (!customer_api_column_exists($conn, 'bills', 'bill_status')) {
        return '';
    }
    return " AND COALESCE(bill_status, 'active') NOT IN ('cancelled', 'deleted', 'returned', 'return')";
}

function customer_api_pending_bill_rows(mysqli $conn, int $businessId, array $customerIds): array
{
    $customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds), static fn($id) => $id > 0)));
    if (!$customerIds || !customer_api_table_exists($conn, 'bills')) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($customerIds), '?'));
    $types = 'i' . str_repeat('i', count($customerIds));
    $params = array_merge([$businessId], $customerIds);

    $statusFilter = customer_api_bill_status_filter($conn);
    $pendingCondition = "(COALESCE(balance_amount, 0) > 0.0001 OR LOWER(COALESCE(payment_status, '')) IN ('pending', 'partial', 'partially_paid'))";

    $sql = "
        SELECT
            bill_id,
            customer_id,
            bill_no,
            bill_date,
            bill_time,
            net_amount,
            paid_amount,
            balance_amount,
            payment_status,
            bill_status
        FROM bills
        WHERE business_id = ?
          AND customer_id IN ($placeholders)
          $statusFilter
          AND $pendingCondition
        ORDER BY bill_date DESC, bill_id DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return [];
    }
    customer_api_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $row['bill_id'] = (int)($row['bill_id'] ?? 0);
        $row['customer_id'] = (int)($row['customer_id'] ?? 0);
        $row['net_amount'] = round((float)($row['net_amount'] ?? 0), 2);
        $row['paid_amount'] = round((float)($row['paid_amount'] ?? 0), 2);
        $row['balance_amount'] = round((float)($row['balance_amount'] ?? 0), 2);
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $rows;
}

function customer_api_pending_bill_map(mysqli $conn, int $businessId, array $customerIds): array
{
    $map = [];
    foreach ($customerIds as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $map[$id] = [
                'pending_bill_count' => 0,
                'pending_bill_amount' => 0.0,
                'pending_bills' => [],
            ];
        }
    }

    if (!$map) {
        return [];
    }

    foreach (customer_api_pending_bill_rows($conn, $businessId, array_keys($map)) as $bill) {
        $customerId = (int)$bill['customer_id'];
        if (!isset($map[$customerId])) {
            continue;
        }
        $map[$customerId]['pending_bill_count']++;
        $map[$customerId]['pending_bill_amount'] = round($map[$customerId]['pending_bill_amount'] + (float)$bill['balance_amount'], 2);
        if (count($map[$customerId]['pending_bills']) < 5) {
            $map[$customerId]['pending_bills'][] = $bill;
        }
    }

    return $map;
}

function customer_api_enrich_rows_with_pending(mysqli $conn, int $businessId, array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int)($row['customer_id'] ?? 0);
    }
    $pendingMap = customer_api_pending_bill_map($conn, $businessId, $ids);

    foreach ($rows as &$row) {
        $customerId = (int)($row['customer_id'] ?? 0);
        $pending = $pendingMap[$customerId] ?? ['pending_bill_count' => 0, 'pending_bill_amount' => 0.0, 'pending_bills' => []];
        $row['pending_bill_count'] = (int)$pending['pending_bill_count'];
        $row['pending_bill_amount'] = round((float)$pending['pending_bill_amount'], 2);
        $row['pending_bills'] = $pending['pending_bills'];
        $row['can_permanently_delete'] = ((int)($row['status'] ?? 1) === 0 && (int)$row['pending_bill_count'] === 0 && (float)$row['pending_bill_amount'] <= 0.0001);
        if ((int)($row['status'] ?? 1) === 1) {
            $row['delete_block_message'] = 'Please mark this customer as Inactive before deleting permanently.';
        } elseif ((int)$row['pending_bill_count'] > 0 || (float)$row['pending_bill_amount'] > 0.0001) {
            $row['delete_block_message'] = 'This customer cannot be deleted because there are pending bills.';
        } else {
            $row['delete_block_message'] = '';
        }
    }
    unset($row);

    return $rows;
}

function customer_api_enrich_response(mysqli $conn, int $businessId, array $payload): array
{
    if (isset($payload['customers']['items']) && is_array($payload['customers']['items'])) {
        $payload['customers']['items'] = customer_api_enrich_rows_with_pending($conn, $businessId, $payload['customers']['items']);
        return $payload;
    }

    if (isset($payload['customers']) && is_array($payload['customers'])) {
        $payload['customers'] = customer_api_enrich_rows_with_pending($conn, $businessId, $payload['customers']);
        return $payload;
    }

    if (isset($payload['customer']) && is_array($payload['customer'])) {
        $rows = customer_api_enrich_rows_with_pending($conn, $businessId, [$payload['customer']]);
        $payload['customer'] = $rows[0] ?? $payload['customer'];
        $payload['pending_bills'] = $payload['customer']['pending_bills'] ?? [];
        return $payload;
    }

    return $payload;
}

function customer_api_outstanding_row_exists(mysqli $conn, int $businessId, int $customerId): bool
{
    if (!customer_api_table_exists($conn, 'customer_outstanding')) {
        return false;
    }
    $stmt = mysqli_prepare($conn, "SELECT id FROM customer_outstanding WHERE business_id = ? AND customer_id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (bool)$row;
}

function customer_api_pending_balance(mysqli $conn, int $businessId, int $customerId): float
{
    $outstandingBalance = 0.0;
    $openingBalance = 0.0;
    $hasOutstandingRow = customer_api_outstanding_row_exists($conn, $businessId, $customerId);

    if (customer_api_table_exists($conn, 'customer_outstanding')) {
        $outstandingBalance = customer_api_scalar_decimal(
            $conn,
            "SELECT COALESCE(SUM(balance_amount), 0) AS amount FROM customer_outstanding WHERE business_id = ? AND customer_id = ?",
            'ii',
            [$businessId, $customerId]
        );
    }

    if (!$hasOutstandingRow && customer_api_column_exists($conn, 'customers', 'opening_outstanding')) {
        $openingBalance = customer_api_scalar_decimal(
            $conn,
            "SELECT COALESCE(opening_outstanding, 0) AS amount FROM customers WHERE business_id = ? AND customer_id = ? LIMIT 1",
            'ii',
            [$businessId, $customerId]
        );
    }

    return round(max($outstandingBalance, $openingBalance), 2);
}

function customer_api_delete_customer(mysqli $conn, int $businessId, int $userId, array $post): array
{
    customer_api_verify_csrf_if_possible();

    if (!customer_api_can_delete($conn, $businessId)) {
        return ['success' => false, 'message' => 'You do not have permission to delete customers.'];
    }

    $customerId = (int)($post['customer_id'] ?? 0);
    if ($customerId <= 0) {
        return ['success' => false, 'message' => 'Invalid customer selected.'];
    }

    mysqli_begin_transaction($conn);

    try {
        $stmt = mysqli_prepare($conn, "
            SELECT customer_id, customer_name, mobile, status
            FROM customers
            WHERE business_id = ?
              AND customer_id = ?
            LIMIT 1
            FOR UPDATE
        ");
        if (!$stmt) {
            throw new RuntimeException('Unable to prepare customer delete check.');
        }
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
        mysqli_stmt_execute($stmt);
        $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$customer) {
            mysqli_rollback($conn);
            return ['success' => false, 'message' => 'Customer not found.'];
        }

        if ((int)($customer['status'] ?? 1) === 1) {
            mysqli_rollback($conn);
            return ['success' => false, 'message' => 'Please mark this customer as Inactive before deleting permanently.'];
        }

        $pendingMap = customer_api_pending_bill_map($conn, $businessId, [$customerId]);
        $pending = $pendingMap[$customerId] ?? ['pending_bill_count' => 0, 'pending_bill_amount' => 0.0, 'pending_bills' => []];
        if ((int)$pending['pending_bill_count'] > 0 || (float)$pending['pending_bill_amount'] > 0.0001) {
            mysqli_rollback($conn);
            return [
                'success' => false,
                'message' => 'This customer cannot be deleted because there are pending bills.',
                'pending_bill_count' => (int)$pending['pending_bill_count'],
                'pending_bill_amount' => round((float)$pending['pending_bill_amount'], 2),
                'pending_bills' => $pending['pending_bills'],
            ];
        }

        $pendingBalance = customer_api_pending_balance($conn, $businessId, $customerId);
        if ($pendingBalance > 0.0001) {
            mysqli_rollback($conn);
            return [
                'success' => false,
                'message' => 'This customer cannot be deleted because there is a pending balance.',
                'pending_balance' => $pendingBalance,
            ];
        }

        // Paid/cancelled bill history is preserved through bills.customer_id ON DELETE SET NULL.
        // Ledger/outstanding rows are removed only after all delete rules are passed.
        if (customer_api_table_exists($conn, 'customer_ledger')) {
            $ledgerStmt = mysqli_prepare($conn, "DELETE FROM customer_ledger WHERE business_id = ? AND customer_id = ?");
            if ($ledgerStmt) {
                mysqli_stmt_bind_param($ledgerStmt, 'ii', $businessId, $customerId);
                mysqli_stmt_execute($ledgerStmt);
                mysqli_stmt_close($ledgerStmt);
            }
        }

        if (customer_api_table_exists($conn, 'customer_outstanding')) {
            $outStmt = mysqli_prepare($conn, "DELETE FROM customer_outstanding WHERE business_id = ? AND customer_id = ?");
            if ($outStmt) {
                mysqli_stmt_bind_param($outStmt, 'ii', $businessId, $customerId);
                mysqli_stmt_execute($outStmt);
                mysqli_stmt_close($outStmt);
            }
        }

        $deleteStmt = mysqli_prepare($conn, "DELETE FROM customers WHERE business_id = ? AND customer_id = ? LIMIT 1");
        if (!$deleteStmt) {
            throw new RuntimeException('Unable to prepare customer delete query.');
        }
        mysqli_stmt_bind_param($deleteStmt, 'ii', $businessId, $customerId);
        mysqli_stmt_execute($deleteStmt);
        $deletedRows = mysqli_stmt_affected_rows($deleteStmt);
        mysqli_stmt_close($deleteStmt);

        if ($deletedRows <= 0) {
            throw new RuntimeException('Customer delete failed.');
        }

        mysqli_commit($conn);
        return ['success' => true, 'message' => 'Customer deleted successfully.', 'customer_id' => $customerId];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $message = $e->getMessage();
        if (stripos($message, 'pending bills') !== false) {
            return ['success' => false, 'message' => 'This customer cannot be deleted because there are pending bills.'];
        }
        if (stripos($message, 'pending balance') !== false) {
            return ['success' => false, 'message' => 'This customer cannot be deleted because there is a pending balance.'];
        }
        if (stripos($message, 'inactive') !== false) {
            return ['success' => false, 'message' => 'Please mark this customer as Inactive before deleting permanently.'];
        }
        return ['success' => false, 'message' => 'Unable to delete customer. Please check linked records.', 'debug' => $message];
    }
}

try {
    require_business_login();

    if (function_exists('require_page_access')) {
        require_page_access($conn, 'customers.php');
    }

    $businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);
    $userId = function_exists('current_user_id') ? (int)current_user_id() : (int)($_SESSION['user_id'] ?? 0);

    if ($businessId <= 0) {
        customer_api_json(['success' => false, 'message' => 'Business session missing. Please login again.'], 401);
    }

    if (function_exists('mysqli_set_charset')) {
        mysqli_set_charset($conn, 'utf8mb4');
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = $method === 'POST' ? (string)($_POST['action'] ?? '') : (string)($_GET['action'] ?? 'list');

    if ($method === 'POST' && $action === 'delete_customer') {
        customer_api_json(customer_api_delete_customer($conn, $businessId, $userId, $_POST));
    }

    $controller = new CustomerController($conn, $businessId, $userId);

    if ($method === 'POST') {
        customer_api_json($controller->post($_POST));
    }

    $payload = $controller->get($_GET);
    $payload = customer_api_enrich_response($conn, $businessId, is_array($payload) ? $payload : []);
    customer_api_json($payload);
} catch (Throwable $e) {
    customer_api_json(['success' => false, 'message' => $e->getMessage()], 500);
}
