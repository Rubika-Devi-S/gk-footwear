<?php
/**
 * Universal Footwear POS - Suppliers API
 * Supplier master and detail ledger use the same calculation as Supplier Ledger Report.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

if (function_exists('require_business_login')) {
    require_business_login();
}

function supplier_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function supplier_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int) current_user_id();
    }

    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function supplier_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int) current_business_id();
    }

    return (int)($_SESSION['business_id'] ?? 0);
}

function supplier_table_exists(mysqli $conn, string $table): bool
{
    if (function_exists('table_exists')) {
        return (bool) table_exists($conn, $table);
    }

    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    return $res && mysqli_num_rows($res) > 0;
}

function supplier_column_exists(mysqli $conn, string $table, string $column): bool
{
    if (function_exists('table_has_column')) {
        return (bool) table_has_column($conn, $table, $column);
    }

    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function supplier_bind(mysqli_stmt $stmt, string $types, array $params): void
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

function supplier_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('SQL prepare failed: ' . mysqli_error($conn));
    }

    supplier_bind($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('SQL execute failed: ' . $error);
    }

    $res = mysqli_stmt_get_result($stmt);
    $rows = [];

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

function supplier_one(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = supplier_rows($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function supplier_permissions(mysqli $conn): array
{
    $all = [
        'can_view' => true,
        'can_create' => true,
        'can_edit' => true,
        'can_delete' => true,
        'can_print' => true,
        'can_export' => true,
    ];

    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return $all;
    }

    $businessId = supplier_business_id();
    $roleId = function_exists('current_role_id') ? (int) current_role_id() : (int)($_SESSION['role_id'] ?? 0);

    if ($businessId <= 0 || $roleId <= 0 || !supplier_table_exists($conn, 'business_sidebar_menus') || !supplier_table_exists($conn, 'business_role_sidebar_access')) {
        return $all;
    }

    $cols = ['can_view'];
    foreach (['can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'] as $col) {
        $cols[] = supplier_column_exists($conn, 'business_role_sidebar_access', $col) ? $col : '0 AS ' . $col;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT " . implode(', ', $cols) . "
        FROM business_sidebar_menus sm
        INNER JOIN business_role_sidebar_access rsa
            ON rsa.menu_id = sm.id
           AND rsa.business_id = sm.business_id
           AND rsa.role_id = ?
        WHERE sm.business_id = ?
          AND sm.menu_url = 'suppliers.php'
          AND sm.is_active = 1
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, 'ii', $roleId, $businessId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        return $all;
    }

    return [
        'can_view' => (int)($row['can_view'] ?? 0) === 1,
        'can_create' => (int)($row['can_create'] ?? 0) === 1,
        'can_edit' => (int)($row['can_edit'] ?? 0) === 1,
        'can_delete' => (int)($row['can_delete'] ?? 0) === 1,
        'can_print' => (int)($row['can_print'] ?? 0) === 1,
        'can_export' => (int)($row['can_export'] ?? 0) === 1,
    ];
}

function supplier_ledger_delta(array $row): float
{
    $referenceType = strtolower((string)($row['reference_type'] ?? ''));
    $purpose = strtolower((string)($row['purpose'] ?? ''));
    $direction = strtolower((string)($row['transaction_direction'] ?? ''));
    $debit = round((float)($row['debit'] ?? 0), 2);
    $credit = round((float)($row['credit'] ?? 0), 2);

    if ($referenceType === 'opening') {
        return 0.0;
    }

    if (strpos($referenceType, 'cancel') !== false || strpos($referenceType, 'reverse') !== false) {
        return round(-1 * ($debit + $credit), 2);
    }

    if (strpos($referenceType, 'stock_inward') !== false) {
        /*
         * Stock inward purchase must always ADD to supplier payable.
         * This supports both old rows posted in debit and new rows posted in credit.
         */
        return round($debit + $credit, 2);
    }

    if (in_array($purpose, ['debit', 'advance_payment', 'purchase_return'], true) || $direction === 'debit') {
        return round(-1 * ($debit + $credit), 2);
    }

    if ($purpose === 'credit' || $direction === 'credit') {
        return round($debit + $credit, 2);
    }

    return round($credit - $debit, 2);
}

