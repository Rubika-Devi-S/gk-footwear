<?php
/**
 * GK Footwear POS Billing API
 * Place this file at: api/pos-billing-api.php
 *
 * Fix included:
 * - search_products returns stock_entry_date / entry_date / inward_date
 * - get_product_options returns stock_entry_date / entry_date / inward_date
 * - joins stock_inward_batches so newly added stock shows exact inward/entry date
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../controllers/PosBillingController.php';

/*
 * POS billing must save date/time in India time.
 * date_default_timezone_set fixes PHP date()/time(); SET time_zone fixes MySQL NOW()/CURTIME() for this connection.
 */
if (!defined('POS_BILLING_TIMEZONE')) { define('POS_BILLING_TIMEZONE', 'Asia/Kolkata'); }
date_default_timezone_set(POS_BILLING_TIMEZONE);
if (isset($conn) && $conn instanceof mysqli) {
    @mysqli_query($conn, "SET time_zone = '+05:30'");
}

function pos_api_json(array $payload, $statusCode = 200)
{
    http_response_code((int)$statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function pos_api_user_id()
{
    if (function_exists('current_user_id')) { return (int)current_user_id(); }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function pos_api_business_id()
{
    if (function_exists('current_business_id')) { return (int)current_business_id(); }
    return (int)($_SESSION['business_id'] ?? 0);
}

function pos_api_branch_id(array $params = array())
{
    if (isset($params['branch_id']) && (int)$params['branch_id'] > 0) {
        return (int)$params['branch_id'];
    }

    if (isset($params['firm_id']) && (int)$params['firm_id'] > 0) {
        return (int)$params['firm_id'];
    }

    if (function_exists('current_branch_id')) {
        $branchId = (int)current_branch_id();
        if ($branchId > 0) { return $branchId; }
    }

    return (int)($_SESSION['branch_id'] ?? $_SESSION['default_branch_id'] ?? 0);
}

function pos_api_is_admin($conn)
{
    if (function_exists('is_business_admin')) { return (bool)is_business_admin($conn); }
    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));
    return in_array($roleName, array('admin', 'business admin', 'branch admin'), true) || (int)($_SESSION['role_id'] ?? 0) === 1;
}

function pos_api_payload()
{
    $payload = array();
    if (isset($_POST['payload'])) {
        $decoded = json_decode((string)$_POST['payload'], true);
        if (is_array($decoded)) { $payload = $decoded; }
    }
    if (!$payload) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) { $payload = $decoded; }
    }
    foreach ($_POST as $key => $value) {
        if ($key === 'payload') { continue; }
        if (!array_key_exists($key, $payload)) { $payload[$key] = $value; }
    }
    return $payload;
}

function pos_api_verify_csrf()
{
    // Use the project's CSRF helper when present. Otherwise allow the request because older installs vary in helper names.
    if (function_exists('csrf_verify')) {
        if (!csrf_verify()) { pos_api_json(array('success' => false, 'message' => 'Invalid security token. Refresh and try again.'), 419); }
        return;
    }
    if (function_exists('verify_csrf_token')) {
        $token = (string)($_POST['csrf_token'] ?? $_POST['_csrf'] ?? $_POST['token'] ?? '');
        if (!verify_csrf_token($token)) { pos_api_json(array('success' => false, 'message' => 'Invalid security token. Refresh and try again.'), 419); }
        return;
    }
    if (function_exists('csrf_validate')) {
        $token = (string)($_POST['csrf_token'] ?? $_POST['_csrf'] ?? $_POST['token'] ?? '');
        if (!csrf_validate($token)) { pos_api_json(array('success' => false, 'message' => 'Invalid security token. Refresh and try again.'), 419); }
        return;
    }
}

function pos_api_bind_params(mysqli_stmt $stmt, $types, array $params)
{
    if ($types === '') {
        return;
    }

    $bind = array();
    $bind[] = $types;
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind);
}

