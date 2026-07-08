<?php
/**
 * GK Footwear POS - Bill Collection Model
 * Handles pending/partial bill payment collection using existing schema.
 */

class BillCollection
{
    private $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function tableExists($tableName)
    {
        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        mysqli_stmt_bind_param($stmt, 's', $tableName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return (int)($row['total'] ?? 0) > 0;
    }

    public function columnExists($tableName, $columnName)
    {
        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $tableName, $columnName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return (int)($row['total'] ?? 0) > 0;
    }

    private function bindParams(mysqli_stmt $stmt, $types, array $params)
    {
        if ($types === '') { return; }
        $bind = array($types);
        foreach ($params as $key => $value) { $bind[] = &$params[$key]; }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    private function fetchAll($sql, $types = '', array $params = array())
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { throw new Exception('SQL prepare failed: ' . mysqli_error($this->conn)); }
        $this->bindParams($stmt, $types, $params);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('SQL execute failed: ' . $err);
        }
        $rs = mysqli_stmt_get_result($stmt);
        $rows = array();
        while ($row = mysqli_fetch_assoc($rs)) { $rows[] = $row; }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    private function fetchOne($sql, $types = '', array $params = array())
    {
        $rows = $this->fetchAll($sql, $types, $params);
        return $rows ? $rows[0] : null;
    }

    private function execute($sql, $types = '', array $params = array())
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { throw new Exception('SQL prepare failed: ' . mysqli_error($this->conn)); }
        $this->bindParams($stmt, $types, $params);
        if (!mysqli_stmt_execute($stmt)) {
            $err = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('SQL execute failed: ' . $err);
        }
        $id = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        return $id;
    }

    private function safeLimit($value, $min, $max, $default)
    {
        $n = (int)$value;
        if ($n < $min) { return $default; }
        if ($n > $max) { return $max; }
        return $n;
    }

    public function getBranches($businessId, $userId, $isAdmin)
    {
        if (!$this->tableExists('branches')) { return array(); }
        if ($isAdmin || $userId <= 0 || !$this->tableExists('user_branch_access')) {
            return $this->fetchAll("SELECT branch_id, branch_code, branch_name, floor_name FROM branches WHERE business_id = ? AND status = 1 ORDER BY branch_id ASC", 'i', array($businessId));
        }

        $rows = $this->fetchAll("\n            SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name\n            FROM branches b\n            INNER JOIN user_branch_access uba ON uba.business_id = b.business_id AND uba.branch_id = b.branch_id AND uba.user_id = ?\n            WHERE b.business_id = ? AND b.status = 1\n            ORDER BY b.branch_id ASC\n        ", 'ii', array($userId, $businessId));
        if ($rows) { return $rows; }

        if ($this->columnExists('users', 'default_branch_id')) {
            $rows = $this->fetchAll("\n                SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name\n                FROM users u\n                INNER JOIN branches b ON b.business_id = u.business_id AND b.branch_id = u.default_branch_id AND b.status = 1\n                WHERE u.business_id = ? AND u.user_id = ?\n                LIMIT 1\n            ", 'ii', array($businessId, $userId));
            if ($rows) { return $rows; }
        }

        return array();
    }

    public function userCanAccessBranch($businessId, $userId, $branchId, $isAdmin)
    {
        if ($branchId <= 0) { return false; }
        $branches = $this->getBranches($businessId, $userId, $isAdmin);
        foreach ($branches as $branch) {
            if ((int)$branch['branch_id'] === (int)$branchId) { return true; }
        }
        return false;
    }

    private function branchCondition($businessId, $userId, $isAdmin, $requestedBranchId, &$types, &$params, $alias)
    {
        if ($requestedBranchId > 0) {
            if (!$this->userCanAccessBranch($businessId, $userId, $requestedBranchId, $isAdmin)) {
                throw new Exception('You do not have access to this branch / firm.');
            }
            $types .= 'i';
            $params[] = $requestedBranchId;
            return " AND {$alias}.branch_id = ?";
        }

        if ($isAdmin) { return ''; }

        $ids = array();
        foreach ($this->getBranches($businessId, $userId, $isAdmin) as $branch) { $ids[] = (int)$branch['branch_id']; }
        if (!$ids) { return ' AND 1 = 0'; }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        foreach ($ids as $id) { $types .= 'i'; $params[] = $id; }
        return " AND {$alias}.branch_id IN ({$placeholders})";
    }