function supplier_ledger_display_type(array $row): string
{
    $referenceType = strtolower((string)($row['reference_type'] ?? ''));
    $purpose = strtolower((string)($row['purpose'] ?? ''));
    $direction = strtolower((string)($row['transaction_direction'] ?? ''));

    if ($referenceType === 'opening') {
        return 'Opening Balance';
    }

    if (strpos($referenceType, 'cancel') !== false || strpos($referenceType, 'reverse') !== false) {
        return 'Reversal';
    }

    if (strpos($referenceType, 'stock_inward') !== false) {
        return 'Purchase';
    }

    if ($purpose === 'credit' || $direction === 'credit') {
        return 'Credit';
    }

    if ($purpose === 'debit' || $direction === 'debit') {
        return 'Debit';
    }

    if ($purpose !== '') {
        return ucwords(str_replace('_', ' ', $purpose));
    }

    return ucwords(str_replace('_', ' ', (string)($row['reference_type'] ?? 'Ledger')));
}

function supplier_calculate_statement(mysqli $conn, int $businessId, array $supplier, string $fromDate = '', string $toDate = ''): array
{
    $supplierId = (int)($supplier['supplier_id'] ?? 0);
    $opening = round((float)($supplier['opening_outstanding'] ?? 0), 2);

    $types = 'ii';
    $params = [$businessId, $supplierId];
    $where = "vl.business_id = ? AND vl.supplier_id = ? AND COALESCE(vl.reference_type, '') <> 'opening'";

    if ($fromDate !== '') {
        $where .= " AND DATE(vl.created_at) >= ?";
        $types .= 's';
        $params[] = $fromDate;
    }

    if ($toDate !== '') {
        $where .= " AND DATE(vl.created_at) <= ?";
        $types .= 's';
        $params[] = $toDate;
    }

    $rows = supplier_rows($conn, "
        SELECT
            vl.vendor_ledger_id,
            vl.supplier_id,
            vl.reference_type,
            vl.reference_id,
            vl.purpose,
            vl.transaction_direction,
            vl.debit,
            vl.credit,
            vl.balance AS db_ledger_balance,
            vl.remarks,
            vl.created_at,
            vl.created_at AS entry_datetime,
            DATE_FORMAT(vl.created_at, '%d-%m-%Y %h:%i %p') AS entry_display,
            COALESCE(sib.batch_no, CONCAT('#', COALESCE(vl.reference_id, '-'))) AS reference_no,
            br.branch_name,
            br.floor_name
        FROM vendor_ledger vl
        LEFT JOIN stock_inward_batches sib
            ON sib.batch_id = vl.reference_id
           AND sib.business_id = vl.business_id
           AND vl.reference_type LIKE 'stock_inward%'
        LEFT JOIN branches br
            ON br.branch_id = vl.branch_id
           AND br.business_id = vl.business_id
        WHERE $where
        ORDER BY vl.created_at ASC, vl.vendor_ledger_id ASC
    ", $types, $params);

    $statement = [];
    $balance = $opening;
    $additions = 0.0;
    $decreases = 0.0;
    $debitTotal = 0.0;
    $creditTotal = 0.0;

    if (abs($opening) > 0) {
        $statement[] = [
            'vendor_ledger_id' => 0,
            'supplier_id' => $supplierId,
            'reference_type' => 'opening',
            'display_type' => 'Opening Balance',
            'purpose' => 'Opening Balance',
            'transaction_direction' => 'opening',
            'reference_no' => '-',
            'debit' => 0,
            'credit' => 0,
            'balance' => $balance,
            'remarks' => 'Supplier opening balance',
            'created_at' => '',
            'entry_datetime' => '',
            'entry_display' => 'Opening Balance',
            'branch_name' => '',
            'floor_name' => '',
            'is_opening' => 1,
        ];
    }

    foreach ($rows as $row) {
        $debit = round((float)($row['debit'] ?? 0), 2);
        $credit = round((float)($row['credit'] ?? 0), 2);
        $delta = supplier_ledger_delta($row);

        if ($delta >= 0) {
            $additions = round($additions + $delta, 2);
        } else {
            $decreases = round($decreases + abs($delta), 2);
        }

        $debitTotal = round($debitTotal + $debit, 2);
        $creditTotal = round($creditTotal + $credit, 2);
        $balance = round($balance + $delta, 2);

        $row['debit'] = $debit;
        $row['credit'] = $credit;
        $row['delta_amount'] = $delta;
        $row['balance'] = $balance;
        $row['display_type'] = supplier_ledger_display_type($row);
        $row['is_opening'] = 0;

        $statement[] = $row;
    }

    return [
        'rows' => $statement,
        'summary' => [
            'opening_balance' => $opening,
            'total_addition' => round($additions, 2),
            'total_decrease' => round($decreases, 2),
            'total_debit' => round($decreases, 2),
            'total_credit' => round($additions, 2),
            'raw_debit_total' => round($debitTotal, 2),
            'raw_credit_total' => round($creditTotal, 2),
            'closing_balance' => round($balance, 2),
            'current_balance' => round($balance, 2),
        ],
    ];
}

function supplier_calculate_map(mysqli $conn, int $businessId): array
{
    $suppliers = supplier_rows($conn, "SELECT supplier_id, opening_outstanding FROM suppliers WHERE business_id = ?", 'i', [$businessId]);
    $map = [];

    foreach ($suppliers as $supplier) {
        $supplierId = (int)$supplier['supplier_id'];
        $map[$supplierId] = [
            'opening_balance' => round((float)($supplier['opening_outstanding'] ?? 0), 2),
            'total_addition' => 0.0,
            'total_decrease' => 0.0,
            'calculated_balance' => round((float)($supplier['opening_outstanding'] ?? 0), 2),
        ];
    }

    $rows = supplier_rows($conn, "
        SELECT supplier_id, reference_type, purpose, transaction_direction, debit, credit
        FROM vendor_ledger
        WHERE business_id = ?
          AND COALESCE(reference_type, '') <> 'opening'
        ORDER BY created_at ASC, vendor_ledger_id ASC
    ", 'i', [$businessId]);

    foreach ($rows as $row) {
        $supplierId = (int)$row['supplier_id'];
        if (!isset($map[$supplierId])) {
            $map[$supplierId] = [
                'opening_balance' => 0.0,
                'total_addition' => 0.0,
                'total_decrease' => 0.0,
                'calculated_balance' => 0.0,
            ];
        }

        $delta = supplier_ledger_delta($row);

        if ($delta >= 0) {
            $map[$supplierId]['total_addition'] = round($map[$supplierId]['total_addition'] + $delta, 2);
        } else {
            $map[$supplierId]['total_decrease'] = round($map[$supplierId]['total_decrease'] + abs($delta), 2);
        }

        $map[$supplierId]['calculated_balance'] = round($map[$supplierId]['calculated_balance'] + $delta, 2);
    }

    return $map;
}

function supplier_refresh_balances(mysqli $conn, int $businessId, int $supplierId): void
{
    $supplier = supplier_one($conn, "SELECT * FROM suppliers WHERE business_id = ? AND supplier_id = ? LIMIT 1", 'ii', [$businessId, $supplierId]);

    if (!$supplier) {
        return;
    }

    $calculated = supplier_calculate_statement($conn, $businessId, $supplier);
    $summary = $calculated['summary'];
    $balance = (float)$summary['closing_balance'];
    $addition = (float)$summary['total_addition'];
    $decrease = (float)$summary['total_decrease'];

    mysqli_begin_transaction($conn);

    try {
        if (supplier_column_exists($conn, 'suppliers', 'current_outstanding')) {
            $stmt = mysqli_prepare($conn, "UPDATE suppliers SET current_outstanding = ?, updated_at = NOW() WHERE business_id = ? AND supplier_id = ?");
            mysqli_stmt_bind_param($stmt, 'dii', $balance, $businessId, $supplierId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        if (supplier_table_exists($conn, 'vendor_outstanding')) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO vendor_outstanding
                (business_id, supplier_id, total_purchase_amount, total_paid_amount, balance_amount, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    total_purchase_amount = VALUES(total_purchase_amount),
                    total_paid_amount = VALUES(total_paid_amount),
                    balance_amount = VALUES(balance_amount),
                    updated_at = NOW()
            ");
            mysqli_stmt_bind_param($stmt, 'iiddd', $businessId, $supplierId, $addition, $decrease, $balance);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

function supplier_name_exists(mysqli $conn, int $businessId, string $supplierName, int $excludeSupplierId = 0): bool
{
    $sql = "SELECT COUNT(*) AS total FROM suppliers WHERE business_id = ? AND supplier_name = ?";
    $types = 'is';
    $params = [$businessId, $supplierName];

    if ($excludeSupplierId > 0) {
        $sql .= " AND supplier_id <> ?";
        $types .= 'i';
        $params[] = $excludeSupplierId;
    }

    $row = supplier_one($conn, $sql, $types, $params);

    return (int)($row['total'] ?? 0) > 0;
}

function supplier_has_dependency(mysqli $conn, int $businessId, int $supplierId): bool
{
    foreach (['stock_inward_batches', 'vendor_ledger', 'vendor_outstanding'] as $table) {
        if (supplier_table_exists($conn, $table) && supplier_column_exists($conn, $table, 'supplier_id')) {
            $safe = str_replace('`', '', $table);
            $row = supplier_one($conn, "SELECT COUNT(*) AS total FROM `{$safe}` WHERE business_id = ? AND supplier_id = ?", 'ii', [$businessId, $supplierId]);

            if ((int)($row['total'] ?? 0) > 0) {
                return true;
            }
        }
    }

    return false;
}

try {
    $businessId = supplier_business_id();
    $userId = supplier_user_id();

    if ($businessId <= 0) {
        supplier_json(['success' => false, 'message' => 'Business session missing. Please login again.'], 401);
    }

    $permissions = supplier_permissions($conn);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = (string)($_GET['action'] ?? 'list');

        if ($action === 'list') {
            if (!$permissions['can_view']) {
                throw new RuntimeException('You do not have permission to view suppliers.');
            }

            $search = trim((string)($_GET['search'] ?? ''));
            $status = trim((string)($_GET['status'] ?? ''));

            $types = 'i';
            $params = [$businessId];
            $where = 'business_id = ?';

            if ($status !== '') {
                $where .= ' AND status = ?';
                $types .= 'i';
                $params[] = (int)$status;
            }

            if ($search !== '') {
                $term = '%' . $search . '%';
                $where .= " AND (supplier_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR gstin LIKE ?)";
                $types .= 'ssss';
                array_push($params, $term, $term, $term, $term);
            }

            $suppliers = supplier_rows($conn, "SELECT * FROM suppliers WHERE $where ORDER BY supplier_id DESC", $types, $params);
            $calcMap = supplier_calculate_map($conn, $businessId);

            $stats = [
                'total_suppliers' => 0,
                'active_suppliers' => 0,
                'outstanding_suppliers' => 0,
                'calculated_balance_total' => 0.0,
                'current_outstanding_total' => 0.0,
            ];

            foreach ($suppliers as &$supplier) {
                $supplierId = (int)$supplier['supplier_id'];
                $calc = $calcMap[$supplierId] ?? [
                    'opening_balance' => (float)($supplier['opening_outstanding'] ?? 0),
                    'total_addition' => 0,
                    'total_decrease' => 0,
                    'calculated_balance' => (float)($supplier['opening_outstanding'] ?? 0),
                ];

                $supplier['total_addition'] = $calc['total_addition'];
                $supplier['total_decrease'] = $calc['total_decrease'];
                $supplier['calculated_balance'] = $calc['calculated_balance'];
                $supplier['db_current_balance'] = (float)($supplier['current_outstanding'] ?? 0);
                $supplier['current_outstanding'] = $calc['calculated_balance'];

                $stats['total_suppliers']++;
                if ((int)($supplier['status'] ?? 0) === 1) {
                    $stats['active_suppliers']++;
                }
                if ((float)$calc['calculated_balance'] > 0) {
                    $stats['outstanding_suppliers']++;
                }
                $stats['calculated_balance_total'] = round($stats['calculated_balance_total'] + (float)$calc['calculated_balance'], 2);
                $stats['current_outstanding_total'] = $stats['calculated_balance_total'];
            }
            unset($supplier);

            supplier_json([
                'success' => true,
                'suppliers' => $suppliers,
                'stats' => $stats,
            ]);
        }

        if ($action === 'get') {
            if (!$permissions['can_view']) {
                throw new RuntimeException('You do not have permission to view suppliers.');
            }

            $supplierId = (int)($_GET['supplier_id'] ?? 0);

            if ($supplierId <= 0) {
                throw new RuntimeException('Invalid supplier selected.');
            }

            $supplier = supplier_one($conn, "SELECT * FROM suppliers WHERE business_id = ? AND supplier_id = ? LIMIT 1", 'ii', [$businessId, $supplierId]);

            if (!$supplier) {
                throw new RuntimeException('Supplier not found.');
            }

            $statement = supplier_calculate_statement($conn, $businessId, $supplier);
            $supplier['calculated_balance'] = $statement['summary']['closing_balance'];
            $supplier['db_current_balance'] = (float)($supplier['current_outstanding'] ?? 0);
            $supplier['current_outstanding'] = $statement['summary']['closing_balance'];

            supplier_json([
                'success' => true,
                'supplier' => $supplier,
                'summary' => $statement['summary'],
                'ledger' => $statement['rows'],
            ]);
        }

        supplier_json(['success' => false, 'message' => 'Invalid supplier action.'], 400);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        supplier_json(['success' => false, 'message' => 'Invalid request method.'], 405);
    }

    if (function_exists('verify_csrf')) {
        verify_csrf();
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save_supplier') {
        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $supplierName = trim((string)($_POST['supplier_name'] ?? ''));
        $mobile = trim((string)($_POST['mobile'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $gstin = strtoupper(trim((string)($_POST['gstin'] ?? '')));
        $address = trim((string)($_POST['address'] ?? ''));
        $opening = max(0, (float)($_POST['opening_outstanding'] ?? 0));
        $status = (int)($_POST['status'] ?? 1);

        if ($supplierName === '') {
            throw new RuntimeException('Supplier name is required.');
        }

        if ($supplierId > 0) {
            if (!$permissions['can_edit']) {
                throw new RuntimeException('You do not have permission to edit suppliers.');
            }

            if (supplier_name_exists($conn, $businessId, $supplierName, $supplierId)) {
                throw new RuntimeException('Supplier name already exists.');
            }

            $stmt = mysqli_prepare($conn, "
                UPDATE suppliers
                SET supplier_name = ?, mobile = ?, email = ?, gstin = ?, address = ?,
                    opening_outstanding = ?, status = ?, updated_at = NOW()
                WHERE business_id = ? AND supplier_id = ?
            ");
            mysqli_stmt_bind_param($stmt, 'sssssdiii', $supplierName, $mobile, $email, $gstin, $address, $opening, $status, $businessId, $supplierId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            supplier_refresh_balances($conn, $businessId, $supplierId);
            supplier_json(['success' => true, 'message' => 'Supplier updated successfully.']);
        }

        if (!$permissions['can_create']) {
            throw new RuntimeException('You do not have permission to create suppliers.');
        }

        if (supplier_name_exists($conn, $businessId, $supplierName)) {
            throw new RuntimeException('Supplier name already exists.');
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO suppliers
            (business_id, supplier_name, mobile, email, gstin, address, opening_outstanding, current_outstanding, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'isssssddi', $businessId, $supplierName, $mobile, $email, $gstin, $address, $opening, $opening, $status);
        mysqli_stmt_execute($stmt);
        $newId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        supplier_refresh_balances($conn, $businessId, $newId);
        supplier_json(['success' => true, 'message' => 'Supplier created successfully.']);
    }

    if ($action === 'save_transaction') {
        if (!$permissions['can_create']) {
            throw new RuntimeException('You do not have permission to add supplier transactions.');
        }

        $supplierId = (int)($_POST['supplier_id'] ?? 0);
        $branchId = !empty($_POST['branch_id']) ? (int)$_POST['branch_id'] : null;
        $purpose = strtolower(trim((string)($_POST['purpose'] ?? '')));
        $otherDirection = strtolower(trim((string)($_POST['other_direction'] ?? '')));
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        $referenceType = trim((string)($_POST['reference_type'] ?? 'supplier_transaction')) ?: 'supplier_transaction';
        $referenceNo = trim((string)($_POST['reference_no'] ?? ''));
        $remarks = trim((string)($_POST['remarks'] ?? ''));

        if ($supplierId <= 0) {
            throw new RuntimeException('Please select supplier.');
        }

        if ($amount <= 0) {
            throw new RuntimeException('Enter valid transaction amount.');
        }

        $supplier = supplier_one($conn, "SELECT * FROM suppliers WHERE business_id = ? AND supplier_id = ? LIMIT 1", 'ii', [$businessId, $supplierId]);

        if (!$supplier) {
            throw new RuntimeException('Supplier not found.');
        }

        $direction = 'debit';
        $debit = 0.00;
        $credit = 0.00;

        if ($purpose === 'credit') {
            $direction = 'credit';
            $credit = $amount;
        } elseif ($purpose === 'other') {
            $direction = $otherDirection === 'credit' ? 'credit' : 'debit';
            if ($direction === 'credit') {
                $credit = $amount;
            } else {
                $debit = $amount;
            }
        } else {
            $direction = 'debit';
            $debit = $amount;
        }

        $current = supplier_calculate_statement($conn, $businessId, $supplier);
        $baseBalance = (float)$current['summary']['closing_balance'];
        $delta = supplier_ledger_delta([
            'reference_type' => $referenceType,
            'purpose' => $purpose,
            'transaction_direction' => $direction,
            'debit' => $debit,
            'credit' => $credit,
        ]);
        $newBalance = round($baseBalance + $delta, 2);
        $finalRemarks = $remarks !== '' ? $remarks : ('Purpose: ' . ucwords(str_replace('_', ' ', $purpose)) . ' | ' . ($direction === 'credit' ? 'Amount added' : 'Amount decreased'));

        $stmt = mysqli_prepare($conn, "
            INSERT INTO vendor_ledger
            (business_id, branch_id, supplier_id, reference_type, reference_id, purpose, transaction_direction,
             debit, credit, balance, remarks, created_by)
            VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'iiisssdddsi', $businessId, $branchId, $supplierId, $referenceType, $purpose, $direction, $debit, $credit, $newBalance, $finalRemarks, $userId);
        mysqli_stmt_execute($stmt);
        $ledgerId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        supplier_refresh_balances($conn, $businessId, $supplierId);

        if (function_exists('log_activity')) {
            try {
                log_activity($conn, 'Suppliers', 'transaction', $supplierId, null, [
                    'ledger_id' => $ledgerId,
                    'purpose' => $purpose,
                    'amount' => $amount,
                    'balance' => $newBalance,
                ]);
            } catch (Throwable $ignored) {}
        }

        supplier_json(['success' => true, 'message' => 'Supplier transaction saved successfully.']);
    }

    if ($action === 'toggle_status') {
        if (!$permissions['can_edit']) {
            throw new RuntimeException('You do not have permission to change supplier status.');
        }

        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        if ($supplierId <= 0) {
            throw new RuntimeException('Invalid supplier selected.');
        }

        $stmt = mysqli_prepare($conn, "UPDATE suppliers SET status = IF(status = 1, 0, 1), updated_at = NOW() WHERE business_id = ? AND supplier_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $supplierId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        supplier_json(['success' => true, 'message' => 'Supplier status updated successfully.']);
    }

    if ($action === 'delete_supplier') {
        if (!$permissions['can_delete']) {
            throw new RuntimeException('You do not have permission to delete suppliers.');
        }

        $supplierId = (int)($_POST['supplier_id'] ?? 0);

        if ($supplierId <= 0) {
            throw new RuntimeException('Invalid supplier selected.');
        }

        if (supplier_has_dependency($conn, $businessId, $supplierId)) {
            throw new RuntimeException('This supplier is already used in stock/ledger. Deactivate it instead of deleting.');
        }

        $stmt = mysqli_prepare($conn, "DELETE FROM suppliers WHERE business_id = ? AND supplier_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $supplierId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        supplier_json(['success' => true, 'message' => 'Supplier deleted successfully.']);
    }

    supplier_json(['success' => false, 'message' => 'Invalid supplier action.'], 400);
} catch (Throwable $e) {
    supplier_json(['success' => false, 'message' => $e->getMessage()], 500);
}
?>
