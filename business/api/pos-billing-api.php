<?php
/**
 * GK Footwear POS Billing API
 * Place this file at: api/pos-billing-api.php
 * 
 * Supports: Create Bill Printing & Collection Payment Printing
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

// Check if controller exists before requiring
$controllerPath = __DIR__ . '/../controllers/PosBillingController.php';
if (!file_exists($controllerPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false, 
        'message' => 'PosBillingController.php not found at: ' . $controllerPath
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once $controllerPath;

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
    if ($types === '') { return; }
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
    $selectedBranchId = pos_api_branch_id($params);

    if ($businessId <= 0) {
        return array('success' => false, 'message' => 'Business session missing. Please login again.');
    }

    if ($stockItemId <= 0) {
        return array('success' => false, 'message' => 'Invalid product selected.');
    }

    $baseSql = "
        SELECT stock_item_id, business_id, branch_id, category_id, brand_id, article_no
        FROM stock_inward_items
        WHERE business_id = ?
          AND stock_item_id = ?
          AND (? = 0 OR branch_id = ?)
        LIMIT 1
    ";
    $baseStmt = mysqli_prepare($conn, $baseSql);
    if (!$baseStmt) {
        return array('success' => false, 'message' => 'Product option base query error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($baseStmt, 'iiii', $businessId, $stockItemId, $selectedBranchId, $selectedBranchId);
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

/** Check matching available stock in all branches except the currently selected branch. */
function pos_api_other_branch_stock(mysqli $conn, $businessId, array $params)
{
    $businessId = (int)$businessId;
    $selectedBranchId = pos_api_branch_id($params);
    $query = trim((string)($params['q'] ?? $params['search'] ?? $params['term'] ?? ''));

    if ($businessId <= 0) {
        return array('success' => false, 'message' => 'Business session missing. Please login again.');
    }
    if ($query === '') {
        return array('success' => false, 'message' => 'Product search value is required.');
    }

    $like = '%' . $query . '%';
    $sql = "
        SELECT
            br.branch_id, br.branch_name, br.floor_name,
            COALESCE(SUM(si.available_qty), 0) AS available_qty
        FROM stock_inward_items si
        INNER JOIN stock_inward_batches sib
            ON sib.batch_id = si.batch_id
           AND sib.business_id = si.business_id
           AND sib.branch_id = si.branch_id
        INNER JOIN branches br
            ON br.branch_id = si.branch_id
           AND br.business_id = si.business_id
           AND br.status = 1
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
        WHERE si.business_id = ?
          AND si.item_status = 'active'
          AND si.available_qty > 0
          AND (? = 0 OR si.branch_id <> ?)
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
        GROUP BY br.branch_id, br.branch_name, br.floor_name
        HAVING available_qty > 0
        ORDER BY br.branch_name ASC, br.floor_name ASC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return array('success' => false, 'message' => 'Other branch stock query error: ' . mysqli_error($conn));
    }
    $types = 'iiissssssss';
    $bind = array($businessId, $selectedBranchId, $selectedBranchId, $like, $like, $like, $like, $like, $like, $like, $like);
    pos_api_bind_params($stmt, $types, $bind);
    $rows = pos_api_fetch_all($stmt);
    foreach ($rows as &$row) {
        $row['branch_id'] = (int)$row['branch_id'];
        $row['available_qty'] = (float)$row['available_qty'];
    }
    unset($row);

    return array('success' => true, 'branches' => $rows);
}

/**
 * Get branch name with floor name for display
 */
function pos_api_get_branch_display_name(mysqli $conn, $branchId, $businessId)
{
    if (empty($branchId) || empty($businessId)) {
        return '';
    }
    
    $query = "SELECT branch_name, floor_name FROM branches WHERE branch_id = ? AND business_id = ? AND status = 1";
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        return '';
    }
    
    mysqli_stmt_bind_param($stmt, 'ii', $branchId, $businessId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if ($row) {
        $branchName = $row['branch_name'] ?? '';
        $floorName = $row['floor_name'] ?? '';
        return trim($branchName . ($floorName ? ' ' . $floorName : ''));
    }
    
    return '';
}


