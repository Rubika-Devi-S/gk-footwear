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
$typographyControls = [
    ['font_family', 'Font Family', 'select'],
    ['base_font_size', 'Base Font Size', 'number'],
    ['heading_font_size', 'Heading Font Size', 'number'],
    ['font_weight', 'Default Font Weight', 'select'],
    ['heading_font_weight', 'Heading Font Weight', 'select'],
    ['line_height', 'Line Height', 'number'],
    ['letter_spacing', 'Letter Spacing', 'number'],
    ['button_text_transform', 'Button Text Style', 'select'],
];

$fontFamilyOptions = [
    'Inter, "Segoe UI", Arial, sans-serif' => 'Inter / Segoe UI',
    '"Segoe UI", Arial, sans-serif' => 'Segoe UI',
    'Arial, Helvetica, sans-serif' => 'Arial',
    'Roboto, Arial, sans-serif' => 'Roboto',
    'Poppins, Arial, sans-serif' => 'Poppins',
    'Georgia, "Times New Roman", serif' => 'Georgia',
];

$fontWeightOptions = [
    '400' => 'Regular (400)',
    '500' => 'Medium (500)',
    '600' => 'Semi Bold (600)',
    '700' => 'Bold (700)',
    '800' => 'Extra Bold (800)',
    '900' => 'Black (900)',
];

$textTransformOptions = [
    'none' => 'Normal',
    'uppercase' => 'UPPERCASE',
    'capitalize' => 'Capitalize',
];

$typographyDefaults = [
    'font_family' => 'Inter, "Segoe UI", Arial, sans-serif',
    'base_font_size' => '14',
    'heading_font_size' => '24',
    'font_weight' => '500',
    'heading_font_weight' => '800',
    'line_height' => '1.5',
    'letter_spacing' => '0',
    'button_text_transform' => 'none',
];

