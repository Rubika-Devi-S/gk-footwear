<?php
/**
 * Global Typography Loader
 * Include this file from business/includes/links.php
 * after the main stylesheet links.
 */
?>
<style id="global-typography-rules">
html, body,
button, input, select, textarea,
table, .btn, .form-control, .form-select,
.card, .modal, .dropdown-menu {
    font-family: var(--app-font-family, Inter, "Segoe UI", Arial, sans-serif) !important;
}
body {
    font-size: var(--app-font-size, 14px) !important;
    font-weight: var(--app-font-weight, 500) !important;
    line-height: var(--app-line-height, 1.5) !important;
    letter-spacing: var(--app-letter-spacing, 0px) !important;
}
h1, h2, h3, h4, h5, h6,
.page-title, .mp-hero h1, .mp-card-title, .modal-title {
    font-weight: var(--app-heading-weight, 800) !important;
}
button, .btn, input[type="button"], input[type="submit"] {
    text-transform: var(--app-button-transform, none) !important;
}
</style>
<script>
(function () {
    "use strict";

    const key = "gk_footwear_typography";
    const map = {
        font_family: "--app-font-family",
        base_font_size: "--app-font-size",
        heading_font_size: "--app-heading-size",
        font_weight: "--app-font-weight",
        heading_font_weight: "--app-heading-weight",
        line_height: "--app-line-height",
        letter_spacing: "--app-letter-spacing",
        button_text_transform: "--app-button-transform"
    };

    function normalize(name, value) {
        value = String(value ?? "").trim();
        if (name === "base_font_size" || name === "heading_font_size" || name === "letter_spacing") {
            return value + "px";
        }
        return value;
    }

    try {
        const settings = JSON.parse(localStorage.getItem(key) || "{}");
        Object.entries(settings).forEach(function ([name, value]) {
            if (map[name] && value !== "") {
                document.documentElement.style.setProperty(map[name], normalize(name, value));
            }
        });
    } catch (error) {
        console.warn("Global typography loading failed.", error);
    }
})();
</script>
