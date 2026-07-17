<?php
/**
 * GK Footwear POS - Customer Collections API
 * Supports two separate pages:
 *  1) pending-collections.php - pending/partial invoices only with payment collection
 *  2) collections.php - received payment history only
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

header('Content-Type: application/json; charset=utf-8');

function cc_json($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cc_business_id() {
    if (function_exists('current_business_id')) {
        return (int) current_business_id();
    }
    return (int)($_SESSION['business_id'] ?? 0);
}

function cc_branch_id() {
    if (function_exists('current_branch_id')) {
        return (int) current_branch_id();
    }
    return (int)($_SESSION['branch_id'] ?? $_SESSION['default_branch_id'] ?? 0);
}

function cc_user_id() {
    if (function_exists('current_user_id')) {
        return (int) current_user_id();
    }
    return (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? 0);
}

function cc_role_id() {
    if (function_exists('current_role_id')) {
        return (int) current_role_id();
    }
    return (int)($_SESSION['role_id'] ?? 0);
}

function cc_table_exists(mysqli $conn, $table) {
    if (function_exists('table_exists')) {
        return table_exists($conn, $table);
    }
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    mysqli_stmt_bind_param($stmt, 's', $table);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return ((int)($row['total'] ?? 0)) > 0;
}

function cc_bind(mysqli_stmt $stmt, $types, array &$params) {
    if ($types === '' || empty($params)) {
        return;
    }
    $bind = array($stmt, $types);
    foreach ($params as $key => $value) {
        $bind[] = &$params[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $bind);
}

function cc_all(mysqli $conn, $sql, $types = '', array $params = array()) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }
    cc_bind($stmt, $types, $params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function cc_one(mysqli $conn, $sql, $types = '', array $params = array()) {
    $rows = cc_all($conn, $sql, $types, $params);
    return $rows ? $rows[0] : null;
}

function cc_exec(mysqli $conn, $sql, $types = '', array $params = array()) {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception(mysqli_error($conn));
    }
    cc_bind($stmt, $types, $params);
    if (!mysqli_stmt_execute($stmt)) {
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception($error);
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected;
}

function cc_is_admin(mysqli $conn) {
    if (function_exists('is_business_admin') && is_business_admin($conn)) {
        return true;
    }
    $roleName = strtolower((string)($_SESSION['role_name'] ?? $_SESSION['role'] ?? ''));
    return in_array($roleName, array('admin', 'business admin', 'administrator'), true);
}

function cc_allowed_branch_ids(mysqli $conn, $businessId, $userId) {
    if (cc_is_admin($conn)) {
        return null;
    }

    if ($userId > 0 && cc_table_exists($conn, 'user_branch_access')) {
        $rows = cc_all($conn, "
            SELECT branch_id
            FROM user_branch_access
            WHERE business_id = ?
              AND user_id = ?
              AND access_status = 1
        ", 'ii', array($businessId, $userId));
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int)$row['branch_id'];
        }
        if (!empty($ids)) {
            return array_values(array_unique($ids));
        }
    }

    $branchId = cc_branch_id();
    if ($branchId > 0) {
        return array($branchId);
    }

    return null;
}

function cc_add_branch_clause($column, array $allowedIds, &$sql, &$types, array &$params) {
    if (empty($allowedIds)) {
        $sql .= " AND 1 = 0";
        return;
    }
    $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
    $sql .= " AND {$column} IN ({$placeholders})";
    foreach ($allowedIds as $id) {
        $types .= 'i';
        $params[] = (int)$id;
    }
}

function cc_branches(mysqli $conn, $businessId, $allowedIds) {
    $sql = "SELECT branch_id, branch_name, floor_name FROM branches WHERE business_id = ? AND status = 1";
    $types = 'i';
    $params = array($businessId);
    if (is_array($allowedIds)) {
        cc_add_branch_clause('branch_id', $allowedIds, $sql, $types, $params);
    }
    $sql .= " ORDER BY branch_name ASC";
    return cc_all($conn, $sql, $types, $params);
}

function cc_payment_methods(mysqli $conn, $businessId) {
    return cc_all($conn, "
        SELECT payment_method_id, payment_method_name, method_type
        FROM payment_methods
        WHERE business_id = ? AND status = 1
        ORDER BY FIELD(method_type,'cash','upi','card','cheque','credit','split','other'), payment_method_name
    ", 'i', array($businessId));
}

// FIXED: Simplified pending filters - shows ALL pending/partial bills correctly
function cc_pending_filters(mysqli $conn, $businessId, $allowedIds, &$sql, &$types, array &$params) {
    $q = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));
    $branchId = (int)($_GET['branch_id'] ?? 0);
    $paymentStatus = strtolower(trim((string)($_GET['payment_status'] ?? '')));
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    // FIX: Simplified WHERE clause - show all pending/partial bills regardless of balance
    $sql .= " WHERE b.business_id = ?
                AND b.bill_status = 'active'
                AND b.payment_status IN ('pending', 'partial')";
    $types .= 'i';
    $params[] = $businessId;

    if (is_array($allowedIds)) {
        cc_add_branch_clause('b.branch_id', $allowedIds, $sql, $types, $params);
    }

    if ($branchId > 0) {
        if (!is_array($allowedIds) || in_array($branchId, $allowedIds, true)) {
            $sql .= " AND b.branch_id = ?";
            $types .= 'i';
            $params[] = $branchId;
        } else {
            $sql .= " AND 1 = 0";
        }
    }

    // FIX: Additional filter for pending/partial
    if (in_array($paymentStatus, array('pending', 'partial'), true)) {
        $sql .= " AND b.payment_status = ?";
        $types .= 's';
        $params[] = $paymentStatus;
    }

    if ($dateFrom !== '') {
        $sql .= " AND b.bill_date >= ?";
        $types .= 's';
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $sql .= " AND b.bill_date <= ?";
        $types .= 's';
        $params[] = $dateTo;
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= " AND (b.bill_no LIKE ? OR b.order_no LIKE ? OR b.customer_name LIKE ? OR b.customer_mobile LIKE ? OR bb.barcode_value LIKE ?)";
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }
}

function cc_pending_stats(mysqli $conn, $businessId, $allowedIds) {
    $sql = "
        SELECT
            COUNT(*) AS pending_invoices,
            SUM(CASE WHEN b.payment_status = 'pending' THEN 1 ELSE 0 END) AS unpaid_invoices,
            SUM(CASE WHEN b.payment_status = 'partial' THEN 1 ELSE 0 END) AS partial_invoices,
            COALESCE(SUM(b.balance_amount),0) AS pending_due,
            COALESCE(SUM(b.net_amount),0) AS invoice_total,
            COALESCE(SUM(b.paid_amount),0) AS already_collected
        FROM bills b
        LEFT JOIN bill_barcodes bb ON bb.bill_id = b.bill_id AND bb.business_id = b.business_id
    ";
    $types = '';
    $params = array();
    cc_pending_filters($conn, $businessId, $allowedIds, $sql, $types, $params);
    return cc_one($conn, $sql, $types, $params) ?: array();
}

function cc_pending_rows(mysqli $conn, $businessId, $allowedIds) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $base = "
        FROM bills b
        LEFT JOIN bill_barcodes bb ON bb.bill_id = b.bill_id AND bb.business_id = b.business_id AND bb.barcode_status = 'active'
        LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
        LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id
    ";
    $whereTypes = '';
    $whereParams = array();
    $whereSql = $base;
    cc_pending_filters($conn, $businessId, $allowedIds, $whereSql, $whereTypes, $whereParams);

    $countRow = cc_one($conn, "SELECT COUNT(*) AS total " . $whereSql, $whereTypes, $whereParams);
    $total = (int)($countRow['total'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $perPage));

    // FIX: Added ORDER BY to show newest bills first
    $sql = "
        SELECT
            b.bill_id, b.branch_id, b.bill_no, b.order_no, b.bill_date, b.bill_time,
            b.customer_id, b.customer_name, b.customer_mobile,
            b.net_amount, b.paid_amount, b.balance_amount, b.payment_status, b.bill_status,
            b.selling_amount, b.item_discount_total, b.bill_discount_amount, b.round_off,
            b.tax_amount, b.created_at,
            bb.barcode_value,
            br.branch_name, br.floor_name,
            u.name AS created_by_name,
            (SELECT COUNT(*) FROM bill_items bi WHERE bi.bill_id = b.bill_id AND bi.business_id = b.business_id) AS item_count,
            (SELECT COALESCE(SUM(bi.qty),0) FROM bill_items bi WHERE bi.bill_id = b.bill_id AND bi.business_id = b.business_id) AS total_qty
        " . $whereSql . "
        ORDER BY b.bill_date DESC, b.bill_time DESC, b.bill_id DESC
        LIMIT ? OFFSET ?
    ";
    $types = $whereTypes . 'ii';
    $params = $whereParams;
    $params[] = $perPage;
    $params[] = $offset;
    $rows = cc_all($conn, $sql, $types, $params);

    return array(
        'rows' => $rows,
        'pagination' => array('page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages)
    );
}

function cc_collection_filters($businessId, $allowedIds, &$sql, &$types, array &$params) {
    $q = trim((string)($_GET['q'] ?? $_GET['search'] ?? ''));
    $branchId = (int)($_GET['branch_id'] ?? 0);
    $methodId = (int)($_GET['payment_method_id'] ?? 0);
    $dateFrom = trim((string)($_GET['date_from'] ?? ''));
    $dateTo = trim((string)($_GET['date_to'] ?? ''));

    $sql .= " WHERE bp.business_id = ?
                AND bp.payment_status = 'received'
                AND bp.paid_amount > 0";
    $types .= 'i';
    $params[] = $businessId;

    if (is_array($allowedIds)) {
        cc_add_branch_clause('bp.branch_id', $allowedIds, $sql, $types, $params);
    }

    if ($branchId > 0) {
        if (!is_array($allowedIds) || in_array($branchId, $allowedIds, true)) {
            $sql .= " AND bp.branch_id = ?";
            $types .= 'i';
            $params[] = $branchId;
        } else {
            $sql .= " AND 1 = 0";
        }
    }

    if ($methodId > 0) {
        $sql .= " AND bp.payment_method_id = ?";
        $types .= 'i';
        $params[] = $methodId;
    }

    if ($dateFrom !== '') {
        $sql .= " AND DATE(bp.collected_at) >= ?";
        $types .= 's';
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $sql .= " AND DATE(bp.collected_at) <= ?";
        $types .= 's';
        $params[] = $dateTo;
    }

    if ($q !== '') {
        $like = '%' . $q . '%';
        $sql .= " AND (b.bill_no LIKE ? OR b.order_no LIKE ? OR b.customer_name LIKE ? OR b.customer_mobile LIKE ? OR bp.reference_no LIKE ? OR pm.payment_method_name LIKE ?)";
        $types .= 'ssssss';
        array_push($params, $like, $like, $like, $like, $like, $like);
    }
}

function cc_collection_stats(mysqli $conn, $businessId, $allowedIds) {
    $sql = "
        SELECT
            COUNT(*) AS total_collections,
            COALESCE(SUM(bp.paid_amount),0) AS total_collected,
            COALESCE(SUM(CASE WHEN DATE(bp.collected_at) = CURDATE() THEN bp.paid_amount ELSE 0 END),0) AS today_collected,
            COUNT(DISTINCT bp.bill_id) AS collected_invoices
        FROM bill_payments bp
        INNER JOIN bills b ON b.bill_id = bp.bill_id AND b.business_id = bp.business_id
        LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id AND pm.business_id = bp.business_id
    ";
    $types = '';
    $params = array();
    cc_collection_filters($businessId, $allowedIds, $sql, $types, $params);
    return cc_one($conn, $sql, $types, $params) ?: array();
}

function cc_collection_rows(mysqli $conn, $businessId, $allowedIds) {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $base = "
        FROM bill_payments bp
        INNER JOIN bills b ON b.bill_id = bp.bill_id AND b.business_id = bp.business_id
        LEFT JOIN cashier_collections cc ON cc.payment_id = bp.payment_id AND cc.business_id = bp.business_id
        LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id AND pm.business_id = bp.business_id
        LEFT JOIN branches br ON br.branch_id = bp.branch_id AND br.business_id = bp.business_id
        LEFT JOIN users u ON u.user_id = bp.collected_by AND u.business_id = bp.business_id
    ";
    $whereTypes = '';
    $whereParams = array();
    $whereSql = $base;
    cc_collection_filters($businessId, $allowedIds, $whereSql, $whereTypes, $whereParams);

    $countRow = cc_one($conn, "SELECT COUNT(*) AS total " . $whereSql, $whereTypes, $whereParams);
    $total = (int)($countRow['total'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $perPage));

    $sql = "
        SELECT
            bp.payment_id, bp.bill_id, bp.branch_id, bp.paid_amount, bp.reference_no, bp.payment_note,
            bp.payment_status, bp.collected_by, bp.collected_at,
            b.bill_no, b.order_no, b.bill_date, b.customer_id, b.customer_name, b.customer_mobile,
            b.net_amount, b.paid_amount AS bill_paid_amount, b.balance_amount, b.payment_status AS bill_payment_status,
            cc.collection_id, cc.collection_status,
            pm.payment_method_name, pm.method_type,
            br.branch_name, br.floor_name,
            u.name AS cashier_name
        " . $whereSql . "
        ORDER BY bp.collected_at DESC, bp.payment_id DESC
        LIMIT ? OFFSET ?
    ";
    $types = $whereTypes . 'ii';
    $params = $whereParams;
    $params[] = $perPage;
    $params[] = $offset;
    $rows = cc_all($conn, $sql, $types, $params);

    return array(
        'rows' => $rows,
        'pagination' => array('page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => $totalPages)
    );
}


function cc_normalize_scan_code($value) {
    $value = strtoupper(trim((string)$value));
    return preg_replace('/[^A-Z0-9]/', '', $value);
}

function cc_scan_candidates($raw) {
    $raw = trim((string)$raw);
    $candidates = array();
    $add = function($value) use (&$candidates) {
        $value = trim((string)$value);
        if ($value !== '') {
            $candidates[] = $value;
        }
    };

    $add($raw);

    $parts = @parse_url($raw);
    if (is_array($parts)) {
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            foreach (array('barcode','bill_barcode','bill_no','order_no','q','code') as $key) {
                if (!empty($queryParams[$key])) {
                    $add($queryParams[$key]);
                }
            }
        }
        if (!empty($parts['path'])) {
            $segments = explode('/', trim($parts['path'], '/'));
            foreach ($segments as $seg) {
                if (preg_match('/^(BILL|ORD|GK|STK)[A-Z0-9\-]+$/i', $seg)) {
                    $add($seg);
                }
            }
        }
    }

    if (preg_match_all('/(?:barcode|bill_barcode|bill_no|order_no|code|q)\s*[=:]\s*([A-Z0-9\-\/]+)/i', $raw, $matches)) {
        foreach ($matches[1] as $match) {
            $add($match);
        }
    }

    $clean = cc_normalize_scan_code($raw);
    if ($clean !== '') {
        $add($clean);
        if (preg_match('/(BILL|ORD|GK|STK)(0*[0-9]+)$/i', $clean, $m)) {
            $add($m[1] . '-' . $m[2]);
            $add((string)((int)$m[2]));
        }
    }

    return array_values(array_unique(array_filter($candidates, function($v) { return trim((string)$v) !== ''; })));
}

function cc_bill_from_id_or_code(mysqli $conn, $businessId, $allowedIds, $billId, $rawCode) {
    $candidates = cc_scan_candidates($rawCode);
    $conditions = array();
    $types = 'i';
    $params = array($businessId);

    $sql = "
        SELECT
            b.*, bb.barcode_value, br.branch_name, br.floor_name, u.name AS created_by_name
        FROM bills b
        LEFT JOIN (
            SELECT business_id, bill_id, MAX(barcode_value) AS barcode_value
            FROM bill_barcodes
            WHERE barcode_status <> 'deleted'
            GROUP BY business_id, bill_id
        ) bb ON bb.bill_id = b.bill_id AND bb.business_id = b.business_id
        LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
        LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id
        WHERE b.business_id = ?
    ";

    if (is_array($allowedIds)) {
        cc_add_branch_clause('b.branch_id', $allowedIds, $sql, $types, $params);
    }

    if ($billId > 0) {
        $conditions[] = 'b.bill_id = ?';
        $types .= 'i';
        $params[] = $billId;
    }

    foreach ($candidates as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate === '') {
            continue;
        }
        $conditions[] = '(bb.barcode_value = ? OR b.bill_no = ? OR b.order_no = ?)';
        $types .= 'sss';
        array_push($params, $candidate, $candidate, $candidate);

        $normalized = cc_normalize_scan_code($candidate);
        if ($normalized !== '') {
            $conditions[] = "(
                REPLACE(REPLACE(REPLACE(UPPER(COALESCE(bb.barcode_value,'')), '-', ''), ' ', ''), '/', '') = ?
                OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(b.bill_no,'')), '-', ''), ' ', ''), '/', '') = ?
                OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(b.order_no,'')), '-', ''), ' ', ''), '/', '') = ?
            )";
            $types .= 'sss';
            array_push($params, $normalized, $normalized, $normalized);

            if (strlen($normalized) >= 3) {
                $likeEnding = '%' . $normalized;
                $conditions[] = "(
                    REPLACE(REPLACE(REPLACE(UPPER(COALESCE(bb.barcode_value,'')), '-', ''), ' ', ''), '/', '') LIKE ?
                    OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(b.bill_no,'')), '-', ''), ' ', ''), '/', '') LIKE ?
                    OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(b.order_no,'')), '-', ''), ' ', ''), '/', '') LIKE ?
                )";
                $types .= 'sss';
                array_push($params, $likeEnding, $likeEnding, $likeEnding);
            }
        }
    }

    if (empty($conditions)) {
        return null;
    }

    $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
    $sql .= " ORDER BY b.bill_id DESC LIMIT 1";
    return cc_one($conn, $sql, $types, $params);
}

function cc_find_pending_by_barcode(mysqli $conn, $businessId, $allowedIds) {
    $raw = trim((string)($_GET['barcode'] ?? $_GET['scan_value'] ?? $_GET['q'] ?? $_GET['search'] ?? ''));
    $billId = (int)($_GET['bill_id'] ?? 0);

    if ($billId <= 0 && $raw !== '') {
        $parts = @parse_url($raw);
        if (is_array($parts) && !empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
            foreach (array('bill_id', 'bill', 'id') as $key) {
                if (!empty($queryParams[$key]) && (int)$queryParams[$key] > 0) {
                    $billId = (int)$queryParams[$key];
                    break;
                }
            }
        }
        if ($billId <= 0 && preg_match('/(?:bill_id|bill|id)\s*[=:]\s*(\d+)/i', $raw, $m)) {
            $billId = (int)$m[1];
        }
    }

    if ($billId <= 0 && $raw === '') {
        cc_json(array('success' => false, 'message' => 'Enter or scan a bill barcode number.'), 422);
    }

    $bill = cc_bill_from_id_or_code($conn, $businessId, $allowedIds, $billId, $raw);
    if (!$bill) {
        cc_json(array('success' => false, 'message' => 'No bill found for this barcode. Please check the bill barcode number.'), 404);
    }

    $billStatus = strtolower((string)($bill['bill_status'] ?? ''));
    $paymentStatus = strtolower((string)($bill['payment_status'] ?? ''));
    $balanceAmount = (float)($bill['balance_amount'] ?? 0);

    if ($billStatus !== 'active') {
        cc_json(array('success' => false, 'message' => 'This bill is ' . $billStatus . ' and cannot be collected.', 'bill' => $bill), 409);
    }

    if ($paymentStatus === 'paid' || $balanceAmount <= 0.0001) {
        cc_json(array('success' => false, 'already_paid' => true, 'message' => 'This bill is already fully paid. Check the Collections / Paid Bills list.', 'bill' => $bill), 409);
    }

    if (!in_array($paymentStatus, array('pending', 'partial'), true)) {
        cc_json(array('success' => false, 'message' => 'This bill is not available for pending collection.', 'bill' => $bill), 409);
    }

    cc_json(array('success' => true, 'bill_id' => (int)$bill['bill_id'], 'bill' => $bill, 'message' => 'Pending bill found.'));
}

function cc_get_pending_bill(mysqli $conn, $businessId, $allowedIds) {
    $billId = (int)($_GET['bill_id'] ?? 0);
    if ($billId <= 0) {
        cc_json(array('success' => false, 'message' => 'Bill id is required.'), 422);
    }

    $sql = "
        SELECT
            b.*, bb.barcode_value, br.branch_name, br.floor_name, u.name AS created_by_name
        FROM bills b
        LEFT JOIN bill_barcodes bb ON bb.bill_id = b.bill_id AND bb.business_id = b.business_id
        LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
        LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id
        WHERE b.business_id = ?
          AND b.bill_id = ?
          AND b.bill_status = 'active'
          AND b.payment_status IN ('pending', 'partial')
    ";
    $types = 'ii';
    $params = array($businessId, $billId);
    if (is_array($allowedIds)) {
        cc_add_branch_clause('b.branch_id', $allowedIds, $sql, $types, $params);
    }
    $sql .= " LIMIT 1";
    $bill = cc_one($conn, $sql, $types, $params);
    if (!$bill) {
        cc_json(array('success' => false, 'message' => 'Pending bill not found or already collected.'), 404);
    }

    $items = cc_all($conn, "
        SELECT bi.*, sii.color
        FROM bill_items bi
        LEFT JOIN stock_inward_items sii ON sii.stock_item_id = bi.stock_item_id AND sii.business_id = bi.business_id
        WHERE bi.business_id = ? AND bi.bill_id = ?
        ORDER BY bi.bill_item_id ASC
    ", 'ii', array($businessId, $billId));

    $payments = cc_all($conn, "
        SELECT bp.*, pm.payment_method_name, pm.method_type, u.name AS cashier_name
        FROM bill_payments bp
        LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id AND pm.business_id = bp.business_id
        LEFT JOIN users u ON u.user_id = bp.collected_by AND u.business_id = bp.business_id
        WHERE bp.business_id = ? AND bp.bill_id = ? AND bp.payment_status = 'received'
        ORDER BY bp.collected_at DESC, bp.payment_id DESC
    ", 'ii', array($businessId, $billId));

    cc_json(array('success' => true, 'bill' => $bill, 'items' => $items, 'payments' => $payments));
}

function cc_update_customer_outstanding(mysqli $conn, $businessId, $customerId, $billNet, $applied) {
    if ($customerId <= 0 || !cc_table_exists($conn, 'customer_outstanding')) {
        return;
    }

    $affected = cc_exec($conn, "
        UPDATE customer_outstanding
        SET total_paid_amount = total_paid_amount + ?,
            balance_amount = GREATEST(0, balance_amount - ?),
            updated_at = NOW()
        WHERE business_id = ? AND customer_id = ?
    ", 'ddii', array($applied, $applied, $businessId, $customerId));

    if ($affected <= 0) {
        $balance = max(0, (float)$billNet - (float)$applied);
        cc_exec($conn, "
            INSERT INTO customer_outstanding (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ", 'iiddd', array($businessId, $customerId, $billNet, $applied, $balance));
    }
}

function cc_log_activity(mysqli $conn, $businessId, $branchId, $userId, $module, $action, $recordId, $newValue) {
    if (!cc_table_exists($conn, 'activity_logs')) {
        return;
    }
    $roleId = cc_role_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $device = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $json = json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    cc_exec($conn, "
        INSERT INTO activity_logs (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NOW())
    ", 'iiiississs', array($businessId, $branchId, $userId, $roleId, $module, $action, $recordId, $json, $ip, $device));
}

function cc_collect_payment(mysqli $conn, $businessId, $allowedIds) {
    $billId = (int)($_POST['bill_id'] ?? 0);
    $methodId = (int)($_POST['payment_method_id'] ?? 0);
    $amountCollected = (float)($_POST['amount_collected'] ?? 0);
    $referenceNo = trim((string)($_POST['reference_no'] ?? ''));
    $paymentNote = trim((string)($_POST['payment_note'] ?? ''));
    $paymentsJson = trim((string)($_POST['payments_json'] ?? ''));
    $userId = cc_user_id();

    if ($billId <= 0) {
        cc_json(array('success' => false, 'message' => 'Please select a pending bill.'), 422);
    }

    $paymentsInput = array();
    if ($paymentsJson !== '') {
        $decoded = json_decode($paymentsJson, true);
        if (!is_array($decoded)) {
            cc_json(array('success' => false, 'message' => 'Invalid mixed payment data.'), 422);
        }
        foreach ($decoded as $row) {
            $rowMethodId = (int)($row['payment_method_id'] ?? 0);
            $rowAmount = (float)($row['amount_collected'] ?? 0);
            if ($rowMethodId > 0 && $rowAmount > 0) {
                $paymentsInput[] = array(
                    'payment_method_id' => $rowMethodId,
                    'amount' => $rowAmount,
                    'reference_no' => trim((string)($row['reference_no'] ?? '')),
                    'payment_note' => trim((string)($row['payment_note'] ?? $paymentNote))
                );
            }
        }
    } elseif ($methodId > 0 && $amountCollected > 0) {
        $paymentsInput[] = array('payment_method_id' => $methodId, 'amount' => $amountCollected, 'reference_no' => $referenceNo, 'payment_note' => $paymentNote);
    }

    if (empty($paymentsInput)) {
        cc_json(array('success' => false, 'message' => 'Enter payment amount and payment method.'), 422);
    }

    mysqli_begin_transaction($conn);
    try {
        $sql = "
            SELECT *
            FROM bills
            WHERE business_id = ?
              AND bill_id = ?
              AND bill_status = 'active'
              AND payment_status IN ('pending', 'partial')
        ";
        $types = 'ii';
        $params = array($businessId, $billId);
        if (is_array($allowedIds)) {
            cc_add_branch_clause('branch_id', $allowedIds, $sql, $types, $params);
        }
        $sql .= " LIMIT 1 FOR UPDATE";
        $bill = cc_one($conn, $sql, $types, $params);
        if (!$bill) {
            throw new Exception('Pending bill not found or already collected.');
        }

        $branchId = (int)$bill['branch_id'];
        $customerId = (int)($bill['customer_id'] ?? 0);
        $oldPaid = (float)$bill['paid_amount'];
        $oldBalance = (float)$bill['balance_amount'];
        $remainingToApply = $oldBalance;
        $totalTendered = 0.0;
        $totalApplied = 0.0;
        $paymentIds = array();

        foreach ($paymentsInput as $payment) {
            $rowMethodId = (int)$payment['payment_method_id'];
            $rowAmountTendered = (float)$payment['amount'];
            $totalTendered += $rowAmountTendered;
            if ($remainingToApply <= 0) {
                break;
            }
            $method = cc_one($conn, "SELECT payment_method_id FROM payment_methods WHERE business_id = ? AND payment_method_id = ? AND status = 1 LIMIT 1", 'ii', array($businessId, $rowMethodId));
            if (!$method) {
                throw new Exception('Invalid payment method selected.');
            }
            $applied = min($remainingToApply, $rowAmountTendered);
            if ($applied <= 0) {
                continue;
            }
            cc_exec($conn, "
                INSERT INTO bill_payments (business_id, branch_id, bill_id, payment_method_id, paid_amount, reference_no, payment_note, payment_status, collected_by, collected_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'received', ?, NOW())
            ", 'iiiidssi', array($businessId, $branchId, $billId, $rowMethodId, $applied, $payment['reference_no'], $payment['payment_note'], $userId));
            $paymentId = (int)mysqli_insert_id($conn);
            $paymentIds[] = $paymentId;

            $remainingToApply -= $applied;
            $totalApplied += $applied;
            $statusForRow = $remainingToApply <= 0.0001 ? 'paid' : 'partial';
            cc_exec($conn, "
                INSERT INTO cashier_collections (business_id, branch_id, cashier_id, bill_id, payment_id, collected_amount, payment_method_id, collection_status, collected_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", 'iiiiidis', array($businessId, $branchId, $userId, $billId, $paymentId, $applied, $rowMethodId, $statusForRow));
        }

        if ($totalApplied <= 0) {
            throw new Exception('Payment amount could not be applied.');
        }

        $newPaid = $oldPaid + $totalApplied;
        $newBalance = max(0, (float)$bill['net_amount'] - $newPaid);
        $newStatus = $newBalance <= 0.0001 ? 'paid' : 'partial';

        cc_exec($conn, "
            UPDATE bills
            SET paid_amount = ?,
                balance_amount = ?,
                payment_status = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE business_id = ? AND bill_id = ?
        ", 'ddsiii', array($newPaid, $newBalance, $newStatus, $userId, $businessId, $billId));

        cc_update_customer_outstanding($conn, $businessId, $customerId, (float)$bill['net_amount'], $totalApplied);

        if ($customerId > 0 && cc_table_exists($conn, 'customer_ledger')) {
            cc_exec($conn, "
                INSERT INTO customer_ledger (business_id, branch_id, customer_id, reference_type, reference_id, debit, credit, balance, remarks, created_by, created_at)
                VALUES (?, ?, ?, 'bill_payment', ?, 0, ?, ?, ?, ?, NOW())
            ", 'iiiiddsi', array($businessId, $branchId, $customerId, $billId, $totalApplied, $newBalance, 'Payment collected against bill ' . $bill['bill_no'], $userId));
        }

        if (cc_table_exists($conn, 'payment_ledger')) {
            $transactionType = $newStatus === 'paid' ? 'payment' : 'partial_payment';
            cc_exec($conn, "
                INSERT INTO payment_ledger (business_id, branch_id, customer_id, bill_id, transaction_type, debit, credit, balance, payment_method_id, remarks, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())
            ", 'iiiisddisi', array($businessId, $branchId, $customerId > 0 ? $customerId : null, $billId, $transactionType, $totalApplied, $newBalance, $paymentsInput[0]['payment_method_id'], 'Payment collection for bill ' . $bill['bill_no'], $userId));
        }

        if (cc_table_exists($conn, 'bill_barcodes')) {
            cc_exec($conn, "UPDATE bill_barcodes SET barcode_status = 'scanned' WHERE business_id = ? AND bill_id = ? AND barcode_status = 'active'", 'ii', array($businessId, $billId));
        }

        cc_log_activity($conn, $businessId, $branchId, $userId, 'Collections', 'payment_collected', $billId, array(
            'bill_no' => $bill['bill_no'],
            'applied_amount' => $totalApplied,
            'tendered_amount' => $totalTendered,
            'change_amount' => max(0, $totalTendered - $totalApplied),
            'payment_status' => $newStatus,
            'payment_ids' => $paymentIds
        ));

        mysqli_commit($conn);

        $_GET['bill_id'] = $billId;
        $fresh = cc_one($conn, "
            SELECT b.*, bb.barcode_value, br.branch_name, br.floor_name
            FROM bills b
            LEFT JOIN bill_barcodes bb ON bb.bill_id = b.bill_id AND bb.business_id = b.business_id
            LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
            WHERE b.business_id = ? AND b.bill_id = ?
            LIMIT 1
        ", 'ii', array($businessId, $billId));

        cc_json(array(
            'success' => true,
            'message' => 'Payment collected successfully.',
            'bill' => $fresh,
            'applied_amount' => $totalApplied,
            'change_amount' => max(0, $totalTendered - $totalApplied),
            'payment_status' => $newStatus
        ));
    } catch (Exception $e) {
        mysqli_rollback($conn);
        cc_json(array('success' => false, 'message' => $e->getMessage()), 500);
    }
}

try {
    $businessId = cc_business_id();
    if ($businessId <= 0) {
        cc_json(array('success' => false, 'message' => 'Business session missing. Please login again.'), 401);
    }
    $userId = cc_user_id();
    $allowedIds = cc_allowed_branch_ids($conn, $businessId, $userId);

    $action = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string)($_POST['action'] ?? '') : (string)($_GET['action'] ?? '');

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (function_exists('csrf_verify')) {
            csrf_verify();
        } elseif (function_exists('verify_csrf')) {
            verify_csrf();
        }
    }

    switch ($action) {
        case 'init_pending':
            $list = cc_pending_rows($conn, $businessId, $allowedIds);
            cc_json(array(
                'success' => true,
                'branches' => cc_branches($conn, $businessId, $allowedIds),
                'payment_methods' => cc_payment_methods($conn, $businessId),
                'stats' => cc_pending_stats($conn, $businessId, $allowedIds),
                'bills' => $list['rows'],
                'pagination' => $list['pagination']
            ));
            break;
        case 'pending_list':
            $list = cc_pending_rows($conn, $businessId, $allowedIds);
            cc_json(array('success' => true, 'stats' => cc_pending_stats($conn, $businessId, $allowedIds), 'bills' => $list['rows'], 'pagination' => $list['pagination']));
            break;
        case 'find_pending_by_barcode':
            cc_find_pending_by_barcode($conn, $businessId, $allowedIds);
            break;
        case 'get_pending_bill':
            cc_get_pending_bill($conn, $businessId, $allowedIds);
            break;
        case 'collect_payment':
            cc_collect_payment($conn, $businessId, $allowedIds);
            break;
        case 'init_collections':
            $list = cc_collection_rows($conn, $businessId, $allowedIds);
            cc_json(array(
                'success' => true,
                'branches' => cc_branches($conn, $businessId, $allowedIds),
                'payment_methods' => cc_payment_methods($conn, $businessId),
                'stats' => cc_collection_stats($conn, $businessId, $allowedIds),
                'collections' => $list['rows'],
                'pagination' => $list['pagination']
            ));
            break;
        case 'collection_list':
            $list = cc_collection_rows($conn, $businessId, $allowedIds);
            cc_json(array('success' => true, 'stats' => cc_collection_stats($conn, $businessId, $allowedIds), 'collections' => $list['rows'], 'pagination' => $list['pagination']));
            break;
        default:
            cc_json(array('success' => false, 'message' => 'Invalid action.'), 400);
    }
} catch (Exception $e) {
    cc_json(array('success' => false, 'message' => $e->getMessage()), 500);
}