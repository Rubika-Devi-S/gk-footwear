<?php
$currentPage = basename(parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH));
$businessId = current_business_id();
$roleId = current_role_id();
$hasShowInSidebar = table_has_column($conn, 'business_sidebar_menus', 'show_in_sidebar');
$sidebarRows = [];

if ($businessId > 0 && table_exists($conn, 'business_sidebar_menus')) {
    $sidebarVisibleSql = $hasShowInSidebar ? ' AND show_in_sidebar = 1 ' : '';
    $sidebarVisibleSqlAlias = $hasShowInSidebar ? ' AND sm.show_in_sidebar = 1 ' : '';
    if (is_business_admin($conn)) {
        $stmt = mysqli_prepare($conn, "
            SELECT *
            FROM business_sidebar_menus
            WHERE business_id = ?
              AND is_active = 1 {$sidebarVisibleSql}
            ORDER BY
                CASE WHEN parent_id IS NULL THEN sort_order ELSE 999999 END ASC,
                COALESCE(parent_id, id) ASC,
                parent_id IS NOT NULL ASC,
                sort_order ASC,
                id ASC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $businessId);
    } else {
        $stmt = mysqli_prepare($conn, "
            SELECT DISTINCT sm.*
            FROM business_sidebar_menus sm
            INNER JOIN business_role_sidebar_access rsa
                ON rsa.menu_id = sm.id
               AND rsa.business_id = sm.business_id
               AND rsa.role_id = ?
               AND rsa.can_view = 1
            WHERE sm.business_id = ?
              AND sm.is_active = 1 {$sidebarVisibleSqlAlias}
            ORDER BY
                CASE WHEN sm.parent_id IS NULL THEN sm.sort_order ELSE 999999 END ASC,
                COALESCE(sm.parent_id, sm.id) ASC,
                sm.parent_id IS NOT NULL ASC,
                sm.sort_order ASC,
                sm.id ASC
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $roleId, $businessId);
    }

    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $sidebarRows[] = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

$mainMenus = [];
foreach ($sidebarRows as $menu) {
    $id = (int)$menu['id'];
    $parentId = (int)($menu['parent_id'] ?? 0);

    if ($parentId <= 0) {
        $mainMenus[$id] = $menu;
        $mainMenus[$id]['children'] = [];
    }
}

foreach ($sidebarRows as $menu) {
    $parentId = (int)($menu['parent_id'] ?? 0);
    if ($parentId > 0 && isset($mainMenus[$parentId])) {
        $mainMenus[$parentId]['children'][] = $menu;
    }
}

function sidebar_active_url(string $url, string $currentPage): bool
{
    if ($url === '' || $url === '#') {
        return false;
    }

    return basename(parse_url($url, PHP_URL_PATH)) === $currentPage;
}

function sidebar_has_active_child(array $children, string $currentPage): bool
{
    foreach ($children as $child) {
        if (sidebar_active_url((string)$child['menu_url'], $currentPage)) {
            return true;
        }
    }
    return false;
}
?>

<style>
:root {
    --sidebar-expanded-width: 268px;
    --sidebar-collapsed-width: 88px;
}
#sidebar {
    width: var(--sidebar-expanded-width);
    min-width: var(--sidebar-expanded-width);
    height: 100vh;
    position: fixed;
    inset: 0 auto 0 0;
    z-index: 1040;
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    transition: width .24s ease, min-width .24s ease, transform .24s ease;
    overflow: visible;
}
.sidebar-header {
    height: 72px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid rgba(255,255,255,.12);
}
.sidebar-brand-icon {
    width: 44px;
    height: 44px;
    border-radius: 16px;
    display: grid;
    place-items: center;
    background: rgba(255,255,255,.12);
}
.sidebar-brand-text {
    min-width: 0;
}
.sidebar-brand-title {
    font-weight: 900;
    line-height: 1.1;
}
.sidebar-brand-sub {
    font-size: 11px;
    opacity: .75;
}
.sidebar-nav {
    height: calc(100vh - 72px);
    overflow-y: auto;
    overflow-x: hidden;
    padding: 14px 10px 22px;

    /* Hide scrollbar but keep scrolling */
    scrollbar-width: none;        /* Firefox */
    -ms-overflow-style: none;     /* Internet Explorer / Edge */
}

