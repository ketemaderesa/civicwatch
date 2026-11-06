<?php
require_once __DIR__ . '/functions.php';

$user = auth_user();
if (!$user) {
    redirect('login.php');
}

$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? 'Welcome back, ' . $user['full_name'] . '.';
$activePage = $activePage ?? '';
$unread = unread_notifications((int) $user['id']);
$role = $user['role'];
$roleLabel = role_label($role);

$navItems = [];
if ($role === 'admin') {
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'href' => 'admin/index.php'],
        ['key' => 'reports', 'label' => 'All Reports', 'icon' => 'fa-clipboard-list', 'href' => 'admin/reports.php'],
        ['key' => 'users', 'label' => 'Users', 'icon' => 'fa-users', 'href' => 'admin/users.php'],
        ['key' => 'departments', 'label' => 'Departments', 'icon' => 'fa-building-columns', 'href' => 'admin/departments.php'],
        ['key' => 'categories', 'label' => 'Categories', 'icon' => 'fa-layer-group', 'href' => 'admin/categories.php'],
        ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'fa-bullhorn', 'href' => 'admin/announcements.php'],
        ['key' => 'analytics', 'label' => 'Analytics & Reports', 'icon' => 'fa-chart-line', 'href' => 'admin/analytics.php'],
    ];
} elseif ($role === 'staff') {
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'href' => 'staff/index.php'],
        ['key' => 'reports', 'label' => 'Assigned Reports', 'icon' => 'fa-clipboard-check', 'href' => 'staff/reports.php'],
    ];
} else {
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'href' => 'user/index.php'],
        ['key' => 'submit', 'label' => 'Report an Issue', 'icon' => 'fa-circle-plus', 'href' => 'user/submit_report.php'],
        ['key' => 'reports', 'label' => 'My Reports', 'icon' => 'fa-clipboard-list', 'href' => 'user/reports.php'],
    ];
}

$navItems[] = ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'fa-bell', 'href' => 'notifications.php'];
$navItems[] = ['key' => 'profile', 'label' => 'My Profile', 'icon' => 'fa-user-gear', 'href' => 'profile.php'];
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <meta name="author" content="<?= e(DEVELOPER_NAME) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <script>
        (function () {
            var saved = localStorage.getItem('civicwatch-theme');
            if (saved === 'dark') document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet">
    <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
</head>
<body class="dashboard-body">
<div class="app-shell">
    <aside class="app-sidebar" id="appSidebar" aria-label="Main navigation">
        <div class="sidebar-head">
            <a class="brand-lockup brand-lockup-light" href="<?= e(url(dashboard_path($role))) ?>">
                <img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="38" height="38">
                <span><strong>Civic<span>Watch</span></strong><small><?= e($roleLabel) ?></small></span>
            </a>
            <button class="sidebar-close d-lg-none" type="button" id="sidebarClose" aria-label="Close navigation">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="sidebar-profile">
            <div class="avatar avatar-teal"><?= e(initials($user['full_name'])) ?></div>
            <div class="min-w-0">
                <strong class="text-truncate d-block"><?= e($user['full_name']) ?></strong>
                <span class="text-truncate d-block"><?= e($user['department_name'] ?: $roleLabel) ?></span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <span class="sidebar-label">Workspace</span>
            <?php foreach ($navItems as $item): ?>
                <a class="sidebar-link <?= $activePage === $item['key'] ? 'active' : '' ?>"
                   href="<?= e(url($item['href'])) ?>">
                    <span class="sidebar-icon"><i class="fa-solid <?= e($item['icon']) ?>"></i></span>
                    <span><?= e($item['label']) ?></span>
                    <?php if ($item['key'] === 'notifications' && $unread > 0): ?>
                        <span class="nav-count"><?= e($unread > 99 ? '99+' : (string) $unread) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <span class="sidebar-label mt-3">Community</span>
            <a class="sidebar-link" href="<?= e(url('index.php')) ?>">
                <span class="sidebar-icon"><i class="fa-solid fa-globe"></i></span>
                <span>View public site</span>
                <i class="fa-solid fa-arrow-up-right-from-square ms-auto small opacity-50"></i>
            </a>
        </nav>

        <div class="sidebar-foot">
            <div class="sidebar-tip">
                <i class="fa-solid fa-lightbulb"></i>
                <span><?= $role === 'citizen' ? 'Spotted something? Report it and follow the progress.' : ($role === 'staff' ? 'Keep residents informed by adding clear progress notes.' : 'Timely assignments lead to faster resolutions.') ?></span>
            </div>
            <form method="post" action="<?= e(url('logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="sidebar-logout"><i class="fa-solid fa-arrow-right-from-bracket"></i> Sign out</button>
            </form>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <button class="topbar-menu" type="button" id="sidebarToggle" aria-label="Open navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="min-w-0">
                    <h1 class="topbar-title text-truncate"><?= e($pageTitle) ?></h1>
                    <p class="topbar-subtitle text-truncate d-none d-sm-block"><?= e($pageSubtitle) ?></p>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn theme-toggle" type="button" aria-label="Toggle dark mode" title="Toggle dark mode">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <a class="icon-btn notification-btn" href="<?= e(url('notifications.php')) ?>" aria-label="Notifications" title="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <?php if ($unread > 0): ?><span class="notification-dot"><?= e($unread > 9 ? '9+' : (string) $unread) ?></span><?php endif; ?>
                </a>
                <div class="dropdown">
                    <button class="user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar avatar-sm"><?= e(initials($user['full_name'])) ?></span>
                        <span class="user-menu-text d-none d-md-block">
                            <strong><?= e($user['full_name']) ?></strong>
                            <small><?= e($roleLabel) ?></small>
                        </span>
                        <i class="fa-solid fa-chevron-down small d-none d-md-block"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li class="dropdown-header">
                            <span class="d-block fw-semibold"><?= e($user['full_name']) ?></span>
                            <span class="small text-body-secondary"><?= e($user['email']) ?></span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>"><i class="fa-solid fa-user-gear me-2"></i>My profile</a></li>
                        <li><a class="dropdown-item" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-globe me-2"></i>Public site</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= e(url('logout.php')) ?>">
                                <?= csrf_field() ?>
                                <button class="dropdown-item text-danger" type="submit"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
            <?php render_flashes(); ?>
