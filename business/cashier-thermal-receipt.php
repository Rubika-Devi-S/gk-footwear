<?php
/**
 * GK Footwear - Thermal Receipt Generator for Pending Collections
 * Generates JSON data for the .NET thermal print service
 * 
 * FIX: Correctly calculates paid amount without doubling
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';

require_business_login();

$businessId = function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);
$branchId = function_exists('current_branch_id') ? (int)current_branch_id() : (int)($_SESSION['branch_id'] ?? 0);
$cashierName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Cashier';

// Get parameters
$billId = isset($_GET['bill_id']) ? max(0, (int)$_GET['bill_id']) : 0;
$autoPrint = isset($_GET['auto_print']) && $_GET['auto_print'] == '1';
$collectionAmount = isset($_GET['collection_amount']) ? (float)$_GET['collection_amount'] : 0;
$baseDueAmount = isset($_GET['base_due_amount']) ? (float)$_GET['base_due_amount'] : 0;
$collectionTotal = isset($_GET['collection_total_amount']) ? (float)$_GET['collection_total_amount'] : 0;
$paymentMethod = isset($_GET['payment_method_name']) ? trim($_GET['payment_method_name']) : '';
$collectedBy = isset($_GET['collected_by']) ? trim($_GET['collected_by']) : $cashierName;

// GST Parameters
$gstEnabled = isset($_GET['gst_enabled']) && (int)$_GET['gst_enabled'] === 1;
$gstMode = isset($_GET['gst_mode']) ? trim($_GET['gst_mode']) : 'intra';
$gstRate = isset($_GET['gst_rate']) ? (float)$_GET['gst_rate'] : 0;
$taxableAmount = isset($_GET['taxable_amount']) ? (float)$_GET['taxable_amount'] : 0;
$cgstAmount = isset($_GET['cgst_amount']) ? (float)$_GET['cgst_amount'] : 0;
$sgstAmount = isset($_GET['sgst_amount']) ? (float)$_GET['sgst_amount'] : 0;
$igstAmount = isset($_GET['igst_amount']) ? (float)$_GET['igst_amount'] : 0;
$taxAmount = isset($_GET['tax_amount']) ? (float)$_GET['tax_amount'] : 0;

if ($businessId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Business session missing. Please login again.']);
    exit;
}

if ($billId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Bill ID required.']);
    exit;
}

// Fetch bill details
$billQuery = "
    SELECT 
        b.*,
        br.branch_name,
        br.floor_name,
        bs.business_name,
        bs.address AS business_address,
        bs.gstin,
        u.name AS sales_user_name,
        u.user_id AS sales_user_id
    FROM bills b
    LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
    LEFT JOIN businesses bs ON bs.business_id = b.business_id
    LEFT JOIN users u ON u.user_id = b.created_by
    WHERE b.bill_id = ? 
      AND b.business_id = ?
      AND b.bill_status = 'active'
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $billQuery);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $billId, $businessId);
mysqli_stmt_execute($stmt);
$billResult = mysqli_stmt_get_result($stmt);
$bill = mysqli_fetch_assoc($billResult);
mysqli_stmt_close($stmt);

if (!$bill) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Bill not found.']);
    exit;
}

// Fetch bill items
$itemsQuery = "
    SELECT 
        bi.*,
        si.article_no,
        si.article_name,
        si.size,
        si.color,
        b.brand_name,
        c.category_name
    FROM bill_items bi
    LEFT JOIN stock_inward_items si ON si.stock_item_id = bi.stock_item_id
    LEFT JOIN brands b ON b.brand_id = si.brand_id
    LEFT JOIN categories c ON c.category_id = si.category_id
    WHERE bi.bill_id = ? 
      AND bi.business_id = ?
    ORDER BY bi.bill_item_id ASC
";

$stmt = mysqli_prepare($conn, $itemsQuery);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'ii', $billId, $businessId);
mysqli_stmt_execute($stmt);
$itemsResult = mysqli_stmt_get_result($stmt);
$items = array();
while ($row = mysqli_fetch_assoc($itemsResult)) {
    $items[] = $row;
}
mysqli_stmt_close($stmt);

// Build print data for .NET service
$branchDisplay = ($bill['branch_name'] ?? '') . ($bill['floor_name'] ? ' ' . $bill['floor_name'] : '');

// Get the net amount from the bill
$netAmount = (float)($bill['net_amount'] ?? 0);

// ============================================
// FIX: CORRECT PAID AMOUNT CALCULATION
// ============================================
// Get the existing paid amount from the bill
$existingPaid = (float)($bill['paid_amount'] ?? 0);

// The collection amount is the amount being paid NOW
$newCollectionAmount = $collectionAmount;

// If collection amount is provided via GET, use it
if ($newCollectionAmount > 0) {
    // The total paid should be: existing paid + this collection
    $totalPaid = $existingPaid + $newCollectionAmount;
} else {
    // If no collection amount, use the existing paid
    $totalPaid = $existingPaid;
}

// IMPORTANT: The total paid should NEVER exceed the grand total
$totalPaid = min($totalPaid, $netAmount);

// Calculate balance
$balanceAmount = max(0, $netAmount - $totalPaid);

// Determine payment status
if ($balanceAmount <= 0.009) {
    $paymentStatus = 'PAID';
} elseif ($totalPaid > 0) {
    $paymentStatus = 'PARTIAL';
} else {
    $paymentStatus = 'PENDING';
}

// Determine the sales user name
$salesUserName = $bill['sales_user_name'] ?? 'N/A';

// Build the print data
$printData = array(
    "PrintType" => "COLLECTION",
    "ShopName" => $bill['business_name'] ?? 'GK FOOTWEAR',
    "Address" => $bill['business_address'] ?? 'Gandhi Nagar, Krishnagiri.',
    "InvoiceTitle" => $bill['invoice_title'] ?? 'Bill of Supply',
    "BillNo" => $bill['bill_no'] ?? '',
    "OrderNo" => $bill['order_no'] ?? 'ORD-' . ($bill['bill_no'] ?? ''),
    "Date" => date('d-m-Y', strtotime($bill['bill_date'] ?? date('Y-m-d'))),
    "Time" => $bill['bill_time'] ?? date('h.i.s A'),
    "Customer" => $bill['customer_name'] ?? 'Walk-in Customer',
    "Branch" => $branchDisplay ?: 'N/A',
    "Salesman" => $salesUserName,
    "CollectedBy" => $collectedBy ?: 'N/A',
    "PaymentStatus" => $paymentStatus,
    "PaymentMethod" => $paymentMethod ?: 'Cash',
    "GrandTotal" => $netAmount,
    "Paid" => $totalPaid,  // FIXED: Correct total paid amount
    "Balance" => $balanceAmount,  // FIXED: Correct balance
    "Barcode" => $bill['bill_barcode'] ?? $bill['barcode_value'] ?? '',
    "Items" => array()
);

// Add GST details if enabled
if ($gstEnabled) {
    $printData['GSTEnabled'] = true;
    $printData['GSTMode'] = $gstMode;
    $printData['GSTRate'] = $gstRate;
    $printData['TaxableAmount'] = $taxableAmount;
    $printData['CGSTAmount'] = $cgstAmount;
    $printData['SGSTAmount'] = $sgstAmount;
    $printData['IGSTAmount'] = $igstAmount;
    $printData['TaxAmount'] = $taxAmount;
}

// Add items
foreach ($items as $item) {
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
        "Rate" => (float)($item['selling_rate'] ?? 0)
    );
}

// Log the print data for debugging
error_log("Thermal Print Data: " . json_encode($printData));

// If auto-print is requested, send directly to .NET service
if ($autoPrint) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:17900/");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($printData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $printResponse = curl_exec($ch);
    $printError = curl_error($ch);
    $printHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Prepare response
    $response = array(
        'success' => empty($printError) && $printHttpCode == 200,
        'message' => empty($printError) ? 'Print sent successfully' : 'Print error: ' . $printError,
        'data' => $printData,
        'print_response' => $printResponse,
        'http_code' => $printHttpCode
    );

    if (!empty($printError)) {
        error_log("Thermal Print Error: " . $printError);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Otherwise, display the print data as JSON
header('Content-Type: application/json');
echo json_encode(array(
    'success' => true,
    'message' => 'Print data ready',
    'data' => $printData
));
exit;