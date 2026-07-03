<?php
$currentUserName = $_SESSION['name'] ?? 'User';
$currentUserEmail = $_SESSION['email'] ?? '';
$currentUserRole = $_SESSION['role_name'] ?? 'User';
$currentUserInitial = strtoupper(substr(trim($currentUserName), 0, 1)) ?: 'U';
?>
<style>
#topbar {
    height: 68px;
    background: var(--topbar-bg);
    color: var(--topbar-text);
    border-bottom: 1px solid var(--border-soft);
    position: fixed;
    top: 0;
    left: 268px;
    right: 0;
    z-index: 1030;
    transition: left .24s ease;
}
body.sidebar-collapsed #topbar {
    left: 88px;
}
.topbar-user-btn {
    border: 1px solid var(--border-soft);
    background: var(--card-bg);
    border-radius: 18px;
    padding: 7px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-main);
}
.user-avatar {
    width: 38px;
    height: 38px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    color: #fff;
    background-image: linear-gradient(135deg, var(--brand-1), var(--brand-2));
    font-weight: 900;
}
.dropdown-menu {
    border: 1px solid var(--border-soft);
    border-radius: 18px;
    box-shadow: var(--shadow-card);
    padding: 8px;
}
.dropdown-item {
    border-radius: 12px;
    font-weight: 700;
    font-size: 13px;
}
@media (max-width: 1199px) {
    #topbar {
        left: 0 !important;
    }
}
</style>

<header id="topbar" class="d-flex align-items-center px-3 px-lg-4">
    <div class="d-flex align-items-center gap-3 w-100">
        <button id="sidebarToggle" class="icon-btn border-0" type="button" title="Toggle sidebar">
            <i data-lucide="menu"></i>
        </button>

        <div>
            <div class="fw-bold"><?= e($_SESSION['business_name'] ?? 'GK Footwear') ?></div>
            <div class="small text-muted-custom"><?= e($currentUserRole) ?></div>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2 gap-sm-3">
            <button id="themeModeToggle" class="icon-btn" type="button" title="Toggle theme mode">
                <i data-lucide="moon" style="width:16px;height:16px;"></i>
            </button>

            <div class="dropdown">
                <button class="topbar-user-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="user-avatar"><?= e($currentUserInitial) ?></span>
                    <span class="d-none d-sm-block text-start">
                        <span class="d-block fw-bold lh-sm"><?= e($currentUserName) ?></span>
                        <small class="text-muted-custom"><?= e($currentUserRole) ?></small>
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2">
                        <div class="fw-bold"><?= e($currentUserName) ?></div>
                        <small class="text-muted"><?= e($currentUserEmail ?: $currentUserRole) ?></small>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="profile.php"><i data-lucide="user" style="width:15px;height:15px;"></i> My Profile</a></li>
                    <li><a class="dropdown-item" href="system-config.php"><i data-lucide="settings" style="width:15px;height:15px;"></i> Settings</a></li>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i data-lucide="log-out" style="width:15px;height:15px;"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</header>
