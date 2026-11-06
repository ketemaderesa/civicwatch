<?php
require_once __DIR__ . '/includes/functions.php';
require_guest();

if (is_post()) {
    require_csrf();

    $fullName = post('full_name');
    $email = strtolower(post('email'));
    $phone = post('phone');
    $password = post('password');
    $confirm = post('password_confirm');
    $terms = post_bool('terms');

    $errors = [];

    if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 120) {
        $errors[] = 'Please enter your full name (3–120 characters).';
    }
    if (!valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Your password must be between 8 and 72 characters.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Your password must include an uppercase letter, a lowercase letter and a number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'The password confirmation does not match.';
    }
    if (!$terms) {
        $errors[] = 'Please confirm that you will use CivicWatch responsibly.';
    }
    if (!$errors && fetch_one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
        $errors[] = 'An account with this email address already exists. Try signing in instead.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('danger', $error);
        }
        flash_old(['full_name' => $fullName, 'email' => $email, 'phone' => $phone]);
        redirect('register.php');
    }

    try {
        $stmt = db()->prepare(
            "INSERT INTO users (full_name, email, phone, password_hash, role, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'citizen', 1, NOW(), NOW())"
        );
        $stmt->execute([
            $fullName,
            $email,
            $phone !== '' ? $phone : null,
            password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) db()->lastInsertId();
        $user = fetch_one('SELECT * FROM users WHERE id = ?', [$userId]);

        login_user($user);
        clear_old();
        send_notification(
            $userId,
            'Welcome to CivicWatch',
            'Your account is ready. You can now report community issues, attach photos and track every update from your dashboard.',
            null
        );
        flash('success', 'Welcome to CivicWatch, ' . $fullName . '! Your account has been created.');
        redirect('user/index.php');
    } catch (PDOException $e) {
        flash('danger', 'We could not create your account right now. Please try again.');
        flash_old(['full_name' => $fullName, 'email' => $email, 'phone' => $phone]);
        redirect('register.php');
    }
}

$pageTitle = 'Create your account · ' . APP_NAME;
$saved = [
    'full_name' => old('full_name'),
    'email' => old('email'),
    'phone' => old('phone'),
];
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
            <span class="hero-eyebrow"><span class="pulse"></span> Join your community</span>
            <h2>Your eyes on the street can make the whole city better.</h2>
            <p>Create a free CivicWatch account to report issues, attach evidence and follow the work of the departments responsible for your neighbourhood.</p>
            <div class="auth-points">
                <div class="auth-point"><i class="fa-solid fa-camera"></i><span>Attach up to five photos to every report.</span></div>
                <div class="auth-point"><i class="fa-solid fa-location-crosshairs"></i><span>Pin the exact location using OpenStreetMap.</span></div>
                <div class="auth-point"><i class="fa-solid fa-bell"></i><span>Get notified the moment your report moves forward.</span></div>
                <div class="auth-point"><i class="fa-solid fa-shield-halved"></i><span>Your personal details stay private and secure.</span></div>
            </div>
        </div>
        <div class="auth-visual-quote">
            <p>“A pothole reported on Monday was repaired by Thursday. Being able to see each step made all the difference.”</p>
            <strong>— CivicWatch resident</strong>
        </div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <a class="d-inline-flex align-items-center gap-2 text-body-secondary small mb-4" href="<?= e(url('index.php')) ?>"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
            <h1>Create your account</h1>
            <p class="auth-sub">Already have an account? <a class="fw-600" href="<?= e(url('login.php')) ?>">Sign in instead</a></p>

            <div class="mb-3"><?php render_flashes(); ?></div>

            <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="full_name">Full name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                        <input class="form-control" type="text" id="full_name" name="full_name" value="<?= e($saved['full_name']) ?>" placeholder="e.g. Amina Bekele" maxlength="120" autocomplete="name" required autofocus>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label" for="email">Email address</label>
                        <input class="form-control" type="email" id="email" name="email" value="<?= e($saved['email']) ?>" placeholder="you@example.com" maxlength="160" autocomplete="email" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="phone">Phone <span class="text-body-secondary fw-normal">(optional)</span></label>
                        <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($saved['phone']) ?>" placeholder="+1 555 0100" maxlength="30" autocomplete="tel">
                    </div>
                </div>
                <div class="row g-3 mt-0">
                    <div class="col-md-6">
                        <label class="form-label" for="password">Password</label>
                        <div class="position-relative">
                            <input class="form-control pe-5" type="password" id="password" name="password" placeholder="Minimum 8 characters" minlength="8" maxlength="72" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" data-toggle-password="#password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="password_confirm">Confirm password</label>
                        <div class="position-relative">
                            <input class="form-control pe-5" type="password" id="password_confirm" name="password_confirm" placeholder="Repeat your password" minlength="8" maxlength="72" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" data-toggle-password="#password_confirm" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <div class="form-text mt-2">Use at least 8 characters with uppercase, lowercase and a number.</div>

                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" id="terms" name="terms" required>
                    <label class="form-check-label fs-7" for="terms">I will use CivicWatch responsibly and provide accurate information in my reports.</label>
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-lg mt-4">
                    <i class="fa-solid fa-user-plus me-2"></i>Create my account
                </button>
            </form>

            <p class="text-center small text-body-secondary mt-4 mb-0">
                <i class="fa-solid fa-shield-halved me-1"></i> Your password is stored using secure one-way hashing.
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
