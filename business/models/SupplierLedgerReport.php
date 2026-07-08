<?php
/**
 * Universal Footwear POS - Supplier Ledger Report Model
 * Single source calculation used by Supplier Ledger Report.
 *
 * Calculation copied from Supplier master:
 * Opening Balance + Purchase/Credit additions - Payment/Debit/Reversal decreases.
 * Important: CREDIT increases payable balance, DEBIT decreases payable balance.
 */

declare(strict_types=1);

class SupplierLedgerReport
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
    }

    private function bindParams(mysqli_stmt $stmt, string $types, array $params): void
    {
        if ($types === '' || !$params) {
            return;
        }

        $refs = [$stmt, $types];
        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }

        call_user_func_array('mysqli_stmt_bind_param', $refs);
    }

    private function queryRows(string $sql, string $types = '', array $params = []): array
    {
        $stmt = mysqli_prepare($this->conn, $sql);

        if (!$stmt) {
            throw new RuntimeException('SQL prepare failed: ' . mysqli_error($this->conn));
        }

        $this->bindParams($stmt, $types, $params);

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException('SQL execute failed: ' . $error);
        }

        $res = mysqli_stmt_get_result($stmt);
        $rows = [];

        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $rows[] = $row;
            }
        }

        mysqli_stmt_close($stmt);

        return $rows;
    }

    private function queryOne(string $sql, string $types = '', array $params = []): array
    {
        $rows = $this->queryRows($sql, $types, $params);
        return $rows[0] ?? [];
    }

    private function tableExists(string $table): bool
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?",
            's',
            [$table]
        );

        return (int)($row['total'] ?? 0) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS total
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?",
            'ss',
            [$table, $column]
        );

        return (int)($row['total'] ?? 0) > 0;
    }

    private function userNameExpr(string $alias = 'u'): string
    {
        $parts = [];

        foreach (['full_name', 'name', 'username', 'email'] as $col) {
            if ($this->columnExists('users', $col)) {
                $parts[] = "NULLIF($alias.$col, '')";
            }
        }

        return $parts ? 'COALESCE(' . implode(', ', $parts) . ", 'System')" : "'System'";
    }

    private function allowedBranchClause(string $alias, int $businessId, int $userId, bool $isAdmin, string &$types, array &$params): string
    {
        if ($isAdmin || !$this->tableExists('user_branch_access')) {
            return '';
        }

        $types .= 'ii';
        $params[] = $businessId;
        $params[] = $userId;

        return " AND EXISTS (
            SELECT 1
            FROM user_branch_access uba_acl
            WHERE uba_acl.business_id = ?
              AND uba_acl.user_id = ?
              AND uba_acl.branch_id = $alias.branch_id
              AND uba_acl.access_status = 1
        )";
    }

    private function dateClause(string $field, array $filters, string &$types, array &$params): string
    {
        $sql = '';

        if (!empty($filters['from_date'])) {
            $sql .= " AND DATE($field) >= ?";
            $types .= 's';
            $params[] = (string)$filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND DATE($field) <= ?";
            $types .= 's';
            $params[] = (string)$filters['to_date'];
        }

        return $sql;
    }

    private function supplierSearchClause(array $filters, string &$types, array &$params, string $alias = 's'): string
    {
        if (empty($filters['search'])) {
            return '';
        }

        $term = '%' . trim((string)$filters['search']) . '%';

        $types .= 'ssss';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;

        return " AND ($alias.supplier_name LIKE ? OR $alias.mobile LIKE ? OR $alias.email LIKE ? OR $alias.gstin LIKE ?)";
    }

    private function purchaseWhere(int $businessId, int $userId, bool $isAdmin, array $filters, string $alias, string &$types, array &$params): string
    {
        $types .= 'i';
        $params[] = $businessId;

        $where = "$alias.business_id = ?";

        $where .= $this->dateClause("$alias.inward_date", $filters, $types, $params);

        if (!empty($filters['branch_id'])) {
            $where .= " AND $alias.branch_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['branch_id'];
        } else {
            $where .= $this->allowedBranchClause($alias, $businessId, $userId, $isAdmin, $types, $params);
        }

        if (!empty($filters['supplier_id'])) {
            $where .= " AND $alias.supplier_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['purchase_status'])) {
            $where .= " AND $alias.batch_status = ?";
            $types .= 's';
            $params[] = (string)$filters['purchase_status'];
        } else {
            $where .= " AND COALESCE($alias.batch_status, 'active') <> 'deleted'";
        }

        return $where;
    }

    private function ledgerWhere(int $businessId, int $userId, bool $isAdmin, array $filters, string $alias, string &$types, array &$params): string
    {
        $types .= 'i';
        $params[] = $businessId;

        $where = "$alias.business_id = ? AND COALESCE($alias.reference_type, '') <> 'opening'";
        $where .= $this->dateClause("$alias.created_at", $filters, $types, $params);

        if (!empty($filters['branch_id'])) {
            $where .= " AND $alias.branch_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['branch_id'];
        } else {
            $where .= $this->allowedBranchClause($alias, $businessId, $userId, $isAdmin, $types, $params);
        }

        if (!empty($filters['supplier_id'])) {
            $where .= " AND $alias.supplier_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['reference_type'])) {
            $where .= " AND $alias.reference_type = ?";
            $types .= 's';
            $params[] = (string)$filters['reference_type'];
        }

        return $where;
    }

    private function ledgerDelta(array $row): float
    {
        $referenceType = strtolower((string)($row['reference_type'] ?? ''));
        $purpose = strtolower((string)($row['purpose'] ?? ''));
        $direction = strtolower((string)($row['transaction_direction'] ?? ''));
        $debit = round((float)($row['debit'] ?? 0), 2);
        $credit = round((float)($row['credit'] ?? 0), 2);

        if ($referenceType === 'opening') {
            return 0.0;
        }

        /*
         * Supplier master working rule copied here:
         * - Credit bill / credit transaction ADDS payable amount.
         * - Debit bill / debit transaction DECREASES payable amount.
         * Example: 18,000 payable + 10,000 credit = 28,000 payable.
         */
        if (strpos($referenceType, 'cancel') !== false || strpos($referenceType, 'reverse') !== false) {
            return round(-1 * ($debit + $credit), 2);
        }

        if (strpos($referenceType, 'stock_inward') !== false) {
            /*
             * Stock inward purchase must add to supplier payable.
             * Supports old rows stored in debit and new rows stored in credit.
             */
            return round($debit + $credit, 2);
        }

        if (in_array($purpose, ['debit', 'advance_payment', 'purchase_return'], true) || $direction === 'debit') {
            return round(-1 * ($debit + $credit), 2);
        }

        if ($purpose === 'credit' || $direction === 'credit') {
            return round($debit + $credit, 2);
        }

        return round($credit - $debit, 2);
    }

    private function calculatedBalanceUpToLedgerRow(int $businessId, int $supplierId, string $createdAt, int $ledgerId, int $branchId = 0): float
    {
        $supplier = $this->queryOne(
            "SELECT opening_outstanding
             FROM suppliers
             WHERE business_id = ?
               AND supplier_id = ?
             LIMIT 1",
            'ii',
            [$businessId, $supplierId]
        );

        $balance = round((float)($supplier['opening_outstanding'] ?? 0), 2);

        $types = 'iisi';
        $params = [$businessId, $supplierId, $createdAt, $ledgerId];
        $where = "business_id = ?
            AND supplier_id = ?
            AND COALESCE(reference_type, '') <> 'opening'
            AND (created_at < ? OR (created_at = ? AND vendor_ledger_id <= ?))";

        /* Correct bind types for created_at repeated value. */
        $types = 'iis si';
        $types = str_replace(' ', '', $types);
        $params = [$businessId, $supplierId, $createdAt, $createdAt, $ledgerId];

        if ($branchId > 0) {
            $where .= " AND branch_id = ?";
            $types .= 'i';
            $params[] = $branchId;
        }

        $rows = $this->queryRows(
            "SELECT reference_type, purpose, transaction_direction, debit, credit
             FROM vendor_ledger
             WHERE $where
             ORDER BY created_at ASC, vendor_ledger_id ASC",
            $types,
            $params
        );

        foreach ($rows as $row) {
            $balance = round($balance + $this->ledgerDelta($row), 2);
        }

        return $balance;
    }

    private function ledgerDisplayType(array $row): string
    {
        $referenceType = strtolower((string)($row['reference_type'] ?? ''));
        $purpose = strtolower((string)($row['purpose'] ?? ''));
        $direction = strtolower((string)($row['transaction_direction'] ?? ''));

        if ($referenceType === 'opening') {
            return 'Opening Balance';
        }

        if (strpos($referenceType, 'cancel') !== false || strpos($referenceType, 'reverse') !== false) {
            return 'Reversal';
        }

        if (strpos($referenceType, 'stock_inward') !== false) {
            return 'Purchase';
        }

        if ($purpose === 'credit' || $direction === 'credit') {
            return 'Credit';
        }

        if ($purpose === 'debit' || $direction === 'debit') {
            return 'Debit';
        }

        if ($purpose !== '') {
            return ucwords(str_replace('_', ' ', $purpose));
        }

        return ucwords(str_replace('_', ' ', (string)($row['reference_type'] ?? 'Ledger')));
    }

    private function calculatedSupplierMap(int $businessId, array $filters = []): array
    {
        $suppliers = $this->queryRows("SELECT supplier_id, opening_outstanding FROM suppliers WHERE business_id = ?", 'i', [$businessId]);
        $map = [];

        foreach ($suppliers as $supplier) {
            $supplierId = (int)$supplier['supplier_id'];
            $opening = round((float)($supplier['opening_outstanding'] ?? 0), 2);

            $map[$supplierId] = [
                'opening_balance' => $opening,
                'total_addition' => 0.0,
                'total_decrease' => 0.0,
                'raw_debit_total' => 0.0,
                'raw_credit_total' => 0.0,
                'calculated_balance' => $opening,
                'last_ledger_datetime' => null,
            ];
        }

        $types = 'i';
        $params = [$businessId];
        $where = "business_id = ? AND COALESCE(reference_type, '') <> 'opening'";
        $where .= $this->dateClause("created_at", $filters, $types, $params);

        if (!empty($filters['supplier_id'])) {
            $where .= " AND supplier_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['supplier_id'];
        }

        if (!empty($filters['reference_type'])) {
            $where .= " AND reference_type = ?";
            $types .= 's';
            $params[] = (string)$filters['reference_type'];
        }

        $rows = $this->queryRows(
            "SELECT supplier_id, reference_type, purpose, transaction_direction, debit, credit, created_at
             FROM vendor_ledger
             WHERE $where
             ORDER BY created_at ASC, vendor_ledger_id ASC",
            $types,
            $params
        );

        foreach ($rows as $row) {
            $supplierId = (int)$row['supplier_id'];

            if (!isset($map[$supplierId])) {
                $map[$supplierId] = [
                    'opening_balance' => 0.0,
                    'total_addition' => 0.0,
                    'total_decrease' => 0.0,
                    'raw_debit_total' => 0.0,
                    'raw_credit_total' => 0.0,
                    'calculated_balance' => 0.0,
                    'last_ledger_datetime' => null,
                ];
            }

            $delta = $this->ledgerDelta($row);

            if ($delta >= 0) {
                $map[$supplierId]['total_addition'] = round($map[$supplierId]['total_addition'] + $delta, 2);
            } else {
                $map[$supplierId]['total_decrease'] = round($map[$supplierId]['total_decrease'] + abs($delta), 2);
            }

            $map[$supplierId]['raw_debit_total'] = round($map[$supplierId]['raw_debit_total'] + (float)($row['debit'] ?? 0), 2);
            $map[$supplierId]['raw_credit_total'] = round($map[$supplierId]['raw_credit_total'] + (float)($row['credit'] ?? 0), 2);
            $map[$supplierId]['calculated_balance'] = round($map[$supplierId]['calculated_balance'] + $delta, 2);
            $map[$supplierId]['last_ledger_datetime'] = $row['created_at'] ?? $map[$supplierId]['last_ledger_datetime'];
        }

        return $map;
    }

    private function paginateArray(array $rows, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(10, min(200, (int)($filters['per_page'] ?? 25)));
        $total = count($rows);
        $totalPages = max(1, (int)ceil($total / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        return [
            'rows' => array_slice($rows, $offset, $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    private function paginate(string $sql, string $types, array $params, array $filters, array $sortMap, string $defaultSort): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = max(10, min(200, (int)($filters['per_page'] ?? 25)));
        $sortBy = (string)($filters['sort_by'] ?? '');
        $sortDir = strtolower((string)($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $order = $sortMap[$sortBy] ?? $defaultSort;

        $countRow = $this->queryOne("SELECT COUNT(*) AS total FROM ($sql) report_rows", $types, $params);
        $total = (int)($countRow['total'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;
        $rows = $this->queryRows($sql . " ORDER BY $order $sortDir LIMIT ? OFFSET ?", $types . 'ii', array_merge($params, [$perPage, $offset]));

        return [
            'rows' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public function masters(int $businessId, int $userId, bool $isAdmin): array
    {
        return [
            'branches' => $this->branches($businessId, $userId, $isAdmin),
            'suppliers' => $this->suppliersMaster($businessId),
            'reference_types' => [
                ['value' => 'stock_inward', 'label' => 'Stock Inward'],
                ['value' => 'stock_inward_update', 'label' => 'Stock Inward Update'],
                ['value' => 'stock_inward_update_reverse', 'label' => 'Stock Inward Reverse'],
                ['value' => 'stock_inward_cancelled', 'label' => 'Stock Inward Cancelled'],
                ['value' => 'supplier_transaction', 'label' => 'Supplier Transaction'],
            ],
        ];
    }

    private function branches(int $businessId, int $userId, bool $isAdmin): array
    {
        if ($isAdmin || !$this->tableExists('user_branch_access')) {
            return $this->queryRows(
                "SELECT branch_id, branch_code, branch_name, floor_name
                 FROM branches
                 WHERE business_id = ? AND status = 1
                 ORDER BY branch_name, floor_name",
                'i',
                [$businessId]
            );
        }

        return $this->queryRows(
            "SELECT b.branch_id, b.branch_code, b.branch_name, b.floor_name
             FROM branches b
             INNER JOIN user_branch_access uba
                ON uba.branch_id = b.branch_id
               AND uba.business_id = b.business_id
               AND uba.user_id = ?
               AND uba.access_status = 1
             WHERE b.business_id = ?
               AND b.status = 1
             ORDER BY b.branch_name, b.floor_name",
            'ii',
            [$userId, $businessId]
        );
    }

    private function suppliersMaster(int $businessId): array
    {
        return $this->queryRows(
            "SELECT supplier_id, supplier_name, mobile, gstin
             FROM suppliers
             WHERE business_id = ? AND status = 1
             ORDER BY supplier_name",
            'i',
            [$businessId]
        );
    }

    public function summary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $suppliers = $this->supplierSummaryRows($businessId, $userId, $isAdmin, $filters);
        $total = count($suppliers);
        $opening = 0.0;
        $addition = 0.0;
        $decrease = 0.0;
        $balance = 0.0;
        $outstanding = 0;
        $clear = 0;

        foreach ($suppliers as $row) {
            $opening += (float)($row['opening_outstanding'] ?? 0);
            $addition += (float)($row['total_purchase_amount'] ?? 0);
            $decrease += (float)($row['total_paid_amount'] ?? 0);
            $balance += (float)($row['balance_amount'] ?? 0);

            if ((float)($row['balance_amount'] ?? 0) > 0) {
                $outstanding++;
            } else {
                $clear++;
            }
        }

        return [
            'total_suppliers' => $total,
            'opening_outstanding' => round($opening, 2),
            'total_purchase_amount' => round($addition, 2),
            'total_paid_amount' => round($decrease, 2),
            'total_balance_amount' => round($balance, 2),
            'outstanding_suppliers' => $outstanding,
            'clear_suppliers' => $clear,
            'daily_trend' => $this->dailyPurchaseTrend($businessId, $userId, $isAdmin, $filters),
            'purpose_mix' => $this->purposeMix($businessId, $userId, $isAdmin, $filters),
        ];
    }

    private function dailyPurchaseTrend(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $types = '';
        $params = [];
        $where = $this->purchaseWhere($businessId, $userId, $isAdmin, $filters, 'sib', $types, $params);

        return $this->queryRows(
            "SELECT sib.inward_date AS entry_date,
                    DATE_FORMAT(sib.inward_date, '%d-%m') AS display_date,
                    COALESCE(SUM(sib.purchase_total_value),0) AS purchase_amount
             FROM stock_inward_batches sib
             WHERE $where
             GROUP BY sib.inward_date
             ORDER BY sib.inward_date DESC
             LIMIT 10",
            $types,
            $params
        );
    }

    private function purposeMix(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $types = '';
        $params = [];
        $where = $this->ledgerWhere($businessId, $userId, $isAdmin, $filters, 'vl', $types, $params);

        return $this->queryRows(
            "SELECT COALESCE(NULLIF(vl.purpose,''), NULLIF(vl.reference_type,''), 'ledger') AS purpose_name,
                    COALESCE(SUM(vl.debit),0) AS debit_amount,
                    COALESCE(SUM(vl.credit),0) AS credit_amount,
                    COALESCE(SUM(vl.debit + vl.credit),0) AS total_amount
             FROM vendor_ledger vl
             WHERE $where
             GROUP BY purpose_name
             ORDER BY total_amount DESC
             LIMIT 8",
            $types,
            $params
        );
    }

    private function supplierSummaryRows(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $calcMap = $this->calculatedSupplierMap($businessId, $filters);

        $pTypes = '';
        $pParams = [];
        $pWhere = $this->purchaseWhere($businessId, $userId, $isAdmin, $filters, 'sib', $pTypes, $pParams);

        $types = $pTypes;
        $params = $pParams;
        $where = 's.business_id = ?';
        $types .= 'i';
        $params[] = $businessId;

        if (!empty($filters['supplier_id'])) {
            $where .= ' AND s.supplier_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['supplier_id'];
        }

        if (isset($filters['supplier_status']) && $filters['supplier_status'] !== '') {
            $where .= ' AND s.status = ?';
            $types .= 'i';
            $params[] = (int)$filters['supplier_status'];
        }

        $where .= $this->supplierSearchClause($filters, $types, $params, 's');

        $rows = $this->queryRows(
            "SELECT
                s.supplier_id,
                s.supplier_name,
                s.mobile,
                s.email,
                s.gstin,
                s.status,
                s.opening_outstanding,
                s.current_outstanding AS db_current_balance,
                COALESCE(p.purchase_count, 0) AS purchase_count,
                p.last_purchase_datetime,
                CASE WHEN p.last_purchase_datetime IS NULL THEN '-' ELSE DATE_FORMAT(p.last_purchase_datetime, '%d-%m-%Y %h:%i %p') END AS last_purchase_display
             FROM suppliers s
             LEFT JOIN (
                SELECT sib.supplier_id,
                       COUNT(*) AS purchase_count,
                       MAX(sib.created_at) AS last_purchase_datetime
                FROM stock_inward_batches sib
                WHERE $pWhere
                  AND sib.supplier_id IS NOT NULL
                GROUP BY sib.supplier_id
             ) p ON p.supplier_id = s.supplier_id
             WHERE $where
             ORDER BY s.supplier_name ASC",
            $types,
            $params
        );

        $filtered = [];

        foreach ($rows as $row) {
            $supplierId = (int)$row['supplier_id'];
            $calc = $calcMap[$supplierId] ?? [
                'total_addition' => 0,
                'total_decrease' => 0,
                'calculated_balance' => (float)($row['opening_outstanding'] ?? 0),
            ];

            $row['total_purchase_amount'] = round((float)$calc['total_addition'], 2);
            $row['total_paid_amount'] = round((float)$calc['total_decrease'], 2);
            $row['balance_amount'] = round((float)$calc['calculated_balance'], 2);
            $row['calculated_balance'] = $row['balance_amount'];

            if (($filters['ledger_status'] ?? '') === 'outstanding' && (float)$row['balance_amount'] <= 0) {
                continue;
            }

            if (($filters['ledger_status'] ?? '') === 'clear' && (float)$row['balance_amount'] > 0) {
                continue;
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    public function supplierSummary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $rows = $this->supplierSummaryRows($businessId, $userId, $isAdmin, $filters);

        $sortBy = (string)($filters['sort_by'] ?? 'supplier_name');
        $sortDir = strtolower((string)($filters['sort_dir'] ?? 'asc')) === 'desc' ? -1 : 1;
        $sortMap = [
            'supplier_name' => 'supplier_name',
            'mobile' => 'mobile',
            'purchase_count' => 'purchase_count',
            'total_purchase_amount' => 'total_purchase_amount',
            'total_paid_amount' => 'total_paid_amount',
            'balance_amount' => 'balance_amount',
            'last_purchase' => 'last_purchase_datetime',
        ];
        $key = $sortMap[$sortBy] ?? 'supplier_name';

        usort($rows, static function ($a, $b) use ($key, $sortDir) {
            $av = $a[$key] ?? '';
            $bv = $b[$key] ?? '';

            if (is_numeric($av) && is_numeric($bv)) {
                return ((float)$av <=> (float)$bv) * $sortDir;
            }

            return strcasecmp((string)$av, (string)$bv) * $sortDir;
        });

        return $this->paginateArray($rows, $filters);
    }

    public function ledgerEntries(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $nameExpr = $this->userNameExpr('u');
        $types = '';
        $params = [];
        $where = $this->ledgerWhere($businessId, $userId, $isAdmin, $filters, 'vl', $types, $params);

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (s.supplier_name LIKE ? OR s.mobile LIKE ? OR s.gstin LIKE ? OR vl.remarks LIKE ? OR sib.batch_no LIKE ? OR sib.invoice_number LIKE ?)";
            $types .= 'ssssss';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        $sql = "SELECT
                    vl.vendor_ledger_id,
                    vl.supplier_id,
                    s.supplier_name,
                    s.mobile,
                    s.gstin,
                    vl.reference_type,
                    vl.reference_id,
                    vl.purpose,
                    vl.transaction_direction,
                    vl.debit,
                    vl.credit,
                    vl.balance AS db_ledger_balance,
                    vl.remarks,
                    vl.created_at AS entry_datetime,
                    DATE_FORMAT(vl.created_at, '%d-%m-%Y %h:%i %p') AS entry_display,
                    COALESCE(sib.batch_no, CONCAT('#', vl.reference_id)) AS reference_no,
                    sib.invoice_number,
                    br.branch_name,
                    br.floor_name,
                    $nameExpr AS created_by_name
                FROM vendor_ledger vl
                INNER JOIN suppliers s
                    ON s.supplier_id = vl.supplier_id
                   AND s.business_id = vl.business_id
                LEFT JOIN stock_inward_batches sib
                    ON sib.batch_id = vl.reference_id
                   AND sib.business_id = vl.business_id
                   AND vl.reference_type LIKE 'stock_inward%'
                LEFT JOIN branches br
                    ON br.branch_id = vl.branch_id
                   AND br.business_id = vl.business_id
                LEFT JOIN users u
                    ON u.user_id = vl.created_by
                   AND u.business_id = vl.business_id
                WHERE $where";

        $data = $this->paginate($sql, $types, $params, $filters, [
            'date' => 'entry_datetime',
            'supplier_name' => 'supplier_name',
            'reference_type' => 'reference_type',
            'debit' => 'debit',
            'credit' => 'credit',
            'balance' => 'db_ledger_balance',
        ], 'entry_datetime');

        $branchFilter = !empty($filters['branch_id']) ? (int)$filters['branch_id'] : 0;

        foreach ($data['rows'] as &$row) {
            $row['display_type'] = $this->ledgerDisplayType($row);
            $row['balance'] = $this->calculatedBalanceUpToLedgerRow(
                $businessId,
                (int)($row['supplier_id'] ?? 0),
                (string)($row['entry_datetime'] ?? ''),
                (int)($row['vendor_ledger_id'] ?? 0),
                $branchFilter
            );
        }
        unset($row);

        return $data;
    }

    public function purchaseHistory(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $nameExpr = $this->userNameExpr('u');
        $types = '';
        $params = [];
        $where = $this->purchaseWhere($businessId, $userId, $isAdmin, $filters, 'sib', $types, $params);

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (s.supplier_name LIKE ? OR s.mobile LIKE ? OR s.gstin LIKE ? OR sib.batch_no LIKE ? OR sib.invoice_number LIKE ?)";
            $types .= 'sssss';
            array_push($params, $term, $term, $term, $term, $term);
        }

        $sql = "SELECT
                    sib.batch_id,
                    sib.batch_no,
                    sib.invoice_number,
                    sib.inward_date,
                    sib.inward_time,
                    DATE_FORMAT(CONCAT(sib.inward_date, ' ', COALESCE(sib.inward_time, '00:00:00')), '%d-%m-%Y %h:%i %p') AS inward_display,
                    sib.supplier_id,
                    s.supplier_name,
                    s.mobile,
                    sib.total_qty,
                    sib.purchase_total_value,
                    sib.mrp_total_value,
                    sib.selling_total_value,
                    sib.batch_status,
                    br.branch_name,
                    br.floor_name,
                    $nameExpr AS created_by_name,
                    sib.created_at
                FROM stock_inward_batches sib
                LEFT JOIN suppliers s
                    ON s.supplier_id = sib.supplier_id
                   AND s.business_id = sib.business_id
                LEFT JOIN branches br
                    ON br.branch_id = sib.branch_id
                   AND br.business_id = sib.business_id
                LEFT JOIN users u
                    ON u.user_id = sib.created_by
                   AND u.business_id = sib.business_id
                WHERE $where";

        return $this->paginate($sql, $types, $params, $filters, [
            'date' => 'created_at',
            'batch_no' => 'batch_no',
            'supplier_name' => 'supplier_name',
            'total_qty' => 'total_qty',
            'purchase_total_value' => 'purchase_total_value',
        ], 'created_at');
    }

    public function outstanding(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $filters['ledger_status'] = 'outstanding';
        return $this->supplierSummary($businessId, $userId, $isAdmin, $filters);
    }

    private function previousOpening(int $businessId, int $userId, bool $isAdmin, int $supplierId, array $filters, float $opening): float
    {
        if (empty($filters['from_date'])) {
            return $opening;
        }

        $types = 'iis';
        $params = [$businessId, $supplierId, (string)$filters['from_date']];
        $where = "vl.business_id = ?
            AND vl.supplier_id = ?
            AND COALESCE(vl.reference_type, '') <> 'opening'
            AND DATE(vl.created_at) < ?";

        if (!empty($filters['branch_id'])) {
            $where .= " AND vl.branch_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['branch_id'];
        } else {
            $where .= $this->allowedBranchClause('vl', $businessId, $userId, $isAdmin, $types, $params);
        }

        $rows = $this->queryRows(
            "SELECT reference_type, purpose, transaction_direction, debit, credit
             FROM vendor_ledger vl
             WHERE $where
             ORDER BY vl.created_at ASC, vl.vendor_ledger_id ASC",
            $types,
            $params
        );

        foreach ($rows as $row) {
            $opening = round($opening + $this->ledgerDelta($row), 2);
        }

        return $opening;
    }

    public function statement(int $businessId, int $userId, bool $isAdmin, int $supplierId, array $filters): array
    {
        $supplier = $this->queryOne(
            "SELECT s.*,
                    COALESCE(vo.total_purchase_amount, 0) AS db_total_purchase_amount,
                    COALESCE(vo.total_paid_amount, 0) AS db_total_paid_amount,
                    COALESCE(vo.balance_amount, s.current_outstanding, 0) AS db_current_balance
             FROM suppliers s
             LEFT JOIN vendor_outstanding vo
                ON vo.business_id = s.business_id
               AND vo.supplier_id = s.supplier_id
             WHERE s.business_id = ?
               AND s.supplier_id = ?
             LIMIT 1",
            'ii',
            [$businessId, $supplierId]
        );

        if (!$supplier) {
            return ['supplier' => [], 'summary' => [], 'rows' => []];
        }

        $baseOpening = round((float)($supplier['opening_outstanding'] ?? 0), 2);
        $opening = $this->previousOpening($businessId, $userId, $isAdmin, $supplierId, $filters, $baseOpening);

        $types = 'ii';
        $params = [$businessId, $supplierId];
        $where = "vl.business_id = ?
            AND vl.supplier_id = ?
            AND COALESCE(vl.reference_type, '') <> 'opening'";

        if (!empty($filters['branch_id'])) {
            $where .= " AND vl.branch_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['branch_id'];
        } else {
            $where .= $this->allowedBranchClause('vl', $businessId, $userId, $isAdmin, $types, $params);
        }

        if (!empty($filters['from_date'])) {
            $where .= " AND DATE(vl.created_at) >= ?";
            $types .= 's';
            $params[] = (string)$filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where .= " AND DATE(vl.created_at) <= ?";
            $types .= 's';
            $params[] = (string)$filters['to_date'];
        }

        if (!empty($filters['reference_type']) && $filters['reference_type'] !== 'opening') {
            $where .= " AND vl.reference_type = ?";
            $types .= 's';
            $params[] = (string)$filters['reference_type'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (
                s.supplier_name LIKE ?
                OR s.mobile LIKE ?
                OR s.gstin LIKE ?
                OR vl.remarks LIKE ?
                OR sib.batch_no LIKE ?
                OR sib.invoice_number LIKE ?
            )";
            $types .= 'ssssss';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        $nameExpr = $this->userNameExpr('u');

        $ledgerRows = $this->queryRows(
            "SELECT
                    vl.vendor_ledger_id,
                    vl.supplier_id,
                    s.supplier_name,
                    s.mobile,
                    s.gstin,
                    vl.reference_type,
                    vl.reference_id,
                    vl.purpose,
                    vl.transaction_direction,
                    vl.debit,
                    vl.credit,
                    vl.balance AS db_ledger_balance,
                    vl.remarks,
                    vl.created_at AS entry_datetime,
                    DATE_FORMAT(vl.created_at, '%d-%m-%Y %h:%i %p') AS entry_display,
                    COALESCE(sib.batch_no, CONCAT('#', COALESCE(vl.reference_id, '-'))) AS reference_no,
                    sib.invoice_number,
                    br.branch_name,
                    br.floor_name,
                    $nameExpr AS created_by_name
             FROM vendor_ledger vl
             INNER JOIN suppliers s
                ON s.supplier_id = vl.supplier_id
               AND s.business_id = vl.business_id
             LEFT JOIN stock_inward_batches sib
                ON sib.batch_id = vl.reference_id
               AND sib.business_id = vl.business_id
               AND vl.reference_type LIKE 'stock_inward%'
             LEFT JOIN branches br
                ON br.branch_id = vl.branch_id
               AND br.business_id = vl.business_id
             LEFT JOIN users u
                ON u.user_id = vl.created_by
               AND u.business_id = vl.business_id
             WHERE $where
             ORDER BY vl.created_at ASC, vl.vendor_ledger_id ASC",
            $types,
            $params
        );

        $rows = [];
        $totalAddition = 0.0;
        $totalDecrease = 0.0;
        $rawDebitTotal = 0.0;
        $rawCreditTotal = 0.0;
        $runningBalance = $opening;

        if (abs($opening) > 0) {
            $rows[] = [
                'vendor_ledger_id' => 0,
                'supplier_id' => $supplierId,
                'supplier_name' => $supplier['supplier_name'] ?? '',
                'mobile' => $supplier['mobile'] ?? '',
                'gstin' => $supplier['gstin'] ?? '',
                'reference_type' => 'opening',
                'display_type' => 'Opening Balance',
                'reference_id' => 0,
                'purpose' => 'Opening Balance',
                'transaction_direction' => 'opening',
                'debit' => 0,
                'credit' => 0,
                'remarks' => empty($filters['from_date']) ? 'Supplier opening balance' : 'Opening / previous balance before selected period',
                'entry_datetime' => '',
                'entry_display' => 'Opening Balance',
                'reference_no' => '-',
                'invoice_number' => '',
                'branch_name' => '',
                'floor_name' => '',
                'created_by_name' => 'Opening',
                'balance' => $runningBalance,
                'db_ledger_balance' => null,
                'is_opening' => 1,
            ];
        }

        foreach ($ledgerRows as $row) {
            $debit = round((float)($row['debit'] ?? 0), 2);
            $credit = round((float)($row['credit'] ?? 0), 2);
            $delta = $this->ledgerDelta($row);

            if ($delta >= 0) {
                $totalAddition = round($totalAddition + $delta, 2);
            } else {
                $totalDecrease = round($totalDecrease + abs($delta), 2);
            }

            $rawDebitTotal = round($rawDebitTotal + $debit, 2);
            $rawCreditTotal = round($rawCreditTotal + $credit, 2);
            $runningBalance = round($runningBalance + $delta, 2);

            $row['display_type'] = $this->ledgerDisplayType($row);
            $row['debit'] = $debit;
            $row['credit'] = $credit;
            $row['delta_amount'] = $delta;
            $row['balance'] = $runningBalance;
            $row['is_opening'] = 0;

            $rows[] = $row;
        }

        $supplier['calculated_balance'] = round($runningBalance, 2);

        return [
            'supplier' => $supplier,
            'summary' => [
                'opening_balance' => round($opening, 2),
                'total_debit' => round($totalDecrease, 2),
                'total_credit' => round($totalAddition, 2),
                'raw_debit_total' => round($rawDebitTotal, 2),
                'raw_credit_total' => round($rawCreditTotal, 2),
                'total_addition' => round($totalAddition, 2),
                'total_decrease' => round($totalDecrease, 2),
                'closing_balance' => round($runningBalance, 2),
                'current_balance' => round($runningBalance, 2),
                'db_current_balance' => round((float)($supplier['db_current_balance'] ?? 0), 2),
                'difference' => round($runningBalance - (float)($supplier['db_current_balance'] ?? 0), 2),
            ],
            'rows' => $rows,
        ];
    }

    public function verificationRows(int $businessId): array
    {
        return $this->supplierSummaryRows($businessId, 0, true, []);
    }
}
?>