$themePresets = [
    'classic_blue' => [
        'label' => 'Classic Blue',
        'colors' => [
            'body_bg' => '#EEF3FB', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#0F172A',
            'card_bg' => '#FFFFFF', 'card_header_bg' => '#F8FAFC', 'border_soft' => '#DBE4F0',
            'text_main' => '#0F172A', 'text_muted' => '#64748B',
            'sidebar_bg_1' => '#0F172A', 'sidebar_bg_2' => '#1E3A8A', 'sidebar_bg_3' => '#312E81',
            'sidebar_text' => '#E2E8F0', 'sidebar_active_bg_1' => '#2563EB', 'sidebar_active_bg_2' => '#7C3AED',
            'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => '#1E293B', 'sidebar_hover_text' => '#FFFFFF',
            'sidebar_submenu_bg' => '#172033', 'brand_1' => '#2563EB', 'brand_2' => '#7C3AED',
            'brand_text' => '#FFFFFF', 'table_header_bg' => '#F1F5F9', 'table_header_text' => '#0F172A',
            'table_row_hover' => '#EFF6FF', 'input_bg' => '#FFFFFF', 'input_border' => '#CBD5E1',
            'input_text' => '#0F172A', 'success_color' => '#16A34A', 'warning_color' => '#F59E0B',
            'danger_color' => '#DC2626', 'info_color' => '#0284C7',
        ],
        'typography' => ['font_family' => 'Inter, "Segoe UI", Arial, sans-serif', 'base_font_size' => '14', 'heading_font_size' => '24', 'font_weight' => '500', 'heading_font_weight' => '800', 'line_height' => '1.5', 'letter_spacing' => '0', 'button_text_transform' => 'none']
    ],
    'emerald_business' => [
        'label' => 'Emerald Business',
        'colors' => [
            'body_bg' => '#F0FDF4', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#052E16',
            'card_bg' => '#FFFFFF', 'card_header_bg' => '#ECFDF5', 'border_soft' => '#BBF7D0',
            'text_main' => '#14532D', 'text_muted' => '#4B7A5D',
            'sidebar_bg_1' => '#052E16', 'sidebar_bg_2' => '#14532D', 'sidebar_bg_3' => '#166534',
            'sidebar_text' => '#DCFCE7', 'sidebar_active_bg_1' => '#16A34A', 'sidebar_active_bg_2' => '#059669',
            'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => '#166534', 'sidebar_hover_text' => '#FFFFFF',
            'sidebar_submenu_bg' => '#0F3D24', 'brand_1' => '#16A34A', 'brand_2' => '#059669',
            'brand_text' => '#FFFFFF', 'table_header_bg' => '#DCFCE7', 'table_header_text' => '#14532D',
            'table_row_hover' => '#F0FDF4', 'input_bg' => '#FFFFFF', 'input_border' => '#86EFAC',
            'input_text' => '#14532D', 'success_color' => '#15803D', 'warning_color' => '#D97706',
            'danger_color' => '#B91C1C', 'info_color' => '#0F766E',
        ],
        'typography' => ['font_family' => 'Poppins, Arial, sans-serif', 'base_font_size' => '14', 'heading_font_size' => '24', 'font_weight' => '500', 'heading_font_weight' => '800', 'line_height' => '1.55', 'letter_spacing' => '0', 'button_text_transform' => 'none']
    ],
    'royal_purple' => [
        'label' => 'Royal Purple',
        'colors' => [
            'body_bg' => '#F5F3FF', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#2E1065',
            'card_bg' => '#FFFFFF', 'card_header_bg' => '#F5F3FF', 'border_soft' => '#DDD6FE',
            'text_main' => '#2E1065', 'text_muted' => '#6D5A8A',
            'sidebar_bg_1' => '#2E1065', 'sidebar_bg_2' => '#4C1D95', 'sidebar_bg_3' => '#581C87',
            'sidebar_text' => '#EDE9FE', 'sidebar_active_bg_1' => '#7C3AED', 'sidebar_active_bg_2' => '#C026D3',
            'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => '#4C1D95', 'sidebar_hover_text' => '#FFFFFF',
            'sidebar_submenu_bg' => '#3B176F', 'brand_1' => '#7C3AED', 'brand_2' => '#C026D3',
            'brand_text' => '#FFFFFF', 'table_header_bg' => '#EDE9FE', 'table_header_text' => '#3B0764',
            'table_row_hover' => '#FAF5FF', 'input_bg' => '#FFFFFF', 'input_border' => '#C4B5FD',
            'input_text' => '#2E1065', 'success_color' => '#16A34A', 'warning_color' => '#D97706',
            'danger_color' => '#DC2626', 'info_color' => '#7C3AED',
        ],
        'typography' => ['font_family' => 'Inter, "Segoe UI", Arial, sans-serif', 'base_font_size' => '14', 'heading_font_size' => '25', 'font_weight' => '500', 'heading_font_weight' => '900', 'line_height' => '1.5', 'letter_spacing' => '0.1', 'button_text_transform' => 'uppercase']
    ],
    'sunset_orange' => [
        'label' => 'Sunset Orange',
        'colors' => [
            'body_bg' => '#FFF7ED', 'topbar_bg' => '#FFFFFF', 'topbar_text' => '#431407',
            'card_bg' => '#FFFFFF', 'card_header_bg' => '#FFF7ED', 'border_soft' => '#FED7AA',
            'text_main' => '#431407', 'text_muted' => '#8A5A44',
            'sidebar_bg_1' => '#431407', 'sidebar_bg_2' => '#7C2D12', 'sidebar_bg_3' => '#9A3412',
            'sidebar_text' => '#FFEDD5', 'sidebar_active_bg_1' => '#EA580C', 'sidebar_active_bg_2' => '#F59E0B',
            'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => '#7C2D12', 'sidebar_hover_text' => '#FFFFFF',
            'sidebar_submenu_bg' => '#5A2110', 'brand_1' => '#EA580C', 'brand_2' => '#F59E0B',
            'brand_text' => '#FFFFFF', 'table_header_bg' => '#FFEDD5', 'table_header_text' => '#7C2D12',
            'table_row_hover' => '#FFF7ED', 'input_bg' => '#FFFFFF', 'input_border' => '#FDBA74',
            'input_text' => '#431407', 'success_color' => '#15803D', 'warning_color' => '#EA580C',
            'danger_color' => '#B91C1C', 'info_color' => '#0284C7',
        ],
        'typography' => ['font_family' => '"Segoe UI", Arial, sans-serif', 'base_font_size' => '14', 'heading_font_size' => '24', 'font_weight' => '500', 'heading_font_weight' => '800', 'line_height' => '1.5', 'letter_spacing' => '0', 'button_text_transform' => 'capitalize']
    ],
    'midnight_dark' => [
        'label' => 'Midnight Dark',
        'colors' => [
            'body_bg' => '#020617', 'topbar_bg' => '#0F172A', 'topbar_text' => '#F8FAFC',
            'card_bg' => '#111827', 'card_header_bg' => '#1E293B', 'border_soft' => '#334155',
            'text_main' => '#F8FAFC', 'text_muted' => '#94A3B8',
            'sidebar_bg_1' => '#020617', 'sidebar_bg_2' => '#0F172A', 'sidebar_bg_3' => '#111827',
            'sidebar_text' => '#CBD5E1', 'sidebar_active_bg_1' => '#2563EB', 'sidebar_active_bg_2' => '#06B6D4',
            'sidebar_active_text' => '#FFFFFF', 'sidebar_hover_bg' => '#1E293B', 'sidebar_hover_text' => '#FFFFFF',
            'sidebar_submenu_bg' => '#111827', 'brand_1' => '#2563EB', 'brand_2' => '#06B6D4',
            'brand_text' => '#FFFFFF', 'table_header_bg' => '#1E293B', 'table_header_text' => '#F8FAFC',
            'table_row_hover' => '#1E293B', 'input_bg' => '#0F172A', 'input_border' => '#475569',
            'input_text' => '#F8FAFC', 'success_color' => '#22C55E', 'warning_color' => '#F59E0B',
            'danger_color' => '#EF4444', 'info_color' => '#38BDF8',
        ],
        'typography' => ['font_family' => 'Roboto, Arial, sans-serif', 'base_font_size' => '14', 'heading_font_size' => '24', 'font_weight' => '400', 'heading_font_weight' => '700', 'line_height' => '1.55', 'letter_spacing' => '0', 'button_text_transform' => 'none']
    ],
];