    public function getPaymentMethods($businessId)
    {
        if (!$this->tableExists('payment_methods')) { return array(); }
        return $this->fetchAll("\n            SELECT payment_method_id, payment_method_name, method_type\n            FROM payment_methods\n            WHERE business_id = ? AND status = 1\n            ORDER BY FIELD(method_type, 'cash','upi','card','cheque','credit','split','other'), payment_method_name ASC\n        ", 'i', array($businessId));
    }

    public function getPaymentMethod($businessId, $paymentMethodId)
    {
        if ($paymentMethodId <= 0 || !$this->tableExists('payment_methods')) { return null; }
        return $this->fetchOne("\n            SELECT payment_method_id, payment_method_name, method_type\n            FROM payment_methods\n            WHERE business_id = ? AND payment_method_id = ? AND status = 1\n            LIMIT 1\n        ", 'ii', array($businessId, $paymentMethodId));
    }

    private function firstPaymentMethod($businessId)
    {
        $rows = $this->getPaymentMethods($businessId);
        return $rows ? $rows[0] : null;
    }

    public function stats($businessId, $userId, $isAdmin, array $filters = array())
    {
        if (!$this->tableExists('bills')) {
            return array('pending_bills' => 0, 'partial_bills' => 0, 'pending_due' => 0, 'today_collection' => 0);
        }
        $branchId = (int)($filters['branch_id'] ?? 0);

        $types = 'i';
        $params = array($businessId);
        $branchSql = $this->branchCondition($businessId, $userId, $isAdmin, $branchId, $types, $params, 'b');
        $billStats = $this->fetchOne("\n            SELECT\n                COALESCE(SUM(CASE WHEN b.payment_status = 'pending' AND b.bill_status = 'active' AND b.balance_amount > 0 THEN 1 ELSE 0 END),0) AS pending_bills,\n                COALESCE(SUM(CASE WHEN b.payment_status = 'partial' AND b.bill_status = 'active' AND b.balance_amount > 0 THEN 1 ELSE 0 END),0) AS partial_bills,\n                COALESCE(SUM(CASE WHEN b.bill_status = 'active' AND b.balance_amount > 0 THEN b.balance_amount ELSE 0 END),0) AS pending_due\n            FROM bills b\n            WHERE b.business_id = ? {$branchSql}\n        ", $types, $params);

        $todayCollection = 0;
        if ($this->tableExists('bill_payments')) {
            $pTypes = 'i';
            $pParams = array($businessId);
            $pBranchSql = $this->branchCondition($businessId, $userId, $isAdmin, $branchId, $pTypes, $pParams, 'bp');
            $row = $this->fetchOne("\n                SELECT COALESCE(SUM(bp.paid_amount),0) AS today_collection\n                FROM bill_payments bp\n                WHERE bp.business_id = ? {$pBranchSql}\n                  AND bp.payment_status = 'received'\n                  AND DATE(bp.collected_at) = CURDATE()\n            ", $pTypes, $pParams);
            $todayCollection = (float)($row['today_collection'] ?? 0);
        }

        return array(
            'pending_bills' => (int)($billStats['pending_bills'] ?? 0),
            'partial_bills' => (int)($billStats['partial_bills'] ?? 0),
            'pending_due' => (float)($billStats['pending_due'] ?? 0),
            'today_collection' => $todayCollection,
        );
    }

