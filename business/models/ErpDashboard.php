<?php
/**
 * GK Footwear POS - ERP Dashboard Model
 * Place this file at: models/ErpDashboard.php
 */

declare(strict_types=1);

class ErpDashboard
{
    private $conn;
    private $businessId;
    private $userId;
    private $isAdmin;

    public function __construct(mysqli $conn, int $businessId, int $userId = 0, bool $isAdmin = false)
    {
        $this->conn = $conn;
        $this->businessId = $businessId;
        $this->userId = $userId;
        $this->isAdmin = $isAdmin;
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

    private function rows(string $sql, string $types = '', array $params = []): array
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new RuntimeException(mysqli_error($this->conn));
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

    private function one(string $sql, string $types = '', array $params = []): array
    {
        $rows = $this->rows($sql, $types, $params);
        return $rows ? $rows[0] : [];
    }

    private function tableExists(string $table): bool
    {
        $row = $this->one(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            's',
            [$table]
        );
        return (int)($row['total'] ?? 0) > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $row = $this->one(
            "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            'ss',
            [$table, $column]
        );
        return (int)($row['total'] ?? 0) > 0;
    }

    private function userNameExpression(string $alias = 'u'): string
    {
        $parts = [];
        if ($this->columnExists('users', 'full_name')) {
            $parts[] = "NULLIF($alias.full_name, '')";
        }
        if ($this->columnExists('users', 'name')) {
            $parts[] = "NULLIF($alias.name, '')";
        }
        if ($this->columnExists('users', 'username')) {
            $parts[] = "NULLIF($alias.username, '')";
        }
        if ($this->columnExists('users', 'email')) {
            $parts[] = "NULLIF($alias.email, '')";
        }
        $parts[] = "'System'";
        return 'COALESCE(' . implode(', ', $parts) . ')';
    }

    private function accessibleBranches(): array
    {
        if ($this->isAdmin) {
            return [];
        }

        $branchIds = [];
        if ($this->tableExists('user_branch_access')) {
            $rows = $this->rows(
                "SELECT branch_id
                 FROM user_branch_access
                 WHERE business_id = ?
                   AND user_id = ?
                   AND access_status = 1",
                'ii',
                [$this->businessId, $this->userId]
            );
            foreach ($rows as $row) {
                $branchIds[] = (int)$row['branch_id'];
            }
        }

        if (!$branchIds && isset($_SESSION['default_branch_id'])) {
            $branchIds[] = (int)$_SESSION['default_branch_id'];
        }

        return array_values(array_filter(array_unique($branchIds)));
    }

    private function branchCondition(string $alias, array $filters, string &$types, array &$params): string
    {
        $branchId = (int)($filters['branch_id'] ?? 0);
        $accessible = $this->accessibleBranches();

        if ($branchId > 0) {
            if ($accessible && !in_array($branchId, $accessible, true)) {
                return " AND 1 = 0 ";
            }
            $types .= 'i';
            $params[] = $branchId;
            return " AND $alias.branch_id = ? ";
        }

        if ($accessible) {
            $placeholders = implode(',', array_fill(0, count($accessible), '?'));
            $types .= str_repeat('i', count($accessible));
            foreach ($accessible as $id) {
                $params[] = $id;
            }
            return " AND $alias.branch_id IN ($placeholders) ";
        }

        return '';
    }

    private function dateCondition(string $alias, array $filters, string &$types, array &$params, string $dateColumn = 'bill_date'): string
    {
        $from = (string)($filters['date_from'] ?? date('Y-m-01'));
        $to = (string)($filters['date_to'] ?? date('Y-m-d'));
        $types .= 'ss';
        $params[] = $from;
        $params[] = $to;
        return " AND $alias.$dateColumn BETWEEN ? AND ? ";
    }

    public function dashboardSummary(array $filters): array
    {
        return [
            'success' => true,
            'period_label' => $filters['period_label'] ?? 'Selected Period',
            'branches' => $this->branches(),
            'kpis' => $this->kpis($filters),
            'sales_trend' => $this->salesTrend($filters),
            'payment_mix' => $this->paymentMix($filters),
            'stock_overview' => $this->stockOverview($filters),
            'low_stock_alerts' => $this->lowStockAlerts($filters),
            'pending_payments' => $this->pendingPayments($filters),
            'customer_outstanding' => $this->customerOutstanding($filters),
            'supplier_outstanding' => $this->supplierOutstanding($filters),
            'branch_performance' => $this->branchPerformance($filters),
            'user_performance' => $this->userPerformance($filters),
            'recent_activities' => $this->recentActivities($filters),
        ];
    }

    public function branches(): array
    {
        $types = 'i';
        $params = [$this->businessId];
        $where = '';

        $accessible = $this->accessibleBranches();
        if ($accessible) {
            $where .= ' AND branch_id IN (' . implode(',', array_fill(0, count($accessible), '?')) . ')';
            $types .= str_repeat('i', count($accessible));
            foreach ($accessible as $branchId) {
                $params[] = $branchId;
            }
        }

        return $this->rows(
            "SELECT branch_id, branch_code, branch_name, floor_name
             FROM branches
             WHERE business_id = ?
               AND status = 1
               $where
             ORDER BY branch_name, floor_name, branch_id",
            $types,
            $params
        );
    }

    private function kpis(array $filters): array
    {
        $types = 'i';
        $params = [$this->businessId];
        $where = " b.business_id = ? AND b.bill_status = 'active' ";
        $where .= $this->dateCondition('b', $filters, $types, $params);
        $where .= $this->branchCondition('b', $filters, $types, $params);

        $sales = $this->one(
            "SELECT
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.net_amount), 0) AS net_sales,
                COALESCE(SUM(b.paid_amount), 0) AS collected_amount,
                COALESCE(SUM(b.balance_amount), 0) AS pending_amount,
                COALESCE(AVG(NULLIF(b.net_amount, 0)), 0) AS avg_bill_value,
                COALESCE(SUM(CASE WHEN COALESCE(b.balance_amount, 0) > 0 OR b.payment_status IN ('pending','partial') THEN 1 ELSE 0 END), 0) AS pending_bill_count
             FROM bills b
             WHERE $where",
            $types,
            $params
        );

