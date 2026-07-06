<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../models/PaymentMethod.php';
require_once __DIR__ . '/../controllers/PaymentMethodController.php';

require_business_login();
require_page_access($conn, 'payment-methods.php');

header('Content-Type: application/json; charset=utf-8');

$businessId = (int) current_business_id();

$controller = new PaymentMethodController($conn, $businessId);
$controller->handle();
