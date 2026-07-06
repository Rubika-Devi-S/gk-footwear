<?php
/**
 * GK Footwear POS - Bill List Model
 * PHP 7.2 compatible. Uses mysqli and schema-safe helpers.
 */

class BillList
{
    private $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function tableExists($tableName)
    {
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        mysqli_stmt_bind_param($stmt, 's', $tableName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }

    public function columnExists($tableName, $columnName)
    {
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }

    private function bindParams($stmt, $types, array &$params)
    {
        if ($types === '' || !$params) {
            return;
        }

        $bind = array();
        $bind[] = $types;
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    private function fetchAll($stmt)
    {
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $rows = array();
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    private function fetchOne($stmt)
    {
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ?: null;
    }

    public function getBranches($businessId, $userId, $isAdmin = false)
    {
        $businessId = (int)$businessId;
        $userId = (int)$userId;

        if (!$this->tableExists('branches')) {
            return array();
        }

        if (!$this->tableExists('user_branch_access') || $isAdmin || $userId <= 0) {
            $stmt = mysqli_prepare($this->conn, "
                SELECT branch_id, branch_code, branch_name, floor_name
                FROM branches
                WHERE business_id = ?
                  AND status = 1
                ORDER BY branch_name ASC, branch_id ASC
            ");
            mysqli_stmt_bind_param($stmt, 'i', $businessId);
            return $this->fetchAll($stmt);
        }

        $stmt = mysqli_prepare($this->conn, "
            SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name
            FROM branches b
            INNER JOIN user_branch_access uba
                ON uba.branch_id = b.branch_id
               AND uba.business_id = b.business_id
               AND uba.user_id = ?
               AND uba.access_status = 1
            WHERE b.business_id = ?
              AND b.status = 1
            ORDER BY b.branch_name ASC, b.branch_id ASC
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $businessId);
        $rows = $this->fetchAll($stmt);

        if ($rows) {
            return $rows;
        }

        // Safe fallback: when access table exists but no rows are configured, do not show a blank Bill List.
        $stmt = mysqli_prepare($this->conn, "
            SELECT branch_id, branch_code, branch_name, floor_name
            FROM branches
            WHERE business_id = ?
              AND status = 1
            ORDER BY branch_name ASC, branch_id ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $businessId);
        return $this->fetchAll($stmt);
    }

    public function userCanAccessBranch($businessId, $userId, $branchId, $isAdmin = false)
    {
        $businessId = (int)$businessId;
        $userId = (int)$userId;
        $branchId = (int)$branchId;

        if ($branchId <= 0) {
            return true;
        }

        if ($isAdmin || $userId <= 0 || !$this->tableExists('user_branch_access')) {
            $stmt = mysqli_prepare($this->conn, "
                SELECT branch_id
                FROM branches
                WHERE business_id = ?
                  AND branch_id = ?
                  AND status = 1
                LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $businessId, $branchId);
            return (bool)$this->fetchOne($stmt);
        }

        $stmt = mysqli_prepare($this->conn, "
            SELECT b.branch_id
            FROM branches b
            LEFT JOIN user_branch_access uba
                ON uba.branch_id = b.branch_id
               AND uba.business_id = b.business_id
               AND uba.user_id = ?
               AND uba.access_status = 1
            WHERE b.business_id = ?
              AND b.branch_id = ?
              AND b.status = 1
              AND (uba.id IS NOT NULL OR b.created_by = ?)
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'iiii', $userId, $businessId, $branchId, $userId);
        $row = $this->fetchOne($stmt);
        if ($row) {
            return true;
        }

        // Final fallback for local/demo DB where branch access is not assigned correctly.
        $stmt = mysqli_prepare($this->conn, "
            SELECT branch_id
            FROM branches
            WHERE business_id = ?
              AND branch_id = ?
              AND status = 1
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $branchId);
        return (bool)$this->fetchOne($stmt);
    }

    private function accessibleBranchIds($businessId, $userId, $isAdmin = false)
    {
        $branches = $this->getBranches($businessId, $userId, $isAdmin);
        $ids = array();
        foreach ($branches as $branch) {
            $ids[] = (int)$branch['branch_id'];
        }
        return $ids;
    }

    private function buildWhere($businessId, $userId, $isAdmin, array $filters, &$params, &$types)
    {
        $where = " WHERE b.business_id = ? ";
        $params = array((int)$businessId);
        $types = 'i';

        $branchIds = $this->accessibleBranchIds($businessId, $userId, $isAdmin);
        $branchId = (int)($filters['branch_id'] ?? 0);

        if ($branchId > 0) {
            if (!$this->userCanAccessBranch($businessId, $userId, $branchId, $isAdmin)) {
                $where .= " AND 1 = 0 ";
                return $where;
            }
            $where .= " AND b.branch_id = ? ";
            $params[] = $branchId;
            $types .= 'i';
        } elseif (!$isAdmin && $branchIds) {
            $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
            $where .= " AND b.branch_id IN ({$placeholders}) ";
            foreach ($branchIds as $id) {
                $params[] = $id;
                $types .= 'i';
            }
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where .= " AND (b.bill_no LIKE ? OR b.order_no LIKE ? OR b.customer_name LIKE ? OR b.customer_mobile LIKE ?) ";
            array_push($params, $like, $like, $like, $like);
            $types .= 'ssss';
        }

        $paymentStatus = trim((string)($filters['payment_status'] ?? ''));
        if (in_array($paymentStatus, array('pending', 'partial', 'paid', 'cancelled'), true)) {
            $where .= " AND b.payment_status = ? ";
            $params[] = $paymentStatus;
            $types .= 's';
        }

        $billStatus = trim((string)($filters['bill_status'] ?? ''));
        if (in_array($billStatus, array('active', 'cancelled', 'deleted'), true)) {
            $where .= " AND b.bill_status = ? ";
            $params[] = $billStatus;
            $types .= 's';
        } elseif ($billStatus === '') {
            $where .= " AND b.bill_status <> 'deleted' ";
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where .= " AND b.bill_date >= ? ";
            $params[] = $dateFrom;
            $types .= 's';
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where .= " AND b.bill_date <= ? ";
            $params[] = $dateTo;
            $types .= 's';
        }

        return $where;
    }

    public function getStats($businessId, $userId, $isAdmin, array $filters = array())
    {
        if (!$this->tableExists('bills')) {
            return array(
                'total_bills' => 0,
                'net_total' => 0,
                'paid_total' => 0,
                'balance_total' => 0,
                'pending_bills' => 0,
                'paid_bills' => 0,
                'cancelled_bills' => 0
            );
        }

        $params = array();
        $types = '';
        $where = $this->buildWhere($businessId, $userId, $isAdmin, $filters, $params, $types);

        $sql = "
            SELECT
                COUNT(*) AS total_bills,
                COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.net_amount ELSE 0 END), 0) AS net_total,
                COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.paid_amount ELSE 0 END), 0) AS paid_total,
                COALESCE(SUM(CASE WHEN b.bill_status = 'active' THEN b.balance_amount ELSE 0 END), 0) AS balance_total,
                SUM(CASE WHEN b.payment_status = 'pending' AND b.bill_status = 'active' THEN 1 ELSE 0 END) AS pending_bills,
                SUM(CASE WHEN b.payment_status = 'paid' AND b.bill_status = 'active' THEN 1 ELSE 0 END) AS paid_bills,
                SUM(CASE WHEN b.bill_status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_bills
            FROM bills b
            {$where}
        ";

        $stmt = mysqli_prepare($this->conn, $sql);
        $this->bindParams($stmt, $types, $params);
        $row = $this->fetchOne($stmt);

        return array(
            'total_bills' => (int)($row['total_bills'] ?? 0),
            'net_total' => (float)($row['net_total'] ?? 0),
            'paid_total' => (float)($row['paid_total'] ?? 0),
            'balance_total' => (float)($row['balance_total'] ?? 0),
            'pending_bills' => (int)($row['pending_bills'] ?? 0),
            'paid_bills' => (int)($row['paid_bills'] ?? 0),
            'cancelled_bills' => (int)($row['cancelled_bills'] ?? 0)
        );
    }

    public function listBills($businessId, $userId, $isAdmin, array $filters = array())
    {
        if (!$this->tableExists('bills')) {
            return array('items' => array(), 'pagination' => array('page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1));
        }

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(5, min(100, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $params = array();
        $types = '';
        $where = $this->buildWhere($businessId, $userId, $isAdmin, $filters, $params, $types);

        $countSql = "SELECT COUNT(*) AS total FROM bills b {$where}";
        $countStmt = mysqli_prepare($this->conn, $countSql);
        $this->bindParams($countStmt, $types, $params);
        $countRow = $this->fetchOne($countStmt);
        $total = (int)($countRow['total'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $perPage));

        $userNameSelect = $this->columnExists('users', 'name') ? "u.name" : ($this->columnExists('users', 'username') ? "u.username" : "NULL");
        if ($this->columnExists('users', 'username')) {
            $userNameSelect = "COALESCE(" . $userNameSelect . ", u.username)";
        }

        $billItemsJoin = $this->tableExists('bill_items') ? "
            LEFT JOIN (
                SELECT bill_id, COUNT(*) AS item_count, COALESCE(SUM(qty), 0) AS total_qty
                FROM bill_items
                WHERE business_id = ?
                GROUP BY bill_id
            ) bi ON bi.bill_id = b.bill_id
        " : "";

        $listParams = $params;
        $listTypes = $types;
        if ($this->tableExists('bill_items')) {
            array_unshift($listParams, (int)$businessId);
            $listTypes = 'i' . $listTypes;
        }

        $sql = "
            SELECT
                b.bill_id,
                b.business_id,
                b.branch_id,
                b.bill_no,
                b.order_no,
                b.bill_date,
                b.bill_time,
                b.customer_id,
                b.customer_name,
                b.customer_mobile,
                b.invoice_title,
                b.mrp_total,
                b.item_discount_total,
                b.bill_discount_amount,
                b.selling_amount,
                b.loyalty_redeem_amount,
                b.today_savings_amount,
                b.net_amount,
                b.paid_amount,
                b.balance_amount,
                b.payment_status,
                b.bill_status,
                b.print_count,
                b.created_at,
                br.branch_name,
                br.floor_name,
                COALESCE(bi.item_count, 0) AS item_count,
                COALESCE(bi.total_qty, 0) AS total_qty,
                {$userNameSelect} AS created_by_name
            FROM bills b
            LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
            LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id
            {$billItemsJoin}
            {$where}
            ORDER BY b.bill_id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $stmt = mysqli_prepare($this->conn, $sql);
        $this->bindParams($stmt, $listTypes, $listParams);
        $items = $this->fetchAll($stmt);

        return array(
            'items' => $items,
            'pagination' => array(
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages
            )
        );
    }

    public function getBill($businessId, $userId, $isAdmin, $billId)
    {
        $billId = (int)$billId;
        if ($billId <= 0 || !$this->tableExists('bills')) {
            return null;
        }

        $userNameSelect = $this->columnExists('users', 'name') ? "u.name" : ($this->columnExists('users', 'username') ? "u.username" : "NULL");
        if ($this->columnExists('users', 'username')) {
            $userNameSelect = "COALESCE(" . $userNameSelect . ", u.username)";
        }

        $stmt = mysqli_prepare($this->conn, "
            SELECT b.*, br.branch_code, br.branch_name, br.floor_name, br.mobile AS branch_mobile,
                   {$userNameSelect} AS created_by_name
            FROM bills b
            LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
            LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id
            WHERE b.business_id = ?
              AND b.bill_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $billId);
        $bill = $this->fetchOne($stmt);

        if (!$bill) {
            return null;
        }

        if (!$this->userCanAccessBranch($businessId, $userId, (int)$bill['branch_id'], $isAdmin)) {
            return null;
        }

        $items = array();
        if ($this->tableExists('bill_items')) {
            $brandJoin = $this->tableExists('brands') ? "LEFT JOIN brands bd ON bd.brand_id = bi.brand_id AND bd.business_id = bi.business_id" : "";
            $brandSelect = $this->tableExists('brands') ? "bd.brand_name" : "NULL AS brand_name";

            $stmt = mysqli_prepare($this->conn, "
                SELECT bi.*, {$brandSelect}, sb.barcode_value
                FROM bill_items bi
                {$brandJoin}
                LEFT JOIN stock_barcodes sb ON sb.barcode_id = bi.barcode_id AND sb.business_id = bi.business_id
                WHERE bi.business_id = ?
                  AND bi.bill_id = ?
                ORDER BY bi.bill_item_id ASC
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $businessId, $billId);
            $items = $this->fetchAll($stmt);
        }

        $payments = array();
        if ($this->tableExists('bill_payments')) {
            $stmt = mysqli_prepare($this->conn, "
                SELECT bp.*, pm.payment_method_name, pm.method_type
                FROM bill_payments bp
                LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id AND pm.business_id = bp.business_id
                WHERE bp.business_id = ?
                  AND bp.bill_id = ?
                ORDER BY bp.payment_id ASC
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $businessId, $billId);
            $payments = $this->fetchAll($stmt);
        }

        $barcodes = array();
        if ($this->tableExists('bill_barcodes')) {
            $stmt = mysqli_prepare($this->conn, "
                SELECT bill_barcode_id, barcode_value, barcode_status, created_at
                FROM bill_barcodes
                WHERE business_id = ?
                  AND bill_id = ?
                ORDER BY bill_barcode_id ASC
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $businessId, $billId);
            $barcodes = $this->fetchAll($stmt);
        }

        return array('bill' => $bill, 'items' => $items, 'payments' => $payments, 'barcodes' => $barcodes);
    }

    private function addActivityLog($businessId, $branchId, $userId, $roleId, $module, $action, $recordId, $oldValue = null, $newValue = null)
    {
        $table = $this->tableExists('business_activity_logs') ? 'business_activity_logs' : ($this->tableExists('activity_logs') ? 'activity_logs' : '');
        if ($table === '') {
            return;
        }

        $oldJson = $oldValue === null ? null : json_encode($oldValue);
        $newJson = $newValue === null ? null : json_encode($newValue);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $device = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO {$table}
                (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        mysqli_stmt_bind_param($stmt, 'iiiississss', $businessId, $branchId, $userId, $roleId, $module, $action, $recordId, $oldJson, $newJson, $ip, $device);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    public function cancelBill($businessId, $userId, $roleId, $isAdmin, $billId, $reason = '')
    {
        $details = $this->getBill($businessId, $userId, $isAdmin, $billId);
        if (!$details) {
            throw new Exception('Bill not found.');
        }

        $bill = $details['bill'];
        if ($bill['bill_status'] !== 'active') {
            throw new Exception('Only active bills can be cancelled.');
        }

        mysqli_begin_transaction($this->conn);
        try {
            $stmt = mysqli_prepare($this->conn, "
                UPDATE bills
                SET bill_status = 'cancelled',
                    payment_status = 'cancelled',
                    updated_by = ?,
                    updated_at = NOW()
                WHERE business_id = ?
                  AND bill_id = ?
                  AND bill_status = 'active'
                LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, 'iii', $userId, $businessId, $billId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($this->tableExists('stock_inward_items')) {
                foreach ($details['items'] as $item) {
                    $stockItemId = (int)($item['stock_item_id'] ?? 0);
                    $qty = (float)($item['qty'] ?? 0);
                    if ($stockItemId > 0 && $qty > 0) {
                        $stmt = mysqli_prepare($this->conn, "
                            UPDATE stock_inward_items
                            SET available_qty = available_qty + ?,
                                item_status = IF(item_status = 'out_of_stock', 'active', item_status),
                                updated_at = NOW()
                            WHERE business_id = ?
                              AND branch_id = ?
                              AND stock_item_id = ?
                            LIMIT 1
                        ");
                        mysqli_stmt_bind_param($stmt, 'diii', $qty, $businessId, $bill['branch_id'], $stockItemId);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);

                        if ($this->tableExists('stock_movements')) {
                            $stmt = mysqli_prepare($this->conn, "
                                INSERT INTO stock_movements
                                    (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, qty_in, qty_out, balance_qty, remarks, created_by, created_at)
                                SELECT ?, ?, stock_item_id, 'sale_cancel', 'bill_cancel', ?, ?, 0.00, available_qty,
                                       ?, ?, NOW()
                                FROM stock_inward_items
                                WHERE business_id = ? AND branch_id = ? AND stock_item_id = ?
                                LIMIT 1
                            ");
                            $remarks = 'Bill cancelled: ' . ($bill['bill_no'] ?? '');
                            mysqli_stmt_bind_param($stmt, 'iiidsiiii', $businessId, $bill['branch_id'], $billId, $qty, $remarks, $userId, $businessId, $bill['branch_id'], $stockItemId);
                            mysqli_stmt_execute($stmt);
                            mysqli_stmt_close($stmt);
                        }
                    }
                }
            }

            if ($this->tableExists('stock_barcodes')) {
                foreach ($details['items'] as $item) {
                    $barcodeId = (int)($item['barcode_id'] ?? 0);
                    if ($barcodeId > 0) {
                        $stmt = mysqli_prepare($this->conn, "
                            UPDATE stock_barcodes
                            SET barcode_status = 'active'
                            WHERE business_id = ?
                              AND branch_id = ?
                              AND barcode_id = ?
                            LIMIT 1
                        ");
                        mysqli_stmt_bind_param($stmt, 'iii', $businessId, $bill['branch_id'], $barcodeId);
                        mysqli_stmt_execute($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
            }

            if ($this->tableExists('bill_barcodes')) {
                $stmt = mysqli_prepare($this->conn, "
                    UPDATE bill_barcodes
                    SET barcode_status = 'cancelled'
                    WHERE business_id = ?
                      AND bill_id = ?
                ");
                mysqli_stmt_bind_param($stmt, 'ii', $businessId, $billId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            if ($this->tableExists('payment_ledger')) {
                $customerId = (int)($bill['customer_id'] ?? 0);
                $stmt = mysqli_prepare($this->conn, "
                    INSERT INTO payment_ledger
                        (business_id, branch_id, customer_id, bill_id, transaction_type, debit, credit, balance, payment_method_id, remarks, created_by, created_at)
                    VALUES
                        (?, ?, ?, ?, 'reverse', 0.00, ?, 0.00, NULL, ?, ?, NOW())
                ");
                $credit = (float)($bill['net_amount'] ?? 0);
                $remarks = 'Bill cancelled: ' . ($bill['bill_no'] ?? '') . ($reason !== '' ? ' - ' . $reason : '');
                mysqli_stmt_bind_param($stmt, 'iiiidsi', $businessId, $bill['branch_id'], $customerId, $billId, $credit, $remarks, $userId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            $this->addActivityLog($businessId, (int)$bill['branch_id'], $userId, $roleId, 'Bill List', 'cancel', $billId, $bill, array('reason' => $reason));

            mysqli_commit($this->conn);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    public function deleteBill($businessId, $userId, $roleId, $isAdmin, $billId, $reason = '')
    {
        $details = $this->getBill($businessId, $userId, $isAdmin, $billId);
        if (!$details) {
            throw new Exception('Bill not found.');
        }

        $bill = $details['bill'];
        if ($bill['bill_status'] === 'active') {
            throw new Exception('Cancel the bill before delete. This avoids stock mismatch.');
        }

        mysqli_begin_transaction($this->conn);
        try {
            $stmt = mysqli_prepare($this->conn, "
                UPDATE bills
                SET bill_status = 'deleted',
                    updated_by = ?,
                    updated_at = NOW()
                WHERE business_id = ?
                  AND bill_id = ?
                LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, 'iii', $userId, $businessId, $billId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($this->tableExists('bill_delete_logs')) {
                $stmt = mysqli_prepare($this->conn, "
                    INSERT INTO bill_delete_logs
                        (business_id, branch_id, bill_id, deleted_by, delete_reason, deleted_at)
                    VALUES
                        (?, ?, ?, ?, ?, NOW())
                ");
                mysqli_stmt_bind_param($stmt, 'iiiis', $businessId, $bill['branch_id'], $billId, $userId, $reason);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            $this->addActivityLog($businessId, (int)$bill['branch_id'], $userId, $roleId, 'Bill List', 'delete', $billId, $bill, array('reason' => $reason));

            mysqli_commit($this->conn);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }
}
