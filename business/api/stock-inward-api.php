<?php
/**
 * Universal Footwear POS - Stock Inward API
 * Place at: business/api/stock-inward-api.php
 *
 * Important supplier ledger rule:
 * Stock inward purchase amount is automatically added to the supplier account.
 * Supplier calculated balance = Opening + Stock Inward / Credit additions - Payment / Debit / Reversal decreases.
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

if (function_exists('require_page_access')) {
    require_page_access($conn, 'stock-inward.php');
}

function si_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function si_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int) current_user_id();
    }

    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function si_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int) current_business_id();
    }

    return (int)($_SESSION['business_id'] ?? 0);
}

function si_table_exists(mysqli $conn, string $table): bool
{
    if (function_exists('table_exists')) {
        return (bool) table_exists($conn, $table);
    }

    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    return $res && mysqli_num_rows($res) > 0;
}

function si_column_exists(mysqli $conn, string $table, string $column): bool
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

function si_bind(mysqli_stmt $stmt, string $types, array $params): void
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

function si_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('SQL prepare failed: ' . mysqli_error($conn));
    }

    si_bind($stmt, $types, $params);

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

function si_one(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = si_rows($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function si_verify_csrf_token(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $posted = (string)($_POST['csrf_token'] ?? $_POST['_token'] ?? '');
    $session = (string)($_SESSION['business_csrf_token'] ?? $_SESSION['csrf_token'] ?? '');

    if ($session !== '' && $posted !== '' && hash_equals($session, $posted)) {
        return;
    }

    if (function_exists('csrf_token')) {
        $expected = (string) csrf_token();
        if ($posted !== '' && hash_equals($expected, $posted)) {
            return;
        }
    }

    si_json(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403);
}

function si_permissions(mysqli $conn): array
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

    $businessId = si_business_id();
    $roleId = function_exists('current_role_id') ? (int) current_role_id() : (int)($_SESSION['role_id'] ?? 0);

    if ($businessId <= 0 || $roleId <= 0 || !si_table_exists($conn, 'business_sidebar_menus') || !si_table_exists($conn, 'business_role_sidebar_access')) {
        return $all;
    }

    $cols = ['can_view'];
    foreach (['can_create', 'can_edit', 'can_delete', 'can_print', 'can_export'] as $col) {
        $cols[] = si_column_exists($conn, 'business_role_sidebar_access', $col) ? $col : '0 AS ' . $col;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT " . implode(', ', $cols) . "
        FROM business_sidebar_menus sm
        INNER JOIN business_role_sidebar_access rsa
            ON rsa.menu_id = sm.id
           AND rsa.business_id = sm.business_id
           AND rsa.role_id = ?
        WHERE sm.business_id = ?
          AND sm.menu_url = 'stock-inward.php'
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

function si_allowed_branch_ids(mysqli $conn, int $businessId, int $userId): array
{
    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        $rows = si_rows($conn, "SELECT branch_id FROM branches WHERE business_id = ? AND status = 1", 'i', [$businessId]);
        return array_map(static fn($row) => (int)$row['branch_id'], $rows);
    }

    if (!si_table_exists($conn, 'user_branch_access')) {
        $rows = si_rows($conn, "SELECT branch_id FROM branches WHERE business_id = ? AND status = 1", 'i', [$businessId]);
        return array_map(static fn($row) => (int)$row['branch_id'], $rows);
    }

    $rows = si_rows($conn, "
        SELECT DISTINCT b.branch_id
        FROM branches b
        LEFT JOIN user_branch_access uba
            ON uba.business_id = b.business_id
           AND uba.branch_id = b.branch_id
           AND uba.user_id = ?
           AND uba.access_status = 1
        LEFT JOIN users u
            ON u.business_id = b.business_id
           AND u.default_branch_id = b.branch_id
           AND u.user_id = ?
        WHERE b.business_id = ?
          AND b.status = 1
          AND (uba.id IS NOT NULL OR u.user_id IS NOT NULL)
    ", 'iii', [$userId, $userId, $businessId]);

    return array_map(static fn($row) => (int)$row['branch_id'], $rows);
}

function si_require_branch_access(array $allowedBranchIds, int $branchId): void
{
    if ($branchId <= 0 || !in_array($branchId, $allowedBranchIds, true)) {
        throw new RuntimeException('Invalid branch / firm access.');
    }
}

function si_ledger_delta(array $row): float
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
        // Stock inward purchase amount must add to supplier account.
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

function si_calculate_supplier_balance(mysqli $conn, int $businessId, int $supplierId): array
{
    $supplier = si_one($conn, "SELECT supplier_id, opening_outstanding FROM suppliers WHERE business_id = ? AND supplier_id = ? LIMIT 1", 'ii', [$businessId, $supplierId]);

    if (!$supplier) {
        throw new RuntimeException('Supplier not found.');
    }

    $balance = round((float)($supplier['opening_outstanding'] ?? 0), 2);
    $addition = 0.0;
    $decrease = 0.0;

    $rows = si_rows($conn, "
        SELECT reference_type, purpose, transaction_direction, debit, credit
        FROM vendor_ledger
        WHERE business_id = ?
          AND supplier_id = ?
          AND COALESCE(reference_type, '') <> 'opening'
        ORDER BY created_at ASC, vendor_ledger_id ASC
    ", 'ii', [$businessId, $supplierId]);

    foreach ($rows as $row) {
        $delta = si_ledger_delta($row);

        if ($delta >= 0) {
            $addition = round($addition + $delta, 2);
        } else {
            $decrease = round($decrease + abs($delta), 2);
        }

        $balance = round($balance + $delta, 2);
    }

    return [
        'opening' => round((float)($supplier['opening_outstanding'] ?? 0), 2),
        'total_addition' => round($addition, 2),
        'total_decrease' => round($decrease, 2),
        'balance' => round($balance, 2),
    ];
}

function si_refresh_supplier_balance(mysqli $conn, int $businessId, int $supplierId): void
{
    $calc = si_calculate_supplier_balance($conn, $businessId, $supplierId);

    if (si_column_exists($conn, 'suppliers', 'current_outstanding')) {
        $stmt = mysqli_prepare($conn, "UPDATE suppliers SET current_outstanding = ?, updated_at = NOW() WHERE business_id = ? AND supplier_id = ?");
        mysqli_stmt_bind_param($stmt, 'dii', $calc['balance'], $businessId, $supplierId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    if (si_table_exists($conn, 'vendor_outstanding')) {
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
        mysqli_stmt_bind_param($stmt, 'iiddd', $businessId, $supplierId, $calc['total_addition'], $calc['total_decrease'], $calc['balance']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function si_post_supplier_ledger(mysqli $conn, int $businessId, ?int $branchId, int $supplierId, string $referenceType, int $referenceId, float $amount, string $mode, string $remarks): void
{
    $amount = round($amount, 2);

    if ($supplierId <= 0 || $amount <= 0) {
        return;
    }

    $before = si_calculate_supplier_balance($conn, $businessId, $supplierId);
    $delta = $mode === 'add' ? $amount : -$amount;
    $newBalance = round((float)$before['balance'] + $delta, 2);

    if ($mode === 'add') {
        $purpose = 'credit';
        $direction = 'credit';
        $debit = 0.00;
        $credit = $amount;
    } else {
        $purpose = 'debit';
        $direction = 'debit';
        $debit = $amount;
        $credit = 0.00;
    }

    $createdBy = si_user_id();
    $stmt = mysqli_prepare($conn, "
        INSERT INTO vendor_ledger
        (business_id, branch_id, supplier_id, reference_type, reference_id, purpose, transaction_direction, debit, credit, balance, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    mysqli_stmt_bind_param($stmt, 'iiisissddssi', $businessId, $branchId, $supplierId, $referenceType, $referenceId, $purpose, $direction, $debit, $credit, $newBalance, $remarks, $createdBy);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    si_refresh_supplier_balance($conn, $businessId, $supplierId);
}

function si_sequence(mysqli $conn, int $businessId, ?int $branchId, string $key, string $prefix, int $padding): string
{
    $stmt = mysqli_prepare($conn, "
        SELECT sequence_id, current_number, prefix, padding_length
        FROM number_sequences
        WHERE business_id = ?
          AND ((branch_id IS NULL AND ? IS NULL) OR branch_id = ?)
          AND sequence_key = ?
        LIMIT 1
        FOR UPDATE
    ");
    mysqli_stmt_bind_param($stmt, 'iiis', $businessId, $branchId, $branchId, $key);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        $zero = 0;
        $active = 1;
        $stmt = mysqli_prepare($conn, "
            INSERT INTO number_sequences
            (business_id, branch_id, sequence_key, prefix, current_number, padding_length, status)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'iissiii', $businessId, $branchId, $key, $prefix, $zero, $padding, $active);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $current = 0;
        $seqPrefix = $prefix;
        $seqPadding = $padding;
    } else {
        $current = (int)$row['current_number'];
        $seqPrefix = (string)($row['prefix'] ?: $prefix);
        $seqPadding = (int)($row['padding_length'] ?: $padding);
    }

    $next = $current + 1;

    $stmt = mysqli_prepare($conn, "
        UPDATE number_sequences
        SET current_number = ?, updated_at = NOW()
        WHERE business_id = ?
          AND ((branch_id IS NULL AND ? IS NULL) OR branch_id = ?)
          AND sequence_key = ?
    ");
    mysqli_stmt_bind_param($stmt, 'iiiis', $next, $businessId, $branchId, $branchId, $key);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $seqPrefix . '-' . str_pad((string)$next, $seqPadding, '0', STR_PAD_LEFT);
}

function si_batch_no_exists(mysqli $conn, int $businessId, int $branchId, string $batchNo): bool
{
    $row = si_one($conn, "
        SELECT COUNT(*) AS total
        FROM stock_inward_batches
        WHERE business_id = ?
          AND branch_id = ?
          AND batch_no = ?
    ", 'iis', [$businessId, $branchId, $batchNo]);

    return (int)($row['total'] ?? 0) > 0;
}

function si_generate_batch_no(mysqli $conn, int $businessId, int $branchId): string
{
    for ($i = 0; $i < 20; $i++) {
        $batchNo = si_sequence($conn, $businessId, $branchId, 'batch_no', 'BATCH', 4);

        if (!si_batch_no_exists($conn, $businessId, $branchId, $batchNo)) {
            return $batchNo;
        }
    }

    return 'BATCH-' . date('YmdHis');
}

function si_generate_barcode(mysqli $conn, int $businessId, int $branchId): string
{
    for ($i = 0; $i < 20; $i++) {
        $barcode = si_sequence($conn, $businessId, null, 'stock_barcode', 'STK', 6);
        $row = si_one($conn, "SELECT COUNT(*) AS total FROM stock_barcodes WHERE barcode_value = ?", 's', [$barcode]);

        if ((int)($row['total'] ?? 0) === 0) {
            return $barcode;
        }
    }

    return 'STK-' . date('YmdHis') . random_int(100, 999);
}

function si_discount_amount(float $mrp, string $type, float $value): float
{
    if ($type === 'percent') {
        return round($mrp * max(0, $value) / 100, 2);
    }

    if ($type === 'amount') {
        return round(max(0, $value), 2);
    }

    return 0.0;
}

function si_item_payloads(): array
{
    $raw = (string)($_POST['items_json'] ?? '');
    $items = json_decode($raw, true);

    if (!is_array($items) || !$items) {
        throw new RuntimeException('Please add at least one product line.');
    }

    $clean = [];

    foreach ($items as $index => $item) {
        $qty = round((float)($item['qty'] ?? 0), 2);
        $purchase = round((float)($item['purchase_rate'] ?? 0), 2);
        $mrp = round((float)($item['mrp_rate'] ?? 0), 2);
        $discountType = in_array((string)($item['product_discount_type'] ?? 'none'), ['none', 'percent', 'amount'], true) ? (string)$item['product_discount_type'] : 'none';
        $discountValue = round((float)($item['product_discount_value'] ?? 0), 2);
        $discountAmount = si_discount_amount($mrp, $discountType, $discountValue);
        $selling = round(max(0, $mrp - $discountAmount), 2);

        $row = [
            'row_no' => $index + 1,
            'category_id' => (int)($item['category_id'] ?? 0),
            'brand_id' => (int)($item['brand_id'] ?? 0),
            'article_no' => trim((string)($item['article_no'] ?? '')),
            'article_name' => trim((string)($item['article_name'] ?? '')),
            'size' => trim((string)($item['size'] ?? '')),
            'color' => trim((string)($item['color'] ?? '')),
            'qty' => $qty,
            'purchase_rate' => $purchase,
            'mrp_rate' => $mrp,
            'product_discount_type' => $discountType,
            'product_discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'selling_rate' => $selling,
            'barcode_required' => (int)($item['barcode_required'] ?? 1) === 1 ? 1 : 0,
            'line_purchase_value' => round($qty * $purchase, 2),
            'line_mrp_value' => round($qty * $mrp, 2),
            'line_selling_value' => round($qty * $selling, 2),
        ];

        if ($row['category_id'] <= 0) {
            throw new RuntimeException('Row ' . $row['row_no'] . ': Category is required.');
        }

        if ($row['brand_id'] <= 0) {
            throw new RuntimeException('Row ' . $row['row_no'] . ': Brand is required.');
        }

        if ($row['article_no'] === '') {
            throw new RuntimeException('Row ' . $row['row_no'] . ': Article number is required.');
        }

        if ($row['size'] === '') {
            throw new RuntimeException('Row ' . $row['row_no'] . ': Size is required.');
        }

        if ($qty <= 0 || $purchase <= 0 || $mrp <= 0 || $selling <= 0) {
            throw new RuntimeException('Row ' . $row['row_no'] . ': Qty, purchase, MRP and selling rate must be greater than zero.');
        }

        if ($mrp < $purchase) {
            throw new RuntimeException('Row ' . $row['row_no'] . ': MRP must be greater than or equal to purchase rate.');
        }

        $clean[] = $row;
    }

    return $clean;
}

function si_check_supplier(mysqli $conn, int $businessId, int $supplierId): void
{
    $row = si_one($conn, "SELECT supplier_id FROM suppliers WHERE business_id = ? AND supplier_id = ? AND status = 1 LIMIT 1", 'ii', [$businessId, $supplierId]);

    if (!$row) {
        throw new RuntimeException('Invalid supplier selected.');
    }
}

function si_check_master(mysqli $conn, int $businessId, string $table, string $idColumn, int $id, string $label): void
{
    $row = si_one($conn, "SELECT {$idColumn} FROM {$table} WHERE business_id = ? AND {$idColumn} = ? AND status = 1 LIMIT 1", 'ii', [$businessId, $id]);

    if (!$row) {
        throw new RuntimeException("Invalid {$label} selected.");
    }
}

function si_batch_is_used(mysqli $conn, int $businessId, int $batchId): bool
{
    $row = si_one($conn, "
        SELECT
            COALESCE(SUM(CASE WHEN available_qty < qty THEN 1 ELSE 0 END),0) AS used_items
        FROM stock_inward_items
        WHERE business_id = ?
          AND batch_id = ?
    ", 'ii', [$businessId, $batchId]);

    if ((int)($row['used_items'] ?? 0) > 0) {
        return true;
    }

    $barcode = si_one($conn, "
        SELECT COUNT(*) AS total
        FROM stock_barcodes
        WHERE business_id = ?
          AND batch_id = ?
          AND barcode_status = 'used'
    ", 'ii', [$businessId, $batchId]);

    return (int)($barcode['total'] ?? 0) > 0;
}

function si_insert_items(mysqli $conn, int $businessId, int $branchId, int $batchId, string $inwardDate, array $items): void
{
    $createdBy = si_user_id();

    foreach ($items as $item) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO stock_inward_items
            (business_id, branch_id, batch_id, stock_entry_date, category_id, brand_id, article_no, article_name, size, color,
             qty, available_qty, purchase_rate, mrp_rate, product_discount_type, product_discount_value, discount_amount, selling_rate,
             line_purchase_value, line_mrp_value, line_selling_value, barcode_required, item_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        mysqli_stmt_bind_param(
            $stmt,
            'iiisiissssddddsddddddi',
            $businessId,
            $branchId,
            $batchId,
            $inwardDate,
            $item['category_id'],
            $item['brand_id'],
            $item['article_no'],
            $item['article_name'],
            $item['size'],
            $item['color'],
            $item['qty'],
            $item['qty'],
            $item['purchase_rate'],
            $item['mrp_rate'],
            $item['product_discount_type'],
            $item['product_discount_value'],
            $item['discount_amount'],
            $item['selling_rate'],
            $item['line_purchase_value'],
            $item['line_mrp_value'],
            $item['line_selling_value'],
            $item['barcode_required']
        );
        mysqli_stmt_execute($stmt);
        $stockItemId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $remarks = 'Stock inward batch #' . $batchId;
        $stmt = mysqli_prepare($conn, "
            INSERT INTO stock_movements
            (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, entry_date, qty_in, qty_out, balance_qty, remarks, created_by)
            VALUES (?, ?, ?, 'inward', 'stock_inward', ?, ?, ?, 0, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'iiiisddsi', $businessId, $branchId, $stockItemId, $batchId, $inwardDate, $item['qty'], $item['qty'], $remarks, $createdBy);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ((int)$item['barcode_required'] === 1) {
            /*
             * One barcode is generated for the complete purchased product line.
             * Example: Qty 10 => one shared barcode such as STK-000148.
             * The next inward product line receives the next sequence number.
             */
            $barcode = si_generate_barcode($conn, $businessId, $branchId);
            $stmt = mysqli_prepare($conn, "
                INSERT INTO stock_barcodes
                (business_id, branch_id, batch_id, stock_item_id, barcode_value, barcode_status)
                VALUES (?, ?, ?, ?, ?, 'active')
            ");

            if (!$stmt) {
                throw new RuntimeException('Barcode insert prepare failed: ' . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt, 'iiiis', $businessId, $branchId, $batchId, $stockItemId, $barcode);

            if (!mysqli_stmt_execute($stmt)) {
                $error = mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
                throw new RuntimeException('Barcode insert failed: ' . $error);
            }

            mysqli_stmt_close($stmt);
        }
    }
}

