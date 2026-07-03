<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

require_business_login();

if (!is_business_admin($conn)) {
    flash('error', 'Only Admin can manage sidebar.');
    redirect('../manage-sidebar.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../manage-sidebar.php');
}

verify_csrf();

$businessId = current_business_id();
$action = $_POST['action'] ?? '';
$hasShowInSidebar = table_has_column($conn, 'business_sidebar_menus', 'show_in_sidebar');

function create_page_file_if_missing(string $url, string $title): void
{
    $url = trim($url);

    if ($url === '' || $url === '#' || !preg_match('/^[a-zA-Z0-9_\-\/]+\.php$/', $url)) {
        return;
    }

    if (str_contains($url, '..')) {
        return;
    }

    $businessRoot = realpath(__DIR__ . '/..');
    if (!$businessRoot) {
        return;
    }

    $target = $businessRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $url);

    if (file_exists($target)) {
        return;
    }

    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $safeTitle = addslashes($title);

    $content = <<<PHP
<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access(\$conn, basename(__FILE__));

\$pageTitle = '{$safeTitle}';
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(\$pageTitle) ?> - GK Footwear POS</title>
    <?php include __DIR__ . '/includes/links.php'; ?>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<div class="min-vh-100 d-flex">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <main id="main">
        <?php include __DIR__ . '/includes/nav.php'; ?>
        <section class="page-section p-3 p-lg-3">
            <div class="page-head-card mb-3">
                <h1 class="h4 fw-bold mb-1"><?= e(\$pageTitle) ?></h1>
                <p class="text-muted-custom mb-0 small">Auto-created page. Add module code here.</p>
            </div>

            <div class="card-ui p-4 text-center">
                <h4 class="fw-bold">Page Created</h4>
                <p class="text-muted-custom mb-0">This page was generated from Manage Sidebar.</p>
            </div>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>
<?php include __DIR__ . '/includes/script.php'; ?>
</body>
</html>
PHP;

    file_put_contents($target, $content);
}

