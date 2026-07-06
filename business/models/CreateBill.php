<?php

declare(strict_types=1);

/**
 * CreateBill model for GK Footwear POS.
 * Uses the current database schema and the same defensive DB helper style as Stock List.
 * PHP 7.2+ compatible.
 */
class CreateBill
{
    private $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    public function tableExists(string $tableName): bool
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

    public function columnExists(string $tableName, string $columnName): bool
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

    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '') {
            return;
        }

        if (strlen($types) !== count($params)) {
            throw new Exception('SQL bind count mismatch. Types: ' . strlen($types) . ', Params: ' . count($params));
        }

        $bind = [];
        $bind[] = $types;
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    private function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new Exception('SQL prepare failed: ' . mysqli_error($this->conn));
        }

        $this->bindParams($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);

        $rows = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = $row;
        }

        mysqli_stmt_close($stmt);
        return $rows;
    }

    private function fetchOne(string $sql, string $types = '', array $params = []): ?array
    {
        $rows = $this->fetchAll($sql, $types, $params);
        return $rows[0] ?? null;
    }

    private function execute(string $sql, string $types = '', array $params = []): int
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new Exception('SQL prepare failed: ' . mysqli_error($this->conn));
        }

        $this->bindParams($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $insertId = (int)mysqli_insert_id($this->conn);
        mysqli_stmt_close($stmt);

        return $insertId;
    }

    public function getBranches(int $businessId, int $userId, bool $isAdmin = false): array
    {
        if (!$this->tableExists('branches')) {
            return [];
        }

        if ($isAdmin || $userId <= 0 || !$this->tableExists('user_branch_access')) {
            return $this->fetchAll(" 
                SELECT branch_id, branch_code, branch_name, floor_name
                FROM branches
                WHERE business_id = ?
                  AND status = 1
                ORDER BY branch_id ASC
            ", 'i', [$businessId]);
        }

        $branches = $this->fetchAll(" 
            SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name
            FROM branches b
            INNER JOIN user_branch_access uba
                ON uba.branch_id = b.branch_id
               AND uba.business_id = b.business_id
               AND uba.user_id = ?
            WHERE b.business_id = ?
              AND b.status = 1
            ORDER BY b.branch_id ASC
        ", 'ii', [$userId, $businessId]);

        if ($branches) {
            return $branches;
        }

        if ($this->columnExists('users', 'default_branch_id')) {
            $branches = $this->fetchAll(" 
                SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name
                FROM users u
                INNER JOIN branches b
                    ON b.branch_id = u.default_branch_id
                   AND b.business_id = u.business_id
                   AND b.status = 1
                WHERE u.business_id = ?
                  AND u.user_id = ?
                LIMIT 1
            ", 'ii', [$businessId, $userId]);

            if ($branches) {
                return $branches;
            }
        }

        return $this->fetchAll(" 
            SELECT branch_id, branch_code, branch_name, floor_name
            FROM branches
            WHERE business_id = ?
              AND status = 1
            ORDER BY branch_id ASC
        ", 'i', [$businessId]);
    }

    public function userCanAccessBranch(int $businessId, int $userId, int $branchId, bool $isAdmin = false): bool
    {
        if ($branchId <= 0) {
            return false;
        }

        $branches = $this->getBranches($businessId, $userId, $isAdmin);
        foreach ($branches as $branch) {
            if ((int)$branch['branch_id'] === $branchId) {
                return true;
            }
        }

        return false;
    }

    public function getCategories(int $businessId): array
    {
        if (!$this->tableExists('categories')) {
            return [];
        }

        $statusSql = $this->columnExists('categories', 'status') ? 'AND status = 1' : '';

        return $this->fetchAll(" 
            SELECT category_id, category_name
            FROM categories
            WHERE business_id = ?
              {$statusSql}
            ORDER BY category_name ASC
        ", 'i', [$businessId]);
    }

    public function getBrands(int $businessId): array
    {
        if (!$this->tableExists('brands')) {
            return [];
        }

        $statusSql = $this->columnExists('brands', 'status') ? 'AND status = 1' : '';

        return $this->fetchAll(" 
            SELECT brand_id, brand_name
            FROM brands
            WHERE business_id = ?
              {$statusSql}
            ORDER BY brand_name ASC
        ", 'i', [$businessId]);
    }

    public function getCustomers(int $businessId, string $search = ''): array
    {
        if (!$this->tableExists('customers')) {
            return [];
        }

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            return $this->fetchAll(" 
                SELECT customer_id, customer_name, mobile, email, gstin, address, loyalty_points, status
                FROM customers
                WHERE business_id = ?
                  AND status = 1
                  AND (customer_name LIKE ? OR mobile LIKE ? OR email LIKE ? OR gstin LIKE ?)
                ORDER BY customer_name ASC
                LIMIT 50
            ", 'issss', [$businessId, $like, $like, $like, $like]);
        }

        return $this->fetchAll(" 
            SELECT customer_id, customer_name, mobile, email, gstin, address, loyalty_points, status
            FROM customers
            WHERE business_id = ?
              AND status = 1
            ORDER BY customer_name ASC
            LIMIT 100
        ", 'i', [$businessId]);
    }

    public function ensureDefaultPaymentMethods(int $businessId): void
    {
        if (!$this->tableExists('payment_methods')) {
            return;
        }

        $defaults = [
            ['Cash', 'cash'],
            ['UPI', 'upi'],
            ['Card', 'card'],
            ['Cheque', 'cheque'],
            ['Credit', 'credit'],
            ['Split Payment', 'split'],
        ];

        foreach ($defaults as $default) {
            $name = $default[0];
            $type = $default[1];
            $existing = $this->fetchOne(" 
                SELECT payment_method_id
                FROM payment_methods
                WHERE business_id = ?
                  AND (method_type = ? OR LOWER(payment_method_name) = LOWER(?))
                LIMIT 1
            ", 'iss', [$businessId, $type, $name]);

            if ($existing) {
                continue;
            }

            $this->execute(" 
                INSERT INTO payment_methods (business_id, payment_method_name, method_type, status, created_at)
                VALUES (?, ?, ?, 1, NOW())
            ", 'iss', [$businessId, $name, $type]);
        }
    }

    public function getPaymentMethods(int $businessId): array
    {
        if (!$this->tableExists('payment_methods')) {
            return [];
        }

        $this->ensureDefaultPaymentMethods($businessId);

        return $this->fetchAll(" 
            SELECT payment_method_id, payment_method_name, method_type
            FROM payment_methods
            WHERE business_id = ?
              AND status = 1
            ORDER BY FIELD(method_type, 'cash','upi','card','cheque','credit','split','other'), payment_method_name ASC
        ", 'i', [$businessId]);
    }

    public function firstPaymentMethodId(int $businessId, string $type = 'cash'): int
    {
        if (!$this->tableExists('payment_methods')) {
            return 0;
        }

        $row = $this->fetchOne(" 
            SELECT payment_method_id
            FROM payment_methods
            WHERE business_id = ?
              AND status = 1
              AND method_type = ?
            LIMIT 1
        ", 'is', [$businessId, $type]);

        if ($row) {
            return (int)$row['payment_method_id'];
        }

        $row = $this->fetchOne(" 
            SELECT payment_method_id
            FROM payment_methods
            WHERE business_id = ?
              AND status = 1
            ORDER BY payment_method_id ASC
            LIMIT 1
        ", 'i', [$businessId]);

        return (int)($row['payment_method_id'] ?? 0);
    }

    public function searchProducts(int $businessId, int $branchId, array $filters): array
    {
        if (!$this->tableExists('stock_inward_items')) {
            return [];
        }

        $search = trim((string)($filters['search'] ?? ''));
        $categoryId = (int)($filters['category_id'] ?? 0);
        $brandId = (int)($filters['brand_id'] ?? 0);
        $limit = max(1, min(100, (int)($filters['limit'] ?? 24)));

        $where = "
            WHERE si.business_id = ?
              AND si.branch_id = ?
              AND si.item_status = 'active'
              AND si.available_qty > 0
              AND sib.batch_status = 'active'
        ";
        $types = 'ii';
        $params = [$businessId, $branchId];

        if ($categoryId > 0) {
            $where .= ' AND si.category_id = ?';
            $types .= 'i';
            $params[] = $categoryId;
        }

        if ($brandId > 0) {
            $where .= ' AND si.brand_id = ?';
            $types .= 'i';
            $params[] = $brandId;
        }

        if ($search !== '') {
            $where .= " AND (
                si.article_no LIKE ? OR si.article_name LIKE ? OR si.size LIKE ? OR si.color LIKE ? OR COALESCE(sb.barcode_value, '') LIKE ?
            )";
            $like = '%' . $search . '%';
            for ($i = 0; $i < 5; $i++) {
                $types .= 's';
                $params[] = $like;
            }
        }

        return $this->fetchAll(" 
            SELECT
                si.stock_item_id,
                si.batch_id,
                si.branch_id,
                sib.batch_no,
                si.category_id,
                c.category_name,
                si.brand_id,
                b.brand_name,
                si.article_no,
                si.article_name,
                si.size,
                si.color,
                si.available_qty,
                si.mrp_rate,
                si.product_discount_type AS discount_type,
                si.product_discount_value AS discount_value,
                si.discount_amount,
                si.selling_rate,
                sb.barcode_id,
                sb.barcode_value
            FROM stock_inward_items si
            INNER JOIN stock_inward_batches sib
                ON sib.batch_id = si.batch_id
               AND sib.business_id = si.business_id
               AND sib.branch_id = si.branch_id
            LEFT JOIN categories c ON c.category_id = si.category_id AND c.business_id = si.business_id
            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id
            LEFT JOIN (
                SELECT stock_item_id, business_id, branch_id, MIN(barcode_id) AS barcode_id, MIN(barcode_value) AS barcode_value
                FROM stock_barcodes
                WHERE barcode_status = 'active'
                GROUP BY stock_item_id, business_id, branch_id
            ) sb ON sb.stock_item_id = si.stock_item_id AND sb.business_id = si.business_id AND sb.branch_id = si.branch_id
            {$where}
            ORDER BY si.stock_item_id DESC
            LIMIT {$limit}
        ", $types, $params);
    }

    public function productByStockItemId(int $businessId, int $branchId, int $stockItemId, bool $forUpdate = false): ?array
    {
        $forUpdateSql = $forUpdate ? ' FOR UPDATE' : '';

        return $this->fetchOne(" 
            SELECT
                si.stock_item_id,
                si.batch_id,
                si.branch_id,
                sib.batch_no,
                si.category_id,
                c.category_name,
                si.brand_id,
                b.brand_name,
                si.article_no,
                si.article_name,
                si.size,
                si.color,
                si.available_qty,
                si.mrp_rate,
                si.product_discount_type AS discount_type,
                si.product_discount_value AS discount_value,
                si.discount_amount,
                si.selling_rate,
                sb.barcode_id,
                sb.barcode_value
            FROM stock_inward_items si
            INNER JOIN stock_inward_batches sib
                ON sib.batch_id = si.batch_id
               AND sib.business_id = si.business_id
               AND sib.branch_id = si.branch_id
               AND sib.batch_status = 'active'
            LEFT JOIN categories c ON c.category_id = si.category_id AND c.business_id = si.business_id
            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id
            LEFT JOIN (
                SELECT stock_item_id, business_id, branch_id, MIN(barcode_id) AS barcode_id, MIN(barcode_value) AS barcode_value
                FROM stock_barcodes
                WHERE barcode_status = 'active'
                GROUP BY stock_item_id, business_id, branch_id
            ) sb ON sb.stock_item_id = si.stock_item_id AND sb.business_id = si.business_id AND sb.branch_id = si.branch_id
            WHERE si.business_id = ?
              AND si.branch_id = ?
              AND si.stock_item_id = ?
              AND si.item_status = 'active'
            LIMIT 1{$forUpdateSql}
        ", 'iii', [$businessId, $branchId, $stockItemId]);
    }

    public function productByBarcode(int $businessId, int $branchId, string $barcode): ?array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        $row = $this->fetchOne(" 
            SELECT si.stock_item_id
            FROM stock_barcodes sb
            INNER JOIN stock_inward_items si
                ON si.stock_item_id = sb.stock_item_id
               AND si.business_id = sb.business_id
               AND si.branch_id = sb.branch_id
            INNER JOIN stock_inward_batches sib
                ON sib.batch_id = si.batch_id
               AND sib.business_id = si.business_id
               AND sib.branch_id = si.branch_id
               AND sib.batch_status = 'active'
            WHERE sb.business_id = ?
              AND sb.branch_id = ?
              AND sb.barcode_value = ?
              AND sb.barcode_status = 'active'
              AND si.item_status = 'active'
              AND si.available_qty > 0
            LIMIT 1
        ", 'iis', [$businessId, $branchId, $barcode]);

        if (!$row) {
            return null;
        }

        return $this->productByStockItemId($businessId, $branchId, (int)$row['stock_item_id']);
    }

    public function recentBills(int $businessId, int $branchId = 0, int $limit = 10): array
    {
        if (!$this->tableExists('bills')) {
            return [];
        }

        $limit = max(1, min(20, $limit));

        if ($branchId > 0) {
            return $this->fetchAll(" 
                SELECT b.bill_id, b.bill_no, b.order_no, b.customer_name, b.customer_mobile, b.net_amount,
                       b.paid_amount, b.balance_amount, b.payment_status, b.bill_date, b.bill_time, br.branch_name
                FROM bills b
                LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
                WHERE b.business_id = ?
                  AND b.branch_id = ?
                  AND b.bill_status = 'active'
                ORDER BY b.bill_id DESC
                LIMIT {$limit}
            ", 'ii', [$businessId, $branchId]);
        }

        return $this->fetchAll(" 
            SELECT b.bill_id, b.bill_no, b.order_no, b.customer_name, b.customer_mobile, b.net_amount,
                   b.paid_amount, b.balance_amount, b.payment_status, b.bill_date, b.bill_time, br.branch_name
            FROM bills b
            LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
            WHERE b.business_id = ?
              AND b.bill_status = 'active'
            ORDER BY b.bill_id DESC
            LIMIT {$limit}
        ", 'i', [$businessId]);
    }

    private function calculateDiscount(float $base, string $type, float $value): float
    {
        $base = max(0, $base);
        $value = max(0, $value);

        if ($type === 'percent') {
            return min($base, round($base * min(100, $value) / 100, 2));
        }

        if ($type === 'amount') {
            return min($base, round($value, 2));
        }

        return 0.00;
    }

    private function nextBillNo(int $businessId): string
    {
        if (!$this->tableExists('number_sequences')) {
            $row = $this->fetchOne(" 
                SELECT COALESCE(MAX(bill_id), 0) + 1 AS next_no
                FROM bills
                WHERE business_id = ?
            ", 'i', [$businessId]);

            return 'BILL-' . str_pad((string)((int)($row['next_no'] ?? 1)), 4, '0', STR_PAD_LEFT);
        }

        $row = $this->fetchOne(" 
            SELECT sequence_id, prefix, current_number, padding_length
            FROM number_sequences
            WHERE business_id = ?
              AND sequence_key = 'bill_no'
              AND status = 1
            ORDER BY sequence_id ASC
            LIMIT 1
            FOR UPDATE
        ", 'i', [$businessId]);

        if (!$row) {
            $sequenceId = $this->execute(" 
                INSERT INTO number_sequences (business_id, branch_id, sequence_key, prefix, current_number, padding_length, status, updated_at)
                VALUES (?, NULL, 'bill_no', 'BILL', 0, 4, 1, NOW())
            ", 'i', [$businessId]);

            $row = [
                'sequence_id' => $sequenceId,
                'prefix' => 'BILL',
                'current_number' => 0,
                'padding_length' => 4,
            ];
        }

        $nextNo = ((int)$row['current_number']) + 1;
        $prefix = (string)($row['prefix'] ?: 'BILL');
        $padding = max(1, (int)($row['padding_length'] ?? 4));

        $this->execute(" 
            UPDATE number_sequences
            SET current_number = ?, updated_at = NOW()
            WHERE sequence_id = ?
        ", 'ii', [$nextNo, (int)$row['sequence_id']]);

        return $prefix . '-' . str_pad((string)$nextNo, $padding, '0', STR_PAD_LEFT);
    }

    private function logActivity(int $businessId, int $branchId, int $userId, string $action, int $recordId, array $payload): void
    {
        $table = $this->tableExists('business_activity_logs') ? 'business_activity_logs' : ($this->tableExists('activity_logs') ? 'activity_logs' : '');
        if ($table === '') {
            return;
        }

        $roleId = (int)($_SESSION['role_id'] ?? 0);
        $moduleName = 'Create Bill POS';
        $newValue = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $device = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');

        $this->execute(" 
            INSERT INTO {$table}
                (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NOW())
        ", 'iiiississs', [$businessId, $branchId, $userId, $roleId, $moduleName, $action, $recordId, $newValue, $ip, $device]);
    }

    public function saveBill(int $businessId, int $userId, array $payload): array
    {
        if (!$this->tableExists('bills') || !$this->tableExists('bill_items')) {
            throw new Exception('Billing tables are missing.');
        }

        $branchId = (int)($payload['branch_id'] ?? 0);
        $customerId = (int)($payload['customer_id'] ?? 0);
        $customerName = trim((string)($payload['customer_name'] ?? 'Walk-in Customer'));
        $customerMobile = preg_replace('/[^0-9]/', '', (string)($payload['customer_mobile'] ?? ''));
        $billDiscountType = (string)($payload['bill_discount_type'] ?? 'none');
        $billDiscountValue = (float)($payload['bill_discount_value'] ?? 0);
        $loyaltyRedeem = (float)($payload['loyalty_redeem_amount'] ?? 0);
        $collectNow = (int)($payload['collect_now'] ?? 0) === 1;
        $paymentMethodId = (int)($payload['payment_method_id'] ?? 0);
        $paidAmountInput = (float)($payload['paid_amount'] ?? 0);
        $itemsJson = (string)($payload['items_json'] ?? '[]');

        if ($branchId <= 0) {
            throw new Exception('Select branch / firm.');
        }

        if ($customerName === '') {
            $customerName = 'Walk-in Customer';
        }

        if ($customerMobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $customerMobile)) {
            throw new Exception('Mobile number must be exactly 10 digits and start with 6, 7, 8, or 9.');
        }

        if (!in_array($billDiscountType, ['none', 'percent', 'amount'], true)) {
            $billDiscountType = 'none';
        }

        $items = json_decode($itemsJson, true);
        if (!is_array($items) || !$items) {
            throw new Exception('Add at least one item.');
        }

        mysqli_begin_transaction($this->conn);

        try {
            $billNo = $this->nextBillNo($businessId);
            $orderNo = 'ORD-' . $billNo;

            $mrpTotal = 0.00;
            $itemDiscountTotal = 0.00;
            $sellingAmount = 0.00;
            $preparedItems = [];

            foreach ($items as $rawItem) {
                $stockItemId = (int)($rawItem['stock_item_id'] ?? 0);
                $qty = (float)($rawItem['qty'] ?? 0);
                $discountType = (string)($rawItem['discount_type'] ?? 'none');
                $discountValue = (float)($rawItem['discount_value'] ?? 0);

                if ($stockItemId <= 0) {
                    throw new Exception('Invalid stock item selected.');
                }

                if ($qty <= 0) {
                    throw new Exception('Quantity must be greater than zero.');
                }

                if (!in_array($discountType, ['none', 'percent', 'amount'], true)) {
                    $discountType = 'none';
                }

                $stock = $this->productByStockItemId($businessId, $branchId, $stockItemId, true);
                if (!$stock) {
                    throw new Exception('Stock item not found for selected branch / firm.');
                }

                $availableQty = (float)$stock['available_qty'];
                if ($qty > $availableQty) {
                    throw new Exception('Quantity exceeds available stock for ' . $stock['article_no'] . '. Available: ' . number_format($availableQty, 2));
                }

                $mrpRate = (float)$stock['mrp_rate'];
                $discountAmount = $this->calculateDiscount($mrpRate, $discountType, $discountValue);
                $sellingRate = max(0, round($mrpRate - $discountAmount, 2));
                $amount = round($sellingRate * $qty, 2);

                $mrpTotal += round($mrpRate * $qty, 2);
                $itemDiscountTotal += round($discountAmount * $qty, 2);
                $sellingAmount += $amount;

                $stock['qty_to_bill'] = $qty;
                $stock['bill_discount_type'] = $discountType;
                $stock['bill_discount_value'] = $discountValue;
                $stock['bill_discount_amount'] = $discountAmount;
                $stock['bill_selling_rate'] = $sellingRate;
                $stock['bill_amount'] = $amount;
                $preparedItems[] = $stock;
            }

            $billDiscountAmount = $this->calculateDiscount($sellingAmount, $billDiscountType, $billDiscountValue);
            $loyaltyRedeem = min(max(0, $loyaltyRedeem), max(0, $sellingAmount - $billDiscountAmount));
            $todaySavings = round($itemDiscountTotal + $billDiscountAmount + $loyaltyRedeem, 2);
            $netAmount = round(max(0, $sellingAmount - $billDiscountAmount - $loyaltyRedeem), 2);
            $paidAmount = $collectNow ? min(max(0, $paidAmountInput), $netAmount) : 0.00;
            $balanceAmount = round(max(0, $netAmount - $paidAmount), 2);
            $paymentStatus = $paidAmount <= 0 ? 'pending' : ($balanceAmount > 0 ? 'partial' : 'paid');
            $gstTypeKey = 'gst_composition';
            $invoiceTitle = 'Bill of Supply';
            $customerIdForDb = $customerId > 0 ? $customerId : null;

            $billId = $this->execute(" 
                INSERT INTO bills
                    (business_id, branch_id, bill_no, order_no, bill_date, bill_time, customer_id,
                     customer_name, customer_mobile, gst_type_key, invoice_title, mrp_total,
                     item_discount_total, bill_discount_type, bill_discount_value, bill_discount_amount,
                     selling_amount, loyalty_redeem_amount, today_savings_amount, taxable_amount,
                     cgst_amount, sgst_amount, igst_amount, tax_amount, round_off, net_amount,
                     paid_amount, balance_amount, payment_status, updated_status, bill_status,
                     print_count, created_by, created_at)
                VALUES
                    (?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0,
                     0, 0, 0, 0, 0, ?, ?, ?, ?, 'original', 'active', 0, ?, NOW())
            ", 'iississssddsddddddddsi', [
                $businessId,
                $branchId,
                $billNo,
                $orderNo,
                $customerIdForDb,
                $customerName,
                $customerMobile,
                $gstTypeKey,
                $invoiceTitle,
                $mrpTotal,
                $itemDiscountTotal,
                $billDiscountType,
                $billDiscountValue,
                $billDiscountAmount,
                $sellingAmount,
                $loyaltyRedeem,
                $todaySavings,
                $netAmount,
                $paidAmount,
                $balanceAmount,
                $paymentStatus,
                $userId,
            ]);

            foreach ($preparedItems as $item) {
                $stockBatchId = (int)$item['batch_id'];
                $stockItemId = (int)$item['stock_item_id'];
                $barcodeId = (int)($item['barcode_id'] ?? 0);
                $barcodeIdForDb = $barcodeId > 0 ? $barcodeId : null;
                $articleNo = (string)$item['article_no'];
                $articleName = (string)($item['article_name'] ?? '');
                $brandId = (int)($item['brand_id'] ?? 0);
                $brandIdForDb = $brandId > 0 ? $brandId : null;
                $size = (string)$item['size'];
                $qty = (float)$item['qty_to_bill'];
                $mrpRate = (float)$item['mrp_rate'];
                $discountType = (string)$item['bill_discount_type'];
                $discountValue = (float)$item['bill_discount_value'];
                $discountAmount = (float)$item['bill_discount_amount'];
                $sellingRate = (float)$item['bill_selling_rate'];
                $amount = (float)$item['bill_amount'];

                $this->execute(" 
                    INSERT INTO bill_items
                        (business_id, branch_id, bill_id, stock_batch_id, stock_item_id, barcode_id,
                         article_no, article_name, brand_id, size, qty, mrp_rate, discount_type,
                         discount_value, discount_amount, selling_rate, amount, taxable_amount,
                         cgst_amount, sgst_amount, igst_amount, tax_amount, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 0, NOW())
                ", 'iiiiiissisddsdddd', [
                    $businessId,
                    $branchId,
                    $billId,
                    $stockBatchId,
                    $stockItemId,
                    $barcodeIdForDb,
                    $articleNo,
                    $articleName,
                    $brandIdForDb,
                    $size,
                    $qty,
                    $mrpRate,
                    $discountType,
                    $discountValue,
                    $discountAmount,
                    $sellingRate,
                    $amount,
                ]);

                $newBalance = round(((float)$item['available_qty']) - $qty, 2);
                $newStatus = $newBalance <= 0 ? 'out_of_stock' : 'active';

                $this->execute(" 
                    UPDATE stock_inward_items
                    SET available_qty = ?, item_status = ?, updated_at = NOW()
                    WHERE business_id = ?
                      AND branch_id = ?
                      AND stock_item_id = ?
                ", 'dsiii', [$newBalance, $newStatus, $businessId, $branchId, $stockItemId]);

                if ($this->tableExists('stock_movements')) {
                    $remarks = 'Sale bill ' . $billNo;
                    $referenceType = 'bill';
                    $this->execute(" 
                        INSERT INTO stock_movements
                            (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id,
                             qty_in, qty_out, balance_qty, remarks, created_by, created_at)
                        VALUES
                            (?, ?, ?, 'sale', ?, ?, 0, ?, ?, ?, ?, NOW())
                    ", 'iiisiddsi', [$businessId, $branchId, $stockItemId, $referenceType, $billId, $qty, $newBalance, $remarks, $userId]);
                }
            }

            $barcodeValue = 'BILL-' . str_pad((string)$billId, 6, '0', STR_PAD_LEFT);
            if ($this->tableExists('bill_barcodes')) {
                $this->execute(" 
                    INSERT INTO bill_barcodes (business_id, branch_id, bill_id, barcode_value, barcode_status, created_at)
                    VALUES (?, ?, ?, ?, 'active', NOW())
                ", 'iiis', [$businessId, $branchId, $billId, $barcodeValue]);
            }

            $paymentId = null;
            if ($paidAmount > 0 && $this->tableExists('bill_payments')) {
                if ($paymentMethodId <= 0) {
                    $paymentMethodId = $this->firstPaymentMethodId($businessId, 'cash');
                }

                if ($paymentMethodId > 0) {
                    $paymentNote = 'POS collection';
                    $paymentId = $this->execute(" 
                        INSERT INTO bill_payments
                            (business_id, branch_id, bill_id, payment_method_id, paid_amount,
                             reference_no, payment_note, payment_status, collected_by, collected_at)
                        VALUES
                            (?, ?, ?, ?, ?, NULL, ?, 'received', ?, NOW())
                    ", 'iiiidsi', [$businessId, $branchId, $billId, $paymentMethodId, $paidAmount, $paymentNote, $userId]);

                    if ($this->tableExists('cashier_collections')) {
                        $collectionStatus = $balanceAmount > 0 ? 'partial' : 'paid';
                        $this->execute(" 
                            INSERT INTO cashier_collections
                                (business_id, branch_id, cashier_id, bill_id, payment_id, collected_amount,
                                 payment_method_id, collection_status, collected_at)
                            VALUES
                                (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                        ", 'iiiiidis', [$businessId, $branchId, $userId, $billId, $paymentId, $paidAmount, $paymentMethodId, $collectionStatus]);
                    }
                }
            }

            if ($customerId > 0) {
                if ($this->tableExists('customer_ledger')) {
                    $remarks = 'Bill ' . $billNo;
                    $this->execute(" 
                        INSERT INTO customer_ledger
                            (business_id, branch_id, customer_id, reference_type, reference_id,
                             debit, credit, balance, remarks, created_by, created_at)
                        VALUES
                            (?, ?, ?, 'bill', ?, ?, ?, ?, ?, ?, NOW())
                    ", 'iiiidddsi', [$businessId, $branchId, $customerId, $billId, $netAmount, $paidAmount, $balanceAmount, $remarks, $userId]);
                }

                if ($this->tableExists('customer_outstanding')) {
                    $this->execute(" 
                        INSERT INTO customer_outstanding
                            (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount, updated_at)
                        VALUES
                            (?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE
                            total_bill_amount = total_bill_amount + VALUES(total_bill_amount),
                            total_paid_amount = total_paid_amount + VALUES(total_paid_amount),
                            balance_amount = balance_amount + VALUES(balance_amount),
                            updated_at = NOW()
                    ", 'iiddd', [$businessId, $customerId, $netAmount, $paidAmount, $balanceAmount]);
                }

                if ($this->tableExists('payment_ledger')) {
                    $remarks = 'Bill ' . $billNo;
                    $this->execute(" 
                        INSERT INTO payment_ledger
                            (business_id, branch_id, customer_id, bill_id, transaction_type,
                             debit, credit, balance, payment_method_id, remarks, created_by, created_at)
                        VALUES
                            (?, ?, ?, ?, 'bill', ?, 0, ?, NULL, ?, ?, NOW())
                    ", 'iiiiddsi', [$businessId, $branchId, $customerId, $billId, $netAmount, $balanceAmount, $remarks, $userId]);

                    if ($paidAmount > 0) {
                        $transactionType = $balanceAmount > 0 ? 'partial_payment' : 'payment';
                        $remarks = 'Payment for bill ' . $billNo;
                        $this->execute(" 
                            INSERT INTO payment_ledger
                                (business_id, branch_id, customer_id, bill_id, transaction_type,
                                 debit, credit, balance, payment_method_id, remarks, created_by, created_at)
                            VALUES
                                (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW())
                        ", 'iiiisddisi', [$businessId, $branchId, $customerId, $billId, $transactionType, $paidAmount, $balanceAmount, $paymentMethodId, $remarks, $userId]);
                    }
                }
            }

            $this->logActivity($businessId, $branchId, $userId, 'bill_created', $billId, [
                'bill_no' => $billNo,
                'order_no' => $orderNo,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'payment_status' => $paymentStatus,
            ]);

            mysqli_commit($this->conn);

            return [
                'bill_id' => $billId,
                'bill_no' => $billNo,
                'order_no' => $orderNo,
                'barcode_value' => $barcodeValue,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'balance_amount' => $balanceAmount,
                'payment_status' => $paymentStatus,
                'print_url' => 'bill-print.php?bill_id=' . $billId,
            ];
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }
}
