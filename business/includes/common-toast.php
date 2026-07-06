<?php
if (!defined('APP_COMMON_TOAST_INCLUDED')) {
    define('APP_COMMON_TOAST_INCLUDED', true);
}
?>
<?php if (defined('APP_COMMON_TOAST_INCLUDED')): ?>
<div id="appToastStack" class="app-toast-stack" aria-live="polite" aria-atomic="true"></div>

<style>
    .app-toast-stack {
        position: fixed;
        top: 16px;
        right: 16px;
        width: min(340px, calc(100vw - 32px));
        display: grid;
        gap: 10px;
        z-index: 2147483000;
        pointer-events: none;
    }

    .app-toast-item {
        pointer-events: auto;
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 16px;
        background: rgba(255, 255, 255, .96);
        border: 1px solid rgba(226, 232, 240, .95);
        box-shadow: 0 18px 42px rgba(15, 23, 42, .14);
        backdrop-filter: blur(10px);
        transform: translateX(110%);
        opacity: 0;
        transition: transform .22s ease, opacity .22s ease;
        overflow: hidden;
        font-size: 13px;
        line-height: 1.35;
        color: #0f172a;
        position: relative;
    }

    .app-toast-item.show {
        transform: translateX(0);
        opacity: 1;
    }

    .app-toast-item.hide {
        transform: translateX(110%);
        opacity: 0;
    }

    .app-toast-icon {
        width: 28px;
        height: 28px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        font-weight: 800;
    }

    .app-toast-content {
        flex: 1;
        min-width: 0;
    }

    .app-toast-title {
        font-weight: 800;
        margin-bottom: 1px;
        font-size: 13px;
    }

    .app-toast-message {
        color: #475569;
        word-break: break-word;
    }

    .app-toast-close {
        border: 0;
        background: transparent;
        color: #64748b;
        font-size: 18px;
        line-height: 1;
        padding: 0 0 0 6px;
        cursor: pointer;
    }

    .app-toast-success .app-toast-icon {
        background: #dcfce7;
        color: #15803d;
    }

    .app-toast-error .app-toast-icon {
        background: #fee2e2;
        color: #b91c1c;
    }

    .app-toast-warning .app-toast-icon {
        background: #fef3c7;
        color: #b45309;
    }

    .app-toast-info .app-toast-icon {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .app-toast-success::before,
    .app-toast-error::before,
    .app-toast-warning::before,
    .app-toast-info::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
    }

    .app-toast-success::before { background: #22c55e; }
    .app-toast-error::before { background: #ef4444; }
    .app-toast-warning::before { background: #f59e0b; }
    .app-toast-info::before { background: #3b82f6; }

    @media (max-width: 575.98px) {
        .app-toast-stack {
            top: 10px;
            right: 10px;
            width: calc(100vw - 20px);
        }
    }
</style>

<script>
(function () {
    'use strict';

    if (window.AppToast && window.AppToast.__ready) {
        return;
    }

    const toastTypes = {
        success: { title: 'Success', icon: '✓' },
        error: { title: 'Error', icon: '!' },
        warning: { title: 'Warning', icon: '!' },
        info: { title: 'Info', icon: 'i' }
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getStack() {
        let stack = document.getElementById('appToastStack');

        if (!stack) {
            stack = document.createElement('div');
            stack.id = 'appToastStack';
            stack.className = 'app-toast-stack';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'true');
            document.body.appendChild(stack);
        }

        return stack;
    }

    function removeToast(toast) {
        if (!toast || toast.dataset.removing === '1') {
            return;
        }

        toast.dataset.removing = '1';
        toast.classList.remove('show');
        toast.classList.add('hide');

        window.setTimeout(function () {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 240);
    }

    window.AppToast = {
        __ready: true,
        show: function (type, message, options) {
            const normalizedType = toastTypes[type] ? type : 'info';
            const config = Object.assign({
                title: toastTypes[normalizedType].title,
                duration: 3600,
                closable: true
            }, options || {});

            const toast = document.createElement('div');
            toast.className = 'app-toast-item app-toast-' + normalizedType;
            toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
            toast.innerHTML = `
                <div class="app-toast-icon">${escapeHtml(toastTypes[normalizedType].icon)}</div>
                <div class="app-toast-content">
                    <div class="app-toast-title">${escapeHtml(config.title)}</div>
                    <div class="app-toast-message">${escapeHtml(message)}</div>
                </div>
                ${config.closable ? '<button type="button" class="app-toast-close" aria-label="Close">&times;</button>' : ''}
            `;

            getStack().appendChild(toast);

            window.requestAnimationFrame(function () {
                toast.classList.add('show');
            });

            const closeButton = toast.querySelector('.app-toast-close');
            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    removeToast(toast);
                });
            }

            if (config.duration > 0) {
                window.setTimeout(function () {
                    removeToast(toast);
                }, config.duration);
            }

            return toast;
        },
        success: function (message, options) { return this.show('success', message, options); },
        error: function (message, options) { return this.show('error', message, options); },
        warning: function (message, options) { return this.show('warning', message, options); },
        info: function (message, options) { return this.show('info', message, options); }
    };
})();
</script>
<?php endif; ?>
