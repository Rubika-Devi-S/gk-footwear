<?php
/**
 * GK Footwear POS - ERP Dashboard API
 * Place this file at: api/erp-dashboard-api.php
 * Built against current u966043993_footwear database schema.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function dash_json($payload, $statusCode = 200)
{
    http_response_code((int)$statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dash_bind(mysqli_stmt $stmt, $types, array $params)
{
    if ($types === '') { return; }
    $bind = array($types);
    foreach ($params as $k => $v) { $bind[] = &$params[$k]; }
    call_user_func_array(array($stmt, 'bind_param'), $bind);
}

function dash_fetch_all(mysqli $conn, $sql, $types = '', array $params = array())
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { throw new Exception('SQL prepare failed: ' . mysqli_error($conn)); }
    dash_bind($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) { throw new Exception('SQL execute failed: ' . mysqli_stmt_error($stmt)); }
    $rs = mysqli_stmt_get_result($stmt);
    $rows = array();
    if ($rs) {
        while ($row = mysqli_fetch_assoc($rs)) { $rows[] = $row; }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function dash_fetch_one(mysqli $conn, $sql, $types = '', array $params = array())
{
    $rows = dash_fetch_all($conn, $sql, $types, $params);
    return $rows ? $rows[0] : array();
}

function dash_table_exists(mysqli $conn, $tableName)
{
    $row = dash_fetch_one($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?", 's', array($tableName));
    return (int)($row['total'] ?? 0) > 0;
}

function dash_column_exists(mysqli $conn, $tableName, $columnName)
{
    $row = dash_fetch_one($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", 'ss', array($tableName, $columnName));
    return (int)($row['total'] ?? 0) > 0;
}

function dash_current_business_id()
{
    return function_exists('current_business_id') ? (int)current_business_id() : (int)($_SESSION['business_id'] ?? 0);
}

function dash_current_user_id()
{
    if (function_exists('current_user_id')) { return (int)current_user_id(); }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);
}

function dash_current_role_id()
{
    return function_exists('current_role_id') ? (int)current_role_id() : (int)($_SESSION['role_id'] ?? 0);
}

function dash_is_admin(mysqli $conn)
{
    if (function_exists('is_business_admin')) { return (bool)is_business_admin($conn); }
    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));
    return in_array($roleName, array('admin', 'business admin', 'branch admin', 'super admin'), true) || (int)($_SESSION['role_id'] ?? 0) === 1;
}

function dash_accessible_branches(mysqli $conn, $businessId, $userId, $isAdmin)
{
    if (!dash_table_exists($conn, 'branches')) { return array(); }

    if (!$isAdmin && $userId > 0 && dash_table_exists($conn, 'user_branch_access')) {
        $rows = dash_fetch_all($conn, "
            SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name
            FROM branches b
            INNER JOIN user_branch_access uba
                ON uba.business_id = b.business_id
               AND uba.branch_id = b.branch_id
               AND uba.user_id = ?
               AND uba.access_status = 1
            WHERE b.business_id = ? AND b.status = 1
            ORDER BY b.branch_id ASC
        ", 'ii', array($userId, $businessId));
        if ($rows) { return $rows; }
    }

    if (!$isAdmin && $userId > 0 && dash_table_exists($conn, 'users') && dash_column_exists($conn, 'users', 'default_branch_id')) {
        $rows = dash_fetch_all($conn, "
            SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name
            FROM users u
            INNER JOIN branches b ON b.business_id = u.business_id AND b.branch_id = u.default_branch_id AND b.status = 1
            WHERE u.business_id = ? AND u.user_id = ?
            ORDER BY b.branch_id ASC
        ", 'ii', array($businessId, $userId));
        if ($rows) { return $rows; }
    }

    return dash_fetch_all($conn, "SELECT branch_id, branch_code, branch_name, floor_name FROM branches WHERE business_id = ? AND status = 1 ORDER BY branch_id ASC", 'i', array($businessId));
}

function dash_branch_ids(array $branches)
{
    $ids = array();
    foreach ($branches as $row) { $ids[] = (int)$row['branch_id']; }
    return array_values(array_unique(array_filter($ids)));
}

function dash_branch_filter_sql($selectedBranchId, array $accessibleBranchIds, $alias, &$types, &$params)
{
    $selectedBranchId = (int)$selectedBranchId;
    if ($selectedBranchId > 0) {
        if ($accessibleBranchIds && !in_array($selectedBranchId, $accessibleBranchIds, true)) {
            return " AND 1 = 0";
        }
        $types .= 'i';
        $params[] = $selectedBranchId;
        return " AND {$alias}.branch_id = ?";
    }

    if (!$accessibleBranchIds) { return ''; }
    $placeholders = implode(',', array_fill(0, count($accessibleBranchIds), '?'));
    $types .= str_repeat('i', count($accessibleBranchIds));
    foreach ($accessibleBranchIds as $id) { $params[] = (int)$id; }
    return " AND {$alias}.branch_id IN ({$placeholders})";
}

function dash_period_range(array $input)
{
    $period = (string)($input['period'] ?? 'month');
    $today = date('Y-m-d');
    $from = date('Y-m-01');
    $to = $today;
    $label = 'This Month';

    if ($period === 'today') {
        $from = $today;
        $to = $today;
        $label = 'Today';
    } elseif ($period === '30') {
        $from = date('Y-m-d', strtotime('-29 days'));
        $to = $today;
        $label = 'Last 30 Days';
    } elseif ($period === 'custom') {
        $rawFrom = trim((string)($input['date_from'] ?? ''));
        $rawTo = trim((string)($input['date_to'] ?? ''));
        if ($rawFrom !== '') { $from = $rawFrom; }
        if ($rawTo !== '') { $to = $rawTo; }
        if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
        $label = date('d M Y', strtotime($from)) . ' - ' . date('d M Y', strtotime($to));
    }

    return array('from' => $from, 'to' => $to, 'label' => $label, 'period' => $period);
}

function dash_bill_where(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range, &$types, &$params, $alias = 'b')
{
    $types = 'i';
    $params = array($businessId);
    $where = "WHERE {$alias}.business_id = ?";
    $where .= dash_branch_filter_sql($selectedBranchId, $accessibleBranchIds, $alias, $types, $params);
    $where .= " AND {$alias}.bill_date BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $range['from'];
    $params[] = $range['to'];
    return $where;
}



function dash_pending_bill_where(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range, &$types, &$params, $alias = 'b')
{
    $where = dash_bill_where($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range, $types, $params, $alias);
    $where .= " AND {$alias}.bill_status = 'active' AND {$alias}.balance_amount > 0";
    return $where;
}

function dash_pending_payment_summary(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range)
{
    $out = array('pending_amount' => 0, 'pending_bill_count' => 0);
    if (!dash_table_exists($conn, 'bills')) { return $out; }

    $types = '';
    $params = array();
    $where = dash_pending_bill_where($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range, $types, $params, 'b');
    $row = dash_fetch_one($conn, "
        SELECT COUNT(*) AS pending_bill_count,
               COALESCE(SUM(b.balance_amount), 0) AS pending_amount
        FROM bills b
        {$where}
    ", $types, $params);

    return array(
        'pending_amount' => (float)($row['pending_amount'] ?? 0),
        'pending_bill_count' => (int)($row['pending_bill_count'] ?? 0),
    );
}

function dash_kpis(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range)
{
    $k = array(
        'bill_count' => 0,
        'net_sales' => 0,
        'avg_bill_value' => 0,
        'collected_amount' => 0,
        'pending_amount' => 0,
        'pending_bill_count' => 0,
        'available_stock_qty' => 0,
        'low_stock_count' => 0,
        'out_of_stock_count' => 0,
        'customer_outstanding' => 0,
        'outstanding_customer_count' => 0,
        'supplier_outstanding' => 0,
        'outstanding_supplier_count' => 0,
    );

    if (dash_table_exists($conn, 'bills')) {
        $types = '';
        $params = array();
        $where = dash_bill_where($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range, $types, $params, 'b');
        $row = dash_fetch_one($conn, "
            SELECT COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN 1 ELSE 0 END),0) AS bill_count,
                   COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.net_amount ELSE 0 END),0) AS net_sales,
                   COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.paid_amount ELSE 0 END),0) AS bill_paid
            FROM bills b
            {$where}
        ", $types, $params);
        $k['bill_count'] = (int)($row['bill_count'] ?? 0);
        $k['net_sales'] = (float)($row['net_sales'] ?? 0);
        $k['collected_amount'] = (float)($row['bill_paid'] ?? 0);

        $pending = dash_pending_payment_summary($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range);
        $k['pending_amount'] = (float)$pending['pending_amount'];
        $k['pending_bill_count'] = (int)$pending['pending_bill_count'];
    }

    if (dash_table_exists($conn, 'bill_payments')) {
        $types = 'i';
        $params = array($businessId);
        $join = dash_table_exists($conn, 'bills') ? "INNER JOIN bills b ON b.business_id = bp.business_id AND b.branch_id = bp.branch_id AND b.bill_id = bp.bill_id" : "";
        $where = "WHERE bp.business_id = ? AND bp.payment_status = 'received'";
        $where .= dash_branch_filter_sql($selectedBranchId, $accessibleBranchIds, 'bp', $types, $params);
        $where .= " AND DATE(bp.collected_at) BETWEEN ? AND ?";
        $types .= 'ss';
        $params[] = $range['from'];
        $params[] = $range['to'];
        $row = dash_fetch_one($conn, "SELECT COALESCE(SUM(bp.paid_amount),0) collected_amount FROM bill_payments bp {$join} {$where}", $types, $params);
        $k['collected_amount'] = (float)($row['collected_amount'] ?? $k['collected_amount']);
    }

    if (dash_table_exists($conn, 'stock_inward_items')) {
        $types = 'i';
        $params = array($businessId);
        $where = "WHERE si.business_id = ? AND si.item_status IN ('active','out_of_stock')";
        $where .= dash_branch_filter_sql($selectedBranchId, $accessibleBranchIds, 'si', $types, $params);
        $row = dash_fetch_one($conn, "
            SELECT COALESCE(SUM(CASE WHEN si.item_status = 'active' AND si.available_qty > 0 THEN si.available_qty ELSE 0 END),0) AS available_stock_qty,
                   COALESCE(SUM(CASE WHEN si.item_status = 'active' AND si.available_qty > 0 AND si.available_qty <= 2 THEN 1 ELSE 0 END),0) AS low_stock_count,
                   COALESCE(SUM(CASE WHEN si.item_status = 'out_of_stock' OR si.available_qty <= 0 THEN 1 ELSE 0 END),0) AS out_of_stock_count
            FROM stock_inward_items si
            {$where}
        ", $types, $params);
        $k['available_stock_qty'] = (float)($row['available_stock_qty'] ?? 0);
        $k['low_stock_count'] = (int)($row['low_stock_count'] ?? 0);
        $k['out_of_stock_count'] = (int)($row['out_of_stock_count'] ?? 0);
    }

    if ($selectedBranchId > 0 && dash_table_exists($conn, 'bills')) {
        $types = 'ii';
        $params = array($businessId, $selectedBranchId);
        $row = dash_fetch_one($conn, "
            SELECT COALESCE(SUM(balance_amount),0) customer_outstanding,
                   COUNT(DISTINCT CASE WHEN balance_amount > 0 THEN customer_id END) outstanding_customer_count
            FROM bills
            WHERE business_id = ? AND branch_id = ? AND bill_status = 'active' AND customer_id IS NOT NULL AND balance_amount > 0
        ", $types, $params);
        $k['customer_outstanding'] = (float)($row['customer_outstanding'] ?? 0);
        $k['outstanding_customer_count'] = (int)($row['outstanding_customer_count'] ?? 0);
    } elseif (dash_table_exists($conn, 'customer_outstanding')) {
        $row = dash_fetch_one($conn, "SELECT COALESCE(SUM(balance_amount),0) customer_outstanding, COALESCE(SUM(CASE WHEN balance_amount > 0 THEN 1 ELSE 0 END),0) outstanding_customer_count FROM customer_outstanding WHERE business_id = ?", 'i', array($businessId));
        $k['customer_outstanding'] = (float)($row['customer_outstanding'] ?? 0);
        $k['outstanding_customer_count'] = (int)($row['outstanding_customer_count'] ?? 0);
    }

    if (dash_table_exists($conn, 'vendor_outstanding')) {
        $row = dash_fetch_one($conn, "SELECT COALESCE(SUM(balance_amount),0) supplier_outstanding, COALESCE(SUM(CASE WHEN balance_amount > 0 THEN 1 ELSE 0 END),0) outstanding_supplier_count FROM vendor_outstanding WHERE business_id = ?", 'i', array($businessId));
        $k['supplier_outstanding'] = (float)($row['supplier_outstanding'] ?? 0);
        $k['outstanding_supplier_count'] = (int)($row['outstanding_supplier_count'] ?? 0);
    } elseif (dash_table_exists($conn, 'suppliers') && dash_column_exists($conn, 'suppliers', 'current_outstanding')) {
        $row = dash_fetch_one($conn, "SELECT COALESCE(SUM(current_outstanding),0) supplier_outstanding, COALESCE(SUM(CASE WHEN current_outstanding > 0 THEN 1 ELSE 0 END),0) outstanding_supplier_count FROM suppliers WHERE business_id = ? AND status = 1", 'i', array($businessId));
        $k['supplier_outstanding'] = (float)($row['supplier_outstanding'] ?? 0);
        $k['outstanding_supplier_count'] = (int)($row['outstanding_supplier_count'] ?? 0);
    }

    return $k;
}

function dash_sales_trend(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range)
{
    if (!dash_table_exists($conn, 'bills')) { return array(); }
    $types = '';
    $params = array();
    $where = dash_bill_where($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range, $types, $params, 'b');
    return dash_fetch_all($conn, "
        SELECT b.bill_date,
               DATE_FORMAT(b.bill_date, '%d %b') AS label,
               COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.net_amount ELSE 0 END),0) AS net_amount,
               COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.paid_amount ELSE 0 END),0) AS paid_amount,
               COUNT(*) AS bill_count
        FROM bills b
        {$where}
        GROUP BY b.bill_date
        ORDER BY b.bill_date ASC
    ", $types, $params);
}

function dash_payment_mix(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range)
{
    if (!dash_table_exists($conn, 'bill_payments')) { return array(); }
    $methodJoin = dash_table_exists($conn, 'payment_methods') ? "LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id AND pm.business_id = bp.business_id" : "";
    $methodName = dash_table_exists($conn, 'payment_methods') ? "COALESCE(pm.payment_method_name, 'Other')" : "'Payment'";
    $types = 'i';
    $params = array($businessId);
    $where = "WHERE bp.business_id = ? AND bp.payment_status = 'received'";
    $where .= dash_branch_filter_sql($selectedBranchId, $accessibleBranchIds, 'bp', $types, $params);
    $where .= " AND DATE(bp.collected_at) BETWEEN ? AND ?";
    $types .= 'ss';
    $params[] = $range['from'];
    $params[] = $range['to'];
    return dash_fetch_all($conn, "
        SELECT {$methodName} AS payment_method_name,
               COALESCE(SUM(bp.paid_amount),0) AS paid_amount,
               COUNT(*) AS payment_count
        FROM bill_payments bp
        {$methodJoin}
        {$where}
        GROUP BY {$methodName}
        ORDER BY paid_amount DESC
        LIMIT 8
    ", $types, $params);
}

function dash_stock_overview(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds)
{
    $out = array('total_stock_qty' => 0, 'available_stock_qty' => 0, 'sold_stock_qty' => 0, 'available_stock_value' => 0);
    if (!dash_table_exists($conn, 'stock_inward_items')) { return $out; }
    $types = 'i';
    $params = array($businessId);
    $where = "WHERE si.business_id = ? AND si.item_status IN ('active','out_of_stock')";
    $where .= dash_branch_filter_sql($selectedBranchId, $accessibleBranchIds, 'si', $types, $params);
    $row = dash_fetch_one($conn, "
        SELECT COALESCE(SUM(CASE WHEN si.item_status IN ('active','out_of_stock') THEN si.qty ELSE 0 END),0) AS total_stock_qty,
               COALESCE(SUM(CASE WHEN si.item_status = 'active' AND si.available_qty > 0 THEN si.available_qty ELSE 0 END),0) AS available_stock_qty,
               COALESCE(SUM(CASE WHEN si.item_status IN ('active','out_of_stock') THEN GREATEST(si.qty - GREATEST(si.available_qty, 0), 0) ELSE 0 END),0) AS sold_stock_qty,
               COALESCE(SUM(CASE WHEN si.item_status = 'active' AND si.available_qty > 0 THEN si.available_qty * si.selling_rate ELSE 0 END),0) AS available_stock_value
        FROM stock_inward_items si
        {$where}
    ", $types, $params);
    return array_merge($out, $row ?: array());
}

function dash_low_stock_alerts(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds)
{
    if (!dash_table_exists($conn, 'stock_inward_items')) { return array(); }
    $branchJoin = dash_table_exists($conn, 'branches') ? "LEFT JOIN branches br ON br.business_id = si.business_id AND br.branch_id = si.branch_id" : "";
    $types = 'i';
    $params = array($businessId);
    $where = "WHERE si.business_id = ? AND si.item_status IN ('active','out_of_stock') AND si.available_qty <= 2";
    $where .= dash_branch_filter_sql($selectedBranchId, $accessibleBranchIds, 'si', $types, $params);
    return dash_fetch_all($conn, "
        SELECT si.stock_item_id, si.article_no, si.article_name, si.size, si.color, si.available_qty,
               COALESCE(br.branch_name, '-') AS branch_name, COALESCE(br.floor_name, '') AS floor_name
        FROM stock_inward_items si
        {$branchJoin}
        {$where}
        ORDER BY si.available_qty ASC, si.stock_item_id DESC
        LIMIT 12
    ", $types, $params);
}

function dash_pending_payments(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range)
{
    if (!dash_table_exists($conn, 'bills')) { return array(); }
    $types = '';
    $params = array();
    $where = dash_pending_bill_where($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range, $types, $params, 'b');
    $branchJoin = dash_table_exists($conn, 'branches') ? "LEFT JOIN branches br ON br.business_id = b.business_id AND br.branch_id = b.branch_id" : "";
    return dash_fetch_all($conn, "
        SELECT b.bill_id, b.bill_no, b.order_no, b.bill_date, b.bill_time,
               COALESCE(NULLIF(b.customer_name,''), 'Walk-in Customer') AS customer_name,
               COALESCE(b.customer_mobile, '') AS customer_mobile,
               b.net_amount, b.paid_amount, b.balance_amount, b.payment_status,
               COALESCE(br.branch_name, '-') AS branch_name, COALESCE(br.floor_name, '') AS floor_name
        FROM bills b
        {$branchJoin}
        {$where}
        ORDER BY b.bill_date DESC, b.bill_time DESC, b.bill_id DESC
    ", $types, $params);
}

function dash_customer_outstanding(mysqli $conn, $businessId, $selectedBranchId)
{
    if ($selectedBranchId > 0 && dash_table_exists($conn, 'bills')) {
        return dash_fetch_all($conn, "
            SELECT COALESCE(b.customer_id, 0) AS id,
                   COALESCE(NULLIF(b.customer_name,''), 'Walk-in Customer') AS customer_name,
                   COALESCE(MAX(b.customer_mobile), '') AS mobile,
                   COALESCE(SUM(b.balance_amount),0) AS balance_amount
            FROM bills b
            WHERE b.business_id = ? AND b.branch_id = ? AND b.bill_status = 'active' AND b.balance_amount > 0
            GROUP BY COALESCE(b.customer_id,0), COALESCE(NULLIF(b.customer_name,''), 'Walk-in Customer')
            ORDER BY balance_amount DESC
            LIMIT 10
        ", 'ii', array($businessId, $selectedBranchId));
    }

    if (dash_table_exists($conn, 'customer_outstanding')) {
        $customerJoin = dash_table_exists($conn, 'customers') ? "LEFT JOIN customers c ON c.business_id = co.business_id AND c.customer_id = co.customer_id" : "";
        return dash_fetch_all($conn, "
            SELECT co.customer_id AS id,
                   COALESCE(c.customer_name, CONCAT('Customer #', co.customer_id)) AS customer_name,
                   COALESCE(c.mobile, '') AS mobile,
                   co.balance_amount
            FROM customer_outstanding co
            {$customerJoin}
            WHERE co.business_id = ? AND co.balance_amount > 0
            ORDER BY co.balance_amount DESC
            LIMIT 10
        ", 'i', array($businessId));
    }

    return array();
}

function dash_supplier_outstanding(mysqli $conn, $businessId)
{
    if (dash_table_exists($conn, 'vendor_outstanding')) {
        $supplierJoin = dash_table_exists($conn, 'suppliers') ? "LEFT JOIN suppliers s ON s.business_id = vo.business_id AND s.supplier_id = vo.supplier_id" : "";
        return dash_fetch_all($conn, "
            SELECT vo.supplier_id AS id,
                   COALESCE(s.supplier_name, CONCAT('Supplier #', vo.supplier_id)) AS supplier_name,
                   COALESCE(s.mobile, '') AS mobile,
                   vo.balance_amount
            FROM vendor_outstanding vo
            {$supplierJoin}
            WHERE vo.business_id = ? AND vo.balance_amount > 0
            ORDER BY vo.balance_amount DESC
            LIMIT 10
        ", 'i', array($businessId));
    }

    if (dash_table_exists($conn, 'suppliers') && dash_column_exists($conn, 'suppliers', 'current_outstanding')) {
        return dash_fetch_all($conn, "
            SELECT supplier_id AS id, supplier_name, mobile, current_outstanding AS balance_amount
            FROM suppliers
            WHERE business_id = ? AND status = 1 AND current_outstanding > 0
            ORDER BY current_outstanding DESC
            LIMIT 10
        ", 'i', array($businessId));
    }

    return array();
}

function dash_branch_performance(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range)
{
    if (!dash_table_exists($conn, 'bills') || !dash_table_exists($conn, 'branches')) { return array(); }
    $types = '';
    $params = array();
    $where = dash_bill_where($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range, $types, $params, 'b');
    $where .= " AND b.bill_status = 'active'";
    return dash_fetch_all($conn, "
        SELECT br.branch_id, br.branch_name, br.floor_name,
               COUNT(b.bill_id) AS bill_count,
               COALESCE(SUM(b.net_amount),0) AS net_amount,
               COALESCE(SUM(b.balance_amount),0) AS balance_amount
        FROM bills b
        INNER JOIN branches br ON br.business_id = b.business_id AND br.branch_id = b.branch_id
        {$where}
        GROUP BY br.branch_id, br.branch_name, br.floor_name
        ORDER BY net_amount DESC
        LIMIT 10
    ", $types, $params);
}

function dash_user_performance(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds, array $range)
{
    if (!dash_table_exists($conn, 'bills')) { return array(); }
    $userNameExpr = "'System'";
    $userJoin = '';
    $roleJoin = '';
    $roleNameExpr = "''";
    if (dash_table_exists($conn, 'users')) {
        $nameCol = dash_column_exists($conn, 'users', 'full_name') ? 'full_name' : (dash_column_exists($conn, 'users', 'name') ? 'name' : 'username');
        $userNameExpr = "COALESCE(NULLIF(u.{$nameCol}, ''), NULLIF(u.username, ''), 'User')";
        $userJoin = "LEFT JOIN users u ON u.business_id = b.business_id AND u.user_id = b.created_by";
        if (dash_table_exists($conn, 'roles')) {
            $roleJoin = "LEFT JOIN roles r ON r.business_id = u.business_id AND r.role_id = u.role_id";
            $roleNameExpr = "COALESCE(r.role_name, '')";
        }
    }
    $types = '';
    $params = array();
    $where = dash_bill_where($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range, $types, $params, 'b');
    $where .= " AND b.bill_status = 'active'";
    return dash_fetch_all($conn, "
        SELECT b.created_by,
               {$userNameExpr} AS user_name,
               {$roleNameExpr} AS role_name,
               COUNT(b.bill_id) AS bill_count,
               COALESCE(SUM(b.net_amount),0) AS net_amount,
               COALESCE(SUM(b.paid_amount),0) AS paid_amount
        FROM bills b
        {$userJoin}
        {$roleJoin}
        {$where}
        GROUP BY b.created_by, user_name, role_name
        ORDER BY net_amount DESC
        LIMIT 10
    ", $types, $params);
}

function dash_recent_activities(mysqli $conn, $businessId, $selectedBranchId, array $accessibleBranchIds)
{
    $table = dash_table_exists($conn, 'activity_logs') ? 'activity_logs' : (dash_table_exists($conn, 'business_activity_logs') ? 'business_activity_logs' : '');
    if ($table === '') { return array(); }

    $userJoin = '';
    $userNameExpr = "'System'";
    if (dash_table_exists($conn, 'users')) {
        $nameCol = dash_column_exists($conn, 'users', 'full_name') ? 'full_name' : (dash_column_exists($conn, 'users', 'name') ? 'name' : 'username');
        $userJoin = "LEFT JOIN users u ON u.business_id = a.business_id AND u.user_id = a.user_id";
        $userNameExpr = "COALESCE(NULLIF(u.{$nameCol}, ''), NULLIF(u.username, ''), 'System')";
    }

    $types = 'i';
    $params = array($businessId);
    $where = "WHERE a.business_id = ?";
    $where .= dash_branch_filter_sql($selectedBranchId, $accessibleBranchIds, 'a', $types, $params);

    return dash_fetch_all($conn, "
        SELECT a.module_name, a.action_type, a.record_id, a.created_at, {$userNameExpr} AS user_name
        FROM {$table} a
        {$userJoin}
        {$where}
        ORDER BY a.created_at DESC
        LIMIT 15
    ", $types, $params);
}

function dash_icon_for_url($url)
{
    $url = strtolower((string)$url);
    if (strpos($url, 'bill-create') !== false) { return 'plus-circle'; }
    if (strpos($url, 'bill-list') !== false) { return 'receipt-text'; }
    if (strpos($url, 'cashier') !== false || strpos($url, 'pending') !== false) { return 'wallet-cards'; }
    if (strpos($url, 'stock-inward') !== false) { return 'package-plus'; }
    if (strpos($url, 'stock-list') !== false) { return 'boxes'; }
    if (strpos($url, 'customers') !== false || strpos($url, 'customer') !== false) { return 'users'; }
    if (strpos($url, 'supplier') !== false || strpos($url, 'vendor') !== false || strpos($url, 'suppiler') !== false) { return 'truck'; }
    if (strpos($url, 'sales-report') !== false) { return 'trending-up'; }
    if (strpos($url, 'ledger') !== false || strpos($url, 'report') !== false) { return 'book-open'; }
    if (strpos($url, 'activity') !== false || strpos($url, 'log') !== false) { return 'history'; }
    return 'circle-dot';
}

function dash_subtitle_for_url($url, $title)
{
    $url = strtolower((string)$url);
    if (strpos($url, 'bill-create') !== false) { return 'Start POS billing'; }
    if (strpos($url, 'bill-list') !== false) { return 'View and print bills'; }
    if (strpos($url, 'cashier') !== false || strpos($url, 'pending') !== false) { return 'Collect unpaid bills'; }
    if (strpos($url, 'stock-inward') !== false) { return 'Add new stock'; }
    if (strpos($url, 'stock-list') !== false) { return 'Firm-wise stock'; }
    if (strpos($url, 'customers') !== false) { return 'Customer master'; }
    if (strpos($url, 'suppliers') !== false) { return 'Supplier master'; }
    if (strpos($url, 'sales-report') !== false) { return 'Sales analytics'; }
    if (strpos($url, 'ledger') !== false) { return 'Ledger tracking'; }
    if (strpos($url, 'activity') !== false) { return 'Audit trail'; }
    return 'Open module';
}

function dash_quick_actions(mysqli $conn, $businessId)
{
    $wanted = array(
        'bill-create.php', 'bill-list.php', 'cashier-pending-bills.php', 'cashier-collections.php',
        'stock-inward.php', 'stock-list.php', 'customers.php', 'suppliers.php',
        'sales-report.php', 'payment-ledger-report.php', 'customer-ledger-report.php', 'supplier-ledger-report.php',
        'suppiler-ledger-report.php', 'activity-logs.php'
    );
    $rows = array();

    if (dash_table_exists($conn, 'business_sidebar_menus')) {
        $dbRows = dash_fetch_all($conn, "
            SELECT menu_title, menu_url, icon, sort_order
            FROM business_sidebar_menus
            WHERE business_id = ?
              AND is_active = 1
              AND show_in_sidebar = 1
              AND menu_url IS NOT NULL
              AND menu_url <> '#'
            ORDER BY sort_order ASC, id ASC
        ", 'i', array($businessId));
        foreach ($dbRows as $row) {
            $url = trim((string)($row['menu_url'] ?? ''));
            if ($url === '') { continue; }
            if (!in_array($url, $wanted, true)) { continue; }
            $rows[] = array(
                'url' => $url,
                'title' => $row['menu_title'] ?: ucwords(str_replace(array('-', '.php'), array(' ', ''), $url)),
                'icon' => dash_icon_for_url($url),
                'subtitle' => dash_subtitle_for_url($url, $row['menu_title'] ?? ''),
            );
        }
    }

    if ($rows) { return array_slice($rows, 0, 12); }

    return array(
        array('url' => 'bill-create.php', 'icon' => 'plus-circle', 'title' => 'Create Bill', 'subtitle' => 'Start POS billing'),
        array('url' => 'bill-list.php', 'icon' => 'receipt-text', 'title' => 'Bill List', 'subtitle' => 'View and print bills'),
        array('url' => 'cashier-pending-bills.php', 'icon' => 'wallet-cards', 'title' => 'Pending Bills', 'subtitle' => 'Collect unpaid bills'),
        array('url' => 'stock-inward.php', 'icon' => 'package-plus', 'title' => 'Stock Inward', 'subtitle' => 'Add new stock'),
        array('url' => 'stock-list.php', 'icon' => 'boxes', 'title' => 'Stock List', 'subtitle' => 'Firm-wise stock'),
        array('url' => 'customers.php', 'icon' => 'users', 'title' => 'Customers', 'subtitle' => 'Customer master'),
        array('url' => 'suppliers.php', 'icon' => 'truck', 'title' => 'Suppliers', 'subtitle' => 'Supplier master'),
        array('url' => 'sales-report.php', 'icon' => 'trending-up', 'title' => 'Sales Report', 'subtitle' => 'Sales analytics'),
        array('url' => 'payment-ledger-report.php', 'icon' => 'book-open', 'title' => 'Payment Ledger', 'subtitle' => 'Payment tracking'),
        array('url' => 'customer-ledger-report.php', 'icon' => 'user-round-check', 'title' => 'Customer Ledger', 'subtitle' => 'Customer dues'),
        array('url' => 'suppiler-ledger-report.php', 'icon' => 'clipboard-list', 'title' => 'Supplier Ledger', 'subtitle' => 'Vendor dues'),
        array('url' => 'activity-logs.php', 'icon' => 'history', 'title' => 'Activity Logs', 'subtitle' => 'Audit trail'),
    );
}

try {
    if (function_exists('require_business_login')) { require_business_login(); }
    if (function_exists('require_page_access')) { require_page_access($conn, 'dashboard.php'); }

    if (function_exists('mysqli_set_charset')) { mysqli_set_charset($conn, 'utf8mb4'); }

    $businessId = dash_current_business_id();
    if ($businessId <= 0) { dash_json(array('success' => false, 'message' => 'Business session missing. Please login again.'), 401); }

    $userId = dash_current_user_id();
    $isAdmin = dash_is_admin($conn);
    $branches = dash_accessible_branches($conn, $businessId, $userId, $isAdmin);
    $accessibleBranchIds = dash_branch_ids($branches);
    $selectedBranchId = (int)($_GET['branch_id'] ?? 0);
    $range = dash_period_range($_GET);

    $action = (string)($_GET['action'] ?? 'summary');
    if ($action !== 'summary' && $action !== 'init') {
        dash_json(array('success' => false, 'message' => 'Invalid dashboard API action.'), 400);
    }

    dash_json(array(
        'success' => true,
        'period_label' => $range['label'],
        'date_from' => $range['from'],
        'date_to' => $range['to'],
        'branches' => $branches,
        'quick_actions' => dash_quick_actions($conn, $businessId),
        'kpis' => dash_kpis($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range),
        'sales_trend' => dash_sales_trend($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range),
        'payment_mix' => dash_payment_mix($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range),
        'stock_overview' => dash_stock_overview($conn, $businessId, $selectedBranchId, $accessibleBranchIds),
        'low_stock_alerts' => dash_low_stock_alerts($conn, $businessId, $selectedBranchId, $accessibleBranchIds),
        'pending_payments' => dash_pending_payments($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range),
        'customer_outstanding' => dash_customer_outstanding($conn, $businessId, $selectedBranchId),
        'supplier_outstanding' => dash_supplier_outstanding($conn, $businessId),
        'branch_performance' => dash_branch_performance($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range),
        'user_performance' => dash_user_performance($conn, $businessId, $selectedBranchId, $accessibleBranchIds, $range),
        'recent_activities' => dash_recent_activities($conn, $businessId, $selectedBranchId, $accessibleBranchIds),
    ));
} catch (Throwable $e) {
    dash_json(array('success' => false, 'message' => 'Dashboard API error: ' . $e->getMessage()), 500);
}
