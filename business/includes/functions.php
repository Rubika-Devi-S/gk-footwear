<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header("Location: {$url}");
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['toast'] = [
        'type' => $type,
        'message' => $message
    ];
}

function table_exists(mysqli $conn, string $table): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $q = mysqli_query($conn, "SHOW TABLES LIKE '{$table}'");
    return $q && mysqli_num_rows($q) > 0;
}

function table_has_column(mysqli $conn, string $table, string $column): bool
{
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return $q && mysqli_num_rows($q) > 0;
}

function current_business_id(): int
{
    return (int)($_SESSION['business_id'] ?? 0);
}

function current_user_id(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_role_id(): int
{
    return (int)($_SESSION['role_id'] ?? 0);
}

function log_activity(mysqli $conn, string $module, string $action, ?int $recordId = null, $oldValue = null, $newValue = null): void
{
    /*
     * Business panel logs are stored only in business_activity_logs.
     * Each row must have business_id, so one business cannot see another business log.
     */
    if (!table_exists($conn, 'business_activity_logs')) {
        return;
    }

    $businessId = current_business_id();

    if ($businessId <= 0) {
        return;
    }

    $branchId = $_SESSION['branch_id'] ?? null;
    $userId = current_user_id() ?: null;
    $roleId = current_role_id() ?: null;
    $oldJson = $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE);
    $newJson = $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $device = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = mysqli_prepare($conn, "
        INSERT INTO business_activity_logs
        (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiiississss",
        $businessId,
        $branchId,
        $userId,
        $roleId,
        $module,
        $action,
        $recordId,
        $oldJson,
        $newJson,
        $ip,
        $device
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function money_inr($amount): string
{
    return '₹' . number_format((float)$amount, 2);
}
?>
