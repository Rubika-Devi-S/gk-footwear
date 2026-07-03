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

$allowed = [
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

$businessId = current_business_id();
$userId = current_user_id();
$hasBusinessId = table_has_column($conn, 'website_color_settings', 'business_id');
$updated = 0;

mysqli_begin_transaction($conn);

try {
    foreach ($allowed as $key => $label) {
        $value = trim($_POST[$key] ?? '');

        if ($value === '') {
            continue;
        }

        if (!preg_match('/^#[a-fA-F0-9]{6}$/', $value) && !preg_match('/^rgba?\([0-9\s,.]+\)$/', $value)) {
            continue;
        }

        if ($hasBusinessId) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO website_color_settings
                (business_id, setting_key, setting_value, setting_label, setting_group, updated_by, is_active)
                VALUES (?, ?, ?, ?, 'layout', ?, 1)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    setting_label = VALUES(setting_label),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP,
                    is_active = 1
            ");
            mysqli_stmt_bind_param($stmt, "isssi", $businessId, $key, $value, $label, $userId);
        } else {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO website_color_settings
                (setting_key, setting_value, setting_label, setting_group, updated_by, is_active)
                VALUES (?, ?, ?, 'layout', ?, 1)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    setting_label = VALUES(setting_label),
                    updated_by = VALUES(updated_by),
                    updated_at = CURRENT_TIMESTAMP,
                    is_active = 1
            ");
            mysqli_stmt_bind_param($stmt, "sssi", $key, $value, $label, $userId);
        }

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $updated++;
    }

    log_activity($conn, 'Theme', 'update', null, null, ['updated' => $updated]);
    mysqli_commit($conn);

    echo json_encode(['ok' => true, 'message' => 'Theme colors saved successfully.']);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
?>
