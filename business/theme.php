<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'theme.php');

$pageTitle = 'Theme';
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - GK Footwear POS</title>
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

<?php
$controlGroups = [
    'Layout Colors' => [
        ['body_bg', 'Body Background'],
        ['topbar_bg', 'Topbar Background'],
        ['topbar_text', 'Topbar Text'],
        ['card_bg', 'Card Background'],
        ['card_header_bg', 'Card Header Background'],
        ['border_soft', 'Border Color'],
        ['text_main', 'Main Text Color'],
        ['text_muted', 'Muted Text Color'],
    ],
    'Sidebar Gradient & Colors' => [
        ['sidebar_bg_1', 'Gradient Start'],
        ['sidebar_bg_2', 'Gradient Middle'],
        ['sidebar_bg_3', 'Gradient End'],
        ['sidebar_text', 'Sidebar Text'],
        ['sidebar_active_bg_1', 'Active BG Start'],
        ['sidebar_active_bg_2', 'Active BG End'],
        ['sidebar_active_text', 'Active Text'],
        ['sidebar_hover_bg', 'Hover Background'],
        ['sidebar_hover_text', 'Hover Text'],
        ['sidebar_submenu_bg', 'Submenu Background'],
    ],
    'Brand Colors' => [
        ['brand_1', 'Primary Brand Color'],
        ['brand_2', 'Secondary Brand Color'],
        ['brand_text', 'Brand Button Text'],
    ],
    'Table Colors' => [
        ['table_header_bg', 'Table Header Background'],
        ['table_header_text', 'Table Header Text'],
        ['table_row_hover', 'Table Row Hover'],
    ],
    'Form Colors' => [
        ['input_bg', 'Input Background'],
        ['input_border', 'Input Border'],
        ['input_text', 'Input Text'],
    ],
    'Status Colors' => [
        ['success_color', 'Success Color'],
        ['warning_color', 'Warning Color'],
        ['danger_color', 'Danger Color'],
        ['info_color', 'Info Color'],
    ],
];
global $defaults, $colors;
?>

<style>
.color-section-title{font-size:12px;font-weight:900;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin:0 0 12px}
.color-control-card{border:1px solid var(--border-soft);background:rgba(148,163,184,.06);border-radius:18px;padding:14px;height:100%}
.color-control-card label{font-size:12px;font-weight:900;color:var(--text-main);margin-bottom:8px}
.color-input-row{display:flex;gap:8px;align-items:center}
.color-input-row input[type=color]{width:52px!important;min-width:52px;height:42px;padding:4px;border-radius:14px;border:1px solid var(--input-border);background:var(--input-bg)}
.color-input-row input[type=text]{height:42px;border-radius:14px;border:1px solid var(--input-border);background:var(--input-bg);color:var(--input-text);font-size:13px;font-weight:800;text-transform:uppercase}
.preview-shell{border:1px solid var(--border-soft);background:var(--body-bg);border-radius:20px;overflow:hidden;min-height:350px}
.preview-topbar{height:42px;background:var(--topbar-bg);color:var(--topbar-text);border-bottom:1px solid var(--border-soft);display:flex;align-items:center;gap:8px;padding:0 12px;font-size:11px;font-weight:900}
.preview-layout{display:grid;grid-template-columns:115px 1fr;min-height:308px}
.preview-sidebar{background:var(--sidebar-bg);padding:12px 9px}
.preview-logo{height:28px;width:28px;border-radius:10px;background-image:linear-gradient(135deg,var(--brand-1),var(--brand-2));margin-bottom:14px}
.preview-nav-item{height:28px;border-radius:10px;color:var(--sidebar-text);display:flex;align-items:center;padding:0 8px;font-size:10px;font-weight:800;margin-bottom:7px}
.preview-nav-item.active{color:var(--sidebar-active-text);background-image:linear-gradient(135deg,var(--sidebar-active-bg-1),var(--sidebar-active-bg-2))}
.preview-content{padding:12px;background:var(--body-bg)}
.preview-card-mini{background:var(--card-bg);border:1px solid var(--border-soft);border-radius:16px;padding:12px;margin-bottom:10px}
.preview-title{color:var(--text-main);font-size:13px;font-weight:900;margin:0 0 4px}
.preview-muted{color:var(--text-muted);font-size:10px;margin:0}
.preview-btn{height:30px;border-radius:12px;background-image:linear-gradient(135deg,var(--brand-1),var(--brand-2));color:var(--brand-text);display:inline-flex;align-items:center;padding:0 12px;font-size:11px;font-weight:800;margin-top:8px}
@media(max-width:1199px){.live-preview-card{position:static!important}}
</style>

