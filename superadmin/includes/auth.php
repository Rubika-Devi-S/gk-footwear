<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_super_admin(): void
{
    if (empty($_SESSION['super_admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

function super_admin_name(): string
{
    return $_SESSION['super_admin_name'] ?? 'Super Admin';
}
?>
