<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
<script>
(function () {
    function initLucide() {
        if (window.lucide) {
            lucide.createIcons();
        }
    }

    initLucide();

    const body = document.body;
    const sidebarToggle = document.getElementById("sidebarToggle");
    const mobileOverlay = document.getElementById("mobileOverlay");
    const themeModeToggle = document.getElementById("themeModeToggle");

    
    function applyThemeMode(mode) {
        if (mode === "dark") {
            body.classList.add("dark-mode");
        } else {
            body.classList.remove("dark-mode");
        }

        if (themeModeToggle && window.lucide) {
            themeModeToggle.innerHTML = mode === "dark"
                ? '<i data-lucide="sun" style="width:16px;height:16px;"></i>'
                : '<i data-lucide="moon" style="width:16px;height:16px;"></i>';
            initLucide();
        }
    }

    applyThemeMode(localStorage.getItem("gkThemeMode") || "light");

    if (themeModeToggle) {
        themeModeToggle.addEventListener("click", function () {
            const nextMode = body.classList.contains("dark-mode") ? "light" : "dark";
            localStorage.setItem("gkThemeMode", nextMode);
            applyThemeMode(nextMode);
            if (window.showThemeToast) {
                showThemeToast("success", "Theme Changed", nextMode === "dark" ? "Night mode enabled." : "Light mode enabled.");
            }
        });
    }

    if (localStorage.getItem("gkSidebarCollapsed") === "1" && window.innerWidth >= 1200) {
        body.classList.add("sidebar-collapsed");
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener("click", function () {
            if (window.innerWidth < 1200) {
                body.classList.toggle("sidebar-mobile-open");
            } else {
                body.classList.toggle("sidebar-collapsed");
                localStorage.setItem("gkSidebarCollapsed", body.classList.contains("sidebar-collapsed") ? "1" : "0");
            }
        });
    }

    if (mobileOverlay) {
        mobileOverlay.addEventListener("click", function () {
            body.classList.remove("sidebar-mobile-open");
        });
    }

    document.querySelectorAll(".sidebar-parent-toggle").forEach(function (btn) {
        btn.addEventListener("click", function () {
            if (body.classList.contains("sidebar-collapsed") && window.innerWidth >= 1200) {
                body.classList.remove("sidebar-collapsed");
                localStorage.setItem("gkSidebarCollapsed", "0");
            }

            const id = btn.getAttribute("data-submenu");
            const submenu = document.getElementById(id);

            if (submenu) {
                btn.classList.toggle("open");
                submenu.classList.toggle("open");
            }
        });
    });

    document.querySelectorAll("[data-toast]").forEach(function (toast) {
        setTimeout(function () {
            toast.classList.add("show");
        }, 100);

        setTimeout(function () {
            toast.classList.remove("show");
            setTimeout(function () {
                if (toast.parentNode) toast.remove();
            }, 300);
        }, 4300);
    });

    window.showThemeToast = function (type, title, message) {
        const wrap = document.getElementById("themeToastWrap");
        if (!wrap) return;

        const icon = type === "success" ? "check" : (type === "info" ? "info" : "alert-triangle");
        const div = document.createElement("div");
        div.className = "theme-toast " + type;
        div.setAttribute("data-toast", "1");
        div.innerHTML = `
            <div class="theme-toast-icon"><i data-lucide="${icon}" style="width:18px;height:18px;"></i></div>
            <div>
                <div class="theme-toast-title">${title}</div>
                <div class="theme-toast-message">${message}</div>
            </div>
            <button type="button" class="theme-toast-close" onclick="this.closest('.theme-toast').remove()">×</button>
        `;
        wrap.appendChild(div);
        initLucide();

        setTimeout(() => div.classList.add("show"), 50);
        setTimeout(() => {
            div.classList.remove("show");
            setTimeout(() => div.remove(), 300);
        }, 4300);
    };
})();
</script>
