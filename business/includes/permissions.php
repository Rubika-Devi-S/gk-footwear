<?php
require_once __DIR__ . '/functions.php';

function current_role_type(mysqli $conn): string
{
    static $roleType = null;

    if ($roleType !== null) {
        return $roleType;
    }

    $roleId = current_role_id();

    if ($roleId <= 0) {
        return '';
    }

    $stmt = mysqli_prepare($conn, "SELECT role_type FROM roles WHERE role_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $roleId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $roleType = (string)($row['role_type'] ?? '');
    return $roleType;
}

function is_business_admin(mysqli $conn): bool
{
    return current_role_type($conn) === 'admin';
}

function can_view_sidebar_menu(mysqli $conn, string $menuSlug): bool
{
    if (is_business_admin($conn)) {
        return true;
    }

    $businessId = current_business_id();
    $roleId = current_role_id();

    if ($businessId <= 0 || $roleId <= 0 || $menuSlug === '') {
        return false;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM business_sidebar_menus sm
        INNER JOIN business_role_sidebar_access rsa
            ON rsa.menu_id = sm.id
           AND rsa.business_id = sm.business_id
           AND rsa.role_id = ?
           AND rsa.can_view = 1
        WHERE sm.business_id = ?
          AND sm.menu_slug = ?
          AND sm.is_active = 1
    ");

    mysqli_stmt_bind_param($stmt, "iis", $roleId, $businessId, $menuSlug);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function can_view_page(mysqli $conn, string $pageUrl): bool
{
    if (is_business_admin($conn)) {
        return true;
    }

    $businessId = current_business_id();
    $roleId = current_role_id();
    $pageUrl = basename($pageUrl);

    if ($businessId <= 0 || $roleId <= 0 || $pageUrl === '') {
        return false;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS total
        FROM business_sidebar_menus sm
        INNER JOIN business_role_sidebar_access rsa
            ON rsa.menu_id = sm.id
           AND rsa.business_id = sm.business_id
           AND rsa.role_id = ?
           AND rsa.can_view = 1
        WHERE sm.business_id = ?
          AND sm.menu_url = ?
          AND sm.is_active = 1
    ");

    mysqli_stmt_bind_param($stmt, "iis", $roleId, $businessId, $pageUrl);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    return (int)($row['total'] ?? 0) > 0;
}

function require_page_access(mysqli $conn, ?string $pageUrl = null): void
{
    $pageUrl = $pageUrl ?: basename($_SERVER['PHP_SELF']);

    if (!can_view_page($conn, $pageUrl)) {
        http_response_code(403);
        echo "<h3 style='font-family:Arial;padding:30px'>403 - Access denied</h3>";
        echo "<p style='font-family:Arial;padding:0 30px'>You do not have permission to view this page.</p>";
        exit;
    }
}
?>
