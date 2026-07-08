<?php
/**
 * Universal Footwear POS - Return & Exchange API
 * Place at: business/api/return-exchange-api.php
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
    require_page_access($conn, 'return-exchange.php');
}

function re_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function re_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int) current_business_id();
    }

    return (int)($_SESSION['business_id'] ?? 0);
}

function re_user_id(): int
{
    if (function_exists('current_user_id')) {
        return (int) current_user_id();
    }

    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function re_table_exists(mysqli $conn, string $table): bool
{
    if (function_exists('table_exists')) {
        return (bool) table_exists($conn, $table);
    }

    $table = mysqli_real_escape_string($conn, $table);
    $res = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    return $res && mysqli_num_rows($res) > 0;
}

function re_column_exists(mysqli $conn, string $table, string $column): bool
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

function re_bind(mysqli_stmt $stmt, string $types, array $params): void
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

function re_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        throw new RuntimeException('SQL prepare failed: ' . mysqli_error($conn));
    }

    re_bind($stmt, $types, $params);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('SQL execute failed: ' . $err);
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

function re_one(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $rows = re_rows($conn, $sql, $types, $params);
    return $rows[0] ?? [];
}

function re_verify_csrf(): void
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

    re_json(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.'], 403);
}

function re_sequence(mysqli $conn, int $businessId, string $key, string $prefix, int $padding = 4): string
{
    $stmt = mysqli_prepare($conn, "
        SELECT sequence_id, current_number, prefix, padding_length
        FROM number_sequences
        WHERE business_id = ?
          AND branch_id IS NULL
          AND sequence_key = ?
        LIMIT 1
        FOR UPDATE
    ");
    mysqli_stmt_bind_param($stmt, 'is', $businessId, $key);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$row) {
        $zero = 0;
        $active = 1;
        $stmt = mysqli_prepare($conn, "
            INSERT INTO number_sequences
            (business_id, branch_id, sequence_key, prefix, current_number, padding_length, status)
            VALUES (?, NULL, ?, ?, ?, ?, ?)
        ");
        mysqli_stmt_bind_param($stmt, 'issiii', $businessId, $key, $prefix, $zero, $padding, $active);
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
          AND branch_id IS NULL
          AND sequence_key = ?
    ");
    mysqli_stmt_bind_param($stmt, 'iis', $next, $businessId, $key);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $seqPrefix . '-' . str_pad((string)$next, $seqPadding, '0', STR_PAD_LEFT);
}

function re_user_name_expr(): string
{
    return "COALESCE(NULLIF(u.full_name,''), NULLIF(u.name,''), NULLIF(u.username,''), 'System')";
}

function re_customer_balance(mysqli $conn, int $businessId, int $customerId): float
{
    $customer = re_one($conn, "SELECT opening_outstanding FROM customers WHERE business_id = ? AND customer_id = ? LIMIT 1", 'ii', [$businessId, $customerId]);
    $balance = round((float)($customer['opening_outstanding'] ?? 0), 2);

    $rows = re_rows($conn, "
        SELECT debit, credit
        FROM customer_ledger
        WHERE business_id = ?
          AND customer_id = ?
          AND COALESCE(reference_type, '') <> 'opening'
        ORDER BY created_at ASC, customer_ledger_id ASC
    ", 'ii', [$businessId, $customerId]);

    foreach ($rows as $row) {
        $balance = round($balance + (float)$row['debit'] - (float)$row['credit'], 2);
    }

    return $balance;
}

function re_refresh_customer_balance(mysqli $conn, int $businessId, int $customerId): void
{
    if ($customerId <= 0) {
        return;
    }

    $totals = re_one($conn, "
        SELECT
            COALESCE(SUM(CASE WHEN COALESCE(reference_type,'') <> 'opening' THEN debit ELSE 0 END),0) AS total_debit,
            COALESCE(SUM(CASE WHEN COALESCE(reference_type,'') <> 'opening' THEN credit ELSE 0 END),0) AS total_credit
        FROM customer_ledger
        WHERE business_id = ?
          AND customer_id = ?
    ", 'ii', [$businessId, $customerId]);

    $opening = re_one($conn, "SELECT opening_outstanding FROM customers WHERE business_id = ? AND customer_id = ? LIMIT 1", 'ii', [$businessId, $customerId]);
    $balance = round((float)($opening['opening_outstanding'] ?? 0) + (float)$totals['total_debit'] - (float)$totals['total_credit'], 2);

    if (re_table_exists($conn, 'customer_outstanding')) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO customer_outstanding
            (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                total_bill_amount = VALUES(total_bill_amount),
                total_paid_amount = VALUES(total_paid_amount),
                balance_amount = VALUES(balance_amount),
                updated_at = NOW()
        ");
        mysqli_stmt_bind_param($stmt, 'iiddd', $businessId, $customerId, $totals['total_debit'], $totals['total_credit'], $balance);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function re_add_customer_ledger(mysqli $conn, int $businessId, ?int $branchId, int $customerId, string $referenceType, int $referenceId, float $debit, float $credit, string $remarks): void
{
    if ($customerId <= 0 || ($debit <= 0 && $credit <= 0)) {
        return;
    }

    $oldBalance = re_customer_balance($conn, $businessId, $customerId);
    $newBalance = round($oldBalance + $debit - $credit, 2);
    $createdBy = re_user_id();

    $stmt = mysqli_prepare($conn, "
        INSERT INTO customer_ledger
        (business_id, branch_id, customer_id, reference_type, reference_id, debit, credit, balance, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    mysqli_stmt_bind_param($stmt, 'iiisidddsi', $businessId, $branchId, $customerId, $referenceType, $referenceId, $debit, $credit, $newBalance, $remarks, $createdBy);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    re_refresh_customer_balance($conn, $businessId, $customerId);
}

function re_search_bill(mysqli $conn, int $businessId, string $search): array
{
    $term = trim($search);

    if ($term === '') {
        throw new RuntimeException('Please scan bill barcode or enter bill number.');
    }

    $nameExpr = re_user_name_expr();
    $hasReturnStatus = re_column_exists($conn, 'bills', 'return_status');
    $returnStatusSelect = $hasReturnStatus ? "b.return_status, b.returned_amount, b.last_return_exchange_id," : "'no_return' AS return_status, 0 AS returned_amount, NULL AS last_return_exchange_id,";

    $bill = re_one($conn, "
        SELECT
            b.*,
            $returnStatusSelect
            bb.barcode_value,
            br.branch_name,
            br.floor_name,
            $nameExpr AS created_by_name
        FROM bills b
        LEFT JOIN bill_barcodes bb
            ON bb.business_id = b.business_id
           AND bb.bill_id = b.bill_id
           AND bb.barcode_status <> 'deleted'
        LEFT JOIN branches br
            ON br.business_id = b.business_id
           AND br.branch_id = b.branch_id
        LEFT JOIN users u
            ON u.business_id = b.business_id
           AND u.user_id = b.created_by
        WHERE b.business_id = ?
          AND (b.bill_no = ? OR b.order_no = ? OR bb.barcode_value = ?)
        ORDER BY b.bill_id DESC
        LIMIT 1
    ", 'isss', [$businessId, $term, $term, $term]);

    if (!$bill) {
        throw new RuntimeException('Bill not found for the entered barcode/bill number.');
    }

    if ((string)($bill['bill_status'] ?? '') !== 'active') {
        throw new RuntimeException('Only active bills are allowed for return/exchange.');
    }

    $items = re_rows($conn, "
        SELECT
            bi.*,
            b.brand_name,
            sii.color,
            COALESCE(ret.returned_qty, 0) AS returned_qty,
            COALESCE(ret.exchange_qty, 0) AS exchange_qty,
            (bi.qty - COALESCE(ret.returned_qty, 0)) AS returnable_qty
        FROM bill_items bi
        LEFT JOIN brands b
            ON b.business_id = bi.business_id
           AND b.brand_id = bi.brand_id
        LEFT JOIN stock_inward_items sii
            ON sii.business_id = bi.business_id
           AND sii.stock_item_id = bi.stock_item_id
        LEFT JOIN (
            SELECT
                bill_item_id,
                SUM(return_qty) AS returned_qty,
                SUM(CASE WHEN item_type = 'exchange' THEN return_qty ELSE 0 END) AS exchange_qty
            FROM return_exchange_items rei
            INNER JOIN return_exchange_headers reh
                ON reh.return_exchange_id = rei.return_exchange_id
               AND reh.business_id = rei.business_id
               AND reh.status = 'completed'
            WHERE rei.business_id = ?
              AND rei.bill_id = ?
            GROUP BY bill_item_id
        ) ret
            ON ret.bill_item_id = bi.bill_item_id
        WHERE bi.business_id = ?
          AND bi.bill_id = ?
        ORDER BY bi.bill_item_id ASC
    ", 'iiii', [$businessId, (int)$bill['bill_id'], $businessId, (int)$bill['bill_id']]);

    $history = re_history($conn, $businessId, (int)$bill['bill_id']);

    return ['bill' => $bill, 'items' => $items, 'history' => $history];
}

function re_search_products(mysqli $conn, int $businessId, string $search): array
{
    $term = '%' . trim($search) . '%';

    if (trim($search) === '') {
        return [];
    }

    return re_rows($conn, "
        SELECT
            sii.stock_item_id,
            sii.business_id,
            sii.branch_id,
            sii.batch_id,
            sii.article_no,
            sii.article_name,
            sii.brand_id,
            b.brand_name,
            sii.size,
            sii.color,
            sii.available_qty,
            sii.selling_rate,
            sii.mrp_rate,
            sb.barcode_id,
            sb.barcode_value,
            br.branch_name,
            br.floor_name
        FROM stock_inward_items sii
        LEFT JOIN stock_barcodes sb
            ON sb.business_id = sii.business_id
           AND sb.stock_item_id = sii.stock_item_id
           AND sb.barcode_status = 'active'
        LEFT JOIN brands b
            ON b.business_id = sii.business_id
           AND b.brand_id = sii.brand_id
        LEFT JOIN branches br
            ON br.business_id = sii.business_id
           AND br.branch_id = sii.branch_id
        WHERE sii.business_id = ?
          AND sii.item_status = 'active'
          AND sii.available_qty > 0
          AND (
            sii.article_no LIKE ?
            OR sii.article_name LIKE ?
            OR sii.size LIKE ?
            OR sii.color LIKE ?
            OR b.brand_name LIKE ?
            OR sb.barcode_value LIKE ?
          )
        GROUP BY sii.stock_item_id
        ORDER BY sii.article_no ASC, sii.size ASC
        LIMIT 20
    ", 'issssss', [$businessId, $term, $term, $term, $term, $term, $term]);
}

function re_history(mysqli $conn, int $businessId, int $billId): array
{
    $nameExpr = re_user_name_expr();

    return re_rows($conn, "
        SELECT
            reh.*,
            $nameExpr AS created_by_name
        FROM return_exchange_headers reh
        LEFT JOIN users u
            ON u.business_id = reh.business_id
           AND u.user_id = reh.created_by
        WHERE reh.business_id = ?
          AND reh.bill_id = ?
        ORDER BY reh.return_exchange_id DESC
    ", 'ii', [$businessId, $billId]);
}

function re_bill_item(mysqli $conn, int $businessId, int $billId, int $billItemId): array
{
    $item = re_one($conn, "
        SELECT
            bi.*,
            sii.color,
            sii.available_qty AS current_stock_qty,
            COALESCE(ret.returned_qty, 0) AS returned_qty
        FROM bill_items bi
        LEFT JOIN stock_inward_items sii
            ON sii.business_id = bi.business_id
           AND sii.stock_item_id = bi.stock_item_id
        LEFT JOIN (
            SELECT rei.bill_item_id, SUM(rei.return_qty) AS returned_qty
            FROM return_exchange_items rei
            INNER JOIN return_exchange_headers reh
                ON reh.business_id = rei.business_id
               AND reh.return_exchange_id = rei.return_exchange_id
               AND reh.status = 'completed'
            WHERE rei.business_id = ?
              AND rei.bill_id = ?
            GROUP BY rei.bill_item_id
        ) ret ON ret.bill_item_id = bi.bill_item_id
        WHERE bi.business_id = ?
          AND bi.bill_id = ?
          AND bi.bill_item_id = ?
        LIMIT 1
    ", 'iiiii', [$businessId, $billId, $businessId, $billId, $billItemId]);

    if (!$item) {
        throw new RuntimeException('Bill item not found.');
    }

    return $item;
}

function re_stock_item(mysqli $conn, int $businessId, int $stockItemId, int $barcodeId = 0): array
{
    $whereBarcode = '';
    $types = 'ii';
    $params = [$businessId, $stockItemId];

    if ($barcodeId > 0) {
        $whereBarcode = ' AND sb.barcode_id = ?';
        $types .= 'i';
        $params[] = $barcodeId;
    }

    $item = re_one($conn, "
        SELECT
            sii.*,
            b.brand_name,
            sb.barcode_id,
            sb.barcode_value
        FROM stock_inward_items sii
        LEFT JOIN brands b
            ON b.business_id = sii.business_id
           AND b.brand_id = sii.brand_id
        LEFT JOIN stock_barcodes sb
            ON sb.business_id = sii.business_id
           AND sb.stock_item_id = sii.stock_item_id
           AND sb.barcode_status = 'active'
           $whereBarcode
        WHERE sii.business_id = ?
          AND sii.stock_item_id = ?
          AND sii.item_status = 'active'
        ORDER BY sb.barcode_id ASC
        LIMIT 1
    ", $types, $params);

    if (!$item) {
        throw new RuntimeException('Selected exchange product not found or no active barcode.');
    }

    if ((float)$item['available_qty'] <= 0) {
        throw new RuntimeException('Selected exchange product has no available stock.');
    }

    return $item;
}

function re_returnable_qty(float $soldQty, float $returnedQty): float
{
    return max(0, round($soldQty - $returnedQty, 2));
}

function re_line_rate(array $billItem): float
{
    $qty = max(1, (float)($billItem['qty'] ?? 1));
    return round((float)($billItem['amount'] ?? 0) / $qty, 2);
}

function re_insert_header(mysqli $conn, int $businessId, array $bill, string $type, string $refundOption, string $collectOption, string $notes): int
{
    $transactionNo = re_sequence($conn, $businessId, $type === 'return' ? 'return_no' : 'exchange_no', $type === 'return' ? 'RET' : 'EXC', 4);
    $createdBy = re_user_id();

    $stmt = mysqli_prepare($conn, "
        INSERT INTO return_exchange_headers
        (business_id, branch_id, transaction_no, bill_id, bill_no, customer_id, customer_name, customer_mobile,
         transaction_type, refund_option, collect_option, notes, status, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
    ");
    $customerId = (int)($bill['customer_id'] ?? 0);
    $customerName = (string)($bill['customer_name'] ?? 'Walk-in Customer');
    $customerMobile = (string)($bill['customer_mobile'] ?? '');
    mysqli_stmt_bind_param(
        $stmt,
        'iisisissssssi',
        $businessId,
        $bill['branch_id'],
        $transactionNo,
        $bill['bill_id'],
        $bill['bill_no'],
        $customerId,
        $customerName,
        $customerMobile,
        $type,
        $refundOption,
        $collectOption,
        $notes,
        $createdBy
    );
    mysqli_stmt_execute($stmt);
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    return $id;
}

function re_update_header_totals(mysqli $conn, int $businessId, int $id, float $returnAmount, float $newAmount, float $refund, float $extra, float $storeCredit): void
{
    $net = round($newAmount - $returnAmount, 2);

    $stmt = mysqli_prepare($conn, "
        UPDATE return_exchange_headers
        SET return_amount = ?,
            new_amount = ?,
            refund_amount = ?,
            extra_collect_amount = ?,
            store_credit_amount = ?,
            net_difference = ?
        WHERE business_id = ?
          AND return_exchange_id = ?
    ");
    mysqli_stmt_bind_param($stmt, 'ddddddii', $returnAmount, $newAmount, $refund, $extra, $storeCredit, $net, $businessId, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function re_stock_add(mysqli $conn, int $businessId, int $branchId, int $stockItemId, ?int $barcodeId, float $qty, int $referenceId, string $remarks): void
{
    if ($stockItemId <= 0 || $qty <= 0) {
        return;
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE stock_inward_items
        SET available_qty = available_qty + ?,
            item_status = 'active',
            updated_at = NOW()
        WHERE business_id = ?
          AND stock_item_id = ?
    ");
    mysqli_stmt_bind_param($stmt, 'dii', $qty, $businessId, $stockItemId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($barcodeId) {
        $stmt = mysqli_prepare($conn, "
            UPDATE stock_barcodes
            SET barcode_status = 'active'
            WHERE business_id = ?
              AND barcode_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $barcodeId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $balance = re_one($conn, "SELECT available_qty FROM stock_inward_items WHERE business_id = ? AND stock_item_id = ? LIMIT 1", 'ii', [$businessId, $stockItemId]);
    $balanceQty = (float)($balance['available_qty'] ?? 0);
    $createdBy = re_user_id();

    $stmt = mysqli_prepare($conn, "
        INSERT INTO stock_movements
        (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, entry_date, qty_in, qty_out, balance_qty, remarks, created_by)
        VALUES (?, ?, ?, 'return', 'return_exchange', ?, CURDATE(), ?, 0, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, 'iiiiddsi', $businessId, $branchId, $stockItemId, $referenceId, $qty, $balanceQty, $remarks, $createdBy);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function re_stock_deduct(mysqli $conn, int $businessId, int $branchId, int $stockItemId, ?int $barcodeId, float $qty, int $referenceId, string $remarks): void
{
    if ($stockItemId <= 0 || $qty <= 0) {
        return;
    }

    $row = re_one($conn, "SELECT available_qty FROM stock_inward_items WHERE business_id = ? AND stock_item_id = ? FOR UPDATE", 'ii', [$businessId, $stockItemId]);
    $available = (float)($row['available_qty'] ?? 0);

    if ($available < $qty) {
        throw new RuntimeException('Exchange product stock not available.');
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE stock_inward_items
        SET available_qty = available_qty - ?,
            item_status = CASE WHEN available_qty - ? <= 0 THEN 'out_of_stock' ELSE item_status END,
            updated_at = NOW()
        WHERE business_id = ?
          AND stock_item_id = ?
    ");
    mysqli_stmt_bind_param($stmt, 'ddii', $qty, $qty, $businessId, $stockItemId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($barcodeId) {
        $stmt = mysqli_prepare($conn, "
            UPDATE stock_barcodes
            SET barcode_status = 'used'
            WHERE business_id = ?
              AND barcode_id = ?
              AND barcode_status = 'active'
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $barcodeId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $balanceQty = round($available - $qty, 2);
    $createdBy = re_user_id();

    $stmt = mysqli_prepare($conn, "
        INSERT INTO stock_movements
        (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, entry_date, qty_in, qty_out, balance_qty, remarks, created_by)
        VALUES (?, ?, ?, 'sale', 'exchange', ?, CURDATE(), 0, ?, ?, ?, ?)
    ");
    mysqli_stmt_bind_param($stmt, 'iiiiddsi', $businessId, $branchId, $stockItemId, $referenceId, $qty, $balanceQty, $remarks, $createdBy);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function re_update_bill_return_status(mysqli $conn, int $businessId, int $billId, int $returnExchangeId): void
{
    $totals = re_one($conn, "
        SELECT
            COALESCE(SUM(bi.qty), 0) AS sold_qty,
            COALESCE(SUM(COALESCE(ret.returned_qty, 0)), 0) AS returned_qty
        FROM bill_items bi
        LEFT JOIN (
            SELECT rei.bill_item_id, SUM(rei.return_qty) AS returned_qty
            FROM return_exchange_items rei
            INNER JOIN return_exchange_headers reh
                ON reh.business_id = rei.business_id
               AND reh.return_exchange_id = rei.return_exchange_id
               AND reh.status = 'completed'
            WHERE rei.business_id = ?
              AND rei.bill_id = ?
            GROUP BY rei.bill_item_id
        ) ret ON ret.bill_item_id = bi.bill_item_id
        WHERE bi.business_id = ?
          AND bi.bill_id = ?
    ", 'iiii', [$businessId, $billId, $businessId, $billId]);

    $sold = (float)($totals['sold_qty'] ?? 0);
    $returned = (float)($totals['returned_qty'] ?? 0);
    $status = 'no_return';

    if ($returned > 0 && $returned < $sold) {
        $status = 'partially_returned';
    } elseif ($sold > 0 && $returned >= $sold) {
        $status = 'fully_returned';
    }

    $amount = re_one($conn, "
        SELECT COALESCE(SUM(return_amount), 0) AS returned_amount
        FROM return_exchange_headers
        WHERE business_id = ?
          AND bill_id = ?
          AND status = 'completed'
    ", 'ii', [$businessId, $billId]);

    if (re_column_exists($conn, 'bills', 'return_status')) {
        $stmt = mysqli_prepare($conn, "
            UPDATE bills
            SET return_status = ?,
                returned_amount = ?,
                last_return_exchange_id = ?,
                updated_status = 'updated',
                updated_at = NOW()
            WHERE business_id = ?
              AND bill_id = ?
        ");
        $returnedAmount = (float)($amount['returned_amount'] ?? 0);
        mysqli_stmt_bind_param($stmt, 'sdiii', $status, $returnedAmount, $returnExchangeId, $businessId, $billId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

function re_create_return(mysqli $conn, int $businessId, int $billId, array $items, string $refundOption, string $notes): array
{
    $bill = re_one($conn, "
        SELECT *
        FROM bills
        WHERE business_id = ?
          AND bill_id = ?
          AND bill_status = 'active'
        LIMIT 1
    ", 'ii', [$businessId, $billId]);

    if (!$bill) {
        throw new RuntimeException('Active bill not found.');
    }

    if (!$items) {
        throw new RuntimeException('Select at least one product to return.');
    }

    mysqli_begin_transaction($conn);

    try {
        $headerId = re_insert_header($conn, $businessId, $bill, 'return', $refundOption, '', $notes);
        $totalReturn = 0.0;

        foreach ($items as $payload) {
            $billItemId = (int)($payload['bill_item_id'] ?? 0);
            $returnQty = round((float)($payload['return_qty'] ?? 0), 2);

            if ($billItemId <= 0 || $returnQty <= 0) {
                continue;
            }

            $billItem = re_bill_item($conn, $businessId, $billId, $billItemId);
            $maxQty = re_returnable_qty((float)$billItem['qty'], (float)$billItem['returned_qty']);

            if ($returnQty > $maxQty) {
                throw new RuntimeException('Return quantity is greater than returnable quantity for ' . $billItem['article_no']);
            }

            $rate = re_line_rate($billItem);
            $returnAmount = round($returnQty * $rate, 2);
            $totalReturn = round($totalReturn + $returnAmount, 2);

            $stmt = mysqli_prepare($conn, "
                INSERT INTO return_exchange_items
                (return_exchange_id, business_id, branch_id, bill_id, bill_item_id, item_type,
                 old_stock_item_id, old_barcode_id, old_article_no, old_article_name, old_brand_id, old_size, old_color,
                 sold_qty, return_qty, old_rate, return_amount, price_difference, created_at)
                VALUES (?, ?, ?, ?, ?, 'return', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            mysqli_stmt_bind_param(
                $stmt,
                'iiiiiiississdddd',
                $headerId,
                $businessId,
                $bill['branch_id'],
                $billId,
                $billItemId,
                $billItem['stock_item_id'],
                $billItem['barcode_id'],
                $billItem['article_no'],
                $billItem['article_name'],
                $billItem['brand_id'],
                $billItem['size'],
                $billItem['color'],
                $billItem['qty'],
                $returnQty,
                $rate,
                $returnAmount
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            re_stock_add(
                $conn,
                $businessId,
                (int)$bill['branch_id'],
                (int)$billItem['stock_item_id'],
                (int)($billItem['barcode_id'] ?? 0),
                $returnQty,
                $headerId,
                'Sales return for bill ' . $bill['bill_no']
            );
        }

        if ($totalReturn <= 0) {
            throw new RuntimeException('Return amount is zero.');
        }

        $storeCredit = $refundOption === 'store_credit' ? $totalReturn : 0.0;
        re_update_header_totals($conn, $businessId, $headerId, $totalReturn, 0, $totalReturn, 0, $storeCredit);
        re_update_bill_return_status($conn, $businessId, $billId, $headerId);

        if ((int)($bill['customer_id'] ?? 0) > 0) {
            re_add_customer_ledger(
                $conn,
                $businessId,
                (int)$bill['branch_id'],
                (int)$bill['customer_id'],
                'sales_return',
                $headerId,
                0,
                $totalReturn,
                'Sales return credit for ' . $bill['bill_no']
            );
        }

        mysqli_commit($conn);

        return ['return_exchange_id' => $headerId, 'refund_amount' => $totalReturn];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

function re_create_exchange(mysqli $conn, int $businessId, int $billId, array $items, string $refundOption, string $collectOption, string $notes): array
{
    $bill = re_one($conn, "SELECT * FROM bills WHERE business_id = ? AND bill_id = ? AND bill_status = 'active' LIMIT 1", 'ii', [$businessId, $billId]);

    if (!$bill) {
        throw new RuntimeException('Active bill not found.');
    }

    if (!$items) {
        throw new RuntimeException('Select at least one product to exchange.');
    }

    mysqli_begin_transaction($conn);

    try {
        $headerId = re_insert_header($conn, $businessId, $bill, 'exchange', $refundOption, $collectOption, $notes);
        $totalReturn = 0.0;
        $totalNew = 0.0;

        foreach ($items as $payload) {
            $billItemId = (int)($payload['bill_item_id'] ?? 0);
            $returnQty = round((float)($payload['return_qty'] ?? 0), 2);
            $newStockItemId = (int)($payload['new_stock_item_id'] ?? 0);
            $newBarcodeId = (int)($payload['new_barcode_id'] ?? 0);
            $newQty = round((float)($payload['new_qty'] ?? 0), 2);

            if ($billItemId <= 0 || $returnQty <= 0 || $newStockItemId <= 0 || $newQty <= 0) {
                continue;
            }

            $oldItem = re_bill_item($conn, $businessId, $billId, $billItemId);
            $oldMax = re_returnable_qty((float)$oldItem['qty'], (float)$oldItem['returned_qty']);

            if ($returnQty > $oldMax) {
                throw new RuntimeException('Exchange return quantity is greater than returnable quantity for ' . $oldItem['article_no']);
            }

            $newItem = re_stock_item($conn, $businessId, $newStockItemId, $newBarcodeId);

            if ((float)$newItem['available_qty'] < $newQty) {
                throw new RuntimeException('New product stock not available for ' . $newItem['article_no']);
            }

            $oldRate = re_line_rate($oldItem);
            $returnAmount = round($returnQty * $oldRate, 2);
            $newRate = round((float)$newItem['selling_rate'], 2);
            $newAmount = round($newQty * $newRate, 2);
            $diff = round($newAmount - $returnAmount, 2);

            $totalReturn = round($totalReturn + $returnAmount, 2);
            $totalNew = round($totalNew + $newAmount, 2);

            $stmt = mysqli_prepare($conn, "
                INSERT INTO return_exchange_items
                (return_exchange_id, business_id, branch_id, bill_id, bill_item_id, item_type,
                 old_stock_item_id, old_barcode_id, old_article_no, old_article_name, old_brand_id, old_size, old_color,
                 sold_qty, return_qty, old_rate, return_amount,
                 new_stock_item_id, new_barcode_id, new_article_no, new_article_name, new_brand_id, new_size, new_color,
                 new_qty, new_rate, new_amount, price_difference, created_at)
                VALUES (?, ?, ?, ?, ?, 'exchange', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            mysqli_stmt_bind_param(
                $stmt,
                'iiiiiiississddddiississdddd',
                $headerId,
                $businessId,
                $bill['branch_id'],
                $billId,
                $billItemId,
                $oldItem['stock_item_id'],
                $oldItem['barcode_id'],
                $oldItem['article_no'],
                $oldItem['article_name'],
                $oldItem['brand_id'],
                $oldItem['size'],
                $oldItem['color'],
                $oldItem['qty'],
                $returnQty,
                $oldRate,
                $returnAmount,
                $newItem['stock_item_id'],
                $newItem['barcode_id'],
                $newItem['article_no'],
                $newItem['article_name'],
                $newItem['brand_id'],
                $newItem['size'],
                $newItem['color'],
                $newQty,
                $newRate,
                $newAmount,
                $diff
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            re_stock_add(
                $conn,
                $businessId,
                (int)$bill['branch_id'],
                (int)$oldItem['stock_item_id'],
                (int)($oldItem['barcode_id'] ?? 0),
                $returnQty,
                $headerId,
                'Exchange return product for bill ' . $bill['bill_no']
            );

            re_stock_deduct(
                $conn,
                $businessId,
                (int)$newItem['branch_id'],
                (int)$newItem['stock_item_id'],
                (int)($newItem['barcode_id'] ?? 0),
                $newQty,
                $headerId,
                'Exchange new product issued for bill ' . $bill['bill_no']
            );
        }

        $net = round($totalNew - $totalReturn, 2);
        $refund = $net < 0 ? abs($net) : 0.0;
        $extra = $net > 0 ? $net : 0.0;
        $storeCredit = ($refundOption === 'store_credit' && $refund > 0) ? $refund : 0.0;

        re_update_header_totals($conn, $businessId, $headerId, $totalReturn, $totalNew, $refund, $extra, $storeCredit);
        re_update_bill_return_status($conn, $businessId, $billId, $headerId);

        if ((int)($bill['customer_id'] ?? 0) > 0) {
            if ($totalReturn > 0) {
                re_add_customer_ledger($conn, $businessId, (int)$bill['branch_id'], (int)$bill['customer_id'], 'exchange_return', $headerId, 0, $totalReturn, 'Exchange returned item credit for ' . $bill['bill_no']);
            }

            if ($totalNew > 0) {
                re_add_customer_ledger($conn, $businessId, (int)$bill['branch_id'], (int)$bill['customer_id'], 'exchange_new_sale', $headerId, $totalNew, 0, 'Exchange new item debit for ' . $bill['bill_no']);
            }

            if ($extra > 0 && $collectOption !== 'credit') {
                re_add_customer_ledger($conn, $businessId, (int)$bill['branch_id'], (int)$bill['customer_id'], 'exchange_collection', $headerId, 0, $extra, 'Exchange balance collected for ' . $bill['bill_no']);
            }
        }

        mysqli_commit($conn);

        return ['return_exchange_id' => $headerId, 'refund_amount' => $refund, 'extra_collect_amount' => $extra];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

try {
    $businessId = re_business_id();

    if ($businessId <= 0) {
        re_json(['success' => false, 'message' => 'Business session missing. Please login again.'], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = (string)($_GET['action'] ?? '');

        if ($action === 'search_bill') {
            $search = trim((string)($_GET['search'] ?? ''));
            $result = re_search_bill($conn, $businessId, $search);
            re_json(['success' => true] + $result);
        }

        if ($action === 'search_products') {
            $products = re_search_products($conn, $businessId, (string)($_GET['search'] ?? ''));
            re_json(['success' => true, 'products' => $products]);
        }

        if ($action === 'history') {
            $billId = (int)($_GET['bill_id'] ?? 0);
            re_json(['success' => true, 'history' => re_history($conn, $businessId, $billId)]);
        }

        re_json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        re_verify_csrf();

        $action = (string)($_POST['action'] ?? '');
        $billId = (int)($_POST['bill_id'] ?? 0);
        $items = json_decode((string)($_POST['items'] ?? '[]'), true);

        if (!is_array($items)) {
            $items = [];
        }

        if ($action === 'create_return') {
            $result = re_create_return(
                $conn,
                $businessId,
                $billId,
                $items,
                (string)($_POST['refund_option'] ?? 'cash_refund'),
                trim((string)($_POST['notes'] ?? ''))
            );

            re_json(['success' => true, 'message' => 'Return completed successfully.'] + $result);
        }

        if ($action === 'create_exchange') {
            $result = re_create_exchange(
                $conn,
                $businessId,
                $billId,
                $items,
                (string)($_POST['refund_option'] ?? 'cash_refund'),
                (string)($_POST['collect_option'] ?? 'cash'),
                trim((string)($_POST['notes'] ?? ''))
            );

            re_json(['success' => true, 'message' => 'Exchange completed successfully.'] + $result);
        }

        re_json(['success' => false, 'message' => 'Invalid action.'], 400);
    }

    re_json(['success' => false, 'message' => 'Invalid request method.'], 405);
} catch (Throwable $e) {
    re_json(['success' => false, 'message' => $e->getMessage()], 500);
}
?>
