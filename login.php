<?php
require_once __DIR__ . '/includes/functions.php';
require_guest();

$portal = get('role', 'citizen');
if (!in_array($portal, ['citizen', 'staff', 'admin'], true)) {
    $portal = 'citizen';
}

$portalMeta = [
    'citizen' => [
        'title' => 'Citizen sign in',
        'subtitle' => 'Sign in to report issues, track progress and manage your notifications.',
        'badge' => 'Citizen portal',
        'icon' => 'fa-user',
    ],
    'staff' => [
        'title' => 'Staff sign in',
        'subtitle' => 'Access reports assigned to your department and keep residents updated.',
        'badge' => 'Staff portal',
        'icon' => 'fa-helmet-safety',
    ],
    'admin' => [
        'title' => 'Administrator sign in',
        'subtitle' => 'Manage reports, departments, users and platform-wide analytics.',
        'badge' => 'Admin portal',
        'icon' => 'fa-user-shield',
    ],
][$portal];

$attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'first' => time()];
if ((time() - (int) $attempts['first']) > 900) {
    $attempts = ['count' => 0, 'first' => time()];
}

if (is_post()) {
    require_csrf();

    if ((int) $attempts['count'] >= 6) {
        flash('danger', 'Too many failed sign-in attempts. Please wait 15 minutes before trying again.');
        redirect('login.php?role=' . $portal);
    }

    $email = strtolower(post('email'));
    $password = post('password');

    if ($email === '' || $password === '') {
        flash('danger', 'Please enter both your email address and password.');
        flash_old(['email' => $email]);
        redirect('login.php?role=' . $portal);
    }

    $user = fetch_one('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!(int) $user['is_active']) {
            flash('danger', 'This account has been deactivated. Please contact the civic office.');
            redirect('login.php?role=' . $portal);
        }

        if ($user['role'] !== $portal) {
            flash('danger', 'These credentials belong to a different portal. Please choose the correct sign-in tab.');
            flash_old(['email' => $email]);
            redirect('login.php?role=' . $portal);
        }

        unset($_SESSION['login_attempts']);
        $stmt = db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([(int) $user['id']]);

        login_user($user);
        clear_old();
        flash('success', 'Welcome back, ' . $user['full_name'] . '.');
        redirect(dashboard_path($user['role']));
    }

    usleep(350000);
    $attempts['count'] = (int) $attempts['count'] + 1;
    $_SESSION['login_attempts'] = $attempts;
    flash('danger', 'The email or password you entered is incorrect.');
    flash_old(['email' => $email]);
    redirect('login.php?role=' . $portal);
}

$pageTitle = $portalMeta['title'] . ' · ' . APP_NAME;
$savedEmail = old('email');
clear_old();
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="author" content="<?= e(DEVELOPER_NAME) ?>">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo.svg')) ?>">
    <script>(function(){if(localStorage.getItem('civicwatch-theme')==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
</head>
<body class="auth-body">
<div class="auth-shell">
    <section class="auth-visual">
        <a class="brand-lockup brand-lockup-light" href="<?= e(url('index.php')) ?>">
            <img src="<?= e(asset('images/logo.svg')) ?>" alt="" width="42" height="42">
            <span><strong>Civic<span>Watch</span></strong><small>Community action, tracked.</small></span>
        </a>
        <div>
            <span class="hero-eyebrow"><span class="pulse"></span><?= e($portalMeta['badge']) ?></span>
            <h2>Better communities start with a clear view of what needs attention.</h2>
            <p>CivicWatch connects residents, administrators and service teams in one transparent workflow — from the first photo to the final repair.</p>
            <div class="auth-points">
                <div class="auth-point"><i class="fa-solid fa-check"></i><span>Report issues with photos, exact map coordinates and a full description.</span></div>
                <div class="auth-point"><i class="fa-solid fa-check"></i><span>Follow every status change and read progress notes from the assigned team.</span></div>
                <div class="auth-point"><i class="fa-solid fa-check"></i><span>Secure, role-based access keeps community data in the right hands.</span></div>
            </div>
        </div>
        <div class="auth-visual-quote">
            <p>“Our residents can finally see what happens after they report a problem. That transparency changed the conversation.”</p>
            <strong>— Civic Services Office</strong>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <a class="d-inline-flex align-items-center gap-2 text-body-secondary small mb-4" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-arrow-left"></i> Back to home</a>

            <div class="auth-role-tabs" role="tablist" aria-label="Select sign-in portal">
                <a class="<?= $portal === 'citizen' ? 'active' : '' ?>" href="<?= e(url('login.php?role=citizen')) ?>"><i class="fa-solid fa-user me-1"></i>Citizen</a>
                <a class="<?= $portal === 'staff' ? 'active' : '' ?>" href="<?= e(url('login.php?role=staff')) ?>"><i class="fa-solid fa-helmet-safety me-1"></i>Staff</a>
                <a class="<?= $portal === 'admin' ? 'active' : '' ?>" href="<?= e(url('login.php?role=admin')) ?>"><i class="fa-solid fa-user-shield me-1"></i>Admin</a>
            </div>

            <div class="d-flex align-items-center gap-3 mb-2">
                <span class="stat-icon teal"><i class="fa-solid <?= e($portalMeta['icon']) ?>"></i></span>
                <div>
                    <h1 class="mb-1"><?= e($portalMeta['title']) ?></h1>
                    <p class="auth-sub mb-0"><?= e($portalMeta['subtitle']) ?></p>
                </div>
            </div>

            <div class="mt-3 mb-3"><?php render_flashes(); ?></div>

            <form method="post" action="<?= e(url('login.php?role=' . $portal)) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($savedEmail) ?>" placeholder="you@example.com" autocomplete="email" required autofocus>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="password" class="form-label">Password</label>
                        <span class="form-text">Minimum 8 characters</span>
                    </div>
                    <div class="position-relative">
                        <input type="password" class="form-control pe-5" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-toggle-password="#password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 btn-lg mt-2">
                    <i class="fa-solid fa-arrow-right-to-bracket me-2"></i>Sign in securely
                </button>
            </form>

            <?php if ($portal === 'citizen'): ?>
                <div class="auth-divider">New to CivicWatch?</div>
                <a class="btn btn-soft w-100" href="<?= e(url('register.php')) ?>"><i class="fa-solid fa-user-plus me-2"></i>Create a citizen account</a>
            <?php else: ?>
                <div class="auth-divider">Need help?</div>
                <p class="small text-body-secondary text-center mb-0">Contact your CivicWatch administrator if you cannot access your <?= e($portal) ?> account.</p>
            <?php endif; ?>

            <p class="text-center small text-body-secondary mt-4 mb-0">
                <i class="fa-solid fa-lock me-1"></i> Protected by secure sessions and password hashing.
            </p>
            <p class="text-center small text-body-secondary mt-2 mb-0">
                Developed by <a href="mailto:<?= e(DEVELOPER_EMAIL) ?>"><?= e(DEVELOPER_NAME) ?></a> &middot; <?= e(DEVELOPER_TITLE) ?>
            </p>
        </div>
    </section>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
