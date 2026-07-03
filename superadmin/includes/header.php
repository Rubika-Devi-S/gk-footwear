<?php
$pageTitle = $pageTitle ?? 'Super Admin';
$activePage = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($pageTitle) ?> | GK Footwear POS</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Move these CDN files to assets/plugins later if needed -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-blue-1: #3b5ba9;
            --sidebar-blue-2: #073b86;
            --sidebar-blue-3: #004b8e;
            --soft-bg: #eef6ff;
            --card-bg: rgba(255,255,255,.72);
            --border: #dbeafe;
            --text: #0b1220;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at 20% 20%, rgba(59,130,246,.20), transparent 28%),
                radial-gradient(circle at 85% 15%, rgba(147,197,253,.24), transparent 26%),
                linear-gradient(135deg, #f8fbff, #eaf4ff 55%, #dceeff);
            color: var(--text);
            font-family: Inter, Arial, sans-serif;
            min-height: 100vh;
            font-weight: 500;
        }

        .app-shell {
            display: flex;
            min-height: 100vh;
            gap: 28px;
            padding: 0;
        }

        .sidebar {
            width: 320px;
            min-height: calc(100vh - 8px);
            margin: 4px;
            background:
                radial-gradient(circle at 70% 20%, rgba(255,255,255,.10), transparent 18%),
                linear-gradient(180deg, var(--sidebar-blue-1), var(--sidebar-blue-2) 48%, var(--sidebar-blue-3));
            color: #fff;
            border-radius: 0 36px 36px 0;
            position: sticky;
            top: 4px;
            align-self: flex-start;
            overflow: hidden;
            box-shadow: 0 22px 55px rgba(16, 68, 147, .23);
        }

        .sidebar:before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,.12) 1px, transparent 1px);
            background-size: 38px 38px;
            opacity: .22;
            pointer-events: none;
        }

        .sidebar-brand {
            position: relative;
            display: flex;
            gap: 16px;
            align-items: center;
            padding: 28px 26px 24px;
            border-bottom: 1px solid rgba(255,255,255,.18);
        }

        .brand-icon {
            width: 66px;
            height: 66px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.27);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.12);
            font-size: 31px;
        }

        .brand-title {
            font-size: 22px;
            line-height: 1.1;
            font-weight: 900;
        }

        .brand-subtitle {
            font-size: 14px;
            color: rgba(255,255,255,.88);
            margin-top: 7px;
        }

        .sidebar-section {
            position: relative;
            padding: 28px 26px 0;
        }

        .section-title {
            font-size: 13px;
            letter-spacing: .13em;
            text-transform: uppercase;
            opacity: .92;
            margin: 0 0 14px 14px;
        }

        .nav-link-custom {
            display: flex;
            align-items: center;
            gap: 16px;
            color: #ffffff;
            text-decoration: none;
            padding: 17px 18px;
            border-radius: 19px;
            margin: 6px 0;
            font-size: 18px;
            font-weight: 650;
            transition: .18s ease;
        }

        .nav-link-custom i {
            font-size: 22px;
            width: 26px;
            text-align: center;
        }

        .nav-link-custom:hover {
            background: rgba(255,255,255,.14);
            color: #fff;
            transform: translateX(2px);
        }

        .nav-link-custom.active {
            background: rgba(255,255,255,.96);
            color: #111827;
            box-shadow: 0 18px 28px rgba(0,0,0,.13);
        }

        .main {
            flex: 1;
            min-width: 0;
            padding: 0 24px 36px 0;
        }

        .topbar {
            height: 102px;
            margin: 0 0 32px;
            background: rgba(255,255,255,.82);
            border: 1px solid rgba(219,234,254,.85);
            border-radius: 0 0 32px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 34px;
            box-shadow: 0 18px 45px rgba(15,23,42,.08);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar h1 {
            margin: 0;
            font-size: 29px;
            font-weight: 900;
            letter-spacing: -.03em;
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 14px;
            width: min(460px, 45vw);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 19px;
            padding: 14px 18px;
            box-shadow: 0 8px 22px rgba(15,23,42,.04);
            color: #111827;
        }

        .search-box input {
            border: none;
            outline: none;
            width: 100%;
            font-size: 16px;
            background: transparent;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .icon-btn {
            width: 56px;
            height: 56px;
            border-radius: 18px;
            background: #fff;
            border: 1px solid #e5e7eb;
            display: grid;
            place-items: center;
            color: #111827;
            text-decoration: none;
            position: relative;
        }

        .admin-pill {
            display: flex;
            gap: 12px;
            align-items: center;
            background: #fff;
            border: 1px solid #e5e7eb;
            padding: 11px 18px 11px 11px;
            border-radius: 22px;
        }

        .admin-avatar {
            width: 48px;
            height: 48px;
            border-radius: 15px;
            display: grid;
            place-items: center;
            color: #fff;
            background: #2563eb;
            font-weight: 900;
            font-size: 18px;
        }

        .page-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255,255,255,.66);
            border: 1px solid rgba(191,219,254,.65);
            padding: 28px 30px;
            border-radius: 34px;
            margin-bottom: 28px;
            box-shadow: 0 14px 35px rgba(59,130,246,.08);
        }

        .page-title {
            font-size: 28px;
            font-weight: 900;
            margin: 0 0 5px;
            letter-spacing: -.03em;
        }

        .page-subtitle {
            font-size: 16px;
            color: #334155;
        }

        .glass-card {
            background: rgba(255,255,255,.72);
            border: 1px solid rgba(191,219,254,.65);
            border-radius: 32px;
            box-shadow: 0 18px 45px rgba(30,64,175,.08);
            overflow: hidden;
        }

        .card-section-head {
            padding: 28px 30px;
            background: rgba(219,234,254,.50);
            border-bottom: 1px solid rgba(191,219,254,.85);
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .section-icon {
            width: 54px;
            height: 54px;
            border-radius: 18px;
            background: rgba(255,255,255,.78);
            display: grid;
            place-items: center;
            font-size: 25px;
            color: #111827;
        }

        .section-head-title {
            font-size: 25px;
            font-weight: 900;
            margin: 0;
            letter-spacing: -.02em;
        }

        .section-head-sub {
            color: #64748b;
            margin-top: 5px;
        }

        .card-body-custom {
            padding: 30px;
        }

        .form-label {
            font-weight: 850;
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 10px;
        }

        .form-control,
        .form-select {
            border: 1px solid #dbe3ef;
            border-radius: 19px;
            min-height: 58px;
            padding: 14px 17px;
            box-shadow: 0 12px 28px rgba(15,23,42,.04);
            background-color: rgba(255,255,255,.86);
            font-weight: 550;
        }

        textarea.form-control {
            min-height: 110px;
        }

        .btn {
            border-radius: 18px;
            padding: 13px 24px;
            font-weight: 800;
        }

        .btn-primary {
            background: #1665ff;
            border-color: #1665ff;
            box-shadow: 0 14px 28px rgba(22,101,255,.23);
        }

        .btn-light {
            background: rgba(255,255,255,.85);
            border-color: rgba(226,232,240,.95);
        }

        .business-avatar {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            background: #fff;
            color: #020617;
            font-weight: 950;
            font-size: 18px;
            box-shadow: 0 10px 22px rgba(15,23,42,.08);
            overflow: hidden;
        }

        .business-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .logo-preview {
            width: 92px;
            height: 118px;
            border-radius: 23px;
            background: rgba(255,255,255,.75);
            border: 1px solid #bfdbfe;
            display: grid;
            place-items: center;
            overflow: hidden;
            font-size: 38px;
            font-weight: 950;
            box-shadow: 0 10px 25px rgba(59,130,246,.09);
        }

        .logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            border-bottom: 1px solid #e5e7eb;
            color: #0f172a;
            font-weight: 900;
            font-size: 14px;
            background: rgba(255,255,255,.55);
            padding: 15px 18px;
        }

        .table tbody td {
            padding: 17px 18px;
            vertical-align: middle;
            border-color: #edf2f7;
            font-weight: 700;
            color: #111827;
        }

        .table .muted {
            color: #64748b;
            font-weight: 550;
            font-size: 13px;
        }

        .status-pill {
            border-radius: 999px;
            padding: 9px 17px;
            font-size: 13px;
            font-weight: 900;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #eeeeee;
            color: #111827;
        }

        .action-btn {
            width: 46px;
            height: 46px;
            border-radius: 17px;
            border: none;
            display: inline-grid;
            place-items: center;
            background: rgba(255,255,255,.78);
            color: #111827;
            text-decoration: none;
            margin-left: 7px;
            box-shadow: 0 8px 20px rgba(15,23,42,.05);
        }

        .sticky-form-actions {
            position: sticky;
            bottom: 0;
            background: rgba(255,255,255,.88);
            border: 1px solid rgba(191,219,254,.65);
            border-radius: 26px 26px 0 0;
            padding: 16px 18px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            backdrop-filter: blur(12px);
        }

        @media (max-width: 1100px) {
            .app-shell {
                display: block;
            }

            .sidebar {
                position: relative;
                width: auto;
                min-height: auto;
                border-radius: 0 0 30px 30px;
                margin: 0;
            }

            .main {
                padding: 0 14px 28px;
            }

            .topbar {
                border-radius: 0 0 28px 28px;
                padding: 18px;
                height: auto;
                gap: 16px;
                flex-wrap: wrap;
            }

            .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon"><i class="bi bi-shield-shaded"></i></div>
            <div>
                <div class="brand-title">Super Admin</div>
                <div class="brand-subtitle">Platform Control</div>
            </div>
        </div>

        <div class="sidebar-section">
            <div class="section-title">Main</div>

            <a href="dashboard.php" class="nav-link-custom <?= $activePage === 'dashboard.php' ? 'active' : '' ?>">
                <i class="bi bi-grid"></i> Dashboard
            </a>

            <a href="businesses.php" class="nav-link-custom <?= in_array($activePage, ['businesses.php', 'business-create.php', 'business-edit.php', 'business-view.php'], true) ? 'active' : '' ?>">
                <i class="bi bi-buildings"></i> Businesses
            </a>

            <a href="branches.php" class="nav-link-custom <?= $activePage === 'branches.php' ? 'active' : '' ?>">
                <i class="bi bi-shop"></i> Branches
            </a>

            <a href="business-users.php" class="nav-link-custom <?= $activePage === 'business-users.php' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i> Business Users
            </a>

            <a href="business-features.php" class="nav-link-custom <?= $activePage === 'business-features.php' ? 'active' : '' ?>">
                <i class="bi bi-toggle-on"></i> Business Features
            </a>

            <a href="feature-manage.php" class="nav-link-custom <?= $activePage === 'feature-manage.php' ? 'active' : '' ?>">
                <i class="bi bi-sliders"></i> Feature Manage
            </a>

            <a href="api-setting.php" class="nav-link-custom <?= $activePage === 'api-setting.php' ? 'active' : '' ?>">
                <i class="bi bi-code-slash"></i> API Setting
            </a>
        </div>

        <div class="sidebar-section">
            <div class="section-title">POS Control</div>

            <a href="menu-master.php" class="nav-link-custom <?= $activePage === 'menu-master.php' ? 'active' : '' ?>">
                <i class="bi bi-menu-button-wide"></i> Menu Master
            </a>

            <a href="business-sidebar-access.php" class="nav-link-custom <?= $activePage === 'business-sidebar-access.php' ? 'active' : '' ?>">
                <i class="bi bi-filter-left"></i> Business Sidebar Access
            </a>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div>
                <h1>Super Admin</h1>
            </div>

            <div class="top-actions">
                <form class="search-box" method="get" action="businesses.php">
                    <i class="bi bi-search fs-5"></i>
                    <input type="text" name="search" placeholder="Search business, user, invoice..." value="<?= e($_GET['search'] ?? '') ?>">
                </form>

                <a class="icon-btn" href="#"><i class="bi bi-bell-fill"></i></a>

                <div class="admin-pill">
                    <div class="admin-avatar">SA</div>
                    <div>
                        <div class="fw-bold"><?= e(super_admin_name()) ?></div>
                        <small class="text-muted">Platform Owner</small>
                    </div>
                </div>
            </div>
        </header>

        <?php show_flash(); ?>
