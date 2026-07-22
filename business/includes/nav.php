<?php
$currentUserId = function_exists('current_user_id')
    ? (int) current_user_id()
    : (int)($_SESSION['user_id'] ?? $_SESSION['business_user_id'] ?? $_SESSION['id'] ?? 0);

$currentBusinessId = function_exists('current_business_id')
    ? (int) current_business_id()
    : (int)($_SESSION['business_id'] ?? 0);

$currentUserName = $_SESSION['name'] ?? $_SESSION['full_name'] ?? 'User';
$currentUserEmail = $_SESSION['email'] ?? '';
$currentUserRole = $_SESSION['role_name'] ?? 'User';
$currentUserProfileImage = trim((string)($_SESSION['profile_image'] ?? ''));

/*
 * Load the latest profile details when the session does not yet contain
 * the uploaded profile photo. This also supports users who logged in before
 * the profile-image feature was added.
 */
if (
    isset($conn) &&
    $conn instanceof mysqli &&
    $currentUserId > 0 &&
    $currentBusinessId > 0 &&
    $currentUserProfileImage === ''
) {
    $profileStmt = mysqli_prepare(
        $conn,
        "SELECT
            COALESCE(NULLIF(full_name, ''), name) AS display_name,
            email,
            profile_image
         FROM users
         WHERE user_id = ?
           AND business_id = ?
         LIMIT 1"
    );

    if ($profileStmt) {
        mysqli_stmt_bind_param(
            $profileStmt,
            'ii',
            $currentUserId,
            $currentBusinessId
        );

        mysqli_stmt_execute($profileStmt);
        $profileResult = mysqli_stmt_get_result($profileStmt);
        $profileRow = $profileResult
            ? mysqli_fetch_assoc($profileResult)
            : null;

        mysqli_stmt_close($profileStmt);

        if ($profileRow) {
            $databaseName = trim((string)($profileRow['display_name'] ?? ''));
            $databaseEmail = trim((string)($profileRow['email'] ?? ''));
            $databaseImage = trim((string)($profileRow['profile_image'] ?? ''));

            if ($databaseName !== '') {
                $currentUserName = $databaseName;
                $_SESSION['name'] = $databaseName;
                $_SESSION['full_name'] = $databaseName;
            }

            if ($databaseEmail !== '') {
                $currentUserEmail = $databaseEmail;
                $_SESSION['email'] = $databaseEmail;
            }

            if ($databaseImage !== '') {
                $currentUserProfileImage = $databaseImage;
                $_SESSION['profile_image'] = $databaseImage;
            }
        }
    }
}

/*
 * Only permit profile images stored by the profile module.
 * The browser resolves this path relative to the business directory.
 */
$showProfileImage = false;
$currentUserProfileImageUrl = '';

if (
    $currentUserProfileImage !== '' &&
    strpos($currentUserProfileImage, 'uploads/profile/') === 0
) {
    $profileImageFile = __DIR__ . '/../' . $currentUserProfileImage;

    if (is_file($profileImageFile)) {
        $showProfileImage = true;
        $currentUserProfileImageUrl =
            $currentUserProfileImage .
            '?v=' .
            rawurlencode((string)filemtime($profileImageFile));
    }
}

$trimmedUserName = trim((string)$currentUserName);
$currentUserInitial = $trimmedUserName !== ''
    ? strtoupper(substr($trimmedUserName, 0, 1))
    : 'U';
?>
<style>
#topbar {
    height: 68px;
    background: var(--topbar-bg);
    color: var(--topbar-text);
    border-bottom: 1px solid var(--border-soft);
    position: fixed;
    top: 0;
    left: 268px;
    right: 0;
    z-index: 1030;
    transition: left .24s ease;
}

body.sidebar-collapsed #topbar {
    left: 88px;
}

.topbar-user-btn {
    border: 1px solid var(--border-soft);
    background: var(--card-bg);
    border-radius: 18px;
    padding: 7px 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-main);
}