global $defaults, $colors;
?>

<style>
.theme-preset-grid{display:grid;grid-template-columns:repeat(5,minmax(140px,1fr));gap:10px}
.theme-preset-card{border:1px solid var(--border-soft);background:var(--card-bg);border-radius:18px;padding:12px;cursor:pointer;transition:.18s ease;box-shadow:0 6px 18px rgba(15,23,42,.06)}
.theme-preset-card:hover,.theme-preset-card.active{transform:translateY(-2px);border-color:var(--brand-1);box-shadow:0 14px 28px rgba(15,23,42,.12)}
.theme-preset-swatches{display:flex;gap:5px;margin-bottom:8px}
.theme-preset-swatches span{width:24px;height:24px;border-radius:8px;border:1px solid rgba(15,23,42,.12)}
.theme-preset-name{font-size:12px;font-weight:900;color:var(--text-main)}
.typography-control-card{border:1px solid var(--border-soft);background:rgba(148,163,184,.06);border-radius:18px;padding:14px;height:100%}
.typography-control-card label{font-size:12px;font-weight:900;color:var(--text-main);margin-bottom:8px}
.typography-control-card .form-control,.typography-control-card .form-select{min-height:42px;border-radius:14px}
.preview-shell,.preview-shell *{font-family:var(--app-font-family,Inter,"Segoe UI",Arial,sans-serif)}
.preview-shell{font-size:var(--app-font-size,14px);line-height:var(--app-line-height,1.5);letter-spacing:var(--app-letter-spacing,0px)}
.preview-title{font-size:var(--app-heading-size,24px)!important;font-weight:var(--app-heading-weight,800)!important}
.preview-content,.preview-nav-item,.preview-topbar{font-weight:var(--app-font-weight,500)}
.preview-btn{text-transform:var(--app-button-transform,none)}
@media(max-width:1199px){.theme-preset-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575px){.theme-preset-grid{grid-template-columns:1fr}}

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
            <button type="button" id="savePreviewBtn" class="btn brand-gradient rounded-4 fw-bold btn-sm px-3">Save All Settings</button>
        </div>
    </div>
</div>

