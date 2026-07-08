<?php
/**
 * GK Footwear POS - Create Bill
 * Full-screen billing screen integrated with api/pos-billing-api.php
 */

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
if (function_exists('require_page_access')) {
    require_page_access($conn, 'bill-create.php');
}

$pageTitle = 'Create Bill';
$businessId = function_exists('current_business_id') ? (int) current_business_id() : (int)($_SESSION['business_id'] ?? 0);
$branchId = function_exists('current_branch_id') ? (int) current_branch_id() : (int)($_SESSION['branch_id'] ?? $_SESSION['default_branch_id'] ?? 0);
$salesUserName = $_SESSION['name'] ?? $_SESSION['username'] ?? 'Sales User';

if (!function_exists('pos_e')) {
    function pos_e($value): string
    {
        if (function_exists('e')) {
            return e((string)$value);
        }
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

function pos_csrf_field(): string
{
    if (function_exists('csrf_field')) {
        return csrf_field();
    }
    if (function_exists('csrf_token')) {
        return '<input type="hidden" name="csrf_token" id="posCsrfToken" value="' . pos_e(csrf_token()) . '">';
    }
    return '<input type="hidden" name="csrf_token" id="posCsrfToken" value="">';
}

if ($businessId <= 0) {
    die('Business session missing. Please login again.');
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= pos_e($pageTitle) ?> - GK Footwear Billing Software</title>
    <?php include __DIR__ . '/includes/links.php'; ?>

    <style>
        :root {
            --pos-bg: #eef3fb;
            --pos-card: var(--card-bg, #ffffff);
            --pos-border: var(--border-soft, #dbe4f0);
            --pos-text: var(--text-main, #0f172a);
            --pos-muted: var(--text-muted, #64748b);
            --pos-brand-1: var(--brand-1, #2563eb);
            --pos-brand-2: var(--brand-2, #7c3aed);
            --pos-success: #16a34a;
            --pos-warning: #f59e0b;
            --pos-danger: #dc2626;
            --pos-radius: 18px;
            --pos-shadow: 0 14px 34px rgba(15, 23, 42, .10);
            --pos-header-height: 72px;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { margin: 0; background: var(--pos-bg); overflow: hidden; }
        .pos-page { height: 100vh; min-height: 100vh; font-family: "Inter", "Segoe UI", Arial, sans-serif; color: var(--pos-text); font-size: 12px; font-weight: 650; display: flex; flex-direction: column; }
        .pos-topbar { min-height: var(--pos-header-height); background: rgba(255, 255, 255, .94); backdrop-filter: blur(18px); border-bottom: 1px solid var(--pos-border); padding: 9px 12px; display: flex; align-items: center; gap: 10px; box-shadow: 0 6px 22px rgba(15, 23, 42, .06); z-index: 20; }
        .pos-back-btn { width: 42px; height: 42px; border: 0; border-radius: 15px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, var(--pos-brand-1), var(--pos-brand-2)); box-shadow: 0 10px 22px rgba(37, 99, 235, .25); text-decoration: none; }
        .pos-title-wrap { min-width: 185px; }
        .pos-title { font-size: 20px; font-weight: 950; margin: 0; letter-spacing: -.03em; line-height: 1.05; }
        .pos-subtitle { color: var(--pos-muted); font-size: 10.5px; margin-top: 2px; line-height: 1.2; white-space: nowrap; }
        .pos-header-grid { flex: 1; display: grid; grid-template-columns: minmax(168px, 1.1fr) minmax(115px, .8fr) minmax(122px, .75fr) minmax(148px, .9fr); gap: 8px; align-items: center; min-width: 0; }
        .pos-field-chip { min-height: 45px; border: 1px solid var(--pos-border); background: #f8fafc; border-radius: 15px; padding: 6px 10px; display: flex; flex-direction: column; justify-content: center; overflow: hidden; }
        .pos-field-chip label { color: var(--pos-muted); font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; line-height: 1.1; margin: 0 0 2px; }
        .pos-field-chip .chip-value { color: var(--pos-text); font-size: 12px; font-weight: 900; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pos-field-chip select, .pos-field-chip input { border: 0; outline: 0; background: transparent; padding: 0; font-size: 12px; color: var(--pos-text); font-weight: 900; width: 100%; }
        .pos-customer-bar { min-height: 45px; display: flex; gap: 8px; align-items: center; border: 1px solid var(--pos-border); background: #f8fafc; border-radius: 15px; padding: 5px 7px 5px 10px; position: relative; min-width: 0; }
        .pos-customer-bar input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; font-size: 12px; font-weight: 850; color: var(--pos-text); }
        .pos-icon-btn { border: 0; width: 34px; height: 34px; flex: 0 0 34px; border-radius: 12px; background: #e2e8f0; color: #0f172a; display: grid; place-items: center; }
        .pos-icon-btn.primary { color: #fff; background: linear-gradient(135deg, var(--pos-brand-1), var(--pos-brand-2)); }
        .pos-icon-btn.danger { color: #b91c1c; background: #fee2e2; }
        .pos-shell { flex: 1; min-height: 0; padding: 12px; display: grid; grid-template-columns: minmax(0, 1fr) minmax(340px, 360px); gap: 12px; align-items: stretch; }
        .pos-workspace { min-width: 0; min-height: 0; display: grid; grid-template-rows: auto minmax(0, 1fr); gap: 12px; }
        .pos-panel { min-height: 0; background: var(--pos-card); border: 1px solid var(--pos-border); border-radius: var(--pos-radius); box-shadow: var(--pos-shadow); overflow: hidden; display: flex; flex-direction: column; }
        .pos-panel-head { padding: 11px 14px; border-bottom: 1px solid var(--pos-border); display: flex; align-items: center; justify-content: space-between; gap: 10px; background: linear-gradient(180deg, rgba(248,250,252,.96), rgba(255,255,255,.94)); }
        .pos-panel-title-row { display:flex; align-items:center; gap:8px; }
        .pos-step-badge { min-width: 52px; height: 24px; border-radius: 999px; display:inline-flex; align-items:center; justify-content:center; background: #e0ecff; color:#1d4ed8; font-size:9px; font-weight:950; letter-spacing:.06em; text-transform:uppercase; }
        .pos-panel-title { font-size: 15px; font-weight: 950; margin: 0; letter-spacing: -.02em; }
        .pos-panel-sub { color: var(--pos-muted); font-size: 10.5px; margin-top: 1px; }
        .pos-panel-body { padding: 12px 14px; flex: 1; min-height: 0; overflow: auto; }
        .pos-left-form { display: grid; gap: 10px; }
        .pos-product-panel { min-height: auto; }
        .pos-product-body { overflow: visible; }
        .pos-product-layout { display: grid; grid-template-columns: minmax(360px, 1fr) minmax(330px, .9fr); gap: 12px; align-items: stretch; }
        .pos-product-search-column { min-width: 0; display: grid; grid-template-columns: minmax(320px, 1fr) minmax(260px, .7fr); gap: 12px; align-items: stretch; }
        .pos-product-search-stack { min-width: 0; display: grid; gap: 10px; }
        .pos-product-stock-stack { min-width: 0; display: grid; gap: 10px; }
        .pos-product-entry-card { min-width: 0; border: 1px solid var(--pos-border); background: linear-gradient(180deg, #f8fbff, #ffffff); border-radius: 18px; padding: 12px; display: flex; flex-direction: column; gap: 10px; }
        .pos-entry-title { display:flex; align-items:center; justify-content:space-between; gap:10px; padding-bottom: 2px; }
        .pos-entry-title h3 { margin:0; font-size:13px; font-weight:950; }
        .pos-entry-title span { color:var(--pos-muted); font-size:10px; font-weight:850; }
        .product-mini-card { border: 1px solid var(--pos-border); background:#f8fafc; border-radius: 16px; padding: 10px; min-height: 72px; }
        .pos-bill-panel { min-height: 0; }
        .pos-bill-panel .pos-panel-body { overflow: hidden; }
        .pos-summary-panel { min-height: 0; }
        .pos-summary-panel .pos-panel-body { overflow: auto; }
        .pos-search-box { border: 2px solid rgba(37, 99, 235, .18); background: #f8fbff; border-radius: 16px; padding: 8px; display: flex; align-items: center; gap: 8px; position: relative; }
        .pos-search-box input { border: 0; outline: 0; background: transparent; flex: 1; min-width: 0; font-size: 13px; font-weight: 850; }
        .pos-scan-btn { border: 0; border-radius: 13px; min-width: 38px; height: 38px; color: #fff; background: linear-gradient(135deg, #0f172a, #334155); display: grid; place-items: center; }
        /* Modern responsive customer/product search dropdown */
        .pos-product-panel { position: relative; z-index: 35; overflow: visible; }
        .pos-product-body, .pos-product-body .pos-panel-body { overflow: visible; }
        .compact-card, .pos-customer-bar, .pos-search-box { position: relative; overflow: visible; }
        .suggestion-list {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 8px);
            width: 100%;
            background: rgba(255, 255, 255, .98);
            border: 1px solid rgba(203, 213, 225, .98);
            border-radius: 18px;
            box-shadow: 0 22px 48px rgba(15, 23, 42, .20);
            max-height: 330px;
            overflow-y: auto;
            overflow-x: hidden;
            overscroll-behavior: contain;
            z-index: 9999;
            display: none;
            padding: 7px;
            backdrop-filter: blur(16px);
        }
        .suggestion-list::-webkit-scrollbar { width: 7px; }
        .suggestion-list::-webkit-scrollbar-track { background: transparent; }
        .suggestion-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
        .suggestion-header {
            padding: 6px 8px 8px;
            display: flex;
            justify-content: space-between;
            gap: 8px;
            color: #64748b;
            font-size: 9px;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: .065em;
        }
        .suggestion-item {
            min-height: 56px;
            padding: 8px 9px;
            display: flex;
            align-items: center;
            gap: 9px;
            cursor: pointer;
            border: 1px solid transparent;
            border-radius: 14px;
            margin-bottom: 6px;
            background: #ffffff;
            transition: background .16s ease, border-color .16s ease, transform .16s ease, box-shadow .16s ease;
        }
        .suggestion-item:last-child { margin-bottom: 0; }
        .suggestion-item:hover, .suggestion-item.active {
            background: #eff6ff;
            border-color: rgba(37, 99, 235, .20);
            box-shadow: 0 8px 18px rgba(37, 99, 235, .10);
            transform: translateY(-1px);
        }
        .suggestion-item.is-empty { cursor: default; background: #f8fafc; }
        .suggestion-item.is-empty:hover { transform: none; box-shadow: none; border-color: transparent; }
        .suggestion-item.is-create { background: linear-gradient(135deg, #f0fdf4, #ecfeff); border-color: rgba(34,197,94,.22); }
        .suggestion-img {
            width: 38px;
            height: 38px;
            border-radius: 13px;
            object-fit: cover;
            background: linear-gradient(135deg, #dbeafe, #ede9fe);
            flex: 0 0 38px;
            display: grid;
            place-items: center;
            font-weight: 950;
            color:#2563eb;
            text-transform: uppercase;
        }
        .suggestion-content { flex: 1 1 auto; min-width: 0; }
        .suggestion-name { font-size: 12px; font-weight: 950; color: var(--pos-text); line-height: 1.18; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .suggestion-meta { color: var(--pos-muted); font-size: 9.8px; margin-top: 3px; line-height: 1.25; display: flex; flex-wrap: wrap; gap: 3px 7px; }
        .suggestion-meta span { white-space: nowrap; }
        .suggestion-stock {
            margin-left: auto;
            text-align: right;
            font-size: 9.5px;
            font-weight: 950;
            min-width: 70px;
            flex: 0 0 auto;
            color: #0f172a;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 6px 7px;
            line-height: 1.25;
        }
        .suggestion-stock strong { display: block; font-size: 10.5px; color: #16a34a; }
        .product-preview { border: 1px solid var(--pos-border); border-radius: 18px; overflow: hidden; background: #f8fafc; display: grid; grid-template-columns: 104px 1fr; min-height: 126px; }
        .product-preview-img { width: 100%; height: 100%; min-height: 126px; background: linear-gradient(135deg, #dbeafe, #ede9fe); display: grid; place-items: center; color: #2563eb; font-size: 34px; font-weight: 950; }
        .product-preview-info { padding: 10px; display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .product-name { font-size: 14px; font-weight: 950; line-height: 1.12; margin: 0; }
        .product-meta-line { color: var(--pos-muted); font-size: 10.5px; line-height: 1.2; }
        .stock-badge { border-radius: 999px; padding: 5px 8px; display: inline-flex; align-items: center; gap: 5px; font-size: 10px; font-weight: 950; width: fit-content; }
        .stock-ok { background: #dcfce7; color: #15803d; }
        .stock-low { background: #fef3c7; color: #b45309; }
        .stock-no { background: #fee2e2; color: #b91c1c; }
        .pos-label { font-size: 10px; font-weight: 950; color: var(--pos-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 5px; }
        .size-grid, .color-grid, .pay-method-grid { display: flex; flex-wrap: wrap; gap: 7px; }
        .size-chip, .color-chip, .pay-chip { border: 1px solid var(--pos-border); background: #fff; color: var(--pos-text); border-radius: 999px; min-height: 30px; padding: 6px 11px; font-size: 11px; font-weight: 950; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; }
        .size-chip.active, .color-chip.active, .pay-chip.active { border-color: transparent; color: #fff; background: linear-gradient(135deg, var(--pos-brand-1), var(--pos-brand-2)); box-shadow: 0 8px 18px rgba(37, 99, 235, .22); }
        .size-chip.disabled { opacity: .42; pointer-events: none; text-decoration: line-through; }
        .color-dot { width: 12px; height: 12px; border-radius: 50%; border: 1px solid rgba(15,23,42,.15); background: #cbd5e1; }
        .quick-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .quick-row.three { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .pos-input { width: 100%; border: 1px solid var(--pos-border); border-radius: 14px; background: #fff; min-height: 39px; padding: 7px 10px; outline: 0; font-size: 12px; font-weight: 850; color: var(--pos-text); }
        .qty-control { display: grid; grid-template-columns: 38px 1fr 38px; gap: 6px; }
        .qty-control button { border: 0; border-radius: 13px; background: #e2e8f0; font-size: 16px; font-weight: 950; }
        .add-bill-btn { position: relative; border: 0; border-radius: 22px; min-height: 46px; color: #fff; font-size: 13px; font-weight: 950; overflow: hidden; isolation: isolate; background: linear-gradient(135deg, #16a34a 0%, #22c55e 45%, #0ea5e9 100%); box-shadow: 0 18px 34px rgba(22, 163, 74, .34), inset 0 -2px 0 rgba(255,255,255,.18); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7px; text-align: center; transform: translateZ(0); transition: transform .18s ease, box-shadow .18s ease, filter .18s ease; }
        .add-bill-btn::before { content: ''; position: absolute; inset: -1px; background: linear-gradient(120deg, transparent 0%, transparent 36%, rgba(255,255,255,.38) 48%, transparent 60%, transparent 100%); transform: translateX(-130%); animation: addBillShine 2.6s infinite; z-index: -1; }
        .add-bill-btn::after { content: ''; position: absolute; inset: 9px; border: 1px solid rgba(255,255,255,.32); border-radius: 18px; pointer-events: none; }
        .add-bill-btn:hover { transform: translateY(-2px) scale(1.02); box-shadow: 0 24px 46px rgba(22, 163, 74, .42), 0 0 0 5px rgba(34, 197, 94, .12); filter: saturate(1.08); }
        .add-bill-btn:active { transform: translateY(0) scale(.99); }
        .add-bill-icon { width: 38px; height: 38px; border-radius: 15px; background: rgba(255,255,255,.22); display: grid; place-items: center; box-shadow: inset 0 0 0 1px rgba(255,255,255,.24), 0 9px 20px rgba(0,0,0,.16); animation: addBillPulse 1.7s infinite; }
        .add-bill-icon svg { width: 21px; height: 21px; stroke-width: 3; }
        .add-bill-title { font-size: 14px; line-height: 1; letter-spacing: .045em; text-transform: uppercase; }
        .add-bill-sub { font-size: 9.5px; line-height: 1; opacity: .9; font-weight: 850; }
        @keyframes addBillShine { 0% { transform: translateX(-130%); } 55%, 100% { transform: translateX(130%); } }
        @keyframes addBillPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
        .small-tools { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .small-tool-btn { min-height: 38px; border: 1px solid var(--pos-border); background: #f8fafc; border-radius: 14px; font-size: 11px; font-weight: 950; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .bill-customer-card { border: 1px solid var(--pos-border); border-radius: 16px; background: #f8fafc; padding: 8px 10px; display: flex; align-items: center; gap: 10px; margin-bottom: 9px; }
        .avatar-circle { width: 38px; height: 38px; border-radius: 14px; background: linear-gradient(135deg, var(--pos-brand-1), var(--pos-brand-2)); color: #fff; display: grid; place-items: center; font-weight: 950; flex: 0 0 auto; }
        .customer-name { font-size: 12px; font-weight: 950; color: var(--pos-text); }
        .customer-meta { color: var(--pos-muted); font-size: 10px; margin-top: 1px; }
        .customer-mini-stats { margin-left: auto; display: flex; gap: 7px; flex-wrap: wrap; justify-content: flex-end; }
        .mini-stat { border-radius: 999px; background: #fff; border: 1px solid var(--pos-border); padding: 4px 8px; font-size: 10px; font-weight: 950; color: #334155; }
        .bill-table-wrap { flex: 1; min-height: 0; overflow: auto; border: 1px solid var(--pos-border); border-radius: 16px; background: #f8fafc; padding: 8px; }
        .bill-table { width: 100%; border-collapse: separate; border-spacing: 0 7px; }
        .bill-table thead th { position: sticky; top: 0; z-index: 5; background: #f1f5f9; color: #334155; text-transform: uppercase; letter-spacing: .05em; font-size: 9.5px; font-weight: 950; padding: 8px 7px; white-space: nowrap; border: 0; }
        .bill-table thead th:first-child { border-top-left-radius: 12px; border-bottom-left-radius: 12px; }
        .bill-table thead th:last-child { border-top-right-radius: 12px; border-bottom-right-radius: 12px; }
        .bill-table tbody tr { background: #fff; box-shadow: 0 4px 12px rgba(15,23,42,.05); }
        .bill-table tbody td { padding: 8px 7px; border-top: 1px solid #eef2f7; border-bottom: 1px solid #eef2f7; font-size: 11px; vertical-align: middle; white-space: nowrap; }
        .bill-table tbody td:first-child { border-left: 1px solid #eef2f7; border-top-left-radius: 13px; border-bottom-left-radius: 13px; min-width: 160px; }
        .bill-table tbody td:last-child { border-right: 1px solid #eef2f7; border-top-right-radius: 13px; border-bottom-right-radius: 13px; }
        .line-product { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .line-img { width: 34px; height: 34px; border-radius: 11px; background: linear-gradient(135deg, #dbeafe, #ede9fe); color: #2563eb; display: grid; place-items: center; font-weight: 950; flex: 0 0 auto; }
        .line-title { font-weight: 950; max-width: 150px; overflow: hidden; text-overflow: ellipsis; }
        .line-sub { color: var(--pos-muted); font-size: 9.5px; margin-top: 1px; }
        .line-edit-input { width: 70px; border: 1px solid var(--pos-border); border-radius: 10px; padding: 5px; font-weight: 850; font-size: 11px; }
        .row-action { border: 0; width: 30px; height: 30px; border-radius: 10px; display: inline-grid; place-items: center; margin: 1px; }
        .row-action.edit { background: #dbeafe; color: #1d4ed8; }
        .row-action.remove { background: #fee2e2; color: #b91c1c; }
        .empty-bill { height: 100%; min-height: 280px; display: grid; place-items: center; color: var(--pos-muted); text-align: center; padding: 30px; }
        .empty-icon { width: 72px; height: 72px; border-radius: 24px; margin: 0 auto 12px; background: linear-gradient(135deg, #e0f2fe, #ede9fe); color: #2563eb; display: grid; place-items: center; }
        .summary-body { display: flex; flex-direction: column; gap: 10px; }
        .summary-card { border: 1px solid var(--pos-border); background: #f8fafc; border-radius: 17px; padding: 10px; }
        .summary-line { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 5px 0; color: #334155; font-size: 11.5px; }
        .summary-line strong { color: var(--pos-text); font-size: 12px; }
        .summary-line.saving { color: #15803d; font-weight: 950; }
        .summary-line.net { border-top: 1px dashed #cbd5e1; margin-top: 5px; padding-top: 9px; font-size: 13px; font-weight: 950; }
        .gst-card { border: 1px dashed #93c5fd; background: linear-gradient(180deg, #eff6ff, #ffffff); }
        .gst-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px; }
        .gst-toggle-wrap { display:inline-flex; align-items:center; gap:7px; font-size:10px; font-weight:950; color:#334155; white-space:nowrap; cursor:pointer; }
        .gst-toggle-wrap input { width:17px; height:17px; accent-color: var(--pos-brand-1); cursor:pointer; }
        .gst-status-pill { border-radius:999px; padding:5px 8px; font-size:10px; font-weight:950; background:#fee2e2; color:#b91c1c; text-align:center; min-width:70px; }
        .gst-status-pill.on { background:#dcfce7; color:#15803d; }
        .gst-tax-row { display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:7px; margin:7px 0; }
        .gst-tax-row .pos-input { min-height:34px; font-size:11px; }
        .gst-hidden { display:none !important; }
        .gst-detail { transition: opacity .16s ease; }
        .grand-total-box { border-radius: 20px; padding: 15px; color: #fff; background: linear-gradient(135deg, #0f172a, #1e293b); box-shadow: 0 14px 28px rgba(15, 23, 42, .24); }
        .grand-label { font-size: 11px; opacity: .78; font-weight: 850; text-transform: uppercase; letter-spacing: .08em; }
        .grand-value { font-size: 30px; font-weight: 950; line-height: 1; margin-top: 4px; }
        .payment-row { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; margin-top: 7px; }
        .split-rows { display: grid; gap: 7px; }
        .split-row { display: grid; grid-template-columns: 88px 1fr 1fr; gap: 6px; align-items: center; }
        .action-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 7px; }
        .action-grid .wide { grid-column: span 2; }
        .pos-action { border: 0; border-radius: 15px; min-height: 42px; padding: 8px 10px; font-size: 11px; font-weight: 950; display: inline-flex; align-items: center; justify-content: center; gap: 6px; }
        .pos-action.primary { color: #fff; background: linear-gradient(135deg, var(--pos-brand-1), var(--pos-brand-2)); }
        .pos-action.success { color: #fff; background: linear-gradient(135deg, var(--pos-success), #22c55e); }
        .pos-action.dark { color: #fff; background: #0f172a; }
        .pos-action.warning { color: #92400e; background: #fef3c7; }
        .pos-action.danger { color: #b91c1c; background: #fee2e2; }
        .pos-action.light { color: #0f172a; background: #e2e8f0; }
        .held-count-badge { min-width: 22px; height: 22px; border-radius: 999px; background: #ef4444; color:#fff; display:inline-grid; place-items:center; font-size:10px; font-weight:950; }
        .held-card { border: 1px solid var(--pos-border); background: #fff; border-radius: 16px; padding: 10px; display: flex; gap: 10px; align-items: center; margin-bottom: 9px; }
        .held-no { font-size: 12px; font-weight: 950; }
        .held-meta { color: var(--pos-muted); font-size: 10.5px; margin-top: 2px; }
        .modal-title { font-size: 15px; font-weight: 950; }
        .modal .form-label { font-size: 11px; font-weight: 850; margin-bottom: 4px; }
        .modal .form-control,.modal .form-select { min-height: 36px; font-size: 12px; border-radius: 12px; padding: 6px 10px; }
        .preview-paper { width: 320px; max-width: 100%; margin: 0 auto; background: #fff; color:#111827; font-family: Arial, sans-serif; padding: 14px; border: 1px solid #e5e7eb; }
        .preview-paper h3 { font-size: 16px; text-align:center; margin: 0 0 4px; font-weight: 800; }
        .preview-paper .center { text-align:center; }
        .preview-paper .muted { color:#6b7280; font-size: 10px; }
        .preview-paper table { width:100%; border-collapse: collapse; font-size: 10.5px; }
        .preview-paper th, .preview-paper td { padding: 4px 2px; border-bottom: 1px dashed #d1d5db; vertical-align: top; }
        .preview-paper .right { text-align:right; }
        .preview-paper .total { font-size: 14px; font-weight: 800; }
        .preview-barcode-wrap { text-align:center; margin:10px 0 4px; padding:8px 6px; border-top:1px dashed #d1d5db; border-bottom:1px dashed #d1d5db; }
        .preview-barcode-img { width:250px; max-width:100%; height:68px; object-fit:contain; display:block; margin:0 auto 3px; }
        .preview-barcode-no { font-size:11px; font-weight:800; letter-spacing:.08em; color:#111827; }
        .pos-pending-note { border:1px dashed #f59e0b; background:#fffbeb; color:#92400e; border-radius:16px; padding:10px; font-size:11px; font-weight:850; line-height:1.35; }
        .pos-pending-note strong { color:#78350f; }
        .scanner-video { width: 100%; max-height: 360px; background:#0f172a; border-radius:16px; object-fit: cover; }
        .dark-pos { --pos-bg: #0f172a; --pos-card: #111827; --pos-border: rgba(148,163,184,.24); --pos-text:#e5e7eb; --pos-muted:#94a3b8; }
        .dark-pos .pos-topbar, .dark-pos .pos-field-chip, .dark-pos .pos-customer-bar, .dark-pos .summary-card, .dark-pos .product-preview, .dark-pos .bill-customer-card, .dark-pos .product-mini-card, .dark-pos .pos-product-entry-card, .dark-pos .bill-table-wrap { background:#111827; }
        .dark-pos .pos-panel-head, .dark-pos .pos-panel-body { background:#111827; }
        .dark-pos .pos-input, .dark-pos .size-chip, .dark-pos .color-chip, .dark-pos .pay-chip, .dark-pos .held-card, .dark-pos .suggestion-list { background:#1f2937; color:#e5e7eb; }
        .dark-pos .bill-table tbody tr { background:#1f2937; }
        .dark-pos .bill-table thead th { background:#1e293b; color:#cbd5e1; }
        .dark-pos .suggestion-item { border-bottom-color: rgba(148,163,184,.12); }
        .print-only { display:none; }

        /* Compact Product Selection - essential fields only */
        .compact-product-layout { grid-template-columns: minmax(245px, .82fr) minmax(310px, 1fr) minmax(180px, .48fr) minmax(330px, .95fr) !important; gap: 8px; align-items: stretch; width: 100%; max-width: 100%; overflow: visible; }
        .compact-card { min-width: 0; border: 1px solid var(--pos-border); background: linear-gradient(180deg, #f8fbff, #ffffff); border-radius: 16px; padding: 10px; display: flex; flex-direction: column; gap: 9px; }
        .compact-card-title { display:flex; align-items:center; justify-content:space-between; gap:8px; color: var(--pos-text); font-size: 11px; font-weight: 950; text-transform: uppercase; letter-spacing: .055em; }
        .compact-card-sub { color: var(--pos-muted); font-size: 10px; font-weight: 800; text-transform: none; letter-spacing: 0; }
        .compact-search-card .pos-search-box { min-height: 42px; padding: 6px 8px; }
        .compact-search-card .pos-search-box input { font-size: 12.5px; }
        .compact-product-preview { min-height: 78px; grid-template-columns: 72px 1fr; border-radius: 15px; }
        .compact-product-preview .product-preview-img, .compact-search-card .product-preview-img { min-height: 78px; font-size: 22px; }
        .compact-search-card .product-preview { min-height: 78px; grid-template-columns: 72px 1fr; border-radius: 15px; }
        .compact-search-card .product-preview-info { padding: 8px 9px; gap: 4px; }
        .compact-search-card .product-name { font-size: 13px; }
        .compact-search-card .product-meta-line { font-size: 10px; }
        .compact-search-card .stock-badge { padding: 4px 7px; font-size: 9.5px; }
        .compact-selector-card { gap: 7px; padding: 8px; }
        .compact-selector-group { min-height: 44px; }
        .compact-selector-group .pos-label { margin-bottom: 4px; font-size: 9.3px; }
        .compact-selector-card .size-grid, .compact-selector-card .color-grid { gap: 4px; max-height: 62px; overflow: auto; }
        .compact-selector-card .size-chip, .compact-selector-card .color-chip { min-height: 24px; padding: 4px 7px; font-size: 9.5px; }
        .compact-field-grid { display: grid; grid-template-columns: minmax(105px, 1fr) minmax(82px, .74fr) minmax(88px, .78fr); gap: 6px; align-items: stretch; }
        .compact-field-grid .discount-wide { grid-column: span 2; }
        .price-entry-box { min-width: 0; background: #ffffff; border: 1px solid rgba(203,213,225,.92); border-radius: 13px; padding: 7px; display: flex; flex-direction: column; justify-content: center; gap: 4px; }
        .price-entry-box .pos-label { margin: 0; font-size: 9.2px; }
        .qty-entry-box { background: linear-gradient(180deg, #ffffff, #f8fbff); }
        .price-entry-box .pos-input { width: 100%; min-width: 0; min-height: 33px; padding: 5px 7px; font-size: 11px; }
        .compact-customer-card .pos-customer-bar { min-height: 42px; }
        .bill-actions-menu .dropdown-menu { border-radius: 16px; border: 1px solid var(--pos-border); box-shadow: 0 18px 40px rgba(15,23,42,.16); padding: 7px; }
        .bill-actions-menu .dropdown-item { border-radius: 12px; font-size: 11px; font-weight: 850; padding: 8px 10px; display: flex; align-items: center; gap: 7px; }
        .bill-history-tools { display: grid; grid-template-columns: 1fr 170px; gap: 8px; }
        .history-card { border: 1px solid var(--pos-border); background: #fff; border-radius: 16px; padding: 10px; display: flex; gap: 10px; align-items: center; margin-bottom: 9px; }
        .history-status-pill { border-radius: 999px; padding: 4px 8px; font-size: 10px; font-weight: 950; background: #e2e8f0; color: #475569; display: inline-flex; align-items: center; gap: 4px; }
        .history-status-pill.paid { background: #dcfce7; color: #15803d; }
        .history-status-pill.hold { background: #e0ecff; color: #1d4ed8; }
        .history-status-pill.unpaid { background: #fef3c7; color: #b45309; }
        .compact-entry-card { gap: 8px; overflow: visible; padding: 9px; transform: translateX(-6px); max-width: calc(100% + 6px); }
        .compact-entry-card .pos-input { min-height: 33px; border-radius: 11px; padding: 5px 7px; font-size: 11px; }
        .compact-entry-card .qty-control { grid-template-columns: 30px minmax(38px, 1fr) 30px; gap: 4px; }
        .compact-entry-card .qty-control button { border-radius: 10px; min-height: 33px; display: grid; place-items: center; }
        .compact-entry-card .qty-control input { min-width: 0; }
        .compact-inline-add { margin-top: auto; display: grid; grid-template-columns: minmax(0, 1fr); gap: 6px; align-items: stretch; }
        .compact-inline-add .add-bill-btn { width: 100%; max-width: 100%; min-height: 46px; padding: 7px 10px; border-radius: 14px; flex-direction: row; gap: 8px; line-height: 1.08; justify-content: center; }
        .compact-inline-add .add-bill-icon { width: 30px; height: 30px; border-radius: 12px; flex: 0 0 30px; }
        .compact-inline-add .add-bill-title { font-size: 12.5px; }
        .compact-inline-add .add-bill-sub { font-size: 9px; opacity: .88; display: block; }
        .compact-hint { color: var(--pos-muted); font-size: 9px; text-align:center; font-weight: 850; line-height: 1.18; }
        .pos-hidden-tools { position: absolute !important; width: 1px !important; height: 1px !important; overflow: hidden !important; opacity: 0 !important; pointer-events: none !important; }
        .dark-pos .compact-card { background:#111827; }
        @media (max-width: 1499px) {
            .pos-header-grid { grid-template-columns: minmax(150px,1fr) minmax(110px,.7fr) minmax(110px,.7fr) minmax(140px,.8fr) minmax(220px,1.3fr); }
            .pos-shell { grid-template-columns: minmax(0, 1fr) 340px; }
            .compact-product-layout { grid-template-columns: minmax(235px, .82fr) minmax(295px, 1fr) minmax(170px, .48fr) minmax(310px, .95fr) !important; gap: 7px; }
            .compact-field-grid { grid-template-columns: minmax(100px, 1fr) minmax(78px, .74fr) minmax(84px, .78fr); gap: 5px; }
            .compact-inline-add .add-bill-btn { min-height: 44px; }
            .compact-inline-add .add-bill-icon { width: 30px; height: 30px; border-radius: 12px; }
        }
        @media (max-width: 1199px) {
            body { overflow: auto; }
            .pos-page { min-height: 100vh; height: auto; }
            .pos-topbar { flex-wrap: wrap; }
            .pos-title-wrap { flex: 1 1 180px; }
            .pos-header-grid { flex: 1 1 100%; grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .pos-shell { height: auto; grid-template-columns: 1fr; }
            .pos-workspace { grid-template-rows: auto auto; }
            .pos-panel { min-height: auto; }
            .pos-bill-panel { min-height: 420px; }
            .pos-summary-panel { min-height: 520px; }
        }
        @media (max-width: 991px) {
            .pos-product-search-column { grid-template-columns: 1fr; }
        }
        @media (max-width: 767px) {
            .pos-topbar { padding: 8px; gap: 8px; }
            .pos-back-btn { width: 38px; height: 38px; border-radius: 13px; }
            .pos-title { font-size: 17px; }
            .pos-subtitle { white-space: normal; }
            .pos-header-grid { grid-template-columns: 1fr; }
            .pos-shell { padding: 8px; gap: 8px; }
            .pos-workspace { gap: 8px; }
            .pos-panel-head { padding: 10px 11px; }
            .pos-panel-body { padding: 10px; }
            .compact-product-layout { grid-template-columns: 1fr !important; gap: 8px; }
            .compact-field-grid { grid-template-columns: 1fr; }
            .compact-field-grid .discount-wide { grid-column: span 1; }
            .price-entry-box { padding: 7px; }
            .compact-entry-card .qty-control { grid-template-columns: 38px 1fr 38px; }
            .compact-inline-add .add-bill-btn { min-height: 48px; gap: 10px; }
            .compact-inline-add .add-bill-title { font-size: 13px; }
            .compact-inline-add .add-bill-icon { width: 30px; height: 30px; }
            .compact-inline-add .compact-hint { text-align:center; }
            .pos-step-badge { min-width: 45px; height: 22px; font-size: 8.5px; }
            .quick-row, .quick-row.three, .payment-row, .split-row { grid-template-columns: 1fr; }
            .action-grid { grid-template-columns: 1fr; }
            .action-grid .wide { grid-column: span 1; }
            .product-preview { grid-template-columns: 86px 1fr; }
            .product-preview-img { min-height: 115px; }
            .customer-mini-stats { width: 100%; justify-content: flex-start; margin-left: 0; }
            .bill-customer-card { align-items:flex-start; flex-wrap: wrap; }
        }

        @media (max-width: 767px) {
            .suggestion-list {
                max-height: 52vh;
                border-radius: 16px;
                padding: 6px;
            }
            .suggestion-item { min-height: 54px; padding: 8px; gap: 8px; }
            .suggestion-stock { min-width: 64px; padding: 5px 6px; font-size: 9px; }
            .suggestion-meta { font-size: 9.4px; gap: 2px 6px; }
        }

        /* =========================================================
           FINAL COMPACT FIX - PRODUCT SELECTION HEIGHT REDUCED
           Added for POS Step 1 compact full-screen billing layout
           ========================================================= */

        .pos-redesigned-shell {
            padding: 10px !important;
            gap: 10px !important;
        }

        .pos-workspace {
            gap: 9px !important;
        }

        .pos-product-panel {
            flex: 0 0 auto !important;
        }

        .pos-product-panel .pos-panel-head {
            padding: 8px 12px !important;
            min-height: 52px !important;
        }

        .pos-product-panel .pos-panel-title-row {
            gap: 6px !important;
        }

        .pos-product-panel .pos-step-badge {
            min-width: 46px !important;
            height: 20px !important;
            font-size: 8px !important;
        }

        .pos-product-panel .pos-panel-title {
            font-size: 14px !important;
            line-height: 1.1 !important;
        }

        .pos-product-panel .pos-panel-sub {
            font-size: 9.5px !important;
            line-height: 1.15 !important;
            margin-top: 1px !important;
        }

        .pos-product-panel #focusSearchBtn {
            width: 34px !important;
            height: 34px !important;
            border-radius: 12px !important;
        }

        .pos-product-panel .pos-panel-body {
            padding: 8px 10px 10px !important;
        }

        .compact-product-layout {
            grid-template-columns: minmax(230px, 1.05fr) minmax(310px, 1.25fr) minmax(165px, .62fr) minmax(300px, 1.18fr) !important;
            gap: 7px !important;
            align-items: stretch !important;
        }

        .compact-card {
            padding: 7px 8px !important;
            gap: 6px !important;
            border-radius: 14px !important;
            min-height: 106px !important;
        }

        .compact-card-title {
            min-height: 15px !important;
            font-size: 10px !important;
            line-height: 1 !important;
            letter-spacing: .045em !important;
            gap: 6px !important;
            margin: 0 !important;
        }

        .compact-card-sub {
            font-size: 8.8px !important;
            line-height: 1 !important;
        }

        .compact-hint {
            font-size: 8.4px !important;
            line-height: 1.15 !important;
            margin: 0 !important;
        }

        .compact-customer-card .pos-customer-bar {
            min-height: 36px !important;
            padding: 4px 5px 4px 8px !important;
            border-radius: 12px !important;
            gap: 5px !important;
        }

        .compact-customer-card .pos-customer-bar input {
            font-size: 11px !important;
        }

        .compact-customer-card .pos-icon-btn {
            width: 30px !important;
            height: 30px !important;
            flex: 0 0 30px !important;
            border-radius: 10px !important;
        }

        .compact-search-card .pos-search-box {
            min-height: 36px !important;
            padding: 4px 5px !important;
            border-radius: 13px !important;
            gap: 6px !important;
        }

        .compact-search-card .pos-search-box input {
            font-size: 11.2px !important;
        }

        .compact-search-card .pos-scan-btn {
            min-width: 32px !important;
            width: 32px !important;
            height: 31px !important;
            border-radius: 10px !important;
        }

        .compact-product-preview,
        .compact-search-card .product-preview {
            min-height: 54px !important;
            grid-template-columns: 54px minmax(0, 1fr) !important;
            border-radius: 13px !important;
        }

        .compact-product-preview .product-preview-img,
        .compact-search-card .product-preview-img {
            min-height: 54px !important;
            font-size: 18px !important;
        }

        .compact-search-card .product-preview-info {
            padding: 6px 7px !important;
            gap: 2px !important;
            justify-content: center !important;
        }

        .compact-search-card .product-name {
            font-size: 12px !important;
            line-height: 1.08 !important;
            margin: 0 !important;
        }

        .compact-search-card .product-meta-line {
            font-size: 8.8px !important;
            line-height: 1.12 !important;
        }

        .compact-search-card .stock-badge {
            padding: 3px 6px !important;
            font-size: 8.3px !important;
            line-height: 1 !important;
        }

        .compact-selector-card {
            padding: 7px !important;
            gap: 5px !important;
        }

        .compact-selector-group {
            min-height: auto !important;
        }

        .compact-selector-group .pos-label {
            font-size: 8.5px !important;
            margin-bottom: 3px !important;
            line-height: 1 !important;
        }

        .compact-selector-card .size-grid,
        .compact-selector-card .color-grid {
            gap: 4px !important;
            max-height: 44px !important;
            overflow-y: auto !important;
        }

        .compact-selector-card .size-chip,
        .compact-selector-card .color-chip {
            min-height: 22px !important;
            padding: 3px 7px !important;
            font-size: 8.8px !important;
            line-height: 1 !important;
        }

        .compact-entry-card {
            padding: 7px !important;
            gap: 6px !important;
            transform: none !important;
            max-width: 100% !important;
        }

        .compact-field-grid {
            grid-template-columns: minmax(94px, 1.05fr) minmax(76px, .8fr) minmax(82px, .85fr) !important;
            gap: 5px !important;
        }

        .price-entry-box {
            padding: 5px !important;
            border-radius: 11px !important;
            gap: 3px !important;
        }

        .price-entry-box .pos-label {
            font-size: 8.5px !important;
            line-height: 1 !important;
        }

        .compact-entry-card .pos-input,
        .price-entry-box .pos-input {
            min-height: 29px !important;
            height: 29px !important;
            padding: 4px 6px !important;
            font-size: 10.5px !important;
            border-radius: 9px !important;
        }

        .compact-entry-card .qty-control {
            grid-template-columns: 27px minmax(34px, 1fr) 27px !important;
            gap: 3px !important;
        }

        .compact-entry-card .qty-control button {
            min-height: 29px !important;
            height: 29px !important;
            border-radius: 9px !important;
            font-size: 14px !important;
        }

        .compact-inline-add {
            margin-top: 0 !important;
            gap: 3px !important;
        }

        .compact-inline-add .add-bill-btn {
            min-height: 37px !important;
            height: 37px !important;
            padding: 5px 8px !important;
            border-radius: 12px !important;
            gap: 7px !important;
        }

        .compact-inline-add .add-bill-icon {
            width: 25px !important;
            height: 25px !important;
            border-radius: 9px !important;
            flex: 0 0 25px !important;
        }

        .compact-inline-add .add-bill-icon svg {
            width: 16px !important;
            height: 16px !important;
        }

        .compact-inline-add .add-bill-title {
            font-size: 11.5px !important;
            line-height: 1 !important;
        }

        .compact-inline-add .add-bill-sub {
            font-size: 8px !important;
            line-height: 1 !important;
        }

        .pos-bill-panel .pos-panel-head {
            padding-top: 8px !important;
            padding-bottom: 8px !important;
        }

        @media (max-width: 1599px) {
            .compact-product-layout {
                grid-template-columns: minmax(220px, 1fr) minmax(285px, 1.2fr) minmax(150px, .62fr) minmax(285px, 1.15fr) !important;
                gap: 6px !important;
            }

            .compact-card-sub {
                display: none !important;
            }

            .compact-card {
                padding: 7px !important;
            }

            .compact-field-grid {
                grid-template-columns: minmax(88px, 1fr) minmax(70px, .78fr) minmax(76px, .82fr) !important;
            }
        }

        @media (max-width: 1199px) {
            .compact-product-layout {
                grid-template-columns: 1fr 1fr !important;
            }

            .compact-card {
                min-height: auto !important;
            }
        }

        @media (max-width: 767px) {
            .pos-redesigned-shell {
                padding: 8px !important;
            }

            .compact-product-layout {
                grid-template-columns: 1fr !important;
            }

            .compact-field-grid {
                grid-template-columns: 1fr !important;
            }

            .compact-inline-add .add-bill-btn {
                height: 42px !important;
                min-height: 42px !important;
            }
        }


        /* =========================================================
           BILL ITEMS HEADER CUSTOMER FIX
           Customer details moved to Step 2 header. Old customer card
           below the header removed to avoid extra highlighted row.
           ========================================================= */

        .pos-bill-panel > .pos-panel-head {
            display: grid !important;
            grid-template-columns: minmax(220px, .85fr) minmax(360px, 1.4fr) auto !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 8px 12px !important;
        }

        .bill-items-title-wrap {
            min-width: 0;
        }

        .bill-header-customer-card {
            min-width: 0;
            width: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid var(--pos-border);
            background: linear-gradient(180deg, #f8fbff, #ffffff);
            border-radius: 16px;
            padding: 6px 8px;
            overflow: hidden;
        }

        .bill-header-customer-card .bill-header-avatar {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            border-radius: 13px;
            font-size: 12px;
        }

        .bill-header-customer-main {
            min-width: 120px;
            flex: 1 1 auto;
            overflow: hidden;
        }

        .bill-header-customer-card .customer-name {
            font-size: 13px;
            line-height: 1.12;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bill-header-customer-card .customer-meta {
            font-size: 9.5px;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .bill-header-customer-card .customer-mini-stats {
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 5px;
            flex: 0 0 auto;
            flex-wrap: nowrap;
            min-width: 0;
        }

        .bill-header-customer-card .mini-stat {
            padding: 4px 7px;
            font-size: 9px;
            line-height: 1;
            white-space: nowrap;
        }

        .bill-header-actions {
            justify-content: flex-end;
            min-width: max-content;
        }

        .pos-bill-panel .pos-panel-body {
            padding-top: 8px !important;
        }

        @media (max-width: 1499px) {
            .pos-bill-panel > .pos-panel-head {
                grid-template-columns: minmax(190px, .8fr) minmax(300px, 1.25fr) auto !important;
                gap: 8px !important;
            }

            .bill-header-customer-card .mini-stat {
                padding: 4px 6px;
                font-size: 8.6px;
            }
        }

        @media (max-width: 1199px) {
            .pos-bill-panel > .pos-panel-head {
                grid-template-columns: 1fr !important;
                align-items: stretch !important;
            }

            .bill-header-actions {
                justify-content: flex-start;
            }
        }

        @media (max-width: 767px) {
            .bill-header-customer-card {
                align-items: flex-start;
                flex-wrap: wrap;
            }

            .bill-header-customer-main {
                min-width: calc(100% - 46px);
            }

            .bill-header-customer-card .customer-mini-stats {
                width: 100%;
                margin-left: 0;
                justify-content: flex-start;
                flex-wrap: wrap;
            }
        }




        /* =========================================================
           PRODUCT SUGGESTION STOCK DATE + SIZE COLOR FIX
           Blue/green size chips and separate stock entry date chip.
           ========================================================= */

        /* Different color for every size + slightly bigger size text */
        .size-chip[class*="size-tone-"] {
            min-height: 28px !important;
            padding: 6px 10px !important;
            font-size: 12px !important;
            line-height: 1.08 !important;
            font-weight: 950 !important;
            letter-spacing: .01em !important;
        }

        .size-chip[class*="size-tone-"] .size-main-text {
            font-size: 13px !important;
            font-weight: 1000 !important;
            line-height: 1 !important;
        }

        .size-chip[class*="size-tone-"] small {
            font-size: 9.5px !important;
            font-weight: 950 !important;
            opacity: .92 !important;
            margin-left: 2px !important;
        }

        .size-chip.size-tone-0 { border-color: rgba(37, 99, 235, .32) !important; background: #eff6ff !important; color: #1d4ed8 !important; }
        .size-chip.size-tone-1 { border-color: rgba(22, 163, 74, .32) !important; background: #f0fdf4 !important; color: #15803d !important; }
        .size-chip.size-tone-2 { border-color: rgba(245, 158, 11, .36) !important; background: #fffbeb !important; color: #b45309 !important; }
        .size-chip.size-tone-3 { border-color: rgba(220, 38, 38, .28) !important; background: #fef2f2 !important; color: #b91c1c !important; }
        .size-chip.size-tone-4 { border-color: rgba(124, 58, 237, .30) !important; background: #f5f3ff !important; color: #6d28d9 !important; }
        .size-chip.size-tone-5 { border-color: rgba(14, 165, 233, .34) !important; background: #ecfeff !important; color: #0369a1 !important; }
        .size-chip.size-tone-6 { border-color: rgba(219, 39, 119, .30) !important; background: #fdf2f8 !important; color: #be185d !important; }
        .size-chip.size-tone-7 { border-color: rgba(79, 70, 229, .30) !important; background: #eef2ff !important; color: #4338ca !important; }
        .size-chip.size-tone-8 { border-color: rgba(13, 148, 136, .32) !important; background: #f0fdfa !important; color: #0f766e !important; }
        .size-chip.size-tone-9 { border-color: rgba(100, 116, 139, .32) !important; background: #f8fafc !important; color: #334155 !important; }
        .size-chip.size-tone-10 { border-color: rgba(234, 88, 12, .34) !important; background: #fff7ed !important; color: #c2410c !important; }
        .size-chip.size-tone-11 { border-color: rgba(5, 150, 105, .34) !important; background: #ecfdf5 !important; color: #047857 !important; }
        .size-chip.size-tone-12 { border-color: rgba(147, 51, 234, .32) !important; background: #faf5ff !important; color: #7e22ce !important; }
        .size-chip.size-tone-13 { border-color: rgba(225, 29, 72, .30) !important; background: #fff1f2 !important; color: #be123c !important; }

        .size-chip.size-tone-0.active { background: linear-gradient(135deg, #2563eb, #0ea5e9) !important; }
        .size-chip.size-tone-1.active { background: linear-gradient(135deg, #16a34a, #22c55e) !important; }
        .size-chip.size-tone-2.active { background: linear-gradient(135deg, #f59e0b, #f97316) !important; }
        .size-chip.size-tone-3.active { background: linear-gradient(135deg, #dc2626, #f43f5e) !important; }
        .size-chip.size-tone-4.active { background: linear-gradient(135deg, #7c3aed, #a855f7) !important; }
        .size-chip.size-tone-5.active { background: linear-gradient(135deg, #0891b2, #06b6d4) !important; }
        .size-chip.size-tone-6.active { background: linear-gradient(135deg, #db2777, #ec4899) !important; }
        .size-chip.size-tone-7.active { background: linear-gradient(135deg, #4f46e5, #6366f1) !important; }
        .size-chip.size-tone-8.active { background: linear-gradient(135deg, #0d9488, #14b8a6) !important; }
        .size-chip.size-tone-9.active { background: linear-gradient(135deg, #334155, #64748b) !important; }
        .size-chip.size-tone-10.active { background: linear-gradient(135deg, #ea580c, #f97316) !important; }
        .size-chip.size-tone-11.active { background: linear-gradient(135deg, #059669, #10b981) !important; }
        .size-chip.size-tone-12.active { background: linear-gradient(135deg, #9333ea, #c084fc) !important; }
        .size-chip.size-tone-13.active { background: linear-gradient(135deg, #e11d48, #fb7185) !important; }

        .size-chip[class*="size-tone-"].active {
            color: #ffffff !important;
            border-color: transparent !important;
            box-shadow: 0 8px 18px rgba(15, 23, 42, .18) !important;
        }

        .size-chip[class*="size-tone-"].disabled {
            opacity: .48 !important;
            color: #64748b !important;
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
        }

        .suggestion-title-line {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            min-width: 0;
        }

        .suggestion-title-line .suggestion-name {
            flex: 1 1 auto;
            min-width: 0;
        }

        .stock-entry-date-chip {
            width: fit-content;
            max-width: 100%;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            border: 1px solid rgba(124, 58, 237, .20);
            background: #f5f3ff;
            color: #6d28d9;
            border-radius: 999px;
            padding: 3px 7px;
            font-size: 8.5px;
            font-weight: 950;
            line-height: 1;
            white-space: nowrap;
        }

        .stock-entry-date-chip.in-suggestion {
            flex: 0 0 auto;
            margin-top: 0;
        }

        .stock-entry-date-row {
            margin-top: 4px !important;
        }

        .suggestion-size-date-line {
            display: flex !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 5px !important;
            margin-top: 5px !important;
        }

        .stock-entry-date-chip.in-suggestion-line {
            border-color: rgba(124, 58, 237, .28);
            background: linear-gradient(135deg, #f5f3ff, #ede9fe);
            color: #6d28d9;
            padding: 4px 8px;
            font-size: 9px;
        }

        .stock-entry-date-chip.entry-date-missing {
            border-color: rgba(100, 116, 139, .22);
            background: #f8fafc;
            color: #64748b;
        }

        .stock-entry-date-chip.in-preview {
            margin-top: 1px;
        }

        .stock-entry-date-chip .date-label {
            color: #7c3aed;
            opacity: .76;
            text-transform: uppercase;
            letter-spacing: .045em;
        }

        @media (max-width: 767px) {
            .suggestion-title-line {
                flex-wrap: wrap;
            }

            .stock-entry-date-chip.in-suggestion {
                margin-left: 0;
            }
        }




        .size-mini[class*="size-tone-"] {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 2px 7px;
            font-size: 11.8px;
            line-height: 1.1;
            font-weight: 950;
            border: 1px solid currentColor;
            background: rgba(255, 255, 255, .68);
        }

        .size-mini.size-tone-0 { color: #1d4ed8; }
        .size-mini.size-tone-1 { color: #15803d; }
        .size-mini.size-tone-2 { color: #b45309; }
        .size-mini.size-tone-3 { color: #b91c1c; }
        .size-mini.size-tone-4 { color: #6d28d9; }
        .size-mini.size-tone-5 { color: #0369a1; }
        .size-mini.size-tone-6 { color: #be185d; }
        .size-mini.size-tone-7 { color: #4338ca; }
        .size-mini.size-tone-8 { color: #0f766e; }
        .size-mini.size-tone-9 { color: #334155; }
        .size-mini.size-tone-10 { color: #c2410c; }
        .size-mini.size-tone-11 { color: #047857; }
        .size-mini.size-tone-12 { color: #7e22ce; }
        .size-mini.size-tone-13 { color: #be123c; }

        @media print {
            body * { visibility: hidden !important; }
            #printArea, #printArea * { visibility: visible !important; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; }
            .modal-backdrop { display:none !important; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<?php if (file_exists(__DIR__ . '/includes/common-toast.php')) { include __DIR__ . '/includes/common-toast.php'; } ?>

<div class="pos-page" id="posPage">
    <form id="posSecurityForm" class="d-none"><?= pos_csrf_field() ?></form>

    <header class="pos-topbar">
        <a href="bill-list.php" class="pos-back-btn" title="Back to bill-list">
            <i data-lucide="arrow-left"></i>
        </a>

        <div class="pos-title-wrap">
            <h1 class="pos-title">Create Bill</h1>
            <div class="pos-subtitle">Full-screen footwear POS • fast billing</div>
        </div>

        <div class="pos-header-grid">
            <div class="pos-field-chip">
                <label>Current Branch / Firm</label>
                <select id="branchSelect">
                    <option value="<?= (int)$branchId ?>">Loading branch...</option>
                </select>
            </div>

            <div class="pos-field-chip">
                <label>Sales User</label>
                <div class="chip-value" id="salesUser"><?= pos_e($salesUserName) ?></div>
            </div>

            <div class="pos-field-chip">
                <label>Bill Number</label>
                <div class="chip-value" id="billNo">AUTO</div>
            </div>

            <div class="pos-field-chip">
                <label>Date & Time</label>
                <div class="chip-value pos-clock" id="dateTime">--</div>
            </div>

        </div>
    </header>

    <main class="pos-shell pos-redesigned-shell">
        <section class="pos-workspace">
            <section class="pos-panel pos-product-panel">
                <div class="pos-panel-head">
                    <div>
                        <div class="pos-panel-title-row">
                            <span class="pos-step-badge">Step 1</span>
                            <h2 class="pos-panel-title">Product Selection</h2>
                        </div>
                        <div class="pos-panel-sub">Select customer, product, size and quantity in one clean flow.</div>
                    </div>
                    <button type="button" class="pos-icon-btn primary" id="focusSearchBtn" title="Focus search"><i data-lucide="search"></i></button>
                </div>
                <div class="pos-panel-body pos-product-body">
                    <div class="pos-left-form pos-product-layout compact-product-layout">
                        <div class="compact-card compact-customer-card">
                            <div class="compact-card-title">
                                <span>Customer</span>
                                <span class="compact-card-sub">Inside product selection</span>
                            </div>
                            <div class="pos-customer-bar">
                                <input type="text" id="customerSearch" placeholder="Walk-in / Search or type customer">
                                <button type="button" class="pos-icon-btn" id="walkInBtn" title="Walk-in customer"><i data-lucide="user"></i></button>
                                <button type="button" class="pos-icon-btn primary" id="addCustomerBtn" title="Add customer"><i data-lucide="user-plus"></i></button>
                                <div class="suggestion-list" id="customerSuggestions"></div>
                            </div>
                            <div class="compact-hint text-start">Existing customer or new name can be used directly for this bill.</div>
                        </div>

                        <div class="compact-card compact-search-card">
                            <div class="compact-card-title">
                                <span>Search Product</span>
                                <span class="compact-card-sub">Article / Brand / Barcode</span>
                            </div>

                            <div class="pos-search-box">
                                <i data-lucide="scan-line" style="width:18px;height:18px;color:#2563eb;"></i>
                                <input type="text" id="productSearch" placeholder="Search article, product, brand or barcode">
                                <button type="button" class="pos-scan-btn" id="scannerBtn" title="Camera scanner"><i data-lucide="camera"></i></button>
                                <div class="suggestion-list" id="productSuggestions"></div>
                            </div>

                            <div class="product-preview compact-product-preview" id="productPreview">
                                <div class="product-preview-img">GK</div>
                                <div class="product-preview-info">
                                    <h3 class="product-name">No product selected</h3>
                                    <div class="product-meta-line">Search or scan to load product.</div>
                                    <span class="stock-badge stock-no">Stock not selected</span>
                                </div>
                            </div>
                        </div>

                        <div class="compact-card compact-selector-card">
                            <div class="compact-card-title">
                                <span>Size & Color</span>
                                <span class="compact-card-sub">Available stock</span>
                            </div>

                            <div class="compact-selector-group">
                                <div class="pos-label">Size-wise Stock</div>
                                <div class="size-grid" id="sizeGrid">
                                    <span class="size-chip disabled">Select product</span>
                                </div>
                            </div>

                            <div class="compact-selector-group">
                                <div class="pos-label">Color</div>
                                <div class="color-grid" id="colorGrid">
                                    <span class="color-chip">Default</span>
                                </div>
                            </div>
                        </div>

                        <div class="compact-card compact-entry-card">
                            <div class="compact-card-title">
                                <span>Qty & Price</span>
                                <span class="compact-card-sub">Fast entry</span>
                            </div>

                            <div class="compact-field-grid">
                                <div class="price-entry-box qty-entry-box">
                                    <div class="pos-label">Qty</div>
                                    <div class="qty-control">
                                        <button type="button" id="qtyMinus">−</button>
                                        <input type="number" min="1" step="1" id="qtyInput" class="pos-input text-center" value="1">
                                        <button type="button" id="qtyPlus">+</button>
                                    </div>
                                </div>
                                <div class="price-entry-box">
                                    <div class="pos-label">MRP</div>
                                    <input type="number" step="0.01" id="mrpInput" class="pos-input" value="0.00" readonly>
                                </div>
                                <div class="price-entry-box">
                                    <div class="pos-label">Selling</div>
                                    <input type="number" step="0.01" id="sellingInput" class="pos-input" value="0.00">
                                </div>
                            </div>

                            <select id="discountType" class="d-none"><option value="none">No Discount</option><option value="percent">Percent %</option><option value="amount">Amount ₹</option></select>
                            <input type="hidden" id="discountValue" value="0.00">
                            <input type="hidden" id="itemRemarks" value="">

                            <div class="compact-inline-add">
                                <button type="button" id="addToBillBtn" class="add-bill-btn" title="Add selected product to bill">
                                    <span class="add-bill-icon"><i data-lucide="shopping-cart"></i></span>
                                    <span>
                                        <span class="add-bill-title">Add to Bill</span>
                                        <span class="add-bill-sub">Selected item</span>
                                    </span>
                                </button>
                                <div class="compact-hint">Product + size + qty required</div>
                            </div>

                            <div class="pos-hidden-tools" aria-hidden="true">
                                <button type="button" id="scanBillBarcodeBtn"></button>
                                <button type="button" id="darkModeBtn"></button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="pos-panel pos-bill-panel">
                <div class="pos-panel-head">
                    <div class="bill-items-title-wrap">
                        <div class="pos-panel-title-row">
                            <span class="pos-step-badge">Step 2</span>
                            <h2 class="pos-panel-title">Bill Items</h2>
                        </div>
                        <div class="pos-panel-sub">Review, edit quantity/price and remove items before saving.</div>
                    </div>

                    <div class="bill-header-customer-card" id="billHeaderCustomerCard">
                        <div class="avatar-circle bill-header-avatar" id="customerAvatar">W</div>
                        <div class="bill-header-customer-main">
                            <div class="customer-name" id="selectedCustomerName">Walk-in Customer</div>
                            <div class="customer-meta" id="selectedCustomerMeta">No mobile • no outstanding</div>
                        </div>
                        <div class="customer-mini-stats">
                            <span class="mini-stat" id="customerOutstanding">Outstanding ₹0.00</span>
                            <span class="mini-stat" id="customerLoyalty">Loyalty 0</span>
                            <span class="mini-stat" id="customerHistory">History 0</span>
                        </div>
                    </div>

                    <div class="bill-header-actions d-flex gap-2 align-items-center">
                        <button type="button" class="small-tool-btn" id="billHistoryBtn">
                            <i data-lucide="history"></i> History <span class="held-count-badge" id="heldCount">0</span>
                        </button>
                        <div class="dropdown bill-actions-menu">
                            <button type="button" class="small-tool-btn dropdown-toggle" id="billActionMenuBtn" data-bs-toggle="dropdown" aria-expanded="false">
                                <i data-lucide="settings-2"></i> Bill Actions
                            </button>
                            <div class="dropdown-menu dropdown-menu-end" aria-labelledby="billActionMenuBtn">
                                <button type="button" class="dropdown-item" id="holdBillBtn"><i data-lucide="pause"></i> Hold Bill</button>
                                <button type="button" class="dropdown-item" id="draftBtn"><i data-lucide="file-clock"></i> Save as Draft</button>
                                <button type="button" class="dropdown-item" id="cancelBillBtn"><i data-lucide="x-circle"></i> Cancel Bill</button>
                                <button type="button" class="dropdown-item" id="returnBillBtn"><i data-lucide="rotate-ccw"></i> Return Bill</button>
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item text-danger" id="clearItemsBtn"><i data-lucide="trash-2"></i> Clear Items</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pos-panel-body d-flex flex-column">
                    <div class="bill-table-wrap" id="billTableWrap">
                        <div class="empty-bill" id="emptyBill">
                            <div>
                                <div class="empty-icon"><i data-lucide="shopping-bag" style="width:34px;height:34px;"></i></div>
                                <div class="fw-bold mb-1">Bill is empty</div>
                                <div>Search or scan footwear stock to add items.</div>
                            </div>
                        </div>
                        <table class="bill-table d-none" id="billItemsTable">
                            <thead>
                            <tr>
                                <th>Product</th>
                                <th>Article</th>
                                <th>Brand</th>
                                <th>Color</th>
                                <th>Size</th>
                                <th>Qty</th>
                                <th>MRP</th>
                                <th>Discount</th>
                                <th>Selling</th>
                                <th>Amount</th>
                                <th>Edit</th>
                                <th>Remove</th>
                            </tr>
                            </thead>
                            <tbody id="billItemsBody"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </section>

        <aside class="pos-panel pos-summary-panel">
            <div class="pos-panel-head">
                <div>
                    <div class="pos-panel-title-row">
                        <span class="pos-step-badge">Step 3</span>
                        <h2 class="pos-panel-title">Bill Summary</h2>
                    </div>
                    <div class="pos-panel-sub">Discount, payment and final actions</div>
                </div>
                <button type="button" class="pos-icon-btn" id="refreshBtn"><i data-lucide="refresh-cw"></i></button>
            </div>

            <div class="pos-panel-body">
                <div class="summary-body">
                    <div class="summary-card">
                        <div class="summary-line"><span>Total Items</span><strong id="sumItems">0</strong></div>
                        <div class="summary-line"><span>Total Quantity</span><strong id="sumQty">0</strong></div>
                        <div class="summary-line"><span>MRP Total</span><strong id="sumMrp">₹0.00</strong></div>
                        <div class="summary-line"><span>Product Discount</span><strong id="sumProductDiscount">₹0.00</strong></div>
                        <div class="summary-line">
                            <span>Bill Discount</span>
                            <strong id="sumBillDiscount">₹0.00</strong>
                        </div>
                        <div class="quick-row">
                            <select id="billDiscountType" class="pos-input">
                                <option value="none">No Bill Discount</option>
                                <option value="percent">Percent %</option>
                                <option value="amount">Amount ₹</option>
                            </select>
                            <input type="number" step="0.01" id="billDiscountValue" class="pos-input" value="0.00">
                        </div>
                        <div class="payment-row">
                            <input type="text" id="offerCode" class="pos-input text-uppercase" placeholder="Coupon / Offer Code">
                            <button type="button" class="small-tool-btn" id="applyOfferBtn">Apply Offer</button>
                        </div>
                        <div class="payment-row">
                            <input type="number" step="0.01" id="loyaltyRedeem" class="pos-input" value="0.00" placeholder="Loyalty redeem">
                            <input type="text" id="customerNotes" class="pos-input" placeholder="Customer notes">
                        </div>
                        <div class="summary-line saving"><span>Today's Savings</span><strong id="sumSavings">₹0.00</strong></div>
                        <div class="summary-line net"><span>Net Amount Before GST</span><strong id="sumNet">₹0.00</strong></div>
                        <div class="summary-line"><span>Round Off</span><strong id="sumRoundOff">₹0.00</strong></div>
                    </div>

                    <div class="summary-card gst-card js-gst-system-visible" id="gstCard">
                        <div class="gst-head">
                            <div>
                                <div class="pos-label">GST Options</div>
                                <div class="pos-panel-sub">Enable GST for this bill and calculate SGST/CGST or IGST.</div>
                            </div>
                            <label class="gst-toggle-wrap">
                                <input type="checkbox" id="gstEnabled">
                                <span id="gstStatusText" class="gst-status-pill">GST OFF</span>
                            </label>
                        </div>

                        <div class="gst-tax-row gst-detail">
                            <select id="gstMode" class="pos-input">
                                <option value="intra">SGST + CGST</option>
                                <option value="inter">IGST</option>
                            </select>
                            <input type="number" step="0.01" min="0" max="100" id="gstRate" class="pos-input" value="18.00" placeholder="GST %">
                            <input type="text" id="gstTaxableAmount" class="pos-input" value="₹0.00" readonly>
                        </div>

                        <div class="summary-line gst-detail"><span>CGST</span><strong id="sumCgst">₹0.00</strong></div>
                        <div class="summary-line gst-detail"><span>SGST</span><strong id="sumSgst">₹0.00</strong></div>
                        <div class="summary-line gst-detail"><span>IGST</span><strong id="sumIgst">₹0.00</strong></div>
                        <div class="summary-line net gst-detail"><span>Total GST</span><strong id="sumGst">₹0.00</strong></div>
                    </div>

                    <div class="grand-total-box">
                        <div class="grand-label">Grand Total</div>
                        <div class="grand-value" id="sumGrand">₹0</div>
                    </div>

                    <div class="summary-card">
                        <div class="pos-label">Payment Status</div>
                        <div class="pos-pending-note mb-2">
                            <strong>Pending Payment</strong><br>
                            POS bill creation will only save and print the invoice barcode. Payment must be collected from the Pending Bills module after scanning the printed barcode.
                        </div>

                        <div class="pay-method-grid d-none" id="paymentMethods"></div>
                        <div id="singlePaymentBox" class="payment-row d-none">
                            <input type="number" step="0.01" id="singlePaidAmount" class="pos-input" value="0.00">
                            <input type="text" id="singlePaymentRef" class="pos-input" placeholder="Reference No">
                        </div>
                        <div id="splitPaymentBox" class="split-rows d-none"></div>

                        <div class="payment-row">
                            <div>
                                <div class="pos-label">Paid Amount</div>
                                <input type="text" id="paidAmountView" class="pos-input" value="₹0.00" readonly>
                            </div>
                            <div>
                                <div class="pos-label">Pending Amount</div>
                                <input type="text" id="balanceAmountView" class="pos-input" value="₹0.00" readonly>
                            </div>
                        </div>

                        <div>
                            <div class="pos-label">Sales Notes</div>
                            <input type="text" id="salesNotes" class="pos-input" placeholder="Sales note">
                        </div>
                    </div>

                    <div class="action-grid">
                        <button type="button" class="pos-action success wide" id="savePrintBtn"><i data-lucide="printer"></i> Save & Print</button>
                        <button type="button" class="pos-action primary" id="saveBillBtn"><i data-lucide="save"></i> Save Bill</button>
                        <button type="button" class="pos-action dark" id="previewBtn"><i data-lucide="eye"></i> Preview</button>
                        <button type="button" class="pos-action light" id="printBtn"><i data-lucide="printer-check"></i> Reprint</button>
                        <button type="button" class="pos-action light wide" id="newBillBtn"><i data-lucide="file-plus-2"></i> New Bill</button>
                    </div>
                </div>
            </div>
        </aside>
    </main>
</div>

<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="customerForm">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Add New Customer</h5>
                    <div class="pos-panel-sub">Auto-create customer for loyalty, outstanding and purchase history.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Customer Name *</label>
                        <input type="text" class="form-control" id="newCustomerName" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Mobile</label>
                        <input type="text" class="form-control" id="newCustomerMobile" maxlength="10" inputmode="numeric">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="newCustomerEmail">
                    </div>
                    <div class="col-12 col-md-6 js-gst-system-visible">
                        <label class="form-label">GSTIN</label>
                        <input type="text" class="form-control text-uppercase" id="newCustomerGstin" maxlength="15">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea class="form-control" id="newCustomerAddress" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn brand-gradient rounded-pill fw-bold">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="billHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Bill History</h5>
                    <div class="pos-panel-sub">Bills show only one status at a time: Paid, Hold, or Unpaid.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="bill-history-tools mb-3">
                    <input type="text" class="form-control" id="billHistorySearch" placeholder="Search bill no / customer / mobile">
                    <select class="form-select" id="billHistoryFilter">
                        <option value="all">All Bills</option>
                        <option value="paid">Paid Bills</option>
                        <option value="hold">Hold Bills</option>
                        <option value="unpaid">Unpaid Bills</option>
                    </select>
                </div>
                <div id="billHistoryBody"><div class="text-center text-muted py-4">Loading bill history...</div></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="scannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Barcode Scanner</h5>
                    <div class="pos-panel-sub">Camera support and manual barcode input.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="closeScannerBtn"></button>
            </div>
            <div class="modal-body">
                <video id="scannerVideo" class="scanner-video" playsinline muted></video>
                <div class="payment-row mt-3">
                    <input type="text" class="pos-input" id="manualScanInput" placeholder="Scan or type stock barcode / bill barcode">
                    <button type="button" class="small-tool-btn" id="manualScanBtn">Use Code</button>
                </div>
                <div class="text-muted small mt-2">USB scanner: click the input and scan. Camera scanning uses browser BarcodeDetector when supported.</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Bill Preview</h5>
                    <div class="pos-panel-sub">Thermal-friendly preview. Final print uses separate FPDF bill-print.php file.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="printArea">
                <div id="previewBody" class="preview-paper"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light rounded-pill fw-bold" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn brand-gradient rounded-pill fw-bold" id="modalPrintBtn">Print</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/script.php'; ?>

<script>
(function () {
    'use strict';

    const apiUrl = 'api/pos-billing-api.php';
    const initialBranchId = <?= (int)$branchId ?>;
    const salesUserName = <?= json_encode((string)$salesUserName) ?>;
    const csrfInput = document.querySelector('#posSecurityForm input[name="csrf_token"], #posSecurityForm input[name="_token"]');

    const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', minimumFractionDigits: 2 });
    const shortMoney = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 });

    const state = {
        business: {},
        businessSettings: {},
        invoiceSettings: {},
        barcodeSettings: {},
        branches: [],
        branchId: initialBranchId,
        billNo: 'AUTO',
        billBarcode: '',
        paymentMethods: [],
        paymentMode: 'cash',
        selectedPaymentMethodId: 0,
        selectedCustomer: null,
        selectedProduct: null,
        productOptions: [],
        items: [],
        heldBills: [],
        billHistory: [],
        holdId: 0,
        workflowType: '',
        lastSavedBill: null,
        lastSavedBillId: 0,
        scannerStream: null,
        scannerTimer: null
    };

    const el = {
        posPage: document.getElementById('posPage'),
        branchSelect: document.getElementById('branchSelect'),
        billNo: document.getElementById('billNo'),
        dateTime: document.getElementById('dateTime'),
        customerSearch: document.getElementById('customerSearch'),
        customerSuggestions: document.getElementById('customerSuggestions'),
        productSearch: document.getElementById('productSearch'),
        productSuggestions: document.getElementById('productSuggestions'),
        productPreview: document.getElementById('productPreview'),
        sizeGrid: document.getElementById('sizeGrid'),
        colorGrid: document.getElementById('colorGrid'),
        qtyInput: document.getElementById('qtyInput'),
        mrpInput: document.getElementById('mrpInput'),
        sellingInput: document.getElementById('sellingInput'),
        discountType: document.getElementById('discountType'),
        discountValue: document.getElementById('discountValue'),
        itemRemarks: document.getElementById('itemRemarks'),
        emptyBill: document.getElementById('emptyBill'),
        billItemsTable: document.getElementById('billItemsTable'),
        billItemsBody: document.getElementById('billItemsBody'),
        selectedCustomerName: document.getElementById('selectedCustomerName'),
        selectedCustomerMeta: document.getElementById('selectedCustomerMeta'),
        customerAvatar: document.getElementById('customerAvatar'),
        customerOutstanding: document.getElementById('customerOutstanding'),
        customerLoyalty: document.getElementById('customerLoyalty'),
        customerHistory: document.getElementById('customerHistory'),
        sumItems: document.getElementById('sumItems'),
        sumQty: document.getElementById('sumQty'),
        sumMrp: document.getElementById('sumMrp'),
        sumProductDiscount: document.getElementById('sumProductDiscount'),
        sumBillDiscount: document.getElementById('sumBillDiscount'),
        sumSavings: document.getElementById('sumSavings'),
        sumNet: document.getElementById('sumNet'),
        sumRoundOff: document.getElementById('sumRoundOff'),
        sumGrand: document.getElementById('sumGrand'),
        gstCard: document.getElementById('gstCard'),
        gstEnabled: document.getElementById('gstEnabled'),
        gstMode: document.getElementById('gstMode'),
        gstRate: document.getElementById('gstRate'),
        gstTaxableAmount: document.getElementById('gstTaxableAmount'),
        gstStatusText: document.getElementById('gstStatusText'),
        sumCgst: document.getElementById('sumCgst'),
        sumSgst: document.getElementById('sumSgst'),
        sumIgst: document.getElementById('sumIgst'),
        sumGst: document.getElementById('sumGst'),
        billDiscountType: document.getElementById('billDiscountType'),
        billDiscountValue: document.getElementById('billDiscountValue'),
        offerCode: document.getElementById('offerCode'),
        loyaltyRedeem: document.getElementById('loyaltyRedeem'),
        customerNotes: document.getElementById('customerNotes'),
        paymentMethods: document.getElementById('paymentMethods'),
        singlePaymentBox: document.getElementById('singlePaymentBox'),
        singlePaidAmount: document.getElementById('singlePaidAmount'),
        singlePaymentRef: document.getElementById('singlePaymentRef'),
        splitPaymentBox: document.getElementById('splitPaymentBox'),
        paidAmountView: document.getElementById('paidAmountView'),
        balanceAmountView: document.getElementById('balanceAmountView'),
        salesNotes: document.getElementById('salesNotes'),
        heldCount: document.getElementById('heldCount'),
        heldBillsBody: document.getElementById('billHistoryBody'),
        billHistoryBody: document.getElementById('billHistoryBody'),
        billHistoryFilter: document.getElementById('billHistoryFilter'),
        billHistorySearch: document.getElementById('billHistorySearch'),
        previewBody: document.getElementById('previewBody'),
        scannerVideo: document.getElementById('scannerVideo'),
        manualScanInput: document.getElementById('manualScanInput')
    };

    function escapeHtml(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function showMessage(type, message) {
        const toastType = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'error');
        if (window.AppToast && typeof window.AppToast.show === 'function') {
            window.AppToast.show(toastType, message);
            return;
        }
        alert(message);
    }

    function refreshIcons() {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    }

    function modal(id, options) {
        const node = document.getElementById(id);
        if (window.bootstrap && window.bootstrap.Modal) {
            return window.bootstrap.Modal.getOrCreateInstance(node, options || {});
        }
        return { show: function(){ node.style.display = 'block'; }, hide: function(){ node.style.display = 'none'; } };
    }

    function apiUrlWith(params) {
        params.branch_id = state.branchId;
        return apiUrl + '?' + new URLSearchParams(params).toString();
    }

    async function apiGet(params) {
        const response = await fetch(apiUrlWith(params), { headers: { 'Accept': 'application/json' } });
        return response.json();
    }

    async function apiPost(action, payload) {
        payload = payload || {};
        payload.action = action;
        payload.branch_id = state.branchId;
        if (csrfInput && csrfInput.value) {
            payload[csrfInput.name] = csrfInput.value;
        }
        const formData = new FormData();
        formData.append('action', action);
        formData.append('branch_id', state.branchId);
        formData.append('payload', JSON.stringify(payload));
        if (csrfInput && csrfInput.value) {
            formData.append(csrfInput.name, csrfInput.value);
        }
        const response = await fetch(apiUrl, { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
        return response.json();
    }

    function toNumber(value) {
        const n = parseFloat(value);
        return Number.isFinite(n) ? n : 0;
    }

    function normalizeMobile(value) {
        return String(value || '').replace(/[^0-9]/g, '').slice(0, 10);
    }

    function roundMoney(value) {
        return Math.round(toNumber(value) * 100) / 100;
    }

    function systemGstEnabled() {
        const keys = [
            state.business && state.business.gst_type_key,
            state.businessSettings && state.businessSettings.gst_type_key,
            state.invoiceSettings && state.invoiceSettings.gst_type_key
        ].map(function (value) { return String(value || '').toLowerCase().trim(); });

        if (keys.some(function (key) { return ['non_gst', 'no_gst', 'gst_off', 'off', 'none', 'disabled'].includes(key); })) {
            return false;
        }

        /*
         * If GST settings are not explicit, keep the GST option available.
         * This allows cashier to use the bill-level GST ON/OFF button.
         */
        return true;
    }

    function applyGstVisibility(summary) {
        const systemOn = systemGstEnabled();
        const gstOn = systemOn && !!(el.gstEnabled && el.gstEnabled.checked);

        document.querySelectorAll('.js-gst-system-visible').forEach(function (node) {
            node.classList.toggle('gst-hidden', !systemOn);
        });

        document.querySelectorAll('.gst-detail').forEach(function (node) {
            node.classList.toggle('gst-hidden', !gstOn);
        });

        if (!systemOn && el.gstEnabled) {
            el.gstEnabled.checked = false;
        }

        if (el.gstRate) el.gstRate.disabled = !gstOn;
        if (el.gstMode) el.gstMode.disabled = !gstOn;
        if (el.gstStatusText) {
            el.gstStatusText.textContent = gstOn ? 'GST ON' : 'GST OFF';
            el.gstStatusText.classList.toggle('on', gstOn);
        }

        return { systemOn: systemOn, gstOn: gstOn };
    }

    /* Persist current bill items in this browser so refresh will not remove products.
       The draft is removed only by Clear Items or after a successful completed/save workflow. */
    let billRestoreInProgress = false;

    function currentBillStorageKey(branchId) {
        const businessKey = state.business && state.business.business_id ? state.business.business_id : 'business';
        const userKey = String(salesUserName || 'sales').replace(/[^a-z0-9_-]/gi, '_').toLowerCase();
        return 'gk_footwear_pos_current_bill_' + businessKey + '_' + String(branchId || state.branchId || initialBranchId || 0) + '_' + userKey;
    }

    function clearPersistedBill(branchId) {
        try {
            window.localStorage.removeItem(currentBillStorageKey(branchId));
        } catch (error) {}
    }

    function persistCurrentBill() {
        if (billRestoreInProgress) return;
        try {
            const key = currentBillStorageKey();

            if (!state.items.length) {
                window.localStorage.removeItem(key);
                return;
            }

            const splitPayments = [];
            document.querySelectorAll('.split-amount').forEach(function (input) {
                splitPayments.push({
                    method_id: input.dataset.methodId || '',
                    amount: input.value || '0.00',
                    reference_no: (document.querySelector('.split-ref[data-method-id="' + input.dataset.methodId + '"]') || {}).value || ''
                });
            });

            const payload = {
                saved_at: Date.now(),
                branch_id: state.branchId,
                bill_no: state.billNo,
                items: state.items,
                selected_customer: state.selectedCustomer,
                customer_search: el.customerSearch.value || '',
                bill_discount_type: el.billDiscountType.value || 'none',
                bill_discount_value: el.billDiscountValue.value || '0.00',
                offer_code: el.offerCode.value || '',
                loyalty_redeem: el.loyaltyRedeem.value || '0.00',
                gst_enabled: el.gstEnabled && el.gstEnabled.checked ? 1 : 0,
                gst_mode: el.gstMode ? el.gstMode.value : 'intra',
                gst_rate: el.gstRate ? el.gstRate.value : '18.00',
                customer_notes: el.customerNotes.value || '',
                sales_notes: el.salesNotes.value || '',
                payment_mode: state.paymentMode || 'cash',
                selected_payment_method_id: state.selectedPaymentMethodId || 0,
                single_paid_amount: '0.00',
                single_payment_ref: el.singlePaymentRef.value || '',
                split_payments: splitPayments
            };

            window.localStorage.setItem(key, JSON.stringify(payload));
        } catch (error) {}
    }

    function restorePersistedBill() {
        try {
            const raw = window.localStorage.getItem(currentBillStorageKey());
            if (!raw) return false;

            const saved = JSON.parse(raw);
            const savedItems = Array.isArray(saved.items) ? saved.items : [];
            if (!savedItems.length || Number(saved.branch_id || 0) !== Number(state.branchId || 0)) {
                return false;
            }

            billRestoreInProgress = true;

            state.items = savedItems.map(function (item) {
                item.qty = Math.max(1, toNumber(item.qty || 1));
                item.available_qty = Math.max(item.qty, toNumber(item.available_qty || item.qty || 1));
                item.mrp_rate = toNumber(item.mrp_rate);
                item.selling_rate = toNumber(item.selling_rate);
                return item;
            });

            state.selectedCustomer = saved.selected_customer || null;
            state.paymentMode = saved.payment_mode || state.paymentMode || 'cash';
            state.selectedPaymentMethodId = saved.selected_payment_method_id || state.selectedPaymentMethodId || 0;

            el.customerSearch.value = saved.customer_search || (state.selectedCustomer ? (state.selectedCustomer.customer_name + (state.selectedCustomer.mobile ? ' - ' + state.selectedCustomer.mobile : '')) : '');
            el.billDiscountType.value = saved.bill_discount_type || 'none';
            el.billDiscountValue.value = toNumber(saved.bill_discount_value || 0).toFixed(2);
            el.offerCode.value = saved.offer_code || '';
            el.loyaltyRedeem.value = toNumber(saved.loyalty_redeem || 0).toFixed(2);
            if (el.gstEnabled) el.gstEnabled.checked = parseInt(saved.gst_enabled || 0, 10) === 1;
            if (el.gstMode) el.gstMode.value = saved.gst_mode || 'intra';
            if (el.gstRate) el.gstRate.value = toNumber(saved.gst_rate || 18).toFixed(2);
            el.customerNotes.value = saved.customer_notes || '';
            el.salesNotes.value = saved.sales_notes || '';
            el.singlePaidAmount.value = '0.00';
            el.singlePaymentRef.value = saved.single_payment_ref || '';

            renderPaymentMethods();
            el.singlePaymentBox.classList.toggle('d-none', state.paymentMode === 'split');
            el.splitPaymentBox.classList.toggle('d-none', state.paymentMode !== 'split');

            (saved.split_payments || []).forEach(function (payment) {
                const amountInput = document.querySelector('.split-amount[data-method-id="' + payment.method_id + '"]');
                const refInput = document.querySelector('.split-ref[data-method-id="' + payment.method_id + '"]');
                if (amountInput) amountInput.value = payment.amount || '0.00';
                if (refInput) refInput.value = payment.reference_no || '';
            });

            renderCustomer();
            renderBillItems();
            renderSummary();
            billRestoreInProgress = false;
            persistCurrentBill();
            showMessage('success', 'Unsaved bill restored. Use Clear Items to remove it.');
            return true;
        } catch (error) {
            billRestoreInProgress = false;
            clearPersistedBill();
            return false;
        }
    }

    function discountPerUnit(mrp, type, value) {
        mrp = toNumber(mrp);
        value = toNumber(value);
        if (type === 'percent') return Math.min(mrp, mrp * value / 100);
        if (type === 'amount') return Math.min(mrp, value);
        return 0;
    }

    function calcBillDiscount(selling, type, value) {
        selling = toNumber(selling);
        value = toNumber(value);
        if (type === 'percent') return Math.min(selling, selling * value / 100);
        if (type === 'amount') return Math.min(selling, value);
        return 0;
    }

    function currentSummary() {
        let qty = 0, mrp = 0, productDiscount = 0, selling = 0;
        state.items.forEach(function (item) {
            const itemQty = toNumber(item.qty);
            const itemMrp = toNumber(item.mrp_rate);
            const itemSelling = toNumber(item.selling_rate);
            qty += itemQty;
            mrp += itemMrp * itemQty;
            productDiscount += Math.max(0, itemMrp - itemSelling) * itemQty;
            selling += itemSelling * itemQty;
        });

        const billDiscount = calcBillDiscount(selling, el.billDiscountType.value, toNumber(el.billDiscountValue.value));
        const maxLoyalty = state.selectedCustomer ? toNumber(state.selectedCustomer.loyalty_points) : 0;
        const loyaltyEnabled = parseInt(state.businessSettings.loyalty_enabled || 0, 10) === 1;
        let loyaltyRedeem = loyaltyEnabled ? Math.min(toNumber(el.loyaltyRedeem.value), maxLoyalty, Math.max(0, selling - billDiscount)) : 0;
        if (!loyaltyEnabled) {
            el.loyaltyRedeem.value = '0.00';
        }

        const net = Math.max(0, selling - billDiscount - loyaltyRedeem);
        const gstState = applyGstVisibility();
        const gstEnabled = gstState.systemOn && !!(el.gstEnabled && el.gstEnabled.checked);
        const gstMode = el.gstMode ? String(el.gstMode.value || 'intra') : 'intra';
        const gstRate = gstEnabled ? Math.max(0, Math.min(100, toNumber(el.gstRate ? el.gstRate.value : 0))) : 0;
        const taxable = gstEnabled ? net : 0;
        const gstAmount = gstEnabled ? roundMoney(taxable * gstRate / 100) : 0;
        const cgst = gstEnabled && gstMode === 'intra' ? roundMoney(gstAmount / 2) : 0;
        const sgst = gstEnabled && gstMode === 'intra' ? roundMoney(gstAmount - cgst) : 0;
        const igst = gstEnabled && gstMode === 'inter' ? gstAmount : 0;
        const beforeRound = net + gstAmount;
        const grand = Math.round(beforeRound);
        const roundOff = roundMoney(grand - beforeRound);
        const paid = currentPaidAmount(grand);
        const balance = Math.max(0, grand - paid);

        return {
            items: state.items.length,
            qty: qty,
            mrp: mrp,
            productDiscount: productDiscount,
            selling: selling,
            billDiscount: billDiscount,
            loyaltyRedeem: loyaltyRedeem,
            savings: Math.max(0, mrp - selling) + billDiscount + loyaltyRedeem,
            net: net,
            gstEnabled: gstEnabled,
            gstMode: gstMode,
            gstRate: gstRate,
            taxable: taxable,
            cgst: cgst,
            sgst: sgst,
            igst: igst,
            gstAmount: gstAmount,
            beforeRound: beforeRound,
            roundOff: roundOff,
            grand: grand,
            paid: paid,
            balance: balance
        };
    }

    function currentPaidAmount(grand) {
        // POS creation does not accept payment. Always keep the bill pending.
        return 0;
    }

    function productInitial(product) {
        const name = String(product.article_name || product.article_no || 'GK').trim();
        return escapeHtml((name.substring(0, 2) || 'GK').toUpperCase());
    }


    function parseCompactDate(value, allowPlainText) {
        const raw = String(value || '').trim();
        if (!raw) return '';

        let match = raw.match(/(20\d{2})[-\/\.](0?[1-9]|1[0-2])[-\/\.](0?[1-9]|[12]\d|3[01])/);
        if (!match) {
            match = raw.match(/(20\d{2})(0[1-9]|1[0-2])([0-2]\d|3[01])/);
        }

        if (!match) {
            return allowPlainText ? (raw.length > 18 ? raw.substring(0, 18) : raw) : '';
        }

        const year = match[1];
        const month = String(match[2]).padStart(2, '0');
        const day = String(match[3]).padStart(2, '0');
        const date = new Date(year + '-' + month + '-' + day + 'T00:00:00');
        if (Number.isNaN(date.getTime())) {
            return day + '-' + month + '-' + year;
        }

        return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function compactDateCandidate(value) {
        if (value === undefined || value === null) return '';
        if (Array.isArray(value)) {
            for (let i = 0; i < value.length; i++) {
                const v = compactDateCandidate(value[i]);
                if (v) return v;
            }
            return '';
        }
        if (typeof value === 'object') {
            return compactDateCandidate(value.stock_entry_date || value.entry_date || value.inward_date || value.created_at || '');
        }
        const text = String(value).trim();
        if (!text || text === '0000-00-00' || text === '0000-00-00 00:00:00' || text.toLowerCase() === 'null') return '';
        return text;
    }

    function stockEntryDate(product) {
        product = product || {};
        const directDate = compactDateCandidate([
            product.stock_entry_date,
            product.stockEntryDate,
            product.entry_date,
            product.entryDate,
            product.product_entry_date,
            product.productEntryDate,
            product.stock_date,
            product.inward_date,
            product.inwardDate,
            product.purchase_date,
            product.purchaseDate,
            product.batch_entry_date,
            product.batchEntryDate,
            product.batch_inward_date,
            product.batchInwardDate,
            product.batch_date,
            product.batchDate,
            product.created_at,
            product.createdAt,
            product.date_time,
            product.batch,
            product.item
        ]);

        const formattedDirect = parseCompactDate(directDate, false);
        if (formattedDirect) return formattedDirect;

        const barcodeDate = parseCompactDate(compactDateCandidate([
            product.barcode_value,
            product.barcode_values,
            product.stock_barcode,
            product.stockBarcode,
            product.batch_no,
            product.stock_batch_no
        ]), false);
        return barcodeDate || '';
    }

    function stockEntryDateText(product) {
        return stockEntryDate(product) || '-';
    }

    function stockEntryDateChip(product, extraClass) {
        const actualDate = stockEntryDate(product);
        const dateText = actualDate || '-';
        const missingClass = actualDate ? '' : ' entry-date-missing';
        return `<span class="stock-entry-date-chip ${extraClass || ''}${missingClass}"><span class="date-label">Entry Date</span>${escapeHtml(dateText)}</span>`;
    }

    function stockEntryDateRow(product, sizeTone) {
        return `<div class="suggestion-meta stock-entry-date-row suggestion-size-date-line">
            <span class="size-mini ${sizeTone || ''}">Size ${escapeHtml(product && product.size ? product.size : '-')}</span>
            ${stockEntryDateChip(product, 'in-suggestion-line')}
        </div>`;
    }

    function sizeToneClass(value, forcedIndex) {
        // forcedIndex is used in visible lists so every size chip gets a different color,
        // even when the same size appears more than once with different stock entries.
        if (forcedIndex !== undefined && forcedIndex !== null && forcedIndex !== '') {
            const n = parseInt(forcedIndex, 10);
            if (!Number.isNaN(n)) {
                return 'size-tone-' + (Math.abs(n) % 14);
            }
        }

        const text = String(value === undefined || value === null ? '' : value).trim();
        let hash = 0;
        if (text) {
            for (let i = 0; i < text.length; i++) {
                hash = ((hash << 5) - hash) + text.charCodeAt(i);
                hash |= 0;
            }
        }
        return 'size-tone-' + (Math.abs(hash) % 14);
    }

    function renderBranches() {
        el.branchSelect.innerHTML = state.branches.map(function (branch) {
            return `<option value="${branch.branch_id}">${escapeHtml(branch.branch_name)} ${branch.floor_name ? '(' + escapeHtml(branch.floor_name) + ')' : ''}</option>`;
        }).join('');
        el.branchSelect.value = String(state.branchId || (state.branches[0] && state.branches[0].branch_id) || '');
    }

    function renderCustomer() {
        const c = state.selectedCustomer;
        if (!c) {
            el.selectedCustomerName.textContent = 'Walk-in Customer';
            el.selectedCustomerMeta.textContent = 'No mobile • no outstanding';
            el.customerAvatar.textContent = 'W';
            el.customerOutstanding.textContent = 'Outstanding ₹0.00';
            el.customerLoyalty.textContent = 'Loyalty 0';
            el.customerHistory.textContent = 'History 0';
            return;
        }

        const name = c.customer_name || c.name || 'Customer';
        el.selectedCustomerName.textContent = name;
        el.selectedCustomerMeta.textContent = (c.mobile || 'No mobile') + (c.email ? ' • ' + c.email : '');
        el.customerAvatar.textContent = name.substring(0, 1).toUpperCase();
        el.customerOutstanding.textContent = 'Outstanding ' + money.format(toNumber(c.outstanding_balance || c.opening_outstanding || 0));
        el.customerLoyalty.textContent = 'Loyalty ' + toNumber(c.loyalty_points || 0).toFixed(0);
        el.customerHistory.textContent = 'History ' + toNumber(c.total_bill_amount || 0).toFixed(0);
    }

    function renderProduct(product) {
        if (!product) {
            el.productPreview.innerHTML = `<div class="product-preview-img">GK</div>
                <div class="product-preview-info">
                    <h3 class="product-name">No product selected</h3>
                    <div class="product-meta-line">Search or scan stock barcode to load product details.</div>
                    <span class="stock-badge stock-no">Stock not selected</span>
                </div>`;
            el.sizeGrid.innerHTML = '<span class="size-chip disabled">Select product</span>';
            el.colorGrid.innerHTML = '<span class="color-chip">Default</span>';
            el.mrpInput.value = '0.00';
            el.sellingInput.value = '0.00';
            el.discountType.value = 'none';
            el.discountValue.value = '0.00';
            return;
        }

        const stockClass = product.available_qty <= 0 ? 'stock-no' : (product.low_stock ? 'stock-low' : 'stock-ok');
        const stockText = product.available_qty <= 0 ? 'Out of stock' : (product.low_stock ? 'Low stock: ' : 'Available: ') + product.available_qty;

        el.productPreview.innerHTML = `<div class="product-preview-img">${productInitial(product)}</div>
            <div class="product-preview-info">
                <h3 class="product-name">${escapeHtml(product.article_name || product.article_no)}</h3>
                <div class="product-meta-line">Article: <b>${escapeHtml(product.article_no)}</b> • Brand: ${escapeHtml(product.brand_name || '-')}</div>
                <div class="product-meta-line">Category: ${escapeHtml(product.category_name || '-')}</div>
                ${stockEntryDateChip(product, 'in-preview')}
                <span class="stock-badge ${stockClass}">${stockText}</span>
            </div>`;

        el.mrpInput.value = toNumber(product.mrp_rate).toFixed(2);
        el.sellingInput.value = toNumber(product.selling_rate).toFixed(2);
        el.discountType.value = product.product_discount_type || 'none';
        el.discountValue.value = toNumber(product.product_discount_value || 0).toFixed(2);

        renderProductOptions();
    }

    function renderProductOptions() {
        const selected = state.selectedProduct;
        if (!selected || !state.productOptions.length) {
            return;
        }

        const colors = {};
        const sizes = [];
        state.productOptions.forEach(function (item) {
            colors[item.color || 'Default'] = true;
            sizes.push(item);
        });

        el.colorGrid.innerHTML = Object.keys(colors).map(function (color) {
            const active = (selected.color || 'Default') === color ? 'active' : '';
            return `<button type="button" class="color-chip ${active}" data-color="${escapeHtml(color)}"><span class="color-dot"></span>${escapeHtml(color)}</button>`;
        }).join('');

        el.sizeGrid.innerHTML = sizes.map(function (item, index) {
            const active = Number(selected.stock_item_id) === Number(item.stock_item_id) ? 'active' : '';
            const disabled = toNumber(item.available_qty) <= 0 ? 'disabled' : '';
            const toneClass = sizeToneClass(item.stock_item_id || item.size || index, index);
            return `<button type="button" class="size-chip ${toneClass} ${active} ${disabled}" data-stock-id="${item.stock_item_id}" title="Stock entry: ${escapeHtml(stockEntryDate(item) || '-')}">
                <span class="size-main-text">${escapeHtml(item.size)}</span> <small>(${item.available_qty})</small>
            </button>`;
        }).join('');
    }

    function renderBillItems() {
        if (!state.items.length) {
            el.emptyBill.classList.remove('d-none');
            el.billItemsTable.classList.add('d-none');
            el.billItemsBody.innerHTML = '';
            renderSummary();
            persistCurrentBill();
            return;
        }

        el.emptyBill.classList.add('d-none');
        el.billItemsTable.classList.remove('d-none');

        el.billItemsBody.innerHTML = state.items.map(function (item, index) {
            const itemDiscount = Math.max(0, toNumber(item.mrp_rate) - toNumber(item.selling_rate));
            const amount = toNumber(item.qty) * toNumber(item.selling_rate);
            return `<tr>
                <td><div class="line-product"><div class="line-img">${productInitial(item)}</div><div><div class="line-title">${escapeHtml(item.article_name || item.article_no)}</div><div class="line-sub">${escapeHtml(item.item_remarks || '')}</div></div></div></td>
                <td>${escapeHtml(item.article_no)}</td>
                <td>${escapeHtml(item.brand_name || '-')}</td>
                <td>${escapeHtml(item.color || '-')}</td>
                <td><b>${escapeHtml(item.size)}</b></td>
                <td><input type="number" class="line-edit-input js-line-qty" data-index="${index}" min="1" step="1" value="${toNumber(item.qty)}"></td>
                <td>${money.format(toNumber(item.mrp_rate))}</td>
                <td>${money.format(itemDiscount)}</td>
                <td><input type="number" class="line-edit-input js-line-selling" data-index="${index}" step="0.01" value="${toNumber(item.selling_rate).toFixed(2)}"></td>
                <td><b>${money.format(amount)}</b></td>
                <td><button type="button" class="row-action edit js-edit-line" data-index="${index}"><i data-lucide="pencil"></i></button></td>
                <td><button type="button" class="row-action remove js-remove-line" data-index="${index}"><i data-lucide="trash-2"></i></button></td>
            </tr>`;
        }).join('');

        refreshIcons();
        renderSummary();
        persistCurrentBill();
    }

    function renderPaymentMethods() {
        if (!state.paymentMethods.length) {
            state.paymentMethods = [{ payment_method_id: 0, payment_method_name: 'Cash', method_type: 'cash' }];
        }

        if (!state.selectedPaymentMethodId && state.paymentMethods.length) {
            state.selectedPaymentMethodId = state.paymentMethods[0].payment_method_id;
            state.paymentMode = state.paymentMethods[0].method_type;
        }

        el.paymentMethods.innerHTML = state.paymentMethods.map(function (method) {
            const active = String(method.payment_method_id) === String(state.selectedPaymentMethodId) || (state.paymentMode === 'split' && method.method_type === 'split');
            return `<button type="button" class="pay-chip ${active ? 'active' : ''}" data-method-id="${method.payment_method_id}" data-method-type="${method.method_type}">
                ${escapeHtml(method.payment_method_name)}
            </button>`;
        }).join('');

        renderSplitRows();
        refreshIcons();
    }

    function renderSplitRows() {
        const splitMethods = state.paymentMethods.filter(m => m.method_type !== 'split' && m.method_type !== 'credit');
        el.splitPaymentBox.innerHTML = splitMethods.map(function (method) {
            return `<div class="split-row">
                <div class="fw-bold">${escapeHtml(method.payment_method_name)}</div>
                <input type="number" step="0.01" class="pos-input split-amount" data-method-id="${method.payment_method_id}" data-method-type="${method.method_type}" data-method-name="${escapeHtml(method.payment_method_name)}" placeholder="Amount">
                <input type="text" class="pos-input split-ref" data-method-id="${method.payment_method_id}" placeholder="Ref">
            </div>`;
        }).join('');
    }

    function renderSummary() {
        const s = currentSummary();
        applyGstVisibility(s);
        el.sumItems.textContent = s.items;
        el.sumQty.textContent = s.qty;
        el.sumMrp.textContent = money.format(s.mrp);
        el.sumProductDiscount.textContent = money.format(s.productDiscount);
        el.sumBillDiscount.textContent = money.format(s.billDiscount);
        el.sumSavings.textContent = money.format(s.savings);
        el.sumNet.textContent = money.format(s.net);
        if (el.gstTaxableAmount) el.gstTaxableAmount.value = money.format(s.taxable || 0);
        if (el.sumCgst) el.sumCgst.textContent = money.format(s.cgst || 0);
        if (el.sumSgst) el.sumSgst.textContent = money.format(s.sgst || 0);
        if (el.sumIgst) el.sumIgst.textContent = money.format(s.igst || 0);
        if (el.sumGst) el.sumGst.textContent = money.format(s.gstAmount || 0);
        el.sumRoundOff.textContent = money.format(s.roundOff);
        el.sumGrand.textContent = shortMoney.format(s.grand);

        // Keep paid amount zero. Pending amount equals grand total until collected in Pending Bills.
        el.singlePaidAmount.value = '0.00';
        el.singlePaymentRef.value = '';

        const s2 = currentSummary();
        el.paidAmountView.value = money.format(s2.paid);
        el.balanceAmountView.value = money.format(s2.balance);
        el.heldCount.textContent = state.heldBills.length;
    }

    function resolveHistoryStatus(row) {
        row = row || {};
        const billStatus = String(row.bill_status || row.workflow_status || '').toLowerCase().trim();
        const paymentStatus = String(row.payment_status || '').toLowerCase().trim();
        const source = String(row.source_type || (row.workflow_id ? 'workflow' : 'bill')).toLowerCase().trim();
        const total = toNumber(row.total_amount || row.net_amount || row.grand_total || 0);
        const paid = toNumber(row.paid_amount || row.collected_amount || 0);
        const balance = toNumber(row.balance_amount || row.pending_amount || row.due_amount || 0);

        if (billStatus === 'hold' || billStatus === 'draft') {
            return { key: 'hold', label: 'Hold' };
        }

        if (source === 'workflow' && !['converted', 'completed', 'cancelled', 'returned'].includes(billStatus)) {
            return { key: 'hold', label: 'Hold' };
        }

        if (paymentStatus === 'paid' || (total > 0 && balance <= 0.009 && paid >= (total - 0.009))) {
            return { key: 'paid', label: 'Paid' };
        }

        return { key: 'unpaid', label: 'Unpaid' };
    }

    function historyStatusPill(row) {
        const status = resolveHistoryStatus(row);
        return '<span class="history-status-pill ' + escapeHtml(status.key) + '">' + escapeHtml(status.label) + '</span>';
    }

    function filterHistoryRecords(records) {
        const selected = el.billHistoryFilter ? String(el.billHistoryFilter.value || 'all').toLowerCase() : 'all';
        if (selected === 'all') return records;
        return (records || []).filter(function (row) {
            return resolveHistoryStatus(row).key === selected;
        });
    }

    function renderBillHistory(records) {
        records = records || state.billHistory || [];
        const holdDraftCount = records.filter(function (r) { return resolveHistoryStatus(r).key === 'hold'; }).length;
        el.heldCount.textContent = holdDraftCount;

        const visibleRecords = filterHistoryRecords(records);
        if (!visibleRecords.length) {
            el.billHistoryBody.innerHTML = '<div class="text-center text-muted py-4">No bill history found.</div>';
            return;
        }

        el.billHistoryBody.innerHTML = visibleRecords.map(function (row) {
            const id = parseInt(row.bill_id || row.workflow_id || 0, 10);
            const rawStatus = String(row.bill_status || 'completed').toLowerCase();
            const singleStatus = resolveHistoryStatus(row);
            const source = String(row.source_type || (row.workflow_id ? 'workflow' : 'bill'));
            const canEdit = source === 'workflow' && ['hold', 'draft'].includes(rawStatus);
            const canCancel = rawStatus !== 'cancelled' && rawStatus !== 'returned';
            const canReturn = source === 'bill' && rawStatus === 'completed' && singleStatus.key === 'paid';
            const canPrint = source === 'bill' && parseInt(row.bill_id || 0, 10) > 0;
            return `<div class="history-card">
                <div class="avatar-circle"><i data-lucide="receipt"></i></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="held-no">${escapeHtml(row.bill_no || row.workflow_no || '-')} <span class="ms-1">${historyStatusPill(row)}</span></div>
                    <div class="held-meta">${escapeHtml(row.customer_name || 'Walk-in Customer')} • ${escapeHtml(row.date_time || row.created_at || '')}</div>
                    <div class="held-meta">Total: <b>${money.format(toNumber(row.total_amount || row.net_amount || 0))}</b></div>
                </div>
                <div class="d-flex flex-wrap gap-1 justify-content-end">
                    ${canEdit ? `<button type="button" class="btn btn-sm btn-outline-primary rounded-pill fw-bold js-resume-workflow" data-id="${id}">Edit</button>` : ''}
                    ${canPrint ? `<button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold js-reprint-bill" data-id="${row.bill_id}">Reprint</button>` : ''}
                    ${canReturn ? `<button type="button" class="btn btn-sm btn-outline-warning rounded-pill fw-bold js-return-bill" data-id="${row.bill_id}">Return</button>` : ''}
                    ${canCancel ? `<button type="button" class="btn btn-sm btn-outline-danger rounded-pill fw-bold js-cancel-history" data-id="${id}" data-source="${escapeHtml(source)}">Cancel</button>` : ''}
                    ${canPrint ? `<button type="button" class="btn btn-sm btn-dark rounded-pill fw-bold js-open-bill" data-id="${row.bill_id}">Open</button>` : ''}
                </div>
            </div>`;
        }).join('');

        refreshIcons();
    }

    function renderHeldBills() {
        renderBillHistory(state.billHistory || []);
    }

    async function loadBillHistory() {
        try {
            const filter = el.billHistoryFilter ? el.billHistoryFilter.value : 'all';
            const q = el.billHistorySearch ? el.billHistorySearch.value.trim() : '';
            const apiFilter = ['paid', 'hold', 'unpaid'].includes(String(filter).toLowerCase()) ? 'all' : filter;
            const data = await apiGet({ action: 'bill_history', status_filter: apiFilter, q: q });
            if (!data.success) {
                showMessage('error', data.message || 'Unable to load bill history.');
                return;
            }
            state.billHistory = data.history || [];
            state.heldBills = state.billHistory.filter(function (r) { return String(r.bill_status || '').toLowerCase() === 'hold'; });
            renderBillHistory(state.billHistory);
        } catch (error) {
            showMessage('error', 'Unable to connect to bill history API.');
        }
    }

    async function bootstrap() {
        try {
            const data = await apiGet({ action: 'bootstrap' });
            if (!data.success) {
                showMessage('error', data.message || 'Unable to load POS data.');
                return;
            }
            state.business = data.business || {};
            state.businessSettings = data.business_settings || {};
            state.invoiceSettings = data.invoice_settings || {};
            state.barcodeSettings = data.barcode_settings || {};
            state.branches = data.branches || [];
            state.branchId = parseInt(data.selected_branch_id || initialBranchId || 0, 10);
            state.billNo = data.next_bill_no || 'AUTO';
            state.billBarcode = data.next_bill_barcode || '';
            state.paymentMethods = data.payment_methods || [];
            state.heldBills = data.held_bills || [];
                state.billHistory = data.bill_history || state.billHistory;
            state.billHistory = data.bill_history || [];
            el.billNo.textContent = state.billNo;
            renderBranches();
            renderPaymentMethods();
            renderHeldBills();
            renderCustomer();
            applyGstVisibility();
            if (el.gstEnabled && !state.items.length) {
                const defaultGstOn = String(state.invoiceSettings.gst_type_key || state.businessSettings.gst_type_key || state.business.gst_type_key || '').toLowerCase() === 'gst_regular';
                el.gstEnabled.checked = defaultGstOn;
            }
            renderSummary();
            refreshIcons();
        } catch (error) {
            showMessage('error', 'Unable to connect POS API. Check api/pos-billing-api.php.');
        }
    }

    async function refreshNumbersAndHolds() {
        try {
            const data = await apiGet({ action: 'bootstrap' });
            if (data.success) {
                state.billNo = data.next_bill_no || 'AUTO';
                state.billBarcode = data.next_bill_barcode || '';
                state.invoiceSettings = data.invoice_settings || state.invoiceSettings;
                state.businessSettings = data.business_settings || state.businessSettings;
                state.paymentMethods = data.payment_methods || state.paymentMethods;
                state.heldBills = data.held_bills || [];
                el.billNo.textContent = state.billNo;
                renderPaymentMethods();
                renderHeldBills();
                applyGstVisibility();
                renderSummary();
            }
        } catch (error) {}
    }

    function selectProduct(product, options) {
        state.selectedProduct = product;
        state.productOptions = options || [product];
        el.qtyInput.value = '1';
        el.itemRemarks.value = '';
        renderProduct(product);
        setTimeout(() => el.qtyInput.select(), 50);
    }

    async function searchProducts(query) {
        query = String(query || '').trim();
        if (!query || query.length < 1) {
            el.productSuggestions.style.display = 'none';
            return;
        }
        try {
            const data = await apiGet({ action: 'search_products', q: query });
            if (!data.success) {
                return;
            }

            const allProducts = data.products || [];
            const products = allProducts.slice(0, 5);

            if (!products.length) {
                el.productSuggestions.products = [];
                el.productSuggestions.innerHTML = `
                    <div class="suggestion-item is-empty">
                        <div class="suggestion-img">0</div>
                        <div class="suggestion-content">
                            <div class="suggestion-name">No stock found</div>
                            <div class="suggestion-meta"><span>Try article no, brand, barcode, color or size.</span></div>
                        </div>
                    </div>`;
                el.productSuggestions.style.display = 'block';
                return;
            }

            let html = `<div class="suggestion-header"><span>Matching Products</span><span>Showing ${products.length} of ${allProducts.length}</span></div>`;
            html += products.map(function (p, index) {
                const sizeTone = sizeToneClass(p.stock_item_id || p.size || index, index);
                return `<div class="suggestion-item js-product-suggestion" data-id="${p.stock_item_id}" title="Select product">
                    <div class="suggestion-img">${productInitial(p)}</div>
                    <div class="suggestion-content">
                        <div class="suggestion-title-line">
                            <div class="suggestion-name">${escapeHtml(p.article_name || p.article_no || 'Product')}</div>
                        </div>
                        <div class="suggestion-meta">
                            <span>Article: ${escapeHtml(p.article_no || '-')}</span>
                            <span>${escapeHtml(p.brand_name || '-')}</span>
                            <span>${escapeHtml(p.color || '-')}</span>
                        </div>
                        ${stockEntryDateRow(p, sizeTone)}
                    </div>
                    <div class="suggestion-stock"><strong>${toNumber(p.available_qty || 0)}</strong>in stock<br>${money.format(toNumber(p.selling_rate))}</div>
                </div>`;
            }).join('');

            el.productSuggestions.products = products;
            el.productSuggestions.innerHTML = html;
            el.productSuggestions.style.display = 'block';
        } catch (error) {}
    }

    async function loadProductOptions(stockItemId, productFromList) {
        try {
            const data = await apiGet({ action: 'get_product_options', stock_item_id: stockItemId });
            const options = data.success ? (data.options || []) : [];
            const selected = options.find(p => Number(p.stock_item_id) === Number(stockItemId)) || productFromList;
            selectProduct(selected, options.length ? options : [productFromList]);
            el.productSuggestions.style.display = 'none';
            el.productSearch.value = '';
        } catch (error) {
            selectProduct(productFromList, [productFromList]);
        }
    }

    function addSelectedProductToBill(autoQty) {
        const p = state.selectedProduct;
        if (!p) {
            showMessage('warning', 'Select a product first.');
            el.productSearch.focus();
            return;
        }

        const qty = Math.max(1, toNumber(autoQty || el.qtyInput.value || 1));
        const existingQty = state.items.filter(i => Number(i.stock_item_id) === Number(p.stock_item_id)).reduce((sum, i) => sum + toNumber(i.qty), 0);

        if (existingQty + qty > toNumber(p.available_qty)) {
            showMessage('error', 'Stock not available. Available: ' + p.available_qty + ', already in bill: ' + existingQty);
            return;
        }

        const selling = toNumber(el.sellingInput.value || p.selling_rate);
        const mrp = toNumber(p.mrp_rate);
        if (selling < 0 || selling > mrp) {
            showMessage('error', 'Selling price cannot be negative or greater than MRP.');
            el.sellingInput.focus();
            return;
        }

        const item = {
            stock_item_id: p.stock_item_id,
            stock_batch_id: p.stock_batch_id,
            barcode_id: p.barcode_id || 0,
            barcode_value: p.barcode_value || '',
            article_no: p.article_no,
            article_name: p.article_name || '',
            brand_id: p.brand_id || 0,
            brand_name: p.brand_name || '',
            category_id: p.category_id || 0,
            category_name: p.category_name || '',
            color: p.color || '',
            size: p.size || '',
            available_qty: p.available_qty,
            qty: qty,
            mrp_rate: mrp,
            product_discount_type: el.discountType.value,
            product_discount_value: toNumber(el.discountValue.value),
            discount_type: el.discountType.value,
            discount_value: toNumber(el.discountValue.value),
            selling_rate: selling,
            item_remarks: el.itemRemarks.value.trim()
        };

        const duplicate = state.items.find(i => Number(i.stock_item_id) === Number(item.stock_item_id) && toNumber(i.selling_rate) === toNumber(item.selling_rate));
        if (duplicate) {
            duplicate.qty = toNumber(duplicate.qty) + qty;
        } else {
            state.items.push(item);
        }

        renderBillItems();
        state.selectedProduct = null;
        state.productOptions = [];
        renderProduct(null);
        el.productSearch.focus();
    }

    async function scanCode(code) {
        code = String(code || '').trim();
        if (!code) return;

        try {
            const data = await apiGet({ action: 'scan_product', code: code });
            if (!data.success || !data.scan) {
                showMessage('error', data.message || 'Scan failed.');
                return;
            }
            if (data.scan.type === 'product') {
                selectProduct(data.scan.product, data.scan.options || [data.scan.product]);
                addSelectedProductToBill(1);
                showMessage('success', 'Product added from scan.');
            } else if (data.scan.type === 'bill') {
                state.lastSavedBill = data.scan.bill;
                showPreview(data.scan.bill);
                showMessage('success', 'Bill barcode loaded.');
            } else {
                showMessage('warning', 'No active stock or bill found for this code.');
            }
        } catch (error) {
            showMessage('error', 'Unable to scan code.');
        }
    }

    async function searchCustomers(query) {
        query = String(query || '').trim();
        try {
            const data = await apiGet({ action: 'search_customers', q: query });
            const allCustomers = data.success ? (data.customers || []) : [];
            const customers = allCustomers.slice(0, 5);
            let html = `<div class="suggestion-header"><span>${query ? 'Matching Customers' : 'Recent Customers'}</span><span>Showing ${customers.length} of ${allCustomers.length}</span></div>`;

            if (!customers.length && !query) {
                html += `<div class="suggestion-item is-empty">
                    <div class="suggestion-img"><i data-lucide="users" style="width:16px;height:16px;"></i></div>
                    <div class="suggestion-content">
                        <div class="suggestion-name">No saved customers yet</div>
                        <div class="suggestion-meta"><span>Type a new customer name/mobile and save the bill to auto-create.</span></div>
                    </div>
                </div>`;
            }

            html += customers.map(function (c) {
                const initial = escapeHtml((c.customer_name || 'C').substring(0,1).toUpperCase());
                return `<div class="suggestion-item js-customer-suggestion" data-id="${c.customer_id}" title="Select customer">
                    <div class="suggestion-img">${initial}</div>
                    <div class="suggestion-content">
                        <div class="suggestion-name">${escapeHtml(c.customer_name || 'Customer')}</div>
                        <div class="suggestion-meta">
                            <span>${escapeHtml(c.mobile || 'No mobile')}</span>
                            <span>Outstanding ${money.format(toNumber(c.outstanding_balance || 0))}</span>
                            <span>Loyalty ${toNumber(c.loyalty_points || 0)}</span>
                            <span>Bills ${toNumber(c.purchase_count || 0)}</span>
                        </div>
                    </div>
                </div>`;
            }).join('');

            if (query) {
                html += `<div class="suggestion-item is-create js-create-customer-from-search" title="Create new customer">
                    <div class="suggestion-img">+</div>
                    <div class="suggestion-content">
                        <div class="suggestion-name">Create "${escapeHtml(query)}"</div>
                        <div class="suggestion-meta"><span>New customer will also be auto-created when Save Bill is clicked.</span></div>
                    </div>
                </div>`;
            }

            el.customerSuggestions.customers = customers;
            el.customerSuggestions.innerHTML = html;
            el.customerSuggestions.style.display = 'block';
            if (window.lucide) window.lucide.createIcons();
        } catch (error) {}
    }

    function setWalkInCustomer() {
        state.selectedCustomer = null;
        el.customerSearch.value = '';
        el.customerSuggestions.style.display = 'none';
        renderCustomer();
        renderSummary();
    }

    function buildPayload() {
        const customer = state.selectedCustomer ? state.selectedCustomer : {
            customer_id: 0,
            customer_name: el.customerSearch.value.trim() || 'Walk-in Customer',
            mobile: normalizeMobile(el.customerSearch.value)
        };
        const taxSummary = currentSummary();

        return {
            hold_id: state.holdId || 0,
            workflow_id: state.holdId || 0,
            workflow_type: state.workflowType || '',
            branch_id: state.branchId,
            customer: customer,
            customer_notes: el.customerNotes.value.trim(),
            sales_notes: el.salesNotes.value.trim(),
            offer_code: el.offerCode.value.trim().toUpperCase(),
            bill_discount_type: el.billDiscountType.value,
            bill_discount_value: toNumber(el.billDiscountValue.value),
            loyalty_redeem_amount: toNumber(el.loyaltyRedeem.value),
            gst_enabled: taxSummary.gstEnabled ? 1 : 0,
            gst_type_key: taxSummary.gstEnabled ? 'gst_regular' : 'non_gst',
            gst_mode: taxSummary.gstMode,
            gst_rate: taxSummary.gstRate,
            taxable_amount: taxSummary.taxable,
            cgst_amount: taxSummary.cgst,
            sgst_amount: taxSummary.sgst,
            igst_amount: taxSummary.igst,
            tax_amount: taxSummary.gstAmount,
            net_before_tax: taxSummary.net,
            net_before_round: taxSummary.beforeRound,
            grand_total: taxSummary.grand,
            items: state.items.map(function (item) {
                return {
                    stock_item_id: item.stock_item_id,
                    stock_batch_id: item.stock_batch_id,
                    barcode_id: item.barcode_id || 0,
                    qty: toNumber(item.qty),
                    selling_rate: toNumber(item.selling_rate),
                    discount_type: item.discount_type || item.product_discount_type || 'none',
                    discount_value: toNumber(item.discount_value || item.product_discount_value || 0),
                    item_remarks: item.item_remarks || '',
                    article_no: item.article_no || '',
                    article_name: item.article_name || '',
                    brand_name: item.brand_name || '',
                    color: item.color || '',
                    size: item.size || '',
                    mrp_rate: toNumber(item.mrp_rate),
                    available_qty: toNumber(item.available_qty)
                };
            }),
            payments: buildPayments()
        };
    }

    function buildPayments() {
        // No payment is sent during bill creation. Payment is collected only from Pending Bills.
        return [];
    }

    function openBillPrint(billId, autoPrint) {
        billId = parseInt(billId || 0, 10);
        if (!billId) {
            showMessage('warning', 'Please save the bill before printing.');
            return;
        }
        const url = 'bill-print.php?bill_id=' + encodeURIComponent(billId) + '&auto_print=' + (autoPrint ? '1' : '0');
        window.open(url, '_blank', 'noopener');
    }

    async function saveBill(printAfter) {
        if (!state.items.length) {
            showMessage('warning', 'Add at least one item.');
            return;
        }

        const payload = buildPayload();
        try {
            const data = await apiPost('save_bill', payload);
            if (!data.success) {
                showMessage('error', data.message || 'Bill save failed.');
                return;
            }

            showMessage('success', data.message || 'Bill saved.');
            state.lastSavedBill = data.saved.bill || null;
            state.lastSavedBillId = parseInt(data.saved.bill_id || (state.lastSavedBill ? state.lastSavedBill.bill_id : 0) || 0, 10);
            if (state.lastSavedBill) {
                state.billBarcode = state.lastSavedBill.bill_barcode || state.lastSavedBill.barcode_value || state.billBarcode || '';
            }

            if (printAfter) {
                openBillPrint(state.lastSavedBillId, true);
            }

            resetBill(true);
            await refreshNumbersAndHolds();
            await loadBillHistory();
        } catch (error) {
            showMessage('error', 'Unable to save bill.');
        }
    }

    async function saveWorkflow(type) {
        if (!state.items.length) {
            showMessage('warning', 'Add items before saving ' + (type === 'draft' ? 'draft.' : 'hold.'));
            return;
        }

        try {
            state.workflowType = type;
            const data = await apiPost(type === 'draft' ? 'save_draft' : 'save_hold', buildPayload());
            if (!data.success) {
                showMessage('error', data.message || 'Unable to save bill workflow.');
                return;
            }

            state.billHistory = data.history || state.billHistory;
            state.heldBills = data.held_bills || state.heldBills;
            renderBillHistory(state.billHistory);
            showMessage('success', data.message || (type === 'draft' ? 'Bill saved as draft.' : 'Bill held successfully.'));
            resetBill(false);
            await loadBillHistory();
            await refreshNumbersAndHolds();
        } catch (error) {
            showMessage('error', 'Unable to save bill workflow.');
        }
    }

    async function holdBill() {
        await saveWorkflow('hold');
    }

    async function draftBill() {
        await saveWorkflow('draft');
    }

    async function cancelCurrentBill() {
        if (!state.items.length) {
            resetBill(false);
            showMessage('success', 'Empty bill cancelled.');
            return;
        }
        if (!confirm('Cancel this current bill? It will be recorded in Bill History.')) return;
        try {
            state.workflowType = 'cancelled';
            const payload = buildPayload();
            payload.workflow_type = 'cancelled';
            const data = await apiPost('cancel_current_bill', payload);
            if (!data.success) {
                showMessage('error', data.message || 'Unable to cancel bill.');
                return;
            }
            showMessage('success', data.message || 'Bill cancelled.');
            resetBill(false);
            await loadBillHistory();
        } catch (error) {
            showMessage('error', 'Unable to cancel bill.');
        }
    }

    async function returnCurrentBill() {
        const billId = state.lastSavedBillId || (state.lastSavedBill ? parseInt(state.lastSavedBill.bill_id || 0, 10) : 0);
        if (!billId) {
            showMessage('warning', 'Open or save a completed bill before return.');
            return;
        }
        await returnSavedBill(billId);
    }

    function resetBill(keepLastSaved) {
        state.items = [];
        state.selectedProduct = null;
        state.productOptions = [];
        state.selectedCustomer = null;
        state.holdId = 0;
        state.workflowType = '';
        if (!keepLastSaved) { state.lastSavedBill = null; state.lastSavedBillId = 0; }
        el.productSearch.value = '';
        el.customerSearch.value = '';
        el.billDiscountType.value = 'none';
        el.billDiscountValue.value = '0.00';
        el.offerCode.value = '';
        el.loyaltyRedeem.value = '0.00';
        el.customerNotes.value = '';
        el.salesNotes.value = '';
        el.singlePaymentRef.value = '';
        renderProduct(null);
        renderCustomer();
        renderBillItems();
        renderSummary();
    }

    async function resumeWorkflow(holdId) {
        try {
            const data = await apiGet({ action: 'resume_workflow', workflow_id: holdId });
            if (!data.success) {
                showMessage('error', data.message || 'Unable to resume saved bill.');
                return;
            }

            const h = data.hold_data || {};
            state.holdId = parseInt(h.hold_id || holdId, 10);
            state.items = h.items || [];
            state.selectedCustomer = h.customer && (h.customer.customer_id || h.customer.customer_name) ? h.customer : null;
            el.billDiscountType.value = h.bill_discount_type || 'none';
            el.billDiscountValue.value = toNumber(h.bill_discount_value || 0).toFixed(2);
            el.offerCode.value = h.offer_code || '';
            el.loyaltyRedeem.value = toNumber(h.loyalty_redeem_amount || 0).toFixed(2);
            el.customerNotes.value = h.customer_notes || '';
            el.salesNotes.value = h.sales_notes || '';
            renderCustomer();
            renderBillItems();
            renderSummary();
            persistCurrentBill();
            modal('billHistoryModal').hide();
            showMessage('success', 'Bill restored.');
        } catch (error) {
            showMessage('error', 'Unable to resume saved bill.');
        }
    }

    async function cancelWorkflow(holdId) {
        if (!confirm('Cancel this history entry?')) return;
        try {
            const data = await apiPost('cancel_workflow', { workflow_id: holdId });
            if (data.success) {
                state.billHistory = data.history || state.billHistory;
                renderBillHistory(state.billHistory);
                showMessage('success', data.message || 'Bill entry cancelled.');
            } else {
                showMessage('error', data.message || 'Unable to cancel bill entry.');
            }
        } catch (error) {
            showMessage('error', 'Unable to cancel bill entry.');
        }
    }

    async function cancelSavedBill(billId) {
        billId = parseInt(billId || 0, 10);
        if (!billId) return;
        if (!confirm('Cancel this completed bill? Stock will be restored if the API supports it.')) return;
        try {
            const data = await apiPost('cancel_saved_bill', { bill_id: billId, reason: 'Cancelled from POS history' });
            showMessage(data.success ? 'success' : 'error', data.message || 'Cancel failed.');
            if (data.success) await loadBillHistory();
        } catch (error) {
            showMessage('error', 'Unable to cancel saved bill.');
        }
    }

    async function returnSavedBill(billId) {
        billId = parseInt(billId || 0, 10);
        if (!billId) return;
        if (!confirm('Return this bill? Stock will be restored and return history will be recorded.')) return;
        try {
            const data = await apiPost('return_saved_bill', { bill_id: billId, reason: 'Returned from POS history' });
            showMessage(data.success ? 'success' : 'error', data.message || 'Return failed.');
            if (data.success) await loadBillHistory();
        } catch (error) {
            showMessage('error', 'Unable to return bill.');
        }
    }

    function showPreview(savedBill) {
        const bill = savedBill || null;
        const summary = currentSummary();
        const business = state.business || {};
        const branch = state.branches.find(b => Number(b.branch_id) === Number(state.branchId)) || {};
        const items = bill ? (bill.items || []) : state.items;
        const billNo = bill ? bill.bill_no : state.billNo;
        const customerName = bill ? bill.customer_name : (state.selectedCustomer ? state.selectedCustomer.customer_name : 'Walk-in Customer');
        const customerMobile = bill ? bill.customer_mobile : (state.selectedCustomer ? state.selectedCustomer.mobile : '');
        const invoiceTitle = bill ? bill.invoice_title : (state.invoiceSettings.invoice_title || 'Bill of Supply');
        const barcode = bill ? (bill.bill_barcode || '') : state.billBarcode;
        const net = bill ? toNumber(bill.net_amount) : summary.grand;
        const mrp = bill ? toNumber(bill.mrp_total) : summary.mrp;
        const savings = bill ? toNumber(bill.today_savings_amount) : summary.savings;
        const paid = bill ? toNumber(bill.paid_amount) : summary.paid;
        const balance = bill ? toNumber(bill.balance_amount) : summary.balance;
        const previewGstOn = bill ? (String(bill.gst_type_key || '').toLowerCase() === 'gst_regular' && toNumber(bill.tax_amount) > 0) : summary.gstEnabled;
        const previewCgst = bill ? toNumber(bill.cgst_amount) : summary.cgst;
        const previewSgst = bill ? toNumber(bill.sgst_amount) : summary.sgst;
        const previewIgst = bill ? toNumber(bill.igst_amount) : summary.igst;
        const previewTax = bill ? toNumber(bill.tax_amount) : summary.gstAmount;
        const previewGstinText = systemGstEnabled() && business.gstin ? '• GSTIN: ' + escapeHtml(business.gstin) : '';

        el.previewBody.innerHTML = `
            <h3>${escapeHtml(business.business_name || 'GK FOOTWEAR')}</h3>
            <div class="center muted">${escapeHtml(business.address || branch.address || '')}</div>
            <div class="center muted">${escapeHtml(invoiceTitle)} ${previewGstinText}</div>
            <hr>
            <table>
                <tr><td>Bill No</td><td class="right"><b>${escapeHtml(billNo)}</b></td></tr>
                <tr><td>Date</td><td class="right">${escapeHtml(bill ? (bill.bill_date + ' ' + (bill.bill_time || '')) : new Date().toLocaleString('en-IN'))}</td></tr>
                <tr><td>Branch</td><td class="right">${escapeHtml(branch.branch_name || bill?.branch_name || '')}</td></tr>
                <tr><td>Customer</td><td class="right">${escapeHtml(customerName || 'Walk-in Customer')} ${customerMobile ? '<br>' + escapeHtml(customerMobile) : ''}</td></tr>
            </table>
            <hr>
            <table>
                <thead><tr><th>Item</th><th class="right">Qty</th><th class="right">Rate</th><th class="right">Amt</th></tr></thead>
                <tbody>
                    ${items.map(function (item) {
                        const name = item.article_name || item.article_no || '';
                        const size = item.size ? ' / ' + item.size : '';
                        const color = item.color ? ' / ' + item.color : '';
                        const qty = toNumber(item.qty);
                        const rate = toNumber(item.selling_rate);
                        const amount = bill ? toNumber(item.amount) : qty * rate;
                        return `<tr><td>${escapeHtml(name + size + color)}<br><span class="muted">${escapeHtml(item.article_no || '')}</span></td><td class="right">${qty}</td><td class="right">${rate.toFixed(2)}</td><td class="right">${amount.toFixed(2)}</td></tr>`;
                    }).join('')}
                </tbody>
            </table>
            <hr>
            <table>
                <tr><td>MRP Total</td><td class="right">${money.format(mrp)}</td></tr>
                <tr><td>Today's Savings</td><td class="right">${money.format(savings)}</td></tr>
                ${previewGstOn && previewCgst > 0 ? `<tr><td>CGST</td><td class="right">${money.format(previewCgst)}</td></tr>` : ''}
                ${previewGstOn && previewSgst > 0 ? `<tr><td>SGST</td><td class="right">${money.format(previewSgst)}</td></tr>` : ''}
                ${previewGstOn && previewIgst > 0 ? `<tr><td>IGST</td><td class="right">${money.format(previewIgst)}</td></tr>` : ''}
                ${previewGstOn && previewTax > 0 ? `<tr><td>Total GST</td><td class="right">${money.format(previewTax)}</td></tr>` : ''}
                <tr><td class="total">Grand Total</td><td class="right total">${money.format(net)}</td></tr>
                <tr><td>Payment Status</td><td class="right"><b>${escapeHtml(bill ? (bill.payment_status || 'pending') : 'pending')}</b></td></tr>
                <tr><td>Paid</td><td class="right">${money.format(paid)}</td></tr>
                <tr><td>Pending</td><td class="right"><b>${money.format(balance)}</b></td></tr>
            </table>
            ${barcode ? `<div class="preview-barcode-wrap"><img class="preview-barcode-img" src="${barcodeImageUrl(barcode)}" alt="Invoice barcode"><div class="preview-barcode-no">${escapeHtml(barcode)}</div></div>` : ''}
            ${state.invoiceSettings.composition_note && parseInt(state.invoiceSettings.show_composition_note || 0, 10) === 1 ? `<div class="center muted mt-2">${escapeHtml(state.invoiceSettings.composition_note)}</div>` : ''}
            ${state.invoiceSettings.footer_text ? `<div class="center muted mt-2">${escapeHtml(state.invoiceSettings.footer_text)}</div>` : '<div class="center muted mt-2">Thank you. Visit again.</div>'}
        `;

        modal('previewModal').show();
    }

    let productSearchTimer = null;
    let customerSearchTimer = null;

    el.productSearch.addEventListener('input', function () {
        clearTimeout(productSearchTimer);
        productSearchTimer = setTimeout(function () { searchProducts(el.productSearch.value.trim()); }, 220);
    });

    el.productSearch.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            scanCode(el.productSearch.value.trim());
        }
    });

    el.customerSearch.addEventListener('input', function () {
        clearTimeout(customerSearchTimer);
        customerSearchTimer = setTimeout(function () { searchCustomers(el.customerSearch.value.trim()); }, 250);
        persistCurrentBill();
    });

    el.customerSearch.addEventListener('focus', function () {
        searchCustomers(el.customerSearch.value.trim());
    });

    el.branchSelect.addEventListener('change', async function () {
        const oldBranchId = state.branchId;
        if (state.items.length && !confirm('Changing branch will clear current bill. Continue?')) {
            el.branchSelect.value = String(state.branchId);
            return;
        }
        clearPersistedBill(oldBranchId);
        state.branchId = parseInt(el.branchSelect.value || 0, 10);
        resetBill(false);
        await refreshNumbersAndHolds();
        restorePersistedBill();
    });

    document.addEventListener('click', function (event) {
        const productNode = event.target.closest('.js-product-suggestion');
        if (productNode) {
            const id = parseInt(productNode.dataset.id || 0, 10);
            const product = (el.productSuggestions.products || []).find(p => Number(p.stock_item_id) === Number(id));
            if (product) loadProductOptions(id, product);
        }

        const customerNode = event.target.closest('.js-customer-suggestion');
        if (customerNode) {
            const id = parseInt(customerNode.dataset.id || 0, 10);
            const c = (el.customerSuggestions.customers || []).find(x => Number(x.customer_id) === Number(id));
            if (c) {
                state.selectedCustomer = c;
                el.customerSearch.value = c.customer_name + (c.mobile ? ' - ' + c.mobile : '');
                el.customerSuggestions.style.display = 'none';
                renderCustomer();
                renderSummary();
                persistCurrentBill();
            }
        }

        if (event.target.closest('.js-create-customer-from-search')) {
            const value = el.customerSearch.value.trim();
            document.getElementById('newCustomerName').value = /^\d+$/.test(value) ? '' : value;
            document.getElementById('newCustomerMobile').value = normalizeMobile(value);
            modal('customerModal').show();
        }

        const insideSearchArea = event.target.closest('.pos-customer-bar, .pos-search-box, .suggestion-list');
        if (!insideSearchArea) {
            el.customerSuggestions.style.display = 'none';
            el.productSuggestions.style.display = 'none';
        }

        const sizeNode = event.target.closest('.size-chip[data-stock-id]');
        if (sizeNode) {
            const id = parseInt(sizeNode.dataset.stockId || 0, 10);
            const p = state.productOptions.find(x => Number(x.stock_item_id) === Number(id));
            if (p) {
                state.selectedProduct = p;
                renderProduct(p);
            }
        }

        const colorNode = event.target.closest('.color-chip[data-color]');
        if (colorNode) {
            const color = colorNode.dataset.color || 'Default';
            const p = state.productOptions.find(x => (x.color || 'Default') === color) || state.selectedProduct;
            if (p) {
                state.selectedProduct = p;
                renderProduct(p);
            }
        }

        const payNode = event.target.closest('.pay-chip[data-method-id]');
        if (payNode) {
            state.selectedPaymentMethodId = parseInt(payNode.dataset.methodId || 0, 10);
            state.paymentMode = payNode.dataset.methodType || 'cash';
            el.singlePaymentBox.classList.toggle('d-none', state.paymentMode === 'split');
            el.splitPaymentBox.classList.toggle('d-none', state.paymentMode !== 'split');
            renderPaymentMethods();
            renderSummary();
            persistCurrentBill();
        }

        const removeNode = event.target.closest('.js-remove-line');
        if (removeNode) {
            state.items.splice(parseInt(removeNode.dataset.index, 10), 1);
            renderBillItems();
        }

        const editNode = event.target.closest('.js-edit-line');
        if (editNode) {
            const item = state.items[parseInt(editNode.dataset.index, 10)];
            if (item) {
                selectProduct(item, [item]);
                el.qtyInput.value = item.qty;
                el.sellingInput.value = toNumber(item.selling_rate).toFixed(2);
                el.discountType.value = item.discount_type || 'none';
                el.discountValue.value = toNumber(item.discount_value || 0).toFixed(2);
                el.itemRemarks.value = item.item_remarks || '';
                state.items.splice(parseInt(editNode.dataset.index, 10), 1);
                renderBillItems();
            }
        }

        const resumeNode = event.target.closest('.js-resume-workflow, .js-resume-hold');
        if (resumeNode) {
            resumeWorkflow(parseInt(resumeNode.dataset.id, 10));
        }

        const cancelHoldNode = event.target.closest('.js-cancel-history, .js-cancel-hold');
        if (cancelHoldNode) {
            const source = cancelHoldNode.dataset.source || 'workflow';
            if (source === 'bill') { cancelSavedBill(parseInt(cancelHoldNode.dataset.id, 10)); }
            else { cancelWorkflow(parseInt(cancelHoldNode.dataset.id, 10)); }
        }

        const reprintNode = event.target.closest('.js-reprint-bill, .js-open-bill');
        if (reprintNode) {
            openBillPrint(parseInt(reprintNode.dataset.id, 10), reprintNode.classList.contains('js-reprint-bill'));
        }

        const returnNode = event.target.closest('.js-return-bill');
        if (returnNode) {
            returnSavedBill(parseInt(returnNode.dataset.id, 10));
        }
    });

    document.addEventListener('input', function (event) {
        const qtyLine = event.target.closest('.js-line-qty');
        if (qtyLine) {
            const i = parseInt(qtyLine.dataset.index, 10);
            const item = state.items[i];
            const qty = Math.max(1, toNumber(qtyLine.value));
            if (item && qty <= toNumber(item.available_qty)) {
                item.qty = qty;
            } else if (item) {
                qtyLine.value = item.qty;
                showMessage('warning', 'Quantity cannot exceed available stock.');
            }
            renderSummary();
        }

        const sellingLine = event.target.closest('.js-line-selling');
        if (sellingLine) {
            const i = parseInt(sellingLine.dataset.index, 10);
            const item = state.items[i];
            const selling = toNumber(sellingLine.value);
            if (item && selling >= 0 && selling <= toNumber(item.mrp_rate)) {
                item.selling_rate = selling;
            }
            renderSummary();
        }

        if (event.target.matches('#billDiscountType, #billDiscountValue, #loyaltyRedeem, #gstRate, #singlePaidAmount, .split-amount, .split-ref, #customerNotes, #salesNotes, #singlePaymentRef')) {
            renderSummary();
            persistCurrentBill();
        }

        if (qtyLine || sellingLine) {
            persistCurrentBill();
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('#gstEnabled, #gstMode')) {
            renderSummary();
            persistCurrentBill();
        }
    });

    document.getElementById('qtyMinus').addEventListener('click', function () { el.qtyInput.value = Math.max(1, toNumber(el.qtyInput.value) - 1); });
    document.getElementById('qtyPlus').addEventListener('click', function () { el.qtyInput.value = toNumber(el.qtyInput.value) + 1; });
    document.getElementById('addToBillBtn').addEventListener('click', function () { addSelectedProductToBill(); });
    document.getElementById('focusSearchBtn').addEventListener('click', function () { el.productSearch.focus(); });
    document.getElementById('walkInBtn').addEventListener('click', setWalkInCustomer);
    document.getElementById('addCustomerBtn').addEventListener('click', function () { modal('customerModal').show(); });
    document.getElementById('clearItemsBtn').addEventListener('click', function () {
        if (confirm('Clear all bill items?')) {
            state.items = [];
            clearPersistedBill();
            renderBillItems();
            renderSummary();
            showMessage('success', 'Bill items cleared.');
        }
    });
    document.getElementById('refreshBtn').addEventListener('click', refreshNumbersAndHolds);
    document.getElementById('billHistoryBtn').addEventListener('click', function () { loadBillHistory(); modal('billHistoryModal').show(); });
    document.getElementById('holdBillBtn').addEventListener('click', holdBill);
    document.getElementById('saveBillBtn').addEventListener('click', function () { saveBill(false); });
    document.getElementById('savePrintBtn').addEventListener('click', function () { saveBill(true); });
    document.getElementById('previewBtn').addEventListener('click', function () { showPreview(null); });
    document.getElementById('printBtn').addEventListener('click', function () { openBillPrint(state.lastSavedBillId || (state.lastSavedBill ? state.lastSavedBill.bill_id : 0), true); });
    document.getElementById('draftBtn').addEventListener('click', draftBill);
    document.getElementById('cancelBillBtn').addEventListener('click', cancelCurrentBill);
    document.getElementById('returnBillBtn').addEventListener('click', returnCurrentBill);
    document.getElementById('newBillBtn').addEventListener('click', function () { if (!state.items.length || confirm('Start new bill?')) resetBill(false); });
    document.getElementById('modalPrintBtn').addEventListener('click', function () {
        const billId = state.lastSavedBillId || (state.lastSavedBill ? state.lastSavedBill.bill_id : 0);
        if (billId) { openBillPrint(billId, true); return; }
        window.print();
    });
    document.getElementById('darkModeBtn').addEventListener('click', function () { el.posPage.classList.toggle('dark-pos'); });

    if (el.billHistoryFilter) el.billHistoryFilter.addEventListener('change', loadBillHistory);
    if (el.billHistorySearch) el.billHistorySearch.addEventListener('input', function () { window.clearTimeout(window.__billHistoryTimer); window.__billHistoryTimer = window.setTimeout(loadBillHistory, 250); });

    document.getElementById('applyOfferBtn').addEventListener('click', async function () {
        const code = el.offerCode.value.trim().toUpperCase();
        if (!code) {
            showMessage('warning', 'Enter offer code.');
            return;
        }
        try {
            const data = await apiGet({ action: 'validate_offer', code: code });
            if (!data.success) {
                showMessage('error', data.message || 'Invalid offer.');
                return;
            }
            el.billDiscountType.value = data.offer.discount_type;
            el.billDiscountValue.value = toNumber(data.offer.discount_value).toFixed(2);
            renderSummary();
            persistCurrentBill();
            showMessage('success', 'Offer applied.');
        } catch (error) {
            showMessage('error', 'Invalid or expired offer.');
        }
    });

    document.getElementById('customerForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        const payload = {
            customer_name: document.getElementById('newCustomerName').value.trim(),
            mobile: normalizeMobile(document.getElementById('newCustomerMobile').value),
            email: document.getElementById('newCustomerEmail').value.trim(),
            gstin: document.getElementById('newCustomerGstin').value.trim().toUpperCase(),
            address: document.getElementById('newCustomerAddress').value.trim()
        };
        if (!payload.customer_name && !payload.mobile) {
            showMessage('warning', 'Enter customer name or mobile.');
            return;
        }

        try {
            const data = await apiPost('save_customer', payload);
            if (!data.success) {
                showMessage('error', data.message || 'Customer save failed.');
                return;
            }
            state.selectedCustomer = data.customer;
            el.customerSearch.value = data.customer.customer_name + (data.customer.mobile ? ' - ' + data.customer.mobile : '');
            renderCustomer();
            renderSummary();
            persistCurrentBill();
            modal('customerModal').hide();
            event.target.reset();
            showMessage('success', data.message || 'Customer saved.');
        } catch (error) {
            showMessage('error', 'Unable to save customer.');
        }
    });

    async function startScanner() {
        modal('scannerModal').show();
        el.manualScanInput.focus();

        if (!('BarcodeDetector' in window) || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            return;
        }

        try {
            state.scannerStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            el.scannerVideo.srcObject = state.scannerStream;
            await el.scannerVideo.play();
            const detector = new BarcodeDetector({ formats: ['qr_code', 'code_128', 'ean_13', 'ean_8'] });

            state.scannerTimer = setInterval(async function () {
                try {
                    const barcodes = await detector.detect(el.scannerVideo);
                    if (barcodes && barcodes.length) {
                        const code = barcodes[0].rawValue;
                        stopScanner();
                        modal('scannerModal').hide();
                        scanCode(code);
                    }
                } catch (error) {}
            }, 700);
        } catch (error) {
            showMessage('warning', 'Camera scanner unavailable. Use manual or USB scanner input.');
        }
    }

    function stopScanner() {
        if (state.scannerTimer) {
            clearInterval(state.scannerTimer);
            state.scannerTimer = null;
        }
        if (state.scannerStream) {
            state.scannerStream.getTracks().forEach(track => track.stop());
            state.scannerStream = null;
        }
        el.scannerVideo.srcObject = null;
    }

    document.getElementById('scannerBtn').addEventListener('click', startScanner);
    document.getElementById('scanBillBarcodeBtn').addEventListener('click', startScanner);
    document.getElementById('closeScannerBtn').addEventListener('click', stopScanner);
    document.getElementById('scannerModal').addEventListener('hidden.bs.modal', stopScanner);
    document.getElementById('manualScanBtn').addEventListener('click', function () {
        const code = el.manualScanInput.value.trim();
        el.manualScanInput.value = '';
        modal('scannerModal').hide();
        stopScanner();
        scanCode(code);
    });
    el.manualScanInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.getElementById('manualScanBtn').click();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.altKey && event.key.toLowerCase() === 's') { event.preventDefault(); saveBill(false); }
        if (event.altKey && event.key.toLowerCase() === 'p') { event.preventDefault(); saveBill(true); }
        if (event.altKey && event.key.toLowerCase() === 'h') { event.preventDefault(); holdBill(); }
        if (event.altKey && event.key.toLowerCase() === 'n') { event.preventDefault(); resetBill(false); }
        if (event.key === 'F2') { event.preventDefault(); el.productSearch.focus(); }
    });

    setInterval(function () {
        el.dateTime.textContent = new Date().toLocaleString('en-IN', { hour12: true });
    }, 1000);

    window.addEventListener('beforeunload', function () {
        persistCurrentBill();
    });

    bootstrap().then(function () {
        restorePersistedBill();
        return loadBillHistory();
    });
})();
</script>
</body>
</html>
