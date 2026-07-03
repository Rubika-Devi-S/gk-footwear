<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_business_login(): void
{
    if (empty($_SESSION['business_id']) || empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function business_user_name(): string
{
    return $_SESSION['name'] ?? 'User';
}

function business_user_role(): string
{
    return $_SESSION['role_name'] ?? 'User';
}
?>
