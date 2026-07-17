<?php
/**
 * GK Footwear POS Billing Model
 * Compatible with PHP 7.2+ and the uploaded u966043993_footwear schema.
 */
class PosBilling
{
    /** @var mysqli */
    private $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        mysqli_set_charset($this->conn, 'utf8mb4');
    }

    public function tableExists($tableName)
    {
        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        if (!$stmt) { return false; }
        mysqli_stmt_bind_param($stmt, 's', $tableName);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return (int)($row['total'] ?? 0) > 0;
    }

    public function columnExists($tableName, $columnName)
    {
        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        if (!$stmt) { return false; }
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
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array(array($stmt, 'bind_param'), $bind);
    }

    private function fetchAll($sql, $types = '', array $params = array())
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) { throw new Exception('SQL prepare failed: ' . mysqli_error($this->conn)); }
        $this->bindParams($stmt, $types, $params);
        if (!mysqli_stmt_execute($stmt)) { throw new Exception('SQL execute failed: ' . mysqli_stmt_error($stmt)); }
        $rs = mysqli_stmt_get_result($stmt);
        $rows = array();
        if ($rs) {
            while ($row = mysqli_fetch_assoc($rs)) { $rows[] = $row; }
        }
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
        if (!mysqli_stmt_execute($stmt)) { throw new Exception('SQL execute failed: ' . mysqli_stmt_error($stmt)); }
        $id = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);
        return $id;
    }

    private function cleanText($value, $default = '')
    {
        $value = trim((string)$value);
        return $value === '' ? $default : $value;
    }

    private function cleanMobile($value)
    {
        $mobile = preg_replace('/[^0-9]/', '', (string)$value);
        if (strlen($mobile) < 6) { return ''; }
        return substr($mobile, 0, 20);
    }

    private function money($value)
    {
        return round((float)$value, 2);
    }

    private function formatSequence($prefix, $number, $padding)
    {
        $prefix = trim((string)$prefix);
        if ($prefix === '') { $prefix = 'BILL'; }
        $padding = max(1, (int)$padding);
        return $prefix . '-' . str_pad((string)((int)$number), $padding, '0', STR_PAD_LEFT);
    }

    private function fallbackNextNumber($businessId, $branchId, $table, $pk)
    {
        if (!$this->tableExists($table)) { return 1; }
        $branchSql = $branchId > 0 ? ' AND branch_id = ?' : '';
        $types = $branchId > 0 ? 'ii' : 'i';
        $params = $branchId > 0 ? array($businessId, $branchId) : array($businessId);
        $row = $this->fetchOne("SELECT COALESCE(MAX({$pk}),0) AS max_id FROM {$table} WHERE business_id = ? {$branchSql}", $types, $params);
        return ((int)($row['max_id'] ?? 0)) + 1;
    }

    public function displayNextBillNo($businessId)
    {
        $prefix = 'BILL';
        $padding = 4;
        $number = $this->fallbackNextNumber($businessId, 0, 'bills', 'bill_id');
        if ($this->tableExists('number_sequences')) {
            $row = $this->fetchOne("SELECT prefix, current_number, padding_length FROM number_sequences WHERE business_id = ? AND branch_id IS NULL AND sequence_key = 'bill_no' AND status = 1 LIMIT 1", 'i', array($businessId));
            if ($row) {
                $prefix = $row['prefix'] ?: $prefix;
                $padding = (int)($row['padding_length'] ?? $padding);
                $number = ((int)($row['current_number'] ?? 0)) + 1;
            }
        }
        return $this->formatSequence($prefix, $number, $padding);
    }

    public function displayNextBillBarcode($businessId)
    {
        $prefix = $this->billBarcodePrefix($businessId, 0);
        $number = $this->fallbackNextNumber($businessId, 0, 'bills', 'bill_id');
        return $this->formatSequence($prefix, $number, 6);
    }

    private function billBarcodePrefix($businessId, $branchId)
    {
        $prefix = 'BILL';
        if ($this->tableExists('barcode_settings')) {
            $row = $this->fetchOne("SELECT bill_barcode_prefix FROM barcode_settings WHERE business_id = ? AND (branch_id = ? OR branch_id IS NULL) AND status = 1 ORDER BY branch_id IS NULL ASC LIMIT 1", 'ii', array($businessId, $branchId));
            if ($row && trim((string)$row['bill_barcode_prefix']) !== '') { $prefix = trim((string)$row['bill_barcode_prefix']); }
        }
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', (string)$prefix);
        return $prefix !== '' ? strtoupper($prefix) : 'BILL';
    }

    private function generateBillBarcodeValue($businessId, $branchId, $billId, $billNo = '')
    {
        $prefix = $this->billBarcodePrefix($businessId, $branchId);
        $base = $this->formatSequence($prefix, (int)$billId, 6);
        if (!$this->tableExists('bill_barcodes')) { return $base; }
        $existing = $this->fetchOne("SELECT bill_id FROM bill_barcodes WHERE business_id = ? AND barcode_value = ? LIMIT 1", 'is', array($businessId, $base));
        if (!$existing || (int)$existing['bill_id'] === (int)$billId) { return $base; }
        $safeNo = preg_replace('/[^A-Za-z0-9]/', '', (string)$billNo);
        if ($safeNo !== '') {
            $candidate = $prefix . '-' . strtoupper($safeNo);
            $existing = $this->fetchOne("SELECT bill_id FROM bill_barcodes WHERE business_id = ? AND barcode_value = ? LIMIT 1", 'is', array($businessId, $candidate));
            if (!$existing || (int)$existing['bill_id'] === (int)$billId) { return $candidate; }
        }
        return $base . '-' . substr(md5((string)$billId . '-' . microtime(true)), 0, 4);
    }

    private function nextSequenceValue($businessId, $sequenceKey, $defaultPrefix, $defaultPadding, $fallbackTable, $fallbackPk)
    {
        if (!$this->tableExists('number_sequences')) {
            return $this->formatSequence($defaultPrefix, $this->fallbackNextNumber($businessId, 0, $fallbackTable, $fallbackPk), $defaultPadding);
        }

        $row = $this->fetchOne("SELECT sequence_id, prefix, current_number, padding_length FROM number_sequences WHERE business_id = ? AND branch_id IS NULL AND sequence_key = ? AND status = 1 LIMIT 1 FOR UPDATE", 'is', array($businessId, $sequenceKey));
        if ($row) {
            $next = ((int)$row['current_number']) + 1;
            $this->execute("UPDATE number_sequences SET current_number = ?, updated_at = NOW() WHERE sequence_id = ?", 'ii', array($next, (int)$row['sequence_id']));
            return $this->formatSequence($row['prefix'] ?: $defaultPrefix, $next, (int)($row['padding_length'] ?: $defaultPadding));
        }

        $next = $this->fallbackNextNumber($businessId, 0, $fallbackTable, $fallbackPk);
        $this->execute("INSERT INTO number_sequences (business_id, branch_id, sequence_key, prefix, current_number, padding_length, status, updated_at) VALUES (?, NULL, ?, ?, ?, ?, 1, NOW())", 'issii', array($businessId, $sequenceKey, $defaultPrefix, $next, $defaultPadding));
        return $this->formatSequence($defaultPrefix, $next, $defaultPadding);
    }

    public function getBusiness($businessId)
    {
        if (!$this->tableExists('businesses')) { return array('business_id' => $businessId); }
        $row = $this->fetchOne("SELECT business_id, business_code, business_name, owner_name, mobile, email, address, logo_path, gst_type_key, gstin, composition_status FROM businesses WHERE business_id = ? LIMIT 1", 'i', array($businessId));
        return $row ?: array('business_id' => $businessId);
    }

    public function getBusinessSettings($businessId)
    {
        if (!$this->tableExists('business_settings')) { return array(); }
        $row = $this->fetchOne("SELECT * FROM business_settings WHERE business_id = ? AND status = 1 LIMIT 1", 'i', array($businessId));
        return $row ?: array();
    }

    public function getInvoiceSettings($businessId, $branchId)
    {
        if (!$this->tableExists('invoice_settings')) { return array('invoice_title' => 'Bill of Supply'); }
        $row = $this->fetchOne("SELECT * FROM invoice_settings WHERE business_id = ? AND (branch_id = ? OR branch_id IS NULL) AND status = 1 ORDER BY branch_id IS NULL ASC LIMIT 1", 'ii', array($businessId, $branchId));
        return $row ?: array('invoice_title' => 'Bill of Supply');
    }

    public function getBarcodeSettings($businessId, $branchId)
    {
        if (!$this->tableExists('barcode_settings')) { return array(); }
        $row = $this->fetchOne("SELECT * FROM barcode_settings WHERE business_id = ? AND (branch_id = ? OR branch_id IS NULL) AND status = 1 ORDER BY branch_id IS NULL ASC LIMIT 1", 'ii', array($businessId, $branchId));
        return $row ?: array();
    }

    public function getBranches($businessId, $userId, $isAdmin = false)
    {
        if (!$this->tableExists('branches')) { return array(); }
        if (!$isAdmin && $userId > 0 && $this->tableExists('user_branch_access')) {
            $rows = $this->fetchAll("SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name, b.address, b.mobile FROM branches b INNER JOIN user_branch_access uba ON uba.business_id = b.business_id AND uba.branch_id = b.branch_id AND uba.user_id = ? WHERE b.business_id = ? AND b.status = 1 ORDER BY b.branch_id ASC", 'ii', array($userId, $businessId));
            if ($rows) { return $rows; }
        }
        if (!$isAdmin && $userId > 0 && $this->tableExists('users') && $this->columnExists('users', 'default_branch_id')) {
            $rows = $this->fetchAll("SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name, b.address, b.mobile FROM users u INNER JOIN branches b ON b.business_id = u.business_id AND b.branch_id = u.default_branch_id AND b.status = 1 WHERE u.business_id = ? AND u.user_id = ? LIMIT 1", 'ii', array($businessId, $userId));
            if ($rows) { return $rows; }
        }
        return $this->fetchAll("SELECT branch_id, branch_code, branch_name, floor_name, address, mobile FROM branches WHERE business_id = ? AND status = 1 ORDER BY branch_id ASC", 'i', array($businessId));
    }

    public function userCanAccessBranch($businessId, $userId, $branchId, $isAdmin = false)
    {
        if ($branchId <= 0) { return false; }
        $branches = $this->getBranches($businessId, $userId, $isAdmin);
        foreach ($branches as $branch) {
            if ((int)$branch['branch_id'] === (int)$branchId) { return true; }
        }
        return false;
    }

    public function getPaymentMethods($businessId)
    {
        if (!$this->tableExists('payment_methods')) { return array(array('payment_method_id' => 0, 'payment_method_name' => 'Cash', 'method_type' => 'cash')); }
        $this->ensureDefaultPaymentMethods($businessId);
        return $this->fetchAll("SELECT payment_method_id, payment_method_name, method_type FROM payment_methods WHERE business_id = ? AND status = 1 ORDER BY FIELD(method_type, 'cash','upi','card','cheque','credit','split','other'), payment_method_name ASC", 'i', array($businessId));
    }

    private function ensureDefaultPaymentMethods($businessId)
    {
        if (!$this->tableExists('payment_methods')) { return; }
        $defaults = array('Cash' => 'cash', 'UPI' => 'upi', 'Card' => 'card', 'Cheque' => 'cheque', 'Credit' => 'credit');
        foreach ($defaults as $name => $type) {
            $row = $this->fetchOne("SELECT payment_method_id FROM payment_methods WHERE business_id = ? AND payment_method_name = ? LIMIT 1", 'is', array($businessId, $name));
            if (!$row) {
                $this->execute("INSERT INTO payment_methods (business_id, payment_method_name, method_type, status, created_at) VALUES (?, ?, ?, 1, NOW())", 'iss', array($businessId, $name, $type));
            }
        }
    }

    private function firstPaymentMethodId($businessId, $type = 'cash')
    {
        if (!$this->tableExists('payment_methods')) { return 0; }
        $row = $this->fetchOne("SELECT payment_method_id FROM payment_methods WHERE business_id = ? AND method_type = ? AND status = 1 LIMIT 1", 'is', array($businessId, $type));
        if ($row) { return (int)$row['payment_method_id']; }
        $row = $this->fetchOne("SELECT payment_method_id FROM payment_methods WHERE business_id = ? AND status = 1 ORDER BY payment_method_id ASC LIMIT 1", 'i', array($businessId));
        return (int)($row['payment_method_id'] ?? 0);
    }

    public function searchProducts($businessId, $branchId, $query = '', $limit = 30)
    {
        if (!$this->tableExists('stock_inward_items')) { return array(); }
        $query = trim((string)$query);
        $limit = max(1, min(60, (int)$limit));
        $where = "WHERE si.business_id = ? AND si.branch_id = ? AND si.available_qty > 0 AND si.item_status = 'active'";
        $types = 'ii';
        $params = array($businessId, $branchId);
        if ($this->tableExists('stock_inward_batches')) {
            $batchJoin = "INNER JOIN stock_inward_batches sib ON sib.batch_id = si.batch_id AND sib.business_id = si.business_id AND sib.branch_id = si.branch_id AND sib.batch_status = 'active'";
            $batchSelect = "sib.batch_no";
        } else {
            $batchJoin = "";
            $batchSelect = "'' AS batch_no";
        }
        if ($query !== '') {
            $where .= " AND (si.article_no LIKE ? OR si.article_name LIKE ? OR si.size LIKE ? OR COALESCE(si.color,'') LIKE ? OR COALESCE(b.brand_name,'') LIKE ? OR COALESCE(sb.barcode_value,'') LIKE ?)";
            $like = '%' . $query . '%';
            $types .= 'ssssss';
            $params = array_merge($params, array($like, $like, $like, $like, $like, $like));
        }
        return $this->fetchAll("\n            SELECT si.stock_item_id, si.batch_id AS stock_batch_id, si.batch_id, si.branch_id, {$batchSelect},\n                   si.category_id, COALESCE(c.category_name,'') AS category_name, si.brand_id, COALESCE(b.brand_name,'') AS brand_name,\n                   si.article_no, si.article_name, si.size, si.color, si.available_qty,\n                   si.mrp_rate, si.product_discount_type, si.product_discount_value,\n                   si.product_discount_type AS discount_type, si.product_discount_value AS discount_value,\n                   si.discount_amount, si.selling_rate, COALESCE(sb.barcode_id,0) AS barcode_id, COALESCE(sb.barcode_value,'') AS barcode_value,\n                   CASE WHEN si.available_qty <= 2 THEN 1 ELSE 0 END AS low_stock\n            FROM stock_inward_items si\n            {$batchJoin}\n            LEFT JOIN categories c ON c.category_id = si.category_id AND c.business_id = si.business_id\n            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id\n            LEFT JOIN (\n                SELECT stock_item_id, business_id, branch_id, MIN(barcode_id) barcode_id, MIN(barcode_value) barcode_value\n                FROM stock_barcodes\n                WHERE barcode_status = 'active'\n                GROUP BY stock_item_id, business_id, branch_id\n            ) sb ON sb.stock_item_id = si.stock_item_id AND sb.business_id = si.business_id AND sb.branch_id = si.branch_id\n            {$where}\n            ORDER BY si.stock_item_id DESC\n            LIMIT {$limit}\n        ", $types, $params);
    }

    public function getProductOptions($businessId, $branchId, $stockItemId)
    {
        $base = $this->fetchOne("SELECT article_no, brand_id FROM stock_inward_items WHERE business_id = ? AND branch_id = ? AND stock_item_id = ? LIMIT 1", 'iii', array($businessId, $branchId, $stockItemId));
        if (!$base) { return array(); }
        $articleNo = (string)$base['article_no'];
        $brandId = (int)($base['brand_id'] ?? 0);
        $rows = $this->searchProducts($businessId, $branchId, $articleNo, 60);
        $out = array();
        foreach ($rows as $row) {
            if ((string)$row['article_no'] === $articleNo && ($brandId <= 0 || (int)$row['brand_id'] === $brandId)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    public function scanProduct($businessId, $branchId, $code)
    {
        $code = trim((string)$code);
        if ($code === '') { return array(); }
        if ($this->tableExists('stock_barcodes')) {
            $row = $this->fetchOne("SELECT stock_item_id FROM stock_barcodes WHERE business_id = ? AND branch_id = ? AND barcode_value = ? AND barcode_status = 'active' LIMIT 1", 'iis', array($businessId, $branchId, $code));
            if ($row) {
                $options = $this->getProductOptions($businessId, $branchId, (int)$row['stock_item_id']);
                foreach ($options as $product) {
                    if ((int)$product['stock_item_id'] === (int)$row['stock_item_id']) {
                        return array('type' => 'product', 'product' => $product, 'options' => $options);
                    }
                }
            }
        }
        $products = $this->searchProducts($businessId, $branchId, $code, 1);
        if ($products) {
            return array('type' => 'product', 'product' => $products[0], 'options' => $this->getProductOptions($businessId, $branchId, (int)$products[0]['stock_item_id']));
        }
        if ($this->tableExists('bills')) {
            if ($this->tableExists('bill_barcodes')) {
                $bill = $this->fetchOne("SELECT b.*, bb.barcode_value AS bill_barcode, bb.barcode_value AS barcode_value FROM bills b LEFT JOIN bill_barcodes bb ON bb.business_id = b.business_id AND bb.branch_id = b.branch_id AND bb.bill_id = b.bill_id WHERE b.business_id = ? AND b.branch_id = ? AND (bb.barcode_value = ? OR b.bill_no = ? OR b.order_no = ?) ORDER BY bb.bill_barcode_id DESC LIMIT 1", 'iisss', array($businessId, $branchId, $code, $code, $code));
                if ($bill) { return array('type' => 'bill', 'bill' => $this->billWithItems($businessId, $branchId, (int)$bill['bill_id'], $bill)); }
            } else {
                $bill = $this->fetchOne("SELECT b.*, '' AS bill_barcode, '' AS barcode_value FROM bills b WHERE b.business_id = ? AND b.branch_id = ? AND (b.bill_no = ? OR b.order_no = ?) LIMIT 1", 'iiss', array($businessId, $branchId, $code, $code));
                if ($bill) { return array('type' => 'bill', 'bill' => $this->billWithItems($businessId, $branchId, (int)$bill['bill_id'], $bill)); }
            }
        }
        return array();
    }

    private function billWithItems($businessId, $branchId, $billId, array $bill)
    {
        if ($this->tableExists('bill_items')) {
            $bill['items'] = $this->fetchAll("SELECT * FROM bill_items WHERE business_id = ? AND branch_id = ? AND bill_id = ? ORDER BY bill_item_id ASC", 'iii', array($businessId, $branchId, $billId));
        } else {
            $bill['items'] = array();
        }
        if (empty($bill['bill_barcode']) && $this->tableExists('bill_barcodes')) {
            $row = $this->fetchOne("SELECT barcode_value FROM bill_barcodes WHERE business_id = ? AND branch_id = ? AND bill_id = ? ORDER BY bill_barcode_id DESC LIMIT 1", 'iii', array($businessId, $branchId, $billId));
            if ($row) { $bill['bill_barcode'] = $row['barcode_value']; $bill['barcode_value'] = $row['barcode_value']; }
        }
        if (!empty($bill['bill_barcode'])) { $bill['barcode_image_url'] = 'barcode-image.php?code=' . rawurlencode($bill['bill_barcode']); }
        return $bill;
    }

    public function searchCustomers($businessId, $query = '', $limit = 5)
    {
        if (!$this->tableExists('customers')) { return array(); }
        $query = trim((string)$query);
        if ($query === '') { return array(); }
        $limit = max(1, min(20, (int)$limit));
        $where = "WHERE c.business_id = ? AND c.status = 1 AND (c.customer_name LIKE ? OR c.mobile LIKE ? OR c.email LIKE ? OR c.gstin LIKE ?)";
        $like = '%' . $query . '%';
        $types = 'issss';
        $params = array($businessId, $like, $like, $like, $like);
        $outJoin = $this->tableExists('customer_outstanding') ? "LEFT JOIN customer_outstanding co ON co.business_id = c.business_id AND co.customer_id = c.customer_id" : "";
        $outSelect = $this->tableExists('customer_outstanding') ? "COALESCE(co.balance_amount,0)" : "0";
        $purchaseSelect = $this->tableExists('bills') ? "(SELECT COUNT(*) FROM bills bx WHERE bx.business_id = c.business_id AND bx.customer_id = c.customer_id AND bx.bill_status = 'active')" : "0";
        return $this->fetchAll("\n            SELECT c.customer_id, c.customer_name, c.mobile, c.email, c.gstin, c.address, c.loyalty_points, c.status,\n                   {$outSelect} AS outstanding_balance,\n                   {$purchaseSelect} AS purchase_count\n            FROM customers c\n            {$outJoin}\n            {$where}\n            ORDER BY c.customer_name ASC\n            LIMIT {$limit}\n        ", $types, $params);
    }

    private function findCustomer($businessId, $customerId, $name, $mobile)
    {
        if (!$this->tableExists('customers')) { return array('customer_id' => 0, 'customer_name' => $name, 'mobile' => $mobile); }
        if ($customerId > 0) {
            $row = $this->fetchOne("SELECT * FROM customers WHERE business_id = ? AND customer_id = ? AND status = 1 LIMIT 1", 'ii', array($businessId, $customerId));
            if ($row) { return $row; }
        }
        if ($mobile !== '') {
            $row = $this->fetchOne("SELECT * FROM customers WHERE business_id = ? AND mobile = ? AND status = 1 ORDER BY customer_id ASC LIMIT 1", 'is', array($businessId, $mobile));
            if ($row) { return $row; }
        }
        if ($name !== '' && strcasecmp($name, 'Walk-in Customer') !== 0) {
            $row = $this->fetchOne("SELECT * FROM customers WHERE business_id = ? AND LOWER(customer_name) = LOWER(?) AND status = 1 ORDER BY customer_id ASC LIMIT 1", 'is', array($businessId, $name));
            if ($row) { return $row; }
        }
        return array();
    }

    public function saveCustomer($businessId, array $payload)
    {
        if (!$this->tableExists('customers')) { throw new Exception('Customers table missing.'); }
        $name = $this->cleanText($payload['customer_name'] ?? $payload['name'] ?? '', 'Customer');
        $mobile = $this->cleanMobile($payload['mobile'] ?? $payload['customer_mobile'] ?? '');
        $email = trim((string)($payload['email'] ?? ''));
        $address = trim((string)($payload['address'] ?? ''));
        $gstin = strtoupper(trim((string)($payload['gstin'] ?? '')));
        $opening = $this->money($payload['opening_outstanding'] ?? 0);
        $loyalty = $this->money($payload['loyalty_points'] ?? 0);

        $existing = $this->findCustomer($businessId, 0, $name, $mobile);
        if ($existing) { return $existing; }

        $customerId = $this->execute("INSERT INTO customers (business_id, customer_name, mobile, email, address, gstin, opening_outstanding, loyalty_points, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())", 'isssssdd', array($businessId, $name, $mobile, $email, $address, $gstin, $opening, $loyalty));
        if ($opening > 0 && $this->tableExists('customer_outstanding')) {
            $this->execute("INSERT INTO customer_outstanding (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount, updated_at) VALUES (?, ?, ?, 0, ?, NOW()) ON DUPLICATE KEY UPDATE balance_amount = VALUES(balance_amount), updated_at = NOW()", 'iidd', array($businessId, $customerId, $opening, $opening));
        }
        return $this->findCustomer($businessId, $customerId, '', '');
    }

    private function ensureCustomerForBill($businessId, array $payloadCustomer)
    {
        $customerId = (int)($payloadCustomer['customer_id'] ?? 0);
        $name = $this->cleanText($payloadCustomer['customer_name'] ?? $payloadCustomer['name'] ?? '', 'Walk-in Customer');
        $mobile = $this->cleanMobile($payloadCustomer['mobile'] ?? $payloadCustomer['customer_mobile'] ?? '');

        /*
         * Existing customer must always be used instead of creating duplicate.
         * Search by selected customer_id, then mobile, then exact name.
         */
        $existing = $this->findCustomer($businessId, $customerId, $name, $mobile);
        if ($existing) {
            return array(
                'customer_id' => (int)$existing['customer_id'],
                'customer_name' => $existing['customer_name'],
                'mobile' => $existing['mobile'] ?? '',
                'email' => $existing['email'] ?? '',
                'address' => $existing['address'] ?? '',
                'loyalty_points' => $existing['loyalty_points'] ?? 0,
                'is_walkin_customer' => 0,
                'customer_source' => 'master',
                'save_to_master' => 1,
                'visible_in_customer_master' => 1,
            );
        }

        /*
         * POS/Create Bill new customer rule:
         * Do NOT call saveCustomer() here.
         * Any new name/mobile/details from Create Bill is bill-only Walk-in data.
         * It will appear in Bill List and Cashier Collection from bills.customer_name/customer_mobile,
         * but it will not be inserted into customers table/customer.php.
         */
        if ($name === '' || preg_match('/^\d+$/', $name)) {
            $name = 'Walk-in Customer';
        }

        return array(
            'customer_id' => 0,
            'customer_name' => $name,
            'mobile' => $mobile,
            'email' => $payloadCustomer['email'] ?? '',
            'address' => $payloadCustomer['address'] ?? '',
            'gstin' => $payloadCustomer['gstin'] ?? '',
            'loyalty_points' => 0,
            'is_walkin_customer' => 1,
            'customer_source' => 'walk_in',
            'save_to_master' => 0,
            'visible_in_customer_master' => 0,
        );
    }

    private function productByStockItemIdForUpdate($businessId, $branchId, $stockItemId)
    {
        if ($this->tableExists('stock_inward_batches')) {
            $batchJoin = "INNER JOIN stock_inward_batches sib ON sib.batch_id = si.batch_id AND sib.business_id = si.business_id AND sib.branch_id = si.branch_id";
            $batchSelect = "sib.batch_no";
        } else {
            $batchJoin = "";
            $batchSelect = "'' AS batch_no";
        }
        return $this->fetchOne("\n            SELECT si.stock_item_id, si.batch_id AS stock_batch_id, si.batch_id, si.branch_id, {$batchSelect}, si.category_id, c.category_name, si.brand_id, b.brand_name,\n                   si.article_no, si.article_name, si.size, si.color, si.available_qty, si.mrp_rate, si.product_discount_type, si.product_discount_value, si.discount_amount, si.selling_rate,\n                   COALESCE(sb.barcode_id,0) AS barcode_id, COALESCE(sb.barcode_value,'') AS barcode_value\n            FROM stock_inward_items si\n            {$batchJoin}\n            LEFT JOIN categories c ON c.category_id = si.category_id AND c.business_id = si.business_id\n            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id\n            LEFT JOIN (\n                SELECT stock_item_id, business_id, branch_id, MIN(barcode_id) barcode_id, MIN(barcode_value) barcode_value\n                FROM stock_barcodes\n                WHERE barcode_status = 'active'\n                GROUP BY stock_item_id, business_id, branch_id\n            ) sb ON sb.stock_item_id = si.stock_item_id AND sb.business_id = si.business_id AND sb.branch_id = si.branch_id\n            WHERE si.business_id = ? AND si.branch_id = ? AND si.stock_item_id = ? AND si.item_status IN ('active','out_of_stock')\n            LIMIT 1 FOR UPDATE\n        ", 'iii', array($businessId, $branchId, $stockItemId));
    }

    private function calculateBillDiscount($amount, $type, $value)
    {
        $amount = $this->money($amount);
        $value = $this->money($value);
        if ($type === 'percent') { return $this->money(min($amount, $amount * $value / 100)); }
        if ($type === 'amount') { return $this->money(min($amount, $value)); }
        return 0.00;
    }

    public function saveBillFromPayload($businessId, $branchId, $userId, array $payload)
    {
        if (!$this->tableExists('bills') || !$this->tableExists('bill_items') || !$this->tableExists('stock_inward_items')) {
            throw new Exception('Required billing tables are missing.');
        }
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
        if (!$items) { throw new Exception('Add at least one product to bill.'); }

        // POS bill creation should not collect payment. Payments are collected only from Pending Bills module.
        $payments = array();
        $billDiscountType = (string)($payload['bill_discount_type'] ?? 'none');
        if (!in_array($billDiscountType, array('none', 'percent', 'amount'), true)) { $billDiscountType = 'none'; }
        $billDiscountValue = $this->money($payload['bill_discount_value'] ?? 0);
        $loyaltyRedeem = $this->money($payload['loyalty_redeem_amount'] ?? 0);

        mysqli_begin_transaction($this->conn);
        try {
            $customer = $this->ensureCustomerForBill($businessId, isset($payload['customer']) && is_array($payload['customer']) ? $payload['customer'] : array());
            $customerId = (int)$customer['customer_id'];
            $customerIdForDb = $customerId > 0 ? $customerId : null;
            $customerName = $customer['customer_name'] ?: 'Walk-in Customer';
            $customerMobile = $customer['mobile'] ?? '';

            $preparedItems = array();
            $mrpTotal = 0.00;
            $itemDiscountTotal = 0.00;
            $sellingAmount = 0.00;

            foreach ($items as $item) {
                $stockItemId = (int)($item['stock_item_id'] ?? 0);
                $qty = max(0, $this->money($item['qty'] ?? 0));
                if ($stockItemId <= 0) { throw new Exception('Invalid stock item selected.'); }
                if ($qty <= 0) { throw new Exception('Quantity must be greater than zero.'); }

                $stock = $this->productByStockItemIdForUpdate($businessId, $branchId, $stockItemId);
                if (!$stock) { throw new Exception('Stock item not found for selected branch / firm.'); }
                $availableQty = (float)$stock['available_qty'];
                if ($qty > $availableQty) { throw new Exception('Quantity exceeds available stock for ' . $stock['article_no'] . '. Available: ' . number_format($availableQty, 2)); }

                $mrpRate = $this->money($stock['mrp_rate']);
                $sellingRate = isset($item['selling_rate']) ? $this->money($item['selling_rate']) : $this->money($stock['selling_rate']);
                if ($sellingRate < 0 || $sellingRate > $mrpRate) { throw new Exception('Invalid selling rate for ' . $stock['article_no'] . '.'); }
                $discountAmount = $this->money(max(0, $mrpRate - $sellingRate));
                $lineAmount = $this->money($sellingRate * $qty);

                $stock['qty_to_bill'] = $qty;
                $stock['bill_discount_type'] = $discountAmount > 0 ? 'amount' : 'none';
                $stock['bill_discount_value'] = $discountAmount;
                $stock['bill_discount_amount'] = $discountAmount;
                $stock['bill_selling_rate'] = $sellingRate;
                $stock['bill_amount'] = $lineAmount;

                $mrpTotal += $this->money($mrpRate * $qty);
                $itemDiscountTotal += $this->money($discountAmount * $qty);
                $sellingAmount += $lineAmount;
                $preparedItems[] = $stock;
            }

            $billDiscountAmount = $this->calculateBillDiscount($sellingAmount, $billDiscountType, $billDiscountValue);
            $loyaltyRedeem = min(max(0, $loyaltyRedeem), max(0, $sellingAmount - $billDiscountAmount));
            $todaySavings = $this->money($itemDiscountTotal + $billDiscountAmount + $loyaltyRedeem);
            $netBeforeRound = $this->money(max(0, $sellingAmount - $billDiscountAmount - $loyaltyRedeem));
            $grandTotal = round($netBeforeRound);
            $roundOff = $this->money($grandTotal - $netBeforeRound);

            // Always save POS-created bills as pending.
            // No bill_payments/cashier_collections rows are created here.
            $paidAmount = 0.00;
            $balanceAmount = $this->money($grandTotal);
            $paymentStatus = 'pending';
            $invoiceSettings = $this->getInvoiceSettings($businessId, $branchId);
            $businessSettings = $this->getBusinessSettings($businessId);
            $gstTypeKey = $invoiceSettings['gst_type_key'] ?? $businessSettings['gst_type_key'] ?? 'gst_composition';
            $invoiceTitle = $invoiceSettings['invoice_title'] ?? 'Bill of Supply';
            $billNo = $this->nextSequenceValue($businessId, 'bill_no', 'BILL', 4, 'bills', 'bill_id');
            $orderNo = 'ORD-' . $billNo;

            $billId = $this->execute("\n                INSERT INTO bills\n                    (business_id, branch_id, bill_no, order_no, bill_date, bill_time, customer_id,\n                     customer_name, customer_mobile, gst_type_key, invoice_title, mrp_total,\n                     item_discount_total, bill_discount_type, bill_discount_value, bill_discount_amount,\n                     selling_amount, loyalty_redeem_amount, today_savings_amount, taxable_amount,\n                     cgst_amount, sgst_amount, igst_amount, tax_amount, round_off, net_amount,\n                     paid_amount, balance_amount, payment_status, updated_status, bill_status,\n                     print_count, created_by, created_at)\n                VALUES\n                    (?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, ?, ?, ?, ?, ?, 'original', 'active', 0, ?, NOW())\n            ", 'iississssddsddddddddssi', array(
                $businessId, $branchId, $billNo, $orderNo, $customerIdForDb,
                $customerName, $customerMobile, $gstTypeKey, $invoiceTitle,
                $mrpTotal, $itemDiscountTotal, $billDiscountType, $billDiscountValue, $billDiscountAmount,
                $sellingAmount, $loyaltyRedeem, $todaySavings, $roundOff, $grandTotal,
                $paidAmount, $balanceAmount, $paymentStatus, $userId
            ));

            foreach ($preparedItems as $item) {
                $stockBatchId = (int)$item['batch_id'];
                $stockItemId = (int)$item['stock_item_id'];
                $barcodeId = (int)($item['barcode_id'] ?? 0);
                $barcodeIdForDb = $barcodeId > 0 ? $barcodeId : null;
                $brandId = (int)($item['brand_id'] ?? 0);
                $brandIdForDb = $brandId > 0 ? $brandId : null;
                $qty = (float)$item['qty_to_bill'];
                $newBalance = $this->money(((float)$item['available_qty']) - $qty);
                $newStatus = $newBalance <= 0 ? 'out_of_stock' : 'active';

                $this->execute("\n                    INSERT INTO bill_items\n                        (business_id, branch_id, bill_id, stock_batch_id, stock_item_id, barcode_id,\n                         article_no, article_name, brand_id, size, qty, mrp_rate, discount_type,\n                         discount_value, discount_amount, selling_rate, amount, taxable_amount,\n                         cgst_amount, sgst_amount, igst_amount, tax_amount, created_at)\n                    VALUES\n                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, NOW())\n                ", 'iiiiiissisddsdddd', array(
                    $businessId, $branchId, $billId, $stockBatchId, $stockItemId, $barcodeIdForDb,
                    $item['article_no'], $item['article_name'], $brandIdForDb, $item['size'], $qty,
                    $item['mrp_rate'], $item['bill_discount_type'], $item['bill_discount_value'],
                    $item['bill_discount_amount'], $item['bill_selling_rate'], $item['bill_amount']
                ));

                $this->execute("UPDATE stock_inward_items SET available_qty = ?, item_status = ?, updated_at = NOW() WHERE business_id = ? AND branch_id = ? AND stock_item_id = ?", 'dsiii', array($newBalance, $newStatus, $businessId, $branchId, $stockItemId));

                if ($this->tableExists('stock_movements')) {
                    $this->execute("\n                        INSERT INTO stock_movements\n                            (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, qty_in, qty_out, balance_qty, remarks, created_by, created_at)\n                        VALUES\n                            (?, ?, ?, 'sale', 'bill', ?, 0, ?, ?, ?, ?, NOW())\n                    ", 'iiiiddsi', array($businessId, $branchId, $stockItemId, $billId, $qty, $newBalance, 'Sale bill ' . $billNo, $userId));
                }
            }

            $barcodeValue = $this->generateBillBarcodeValue($businessId, $branchId, $billId, $billNo);
            if ($this->tableExists('bill_barcodes')) {
                $this->execute("INSERT INTO bill_barcodes (business_id, branch_id, bill_id, barcode_value, barcode_status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())", 'iiis', array($businessId, $branchId, $billId, $barcodeValue));
            }

            // Payment collection is intentionally skipped at POS bill creation.
            // The printed invoice barcode is used in Pending Bills to collect payment later.

            if ($customerId > 0) {
                if ($this->tableExists('customer_outstanding')) {
                    $this->execute("\n                        INSERT INTO customer_outstanding (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount, updated_at)\n                        VALUES (?, ?, ?, ?, ?, NOW())\n                        ON DUPLICATE KEY UPDATE\n                            total_bill_amount = total_bill_amount + VALUES(total_bill_amount),\n                            total_paid_amount = total_paid_amount + VALUES(total_paid_amount),\n                            balance_amount = balance_amount + VALUES(balance_amount),\n                            updated_at = NOW()\n                    ", 'iiddd', array($businessId, $customerId, $grandTotal, $paidAmount, $balanceAmount));
                }
                if ($this->tableExists('customer_ledger')) {
                    $this->execute("INSERT INTO customer_ledger (business_id, branch_id, customer_id, reference_type, reference_id, debit, credit, balance, remarks, created_by, created_at) VALUES (?, ?, ?, 'bill', ?, ?, ?, ?, ?, ?, NOW())", 'iiiidddsi', array($businessId, $branchId, $customerId, $billId, $grandTotal, $paidAmount, $balanceAmount, 'Bill ' . $billNo, $userId));
                }
                if ($this->tableExists('payment_ledger')) {
                    $this->execute("INSERT INTO payment_ledger (business_id, branch_id, customer_id, bill_id, transaction_type, debit, credit, balance, payment_method_id, remarks, created_by, created_at) VALUES (?, ?, ?, ?, 'bill', ?, 0, ?, NULL, ?, ?, NOW())", 'iiiiddsi', array($businessId, $branchId, $customerId, $billId, $grandTotal, $balanceAmount, 'Bill ' . $billNo, $userId));
                    // No payment ledger credit entry is created here because payment is not collected in POS creation.
                }
            }

            $this->logActivity($businessId, $branchId, $userId, 'POS Create Bill', 'bill_created', $billId, array('bill_no' => $billNo, 'grand_total' => $grandTotal, 'payment_status' => $paymentStatus, 'item_count' => count($preparedItems)));

            $workflowId = (int)($payload['workflow_id'] ?? $payload['hold_id'] ?? 0);
            if ($workflowId > 0 && $this->tableExists('pos_bill_holds')) {
                $this->execute("UPDATE pos_bill_holds SET hold_status = 'converted', updated_at = NOW() WHERE business_id = ? AND branch_id = ? AND hold_id = ?", 'iii', array($businessId, $branchId, $workflowId));
            }

            mysqli_commit($this->conn);
            return array(
                'bill_id' => $billId,
                'bill_no' => $billNo,
                'order_no' => $orderNo,
                'barcode_value' => $barcodeValue,
                'bill_barcode' => $barcodeValue,
                'barcode_image_url' => 'barcode-image.php?code=' . rawurlencode($barcodeValue),
                'customer_id' => $customerId,
                'customer_name' => $customerName,
                'net_amount' => $grandTotal,
                'grand_total' => $grandTotal,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'payment_status' => $paymentStatus,
                'print_url' => 'bill-print.php?bill_id=' . $billId,
            );
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    private function summarizePayload(array $payload)
    {
        $items = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : array();
        $count = count($items);
        $qty = 0.0;
        $total = 0.0;
        foreach ($items as $item) {
            $q = (float)($item['qty'] ?? 0);
            $qty += $q;
            $total += $q * (float)($item['selling_rate'] ?? 0);
        }
        $customer = isset($payload['customer']) && is_array($payload['customer']) ? $payload['customer'] : array();
        return array(
            'item_count' => $count,
            'total_qty' => $qty,
            'total_amount' => $this->money($total),
            'customer_id' => (int)($customer['customer_id'] ?? 0),
            'customer_name' => (string)($customer['customer_name'] ?? 'Walk-in Customer'),
            'customer_mobile' => (string)($customer['mobile'] ?? ''),
        );
    }

    public function saveWorkflow($businessId, $branchId, $userId, $type, array $payload)
    {
        if (!$this->tableExists('pos_bill_holds')) { throw new Exception('pos_bill_holds table missing.'); }
        $type = in_array($type, array('hold', 'draft', 'cancelled'), true) ? $type : 'hold';
        $payload['workflow_type'] = $type;
        $summary = $this->summarizePayload($payload);
        $holdId = (int)($payload['workflow_id'] ?? $payload['hold_id'] ?? 0);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($holdId > 0) {
            $this->execute("UPDATE pos_bill_holds SET hold_data = ?, hold_status = 'active', updated_at = NOW() WHERE business_id = ? AND branch_id = ? AND hold_id = ?", 'siii', array($json, $businessId, $branchId, $holdId));
        } else {
            $holdId = $this->execute("INSERT INTO pos_bill_holds (business_id, branch_id, held_by, hold_data, hold_status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())", 'iiis', array($businessId, $branchId, $userId, $json));
        }
        $this->logActivity($businessId, $branchId, $userId, 'POS Create Bill', $type === 'draft' ? 'draft_bill' : ($type === 'cancelled' ? 'cancel_bill' : 'hold_bill'), $holdId, array('items' => $summary['item_count']));
        return array('workflow_id' => $holdId, 'hold_id' => $holdId, 'workflow_no' => strtoupper($type) . '-' . $holdId, 'bill_status' => $type, 'total_amount' => $summary['total_amount'], 'customer_name' => $summary['customer_name']);
    }

    public function getWorkflow($businessId, $branchId, $holdId)
    {
        if (!$this->tableExists('pos_bill_holds')) { return null; }
        return $this->fetchOne("SELECT * FROM pos_bill_holds WHERE business_id = ? AND branch_id = ? AND hold_id = ? LIMIT 1", 'iii', array($businessId, $branchId, $holdId));
    }

    public function cancelWorkflow($businessId, $branchId, $holdId, $userId)
    {
        if (!$this->tableExists('pos_bill_holds')) { throw new Exception('pos_bill_holds table missing.'); }
        $this->execute("UPDATE pos_bill_holds SET hold_status = 'cancelled', updated_at = NOW() WHERE business_id = ? AND branch_id = ? AND hold_id = ?", 'iii', array($businessId, $branchId, $holdId));
        $this->logActivity($businessId, $branchId, $userId, 'POS Create Bill', 'cancel_hold', $holdId, null);
    }

    public function billHistory($businessId, $branchId, $filter = 'all', $q = '')
    {
        $records = array();
        $q = trim((string)$q);
        if ($this->tableExists('pos_bill_holds')) {
            $rows = $this->fetchAll("SELECT * FROM pos_bill_holds WHERE business_id = ? AND branch_id = ? ORDER BY hold_id DESC LIMIT 100", 'ii', array($businessId, $branchId));
            foreach ($rows as $row) {
                $payload = json_decode((string)$row['hold_data'], true);
                if (!is_array($payload)) { $payload = array(); }
                $summary = $this->summarizePayload($payload);
                $type = (string)($payload['workflow_type'] ?? 'hold');
                $status = $row['hold_status'] === 'active' ? $type : $row['hold_status'];
                $record = array(
                    'source_type' => 'workflow',
                    'workflow_id' => (int)$row['hold_id'],
                    'workflow_no' => strtoupper($type) . '-' . $row['hold_id'],
                    'bill_status' => $status,
                    'payment_status' => 'unpaid',
                    'customer_name' => $summary['customer_name'],
                    'customer_mobile' => $summary['customer_mobile'],
                    'total_amount' => $summary['total_amount'],
                    'date_time' => $row['created_at'],
                    'created_at' => $row['created_at'],
                );
                if ($this->historyMatches($record, $filter, $q)) { $records[] = $record; }
            }
        }
        if ($this->tableExists('bills')) {
            $where = "WHERE business_id = ? AND branch_id = ?";
            $types = 'ii';
            $params = array($businessId, $branchId);
            if ($q !== '') {
                $where .= " AND (bill_no LIKE ? OR customer_name LIKE ? OR customer_mobile LIKE ?)";
                $like = '%' . $q . '%';
                $types .= 'sss';
                $params = array_merge($params, array($like, $like, $like));
            }
            $rows = $this->fetchAll("SELECT bill_id, bill_no, customer_name, customer_mobile, CASE WHEN bill_status = 'active' THEN 'completed' ELSE bill_status END AS bill_status, payment_status, net_amount AS total_amount, CONCAT(bill_date, ' ', COALESCE(bill_time,'')) AS date_time, created_at FROM bills {$where} ORDER BY bill_id DESC LIMIT 100", $types, $params);
            foreach ($rows as $row) {
                $row['source_type'] = 'bill';
                if ($this->historyMatches($row, $filter, $q)) { $records[] = $row; }
            }
        }
        usort($records, function ($a, $b) { return strcmp((string)($b['date_time'] ?? ''), (string)($a['date_time'] ?? '')); });
        return array_slice($records, 0, 120);
    }

    private function historyMatches(array $record, $filter, $q)
    {
        $filter = strtolower((string)$filter);
        $status = strtolower((string)($record['bill_status'] ?? ''));
        if ($filter !== '' && $filter !== 'all') {
            if ($filter === 'completed' && $status !== 'completed') { return false; }
            if ($filter === 'hold' && $status !== 'hold') { return false; }
            if ($filter === 'draft' && $status !== 'draft') { return false; }
            if ($filter === 'cancelled' && $status !== 'cancelled') { return false; }
        }
        if ($q !== '') {
            $hay = strtolower(json_encode($record));
            if (strpos($hay, strtolower($q)) === false) { return false; }
        }
        return true;
    }

    private function restoreBillStock($businessId, $branchId, $billId, $userId, $movementType)
    {
        $items = $this->fetchAll("SELECT stock_item_id, qty, article_no FROM bill_items WHERE business_id = ? AND branch_id = ? AND bill_id = ?", 'iii', array($businessId, $branchId, $billId));
        foreach ($items as $item) {
            $stockItemId = (int)$item['stock_item_id'];
            $qty = (float)$item['qty'];
            $this->execute("UPDATE stock_inward_items SET available_qty = available_qty + ?, item_status = 'active', updated_at = NOW() WHERE business_id = ? AND branch_id = ? AND stock_item_id = ?", 'diii', array($qty, $businessId, $branchId, $stockItemId));
            $row = $this->fetchOne("SELECT available_qty FROM stock_inward_items WHERE business_id = ? AND branch_id = ? AND stock_item_id = ?", 'iii', array($businessId, $branchId, $stockItemId));
            if ($this->tableExists('stock_movements')) {
                $balance = (float)($row['available_qty'] ?? 0);
                $this->execute("INSERT INTO stock_movements (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id, qty_in, qty_out, balance_qty, remarks, created_by, created_at) VALUES (?, ?, ?, ?, 'bill', ?, ?, 0, ?, ?, ?, NOW())", 'iiisiddsi', array($businessId, $branchId, $stockItemId, $movementType, $billId, $qty, $balance, ucfirst($movementType) . ' bill #' . $billId, $userId));
            }
        }
    }

    public function cancelSavedBill($businessId, $branchId, $billId, $userId, $reason = '')
    {
        $bill = $this->fetchOne("SELECT bill_id, bill_status FROM bills WHERE business_id = ? AND branch_id = ? AND bill_id = ? LIMIT 1", 'iii', array($businessId, $branchId, $billId));
        if (!$bill) { throw new Exception('Bill not found.'); }
        if ($bill['bill_status'] === 'active') {
            mysqli_begin_transaction($this->conn);
            try {
                $this->restoreBillStock($businessId, $branchId, $billId, $userId, 'sale_cancel');
                $this->execute("UPDATE bills SET bill_status = 'cancelled', payment_status = 'cancelled', updated_by = ?, updated_at = NOW() WHERE business_id = ? AND branch_id = ? AND bill_id = ?", 'iiii', array($userId, $businessId, $branchId, $billId));
                if ($this->tableExists('bill_barcodes')) {
                    $this->execute("UPDATE bill_barcodes SET barcode_status = 'cancelled' WHERE business_id = ? AND branch_id = ? AND bill_id = ?", 'iii', array($businessId, $branchId, $billId));
                }
                $this->logActivity($businessId, $branchId, $userId, 'POS Create Bill', 'bill_cancelled', $billId, array('reason' => $reason));
                mysqli_commit($this->conn);
            } catch (Throwable $e) {
                mysqli_rollback($this->conn);
                throw $e;
            }
        }
    }

    public function returnSavedBill($businessId, $branchId, $billId, $userId, $reason = '')
    {
        // Current schema has no returned status in bills.bill_status enum, so return uses the same stock-safe cancellation path.
        $this->cancelSavedBill($businessId, $branchId, $billId, $userId, $reason ?: 'Returned from POS');
    }

    public function validateOffer($businessId, $code)
    {
        if (!$this->tableExists('offer_codes')) { return array(); }
        $code = strtoupper(trim((string)$code));
        if ($code === '') { return array(); }
        $row = $this->fetchOne("SELECT offer_id, code, discount_type, discount_value FROM offer_codes WHERE business_id = ? AND code = ? AND status = 1 AND (valid_from IS NULL OR valid_from <= CURDATE()) AND (valid_to IS NULL OR valid_to >= CURDATE()) LIMIT 1", 'is', array($businessId, $code));
        return $row ?: array();
    }

    private function logActivity($businessId, $branchId, $userId, $module, $action, $recordId, $data)
    {
        $json = $data === null ? null : json_encode($data, JSON_UNESCAPED_UNICODE);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        if ($this->tableExists('activity_logs')) {
            $roleId = (int)($_SESSION['role_id'] ?? 0);
            $this->execute("INSERT INTO activity_logs (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NOW())", 'iiiississs', array($businessId, $branchId, $userId, $roleId, $module, $action, $recordId, $json, $ip, $ua));
        }
    }

    // ============================================
    // NEW: GET BILL FOR PRINT - Added for direct reprint
    // ============================================
    public function getBillForPrint($businessId, $branchId, $billId)
    {
        if (!$this->tableExists('bills')) {
            return null;
        }
        
        $bill = $this->fetchOne(
            "SELECT b.*, br.branch_name, br.floor_name, u.name AS created_by_name 
             FROM bills b 
             LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id 
             LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id 
             WHERE b.business_id = ? AND b.branch_id = ? AND b.bill_id = ? 
             LIMIT 1",
            'iii', 
            array($businessId, $branchId, $billId)
        );
        
        if (!$bill) {
            return null;
        }
        
        if ($this->tableExists('bill_barcodes')) {
            $barcode = $this->fetchOne(
                "SELECT barcode_value FROM bill_barcodes WHERE business_id = ? AND branch_id = ? AND bill_id = ? ORDER BY bill_barcode_id DESC LIMIT 1",
                'iii',
                array($businessId, $branchId, $billId)
            );
            if ($barcode) {
                $bill['bill_barcode'] = $barcode['barcode_value'];
            }
        }
        
        $items = array();
        if ($this->tableExists('bill_items')) {
            $items = $this->fetchAll(
                "SELECT bi.*, sii.color, sii.size, sii.article_name 
                 FROM bill_items bi
                 LEFT JOIN stock_inward_items sii ON sii.stock_item_id = bi.stock_item_id AND sii.business_id = bi.business_id
                 WHERE bi.business_id = ? AND bi.branch_id = ? AND bi.bill_id = ? 
                 ORDER BY bi.bill_item_id ASC",
                'iii',
                array($businessId, $branchId, $billId)
            );
        }
        
        $payments = array();
        if ($this->tableExists('bill_payments')) {
            $payments = $this->fetchAll(
                "SELECT bp.*, pm.payment_method_name, pm.method_type, u.name AS cashier_name
                 FROM bill_payments bp
                 LEFT JOIN payment_methods pm ON pm.payment_method_id = bp.payment_method_id AND pm.business_id = bp.business_id
                 LEFT JOIN users u ON u.user_id = bp.collected_by AND u.business_id = bp.business_id
                 WHERE bp.business_id = ? AND bp.branch_id = ? AND bp.bill_id = ?
                 ORDER BY bp.collected_at DESC",
                'iii',
                array($businessId, $branchId, $billId)
            );
        }
        
        return array(
            'bill' => $bill,
            'items' => $items,
            'payments' => $payments
        );
    }
}