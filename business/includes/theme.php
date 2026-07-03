<?php
function default_theme_settings(): array
{
    return [
        'body_bg' => '#F7F9FC',
        'topbar_bg' => '#FFFFFF',
        'topbar_text' => '#0F172A',
        'card_bg' => '#FFFFFF',
        'card_header_bg' => '#FFFFFF',
        'border_soft' => '#E2E8F0',
        'text_main' => '#0F172A',
        'text_muted' => '#64748B',
        'sidebar_bg_1' => '#10192E',
        'sidebar_bg_2' => '#030911',
        'sidebar_bg_3' => '#15304C',
        'sidebar_text' => '#FFFFFF',
        'sidebar_active_bg_1' => '#2563EB',
        'sidebar_active_bg_2' => '#1D4ED8',
        'sidebar_active_text' => '#FFFFFF',
        'sidebar_hover_bg' => '#1E293B',
        'sidebar_hover_text' => '#FFFFFF',
        'sidebar_submenu_bg' => '#1E293B',
        'brand_1' => '#2563EB',
        'brand_2' => '#1D4ED8',
        'brand_text' => '#FFFFFF',
        'table_header_bg' => '#EEF2F7',
        'table_header_text' => '#334155',
        'table_row_hover' => '#F8FAFC',
        'input_bg' => '#FFFFFF',
        'input_border' => '#CBD5E1',
        'input_text' => '#0F172A',
        'success_color' => '#16A34A',
        'warning_color' => '#FBBF24',
        'danger_color' => '#DC2626',
        'info_color' => '#2563EB',
    ];
}

function load_theme_settings(PDO $pdo): array
{
    $settings = default_theme_settings();

    try {
        if (!table_exists($pdo, 'website_color_settings')) {
            return $settings;
        }

        $stmt = $pdo->query("
            SELECT setting_key, setting_value
            FROM website_color_settings
            WHERE is_active = 1
        ");

        foreach ($stmt->fetchAll() as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }
    } catch (Throwable $e) {
        return $settings;
    }

    return $settings;
}

function print_theme_css_vars(array $settings): void
{
    $css = [
        '--body-bg' => $settings['body_bg'],
        '--topbar-bg' => $settings['topbar_bg'],
        '--topbar-text' => $settings['topbar_text'],
        '--card-bg' => $settings['card_bg'],
        '--card-header-bg' => $settings['card_header_bg'],
        '--border-soft' => $settings['border_soft'],
        '--text-main' => $settings['text_main'],
        '--text-muted' => $settings['text_muted'],
        '--sidebar-bg-1' => $settings['sidebar_bg_1'],
        '--sidebar-bg-2' => $settings['sidebar_bg_2'],
        '--sidebar-bg-3' => $settings['sidebar_bg_3'],
        '--sidebar-text' => $settings['sidebar_text'],
        '--sidebar-active-bg-1' => $settings['sidebar_active_bg_1'],
        '--sidebar-active-bg-2' => $settings['sidebar_active_bg_2'],
        '--sidebar-active-text' => $settings['sidebar_active_text'],
        '--sidebar-hover-bg' => $settings['sidebar_hover_bg'],
        '--sidebar-hover-text' => $settings['sidebar_hover_text'],
        '--sidebar-submenu-bg' => $settings['sidebar_submenu_bg'],
        '--brand-1' => $settings['brand_1'],
        '--brand-2' => $settings['brand_2'],
        '--brand-text' => $settings['brand_text'],
        '--table-header-bg' => $settings['table_header_bg'],
        '--table-header-text' => $settings['table_header_text'],
        '--table-row-hover' => $settings['table_row_hover'],
        '--input-bg' => $settings['input_bg'],
        '--input-border' => $settings['input_border'],
        '--input-text' => $settings['input_text'],
        '--success-color' => $settings['success_color'],
        '--warning-color' => $settings['warning_color'],
        '--danger-color' => $settings['danger_color'],
        '--info-color' => $settings['info_color'],
    ];

    echo ":root{\n";
    foreach ($css as $key => $value) {
        echo $key . ":" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . ";\n";
    }
    echo "--sidebar-bg:linear-gradient(180deg,var(--sidebar-bg-1),var(--sidebar-bg-2),var(--sidebar-bg-3));\n";
    echo "--brand-gradient:linear-gradient(135deg,var(--brand-1),var(--brand-2));\n";
    echo "--shadow-card:0 18px 45px rgba(15,23,42,.08);\n";
    echo "}\n";
}
?>
