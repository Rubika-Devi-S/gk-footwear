<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/permissions.php';
require_once __DIR__ . '/includes/csrf.php';

require_business_login();
require_page_access($conn, 'theme.php');

$pageTitle = 'Theme';

$fontOptions = [
    'Inter, Arial, sans-serif' => 'Inter (Default)',
    'Poppins, Arial, sans-serif' => 'Poppins',
    'Roboto, Arial, sans-serif' => 'Roboto',
    '"Open Sans", Arial, sans-serif' => 'Open Sans',
    'Nunito, Arial, sans-serif' => 'Nunito',
];


$themePresets = [
    'classic_blue' => [
        'label' => 'Classic Blue',
        'description' => 'Clean professional ERP theme',
        'settings' => [
            'body_bg'=>'#F4F7FC','topbar_bg'=>'#FFFFFF','topbar_text'=>'#0F172A',
            'card_bg'=>'#FFFFFF','card_header_bg'=>'#F8FAFC','border_soft'=>'#DCE5F1',
            'text_main'=>'#0F172A','text_muted'=>'#64748B',
            'sidebar_bg_1'=>'#0F172A','sidebar_bg_2'=>'#1E3A8A','sidebar_bg_3'=>'#312E81',
            'sidebar_text'=>'#E2E8F0','sidebar_active_bg_1'=>'#2563EB','sidebar_active_bg_2'=>'#7C3AED',
            'sidebar_active_text'=>'#FFFFFF','sidebar_hover_bg'=>'rgba(255,255,255,.10)',
            'sidebar_hover_text'=>'#FFFFFF','sidebar_submenu_bg'=>'rgba(255,255,255,.06)',
            'brand_1'=>'#2563EB','brand_2'=>'#7C3AED','brand_text'=>'#FFFFFF',
            'table_header_bg'=>'#EFF6FF','table_header_text'=>'#1E3A8A','table_row_hover'=>'#F8FAFF',
            'success_color'=>'#16A34A','warning_color'=>'#F59E0B','danger_color'=>'#DC2626','info_color'=>'#0284C7',
            'font_family'=>'Inter, Arial, sans-serif','base_font_size'=>'14','heading_font_size'=>'24',
            'font_weight'=>'500','heading_font_weight'=>'800','line_height'=>'1.5','letter_spacing'=>'0',
            'button_text_transform'=>'none','sidebar_style'=>'gradient','sidebar_width'=>'268',
            'navbar_style'=>'solid','navbar_height'=>'64','card_style'=>'elevated','card_radius'=>'18',
            'button_style'=>'rounded','button_radius'=>'12','table_style'=>'clean','table_density'=>'comfortable',
            'layout_width'=>'fluid','content_density'=>'comfortable','page_spacing'=>'16','theme_mode'=>'light'
        ],
    ],
    'emerald_business' => [
        'label' => 'Emerald Business',
        'description' => 'Fresh accounting and retail style',
        'settings' => [
            'body_bg'=>'#F0FDF4','topbar_bg'=>'#FFFFFF','topbar_text'=>'#052E16',
            'card_bg'=>'#FFFFFF','card_header_bg'=>'#ECFDF5','border_soft'=>'#BBF7D0',
            'text_main'=>'#14532D','text_muted'=>'#4B7A5D',
            'sidebar_bg_1'=>'#052E16','sidebar_bg_2'=>'#14532D','sidebar_bg_3'=>'#166534',
            'sidebar_text'=>'#DCFCE7','sidebar_active_bg_1'=>'#16A34A','sidebar_active_bg_2'=>'#059669',
            'sidebar_active_text'=>'#FFFFFF','sidebar_hover_bg'=>'rgba(255,255,255,.10)',
            'sidebar_hover_text'=>'#FFFFFF','sidebar_submenu_bg'=>'rgba(255,255,255,.06)',
            'brand_1'=>'#16A34A','brand_2'=>'#059669','brand_text'=>'#FFFFFF',
            'table_header_bg'=>'#DCFCE7','table_header_text'=>'#14532D','table_row_hover'=>'#F0FDF4',
            'success_color'=>'#15803D','warning_color'=>'#D97706','danger_color'=>'#B91C1C','info_color'=>'#0F766E',
            'font_family'=>'Poppins, Arial, sans-serif','base_font_size'=>'14','heading_font_size'=>'24',
            'font_weight'=>'500','heading_font_weight'=>'800','line_height'=>'1.55','letter_spacing'=>'0',
            'button_text_transform'=>'none','sidebar_style'=>'gradient','sidebar_width'=>'268',
            'navbar_style'=>'glass','navbar_height'=>'64','card_style'=>'soft','card_radius'=>'20',
            'button_style'=>'rounded','button_radius'=>'12','table_style'=>'clean','table_density'=>'comfortable',
            'layout_width'=>'fluid','content_density'=>'comfortable','page_spacing'=>'16','theme_mode'=>'light'
        ],
    ],
    'royal_purple' => [
        'label' => 'Royal Purple',
        'description' => 'Premium modern POS appearance',
        'settings' => [
            'body_bg'=>'#F5F3FF','topbar_bg'=>'#FFFFFF','topbar_text'=>'#2E1065',
            'card_bg'=>'#FFFFFF','card_header_bg'=>'#F5F3FF','border_soft'=>'#DDD6FE',
            'text_main'=>'#2E1065','text_muted'=>'#6D5A8A',
            'sidebar_bg_1'=>'#2E1065','sidebar_bg_2'=>'#4C1D95','sidebar_bg_3'=>'#581C87',
            'sidebar_text'=>'#EDE9FE','sidebar_active_bg_1'=>'#7C3AED','sidebar_active_bg_2'=>'#C026D3',
            'sidebar_active_text'=>'#FFFFFF','sidebar_hover_bg'=>'rgba(255,255,255,.10)',
            'sidebar_hover_text'=>'#FFFFFF','sidebar_submenu_bg'=>'rgba(255,255,255,.06)',
            'brand_1'=>'#7C3AED','brand_2'=>'#C026D3','brand_text'=>'#FFFFFF',
            'table_header_bg'=>'#EDE9FE','table_header_text'=>'#3B0764','table_row_hover'=>'#FAF5FF',
            'success_color'=>'#16A34A','warning_color'=>'#D97706','danger_color'=>'#DC2626','info_color'=>'#7C3AED',
            'font_family'=>'Nunito, Arial, sans-serif','base_font_size'=>'14','heading_font_size'=>'25',
            'font_weight'=>'600','heading_font_weight'=>'900','line_height'=>'1.5','letter_spacing'=>'0.1',
            'button_text_transform'=>'none','sidebar_style'=>'gradient','sidebar_width'=>'272',
            'navbar_style'=>'floating','navbar_height'=>'68','card_style'=>'elevated','card_radius'=>'22',
            'button_style'=>'pill','button_radius'=>'24','table_style'=>'striped','table_density'=>'comfortable',
            'layout_width'=>'fluid','content_density'=>'comfortable','page_spacing'=>'18','theme_mode'=>'light'
        ],
    ],
    'sunset_orange' => [
        'label' => 'Sunset Orange',
        'description' => 'Warm retail and invoice theme',
        'settings' => [
            'body_bg'=>'#FFF7ED','topbar_bg'=>'#FFFFFF','topbar_text'=>'#431407',
            'card_bg'=>'#FFFFFF','card_header_bg'=>'#FFF7ED','border_soft'=>'#FED7AA',
            'text_main'=>'#431407','text_muted'=>'#8A5A44',
            'sidebar_bg_1'=>'#431407','sidebar_bg_2'=>'#7C2D12','sidebar_bg_3'=>'#9A3412',
            'sidebar_text'=>'#FFEDD5','sidebar_active_bg_1'=>'#EA580C','sidebar_active_bg_2'=>'#F59E0B',
            'sidebar_active_text'=>'#FFFFFF','sidebar_hover_bg'=>'rgba(255,255,255,.10)',
            'sidebar_hover_text'=>'#FFFFFF','sidebar_submenu_bg'=>'rgba(255,255,255,.06)',
            'brand_1'=>'#EA580C','brand_2'=>'#F59E0B','brand_text'=>'#FFFFFF',
            'table_header_bg'=>'#FFEDD5','table_header_text'=>'#7C2D12','table_row_hover'=>'#FFF7ED',
            'success_color'=>'#15803D','warning_color'=>'#EA580C','danger_color'=>'#B91C1C','info_color'=>'#0284C7',
            'font_family'=>'"Open Sans", Arial, sans-serif','base_font_size'=>'14','heading_font_size'=>'24',
            'font_weight'=>'500','heading_font_weight'=>'800','line_height'=>'1.5','letter_spacing'=>'0',
            'button_text_transform'=>'capitalize','sidebar_style'=>'solid','sidebar_width'=>'264',
            'navbar_style'=>'bordered','navbar_height'=>'64','card_style'=>'bordered','card_radius'=>'16',
            'button_style'=>'rounded','button_radius'=>'10','table_style'=>'bordered','table_density'=>'comfortable',
            'layout_width'=>'fluid','content_density'=>'comfortable','page_spacing'=>'16','theme_mode'=>'light'
        ],
    ],
    'midnight_dark' => [
        'label' => 'Midnight Dark',
        'description' => 'Elegant dark billing workspace',
        'settings' => [
            'body_bg'=>'#020617','topbar_bg'=>'#0F172A','topbar_text'=>'#F8FAFC',
            'card_bg'=>'#111827','card_header_bg'=>'#1E293B','border_soft'=>'#334155',
            'text_main'=>'#F8FAFC','text_muted'=>'#94A3B8',
            'sidebar_bg_1'=>'#020617','sidebar_bg_2'=>'#0F172A','sidebar_bg_3'=>'#111827',
            'sidebar_text'=>'#CBD5E1','sidebar_active_bg_1'=>'#2563EB','sidebar_active_bg_2'=>'#06B6D4',
            'sidebar_active_text'=>'#FFFFFF','sidebar_hover_bg'=>'rgba(255,255,255,.10)',
            'sidebar_hover_text'=>'#FFFFFF','sidebar_submenu_bg'=>'rgba(255,255,255,.06)',
            'brand_1'=>'#2563EB','brand_2'=>'#06B6D4','brand_text'=>'#FFFFFF',
            'table_header_bg'=>'#1E293B','table_header_text'=>'#F8FAFC','table_row_hover'=>'#1E293B',
            'success_color'=>'#22C55E','warning_color'=>'#F59E0B','danger_color'=>'#EF4444','info_color'=>'#38BDF8',
            'font_family'=>'Roboto, Arial, sans-serif','base_font_size'=>'14','heading_font_size'=>'24',
            'font_weight'=>'400','heading_font_weight'=>'700','line_height'=>'1.55','letter_spacing'=>'0',
            'button_text_transform'=>'none','sidebar_style'=>'gradient','sidebar_width'=>'268',
            'navbar_style'=>'glass','navbar_height'=>'64','card_style'=>'flat','card_radius'=>'18',
            'button_style'=>'rounded','button_radius'=>'12','table_style'=>'clean','table_density'=>'comfortable',
            'layout_width'=>'fluid','content_density'=>'comfortable','page_spacing'=>'16','theme_mode'=>'dark'
        ],
    ],
];