<div class="page-head-card mb-3">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-lg-between gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1">Theme</h1>
            <p class="text-muted-custom mb-0 small">Update GK Footwear business panel colors. Preview live before saving.</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="resetPreviewBtn" class="btn btn-outline-secondary rounded-4 fw-bold btn-sm px-3">Reset Preview</button>
            <button type="button" id="savePreviewBtn" class="btn brand-gradient rounded-4 fw-bold btn-sm px-3">Save Colors</button>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8">
        <form id="themeForm" class="card-ui p-3 p-lg-4">
            <?= csrf_field(); ?>

            <?php foreach ($controlGroups as $groupTitle => $controls): ?>
                <p class="color-section-title"><?= e($groupTitle) ?></p>
                <div class="row g-3 mb-4">
                    <?php foreach ($controls as [$name, $label]): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="color-control-card">
                                <label><?= e($label) ?></label>
                                <div class="color-input-row">
                                    <input type="color" name="<?= e($name) ?>" value="<?= e($colors[$name] ?? $defaults[$name] ?? '#FFFFFF') ?>">
                                    <input type="text" class="form-control live-color-text" data-color-name="<?= e($name) ?>" value="<?= e($colors[$name] ?? $defaults[$name] ?? '#FFFFFF') ?>">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </form>
    </div>

    <div class="col-12 col-xl-4">
        <section class="card-ui p-3 p-lg-4 live-preview-card" style="position:sticky;top:84px;">
            <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                <div>
                    <h2 class="fw-bold fs-6 mb-1">Live Preview</h2>
                    <p class="text-muted-custom small mb-0">Preview updates instantly.</p>
                </div>
                <span class="badge text-bg-success rounded-pill">Live</span>
            </div>

            <div class="preview-shell">
                <div class="preview-topbar"><span>GK Footwear</span><span class="ms-auto">POS</span></div>
                <div class="preview-layout">
                    <div class="preview-sidebar">
                        <div class="preview-logo"></div>
                        <div class="preview-nav-item active">Dashboard</div>
                        <div class="preview-nav-item">Billing</div>
                        <div class="preview-nav-item">Stock</div>
                    </div>
                    <div class="preview-content">
                        <div class="preview-card-mini">
                            <p class="preview-title">Billing Card</p>
                            <p class="preview-muted">Bill of Supply, stock and cashier preview.</p>
                            <span class="preview-btn">Create Bill</span>
                        </div>
                        <div class="preview-card-mini">
                            <p class="preview-title">Table Preview</p>
                            <p class="preview-muted">Business records and mobile cards.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const root = document.documentElement;
    const defaults = <?= json_encode($defaults, JSON_UNESCAPED_SLASHES) ?>;
    const colorMap = {
        body_bg:"--body-bg", topbar_bg:"--topbar-bg", topbar_text:"--topbar-text", card_bg:"--card-bg",
        card_header_bg:"--card-header-bg", border_soft:"--border-soft", text_main:"--text-main", text_muted:"--text-muted",
        sidebar_bg_1:"--sidebar-bg-1", sidebar_bg_2:"--sidebar-bg-2", sidebar_bg_3:"--sidebar-bg-3", sidebar_text:"--sidebar-text",
        sidebar_active_bg_1:"--sidebar-active-bg-1", sidebar_active_bg_2:"--sidebar-active-bg-2", sidebar_active_text:"--sidebar-active-text",
        sidebar_hover_bg:"--sidebar-hover-bg", sidebar_hover_text:"--sidebar-hover-text", sidebar_submenu_bg:"--sidebar-submenu-bg",
        brand_1:"--brand-1", brand_2:"--brand-2", brand_text:"--brand-text",
        table_header_bg:"--table-header-bg", table_header_text:"--table-header-text", table_row_hover:"--table-row-hover",
        input_bg:"--input-bg", input_border:"--input-border", input_text:"--input-text",
        success_color:"--success-color", warning_color:"--warning-color", danger_color:"--danger-color", info_color:"--info-color"
    };

    function applyColor(name, value) {
        const variable = colorMap[name];
        if (!variable) return;
        root.style.setProperty(variable, value);
        if (["sidebar_bg_1","sidebar_bg_2","sidebar_bg_3"].includes(name)) {
            root.style.setProperty("--sidebar-bg", `linear-gradient(180deg, ${root.style.getPropertyValue("--sidebar-bg-1") || getComputedStyle(root).getPropertyValue("--sidebar-bg-1")}, ${root.style.getPropertyValue("--sidebar-bg-2") || getComputedStyle(root).getPropertyValue("--sidebar-bg-2")}, ${root.style.getPropertyValue("--sidebar-bg-3") || getComputedStyle(root).getPropertyValue("--sidebar-bg-3")})`);
        }
    }

    function isHex(value) {
        return /^#[0-9A-Fa-f]{6}$/.test(String(value || "").trim());
    }

    document.querySelectorAll('#themeForm input[type="color"]').forEach(input => {
        input.addEventListener("input", function () {
            const name = this.name;
            const value = this.value.toUpperCase();
            applyColor(name, value);
            const text = document.querySelector(`[data-color-name="${name}"]`);
            if (text) text.value = value;
        });
    });

    document.querySelectorAll(".live-color-text").forEach(input => {
        input.addEventListener("input", function () {
            let value = this.value.trim().toUpperCase();
            if (value && !value.startsWith("#")) value = "#" + value;
            if (isHex(value)) {
                const name = this.dataset.colorName;
                applyColor(name, value);
                const color = document.querySelector(`#themeForm input[type="color"][name="${name}"]`);
                if (color) color.value = value;
            }
        });
    });

    document.getElementById("resetPreviewBtn").addEventListener("click", function () {
        Object.keys(defaults).forEach(name => {
            const value = defaults[name];
            applyColor(name, value);
            const color = document.querySelector(`#themeForm input[type="color"][name="${name}"]`);
            const text = document.querySelector(`[data-color-name="${name}"]`);
            if (color && isHex(value)) color.value = value;
            if (text) text.value = value;
        });
        showThemeToast("info", "Preview Reset", "Theme preview reset to default colors.");
    });

    document.getElementById("savePreviewBtn").addEventListener("click", async function () {
        const form = document.getElementById("themeForm");
        const fd = new FormData(form);

        try {
            const res = await fetch("api/theme-api.php", { method: "POST", body: fd });
            const data = await res.json();

            if (data.ok) {
                showThemeToast("success", "Saved", data.message || "Theme colors saved successfully.");
            } else {
                showThemeToast("error", "Failed", data.message || "Unable to save colors.");
            }
        } catch (error) {
            showThemeToast("error", "Failed", "Unable to connect to server.");
        }
    });
});
</script>

            <?php include __DIR__ . '/includes/footer.php'; ?>
        </section>
    </main>
</div>
<?php include __DIR__ . '/includes/script.php'; ?>
</body>
</html>
