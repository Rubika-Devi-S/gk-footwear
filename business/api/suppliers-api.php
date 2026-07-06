<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();
require_page_access($conn, 'suppliers.php');

header('Content-Type: application/json; charset=utf-8');

$businessId = (int) current_business_id();

if ($businessId <= 0) {
    supplier_api_response(false, 'Business session missing. Please login again.', [], 401);
}

function supplier_api_response(bool $success, string $message = '', array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function supplier_api_post_csrf_check(): void
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

function supplier_api_bind(mysqli_stmt $stmt, string $types, array $params): void
{
    $bind = [];
    $bind[] = $types;

    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function supplier_api_table_exists(mysqli $conn, string $table): bool
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

function supplier_api_log(mysqli $conn, int $businessId, string $actionType, int $recordId, $oldValue = null, $newValue = null): void
{
    $userId = function_exists('current_user_id') ? (int) current_user_id() : (int)($_SESSION['user_id'] ?? 0);
    $roleId = function_exists('current_role_id') ? (int) current_role_id() : (int)($_SESSION['role_id'] ?? 0);
    $branchId = function_exists('current_branch_id') ? (int) current_branch_id() : null;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $deviceDetails = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $oldJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
    $newJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

    $logTable = null;

    if (supplier_api_table_exists($conn, 'business_activity_logs')) {
        $logTable = 'business_activity_logs';
    } elseif (supplier_api_table_exists($conn, 'activity_logs')) {
        $logTable = 'activity_logs';
    }

    if ($logTable === null) {
        return;
    }

    $sql = "
        INSERT INTO {$logTable}
            (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)
        VALUES
            (?, ?, ?, ?, 'Suppliers', ?, ?, ?, ?, ?, ?, NOW())
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

function supplier_api_validate(array $input): array
{
    $supplierName = trim($input['supplier_name'] ?? '');
    $mobile = trim($input['mobile'] ?? '');
    $email = trim($input['email'] ?? '');
    $gstin = strtoupper(trim($input['gstin'] ?? ''));
    $address = trim($input['address'] ?? '');
    $openingOutstanding = (float)($input['opening_outstanding'] ?? 0);

    if ($supplierName === '') {
        supplier_api_response(false, 'Supplier name is required.', [], 422);
    }

    if (mb_strlen($supplierName) > 200) {
        supplier_api_response(false, 'Supplier name should not exceed 200 characters.', [], 422);
    }

    /*
     * Mobile validation:
     * - Optional field.
     * - If entered, allow exactly 10 digits only.
     * - Do not allow +91, spaces, hyphen, letters, or extra digits.
     * - Indian mobile numbers should start with 6, 7, 8, or 9.
     */
    if ($mobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        supplier_api_response(false, 'Mobile number must be exactly 10 digits only. Do not enter +91, spaces, hyphen, or extra digits.', [], 422);
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        supplier_api_response(false, 'Enter a valid email address.', [], 422);
    }

    /*
     * GSTIN validation:
     * - Optional field.
     * - If entered, validate complete Indian GSTIN format.
     * - Example: 33ABCDE1234F1Z5
     */
    if ($gstin !== '' && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
        supplier_api_response(false, 'Enter a valid 15-character GSTIN. Example: 33ABCDE1234F1Z5.', [], 422);
    }

    if ($openingOutstanding < 0) {
        supplier_api_response(false, 'Opening outstanding cannot be negative.', [], 422);
    }

    return [
        'supplier_name' => $supplierName,
        'mobile' => $mobile,
        'email' => $email,
        'gstin' => $gstin,
        'address' => $address,
        'opening_outstanding' => $openingOutstanding,
    ];
}

function supplier_api_get_supplier(mysqli $conn, int $businessId, int $supplierId): ?array
{
    $stmt = mysqli_prepare($conn, "
        SELECT supplier_id, business_id, supplier_name, mobile, email, gstin, address,
               opening_outstanding, current_outstanding, status, created_at, updated_at
        FROM suppliers
        WHERE supplier_id = ?
          AND business_id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $supplierId, $businessId);
    mysqli_stmt_execute($stmt);
    $supplier = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $supplier ? supplier_api_format_supplier($supplier) : null;
}


function supplier_api_format_supplier(array $supplier): array
{
    $supplier['supplier_id'] = (int)($supplier['supplier_id'] ?? 0);
    $supplier['business_id'] = (int)($supplier['business_id'] ?? 0);
    $supplier['opening_outstanding'] = (float)($supplier['opening_outstanding'] ?? 0);
    $supplier['current_outstanding'] = (float)($supplier['current_outstanding'] ?? 0);
    $supplier['status'] = (int)($supplier['status'] ?? 0);

    return $supplier;
}

function supplier_api_validate_gstin_value(string $gstin): string
{
    $gstin = strtoupper(trim($gstin));

    if ($gstin === '') {
        supplier_api_response(false, 'GSTIN is required.', [], 422);
    }

    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstin)) {
        supplier_api_response(false, 'Enter a valid 15-character GSTIN. Example: 33ABCDE1234F1Z5.', [], 422);
    }

    return $gstin;
}

function supplier_api_get_supplier_by_gstin(mysqli $conn, int $businessId, string $gstin, int $excludeSupplierId = 0): ?array
{
    $gstin = strtoupper(trim($gstin));

    if ($gstin === '') {
        return null;
    }

    $sql = "
        SELECT supplier_id, business_id, supplier_name, mobile, email, gstin, address,
               opening_outstanding, current_outstanding, status, created_at, updated_at
        FROM suppliers
        WHERE business_id = ?
          AND gstin = ?
    ";

    $params = [$businessId, $gstin];
    $types = 'is';

    if ($excludeSupplierId > 0) {
        $sql .= " AND supplier_id <> ?";
        $params[] = $excludeSupplierId;
        $types .= 'i';
    }

    $sql .= " ORDER BY status DESC, supplier_id DESC LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    supplier_api_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $supplier = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return $supplier ? supplier_api_format_supplier($supplier) : null;
}

function supplier_api_supplier_is_used(mysqli $conn, int $businessId, int $supplierId): bool
{
    $checks = [
        ['stock_inward_batches', 'supplier_id'],
        ['vendor_ledger', 'supplier_id'],
        ['vendor_outstanding', 'supplier_id'],
    ];

    foreach ($checks as $check) {
        $table = $check[0];
        $column = $check[1];

        if (!supplier_api_table_exists($conn, $table)) {
            continue;
        }

        $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE business_id = ? AND {$column} = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $supplierId);
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
        supplier_api_post_csrf_check();
    }

    if ($action === 'list') {
        $search = trim($_GET['search'] ?? '');
        $statusFilter = $_GET['status'] ?? '';

        $where = 'WHERE business_id = ?';
        $params = [$businessId];
        $types = 'i';

        if ($search !== '') {
            $where .= ' AND (supplier_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR gstin LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'ssss';
        }

        if ($statusFilter !== '' && ($statusFilter === '0' || $statusFilter === '1')) {
            $where .= ' AND status = ?';
            $params[] = (int)$statusFilter;
            $types .= 'i';
        }

        $stmt = mysqli_prepare($conn, "
            SELECT supplier_id, business_id, supplier_name, mobile, email, gstin, address,
                   opening_outstanding, current_outstanding, status, created_at, updated_at
            FROM suppliers
            {$where}
            ORDER BY supplier_id DESC
        ");
        supplier_api_bind($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $suppliers = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['supplier_id'] = (int)$row['supplier_id'];
            $row['business_id'] = (int)$row['business_id'];
            $row['opening_outstanding'] = (float)$row['opening_outstanding'];
            $row['current_outstanding'] = (float)$row['current_outstanding'];
            $row['status'] = (int)$row['status'];
            $suppliers[] = $row;
        }
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "
            SELECT
                COUNT(*) AS total_suppliers,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_suppliers,
                COALESCE(SUM(opening_outstanding), 0) AS opening_total,
                COALESCE(SUM(current_outstanding), 0) AS current_total
            FROM suppliers
            WHERE business_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'i', $businessId);
        mysqli_stmt_execute($stmt);
        $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        supplier_api_response(true, '', [
            'suppliers' => $suppliers,
            'stats' => [
                'total_suppliers' => (int)($stats['total_suppliers'] ?? 0),
                'active_suppliers' => (int)($stats['active_suppliers'] ?? 0),
                'opening_total' => (float)($stats['opening_total'] ?? 0),
                'current_total' => (float)($stats['current_total'] ?? 0),
            ],
        ]);
    }

    if ($action === 'gst_lookup' || $action === 'lookup_by_gstin') {
        $gstin = supplier_api_validate_gstin_value($_GET['gstin'] ?? $_POST['gstin'] ?? '');
        $supplier = supplier_api_get_supplier_by_gstin($conn, $businessId, $gstin);

        if (!$supplier) {
            supplier_api_response(true, 'No existing supplier found for this GSTIN.', [
                'found' => false,
                'supplier' => null,
            ]);
        }

        supplier_api_response(true, 'Supplier details loaded successfully.', [
            'found' => true,
            'supplier' => $supplier,
        ]);
    }

    if ($action === 'get') {
        $supplierId = (int)($_GET['supplier_id'] ?? 0);

        if ($supplierId <= 0) {
            supplier_api_response(false, 'Invalid supplier selected.', [], 422);
        }

        $supplier = supplier_api_get_supplier($conn, $businessId, $supplierId);

        if (!$supplier) {
            supplier_api_response(false, 'Supplier not found.', [], 404);
        }

        supplier_api_response(true, '', ['supplier' => $supplier]);
    }

    if ($action === 'save_supplier') {
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $data = supplier_api_validate($_POST);

        if ($data['gstin'] !== '') {
            $duplicateSupplier = supplier_api_get_supplier_by_gstin($conn, $businessId, $data['gstin'], $supplierId);

            if ($duplicateSupplier) {
                supplier_api_response(false, 'GSTIN already exists for this supplier. Existing supplier details are returned for auto-fill.', [
                    'found' => true,
                    'supplier' => $duplicateSupplier,
                ], 409);
            }
        }

        if ($supplierId > 0) {
            $oldSupplier = supplier_api_get_supplier($conn, $businessId, $supplierId);

            if (!$oldSupplier) {
                supplier_api_response(false, 'Supplier not found.', [], 404);
            }

            $oldOpening = (float)$oldSupplier['opening_outstanding'];
            $oldCurrent = (float)$oldSupplier['current_outstanding'];
            $difference = $data['opening_outstanding'] - $oldOpening;
            $newCurrentOutstanding = $oldCurrent + $difference;

            if ($newCurrentOutstanding < 0) {
                $newCurrentOutstanding = 0;
            }

            $stmt = mysqli_prepare($conn, "
                UPDATE suppliers
                SET supplier_name = ?,
                    mobile = ?,
                    email = ?,
                    gstin = ?,
                    address = ?,
                    opening_outstanding = ?,
                    current_outstanding = ?,
                    updated_at = NOW()
                WHERE supplier_id = ?
                  AND business_id = ?
            ");
            mysqli_stmt_bind_param(
                $stmt,
                'sssssddii',
                $data['supplier_name'],
                $data['mobile'],
                $data['email'],
                $data['gstin'],
                $data['address'],
                $data['opening_outstanding'],
                $newCurrentOutstanding,
                $supplierId,
                $businessId
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            supplier_api_log($conn, $businessId, 'update', $supplierId, $oldSupplier, [
                'supplier_name' => $data['supplier_name'],
                'mobile' => $data['mobile'],
                'email' => $data['email'],
                'gstin' => $data['gstin'],
                'address' => $data['address'],
                'opening_outstanding' => $data['opening_outstanding'],
                'current_outstanding' => $newCurrentOutstanding,
            ]);

            supplier_api_response(true, 'Supplier updated successfully.', ['supplier_id' => $supplierId]);
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO suppliers
                (business_id, supplier_name, mobile, email, gstin, address, opening_outstanding, current_outstanding, status, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        mysqli_stmt_bind_param(
            $stmt,
            'isssssdd',
            $businessId,
            $data['supplier_name'],
            $data['mobile'],
            $data['email'],
            $data['gstin'],
            $data['address'],
            $data['opening_outstanding'],
            $data['opening_outstanding']
        );
        mysqli_stmt_execute($stmt);
        $newSupplierId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        supplier_api_log($conn, $businessId, 'create', $newSupplierId, null, $data);

        supplier_api_response(true, 'Supplier created successfully.', ['supplier_id' => $newSupplierId]);
    }

    if ($action === 'toggle_status') {
        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        if ($supplierId <= 0) {
            supplier_api_response(false, 'Invalid supplier selected.', [], 422);
        }

        $oldSupplier = supplier_api_get_supplier($conn, $businessId, $supplierId);

        if (!$oldSupplier) {
            supplier_api_response(false, 'Supplier not found.', [], 404);
        }

        $newStatus = ((int)$oldSupplier['status'] === 1) ? 0 : 1;

        $stmt = mysqli_prepare($conn, "
            UPDATE suppliers
            SET status = ?,
                updated_at = NOW()
            WHERE supplier_id = ?
              AND business_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'iii', $newStatus, $supplierId, $businessId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        supplier_api_log($conn, $businessId, 'status_update', $supplierId, $oldSupplier, ['status' => $newStatus]);

        supplier_api_response(true, 'Supplier status updated successfully.', ['status' => $newStatus]);
    }

    if ($action === 'delete_supplier') {
        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        if ($supplierId <= 0) {
            supplier_api_response(false, 'Invalid supplier selected.', [], 422);
        }

        $oldSupplier = supplier_api_get_supplier($conn, $businessId, $supplierId);

        if (!$oldSupplier) {
            supplier_api_response(false, 'Supplier not found.', [], 404);
        }

        if (supplier_api_supplier_is_used($conn, $businessId, $supplierId)) {
            supplier_api_response(false, 'This supplier is already used in stock/ledger. Please deactivate instead of deleting.', [], 409);
        }

        $stmt = mysqli_prepare($conn, "
            DELETE FROM suppliers
            WHERE supplier_id = ?
              AND business_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $supplierId, $businessId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        supplier_api_log($conn, $businessId, 'delete', $supplierId, $oldSupplier, null);

        supplier_api_response(true, 'Supplier deleted successfully.');
    }

    supplier_api_response(false, 'Invalid API action.', [], 400);
} catch (Throwable $e) {
    supplier_api_response(false, 'Server error: ' . $e->getMessage(), [], 500);
}
