<?php
/*
|--------------------------------------------------------------------------
| Super Admin Activity Log Helper
|--------------------------------------------------------------------------
| Super Admin logs are stored separately in super_admin_activity_logs.
| Use this file in superadmin pages:
|
| require_once __DIR__ . '/includes/superadmin-log.php';
| log_super_admin_activity($pdo, 'Business', 'business_created', 'businesses', $businessId, null, $newData);
*/

function log_super_admin_activity(
    PDO $pdo,
    string $moduleName,
    string $actionType,
    ?string $recordType = null,
    ?int $recordId = null,
    $oldValue = null,
    $newValue = null
): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $superAdminId = !empty($_SESSION['super_admin_id']) ? (int)$_SESSION['super_admin_id'] : null;
    $oldJson = $oldValue === null ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE);
    $newJson = $newValue === null ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE);
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $device = $_SERVER['HTTP_USER_AGENT'] ?? null;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO super_admin_activity_logs
            (super_admin_id, module_name, action_type, record_type, record_id, old_value, new_value, ip_address, device_details)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $superAdminId,
            $moduleName,
            $actionType,
            $recordType,
            $recordId,
            $oldJson,
            $newJson,
            $ip,
            $device
        ]);
    } catch (Throwable $e) {
        /*
         * Do not stop main action if log insert fails.
         */
    }
}
?>
