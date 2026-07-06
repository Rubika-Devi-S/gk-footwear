<?php

class PaymentMethod
{
    public const ALLOWED_TYPES = ['cash', 'upi', 'card', 'cheque', 'credit', 'split'];

    public static function allowedTypeLabels()
    {
        return [
            'cash' => 'Cash',
            'upi' => 'UPI',
            'card' => 'Card',
            'cheque' => 'Cheque',
            'credit' => 'Credit',
            'split' => 'Split Payment',
        ];
    }

    public static function typeLabel($type)
    {
        $labels = self::allowedTypeLabels();
        return $labels[$type] ?? ucfirst((string)$type);
    }

    public static function tableExists(mysqli $conn, $tableName)
    {
        if (function_exists('table_exists')) {
            return table_exists($conn, $tableName);
        }

        $stmt = mysqli_prepare($conn, "
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

    public static function bind(mysqli_stmt $stmt, $types, array $params)
    {
        $bind = [];
        $bind[] = $types;

        foreach ($params as $key => $value) {
            $bind[] = &$params[$key];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    public static function validate(array $input)
    {
        $paymentMethodName = trim($input['payment_method_name'] ?? '');
        $methodType = strtolower(trim($input['method_type'] ?? ''));

        if ($paymentMethodName === '') {
            throw new InvalidArgumentException('Payment method name is required.');
        }

        if (strlen($paymentMethodName) > 100) {
            throw new InvalidArgumentException('Payment method name should not exceed 100 characters.');
        }

        if (!preg_match('/^[A-Za-z0-9 ._\-\/&()]+$/', $paymentMethodName)) {
            throw new InvalidArgumentException('Payment method name contains invalid characters.');
        }

        if (!in_array($methodType, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Select a valid payment method type.');
        }

        return [
            'payment_method_name' => $paymentMethodName,
            'method_type' => $methodType,
        ];
    }

    public static function getById(mysqli $conn, $businessId, $paymentMethodId)
    {
        $stmt = mysqli_prepare($conn, "
            SELECT payment_method_id, business_id, payment_method_name, method_type, status, created_at
            FROM payment_methods
            WHERE payment_method_id = ?
              AND business_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $paymentMethodId, $businessId);
        mysqli_stmt_execute($stmt);
        $method = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $method ?: null;
    }

    public static function nameExists(mysqli $conn, $businessId, $paymentMethodName, $excludeId = 0)
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM payment_methods
            WHERE business_id = ?
              AND LOWER(payment_method_name) = LOWER(?)
        ";

        $params = [$businessId, $paymentMethodName];
        $types = 'is';

        if ($excludeId > 0) {
            $sql .= " AND payment_method_id != ?";
            $params[] = $excludeId;
            $types .= 'i';
        }

        $stmt = mysqli_prepare($conn, $sql);
        self::bind($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return ((int)($row['total'] ?? 0)) > 0;
    }

    public static function list(mysqli $conn, $businessId, $search = '', $methodType = '', $status = '')
    {
        $where = 'WHERE business_id = ?';
        $params = [$businessId];
        $types = 'i';

        if ($search !== '') {
            $where .= ' AND (payment_method_name LIKE ? OR method_type LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $types .= 'ss';
        }

        if ($methodType !== '' && in_array($methodType, self::ALLOWED_TYPES, true)) {
            $where .= ' AND method_type = ?';
            $params[] = $methodType;
            $types .= 's';
        }

        if ($status !== '' && ($status === '0' || $status === '1')) {
            $where .= ' AND status = ?';
            $params[] = (int)$status;
            $types .= 'i';
        }

        $stmt = mysqli_prepare($conn, "
            SELECT payment_method_id, business_id, payment_method_name, method_type, status, created_at
            FROM payment_methods
            {$where}
            ORDER BY FIELD(method_type, 'cash','upi','card','cheque','credit','split'), payment_method_name ASC
        ");

        self::bind($stmt, $types, $params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $methods = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['payment_method_id'] = (int)$row['payment_method_id'];
            $row['business_id'] = (int)$row['business_id'];
            $row['status'] = (int)$row['status'];
            $row['method_type_label'] = self::typeLabel($row['method_type']);
            $row['used_count'] = self::usageCount($conn, $businessId, (int)$row['payment_method_id']);
            $methods[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $methods;
    }

    public static function stats(mysqli $conn, $businessId)
    {
        $stmt = mysqli_prepare($conn, "
            SELECT
                COUNT(*) AS total_methods,
                SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_methods,
                SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) AS inactive_methods
            FROM payment_methods
            WHERE business_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'i', $businessId);
        mysqli_stmt_execute($stmt);
        $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return [
            'total_methods' => (int)($stats['total_methods'] ?? 0),
            'active_methods' => (int)($stats['active_methods'] ?? 0),
            'inactive_methods' => (int)($stats['inactive_methods'] ?? 0),
            'used_methods' => self::usedMethodCount($conn, $businessId),
        ];
    }

    public static function usedMethodCount(mysqli $conn, $businessId)
    {
        $ids = [];

        if (self::tableExists($conn, 'bill_payments')) {
            $stmt = mysqli_prepare($conn, "
                SELECT DISTINCT payment_method_id
                FROM bill_payments
                WHERE business_id = ?
                  AND payment_method_id IS NOT NULL
            ");
            mysqli_stmt_bind_param($stmt, 'i', $businessId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $ids[(int)$row['payment_method_id']] = true;
            }

            mysqli_stmt_close($stmt);
        }

        if (self::tableExists($conn, 'payment_ledger')) {
            $stmt = mysqli_prepare($conn, "
                SELECT DISTINCT payment_method_id
                FROM payment_ledger
                WHERE business_id = ?
                  AND payment_method_id IS NOT NULL
            ");
            mysqli_stmt_bind_param($stmt, 'i', $businessId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $ids[(int)$row['payment_method_id']] = true;
            }

            mysqli_stmt_close($stmt);
        }

        if (self::tableExists($conn, 'cashier_collections')) {
            $stmt = mysqli_prepare($conn, "
                SELECT DISTINCT payment_method_id
                FROM cashier_collections
                WHERE business_id = ?
                  AND payment_method_id IS NOT NULL
            ");
            mysqli_stmt_bind_param($stmt, 'i', $businessId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $ids[(int)$row['payment_method_id']] = true;
            }

            mysqli_stmt_close($stmt);
        }

        return count($ids);
    }

    public static function usageCount(mysqli $conn, $businessId, $paymentMethodId)
    {
        $total = 0;
        $checks = [
            ['bill_payments', 'payment_method_id'],
            ['payment_ledger', 'payment_method_id'],
            ['cashier_collections', 'payment_method_id'],
        ];

        foreach ($checks as $check) {
            $table = $check[0];
            $column = $check[1];

            if (!self::tableExists($conn, $table)) {
                continue;
            }

            $stmt = mysqli_prepare($conn, "
                SELECT COUNT(*) AS total
                FROM {$table}
                WHERE business_id = ?
                  AND {$column} = ?
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $businessId, $paymentMethodId);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);

            $total += (int)($row['total'] ?? 0);
        }

        return $total;
    }

    public static function isUsed(mysqli $conn, $businessId, $paymentMethodId)
    {
        return self::usageCount($conn, $businessId, $paymentMethodId) > 0;
    }

    public static function create(mysqli $conn, $businessId, array $data)
    {
        if (self::nameExists($conn, $businessId, $data['payment_method_name'])) {
            throw new RuntimeException('Payment method name already exists.');
        }

        $stmt = mysqli_prepare($conn, "
            INSERT INTO payment_methods
                (business_id, payment_method_name, method_type, status, created_at)
            VALUES
                (?, ?, ?, 1, NOW())
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'iss',
            $businessId,
            $data['payment_method_name'],
            $data['method_type']
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Payment method creation failed: ' . mysqli_error($conn));
        }

        $paymentMethodId = (int) mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        return $paymentMethodId;
    }

    public static function update(mysqli $conn, $businessId, $paymentMethodId, array $data)
    {
        if (self::nameExists($conn, $businessId, $data['payment_method_name'], $paymentMethodId)) {
            throw new RuntimeException('Payment method name already exists.');
        }

        $stmt = mysqli_prepare($conn, "
            UPDATE payment_methods
            SET payment_method_name = ?,
                method_type = ?
            WHERE payment_method_id = ?
              AND business_id = ?
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'ssii',
            $data['payment_method_name'],
            $data['method_type'],
            $paymentMethodId,
            $businessId
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Payment method update failed: ' . mysqli_error($conn));
        }

        mysqli_stmt_close($stmt);
    }

    public static function toggleStatus(mysqli $conn, $businessId, $paymentMethodId, $newStatus)
    {
        $stmt = mysqli_prepare($conn, "
            UPDATE payment_methods
            SET status = ?
            WHERE payment_method_id = ?
              AND business_id = ?
        ");
        mysqli_stmt_bind_param($stmt, 'iii', $newStatus, $paymentMethodId, $businessId);

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Payment method status update failed: ' . mysqli_error($conn));
        }

        mysqli_stmt_close($stmt);
    }

    public static function delete(mysqli $conn, $businessId, $paymentMethodId)
    {
        $stmt = mysqli_prepare($conn, "
            DELETE FROM payment_methods
            WHERE payment_method_id = ?
              AND business_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $paymentMethodId, $businessId);

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Payment method delete failed: ' . mysqli_error($conn));
        }

        mysqli_stmt_close($stmt);
    }

    public static function seedDefaults(mysqli $conn, $businessId)
    {
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

            if (self::nameExists($conn, $businessId, $name)) {
                continue;
            }

            $stmt = mysqli_prepare($conn, "
                INSERT INTO payment_methods
                    (business_id, payment_method_name, method_type, status, created_at)
                VALUES
                    (?, ?, ?, 1, NOW())
            ");
            mysqli_stmt_bind_param($stmt, 'iss', $businessId, $name, $type);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}
