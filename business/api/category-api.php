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
$categoryName = trim($_POST['category_name'] ?? '');

if ($categoryName === '') {
    json_response(false, 'Category name is required.');
}

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM categories
    WHERE business_id = ? AND category_name = ?
");
$stmt->execute([$businessId, $categoryName]);

if ((int)$stmt->fetchColumn() > 0) {
    json_response(false, 'This category already exists.');
}

$stmt = $pdo->prepare("
    INSERT INTO categories
    (business_id, category_name, status)
    VALUES
    (?, ?, 1)
");
$stmt->execute([$businessId, $categoryName]);

$categoryId = (int)$pdo->lastInsertId();

add_activity_log($pdo, 'Categories', 'category_created', $categoryId, null, [
    'category_name' => $categoryName
]);

json_response(true, 'Category created successfully.');
?>
