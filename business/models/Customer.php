<?php
/**
 * GK Footwear POS - Customer Model
 * Uses only existing DB schema tables. No dummy/hardcoded customer/product data.
 */

class Customer
{
    private $conn;
    private $businessId;
    private $userId;

    public function __construct(mysqli $conn, int $businessId, int $userId = 0)
    {
        $this->conn = $conn;
        $this->businessId = $businessId;
        $this->userId = $userId;
    }

    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '' || empty($params)) {
            return;
        }
        $refs = [];
        $refs[] = $stmt;
        $refs[] = $types;
        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', $refs);
    }

    private function queryRows(string $sql, string $types = '', array $params = []): array
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new Exception(mysqli_error($this->conn));
        }
        $this->bindParams($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    private function queryOne(string $sql, string $types = '', array $params = []): ?array
    {
        $rows = $this->queryRows($sql, $types, $params);
        return $rows ? $rows[0] : null;
    }

    private function execute(string $sql, string $types = '', array $params = []): bool
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new Exception(mysqli_error($this->conn));
        }
        $this->bindParams($stmt, $types, $params);
        $ok = mysqli_stmt_execute($stmt);
        if (!$ok) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception($error ?: mysqli_error($this->conn));
        }
        mysqli_stmt_close($stmt);
        return true;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            'ss',
            [$table, $column]
        );
        return ((int)($row['total'] ?? 0)) > 0;
    }

    private function tableExists(string $table): bool
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            's',
            [$table]
        );
        return ((int)($row['total'] ?? 0)) > 0;
    }

    private function toFloat($value): float
    {
        return round((float)$value, 2);
    }

    private function cleanMobile(string $mobile): string
    {
        return substr(preg_replace('/[^0-9]/', '', $mobile), 0, 10);
    }

    private function currentBranchId(): ?int
    {
        $branchId = (int)($_SESSION['branch_id'] ?? $_SESSION['default_branch_id'] ?? 0);
        if ($branchId <= 0) {
            return null;
        }
        $row = $this->queryOne(
            "SELECT branch_id FROM branches WHERE branch_id = ? AND business_id = ? AND status = 1 LIMIT 1",
            'ii',
            [$branchId, $this->businessId]
        );
        return $row ? (int)$row['branch_id'] : null;
    }

    public function stats(): array
    {
        $sql = "
            SELECT
                COUNT(*) AS total_customers,
                SUM(CASE WHEN c.status = 1 THEN 1 ELSE 0 END) AS active_customers,
                COALESCE(SUM(COALESCE(co.balance_amount, c.opening_outstanding, 0)), 0) AS current_total,
                COALESCE(SUM(c.loyalty_points), 0) AS loyalty_total,
                COALESCE(SUM(CASE WHEN COALESCE(co.balance_amount, c.opening_outstanding, 0) > 0 THEN 1 ELSE 0 END), 0) AS outstanding_customers
            FROM customers c
            LEFT JOIN customer_outstanding co
                ON co.customer_id = c.customer_id
               AND co.business_id = c.business_id
            WHERE c.business_id = ?
        ";
        return $this->queryOne($sql, 'i', [$this->businessId]) ?: [];
    }

    public function list(array $filters = []): array
    {
        $where = ['c.business_id = ?'];
        $types = 'iiii';
        $params = [$this->businessId, $this->businessId, $this->businessId, $this->businessId];

        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = 'c.status = ?';
            $types .= 'i';
            $params[] = (int)$filters['status'];
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $where[] = "(
                c.customer_name LIKE ?
                OR c.mobile LIKE ?
                OR c.email LIKE ?
                OR c.gstin LIKE ?
                OR EXISTS (
                    SELECT 1
                    FROM bills b
                    INNER JOIN bill_items bi
                        ON bi.bill_id = b.bill_id
                       AND bi.business_id = b.business_id
                    LEFT JOIN stock_inward_items sii
                        ON sii.stock_item_id = bi.stock_item_id
                       AND sii.business_id = bi.business_id
                    LEFT JOIN stock_barcodes sb
                        ON sb.barcode_id = bi.barcode_id
                       AND sb.business_id = bi.business_id
                    WHERE b.business_id = c.business_id
                      AND b.customer_id = c.customer_id
                      AND COALESCE(b.bill_status, 'active') NOT IN ('cancelled', 'deleted', 'returned', 'return')
                      AND (
                          b.bill_no LIKE ? OR b.order_no LIKE ?
                          OR bi.article_no LIKE ? OR bi.article_name LIKE ?
                          OR bi.size LIKE ? OR sii.color LIKE ?
                          OR sb.barcode_value LIKE ?
                      )
                    LIMIT 1
                )
            )";
            $types .= 'sssssssssss';
            for ($i = 0; $i < 11; $i++) {
                $params[] = $search;
            }
        }

        $sql = "
            SELECT
                c.customer_id,
                c.business_id,
                c.customer_name,
                c.mobile,
                c.email,
                c.address,
                c.gstin,
                c.opening_outstanding,
                c.loyalty_points,
                c.status,
                c.created_at,
                c.updated_at,
                GREATEST(COALESCE(co.balance_amount, c.opening_outstanding, 0), COALESCE(pbill.pending_bill_amount, 0)) AS current_outstanding,
                COALESCE(pbill.pending_bill_count, 0) AS pending_bill_count,
                COALESCE(pbill.pending_bill_amount, 0) AS pending_bill_amount,
                COALESCE(pbill.pending_bill_refs, '') AS pending_bill_refs,
                COALESCE(bsum.bill_count, 0) AS bill_count,
                COALESCE(bsum.total_purchase_amount, 0) AS total_purchase_amount,
                COALESCE(bsum.total_paid_amount, 0) AS total_paid_amount,
                COALESCE(psum.purchased_article_count, 0) AS purchased_article_count,
                COALESCE(psum.total_purchased_qty, 0) AS total_purchased_qty,
                COALESCE(psum.latest_article, '-') AS latest_article,
                COALESCE(psum.latest_color, '-') AS latest_color,
                COALESCE(psum.latest_size, '-') AS latest_size,
                COALESCE(psum.latest_qr_code, '') AS latest_qr_code,
                COALESCE(psum.latest_available_qty, 0) AS latest_available_qty
            FROM customers c
            LEFT JOIN customer_outstanding co
                ON co.customer_id = c.customer_id
               AND co.business_id = c.business_id
            LEFT JOIN (
                SELECT customer_id,
                       COUNT(*) AS bill_count,
                       COALESCE(SUM(net_amount), 0) AS total_purchase_amount,
                       COALESCE(SUM(paid_amount), 0) AS total_paid_amount
                FROM bills
                WHERE business_id = ?
                  AND customer_id IS NOT NULL
                  AND COALESCE(bill_status, 'active') NOT IN ('cancelled', 'deleted', 'returned', 'return')
                GROUP BY customer_id
            ) bsum ON bsum.customer_id = c.customer_id
            LEFT JOIN (
                SELECT
                    customer_id,
                    COUNT(*) AS pending_bill_count,
                    COALESCE(SUM(CASE WHEN COALESCE(balance_amount, 0) > 0 THEN balance_amount ELSE 0 END), 0) AS pending_bill_amount,
                    GROUP_CONCAT(CONCAT(bill_id, '::', COALESCE(bill_no, ''), '::', COALESCE(DATE_FORMAT(bill_date, '%d-%m-%Y'), ''), '::', COALESCE(balance_amount, 0)) ORDER BY created_at DESC, bill_id DESC SEPARATOR '||') AS pending_bill_refs
                FROM bills
                WHERE business_id = ?
                  AND customer_id IS NOT NULL
                  AND COALESCE(bill_status, 'active') NOT IN ('cancelled', 'deleted', 'returned', 'return')
                  AND (
                        COALESCE(balance_amount, 0) > 0.0001
                        OR LOWER(COALESCE(payment_status, '')) IN ('pending', 'partial', 'partially_paid')
                      )
                GROUP BY customer_id
            ) pbill ON pbill.customer_id = c.customer_id
            LEFT JOIN (
                SELECT
                    x.customer_id,
                    COUNT(DISTINCT CONCAT(COALESCE(x.stock_item_id, 0), '|', x.article_no, '|', x.size, '|', COALESCE(x.color, ''))) AS purchased_article_count,
                    COALESCE(SUM(x.qty), 0) AS total_purchased_qty,
                    SUBSTRING_INDEX(GROUP_CONCAT(x.article_no ORDER BY x.created_at DESC, x.bill_item_id DESC SEPARATOR '||'), '||', 1) AS latest_article,
                    SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(x.color, '-') ORDER BY x.created_at DESC, x.bill_item_id DESC SEPARATOR '||'), '||', 1) AS latest_color,
                    SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(x.size, '-') ORDER BY x.created_at DESC, x.bill_item_id DESC SEPARATOR '||'), '||', 1) AS latest_size,
                    SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(x.qr_code, '') ORDER BY x.created_at DESC, x.bill_item_id DESC SEPARATOR '||'), '||', 1) AS latest_qr_code,
                    SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(x.available_qty, 0) ORDER BY x.created_at DESC, x.bill_item_id DESC SEPARATOR '||'), '||', 1) AS latest_available_qty
                FROM (
                    SELECT b.customer_id, bi.bill_item_id, bi.stock_item_id, bi.article_no, bi.size, bi.qty, bi.created_at,
                           sii.color, sii.available_qty, COALESCE(sb.barcode_value, sb2.barcode_value, '') AS qr_code
                    FROM bills b
                    INNER JOIN bill_items bi
                        ON bi.bill_id = b.bill_id
                       AND bi.business_id = b.business_id
                    LEFT JOIN stock_inward_items sii
                        ON sii.stock_item_id = bi.stock_item_id
                       AND sii.business_id = bi.business_id
                    LEFT JOIN stock_barcodes sb
                        ON sb.barcode_id = bi.barcode_id
                       AND sb.business_id = bi.business_id
                    LEFT JOIN (
                        SELECT business_id, stock_item_id, MIN(barcode_value) AS barcode_value
                        FROM stock_barcodes
                        WHERE barcode_status <> 'deleted'
                        GROUP BY business_id, stock_item_id
                    ) sb2 ON sb2.business_id = bi.business_id AND sb2.stock_item_id = bi.stock_item_id
                    WHERE b.business_id = ?
                      AND b.customer_id IS NOT NULL
                      AND b.bill_status = 'active'
                ) x
                GROUP BY x.customer_id
            ) psum ON psum.customer_id = c.customer_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.created_at DESC, c.customer_id DESC
            LIMIT 500
        ";

        return $this->queryRows($sql, $types, $params);
    }

    public function get(int $customerId): ?array
    {
        $sql = "
            SELECT c.*,
                   COALESCE(co.total_bill_amount, 0) AS total_bill_amount,
                   COALESCE(co.total_paid_amount, 0) AS total_paid_amount,
                   GREATEST(COALESCE(co.balance_amount, c.opening_outstanding, 0), COALESCE(pbill.pending_bill_amount, 0)) AS current_outstanding,
                   COALESCE(pbill.pending_bill_count, 0) AS pending_bill_count,
                   COALESCE(pbill.pending_bill_amount, 0) AS pending_bill_amount,
                   COALESCE(pbill.pending_bill_refs, '') AS pending_bill_refs
            FROM customers c
            LEFT JOIN customer_outstanding co
                ON co.customer_id = c.customer_id
               AND co.business_id = c.business_id
            LEFT JOIN (
                SELECT
                    customer_id,
                    COUNT(*) AS pending_bill_count,
                    COALESCE(SUM(CASE WHEN COALESCE(balance_amount, 0) > 0 THEN balance_amount ELSE 0 END), 0) AS pending_bill_amount,
                    GROUP_CONCAT(CONCAT(bill_id, '::', COALESCE(bill_no, ''), '::', COALESCE(DATE_FORMAT(bill_date, '%d-%m-%Y'), ''), '::', COALESCE(balance_amount, 0)) ORDER BY created_at DESC, bill_id DESC SEPARATOR '||') AS pending_bill_refs
                FROM bills
                WHERE business_id = ?
                  AND customer_id = ?
                  AND COALESCE(bill_status, 'active') NOT IN ('cancelled', 'deleted', 'returned', 'return')
                  AND (
                        COALESCE(balance_amount, 0) > 0.0001
                        OR LOWER(COALESCE(payment_status, '')) IN ('pending', 'partial', 'partially_paid')
                      )
                GROUP BY customer_id
            ) pbill ON pbill.customer_id = c.customer_id
            WHERE c.business_id = ?
              AND c.customer_id = ?
            LIMIT 1
        ";
        return $this->queryOne($sql, 'iiii', [$this->businessId, $customerId, $this->businessId, $customerId]);
    }

    public function ledger(int $customerId): array
    {
        return $this->queryRows(
            "SELECT cl.*, b.bill_no, b.order_no, br.branch_name, br.floor_name
             FROM customer_ledger cl
             LEFT JOIN bills b
                ON b.bill_id = cl.reference_id
               AND cl.reference_type IN ('bill','payment','partial_payment')
               AND b.business_id = cl.business_id
             LEFT JOIN branches br
                ON br.branch_id = cl.branch_id
               AND br.business_id = cl.business_id
             WHERE cl.business_id = ?
               AND cl.customer_id = ?
             ORDER BY cl.created_at DESC, cl.customer_ledger_id DESC
             LIMIT 150",
            'ii',
            [$this->businessId, $customerId]
        );
    }

    public function bills(int $customerId): array
    {
        return $this->queryRows(
            "SELECT b.bill_id, b.bill_no, b.order_no, b.bill_date, b.bill_time, b.net_amount, b.paid_amount, b.balance_amount,
                    b.payment_status, b.bill_status, b.created_at, br.branch_name, br.floor_name
             FROM bills b
             LEFT JOIN branches br
                ON br.branch_id = b.branch_id
               AND br.business_id = b.business_id
             WHERE b.business_id = ?
               AND b.customer_id = ?
               AND COALESCE(b.bill_status, 'active') NOT IN ('cancelled', 'deleted', 'returned', 'return')
             ORDER BY b.created_at DESC, b.bill_id DESC
             LIMIT 100",
            'ii',
            [$this->businessId, $customerId]
        );
    }

    public function purchasedArticles(int $customerId): array
    {
        $sql = "
            SELECT
                b.bill_id,
                b.bill_no,
                b.order_no,
                b.bill_date,
                b.bill_time,
                b.created_at AS bill_created_at,
                b.payment_status,
                b.bill_status,
                br.branch_name,
                br.floor_name,
                bi.bill_item_id,
                bi.stock_batch_id,
                bi.stock_item_id,
                bi.barcode_id,
                bi.article_no,
                COALESCE(bi.article_name, sii.article_name, '') AS article_name,
                COALESCE(brd.brand_name, '') AS brand_name,
                COALESCE(sii.color, '') AS color,
                bi.size,
                bi.qty AS purchased_qty,
                bi.mrp_rate,
                bi.discount_amount,
                bi.selling_rate,
                bi.amount,
                COALESCE(sii.qty, 0) AS original_stock_qty,
                COALESCE(sii.available_qty, 0) AS current_available_stock,
                GREATEST(COALESCE(sii.qty, 0) - COALESCE(sii.available_qty, 0), 0) AS sold_from_stock_qty,
                COALESCE(sii.available_qty, 0) AS remaining_stock_after_previous_sales,
                COALESCE(sb.barcode_value, sb2.barcode_value, '') AS qr_code,
                COALESCE(sb.barcode_status, sb2.barcode_status, '') AS qr_status
            FROM bills b
            INNER JOIN bill_items bi
                ON bi.bill_id = b.bill_id
               AND bi.business_id = b.business_id
            LEFT JOIN branches br
                ON br.branch_id = b.branch_id
               AND br.business_id = b.business_id
            LEFT JOIN stock_inward_items sii
                ON sii.stock_item_id = bi.stock_item_id
               AND sii.business_id = bi.business_id
            LEFT JOIN brands brd
                ON brd.brand_id = COALESCE(bi.brand_id, sii.brand_id)
               AND brd.business_id = bi.business_id
            LEFT JOIN stock_barcodes sb
                ON sb.barcode_id = bi.barcode_id
               AND sb.business_id = bi.business_id
            LEFT JOIN (
                SELECT business_id, stock_item_id, MIN(barcode_value) AS barcode_value, MIN(barcode_status) AS barcode_status
                FROM stock_barcodes
                WHERE barcode_status <> 'deleted'
                GROUP BY business_id, stock_item_id
            ) sb2 ON sb2.business_id = bi.business_id AND sb2.stock_item_id = bi.stock_item_id
            WHERE b.business_id = ?
              AND b.customer_id = ?
              AND b.bill_status = 'active'
            ORDER BY b.created_at DESC, b.bill_id DESC, bi.bill_item_id DESC
            LIMIT 300
        ";
        return $this->queryRows($sql, 'ii', [$this->businessId, $customerId]);
    }

    public function save(array $input): array
    {
        $customerId = (int)($input['customer_id'] ?? 0);
        $name = trim((string)($input['customer_name'] ?? ''));
        $mobile = $this->cleanMobile((string)($input['mobile'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $gstin = strtoupper(preg_replace('/[^0-9A-Z]/', '', (string)($input['gstin'] ?? '')));
        $address = trim((string)($input['address'] ?? ''));
        $openingOutstanding = $this->toFloat($input['opening_outstanding'] ?? 0);
        $loyaltyPoints = $this->toFloat($input['loyalty_points'] ?? 0);
        $status = (int)($input['status'] ?? 1) === 1 ? 1 : 0;

        if ($name === '') {
            return ['success' => false, 'message' => 'Customer name is required.'];
        }
        if ($mobile !== '' && !preg_match('/^[6-9][0-9]{9}$/', $mobile)) {
            return ['success' => false, 'message' => 'Mobile number must be 10 digits and start with 6, 7, 8 or 9.'];
        }
        if ($openingOutstanding < 0 || $loyaltyPoints < 0) {
            return ['success' => false, 'message' => 'Opening outstanding and loyalty points cannot be negative.'];
        }

        mysqli_begin_transaction($this->conn);
        try {
            if ($customerId > 0) {
                $existing = $this->get($customerId);
                if (!$existing) {
                    throw new Exception('Customer not found.');
                }
                $this->execute(
                    "UPDATE customers
                     SET customer_name = ?, mobile = ?, email = ?, address = ?, gstin = ?, opening_outstanding = ?, loyalty_points = ?, status = ?
                     WHERE customer_id = ? AND business_id = ?",
                    'sssssddiii',
                    [$name, $mobile, $email, $address, $gstin, $openingOutstanding, $loyaltyPoints, $status, $customerId, $this->businessId]
                );
                $this->log('customer_updated', $customerId, $existing, ['customer_name' => $name, 'mobile' => $mobile, 'status' => $status]);
                mysqli_commit($this->conn);
                return ['success' => true, 'message' => 'Customer updated successfully.', 'customer_id' => $customerId];
            }

            $this->execute(
                "INSERT INTO customers (business_id, customer_name, mobile, email, address, gstin, opening_outstanding, loyalty_points, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'isssssddi',
                [$this->businessId, $name, $mobile, $email, $address, $gstin, $openingOutstanding, $loyaltyPoints, $status]
            );
            $customerId = (int)mysqli_insert_id($this->conn);

            $existingOutstanding = $this->queryOne(
                "SELECT id FROM customer_outstanding WHERE business_id = ? AND customer_id = ? LIMIT 1",
                'ii',
                [$this->businessId, $customerId]
            );
            if ($existingOutstanding) {
                $this->execute(
                    "UPDATE customer_outstanding SET total_bill_amount = ?, total_paid_amount = 0.00, balance_amount = ?, updated_at = NOW() WHERE id = ? AND business_id = ?",
                    'ddii',
                    [$openingOutstanding, $openingOutstanding, (int)$existingOutstanding['id'], $this->businessId]
                );
            } else {
                $this->execute(
                    "INSERT INTO customer_outstanding (business_id, customer_id, total_bill_amount, total_paid_amount, balance_amount, updated_at) VALUES (?, ?, ?, 0.00, ?, NOW())",
                    'iidd',
                    [$this->businessId, $customerId, $openingOutstanding, $openingOutstanding]
                );
            }

            if ($openingOutstanding > 0) {
                $branchId = $this->currentBranchId();
                $this->execute(
                    "INSERT INTO customer_ledger (business_id, branch_id, customer_id, reference_type, reference_id, debit, credit, balance, remarks, created_by)
                     VALUES (?, ?, ?, 'opening', ?, ?, 0.00, ?, 'Opening outstanding added', ?)",
                    'iiiiddi',
                    [$this->businessId, $branchId, $customerId, $customerId, $openingOutstanding, $openingOutstanding, $this->userId]
                );
            }

            $this->log('customer_created', $customerId, null, ['customer_name' => $name, 'mobile' => $mobile, 'opening_outstanding' => $openingOutstanding]);
            mysqli_commit($this->conn);
            return ['success' => true, 'message' => 'Customer saved successfully.', 'customer_id' => $customerId];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggleStatus(int $customerId): array
    {
        $customer = $this->get($customerId);
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found.'];
        }
        $newStatus = (int)$customer['status'] === 1 ? 0 : 1;
        $this->execute(
            "UPDATE customers SET status = ? WHERE customer_id = ? AND business_id = ?",
            'iii',
            [$newStatus, $customerId, $this->businessId]
        );
        $this->log('customer_status_changed', $customerId, ['status' => (int)$customer['status']], ['status' => $newStatus]);
        return ['success' => true, 'message' => 'Customer status updated successfully.'];
    }

    private function pendingBillSummary(int $customerId): array
    {
        $row = $this->queryOne(
            "SELECT
                 COUNT(*) AS pending_bill_count,
                 COALESCE(SUM(CASE WHEN COALESCE(balance_amount, 0) > 0 THEN balance_amount ELSE 0 END), 0) AS pending_bill_amount
             FROM bills
             WHERE business_id = ?
               AND customer_id = ?
               AND COALESCE(bill_status, 'active') NOT IN ('cancelled', 'deleted', 'returned', 'return')
               AND (
                    COALESCE(balance_amount, 0) > 0.0001
                    OR LOWER(COALESCE(payment_status, '')) IN ('pending', 'partial', 'partially_paid')
               )",
            'ii',
            [$this->businessId, $customerId]
        ) ?: [];

        return [
            'pending_bill_count' => (int)($row['pending_bill_count'] ?? 0),
            'pending_bill_amount' => $this->toFloat($row['pending_bill_amount'] ?? 0),
        ];
    }

    private function pendingBalance(int $customerId, array $customer): float
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS row_count, COALESCE(MAX(balance_amount), 0) AS balance_amount
             FROM customer_outstanding
             WHERE business_id = ?
               AND customer_id = ?",
            'ii',
            [$this->businessId, $customerId]
        ) ?: [];

        $outstanding = $this->toFloat($row['balance_amount'] ?? 0);
        $opening = $this->toFloat($customer['opening_outstanding'] ?? 0);

        return ((int)($row['row_count'] ?? 0) > 0) ? $outstanding : $opening;
    }

    public function delete(int $customerId): array
    {
        $customer = $this->get($customerId);
        if (!$customer) {
            return ['success' => false, 'message' => 'Customer not found.'];
        }

        if ((int)($customer['status'] ?? 1) === 1) {
            return ['success' => false, 'message' => 'Please mark this customer as Inactive before deleting permanently.'];
        }

        $pendingBills = $this->pendingBillSummary($customerId);
        if ($pendingBills['pending_bill_count'] > 0 || $pendingBills['pending_bill_amount'] > 0.0001) {
            return ['success' => false, 'message' => 'This customer cannot be deleted because there are pending bills.'];
        }

        $pendingBalance = $this->pendingBalance($customerId, $customer);
        if ($pendingBalance > 0.0001) {
            return ['success' => false, 'message' => 'This customer cannot be deleted because there is a pending balance.'];
        }

        mysqli_begin_transaction($this->conn);
        try {
            $this->execute("DELETE FROM customer_ledger WHERE business_id = ? AND customer_id = ?", 'ii', [$this->businessId, $customerId]);
            $this->execute("DELETE FROM customer_outstanding WHERE business_id = ? AND customer_id = ?", 'ii', [$this->businessId, $customerId]);
            $this->execute("DELETE FROM customers WHERE business_id = ? AND customer_id = ?", 'ii', [$this->businessId, $customerId]);
            $this->log('customer_deleted', $customerId, $customer, null);
            mysqli_commit($this->conn);
            return ['success' => true, 'message' => 'Customer deleted successfully.'];
        } catch (Exception $e) {
            mysqli_rollback($this->conn);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function log(string $action, int $recordId, $oldValue, $newValue): void
    {
        if (!$this->tableExists('activity_logs')) {
            return;
        }
        $branchId = $this->currentBranchId();
        $roleId = (int)($_SESSION['role_id'] ?? 0);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $device = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $oldJson = $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE);
        $newJson = $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE);
        try {
            $this->execute(
                "INSERT INTO activity_logs (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details)
                 VALUES (?, ?, ?, ?, 'Customers', ?, ?, ?, ?, ?, ?)",
                'iiiisissss',
                [$this->businessId, $branchId, $this->userId, $roleId, $action, $recordId, $oldJson, $newJson, $ip, $device]
            );
        } catch (Exception $e) {
            // Logging must never stop customer operations.
        }
    }
}