$sections = [
    'Theme Color' => [
        ['body_bg','Page Background','color'], ['topbar_bg','Navbar Background','color'],
        ['topbar_text','Navbar Text','color'], ['card_bg','Card Background','color'],
        ['card_header_bg','Card Header','color'], ['border_soft','Border Color','color'],
        ['text_main','Main Text','color'], ['text_muted','Muted Text','color'],
        ['brand_1','Primary Brand','color'], ['brand_2','Secondary Brand','color'],
        ['brand_text','Button Text','color'], ['success_color','Success','color'],
        ['warning_color','Warning','color'], ['danger_color','Danger','color'], ['info_color','Info','color'],
    ],
    'Sidebar Style' => [
        ['sidebar_bg_1','Gradient Start','color'], ['sidebar_bg_2','Gradient Middle','color'],
        ['sidebar_bg_3','Gradient End','color'], ['sidebar_text','Sidebar Text','color'],
        ['sidebar_active_bg_1','Active Start','color'], ['sidebar_active_bg_2','Active End','color'],
        ['sidebar_active_text','Active Text','color'], ['sidebar_hover_bg','Hover Background','text'],
        ['sidebar_hover_text','Hover Text','color'], ['sidebar_submenu_bg','Submenu Background','text'],
        ['sidebar_style','Sidebar Style','select',['gradient'=>'Gradient','solid'=>'Solid','soft'=>'Soft']],
        ['sidebar_width','Sidebar Width','number',220,340,1],
    ],
    'Navbar Style' => [
        ['navbar_style','Navbar Style','select',['solid'=>'Solid','glass'=>'Glass','bordered'=>'Bordered','floating'=>'Floating']],
        ['navbar_height','Navbar Height','number',56,92,1],
    ],
    'Card Style' => [
        ['card_style','Card Style','select',['elevated'=>'Elevated','flat'=>'Flat','bordered'=>'Bordered','soft'=>'Soft']],
        ['card_radius','Card Radius','number',0,32,1],
    ],
    'Button Style' => [
        ['button_style','Button Style','select',['rounded'=>'Rounded','pill'=>'Pill','square'=>'Square','soft'=>'Soft']],
        ['button_radius','Button Radius','number',0,32,1],
        ['button_text_transform','Button Text','select',['none'=>'Normal','uppercase'=>'UPPERCASE','capitalize'=>'Capitalize']],
    ],
    'Table Style' => [
        ['table_header_bg','Header Background','color'], ['table_header_text','Header Text','color'],
        ['table_row_hover','Row Hover','color'],
        ['table_style','Table Style','select',['clean'=>'Clean','striped'=>'Striped','bordered'=>'Bordered']],
        ['table_density','Table Density','select',['compact'=>'Compact','comfortable'=>'Comfortable','spacious'=>'Spacious']],
    ],
    'Layout Settings' => [
        ['layout_width','Layout Width','select',['fluid'=>'Fluid','boxed'=>'Boxed']],
        ['content_density','Content Density','select',['compact'=>'Compact','comfortable'=>'Comfortable','spacious'=>'Spacious']],
        ['page_spacing','Page Spacing','number',8,40,1],
        ['theme_mode','Light / Dark Mode','select',['light'=>'Light','dark'=>'Dark','system'=>'System']],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> - GK Footwear POS</title>
<?php include __DIR__ . '/includes/links.php'; ?>
<?php
// theme-colors.php is loaded by links.php, so $colors and $defaults are now available.
$themeDefaults = (isset($defaults) && is_array($defaults)) ? $defaults : [];
$themeColors = (isset($colors) && is_array($colors)) ? $colors : $themeDefaults;
$currentFont = (string)($themeColors['font_family'] ?? $themeDefaults['font_family'] ?? 'Inter, Arial, sans-serif');
$isCustomFont = !array_key_exists($currentFont, $fontOptions);
?>
<style>

.theme-preset-section{background:var(--card-bg);border:1px solid var(--border-soft);border-radius:var(--card-radius);box-shadow:var(--shadow-card);padding:18px;margin-bottom:18px}
.theme-preset-grid{display:grid;grid-template-columns:repeat(5,minmax(145px,1fr));gap:12px}
.theme-preset-card{appearance:none;text-align:left;background:var(--card-bg);border:1px solid var(--border-soft);border-radius:17px;padding:13px;cursor:pointer;color:var(--text-main);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.theme-preset-card:hover,.theme-preset-card.active{transform:translateY(-3px);border-color:var(--brand-1);box-shadow:0 14px 30px rgba(15,23,42,.13)}
.theme-preset-swatches{display:flex;gap:6px;margin-bottom:10px}
.theme-preset-swatches span{width:27px;height:27px;border-radius:9px;border:1px solid rgba(148,163,184,.28)}
.theme-preset-name{font-size:13px;font-weight:900;margin-bottom:3px}
.theme-preset-description{font-size:10px;line-height:1.35;color:var(--text-muted)}
.theme-preset-status{display:none;margin-top:8px;font-size:10px;font-weight:900;color:var(--brand-1)}
.theme-preset-card.active .theme-preset-status{display:block}

.theme-shell{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:18px}
.theme-toolbar,.theme-section,.preview-panel{background:var(--card-bg);border:1px solid var(--border-soft);border-radius:var(--card-radius);box-shadow:var(--shadow-card)}
.theme-toolbar{padding:18px}.theme-section{padding:18px;margin-bottom:16px}
.theme-title{font-size:22px;font-weight:900;margin:0}.theme-sub{color:var(--text-muted);margin:4px 0 0}
.section-title{font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.08em;margin:0 0 14px}
.control-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.control-card{border:1px solid var(--border-soft);border-radius:16px;padding:12px;background:color-mix(in srgb,var(--card-bg) 94%,var(--brand-1) 6%)}
.control-card label{display:block;font-size:11px;font-weight:800;color:var(--text-muted);margin-bottom:7px}
.color-row{display:grid;grid-template-columns:48px 1fr;gap:8px}.color-row input[type=color]{width:48px;height:42px;padding:3px;border-radius:12px;border:1px solid var(--input-border)}
.preview-panel{position:sticky;top:86px;padding:16px}.preview-window{overflow:hidden;border:1px solid var(--border-soft);border-radius:18px;background:var(--body-bg)}
.preview-top{height:50px;background:var(--topbar-bg);color:var(--topbar-text);display:flex;align-items:center;padding:0 14px;border-bottom:1px solid var(--border-soft)}
.preview-body{display:grid;grid-template-columns:110px 1fr;min-height:330px}.preview-side{background:var(--sidebar-bg);padding:12px}
.preview-nav{padding:8px;border-radius:10px;color:var(--sidebar-text);font-size:11px;margin-bottom:7px}.preview-nav.active{background:linear-gradient(135deg,var(--sidebar-active-bg-1),var(--sidebar-active-bg-2));color:var(--sidebar-active-text)}
.preview-content{padding:14px}.preview-card{background:var(--card-bg);border:1px solid var(--border-soft);border-radius:var(--card-radius);padding:14px;box-shadow:var(--shadow-card);margin-bottom:12px}
.preview-btn{display:inline-flex;padding:8px 13px;border-radius:var(--button-radius);background:linear-gradient(135deg,var(--brand-1),var(--brand-2));color:var(--brand-text);font-weight:800;text-transform:var(--app-button-transform)}
.font-custom-wrap{display:none;margin-top:8px}.font-custom-wrap.show{display:block}
@media(max-width:1199px){.theme-preset-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.theme-shell{grid-template-columns:1fr}.preview-panel{position:static}.control-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575px){.theme-preset-grid,.control-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div id="mobileOverlay" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50" style="z-index:1035;"></div>
<?php include __DIR__ . '/includes/page-message.php'; ?>
<div class="min-vh-100 d-flex">
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<main id="main">
<?php include __DIR__ . '/includes/nav.php'; ?>
<section class="page-section">
<div class="theme-toolbar mb-3 d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
<div><h1 class="theme-title">Professional Theme Settings</h1><p class="theme-sub">Configure colors, typography and component styles for the entire billing application.</p></div>
<div class="d-flex gap-2"><button type="button" id="resetBtn" class="btn btn-outline-secondary">Reset to Default</button><button type="button" id="saveBtn" class="btn brand-gradient"><span class="spinner-border spinner-border-sm me-2 save-spinner" aria-hidden="true"></span><span class="save-label">Save All Settings</span></button></div>
</div>


<section class="theme-preset-section">
<div class="d-flex flex-column flex-lg-row justify-content-between gap-2 mb-3">
<div>
<h2 class="section-title mb-1">Default Themes</h2>
<p class="theme-sub mb-0">Select one of the five professional themes, preview it instantly, and click Save All Settings.</p>
</div>
</div>
<div class="theme-preset-grid" id="themePresetGrid">
<?php foreach ($themePresets as $presetKey => $preset): ?>
<?php $presetSettings = $preset['settings']; ?>
<button type="button" class="theme-preset-card" data-theme-preset="<?= e($presetKey) ?>">
<div class="theme-preset-swatches">
<span style="background:<?= e($presetSettings['brand_1']) ?>"></span>
<span style="background:<?= e($presetSettings['brand_2']) ?>"></span>
<span style="background:<?= e($presetSettings['body_bg']) ?>"></span>
<span style="background:<?= e($presetSettings['sidebar_bg_2']) ?>"></span>
</div>
<div class="theme-preset-name"><?= e($preset['label']) ?></div>
<div class="theme-preset-description"><?= e($preset['description']) ?></div>
<div class="theme-preset-status">PREVIEW APPLIED</div>
</button>
<?php endforeach; ?>
</div>
</section>

<div class="theme-shell">
<div>
<form id="themeForm">
<?= csrf_field(); ?>
<section class="theme-section">
<h2 class="section-title">Typography & Text Controls</h2>
<div class="control-grid">
<div class="control-card">
<label>Font Family</label>
<select class="form-select js-theme" id="fontFamilyChoice">
<?php foreach($fontOptions as $value=>$label): ?><option value="<?=e($value)?>" <?=(!$isCustomFont && $currentFont===$value)?'selected':''?>><?=e($label)?></option><?php endforeach; ?>
<option value="__custom__" <?=$isCustomFont?'selected':''?>>Custom Font Family</option>
</select>
<div class="font-custom-wrap <?=$isCustomFont?'show':''?>" id="customFontWrap"><input type="text" class="form-control mt-2" id="customFontFamily" placeholder='Example: "Aptos", Arial, sans-serif' value="<?=$isCustomFont?e($currentFont):''?>"></div>
<input type="hidden" name="font_family" id="font_family" value="<?=e($currentFont)?>">
</div>
<?php
$typography = [
 ['base_font_size','Base Font Size',10,24,1],['heading_font_size','Heading Font Size',14,48,1],
 ['font_weight','Default Font Weight',400,900,100],['heading_font_weight','Heading Weight',400,900,100],
 ['line_height','Line Height',1,2.5,.1],['letter_spacing','Letter Spacing',-2,5,.1]
];
foreach($typography as [$n,$l,$min,$max,$step]): ?>
<div class="control-card"><label><?=$l?></label><input type="number" class="form-control js-theme" name="<?=$n?>" value="<?=e($themeColors[$n] ?? $themeDefaults[$n] ?? '')?>" min="<?=$min?>" max="<?=$max?>" step="<?=$step?>"></div>
<?php endforeach; ?>
</div>
</section>

<?php foreach($sections as $title=>$controls): ?>
<section class="theme-section"><h2 class="section-title"><?=e($title)?></h2><div class="control-grid">
<?php foreach($controls as $c): $name=$c[0];$label=$c[1];$type=$c[2];$value=$themeColors[$name] ?? $themeDefaults[$name] ?? ''; ?>
<div class="control-card"><label><?=e($label)?></label>
<?php if ($type === 'color'): ?>
<div class="color-row">
    <input type="color" class="js-color" name="<?= e($name) ?>" value="<?= e($value) ?>">
    <input type="text" class="form-control js-color-text" data-name="<?= e($name) ?>" value="<?= e($value) ?>">
</div>
<?php elseif ($type === 'select'): ?>
<select class="form-select js-theme" name="<?= e($name) ?>">
    <?php foreach (($c[3] ?? []) as $v => $t): ?>
        <option value="<?= e($v) ?>" <?= ((string)$value === (string)$v) ? 'selected' : '' ?>><?= e($t) ?></option>
    <?php endforeach; ?>
</select>
<?php elseif ($type === 'text'): ?>
<input type="text" class="form-control js-theme" name="<?= e($name) ?>" value="<?= e($value) ?>">
<?php else: ?>
<?php
    $minValue = $c[3] ?? 0;
    $maxValue = $c[4] ?? 999;
    $stepValue = $c[5] ?? 1;
?>
<input type="number" class="form-control js-theme" name="<?= e($name) ?>" value="<?= e($value) ?>"
       min="<?= e((string)$minValue) ?>" max="<?= e((string)$maxValue) ?>" step="<?= e((string)$stepValue) ?>">
<?php endif; ?></div>
<?php endforeach; ?>
</div></section>
<?php endforeach; ?>
</form>
</div>

<aside class="preview-panel"><h2 class="section-title">Live Preview</h2>
<div class="preview-window">
<div class="preview-top"><strong>GK Footwear POS</strong><span class="ms-auto">Admin</span></div>
<div class="preview-body"><div class="preview-side"><div class="preview-nav active">Dashboard</div><div class="preview-nav">Billing</div><div class="preview-nav">Stock</div><div class="preview-nav">Reports</div></div>
<div class="preview-content"><div class="preview-card"><h3 style="margin:0 0 5px">Billing Dashboard</h3><p style="color:var(--text-muted)">Live preview of your application theme.</p><span class="preview-btn">Create Bill</span></div>
<div class="preview-card"><table class="table mb-0"><thead><tr><th>Item</th><th>Qty</th></tr></thead><tbody><tr><td>Footwear</td><td>12</td></tr></tbody></table></div></div></div>
</div></aside>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
</section></main></div>
<?php include __DIR__ . '/includes/script.php'; ?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const root=document.documentElement, form=document.getElementById('themeForm');
 const themePresets=<?= json_encode($themePresets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
 const map={body_bg:'--body-bg',topbar_bg:'--topbar-bg',topbar_text:'--topbar-text',card_bg:'--card-bg',card_header_bg:'--card-header-bg',border_soft:'--border-soft',text_main:'--text-main',text_muted:'--text-muted',sidebar_bg_1:'--sidebar-bg-1',sidebar_bg_2:'--sidebar-bg-2',sidebar_bg_3:'--sidebar-bg-3',sidebar_text:'--sidebar-text',sidebar_active_bg_1:'--sidebar-active-bg-1',sidebar_active_bg_2:'--sidebar-active-bg-2',sidebar_active_text:'--sidebar-active-text',sidebar_hover_bg:'--sidebar-hover-bg',sidebar_hover_text:'--sidebar-hover-text',sidebar_submenu_bg:'--sidebar-submenu-bg',brand_1:'--brand-1',brand_2:'--brand-2',brand_text:'--brand-text',table_header_bg:'--table-header-bg',table_header_text:'--table-header-text',table_row_hover:'--table-row-hover',success_color:'--success-color',warning_color:'--warning-color',danger_color:'--danger-color',info_color:'--info-color',font_family:'--app-font-family',base_font_size:'--app-font-size',heading_font_size:'--app-heading-size',font_weight:'--app-font-weight',heading_font_weight:'--app-heading-weight',line_height:'--app-line-height',letter_spacing:'--app-letter-spacing',button_text_transform:'--app-button-transform',card_radius:'--card-radius',button_radius:'--button-radius',sidebar_width:'--sidebar-width',navbar_height:'--navbar-height',page_spacing:'--page-spacing'};
 const unit=n=>['base_font_size','heading_font_size','letter_spacing','card_radius','button_radius','sidebar_width','navbar_height','page_spacing'].includes(n)?'px':'';
 function apply(n,v){if(map[n])root.style.setProperty(map[n],v+unit(n));if(n==='sidebar_bg_1'||n==='sidebar_bg_2'||n==='sidebar_bg_3')root.style.setProperty('--sidebar-bg','linear-gradient(180deg,var(--sidebar-bg-1),var(--sidebar-bg-2),var(--sidebar-bg-3))');if(['theme_mode','sidebar_style','navbar_style','card_style','button_style','table_style','table_density','layout_width','content_density'].includes(n))root.dataset[n.replaceAll('_','-')]=v}
 const choice=document.getElementById('fontFamilyChoice'),custom=document.getElementById('customFontFamily'),hidden=document.getElementById('font_family'),wrap=document.getElementById('customFontWrap');

 function setThemeControl(name,value){
  value=String(value ?? '');
  if(name==='font_family'){
   const available=[...choice.options].some(o=>o.value===value);
   choice.value=available?value:'__custom__';
   if(!available)custom.value=value;
   hidden.value=value;
   wrap.classList.toggle('show',!available);
   apply(name,value);
   return;
  }
  const control=form.querySelector(`[name="${name}"]`);
  if(control){
   control.value=value;
   if(control.classList.contains('js-color')){
    const textInput=form.querySelector(`[data-name="${name}"]`);
    if(textInput)textInput.value=value;
   }
  }
  apply(name,value);
 }
 function applyPreset(presetKey){
  const preset=themePresets[presetKey];
  if(!preset||!preset.settings)return;
  Object.entries(preset.settings).forEach(([name,value])=>setThemeControl(name,value));
  document.querySelectorAll('[data-theme-preset]').forEach(b=>b.classList.toggle('active',b.dataset.themePreset===presetKey));
  if(window.showThemeToast)showThemeToast('success','Theme Applied',(preset.label||'Theme')+' preview applied. Click Save All Settings to keep it.');
 }
 document.querySelectorAll('[data-theme-preset]').forEach(button=>button.addEventListener('click',()=>applyPreset(button.dataset.themePreset)));

 document.querySelectorAll('.js-color').forEach(i=>i.addEventListener('input',()=>{apply(i.name,i.value);document.querySelector(`[data-name="${i.name}"]`).value=i.value}));
 document.querySelectorAll('.js-color-text').forEach(i=>i.addEventListener('input',()=>{if(/^#[0-9a-f]{6}$/i.test(i.value)){document.querySelector(`[name="${i.dataset.name}"]`).value=i.value;apply(i.dataset.name,i.value)}}));
 document.querySelectorAll('.js-theme').forEach(i=>i.addEventListener('input',()=>apply(i.name,i.value)));

 function fontChanged(){const isCustom=choice.value==='__custom__';wrap.classList.toggle('show',isCustom);hidden.value=isCustom?custom.value:choice.value;apply('font_family',hidden.value)}
 choice.addEventListener('change',fontChanged);custom.addEventListener('input',fontChanged);


 function formSnapshot(){
  fontChanged();
  const values={};
  new FormData(form).forEach((value,key)=>{
   if(key!=='csrf_token' && key!=='_token') values[key]=String(value);
  });
  return values;
 }

 let lastSavedSnapshot=formSnapshot();
 let saveInProgress=false;

 function notify(type,title,message){
  if(window.showThemeToast){
   showThemeToast(type,title,message);
  }else{
   alert(message);
  }
 }

 async function save(){
  if(saveInProgress)return;

  fontChanged();
  const current=formSnapshot();
  const changed={};

  Object.entries(current).forEach(([key,value])=>{
   if(String(lastSavedSnapshot[key] ?? '')!==String(value)) changed[key]=value;
  });

  if(Object.keys(changed).length===0){
   notify('info','No Changes','All theme settings are already saved.');
   return;
  }

  const fd=new FormData();
  const csrf=form.querySelector('input[name="csrf_token"],input[name="_token"]');
  if(csrf && csrf.name)fd.append(csrf.name,csrf.value);
  Object.entries(changed).forEach(([key,value])=>fd.append(key,value));
  fd.append('save_scope','all');

  const btn=document.getElementById('saveBtn');
  const controller=new AbortController();
  const timeoutId=setTimeout(()=>controller.abort(),15000);

  saveInProgress=true;
  btn.disabled=true;
  btn.classList.add('is-saving');
  btn.setAttribute('aria-busy','true');

  try{
   const response=await fetch('api/theme-api.php',{
    method:'POST',
    credentials:'same-origin',
    cache:'no-store',
    headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},
    body:fd,
    signal:controller.signal
   });

   const raw=await response.text();
   let data;
   try{
    data=JSON.parse(raw);
   }catch(parseError){
    throw new Error(raw.trim()||'Invalid server response.');
   }

   if(!response.ok || !data.ok){
    throw new Error(data.message||'Unable to save theme settings.');
   }

   lastSavedSnapshot={...current};

   if(window.GKTheme && typeof window.GKTheme.broadcastSettings==='function'){
    window.GKTheme.broadcastSettings(current);
   }

   const timing=data.duration_ms?` (${data.duration_ms} ms)`:'';
   notify('success','Settings Saved',(data.message||'Theme settings saved successfully.')+timing);
  }catch(error){
   const message=error.name==='AbortError'
    ? 'The server took too long to respond. Please try again.'
    : (error.message||'Unable to save theme settings.');
   notify('error','Save Failed',message);
  }finally{
   clearTimeout(timeoutId);
   saveInProgress=false;
   btn.disabled=false;
   btn.classList.remove('is-saving');
   btn.removeAttribute('aria-busy');
  }
 }
 document.getElementById('saveBtn').addEventListener('click',save);
 document.getElementById('resetBtn').addEventListener('click',()=>{if(confirm('Reset all theme settings to default?')){const fd=new FormData(form);fd.append('action','reset');fd.append('save_scope','all');fetch('api/theme-api.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{if(d.ok)location.reload();else alert(d.message)})}});
});
</script>
</body></html>