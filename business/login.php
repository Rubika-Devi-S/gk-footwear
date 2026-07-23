<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';

if (!empty($_SESSION['business_id']) && !empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ECOMMER - Cloud Based Smart Billing Software</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php include __DIR__ . '/includes/links.php'; ?>

    <style>
        :root {
            --brand-blue: #2563eb;
            --brand-indigo: #4338ca;
            --brand-purple: #7c3aed;
            --brand-cyan: #06b6d4;

            --text-main: #0f172a;
            --text-muted: #64748b;
            --text-light: rgba(255,255,255,.82);

            --field-border: #dbe4f0;
            --page-bg: #f8fafc;
            --card-bg: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
            margin: 0;
        }

        body {
            height: 100vh;
            overflow: hidden;
            color: var(--text-main);
            background: var(--page-bg);
            font-family: "Inter", "Segoe UI", Arial, sans-serif;
        }

        .auth-page {
            height: 100vh;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(500px, .92fr);
        }

        .brand-panel {
            height: 100vh;
            position: relative;
            overflow: hidden;
            padding: 26px 42px 22px;
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                radial-gradient(circle at 18% 20%, rgba(59, 130, 246, .35), transparent 34%),
                radial-gradient(circle at 86% 78%, rgba(124, 58, 237, .46), transparent 34%),
                linear-gradient(145deg, #0f3ccf 0%, #162b83 42%, #2b1f70 70%, #5b21b6 100%);
        }

        .brand-panel::before,
        .brand-panel::after {
            content: "";
            position: absolute;
            pointer-events: none;
            border-radius: 50%;
        }

        .brand-panel::before {
            width: 400px;
            height: 400px;
            top: -195px;
            right: -140px;
            border: 1px solid rgba(255,255,255,.10);
            box-shadow:
                0 0 0 36px rgba(255,255,255,.032),
                0 0 0 76px rgba(255,255,255,.024),
                0 0 0 116px rgba(255,255,255,.017);
        }

        .brand-panel::after {
            width: 130px;
            height: 130px;
            right: 9%;
            top: 46%;
            opacity: .24;
            background-image: radial-gradient(rgba(255,255,255,.50) 1.2px, transparent 1.2px);
            background-size: 16px 16px;
        }

        .brand-top,
        .brand-content,
        .brand-bottom {
            position: relative;
            z-index: 2;
        }

        .brand-top {
            display: flex;
            align-items: center;
        }

        .left-brand-logo {
            display: block;
            width: min(280px, 100%);
            max-height: 74px;
            object-fit: contain;
            object-position: left center;
        }

        .left-brand-fallback {
            display: none;
            color: #ffffff;
        }

        .left-brand-fallback h1 {
            margin: 0;
            font-size: 24px;
            line-height: 1;
            font-weight: 900;
            letter-spacing: -.02em;
        }

        .left-brand-fallback p {
            margin: 6px 0 0;
            color: rgba(255,255,255,.78);
            font-size: 13px;
            font-weight: 500;
        }

        .brand-content {
            max-width: 650px;
            margin: 12px 0 10px;
        }

        .brand-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            min-height: 34px;
            padding: 6px 13px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.16);
            background: rgba(255,255,255,.10);
            backdrop-filter: blur(8px);
            font-size: 12px;
            font-weight: 750;
        }

        .brand-pill svg {
            width: 15px;
            height: 15px;
        }

        .brand-heading {
            margin: 22px 0 14px;
            max-width: 640px;
            font-size: clamp(38px, 4.2vw, 62px);
            line-height: .98;
            font-weight: 900;
            letter-spacing: -.05em;
        }

        .brand-heading .accent {
            display: block;
            color: #c7d2fe;
        }

        .brand-description {
            max-width: 640px;
            margin: 0;
            color: rgba(255,255,255,.80);
            font-size: 14px;
            line-height: 1.55;
            font-weight: 500;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-top: 22px;
        }

        .feature-card {
            min-height: 138px;
            padding: 14px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.12);
            background: rgba(255,255,255,.085);
            backdrop-filter: blur(10px);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.08);
        }

        .feature-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            margin-bottom: 13px;
            color: #ffffff;
            background: linear-gradient(135deg, rgba(59,130,246,.95), rgba(124,58,237,.92));
            box-shadow: 0 8px 18px rgba(15,23,42,.15);
        }

        .feature-icon svg {
            width: 19px;
            height: 19px;
        }

        .feature-title {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 800;
        }

        .feature-text {
            margin: 0;
            color: rgba(255,255,255,.70);
            font-size: 11px;
            line-height: 1.5;
        }

        .brand-bottom {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            color: rgba(255,255,255,.68);
            font-size: 11px;
            font-weight: 600;
            margin-top: 8px;
        }

        .brand-bottom-item {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .brand-bottom-item svg {
            width: 14px;
            height: 14px;
        }

        .brand-divider {
            width: 1px;
            height: 16px;
            background: rgba(255,255,255,.25);
        }

        .login-side {
            height: 100vh;
            padding: 22px;
            display: grid;
            place-items: center;
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at 90% 16%, rgba(99,102,241,.08), transparent 28%),
                radial-gradient(circle at 84% 88%, rgba(124,58,237,.10), transparent 30%),
                linear-gradient(135deg, #ffffff 0%, #f8fafc 52%, #f4f3ff 100%);
        }

        .login-side::after {
            content: "";
            position: absolute;
            width: 180px;
            height: 180px;
            right: -32px;
            bottom: -28px;
            opacity: .22;
            background-image: radial-gradient(rgba(99,102,241,.38) 1.2px, transparent 1.2px);
            background-size: 15px 15px;
            pointer-events: none;
        }

        .login-card {
            position: relative;
            z-index: 2;
            width: min(540px, 100%);
            padding: 28px 30px 24px;
            border: 1px solid rgba(226,232,240,.85);
            border-radius: 26px;
            background: rgba(255,255,255,.96);
            box-shadow:
                0 22px 58px rgba(15,23,42,.12),
                0 6px 22px rgba(79,70,229,.05);
            backdrop-filter: blur(18px);
        }

        .login-brand {
            text-align: center;
            margin-bottom: 18px;
        }

        .company-logo {
            display: block;
            width: min(360px, 92%);
            height: auto;
            max-height: 86px;
            margin: 0 auto;
            object-fit: contain;
        }

        .logo-fallback {
            display: none;
            text-align: center;
        }

        .logo-fallback-title {
            margin: 0;
            font-size: clamp(34px, 5vw, 52px);
            font-weight: 900;
            line-height: 1;
            letter-spacing: -.05em;
            background: linear-gradient(90deg, #4f00dc, #2563eb 42%, #06b6d4 76%, #14d8cc);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .logo-fallback-subtitle {
            margin: 7px 0 0;
            color: #52525b;
            font-size: 14px;
            font-weight: 500;
        }

        .login-form {
            display: grid;
            gap: 16px;
        }

        .form-group {
            display: grid;
            gap: 7px;
        }

        .form-label {
            margin: 0;
            color: #1e293b;
            font-size: 12.5px;
            font-weight: 800;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            top: 50%;
            left: 15px;
            width: 18px;
            height: 18px;
            color: #91a0b5;
            transform: translateY(-50%);
            pointer-events: none;
            z-index: 2;
        }

        .login-input {
            width: 100%;
            min-height: 56px;
            padding: 12px 46px 12px 48px;
            border: 1px solid var(--field-border);
            border-radius: 14px;
            outline: none;
            color: var(--text-main);
            background: #ffffff;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(15,23,42,.02);
            transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
        }

        .login-input::placeholder {
            color: #94a3b8;
            font-weight: 550;
        }

        .login-input:hover {
            border-color: #bcc8d8;
        }

        .login-input:focus {
            border-color: var(--brand-blue);
            box-shadow:
                0 0 0 4px rgba(37,99,235,.10),
                0 8px 20px rgba(37,99,235,.05);
        }

        .password-toggle-btn {
            position: absolute;
            top: 50%;
            right: 10px;
            width: 36px;
            height: 36px;
            border: 0;
            border-radius: 10px;
            display: inline-grid;
            place-items: center;
            color: #75869d;
            background: transparent;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 3;
        }

        .password-toggle-btn:hover {
            color: var(--brand-blue);
            background: rgba(37,99,235,.08);
        }

        .password-toggle-btn svg {
            width: 18px;
            height: 18px;
        }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: -2px;
        }

        .remember-wrap {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #475569;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
        }

        .remember-wrap input {
            width: 16px;
            height: 16px;
            margin: 0;
            accent-color: var(--brand-indigo);
            cursor: pointer;
        }

        .login-button {
            position: relative;
            width: 100%;
            min-height: 56px;
            overflow: hidden;
            border: 0;
            border-radius: 14px;
            color: #ffffff;
            background: linear-gradient(100deg, var(--brand-blue), #4f46e5 48%, var(--brand-purple));
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(79,70,229,.23);
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }

        .login-button::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(110deg, transparent 20%, rgba(255,255,255,.28) 48%, transparent 75%);
            transform: translateX(-120%);
            transition: transform .55s ease;
        }

        .login-button:hover {
            transform: translateY(-2px);
            filter: saturate(1.06);
            box-shadow: 0 18px 36px rgba(79,70,229,.30);
        }

        .login-button:hover::before {
            transform: translateX(120%);
        }

        .login-button-content {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
        }

        .login-button-content svg {
            width: 18px;
            height: 18px;
        }

        .security-box {
            margin-top: 16px;
            padding: 12px 13px;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
        }

        .security-icon {
            width: 38px;
            height: 38px;
            flex: 0 0 38px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            color: #16a34a;
            background: #ecfdf5;
        }

        .security-icon svg {
            width: 20px;
            height: 20px;
        }

        .security-title {
            color: #334155;
            font-size: 12px;
            font-weight: 800;
        }

        .security-text {
            margin-top: 2px;
            color: #94a3b8;
            font-size: 10.5px;
            line-height: 1.4;
        }

        /* Medium desktop / laptop height fitting */
        @media (max-width: 1366px), (max-height: 860px) {
            .brand-panel {
                padding: 22px 34px 18px;
            }

            .left-brand-logo {
                max-height: 66px;
                width: min(250px, 100%);
            }

            .brand-heading {
                font-size: clamp(34px, 3.8vw, 54px);
                margin: 18px 0 10px;
            }

            .brand-description {
                font-size: 13px;
                line-height: 1.5;
            }

            .feature-grid {
                margin-top: 18px;
                gap: 10px;
            }

            .feature-card {
                min-height: 124px;
                padding: 12px;
            }

            .feature-title {
                font-size: 13px;
            }

            .feature-text {
                font-size: 10.5px;
            }

            .login-card {
                padding: 24px 26px 20px;
            }

            .company-logo {
                max-height: 78px;
                width: min(330px, 92%);
            }
        }

        @media (max-width: 1180px) {
            body {
                overflow-y: auto;
                height: auto;
            }

            .auth-page {
                min-height: 100vh;
                height: auto;
                grid-template-columns: 1fr;
            }

            .brand-panel {
                height: auto;
                min-height: auto;
                padding: 24px 24px 18px;
            }

            .brand-content {
                margin: 18px 0 12px;
            }

            .brand-heading {
                font-size: clamp(34px, 5.4vw, 50px);
            }

            .login-side {
                height: auto;
                min-height: auto;
                padding: 22px 18px 26px;
            }

            .login-card {
                width: min(560px, 100%);
            }
        }

        @media (max-width: 760px) {
            .feature-grid {
                grid-template-columns: 1fr;
            }

            .feature-card {
                min-height: auto;
            }

            .brand-panel {
                padding: 22px 18px 16px;
            }

            .left-brand-logo {
                width: min(240px, 100%);
                max-height: 60px;
            }

            .brand-heading {
                font-size: 34px;
            }

            .brand-description {
                font-size: 13px;
            }

            .login-card {
                padding: 22px 18px 18px;
                border-radius: 20px;
            }

            .company-logo {
                width: min(300px, 90%);
                max-height: 70px;
            }

            .form-options {
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .brand-panel {
                display: none;
            }

            .auth-page {
                display: block;
            }

            .login-side {
                min-height: 100vh;
                padding: 14px;
            }

            .login-card {
                padding: 20px 16px 16px;
            }

            .login-input,
            .login-button {
                min-height: 52px;
            }

            .form-options {
                display: block;
            }

            .remember-wrap {
                margin-bottom: 4px;
            }

            .company-logo {
                width: min(270px, 88%);
                max-height: 62px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/page-message.php'; ?>

<div class="auth-page">

    <section class="brand-panel">
        <div class="brand-top">
            <img
                src="assets/images/ecommer-login-logo.png"
                alt="ECOMMER - Cloud Based Smart Billing Software"
                class="left-brand-logo"
                id="leftBrandLogo"
            >

            <div class="left-brand-fallback" id="leftBrandFallback">
                <h1>ECOMMER</h1>
                <p>Cloud Based Smart Billing Software</p>
            </div>
        </div>

        <div class="brand-content">
            <div class="brand-pill">
                <i data-lucide="sparkles"></i>
                <span>Built for modern businesses</span>
            </div>

            <h2 class="brand-heading">
                Cloud-based billing made simple,
                <span class="accent">powerful &amp; scalable.</span>
            </h2>

            <p class="brand-description">
                Manage billing, inventory, customers, suppliers and reports—all in one secure,
                cloud-based platform.
            </p>

            <div class="feature-grid">
                <article class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="shield-check"></i>
                    </div>
                    <h3 class="feature-title">Secure &amp; Reliable</h3>
                    <p class="feature-text">
                        Enterprise-grade security to protect your business data.
                    </p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="chart-no-axes-combined"></i>
                    </div>
                    <h3 class="feature-title">Smart Insights</h3>
                    <p class="feature-text">
                        Real-time reports and analytics to help your business grow faster.
                    </p>
                </article>

                <article class="feature-card">
                    <div class="feature-icon">
                        <i data-lucide="cloud"></i>
                    </div>
                    <h3 class="feature-title">Cloud Anywhere</h3>
                    <p class="feature-text">
                        Access your business securely from anywhere, anytime.
                    </p>
                </article>
            </div>
        </div>

        <div class="brand-bottom">
            <span class="brand-bottom-item">
                <i data-lucide="lock-keyhole"></i>
                Enterprise-grade security
            </span>

            <span class="brand-divider"></span>

            <span class="brand-bottom-item">
                <i data-lucide="cloud"></i>
                99.9% Uptime
            </span>
        </div>
    </section>

    <section class="login-side">
        <main class="login-card">

            <header class="login-brand">
                <img
                    src="assets/images/ecommer-login-logo.png"
                    alt="ECOMMER - Cloud Based Smart Billing Software"
                    class="company-logo"
                    id="companyLogo"
                >

                <div class="logo-fallback" id="logoFallback">
                    <h1 class="logo-fallback-title">ECOMMER</h1>
                    <p class="logo-fallback-subtitle">
                        Cloud Based Smart Billing Software
                    </p>
                </div>
            </header>

            <form method="post" action="api/login-api.php" class="login-form">
                <?= csrf_field(); ?>

                <div class="form-group">
                    <label for="username" class="form-label">
                        Username or Email
                    </label>

                    <div class="input-wrap">
                        <i data-lucide="user-round" class="input-icon"></i>

                        <input
                            type="text"
                            name="username"
                            id="username"
                            class="login-input"
                            placeholder="Enter username or email"
                            autocomplete="username"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        Password
                    </label>

                    <div class="input-wrap">
                        <i data-lucide="lock-keyhole" class="input-icon"></i>

                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="login-input"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button
                            type="button"
                            class="password-toggle-btn"
                            id="passwordToggleBtn"
                            aria-label="Show password"
                        >
                            <i data-lucide="eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-wrap">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember me</span>
                    </label>
                </div>

                <button type="submit" class="login-button">
                    <span class="login-button-content">
                        <i data-lucide="log-in"></i>
                        <span>Sign In</span>
                    </span>
                </button>
            </form>

            <div class="security-box">
                <div class="security-icon">
                    <i data-lucide="shield-check"></i>
                </div>

                <div>
                    <div class="security-title">
                        Secure login • Passwords are encrypted
                    </div>

                    <div class="security-text">
                        Your session is protected with secure authentication.
                    </div>
                </div>
            </div>

        </main>
    </section>

</div>

<?php include __DIR__ . '/includes/script.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('passwordToggleBtn');

    const companyLogo = document.getElementById('companyLogo');
    const logoFallback = document.getElementById('logoFallback');

    const leftBrandLogo = document.getElementById('leftBrandLogo');
    const leftBrandFallback = document.getElementById('leftBrandFallback');

    function refreshIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function handleLogoVisibility(imgEl, fallbackEl) {
        if (!imgEl || !fallbackEl) return;

        function showImage() {
            imgEl.style.display = 'block';
            fallbackEl.style.display = 'none';
        }

        function showFallback() {
            imgEl.style.display = 'none';
            fallbackEl.style.display = 'block';
        }

        imgEl.addEventListener('load', showImage);
        imgEl.addEventListener('error', showFallback);

        if (imgEl.complete) {
            if (imgEl.naturalWidth > 0) {
                showImage();
            } else {
                showFallback();
            }
        }
    }

    handleLogoVisibility(companyLogo, logoFallback);
    handleLogoVisibility(leftBrandLogo, leftBrandFallback);

    if (passwordInput && toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            const isHidden = passwordInput.type === 'password';

            passwordInput.type = isHidden ? 'text' : 'password';

            toggleBtn.setAttribute(
                'aria-label',
                isHidden ? 'Hide password' : 'Show password'
            );

            const icon = document.getElementById('passwordToggleIcon');
            if (icon) {
                icon.setAttribute('data-lucide', isHidden ? 'eye-off' : 'eye');
            }

            refreshIcons();
            passwordInput.focus();
        });
    }

    refreshIcons();
});
</script>

</body>
</html>