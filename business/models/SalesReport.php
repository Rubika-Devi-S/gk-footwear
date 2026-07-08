<?php
/**
 * Universal Footwear POS - Sales Report Model
 * Uses only existing schema: bills, bill_items, bill_payments, branches,
 * users, customers, payment_methods, stock_inward_items, brands, categories.
 */

class SalesReport
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

    private function tableExists(string $table): bool
    {
        $sql = "SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
        $row = $this->fetchOne($sql, 's', array($table));
        return (int)($row['total'] ?? 0) > 0;
    }

    private function fetchAll(string $sql, string $types = '', array $params = array()): array
    {
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new Exception('SQL prepare failed: ' . mysqli_error($this->conn));
        }

        if ($types !== '' && count($params) > 0) {
            $bind = array($stmt, $types);
            foreach ($params as $key => $value) {
                $bind[] = &$params[$key];
            }
            call_user_func_array('mysqli_stmt_bind_param', $bind);
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('SQL execute failed: ' . $error);
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
        return $rows[0] ?? array();
    }

    private function clampInt($value, int $min, int $max, int $default): int
    {
        $n = (int)$value;
        if ($n < $min) {
            return $default;
        }
        if ($n > $max) {
            return $max;
        }
        return $n;
    }

    private function cleanDate($value): string
    {
        $value = trim((string)$value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    private function buildWhere(array $filters, string $mode = 'bill'): array
    {
        $where = array();
        $types = '';
        $params = array();

        $where[] = "b.business_id = ?";
        $types .= 'i';
        $params[] = $this->businessId;

        $from = $this->cleanDate($filters['from_date'] ?? '');
        $to = $this->cleanDate($filters['to_date'] ?? '');
        if ($from !== '') {
            $where[] = "b.bill_date >= ?";
            $types .= 's';
            $params[] = $from;
        }
        if ($to !== '') {
            $where[] = "b.bill_date <= ?";
            $types .= 's';
            $params[] = $to;
        }

        if (!$this->isAdmin && $this->userId > 0 && $this->tableExists('user_branch_access')) {
            $where[] = "EXISTS (
                SELECT 1 FROM user_branch_access uba
                WHERE uba.business_id = b.business_id
                  AND uba.user_id = ?
                  AND uba.branch_id = b.branch_id
                  AND uba.access_status = 1
            )";
            $types .= 'i';
            $params[] = $this->userId;
        }

        $branchId = (int)($filters['branch_id'] ?? 0);
        if ($branchId > 0) {
            $where[] = "b.branch_id = ?";
            $types .= 'i';
            $params[] = $branchId;
        }

        $createdBy = (int)($filters['created_by'] ?? 0);
        if ($createdBy > 0) {
            $where[] = "b.created_by = ?";
            $types .= 'i';
            $params[] = $createdBy;
        }

        $customerId = (int)($filters['customer_id'] ?? 0);
        if ($customerId > 0) {
            $where[] = "b.customer_id = ?";
            $types .= 'i';
            $params[] = $customerId;
        }

        $paymentStatus = trim((string)($filters['payment_status'] ?? ''));
        if ($paymentStatus !== '') {
            $where[] = "b.payment_status = ?";
            $types .= 's';
            $params[] = $paymentStatus;
        }

        $billStatus = trim((string)($filters['bill_status'] ?? ''));
        if ($billStatus !== '') {
            $where[] = "b.bill_status = ?";
            $types .= 's';
            $params[] = $billStatus;
        } else {
            $where[] = "b.bill_status <> 'deleted'";
        }

        $updatedStatus = trim((string)($filters['updated_status'] ?? ''));
        if ($updatedStatus !== '') {
            $where[] = "b.updated_status = ?";
            $types .= 's';
            $params[] = $updatedStatus;
        }

        $minAmount = trim((string)($filters['min_amount'] ?? ''));
        if ($minAmount !== '' && is_numeric($minAmount)) {
            $where[] = "b.net_amount >= ?";
            $types .= 'd';
            $params[] = (float)$minAmount;
        }

        $maxAmount = trim((string)($filters['max_amount'] ?? ''));
        if ($maxAmount !== '' && is_numeric($maxAmount)) {
            $where[] = "b.net_amount <= ?";
            $types .= 'd';
            $params[] = (float)$maxAmount;
        }

        $categoryId = (int)($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = "EXISTS (
                SELECT 1 FROM bill_items ebi
                INNER JOIN stock_inward_items esi
                    ON esi.stock_item_id = ebi.stock_item_id
                   AND esi.business_id = ebi.business_id
                WHERE ebi.business_id = b.business_id
                  AND ebi.bill_id = b.bill_id
                  AND esi.category_id = ?
            )";
            $types .= 'i';
            $params[] = $categoryId;
        }

        $brandId = (int)($filters['brand_id'] ?? 0);
        if ($brandId > 0) {
            $where[] = "EXISTS (
                SELECT 1 FROM bill_items ebi
                LEFT JOIN stock_inward_items esi
                    ON esi.stock_item_id = ebi.stock_item_id
                   AND esi.business_id = ebi.business_id
                WHERE ebi.business_id = b.business_id
                  AND ebi.bill_id = b.bill_id
                  AND COALESCE(ebi.brand_id, esi.brand_id) = ?
            )";
            $types .= 'i';
            $params[] = $brandId;
        }

        $article = trim((string)($filters['article'] ?? ''));
        if ($article !== '') {
            $like = '%' . $article . '%';
            $where[] = "EXISTS (
                SELECT 1 FROM bill_items ebi
                WHERE ebi.business_id = b.business_id
                  AND ebi.bill_id = b.bill_id
                  AND (ebi.article_no LIKE ? OR ebi.article_name LIKE ? OR ebi.size LIKE ?)
            )";
            $types .= 'sss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = "(
                b.bill_no LIKE ?
                OR b.order_no LIKE ?
                OR b.customer_name LIKE ?
                OR b.customer_mobile LIKE ?
                OR EXISTS (
                    SELECT 1 FROM bill_items sbi
                    WHERE sbi.business_id = b.business_id
                      AND sbi.bill_id = b.bill_id
                      AND (sbi.article_no LIKE ? OR sbi.article_name LIKE ? OR sbi.size LIKE ?)
                )
            )";
            $types .= 'sssssss';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return array(
            'sql' => implode(' AND ', $where),
            'types' => $types,
            'params' => $params,
        );
    }

    private function pagination(array $filters): array
    {
        $page = $this->clampInt($filters['page'] ?? 1, 1, 1000000, 1);
        $perPage = $this->clampInt($filters['per_page'] ?? 25, 5, 200, 25);
        return array(
            'page' => $page,
            'per_page' => $perPage,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
        );
    }

    private function orderBy(array $filters, array $map, string $default): string
    {
        $field = (string)($filters['sort_field'] ?? '');
        $dir = strtoupper((string)($filters['sort_dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        if ($field !== '' && isset($map[$field])) {
            return $map[$field] . ' ' . $dir;
        }
        return $default;
    }

    private function pagedQuery(string $sql, string $types, array $params, string $orderSql, array $filters): array
    {
        $countRow = $this->fetchOne("SELECT COUNT(*) AS total FROM (" . $sql . ") report_count", $types, $params);
        $total = (int)($countRow['total'] ?? 0);
        $pageInfo = $this->pagination($filters);

        $rows = $this->fetchAll(
            $sql . " ORDER BY " . $orderSql . " LIMIT ? OFFSET ?",
            $types . 'ii',
            array_merge($params, array($pageInfo['limit'], $pageInfo['offset']))
        );

        $pageInfo['total'] = $total;
        $pageInfo['total_pages'] = max(1, (int)ceil($total / max(1, $pageInfo['per_page'])));
        return array('rows' => $rows, 'pagination' => $pageInfo);
    }

    public function masters(): array
    {
        $branchWhere = "business_id = ? AND status = 1";
        $branchTypes = 'i';
        $branchParams = array($this->businessId);

        if (!$this->isAdmin && $this->userId > 0 && $this->tableExists('user_branch_access')) {
            $branchWhere .= " AND EXISTS (
                SELECT 1 FROM user_branch_access uba
                WHERE uba.business_id = branches.business_id
                  AND uba.user_id = ?
                  AND uba.branch_id = branches.branch_id
                  AND uba.access_status = 1
            )";
            $branchTypes .= 'i';
            $branchParams[] = $this->userId;
        }

        return array(
            'branches' => $this->fetchAll("SELECT branch_id, branch_code, branch_name, floor_name FROM branches WHERE " . $branchWhere . " ORDER BY branch_name, floor_name", $branchTypes, $branchParams),
            'users' => $this->fetchAll("SELECT user_id, name AS user_name, username FROM users WHERE business_id = ? AND status = 1 ORDER BY name, username", 'i', array($this->businessId)),
            'customers' => $this->fetchAll("SELECT customer_id, customer_name, mobile FROM customers WHERE business_id = ? AND status = 1 ORDER BY customer_name LIMIT 1000", 'i', array($this->businessId)),
            'categories' => $this->fetchAll("SELECT category_id, category_name FROM categories WHERE business_id = ? AND status = 1 ORDER BY category_name", 'i', array($this->businessId)),
            'brands' => $this->fetchAll("SELECT brand_id, brand_name FROM brands WHERE business_id = ? AND status = 1 ORDER BY brand_name", 'i', array($this->businessId)),
            'payment_methods' => $this->fetchAll("SELECT payment_method_id, payment_method_name, method_type FROM payment_methods WHERE business_id = ? AND status = 1 ORDER BY payment_method_name", 'i', array($this->businessId)),
        );
    }

    public function summary(array $filters): array
    {
        $w = $this->buildWhere($filters);
        $sql = "
            SELECT
                COUNT(*) AS total_bills,
                COALESCE(SUM(x.total_qty),0) AS total_qty,
                COALESCE(SUM(b.mrp_total),0) AS gross_mrp,
                COALESCE(SUM(b.item_discount_total + b.bill_discount_amount),0) AS discount_total,
                COALESCE(SUM(b.selling_amount),0) AS selling_amount,
                COALESCE(SUM(b.net_amount),0) AS net_sales,
                COALESCE(SUM(b.paid_amount),0) AS paid_amount,
                COALESCE(SUM(b.balance_amount),0) AS balance_amount,
                COALESCE(AVG(NULLIF(b.net_amount,0)),0) AS average_bill_value,
                COALESCE(SUM(CASE WHEN b.payment_status='paid' THEN 1 ELSE 0 END),0) AS paid_bills,
                COALESCE(SUM(CASE WHEN b.payment_status='pending' THEN 1 ELSE 0 END),0) AS pending_bills,
                COALESCE(SUM(CASE WHEN b.payment_status='partial' THEN 1 ELSE 0 END),0) AS partial_bills
            FROM bills b
            LEFT JOIN (
                SELECT business_id, bill_id, SUM(qty) AS total_qty
                FROM bill_items
                GROUP BY business_id, bill_id
            ) x ON x.business_id = b.business_id AND x.bill_id = b.bill_id
            WHERE " . $w['sql'];

        return $this->fetchOne($sql, $w['types'], $w['params']);
    }

    public function listBills(array $filters): array
    {
        $w = $this->buildWhere($filters);
        $order = $this->orderBy($filters, array(
            'bill_no' => 'b.bill_no',
            'bill_datetime' => 'b.bill_date',
            'branch' => 'br.branch_name',
            'customer' => 'b.customer_name',
            'sales_user' => 'u.name',
            'net_amount' => 'b.net_amount',
            'paid_amount' => 'b.paid_amount',
            'balance_amount' => 'b.balance_amount',
            'payment_status' => 'b.payment_status',
            'bill_status' => 'b.bill_status',
        ), 'b.bill_id DESC');

        $count = $this->fetchOne("SELECT COUNT(*) AS total FROM bills b WHERE " . $w['sql'], $w['types'], $w['params']);
        $pageInfo = $this->pagination($filters);

        $sql = "
            SELECT
                b.bill_id,
                b.bill_no,
                b.order_no,
                b.bill_date,
                b.bill_time,
                DATE_FORMAT(CONCAT(b.bill_date, ' ', COALESCE(b.bill_time, '00:00:00')), '%d-%m-%Y %h:%i %p') AS bill_datetime,
                b.customer_id,
                COALESCE(NULLIF(b.customer_name,''),'Walk-in Customer') AS customer_name,
                b.customer_mobile,
                br.branch_name,
                br.floor_name,
                COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), 'System') AS created_by_name,
                COALESCE(x.item_count,0) AS item_count,
                COALESCE(x.total_qty,0) AS total_qty,
                b.mrp_total,
                b.item_discount_total,
                b.bill_discount_amount,
                b.selling_amount,
                b.loyalty_redeem_amount,
                b.today_savings_amount,
                b.round_off,
                b.net_amount,
                b.paid_amount,
                b.balance_amount,
                b.payment_status,
                b.updated_status,
                b.bill_status,
                b.print_count
            FROM bills b
            LEFT JOIN branches br
                ON br.branch_id = b.branch_id
               AND br.business_id = b.business_id
            LEFT JOIN users u
                ON u.user_id = b.created_by
               AND u.business_id = b.business_id
            LEFT JOIN (
                SELECT business_id, bill_id, COUNT(*) AS item_count, SUM(qty) AS total_qty
                FROM bill_items
                GROUP BY business_id, bill_id
            ) x ON x.business_id = b.business_id AND x.bill_id = b.bill_id
            WHERE " . $w['sql'] . "
            ORDER BY " . $order . "
            LIMIT ? OFFSET ?";

        $rows = $this->fetchAll($sql, $w['types'] . 'ii', array_merge($w['params'], array($pageInfo['limit'], $pageInfo['offset'])));
        $total = (int)($count['total'] ?? 0);
        $pageInfo['total'] = $total;
        $pageInfo['total_pages'] = max(1, (int)ceil($total / max(1, $pageInfo['per_page'])));
        return array('rows' => $rows, 'pagination' => $pageInfo);
    }

    public function itemSales(array $filters): array
    {
        $w = $this->buildWhere($filters, 'item');
        $order = $this->orderBy($filters, array(
            'article_no' => 'article_no',
            'article_name' => 'article_name',
            'brand_name' => 'brand_name',
            'category_name' => 'category_name',
            'total_qty' => 'total_qty',
            'sales_value' => 'sales_value',
            'bill_count' => 'bill_count',
        ), 'sales_value DESC');

        $sql = "
            SELECT
                bi.article_no,
                COALESCE(NULLIF(bi.article_name,''), '-') AS article_name,
                COALESCE(brd.brand_name, '-') AS brand_name,
                COALESCE(cat.category_name, '-') AS category_name,
                COALESCE(NULLIF(bi.size,''), '-') AS size,
                COALESCE(NULLIF(si.color,''), '-') AS color,
                COUNT(DISTINCT b.bill_id) AS bill_count,
                COALESCE(SUM(bi.qty),0) AS total_qty,
                COALESCE(SUM(bi.qty * bi.mrp_rate),0) AS mrp_value,
                COALESCE(SUM(bi.qty * bi.discount_amount),0) AS item_discount,
                COALESCE(SUM(bi.amount),0) AS sales_value,
                COALESCE(SUM(bi.amount) / NULLIF(SUM(bi.qty),0),0) AS average_selling_rate
            FROM bills b
            INNER JOIN bill_items bi
                ON bi.bill_id = b.bill_id
               AND bi.business_id = b.business_id
            LEFT JOIN stock_inward_items si
                ON si.stock_item_id = bi.stock_item_id
               AND si.business_id = bi.business_id
            LEFT JOIN brands brd
                ON brd.brand_id = COALESCE(bi.brand_id, si.brand_id)
               AND brd.business_id = b.business_id
            LEFT JOIN categories cat
                ON cat.category_id = si.category_id
               AND cat.business_id = b.business_id
            WHERE " . $w['sql'] . "
            GROUP BY bi.article_no, bi.article_name, brd.brand_name, cat.category_name, bi.size, si.color";

        return $this->pagedQuery($sql, $w['types'], $w['params'], $order, $filters);
    }

    public function dailyTrend(array $filters): array
    {
        $w = $this->buildWhere($filters);
        $order = $this->orderBy($filters, array(
            'display_date' => 'sales_date',
            'bill_count' => 'bill_count',
            'net_sales' => 'net_sales',
            'paid_amount' => 'paid_amount',
            'balance_amount' => 'balance_amount',
        ), 'sales_date ASC');

        $sql = "
            SELECT
                b.bill_date AS sales_date,
                DATE_FORMAT(b.bill_date, '%d-%m-%Y') AS display_date,
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.net_amount),0) AS net_sales,
                COALESCE(SUM(b.paid_amount),0) AS paid_amount,
                COALESCE(SUM(b.balance_amount),0) AS balance_amount
            FROM bills b
            WHERE " . $w['sql'] . "
            GROUP BY b.bill_date";

        return $this->pagedQuery($sql, $w['types'], $w['params'], $order, $filters);
    }

    public function branchSummary(array $filters): array
    {
        $w = $this->buildWhere($filters);
        $order = $this->orderBy($filters, array(
            'branch_name' => 'branch_name',
            'bill_count' => 'bill_count',
            'net_sales' => 'net_sales',
            'paid_amount' => 'paid_amount',
            'balance_amount' => 'balance_amount',
        ), 'net_sales DESC');

        $sql = "
            SELECT
                b.branch_id,
                COALESCE(br.branch_name, '-') AS branch_name,
                COALESCE(br.floor_name, '-') AS floor_name,
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.net_amount),0) AS net_sales,
                COALESCE(SUM(b.paid_amount),0) AS paid_amount,
                COALESCE(SUM(b.balance_amount),0) AS balance_amount,
                COALESCE(AVG(NULLIF(b.net_amount,0)),0) AS average_bill_value
            FROM bills b
            LEFT JOIN branches br ON br.branch_id = b.branch_id AND br.business_id = b.business_id
            WHERE " . $w['sql'] . "
            GROUP BY b.branch_id, br.branch_name, br.floor_name";

        return $this->pagedQuery($sql, $w['types'], $w['params'], $order, $filters);
    }

    public function paymentSummary(array $filters): array
    {
        $w = $this->buildWhere($filters);
        $order = $this->orderBy($filters, array(
            'payment_method_name' => 'payment_method_name',
            'payment_count' => 'payment_count',
            'paid_amount' => 'paid_amount',
        ), 'paid_amount DESC');

        $sql = "
            SELECT
                COALESCE(pm.payment_method_name, 'Unknown') AS payment_method_name,
                COALESCE(pm.method_type, 'other') AS method_type,
                COUNT(p.payment_id) AS payment_count,
                COALESCE(SUM(p.paid_amount),0) AS paid_amount
            FROM bills b
            INNER JOIN bill_payments p
                ON p.bill_id = b.bill_id
               AND p.business_id = b.business_id
               AND p.payment_status = 'received'
            LEFT JOIN payment_methods pm
                ON pm.payment_method_id = p.payment_method_id
               AND pm.business_id = b.business_id
            WHERE " . $w['sql'] . "
            GROUP BY pm.payment_method_id, pm.payment_method_name, pm.method_type";

        return $this->pagedQuery($sql, $w['types'], $w['params'], $order, $filters);
    }

    public function topProducts(array $filters): array
    {
        $filters['per_page'] = $filters['per_page'] ?? 10;
        return $this->itemSales(array_merge($filters, array('sort_field' => 'sales_value', 'sort_dir' => 'DESC')));
    }

    public function customerSummary(array $filters): array
    {
        $w = $this->buildWhere($filters);
        $order = $this->orderBy($filters, array(
            'customer_name' => 'customer_name',
            'bill_count' => 'bill_count',
            'net_sales' => 'net_sales',
            'paid_amount' => 'paid_amount',
            'balance_amount' => 'balance_amount',
        ), 'net_sales DESC');

        $sql = "
            SELECT
                COALESCE(b.customer_id,0) AS customer_id,
                COALESCE(NULLIF(b.customer_name,''),'Walk-in Customer') AS customer_name,
                COALESCE(NULLIF(b.customer_mobile,''), '-') AS customer_mobile,
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.net_amount),0) AS net_sales,
                COALESCE(SUM(b.paid_amount),0) AS paid_amount,
                COALESCE(SUM(b.balance_amount),0) AS balance_amount,
                DATE_FORMAT(MAX(b.bill_date), '%d-%m-%Y') AS last_bill_date
            FROM bills b
            WHERE " . $w['sql'] . "
            GROUP BY COALESCE(b.customer_id,0), b.customer_name, b.customer_mobile";

        return $this->pagedQuery($sql, $w['types'], $w['params'], $order, $filters);
    }

    public function salesUserSummary(array $filters): array
    {
        $w = $this->buildWhere($filters);
        $order = $this->orderBy($filters, array(
            'created_by_name' => 'created_by_name',
            'bill_count' => 'bill_count',
            'net_sales' => 'net_sales',
            'paid_amount' => 'paid_amount',
            'balance_amount' => 'balance_amount',
        ), 'net_sales DESC');

        $sql = "
            SELECT
                COALESCE(b.created_by,0) AS user_id,
                COALESCE(NULLIF(u.name,''), NULLIF(u.username,''), 'System') AS created_by_name,
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.net_amount),0) AS net_sales,
                COALESCE(SUM(b.paid_amount),0) AS paid_amount,
                COALESCE(SUM(b.balance_amount),0) AS balance_amount,
                COALESCE(AVG(NULLIF(b.net_amount,0)),0) AS average_bill_value
            FROM bills b
            LEFT JOIN users u
                ON u.user_id = b.created_by
               AND u.business_id = b.business_id
            WHERE " . $w['sql'] . "
            GROUP BY b.created_by, u.name, u.username";

        return $this->pagedQuery($sql, $w['types'], $w['params'], $order, $filters);
    }

    public function categorySummary(array $filters): array
    {
        $w = $this->buildWhere($filters, 'item');
        $order = $this->orderBy($filters, array(
            'category_name' => 'category_name',
            'bill_count' => 'bill_count',
            'total_qty' => 'total_qty',
            'sales_value' => 'sales_value',
        ), 'sales_value DESC');

        $sql = "
            SELECT
                COALESCE(cat.category_name, '-') AS category_name,
                COUNT(DISTINCT b.bill_id) AS bill_count,
                COALESCE(SUM(bi.qty),0) AS total_qty,
                COALESCE(SUM(bi.qty * bi.mrp_rate),0) AS mrp_value,
                COALESCE(SUM(bi.qty * bi.discount_amount),0) AS item_discount,
                COALESCE(SUM(bi.amount),0) AS sales_value
            FROM bills b
            INNER JOIN bill_items bi
                ON bi.bill_id = b.bill_id
               AND bi.business_id = b.business_id
            LEFT JOIN stock_inward_items si
                ON si.stock_item_id = bi.stock_item_id
               AND si.business_id = bi.business_id
            LEFT JOIN categories cat
                ON cat.category_id = si.category_id
               AND cat.business_id = b.business_id
            WHERE " . $w['sql'] . "
            GROUP BY cat.category_id, cat.category_name";

        return $this->pagedQuery($sql, $w['types'], $w['params'], $order, $filters);
    }

    public function hourlySummary(array $filters): array
    {
        $w = $this->buildWhere($filters);
        $order = $this->orderBy($filters, array(
            'hour_no' => 'hour_no',
            'bill_count' => 'bill_count',
            'net_sales' => 'net_sales',
        ), 'hour_no ASC');

        $sql = "
            SELECT
                HOUR(COALESCE(b.bill_time, TIME(b.created_at))) AS hour_no,
                DATE_FORMAT(MAKETIME(HOUR(COALESCE(b.bill_time, TIME(b.created_at))),0,0), '%h:00 %p') AS hour_label,
                COUNT(*) AS bill_count,
                COALESCE(SUM(b.net_amount),0) AS net_sales,
                COALESCE(SUM(b.paid_amount),0) AS paid_amount,
                COALESCE(SUM(b.balance_amount),0) AS balance_amount
            FROM bills b
            WHERE " . $w['sql'] . "
            GROUP BY HOUR(COALESCE(b.bill_time, TIME(b.created_at)))";

        return $this->pagedQuery($sql, $w['types'], $w['params'], $order, $filters);
    }

    public function analytics(array $filters): array
    {
        $trendFilters = $filters;
        $trendFilters['page'] = 1;
        $trendFilters['per_page'] = 14;
        $trendFilters['sort_field'] = 'display_date';
        $trendFilters['sort_dir'] = 'ASC';

        $paymentFilters = $filters;
        $paymentFilters['page'] = 1;
        $paymentFilters['per_page'] = 8;

        $topFilters = $filters;
        $topFilters['page'] = 1;
        $topFilters['per_page'] = 10;

        return array(
            'daily_trend' => $this->dailyTrend($trendFilters)['rows'],
            'payments' => $this->paymentSummary($paymentFilters)['rows'],
            'top_products' => $this->topProducts($topFilters)['rows'],
        );
    }

    public function rowsForType(string $type, array $filters): array
    {
        $filters['page'] = 1;
        $filters['per_page'] = (int)($filters['export_limit'] ?? 5000);

        switch ($type) {
            case 'items':
                return $this->itemSales($filters)['rows'];
            case 'trend':
                return $this->dailyTrend($filters)['rows'];
            case 'branch':
                return $this->branchSummary($filters)['rows'];
            case 'payment':
                return $this->paymentSummary($filters)['rows'];
            case 'top_products':
                return $this->topProducts($filters)['rows'];
            case 'customer':
                return $this->customerSummary($filters)['rows'];
            case 'user':
                return $this->salesUserSummary($filters)['rows'];
            case 'category':
                return $this->categorySummary($filters)['rows'];
            case 'hourly':
                return $this->hourlySummary($filters)['rows'];
            case 'bills':
            default:
                return $this->listBills($filters)['rows'];
        }
    }

    public function headingsForType(string $type): array
    {
        $map = array(
            'bills' => array('Bill No','Order No','Date & Time','Branch','Customer','Mobile','Sales User','Items','Qty','MRP','Discount','Net','Paid','Balance','Payment Status','Bill Status'),
            'items' => array('Article No','Product','Brand','Category','Size','Color','Bills','Qty','MRP Value','Discount','Sales Value','Avg Rate'),
            'trend' => array('Date','Bills','Net Sales','Paid','Balance'),
            'branch' => array('Branch','Floor','Bills','Net Sales','Paid','Balance','Average Bill'),
            'payment' => array('Payment Method','Type','Count','Amount'),
            'top_products' => array('Article No','Product','Brand','Category','Size','Color','Bills','Qty','MRP Value','Discount','Sales Value','Avg Rate'),
            'customer' => array('Customer','Mobile','Bills','Net Sales','Paid','Balance','Last Bill'),
            'user' => array('Sales User','Bills','Net Sales','Paid','Balance','Average Bill'),
            'category' => array('Category','Bills','Qty','MRP Value','Discount','Sales Value'),
            'hourly' => array('Hour','Bills','Net Sales','Paid','Balance'),
        );
        return $map[$type] ?? $map['bills'];
    }

    public function exportMatrix(string $type, array $filters): array
    {
        $rows = $this->rowsForType($type, $filters);
        $headings = $this->headingsForType($type);
        $matrix = array();

        foreach ($rows as $r) {
            switch ($type) {
                case 'items':
                case 'top_products':
                    $matrix[] = array($r['article_no'], $r['article_name'], $r['brand_name'], $r['category_name'], $r['size'], $r['color'], $r['bill_count'], $r['total_qty'], $r['mrp_value'], $r['item_discount'], $r['sales_value'], $r['average_selling_rate']);
                    break;
                case 'trend':
                    $matrix[] = array($r['display_date'], $r['bill_count'], $r['net_sales'], $r['paid_amount'], $r['balance_amount']);
                    break;
                case 'branch':
                    $matrix[] = array($r['branch_name'], $r['floor_name'], $r['bill_count'], $r['net_sales'], $r['paid_amount'], $r['balance_amount'], $r['average_bill_value']);
                    break;
                case 'payment':
                    $matrix[] = array($r['payment_method_name'], $r['method_type'], $r['payment_count'], $r['paid_amount']);
                    break;
                case 'customer':
                    $matrix[] = array($r['customer_name'], $r['customer_mobile'], $r['bill_count'], $r['net_sales'], $r['paid_amount'], $r['balance_amount'], $r['last_bill_date']);
                    break;
                case 'user':
                    $matrix[] = array($r['created_by_name'], $r['bill_count'], $r['net_sales'], $r['paid_amount'], $r['balance_amount'], $r['average_bill_value']);
                    break;
                case 'category':
                    $matrix[] = array($r['category_name'], $r['bill_count'], $r['total_qty'], $r['mrp_value'], $r['item_discount'], $r['sales_value']);
                    break;
                case 'hourly':
                    $matrix[] = array($r['hour_label'], $r['bill_count'], $r['net_sales'], $r['paid_amount'], $r['balance_amount']);
                    break;
                case 'bills':
                default:
                    $branch = trim(($r['branch_name'] ?? '') . (($r['floor_name'] ?? '') !== '' ? ' - ' . $r['floor_name'] : ''));
                    $matrix[] = array($r['bill_no'], $r['order_no'], $r['bill_datetime'], $branch, $r['customer_name'], $r['customer_mobile'], $r['created_by_name'], $r['item_count'], $r['total_qty'], $r['mrp_total'], ((float)$r['item_discount_total'] + (float)$r['bill_discount_amount']), $r['net_amount'], $r['paid_amount'], $r['balance_amount'], $r['payment_status'], $r['bill_status']);
                    break;
            }
        }

        return array('headings' => $headings, 'rows' => $matrix);
    }
}