/** Name-only registered customer search for Create Bill. */
function pos_api_search_customers_enhanced(mysqli $conn, $businessId, array $params)
{
    $businessId=(int)$businessId; $branchId=pos_api_branch_id($params);
    $query=trim((string)($params['q']??$params['search']??$params['term']??''));
    $limit=max(1,min(20,(int)($params['limit']??10)));
    if ($businessId<=0) return array('success'=>false,'message'=>'Business session missing.');
    $where="c.business_id = ? AND c.status = 1"; $types='i'; $bind=array($businessId);
    if ($query!=='') { $where.=" AND c.customer_name LIKE ?"; $types.='s'; $bind[]='%'.$query.'%'; }
    $branchJoin='';
    if ($branchId>0) { $branchJoin=' AND b.branch_id = ?'; $types='i'.$types; array_unshift($bind,$branchId); }
    $sql="SELECT c.customer_id,c.customer_name,c.mobile,c.email,c.address,c.gstin,c.opening_outstanding,c.loyalty_points,'registered' AS customer_type,COUNT(b.bill_id) AS total_bills,COUNT(b.bill_id) AS purchase_count,MAX(b.bill_date) AS last_purchase_date,COALESCE(SUM(b.net_amount),0) AS total_purchase_amount,COALESCE(c.opening_outstanding,0)+COALESCE(SUM(CASE WHEN b.bill_status='active' AND b.balance_amount>0 THEN b.balance_amount ELSE 0 END),0) AS outstanding_balance FROM customers c LEFT JOIN bills b ON b.business_id=c.business_id AND b.customer_id=c.customer_id AND b.bill_status='active' {$branchJoin} WHERE {$where} GROUP BY c.customer_id,c.customer_name,c.mobile,c.email,c.address,c.gstin,c.opening_outstanding,c.loyalty_points ORDER BY CASE WHEN ?<>'' AND LOWER(c.customer_name)=LOWER(?) THEN 0 ELSE 1 END,c.customer_name ASC LIMIT {$limit}";
    $types.='ss'; $bind[]=$query; $bind[]=$query;
    $stmt=mysqli_prepare($conn,$sql); if(!$stmt) return array('success'=>false,'message'=>'Customer search query error: '.mysqli_error($conn));
    pos_api_bind_params($stmt,$types,$bind); $customers=pos_api_fetch_all($stmt); $exact=null;
    foreach($customers as &$row){$row['customer_id']=(int)$row['customer_id'];$row['total_bills']=(int)$row['total_bills'];$row['purchase_count']=(int)$row['purchase_count'];$row['total_purchase_amount']=(float)$row['total_purchase_amount'];$row['outstanding_balance']=(float)$row['outstanding_balance'];$row['loyalty_points']=(float)$row['loyalty_points'];$row['is_walkin_customer']=0;if($query!==''&&strcasecmp(trim((string)$row['customer_name']),$query)===0)$exact=$row;} unset($row);
    return array('success'=>true,'customers'=>$customers,'registered_customers'=>$customers,'walkin_customers'=>array(),'exact_customer'=>$exact,'exact_walkin'=>null,'query_type'=>'name');
}


function pos_api_thermal_print_success($httpCode, $responseText)
{
    $httpCode=(int)$httpCode; $text=trim((string)$responseText); $upper=strtoupper($text);
    if (strpos($upper,'ERROR')!==false || strpos($upper,'FAIL')!==false || strpos($upper,'EXCEPTION')!==false) return false;
    $json=json_decode($text,true);
    if (is_array($json)) {
        if (!empty($json['success']) || !empty($json['printed']) || strtolower((string)($json['status']??''))==='success') return true;
        $nested=strtoupper(trim((string)($json['print_response']??($json['data']['print_response']??''))));
        if (strpos($nested,'PRINT_SUCCESS')!==false || strpos($nested,'PRINT SUCCESS')!==false || strpos($nested,'PRINTED')!==false || $nested==='OK') return true;
    }
    if (strpos($upper,'PRINT_SUCCESS')!==false || strpos($upper,'PRINT SUCCESS')!==false || strpos($upper,'PRINTED')!==false || $upper==='OK') return true;
    return $httpCode>=200 && $httpCode<300;
}

