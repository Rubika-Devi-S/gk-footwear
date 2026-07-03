<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header("Location: {$url}");
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function show_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $type = $_SESSION['flash']['type'] === 'success' ? 'success' : 'danger';
    $title = $type === 'success' ? 'Success' : 'Error';
    $message = e($_SESSION['flash']['message']);
    $icon = $type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';

    echo "
    <div class='toast-container position-fixed top-0 end-0 p-3' style='z-index: 1100;'>
        <div class='toast align-items-center border-0 shadow-lg text-bg-{$type}' role='alert' data-bs-delay='3500'>
            <div class='d-flex'>
                <div class='toast-body'>
                    <strong><i class='bi {$icon} me-1'></i> {$title}</strong><br>
                    {$message}
                </div>
                <button type='button' class='btn-close btn-close-white me-2 m-auto' data-bs-dismiss='toast'></button>
            </div>
        </div>
    </div>";

    unset($_SESSION['flash']);
}

function show_error_toasts(array $errors): void
{
    if (!$errors) {
        return;
    }

    echo "<div class='toast-container position-fixed top-0 end-0 p-3' style='z-index: 1100;'>";

    foreach ($errors as $error) {
        $message = e($error);
        echo "
        <div class='toast align-items-center border-0 shadow-lg text-bg-danger mb-2' role='alert' data-bs-delay='4500'>
            <div class='d-flex'>
                <div class='toast-body'>
                    <strong><i class='bi bi-exclamation-triangle-fill me-1'></i> Error</strong><br>
                    {$message}
                </div>
                <button type='button' class='btn-close btn-close-white me-2 m-auto' data-bs-dismiss='toast'></button>
            </div>
        </div>";
    }

    echo "</div>";
}

function current_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function current_device(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];

    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);

    $cache[$key] = (int)$stmt->fetchColumn() > 0;
    return $cache[$key];
}

function make_business_prefix(string $businessName): string
{
    $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $businessName));

    if ($clean === '') {
        return 'BIZ';
    }

    if (strlen($clean) < 3) {
        return str_pad($clean, 3, 'X');
    }

    return substr($clean, 0, 6);
}

function normalize_business_code(string $code): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
}

function generate_business_code(PDO $pdo, string $businessName = ''): string
{
    $prefix = make_business_prefix($businessName ?: 'BIZ');

    $stmt = $pdo->prepare("
        SELECT business_code
        FROM businesses
        WHERE business_code LIKE ?
        ORDER BY business_id DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);

    $lastCode = $stmt->fetchColumn();

    if (!$lastCode) {
        return $prefix . '001';
    }

    preg_match('/(\d+)$/', (string)$lastCode, $matches);
    $lastNumber = isset($matches[1]) ? (int)$matches[1] : 0;

    return $prefix . str_pad((string)($lastNumber + 1), 3, '0', STR_PAD_LEFT);
}

