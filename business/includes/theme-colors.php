<?php
$defaults = [
    'body_bg' => '#F7F9FC',
    'topbar_bg' => '#FFFFFF',
    'topbar_text' => '#0F172A',
    'card_bg' => '#FFFFFF',
    'card_header_bg' => '#FFFFFF',
    'border_soft' => '#E2E8F0',
    'text_main' => '#0F172A',
    'text_muted' => '#64748B',
    'sidebar_bg_1' => '#243447',
    'sidebar_bg_2' => '#2F3A45',
    'sidebar_bg_3' => '#1F2933',
    'sidebar_text' => '#FFFFFF',
    'sidebar_active_bg_1' => '#3B82F6',
    'sidebar_active_bg_2' => '#2563EB',
    'sidebar_active_text' => '#FFFFFF',
    'sidebar_hover_bg' => 'rgba(255,255,255,.10)',
    'sidebar_hover_text' => '#FFFFFF',
    'sidebar_submenu_bg' => 'rgba(255,255,255,.06)',
    'brand_1' => '#0F766E',
    'brand_2' => '#2563EB',
    'brand_text' => '#FFFFFF',
    'table_header_bg' => '#EEF2F7',
    'table_header_text' => '#334155',
    'table_row_hover' => '#F8FAFC',
    'input_bg' => '#FFFFFF',
    'input_border' => '#CBD5E1',
    'input_text' => '#0F172A',
    'success_color' => '#16A34A',
    'warning_color' => '#F59E0B',
    'danger_color' => '#DC2626',
    'info_color' => '#2563EB',
];

$colors = $defaults;
$businessId = current_business_id();

if (isset($conn) && table_exists($conn, 'website_color_settings')) {
    $hasBusinessId = table_has_column($conn, 'website_color_settings', 'business_id');

    if ($hasBusinessId && $businessId > 0) {
        $stmt = mysqli_prepare($conn, "
            SELECT setting_key, setting_value
            FROM website_color_settings
            WHERE is_active = 1
              AND (business_id = ? OR business_id IS NULL)
            ORDER BY business_id ASC
        ");
        mysqli_stmt_bind_param($stmt, "i", $businessId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($conn, "
            SELECT setting_key, setting_value
            FROM website_color_settings
            WHERE is_active = 1
        ");
    }

    if (!empty($result)) {
        while ($row = mysqli_fetch_assoc($result)) {
            if (array_key_exists($row['setting_key'], $colors)) {
                $colors[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
}

if (!function_exists('cssv')) {
    function cssv(array $colors, string $key): string
    {
        return htmlspecialchars((string)($colors[$key] ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
?>
<style>
:root {
    --body-bg: <?=cssv($colors, 'body_bg') ?>;
    --topbar-bg: <?=cssv($colors, 'topbar_bg') ?>;
    --topbar-text: <?=cssv($colors, 'topbar_text') ?>;
    --card-bg: <?=cssv($colors, 'card_bg') ?>;
    --card-header-bg: <?=cssv($colors, 'card_header_bg') ?>;
    --border-soft: <?=cssv($colors, 'border_soft') ?>;
    --text-main: <?=cssv($colors, 'text_main') ?>;
    --text-muted: <?=cssv($colors, 'text_muted') ?>;
    --sidebar-bg-1: <?=cssv($colors, 'sidebar_bg_1') ?>;
    --sidebar-bg-2: <?=cssv($colors, 'sidebar_bg_2') ?>;
    --sidebar-bg-3: <?=cssv($colors, 'sidebar_bg_3') ?>;
    --sidebar-bg: linear-gradient(180deg, var(--sidebar-bg-1), var(--sidebar-bg-2), var(--sidebar-bg-3));
    --sidebar-text: <?=cssv($colors, 'sidebar_text') ?>;
    --sidebar-active-bg-1: <?=cssv($colors, 'sidebar_active_bg_1') ?>;
    --sidebar-active-bg-2: <?=cssv($colors, 'sidebar_active_bg_2') ?>;
    --sidebar-active-text: <?=cssv($colors, 'sidebar_active_text') ?>;
    --sidebar-hover-bg: <?=cssv($colors, 'sidebar_hover_bg') ?>;
    --sidebar-hover-text: <?=cssv($colors, 'sidebar_hover_text') ?>;
    --sidebar-submenu-bg: <?=cssv($colors, 'sidebar_submenu_bg') ?>;
    --brand-1: <?=cssv($colors, 'brand_1') ?>;
    --brand-2: <?=cssv($colors, 'brand_2') ?>;
    --brand-text: <?=cssv($colors, 'brand_text') ?>;
    --table-header-bg: <?=cssv($colors, 'table_header_bg') ?>;
    --table-header-text: <?=cssv($colors, 'table_header_text') ?>;
    --table-row-hover: <?=cssv($colors, 'table_row_hover') ?>;
    --input-bg: <?=cssv($colors, 'input_bg') ?>;
    --input-border: <?=cssv($colors, 'input_border') ?>;
    --input-text: <?=cssv($colors, 'input_text') ?>;
    --success-color: <?=cssv($colors, 'success_color') ?>;
    --warning-color: <?=cssv($colors, 'warning_color') ?>;
    --danger-color: <?=cssv($colors, 'danger_color') ?>;
    --info-color: <?=cssv($colors, 'info_color') ?>;
    --shadow-card: 0 18px 45px rgba(15, 23, 42, .08);
}
</style>