function pos_api_fetch_all(mysqli_stmt $stmt)
{
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Common product SELECT.
 * IMPORTANT: Entry date fields are returned here for Create Bill page.
 */
function pos_api_product_select_sql()
{
    return "
        SELECT
            si.stock_item_id,
            si.business_id,
            si.branch_id,
            si.batch_id,
            si.category_id,
            si.brand_id,
            si.article_no,
            si.article_name,
            si.size,
            si.color,
            si.qty,
            si.available_qty,
            si.purchase_rate,
            si.mrp_rate,
            si.product_discount_type,
            si.product_discount_value,
            si.discount_amount,
            si.selling_rate,
            si.barcode_required,
            si.item_status,
            si.created_at,
            c.category_name,
            b.brand_name,
            COALESCE(si.stock_entry_date, sib.inward_date, DATE(si.created_at)) AS stock_entry_date,
            COALESCE(si.stock_entry_date, sib.inward_date, DATE(si.created_at)) AS entry_date,
            sib.inward_date AS inward_date,
            sib.batch_no AS batch_no,
            DATE_FORMAT(COALESCE(si.stock_entry_date, sib.inward_date, DATE(si.created_at)), '%d-%m-%Y') AS stock_entry_date_display,
            GROUP_CONCAT(DISTINCT sb.barcode_value ORDER BY sb.barcode_id SEPARATOR ', ') AS barcode_values,
            MIN(sb.barcode_value) AS barcode_value,
            MIN(sb.barcode_id) AS barcode_id,
            CASE WHEN si.available_qty > 0 AND si.available_qty <= 5 THEN 1 ELSE 0 END AS low_stock
        FROM stock_inward_items si
        INNER JOIN stock_inward_batches sib
            ON sib.batch_id = si.batch_id
           AND sib.business_id = si.business_id
           AND sib.branch_id = si.branch_id
        LEFT JOIN categories c
            ON c.category_id = si.category_id
           AND c.business_id = si.business_id
        LEFT JOIN brands b
            ON b.brand_id = si.brand_id
           AND b.business_id = si.business_id
        LEFT JOIN stock_barcodes sb
            ON sb.stock_item_id = si.stock_item_id
           AND sb.business_id = si.business_id
           AND sb.branch_id = si.branch_id
           AND sb.barcode_status = 'active'
    ";
}

function pos_api_search_products(mysqli $conn, $businessId, array $params)
{
    $businessId = (int)$businessId;
    $branchId = pos_api_branch_id($params);
    $query = trim((string)($params['q'] ?? $params['search'] ?? $params['term'] ?? ''));

    if ($businessId <= 0) {
        return array('success' => false, 'message' => 'Business session missing. Please login again.');
    }

    if ($query === '') {
        return array('success' => true, 'products' => array());
    }

    $where = "
        WHERE si.business_id = ?
          AND si.item_status = 'active'
          AND si.available_qty > 0
    ";
    $types = 'i';
    $bind = array($businessId);

    if ($branchId > 0) {
        $where .= " AND si.branch_id = ? ";
        $types .= 'i';
        $bind[] = $branchId;
    }

    $where .= "
        AND (
            si.article_no LIKE ?
            OR si.article_name LIKE ?
            OR si.size LIKE ?
            OR si.color LIKE ?
            OR sb.barcode_value LIKE ?
            OR b.brand_name LIKE ?
            OR c.category_name LIKE ?
            OR sib.batch_no LIKE ?
        )
    ";

    $like = '%' . $query . '%';
    for ($i = 0; $i < 8; $i++) {
        $types .= 's';
        $bind[] = $like;
    }

    $sql = pos_api_product_select_sql() . "
        {$where}
        GROUP BY si.stock_item_id
        ORDER BY si.stock_item_id DESC
        LIMIT 30
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return array('success' => false, 'message' => 'Product search query error: ' . mysqli_error($conn));
    }

    pos_api_bind_params($stmt, $types, $bind);
    $products = pos_api_fetch_all($stmt);

    return array(
        'success' => true,
        'products' => $products
    );
}

