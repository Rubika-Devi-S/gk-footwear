<?php
/**
 * GK Footwear POS - Payment Ledger Report Model
 * Uses existing tables only. No schema changes required.
 */

class PaymentLedgerReport
{
    private $conn;
    private $userNameExpressionCache = array();

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
        if (function_exists('mysqli_set_charset')) {
            @mysqli_set_charset($this->conn, 'utf8mb4');
        }
    }

    private function tableExists(string $tableName): bool
    {
        $sql = "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        $row = $this->fetchOne($sql, 's', array($tableName));
        return ((int)($row['total'] ?? 0)) > 0;
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $sql = "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
        $row = $this->fetchOne($sql, 'ss', array($tableName, $columnName));
        return ((int)($row['total'] ?? 0)) > 0;
    }

    private function userNameExpr(string $alias): string
    {
        $cacheKey = $alias;
        if (isset($this->userNameExpressionCache[$cacheKey])) {
            return $this->userNameExpressionCache[$cacheKey];
        }

        $parts = array();
        foreach (array('full_name', 'name', 'username', 'email') as $column) {
            if ($this->columnExists('users', $column)) {
                $parts[] = "NULLIF($alias.`$column`, '')";
            }
        }

        if (!$parts) {
            $expr = "'System'";
        } else {
            $parts[] = "'System'";
            $expr = 'COALESCE(' . implode(', ', $parts) . ')';
        }

        $this->userNameExpressionCache[$cacheKey] = $expr;
        return $expr;
    }

    private function fetchAll(string $sql, string $types = '', array $params = array()): array
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new RuntimeException(mysqli_error($this->conn));
        }

        if ($types !== '' && $params) {
            $refs = array();
            foreach ($params as $key => $value) {
                $refs[$key] = &$params[$key];
            }
            mysqli_stmt_bind_param($stmt, $types, ...$refs);
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException($error);
        }

        $result = mysqli_stmt_get_result($stmt);
        $rows = array();
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        mysqli_stmt_close($stmt);
        return $rows;
    }

    private function fetchOne(string $sql, string $types = '', array $params = array()): array
    {
        $rows = $this->fetchAll($sql, $types, $params);
        return $rows ? $rows[0] : array();
    }

    private function intVal($value): int
    {
        return max(0, (int)$value);
    }

    private function moneyVal($value): float
    {
        return (float)str_replace(',', '', (string)$value);
    }

    public function normalizeFilters(array $input): array
    {
        $today = date('Y-m-d');
        $firstDay = date('Y-m-01');

        $from = trim((string)($input['from_date'] ?? $firstDay));
        $to = trim((string)($input['to_date'] ?? $today));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = $firstDay;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $today;
        }

        $perPage = (int)($input['per_page'] ?? 25);
        if (!in_array($perPage, array(10, 25, 50, 100, 250), true)) {
            $perPage = 25;
        }

        $page = max(1, (int)($input['page'] ?? 1));
        $sortOrder = strtolower((string)($input['sort_order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return array(
            'from_date' => $from,
            'to_date' => $to,
            'branch_id' => $this->intVal($input['branch_id'] ?? 0),
            'customer_id' => $this->intVal($input['customer_id'] ?? 0),
            'cashier_id' => $this->intVal($input['cashier_id'] ?? ($input['created_by'] ?? 0)),
            'payment_method_id' => $this->intVal($input['payment_method_id'] ?? 0),
            'payment_status' => trim((string)($input['payment_status'] ?? '')),
            'record_status' => trim((string)($input['record_status'] ?? '')),
            'transaction_type' => trim((string)($input['transaction_type'] ?? '')),
            'search' => trim((string)($input['search'] ?? '')),
            'min_amount' => $this->moneyVal($input['min_amount'] ?? 0),
            'max_amount' => $this->moneyVal($input['max_amount'] ?? 0),
            'sort_by' => trim((string)($input['sort_by'] ?? 'collected_at')),
            'sort_order' => $sortOrder,
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        );
    }

    public function masters(int $businessId, int $userId, bool $isAdmin): array
    {
        $branches = $this->branches($businessId, $userId, $isAdmin);

        $customers = $this->fetchAll("
            SELECT customer_id, customer_name, mobile
            FROM customers
            WHERE business_id = ?
              AND status = 1
            ORDER BY customer_name ASC
            LIMIT 1000
        ", 'i', array($businessId));

        $userExpr = $this->userNameExpr('u');
        $users = $this->fetchAll("
            SELECT u.user_id, $userExpr AS user_name, u.username
            FROM users u
            WHERE u.business_id = ?
              AND u.status = 1
            ORDER BY user_name ASC
        ", 'i', array($businessId));

        $methods = $this->fetchAll("
            SELECT payment_method_id, payment_method_name, method_type
            FROM payment_methods
            WHERE business_id = ?
              AND status = 1
            ORDER BY payment_method_name ASC
        ", 'i', array($businessId));

        return array(
            'branches' => $branches,
            'customers' => $customers,
            'users' => $users,
            'payment_methods' => $methods,
            'payment_statuses' => array('pending', 'partial', 'paid', 'cancelled'),
            'record_statuses' => array('received', 'reversed', 'cancelled'),
            'transaction_types' => array('bill', 'payment', 'partial_payment', 'reverse', 'adjustment', 'opening'),
        );
    }

    public function branches(int $businessId, int $userId, bool $isAdmin): array
    {
        if ($isAdmin || $userId <= 0 || !$this->tableExists('user_branch_access')) {
            return $this->fetchAll("
                SELECT branch_id, branch_code, branch_name, floor_name
                FROM branches
                WHERE business_id = ?
                  AND status = 1
                ORDER BY branch_name ASC, floor_name ASC
            ", 'i', array($businessId));
        }

        return $this->fetchAll("
            SELECT DISTINCT b.branch_id, b.branch_code, b.branch_name, b.floor_name
            FROM branches b
            INNER JOIN user_branch_access uba
                ON uba.branch_id = b.branch_id
               AND uba.business_id = b.business_id
               AND uba.user_id = ?
               AND uba.access_status = 1
            WHERE b.business_id = ?
              AND b.status = 1
            ORDER BY b.branch_name ASC, b.floor_name ASC
        ", 'ii', array($userId, $businessId));
    }

    private function accessibleBranchIds(int $businessId, int $userId, bool $isAdmin): array
    {
        if ($isAdmin || $userId <= 0) {
            return array();
        }

        $rows = $this->branches($businessId, $userId, false);
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int)$row['branch_id'];
        }
        return $ids;
    }

    private function appendBranchAccess(string $alias, int $businessId, int $userId, bool $isAdmin, array &$where, string &$types, array &$params): void
    {
        $ids = $this->accessibleBranchIds($businessId, $userId, $isAdmin);
        if (!$ids) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where[] = "$alias.branch_id IN ($placeholders)";
        foreach ($ids as $id) {
            $types .= 'i';
            $params[] = $id;
        }
    }

    private function paymentWhere(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $where = array('bp.business_id = ?');
        $types = 'i';
        $params = array($businessId);

        $where[] = 'bp.collected_at >= ?';
        $types .= 's';
        $params[] = $filters['from_date'] . ' 00:00:00';

        $where[] = 'bp.collected_at <= ?';
        $types .= 's';
        $params[] = $filters['to_date'] . ' 23:59:59';

        if ($filters['branch_id'] > 0) {
            $where[] = 'bp.branch_id = ?';
            $types .= 'i';
            $params[] = $filters['branch_id'];
        }

        $this->appendBranchAccess('bp', $businessId, $userId, $isAdmin, $where, $types, $params);

        if ($filters['customer_id'] > 0) {
            $where[] = 'b.customer_id = ?';
            $types .= 'i';
            $params[] = $filters['customer_id'];
        }

        if ($filters['cashier_id'] > 0) {
            $where[] = 'bp.collected_by = ?';
            $types .= 'i';
            $params[] = $filters['cashier_id'];
        }

        if ($filters['payment_method_id'] > 0) {
            $where[] = 'bp.payment_method_id = ?';
            $types .= 'i';
            $params[] = $filters['payment_method_id'];
        }

        if ($filters['payment_status'] !== '') {
            $where[] = 'b.payment_status = ?';
            $types .= 's';
            $params[] = $filters['payment_status'];
        }

        if ($filters['record_status'] !== '') {
            $where[] = 'bp.payment_status = ?';
            $types .= 's';
            $params[] = $filters['record_status'];
        }

        if ($filters['min_amount'] > 0) {
            $where[] = 'bp.paid_amount >= ?';
            $types .= 'd';
            $params[] = $filters['min_amount'];
        }

        if ($filters['max_amount'] > 0) {
            $where[] = 'bp.paid_amount <= ?';
            $types .= 'd';
            $params[] = $filters['max_amount'];
        }

        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(b.bill_no LIKE ? OR b.order_no LIKE ? OR b.customer_name LIKE ? OR b.customer_mobile LIKE ? OR c.customer_name LIKE ? OR c.mobile LIKE ? OR bp.reference_no LIKE ? OR pm.payment_method_name LIKE ?)";
            $types .= 'ssssssss';
            array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
        }

        return array($where, $types, $params);
    }

    private function paymentBaseSelect(): string
    {
        $cashierExpr = $this->userNameExpr('u');
        return "
            FROM bill_payments bp
            INNER JOIN bills b
                ON b.bill_id = bp.bill_id
               AND b.business_id = bp.business_id
               AND b.branch_id = bp.branch_id
            LEFT JOIN customers c
                ON c.customer_id = b.customer_id
               AND c.business_id = b.business_id
            LEFT JOIN branches br
                ON br.branch_id = bp.branch_id
               AND br.business_id = bp.business_id
            LEFT JOIN payment_methods pm
                ON pm.payment_method_id = bp.payment_method_id
               AND pm.business_id = bp.business_id
            LEFT JOIN users u
                ON u.user_id = bp.collected_by
               AND u.business_id = bp.business_id
        ";
    }

    public function paymentSummary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        list($where, $types, $params) = $this->paymentWhere($businessId, $userId, $isAdmin, $filters);
        $base = $this->paymentBaseSelect();

        $row = $this->fetchOne("
            SELECT
                COUNT(*) AS total_transactions,
                COALESCE(SUM(CASE WHEN bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS total_collected,
                COALESCE(SUM(CASE WHEN pm.method_type = 'cash' AND bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS cash_amount,
                COALESCE(SUM(CASE WHEN pm.method_type = 'upi' AND bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS upi_amount,
                COALESCE(SUM(CASE WHEN pm.method_type = 'card' AND bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS card_amount,
                COALESCE(SUM(CASE WHEN pm.method_type = 'cheque' AND bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS cheque_amount,
                COALESCE(SUM(CASE WHEN pm.method_type = 'credit' AND bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS credit_amount,
                COALESCE(SUM(CASE WHEN bp.payment_status <> 'received' THEN bp.paid_amount ELSE 0 END), 0) AS reversed_cancelled_amount,
                COUNT(DISTINCT b.customer_id) AS unique_customers,
                COUNT(DISTINCT bp.bill_id) AS paid_bills
            $base
            WHERE " . implode(' AND ', $where) . "
        ", $types, $params);

        $outstanding = $this->billOutstandingSummary($businessId, $userId, $isAdmin, $filters);
        $row['bill_outstanding'] = $outstanding['bill_outstanding'] ?? 0;
        $row['pending_bills'] = $outstanding['pending_bills'] ?? 0;
        $row['partial_bills'] = $outstanding['partial_bills'] ?? 0;
        $row['customer_outstanding'] = $this->customerOutstandingTotal($businessId, $userId, $isAdmin, $filters);
        return $row;
    }

    private function billOutstandingSummary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $where = array('b.business_id = ?', "b.bill_status = 'active'");
        $types = 'i';
        $params = array($businessId);

        $where[] = 'b.bill_date >= ?';
        $types .= 's';
        $params[] = $filters['from_date'];

        $where[] = 'b.bill_date <= ?';
        $types .= 's';
        $params[] = $filters['to_date'];

        if ($filters['branch_id'] > 0) {
            $where[] = 'b.branch_id = ?';
            $types .= 'i';
            $params[] = $filters['branch_id'];
        }

        $this->appendBranchAccess('b', $businessId, $userId, $isAdmin, $where, $types, $params);

        if ($filters['customer_id'] > 0) {
            $where[] = 'b.customer_id = ?';
            $types .= 'i';
            $params[] = $filters['customer_id'];
        }

        if ($filters['cashier_id'] > 0) {
            $where[] = 'b.created_by = ?';
            $types .= 'i';
            $params[] = $filters['cashier_id'];
        }

        if ($filters['payment_status'] !== '') {
            $where[] = 'b.payment_status = ?';
            $types .= 's';
            $params[] = $filters['payment_status'];
        }

        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(b.bill_no LIKE ? OR b.order_no LIKE ? OR b.customer_name LIKE ? OR b.customer_mobile LIKE ?)";
            $types .= 'ssss';
            array_push($params, $like, $like, $like, $like);
        }

        return $this->fetchOne("
            SELECT
                COALESCE(SUM(CASE WHEN b.payment_status IN ('pending','partial') THEN b.balance_amount ELSE 0 END), 0) AS bill_outstanding,
                COALESCE(SUM(CASE WHEN b.payment_status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_bills,
                COALESCE(SUM(CASE WHEN b.payment_status = 'partial' THEN 1 ELSE 0 END), 0) AS partial_bills
            FROM bills b
            WHERE " . implode(' AND ', $where) . "
        ", $types, $params);
    }

    private function customerOutstandingTotal(int $businessId, int $userId, bool $isAdmin, array $filters): float
    {
        $where = array('co.business_id = ?');
        $types = 'i';
        $params = array($businessId);

        if ($filters['customer_id'] > 0) {
            $where[] = 'co.customer_id = ?';
            $types .= 'i';
            $params[] = $filters['customer_id'];
        }

        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(c.customer_name LIKE ? OR c.mobile LIKE ?)";
            $types .= 'ss';
            array_push($params, $like, $like);
        }

        $row = $this->fetchOne("
            SELECT COALESCE(SUM(co.balance_amount), 0) AS total_balance
            FROM customer_outstanding co
            LEFT JOIN customers c
                ON c.customer_id = co.customer_id
               AND c.business_id = co.business_id
            WHERE " . implode(' AND ', $where) . "
        ", $types, $params);

        return (float)($row['total_balance'] ?? 0);
    }

    public function payments(int $businessId, int $userId, bool $isAdmin, array $filters, bool $paginate = true): array
    {
        list($where, $types, $params) = $this->paymentWhere($businessId, $userId, $isAdmin, $filters);
        $base = $this->paymentBaseSelect();
        $whereSql = implode(' AND ', $where);

        $sortMap = array(
            'collected_at' => 'bp.collected_at',
            'bill_no' => 'b.bill_no',
            'customer_name' => 'customer_name',
            'paid_amount' => 'bp.paid_amount',
            'balance_amount' => 'b.balance_amount',
            'payment_method_name' => 'pm.payment_method_name',
            'cashier_name' => 'cashier_name',
        );
        $sortBy = $sortMap[$filters['sort_by']] ?? 'bp.collected_at';
        $sortOrder = $filters['sort_order'] === 'asc' ? 'ASC' : 'DESC';

        $count = 0;
        if ($paginate) {
            $countRow = $this->fetchOne("SELECT COUNT(*) AS total $base WHERE $whereSql", $types, $params);
            $count = (int)($countRow['total'] ?? 0);
        }

        $cashierExpr = $this->userNameExpr('u');
        $limitSql = '';
        $rowTypes = $types;
        $rowParams = $params;
        if ($paginate) {
            $limitSql = ' LIMIT ? OFFSET ?';
            $rowTypes .= 'ii';
            $rowParams[] = $filters['per_page'];
            $rowParams[] = $filters['offset'];
        }

        $rows = $this->fetchAll("
            SELECT
                bp.payment_id,
                bp.bill_id,
                bp.branch_id,
                b.bill_no,
                b.order_no,
                CONCAT(DATE_FORMAT(b.bill_date, '%d-%m-%Y'), ' ', DATE_FORMAT(COALESCE(b.bill_time, TIME(b.created_at)), '%h:%i %p')) AS bill_datetime,
                DATE_FORMAT(bp.collected_at, '%d-%m-%Y %h:%i %p') AS collected_datetime,
                bp.collected_at,
                br.branch_name,
                br.floor_name,
                COALESCE(NULLIF(c.customer_name, ''), NULLIF(b.customer_name, ''), 'Walk-in Customer') AS customer_name,
                COALESCE(NULLIF(c.mobile, ''), NULLIF(b.customer_mobile, ''), '') AS customer_mobile,
                pm.payment_method_name,
                pm.method_type,
                bp.paid_amount,
                bp.reference_no,
                bp.payment_note,
                bp.payment_status AS record_status,
                b.net_amount,
                b.paid_amount AS bill_paid_amount,
                b.balance_amount,
                b.payment_status AS bill_payment_status,
                b.bill_status,
                $cashierExpr AS cashier_name
            $base
            WHERE $whereSql
            ORDER BY $sortBy $sortOrder, bp.payment_id DESC
            $limitSql
        ", $rowTypes, $rowParams);

        return array(
            'rows' => $rows,
            'total' => $paginate ? $count : count($rows),
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total_pages' => $paginate ? max(1, (int)ceil($count / max(1, $filters['per_page']))) : 1,
        );
    }

    private function ledgerWhere(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        $where = array('pl.business_id = ?');
        $types = 'i';
        $params = array($businessId);

        $where[] = 'pl.created_at >= ?';
        $types .= 's';
        $params[] = $filters['from_date'] . ' 00:00:00';

        $where[] = 'pl.created_at <= ?';
        $types .= 's';
        $params[] = $filters['to_date'] . ' 23:59:59';

        if ($filters['branch_id'] > 0) {
            $where[] = 'pl.branch_id = ?';
            $types .= 'i';
            $params[] = $filters['branch_id'];
        }

        $this->appendBranchAccess('pl', $businessId, $userId, $isAdmin, $where, $types, $params);

        if ($filters['customer_id'] > 0) {
            $where[] = 'pl.customer_id = ?';
            $types .= 'i';
            $params[] = $filters['customer_id'];
        }

        if ($filters['cashier_id'] > 0) {
            $where[] = 'pl.created_by = ?';
            $types .= 'i';
            $params[] = $filters['cashier_id'];
        }

        if ($filters['payment_method_id'] > 0) {
            $where[] = 'pl.payment_method_id = ?';
            $types .= 'i';
            $params[] = $filters['payment_method_id'];
        }

        if ($filters['transaction_type'] !== '') {
            $where[] = 'pl.transaction_type = ?';
            $types .= 's';
            $params[] = $filters['transaction_type'];
        }

        if ($filters['min_amount'] > 0) {
            $where[] = '(pl.debit >= ? OR pl.credit >= ?)';
            $types .= 'dd';
            $params[] = $filters['min_amount'];
            $params[] = $filters['min_amount'];
        }

        if ($filters['max_amount'] > 0) {
            $where[] = '(pl.debit <= ? OR pl.credit <= ?)';
            $types .= 'dd';
            $params[] = $filters['max_amount'];
            $params[] = $filters['max_amount'];
        }

        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(b.bill_no LIKE ? OR c.customer_name LIKE ? OR c.mobile LIKE ? OR pl.remarks LIKE ? OR pm.payment_method_name LIKE ?)";
            $types .= 'sssss';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return array($where, $types, $params);
    }

    private function ledgerBaseSelect(): string
    {
        $userExpr = $this->userNameExpr('u');
        return "
            FROM payment_ledger pl
            LEFT JOIN bills b
                ON b.bill_id = pl.bill_id
               AND b.business_id = pl.business_id
            LEFT JOIN customers c
                ON c.customer_id = pl.customer_id
               AND c.business_id = pl.business_id
            LEFT JOIN branches br
                ON br.branch_id = pl.branch_id
               AND br.business_id = pl.business_id
            LEFT JOIN payment_methods pm
                ON pm.payment_method_id = pl.payment_method_id
               AND pm.business_id = pl.business_id
            LEFT JOIN users u
                ON u.user_id = pl.created_by
               AND u.business_id = pl.business_id
        ";
    }

    public function ledgerEntries(int $businessId, int $userId, bool $isAdmin, array $filters, bool $paginate = true): array
    {
        list($where, $types, $params) = $this->ledgerWhere($businessId, $userId, $isAdmin, $filters);
        $base = $this->ledgerBaseSelect();
        $whereSql = implode(' AND ', $where);

        $count = 0;
        if ($paginate) {
            $countRow = $this->fetchOne("SELECT COUNT(*) AS total $base WHERE $whereSql", $types, $params);
            $count = (int)($countRow['total'] ?? 0);
        }

        $sortMap = array(
            'collected_at' => 'pl.created_at',
            'customer_name' => 'customer_name',
            'paid_amount' => 'pl.credit',
            'balance_amount' => 'pl.balance',
            'payment_method_name' => 'pm.payment_method_name',
        );
        $sortBy = $sortMap[$filters['sort_by']] ?? 'pl.created_at';
        $sortOrder = $filters['sort_order'] === 'asc' ? 'ASC' : 'DESC';

        $userExpr = $this->userNameExpr('u');
        $limitSql = '';
        $rowTypes = $types;
        $rowParams = $params;
        if ($paginate) {
            $limitSql = ' LIMIT ? OFFSET ?';
            $rowTypes .= 'ii';
            $rowParams[] = $filters['per_page'];
            $rowParams[] = $filters['offset'];
        }

        $rows = $this->fetchAll("
            SELECT
                pl.ledger_id,
                pl.branch_id,
                pl.customer_id,
                pl.bill_id,
                pl.transaction_type,
                pl.debit,
                pl.credit,
                pl.balance,
                pl.remarks,
                DATE_FORMAT(pl.created_at, '%d-%m-%Y %h:%i %p') AS entry_datetime,
                b.bill_no,
                br.branch_name,
                br.floor_name,
                COALESCE(NULLIF(c.customer_name, ''), 'Walk-in Customer') AS customer_name,
                COALESCE(c.mobile, '') AS customer_mobile,
                pm.payment_method_name,
                pm.method_type,
                $userExpr AS created_by_name
            $base
            WHERE $whereSql
            ORDER BY $sortBy $sortOrder, pl.ledger_id DESC
            $limitSql
        ", $rowTypes, $rowParams);

        return array(
            'rows' => $rows,
            'total' => $paginate ? $count : count($rows),
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total_pages' => $paginate ? max(1, (int)ceil($count / max(1, $filters['per_page']))) : 1,
        );
    }

    public function outstanding(int $businessId, int $userId, bool $isAdmin, array $filters, bool $paginate = true): array
    {
        $where = array('co.business_id = ?');
        $types = 'i';
        $params = array($businessId);

        if ($filters['customer_id'] > 0) {
            $where[] = 'co.customer_id = ?';
            $types .= 'i';
            $params[] = $filters['customer_id'];
        }

        if ($filters['min_amount'] > 0) {
            $where[] = 'co.balance_amount >= ?';
            $types .= 'd';
            $params[] = $filters['min_amount'];
        }

        if ($filters['max_amount'] > 0) {
            $where[] = 'co.balance_amount <= ?';
            $types .= 'd';
            $params[] = $filters['max_amount'];
        }

        if ($filters['payment_status'] === 'paid') {
            $where[] = 'co.balance_amount <= 0';
        } elseif (in_array($filters['payment_status'], array('pending', 'partial'), true)) {
            $where[] = 'co.balance_amount > 0';
        }

        if ($filters['search'] !== '') {
            $like = '%' . $filters['search'] . '%';
            $where[] = "(c.customer_name LIKE ? OR c.mobile LIKE ?)";
            $types .= 'ss';
            array_push($params, $like, $like);
        }

        $whereSql = implode(' AND ', $where);
        $count = 0;
        if ($paginate) {
            $countRow = $this->fetchOne("
                SELECT COUNT(*) AS total
                FROM customer_outstanding co
                LEFT JOIN customers c ON c.customer_id = co.customer_id AND c.business_id = co.business_id
                WHERE $whereSql
            ", $types, $params);
            $count = (int)($countRow['total'] ?? 0);
        }

        $limitSql = '';
        $rowTypes = $types;
        $rowParams = $params;
        if ($paginate) {
            $limitSql = ' LIMIT ? OFFSET ?';
            $rowTypes .= 'ii';
            $rowParams[] = $filters['per_page'];
            $rowParams[] = $filters['offset'];
        }

        $rows = $this->fetchAll("
            SELECT
                co.customer_id,
                c.customer_name,
                c.mobile,
                c.opening_outstanding,
                co.total_bill_amount,
                co.total_paid_amount,
                co.balance_amount,
                DATE_FORMAT(co.updated_at, '%d-%m-%Y %h:%i %p') AS updated_datetime,
                (
                    SELECT COUNT(*)
                    FROM bills b
                    WHERE b.business_id = co.business_id
                      AND b.customer_id = co.customer_id
                      AND b.bill_status = 'active'
                ) AS bill_count,
                (
                    SELECT DATE_FORMAT(MAX(b.bill_date), '%d-%m-%Y')
                    FROM bills b
                    WHERE b.business_id = co.business_id
                      AND b.customer_id = co.customer_id
                      AND b.bill_status = 'active'
                ) AS last_bill_date
            FROM customer_outstanding co
            LEFT JOIN customers c
                ON c.customer_id = co.customer_id
               AND c.business_id = co.business_id
            WHERE $whereSql
            ORDER BY co.balance_amount DESC, c.customer_name ASC
            $limitSql
        ", $rowTypes, $rowParams);

        return array(
            'rows' => $rows,
            'total' => $paginate ? $count : count($rows),
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total_pages' => $paginate ? max(1, (int)ceil($count / max(1, $filters['per_page']))) : 1,
        );
    }

    public function dailySummary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        list($where, $types, $params) = $this->paymentWhere($businessId, $userId, $isAdmin, $filters);
        $base = $this->paymentBaseSelect();

        return $this->fetchAll("
            SELECT
                DATE(bp.collected_at) AS payment_date,
                DATE_FORMAT(bp.collected_at, '%d-%m-%Y') AS display_date,
                COUNT(*) AS payment_count,
                COUNT(DISTINCT bp.bill_id) AS bill_count,
                COUNT(DISTINCT b.customer_id) AS customer_count,
                COALESCE(SUM(CASE WHEN bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS total_collected,
                COALESCE(SUM(CASE WHEN pm.method_type = 'cash' AND bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS cash_amount,
                COALESCE(SUM(CASE WHEN pm.method_type = 'upi' AND bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS upi_amount,
                COALESCE(SUM(CASE WHEN pm.method_type = 'card' AND bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS card_amount,
                COALESCE(SUM(CASE WHEN bp.payment_status <> 'received' THEN bp.paid_amount ELSE 0 END), 0) AS cancelled_amount
            $base
            WHERE " . implode(' AND ', $where) . "
            GROUP BY DATE(bp.collected_at)
            ORDER BY payment_date ASC
        ", $types, $params);
    }

    public function methodSummary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        list($where, $types, $params) = $this->paymentWhere($businessId, $userId, $isAdmin, $filters);
        $base = $this->paymentBaseSelect();

        return $this->fetchAll("
            SELECT
                COALESCE(pm.payment_method_name, 'Unknown') AS payment_method_name,
                COALESCE(pm.method_type, 'other') AS method_type,
                COUNT(*) AS payment_count,
                COUNT(DISTINCT bp.bill_id) AS bill_count,
                COALESCE(SUM(CASE WHEN bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS collected_amount,
                COALESCE(SUM(CASE WHEN bp.payment_status <> 'received' THEN bp.paid_amount ELSE 0 END), 0) AS cancelled_amount
            $base
            WHERE " . implode(' AND ', $where) . "
            GROUP BY pm.payment_method_id, pm.payment_method_name, pm.method_type
            ORDER BY collected_amount DESC
        ", $types, $params);
    }

    public function cashierSummary(int $businessId, int $userId, bool $isAdmin, array $filters): array
    {
        list($where, $types, $params) = $this->paymentWhere($businessId, $userId, $isAdmin, $filters);
        $base = $this->paymentBaseSelect();
        $cashierExpr = $this->userNameExpr('u');

        return $this->fetchAll("
            SELECT
                bp.collected_by AS cashier_id,
                $cashierExpr AS cashier_name,
                COUNT(*) AS payment_count,
                COUNT(DISTINCT bp.bill_id) AS bill_count,
                COUNT(DISTINCT b.customer_id) AS customer_count,
                COALESCE(SUM(CASE WHEN bp.payment_status = 'received' THEN bp.paid_amount ELSE 0 END), 0) AS collected_amount,
                COALESCE(SUM(CASE WHEN bp.payment_status <> 'received' THEN bp.paid_amount ELSE 0 END), 0) AS cancelled_amount
            $base
            WHERE " . implode(' AND ', $where) . "
            GROUP BY bp.collected_by, cashier_name
            ORDER BY collected_amount DESC
        ", $types, $params);
    }

    public function customerHistory(int $businessId, int $userId, bool $isAdmin, array $filters, int $customerId): array
    {
        if ($customerId <= 0) {
            return array('customer' => array(), 'summary' => array(), 'rows' => array());
        }

        $customer = $this->fetchOne("
            SELECT c.customer_id, c.customer_name, c.mobile, c.opening_outstanding, c.loyalty_points,
                   COALESCE(co.total_bill_amount, 0) AS total_bill_amount,
                   COALESCE(co.total_paid_amount, 0) AS total_paid_amount,
                   COALESCE(co.balance_amount, 0) AS balance_amount
            FROM customers c
            LEFT JOIN customer_outstanding co
                ON co.customer_id = c.customer_id
               AND co.business_id = c.business_id
            WHERE c.business_id = ?
              AND c.customer_id = ?
            LIMIT 1
        ", 'ii', array($businessId, $customerId));

        $filters['customer_id'] = $customerId;
        $ledger = $this->ledgerEntries($businessId, $userId, $isAdmin, $filters, false);
        $rows = $ledger['rows'];

        $summary = array(
            'total_debit' => 0,
            'total_credit' => 0,
            'closing_balance' => 0,
            'current_balance' => (float)($customer['balance_amount'] ?? 0),
        );
        foreach ($rows as $row) {
            $summary['total_debit'] += (float)($row['debit'] ?? 0);
            $summary['total_credit'] += (float)($row['credit'] ?? 0);
            $summary['closing_balance'] = (float)($row['balance'] ?? $summary['closing_balance']);
        }

        return array('customer' => $customer, 'summary' => $summary, 'rows' => $rows);
    }

    public function reportRows(string $report, int $businessId, int $userId, bool $isAdmin, array $filters, bool $paginate = true): array
    {
        if ($report === 'ledger') {
            return $this->ledgerEntries($businessId, $userId, $isAdmin, $filters, $paginate);
        }
        if ($report === 'outstanding') {
            return $this->outstanding($businessId, $userId, $isAdmin, $filters, $paginate);
        }
        if ($report === 'daily') {
            $rows = $this->dailySummary($businessId, $userId, $isAdmin, $filters);
            return array('rows' => $rows, 'total' => count($rows), 'page' => 1, 'per_page' => count($rows), 'total_pages' => 1);
        }
        if ($report === 'method') {
            $rows = $this->methodSummary($businessId, $userId, $isAdmin, $filters);
            return array('rows' => $rows, 'total' => count($rows), 'page' => 1, 'per_page' => count($rows), 'total_pages' => 1);
        }
        if ($report === 'cashier') {
            $rows = $this->cashierSummary($businessId, $userId, $isAdmin, $filters);
            return array('rows' => $rows, 'total' => count($rows), 'page' => 1, 'per_page' => count($rows), 'total_pages' => 1);
        }
        return $this->payments($businessId, $userId, $isAdmin, $filters, $paginate);
    }

    public function exportColumns(string $report): array
    {
        $map = array(
            'payments' => array(
                'Payment ID' => 'payment_id',
                'Collected At' => 'collected_datetime',
                'Bill No' => 'bill_no',
                'Branch' => 'branch_full',
                'Customer' => 'customer_full',
                'Method' => 'payment_method_name',
                'Type' => 'method_type',
                'Paid Amount' => 'paid_amount',
                'Reference No' => 'reference_no',
                'Cashier' => 'cashier_name',
                'Record Status' => 'record_status',
                'Bill Net' => 'net_amount',
                'Bill Balance' => 'balance_amount',
                'Bill Payment Status' => 'bill_payment_status',
            ),
            'ledger' => array(
                'Ledger ID' => 'ledger_id',
                'Date' => 'entry_datetime',
                'Transaction Type' => 'transaction_type',
                'Bill No' => 'bill_no',
                'Branch' => 'branch_full',
                'Customer' => 'customer_full',
                'Method' => 'payment_method_name',
                'Debit' => 'debit',
                'Credit' => 'credit',
                'Balance' => 'balance',
                'Created By' => 'created_by_name',
                'Remarks' => 'remarks',
            ),
            'outstanding' => array(
                'Customer' => 'customer_name',
                'Mobile' => 'mobile',
                'Opening Outstanding' => 'opening_outstanding',
                'Bills' => 'bill_count',
                'Bill Amount' => 'total_bill_amount',
                'Paid Amount' => 'total_paid_amount',
                'Balance Amount' => 'balance_amount',
                'Last Bill Date' => 'last_bill_date',
            ),
            'daily' => array(
                'Date' => 'display_date',
                'Payments' => 'payment_count',
                'Bills' => 'bill_count',
                'Customers' => 'customer_count',
                'Total Collected' => 'total_collected',
                'Cash' => 'cash_amount',
                'UPI' => 'upi_amount',
                'Card' => 'card_amount',
                'Cancelled/Reversed' => 'cancelled_amount',
            ),
            'method' => array(
                'Payment Method' => 'payment_method_name',
                'Type' => 'method_type',
                'Payments' => 'payment_count',
                'Bills' => 'bill_count',
                'Collected' => 'collected_amount',
                'Cancelled/Reversed' => 'cancelled_amount',
            ),
            'cashier' => array(
                'Cashier' => 'cashier_name',
                'Payments' => 'payment_count',
                'Bills' => 'bill_count',
                'Customers' => 'customer_count',
                'Collected' => 'collected_amount',
                'Cancelled/Reversed' => 'cancelled_amount',
            ),
        );
        return $map[$report] ?? $map['payments'];
    }

    public function enrichRowsForExport(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['branch_full'] = trim((string)($row['branch_name'] ?? '') . (($row['floor_name'] ?? '') !== '' ? ' - ' . (string)$row['floor_name'] : ''));
            $row['customer_full'] = trim((string)($row['customer_name'] ?? '') . (($row['customer_mobile'] ?? '') !== '' ? ' - ' . (string)$row['customer_mobile'] : ''));
        }
        unset($row);
        return $rows;
    }
}
