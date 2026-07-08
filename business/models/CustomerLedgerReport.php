<?php
/**
 * Universal Footwear POS - Customer Ledger Report Model
 * Corrected calculation workflow:
 * Opening Balance -> Date-wise Bill/Debit additions and Payment/Credit decreases -> Correct Running Closing Balance.
 * Customer rule: Debit/Bill adds outstanding, Credit/Payment decreases outstanding.
 */

declare(strict_types=1);

class CustomerLedgerReport
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

    public function getBranches(int $businessId, int $userId, bool $isAdmin): array
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

    public function getCustomers(int $businessId): array
    {
        return $this->queryRows(
            "SELECT customer_id, customer_name, mobile, email, gstin, opening_outstanding, loyalty_points
             FROM customers
             WHERE business_id = ? AND status = 1
             ORDER BY customer_name
             LIMIT 1500",
            'i',
            [$businessId]
        );
    }

    public function getUsers(int $businessId): array
    {
        $nameExpr = $this->userNameExpr('u');

        return $this->queryRows(
            "SELECT u.user_id, $nameExpr AS user_name, u.username
             FROM users u
             WHERE u.business_id = ? AND u.status = 1
             ORDER BY user_name",
            'i',
            [$businessId]
        );
    }

    public function masters(int $businessId, int $userId, bool $isAdmin): array
    {
        return [
            'branches' => $this->getBranches($businessId, $userId, $isAdmin),
            'customers' => $this->getCustomers($businessId),
            'users' => $this->getUsers($businessId),
            'reference_types' => [
                ['value' => 'opening', 'label' => 'Opening'],
                ['value' => 'bill', 'label' => 'Bill'],
                ['value' => 'bill_payment', 'label' => 'Bill Payment'],
                ['value' => 'payment', 'label' => 'Payment'],
                ['value' => 'partial_payment', 'label' => 'Partial Payment'],
                ['value' => 'reverse', 'label' => 'Reverse'],
                ['value' => 'adjustment', 'label' => 'Adjustment'],
            ],
        ];
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

    private function customerSearchClause(array $filters, string &$types, array &$params, string $alias = 'c'): string
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

        return " AND ($alias.customer_name LIKE ? OR $alias.mobile LIKE ? OR $alias.email LIKE ? OR $alias.gstin LIKE ?)";
    }

    private function billWhere(int $businessId, int $userId, bool $isAdmin, array $filters, string $alias, string &$types, array &$params): string
    {
        $types .= 'i';
        $params[] = $businessId;

        $where = "$alias.business_id = ? AND COALESCE($alias.bill_status, 'active') <> 'deleted'";
        $where .= $this->dateClause("$alias.created_at", $filters, $types, $params);

        if (!empty($filters['branch_id'])) {
            $where .= " AND $alias.branch_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['branch_id'];
        } else {
            $where .= $this->allowedBranchClause($alias, $businessId, $userId, $isAdmin, $types, $params);
        }

        if (!empty($filters['customer_id'])) {
            $where .= " AND $alias.customer_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['payment_status'])) {
            $where .= " AND $alias.payment_status = ?";
            $types .= 's';
            $params[] = (string)$filters['payment_status'];
        }

        return $where;
    }

    private function ledgerWhere(int $businessId, int $userId, bool $isAdmin, array $filters, string $alias, string &$types, array &$params, bool $includeOpening = true): string
    {
        $types .= 'i';
        $params[] = $businessId;

        $where = "$alias.business_id = ?";
        $where .= $this->dateClause("$alias.created_at", $filters, $types, $params);

        if (!empty($filters['branch_id'])) {
            $where .= " AND $alias.branch_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['branch_id'];
        } else {
            $where .= $this->allowedBranchClause($alias, $businessId, $userId, $isAdmin, $types, $params);
        }

        if (!empty($filters['customer_id'])) {
            $where .= " AND $alias.customer_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['customer_id'];
        }

        if (!empty($filters['reference_type'])) {
            $where .= " AND $alias.reference_type = ?";
            $types .= 's';
            $params[] = (string)$filters['reference_type'];
        }

        if (!$includeOpening) {
            $where .= " AND COALESCE($alias.reference_type, '') <> 'opening'";
        }

        return $where;
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

    public function summary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $types = 'i';
        $params = [$businessId];
        $where = 'c.business_id = ?';

        if (!empty($filters['customer_id'])) {
            $where .= ' AND c.customer_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['customer_id'];
        }

        if (isset($filters['customer_status']) && $filters['customer_status'] !== '') {
            $where .= ' AND c.status = ?';
            $types .= 'i';
            $params[] = (int)$filters['customer_status'];
        }

        $where .= $this->customerSearchClause($filters, $types, $params, 'c');

        if (($filters['ledger_status'] ?? '') === 'outstanding') {
            $where .= ' AND COALESCE(co.balance_amount, c.opening_outstanding, 0) > 0';
        }

        if (($filters['ledger_status'] ?? '') === 'clear') {
            $where .= ' AND COALESCE(co.balance_amount, c.opening_outstanding, 0) <= 0';
        }

        $summary = $this->queryOne(
            "SELECT
                COUNT(DISTINCT c.customer_id) AS total_customers,
                COALESCE(SUM(c.opening_outstanding),0) AS opening_outstanding,
                COALESCE(SUM(COALESCE(co.total_bill_amount,0)),0) AS total_bill_amount,
                COALESCE(SUM(COALESCE(co.total_paid_amount,0)),0) AS total_paid_amount,
                COALESCE(SUM(COALESCE(co.balance_amount, c.opening_outstanding, 0)),0) AS total_balance_amount,
                COALESCE(SUM(CASE WHEN COALESCE(co.balance_amount, c.opening_outstanding, 0) > 0 THEN 1 ELSE 0 END),0) AS outstanding_customers,
                COALESCE(SUM(CASE WHEN COALESCE(co.balance_amount, c.opening_outstanding, 0) <= 0 THEN 1 ELSE 0 END),0) AS clear_customers
             FROM customers c
             LEFT JOIN customer_outstanding co
                ON co.business_id = c.business_id
               AND co.customer_id = c.customer_id
             WHERE $where",
            $types,
            $params
        );

        $summary['daily_trend'] = $this->dailyBillTrend($businessId, $userId, $isAdmin, $filters);
        $summary['purpose_mix'] = $this->purposeMix($businessId, $userId, $isAdmin, $filters);

        return $summary;
    }

    private function dailyBillTrend(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $types = '';
        $params = [];
        $where = $this->billWhere($businessId, $userId, $isAdmin, $filters, 'b', $types, $params);

        return $this->queryRows(
            "SELECT DATE(b.created_at) AS entry_date,
                    DATE_FORMAT(b.created_at, '%d-%m') AS display_date,
                    COALESCE(SUM(b.net_amount),0) AS bill_amount,
                    COALESCE(SUM(b.paid_amount),0) AS paid_amount
             FROM bills b
             WHERE $where
             GROUP BY DATE(b.created_at)
             ORDER BY DATE(b.created_at) DESC
             LIMIT 10",
            $types,
            $params
        );
    }

    private function purposeMix(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $types = '';
        $params = [];
        $where = $this->ledgerWhere($businessId, $userId, $isAdmin, $filters, 'cl', $types, $params, true);

        return $this->queryRows(
            "SELECT COALESCE(NULLIF(cl.reference_type,''), 'ledger') AS purpose_name,
                    COALESCE(SUM(cl.debit),0) AS debit_amount,
                    COALESCE(SUM(cl.credit),0) AS credit_amount,
                    COALESCE(SUM(cl.debit + cl.credit),0) AS total_amount
             FROM customer_ledger cl
             WHERE $where
             GROUP BY purpose_name
             ORDER BY total_amount DESC
             LIMIT 8",
            $types,
            $params
        );
    }

    public function customerSummary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $bTypes = '';
        $bParams = [];
        $bWhere = $this->billWhere($businessId, $userId, $isAdmin, $filters, 'b', $bTypes, $bParams);

        $types = $bTypes;
        $params = $bParams;

        $where = 'c.business_id = ?';
        $types .= 'i';
        $params[] = $businessId;

        if (!empty($filters['customer_id'])) {
            $where .= ' AND c.customer_id = ?';
            $types .= 'i';
            $params[] = (int)$filters['customer_id'];
        }

        if (isset($filters['customer_status']) && $filters['customer_status'] !== '') {
            $where .= ' AND c.status = ?';
            $types .= 'i';
            $params[] = (int)$filters['customer_status'];
        }

        $where .= $this->customerSearchClause($filters, $types, $params, 'c');

        if (($filters['ledger_status'] ?? '') === 'outstanding') {
            $where .= ' AND COALESCE(co.balance_amount, c.opening_outstanding, 0) > 0';
        }

        if (($filters['ledger_status'] ?? '') === 'clear') {
            $where .= ' AND COALESCE(co.balance_amount, c.opening_outstanding, 0) <= 0';
        }

        $sql = "SELECT
                    c.customer_id,
                    c.customer_name,
                    c.mobile,
                    c.email,
                    c.gstin,
                    c.status,
                    c.opening_outstanding,
                    c.loyalty_points,
                    COALESCE(b.bill_count, 0) AS bill_count,
                    COALESCE(b.period_bill_amount, 0) AS period_bill_amount,
                    COALESCE(b.period_paid_amount, 0) AS period_paid_amount,
                    COALESCE(co.total_bill_amount, 0) AS total_bill_amount,
                    COALESCE(co.total_paid_amount, 0) AS total_paid_amount,
                    COALESCE(co.balance_amount, c.opening_outstanding, 0) AS balance_amount,
                    b.last_bill_datetime,
                    CASE WHEN b.last_bill_datetime IS NULL THEN '-' ELSE DATE_FORMAT(b.last_bill_datetime, '%d-%m-%Y %h:%i %p') END AS last_bill_display
                FROM customers c
                LEFT JOIN customer_outstanding co
                    ON co.business_id = c.business_id
                   AND co.customer_id = c.customer_id
                LEFT JOIN (
                    SELECT b.customer_id,
                           COUNT(*) AS bill_count,
                           COALESCE(SUM(b.net_amount),0) AS period_bill_amount,
                           COALESCE(SUM(b.paid_amount),0) AS period_paid_amount,
                           MAX(b.created_at) AS last_bill_datetime
                    FROM bills b
                    WHERE $bWhere
                      AND b.customer_id IS NOT NULL
                    GROUP BY b.customer_id
                ) b ON b.customer_id = c.customer_id
                WHERE $where";

        return $this->paginate($sql, $types, $params, $filters, [
            'customer_name' => 'customer_name',
            'mobile' => 'mobile',
            'bill_count' => 'bill_count',
            'total_bill_amount' => 'total_bill_amount',
            'total_paid_amount' => 'total_paid_amount',
            'balance_amount' => 'balance_amount',
            'last_bill' => 'last_bill_datetime',
        ], 'customer_name');
    }

    public function ledgerEntries(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $nameExpr = $this->userNameExpr('u');
        $types = '';
        $params = [];
        $where = $this->ledgerWhere($businessId, $userId, $isAdmin, $filters, 'cl', $types, $params, true);

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (c.customer_name LIKE ? OR c.mobile LIKE ? OR c.gstin LIKE ? OR cl.remarks LIKE ? OR b.bill_no LIKE ? OR b.order_no LIKE ?)";
            $types .= 'ssssss';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        $sql = "SELECT
                    cl.customer_ledger_id,
                    cl.customer_id,
                    c.customer_name,
                    c.mobile,
                    c.gstin,
                    cl.reference_type,
                    cl.reference_id,
                    cl.debit,
                    cl.credit,
                    cl.balance,
                    cl.remarks,
                    cl.created_at AS entry_datetime,
                    DATE_FORMAT(cl.created_at, '%d-%m-%Y %h:%i %p') AS entry_display,
                    COALESCE(b.bill_no, CONCAT('#', cl.reference_id)) AS reference_no,
                    b.order_no,
                    br.branch_name,
                    br.floor_name,
                    $nameExpr AS created_by_name
                FROM customer_ledger cl
                INNER JOIN customers c
                    ON c.customer_id = cl.customer_id
                   AND c.business_id = cl.business_id
                LEFT JOIN bills b
                    ON b.bill_id = cl.reference_id
                   AND b.business_id = cl.business_id
                   AND cl.reference_type LIKE 'bill%'
                LEFT JOIN branches br
                    ON br.branch_id = cl.branch_id
                   AND br.business_id = cl.business_id
                LEFT JOIN users u
                    ON u.user_id = cl.created_by
                   AND u.business_id = cl.business_id
                WHERE $where";

        return $this->paginate($sql, $types, $params, $filters, [
            'date' => 'entry_datetime',
            'customer_name' => 'customer_name',
            'reference_type' => 'reference_type',
            'debit' => 'debit',
            'credit' => 'credit',
            'balance' => 'balance',
        ], 'entry_datetime');
    }

    public function billHistory(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $nameExpr = $this->userNameExpr('u');
        $types = '';
        $params = [];
        $where = $this->billWhere($businessId, $userId, $isAdmin, $filters, 'b', $types, $params);

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (c.customer_name LIKE ? OR c.mobile LIKE ? OR b.bill_no LIKE ? OR b.order_no LIKE ? OR b.customer_name LIKE ? OR b.customer_mobile LIKE ?)";
            $types .= 'ssssss';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        $sql = "SELECT
                    b.bill_id,
                    b.bill_no,
                    b.order_no,
                    b.bill_date,
                    b.bill_time,
                    DATE_FORMAT(b.created_at, '%d-%m-%Y %h:%i %p') AS bill_display,
                    b.customer_id,
                    COALESCE(c.customer_name, b.customer_name, 'Walk-in Customer') AS customer_name,
                    COALESCE(c.mobile, b.customer_mobile, '') AS mobile,
                    b.net_amount,
                    b.paid_amount,
                    b.balance_amount,
                    b.payment_status,
                    b.updated_status,
                    b.bill_status,
                    br.branch_name,
                    br.floor_name,
                    $nameExpr AS created_by_name,
                    b.created_at
                FROM bills b
                LEFT JOIN customers c
                    ON c.customer_id = b.customer_id
                   AND c.business_id = b.business_id
                LEFT JOIN branches br
                    ON br.branch_id = b.branch_id
                   AND br.business_id = b.business_id
                LEFT JOIN users u
                    ON u.user_id = b.created_by
                   AND u.business_id = b.business_id
                WHERE $where";

        return $this->paginate($sql, $types, $params, $filters, [
            'date' => 'created_at',
            'customer_name' => 'customer_name',
            'bill_no' => 'bill_no',
            'net_amount' => 'net_amount',
            'paid_amount' => 'paid_amount',
            'balance_amount' => 'balance_amount',
            'payment_status' => 'payment_status',
        ], 'created_at');
    }

    public function outstanding(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $filters['ledger_status'] = 'outstanding';
        return $this->customerSummary($businessId, $userId, $isAdmin, $filters);
    }

    private function previousBalance(int $businessId, int $userId, bool $isAdmin, int $customerId, array $filters, float $opening): float
    {
        if (empty($filters['from_date'])) {
            return $opening;
        }

        $types = 'ii';
        $params = [$businessId, $customerId];
        $where = "cl.business_id = ? AND cl.customer_id = ? AND COALESCE(cl.reference_type, '') <> 'opening' AND DATE(cl.created_at) < ?";

        $types .= 's';
        $params[] = (string)$filters['from_date'];

        if (!empty($filters['branch_id'])) {
            $where .= " AND cl.branch_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['branch_id'];
        } else {
            $where .= $this->allowedBranchClause('cl', $businessId, $userId, $isAdmin, $types, $params);
        }

        $row = $this->queryOne(
            "SELECT COALESCE(SUM(cl.debit),0) AS debit_total,
                    COALESCE(SUM(cl.credit),0) AS credit_total
             FROM customer_ledger cl
             WHERE $where",
            $types,
            $params
        );

        return round($opening + (float)($row['debit_total'] ?? 0) - (float)($row['credit_total'] ?? 0), 2);
    }

    public function statement(int $businessId, int $userId, bool $isAdmin, int $customerId, array $filters): array
    {
        $customer = $this->queryOne(
            "SELECT c.*,
                    COALESCE(co.total_bill_amount, 0) AS total_bill_amount,
                    COALESCE(co.total_paid_amount, 0) AS total_paid_amount,
                    COALESCE(co.balance_amount, c.opening_outstanding, 0) AS current_balance
             FROM customers c
             LEFT JOIN customer_outstanding co
                ON co.business_id = c.business_id
               AND co.customer_id = c.customer_id
             WHERE c.business_id = ? AND c.customer_id = ?
             LIMIT 1",
            'ii',
            [$businessId, $customerId]
        );

        if (!$customer) {
            return ['customer' => [], 'summary' => [], 'rows' => []];
        }

        $baseOpening = round((float)($customer['opening_outstanding'] ?? 0), 2);
        $openingBalance = $this->previousBalance($businessId, $userId, $isAdmin, $customerId, $filters, $baseOpening);

        $types = 'ii';
        $params = [$businessId, $customerId];
        $where = "cl.business_id = ? AND cl.customer_id = ? AND COALESCE(cl.reference_type, '') <> 'opening'";

        if (!empty($filters['branch_id'])) {
            $where .= " AND cl.branch_id = ?";
            $types .= 'i';
            $params[] = (int)$filters['branch_id'];
        } else {
            $where .= $this->allowedBranchClause('cl', $businessId, $userId, $isAdmin, $types, $params);
        }

        if (!empty($filters['from_date'])) {
            $where .= " AND DATE(cl.created_at) >= ?";
            $types .= 's';
            $params[] = (string)$filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where .= " AND DATE(cl.created_at) <= ?";
            $types .= 's';
            $params[] = (string)$filters['to_date'];
        }

        if (!empty($filters['reference_type']) && $filters['reference_type'] !== 'opening') {
            $where .= " AND cl.reference_type = ?";
            $types .= 's';
            $params[] = (string)$filters['reference_type'];
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string)$filters['search']) . '%';
            $where .= " AND (
                c.customer_name LIKE ?
                OR c.mobile LIKE ?
                OR c.gstin LIKE ?
                OR cl.remarks LIKE ?
                OR b.bill_no LIKE ?
                OR b.order_no LIKE ?
            )";
            $types .= 'ssssss';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        $nameExpr = $this->userNameExpr('u');

        $ledgerRows = $this->queryRows(
            "SELECT
                    cl.customer_ledger_id,
                    cl.customer_id,
                    c.customer_name,
                    c.mobile,
                    c.gstin,
                    cl.reference_type,
                    cl.reference_id,
                    cl.debit,
                    cl.credit,
                    cl.remarks,
                    cl.created_at AS entry_datetime,
                    DATE_FORMAT(cl.created_at, '%d-%m-%Y %h:%i %p') AS entry_display,
                    COALESCE(b.bill_no, CONCAT('#', cl.reference_id)) AS reference_no,
                    b.order_no,
                    br.branch_name,
                    br.floor_name,
                    $nameExpr AS created_by_name
             FROM customer_ledger cl
             INNER JOIN customers c
                ON c.customer_id = cl.customer_id
               AND c.business_id = cl.business_id
             LEFT JOIN bills b
                ON b.bill_id = cl.reference_id
               AND b.business_id = cl.business_id
               AND cl.reference_type LIKE 'bill%'
             LEFT JOIN branches br
                ON br.branch_id = cl.branch_id
               AND br.business_id = cl.business_id
             LEFT JOIN users u
                ON u.user_id = cl.created_by
               AND u.business_id = cl.business_id
             WHERE $where
             ORDER BY cl.created_at ASC, cl.customer_ledger_id ASC",
            $types,
            $params
        );

        $statementRows = [];
        $runningBalance = $openingBalance;

        if (abs($openingBalance) > 0) {
            $statementRows[] = [
                'customer_ledger_id' => 0,
                'customer_id' => $customerId,
                'customer_name' => $customer['customer_name'] ?? '',
                'mobile' => $customer['mobile'] ?? '',
                'gstin' => $customer['gstin'] ?? '',
                'reference_type' => 'opening',
                'display_type' => 'Opening Balance',
                'reference_id' => 0,
                'debit' => 0,
                'credit' => 0,
                'remarks' => empty($filters['from_date']) ? 'Customer opening balance' : 'Opening / previous balance before selected period',
                'entry_datetime' => '',
                'entry_display' => 'Opening Balance',
                'reference_no' => '-',
                'order_no' => '',
                'branch_name' => '',
                'floor_name' => '',
                'created_by_name' => 'Opening',
                'balance' => $runningBalance,
                'is_opening' => 1,
            ];
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($ledgerRows as $row) {
            $debit = round((float)($row['debit'] ?? 0), 2);
            $credit = round((float)($row['credit'] ?? 0), 2);

            $totalDebit += $debit;
            $totalCredit += $credit;

            $runningBalance = round($runningBalance + $debit - $credit, 2);

            $displayType = strtolower((string)($row['reference_type'] ?? 'ledger'));
            if (in_array($displayType, ['bill_payment', 'payment', 'partial_payment'], true)) {
                $displayType = 'Payment / Credit';
            } elseif ($displayType === 'bill') {
                $displayType = 'Bill / Debit';
            } elseif (strpos($displayType, 'reverse') !== false || strpos($displayType, 'cancel') !== false) {
                $displayType = 'Reversal';
            } elseif ($credit > 0 && $debit <= 0) {
                $displayType = 'Credit';
            } elseif ($debit > 0) {
                $displayType = 'Debit';
            }

            $row['display_type'] = ucwords(str_replace('_', ' ', $displayType));
            $row['debit'] = $debit;
            $row['credit'] = $credit;
            $row['balance'] = $runningBalance;
            $row['is_opening'] = 0;

            $statementRows[] = $row;
        }

        return [
            'customer' => $customer,
            'summary' => [
                'opening_balance' => round($openingBalance, 2),
                'total_debit' => round($totalDebit, 2),
                'total_credit' => round($totalCredit, 2),
                'closing_balance' => round($runningBalance, 2),
                'current_balance' => round($runningBalance, 2),
            ],
            'rows' => $statementRows,
        ];
    }

    public function verificationRows(int $businessId): array
    {
        return $this->queryRows(
            "SELECT
                c.customer_id,
                c.customer_name,
                c.opening_outstanding,
                COALESCE(SUM(CASE WHEN COALESCE(cl.reference_type,'') <> 'opening' THEN cl.debit ELSE 0 END),0) AS ledger_debit,
                COALESCE(SUM(CASE WHEN COALESCE(cl.reference_type,'') <> 'opening' THEN cl.credit ELSE 0 END),0) AS ledger_credit,
                ROUND(c.opening_outstanding
                    + COALESCE(SUM(CASE WHEN COALESCE(cl.reference_type,'') <> 'opening' THEN cl.debit ELSE 0 END),0)
                    - COALESCE(SUM(CASE WHEN COALESCE(cl.reference_type,'') <> 'opening' THEN cl.credit ELSE 0 END),0), 2) AS calculated_closing,
                COALESCE(co.balance_amount, c.opening_outstanding, 0) AS outstanding_balance,
                ROUND(
                    ROUND(c.opening_outstanding
                        + COALESCE(SUM(CASE WHEN COALESCE(cl.reference_type,'') <> 'opening' THEN cl.debit ELSE 0 END),0)
                        - COALESCE(SUM(CASE WHEN COALESCE(cl.reference_type,'') <> 'opening' THEN cl.credit ELSE 0 END),0), 2)
                    - COALESCE(co.balance_amount, c.opening_outstanding, 0), 2
                ) AS difference
             FROM customers c
             LEFT JOIN customer_ledger cl
                ON cl.business_id = c.business_id
               AND cl.customer_id = c.customer_id
             LEFT JOIN customer_outstanding co
                ON co.business_id = c.business_id
               AND co.customer_id = c.customer_id
             WHERE c.business_id = ?
             GROUP BY c.customer_id, c.customer_name, c.opening_outstanding, co.balance_amount
             ORDER BY c.customer_id",
            'i',
            [$businessId]
        );
    }
}
?>
