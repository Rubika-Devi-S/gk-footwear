<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json');

require_business_login();

if (!is_business_admin($conn)) {
    echo json_encode(['ok' => false, 'message' => 'Only Admin can update theme.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request.']);
    exit;
}

verify_csrf();

if (!table_exists($conn, 'website_color_settings')) {
    echo json_encode(['ok' => false, 'message' => 'website_color_settings table not found. Run SQL patch.']);
    exit;
}

$colorSettings = [
    'body_bg' => 'Body Background',
    'topbar_bg' => 'Topbar Background',
    'topbar_text' => 'Topbar Text',
    'card_bg' => 'Card Background',
    'card_header_bg' => 'Card Header Background',
    'border_soft' => 'Border Color',
    'text_main' => 'Main Text Color',
    'text_muted' => 'Muted Text Color',
    'sidebar_bg_1' => 'Sidebar Gradient Start',
    'sidebar_bg_2' => 'Sidebar Gradient Middle',
    'sidebar_bg_3' => 'Sidebar Gradient End',
    'sidebar_text' => 'Sidebar Text',
    'sidebar_active_bg_1' => 'Active BG Start',
    'sidebar_active_bg_2' => 'Active BG End',
    'sidebar_active_text' => 'Active Text',
    'sidebar_hover_bg' => 'Hover Background',
    'sidebar_hover_text' => 'Hover Text',
    'sidebar_submenu_bg' => 'Submenu Background',
    'brand_1' => 'Primary Brand Color',
    'brand_2' => 'Secondary Brand Color',
    'brand_text' => 'Brand Button Text',
    'table_header_bg' => 'Table Header Background',
    'table_header_text' => 'Table Header Text',
    'table_row_hover' => 'Table Row Hover',
    'input_bg' => 'Input Background',
    'input_border' => 'Input Border',
    'input_text' => 'Input Text',
    'success_color' => 'Success Color',
    'warning_color' => 'Warning Color',
    'danger_color' => 'Danger Color',
    'info_color' => 'Info Color',
];

$typographySettings = [
    'font_family' => 'Font Family',
    'base_font_size' => 'Base Font Size',
    'heading_font_size' => 'Heading Font Size',
    'font_weight' => 'Default Font Weight',
    'heading_font_weight' => 'Heading Font Weight',
    'line_height' => 'Line Height',
    'letter_spacing' => 'Letter Spacing',
    'button_text_transform' => 'Button Text Style',
];

$allowedFontFamilies = [
    'Inter, "Segoe UI", Arial, sans-serif',
    '"Segoe UI", Arial, sans-serif',
    'Arial, Helvetica, sans-serif',
    'Roboto, Arial, sans-serif',
    'Poppins, Arial, sans-serif',
    'Georgia, "Times New Roman", serif',
];

$allowedTextTransforms = ['none', 'uppercase', 'capitalize'];
$allowedFontWeights = ['400', '500', '600', '700', '800', '900'];

function valid_theme_color(string $value): bool
{
    return (bool)(
        preg_match('/^#[a-fA-F0-9]{6}$/', $value) ||
        preg_match('/^rgba?\(\s*(?:\d{1,3}\s*,\s*){2}\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/', $value)
    );
}

function valid_typography_setting(
    string $key,
    string $value,
    array $fontFamilies,
    array $fontWeights,
    array $textTransforms
): bool {
    switch ($key) {
        case 'font_family':
            return in_array($value, $fontFamilies, true);

        case 'base_font_size':
            return is_numeric($value) && (float)$value >= 10 && (float)$value <= 24;

        case 'heading_font_size':
            return is_numeric($value) && (float)$value >= 14 && (float)$value <= 48;

        case 'font_weight':
        case 'heading_font_weight':
            return in_array($value, $fontWeights, true);

        case 'line_height':
            return is_numeric($value) && (float)$value >= 1 && (float)$value <= 2.5;

        case 'letter_spacing':
            return is_numeric($value) && (float)$value >= -2 && (float)$value <= 5;

        case 'button_text_transform':
            return in_array($value, $textTransforms, true);
    }

    return false;
}

$businessId = (int) current_business_id();
$userId = (int) current_user_id();
$hasBusinessId = table_has_column($conn, 'website_color_settings', 'business_id');
$saveScope = strtolower(trim((string)($_POST['save_scope'] ?? 'all')));
if (!in_array($saveScope, ['all', 'colors', 'typography'], true)) {
    $saveScope = 'all';
}

$updated = 0;

mysqli_begin_transaction($conn);

try {
    $settings = [];

    if ($saveScope === 'all' || $saveScope === 'colors') {
    foreach ($colorSettings as $key => $label) {
        $value = trim((string)($_POST[$key] ?? ''));
        if ($value === '' || !valid_theme_color($value)) {
            continue;
        }

        $settings[] = [
            'key' => $key,
            'value' => $value,
            'label' => $label,
            'group' => 'layout',
        ];
    }

    }

    if ($saveScope === 'all' || $saveScope === 'typography') {
    foreach ($typographySettings as $key => $label) {
        $value = trim((string)($_POST[$key] ?? ''));
        if (
            $value === '' ||
            !valid_typography_setting(
                $key,
                $value,
                $allowedFontFamilies,
                $allowedFontWeights,
                $allowedTextTransforms
            )
        ) {
            continue;
        }

        $settings[] = [
            'key' => $key,
            'value' => $value,
            'label' => $label,
            'group' => 'typography',
        ];
    }

    }

    foreach ($settings as $setting) {
        $key = $setting['key'];
        $value = $setting['value'];
        $label = $setting['label'];
        $group = $setting['group'];

        if ($hasBusinessId) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO website_color_settings
                    (business_id, setting_key, setting_value, setting_label, setting_group, updated_by, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    setting_label = VALUES(setting_label),
                    setting_group = VALUES(setting_group),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP,
                    is_active = 1
            ");

            if (!$stmt) {
                throw new RuntimeException(mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $stmt,
                "issssi",
                $businessId,
                $key,
                $value,
                $label,
                $group,
                $userId
            );
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO website_color_settings
                    (setting_key, setting_value, setting_label, setting_group, updated_by, is_active)
                VALUES (?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    setting_label = VALUES(setting_label),
                    setting_group = VALUES(setting_group),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP,
                    is_active = 1
            ");

            if (!$stmt) {
                throw new RuntimeException(mysqli_error($conn));
            }

            mysqli_stmt_bind_param(
                $stmt,
                "ssssi",
                $key,
                $value,
                $label,
                $group,
                $userId
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new RuntimeException($error);
        }

        mysqli_stmt_close($stmt);
        $updated++;
    }

    log_activity($conn, 'Theme', 'update', null, null, [
        'updated' => $updated,
        'save_scope' => $saveScope,
        'color_settings' => count($colorSettings),
        'typography_settings' => count($typographySettings),
    ]);

    mysqli_commit($conn);

    $message = 'Theme colors and typography saved successfully.';

    if ($saveScope === 'typography') {
        $message = 'Typography and text controls saved successfully.';
    } elseif ($saveScope === 'colors') {
        $message = 'Theme colors saved successfully.';
    }

    echo json_encode([
        'ok' => true,
        'message' => $message,
        'updated' => $updated,
        'save_scope' => $saveScope,
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);

    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ]);
}
?>