// ============================================
// MAIN API HANDLER
// ============================================
try {
    // Check if user is logged in
    if (function_exists('require_business_login')) { 
        require_business_login(); 
    }

    // Check page access
    if (function_exists('require_page_access')) { 
        require_page_access($conn, 'bill-create.php'); 
    }

    $businessId = pos_api_business_id();
    if ($businessId <= 0) { 
        pos_api_json(array('success' => false, 'message' => 'Business session missing. Please login again.'), 401); 
    }

    // Check if controller class exists
    if (!class_exists('PosBillingController')) {
        pos_api_json(array(
            'success' => false, 
            'message' => 'PosBillingController class not found. Please check the controller file at: controllers/PosBillingController.php'
        ), 500);
    }

    // Initialize controller
    try {
        $controller = new PosBillingController($conn, $businessId, pos_api_user_id(), pos_api_is_admin($conn));
    } catch (Exception $e) {
        pos_api_json(array(
            'success' => false, 
            'message' => 'Failed to initialize PosBillingController: ' . $e->getMessage()
        ), 500);
    }

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = $method === 'POST' ? (string)($_POST['action'] ?? '') : (string)($_GET['action'] ?? 'bootstrap');

    // ============================================
    // POST REQUESTS
    // ============================================
    if ($method === 'POST') {
        pos_api_verify_csrf();
        $payload = pos_api_payload();
        
        if ($action === 'save_bill') 
        {
            // Save bill first
            $result = $controller->saveBill($payload);

            // If bill saved successfully then print
            if(isset($result['success']) && $result['success'] === true)
            {
                $savedBill = $result['saved']['bill'] ?? array();
                $savedBillId = $result['saved']['bill_id'] ?? 0;
                
                // Get branch name
                $branchName = '';
                if (!empty($payload['branch_id'])) {
                    $branchName = pos_api_get_branch_display_name($conn, $payload['branch_id'], $businessId);
                }
                if (empty($branchName) && !empty($savedBill['branch_name'])) {
                    $branchName = $savedBill['branch_name'];
                }
                
                // Get sales user name
                $salesUserName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Sales User';
                if (!empty($payload['sales_user'])) {
                    $salesUserName = $payload['sales_user'];
                }
                
                // Build complete print data with PrintType
                $printData = array(
                    "PrintType" => "CREATE_BILL",
                    "ShopName" => $result['saved']['business_name'] ?? 'GK FOOTWEAR',
                    "Address" => $result['saved']['business_address'] ?? 'Gandhi Nagar, Krishnagiri.',
                    "InvoiceTitle" => $savedBill['invoice_title'] ?? 'Bill of Supply',
                    "BillNo" => $savedBill['bill_no'] ?? '',
                    "OrderNo" => $savedBill['order_no'] ?? 'ORD-' . ($savedBill['bill_no'] ?? ''),
                    "Date" => date('d-m-Y'),
                    "Time" => date('h.i.s A'),
                    "Customer" => $savedBill['customer_name'] ?? ($payload['customer_name'] ?? 'Walk-in Customer'),
                    "Branch" => $branchName ?: 'N/A',
                    "Salesman" => $salesUserName,
                    "CollectedBy" => '',
                    "PaymentStatus" => strtoupper($savedBill['payment_status'] ?? 'PENDING'),
                    "PaymentMethod" => '',
                    "GrandTotal" => (float)($savedBill['net_amount'] ?? 0),
                    "Paid" => (float)($savedBill['paid_amount'] ?? 0),
                    "Balance" => (float)($savedBill['balance_amount'] ?? 0),
                    "Barcode" => $savedBill['bill_barcode'] ?? $savedBill['barcode_value'] ?? '',
                    "Items" => array()
                );

                // Prepare items for thermal printer
                if(isset($payload['items']) && is_array($payload['items']))
                {
                    foreach($payload['items'] as $item)
                    {
                        $description = '';
                        if (!empty($item['article_no'])) {
                            $description .= $item['article_no'];
                        }
                        if (!empty($item['brand_name'])) {
                            $description .= ($description ? ' / ' : '') . $item['brand_name'];
                        }
                        if (!empty($item['size'])) {
                            $description .= ($description ? ' / ' : '') . 'Size ' . $item['size'];
                        }

                        $printData['Items'][] = array(
                            "Name" => $item['article_name'] ?? $item['name'] ?? 'Item',
                            "Description" => $description,
                            "Qty" => (float)($item['qty'] ?? 1),
                            "Rate" => (float)($item['selling_rate'] ?? $item['rate'] ?? 0)
                        );
                    }
                }

                // Send data to local .NET Thermal Print Service
                try {
                    $ch=curl_init();
                    curl_setopt_array($ch,array(
                        CURLOPT_URL=>"http://127.0.0.1:17900/", CURLOPT_POST=>true,
                        CURLOPT_POSTFIELDS=>json_encode($printData,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                        CURLOPT_HTTPHEADER=>array("Content-Type: application/json","Accept: application/json, text/plain, */*","X-Print-Job-Id: GK-BILL-".(int)$savedBillId."-".time()),
                        CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT_MS=>1500,
                        CURLOPT_TIMEOUT_MS=>5000, CURLOPT_FAILONERROR=>false
                    ));
                    $printResponse=curl_exec($ch); $curlErrno=curl_errno($ch); $curlError=curl_error($ch);
                    $httpCode=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
                    $printSuccess=false;
                    if ($curlErrno===0) $printSuccess=pos_api_thermal_print_success($httpCode,$printResponse);
                    $message=$curlErrno!==0 ? ('Printer Error: '.$curlError) : ($printSuccess ? 'Bill printed successfully.' : (trim((string)$printResponse)!==''?trim((string)$printResponse):'The thermal print service did not confirm printing.'));
                    $result['thermal_print']=(string)$printResponse;
                    $result['thermal_print_success']=$printSuccess;
                    $result['thermal_print_result']=array('attempted'=>true,'success'=>$printSuccess,'printed'=>$printSuccess,'status'=>$printSuccess?'success':'failed','http_code'=>$httpCode,'response'=>(string)$printResponse,'message'=>$message);
                } catch (Throwable $e) {
                    $result['thermal_print']='Print Service Error: '.$e->getMessage();
                    $result['thermal_print_success']=false;
                    $result['thermal_print_result']=array('attempted'=>true,'success'=>false,'printed'=>false,'status'=>'failed','http_code'=>0,'response'=>'','message'=>$e->getMessage());
                }
            }

            // Return final response
            pos_api_json($result);
        }
        elseif ($action === 'save_hold') { pos_api_json($controller->saveWorkflow($payload, 'hold')); }
        elseif ($action === 'save_draft') { pos_api_json($controller->saveWorkflow($payload, 'draft')); }
        elseif ($action === 'cancel_current_bill') { pos_api_json($controller->saveWorkflow($payload, 'cancelled')); }
        elseif ($action === 'cancel_workflow' || $action === 'cancel_hold') { pos_api_json($controller->cancelWorkflow($payload)); }
        elseif ($action === 'cancel_saved_bill') { pos_api_json($controller->cancelSavedBill($payload)); }
        elseif ($action === 'return_saved_bill') { pos_api_json($controller->returnSavedBill($payload)); }
        elseif ($action === 'save_customer') { pos_api_json($controller->saveCustomer($payload)); }
        else {
            pos_api_json(array('success' => false, 'message' => 'Invalid POST action: ' . $action), 400);
        }
    }

    // ============================================
    // GET REQUESTS
    // ============================================
    if ($method === 'GET') {
        if ($action === 'bootstrap') { 
            pos_api_json($controller->bootstrap($_GET)); 
        }
        elseif ($action === 'search_products') { 
            pos_api_json(pos_api_search_products($conn, $businessId, $_GET)); 
        }
        elseif ($action === 'get_product_options') { 
            pos_api_json(pos_api_product_options($conn, $businessId, $_GET)); 
        }
        elseif ($action === 'other_branch_stock') { 
            pos_api_json(pos_api_other_branch_stock($conn, $businessId, $_GET)); 
        }
        elseif ($action === 'scan_product') { 
            pos_api_json($controller->scan($_GET)); 
        }
        elseif ($action === 'search_customers') { 
            pos_api_json(pos_api_search_customers_enhanced($conn, $businessId, $_GET)); 
        }
        elseif ($action === 'resume_workflow' || $action === 'resume_hold') { 
            pos_api_json($controller->resumeWorkflow($_GET)); 
        }
        elseif ($action === 'bill_history') { 
            pos_api_json($controller->history($_GET)); 
        }
        elseif ($action === 'validate_offer') { 
            pos_api_json($controller->validateOffer($_GET)); 
        }
        // ============================================
        // NEW: GET BILL FOR PRINT - Added for direct reprint
        // ============================================
        elseif ($action === 'get_bill_for_print') { 
            pos_api_json($controller->getBillForPrint($_GET)); 
        }
        else {
            pos_api_json(array('success' => false, 'message' => 'Invalid GET action: ' . $action), 400);
        }
    }

    pos_api_json(array('success' => false, 'message' => 'Invalid POS billing API action.'), 400);

} catch (Throwable $e) {
    // Log the error for debugging
    error_log("POS Billing API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    pos_api_json(array(
        'success' => false, 
        'message' => 'POS Billing API error: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ), 500);
}