try {
    if ($action === 'toggle_active') {
        $menuId = (int)($_POST['menu_id'] ?? 0);

        $stmt = mysqli_prepare($conn, "
            UPDATE business_sidebar_menus
            SET is_active = IF(is_active = 1, 0, 1)
            WHERE business_id = ? AND id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $businessId, $menuId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Manage Sidebar', 'toggle_active', $menuId);
        flash('success', 'Menu status updated successfully.');
        redirect('../manage-sidebar.php');
    }

    if ($action === 'toggle_sidebar') {
        $menuId = (int)($_POST['menu_id'] ?? 0);

        if (!$hasShowInSidebar) {
            throw new RuntimeException('show_in_sidebar column missing. Run manage_sidebar_ui_darkmode_patch.sql');
        }

        $stmt = mysqli_prepare($conn, "
            UPDATE business_sidebar_menus
            SET show_in_sidebar = IF(show_in_sidebar = 1, 0, 1)
            WHERE business_id = ? AND id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $businessId, $menuId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Manage Sidebar', 'toggle_sidebar', $menuId);
        flash('success', 'Sidebar visibility updated successfully.');
        redirect('../manage-sidebar.php');
    }

    if ($action === 'delete') {
        $menuId = (int)($_POST['menu_id'] ?? 0);

        $stmt = mysqli_prepare($conn, "
            DELETE FROM business_sidebar_menus
            WHERE business_id = ? AND id = ?
        ");
        mysqli_stmt_bind_param($stmt, "ii", $businessId, $menuId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Manage Sidebar', 'delete', $menuId);
        flash('success', 'Menu deleted successfully.');
        redirect('../manage-sidebar.php');
    }

    if ($action === 'bulk_sort') {
        $sortItems = $_POST['sort_order'] ?? [];

        $stmt = mysqli_prepare($conn, "
            UPDATE business_sidebar_menus
            SET sort_order = ?
            WHERE business_id = ? AND id = ?
        ");

        foreach ($sortItems as $menuId => $sortOrder) {
            $menuId = (int)$menuId;
            $sortOrder = (int)$sortOrder;
            mysqli_stmt_bind_param($stmt, "iii", $sortOrder, $businessId, $menuId);
            mysqli_stmt_execute($stmt);
        }

        mysqli_stmt_close($stmt);

        log_activity($conn, 'Manage Sidebar', 'bulk_sort', null, null, $sortItems);
        flash('success', 'Sort order updated successfully.');
        redirect('../manage-sidebar.php');
    }

    if ($action === 'sort_one') {
        $menuId = (int)($_POST['menu_id'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        $stmt = mysqli_prepare($conn, "
            UPDATE business_sidebar_menus
            SET sort_order = ?
            WHERE business_id = ? AND id = ?
        ");
        mysqli_stmt_bind_param($stmt, "iii", $sortOrder, $businessId, $menuId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        log_activity($conn, 'Manage Sidebar', 'sort_update', $menuId, null, ['sort_order' => $sortOrder]);
        flash('success', 'Sort order updated successfully.');
        redirect('../manage-sidebar.php');
    }

    $menuId = (int)($_POST['menu_id'] ?? 0);
    $title = trim($_POST['menu_title'] ?? '');
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', trim($_POST['menu_slug'] ?? '')));
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $url = trim($_POST['menu_url'] ?? '#');
    $icon = trim($_POST['icon'] ?? 'circle');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = (int)($_POST['is_active'] ?? 1);
    $showInSidebar = (int)($_POST['show_in_sidebar'] ?? 1);
    $menuType = $parentId ? 'sub' : 'main';
    $hasSubmenu = 0;

    if ($title === '' || $slug === '') {
        throw new RuntimeException('Menu title and slug are required.');
    }

    mysqli_begin_transaction($conn);

    if ($action === 'create') {
        if ($hasShowInSidebar) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO business_sidebar_menus
                (business_id, parent_id, menu_title, menu_slug, menu_url, icon, menu_type, has_submenu, sort_order, is_active, show_in_sidebar)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "iisssssiiii", $businessId, $parentId, $title, $slug, $url, $icon, $menuType, $hasSubmenu, $sortOrder, $isActive, $showInSidebar);
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO business_sidebar_menus
                (business_id, parent_id, menu_title, menu_slug, menu_url, icon, menu_type, has_submenu, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, "iisssssiii", $businessId, $parentId, $title, $slug, $url, $icon, $menuType, $hasSubmenu, $sortOrder, $isActive);
        }

        mysqli_stmt_execute($stmt);
        $menuId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if ($parentId) {
            $stmt = mysqli_prepare($conn, "UPDATE business_sidebar_menus SET has_submenu = 1 WHERE business_id = ? AND id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $businessId, $parentId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        create_page_file_if_missing($url, $title);
        log_activity($conn, 'Manage Sidebar', 'create', $menuId, null, $_POST);
        flash('success', 'Sidebar menu created successfully.');
    } elseif ($action === 'update') {
        if ($hasShowInSidebar) {
            $stmt = mysqli_prepare($conn, "
                UPDATE business_sidebar_menus
                SET parent_id = ?,
                    menu_title = ?,
                    menu_slug = ?,
                    menu_url = ?,
                    icon = ?,
                    menu_type = ?,
                    sort_order = ?,
                    is_active = ?,
                    show_in_sidebar = ?
                WHERE business_id = ? AND id = ?
            ");
            mysqli_stmt_bind_param($stmt, "isssssiiiii", $parentId, $title, $slug, $url, $icon, $menuType, $sortOrder, $isActive, $showInSidebar, $businessId, $menuId);
        } else {
            $stmt = mysqli_prepare($conn, "
                UPDATE business_sidebar_menus
                SET parent_id = ?,
                    menu_title = ?,
                    menu_slug = ?,
                    menu_url = ?,
                    icon = ?,
                    menu_type = ?,
                    sort_order = ?,
                    is_active = ?
                WHERE business_id = ? AND id = ?
            ");
            mysqli_stmt_bind_param($stmt, "isssssiiii", $parentId, $title, $slug, $url, $icon, $menuType, $sortOrder, $isActive, $businessId, $menuId);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($parentId) {
            $stmt = mysqli_prepare($conn, "UPDATE business_sidebar_menus SET has_submenu = 1 WHERE business_id = ? AND id = ?");
            mysqli_stmt_bind_param($stmt, "ii", $businessId, $parentId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        create_page_file_if_missing($url, $title);
        log_activity($conn, 'Manage Sidebar', 'update', $menuId, null, $_POST);
        flash('success', 'Sidebar menu updated successfully.');
    } else {
        throw new RuntimeException('Invalid action.');
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    if (mysqli_errno($conn)) {
        mysqli_rollback($conn);
    }
    flash('error', $e->getMessage());
}

redirect('../manage-sidebar.php');
?>