/* Chrome, Safari, Opera */
.sidebar-nav::-webkit-scrollbar {
    display: none;
    width: 0;
    height: 0;
}
.nav-link-custom {
    width: 100%;
    border: 0;
    background: transparent;
    color: var(--sidebar-text);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 46px;
    padding: 11px 14px;
    border-radius: 16px;
    margin: 4px 0;
    font-size: 14px;
    font-weight: 850;
    white-space: nowrap;
    transition: .18s ease;
    cursor: pointer;
}
.nav-link-custom:hover {
    background: var(--sidebar-hover-bg);
    color: var(--sidebar-hover-text);
}
.nav-link-custom.active {
    background-image: linear-gradient(135deg, var(--sidebar-active-bg-1), var(--sidebar-active-bg-2));
    color: var(--sidebar-active-text);
}
.nav-link-custom svg {
    width: 20px;
    height: 20px;
    min-width: 20px;
}
.sidebar-arrow {
    margin-left: auto;
    transition: transform .2s ease;
}
.sidebar-parent-toggle.open .sidebar-arrow {
    transform: rotate(180deg);
}
.sidebar-submenu {
    display: none;
    background: var(--sidebar-submenu-bg);
    border-radius: 18px;
    padding: 8px;
    margin: 5px 0 10px;
}
.sidebar-submenu.open {
    display: block;
}
.sidebar-submenu .nav-link-custom {
    min-height: 42px;
    font-size: 13px;
    padding-left: 14px;
}
body.sidebar-collapsed #sidebar {
    width: var(--sidebar-collapsed-width);
    min-width: var(--sidebar-collapsed-width);
}
body.sidebar-collapsed .sidebar-brand-text,
body.sidebar-collapsed .sidebar-text,
body.sidebar-collapsed .sidebar-arrow {
    display: none !important;
}
body.sidebar-collapsed .sidebar-header {
    justify-content: center;
}
body.sidebar-collapsed .nav-link-custom {
    width: 56px;
    height: 48px;
    margin: 5px auto;
    justify-content: center;
    padding: 0 !important;
}
body.sidebar-collapsed .sidebar-submenu {
    display: none !important;
}
#mobileOverlay {
    display: none;
}
@media (max-width: 1199px) {
    #sidebar {
        transform: translateX(-105%);
        width: 286px;
        min-width: 286px;
    }
    body.sidebar-mobile-open #sidebar {
        transform: translateX(0);
    }
    body.sidebar-mobile-open #mobileOverlay {
        display: block !important;
    }
}
</style>

<aside id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand-icon"><i data-lucide="shopping-bag"></i></div>
        <div class="sidebar-brand-text">
            <div class="sidebar-brand-title"><?= e($_SESSION['business_name'] ?? 'GK Footwear') ?></div>
            <div class="sidebar-brand-sub">Business POS Panel</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php if (!$mainMenus): ?>
            <div class="small text-white-50 p-3">No menu access assigned.</div>
        <?php endif; ?>

        <?php foreach ($mainMenus as $menu): ?>
            <?php
            $children = $menu['children'] ?? [];
            $hasChildren = !empty($children);
            $isActive = sidebar_active_url((string)$menu['menu_url'], $currentPage);
            $childActive = sidebar_has_active_child($children, $currentPage);
            $open = $childActive ? 'open' : '';
            ?>

            <?php if ($hasChildren): ?>
                <button type="button" class="nav-link-custom sidebar-parent-toggle <?= $childActive ? 'active open' : '' ?>" data-submenu="submenu-<?= (int)$menu['id'] ?>">
                    <i data-lucide="<?= e($menu['icon']) ?>"></i>
                    <span class="sidebar-text"><?= e($menu['menu_title']) ?></span>
                    <i data-lucide="chevron-up" class="sidebar-arrow"></i>
                </button>

                <div id="submenu-<?= (int)$menu['id'] ?>" class="sidebar-submenu <?= $open ?>">
                    <?php foreach ($children as $child): ?>
                        <a class="nav-link-custom <?= sidebar_active_url((string)$child['menu_url'], $currentPage) ? 'active' : '' ?>" href="<?= e($child['menu_url']) ?>">
                            <i data-lucide="<?= e($child['icon']) ?>"></i>
                            <span class="sidebar-text"><?= e($child['menu_title']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <a class="nav-link-custom <?= $isActive ? 'active' : '' ?>" href="<?= e($menu['menu_url']) ?>">
                    <i data-lucide="<?= e($menu['icon']) ?>"></i>
                    <span class="sidebar-text"><?= e($menu['menu_title']) ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</aside>
