<?php
require_once __DIR__ . '/functions.php';

$publicUser = auth_user();
$pageTitle = $pageTitle ?? APP_NAME . ' – ' . APP_TAGLINE;
$metaDescription = $metaDescription ?? 'CivicWatch helps residents report community issues, follow progress, and work with local departments to build a safer, cleaner city.';
$navBase = url('index.php');
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
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
<body class="public-body">
<nav class="navbar navbar-expand-lg public-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand brand-lockup" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="38" height="38">
            <span><strong>Civic<span>Watch</span></strong><small>Community action, tracked.</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNav" aria-controls="publicNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="publicNav">
            <ul class="navbar-nav mx-auto mb-3 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?= e($navBase) ?>">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($navBase) ?>#how-it-works">How it works</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($navBase) ?>#categories">Categories</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($navBase) ?>#recent-reports">Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= e($navBase) ?>#contact">Contact</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2 public-actions">
                <button class="icon-btn theme-toggle" type="button" aria-label="Toggle dark mode" title="Toggle dark mode">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <?php if ($publicUser): ?>
                    <a class="btn btn-sm btn-primary" href="<?= e(url(dashboard_path($publicUser['role']))) ?>">
                        <i class="fa-solid fa-gauge-high me-1"></i> Dashboard
                    </a>
                    <form method="post" action="<?= e(url('logout.php')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Sign out</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('login.php')) ?>">Sign in</a>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('register.php')) ?>">Report an issue</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<div class="public-flashes container mt-3">
    <?php render_flashes(); ?>
</div>
