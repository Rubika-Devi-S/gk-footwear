<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();
require_page_access($conn, 'customers.php');

header('Content-Type: application/json; charset=utf-8');

$businessId = (int) current_business_id();

if ($businessId <= 0) {
    customer_api_response(false, 'Business session missing. Please login again.', [], 401);
}

function customer_api_response(bool $success, string $message = '', array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function customer_api_post_csrf_check(): void
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

function customer_api_bind(mysqli_stmt $stmt, string $types, array $params): void
{
    $bind = [];
    $bind[] = $types;

    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function customer_api_table_exists(mysqli $conn, string $table): bool
{
    if (function_exists('table_exists')) {
        return table_exists($conn, $table);
    }

    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return ((int)($row['total'] ?? 0)) > 0;
}

function customer_api_current_user_id(): ?int
{
    if (function_exists('current_user_id')) {
        $id = (int) current_user_id();
        return $id > 0 ? $id : null;
    }

    $id = (int)($_SESSION['user_id'] ?? 0);
    return $id > 0 ? $id : null;
}

function customer_api_current_role_id(): ?int
{
    if (function_exists('current_role_id')) {
        $id = (int) current_role_id();
        return $id > 0 ? $id : null;
    }

    $id = (int)($_SESSION['role_id'] ?? 0);
    return $id > 0 ? $id : null;
}

function customer_api_current_branch_id(): ?int
{
    if (function_exists('current_branch_id')) {
        $id = (int) current_branch_id();
        return $id > 0 ? $id : null;
    }

    $id = (int)($_SESSION['branch_id'] ?? ($_SESSION['default_branch_id'] ?? 0));
    return $id > 0 ? $id : null;
}

function customer_api_log(mysqli $conn, int $businessId, string $actionType, int $recordId, $oldValue = null, $newValue = null): void
{
    $userId = customer_api_current_user_id();
    $roleId = customer_api_current_role_id();
    $branchId = customer_api_current_branch_id();

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceDetails = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $oldJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
    $newJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

    $logTable = null;

    if (customer_api_table_exists($conn, 'business_activity_logs')) {
        $logTable = 'business_activity_logs';
    } elseif (customer_api_table_exists($conn, 'activity_logs')) {
        $logTable = 'activity_logs';
    }

    if ($logTable === null) {
        return;
    }

    $sql = "
        INSERT INTO {$logTable}
            (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)
        VALUES
            (?, ?, ?, ?, 'Customers', ?, ?, ?, ?, ?, ?, NOW())
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'iiiisissss',
        $businessId,
        $branchId,
        $userId,
        $roleId,
        $actionType,
        $recordId,
        $oldJson,
        $newJson,
        $ipAddress,
        $deviceDetails
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function customer_api_validate(array $input): array
{
    $customerName = trim($input['customer_name'] ?? '');
    $mobile = preg_replace('/[^0-9]/', '', trim($input['mobile'] ?? ''));
    $email = trim($input['email'] ?? '');
    $address = trim($input['address'] ?? '');
    $gstin = strtoupper(preg_replace('/[^0-9A-Z]/', '', trim($input['gstin'] ?? '')));
    $openingOutstanding = (float)($input['opening_outstanding'] ?? 0);
    $loyaltyPoints = (float)($input['loyalty_points'] ?? 0);

    if ($customerName === '') {
        customer_api_response(false, 'Customer name is required.', [], 422);
    }

    if (mb_strlen($customerName) > 200) {
        customer_api_response(false, 'Customer name should not exceed 200 characters.', [], 422);
    }

    if ($mobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        customer_api_response(false, 'Mobile number must be exactly 10 digits only. Do not enter +91, spaces, hyphen, or extra digits.', [], 422);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        customer_api_response(false, 'Enter a valid email address.', [], 422);
    }

    if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
        customer_api_response(false, 'Enter a valid 15-character GSTIN. Example: 33ABCDE1234F1Z5.', [], 422);
    }

    if ($openingOutstanding < 0) {
        customer_api_response(false, 'Opening outstanding cannot be negative.', [], 422);
    }

    if ($loyaltyPoints < 0) {
        customer_api_response(false, 'Loyalty points cannot be negative.', [], 422);
    }

    return [
        'customer_name' => $customerName,
        'mobile' => $mobile,
        'email' => $email,
        'address' => $address,
        'gstin' => $gstin,
        'opening_outstanding' => $openingOutstanding,
        'loyalty_points' => $loyaltyPoints,
    ];
}

function customer_api_get_customer(mysqli $conn, int $businessId, int $customerId): ?array
{
    $stmt = mysqli_prepare($conn, "
        SELECT
            c.customer_id,
            c.business_id,
            c.customer_name,
            c.mobile,
            c.email,
            c.address,
            c.gstin,
            c.opening_outstanding,
            c.loyalty_points,
            c.status,
            c.created_at,
            c.updated_at,
            COALESCE(co.total_bill_amount, c.opening_outstanding) AS total_bill_amount,
            COALESCE(co.total_paid_amount, 0.00) AS total_paid_amount,
            COALESCE(co.balance_amount, c.opening_outstanding) AS current_outstanding
        FROM customers c
        LEFT JOIN customer_outstanding co
            ON co.business_id = c.business_id
           AND co.customer_id = c.customer_id
        WHERE c.customer_id = ?
          AND c.business_id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $customerId, $businessId);
    mysqli_stmt_execute($stmt);
    $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$customer) {
        return null;
    }

    $customer['customer_id'] = (int)$customer['customer_id'];
    $customer['business_id'] = (int)$customer['business_id'];
    $customer['opening_outstanding'] = (float)$customer['opening_outstanding'];
    $customer['loyalty_points'] = (float)$customer['loyalty_points'];
    $customer['status'] = (int)$customer['status'];
    $customer['total_bill_amount'] = (float)$customer['total_bill_amount'];
    $customer['total_paid_amount'] = (float)$customer['total_paid_amount'];
    $customer['current_outstanding'] = (float)$customer['current_outstanding'];

    return $customer;
}

function customer_api_get_outstanding_balance(mysqli $conn, int $businessId, int $customerId): float
{
    if (!customer_api_table_exists($conn, 'customer_outstanding')) {
        return 0.00;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT COALESCE(balance_amount, 0) AS balance_amount
        FROM customer_outstanding
        WHERE business_id = ?
          AND customer_id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (float)($row['balance_amount'] ?? 0.00);
}

function customer_api_insert_ledger_entry(
    mysqli $conn,
    int $businessId,
    int $customerId,
    float $debit,
    float $credit,
    float $balance,
    string $referenceType,
    string $remarks
): void {
    $branchId = customer_api_current_branch_id();
    $userId = customer_api_current_user_id();

    if (customer_api_table_exists($conn, 'customer_ledger')) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO customer_ledger
                (business_id, branch_id, customer_id, reference_type, reference_id, debit, credit, balance, remarks, created_by, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $referenceId = $customerId;

        mysqli_stmt_bind_param(
            $stmt,
            'iiisidddsi',
            $businessId,
            $branchId,
            $customerId,
            $referenceType,
            $referenceId,
            $debit,
            $credit,
            $balance,
            $remarks,
            $userId
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    if (customer_api_table_exists($conn, 'payment_ledger')) {
        $transactionType = ($referenceType === 'opening') ? 'opening' : 'adjustment';

        $stmt = mysqli_prepare($conn, "
            INSERT INTO payment_ledger
                (business_id, branch_id, customer_id, bill_id, transaction_type, debit, credit, balance, payment_method_id, remarks, created_by, created_at)
            VALUES
                (?, ?, ?, NULL, ?, ?, ?, ?, NULL, ?, ?, NOW())
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'iiisdddsi',
            $businessId,
            $branchId,
            $customerId,
            $transactionType,
            $debit,
            $credit,
            $balance,
            $remarks,
            $userId
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function customer_api_sync_opening_outstanding(mysqli $conn, int $businessId, int $customerId, float $oldOpening, float $newOpening, bool $isNewCustomer): void
{
    if (!customer_api_table_exists($conn, 'customer_outstanding')) {
        return;
    }

    $difference = $newOpening - $oldOpening;

    if ($isNewCustomer) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO customer_outstanding
                (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount, updated_at)
            VALUES
                (?, ?, ?, 0.00, ?, NOW())
            ON DUPLICATE KEY UPDATE
                total_bill_amount = VALUES(total_bill_amount),
                balance_amount = VALUES(balance_amount),
                updated_at = NOW()
        ");

        mysqli_stmt_bind_param($stmt, 'iidd', $businessId, $customerId, $newOpening, $newOpening);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($newOpening > 0) {
            customer_api_insert_ledger_entry($conn, $businessId, $customerId, $newOpening, 0.00, $newOpening, 'opening', 'Opening outstanding added');
        }

        return;
    }

    if (abs($difference) < 0.00001) {
        return;
    }

    $stmt = mysqli_prepare($conn, "
        INSERT INTO customer_outstanding
            (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount, updated_at)
        VALUES
            (?, ?, ?, 0.00, ?, NOW())
        ON DUPLICATE KEY UPDATE
            total_bill_amount = GREATEST(total_bill_amount + ?, 0),
            balance_amount = GREATEST(balance_amount + ?, 0),
            updated_at = NOW()
    ");

    mysqli_stmt_bind_param($stmt, 'iidddd', $businessId, $customerId, $newOpening, $newOpening, $difference, $difference);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $newBalance = customer_api_get_outstanding_balance($conn, $businessId, $customerId);
    $debit = $difference > 0 ? $difference : 0.00;
    $credit = $difference < 0 ? abs($difference) : 0.00;

    customer_api_insert_ledger_entry($conn, $businessId, $customerId, $debit, $credit, $newBalance, 'opening_adjustment', 'Opening outstanding adjusted');
}

function customer_api_customer_is_used(mysqli $conn, int $businessId, int $customerId): bool
{
    $simpleChecks = [
        ['bills', 'customer_id'],
        ['cashier_collections', 'customer_id'],
    ];

    foreach ($simpleChecks as $check) {
        $table = $check[0];
        $column = $check[1];

        if (!customer_api_table_exists($conn, $table)) {
            continue;
        }

        $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE business_id = ? AND {$column} = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    if (customer_api_table_exists($conn, 'customer_ledger')) {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM customer_ledger
            WHERE business_id = ?
              AND customer_id = ?
              AND COALESCE(reference_type, '') NOT IN ('opening', 'opening_adjustment')
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    if (customer_api_table_exists($conn, 'payment_ledger')) {
        $stmt = mysqli_prepare($conn, "
            SELECT COUNT(*) AS total
            FROM payment_ledger
            WHERE business_id = ?
              AND customer_id = ?
              AND (
                  bill_id IS NOT NULL
                  OR transaction_type NOT IN ('opening', 'adjustment')
              )
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ((int)($row['total'] ?? 0) > 0) {
            return true;
        }
    }

    return false;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        customer_api_post_csrf_check();
    }

    if ($action === 'list') {
        $search = trim($_GET['search'] ?? '');
        $statusFilter = $_GET['status'] ?? '';

        $where = 'WHERE c.business_id = ?';
        $params = [$businessId];
        $types = 'i';

        if ($search !== '') {
            $where .= ' AND (c.customer_name LIKE ? OR c.mobile LIKE ? OR c.email LIKE ? OR c.gstin LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'ssss';
        }

        if ($statusFilter !== '' && ($statusFilter === '0' || $statusFilter === '1')) {
            $where .= ' AND c.status = ?';
            $params[] = (int)$statusFilter;
            $types .= 'i';
        }

        $stmt = mysqli_prepare($conn, "
            SELECT
                c.customer_id,
                c.business_id,
                c.customer_name,
                c.mobile,
                c.email,
                c.address,
                c.gstin,
                c.opening_outstanding,
                c.loyalty_points,
                c.status,
                c.created_at,
                c.updated_at,
                COALESCE(co.total_bill_amount, c.opening_outstanding) AS total_bill_amount,
                COALESCE(co.total_paid_amount, 0.00) AS total_paid_amount,
                COALESCE(co.balance_amount, c.opening_outstanding) AS current_outstanding
            FROM customers c
            LEFT JOIN customer_outstanding co
                ON co.business_id = c.business_id
               AND co.customer_id = c.customer_id
            {$where}
            ORDER BY c.customer_id DESC
        ");
        customer_api_bind($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $customers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['customer_id'] = (int)$row['customer_id'];
            $row['business_id'] = (int)$row['business_id'];
            $row['opening_outstanding'] = (float)$row['opening_outstanding'];
            $row['loyalty_points'] = (float)$row['loyalty_points'];
            $row['status'] = (int)$row['status'];
            $row['total_bill_amount'] = (float)$row['total_bill_amount'];
            $row['total_paid_amount'] = (float)$row['total_paid_amount'];
            $row['current_outstanding'] = (float)$row['current_outstanding'];
            $customers[] = $row;
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "
            SELECT
                COUNT(*) AS total_customers,
                SUM(CASE WHEN c.status = 1 THEN 1 ELSE 0 END) AS active_customers,
                COALESCE(SUM(c.opening_outstanding), 0) AS opening_total,
                COALESCE(SUM(COALESCE(co.balance_amount, c.opening_outstanding)), 0) AS current_total,
                COALESCE(SUM(c.loyalty_points), 0) AS loyalty_total
            FROM customers c
            LEFT JOIN customer_outstanding co
                ON co.business_id = c.business_id
               AND co.customer_id = c.customer_id
            WHERE c.business_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'i', $businessId);
        mysqli_stmt_execute($stmt);
        $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        customer_api_response(true, '', [
            'customers' => $customers,
            'stats' => [
                'total_customers' => (int)($stats['total_customers'] ?? 0),
                'active_customers' => (int)($stats['active_customers'] ?? 0),
                'opening_total' => (float)($stats['opening_total'] ?? 0),
                'current_total' => (float)($stats['current_total'] ?? 0),
                'loyalty_total' => (float)($stats['loyalty_total'] ?? 0),
            ],
        ]);
    }

    if ($action === 'get') {
        $customerId = (int)($_GET['customer_id'] ?? 0);

        if ($customerId <= 0) {
            customer_api_response(false, 'Invalid customer selected.', [], 422);
        }

        $customer = customer_api_get_customer($conn, $businessId, $customerId);

        if (!$customer) {
            customer_api_response(false, 'Customer not found.', [], 404);
        }

        customer_api_response(true, '', ['customer' => $customer]);
    }

    if ($action === 'save_customer') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $data = customer_api_validate($_POST);

        mysqli_begin_transaction($conn);

        try {
            if ($customerId > 0) {
                $oldCustomer = customer_api_get_customer($conn, $businessId, $customerId);

                if (!$oldCustomer) {
                    mysqli_rollback($conn);
                    customer_api_response(false, 'Customer not found.', [], 404);
                }

                $oldOpeningOutstanding = (float)$oldCustomer['opening_outstanding'];

                $stmt = mysqli_prepare($conn, "
                    UPDATE customers
                    SET customer_name = ?,
                        mobile = ?,
                        email = ?,
                        address = ?,
                        gstin = ?,
                        opening_outstanding = ?,
                        loyalty_points = ?,
                        updated_at = NOW()
                    WHERE customer_id = ?
                      AND business_id = ?
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    'sssssddii',
                    $data['customer_name'],
                    $data['mobile'],
                    $data['email'],
                    $data['address'],
                    $data['gstin'],
                    $data['opening_outstanding'],
                    $data['loyalty_points'],
                    $customerId,
                    $businessId
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                customer_api_sync_opening_outstanding($conn, $businessId, $customerId, $oldOpeningOutstanding, $data['opening_outstanding'], false);

                customer_api_log($conn, $businessId, 'update', $customerId, $oldCustomer, $data);
                mysqli_commit($conn);

                customer_api_response(true, 'Customer updated successfully.', ['customer_id' => $customerId]);
            }

            $stmt = mysqli_prepare($conn, "
                INSERT INTO customers
                    (business_id, customer_name, mobile, email, address, gstin, opening_outstanding, loyalty_points, status, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            mysqli_stmt_bind_param(
                $stmt,
                'isssssdd',
                $businessId,
                $data['customer_name'],
                $data['mobile'],
                $data['email'],
                $data['address'],
                $data['gstin'],
                $data['opening_outstanding'],
                $data['loyalty_points']
            );
            mysqli_stmt_execute($stmt);
            $newCustomerId = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            customer_api_sync_opening_outstanding($conn, $businessId, $newCustomerId, 0.00, $data['opening_outstanding'], true);
            customer_api_log($conn, $businessId, 'create', $newCustomerId, null, $data);
            mysqli_commit($conn);

            customer_api_response(true, 'Customer created successfully.', ['customer_id' => $newCustomerId]);
        } catch (Throwable $innerException) {
            mysqli_rollback($conn);
            throw $innerException;
        }
    }

    if ($action === 'toggle_status') {
        $customerId = (int)($_POST['customer_id'] ?? 0);

        if ($customerId <= 0) {
            customer_api_response(false, 'Invalid customer selected.', [], 422);
        }

        $oldCustomer = customer_api_get_customer($conn, $businessId, $customerId);

        if (!$oldCustomer) {
            customer_api_response(false, 'Customer not found.', [], 404);
        }

        $newStatus = ((int)$oldCustomer['status'] === 1) ? 0 : 1;

        $stmt = mysqli_prepare($conn, "
            UPDATE customers
            SET status = ?,
                updated_at = NOW()
            WHERE customer_id = ?
              AND business_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'iii', $newStatus, $customerId, $businessId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        customer_api_log($conn, $businessId, $newStatus === 1 ? 'activate' : 'deactivate', $customerId, $oldCustomer, ['status' => $newStatus]);

        customer_api_response(true, 'Customer status updated successfully.', ['status' => $newStatus]);
    }

    if ($action === 'delete_customer') {
        $customerId = (int)($_POST['customer_id'] ?? 0);

        if ($customerId <= 0) {
            customer_api_response(false, 'Invalid customer selected.', [], 422);
        }

        $oldCustomer = customer_api_get_customer($conn, $businessId, $customerId);

        if (!$oldCustomer) {
            customer_api_response(false, 'Customer not found.', [], 404);
        }

        if (customer_api_customer_is_used($conn, $businessId, $customerId)) {
            customer_api_response(false, 'This customer is already used in bills or ledger. Please deactivate instead of deleting.', [], 409);
        }

        mysqli_begin_transaction($conn);

        try {
            if (customer_api_table_exists($conn, 'customer_ledger')) {
                $stmt = mysqli_prepare($conn, "DELETE FROM customer_ledger WHERE business_id = ? AND customer_id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            if (customer_api_table_exists($conn, 'payment_ledger')) {
                $stmt = mysqli_prepare($conn, "
                    DELETE FROM payment_ledger
                    WHERE business_id = ?
                      AND customer_id = ?
                      AND bill_id IS NULL
                      AND transaction_type IN ('opening', 'adjustment')
                ");
                mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            if (customer_api_table_exists($conn, 'customer_outstanding')) {
                $stmt = mysqli_prepare($conn, "DELETE FROM customer_outstanding WHERE business_id = ? AND customer_id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $businessId, $customerId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            $stmt = mysqli_prepare($conn, "
                DELETE FROM customers
                WHERE customer_id = ?
                  AND business_id = ?
                LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $customerId, $businessId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            customer_api_log($conn, $businessId, 'delete', $customerId, $oldCustomer, null);
            mysqli_commit($conn);

            customer_api_response(true, 'Customer deleted successfully.');
        } catch (Throwable $innerException) {
            mysqli_rollback($conn);
            throw $innerException;
        }
    }

    customer_api_response(false, 'Invalid API action.', [], 400);
} catch (Throwable $e) {
    customer_api_response(false, 'Server error: ' . $e->getMessage(), [], 500);
}
