<?php
/**
 * StockInward Model
 * Uses existing footwear schema and safe dynamic column checks for invoice fields.
 */
class StockInward
{
    private $conn;
    private $businessId;

    public function __construct(mysqli $conn, int $businessId)
    {
        $this->conn = $conn;
        $this->businessId = $businessId;
    }

    private function bind(mysqli_stmt $stmt, string $types, array $params): void
    {
        $bind = [$types];
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    public function tableExists(string $table): bool
    {
        if (function_exists('table_exists')) {
            return table_exists($this->conn, $table);
        }
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
        ");
        mysqli_stmt_bind_param($stmt, 's', $table);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }

    public function columnExists(string $table, string $column): bool
    {
        if (function_exists('table_has_column')) {
            return table_has_column($this->conn, $table, $column);
        }
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) AS total
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }

    public function currentUserId(): ?int
    {
        if (function_exists('current_user_id')) {
            $id = (int)current_user_id();
            return $id > 0 ? $id : null;
        }
        $id = (int)($_SESSION['user_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public function currentRoleId(): ?int
    {
        if (function_exists('current_role_id')) {
            $id = (int)current_role_id();
            return $id > 0 ? $id : null;
        }
        $id = (int)($_SESSION['role_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public function currentBranchId(): ?int
    {
        if (function_exists('current_branch_id')) {
            $id = (int)current_branch_id();
            return $id > 0 ? $id : null;
        }
        $id = (int)($_SESSION['branch_id'] ?? ($_SESSION['default_branch_id'] ?? 0));
        return $id > 0 ? $id : null;
    }

    public function getMasters(): array
    {
        return [
            'branches' => $this->getBranches(),
            'suppliers' => $this->getSuppliers(),
            'categories' => $this->getCategories(),
            'brands' => $this->getBrands(),
            'discount_types' => [
                ['value' => 'none', 'label' => 'No Discount'],
                ['value' => 'percent', 'label' => 'Percent'],
                ['value' => 'amount', 'label' => 'Amount'],
            ],
            'today' => (new DateTimeImmutable('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d'),
        ];
    }

    public function getBranches(): array
    {
        $userId = $this->currentUserId();
        $branches = [];

        if ($userId && $this->tableExists('user_branch_access')) {
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
                ORDER BY b.branch_name ASC
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $userId, $this->businessId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($result)) {
                $branches[] = $row;
            }
            mysqli_stmt_close($stmt);
        }

        if ($branches) {
            return $branches;
        }

        $stmt = mysqli_prepare($this->conn, "
            SELECT branch_id, branch_code, branch_name, floor_name
            FROM branches
            WHERE business_id = ?
              AND status = 1
            ORDER BY branch_name ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $this->businessId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $branches[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $branches;
    }

    public function getSuppliers(): array
    {
        $data = [];
        $stmt = mysqli_prepare($this->conn, "
            SELECT supplier_id, supplier_name, mobile, gstin
            FROM suppliers
            WHERE business_id = ?
              AND status = 1
            ORDER BY supplier_name ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $this->businessId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $data;
    }

    public function getCategories(): array
    {
        $data = [];
        $stmt = mysqli_prepare($this->conn, "
            SELECT category_id, category_name
            FROM categories
            WHERE business_id = ?
              AND status = 1
            ORDER BY category_name ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $this->businessId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $data;
    }

    public function getBrands(): array
    {
        $data = [];
        $stmt = mysqli_prepare($this->conn, "
            SELECT brand_id, brand_name
            FROM brands
            WHERE business_id = ?
              AND status = 1
            ORDER BY brand_name ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $this->businessId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $data;
    }

    public function masterExists(string $table, string $idColumn, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $sql = "SELECT COUNT(*) AS total FROM {$table} WHERE business_id = ? AND {$idColumn} = ?";
        if (in_array($table, ['branches', 'suppliers', 'categories', 'brands'], true)) {
            $sql .= " AND status = 1";
        }

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }

    private function buildWhere(array $filters): array
    {
        $where = "WHERE b.business_id = ?";
        $types = 'i';
        $params = [$this->businessId];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where .= " AND (b.batch_no LIKE ? OR s.supplier_name LIKE ? OR br.branch_name LIKE ? OR i.article_no LIKE ? OR i.article_name LIKE ? OR c.category_name LIKE ? OR bd.brand_name LIKE ?)";
            $like = '%' . $search . '%';
            for ($x = 0; $x < 7; $x++) {
                $params[] = $like;
            }
            $types .= 'sssssss';
        }

        foreach (['branch_id' => 'b.branch_id', 'supplier_id' => 'b.supplier_id', 'brand_id' => 'i.brand_id', 'category_id' => 'i.category_id'] as $key => $column) {
            $value = (int)($filters[$key] ?? 0);
            if ($value > 0) {
                $where .= " AND {$column} = ?";
                $params[] = $value;
                $types .= 'i';
            }
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['active', 'cancelled', 'deleted'], true)) {
            $where .= " AND b.batch_status = ?";
            $params[] = $status;
            $types .= 's';
        }

        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where .= " AND b.inward_date >= ?";
            $params[] = $dateFrom;
            $types .= 's';
        }

        $dateTo = trim((string)($filters['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where .= " AND b.inward_date <= ?";
            $params[] = $dateTo;
            $types .= 's';
        }

        return [$where, $types, $params];
    }

    public function listBatches(array $filters): array
    {
        [$where, $types, $params] = $this->buildWhere($filters);
        $invoiceNoSelect = $this->columnExists('stock_inward_batches', 'invoice_number') ? 'b.invoice_number' : "NULL AS invoice_number";
        $invoiceDateSelect = $this->columnExists('stock_inward_batches', 'invoice_date') ? 'b.invoice_date' : "NULL AS invoice_date";

        $sql = "
            SELECT
                b.batch_id,
                b.business_id,
                b.branch_id,
                br.branch_name,
                br.floor_name,
                b.batch_no,
                b.inward_date,
                b.inward_time,
                {$invoiceNoSelect},
                {$invoiceDateSelect},
                b.supplier_id,
                s.supplier_name,
                b.total_qty,
                b.purchase_total_value,
                b.mrp_total_value,
                b.selling_total_value,
                b.remarks,
                b.batch_status,
                b.created_by,
                COALESCE(u.name, 'System') AS created_by_name,
                b.created_at,
                COUNT(DISTINCT i.stock_item_id) AS item_count
            FROM stock_inward_batches b
            LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
            LEFT JOIN suppliers s ON s.supplier_id = b.supplier_id AND s.business_id = b.business_id
            LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id
            LEFT JOIN stock_inward_items i ON i.batch_id = b.batch_id AND i.business_id = b.business_id
            LEFT JOIN categories c ON c.category_id = i.category_id AND c.business_id = i.business_id
            LEFT JOIN brands bd ON bd.brand_id = i.brand_id AND bd.business_id = i.business_id
            {$where}
            GROUP BY b.batch_id
            ORDER BY b.batch_id DESC
            LIMIT 300
        ";

        $stmt = mysqli_prepare($this->conn, $sql);
        $this->bind($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['batch_id'] = (int)$row['batch_id'];
            $row['branch_id'] = (int)$row['branch_id'];
            $row['supplier_id'] = $row['supplier_id'] !== null ? (int)$row['supplier_id'] : null;
            $row['total_qty'] = (float)$row['total_qty'];
            $row['purchase_total_value'] = (float)$row['purchase_total_value'];
            $row['mrp_total_value'] = (float)$row['mrp_total_value'];
            $row['selling_total_value'] = (float)$row['selling_total_value'];
            $row['item_count'] = (int)$row['item_count'];
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    public function stats(array $filters = []): array
    {
        [$where, $types, $params] = $this->buildWhere($filters);
        $sql = "
            SELECT
                COUNT(DISTINCT b.batch_id) AS total_batches,
                COUNT(DISTINCT CASE WHEN b.batch_status = 'active' THEN b.batch_id END) AS active_batches,
                COUNT(DISTINCT CASE WHEN b.batch_status = 'active' THEN i.stock_item_id END) AS total_items,
                COALESCE(SUM(CASE WHEN b.batch_status = 'active' THEN i.qty ELSE 0 END), 0) AS total_qty,
                COALESCE(SUM(CASE WHEN b.batch_status = 'active' THEN i.line_purchase_value ELSE 0 END), 0) AS purchase_total,
                COALESCE(SUM(CASE WHEN b.batch_status = 'active' THEN i.line_selling_value ELSE 0 END), 0) AS selling_total
            FROM stock_inward_batches b
            LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
            LEFT JOIN suppliers s ON s.supplier_id = b.supplier_id AND s.business_id = b.business_id
            LEFT JOIN stock_inward_items i ON i.batch_id = b.batch_id AND i.business_id = b.business_id
            LEFT JOIN categories c ON c.category_id = i.category_id AND c.business_id = i.business_id
            LEFT JOIN brands bd ON bd.brand_id = i.brand_id AND bd.business_id = i.business_id
            {$where}
        ";
        $stmt = mysqli_prepare($this->conn, $sql);
        $this->bind($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return [
            'total_batches' => (int)($row['total_batches'] ?? 0),
            'active_batches' => (int)($row['active_batches'] ?? 0),
            'total_items' => (int)($row['total_items'] ?? 0),
            'total_qty' => (float)($row['total_qty'] ?? 0),
            'purchase_total' => (float)($row['purchase_total'] ?? 0),
            'selling_total' => (float)($row['selling_total'] ?? 0),
        ];
    }

    public function getBatch(int $batchId): ?array
    {
        $invoiceNoSelect = $this->columnExists('stock_inward_batches', 'invoice_number') ? 'b.invoice_number' : "NULL AS invoice_number";
        $invoiceDateSelect = $this->columnExists('stock_inward_batches', 'invoice_date') ? 'b.invoice_date' : "NULL AS invoice_date";
        $stmt = mysqli_prepare($this->conn, "
            SELECT b.*, {$invoiceNoSelect}, {$invoiceDateSelect}, br.branch_name, br.floor_name,
                   s.supplier_name, s.mobile AS supplier_mobile, s.gstin AS supplier_gstin,
                   COALESCE(u.name, 'System') AS created_by_name
            FROM stock_inward_batches b
            LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
            LEFT JOIN suppliers s ON s.supplier_id = b.supplier_id AND s.business_id = b.business_id
            LEFT JOIN users u ON u.user_id = b.created_by AND u.business_id = b.business_id
            WHERE b.business_id = ?
              AND b.batch_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $batchId);
        mysqli_stmt_execute($stmt);
        $batch = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$batch) {
            return null;
        }

        $stmt = mysqli_prepare($this->conn, "
            SELECT i.*, c.category_name, bd.brand_name, sb.barcode_id, sb.barcode_value, sb.barcode_status
            FROM stock_inward_items i
            LEFT JOIN categories c ON c.category_id = i.category_id AND c.business_id = i.business_id
            LEFT JOIN brands bd ON bd.brand_id = i.brand_id AND bd.business_id = i.business_id
            LEFT JOIN stock_barcodes sb ON sb.stock_item_id = i.stock_item_id AND sb.barcode_status <> 'deleted'
            WHERE i.business_id = ?
              AND i.batch_id = ?
            ORDER BY i.stock_item_id ASC
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $batchId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            foreach (['stock_item_id', 'category_id', 'brand_id', 'barcode_id'] as $key) {
                $row[$key] = $row[$key] !== null ? (int)$row[$key] : null;
            }
            foreach (['qty', 'available_qty', 'purchase_rate', 'mrp_rate', 'product_discount_value', 'discount_amount', 'selling_rate', 'line_purchase_value', 'line_mrp_value', 'line_selling_value'] as $key) {
                $row[$key] = (float)($row[$key] ?? 0);
            }
            $row['barcode_required'] = (int)$row['barcode_required'];
            $items[] = $row;
        }
        mysqli_stmt_close($stmt);

        $batch['batch_id'] = (int)$batch['batch_id'];
        $batch['branch_id'] = (int)$batch['branch_id'];
        $batch['supplier_id'] = $batch['supplier_id'] !== null ? (int)$batch['supplier_id'] : null;
        foreach (['total_qty', 'purchase_total_value', 'mrp_total_value', 'selling_total_value'] as $key) {
            $batch[$key] = (float)($batch[$key] ?? 0);
        }
        $batch['items'] = $items;
        return $batch;
    }

    public function batchHasUsedStock(int $batchId): bool
    {
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) AS total
            FROM stock_inward_items
            WHERE business_id = ?
              AND batch_id = ?
              AND available_qty < qty
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $batchId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return ((int)($row['total'] ?? 0)) > 0;
    }

    public function calculateItem(array $input): array
    {
        $qty = round((float)$input['qty'], 2);
        $purchaseRate = round((float)$input['purchase_rate'], 2);
        $mrpRate = round((float)$input['mrp_rate'], 2);
        $discountType = (string)$input['product_discount_type'];
        $discountValue = round((float)$input['product_discount_value'], 2);
        $discountAmount = 0.00;

        if ($discountType === 'percent') {
            $discountAmount = round(($mrpRate * $discountValue) / 100, 2);
        } elseif ($discountType === 'amount') {
            $discountAmount = $discountValue;
        } else {
            $discountValue = 0.00;
        }

        $sellingRate = max(0, round($mrpRate - $discountAmount, 2));
        return array_merge($input, [
            'qty' => $qty,
            'purchase_rate' => $purchaseRate,
            'mrp_rate' => $mrpRate,
            'product_discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'selling_rate' => $sellingRate,
            'line_purchase_value' => round($qty * $purchaseRate, 2),
            'line_mrp_value' => round($qty * $mrpRate, 2),
            'line_selling_value' => round($qty * $sellingRate, 2),
        ]);
    }

    private function calculateTotals(array $items): array
    {
        $totalQty = 0.00;
        $purchaseTotal = 0.00;
        $mrpTotal = 0.00;
        $sellingTotal = 0.00;
        foreach ($items as $item) {
            $totalQty += (float)$item['qty'];
            $purchaseTotal += (float)$item['line_purchase_value'];
            $mrpTotal += (float)$item['line_mrp_value'];
            $sellingTotal += (float)$item['line_selling_value'];
        }
        return [
            'total_qty' => round($totalQty, 2),
            'purchase_total' => round($purchaseTotal, 2),
            'mrp_total' => round($mrpTotal, 2),
            'selling_total' => round($sellingTotal, 2),
        ];
    }


    private function nextBatchNo(int $branchId): string
    {
        $prefix = 'BATCH';
        $pad = 4;
        $like = $prefix . '-%';

        $stmt = mysqli_prepare($this->conn, "
            SELECT COALESCE(MAX(CAST(REPLACE(batch_no, 'BATCH-', '') AS UNSIGNED)), 0) + 1 AS next_no
            FROM stock_inward_batches
            WHERE business_id = ?
              AND branch_id = ?
              AND batch_no LIKE ?
        ");
        mysqli_stmt_bind_param($stmt, 'iis', $this->businessId, $branchId, $like);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        $nextNo = max(1, (int)($row['next_no'] ?? 1));

        return $prefix . '-' . str_pad((string)$nextNo, $pad, '0', STR_PAD_LEFT);
    }

    private function nextSequence(string $sequenceKey, string $prefix, int $pad): string
    {
        if ($this->tableExists('number_sequences')) {
            $stmt = mysqli_prepare($this->conn, "
                SELECT sequence_id, current_number, padding_length, prefix
                FROM number_sequences
                WHERE business_id = ?
                  AND sequence_key = ?
                ORDER BY sequence_id ASC
                LIMIT 1
                FOR UPDATE
            ");
            mysqli_stmt_bind_param($stmt, 'is', $this->businessId, $sequenceKey);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            if ($row) {
                $next = ((int)$row['current_number']) + 1;
                $sequenceId = (int)$row['sequence_id'];
                $usePrefix = $row['prefix'] ?: $prefix;
                $usePad = (int)($row['padding_length'] ?: $pad);
                $stmt = mysqli_prepare($this->conn, "UPDATE number_sequences SET current_number = ?, updated_at = NOW() WHERE sequence_id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $next, $sequenceId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return $usePrefix . '-' . str_pad((string)$next, $usePad, '0', STR_PAD_LEFT);
            }

            $one = 1;
            $stmt = mysqli_prepare($this->conn, "
                INSERT INTO number_sequences
                    (business_id, branch_id, sequence_key, prefix, current_number, padding_length, status, updated_at)
                VALUES
                    (?, NULL, ?, ?, ?, ?, 1, NOW())
            ");
            mysqli_stmt_bind_param($stmt, 'issii', $this->businessId, $sequenceKey, $prefix, $one, $pad);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $prefix . '-' . str_pad('1', $pad, '0', STR_PAD_LEFT);
        }

        $like = $prefix . '-%';
        $stmt = mysqli_prepare($this->conn, "
            SELECT COUNT(*) + 1 AS next_no
            FROM stock_inward_batches
            WHERE business_id = ?
              AND batch_no LIKE ?
        ");
        mysqli_stmt_bind_param($stmt, 'is', $this->businessId, $like);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $prefix . '-' . str_pad((string)((int)($row['next_no'] ?? 1)), $pad, '0', STR_PAD_LEFT);
    }

    public function createBatch(array $data, array $items): array
    {
        mysqli_begin_transaction($this->conn);
        try {
            $createdBy = $this->currentUserId();
            $batchNo = $this->nextBatchNo((int)$data['branch_id']);
            $inwardTime = date('H:i:s');
            $totals = $this->calculateTotals($items);
            $hasInvoiceNo = $this->columnExists('stock_inward_batches', 'invoice_number');
            $hasInvoiceDate = $this->columnExists('stock_inward_batches', 'invoice_date');

            $columns = ['business_id', 'branch_id', 'batch_no', 'inward_date', 'inward_time', 'supplier_id'];
            $values = ['?', '?', '?', '?', '?', '?'];
            $types = 'iisssi';
            $params = [$this->businessId, $data['branch_id'], $batchNo, $data['inward_date'], $inwardTime, $data['supplier_id']];
            if ($hasInvoiceNo) {
                $columns[] = 'invoice_number';
                $values[] = '?';
                $types .= 's';
                $params[] = $data['invoice_number'];
            }
            if ($hasInvoiceDate) {
                $columns[] = 'invoice_date';
                $values[] = '?';
                $types .= 's';
                $params[] = $data['invoice_date'];
            }
            $columns = array_merge($columns, ['total_qty', 'purchase_total_value', 'mrp_total_value', 'selling_total_value', 'remarks', 'batch_status', 'created_by', 'created_at']);
            $values = array_merge($values, ['?', '?', '?', '?', '?', "'active'", '?', 'NOW()']);
            $types .= 'ddddsi';
            $params[] = $totals['total_qty'];
            $params[] = $totals['purchase_total'];
            $params[] = $totals['mrp_total'];
            $params[] = $totals['selling_total'];
            $params[] = $data['remarks'];
            $params[] = $createdBy;

            $sql = 'INSERT INTO stock_inward_batches (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ')';
            $stmt = mysqli_prepare($this->conn, $sql);
            $this->bind($stmt, $types, $params);
            mysqli_stmt_execute($stmt);
            $batchId = (int)mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);

            $this->insertItemRows($batchId, $data, $items);
            $this->syncVendorOutstanding((int)$data['supplier_id'], (int)$data['branch_id'], $totals['purchase_total'], 'stock_inward', $batchId, 'Stock inward purchase posted: ' . $batchNo);
            $this->activityLog('create', $batchId, null, ['batch_no' => $batchNo, 'totals' => $totals, 'items' => $items]);
            mysqli_commit($this->conn);
            return ['batch_id' => $batchId, 'batch_no' => $batchNo];
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    public function updateBatch(int $batchId, array $data, array $items): array
    {
        $oldBatch = $this->getBatch($batchId);
        if (!$oldBatch) {
            throw new RuntimeException('Stock inward batch not found.');
        }
        if ($oldBatch['batch_status'] !== 'active') {
            throw new RuntimeException('Only active stock inward batch can be edited.');
        }
        if ($this->batchHasUsedStock($batchId)) {
            throw new RuntimeException('This stock is already used in billing. Edit is blocked.');
        }

        mysqli_begin_transaction($this->conn);
        try {
            $totals = $this->calculateTotals($items);
            $inwardTime = date('H:i:s');
            $hasInvoiceNo = $this->columnExists('stock_inward_batches', 'invoice_number');
            $hasInvoiceDate = $this->columnExists('stock_inward_batches', 'invoice_date');
            $oldPurchase = (float)$oldBatch['purchase_total_value'];

            $this->deleteBatchChildren($batchId);

            $sets = ['branch_id = ?', 'inward_date = ?', 'inward_time = ?', 'supplier_id = ?'];
            $types = 'issi';
            $params = [$data['branch_id'], $data['inward_date'], $inwardTime, $data['supplier_id']];
            if ($hasInvoiceNo) {
                $sets[] = 'invoice_number = ?';
                $types .= 's';
                $params[] = $data['invoice_number'];
            }
            if ($hasInvoiceDate) {
                $sets[] = 'invoice_date = ?';
                $types .= 's';
                $params[] = $data['invoice_date'];
            }
            $sets = array_merge($sets, ['total_qty = ?', 'purchase_total_value = ?', 'mrp_total_value = ?', 'selling_total_value = ?', 'remarks = ?', 'updated_at = NOW()']);
            $types .= 'ddddsii';
            $params[] = $totals['total_qty'];
            $params[] = $totals['purchase_total'];
            $params[] = $totals['mrp_total'];
            $params[] = $totals['selling_total'];
            $params[] = $data['remarks'];
            $params[] = $this->businessId;
            $params[] = $batchId;

            $stmt = mysqli_prepare($this->conn, 'UPDATE stock_inward_batches SET ' . implode(', ', $sets) . ' WHERE business_id = ? AND batch_id = ?');
            $this->bind($stmt, $types, $params);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $this->insertItemRows($batchId, $data, $items);
            if ((int)$oldBatch['supplier_id'] > 0) {
                $this->syncVendorOutstanding((int)$oldBatch['supplier_id'], (int)$oldBatch['branch_id'], -$oldPurchase, 'stock_inward_update_reverse', $batchId, 'Stock inward purchase reversed before update: ' . $oldBatch['batch_no']);
            }
            $this->syncVendorOutstanding((int)$data['supplier_id'], (int)$data['branch_id'], $totals['purchase_total'], 'stock_inward_update', $batchId, 'Stock inward purchase posted after update: ' . $oldBatch['batch_no']);
            $this->activityLog('update', $batchId, $oldBatch, ['batch' => $data, 'totals' => $totals, 'items' => $items]);
            mysqli_commit($this->conn);
            return ['batch_id' => $batchId, 'batch_no' => $oldBatch['batch_no']];
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    private function deleteBatchChildren(int $batchId): void
    {
        if ($this->tableExists('stock_barcodes')) {
            $stmt = mysqli_prepare($this->conn, "DELETE FROM stock_barcodes WHERE business_id = ? AND batch_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $batchId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        if ($this->tableExists('stock_movements')) {
            $stmt = mysqli_prepare($this->conn, "DELETE FROM stock_movements WHERE business_id = ? AND reference_type IN ('stock_inward','stock_inward_update') AND reference_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $batchId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $stmt = mysqli_prepare($this->conn, "DELETE FROM stock_inward_items WHERE business_id = ? AND batch_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $batchId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    private function insertItemRows(int $batchId, array $batchData, array $items): void
    {
        $createdBy = $this->currentUserId();
        foreach ($items as $item) {
            $stmt = mysqli_prepare($this->conn, "
                INSERT INTO stock_inward_items
                    (business_id, branch_id, batch_id, category_id, brand_id, article_no, article_name, size, color,
                     qty, available_qty, purchase_rate, mrp_rate, product_discount_type, product_discount_value,
                     discount_amount, selling_rate, line_purchase_value, line_mrp_value, line_selling_value,
                     barcode_required, item_status, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            mysqli_stmt_bind_param(
                $stmt,
                'iiiiissssddddsddddddi',
                $this->businessId,
                $batchData['branch_id'],
                $batchId,
                $item['category_id'],
                $item['brand_id'],
                $item['article_no'],
                $item['article_name'],
                $item['size'],
                $item['color'],
                $item['qty'],
                $item['qty'],
                $item['purchase_rate'],
                $item['mrp_rate'],
                $item['product_discount_type'],
                $item['product_discount_value'],
                $item['discount_amount'],
                $item['selling_rate'],
                $item['line_purchase_value'],
                $item['line_mrp_value'],
                $item['line_selling_value'],
                $item['barcode_required']
            );
            mysqli_stmt_execute($stmt);
            $stockItemId = (int)mysqli_insert_id($this->conn);
            mysqli_stmt_close($stmt);

            if ((int)$item['barcode_required'] === 1 && $this->tableExists('stock_barcodes')) {
                $barcode = $this->nextSequence('stock_barcode', 'STK', 6);
                $stmt = mysqli_prepare($this->conn, "
                    INSERT INTO stock_barcodes
                        (business_id, branch_id, batch_id, stock_item_id, barcode_value, barcode_status, created_at)
                    VALUES
                        (?, ?, ?, ?, ?, 'active', NOW())
                ");
                mysqli_stmt_bind_param($stmt, 'iiiis', $this->businessId, $batchData['branch_id'], $batchId, $stockItemId, $barcode);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            if ($this->tableExists('stock_movements')) {
                $referenceType = 'stock_inward';
                $remarks = 'Stock inward batch';
                $zero = 0.00;
                $stmt = mysqli_prepare($this->conn, "
                    INSERT INTO stock_movements
                        (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id,
                         qty_in, qty_out, balance_qty, remarks, created_by, created_at)
                    VALUES
                        (?, ?, ?, 'inward', ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                mysqli_stmt_bind_param(
                    $stmt,
                    'iiisidddsi',
                    $this->businessId,
                    $batchData['branch_id'],
                    $stockItemId,
                    $referenceType,
                    $batchId,
                    $item['qty'],
                    $zero,
                    $item['qty'],
                    $remarks,
                    $createdBy
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }
        }
    }

    public function changeBatchStatus(int $batchId, string $newStatus): array
    {
        if (!in_array($newStatus, ['cancelled', 'deleted'], true)) {
            throw new RuntimeException('Invalid stock inward status.');
        }
        $oldBatch = $this->getBatch($batchId);
        if (!$oldBatch) {
            throw new RuntimeException('Stock inward batch not found.');
        }
        if ($oldBatch['batch_status'] !== 'active') {
            throw new RuntimeException('Only active stock inward batch can be changed.');
        }
        if ($this->batchHasUsedStock($batchId)) {
            throw new RuntimeException('This stock is already used in billing. Delete/cancel is blocked.');
        }

        mysqli_begin_transaction($this->conn);
        try {
            $createdBy = $this->currentUserId();
            foreach ($oldBatch['items'] as $item) {
                $stockItemId = (int)$item['stock_item_id'];
                $qtyOut = (float)$item['available_qty'];
                if ($qtyOut > 0 && $this->tableExists('stock_movements')) {
                    $referenceType = 'stock_inward_' . $newStatus;
                    $remarks = 'Stock inward ' . $newStatus . ': ' . $oldBatch['batch_no'];
                    $zero = 0.00;
                    $stmt = mysqli_prepare($this->conn, "
                        INSERT INTO stock_movements
                            (business_id, branch_id, stock_item_id, movement_type, reference_type, reference_id,
                             qty_in, qty_out, balance_qty, remarks, created_by, created_at)
                        VALUES
                            (?, ?, ?, 'adjustment', ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    mysqli_stmt_bind_param($stmt, 'iiisidddsi', $this->businessId, $oldBatch['branch_id'], $stockItemId, $referenceType, $batchId, $zero, $qtyOut, $zero, $remarks, $createdBy);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }

            $stmt = mysqli_prepare($this->conn, "UPDATE stock_inward_batches SET batch_status = ?, updated_at = NOW() WHERE business_id = ? AND batch_id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $newStatus, $this->businessId, $batchId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($this->conn, "UPDATE stock_inward_items SET item_status = ?, available_qty = 0, updated_at = NOW() WHERE business_id = ? AND batch_id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $newStatus, $this->businessId, $batchId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            if ($this->tableExists('stock_barcodes')) {
                $stmt = mysqli_prepare($this->conn, "UPDATE stock_barcodes SET barcode_status = ? WHERE business_id = ? AND batch_id = ?");
                mysqli_stmt_bind_param($stmt, 'sii', $newStatus, $this->businessId, $batchId);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            if ((int)$oldBatch['supplier_id'] > 0) {
                $this->syncVendorOutstanding((int)$oldBatch['supplier_id'], (int)$oldBatch['branch_id'], -(float)$oldBatch['purchase_total_value'], 'stock_inward_' . $newStatus, $batchId, 'Stock inward purchase reversed: ' . $oldBatch['batch_no']);
            }
            $this->activityLog($newStatus, $batchId, $oldBatch, ['batch_status' => $newStatus]);
            mysqli_commit($this->conn);
            return ['batch_id' => $batchId, 'batch_status' => $newStatus];
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }
    }

    private function syncVendorOutstanding(int $supplierId, int $branchId, float $amountDiff, string $referenceType, int $referenceId, string $remarks): void
    {
        if ($supplierId <= 0 || abs($amountDiff) <= 0.0001 || !$this->tableExists('vendor_outstanding') || !$this->tableExists('vendor_ledger')) {
            return;
        }

        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO vendor_outstanding
                (business_id, supplier_id, total_purchase_amount, total_paid_amount, balance_amount, updated_at)
            VALUES
                (?, ?, 0.00, 0.00, 0.00, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $supplierId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($this->conn, "
            UPDATE vendor_outstanding
            SET total_purchase_amount = GREATEST(total_purchase_amount + ?, 0),
                balance_amount = GREATEST(balance_amount + ?, 0),
                updated_at = NOW()
            WHERE business_id = ?
              AND supplier_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'ddii', $amountDiff, $amountDiff, $this->businessId, $supplierId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($this->conn, "
            SELECT COALESCE(balance_amount, 0) AS balance_amount
            FROM vendor_outstanding
            WHERE business_id = ?
              AND supplier_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $this->businessId, $supplierId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $balance = (float)($row['balance_amount'] ?? 0);

        $debit = $amountDiff > 0 ? $amountDiff : 0.00;
        $credit = $amountDiff < 0 ? abs($amountDiff) : 0.00;
        $createdBy = $this->currentUserId();
        $stmt = mysqli_prepare($this->conn, "
            INSERT INTO vendor_ledger
                (business_id, branch_id, supplier_id, reference_type, reference_id, debit, credit, balance, remarks, created_by, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        mysqli_stmt_bind_param($stmt, 'iiisidddsi', $this->businessId, $branchId, $supplierId, $referenceType, $referenceId, $debit, $credit, $balance, $remarks, $createdBy);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    public function activityLog(string $actionType, int $recordId, $oldValue = null, $newValue = null): void
    {
        $logTable = null;
        if ($this->tableExists('business_activity_logs')) {
            $logTable = 'business_activity_logs';
        } elseif ($this->tableExists('activity_logs')) {
            $logTable = 'activity_logs';
        }
        if ($logTable === null) {
            return;
        }

        $userId = $this->currentUserId();
        $roleId = $this->currentRoleId();
        $branchId = $this->currentBranchId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $deviceDetails = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $oldJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;

        $sql = "
            INSERT INTO {$logTable}
                (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details, created_at)
            VALUES
                (?, ?, ?, ?, 'Stock Inward', ?, ?, ?, ?, ?, ?, NOW())
        ";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'iiiisissss', $this->businessId, $branchId, $userId, $roleId, $actionType, $recordId, $oldJson, $newJson, $ipAddress, $deviceDetails);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
