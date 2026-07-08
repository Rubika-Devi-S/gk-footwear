<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

function bl_json($payload, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bl_fail($message, $statusCode = 400) {
    bl_json(array('success' => false, 'message' => $message), $statusCode);
}

function bl_bind(mysqli_stmt $stmt, $types, array $params) {
    if ($types === '') return;
    $bind = array($types);
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bind);
}

function bl_fetch_all(mysqli $conn, $sql, $types = '', array $params = array()) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception('SQL prepare failed: ' . mysqli_error($conn));
    bl_bind($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('SQL execute failed: ' . mysqli_stmt_error($stmt));
    $rs = mysqli_stmt_get_result($stmt);
    $rows = array();
    while ($row = mysqli_fetch_assoc($rs)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function bl_fetch_one(mysqli $conn, $sql, $types = '', array $params = array()) {
    $rows = bl_fetch_all($conn, $sql, $types, $params);
    return $rows ? $rows[0] : null;
}

function bl_execute(mysqli $conn, $sql, $types = '', array $params = array()) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception('SQL prepare failed: ' . mysqli_error($conn));
    bl_bind($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('SQL execute failed: ' . mysqli_stmt_error($stmt));
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected;
}

function bl_insert(mysqli $conn, $sql, $types = '', array $params = array()) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new Exception('SQL prepare failed: ' . mysqli_error($conn));
    bl_bind($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) throw new Exception('SQL execute failed: ' . mysqli_stmt_error($stmt));
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function bl_table_exists(mysqli $conn, $tableName) {
    $row = bl_fetch_one($conn, "SELECT COUNT(*) total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", 's', array($tableName));
    return (int)($row['total'] ?? 0) > 0;
}

function bl_column_exists(mysqli $conn, $tableName, $columnName) {
    $row = bl_fetch_one($conn, "SELECT COUNT(*) total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", 'ss', array($tableName, $columnName));
    return (int)($row['total'] ?? 0) > 0;
}

function bl_current_business_id() {
    return function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);
}

function bl_current_user_id() {
    if (function_exists('current_user_id')) return (int)current_user_id();
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function bl_current_role_id() {
    return function_exists('current_role_id') ? (int)current_role_id() : (int)($_SESSION['role_id'] ?? 0);
}

function bl_is_admin(mysqli $conn) {
    if (function_exists('is_business_admin')) return (bool)is_business_admin($conn);
    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));
    return in_array($roleName, array('admin', 'business admin', 'branch admin'), true);
}

function bl_verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = (string)($_POST['csrf_token'] ?? $_POST['_token'] ?? '');
    if (function_exists('verify_csrf_token')) {
        if (!verify_csrf_token($token)) throw new Exception('Invalid security token. Please refresh and try again.');
        return;
    }
    if (function_exists('csrf_token')) {
        $expected = (string)csrf_token();
        if ($expected !== '' && !hash_equals($expected, $token)) {
            throw new Exception('Invalid security token. Please refresh and try again.');
        }
    }
}

function bl_branch_sql(mysqli $conn, $businessId, $userId, $isAdmin, &$types, &$params, $alias = 'b') {
    if ($isAdmin || $userId <= 0 || !bl_table_exists($conn, 'user_branch_access')) {
        return '';
    }
    $branches = bl_fetch_all($conn, "SELECT branch_id FROM user_branch_access WHERE business_id = ? AND user_id = ? AND access_status = 1", 'ii', array($businessId, $userId));
    if (!$branches) return " AND 1 = 0";
    $ids = array();
    foreach ($branches as $row) $ids[] = (int)$row['branch_id'];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types .= str_repeat('i', count($ids));
    foreach ($ids as $id) $params[] = $id;
    return " AND {$alias}.branch_id IN ({$placeholders})";
}