        $stock = $this->stockOverview($filters);

        $customer = $this->one(
            "SELECT
                COALESCE(SUM(COALESCE(co.balance_amount, c.opening_outstanding, 0)), 0) AS customer_outstanding,
                COALESCE(SUM(CASE WHEN COALESCE(co.balance_amount, c.opening_outstanding, 0) > 0 THEN 1 ELSE 0 END), 0) AS outstanding_customer_count
             FROM customers c
             LEFT JOIN customer_outstanding co
                ON co.customer_id = c.customer_id
               AND co.business_id = c.business_id
             WHERE c.business_id = ?",
            'i',
            [$this->businessId]
        );

        $supplierSql = "
            SELECT
                COALESCE(SUM(COALESCE(vo.balance_amount, s.current_outstanding, s.opening_outstanding, 0)), 0) AS supplier_outstanding,
                COALESCE(SUM(CASE WHEN COALESCE(vo.balance_amount, s.current_outstanding, s.opening_outstanding, 0) > 0 THEN 1 ELSE 0 END), 0) AS outstanding_supplier_count
            FROM suppliers s
            LEFT JOIN vendor_outstanding vo
                ON vo.supplier_id = s.supplier_id
               AND vo.business_id = s.business_id
            WHERE s.business_id = ?";
        $supplier = $this->one($supplierSql, 'i', [$this->businessId]);