function business_code_exists(PDO $pdo, string $businessCode, ?int $excludeBusinessId = null): bool
{
    if ($excludeBusinessId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE business_code = ? AND business_id <> ?");
        $stmt->execute([$businessCode, $excludeBusinessId]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE business_code = ?");
        $stmt->execute([$businessCode]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

function username_exists(PDO $pdo, string $username, ?int $excludeBusinessId = null): bool
{
    if ($excludeBusinessId) {
        $stmt = $pdo->prepare("
            SELECT 
                (
                    SELECT COUNT(*) 
                    FROM business_login_credentials 
                    WHERE username = ? AND business_id <> ?
                ) +
                (
                    SELECT COUNT(*) 
                    FROM users 
                    WHERE username = ? AND business_id <> ?
                ) AS total_count
        ");
        $stmt->execute([$username, $excludeBusinessId, $username, $excludeBusinessId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                (
                    SELECT COUNT(*) 
                    FROM business_login_credentials 
                    WHERE username = ?
                ) +
                (
                    SELECT COUNT(*) 
                    FROM users 
                    WHERE username = ?
                ) AS total_count
        ");
        $stmt->execute([$username, $username]);
    }

    return (int)$stmt->fetchColumn() > 0;
}

function gst_config(PDO $pdo, string $gstTypeKey): array
{
    $stmt = $pdo->prepare("
        SELECT * 
        FROM gst_type_settings 
        WHERE gst_type_key = ? AND status = 1
        LIMIT 1
    ");
    $stmt->execute([$gstTypeKey]);
    $row = $stmt->fetch();

    if (!$row) {
        return [
            'invoice_title' => 'Bill of Supply',
            'show_gstin' => 1,
            'show_tax_columns' => 0,
            'show_composition_note' => 1,
            'composition_note' => 'Composition taxable person, not eligible to collect tax on supplies.'
        ];
    }

    return $row;
}

function upload_business_logo(array $file, string $businessCode, ?string $oldLogoPath = null): ?string
{
    if (empty($file['name']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldLogoPath;
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Logo upload failed.');
    }

    if ((int)$file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Logo size must be below 2 MB.');
    }

    $allowed = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    $mime = mime_content_type($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only PNG, JPG, JPEG, and WEBP logo files are allowed.');
    }

    $ext = $allowed[$mime];
    $safeCode = normalize_business_code($businessCode);

    $rootDir = realpath(__DIR__ . '/../..');
    if (!$rootDir) {
        $rootDir = dirname(__DIR__, 2);
    }

    $uploadDir = $rootDir . '/uploads/businesses/' . $safeCode . '/logo';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save logo file.');
    }

    return 'uploads/businesses/' . $safeCode . '/logo/' . $fileName;
}

function logo_url(?string $logoPath): string
{
    if (!$logoPath) {
        return '';
    }

    return '../' . ltrim($logoPath, '/');
}

function business_initial(string $name): string
{
    $name = trim($name);
    return strtoupper(substr($name !== '' ? $name : 'B', 0, 1));
}

function insert_activity_log(
    PDO $pdo,
    ?int $businessId,
    ?int $branchId,
    ?int $userId,
    ?int $roleId,
    string $moduleName,
    string $actionType,
    ?int $recordId = null,
    $oldValue = null,
    $newValue = null
): void {
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs
        (business_id, branch_id, user_id, role_id, module_name, action_type, record_id, old_value, new_value, ip_address, device_details)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $businessId,
        $branchId,
        $userId,
        $roleId,
        $moduleName,
        $actionType,
        $recordId,
        is_null($oldValue) ? null : json_encode($oldValue, JSON_UNESCAPED_UNICODE),
        is_null($newValue) ? null : json_encode($newValue, JSON_UNESCAPED_UNICODE),
        current_ip(),
        current_device()
    ]);
}

function seed_limited_permissions(PDO $pdo, int $roleId, array $pageUrls, string $roleType): void
{
    if (!$pageUrls) {
        return;
    }

    $in = implode(',', array_fill(0, count($pageUrls), '?'));

    $stmt = $pdo->prepare("
        SELECT page_id, page_url 
        FROM pages 
        WHERE page_url IN ($in) AND status = 1
    ");
    $stmt->execute($pageUrls);

    $permStmt = $pdo->prepare("
        INSERT INTO role_permissions
        (role_id, page_id, can_view, can_create, can_edit, can_delete, can_print, can_export)
        VALUES
        (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($stmt->fetchAll() as $page) {
        $url = $page['page_url'];

        $canView = 1;
        $canCreate = 0;
        $canEdit = 0;
        $canDelete = 0;
        $canPrint = 0;
        $canExport = 0;

        if ($roleType === 'sales') {
            $canCreate = $url === 'bill-create.php' ? 1 : 0;
            $canPrint = in_array($url, ['bill-print.php', 'bill-list.php'], true) ? 1 : 0;
        }

        if ($roleType === 'cashier') {
            $canCreate = $url === 'cashier-collect-payment.php' ? 1 : 0;
            $canPrint = $url === 'bill-print.php' ? 1 : 0;
        }

        $permStmt->execute([
            $roleId,
            $page['page_id'],
            $canView,
            $canCreate,
            $canEdit,
            $canDelete,
            $canPrint,
            $canExport
        ]);
    }
}

function seed_default_business_data(PDO $pdo, int $businessId, string $adminName, string $adminUsername, string $adminPasswordHash): void
{
    $defaultRoles = [
        ['Admin', 'admin'],
        ['Sales', 'sales'],
        ['Cashier', 'cashier'],
        ['Stock Manager', 'stock_manager'],
        ['Branch Manager', 'branch_manager'],
    ];

    $roleIds = [];

    $roleStmt = $pdo->prepare("
        INSERT INTO roles (business_id, role_name, role_type)
        VALUES (?, ?, ?)
    ");

    foreach ($defaultRoles as [$roleName, $roleType]) {
        $roleStmt->execute([$businessId, $roleName, $roleType]);
        $roleIds[$roleType] = (int)$pdo->lastInsertId();
    }

    $userStmt = $pdo->prepare("
        INSERT INTO users
        (business_id, default_branch_id, role_id, name, username, password, password_reset_required, status)
        VALUES
        (?, NULL, ?, ?, ?, ?, 0, 1)
    ");

    $userStmt->execute([
        $businessId,
        $roleIds['admin'],
        $adminName,
        $adminUsername,
        $adminPasswordHash
    ]);

    $pageIds = $pdo->query("SELECT page_id FROM pages WHERE status = 1")->fetchAll(PDO::FETCH_COLUMN);

    if ($pageIds) {
        $permStmt = $pdo->prepare("
            INSERT INTO role_permissions
            (role_id, page_id, can_view, can_create, can_edit, can_delete, can_print, can_export)
            VALUES
            (?, ?, 1, 1, 1, 1, 1, 1)
        ");

        foreach ($pageIds as $pageId) {
            $permStmt->execute([$roleIds['admin'], $pageId]);
        }
    }

    if (!empty($roleIds['sales'])) {
        seed_limited_permissions($pdo, $roleIds['sales'], [
            'dashboard.php',
            'stock-list.php',
            'bill-create.php',
            'bill-list.php',
            'bill-print.php',
        ], 'sales');
    }

    if (!empty($roleIds['cashier'])) {
        seed_limited_permissions($pdo, $roleIds['cashier'], [
            'dashboard.php',
            'cashier-pending-bills.php',
            'cashier-collect-payment.php',
            'cashier-collections.php',
            'bill-print.php',
        ], 'cashier');
    }

    $paymentMethods = [
        ['Cash', 'cash'],
        ['UPI', 'upi'],
        ['Card', 'card'],
        ['Cheque', 'cheque'],
        ['Credit', 'credit'],
        ['Split Payment', 'split'],
    ];

    $paymentStmt = $pdo->prepare("
        INSERT INTO payment_methods (business_id, payment_method_name, method_type)
        VALUES (?, ?, ?)
    ");

    foreach ($paymentMethods as [$name, $type]) {
        $paymentStmt->execute([$businessId, $name, $type]);
    }

    $categories = ['Mens', 'Womens', 'Kids', 'Accessories', 'Socks'];

    $categoryStmt = $pdo->prepare("
        INSERT INTO categories (business_id, category_name)
        VALUES (?, ?)
    ");

    foreach ($categories as $category) {
        $categoryStmt->execute([$businessId, $category]);
    }
}
?>
