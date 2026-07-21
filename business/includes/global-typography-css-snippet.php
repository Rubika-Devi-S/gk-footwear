<?php
/*
 * Add these CSS variables/rules to includes/links.php after loading
 * website_color_settings so saved typography applies across all pages.
 *
 * Expected variables:
 * --app-font-family
 * --app-font-size
 * --app-heading-size
 * --app-font-weight
 * --app-heading-weight
 * --app-line-height
 * --app-letter-spacing
 * --app-button-transform
 */
?>
<style>
:root{
    --app-font-family:<?= e($colors['font_family'] ?? 'Inter, "Segoe UI", Arial, sans-serif') ?>;
    --app-font-size:<?= e($colors['base_font_size'] ?? '14') ?>px;
    --app-heading-size:<?= e($colors['heading_font_size'] ?? '24') ?>px;
    --app-font-weight:<?= e($colors['font_weight'] ?? '500') ?>;
    --app-heading-weight:<?= e($colors['heading_font_weight'] ?? '800') ?>;
    --app-line-height:<?= e($colors['line_height'] ?? '1.5') ?>;
    --app-letter-spacing:<?= e($colors['letter_spacing'] ?? '0') ?>px;
    --app-button-transform:<?= e($colors['button_text_transform'] ?? 'none') ?>;
}
body,button,input,select,textarea{
    font-family:var(--app-font-family);
    font-size:var(--app-font-size);
    font-weight:var(--app-font-weight);
    line-height:var(--app-line-height);
    letter-spacing:var(--app-letter-spacing);
}
h1,h2,h3,h4,h5,h6,.page-title,.mp-card-title{
    font-family:var(--app-font-family);
    font-weight:var(--app-heading-weight);
}
h1,.page-head-card h1{
    font-size:var(--app-heading-size);
}
button,.btn{
    text-transform:var(--app-button-transform);
}
</style>