function si_delete_batch_item_rows(mysqli $conn, int $businessId, int $batchId): void
{
    $stmt = mysqli_prepare($conn, "DELETE sm FROM stock_movements sm INNER JOIN stock_inward_items sii ON sii.stock_item_id = sm.stock_item_id WHERE sii.business_id = ? AND sii.batch_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $businessId, $batchId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "DELETE FROM stock_barcodes WHERE business_id = ? AND batch_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $businessId, $batchId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "DELETE FROM stock_inward_items WHERE business_id = ? AND batch_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $businessId, $batchId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function si_save_stock_inward(mysqli $conn, int $businessId, array $allowedBranchIds, array $permissions): array
{
    $batchId = (int)($_POST['batch_id'] ?? 0);
    $branchId = (int)($_POST['branch_id'] ?? 0);
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $inwardDate = (string)($_POST['inward_date'] ?? date('Y-m-d'));
    $invoiceNumber = trim((string)($_POST['invoice_number'] ?? ''));
    $invoiceDate = trim((string)($_POST['invoice_date'] ?? ''));
    $remarks = trim((string)($_POST['remarks'] ?? ''));

    if ($batchId > 0 && !$permissions['can_edit']) {
        throw new RuntimeException('You do not have permission to edit stock inward.');
    }

    if ($batchId <= 0 && !$permissions['can_create']) {
        throw new RuntimeException('You do not have permission to create stock inward.');
    }

    si_require_branch_access($allowedBranchIds, $branchId);
    si_check_supplier($conn, $businessId, $supplierId);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $inwardDate)) {
        throw new RuntimeException('Invalid stock entry date.');
    }

    if ($invoiceDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $invoiceDate)) {
        throw new RuntimeException('Invalid invoice date.');
    }

    $items = si_item_payloads();

    foreach ($items as $item) {
        si_check_master($conn, $businessId, 'categories', 'category_id', (int)$item['category_id'], 'category');
        si_check_master($conn, $businessId, 'brands', 'brand_id', (int)$item['brand_id'], 'brand');
    }

    $totalQty = 0.0;
    $purchaseTotal = 0.0;
    $mrpTotal = 0.0;
    $sellingTotal = 0.0;

    foreach ($items as $item) {
        $totalQty = round($totalQty + (float)$item['qty'], 2);
        $purchaseTotal = round($purchaseTotal + (float)$item['line_purchase_value'], 2);
        $mrpTotal = round($mrpTotal + (float)$item['line_mrp_value'], 2);
        $sellingTotal = round($sellingTotal + (float)$item['line_selling_value'], 2);
    }

    $createdBy = si_user_id();

    mysqli_begin_transaction($conn);

    try {
        $oldBatch = [];
        $oldSupplierId = 0;
        $oldPurchaseTotal = 0.0;

        if ($batchId > 0) {
            $oldBatch = si_one($conn, "
                SELECT *
                FROM stock_inward_batches
                WHERE business_id = ?
                  AND batch_id = ?
                LIMIT 1
                FOR UPDATE
            ", 'ii', [$businessId, $batchId]);

            if (!$oldBatch) {
                throw new RuntimeException('Stock inward batch not found.');
            }

            si_require_branch_access($allowedBranchIds, (int)$oldBatch['branch_id']);

            if ((string)$oldBatch['batch_status'] !== 'active') {
                throw new RuntimeException('Only active stock inward batch can be edited.');
            }

            if (si_batch_is_used($conn, $businessId, $batchId)) {
                throw new RuntimeException('This stock inward batch has sales/used barcode entries. Edit is blocked.');
            }

            $oldSupplierId = (int)($oldBatch['supplier_id'] ?? 0);
            $oldPurchaseTotal = round((float)($oldBatch['purchase_total_value'] ?? 0), 2);

            if ($oldSupplierId > 0 && $oldPurchaseTotal > 0) {
                si_post_supplier_ledger(
                    $conn,
                    $businessId,
                    (int)$oldBatch['branch_id'],
                    $oldSupplierId,
                    'stock_inward_update_reverse',
                    $batchId,
                    $oldPurchaseTotal,
                    'decrease',
                    'Stock inward purchase reversed before update: ' . (string)$oldBatch['batch_no']
                );
            }

            si_delete_batch_item_rows($conn, $businessId, $batchId);

            $stmt = mysqli_prepare($conn, "
                UPDATE stock_inward_batches
                SET branch_id = ?, inward_date = ?, inward_time = CURTIME(), supplier_id = ?, invoice_number = ?, invoice_date = NULLIF(?, ''),
                    total_qty = ?, purchase_total_value = ?, mrp_total_value = ?, selling_total_value = ?, remarks = ?, updated_at = NOW()
                WHERE business_id = ? AND batch_id = ?
            ");
            mysqli_stmt_bind_param($stmt, 'isissddddsii', $branchId, $inwardDate, $supplierId, $invoiceNumber, $invoiceDate, $totalQty, $purchaseTotal, $mrpTotal, $sellingTotal, $remarks, $businessId, $batchId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $batchNo = (string)$oldBatch['batch_no'];
            $referenceType = 'stock_inward_update';
            $ledgerRemarks = 'Stock inward purchase posted after update: ' . $batchNo;
        } else {
            $batchNo = si_generate_batch_no($conn, $businessId, $branchId);

            $stmt = mysqli_prepare($conn, "
                INSERT INTO stock_inward_batches
                (business_id, branch_id, batch_no, inward_date, inward_time, supplier_id, invoice_number, invoice_date,
                 total_qty, purchase_total_value, mrp_total_value, selling_total_value, remarks, batch_status, created_by)
                VALUES (?, ?, ?, ?, CURTIME(), ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, 'active', ?)
            ");
            mysqli_stmt_bind_param($stmt, 'iississddddsi', $businessId, $branchId, $batchNo, $inwardDate, $supplierId, $invoiceNumber, $invoiceDate, $totalQty, $purchaseTotal, $mrpTotal, $sellingTotal, $remarks, $createdBy);
            mysqli_stmt_execute($stmt);
            $batchId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $referenceType = 'stock_inward';
            $ledgerRemarks = 'Stock inward purchase posted: ' . $batchNo;
        }

        si_insert_items($conn, $businessId, $branchId, $batchId, $inwardDate, $items);

        if ($purchaseTotal > 0) {
            si_post_supplier_ledger(
                $conn,
                $businessId,
                $branchId,
                $supplierId,
                $referenceType,
                $batchId,
                $purchaseTotal,
                'add',
                $ledgerRemarks
            );
        }

        if ($oldSupplierId > 0 && $oldSupplierId !== $supplierId) {
            si_refresh_supplier_balance($conn, $businessId, $oldSupplierId);
        }

        si_refresh_supplier_balance($conn, $businessId, $supplierId);

        if (function_exists('log_activity')) {
            try {
                log_activity($conn, 'Stock Inward', $batchId > 0 ? 'save' : 'create', $batchId, null, [
                    'batch_no' => $batchNo,
                    'supplier_id' => $supplierId,
                    'purchase_total_value' => $purchaseTotal,
                ]);
            } catch (Throwable $ignored) {
                // Activity log should not block stock inward save.
            }
        }

        mysqli_commit($conn);

        return [
            'batch_id' => $batchId,
            'batch_no' => $batchNo,
            'supplier_id' => $supplierId,
            'purchase_total_value' => $purchaseTotal,
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}


function si_get_stock_item_for_edit(
    mysqli $conn,
    int $businessId,
    int $stockItemId,
    array $allowedBranchIds
): array {
    if ($stockItemId <= 0) {
        throw new RuntimeException('Invalid stock item.');
    }

    if (!$allowedBranchIds) {
        throw new RuntimeException('No branch access.');
    }

    $branchSql = implode(',', array_map('intval', $allowedBranchIds));

    $item = si_one($conn, "
        SELECT
            sii.*,
            br.brand_name,
            c.category_name,
            sib.batch_no,
            sib.batch_status,
            MIN(CASE WHEN sb.barcode_status <> 'deleted' THEN sb.barcode_value ELSE NULL END) AS barcode_value,
            GREATEST(0, sii.qty - sii.available_qty) AS used_qty
        FROM stock_inward_items sii
        INNER JOIN stock_inward_batches sib
            ON sib.business_id = sii.business_id
           AND sib.batch_id = sii.batch_id
        LEFT JOIN brands br
            ON br.business_id = sii.business_id
           AND br.brand_id = sii.brand_id
        LEFT JOIN categories c
            ON c.business_id = sii.business_id
           AND c.category_id = sii.category_id
        LEFT JOIN stock_barcodes sb
            ON sb.business_id = sii.business_id
           AND sb.stock_item_id = sii.stock_item_id
        WHERE sii.business_id = ?
          AND sii.stock_item_id = ?
          AND sii.branch_id IN ($branchSql)
        GROUP BY sii.stock_item_id
        LIMIT 1
    ", 'ii', [$businessId, $stockItemId]);

    if (!$item) {
        throw new RuntimeException('Stock item not found.');
    }

    return $item;
}

function si_recalculate_batch_totals(
    mysqli $conn,
    int $businessId,
    int $batchId
): array {
    $totals = si_one($conn, "
        SELECT
            COALESCE(SUM(qty), 0) AS total_qty,
            COALESCE(SUM(qty * purchase_rate), 0) AS purchase_total,
            COALESCE(SUM(qty * mrp_rate), 0) AS mrp_total,
            COALESCE(SUM(qty * selling_rate), 0) AS selling_total
        FROM stock_inward_items
        WHERE business_id = ?
          AND batch_id = ?
          AND item_status <> 'deleted'
    ", 'ii', [$businessId, $batchId]);

    $totalQty = round((float)($totals['total_qty'] ?? 0), 2);
    $purchaseTotal = round((float)($totals['purchase_total'] ?? 0), 2);
    $mrpTotal = round((float)($totals['mrp_total'] ?? 0), 2);
    $sellingTotal = round((float)($totals['selling_total'] ?? 0), 2);

    $stmt = mysqli_prepare($conn, "
        UPDATE stock_inward_batches
        SET total_qty = ?,
            purchase_total_value = ?,
            mrp_total_value = ?,
            selling_total_value = ?,
            updated_at = NOW()
        WHERE business_id = ?
          AND batch_id = ?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        'ddddii',
        $totalQty,
        $purchaseTotal,
        $mrpTotal,
        $sellingTotal,
        $businessId,
        $batchId
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return [
        'total_qty' => $totalQty,
        'purchase_total' => $purchaseTotal,
        'mrp_total' => $mrpTotal,
        'selling_total' => $sellingTotal,
    ];
}

function si_update_stock_item(
    mysqli $conn,
    int $businessId,
    array $allowedBranchIds,
    array $permissions
): array {
    if (!$permissions['can_edit']) {
        throw new RuntimeException('You do not have permission to edit stock.');
    }

    $stockItemId = (int)($_POST['stock_item_id'] ?? 0);
    $articleName = trim((string)($_POST['article_name'] ?? ''));
    $brandId = (int)($_POST['brand_id'] ?? 0);
    $size = trim((string)($_POST['size'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $purchaseRate = round((float)($_POST['purchase_rate'] ?? 0), 2);
    $mrpRate = round((float)($_POST['mrp_rate'] ?? 0), 2);
    $sellingRate = round((float)($_POST['selling_rate'] ?? 0), 2);
    $newQty = round((float)($_POST['qty'] ?? 0), 2);

    if ($stockItemId <= 0) {
        throw new RuntimeException('Invalid stock item.');
    }

    if ($articleName === '') {
        throw new RuntimeException('Product name is required.');
    }

    if ($brandId <= 0) {
        throw new RuntimeException('Brand is required.');
    }

    if ($size === '') {
        throw new RuntimeException('Size is required.');
    }

    if ($purchaseRate <= 0 || $mrpRate <= 0 || $sellingRate <= 0 || $newQty <= 0) {
        throw new RuntimeException('Quantity and all prices must be greater than zero.');
    }

    if ($mrpRate < $purchaseRate) {
        throw new RuntimeException('MRP must be greater than or equal to purchase price.');
    }

    if ($sellingRate > $mrpRate) {
        throw new RuntimeException('Selling price cannot be greater than MRP.');
    }

    si_check_master($conn, $businessId, 'brands', 'brand_id', $brandId, 'brand');

    mysqli_begin_transaction($conn);

    try {
        $item = si_one($conn, "
            SELECT
                sii.*,
                sib.batch_status,
                sib.supplier_id,
                sib.purchase_total_value AS old_batch_purchase_total,
                sib.batch_no
            FROM stock_inward_items sii
            INNER JOIN stock_inward_batches sib
                ON sib.business_id = sii.business_id
               AND sib.batch_id = sii.batch_id
            WHERE sii.business_id = ?
              AND sii.stock_item_id = ?
            LIMIT 1
            FOR UPDATE
        ", 'ii', [$businessId, $stockItemId]);

        if (!$item) {
            throw new RuntimeException('Stock item not found.');
        }

        si_require_branch_access($allowedBranchIds, (int)$item['branch_id']);

        if ((string)$item['batch_status'] !== 'active') {
            throw new RuntimeException('Only stock items in an active batch can be edited.');
        }

        if ((string)($item['item_status'] ?? 'active') !== 'active') {
            throw new RuntimeException('Only active stock items can be edited.');
        }

        $oldQty = round((float)$item['qty'], 2);
        $oldAvailable = round((float)$item['available_qty'], 2);
        $usedQty = round(max(0, $oldQty - $oldAvailable), 2);

        if ($newQty < $usedQty) {
            throw new RuntimeException(
                'Quantity cannot be less than the already sold/used quantity of '
                . number_format($usedQty, 2, '.', '') . '.'
            );
        }

        $newAvailable = round($newQty - $usedQty, 2);
        $qtyDelta = round($newQty - $oldQty, 2);

        $linePurchaseValue = round($newQty * $purchaseRate, 2);
        $lineMrpValue = round($newQty * $mrpRate, 2);
        $lineSellingValue = round($newQty * $sellingRate, 2);
        $discountAmount = round(max(0, $mrpRate - $sellingRate), 2);

        $stmt = mysqli_prepare($conn, "
            UPDATE stock_inward_items
            SET article_name = ?,
                brand_id = ?,
                size = ?,
                color = ?,
                qty = ?,
                available_qty = ?,
                purchase_rate = ?,
                mrp_rate = ?,
                product_discount_type = 'amount',
                product_discount_value = ?,
                discount_amount = ?,
                selling_rate = ?,
                line_purchase_value = ?,
                line_mrp_value = ?,
                line_selling_value = ?,
                updated_at = NOW()
            WHERE business_id = ?
              AND stock_item_id = ?
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'sissddddddddddii',
            $articleName,
            $brandId,
            $size,
            $color,
            $newQty,
            $newAvailable,
            $purchaseRate,
            $mrpRate,
            $discountAmount,
            $discountAmount,
            $sellingRate,
            $linePurchaseValue,
            $lineMrpValue,
            $lineSellingValue,
            $businessId,
            $stockItemId
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        // Record only the quantity difference. Existing barcode rows remain untouched.
        if (abs($qtyDelta) > 0.000001) {
            $qtyIn = $qtyDelta > 0 ? $qtyDelta : 0.0;
            $qtyOut = $qtyDelta < 0 ? abs($qtyDelta) : 0.0;
            $remarks = $qtyDelta > 0
                ? 'Existing stock quantity increased through Edit Stock'
                : 'Existing stock quantity reduced through Edit Stock';
            $createdBy = si_user_id();

            $stmt = mysqli_prepare($conn, "
                INSERT INTO stock_movements
                (business_id, branch_id, stock_item_id, movement_type, reference_type,
                 reference_id, entry_date, qty_in, qty_out, balance_qty, remarks, created_by)
                VALUES (?, ?, ?, 'adjustment', 'stock_item_edit', ?, CURDATE(), ?, ?, ?, ?, ?)
            ");

            $referenceId = (int)$item['batch_id'];

            mysqli_stmt_bind_param(
                $stmt,
                'iiiidddsi',
                $businessId,
                $item['branch_id'],
                $stockItemId,
                $referenceId,
                $qtyIn,
                $qtyOut,
                $newAvailable,
                $remarks,
                $createdBy
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $batchTotals = si_recalculate_batch_totals(
            $conn,
            $businessId,
            (int)$item['batch_id']
        );

        $oldBatchPurchaseTotal = round((float)$item['old_batch_purchase_total'], 2);
        $newBatchPurchaseTotal = round((float)$batchTotals['purchase_total'], 2);
        $supplierDifference = round($newBatchPurchaseTotal - $oldBatchPurchaseTotal, 2);
        $supplierId = (int)($item['supplier_id'] ?? 0);

        if ($supplierId > 0 && abs($supplierDifference) > 0.000001) {
            si_post_supplier_ledger(
                $conn,
                $businessId,
                (int)$item['branch_id'],
                $supplierId,
                'stock_inward_item_edit',
                (int)$item['batch_id'],
                abs($supplierDifference),
                $supplierDifference > 0 ? 'add' : 'decrease',
                'Stock item edited in batch: ' . (string)$item['batch_no']
            );
        }

        if ($supplierId > 0) {
            si_refresh_supplier_balance($conn, $businessId, $supplierId);
        }

        if (function_exists('log_activity')) {
            try {
                log_activity(
                    $conn,
                    'Stock Inward',
                    'edit_stock_item',
                    $stockItemId,
                    null,
                    [
                        'batch_id' => (int)$item['batch_id'],
                        'old_qty' => $oldQty,
                        'new_qty' => $newQty,
                        'barcode_preserved' => true,
                    ]
                );
            } catch (Throwable $ignored) {}
        }

        mysqli_commit($conn);

        return [
            'stock_item_id' => $stockItemId,
            'batch_id' => (int)$item['batch_id'],
            'old_qty' => $oldQty,
            'new_qty' => $newQty,
            'available_qty' => $newAvailable,
            'barcode_preserved' => true,
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

function si_cancel_stock_inward(mysqli $conn, int $businessId, array $allowedBranchIds, array $permissions): array
{
    if (!$permissions['can_delete']) {
        throw new RuntimeException('You do not have permission to cancel stock inward.');
    }

    $batchId = (int)($_POST['batch_id'] ?? 0);

    if ($batchId <= 0) {
        throw new RuntimeException('Invalid stock inward batch.');
    }

    mysqli_begin_transaction($conn);

    try {
        $batch = si_one($conn, "
            SELECT *
            FROM stock_inward_batches
            WHERE business_id = ?
              AND batch_id = ?
            LIMIT 1
            FOR UPDATE
        ", 'ii', [$businessId, $batchId]);

        if (!$batch) {
            throw new RuntimeException('Stock inward batch not found.');
        }

        si_require_branch_access($allowedBranchIds, (int)$batch['branch_id']);

        if ((string)$batch['batch_status'] !== 'active') {
            throw new RuntimeException('Only active stock inward batch can be cancelled.');
        }

        if (si_batch_is_used($conn, $businessId, $batchId)) {
            throw new RuntimeException('This stock inward batch has sales/used barcode entries. Cancel is blocked.');
        }

        $supplierId = (int)($batch['supplier_id'] ?? 0);
        $purchaseTotal = round((float)($batch['purchase_total_value'] ?? 0), 2);

        $items = si_rows($conn, "
            SELECT stock_item_id, branch_id, qty
            FROM stock_inward_items
            WHERE business_id = ?
              AND batch_id = ?
        ", 'ii', [$businessId, $batchId]);

        foreach ($items as $item) {
            $qty = round((float)($item['qty'] ?? 0), 2);
            $stockItemId = (int)$item['stock_item_id'];
            $branchId = (int)$item['branch_id'];
            $remarks = 'Stock inward cancelled: ' . (string)$batch['batch_no'];
            $createdBy = si_user_id();

            $stmt = mysqli_prepare($conn, "
                INSERT INTO stock_movements
                (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, entry_date, qty_in, qty_out, balance_qty, remarks, created_by)
                VALUES (?, ?, ?, 'adjustment', 'stock_inward_cancelled', ?, ?, 0, ?, 0, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, 'iiiisdsii', $businessId, $branchId, $stockItemId, $batchId, $batch['inward_date'], $qty, $remarks, $createdBy);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($conn, "UPDATE stock_inward_batches SET batch_status = 'cancelled', updated_at = NOW() WHERE business_id = ? AND batch_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $batchId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "UPDATE stock_inward_items SET item_status = 'cancelled', available_qty = 0, updated_at = NOW() WHERE business_id = ? AND batch_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $batchId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "UPDATE stock_barcodes SET barcode_status = 'cancelled' WHERE business_id = ? AND batch_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $batchId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($supplierId > 0 && $purchaseTotal > 0) {
            si_post_supplier_ledger(
                $conn,
                $businessId,
                (int)$batch['branch_id'],
                $supplierId,
                'stock_inward_cancelled',
                $batchId,
                $purchaseTotal,
                'decrease',
                'Stock inward purchase reversed: ' . (string)$batch['batch_no']
            );
        }

        if ($supplierId > 0) {
            si_refresh_supplier_balance($conn, $businessId, $supplierId);
        }

        if (function_exists('log_activity')) {
            try {
                log_activity($conn, 'Stock Inward', 'cancel', $batchId, null, [
                    'batch_no' => $batch['batch_no'],
                    'supplier_id' => $supplierId,
                    'purchase_total_value' => $purchaseTotal,
                ]);
            } catch (Throwable $ignored) {}
        }

        mysqli_commit($conn);

        return [
            'batch_id' => $batchId,
            'supplier_id' => $supplierId,
            'purchase_total_value' => $purchaseTotal,
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

function si_masters(mysqli $conn, int $businessId, array $allowedBranchIds): array
{
    $branchRows = [];
    if ($allowedBranchIds) {
        $branchSql = implode(',', array_map('intval', $allowedBranchIds));
        $branchRows = si_rows($conn, "
            SELECT branch_id, branch_code, branch_name, floor_name
            FROM branches
            WHERE business_id = ?
              AND status = 1
              AND branch_id IN ($branchSql)
            ORDER BY branch_name, floor_name
        ", 'i', [$businessId]);
    }

    return [
        'branches' => $branchRows,
        'suppliers' => si_rows($conn, "
            SELECT supplier_id, supplier_name, mobile, gstin, current_outstanding
            FROM suppliers
            WHERE business_id = ?
              AND status = 1
            ORDER BY supplier_name
        ", 'i', [$businessId]),
        'categories' => si_rows($conn, "
            SELECT category_id, category_name
            FROM categories
            WHERE business_id = ?
              AND status = 1
            ORDER BY category_name
        ", 'i', [$businessId]),
        'brands' => si_rows($conn, "
            SELECT brand_id, brand_name
            FROM brands
            WHERE business_id = ?
              AND status = 1
            ORDER BY brand_name
        ", 'i', [$businessId]),
        'discount_types' => [
            ['value' => 'none', 'label' => 'None'],
            ['value' => 'percent', 'label' => 'Percent'],
            ['value' => 'amount', 'label' => 'Amount'],
        ],
    ];
}

function si_list_batches(mysqli $conn, int $businessId, array $allowedBranchIds, array $filters): array
{
    if (!$allowedBranchIds) {
        return ['batches' => [], 'stats' => []];
    }

    $types = 'i';
    $params = [$businessId];
    $branchSql = implode(',', array_map('intval', $allowedBranchIds));
    $where = "sib.business_id = ? AND sib.branch_id IN ($branchSql)";

    if (!empty($filters['branch_id'])) {
        $where .= " AND sib.branch_id = ?";
        $types .= 'i';
        $params[] = (int)$filters['branch_id'];
    }

    if (!empty($filters['supplier_id'])) {
        $where .= " AND sib.supplier_id = ?";
        $types .= 'i';
        $params[] = (int)$filters['supplier_id'];
    }

    if (!empty($filters['status'])) {
        $where .= " AND sib.batch_status = ?";
        $types .= 's';
        $params[] = (string)$filters['status'];
    }

    if (!empty($filters['date_from'])) {
        $where .= " AND sib.inward_date >= ?";
        $types .= 's';
        $params[] = (string)$filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $where .= " AND sib.inward_date <= ?";
        $types .= 's';
        $params[] = (string)$filters['date_to'];
    }

    if (!empty($filters['brand_id'])) {
        $where .= " AND EXISTS (SELECT 1 FROM stock_inward_items i WHERE i.business_id = sib.business_id AND i.batch_id = sib.batch_id AND i.brand_id = ?)";
        $types .= 'i';
        $params[] = (int)$filters['brand_id'];
    }

    if (!empty($filters['category_id'])) {
        $where .= " AND EXISTS (SELECT 1 FROM stock_inward_items i WHERE i.business_id = sib.business_id AND i.batch_id = sib.batch_id AND i.category_id = ?)";
        $types .= 'i';
        $params[] = (int)$filters['category_id'];
    }

    if (!empty($filters['search'])) {
        $term = '%' . trim((string)$filters['search']) . '%';
        $where .= " AND (
            sib.batch_no LIKE ?
            OR sib.invoice_number LIKE ?
            OR s.supplier_name LIKE ?
            OR s.mobile LIKE ?
            OR EXISTS (
                SELECT 1 FROM stock_inward_items si
                WHERE si.batch_id = sib.batch_id
                  AND si.business_id = sib.business_id
                  AND (si.article_no LIKE ? OR si.article_name LIKE ? OR si.size LIKE ? OR si.color LIKE ?)
            )
        )";
        $types .= 'ssssssss';
        array_push($params, $term, $term, $term, $term, $term, $term, $term, $term);
    }

    $nameExpr = si_column_exists($conn, 'users', 'full_name') ? "COALESCE(NULLIF(u.full_name,''), NULLIF(u.name,''), NULLIF(u.username,''), 'System')" : "COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), 'System')";

    $rows = si_rows($conn, "
        SELECT
            sib.*,
            b.branch_name,
            b.floor_name,
            s.supplier_name,
            s.mobile AS supplier_mobile,
            $nameExpr AS created_by_name,
            COALESCE(COUNT(i.stock_item_id), 0) AS item_count
        FROM stock_inward_batches sib
        INNER JOIN branches b
            ON b.branch_id = sib.branch_id
           AND b.business_id = sib.business_id
        LEFT JOIN suppliers s
            ON s.supplier_id = sib.supplier_id
           AND s.business_id = sib.business_id
        LEFT JOIN users u
            ON u.user_id = sib.created_by
           AND u.business_id = sib.business_id
        LEFT JOIN stock_inward_items i
            ON i.batch_id = sib.batch_id
           AND i.business_id = sib.business_id
        WHERE $where
        GROUP BY sib.batch_id
        ORDER BY sib.created_at DESC, sib.batch_id DESC
        LIMIT 500
    ", $types, $params);

    $stats = [
        'total_batches' => count($rows),
        'total_items' => 0,
        'total_qty' => 0.0,
        'purchase_total' => 0.0,
        'selling_total' => 0.0,
    ];

    foreach ($rows as $row) {
        $stats['total_items'] += (int)($row['item_count'] ?? 0);
        $stats['total_qty'] += (float)($row['total_qty'] ?? 0);
        $stats['purchase_total'] += (float)($row['purchase_total_value'] ?? 0);
        $stats['selling_total'] += (float)($row['selling_total_value'] ?? 0);
    }

    $stats['total_qty'] = round($stats['total_qty'], 2);
    $stats['purchase_total'] = round($stats['purchase_total'], 2);
    $stats['selling_total'] = round($stats['selling_total'], 2);

    return ['batches' => $rows, 'stats' => $stats];
}

function si_get_batch(mysqli $conn, int $businessId, int $batchId, array $allowedBranchIds): array
{
    if (!$allowedBranchIds) {
        throw new RuntimeException('No branch access.');
    }

    $branchSql = implode(',', array_map('intval', $allowedBranchIds));
    $nameExpr = si_column_exists($conn, 'users', 'full_name') ? "COALESCE(NULLIF(u.full_name,''), NULLIF(u.name,''), NULLIF(u.username,''), 'System')" : "COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), 'System')";

    $batch = si_one($conn, "
        SELECT
            sib.*,
            b.branch_name,
            b.floor_name,
            s.supplier_name,
            s.mobile AS supplier_mobile,
            s.gstin AS supplier_gstin,
            $nameExpr AS created_by_name
        FROM stock_inward_batches sib
        INNER JOIN branches b
            ON b.branch_id = sib.branch_id
           AND b.business_id = sib.business_id
        LEFT JOIN suppliers s
            ON s.supplier_id = sib.supplier_id
           AND s.business_id = sib.business_id
        LEFT JOIN users u
            ON u.user_id = sib.created_by
           AND u.business_id = sib.business_id
        WHERE sib.business_id = ?
          AND sib.batch_id = ?
          AND sib.branch_id IN ($branchSql)
        LIMIT 1
    ", 'ii', [$businessId, $batchId]);

    if (!$batch) {
        throw new RuntimeException('Stock inward batch not found.');
    }

    $items = si_rows($conn, "
        SELECT
            sii.*,
            c.category_name,
            br.brand_name,
            MIN(CASE WHEN sb.barcode_status = 'active' THEN sb.barcode_value ELSE NULL END) AS barcode_value,
            COALESCE(SUM(CASE WHEN sb.barcode_status = 'active' THEN 1 ELSE 0 END), 0) AS active_barcode_count
        FROM stock_inward_items sii
        LEFT JOIN categories c
            ON c.category_id = sii.category_id
           AND c.business_id = sii.business_id
        LEFT JOIN brands br
            ON br.brand_id = sii.brand_id
           AND br.business_id = sii.business_id
        LEFT JOIN stock_barcodes sb
            ON sb.stock_item_id = sii.stock_item_id
           AND sb.business_id = sii.business_id
           AND sb.barcode_status <> 'deleted'
        WHERE sii.business_id = ?
          AND sii.batch_id = ?
        GROUP BY sii.stock_item_id
        ORDER BY sii.stock_item_id ASC
    ", 'ii', [$businessId, $batchId]);

    $batch['items'] = $items;
    return $batch;
}

try {
    $businessId = si_business_id();
    $userId = si_user_id();

    if ($businessId <= 0) {
        si_json(['success' => false, 'message' => 'Business session missing. Please login again.'], 401);
    }

    $permissions = si_permissions($conn);
    $allowedBranchIds = si_allowed_branch_ids($conn, $businessId, $userId);
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $action = (string)($_GET['action'] ?? 'list');

        if ($action === 'masters') {
            si_json(['success' => true, 'masters' => si_masters($conn, $businessId, $allowedBranchIds)]);
        }

        if ($action === 'list') {
            $filters = [
                'search' => trim((string)($_GET['search'] ?? '')),
                'branch_id' => (int)($_GET['branch_id'] ?? 0),
                'supplier_id' => (int)($_GET['supplier_id'] ?? 0),
                'brand_id' => (int)($_GET['brand_id'] ?? 0),
                'category_id' => (int)($_GET['category_id'] ?? 0),
                'status' => trim((string)($_GET['status'] ?? 'active')),
                'date_from' => trim((string)($_GET['date_from'] ?? '')),
                'date_to' => trim((string)($_GET['date_to'] ?? '')),
            ];

            si_json(['success' => true] + si_list_batches($conn, $businessId, $allowedBranchIds, $filters));
        }

        if ($action === 'get') {
            $batchId = (int)($_GET['batch_id'] ?? 0);
            si_json(['success' => true, 'batch' => si_get_batch($conn, $businessId, $batchId, $allowedBranchIds)]);
        }

        if ($action === 'get_stock_item') {
            $stockItemId = (int)($_GET['stock_item_id'] ?? 0);
            si_json([
                'success' => true,
                'item' => si_get_stock_item_for_edit(
                    $conn,
                    $businessId,
                    $stockItemId,
                    $allowedBranchIds
                )
            ]);
        }

        si_json(['success' => false, 'message' => 'Invalid stock inward action.'], 400);
    }

    if ($method === 'POST') {
        si_verify_csrf_token();
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_stock_inward') {
            $result = si_save_stock_inward($conn, $businessId, $allowedBranchIds, $permissions);
            si_json([
                'success' => true,
                'message' => 'Stock inward saved successfully. Purchase amount added to supplier account.',
                'data' => $result,
            ]);
        }

        if ($action === 'cancel_stock_inward') {
            $result = si_cancel_stock_inward($conn, $businessId, $allowedBranchIds, $permissions);
            si_json([
                'success' => true,
                'message' => 'Stock inward cancelled and supplier balance reversed successfully.',
                'data' => $result,
            ]);
        }

        if ($action === 'update_stock_item') {
            $result = si_update_stock_item(
                $conn,
                $businessId,
                $allowedBranchIds,
                $permissions
            );

            si_json([
                'success' => true,
                'message' => 'Stock item updated successfully. Existing barcode was preserved.',
                'data' => $result,
            ]);
        }

        si_json(['success' => false, 'message' => 'Invalid stock inward POST action.'], 400);
    }

    si_json(['success' => false, 'message' => 'Invalid request method.'], 405);
} catch (Throwable $e) {
    si_json(['success' => false, 'message' => 'Stock Inward API error: ' . $e->getMessage()], 500);
}
?>
