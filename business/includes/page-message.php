<?php
$type = $_SESSION['toast']['type'] ?? ($_GET['toast'] ?? '');
$message = $_SESSION['toast']['message'] ?? ($_GET['message'] ?? '');

if (!empty($_SESSION['toast'])) {
    unset($_SESSION['toast']);
}

$type = $type === 'success' ? 'success' : ($type === 'info' ? 'info' : ($type ? 'error' : ''));
$title = $type === 'success' ? 'Success' : ($type === 'info' ? 'Info' : 'Error');
$icon = $type === 'success' ? 'check' : ($type === 'info' ? 'info' : 'alert-triangle');

if ($type && $message):
?>
<div id="themeToastWrap" class="theme-toast-wrap" aria-live="polite" aria-atomic="true">
    <div class="theme-toast <?= e($type) ?>" data-toast>
        <div class="theme-toast-icon"><i data-lucide="<?= e($icon) ?>" style="width:18px;height:18px;"></i></div>
        <div>
            <div class="theme-toast-title"><?= e($title) ?></div>
            <div class="theme-toast-message"><?= e($message) ?></div>
        </div>
        <button type="button" class="theme-toast-close" onclick="this.closest('.theme-toast').remove()">×</button>
    </div>
</div>
<?php else: ?>
<div id="themeToastWrap" class="theme-toast-wrap" aria-live="polite" aria-atomic="true"></div>
<?php endif; ?>
