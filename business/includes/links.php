<?php
require_once __DIR__ . '/functions.php';
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<?php include_once __DIR__ . '/theme-colors.php'; ?>
<style>
html,
body {
    min-height: 100%;
}

body {
    background: var(--body-bg);
    color: var(--text-main);
    font-family: Inter, Arial, sans-serif;
    font-weight: 600;
}

#main {
    width: 100%;
    min-height: 100vh;
    margin-left: 268px;
    transition: margin-left .24s ease;
}

body.sidebar-collapsed #main {
    margin-left: 88px;
}

.page-section {
    padding-top: 82px !important;
}

.card-ui,
.page-head-card,
.kpi-card {
    background: var(--card-bg);
    border: 1px solid var(--border-soft);
    border-radius: 22px;
    box-shadow: var(--shadow-card);
}

.page-head-card {
    padding: 16px;
}

.kpi-card {
    width: 100%;
    min-height: 112px;
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 18px;
}

.kpi-icon {
    width: 56px;
    height: 56px;
    min-width: 56px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.kpi-label {
    color: var(--text-muted);
    font-size: 13px;
    font-weight: 800;
}

.kpi-value {
    color: var(--text-main);
    font-size: 24px;
    font-weight: 900;
    margin: 4px 0 2px;
}

.kpi-sub {
    color: var(--text-muted);
    font-size: 12px;
    font-weight: 700;
    margin: 0;
}

.brand-gradient {
    background-image: linear-gradient(135deg, var(--brand-1), var(--brand-2));
    border: 0;
    color: var(--brand-text) !important;
}

.text-muted-custom {
    color: var(--text-muted) !important;
}

.border-soft {
    border-color: var(--border-soft) !important;
}

.form-control,
.form-select {
    background: var(--input-bg);
    color: var(--input-text);
    border-color: var(--input-border);
    min-height: 42px;
    font-size: 13px;
    font-weight: 700;
}

.form-control:focus,
.form-select:focus {
    color: var(--input-text);
    background: var(--input-bg);
    border-color: var(--brand-2);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
}

.icon-btn {
    width: 42px;
    height: 42px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--card-bg);
    color: var(--text-main);
    border: 1px solid var(--border-soft);
}

.table {
    color: var(--text-main);
}

.table thead th {
    background: var(--table-header-bg);
    color: var(--table-header-text);
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
    border-bottom: 1px solid var(--border-soft);
}

.table tbody td {
    font-size: 13px;
    font-weight: 700;
    vertical-align: middle;
    border-color: var(--border-soft);
}

.table tbody tr:hover td {
    background: var(--table-row-hover);
}

.mobile-data-card {
    background: var(--card-bg);
    border: 1px solid var(--border-soft);
    border-radius: 18px;
    padding: 14px;
    box-shadow: var(--shadow-card);
}

.theme-toast-wrap {
    position: fixed;
    top: 88px;
    right: 22px;
    z-index: 9999;
    display: grid;
    gap: 12px;
    width: min(380px, calc(100vw - 28px));
    pointer-events: none;
}

.theme-toast {
    pointer-events: auto;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 15px;
    border-radius: 18px;
    background: var(--card-bg);
    color: var(--text-main);
    border: 1px solid var(--border-soft);
    box-shadow: 0 18px 45px rgba(15, 23, 42, .18);
    transform: translateX(110%);
    opacity: 0;
    transition: .25s ease;
    position: relative;
    overflow: hidden;
}

.theme-toast.show {
    transform: translateX(0);
    opacity: 1;
}

.theme-toast::before {
    content: "";
    position: absolute;
    inset: 0 auto 0 0;
    width: 5px;
    background: var(--brand-1);
}

.theme-toast.success::before {
    background: var(--success-color);
}

.theme-toast.error::before {
    background: var(--danger-color);
}

.theme-toast.info::before {
    background: var(--info-color);
}

.theme-toast-icon {
    width: 36px;
    height: 36px;
    border-radius: 13px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: #fff;
    background: var(--brand-1);
}

.theme-toast.success .theme-toast-icon {
    background: var(--success-color);
}

.theme-toast.error .theme-toast-icon {
    background: var(--danger-color);
}

.theme-toast.info .theme-toast-icon {
    background: var(--info-color);
}

.theme-toast-title {
    font-size: 14px;
    font-weight: 900;
    margin-bottom: 3px;
}

.theme-toast-message {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-muted);
    line-height: 1.4;
}

.theme-toast-close {
    margin-left: auto;
    border: 0;
    background: transparent;
    color: var(--text-muted);
    font-size: 20px;
    font-weight: 900;
    cursor: pointer;
}

body.dark-mode {
    --body-bg: #0F172A;
    --topbar-bg: #111827;
    --topbar-text: #F8FAFC;
    --card-bg: #111827;
    --card-header-bg: #0F172A;
    --border-soft: #334155;
    --text-main: #F8FAFC;
    --text-muted: #94A3B8;
    --sidebar-bg-1: #0F172A;
    --sidebar-bg-2: #111827;
    --sidebar-bg-3: #020617;
    --sidebar-bg: linear-gradient(180deg, var(--sidebar-bg-1), var(--sidebar-bg-2), var(--sidebar-bg-3));
    --sidebar-hover-bg: rgba(255, 255, 255, .10);
    --sidebar-submenu-bg: rgba(255, 255, 255, .06);
    --table-header-bg: #1E293B;
    --table-header-text: #CBD5E1;
    --table-row-hover: #1E293B;
    --input-bg: #0F172A;
    --input-border: #334155;
    --input-text: #F8FAFC;
}

body.dark-mode .dropdown-menu {
    background: var(--card-bg);
    border-color: var(--border-soft);
}

body.dark-mode .dropdown-item {
    color: var(--text-main);
}

body.dark-mode .dropdown-item:hover {
    background: rgba(148, 163, 184, .15);
}

@media (max-width: 1199px) {
    #main {
        margin-left: 0 !important;
    }

    .page-section {
        padding-top: 76px !important;
    }
}

@media (max-width: 575px) {
    .theme-toast-wrap {
        top: 76px;
        right: 14px;
        left: 14px;
        width: auto;
    }
}
</style>