    public function searchPendingBills($businessId, $userId, $isAdmin, array $input = array())
    {
        if (!$this->tableExists('bills')) { return array(); }
        $query = trim((string)($input['q'] ?? $input['search'] ?? ''));
        $branchId = (int)($input['branch_id'] ?? 0);
        $limit = $this->safeLimit(($input['limit'] ?? 20), 1, 50, 20);

        $types = 'i';
        $params = array($businessId);
        $where = "WHERE b.business_id = ? AND b.bill_status = 'active' AND b.payment_status IN ('pending','partial') AND b.balance_amount > 0";
        $where .= $this->branchCondition($businessId, $userId, $isAdmin, $branchId, $types, $params, 'b');

        if ($query !== '') {
            $like = '%' . $query . '%';
            $where .= " AND (b.bill_no LIKE ? OR b.order_no LIKE ? OR b.customer_name LIKE ? OR b.customer_mobile LIKE ? OR COALESCE(bb.barcode_value,'') LIKE ?)";
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return $this->fetchAll("\n            SELECT\n                b.bill_id, b.branch_id, b.bill_no, b.order_no, b.bill_date, b.bill_time,\n                b.customer_id, b.customer_name, b.customer_mobile, b.mrp_total, b.item_discount_total,\n                b.bill_discount_amount, b.selling_amount, b.today_savings_amount, b.tax_amount, b.round_off,\n                b.net_amount, b.paid_amount, b.balance_amount, b.payment_status, b.bill_status,\n                br.branch_name, br.floor_name, u.name AS sales_user_name, bb.barcode_value,\n                COALESCE(SUM(bi.qty),0) AS total_qty, COUNT(bi.bill_item_id) AS item_count,\n                GROUP_CONCAT(DISTINCT bi.article_no ORDER BY bi.article_no SEPARATOR ', ') AS article_summary\n            FROM bills b\n            LEFT JOIN branches br ON br.business_id = b.business_id AND br.branch_id = b.branch_id\n            LEFT JOIN users u ON u.business_id = b.business_id AND u.user_id = b.created_by\n            LEFT JOIN bill_barcodes bb ON bb.business_id = b.business_id AND bb.branch_id = b.branch_id AND bb.bill_id = b.bill_id AND bb.barcode_status = 'active'\n            LEFT JOIN bill_items bi ON bi.business_id = b.business_id AND bi.branch_id = b.branch_id AND bi.bill_id = b.bill_id\n            {$where}\n            GROUP BY b.bill_id\n            ORDER BY b.bill_date DESC, b.bill_time DESC, b.bill_id DESC\n            LIMIT {$limit}\n        ", $types, $params);
    }

    private function billHeader($businessId, $userId, $isAdmin, $billId, $forUpdate = false)
    {
        if ($billId <= 0) { throw new Exception('Invalid bill selected.'); }
        $types = 'ii';
        $params = array($businessId, $billId);
        $where = "WHERE b.business_id = ? AND b.bill_id = ?";
        if (!$isAdmin) {
            $branches = $this->getBranches($businessId, $userId, $isAdmin);
            $ids = array();
            foreach ($branches as $branch) { $ids[] = (int)$branch['branch_id']; }
            if (!$ids) { $where .= ' AND 1 = 0'; }
            else {
                $where .= ' AND b.branch_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                foreach ($ids as $id) { $types .= 'i'; $params[] = $id; }
            }
        }
        $lock = $forUpdate ? ' FOR UPDATE' : '';
        $row = $this->fetchOne("\n            SELECT b.*, br.branch_name, br.floor_name, u.name AS sales_user_name, bb.barcode_value\n            FROM bills b\n            LEFT JOIN branches br ON br.business_id = b.business_id AND br.branch_id = b.branch_id\n            LEFT JOIN users u ON u.business_id = b.business_id AND u.user_id = b.created_by\n            LEFT JOIN bill_barcodes bb ON bb.business_id = b.business_id AND bb.branch_id = b.branch_id AND bb.bill_id = b.bill_id AND bb.barcode_status = 'active'\n            {$where}\n            LIMIT 1{$lock}\n        ", $types, $params);
        if (!$row) { throw new Exception('Bill not found or branch access denied.'); }
        return $row;
    }

    public function getBill($businessId, $userId, $isAdmin, $billId)
    {
        $bill = $this->billHeader($businessId, $userId, $isAdmin, $billId, false);
        $branchId = (int)$bill['branch_id'];

        $items = $this->fetchAll("\n            SELECT\n                bi.bill_item_id, bi.stock_item_id, bi.article_no, bi.article_name, bi.brand_id, br.brand_name,\n                COALESCE(si.color, '') AS color, bi.size, bi.qty, bi.mrp_rate, bi.discount_type, bi.discount_value,\n                bi.discount_amount, bi.selling_rate, bi.amount\n            FROM bill_items bi\n            LEFT JOIN brands br ON br.business_id = bi.business_id AND br.brand_id = bi.brand_id\n            LEFT JOIN stock_inward_items si ON si.business_id = bi.business_id AND si.branch_id = bi.branch_id AND si.stock_item_id = bi.stock_item_id\n            WHERE bi.business_id = ? AND bi.branch_id = ? AND bi.bill_id = ?\n            ORDER BY bi.bill_item_id ASC\n        ", 'iii', array($businessId, $branchId, $billId));

        $payments = array();
        if ($this->tableExists('bill_payments')) {
            $payments = $this->fetchAll("\n                SELECT bp.*, pm.payment_method_name, pm.method_type, u.name AS cashier_name\n                FROM bill_payments bp\n                LEFT JOIN payment_methods pm ON pm.business_id = bp.business_id AND pm.payment_method_id = bp.payment_method_id\n                LEFT JOIN users u ON u.business_id = bp.business_id AND u.user_id = bp.collected_by\n                WHERE bp.business_id = ? AND bp.branch_id = ? AND bp.bill_id = ?\n                ORDER BY bp.payment_id DESC\n            ", 'iii', array($businessId, $branchId, $billId));
        }

        return array('bill' => $bill, 'items' => $items, 'payments' => $payments);
    }

    public function recentTransactions($businessId, $userId, $isAdmin, array $input = array())
    {
        if (!$this->tableExists('bill_payments')) { return array(); }
        $branchId = (int)($input['branch_id'] ?? 0);
        $limit = $this->safeLimit(($input['limit'] ?? 12), 1, 40, 12);
        $types = 'i';
        $params = array($businessId);
        $branchSql = $this->branchCondition($businessId, $userId, $isAdmin, $branchId, $types, $params, 'bp');

        return $this->fetchAll("\n            SELECT\n                bp.payment_id, bp.bill_id, bp.branch_id, bp.paid_amount, bp.reference_no, bp.payment_note,\n                bp.payment_status, bp.collected_at, b.bill_no, b.customer_name, b.customer_mobile, b.balance_amount,\n                pm.payment_method_name, pm.method_type, u.name AS cashier_name, br.branch_name, br.floor_name\n            FROM bill_payments bp\n            INNER JOIN bills b ON b.business_id = bp.business_id AND b.branch_id = bp.branch_id AND b.bill_id = bp.bill_id\n            LEFT JOIN payment_methods pm ON pm.business_id = bp.business_id AND pm.payment_method_id = bp.payment_method_id\n            LEFT JOIN users u ON u.business_id = bp.business_id AND u.user_id = bp.collected_by\n            LEFT JOIN branches br ON br.business_id = bp.business_id AND br.branch_id = bp.branch_id\n            WHERE bp.business_id = ? {$branchSql}\n            ORDER BY bp.payment_id DESC\n            LIMIT {$limit}\n        ", $types, $params);
    }

    private function sanitizePaymentRows($businessId, array $payload, $due)
    {
        $rows = array();
        $json = (string)($payload['payments_json'] ?? '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) { $rows = $decoded; }
        }
        if (!$rows) {
            $rows[] = array(
                'payment_method_id' => (int)($payload['payment_method_id'] ?? 0),
                'amount_collected' => (float)($payload['amount_collected'] ?? $payload['paid_amount'] ?? 0),
                'reference_no' => (string)($payload['reference_no'] ?? ''),
                'payment_note' => (string)($payload['payment_note'] ?? ''),
            );
        }

        $out = array();
        $remaining = round((float)$due, 2);
        $totalTendered = 0.0;
        $totalApplied = 0.0;
        foreach ($rows as $row) {
            $methodId = (int)($row['payment_method_id'] ?? 0);
            $method = $this->getPaymentMethod($businessId, $methodId);
            if (!$method) { $method = $this->firstPaymentMethod($businessId); }
            if (!$method) { throw new Exception('No active payment method found.'); }

            $tendered = round(max(0, (float)($row['amount_collected'] ?? $row['paid_amount'] ?? 0)), 2);
            if ($tendered <= 0) { continue; }
            $applied = round(min($tendered, $remaining), 2);
            if ($applied <= 0) { continue; }
            $remaining = round(max(0, $remaining - $applied), 2);
            $totalTendered += $tendered;
            $totalApplied += $applied;

            $note = trim((string)($row['payment_note'] ?? ''));
            if ($tendered > $applied) {
                $note = trim($note . ' Change given: ' . number_format($tendered - $applied, 2));
            }
            $out[] = array(
                'payment_method_id' => (int)$method['payment_method_id'],
                'method_type' => (string)$method['method_type'],
                'tendered_amount' => $tendered,
                'applied_amount' => $applied,
                'reference_no' => trim((string)($row['reference_no'] ?? '')),
                'payment_note' => $note,
            );
            if ($remaining <= 0) { break; }
        }
        if (!$out) { throw new Exception('Enter payment amount.'); }
        return array(
            'rows' => $out,
            'total_tendered' => round($totalTendered, 2),
            'total_applied' => round($totalApplied, 2),
            'change_amount' => round(max(0, $totalTendered - $totalApplied), 2),
        );
    }

    private function logActivity($businessId, $branchId, $userId, $module, $action, $recordId, array $newValue = array())
    {
        if (!$this->tableExists('activity_logs')) { return; }
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $device = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $json = json_encode($newValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->execute("\n            INSERT INTO activity_logs (business_id, branch_id, user_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)\n            VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NOW())\n        ", 'iiississs', array($businessId, $branchId, $userId, $module, $action, $recordId, $json, $ip, $device));
    }

    private function updateCustomerLedgers($businessId, $branchId, $customerId, $billId, $billNo, $paidAmount, $paymentMethodId, $userId, $newBillBalance)
    {
        if ($customerId <= 0 || $paidAmount <= 0) { return; }

        $customerBalance = $newBillBalance;
        if ($this->tableExists('customer_outstanding')) {
            $this->execute("\n                UPDATE customer_outstanding\n                SET total_paid_amount = total_paid_amount + ?, balance_amount = GREATEST(balance_amount - ?, 0), updated_at = NOW()\n                WHERE business_id = ? AND customer_id = ?\n            ", 'ddii', array($paidAmount, $paidAmount, $businessId, $customerId));
            $row = $this->fetchOne("SELECT balance_amount FROM customer_outstanding WHERE business_id = ? AND customer_id = ? LIMIT 1", 'ii', array($businessId, $customerId));
            if ($row) { $customerBalance = (float)$row['balance_amount']; }
        }

        if ($this->tableExists('customer_ledger')) {
            $remarks = 'Payment collected for bill ' . $billNo;
            $this->execute("\n                INSERT INTO customer_ledger (business_id, branch_id, customer_id, reference_type, reference_id, debit, credit, balance, remarks, created_by, created_at)\n                VALUES (?, ?, ?, 'payment', ?, 0, ?, ?, ?, ?, NOW())\n            ", 'iiiiddsi', array($businessId, $branchId, $customerId, $billId, $paidAmount, $customerBalance, $remarks, $userId));
        }

        if ($this->tableExists('payment_ledger')) {
            $remarks = 'Payment collected for bill ' . $billNo;
            $transactionType = $newBillBalance > 0 ? 'partial_payment' : 'payment';
            $this->execute("\n                INSERT INTO payment_ledger (business_id, branch_id, customer_id, bill_id, transaction_type, debit, credit, balance, payment_method_id, remarks, created_by, created_at)\n                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())\n            ", 'iiiisddisi', array($businessId, $branchId, $customerId, $billId, $transactionType, $paidAmount, $customerBalance, $paymentMethodId, $remarks, $userId));
        }
    }

    public function collectPayment($businessId, $userId, $isAdmin, array $payload)
    {
        if (!$this->tableExists('bills') || !$this->tableExists('bill_payments')) {
            throw new Exception('Billing or payment tables are missing.');
        }
        $billId = (int)($payload['bill_id'] ?? 0);
        mysqli_begin_transaction($this->conn);
        try {
            $bill = $this->billHeader($businessId, $userId, $isAdmin, $billId, true);
            $branchId = (int)$bill['branch_id'];
            if ((string)$bill['bill_status'] !== 'active') { throw new Exception('Only active bills can receive payment.'); }
            $due = round((float)$bill['balance_amount'], 2);
            if ($due <= 0) { throw new Exception('This bill is already fully paid.'); }

            $paymentSet = $this->sanitizePaymentRows($businessId, $payload, $due);
            $totalApplied = (float)$paymentSet['total_applied'];
            if ($totalApplied <= 0) { throw new Exception('No payment amount to apply.'); }

            $newPaid = round((float)$bill['paid_amount'] + $totalApplied, 2);
            $newBalance = round(max(0, (float)$bill['net_amount'] - $newPaid), 2);
            $newStatus = $newBalance <= 0 ? 'paid' : 'partial';

            $paymentIds = array();
            foreach ($paymentSet['rows'] as $row) {
                $note = (string)$row['payment_note'];
                if ($note === '') { $note = 'Bill collection'; }
                $paymentId = $this->execute("\n                    INSERT INTO bill_payments (business_id, branch_id, bill_id, payment_method_id, paid_amount, reference_no, payment_note, payment_status, collected_by, collected_at)\n                    VALUES (?, ?, ?, ?, ?, ?, ?, 'received', ?, NOW())\n                ", 'iiiidssi', array($businessId, $branchId, $billId, (int)$row['payment_method_id'], (float)$row['applied_amount'], (string)$row['reference_no'], $note, $userId));
                $paymentIds[] = $paymentId;

                if ($this->tableExists('cashier_collections')) {
                    $collectionStatus = $newStatus === 'paid' ? 'paid' : 'partial';
                    $this->execute("\n                        INSERT INTO cashier_collections (business_id, branch_id, cashier_id, bill_id, payment_id, collected_amount, payment_method_id, collection_status, collected_at)\n                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())\n                    ", 'iiiiidis', array($businessId, $branchId, $userId, $billId, $paymentId, (float)$row['applied_amount'], (int)$row['payment_method_id'], $collectionStatus));
                }
            }

            $this->execute("\n                UPDATE bills\n                SET paid_amount = ?, balance_amount = ?, payment_status = ?, updated_by = ?, updated_at = NOW()\n                WHERE business_id = ? AND branch_id = ? AND bill_id = ?\n            ", 'ddsiiii', array($newPaid, $newBalance, $newStatus, $userId, $businessId, $branchId, $billId));

            $firstMethodId = isset($paymentSet['rows'][0]) ? (int)$paymentSet['rows'][0]['payment_method_id'] : 0;
            $this->updateCustomerLedgers($businessId, $branchId, (int)($bill['customer_id'] ?? 0), $billId, (string)$bill['bill_no'], $totalApplied, $firstMethodId, $userId, $newBalance);

            $this->logActivity($businessId, $branchId, $userId, 'Bill Collection', 'payment_collected', $billId, array(
                'bill_no' => $bill['bill_no'],
                'applied_amount' => $totalApplied,
                'tendered_amount' => $paymentSet['total_tendered'],
                'change_amount' => $paymentSet['change_amount'],
                'payment_status' => $newStatus,
            ));

            mysqli_commit($this->conn);
            return array(
                'bill_id' => $billId,
                'bill_no' => $bill['bill_no'],
                'paid_amount' => $newPaid,
                'balance_amount' => $newBalance,
                'payment_status' => $newStatus,
                'applied_amount' => $totalApplied,
                'tendered_amount' => $paymentSet['total_tendered'],
                'change_amount' => $paymentSet['change_amount'],
                'payment_ids' => $paymentIds,
            );
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }
}