function bl_accessible_branches(mysqli $conn, $businessId, $userId, $isAdmin) {
    if ($isAdmin || $userId <= 0 || !bl_table_exists($conn, 'user_branch_access')) {
        return bl_fetch_all($conn, "SELECT branch_id, branch_code, branch_name, floor_name FROM branches WHERE business_id = ? AND status = 1 ORDER BY branch_id ASC", 'i', array($businessId));
    }
    $rows = bl_fetch_all($conn, "
        SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name
        FROM branches b
        INNER JOIN user_branch_access uba ON uba.branch_id = b.branch_id AND uba.business_id = b.business_id
        WHERE b.business_id = ? AND uba.user_id = ? AND uba.access_status = 1 AND b.status = 1
        ORDER BY b.branch_id ASC
    ", 'ii', array($businessId, $userId));
    if ($rows) return $rows;
    return bl_fetch_all($conn, "SELECT branch_id, branch_code, branch_name, floor_name FROM branches WHERE business_id = ? AND status = 1 ORDER BY branch_id ASC", 'i', array($businessId));
}

function bl_user_join_column(mysqli $conn) {
    if (bl_table_exists($conn, 'users') && bl_column_exists($conn, 'users', 'user_id')) return 'user_id';
    return 'id';
}

function bl_user_name_expr(mysqli $conn, $alias) {
    if (!bl_table_exists($conn, 'users')) return "'-'";
    if (bl_column_exists($conn, 'users', 'name')) return "COALESCE(NULLIF({$alias}.name,''), NULLIF({$alias}.username,''), '-')";
    return "COALESCE(NULLIF({$alias}.username,''), '-')";
}

function bl_bill_where(mysqli $conn, $businessId, $userId, $isAdmin, array $input, &$types, &$params) {
    $where = " WHERE b.business_id = ?";
    $types = 'i';
    $params = array($businessId);

    $where .= bl_branch_sql($conn, $businessId, $userId, $isAdmin, $types, $params, 'b');

    $branchId = (int)($input['branch_id'] ?? 0);
    if ($branchId > 0) {
        $where .= " AND b.branch_id = ?";
        $types .= 'i';
        $params[] = $branchId;
    }

    $billStatus = trim((string)($input['bill_status'] ?? ''));
    if ($billStatus !== '') {
        $where .= " AND b.bill_status = ?";
        $types .= 's';
        $params[] = $billStatus;
    }

    $paymentStatus = trim((string)($input['payment_status'] ?? ''));
    if ($paymentStatus !== '') {
        $where .= " AND b.payment_status = ?";
        $types .= 's';
        $params[] = $paymentStatus;
    }

    $dateFrom = trim((string)($input['date_from'] ?? ''));
    if ($dateFrom !== '') {
        $where .= " AND b.bill_date >= ?";
        $types .= 's';
        $params[] = $dateFrom;
    }

    $dateTo = trim((string)($input['date_to'] ?? ''));
    if ($dateTo !== '') {
        $where .= " AND b.bill_date <= ?";
        $types .= 's';
        $params[] = $dateTo;
    }

    $paymentMethodId = (int)($input['payment_method_id'] ?? 0);
    if ($paymentMethodId > 0 && bl_table_exists($conn, 'bill_payments')) {
        $where .= " AND EXISTS (SELECT 1 FROM bill_payments bpf WHERE bpf.business_id = b.business_id AND bpf.branch_id = b.branch_id AND bpf.bill_id = b.bill_id AND bpf.payment_method_id = ? AND bpf.payment_status = 'received')";
        $types .= 'i';
        $params[] = $paymentMethodId;
    }

    $search = trim((string)($input['search'] ?? ''));
    if ($search !== '') {
        $where .= " AND (b.bill_no LIKE ? OR b.order_no LIKE ? OR b.customer_name LIKE ? OR b.customer_mobile LIKE ?";
        $like = '%' . $search . '%';
        $types .= 'ssss';
        array_push($params, $like, $like, $like, $like);
        if (bl_table_exists($conn, 'bill_barcodes')) {
            $where .= " OR EXISTS (SELECT 1 FROM bill_barcodes bbx WHERE bbx.business_id = b.business_id AND bbx.branch_id = b.branch_id AND bbx.bill_id = b.bill_id AND bbx.barcode_value LIKE ?)";
            $types .= 's';
            $params[] = $like;
        }
        $where .= ")";
    }

    return $where;
}

function bl_stats(mysqli $conn, $businessId, $userId, $isAdmin, array $input) {
    $types = '';
    $params = array();
    $where = bl_bill_where($conn, $businessId, $userId, $isAdmin, $input, $types, $params);
    $row = bl_fetch_one($conn, "
        SELECT COUNT(*) total_bills,
               COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.net_amount ELSE 0 END),0) total_net_amount,
               COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.paid_amount ELSE 0 END),0) total_paid_amount,
               COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.balance_amount ELSE 0 END),0) total_balance_amount,
               COALESCE(SUM(CASE WHEN b.bill_status = 'cancelled' THEN 1 ELSE 0 END),0) cancelled_bills
        FROM bills b
        {$where}
    ", $types, $params);
    return array(
        'total_bills' => (int)($row['total_bills'] ?? 0),
        'total_net_amount' => (float)($row['total_net_amount'] ?? 0),
        'total_paid_amount' => (float)($row['total_paid_amount'] ?? 0),
        'total_balance_amount' => (float)($row['total_balance_amount'] ?? 0),
        'cancelled_bills' => (int)($row['cancelled_bills'] ?? 0),
    );
}