<section class="card-ui p-3 p-lg-4 mb-3">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
        <div>
            <h2 class="fw-bold fs-6 mb-1">Default Themes</h2>
            <p class="text-muted-custom small mb-0">Choose a ready-made theme, preview it, then save.</p>
        </div>
    </div>
    <div class="theme-preset-grid" id="themePresetGrid">
        <?php foreach ($themePresets as $presetKey => $preset): ?>
            <button type="button" class="theme-preset-card text-start" data-theme-preset="<?= e($presetKey) ?>">
                <div class="theme-preset-swatches">
                    <span style="background:<?= e($preset['colors']['brand_1']) ?>"></span>
                    <span style="background:<?= e($preset['colors']['brand_2']) ?>"></span>
                    <span style="background:<?= e($preset['colors']['body_bg']) ?>"></span>
                    <span style="background:<?= e($preset['colors']['sidebar_bg_2']) ?>"></span>
                </div>
                <div class="theme-preset-name"><?= e($preset['label']) ?></div>
            </button>
        <?php endforeach; ?>
    </div>
</section>

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

            <p class="color-section-title">Typography & Text Controls</p>
            <div class="row g-3 mb-2">
                <?php foreach ($typographyControls as [$name, $label, $type]): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="typography-control-card">
                            <label for="<?= e($name) ?>"><?= e($label) ?></label>

                            <?php if ($name === 'font_family'): ?>
                                <select class="form-select live-typography" name="<?= e($name) ?>" id="<?= e($name) ?>">
                                    <?php $current = $colors[$name] ?? $typographyDefaults[$name]; ?>
                                    <?php foreach ($fontFamilyOptions as $value => $optionLabel): ?>
                                        <option value="<?= e($value) ?>" <?= $current === $value ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($name === 'font_weight' || $name === 'heading_font_weight'): ?>
                                <select class="form-select live-typography" name="<?= e($name) ?>" id="<?= e($name) ?>">
                                    <?php $current = (string)($colors[$name] ?? $typographyDefaults[$name]); ?>
                                    <?php foreach ($fontWeightOptions as $value => $optionLabel): ?>
                                        <option value="<?= e($value) ?>" <?= $current === (string)$value ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php elseif ($name === 'button_text_transform'): ?>
                                <select class="form-select live-typography" name="<?= e($name) ?>" id="<?= e($name) ?>">
                                    <?php $current = $colors[$name] ?? $typographyDefaults[$name]; ?>
                                    <?php foreach ($textTransformOptions as $value => $optionLabel): ?>
                                        <option value="<?= e($value) ?>" <?= $current === $value ? 'selected' : '' ?>><?= e($optionLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <?php
                                $step = in_array($name, ['line_height', 'letter_spacing'], true) ? '0.1' : '1';
                                $min = $name === 'base_font_size' ? '10' : ($name === 'heading_font_size' ? '14' : ($name === 'line_height' ? '1' : '-2'));
                                $max = $name === 'base_font_size' ? '24' : ($name === 'heading_font_size' ? '48' : ($name === 'line_height' ? '2.5' : '5'));
                                ?>
                                <input type="number" class="form-control live-typography" name="<?= e($name) ?>" id="<?= e($name) ?>"
                                       value="<?= e($colors[$name] ?? $typographyDefaults[$name]) ?>"
                                       min="<?= e($min) ?>" max="<?= e($max) ?>" step="<?= e($step) ?>">
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 mt-3">
                <button type="button" id="resetTypographyBtn" class="btn btn-outline-secondary rounded-4 fw-bold px-3">
                    Reset Typography
                </button>
                <button type="button" id="saveTypographyBtn" class="btn brand-gradient rounded-4 fw-bold px-4">
                    Save Typography
                </button>
            </div>
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
    const typographyDefaults = <?= json_encode($typographyDefaults, JSON_UNESCAPED_SLASHES) ?>;
    const themePresets = <?= json_encode($themePresets, JSON_UNESCAPED_SLASHES) ?>;
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
    const typographyMap = {
        font_family: "--app-font-family",
        base_font_size: "--app-font-size",
        heading_font_size: "--app-heading-size",
        font_weight: "--app-font-weight",
        heading_font_weight: "--app-heading-weight",
        line_height: "--app-line-height",
        letter_spacing: "--app-letter-spacing",
        button_text_transform: "--app-button-transform"
    };

    function normalizeTypographyValue(name, value) {
        value = String(value ?? "").trim();
        if (name === "base_font_size" || name === "heading_font_size") return value + "px";
        if (name === "letter_spacing") return value + "px";
        return value;
    }

    function applyTypography(name, value) {
        const variable = typographyMap[name];
        if (!variable) return;
        root.style.setProperty(variable, normalizeTypographyValue(name, value));
    }

    function applyPreset(presetKey) {
        const preset = themePresets[presetKey];
        if (!preset) return;

        Object.entries(preset.colors || {}).forEach(([name, value]) => {
            applyColor(name, value);
            const color = document.querySelector(`#themeForm input[type="color"][name="${name}"]`);
            const text = document.querySelector(`[data-color-name="${name}"]`);
            if (color && isHex(value)) color.value = value;
            if (text) text.value = value;
        });

        Object.entries(preset.typography || {}).forEach(([name, value]) => {
            applyTypography(name, value);
            const input = document.querySelector(`#themeForm [name="${name}"]`);
            if (input) input.value = value;
        });

        document.querySelectorAll("[data-theme-preset]").forEach(btn => btn.classList.remove("active"));
        document.querySelector(`[data-theme-preset="${presetKey}"]`)?.classList.add("active");
        showThemeToast("success", "Theme Applied", (preset.label || "Theme") + " preview applied.");
    }

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

    document.querySelectorAll("[data-theme-preset]").forEach(button => {
        button.addEventListener("click", function () {
            applyPreset(this.dataset.themePreset);
        });
    });

    document.querySelectorAll(".live-typography").forEach(input => {
        input.addEventListener("input", function () {
            applyTypography(this.name, this.value);
        });
        input.addEventListener("change", function () {
            applyTypography(this.name, this.value);
        });
        applyTypography(input.name, input.value);
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
        Object.keys(typographyDefaults).forEach(name => {
            const value = typographyDefaults[name];
            applyTypography(name, value);
            const input = document.querySelector(`#themeForm [name="${name}"]`);
            if (input) input.value = value;
        });
        document.querySelectorAll("[data-theme-preset]").forEach(btn => btn.classList.remove("active"));
        showThemeToast("info", "Preview Reset", "Theme preview reset to default colors and typography.");
    });

    async function saveThemeForm(formData, successFallback) {
        try {
            const res = await fetch("api/theme-api.php", {
                method: "POST",
                body: formData
            });

            const data = await res.json();

            if (data.ok) {
                showThemeToast("success", "Saved", data.message || successFallback);
                return true;
            }

            showThemeToast("error", "Failed", data.message || "Unable to save theme settings.");
            return false;
        } catch (error) {
            showThemeToast("error", "Failed", "Unable to connect to server.");
            return false;
        }
    }

    document.getElementById("saveTypographyBtn")?.addEventListener("click", async function () {
        const form = document.getElementById("themeForm");
        const fd = new FormData();

        const csrfInput = form.querySelector('input[name="csrf_token"], input[name="_token"], input[type="hidden"]');
        if (csrfInput && csrfInput.name) {
            fd.append(csrfInput.name, csrfInput.value);
        }

        document.querySelectorAll("#themeForm .live-typography").forEach(input => {
            fd.append(input.name, input.value);
        });

        fd.append("save_scope", "typography");

        const button = this;
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = "Saving...";

        await saveThemeForm(fd, "Typography settings saved successfully.");

        button.disabled = false;
        button.textContent = originalText;
    });

    document.getElementById("resetTypographyBtn")?.addEventListener("click", function () {
        Object.keys(typographyDefaults).forEach(name => {
            const value = typographyDefaults[name];
            applyTypography(name, value);
            const input = document.querySelector(`#themeForm [name="${name}"]`);
            if (input) input.value = value;
        });

        showThemeToast("info", "Typography Reset", "Typography preview reset to default values.");
    });

    document.getElementById("savePreviewBtn").addEventListener("click", async function () {
        const form = document.getElementById("themeForm");
        const fd = new FormData(form);
        fd.append("save_scope", "all");

        const button = this;
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = "Saving...";

        await saveThemeForm(fd, "Theme colors and typography saved successfully.");

        button.disabled = false;
        button.textContent = originalText;
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
