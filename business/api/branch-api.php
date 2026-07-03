<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

api_require_business_login();
verify_csrf_header();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request.');
}

$businessId = current_business_id();
$branchCode = strtoupper(trim($_POST['branch_code'] ?? ''));
$branchName = trim($_POST['branch_name'] ?? '');
$floorName = trim($_POST['floor_name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$address = trim($_POST['address'] ?? '');

if ($branchCode === '' || $branchName === '') {
    json_response(false, 'Branch code and branch name are required.');
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM branches
    WHERE business_id = ? AND branch_code = ?
");
$stmt->execute([$businessId, $branchCode]);

if ((int)$stmt->fetchColumn() > 0) {
    json_response(false, 'This branch code already exists.');
}

$stmt = $pdo->prepare("
    INSERT INTO branches
    (business_id, branch_code, branch_name, floor_name, address, mobile, status, created_by)
    VALUES
    (?, ?, ?, ?, ?, ?, 1, ?)
");
$stmt->execute([
    $businessId,
    $branchCode,
    $branchName,
    $floorName ?: null,
    $address ?: null,
    $mobile ?: null,
    current_user_id()
]);

$branchId = (int)$pdo->lastInsertId();

$stmt = $pdo->prepare("
    INSERT INTO branch_settings
    (business_id, branch_id, invoice_prefix, barcode_prefix, default_payment_method, thermal_printer_size, status)
    VALUES
    (?, ?, 'BILL', 'GK', 'Cash', '3-inch', 1)
");
$stmt->execute([$businessId, $branchId]);

add_activity_log($pdo, 'Branches', 'branch_created', $branchId, null, [
    'branch_code' => $branchCode,
    'branch_name' => $branchName
]);

json_response(true, 'Branch created successfully.');
?>
