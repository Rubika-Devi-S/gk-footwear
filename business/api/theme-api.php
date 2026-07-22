<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$startedAt = microtime(true);

function theme_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function valid_theme_color_fast(string $value): bool
{
    return (bool)(
        preg_match('/^#[0-9a-f]{6}$/i', $value) ||
        preg_match('/^rgba?\(\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}\s*,\s*[0-9]{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value)
    );
}

function valid_theme_font_fast(string $value): bool
{
    return $value !== ''
        && strlen($value) <= 200
        && !preg_match('/[;{}<>]|url\s*\(|expression\s*\(|@import/i', $value);
}

require_business_login();

if (!is_business_admin($conn)) {
    theme_json(['ok' => false, 'message' => 'Only Admin can update theme.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    theme_json(['ok' => false, 'message' => 'Invalid request method.'], 405);
}

verify_csrf();

if (!table_exists($conn, 'website_color_settings')) {
    theme_json(['ok' => false, 'message' => 'website_color_settings table not found.'], 500);
}

$colorKeys = [
    'body_bg','topbar_bg','topbar_text','card_bg','card_header_bg','border_soft',
    'text_main','text_muted','sidebar_bg_1','sidebar_bg_2','sidebar_bg_3',
    'sidebar_text','sidebar_active_bg_1','sidebar_active_bg_2','sidebar_active_text',
    'sidebar_hover_bg','sidebar_hover_text','sidebar_submenu_bg','brand_1','brand_2',
    'brand_text','table_header_bg','table_header_text','table_row_hover','input_bg',
    'input_border','input_text','success_color','warning_color','danger_color','info_color'
];

$labels = [
    'font_family'=>'Font Family','base_font_size'=>'Base Font Size',
    'heading_font_size'=>'Heading Font Size','font_weight'=>'Font Weight',
    'heading_font_weight'=>'Heading Weight','line_height'=>'Line Height',
    'letter_spacing'=>'Letter Spacing','button_text_transform'=>'Button Text',
    'card_radius'=>'Card Radius','button_radius'=>'Button Radius',
    'sidebar_width'=>'Sidebar Width','navbar_height'=>'Navbar Height',
    'page_spacing'=>'Page Spacing','sidebar_style'=>'Sidebar Style',
    'navbar_style'=>'Navbar Style','card_style'=>'Card Style',
    'button_style'=>'Button Style','table_style'=>'Table Style',
    'table_density'=>'Table Density','theme_mode'=>'Theme Mode',
    'layout_width'=>'Layout Width','content_density'=>'Content Density'
];

foreach ($colorKeys as $key) {
    $labels[$key] = ucwords(str_replace('_', ' ', $key));
}

$enumValues = [
    'button_text_transform'=>['none','uppercase','capitalize'],
    'sidebar_style'=>['gradient','solid','soft'],
    'navbar_style'=>['solid','glass','bordered','floating'],
    'card_style'=>['elevated','flat','bordered','soft'],
    'button_style'=>['rounded','pill','square','soft'],
    'table_style'=>['clean','striped','bordered'],
    'table_density'=>['compact','comfortable','spacious'],
    'theme_mode'=>['light','dark','system'],
    'layout_width'=>['fluid','boxed'],
    'content_density'=>['compact','comfortable','spacious']
];

$numericRanges = [
    'base_font_size'=>[10,24],'heading_font_size'=>[14,48],
    'font_weight'=>[400,900],'heading_font_weight'=>[400,900],
    'line_height'=>[1,2.5],'letter_spacing'=>[-2,5],
    'card_radius'=>[0,32],'button_radius'=>[0,32],
    'sidebar_width'=>[220,340],'navbar_height'=>[56,92],
    'page_spacing'=>[8,40]
];

$typographyKeys = [
    'font_family','base_font_size','heading_font_size','font_weight',
    'heading_font_weight','line_height','letter_spacing','button_text_transform'
];

$businessId = (int) current_business_id();
$userId = (int) current_user_id();
$hasBusinessId = table_has_column($conn, 'website_color_settings', 'business_id');

if (($_POST['action'] ?? '') === 'reset') {
    mysqli_begin_transaction($conn);
    try {
        if ($hasBusinessId) {
            $stmt = mysqli_prepare($conn, 'DELETE FROM website_color_settings WHERE business_id = ?');
            if (!$stmt) throw new RuntimeException(mysqli_error($conn));
            mysqli_stmt_bind_param($stmt, 'i', $businessId);
        } else {
            $stmt = mysqli_prepare($conn, 'DELETE FROM website_color_settings');
            if (!$stmt) throw new RuntimeException(mysqli_error($conn));
        }

        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));

        $deleted = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($conn);

        theme_json([
            'ok'=>true,
            'message'=>'Theme reset to default.',
            'updated'=>$deleted,
            'duration_ms'=>(int)round((microtime(true)-$startedAt)*1000)
        ]);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        theme_json(['ok'=>false,'message'=>$e->getMessage()], 500);
    }
}

$settings = [];

foreach ($labels as $key => $label) {
    if (!array_key_exists($key, $_POST)) continue;

    $value = trim((string)$_POST[$key]);
    if ($value === '') continue;

    if (in_array($key, $colorKeys, true) && !valid_theme_color_fast($value)) {
        theme_json(['ok'=>false,'message'=>"Invalid value for {$label}."], 422);
    }

    if ($key === 'font_family' && !valid_theme_font_fast($value)) {
        theme_json(['ok'=>false,'message'=>'Invalid font family value.'], 422);
    }

    if (isset($enumValues[$key]) && !in_array($value, $enumValues[$key], true)) {
        theme_json(['ok'=>false,'message'=>"Invalid value for {$label}."], 422);
    }

    if (isset($numericRanges[$key])) {
        [$minimum, $maximum] = $numericRanges[$key];
        if (!is_numeric($value) || (float)$value < $minimum || (float)$value > $maximum) {
            theme_json(['ok'=>false,'message'=>"{$label} must be between {$minimum} and {$maximum}."], 422);
        }
    }

    $group = in_array($key, $colorKeys, true)
        ? 'layout'
        : (in_array($key, $typographyKeys, true) ? 'typography' : 'component');

    $settings[] = [
        'key'=>$key,
        'value'=>$value,
        'label'=>$label,
        'group'=>$group
    ];
}

if (!$settings) {
    theme_json([
        'ok'=>true,
        'message'=>'No changed settings to save.',
        'updated'=>0,
        'duration_ms'=>(int)round((microtime(true)-$startedAt)*1000)
    ]);
}

mysqli_begin_transaction($conn);

try {
    if ($hasBusinessId) {
        $sql = "
            INSERT INTO website_color_settings
                (business_id, setting_key, setting_value, setting_label, setting_group, updated_by, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                setting_value=VALUES(setting_value),
                setting_label=VALUES(setting_label),
                setting_group=VALUES(setting_group),
                updated_by=VALUES(updated_by),
                updated_at=CURRENT_TIMESTAMP,
                is_active=1
        ";
    } else {
        $sql = "
            INSERT INTO website_color_settings
                (setting_key, setting_value, setting_label, setting_group, updated_by, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                setting_value=VALUES(setting_value),
                setting_label=VALUES(setting_label),
                setting_group=VALUES(setting_group),
                updated_by=VALUES(updated_by),
                updated_at=CURRENT_TIMESTAMP,
                is_active=1
        ";
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) throw new RuntimeException(mysqli_error($conn));

    $updated = 0;

    foreach ($settings as $setting) {
        $key = $setting['key'];
        $value = $setting['value'];
        $label = $setting['label'];
        $group = $setting['group'];

        if ($hasBusinessId) {
            mysqli_stmt_bind_param($stmt, 'issssi', $businessId, $key, $value, $label, $group, $userId);
        } else {
            mysqli_stmt_bind_param($stmt, 'ssssi', $key, $value, $label, $group, $userId);
        }

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException(mysqli_stmt_error($stmt));
        }

        $updated++;
    }

    mysqli_stmt_close($stmt);
    mysqli_commit($conn);

    try {
        log_activity($conn, 'Theme', 'update', null, null, [
            'updated'=>$updated,
            'optimized'=>true
        ]);
    } catch (Throwable $ignored) {
    }

    theme_json([
        'ok'=>true,
        'message'=>$updated===1
            ? '1 theme setting saved successfully.'
            : "{$updated} theme settings saved successfully.",
        'updated'=>$updated,
        'duration_ms'=>(int)round((microtime(true)-$startedAt)*1000)
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    theme_json(['ok'=>false,'message'=>$e->getMessage()], 500);
}
