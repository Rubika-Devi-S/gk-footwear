<?php
/**
 * GK Footwear POS Billing API
 * Fully mapped to the provided footwear schema:
 * bills, bill_items, bill_payments, bill_barcodes, pos_bill_holds,
 * stock_inward_items, stock_barcodes, stock_movements, customers,
 * customer_outstanding, customer_ledger, payment_ledger, cashier_collections,
 * invoice_settings, branch_settings, barcode_settings, number_sequences,
 * branches, businesses, payment_methods, offer_codes, activity logs.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

if (function_exists('require_business_login')) {
    require_business_login();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json; charset=utf-8');

function pos_api_json(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pos_api_business_id(): int
{
    if (function_exists('current_business_id')) {
        return (int) current_business_id();
    }
    return (int)($_SESSION['business_id'] ?? 0);
}

function pos_api_branch_id(): int
{
    if (function_exists('current_branch_id')) {
        return (int) current_branch_id();
    }
    return (int)($_SESSION['branch_id'] ?? $_SESSION['default_branch_id'] ?? 0);
}

function pos_api_user_id(): ?int
{
    if (function_exists('current_user_id')) {
        $id = (int) current_user_id();
        return $id > 0 ? $id : null;
    }
    $id = (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? 0);
    return $id > 0 ? $id : null;
}

function pos_api_role_id(): ?int
{
    if (function_exists('current_role_id')) {
        $id = (int) current_role_id();
        return $id > 0 ? $id : null;
    }
    $id = (int)($_SESSION['role_id'] ?? 0);
    return $id > 0 ? $id : null;
}

function pos_api_bind(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '') {
        return;
    }
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    mysqli_stmt_bind_param($stmt, $types, ...$refs);
}

function pos_api_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = mysqli_prepare($conn, $sql);
    pos_api_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function pos_api_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);
    pos_api_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function pos_api_exec(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = mysqli_prepare($conn, $sql);
    pos_api_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $id = (int) mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function pos_api_is_duplicate_key(Throwable $e): bool
{
    return (int)$e->getCode() === 1062 || stripos($e->getMessage(), 'Duplicate entry') !== false;
}

function pos_api_input_payload(): array
{
    $payload = [];
    $raw = file_get_contents('php://input');
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false && $raw) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    if (isset($_POST['payload'])) {
        $decoded = json_decode((string)$_POST['payload'], true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    return $payload;
}

function pos_api_action(): string
{
    $payload = pos_api_input_payload();
    return (string)($_GET['action'] ?? $_POST['action'] ?? $payload['action'] ?? '');
}

function pos_api_num($value, float $default = 0.0): float
{
    if ($value === '' || $value === null) {
        return $default;
    }
    return round((float)$value, 2);
}

function pos_api_clean_string($value, int $limit = 255): string
{
    $value = trim((string)($value ?? ''));
    if ($limit > 0 && strlen($value) > $limit) {
        $value = substr($value, 0, $limit);
    }
    return $value;
}

function pos_api_mobile($value): string
{
    return substr(preg_replace('/[^0-9]/', '', (string)$value), 0, 10);
}

function pos_api_csrf_ok(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }

    $token = (string)($_POST['csrf_token'] ?? $_POST['_token'] ?? '');
    if ($token === '') {
        $payload = pos_api_input_payload();
        $token = (string)($payload['csrf_token'] ?? $payload['_token'] ?? '');
    }

    if (function_exists('csrf_token')) {
        return $token !== '' && hash_equals((string)csrf_token(), $token);
    }

    $sessionCandidates = [
        $_SESSION['csrf_token'] ?? null,
        $_SESSION['_token'] ?? null,
        $_SESSION['csrf'] ?? null,
    ];
    foreach ($sessionCandidates as $sessionToken) {
        if ($sessionToken && $token !== '' && hash_equals((string)$sessionToken, $token)) {
            return true;
        }
    }

    return true;
}

function pos_api_branch_allowed(mysqli $conn, int $businessId, int $branchId): bool
{
    if ($businessId <= 0 || $branchId <= 0) {
        return false;
    }
    $row = pos_api_one($conn, "SELECT branch_id FROM branches WHERE business_id = ? AND branch_id = ? AND status = 1 LIMIT 1", 'ii', [$businessId, $branchId]);
    return (bool)$row;
}

function pos_api_fetch_branches(mysqli $conn, int $businessId, ?int $userId): array
{
    $allBranches = pos_api_all($conn, "
        SELECT branch_id, branch_code, branch_name, floor_name, address, mobile
        FROM branches
        WHERE business_id = ? AND status = 1
        ORDER BY branch_name ASC
    ", 'i', [$businessId]);

    if (!$userId) {
        return $allBranches;
    }

    $accessRows = pos_api_all($conn, "
        SELECT branch_id
        FROM user_branch_access
        WHERE business_id = ? AND user_id = ? AND access_status = 1
    ", 'ii', [$businessId, $userId]);

    if (!$accessRows) {
        return $allBranches;
    }

    $allowed = [];
    foreach ($accessRows as $row) {
        $allowed[(int)$row['branch_id']] = true;
    }

    return array_values(array_filter($allBranches, function ($branch) use ($allowed) {
        return isset($allowed[(int)$branch['branch_id']]);
    }));
}

function pos_api_fetch_business(mysqli $conn, int $businessId): array
{
    $row = pos_api_one($conn, "
        SELECT business_id, business_code, business_name, owner_name, mobile, email, address, logo_path, gst_type_key, gstin, composition_status
        FROM businesses
        WHERE business_id = ? AND status = 1
        LIMIT 1
    ", 'i', [$businessId]);

    return $row ?: [
        'business_id' => $businessId,
        'business_name' => 'GK Footwear',
        'gst_type_key' => 'gst_composition',
        'gstin' => '',
        'composition_status' => 1,
    ];
}

function pos_api_fetch_business_settings(mysqli $conn, int $businessId): array
{
    $row = pos_api_one($conn, "
        SELECT *
        FROM business_settings
        WHERE business_id = ? AND status = 1
        LIMIT 1
    ", 'i', [$businessId]);

    return $row ?: [
        'gst_type_key' => 'gst_composition',
        'default_currency' => 'INR',
        'timezone' => 'Asia/Kolkata',
        'invoice_prefix' => 'BILL',
        'barcode_prefix' => 'GK',
        'tax_columns_enabled' => 0,
        'partial_payment_enabled' => 1,
        'walkin_credit_enabled' => 0,
        'loyalty_enabled' => 0,
    ];
}

function pos_api_fetch_branch_settings(mysqli $conn, int $businessId, int $branchId): array
{
    $row = pos_api_one($conn, "
        SELECT *
        FROM branch_settings
        WHERE business_id = ? AND branch_id = ? AND status = 1
        LIMIT 1
    ", 'ii', [$businessId, $branchId]);

    return $row ?: [
        'invoice_prefix' => 'BILL',
        'barcode_prefix' => 'GK',
        'default_payment_method' => 'Cash',
        'thermal_printer_size' => '3-inch',
    ];
}

function pos_api_fetch_invoice_settings(mysqli $conn, int $businessId, int $branchId): array
{
    $row = pos_api_one($conn, "
        SELECT *
        FROM invoice_settings
        WHERE business_id = ?
          AND (branch_id = ? OR branch_id IS NULL)
          AND status = 1
        ORDER BY branch_id IS NULL ASC
        LIMIT 1
    ", 'ii', [$businessId, $branchId]);

    if ($row) {
        return $row;
    }

    $business = pos_api_fetch_business($conn, $businessId);
    $gstType = (string)($business['gst_type_key'] ?? 'gst_composition');
    $gst = pos_api_one($conn, "
        SELECT *
        FROM gst_type_settings
        WHERE gst_type_key = ? AND status = 1
        LIMIT 1
    ", 's', [$gstType]);

    return [
        'gst_type_key' => $gstType,
        'invoice_title' => $gst['invoice_title'] ?? 'Bill of Supply',
        'paper_size' => '3-inch',
        'show_gstin' => (int)($gst['show_gstin'] ?? 1),
        'show_composition_note' => (int)($gst['show_composition_note'] ?? 1),
        'composition_note' => $gst['composition_note'] ?? 'Composition taxable person, not eligible to collect tax on supplies.',
        'show_tax_columns' => (int)($gst['show_tax_columns'] ?? 0),
        'show_cgst_sgst_igst' => 0,
        'show_barcode' => 1,
        'show_today_savings' => 1,
        'show_loyalty_redeem' => 1,
        'footer_text' => '',
    ];
}

function pos_api_fetch_barcode_settings(mysqli $conn, int $businessId, int $branchId): array
{
    $row = pos_api_one($conn, "
        SELECT *
        FROM barcode_settings
        WHERE business_id = ?
          AND (branch_id = ? OR branch_id IS NULL)
          AND status = 1
        ORDER BY branch_id IS NULL ASC
        LIMIT 1
    ", 'ii', [$businessId, $branchId]);

    return $row ?: [
        'barcode_type' => 'CODE128',
        'stock_barcode_prefix' => 'STK',
        'bill_barcode_prefix' => 'BILL',
        'running_number' => 1,
    ];
}

function pos_api_fetch_payment_methods(mysqli $conn, int $businessId): array
{
    $rows = pos_api_all($conn, "
        SELECT payment_method_id, payment_method_name, method_type
        FROM payment_methods
        WHERE business_id = ? AND status = 1
        ORDER BY FIELD(method_type, 'cash','upi','card','cheque','credit','split','other'), payment_method_name
    ", 'i', [$businessId]);

    $hasCash = false;
    foreach ($rows as $row) {
        if (($row['method_type'] ?? '') === 'cash') {
            $hasCash = true;
            break;
        }
    }

    if (!$hasCash) {
        array_unshift($rows, [
            'payment_method_id' => 0,
            'payment_method_name' => 'Cash',
            'method_type' => 'cash',
            'virtual' => 1,
        ]);
    }

    return $rows;
}

function pos_api_resolve_payment_method(mysqli $conn, int $businessId, int $paymentMethodId, string $methodType = 'cash', string $methodName = ''): int
{
    if ($paymentMethodId > 0) {
        $row = pos_api_one($conn, "
            SELECT payment_method_id
            FROM payment_methods
            WHERE business_id = ? AND payment_method_id = ? AND status = 1
            LIMIT 1
        ", 'ii', [$businessId, $paymentMethodId]);
        if ($row) {
            return (int)$row['payment_method_id'];
        }
    }

    $methodType = in_array($methodType, ['cash','upi','card','cheque','credit','split','other'], true) ? $methodType : 'cash';
    $methodName = $methodName !== '' ? $methodName : ucfirst($methodType);

    $row = pos_api_one($conn, "
        SELECT payment_method_id
        FROM payment_methods
        WHERE business_id = ? AND method_type = ? AND status = 1
        ORDER BY payment_method_id ASC
        LIMIT 1
    ", 'is', [$businessId, $methodType]);

    if ($row) {
        return (int)$row['payment_method_id'];
    }

    return pos_api_exec($conn, "
        INSERT INTO payment_methods (business_id, payment_method_name, method_type, status)
        VALUES (?, ?, ?, 1)
    ", 'iss', [$businessId, $methodName, $methodType]);
}

function pos_api_sequence_candidate(string $prefix, int $number, int $pad): string
{
    return $prefix . '-' . str_pad((string)$number, $pad, '0', STR_PAD_LEFT);
}

function pos_api_sequence_value_exists(mysqli $conn, int $businessId, int $branchId, string $key, string $candidate): bool
{
    if ($key === 'bill_no') {
        return (bool) pos_api_one($conn, "
            SELECT bill_id
            FROM bills
            WHERE business_id = ?
              AND branch_id = ?
              AND bill_no = ?
            LIMIT 1
        ", 'iis', [$businessId, $branchId, $candidate]);
    }

    if ($key === 'bill_barcode') {
        return (bool) pos_api_one($conn, "
            SELECT bill_barcode_id
            FROM bill_barcodes
            WHERE barcode_value = ?
            LIMIT 1
        ", 's', [$candidate]);
    }

    return false;
}

function pos_api_peek_sequence(mysqli $conn, int $businessId, int $branchId, string $key, string $fallbackPrefix, int $fallbackPad = 4): string
{
    $row = pos_api_one($conn, "
        SELECT prefix, current_number, padding_length
        FROM number_sequences
        WHERE business_id = ?
          AND (branch_id = ? OR branch_id IS NULL)
          AND sequence_key = ?
          AND status = 1
        ORDER BY branch_id IS NULL ASC
        LIMIT 1
    ", 'iis', [$businessId, $branchId, $key]);

    $prefix = (string)($row['prefix'] ?? $fallbackPrefix);
    $next = ((int)($row['current_number'] ?? 0)) + 1;
    $pad = (int)($row['padding_length'] ?? $fallbackPad);

    // UI preview only: skip already-used bill numbers/barcodes so the screen does not show a duplicate.
    for ($i = 0; $i < 10000; $i++, $next++) {
        $candidate = pos_api_sequence_candidate($prefix, $next, $pad);
        if (!pos_api_sequence_value_exists($conn, $businessId, $branchId, $key, $candidate)) {
            return $candidate;
        }
    }

    return pos_api_sequence_candidate($prefix, $next, $pad);
}

function pos_api_next_sequence(mysqli $conn, int $businessId, int $branchId, string $key, string $fallbackPrefix, int $fallbackPad = 4): string
{
    $row = pos_api_one($conn, "
        SELECT sequence_id, prefix, current_number, padding_length
        FROM number_sequences
        WHERE business_id = ?
          AND (branch_id = ? OR branch_id IS NULL)
          AND sequence_key = ?
          AND status = 1
        ORDER BY branch_id IS NULL ASC
        LIMIT 1
        FOR UPDATE
    ", 'iis', [$businessId, $branchId, $key]);

    if (!$row) {
        $sequenceId = pos_api_exec($conn, "
            INSERT INTO number_sequences (business_id, branch_id, sequence_key, prefix, current_number, padding_length, status)
            VALUES (?, NULL, ?, ?, 0, ?, 1)
        ", 'issi', [$businessId, $key, $fallbackPrefix, $fallbackPad]);

        $row = [
            'sequence_id' => $sequenceId,
            'prefix' => $fallbackPrefix,
            'current_number' => 0,
            'padding_length' => $fallbackPad,
        ];
    }

    $sequenceId = (int)$row['sequence_id'];
    $prefix = (string)$row['prefix'];
    $pad = (int)$row['padding_length'];
    $next = ((int)$row['current_number']) + 1;

    // Important fix: old/imported data may already contain BILL-000002 while
    // number_sequences.current_number still points before it. Skip any existing
    // value before inserting into bills or bill_barcodes.
    for ($i = 0; $i < 10000; $i++, $next++) {
        $candidate = pos_api_sequence_candidate($prefix, $next, $pad);
        if (!pos_api_sequence_value_exists($conn, $businessId, $branchId, $key, $candidate)) {
            pos_api_exec($conn, "
                UPDATE number_sequences
                SET current_number = ?, updated_at = NOW()
                WHERE sequence_id = ?
            ", 'ii', [$next, $sequenceId]);
            return $candidate;
        }
    }

    throw new RuntimeException('Unable to generate unique ' . $key . '. Please check number_sequences.');
}

function pos_api_discount_amount(float $rate, string $type, float $value): float
{
    if ($type === 'percent') {
        return round(min($rate, max(0, $rate * $value / 100)), 2);
    }
    if ($type === 'amount') {
        return round(min($rate, max(0, $value)), 2);
    }
    return 0.0;
}

function pos_api_calc_bill_discount(float $sellingAmount, string $type, float $value): float
{
    if ($type === 'percent') {
        return round(min($sellingAmount, max(0, $sellingAmount * $value / 100)), 2);
    }
    if ($type === 'amount') {
        return round(min($sellingAmount, max(0, $value)), 2);
    }
    return 0.0;
}

function pos_api_stock_row_to_product(array $row): array
{
    $available = (float)($row['available_qty'] ?? 0);
    $mrp = (float)($row['mrp_rate'] ?? 0);
    $selling = (float)($row['selling_rate'] ?? 0);
    $discountType = (string)($row['product_discount_type'] ?? 'none');
    $discountValue = (float)($row['product_discount_value'] ?? 0);
    $discountAmount = (float)($row['discount_amount'] ?? pos_api_discount_amount($mrp, $discountType, $discountValue));

    if ($selling <= 0 && $mrp > 0) {
        $selling = max(0, $mrp - $discountAmount);
    }

    return [
        'stock_item_id' => (int)$row['stock_item_id'],
        'stock_batch_id' => (int)$row['batch_id'],
        'barcode_id' => isset($row['barcode_id']) ? (int)$row['barcode_id'] : 0,
        'barcode_value' => (string)($row['barcode_value'] ?? ''),
        'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : 0,
        'category_name' => (string)($row['category_name'] ?? ''),
        'brand_id' => isset($row['brand_id']) ? (int)$row['brand_id'] : 0,
        'brand_name' => (string)($row['brand_name'] ?? ''),
        'article_no' => (string)$row['article_no'],
        'article_name' => (string)($row['article_name'] ?? ''),
        'size' => (string)$row['size'],
        'color' => (string)($row['color'] ?? ''),
        'available_qty' => $available,
        'mrp_rate' => $mrp,
        'product_discount_type' => $discountType,
        'product_discount_value' => $discountValue,
        'discount_amount' => $discountAmount,
        'selling_rate' => $selling,
        'item_status' => (string)($row['item_status'] ?? 'active'),
        'image_url' => '',
        'low_stock' => $available > 0 && $available <= 2,
    ];
}

function pos_api_product_select_sql(): string
{
    return "
        SELECT
            si.stock_item_id, si.business_id, si.branch_id, si.batch_id, si.category_id, si.brand_id,
            si.article_no, si.article_name, si.size, si.color, si.available_qty, si.mrp_rate,
            si.product_discount_type, si.product_discount_value, si.discount_amount, si.selling_rate,
            si.item_status,
            b.brand_name,
            c.category_name,
            MIN(sb.barcode_id) AS barcode_id,
            MIN(sb.barcode_value) AS barcode_value
        FROM stock_inward_items si
        LEFT JOIN brands b
               ON b.brand_id = si.brand_id
              AND b.business_id = si.business_id
        LEFT JOIN categories c
               ON c.category_id = si.category_id
              AND c.business_id = si.business_id
        LEFT JOIN stock_barcodes sb
               ON sb.stock_item_id = si.stock_item_id
              AND sb.business_id = si.business_id
              AND sb.branch_id = si.branch_id
              AND sb.barcode_status = 'active'
    ";
}

function pos_api_search_products(mysqli $conn, int $businessId, int $branchId, string $query): array
{
    $query = pos_api_clean_string($query, 120);
    if ($query === '') {
        return [];
    }

    $like = '%' . $query . '%';
    $sql = pos_api_product_select_sql() . "
        WHERE si.business_id = ?
          AND si.branch_id = ?
          AND si.available_qty > 0
          AND si.item_status = 'active'
          AND (
                si.article_no LIKE ?
             OR si.article_name LIKE ?
             OR si.size LIKE ?
             OR si.color LIKE ?
             OR b.brand_name LIKE ?
             OR c.category_name LIKE ?
             OR sb.barcode_value = ?
          )
        GROUP BY si.stock_item_id
        ORDER BY si.article_no ASC, si.color ASC, CAST(si.size AS UNSIGNED) ASC, si.size ASC
        LIMIT 40
    ";

    $rows = pos_api_all($conn, $sql, 'iisssssss', [$businessId, $branchId, $like, $like, $like, $like, $like, $like, $query]);
    return array_map('pos_api_stock_row_to_product', $rows);
}

function pos_api_get_product_options(mysqli $conn, int $businessId, int $branchId, int $stockItemId): array
{
    $base = pos_api_one($conn, "
        SELECT stock_item_id, article_no, brand_id
        FROM stock_inward_items
        WHERE business_id = ? AND branch_id = ? AND stock_item_id = ?
        LIMIT 1
    ", 'iii', [$businessId, $branchId, $stockItemId]);

    if (!$base) {
        return [];
    }

    $sql = pos_api_product_select_sql() . "
        WHERE si.business_id = ?
          AND si.branch_id = ?
          AND si.article_no = ?
          AND IFNULL(si.brand_id, 0) = ?
          AND si.available_qty > 0
          AND si.item_status = 'active'
        GROUP BY si.stock_item_id
        ORDER BY si.color ASC, CAST(si.size AS UNSIGNED) ASC, si.size ASC
    ";

    $rows = pos_api_all($conn, $sql, 'iisi', [$businessId, $branchId, (string)$base['article_no'], (int)$base['brand_id']]);
    return array_map('pos_api_stock_row_to_product', $rows);
}

function pos_api_scan_product(mysqli $conn, int $businessId, int $branchId, string $code): array
{
    $code = pos_api_clean_string($code, 150);
    if ($code === '') {
        return ['type' => 'none'];
    }

    $billBarcode = pos_api_one($conn, "
        SELECT bb.bill_id
        FROM bill_barcodes bb
        INNER JOIN bills b ON b.bill_id = bb.bill_id
        WHERE bb.business_id = ?
          AND bb.branch_id = ?
          AND bb.barcode_value = ?
          AND bb.barcode_status <> 'deleted'
          AND b.bill_status <> 'deleted'
        LIMIT 1
    ", 'iis', [$businessId, $branchId, $code]);

    if ($billBarcode) {
        return [
            'type' => 'bill',
            'bill' => pos_api_get_bill($conn, $businessId, $branchId, (int)$billBarcode['bill_id']),
        ];
    }

    $sql = pos_api_product_select_sql() . "
        WHERE si.business_id = ?
          AND si.branch_id = ?
          AND si.available_qty > 0
          AND si.item_status = 'active'
          AND sb.barcode_value = ?
          AND sb.barcode_status = 'active'
        GROUP BY si.stock_item_id
        LIMIT 1
    ";
    $row = pos_api_one($conn, $sql, 'iis', [$businessId, $branchId, $code]);

    if (!$row) {
        $sql = pos_api_product_select_sql() . "
            WHERE si.business_id = ?
              AND si.branch_id = ?
              AND si.available_qty > 0
              AND si.item_status = 'active'
              AND si.article_no = ?
            GROUP BY si.stock_item_id
            ORDER BY si.available_qty DESC
            LIMIT 1
        ";
        $row = pos_api_one($conn, $sql, 'iis', [$businessId, $branchId, $code]);
    }

    if (!$row) {
        return ['type' => 'none'];
    }

    $product = pos_api_stock_row_to_product($row);
    return [
        'type' => 'product',
        'product' => $product,
        'options' => pos_api_get_product_options($conn, $businessId, $branchId, (int)$product['stock_item_id']),
    ];
}

function pos_api_search_customers(mysqli $conn, int $businessId, string $query): array
{
    $query = pos_api_clean_string($query, 120);

    $baseSql = "
        SELECT
            c.customer_id, c.customer_name, c.mobile, c.email, c.address, c.gstin,
            c.opening_outstanding, c.loyalty_points, c.status,
            COALESCE(co.balance_amount, c.opening_outstanding, 0) AS outstanding_balance,
            COALESCE(co.total_bill_amount, 0) AS total_bill_amount,
            COALESCE(co.total_paid_amount, 0) AS total_paid_amount,
            COALESCE(COUNT(DISTINCT b.bill_id), 0) AS purchase_count
        FROM customers c
        LEFT JOIN customer_outstanding co
               ON co.business_id = c.business_id
              AND co.customer_id = c.customer_id
        LEFT JOIN bills b
               ON b.business_id = c.business_id
              AND b.customer_id = c.customer_id
              AND b.bill_status = 'active'
        WHERE c.business_id = ?
          AND c.status = 1
    ";

    if ($query === '') {
        return pos_api_all($conn, $baseSql . "
            GROUP BY c.customer_id
            ORDER BY c.updated_at DESC, c.created_at DESC, c.customer_name ASC
            LIMIT 50
        ", 'i', [$businessId]);
    }

    $like = '%' . $query . '%';
    return pos_api_all($conn, $baseSql . "
          AND (c.customer_name LIKE ? OR c.mobile LIKE ? OR c.email LIKE ? OR c.gstin LIKE ?)
        GROUP BY c.customer_id
        ORDER BY c.customer_name ASC
        LIMIT 50
    ", 'issss', [$businessId, $like, $like, $like, $like]);
}

function pos_api_save_customer(mysqli $conn, int $businessId, array $payload): array
{
    $name = pos_api_clean_string($payload['customer_name'] ?? $payload['name'] ?? '', 200);
    $mobile = pos_api_mobile($payload['mobile'] ?? '');
    $email = pos_api_clean_string($payload['email'] ?? '', 150);
    $address = pos_api_clean_string($payload['address'] ?? '', 1000);
    $gstin = strtoupper(pos_api_clean_string($payload['gstin'] ?? '', 30));

    if ($name === '' && $mobile !== '') {
        $name = 'Customer ' . $mobile;
    }
    if ($name === '') {
        throw new RuntimeException('Customer name or mobile number is required.');
    }
    if ($mobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
        throw new RuntimeException('Enter a valid 10 digit mobile number.');
    }

    if ($mobile !== '') {
        $existing = pos_api_one($conn, "
            SELECT customer_id, customer_name, mobile, email, address, gstin, opening_outstanding, loyalty_points
            FROM customers
            WHERE business_id = ? AND mobile = ? AND status = 1
            LIMIT 1
        ", 'is', [$businessId, $mobile]);

        if ($existing) {
            pos_api_exec($conn, "
                UPDATE customers
                SET customer_name = ?, email = ?, address = ?, gstin = ?, updated_at = NOW()
                WHERE customer_id = ?
            ", 'ssssi', [$name, $email, $address, $gstin, (int)$existing['customer_id']]);
            $existing['customer_name'] = $name;
            $existing['email'] = $email;
            $existing['address'] = $address;
            $existing['gstin'] = $gstin;
            return $existing;
        }
    }

    $customerId = pos_api_exec($conn, "
        INSERT INTO customers (business_id, customer_name, mobile, email, address, gstin, opening_outstanding, loyalty_points, status)
        VALUES (?, ?, ?, ?, ?, ?, 0.00, 0.00, 1)
    ", 'isssss', [$businessId, $name, $mobile, $email, $address, $gstin]);

    return [
        'customer_id' => $customerId,
        'customer_name' => $name,
        'mobile' => $mobile,
        'email' => $email,
        'address' => $address,
        'gstin' => $gstin,
        'opening_outstanding' => 0,
        'loyalty_points' => 0,
        'outstanding_balance' => 0,
    ];
}

function pos_api_validate_offer(mysqli $conn, int $businessId, string $code): ?array
{
    $code = strtoupper(pos_api_clean_string($code, 50));
    if ($code === '') {
        return null;
    }

    $today = date('Y-m-d');
    $offer = pos_api_one($conn, "
        SELECT offer_id, code, discount_type, discount_value, valid_from, valid_to
        FROM offer_codes
        WHERE business_id = ?
          AND code = ?
          AND status = 1
          AND (valid_from IS NULL OR valid_from <= ?)
          AND (valid_to IS NULL OR valid_to >= ?)
        LIMIT 1
    ", 'isss', [$businessId, $code, $today, $today]);

    if (!$offer) {
        throw new RuntimeException('Invalid or expired offer code.');
    }

    return $offer;
}

function pos_api_activity_log(mysqli $conn, int $businessId, ?int $branchId, string $module, string $action, ?int $recordId, $oldValue, $newValue): void
{
    $userId = pos_api_user_id();
    $roleId = pos_api_role_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $device = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $oldJson = $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newJson = $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        pos_api_exec($conn, "
            INSERT INTO business_activity_logs
                (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", 'iiiississss', [$businessId, $branchId, $userId, $roleId, $module, $action, $recordId, $oldJson, $newJson, $ip, $device]);
    } catch (Throwable $ignored) {
        // Activity log should never block billing.
    }

    try {
        pos_api_exec($conn, "
            INSERT INTO activity_logs
                (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", 'iiiississss', [$businessId, $branchId, $userId, $roleId, $module, $action, $recordId, $oldJson, $newJson, $ip, $device]);
    } catch (Throwable $ignored) {
        // Activity log should never block billing.
    }
}

function pos_api_get_customer_full(mysqli $conn, int $businessId, int $customerId): ?array
{
    if ($customerId <= 0) {
        return null;
    }

    return pos_api_one($conn, "
        SELECT
            c.customer_id, c.customer_name, c.mobile, c.email, c.address, c.gstin,
            c.opening_outstanding, c.loyalty_points,
            COALESCE(co.balance_amount, c.opening_outstanding, 0) AS outstanding_balance,
            COALESCE(co.total_bill_amount, 0) AS total_bill_amount,
            COALESCE(co.total_paid_amount, 0) AS total_paid_amount
        FROM customers c
        LEFT JOIN customer_outstanding co
               ON co.business_id = c.business_id
              AND co.customer_id = c.customer_id
        WHERE c.business_id = ? AND c.customer_id = ? AND c.status = 1
        LIMIT 1
    ", 'ii', [$businessId, $customerId]);
}

function pos_api_customer_purchase_history(mysqli $conn, int $businessId, int $branchId, int $customerId): array
{
    if ($customerId <= 0) {
        return [];
    }

    return pos_api_all($conn, "
        SELECT
            b.bill_id,
            b.bill_no,
            b.bill_date,
            b.bill_time,
            bi.bill_item_id,
            bi.article_no,
            bi.article_name AS article_model,
            bi.size,
            bi.qty,
            bi.selling_rate,
            bi.amount AS total_amount,
            br.brand_name,
            si.color,
            si.available_qty AS remaining_available_stock,
            c.category_name AS product_type
        FROM bills b
        INNER JOIN bill_items bi
                ON bi.bill_id = b.bill_id
               AND bi.business_id = b.business_id
               AND bi.branch_id = b.branch_id
        LEFT JOIN stock_inward_items si
               ON si.stock_item_id = bi.stock_item_id
              AND si.business_id = bi.business_id
              AND si.branch_id = bi.branch_id
        LEFT JOIN brands br
               ON br.brand_id = bi.brand_id
              AND br.business_id = bi.business_id
        LEFT JOIN categories c
               ON c.category_id = si.category_id
              AND c.business_id = si.business_id
        WHERE b.business_id = ?
          AND b.branch_id = ?
          AND b.customer_id = ?
          AND b.bill_status = 'active'
        ORDER BY b.bill_date DESC, b.bill_time DESC, bi.bill_item_id DESC
        LIMIT 200
    ", 'iii', [$businessId, $branchId, $customerId]);
}

function pos_api_get_bill(mysqli $conn, int $businessId, int $branchId, int $billId): ?array
{
    $bill = pos_api_one($conn, "
        SELECT
            b.*,
            br.branch_name,
            bs.business_name,
            bs.gstin AS business_gstin,
            bs.address AS business_address,
            bb.barcode_value AS bill_barcode
        FROM bills b
        INNER JOIN branches br ON br.branch_id = b.branch_id
        INNER JOIN businesses bs ON bs.business_id = b.business_id
        LEFT JOIN bill_barcodes bb ON bb.bill_id = b.bill_id AND bb.barcode_status <> 'deleted'
        WHERE b.business_id = ?
          AND b.branch_id = ?
          AND b.bill_id = ?
        LIMIT 1
    ", 'iii', [$businessId, $branchId, $billId]);

    if (!$bill) {
        return null;
    }

    $items = pos_api_all($conn, "
        SELECT
            bi.*,
            br.brand_name,
            sb.barcode_value
        FROM bill_items bi
        LEFT JOIN brands br
               ON br.brand_id = bi.brand_id
              AND br.business_id = bi.business_id
        LEFT JOIN stock_barcodes sb ON sb.barcode_id = bi.barcode_id
        WHERE bi.business_id = ?
          AND bi.branch_id = ?
          AND bi.bill_id = ?
        ORDER BY bi.bill_item_id ASC
    ", 'iii', [$businessId, $branchId, $billId]);

    $payments = pos_api_all($conn, "
        SELECT
            bp.*,
            pm.payment_method_name,
            pm.method_type
        FROM bill_payments bp
        INNER JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id
        WHERE bp.business_id = ?
          AND bp.branch_id = ?
          AND bp.bill_id = ?
        ORDER BY bp.payment_id ASC
    ", 'iii', [$businessId, $branchId, $billId]);

    $bill['items'] = $items;
    $bill['payments'] = $payments;
    return $bill;
}

function pos_api_save_hold(mysqli $conn, int $businessId, int $branchId, array $payload): array
{
    if (!pos_api_branch_allowed($conn, $businessId, $branchId)) {
        throw new RuntimeException('Invalid branch selected.');
    }

    $items = $payload['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        throw new RuntimeException('Add at least one item before holding bill.');
    }

    $holdId = (int)($payload['hold_id'] ?? 0);
    $userId = pos_api_user_id();
    $payload['branch_id'] = $branchId;
    $payload['held_at'] = date('Y-m-d H:i:s');
    $holdJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($holdId > 0) {
        pos_api_exec($conn, "
            UPDATE pos_bill_holds
            SET hold_data = ?, updated_at = NOW()
            WHERE hold_id = ? AND business_id = ? AND branch_id = ? AND hold_status = 'active'
        ", 'siii', [$holdJson, $holdId, $businessId, $branchId]);
    } else {
        $holdId = pos_api_exec($conn, "
            INSERT INTO pos_bill_holds (business_id, branch_id, held_by, hold_data, hold_status)
            VALUES (?, ?, ?, ?, 'active')
        ", 'iiis', [$businessId, $branchId, $userId, $holdJson]);
    }

    pos_api_activity_log($conn, $businessId, $branchId, 'POS Create Bill', 'hold_bill', $holdId, null, ['items' => count($items)]);

    return [
        'hold_id' => $holdId,
        'hold_no' => 'HOLD-' . str_pad((string)$holdId, 5, '0', STR_PAD_LEFT),
    ];
}

function pos_api_list_holds(mysqli $conn, int $businessId, int $branchId): array
{
    $rows = pos_api_all($conn, "
        SELECT
            h.hold_id, h.branch_id, h.held_by, h.hold_data, h.created_at, h.updated_at,
            u.name AS sales_user,
            br.branch_name
        FROM pos_bill_holds h
        LEFT JOIN users u ON u.user_id = h.held_by
        LEFT JOIN branches br ON br.branch_id = h.branch_id
        WHERE h.business_id = ?
          AND h.branch_id = ?
          AND h.hold_status = 'active'
        ORDER BY h.updated_at DESC, h.created_at DESC
        LIMIT 100
    ", 'ii', [$businessId, $branchId]);

    $holds = [];
    foreach ($rows as $row) {
        $data = json_decode((string)$row['hold_data'], true);
        if (!is_array($data)) {
            $data = [];
        }

        $customer = $data['customer'] ?? [];
        $items = $data['items'] ?? [];

        $holds[] = [
            'hold_id' => (int)$row['hold_id'],
            'hold_no' => 'HOLD-' . str_pad((string)$row['hold_id'], 5, '0', STR_PAD_LEFT),
            'customer_name' => (string)($customer['customer_name'] ?? $customer['name'] ?? $data['customer_name'] ?? 'Walk-in Customer'),
            'customer_mobile' => (string)($customer['mobile'] ?? $data['customer_mobile'] ?? ''),
            'item_count' => is_array($items) ? count($items) : 0,
            'hold_time' => $row['updated_at'] ?: $row['created_at'],
            'sales_user' => (string)($row['sales_user'] ?? 'Sales User'),
            'branch_name' => (string)($row['branch_name'] ?? ''),
            'hold_data' => $data,
        ];
    }

    return $holds;
}

function pos_api_resume_hold(mysqli $conn, int $businessId, int $branchId, int $holdId): array
{
    $row = pos_api_one($conn, "
        SELECT hold_id, hold_data, created_at, updated_at
        FROM pos_bill_holds
        WHERE business_id = ?
          AND branch_id = ?
          AND hold_id = ?
          AND hold_status = 'active'
        LIMIT 1
    ", 'iii', [$businessId, $branchId, $holdId]);

    if (!$row) {
        throw new RuntimeException('Held bill not found or already converted.');
    }

    $data = json_decode((string)$row['hold_data'], true);
    if (!is_array($data)) {
        throw new RuntimeException('Held bill data is invalid.');
    }

    $data['hold_id'] = $holdId;
    return $data;
}

function pos_api_cancel_hold(mysqli $conn, int $businessId, int $branchId, int $holdId): void
{
    pos_api_exec($conn, "
        UPDATE pos_bill_holds
        SET hold_status = 'cancelled', updated_at = NOW()
        WHERE business_id = ? AND branch_id = ? AND hold_id = ? AND hold_status = 'active'
    ", 'iii', [$businessId, $branchId, $holdId]);

    pos_api_activity_log($conn, $businessId, $branchId, 'POS Create Bill', 'cancel_hold', $holdId, null, null);
}

function pos_api_save_bill(mysqli $conn, int $businessId, int $branchId, array $payload): array
{
    $userId = pos_api_user_id();
    $business = pos_api_fetch_business($conn, $businessId);
    $businessSettings = pos_api_fetch_business_settings($conn, $businessId);
    $branchSettings = pos_api_fetch_branch_settings($conn, $businessId, $branchId);
    $invoiceSettings = pos_api_fetch_invoice_settings($conn, $businessId, $branchId);
    $barcodeSettings = pos_api_fetch_barcode_settings($conn, $businessId, $branchId);

    if (!pos_api_branch_allowed($conn, $businessId, $branchId)) {
        throw new RuntimeException('Invalid branch selected.');
    }

    $itemsPayload = $payload['items'] ?? [];
    if (!is_array($itemsPayload) || count($itemsPayload) === 0) {
        throw new RuntimeException('Add at least one product before saving bill.');
    }

    $customerPayload = $payload['customer'] ?? [];
    $customerId = (int)($customerPayload['customer_id'] ?? $payload['customer_id'] ?? 0);
    $customerName = pos_api_clean_string($customerPayload['customer_name'] ?? $customerPayload['name'] ?? $payload['customer_name'] ?? 'Walk-in Customer', 200);
    $customerMobile = pos_api_mobile($customerPayload['mobile'] ?? $payload['customer_mobile'] ?? '');

    if ($customerId <= 0 && ($customerName !== '' && strcasecmp($customerName, 'Walk-in Customer') !== 0 || $customerMobile !== '')) {
        $customer = pos_api_save_customer($conn, $businessId, [
            'customer_name' => $customerName,
            'mobile' => $customerMobile,
            'email' => $customerPayload['email'] ?? '',
            'address' => $customerPayload['address'] ?? '',
            'gstin' => $customerPayload['gstin'] ?? '',
        ]);
        $customerId = (int)$customer['customer_id'];
    } else {
        $customer = pos_api_get_customer_full($conn, $businessId, $customerId);
    }

    if ($customer) {
        $customerName = (string)$customer['customer_name'];
        $customerMobile = (string)$customer['mobile'];
    } else {
        $customerName = $customerName !== '' ? $customerName : 'Walk-in Customer';
    }

    $offerCode = strtoupper(pos_api_clean_string($payload['offer_code'] ?? '', 50));
    $offer = null;
    if ($offerCode !== '') {
        $offer = pos_api_validate_offer($conn, $businessId, $offerCode);
    }

    $serverItems = [];
    $mrpTotal = 0.0;
    $itemDiscountTotal = 0.0;
    $sellingAmount = 0.0;
    $todaySavings = 0.0;

    foreach ($itemsPayload as $item) {
        $stockItemId = (int)($item['stock_item_id'] ?? 0);
        $qty = pos_api_num($item['qty'] ?? 0);
        if ($stockItemId <= 0 || $qty <= 0) {
            throw new RuntimeException('Invalid bill item detected.');
        }

        $stock = pos_api_one($conn, "
            SELECT
                si.*, b.brand_name
            FROM stock_inward_items si
            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id
            WHERE si.business_id = ?
              AND si.branch_id = ?
              AND si.stock_item_id = ?
              AND si.item_status IN ('active','out_of_stock')
            LIMIT 1
            FOR UPDATE
        ", 'iii', [$businessId, $branchId, $stockItemId]);

        if (!$stock) {
            throw new RuntimeException('Stock item not found for selected branch.');
        }

        $availableQty = (float)$stock['available_qty'];
        if ($availableQty < $qty || $availableQty <= 0) {
            throw new RuntimeException('Stock unavailable for ' . $stock['article_no'] . ' - Size ' . $stock['size'] . '. Available: ' . $availableQty);
        }

        $barcodeId = (int)($item['barcode_id'] ?? 0);
        if ($barcodeId > 0) {
            $barcode = pos_api_one($conn, "
                SELECT barcode_id
                FROM stock_barcodes
                WHERE business_id = ?
                  AND branch_id = ?
                  AND stock_item_id = ?
                  AND barcode_id = ?
                  AND barcode_status = 'active'
                LIMIT 1
                FOR UPDATE
            ", 'iiii', [$businessId, $branchId, $stockItemId, $barcodeId]);

            if (!$barcode) {
                $barcodeId = 0;
            }
        }

        $mrpRate = (float)$stock['mrp_rate'];
        $clientSelling = pos_api_num($item['selling_rate'] ?? $item['selling_price'] ?? 0);
        $discountType = pos_api_clean_string($item['discount_type'] ?? $stock['product_discount_type'] ?? 'none', 20);
        $discountType = in_array($discountType, ['none','percent','amount'], true) ? $discountType : 'none';
        $discountValue = pos_api_num($item['discount_value'] ?? $stock['product_discount_value'] ?? 0);

        if ($clientSelling > 0) {
            $sellingRate = round(min($mrpRate, $clientSelling), 2);
            $discountAmountPerUnit = round(max(0, $mrpRate - $sellingRate), 2);
            if ($discountAmountPerUnit <= 0) {
                $discountType = 'none';
                $discountValue = 0;
            }
        } else {
            $discountAmountPerUnit = pos_api_discount_amount($mrpRate, $discountType, $discountValue);
            $sellingRate = round(max(0, $mrpRate - $discountAmountPerUnit), 2);
        }

        $lineMrp = round($mrpRate * $qty, 2);
        $lineDiscount = round($discountAmountPerUnit * $qty, 2);
        $amount = round($sellingRate * $qty, 2);

        $taxable = ((string)$invoiceSettings['gst_type_key'] === 'gst_regular') ? $amount : 0.0;
        $cgst = 0.0;
        $sgst = 0.0;
        $igst = 0.0;
        $tax = 0.0;

        $serverItems[] = [
            'stock_batch_id' => (int)$stock['batch_id'],
            'stock_item_id' => $stockItemId,
            'barcode_id' => $barcodeId > 0 ? $barcodeId : null,
            'article_no' => (string)$stock['article_no'],
            'article_name' => (string)($stock['article_name'] ?? ''),
            'brand_id' => isset($stock['brand_id']) ? (int)$stock['brand_id'] : null,
            'brand_name' => (string)($stock['brand_name'] ?? ''),
            'size' => (string)$stock['size'],
            'color' => (string)($stock['color'] ?? ''),
            'qty' => $qty,
            'mrp_rate' => $mrpRate,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmountPerUnit,
            'selling_rate' => $sellingRate,
            'amount' => $amount,
            'taxable_amount' => $taxable,
            'cgst_amount' => $cgst,
            'sgst_amount' => $sgst,
            'igst_amount' => $igst,
            'tax_amount' => $tax,
        ];

        $mrpTotal += $lineMrp;
        $itemDiscountTotal += $lineDiscount;
        $sellingAmount += $amount;
        $todaySavings += max(0, $lineMrp - $amount);
    }

    $billDiscountType = pos_api_clean_string($payload['bill_discount_type'] ?? 'none', 20);
    $billDiscountValue = pos_api_num($payload['bill_discount_value'] ?? 0);
    if ($offer) {
        $billDiscountType = (string)$offer['discount_type'];
        $billDiscountValue = (float)$offer['discount_value'];
    }
    $billDiscountType = in_array($billDiscountType, ['none','percent','amount'], true) ? $billDiscountType : 'none';
    $billDiscountAmount = pos_api_calc_bill_discount($sellingAmount, $billDiscountType, $billDiscountValue);

    $loyaltyRedeem = pos_api_num($payload['loyalty_redeem_amount'] ?? 0);
    if ((int)($businessSettings['loyalty_enabled'] ?? 0) !== 1) {
        $loyaltyRedeem = 0.0;
    }
    if (!$customer) {
        $loyaltyRedeem = 0.0;
    } else {
        $loyaltyRedeem = min($loyaltyRedeem, (float)($customer['loyalty_points'] ?? 0));
    }

    $netBeforeRound = max(0, round($sellingAmount - $billDiscountAmount - $loyaltyRedeem, 2));
    $grandTotal = round($netBeforeRound);
    $roundOff = round($grandTotal - $netBeforeRound, 2);

    $paymentsPayload = $payload['payments'] ?? [];
    if (!is_array($paymentsPayload)) {
        $paymentsPayload = [];
    }

    $serverPayments = [];
    $paidAmount = 0.0;
    foreach ($paymentsPayload as $payment) {
        $amount = pos_api_num($payment['paid_amount'] ?? $payment['amount'] ?? 0);
        $methodType = pos_api_clean_string($payment['method_type'] ?? 'cash', 20);
        $methodName = pos_api_clean_string($payment['payment_method_name'] ?? ucfirst($methodType), 100);
        $paymentMethodId = pos_api_resolve_payment_method($conn, $businessId, (int)($payment['payment_method_id'] ?? 0), $methodType, $methodName);

        if ($amount < 0) {
            throw new RuntimeException('Payment amount cannot be negative.');
        }

        if ($amount == 0 && $methodType !== 'credit') {
            continue;
        }

        $serverPayments[] = [
            'payment_method_id' => $paymentMethodId,
            'payment_method_name' => $methodName,
            'method_type' => $methodType,
            'paid_amount' => $amount,
            'reference_no' => pos_api_clean_string($payment['reference_no'] ?? '', 150),
            'payment_note' => pos_api_clean_string($payment['payment_note'] ?? $payment['note'] ?? '', 1000),
        ];

        if ($methodType !== 'credit') {
            $paidAmount += $amount;
        }
    }

    if (!$serverPayments) {
        $cashMethodId = pos_api_resolve_payment_method($conn, $businessId, 0, 'cash', 'Cash');
        $serverPayments[] = [
            'payment_method_id' => $cashMethodId,
            'payment_method_name' => 'Cash',
            'method_type' => 'cash',
            'paid_amount' => $grandTotal,
            'reference_no' => '',
            'payment_note' => '',
        ];
        $paidAmount = $grandTotal;
    }

    $paidAmount = round($paidAmount, 2);
    if ($paidAmount > $grandTotal) {
        $paidAmount = $grandTotal;
    }
    $balanceAmount = round(max(0, $grandTotal - $paidAmount), 2);

    $hasCredit = false;
    foreach ($serverPayments as $payment) {
        if ($payment['method_type'] === 'credit') {
            $hasCredit = true;
            break;
        }
    }

    if ($balanceAmount > 0 && (int)($businessSettings['partial_payment_enabled'] ?? 1) !== 1 && !$hasCredit) {
        throw new RuntimeException('Partial payment is disabled in business settings.');
    }

    if ($balanceAmount > 0 && !$customer && (int)($businessSettings['walkin_credit_enabled'] ?? 0) !== 1) {
        throw new RuntimeException('Credit or partial payment is not allowed for Walk-in Customer. Select or create a customer.');
    }

    $paymentStatus = 'paid';
    if ($grandTotal <= 0) {
        $paymentStatus = 'paid';
    } elseif ($paidAmount <= 0) {
        $paymentStatus = 'pending';
    } elseif ($paidAmount < $grandTotal) {
        $paymentStatus = 'partial';
    }

    $billId = 0;
    $billNo = '';
    $maxBillAttempts = 25;
    for ($attempt = 0; $attempt < $maxBillAttempts; $attempt++) {
        $billNo = pos_api_next_sequence($conn, $businessId, $branchId, 'bill_no', (string)($branchSettings['invoice_prefix'] ?? 'BILL'), 4);

        try {
            $billId = pos_api_exec($conn, "
                INSERT INTO bills
                    (business_id, branch_id, bill_no, order_no, bill_date, bill_time, customer_id, customer_name, customer_mobile,
                     gst_type_key, invoice_title, mrp_total, item_discount_total, bill_discount_type, bill_discount_value,
                     bill_discount_amount, selling_amount, loyalty_redeem_amount, today_savings_amount, taxable_amount,
                     cgst_amount, sgst_amount, igst_amount, tax_amount, round_off, net_amount, paid_amount, balance_amount,
                     payment_status, updated_status, bill_status, print_count, created_by)
                VALUES
                    (?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?,
                     ?, ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?,
                     ?, ?, ?, ?, ?, ?, ?, ?,
                     ?, 'original', 'active', 0, ?)
            ", 'iississssddsddddddddddddddsi', [
                $businessId,
                $branchId,
                $billNo,
                'ORD-' . $billNo,
                $customerId > 0 ? $customerId : null,
                $customerName,
                $customerMobile,
                (string)$invoiceSettings['gst_type_key'],
                (string)$invoiceSettings['invoice_title'],
                round($mrpTotal, 2),
                round($itemDiscountTotal, 2),
                $billDiscountType,
                $billDiscountValue,
                $billDiscountAmount,
                round($sellingAmount, 2),
                $loyaltyRedeem,
                round($todaySavings + $billDiscountAmount + $loyaltyRedeem, 2),
                ((string)$invoiceSettings['gst_type_key'] === 'gst_regular') ? $netBeforeRound : 0.0,
                0.0,
                0.0,
                0.0,
                0.0,
                $roundOff,
                $grandTotal,
                $paidAmount,
                $balanceAmount,
                $paymentStatus,
                $userId,
            ]);
            break;
        } catch (Throwable $e) {
            if (!pos_api_is_duplicate_key($e)) {
                throw $e;
            }
            // Sequence may be behind existing imported/manual bills. Try the next number.
            $billId = 0;
        }
    }

    if ($billId <= 0 || $billNo === '') {
        throw new RuntimeException('Unable to generate a unique bill number. Please check number_sequences.');
    }

    $billBarcodeValue = '';
    $maxBarcodeAttempts = 50;
    for ($attempt = 0; $attempt < $maxBarcodeAttempts; $attempt++) {
        $billBarcodeValue = pos_api_next_sequence($conn, $businessId, $branchId, 'bill_barcode', (string)($barcodeSettings['bill_barcode_prefix'] ?? 'BILL'), 6);

        try {
            // INSERT IGNORE prevents the user-facing duplicate-key error when an old/imported
            // bill barcode already exists. If ignored, we immediately generate the next number.
            $insertedBarcodeId = pos_api_exec($conn, "
                INSERT IGNORE INTO bill_barcodes (business_id, branch_id, bill_id, barcode_value, barcode_status)
                VALUES (?, ?, ?, ?, 'active')
            ", 'iiis', [$businessId, $branchId, $billId, $billBarcodeValue]);
            if ($insertedBarcodeId > 0) {
                break;
            }
            $billBarcodeValue = '';
        } catch (Throwable $e) {
            if (!pos_api_is_duplicate_key($e)) {
                throw $e;
            }
            $billBarcodeValue = '';
            // Duplicate barcode exists. Regenerate and retry without failing the bill save.
        }
    }

    if ($billBarcodeValue === '') {
        throw new RuntimeException('Unable to generate a unique bill barcode. Please check bill_barcodes and number_sequences.');
    }

    foreach ($serverItems as $serverItem) {
        pos_api_exec($conn, "
            INSERT INTO bill_items
                (business_id, branch_id, bill_id, stock_batch_id, stock_item_id, barcode_id, article_no, article_name,
                 brand_id, size, qty, mrp_rate, discount_type, discount_value, discount_amount, selling_rate,
                 amount, taxable_amount, cgst_amount, sgst_amount, igst_amount, tax_amount)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", 'iiiiiissisddsddddddddd', [
            $businessId,
            $branchId,
            $billId,
            $serverItem['stock_batch_id'],
            $serverItem['stock_item_id'],
            $serverItem['barcode_id'],
            $serverItem['article_no'],
            $serverItem['article_name'],
            $serverItem['brand_id'],
            $serverItem['size'],
            $serverItem['qty'],
            $serverItem['mrp_rate'],
            $serverItem['discount_type'],
            $serverItem['discount_value'],
            $serverItem['discount_amount'],
            $serverItem['selling_rate'],
            $serverItem['amount'],
            $serverItem['taxable_amount'],
            $serverItem['cgst_amount'],
            $serverItem['sgst_amount'],
            $serverItem['igst_amount'],
            $serverItem['tax_amount'],
        ]);

        pos_api_exec($conn, "
            UPDATE stock_inward_items
            SET available_qty = available_qty - ?,
                item_status = CASE WHEN available_qty - ? <= 0 THEN 'out_of_stock' ELSE 'active' END,
                updated_at = NOW()
            WHERE business_id = ?
              AND branch_id = ?
              AND stock_item_id = ?
        ", 'ddiii', [$serverItem['qty'], $serverItem['qty'], $businessId, $branchId, $serverItem['stock_item_id']]);

        $balanceRow = pos_api_one($conn, "
            SELECT available_qty
            FROM stock_inward_items
            WHERE stock_item_id = ?
            LIMIT 1
        ", 'i', [$serverItem['stock_item_id']]);

        pos_api_exec($conn, "
            INSERT INTO stock_movements
                (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, qty_in, qty_out, balance_qty, remarks, created_by)
            VALUES
                (?, ?, ?, 'sale', 'bill', ?, 0.00, ?, ?, ?, ?)
        ", 'iiiiddsi', [
            $businessId,
            $branchId,
            $serverItem['stock_item_id'],
            $billId,
            $serverItem['qty'],
            (float)($balanceRow['available_qty'] ?? 0),
            'POS Bill ' . $billNo,
            $userId,
        ]);

        if ($serverItem['barcode_id']) {
            pos_api_exec($conn, "
                UPDATE stock_barcodes
                SET barcode_status = 'used'
                WHERE barcode_id = ?
                  AND business_id = ?
                  AND branch_id = ?
            ", 'iii', [$serverItem['barcode_id'], $businessId, $branchId]);
        }
    }

    foreach ($serverPayments as $payment) {
        $paymentId = pos_api_exec($conn, "
            INSERT INTO bill_payments
                (business_id, branch_id, bill_id, payment_method_id, paid_amount, reference_no, payment_note, payment_status, collected_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 'received', ?)
        ", 'iiiidssi', [
            $businessId,
            $branchId,
            $billId,
            $payment['payment_method_id'],
            $payment['paid_amount'],
            $payment['reference_no'],
            $payment['payment_note'],
            $userId,
        ]);

        if ($payment['paid_amount'] > 0 && $userId) {
            pos_api_exec($conn, "
                INSERT INTO cashier_collections
                    (business_id, branch_id, cashier_id, bill_id, payment_id, collected_amount, payment_method_id, collection_status)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?)
            ", 'iiiiidis', [
                $businessId,
                $branchId,
                $userId,
                $billId,
                $paymentId,
                $payment['paid_amount'],
                $payment['payment_method_id'],
                $paymentStatus === 'partial' ? 'partial' : 'paid',
            ]);
        }
    }

    if ($customerId > 0) {
        $out = pos_api_one($conn, "
            SELECT balance_amount
            FROM customer_outstanding
            WHERE business_id = ? AND customer_id = ?
            LIMIT 1
            FOR UPDATE
        ", 'ii', [$businessId, $customerId]);

        $previousBalance = (float)($out['balance_amount'] ?? 0);
        $newBalance = round($previousBalance + $balanceAmount, 2);

        pos_api_exec($conn, "
            INSERT INTO customer_outstanding (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_bill_amount = total_bill_amount + VALUES(total_bill_amount),
                total_paid_amount = total_paid_amount + VALUES(total_paid_amount),
                balance_amount = balance_amount + VALUES(balance_amount),
                updated_at = NOW()
        ", 'iiddd', [$businessId, $customerId, $grandTotal, $paidAmount, $balanceAmount]);

        pos_api_exec($conn, "
            INSERT INTO customer_ledger
                (business_id, branch_id, customer_id, reference_type, reference_id, debit, credit, balance, remarks, created_by)
            VALUES
                (?, ?, ?, 'bill', ?, ?, ?, ?, ?, ?)
        ", 'iiiidddsi', [
            $businessId,
            $branchId,
            $customerId,
            $billId,
            $grandTotal,
            $paidAmount,
            $newBalance,
            'POS Bill ' . $billNo,
            $userId,
        ]);

        pos_api_exec($conn, "
            INSERT INTO payment_ledger
                (business_id, branch_id, customer_id, bill_id, transaction_type, debit, credit, balance, payment_method_id, remarks, created_by)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)
        ", 'iiiisdddsi', [
            $businessId,
            $branchId,
            $customerId,
            $billId,
            $balanceAmount > 0 ? 'partial_payment' : 'bill',
            $grandTotal,
            $paidAmount,
            $newBalance,
            'POS Bill ' . $billNo,
            $userId,
        ]);

        if ($loyaltyRedeem > 0) {
            pos_api_exec($conn, "
                UPDATE customers
                SET loyalty_points = GREATEST(0, loyalty_points - ?), updated_at = NOW()
                WHERE business_id = ? AND customer_id = ?
            ", 'dii', [$loyaltyRedeem, $businessId, $customerId]);
        }
    }

    $holdId = (int)($payload['hold_id'] ?? 0);
    if ($holdId > 0) {
        pos_api_exec($conn, "
            UPDATE pos_bill_holds
            SET hold_status = 'converted', updated_at = NOW()
            WHERE business_id = ? AND branch_id = ? AND hold_id = ?
        ", 'iii', [$businessId, $branchId, $holdId]);
    }

    pos_api_activity_log($conn, $businessId, $branchId, 'POS Create Bill', 'bill_created', $billId, null, [
        'bill_no' => $billNo,
        'grand_total' => $grandTotal,
        'payment_status' => $paymentStatus,
        'item_count' => count($serverItems),
    ]);

    $savedBill = pos_api_get_bill($conn, $businessId, $branchId, $billId);
    return [
        'bill_id' => $billId,
        'bill_no' => $billNo,
        'bill_barcode' => $billBarcodeValue,
        'payment_status' => $paymentStatus,
        'grand_total' => $grandTotal,
        'bill' => $savedBill,
    ];
}

try {
    if (!pos_api_csrf_ok()) {
        pos_api_json(['success' => false, 'message' => 'Security token expired. Refresh the page and try again.'], 419);
    }

    $businessId = pos_api_business_id();
    if ($businessId <= 0) {
        pos_api_json(['success' => false, 'message' => 'Business session missing. Please login again.'], 401);
    }

    $userId = pos_api_user_id();
    $action = pos_api_action();
    $payload = pos_api_input_payload();

    $requestedBranchId = (int)($_GET['branch_id'] ?? $_POST['branch_id'] ?? $payload['branch_id'] ?? pos_api_branch_id());
    $branchId = $requestedBranchId > 0 ? $requestedBranchId : pos_api_branch_id();

    if ($branchId <= 0) {
        $branches = pos_api_fetch_branches($conn, $businessId, $userId);
        $branchId = (int)($branches[0]['branch_id'] ?? 0);
    }

    if ($action === '') {
        pos_api_json(['success' => false, 'message' => 'API action is required.'], 400);
    }

    switch ($action) {
        case 'bootstrap':
            $branches = pos_api_fetch_branches($conn, $businessId, $userId);
            if (!$branchId && $branches) {
                $branchId = (int)$branches[0]['branch_id'];
            }
            $branchSettings = pos_api_fetch_branch_settings($conn, $businessId, $branchId);
            $barcodeSettings = pos_api_fetch_barcode_settings($conn, $businessId, $branchId);
            pos_api_json([
                'success' => true,
                'business' => pos_api_fetch_business($conn, $businessId),
                'business_settings' => pos_api_fetch_business_settings($conn, $businessId),
                'invoice_settings' => pos_api_fetch_invoice_settings($conn, $businessId, $branchId),
                'barcode_settings' => $barcodeSettings,
                'branches' => $branches,
                'selected_branch_id' => $branchId,
                'payment_methods' => pos_api_fetch_payment_methods($conn, $businessId),
                'next_bill_no' => pos_api_peek_sequence($conn, $businessId, $branchId, 'bill_no', (string)($branchSettings['invoice_prefix'] ?? 'BILL'), 4),
                'next_bill_barcode' => pos_api_peek_sequence($conn, $businessId, $branchId, 'bill_barcode', (string)($barcodeSettings['bill_barcode_prefix'] ?? 'BILL'), 6),
                'held_bills' => pos_api_list_holds($conn, $businessId, $branchId),
                'server_time' => date('Y-m-d H:i:s'),
            ]);
            break;

        case 'next_bill_no':
            $branchSettings = pos_api_fetch_branch_settings($conn, $businessId, $branchId);
            $barcodeSettings = pos_api_fetch_barcode_settings($conn, $businessId, $branchId);
            pos_api_json([
                'success' => true,
                'next_bill_no' => pos_api_peek_sequence($conn, $businessId, $branchId, 'bill_no', (string)($branchSettings['invoice_prefix'] ?? 'BILL'), 4),
                'next_bill_barcode' => pos_api_peek_sequence($conn, $businessId, $branchId, 'bill_barcode', (string)($barcodeSettings['bill_barcode_prefix'] ?? 'BILL'), 6),
            ]);
            break;

        case 'search_products':
            pos_api_json([
                'success' => true,
                'products' => pos_api_search_products($conn, $businessId, $branchId, (string)($_GET['q'] ?? $_GET['search'] ?? '')),
            ]);
            break;

        case 'get_product_options':
            $stockItemId = (int)($_GET['stock_item_id'] ?? 0);
            pos_api_json([
                'success' => true,
                'options' => pos_api_get_product_options($conn, $businessId, $branchId, $stockItemId),
            ]);
            break;

        case 'scan_product':
            pos_api_json([
                'success' => true,
                'scan' => pos_api_scan_product($conn, $businessId, $branchId, (string)($_GET['code'] ?? $_GET['barcode'] ?? '')),
            ]);
            break;

        case 'search_customers':
            pos_api_json([
                'success' => true,
                'customers' => pos_api_search_customers($conn, $businessId, (string)($_GET['q'] ?? $_GET['search'] ?? '')),
            ]);
            break;

        case 'save_customer':
            mysqli_begin_transaction($conn);
            $customer = pos_api_save_customer($conn, $businessId, $payload);
            pos_api_activity_log($conn, $businessId, $branchId, 'Customers', 'pos_customer_saved', (int)$customer['customer_id'], null, $customer);
            mysqli_commit($conn);
            pos_api_json(['success' => true, 'customer' => $customer, 'message' => 'Customer saved successfully.']);
            break;

        case 'validate_offer':
            $offer = pos_api_validate_offer($conn, $businessId, (string)($_GET['code'] ?? $payload['code'] ?? ''));
            pos_api_json(['success' => true, 'offer' => $offer, 'message' => 'Offer applied.']);
            break;

        case 'save_hold':
            mysqli_begin_transaction($conn);
            $hold = pos_api_save_hold($conn, $businessId, $branchId, $payload);
            mysqli_commit($conn);
            pos_api_json(['success' => true, 'hold' => $hold, 'held_bills' => pos_api_list_holds($conn, $businessId, $branchId), 'message' => 'Bill held successfully.']);
            break;

        case 'list_holds':
            pos_api_json(['success' => true, 'held_bills' => pos_api_list_holds($conn, $businessId, $branchId)]);
            break;

        case 'resume_hold':
            $holdId = (int)($_GET['hold_id'] ?? $payload['hold_id'] ?? 0);
            pos_api_json(['success' => true, 'hold_data' => pos_api_resume_hold($conn, $businessId, $branchId, $holdId)]);
            break;

        case 'cancel_hold':
            mysqli_begin_transaction($conn);
            pos_api_cancel_hold($conn, $businessId, $branchId, (int)($payload['hold_id'] ?? $_POST['hold_id'] ?? 0));
            mysqli_commit($conn);
            pos_api_json(['success' => true, 'held_bills' => pos_api_list_holds($conn, $businessId, $branchId), 'message' => 'Held bill cancelled.']);
            break;

        case 'save_bill':
            mysqli_begin_transaction($conn);
            $saved = pos_api_save_bill($conn, $businessId, $branchId, $payload);
            mysqli_commit($conn);
            pos_api_json(['success' => true, 'message' => 'Bill saved successfully.', 'saved' => $saved]);
            break;

        case 'customer_history':
            $customerId = (int)($_GET['customer_id'] ?? $payload['customer_id'] ?? 0);
            pos_api_json([
                'success' => true,
                'history' => pos_api_customer_purchase_history($conn, $businessId, $branchId, $customerId),
            ]);
            break;

        case 'get_bill':
            $billId = (int)($_GET['bill_id'] ?? 0);
            $bill = pos_api_get_bill($conn, $businessId, $branchId, $billId);
            if (!$bill) {
                pos_api_json(['success' => false, 'message' => 'Bill not found.'], 404);
            }
            pos_api_json(['success' => true, 'bill' => $bill]);
            break;

        case 'get_bill_by_barcode':
            $scan = pos_api_scan_product($conn, $businessId, $branchId, (string)($_GET['barcode'] ?? $_GET['code'] ?? ''));
            if (($scan['type'] ?? '') !== 'bill') {
                pos_api_json(['success' => false, 'message' => 'Bill barcode not found.'], 404);
            }
            pos_api_json(['success' => true, 'bill' => $scan['bill']]);
            break;

        default:
            pos_api_json(['success' => false, 'message' => 'Invalid API action: ' . $action], 400);
    }
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            mysqli_rollback($conn);
        } catch (Throwable $ignored) {
        }
    }
    pos_api_json([
        'success' => false,
        'message' => $e->getMessage(),
    ], 500);
}