        return [
            'bill_count' => (int)($sales['bill_count'] ?? 0),
            'net_sales' => (float)($sales['net_sales'] ?? 0),
            'collected_amount' => (float)($sales['collected_amount'] ?? 0),
            'pending_amount' => (float)($sales['pending_amount'] ?? 0),
            'avg_bill_value' => (float)($sales['avg_bill_value'] ?? 0),
            'pending_bill_count' => (int)($sales['pending_bill_count'] ?? 0),
            'available_stock_qty' => (float)($stock['available_stock_qty'] ?? 0),
            'low_stock_count' => (int)($stock['low_stock_count'] ?? 0),
            'out_of_stock_count' => (int)($stock['out_of_stock_count'] ?? 0),
            'customer_outstanding' => (float)($customer['customer_outstanding'] ?? 0),
            'outstanding_customer_count' => (int)($customer['outstanding_customer_count'] ?? 0),
            'supplier_outstanding' => (float)($supplier['supplier_outstanding'] ?? 0),
            'outstanding_supplier_count' => (int)($supplier['outstanding_supplier_count'] ?? 0),
        ];
    }

    private function salesTrend(array $filters): array
    {
        $types = 'i';
        $params = [$this->businessId];
        $where = " b.business_id = ? AND b.bill_status = 'active' ";
        $where .= $this->dateCondition('b', $filters, $types, $params);
        $where .= $this->branchCondition('b', $filters, $types, $params);

        return $this->rows(
            "SELECT
                b.bill_date,
                DATE_FORMAT(b.bill_date, '%d %b') AS label,
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.net_amount), 0) AS net_amount,
                COALESCE(SUM(b.paid_amount), 0) AS paid_amount,
                COALESCE(SUM(b.balance_amount), 0) AS balance_amount
             FROM bills b
             WHERE $where
             GROUP BY b.bill_date
             ORDER BY b.bill_date ASC",
            $types,
            $params
        );
    }

    private function paymentMix(array $filters): array
    {
        $types = 'i';
        $params = [$this->businessId];
        $where = " bp.business_id = ? AND bp.payment_status <> 'cancelled' ";
        $types .= 'ss';
        $params[] = (string)($filters['date_from'] ?? date('Y-m-01'));
        $params[] = (string)($filters['date_to'] ?? date('Y-m-d'));
        $where .= " AND DATE(bp.collected_at) BETWEEN ? AND ? ";
        $where .= $this->branchCondition('bp', $filters, $types, $params);

        return $this->rows(
            "SELECT
                COALESCE(pm.payment_method_name, 'Unknown') AS payment_method_name,
                COALESCE(SUM(bp.paid_amount), 0) AS paid_amount,
                COUNT(*) AS payment_count
             FROM bill_payments bp
             LEFT JOIN payment_methods pm
                ON pm.payment_method_id = bp.payment_method_id
               AND pm.business_id = bp.business_id
             WHERE $where
             GROUP BY COALESCE(pm.payment_method_name, 'Unknown')
             ORDER BY paid_amount DESC
             LIMIT 8",
            $types,
            $params
        );
    }

    private function stockOverview(array $filters): array
    {
        $types = 'i';
        $params = [$this->businessId];
        $where = " si.business_id = ? AND si.item_status = 'active' ";
        $where .= $this->branchCondition('si', $filters, $types, $params);

        return $this->one(
            "SELECT
                COALESCE(SUM(si.qty), 0) AS total_stock_qty,
                COALESCE(SUM(si.available_qty), 0) AS available_stock_qty,
                COALESCE(SUM(GREATEST(si.qty - si.available_qty, 0)), 0) AS sold_stock_qty,
                COALESCE(SUM(si.available_qty * si.selling_rate), 0) AS available_stock_value,
                COALESCE(SUM(CASE WHEN si.available_qty <= 2 AND si.available_qty > 0 THEN 1 ELSE 0 END), 0) AS low_stock_count,
                COALESCE(SUM(CASE WHEN si.available_qty <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count
             FROM stock_inward_items si
             WHERE $where",
            $types,
            $params
        );
    }

    private function lowStockAlerts(array $filters): array
    {
        $types = 'i';
        $params = [$this->businessId];
        $where = " si.business_id = ? AND si.item_status = 'active' AND si.available_qty <= 2 ";
        $where .= $this->branchCondition('si', $filters, $types, $params);

        return $this->rows(
            "SELECT
                si.stock_item_id,
                si.article_no,
                si.article_name,
                si.size,
                si.color,
                si.available_qty,
                br.branch_name,
                br.floor_name
             FROM stock_inward_items si
             LEFT JOIN branches br
                ON br.branch_id = si.branch_id
               AND br.business_id = si.business_id
             WHERE $where
             ORDER BY si.available_qty ASC, si.updated_at DESC, si.stock_item_id DESC
             LIMIT 12",
            $types,
            $params
        );
    }

    private function pendingPayments(array $filters): array
    {
        $types = 'i';
        $params = [$this->businessId];
        $where = " b.business_id = ? AND b.bill_status = 'active' AND COALESCE(b.balance_amount, 0) > 0 ";
        $where .= $this->branchCondition('b', $filters, $types, $params);

        return $this->rows(
            "SELECT
                b.bill_id,
                b.bill_no,
                b.bill_date,
                b.customer_name,
                b.customer_mobile,
                b.net_amount,
                b.paid_amount,
                b.balance_amount,
                br.branch_name,
                br.floor_name
             FROM bills b
             LEFT JOIN branches br
                ON br.branch_id = b.branch_id
               AND br.business_id = b.business_id
             WHERE $where
             ORDER BY b.bill_date DESC, b.created_at DESC, b.bill_id DESC
             LIMIT 10",
            $types,
            $params
        );
    }

    private function customerOutstanding(array $filters): array
    {
        return $this->rows(
            "SELECT
                c.customer_id AS id,
                c.customer_name,
                c.mobile,
                COALESCE(co.balance_amount, c.opening_outstanding, 0) AS balance_amount
             FROM customers c
             LEFT JOIN customer_outstanding co
                ON co.customer_id = c.customer_id
               AND co.business_id = c.business_id
             WHERE c.business_id = ?
               AND COALESCE(co.balance_amount, c.opening_outstanding, 0) > 0
             ORDER BY balance_amount DESC, c.customer_name ASC
             LIMIT 10",
            'i',
            [$this->businessId]
        );
    }

    private function supplierOutstanding(array $filters): array
    {
        return $this->rows(
            "SELECT
                s.supplier_id AS id,
                s.supplier_name,
                s.mobile,
                COALESCE(vo.balance_amount, s.current_outstanding, s.opening_outstanding, 0) AS balance_amount
             FROM suppliers s
             LEFT JOIN vendor_outstanding vo
                ON vo.supplier_id = s.supplier_id
               AND vo.business_id = s.business_id
             WHERE s.business_id = ?
               AND COALESCE(vo.balance_amount, s.current_outstanding, s.opening_outstanding, 0) > 0
             ORDER BY balance_amount DESC, s.supplier_name ASC
             LIMIT 10",
            'i',
            [$this->businessId]
        );
    }

    private function branchPerformance(array $filters): array
    {
        $types = 'i';
        $params = [$this->businessId];
        $where = " b.business_id = ? AND b.bill_status = 'active' ";
        $where .= $this->dateCondition('b', $filters, $types, $params);
        $where .= $this->branchCondition('b', $filters, $types, $params);

        return $this->rows(
            "SELECT
                br.branch_id,
                COALESCE(br.branch_name, 'Unknown') AS branch_name,
                br.floor_name,
                COUNT(b.bill_id) AS bill_count,
                COALESCE(SUM(b.net_amount), 0) AS net_amount,
                COALESCE(SUM(b.paid_amount), 0) AS paid_amount,
                COALESCE(SUM(b.balance_amount), 0) AS balance_amount
             FROM bills b
             LEFT JOIN branches br
                ON br.branch_id = b.branch_id
               AND br.business_id = b.business_id
             WHERE $where
             GROUP BY br.branch_id, br.branch_name, br.floor_name
             ORDER BY net_amount DESC
             LIMIT 10",
            $types,
            $params
        );
    }

    private function userPerformance(array $filters): array
    {
        $userNameExpr = $this->userNameExpression('u');
        $types = 'i';
        $params = [$this->businessId];
        $where = " b.business_id = ? AND b.bill_status = 'active' ";
        $where .= $this->dateCondition('b', $filters, $types, $params);
        $where .= $this->branchCondition('b', $filters, $types, $params);

        return $this->rows(
            "SELECT
                b.created_by,
                $userNameExpr AS user_name,
                COALESCE(r.role_name, '') AS role_name,
                COUNT(b.bill_id) AS bill_count,
                COALESCE(SUM(b.net_amount), 0) AS net_amount,
                COALESCE(SUM(b.paid_amount), 0) AS paid_amount
             FROM bills b
             LEFT JOIN users u
                ON u.user_id = b.created_by
               AND u.business_id = b.business_id
             LEFT JOIN roles r
                ON r.role_id = u.role_id
               AND r.business_id = u.business_id
             WHERE $where
             GROUP BY b.created_by, user_name, r.role_name
             ORDER BY net_amount DESC
             LIMIT 10",
            $types,
            $params
        );
    }

    private function recentActivities(array $filters): array
    {
        if (!$this->tableExists('activity_logs')) {
            return [];
        }

        $userNameExpr = $this->userNameExpression('u');
        $types = 'i';
        $params = [$this->businessId];
        $where = " al.business_id = ? ";
        $where .= $this->branchCondition('al', $filters, $types, $params);

        return $this->rows(
            "SELECT
                al.log_id,
                al.module_name,
                al.action_type,
                al.record_id,
                al.created_at,
                $userNameExpr AS user_name
             FROM activity_logs al
             LEFT JOIN users u
                ON u.user_id = al.user_id
               AND u.business_id = al.business_id
             WHERE $where
             ORDER BY al.created_at DESC, al.log_id DESC
             LIMIT 12",
            $types,
            $params
        );
    }
}
