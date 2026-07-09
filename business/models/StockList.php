<?php

declare(strict_types=1);

class StockList
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

        $bind = [];
        $bind[] = $types;
        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    public function getBranches(int $businessId, int $userId, bool $isAdmin = false): array
    {
        if (!$this->tableExists('user_branch_access') || $isAdmin || $userId <= 0) {
            $stmt = mysqli_prepare($this->conn, "
                SELECT branch_id, branch_code, branch_name, floor_name
                FROM branches
                WHERE business_id = ?
                  AND status = 1
                ORDER BY branch_name ASC
            ");
            mysqli_stmt_bind_param($stmt, 'i', $businessId);
        } else {
            $stmt = mysqli_prepare($this->conn, "
                SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name
                FROM branches b
                INNER JOIN user_branch_access uba
                    ON uba.branch_id = b.branch_id
                   AND uba.business_id = b.business_id
                   AND uba.user_id = ?
                WHERE b.business_id = ?
                  AND b.status = 1
                ORDER BY b.branch_name ASC
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $userId, $businessId);
        }

        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    public function userCanAccessBranch(int $businessId, int $userId, int $branchId, bool $isAdmin = false): bool
    {
        if ($branchId <= 0) {
            return false;
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
        } else {
            $stmt = mysqli_prepare($this->conn, "
                SELECT b.branch_id
                FROM branches b
                INNER JOIN user_branch_access uba
                    ON uba.branch_id = b.branch_id
                   AND uba.business_id = b.business_id
                   AND uba.user_id = ?
                WHERE b.business_id = ?
                  AND b.branch_id = ?
                  AND b.status = 1
                LIMIT 1
            ");
            mysqli_stmt_bind_param($stmt, 'iii', $userId, $businessId, $branchId);
        }

        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (bool)$row;
    }

    public function getCategories(int $businessId): array
    {
        $stmt = mysqli_prepare($this->conn, "
            SELECT category_id, category_name
            FROM categories
            WHERE business_id = ?
              AND status = 1
            ORDER BY category_name ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $businessId);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    public function getBrands(int $businessId): array
    {
        $hasCategoryId = $this->columnExists('brands', 'category_id');
        $join = $hasCategoryId ? "LEFT JOIN categories c ON c.category_id = b.category_id AND c.business_id = b.business_id" : "";
        $categorySelect = $hasCategoryId ? "c.category_name" : "NULL AS category_name";

        $stmt = mysqli_prepare($this->conn, "
            SELECT b.brand_id, b.brand_name, {$categorySelect}
            FROM brands b
            {$join}
            WHERE b.business_id = ?
              AND b.status = 1
            ORDER BY b.brand_name ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $businessId);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    public function getSuppliers(int $businessId): array
    {
        if (!$this->tableExists('suppliers')) {
            return [];
        }

        $stmt = mysqli_prepare($this->conn, "
            SELECT supplier_id, supplier_name, mobile, gstin
            FROM suppliers
            WHERE business_id = ?
              AND status = 1
            ORDER BY supplier_name ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $businessId);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    private function buildStockWhere(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $where = "WHERE si.business_id = ?";
        $types = 'i';
        $params = [$businessId];
        $joinAccess = '';

        if (!$isAdmin && $userId > 0 && $this->tableExists('user_branch_access')) {
            $joinAccess = "
                INNER JOIN user_branch_access uba
                    ON uba.branch_id = si.branch_id
                   AND uba.business_id = si.business_id
                   AND uba.user_id = ?
            ";
            $params[] = $userId;
            $types .= 'i';
        }

        $branchId = (int)($filters['branch_id'] ?? 0);
        $categoryId = (int)($filters['category_id'] ?? 0);
        $brandId = (int)($filters['brand_id'] ?? 0);
        $supplierId = (int)($filters['supplier_id'] ?? 0);
        $stockStatus = trim((string)($filters['stock_status'] ?? ''));
        $search = trim((string)($filters['search'] ?? ''));
        $dateFrom = trim((string)($filters['date_from'] ?? ''));
        $dateTo = trim((string)($filters['date_to'] ?? ''));

        if ($branchId > 0) {
            $where .= " AND si.branch_id = ?";
            $params[] = $branchId;
            $types .= 'i';
        }

        if ($categoryId > 0) {
            $where .= " AND si.category_id = ?";
            $params[] = $categoryId;
            $types .= 'i';
        }

        if ($brandId > 0) {
            $where .= " AND si.brand_id = ?";
            $params[] = $brandId;
            $types .= 'i';
        }

        if ($supplierId > 0) {
            $where .= " AND sib.supplier_id = ?";
            $params[] = $supplierId;
            $types .= 'i';
        }

        if ($dateFrom !== '') {
            $where .= " AND sib.inward_date >= ?";
            $params[] = $dateFrom;
            $types .= 's';
        }

        if ($dateTo !== '') {
            $where .= " AND sib.inward_date <= ?";
            $params[] = $dateTo;
            $types .= 's';
        }

        if ($stockStatus === 'available') {
            $where .= " AND si.item_status = 'active' AND si.available_qty > 0";
        } elseif ($stockStatus === 'low') {
            $where .= " AND si.item_status = 'active' AND si.available_qty > 0 AND si.available_qty <= 5";
        } elseif ($stockStatus === 'out') {
            $where .= " AND (si.available_qty <= 0 OR si.item_status = 'out_of_stock')";
        } elseif (in_array($stockStatus, ['active', 'cancelled', 'deleted'], true)) {
            $where .= " AND si.item_status = ?";
            $params[] = $stockStatus;
            $types .= 's';
        } else {
            $where .= " AND si.item_status <> 'deleted'";
        }

        if ($search !== '') {
            $where .= " AND (
                si.article_no LIKE ?
                OR si.article_name LIKE ?
                OR si.size LIKE ?
                OR si.color LIKE ?
                OR sib.batch_no LIKE ?
                OR sb.barcode_value LIKE ?
                OR b.brand_name LIKE ?
                OR c.category_name LIKE ?
                OR br.branch_name LIKE ?
                OR s.supplier_name LIKE ?
            )";
            $like = '%' . $search . '%';
            for ($i = 0; $i < 10; $i++) {
                $params[] = $like;
                $types .= 's';
            }
        }

        return [$joinAccess, $where, $types, $params];
    }

    public function stats(int $businessId, int $userId, bool $isAdmin, array $filters = []): array
    {
        [$joinAccess, $where, $types, $params] = $this->buildStockWhere($businessId, $userId, $isAdmin, $filters);

        $sql = "
            SELECT
                COUNT(DISTINCT sib.batch_id) AS total_batches,
                COUNT(si.stock_item_id) AS total_items,
                COALESCE(SUM(CASE WHEN si.item_status = 'active' THEN si.available_qty ELSE 0 END), 0) AS total_qty,
                COALESCE(SUM(CASE WHEN si.item_status = 'active' THEN si.available_qty * si.selling_rate ELSE 0 END), 0) AS total_stock_value,
                COALESCE(SUM(CASE WHEN si.item_status = 'active' AND si.available_qty > 0 AND si.available_qty <= 5 THEN 1 ELSE 0 END), 0) AS low_stock_items,
                COALESCE(SUM(CASE WHEN si.available_qty <= 0 OR si.item_status = 'out_of_stock' THEN 1 ELSE 0 END), 0) AS out_stock_items
            FROM stock_inward_items si
            INNER JOIN stock_inward_batches sib
                ON sib.batch_id = si.batch_id
               AND sib.business_id = si.business_id
               AND sib.branch_id = si.branch_id
            {$joinAccess}
            LEFT JOIN branches br ON br.branch_id = si.branch_id AND br.business_id = si.business_id
            LEFT JOIN suppliers s ON s.supplier_id = sib.supplier_id AND s.business_id = si.business_id
            LEFT JOIN categories c ON c.category_id = si.category_id AND c.business_id = si.business_id
            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id
            LEFT JOIN stock_barcodes sb ON sb.stock_item_id = si.stock_item_id AND sb.business_id = si.business_id AND sb.branch_id = si.branch_id AND sb.barcode_status <> 'deleted'
            {$where}
        ";

        $stmt = mysqli_prepare($this->conn, $sql);
        $this->bindParams($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return [
            'total_batches' => (int)($row['total_batches'] ?? 0),
            'total_items' => (int)($row['total_items'] ?? 0),
            'total_qty' => (float)($row['total_qty'] ?? 0),
            'total_stock_value' => (float)($row['total_stock_value'] ?? 0),
            'low_stock_items' => (int)($row['low_stock_items'] ?? 0),
            'out_stock_items' => (int)($row['out_stock_items'] ?? 0),
        ];
    }

    public function listStock(int $businessId, int $userId, bool $isAdmin, array $filters = []): array
    {
        [$joinAccess, $where, $types, $params] = $this->buildStockWhere($businessId, $userId, $isAdmin, $filters);

        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(10, min(100, (int)($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $countSql = "
            SELECT COUNT(DISTINCT si.stock_item_id) AS total
            FROM stock_inward_items si
            INNER JOIN stock_inward_batches sib
                ON sib.batch_id = si.batch_id
               AND sib.business_id = si.business_id
               AND sib.branch_id = si.branch_id
            {$joinAccess}
            LEFT JOIN branches br ON br.branch_id = si.branch_id AND br.business_id = si.business_id
            LEFT JOIN suppliers s ON s.supplier_id = sib.supplier_id AND s.business_id = si.business_id
            LEFT JOIN categories c ON c.category_id = si.category_id AND c.business_id = si.business_id
            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id
            LEFT JOIN stock_barcodes sb ON sb.stock_item_id = si.stock_item_id AND sb.business_id = si.business_id AND sb.branch_id = si.branch_id AND sb.barcode_status <> 'deleted'
            {$where}
        ";

        $stmt = mysqli_prepare($this->conn, $countSql);
        $this->bindParams($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $countRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $total = (int)($countRow['total'] ?? 0);

        $sql = "
            SELECT
                si.stock_item_id,
                si.business_id,
                si.branch_id,
                br.branch_code,
                br.branch_name,
                br.floor_name,
                si.batch_id,
                sib.batch_no,
                sib.inward_date,
                sib.supplier_id,
                s.supplier_name,
                si.category_id,
                c.category_name,
                si.brand_id,
                b.brand_name,
                si.article_no,
                si.article_name,
                si.size,
                si.color,
                si.qty,
                si.available_qty,
                si.purchase_rate,
                si.mrp_rate,
                si.product_discount_type,
                si.product_discount_value,
                si.discount_amount,
                si.selling_rate,
                si.line_purchase_value,
                si.line_mrp_value,
                si.line_selling_value,
                si.barcode_required,
                si.item_status,
                si.created_at,
                GROUP_CONCAT(DISTINCT sb.barcode_value ORDER BY sb.barcode_id SEPARATOR ', ') AS barcode_values,
                MIN(sb.barcode_value) AS barcode_value,
                MIN(sb.barcode_id) AS barcode_id
            FROM stock_inward_items si
            INNER JOIN stock_inward_batches sib
                ON sib.batch_id = si.batch_id
               AND sib.business_id = si.business_id
               AND sib.branch_id = si.branch_id
            {$joinAccess}
            LEFT JOIN branches br ON br.branch_id = si.branch_id AND br.business_id = si.business_id
            LEFT JOIN suppliers s ON s.supplier_id = sib.supplier_id AND s.business_id = si.business_id
            LEFT JOIN categories c ON c.category_id = si.category_id AND c.business_id = si.business_id
            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id
            LEFT JOIN stock_barcodes sb ON sb.stock_item_id = si.stock_item_id AND sb.business_id = si.business_id AND sb.branch_id = si.branch_id AND sb.barcode_status <> 'deleted'
            {$where}
            GROUP BY si.stock_item_id
            ORDER BY si.stock_item_id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ";

        $stmt = mysqli_prepare($this->conn, $sql);
        $this->bindParams($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $items = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $items[] = $row;
        }
        mysqli_stmt_close($stmt);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int)ceil($total / $perPage),
            ],
        ];
    }

    public function getStockItem(int $businessId, int $stockItemId): ?array
    {
        $stmt = mysqli_prepare($this->conn, "
            SELECT
                si.*,
                br.branch_code,
                br.branch_name,
                br.floor_name,
                sib.batch_no,
                sib.inward_date,
                sib.inward_time,
                sib.total_qty AS batch_total_qty,
                sib.purchase_total_value AS batch_purchase_value,
                sib.mrp_total_value AS batch_mrp_value,
                sib.selling_total_value AS batch_selling_value,
                s.supplier_name,
                s.mobile AS supplier_mobile,
                s.gstin AS supplier_gstin,
                c.category_name,
                b.brand_name,
                GROUP_CONCAT(DISTINCT sb.barcode_value ORDER BY sb.barcode_id SEPARATOR ', ') AS barcode_values,
                MIN(sb.barcode_value) AS barcode_value
            FROM stock_inward_items si
            INNER JOIN stock_inward_batches sib
                ON sib.batch_id = si.batch_id
               AND sib.business_id = si.business_id
               AND sib.branch_id = si.branch_id
            LEFT JOIN branches br ON br.branch_id = si.branch_id AND br.business_id = si.business_id
            LEFT JOIN suppliers s ON s.supplier_id = sib.supplier_id AND s.business_id = si.business_id
            LEFT JOIN categories c ON c.category_id = si.category_id AND c.business_id = si.business_id
            LEFT JOIN brands b ON b.brand_id = si.brand_id AND b.business_id = si.business_id
            LEFT JOIN stock_barcodes sb ON sb.stock_item_id = si.stock_item_id AND sb.business_id = si.business_id AND sb.branch_id = si.branch_id AND sb.barcode_status <> 'deleted'
            WHERE si.business_id = ?
              AND si.stock_item_id = ?
            GROUP BY si.stock_item_id
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $stockItemId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    public function barcodeLookup(int $businessId, int $branchId, string $barcode): ?array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        $stmt = mysqli_prepare($this->conn, "
            SELECT si.stock_item_id
            FROM stock_barcodes sb
            INNER JOIN stock_inward_items si
                ON si.stock_item_id = sb.stock_item_id
               AND si.business_id = sb.business_id
               AND si.branch_id = sb.branch_id
            WHERE sb.business_id = ?
              AND sb.branch_id = ?
              AND sb.barcode_value = ?
              AND sb.barcode_status = 'active'
              AND si.item_status = 'active'
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'iis', $businessId, $branchId, $barcode);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            return null;
        }

        return $this->getStockItem($businessId, (int)$row['stock_item_id']);
    }

    private function userDisplayNameSql(string $alias = 'u'): string
    {
        if (!$this->tableExists('users')) {
            return "'System'";
        }

        $parts = [];
        foreach (['full_name', 'name', 'username', 'email'] as $column) {
            if ($this->columnExists('users', $column)) {
                $parts[] = "NULLIF({$alias}.`{$column}`, '')";
            }
        }

        if (!$parts) {
            return "'System'";
        }

        return 'COALESCE(' . implode(', ', $parts) . ", 'System')";
    }

    private function movementDisplayLabel($type): string
    {
        $key = strtolower(trim((string)$type));

        $labels = [
            'inward' => 'Stock Inward',
            'stock_inward' => 'Stock Inward',
            'reference' => 'Stock Inward',
            'purchase' => 'Purchase Stock',
            'purchase_inward' => 'Purchase Stock',
            'opening' => 'Opening Stock',
            'opening_stock' => 'Opening Stock',
            'sale' => 'Sale',
            'sales' => 'Sale',
            'bill' => 'Sale Bill',
            'sales_return' => 'Sales Return',
            'return' => 'Return',
            'adjustment' => 'Stock Adjustment',
            'stock_adjustment' => 'Stock Adjustment',
            'transfer' => 'Stock Transfer',
            'cancelled' => 'Cancelled',
        ];

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        if ($key === '') {
            return '-';
        }

        return ucwords(str_replace('_', ' ', $key));
    }

    private function normalizeMovementRow(array $row): array
    {
        $movementType = strtolower(trim((string)($row['movement_type'] ?? '')));
        $referenceType = strtolower(trim((string)($row['reference_type'] ?? '')));

        /*
         * Old inward rows were saved as "reference".
         * Keep the database value untouched, but return a POS-friendly value
         * so View modal/list screens do not show newly added stock as Reference.
         */
        if ($movementType === 'reference') {
            $row['movement_type'] = 'stock_inward';
        }

        if ($referenceType === 'reference') {
            $row['reference_type'] = 'stock_inward';
        }

        $row['movement_type_label'] = $this->movementDisplayLabel($row['movement_type'] ?? $movementType);
        $row['reference_type_label'] = $this->movementDisplayLabel($row['reference_type'] ?? $referenceType);

        if (!isset($row['created_by_name']) || trim((string)$row['created_by_name']) === '') {
            $row['created_by_name'] = 'System';
        }

        return $row;
    }

    public function movements(int $businessId, int $stockItemId): array
    {
        if (!$this->tableExists('stock_movements')) {
            return [];
        }

        $userSelect = "'System'";
        $userJoin = '';

        if (
            $this->tableExists('users')
            && $this->columnExists('users', 'user_id')
            && $this->columnExists('stock_movements', 'created_by')
        ) {
            $userSelect = $this->userDisplayNameSql('u');
            $userJoin = "LEFT JOIN users u ON u.user_id = sm.created_by";

            if ($this->columnExists('users', 'business_id') && $this->columnExists('stock_movements', 'business_id')) {
                $userJoin .= " AND u.business_id = sm.business_id";
            }
        }

        $orderBy = $this->columnExists('stock_movements', 'movement_id')
            ? 'sm.movement_id'
            : ($this->columnExists('stock_movements', 'created_at') ? 'sm.created_at' : 'sm.stock_item_id');

        $stmt = mysqli_prepare($this->conn, "
            SELECT sm.*, {$userSelect} AS created_by_name
            FROM stock_movements sm
            {$userJoin}
            WHERE sm.business_id = ?
              AND sm.stock_item_id = ?
            ORDER BY {$orderBy} DESC
            LIMIT 100
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $businessId, $stockItemId);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = $this->normalizeMovementRow($row);
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }
}