function bl_payment_methods(mysqli $conn, $businessId) {
    if (!bl_table_exists($conn, 'payment_methods')) return array();
    return bl_fetch_all($conn, "SELECT payment_method_id, payment_method_name, method_type FROM payment_methods WHERE business_id = ? AND status = 1 ORDER BY FIELD(method_type,'cash','upi','card','cheque','credit','split','other'), payment_method_name ASC", 'i', array($businessId));
}

function bl_list(mysqli $conn, $businessId, $userId, $isAdmin, array $input) {
    if (!bl_table_exists($conn, 'bills')) throw new Exception('Bills table is missing.');
    $page = max(1, (int)($input['page'] ?? 1));
    $perPage = max(5, min(100, (int)($input['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $types = '';
    $params = array();
    $where = bl_bill_where($conn, $businessId, $userId, $isAdmin, $input, $types, $params);
    $totalRow = bl_fetch_one($conn, "SELECT COUNT(*) total FROM bills b {$where}", $types, $params);
    $total = (int)($totalRow['total'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $perPage));

    $userJoinColumn = bl_user_join_column($conn);
    $createdByExpr = bl_user_name_expr($conn, 'u');
    $userJoin = bl_table_exists($conn, 'users') ? "LEFT JOIN users u ON u.{$userJoinColumn} = b.created_by" : "";
    $branchJoin = bl_table_exists($conn, 'branches') ? "LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id" : "";
    $barcodeJoin = bl_table_exists($conn, 'bill_barcodes') ? "LEFT JOIN (SELECT business_id, branch_id, bill_id, MIN(barcode_value) barcode_value FROM bill_barcodes WHERE barcode_status = 'active' GROUP BY business_id, branch_id, bill_id) bb ON bb.business_id = b.business_id AND bb.branch_id = b.branch_id AND bb.bill_id = b.bill_id" : "";
    $itemJoin = bl_table_exists($conn, 'bill_items') ? "LEFT JOIN (SELECT business_id, branch_id, bill_id, COUNT(*) item_count, COALESCE(SUM(qty),0) total_qty, GROUP_CONCAT(DISTINCT article_no ORDER BY article_no SEPARATOR ', ') article_summary FROM bill_items GROUP BY business_id, branch_id, bill_id) bi ON bi.business_id = b.business_id AND bi.branch_id = b.branch_id AND bi.bill_id = b.bill_id" : "";
    $paymentJoin = bl_table_exists($conn, 'bill_payments') && bl_table_exists($conn, 'payment_methods') ? "LEFT JOIN (SELECT bp.business_id, bp.branch_id, bp.bill_id, GROUP_CONCAT(CONCAT(pm.payment_method_name, ' ', FORMAT(bp.paid_amount,2)) ORDER BY bp.payment_id SEPARATOR ' + ') payment_summary FROM bill_payments bp LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id AND pm.business_id = bp.business_id WHERE bp.payment_status = 'received' GROUP BY bp.business_id, bp.branch_id, bp.bill_id) pay ON pay.business_id = b.business_id AND pay.branch_id = b.branch_id AND pay.bill_id = b.bill_id" : "";

    $rows = bl_fetch_all($conn, "
        SELECT b.bill_id, b.business_id, b.branch_id, b.bill_no, b.order_no, b.bill_date, b.bill_time,
               b.customer_id, b.customer_name, b.customer_mobile, b.mrp_total, b.item_discount_total,
               b.bill_discount_amount, b.selling_amount, b.loyalty_redeem_amount, b.today_savings_amount,
               b.round_off, b.net_amount, b.paid_amount, b.balance_amount, b.payment_status, b.bill_status,
               b.print_count, b.created_at,
               COALESCE(br.branch_name, '-') branch_name, COALESCE(br.floor_name, '') floor_name,
               {$createdByExpr} created_by_name,
               COALESCE(bi.item_count, 0) item_count, COALESCE(bi.total_qty, 0) total_qty, COALESCE(bi.article_summary, '') article_summary,
               COALESCE(pay.payment_summary, '-') payment_summary,
               COALESCE(bb.barcode_value, '') barcode_value
        FROM bills b
        {$branchJoin}
        {$userJoin}
        {$itemJoin}
        {$paymentJoin}
        {$barcodeJoin}
        {$where}
        ORDER BY b.bill_date DESC, b.bill_time DESC, b.bill_id DESC
        LIMIT {$perPage} OFFSET {$offset}
    ", $types, $params);

    return array(
        'items' => $rows,
        'pagination' => array('page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages),
    );
}

function bl_get(mysqli $conn, $businessId, $userId, $isAdmin, $billId) {
    if ($billId <= 0) throw new Exception('Invalid bill ID.');
    $types = 'ii';
    $params = array($businessId, $billId);
    $branchRestriction = bl_branch_sql($conn, $businessId, $userId, $isAdmin, $types, $params, 'b');
    $userJoinColumn = bl_user_join_column($conn);
    $createdByExpr = bl_user_name_expr($conn, 'u');
    $userJoin = bl_table_exists($conn, 'users') ? "LEFT JOIN users u ON u.{$userJoinColumn} = b.created_by" : "";
    $branchJoin = bl_table_exists($conn, 'branches') ? "LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id" : "";
    $barcodeJoin = bl_table_exists($conn, 'bill_barcodes') ? "LEFT JOIN (SELECT business_id, branch_id, bill_id, MIN(barcode_value) barcode_value FROM bill_barcodes GROUP BY business_id, branch_id, bill_id) bb ON bb.business_id = b.business_id AND bb.branch_id = b.branch_id AND bb.bill_id = b.bill_id" : "";

    $bill = bl_fetch_one($conn, "
        SELECT b.*, COALESCE(br.branch_name, '-') branch_name, COALESCE(br.floor_name, '') floor_name,
               {$createdByExpr} created_by_name, COALESCE(bb.barcode_value, '') barcode_value
        FROM bills b
        {$branchJoin}
        {$userJoin}
        {$barcodeJoin}
        WHERE b.business_id = ? AND b.bill_id = ? {$branchRestriction}
        LIMIT 1
    ", $types, $params);
    if (!$bill) throw new Exception('Bill not found or access denied.');

    $items = array();
    if (bl_table_exists($conn, 'bill_items')) {
        $brandJoin = bl_table_exists($conn, 'brands') ? "LEFT JOIN brands brd ON brd.brand_id = bi.brand_id AND brd.business_id = bi.business_id" : "";
        $stockJoin = bl_table_exists($conn, 'stock_inward_items') ? "LEFT JOIN stock_inward_items si ON si.stock_item_id = bi.stock_item_id AND si.business_id = bi.business_id AND si.branch_id = bi.branch_id" : "";
        $items = bl_fetch_all($conn, "
            SELECT bi.*, COALESCE(brd.brand_name, '-') brand_name, COALESCE(si.color, '') color, COALESCE(si.available_qty, 0) current_available_qty
            FROM bill_items bi
            {$brandJoin}
            {$stockJoin}
            WHERE bi.business_id = ? AND bi.branch_id = ? AND bi.bill_id = ?
            ORDER BY bi.bill_item_id ASC
        ", 'iii', array($businessId, (int)$bill['branch_id'], $billId));
    }

    $payments = array();
    if (bl_table_exists($conn, 'bill_payments')) {
        $paymentMethodJoin = bl_table_exists($conn, 'payment_methods') ? "LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id AND pm.business_id = bp.business_id" : "";
        $collectorExpr = "'-'";
        $collectorJoin = '';
        if (bl_table_exists($conn, 'users')) {
            $collectorJoin = "LEFT JOIN users cu ON cu.{$userJoinColumn} = bp.collected_by";
            $collectorExpr = bl_user_name_expr($conn, 'cu');
        }
        $payments = bl_fetch_all($conn, "
            SELECT bp.*, COALESCE(pm.payment_method_name, '-') payment_method_name, COALESCE(pm.method_type, '') method_type,
                   {$collectorExpr} collected_by_name
            FROM bill_payments bp
            {$paymentMethodJoin}
            {$collectorJoin}
            WHERE bp.business_id = ? AND bp.branch_id = ? AND bp.bill_id = ?
            ORDER BY bp.payment_id ASC
        ", 'iii', array($businessId, (int)$bill['branch_id'], $billId));
    }

    return array('bill' => $bill, 'items' => $items, 'payments' => $payments);
}

function bl_log(mysqli $conn, $businessId, $branchId, $userId, $actionType, $recordId, array $newValue = array()) {
    if (!bl_table_exists($conn, 'activity_logs')) return;
    $roleId = bl_current_role_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $device = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $json = json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    bl_insert($conn, "
        INSERT INTO activity_logs (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)
        VALUES (?, ?, ?, ?, 'Bill List', ?, ?, NULL, ?, ?, ?, NOW())
    ", 'iiiisisss', array($businessId, $branchId, $userId, $roleId, $actionType, $recordId, $json, $ip, $device));
}

function bl_cancel_bill(mysqli $conn, $businessId, $userId, $isAdmin, $billId, $reason) {
    $data = bl_get($conn, $businessId, $userId, $isAdmin, $billId);
    $bill = $data['bill'];
    if (!$bill) throw new Exception('Bill not found.');
    if ((string)$bill['bill_status'] !== 'active') throw new Exception('Only active bills can be cancelled.');
    $branchId = (int)$bill['branch_id'];
    $netAmount = (float)$bill['net_amount'];
    $paidAmount = (float)$bill['paid_amount'];
    $balanceAmount = (float)$bill['balance_amount'];
    $customerId = (int)($bill['customer_id'] ?? 0);
    $reason = trim((string)$reason);
    if ($reason === '') $reason = 'Cancelled from Bill List';

    mysqli_begin_transaction($conn);
    try {
        bl_execute($conn, "UPDATE bills SET bill_status = 'cancelled', payment_status = 'cancelled', updated_by = ?, updated_at = NOW() WHERE business_id = ? AND branch_id = ? AND bill_id = ? AND bill_status = 'active'", 'iiii', array($userId, $businessId, $branchId, $billId));

        if (bl_table_exists($conn, 'bill_payments')) {
            bl_execute($conn, "UPDATE bill_payments SET payment_status = 'cancelled' WHERE business_id = ? AND branch_id = ? AND bill_id = ? AND payment_status = 'received'", 'iii', array($businessId, $branchId, $billId));
        }
        if (bl_table_exists($conn, 'bill_barcodes')) {
            bl_execute($conn, "UPDATE bill_barcodes SET barcode_status = 'cancelled' WHERE business_id = ? AND branch_id = ? AND bill_id = ?", 'iii', array($businessId, $branchId, $billId));
        }

        if (bl_table_exists($conn, 'bill_items') && bl_table_exists($conn, 'stock_inward_items')) {
            $items = bl_fetch_all($conn, "SELECT stock_item_id, qty, article_no FROM bill_items WHERE business_id = ? AND branch_id = ? AND bill_id = ?", 'iii', array($businessId, $branchId, $billId));
            foreach ($items as $item) {
                $stockItemId = (int)$item['stock_item_id'];
                $qty = (float)$item['qty'];
                if ($stockItemId <= 0 || $qty <= 0) continue;
                bl_execute($conn, "UPDATE stock_inward_items SET available_qty = available_qty + ?, item_status = 'active', updated_at = NOW() WHERE business_id = ? AND branch_id = ? AND stock_item_id = ?", 'diii', array($qty, $businessId, $branchId, $stockItemId));
                $stock = bl_fetch_one($conn, "SELECT available_qty FROM stock_inward_items WHERE business_id = ? AND branch_id = ? AND stock_item_id = ?", 'iii', array($businessId, $branchId, $stockItemId));
                $balanceQty = (float)($stock['available_qty'] ?? 0);
                if (bl_table_exists($conn, 'stock_movements')) {
                    bl_insert($conn, "
                        INSERT INTO stock_movements (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, qty_in, qty_out, balance_qty, remarks, created_by, created_at)
                        VALUES (?, ?, ?, 'sale_cancel', 'bill', ?, ?, 0, ?, ?, ?, NOW())
                    ", 'iiiiddsi', array($businessId, $branchId, $stockItemId, $billId, $qty, $balanceQty, 'Bill cancelled: ' . $bill['bill_no'], $userId));
                }
            }
        }

        if ($customerId > 0) {
            if (bl_table_exists($conn, 'customer_outstanding')) {
                bl_execute($conn, "UPDATE customer_outstanding SET total_bill_amount = GREATEST(0, total_bill_amount - ?), total_paid_amount = GREATEST(0, total_paid_amount - ?), balance_amount = GREATEST(0, balance_amount - ?), updated_at = NOW() WHERE business_id = ? AND customer_id = ?", 'dddii', array($netAmount, $paidAmount, $balanceAmount, $businessId, $customerId));
            }
            if (bl_table_exists($conn, 'customer_ledger')) {
                bl_insert($conn, "
                    INSERT INTO customer_ledger (business_id, branch_id, customer_id, reference_type, reference_id, debit, credit, balance, remarks, created_by, created_at)
                    VALUES (?, ?, ?, 'bill_cancel', ?, 0, ?, 0, ?, ?, NOW())
                ", 'iiiidsi', array($businessId, $branchId, $customerId, $billId, $netAmount, 'Bill cancelled: ' . $bill['bill_no'], $userId));
            }
            if (bl_table_exists($conn, 'payment_ledger')) {
                bl_insert($conn, "
                    INSERT INTO payment_ledger (business_id, branch_id, customer_id, bill_id, transaction_type, debit, credit, balance, payment_method_id, remarks, created_by, created_at)
                    VALUES (?, ?, ?, ?, 'reverse', 0, ?, 0, NULL, ?, ?, NOW())
                ", 'iiiidsi', array($businessId, $branchId, $customerId, $billId, $paidAmount, 'Payment reversed for cancelled bill: ' . $bill['bill_no'], $userId));
            }
        }

        if (bl_table_exists($conn, 'cashier_collections')) {
            bl_execute($conn, "UPDATE cashier_collections SET collection_status = 'cancelled' WHERE business_id = ? AND branch_id = ? AND bill_id = ?", 'iii', array($businessId, $branchId, $billId));
        }

        bl_log($conn, $businessId, $branchId, $userId, 'bill_cancelled', $billId, array('bill_no' => $bill['bill_no'], 'reason' => $reason, 'net_amount' => $netAmount));
        mysqli_commit($conn);
        return true;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

try {
    require_business_login();
    if (function_exists('require_page_access')) {
        require_page_access($conn, 'bill-list.php');
    }

    $businessId = bl_current_business_id();
    if ($businessId <= 0) bl_fail('Business session missing. Please login again.', 401);
    $userId = bl_current_user_id();
    $isAdmin = bl_is_admin($conn);

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'POST') {
        bl_verify_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'cancel_bill') {
            $billId = (int)($_POST['bill_id'] ?? 0);
            $reason = (string)($_POST['reason'] ?? '');
            bl_cancel_bill($conn, $businessId, $userId, $isAdmin, $billId, $reason);
            bl_json(array('success' => true, 'message' => 'Bill cancelled and stock restored successfully.'));
        }
        bl_fail('Invalid bill list API action.', 400);
    }

    $action = (string)($_GET['action'] ?? 'init');
    if ($action === 'init') {
        $input = array_merge($_GET, array('page' => 1, 'per_page' => 20));
        bl_json(array(
            'success' => true,
            'branches' => bl_accessible_branches($conn, $businessId, $userId, $isAdmin),
            'payment_methods' => bl_payment_methods($conn, $businessId),
            'stats' => bl_stats($conn, $businessId, $userId, $isAdmin, $input),
            'bills' => bl_list($conn, $businessId, $userId, $isAdmin, $input),
        ));
    }
    if ($action === 'list') {
        bl_json(array(
            'success' => true,
            'stats' => bl_stats($conn, $businessId, $userId, $isAdmin, $_GET),
            'bills' => bl_list($conn, $businessId, $userId, $isAdmin, $_GET),
        ));
    }
    if ($action === 'get') {
        $billId = (int)($_GET['bill_id'] ?? 0);
        $data = bl_get($conn, $businessId, $userId, $isAdmin, $billId);
        $data['success'] = true;
        bl_json($data);
    }
    bl_fail('Invalid bill list API action.', 400);
} catch (Throwable $e) {
    bl_json(array('success' => false, 'message' => $e->getMessage()), 500);
}
