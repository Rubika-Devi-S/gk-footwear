<?php require_once __DIR__ . '/functions.php'; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Nunito:wght@400;500;600;700;800;900&family=Open+Sans:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800;900&family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
<?php include_once __DIR__ . '/theme-colors.php'; ?>
<style>
html,body{min-height:100%}
html,body,button,input,select,textarea,table,.btn,.form-control,.form-select,.card,.modal,.dropdown-menu,.nav-link{font-family:var(--app-font-family)!important}
body{background:var(--body-bg);color:var(--text-main);font-size:var(--app-font-size)!important;font-weight:var(--app-font-weight)!important;line-height:var(--app-line-height)!important;letter-spacing:var(--app-letter-spacing)!important}
h1,.page-title,.theme-title,.mp-hero h1{font-size:var(--app-heading-size)!important}h1,h2,h3,h4,h5,h6,.modal-title,.card-title{font-weight:var(--app-heading-weight)!important}
button,.btn,input[type=button],input[type=submit]{text-transform:var(--app-button-transform)!important;border-radius:var(--button-radius)!important}
#main{width:100%;min-height:100vh;margin-left:var(--sidebar-width);transition:margin-left .24s ease}
body.sidebar-collapsed #main{margin-left:88px}.page-section{padding-top:calc(var(--navbar-height) + 18px)!important;padding-left:var(--page-spacing)!important;padding-right:var(--page-spacing)!important}
.card-ui,.page-head-card,.kpi-card,.card,.modal-content{background:var(--card-bg);border:1px solid var(--border-soft);border-radius:var(--card-radius);box-shadow:var(--shadow-card)}
.form-control,.form-select{background:var(--input-bg);color:var(--input-text);border-color:var(--input-border);font-weight:inherit}
.table{color:var(--text-main)}.table thead th{background:var(--table-header-bg)!important;color:var(--table-header-text)!important;font-weight:var(--app-heading-weight)}.table tbody td{font-weight:var(--app-font-weight);border-color:var(--border-soft)}.table tbody tr:hover td{background:var(--table-row-hover)}
html[data-table-style=striped] .table tbody tr:nth-child(odd) td{background:color-mix(in srgb,var(--table-row-hover) 55%,transparent)}
html[data-table-style=bordered] .table td,html[data-table-style=bordered] .table th{border:1px solid var(--border-soft)}
html[data-table-density=compact] .table td,html[data-table-density=compact] .table th{padding:.4rem .55rem}html[data-table-density=spacious] .table td,html[data-table-density=spacious] .table th{padding:1rem}
html[data-card-style=flat] .card,html[data-card-style=flat] .card-ui{box-shadow:none}html[data-card-style=bordered] .card,html[data-card-style=bordered] .card-ui{box-shadow:none;border-width:2px}
html[data-button-style=pill] .btn{border-radius:999px!important}html[data-button-style=square] .btn{border-radius:2px!important}
html[data-layout-width=boxed] .page-section{max-width:1440px;margin-inline:auto}
html[data-content-density=compact] .page-section{--page-spacing:10px}html[data-content-density=spacious] .page-section{--page-spacing:28px}
html[data-navbar-style=glass] nav,html[data-navbar-style=glass] .topbar{backdrop-filter:blur(14px);background:color-mix(in srgb,var(--topbar-bg) 75%,transparent)!important}
html[data-theme-mode=dark]{--body-bg:#0F172A;--topbar-bg:#111827;--topbar-text:#F8FAFC;--card-bg:#111827;--card-header-bg:#0F172A;--border-soft:#334155;--text-main:#F8FAFC;--text-muted:#94A3B8;--table-header-bg:#1E293B;--table-header-text:#CBD5E1;--table-row-hover:#1E293B;--input-bg:#0F172A;--input-border:#334155;--input-text:#F8FAFC}
@media(prefers-color-scheme:dark){html[data-theme-mode=system]{--body-bg:#0F172A;--topbar-bg:#111827;--topbar-text:#F8FAFC;--card-bg:#111827;--border-soft:#334155;--text-main:#F8FAFC;--text-muted:#94A3B8;--table-header-bg:#1E293B;--table-header-text:#CBD5E1;--table-row-hover:#1E293B;--input-bg:#0F172A;--input-border:#334155;--input-text:#F8FAFC}}
.brand-gradient{background-image:linear-gradient(135deg,var(--brand-1),var(--brand-2));border:0;color:var(--brand-text)!important}.text-muted-custom{color:var(--text-muted)!important}
@media(max-width:1199px){#main{margin-left:0!important}.page-section{padding-top:calc(var(--navbar-height) + 12px)!important}}

/* Stable navbar/content geometry: theme switching must never reflow the page */
:root{--stable-navbar-height:var(--navbar-height,64px)}
#topbar,.topbar,nav.navbar,.app-navbar{
    height:var(--stable-navbar-height)!important;
    min-height:var(--stable-navbar-height)!important;
    max-height:var(--stable-navbar-height)!important;
    box-sizing:border-box!important;
}
.page-section,
body.dark-mode .page-section,
body.theme-dark .page-section,
html[data-theme="dark"] .page-section,
html[data-theme="light"] .page-section,
html[data-theme-mode="dark"] .page-section,
html[data-theme-mode="light"] .page-section{
    padding-top:calc(var(--stable-navbar-height) + 18px)!important;
    margin-top:0!important;
    transform:none!important;
}
#main,
body.dark-mode #main,
body.theme-dark #main,
html[data-theme="dark"] #main,
html[data-theme="light"] #main{
    padding-top:0!important;
    margin-top:0!important;
}
.card,.card-ui,.page-head-card,.kpi-card,.modal-content,
body.dark-mode .card,body.theme-dark .card{
    transform:none;
}
html.theme-switching *,html.theme-switching *::before,html.theme-switching *::after{
    transition-property:background-color,color,border-color,box-shadow,fill,stroke!important;
    transition-duration:.18s!important;
}


/* Fixed notification layer: success/error feedback must never affect page layout */
.theme-toast-wrap{
    position:fixed!important;
    top:calc(var(--stable-navbar-height,64px) + 18px)!important;
    right:22px!important;
    left:auto!important;
    z-index:10850!important;
    display:grid!important;
    gap:10px!important;
    width:min(380px,calc(100vw - 28px))!important;
    height:auto!important;
    margin:0!important;
    padding:0!important;
    pointer-events:none!important;
    contain:layout paint style!important;
}
.theme-toast{
    position:relative!important;
    display:flex!important;
    align-items:flex-start!important;
    gap:12px!important;
    min-height:64px!important;
    margin:0!important;
    padding:14px 15px!important;
    overflow:hidden!important;
    border:1px solid var(--border-soft)!important;
    border-radius:16px!important;
    background:var(--card-bg)!important;
    color:var(--text-main)!important;
    box-shadow:0 18px 45px rgba(15,23,42,.18)!important;
    opacity:0;
    transform:translate3d(24px,0,0);
    transition:opacity .18s ease,transform .18s ease!important;
    pointer-events:auto!important;
}
.theme-toast.show{
    opacity:1!important;
    transform:translate3d(0,0,0)!important;
}
.theme-toast::before{
    content:"";
    position:absolute;
    inset:0 auto 0 0;
    width:5px;
    background:var(--brand-1);
}
.theme-toast.success::before{background:var(--success-color)}
.theme-toast.error::before{background:var(--danger-color)}
.theme-toast.info::before{background:var(--info-color)}
.theme-toast-icon{
    width:36px!important;
    height:36px!important;
    min-width:36px!important;
    display:grid!important;
    place-items:center!important;
    border-radius:12px!important;
    background:var(--brand-1)!important;
    color:#fff!important;
}
.theme-toast.success .theme-toast-icon{background:var(--success-color)!important}
.theme-toast.error .theme-toast-icon{background:var(--danger-color)!important}
.theme-toast.info .theme-toast-icon{background:var(--info-color)!important}
.theme-toast-title{margin:0 0 3px!important;font-size:14px!important;font-weight:900!important;line-height:1.25!important}
.theme-toast-message{margin:0!important;font-size:12px!important;font-weight:700!important;line-height:1.4!important;color:var(--text-muted)!important}
.theme-toast-close{
    margin-left:auto!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
    color:var(--text-muted)!important;
    font-size:20px!important;
    line-height:1!important;
}
#saveBtn{
    min-width:166px;
    white-space:nowrap;
    contain:layout paint;
}
#saveBtn .save-spinner{display:none}
#saveBtn.is-saving .save-spinner{display:inline-block}
@media(max-width:575px){
    .theme-toast-wrap{
        top:calc(var(--stable-navbar-height,64px) + 10px)!important;
        right:14px!important;
        left:14px!important;
        width:auto!important;
    }
}

</style>


<script>
(function(){
    "use strict";
    const root = document.documentElement;
    const STORAGE_KEY = "gk_footwear_theme_mode";
    const LEGACY_KEYS = ["subhiksha_theme_mode","subhiksha_dark_mode"];
    const TOGGLE_SELECTOR = "#darkModeToggle,#themeToggle,#themeModeToggle,[data-theme-toggle],[data-dark-toggle],.js-dark-mode-toggle,.js-theme-toggle";

    const SETTINGS_MAP = {
        body_bg:"--body-bg",topbar_bg:"--topbar-bg",topbar_text:"--topbar-text",
        card_bg:"--card-bg",card_header_bg:"--card-header-bg",border_soft:"--border-soft",
        text_main:"--text-main",text_muted:"--text-muted",
        sidebar_bg_1:"--sidebar-bg-1",sidebar_bg_2:"--sidebar-bg-2",sidebar_bg_3:"--sidebar-bg-3",
        sidebar_text:"--sidebar-text",sidebar_active_bg_1:"--sidebar-active-bg-1",
        sidebar_active_bg_2:"--sidebar-active-bg-2",sidebar_active_text:"--sidebar-active-text",
        sidebar_hover_bg:"--sidebar-hover-bg",sidebar_hover_text:"--sidebar-hover-text",
        sidebar_submenu_bg:"--sidebar-submenu-bg",brand_1:"--brand-1",brand_2:"--brand-2",
        brand_text:"--brand-text",table_header_bg:"--table-header-bg",
        table_header_text:"--table-header-text",table_row_hover:"--table-row-hover",
        input_bg:"--input-bg",input_border:"--input-border",input_text:"--input-text",
        success_color:"--success-color",warning_color:"--warning-color",
        danger_color:"--danger-color",info_color:"--info-color",
        font_family:"--app-font-family",base_font_size:"--app-font-size",
        heading_font_size:"--app-heading-size",font_weight:"--app-font-weight",
        heading_font_weight:"--app-heading-weight",line_height:"--app-line-height",
        letter_spacing:"--app-letter-spacing",button_text_transform:"--app-button-transform",
        card_radius:"--card-radius",button_radius:"--button-radius",
        sidebar_width:"--sidebar-width",navbar_height:"--navbar-height",
        page_spacing:"--page-spacing"
    };
    const PX_SETTINGS = new Set([
        "base_font_size","heading_font_size","letter_spacing","card_radius",
        "button_radius","sidebar_width","navbar_height","page_spacing"
    ]);
    const DATA_SETTINGS = new Set([
        "sidebar_style","navbar_style","card_style","button_style","table_style",
        "table_density","layout_width","content_density"
    ]);

    function applySharedSettings(payload){
        if(!payload || typeof payload !== "object") return;
        const settings = payload.settings && typeof payload.settings === "object"
            ? payload.settings
            : payload;

        Object.entries(settings).forEach(function(entry){
            const key = entry[0];
            const value = String(entry[1] ?? "");
            if(!value) return;

            if(SETTINGS_MAP[key]){
                root.style.setProperty(SETTINGS_MAP[key], value + (PX_SETTINGS.has(key) ? "px" : ""));
            }

            if(DATA_SETTINGS.has(key)){
                root.dataset[key.replaceAll("_","-")] = value;
            }
        });

        if(settings.sidebar_bg_1 || settings.sidebar_bg_2 || settings.sidebar_bg_3){
            root.style.setProperty(
                "--sidebar-bg",
                "linear-gradient(180deg,var(--sidebar-bg-1),var(--sidebar-bg-2),var(--sidebar-bg-3))"
            );
        }

        if(settings.theme_mode){
            applyMode(String(settings.theme_mode), false);
        }
    }

    let themeChannel = null;
    if("BroadcastChannel" in window){
        themeChannel = new BroadcastChannel("gk_footwear_theme_sync");
        themeChannel.addEventListener("message", function(event){
            applySharedSettings(event.data);
        });
    }

    function resolvedMode(mode){
        if(mode === "system"){
            return window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light";
        }
        return mode === "dark" ? "dark" : "light";
    }

    function updateIcons(isDark){
        document.querySelectorAll(TOGGLE_SELECTOR).forEach(function(btn){
            btn.setAttribute("aria-pressed", isDark ? "true" : "false");
            btn.setAttribute("title", isDark ? "Switch to light mode" : "Switch to dark mode");
            const icon = btn.querySelector("[data-lucide]");
            if(icon) icon.setAttribute("data-lucide", isDark ? "sun" : "moon");
        });
        if(window.lucide && typeof window.lucide.createIcons === "function"){
            window.lucide.createIcons();
        }
    }

    function applyMode(mode, persist){
        const finalMode = resolvedMode(mode);
        root.classList.add("theme-switching");
        root.dataset.themeMode = finalMode;
        root.dataset.theme = finalMode;
        root.style.colorScheme = finalMode;

        if(document.body){
            document.body.classList.toggle("dark-mode", finalMode === "dark");
            document.body.classList.toggle("theme-dark", finalMode === "dark");
        }

        if(persist !== false){
            localStorage.setItem(STORAGE_KEY, finalMode);
            localStorage.setItem("subhiksha_theme_mode", finalMode);
            localStorage.setItem("subhiksha_dark_mode", finalMode === "dark" ? "1" : "0");
        }

        updateIcons(finalMode === "dark");
        window.dispatchEvent(new CustomEvent("gk:themechange",{detail:{mode:finalMode}}));
        requestAnimationFrame(function(){
            requestAnimationFrame(function(){ root.classList.remove("theme-switching"); });
        });
    }

    function savedMode(){
        const current = localStorage.getItem(STORAGE_KEY);
        if(current === "dark" || current === "light") return current;
        const legacy = localStorage.getItem("subhiksha_theme_mode");
        if(legacy === "dark" || legacy === "light") return legacy;
        return localStorage.getItem("subhiksha_dark_mode") === "1" ? "dark" : (root.dataset.themeMode || "light");
    }

    window.GKTheme = {
        apply: function(mode){ applyMode(mode,true); },
        current: function(){ return root.dataset.themeMode === "dark" ? "dark" : "light"; },
        toggle: function(){ applyMode(root.dataset.themeMode === "dark" ? "light" : "dark",true); },
        applySettings: applySharedSettings,
        broadcastSettings: function(settings){
            const payload = {type:"settings-saved",settings:settings || {},timestamp:Date.now()};
            if(themeChannel){
                themeChannel.postMessage(payload);
            }
            try{
                localStorage.setItem("gk_theme_sync_payload", JSON.stringify(payload));
            }catch(ignore){}
        }
    };

    applyMode(savedMode(),false);

    document.addEventListener("DOMContentLoaded",function(){
        applyMode(savedMode(),false);
    });

    /* Capture first and stop older duplicate handlers from toggling a second time. */
    document.addEventListener("click",function(event){
        const button = event.target.closest(TOGGLE_SELECTOR);
        if(!button) return;
        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        window.GKTheme.toggle();
    },true);

    window.addEventListener("storage",function(event){
        if(event.key === STORAGE_KEY || LEGACY_KEYS.includes(event.key)){
            applyMode(savedMode(),false);
            return;
        }
        if(event.key === "gk_theme_sync_payload" && event.newValue){
            try{
                applySharedSettings(JSON.parse(event.newValue));
            }catch(ignore){}
        }
    });
})();
</script>