.user-avatar {
    width: 38px;
    height: 38px;
    border-radius: 14px;
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    overflow: hidden;
    color: #fff;
    background-image: linear-gradient(
        135deg,
        var(--brand-1),
        var(--brand-2)
    );
    font-weight: 900;
}

.user-avatar img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: center;
}

.dropdown-profile-preview {
    width: 46px;
    height: 46px;
    border-radius: 15px;
    overflow: hidden;
    flex: 0 0 46px;
    display: grid;
    place-items: center;
    color: #fff;
    font-size: 16px;
    font-weight: 900;
    background-image: linear-gradient(
        135deg,
        var(--brand-1),
        var(--brand-2)
    );
}

.dropdown-profile-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.dropdown-menu {
    border: 1px solid var(--border-soft);
    border-radius: 18px;
    box-shadow: var(--shadow-card);
    padding: 8px;
    min-width: 240px;
}

.dropdown-item {
    border-radius: 12px;
    font-weight: 700;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

@media (max-width: 1199px) {
    #topbar {
        left: 0 !important;
    }
}
</style>

<header id="topbar" class="d-flex align-items-center px-3 px-lg-4">
    <div class="d-flex align-items-center gap-3 w-100">
        <button
            id="sidebarToggle"
            class="icon-btn border-0"
            type="button"
            title="Toggle sidebar"
        >
            <i data-lucide="menu"></i>
        </button>

        <div>
            <div class="fw-bold">
                <?= e($_SESSION['business_name'] ?? 'GK Footwear') ?>
            </div>
            <div class="small text-muted-custom">
                <?= e($currentUserRole) ?>
            </div>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2 gap-sm-3">
            <button
                id="themeModeToggle"
                class="icon-btn"
                type="button"
                title="Toggle theme mode"
            >
                <i
                    data-lucide="moon"
                    style="width:16px;height:16px;"
                ></i>
            </button>

            <div class="dropdown">
                <button
                    class="topbar-user-btn dropdown-toggle"
                    type="button"
                    data-bs-toggle="dropdown"
                    aria-expanded="false"
                >
                    <span class="user-avatar">
                        <?php if ($showProfileImage): ?>
                            <img
                                src="<?= e($currentUserProfileImageUrl) ?>"
                                alt="<?= e($currentUserName) ?>"
                            >
                        <?php else: ?>
                            <?= e($currentUserInitial) ?>
                        <?php endif; ?>
                    </span>

                    <span class="d-none d-sm-block text-start">
                        <span class="d-block fw-bold lh-sm">
                            <?= e($currentUserName) ?>
                        </span>
                        <small class="text-muted-custom">
                            <?= e($currentUserRole) ?>
                        </small>
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="px-3 py-2">
                        <div class="d-flex align-items-center gap-3">
                            <span class="dropdown-profile-preview">
                                <?php if ($showProfileImage): ?>
                                    <img
                                        src="<?= e($currentUserProfileImageUrl) ?>"
                                        alt="<?= e($currentUserName) ?>"
                                    >
                                <?php else: ?>
                                    <?= e($currentUserInitial) ?>
                                <?php endif; ?>
                            </span>

                            <span class="min-width-0">
                                <span class="d-block fw-bold text-truncate">
                                    <?= e($currentUserName) ?>
                                </span>
                                <small class="text-muted text-break">
                                    <?= e(
                                        $currentUserEmail !== ''
                                            ? $currentUserEmail
                                            : $currentUserRole
                                    ) ?>
                                </small>
                            </span>
                        </div>
                    </li>

                    <li>
                        <hr class="dropdown-divider">
                    </li>

                    <li>
                        <a class="dropdown-item" href="profile.php">
                            <i
                                data-lucide="user"
                                style="width:15px;height:15px;"
                            ></i>
                            My Profile
                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item" href="system-config.php">
                            <i
                                data-lucide="settings"
                                style="width:15px;height:15px;"
                            ></i>
                            Settings
                        </a>
                    </li>

                    <li>
                        <a
                            class="dropdown-item text-danger"
                            href="logout.php"
                        >
                            <i
                                data-lucide="log-out"
                                style="width:15px;height:15px;"
                            ></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