function pos_api_product_options(mysqli $conn, $businessId, array $params)
{
    $businessId = (int)$businessId;
    $stockItemId = (int)($params['stock_item_id'] ?? $params['id'] ?? 0);

    if ($businessId <= 0) {
        return array('success' => false, 'message' => 'Business session missing. Please login again.');
    }

    if ($stockItemId <= 0) {
        return array('success' => false, 'message' => 'Invalid product selected.');
    }

    // First get the selected stock row so options can be loaded for the same article/brand/category/branch.
    $baseSql = "
        SELECT stock_item_id, business_id, branch_id, category_id, brand_id, article_no
        FROM stock_inward_items
        WHERE business_id = ?
          AND stock_item_id = ?
        LIMIT 1
    ";
    $baseStmt = mysqli_prepare($conn, $baseSql);
    if (!$baseStmt) {
        return array('success' => false, 'message' => 'Product option base query error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($baseStmt, 'ii', $businessId, $stockItemId);
    mysqli_stmt_execute($baseStmt);
    $baseRow = mysqli_fetch_assoc(mysqli_stmt_get_result($baseStmt));
    mysqli_stmt_close($baseStmt);

    if (!$baseRow) {
        return array('success' => false, 'message' => 'Selected stock item not found.');
    }

    $branchId = (int)$baseRow['branch_id'];
    $categoryId = (int)($baseRow['category_id'] ?? 0);
    $brandId = (int)($baseRow['brand_id'] ?? 0);
    $articleNo = (string)($baseRow['article_no'] ?? '');

    $where = "
        WHERE si.business_id = ?
          AND si.branch_id = ?
          AND si.article_no = ?
          AND si.item_status = 'active'
          AND si.available_qty > 0
    ";
    $types = 'iis';
    $bind = array($businessId, $branchId, $articleNo);

    if ($categoryId > 0) {
        $where .= " AND si.category_id = ? ";
        $types .= 'i';
        $bind[] = $categoryId;
    }

    if ($brandId > 0) {
        $where .= " AND si.brand_id = ? ";
        $types .= 'i';
        $bind[] = $brandId;
    }

    $sql = pos_api_product_select_sql() . "
        {$where}
        GROUP BY si.stock_item_id
        ORDER BY
            CASE WHEN si.stock_item_id = ? THEN 0 ELSE 1 END,
            si.stock_item_id DESC
        LIMIT 100
    ";
    $types .= 'i';
    $bind[] = $stockItemId;

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return array('success' => false, 'message' => 'Product option query error: ' . mysqli_error($conn));
    }

    pos_api_bind_params($stmt, $types, $bind);
    $options = pos_api_fetch_all($stmt);

    return array(
        'success' => true,
        'options' => $options
    );
}

try {
    if (function_exists('require_business_login')) { require_business_login(); }
    if (function_exists('require_page_access')) { require_page_access($conn, 'bill-create.php'); }

    $businessId = pos_api_business_id();
    if ($businessId <= 0) { pos_api_json(array('success' => false, 'message' => 'Business session missing. Please login again.'), 401); }

    $controller = new PosBillingController($conn, $businessId, pos_api_user_id(), pos_api_is_admin($conn));
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = $method === 'POST' ? (string)($_POST['action'] ?? '') : (string)($_GET['action'] ?? 'bootstrap');

    if ($method === 'POST') {
        pos_api_verify_csrf();
        $payload = pos_api_payload();
        if ($action === 'save_bill') { pos_api_json($controller->saveBill($payload)); }
        if ($action === 'save_hold') { pos_api_json($controller->saveWorkflow($payload, 'hold')); }
        if ($action === 'save_draft') { pos_api_json($controller->saveWorkflow($payload, 'draft')); }
        if ($action === 'cancel_current_bill') { pos_api_json($controller->saveWorkflow($payload, 'cancelled')); }
        if ($action === 'cancel_workflow' || $action === 'cancel_hold') { pos_api_json($controller->cancelWorkflow($payload)); }
        if ($action === 'cancel_saved_bill') { pos_api_json($controller->cancelSavedBill($payload)); }
        if ($action === 'return_saved_bill') { pos_api_json($controller->returnSavedBill($payload)); }
        if ($action === 'save_customer') { pos_api_json($controller->saveCustomer($payload)); }
    }

    if ($method === 'GET') {
        if ($action === 'bootstrap') { pos_api_json($controller->bootstrap($_GET)); }

        // Entry date fix: handled directly here so the JSON always contains
        // stock_entry_date / entry_date / inward_date for Create Bill UI.
        if ($action === 'search_products') { pos_api_json(pos_api_search_products($conn, $businessId, $_GET)); }
        if ($action === 'get_product_options') { pos_api_json(pos_api_product_options($conn, $businessId, $_GET)); }

        if ($action === 'scan_product') { pos_api_json($controller->scan($_GET)); }
        if ($action === 'search_customers') { pos_api_json($controller->searchCustomers($_GET)); }
        if ($action === 'resume_workflow' || $action === 'resume_hold') { pos_api_json($controller->resumeWorkflow($_GET)); }
        if ($action === 'bill_history') { pos_api_json($controller->history($_GET)); }
        if ($action === 'validate_offer') { pos_api_json($controller->validateOffer($_GET)); }
    }

    pos_api_json(array('success' => false, 'message' => 'Invalid POS billing API action.'), 400);
} catch (Throwable $e) {
    pos_api_json(array('success' => false, 'message' => 'POS Billing API error: ' . $e->getMessage()), 500);
}
