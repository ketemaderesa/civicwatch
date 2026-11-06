<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$userId = (int) $user['id'];
$role = $user['role'];

$values = [
    'full_name' => $user['full_name'],
    'email' => $user['email'],
    'phone' => $user['phone'] ?? '',
    'address' => $user['address'] ?? '',
];

if (is_post()) {
    require_csrf();
    $action = post('action');

    if ($action === 'update_profile') {
        $fullName = post('full_name');
        $email = strtolower(post('email'));
        $phone = post('phone');
        $address = post('address');
        $currentPassword = post('current_password');
        $errors = [];

        if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 120) {
            $errors[] = 'Your full name must be between 3 and 120 characters.';
        }
        if (!valid_email($email)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }
        if (mb_strlen($address) > 255) {
            $errors[] = 'Your address must be 255 characters or fewer.';
        }

        $emailChanged = strtolower((string) $user['email']) !== $email;
        if ($emailChanged) {
            $existing = fetch_one('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1', [$email, $userId]);
            if ($existing) {
                $errors[] = 'That email address is already used by another account.';
            }
            if ($currentPassword === '') {
                $errors[] = 'Enter your current password to change your sign-in email address.';
            } elseif (!password_verify($currentPassword, (string) $user['password_hash'])) {
                $errors[] = 'Your current password is incorrect.';
            }
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
        } else {
            $stmt = db()->prepare(
                'UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([
                $fullName,
                $email,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $userId,
            ]);

            if ($emailChanged) {
                send_notification(
                    $userId,
                    'Sign-in email updated',
                    'Your CivicWatch sign-in email address was changed to ' . $email . '. If you did not make this change, contact the civic office immediately.',
                    null
                );
            }

            flash('success', 'Your profile details have been updated.');
            redirect('profile.php');
        }

        $values = [
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
        ];
    }

    if ($action === 'change_password') {
        $currentPassword = post('current_password');
        $newPassword = post('new_password');
        $confirmPassword = post('password_confirm');
        $errors = [];

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        }
        if (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            $errors[] = 'Your new password must be between 8 and 72 characters.';
        } elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'Your new password must include an uppercase letter, a lowercase letter and a number.';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'The new password confirmation does not match.';
        }
        if (!$errors && password_verify($newPassword, (string) $user['password_hash'])) {
            $errors[] = 'Choose a new password that is different from your current password.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
            redirect('profile.php');
        }

        $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        unset($_SESSION['login_attempts']);

        send_notification(
            $userId,
            'Password changed',
            'Your CivicWatch password was changed successfully. If this was not you, contact the civic office immediately.',
            null
        );

        flash('success', 'Your password has been changed. A confirmation notification has been added to your account.');
        redirect('profile.php');
    }

    flash('danger', 'Unsupported action.');
    redirect('profile.php');
}

if ($role === 'citizen') {
    $stats = [
        ['label' => 'My reports', 'value' => (int) fetch_value('SELECT COUNT(*) FROM reports WHERE user_id = ?', [$userId], 0)],
        ['label' => 'Open', 'value' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status NOT IN ('resolved','rejected')", [$userId], 0)],
        ['label' => 'Resolved', 'value' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'resolved'", [$userId], 0)],
    ];
} elseif ($role === 'staff') {
    $stats = [
        ['label' => 'Assigned cases', 'value' => (int) fetch_value('SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ?', [$userId], 0)],
        ['label' => 'Open', 'value' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status NOT IN ('resolved','rejected')", [$userId], 0)],
        ['label' => 'Resolved', 'value' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status = 'resolved'", [$userId], 0)],
    ];
} else {
    $stats = [
        ['label' => 'Total reports', 'value' => (int) fetch_value('SELECT COUNT(*) FROM reports', [], 0)],
        ['label' => 'Active users', 'value' => (int) fetch_value('SELECT COUNT(*) FROM users WHERE is_active = 1', [], 0)],
        ['label' => 'Departments', 'value' => (int) fetch_value('SELECT COUNT(*) FROM departments WHERE is_active = 1', [], 0)],
    ];
}

$pageTitle = 'My Profile';
$pageSubtitle = 'Manage your account details and sign-in security.';
$activePage = 'profile';

require __DIR__ . '/includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>My profile</h2>
        <p>Keep your contact details current so the civic office can reach you about your reports.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e(url(dashboard_path($role))) ?>"><i class="fa-solid fa-gauge-high me-1"></i> Dashboard</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="content-card profile-avatar-card mb-3">
            <span class="avatar avatar-lg avatar-teal"><?= e(initials($user['full_name'])) ?></span>
            <h3><?= e($user['full_name']) ?></h3>
            <p><?= e(role_label($role)) ?><?= $user['department_name'] ? ' · ' . e($user['department_name']) : '' ?></p>
            <span class="badge-status <?= $role === 'admin' ? 'status-assigned' : ($role === 'staff' ? 'status-in-progress' : 'status-approved') ?>">
                <i class="fa-solid <?= $role === 'admin' ? 'fa-user-shield' : ($role === 'staff' ? 'fa-helmet-safety' : 'fa-user') ?>"></i>
                <?= e(role_label($role)) ?>
            </span>

            <div class="profile-meta">
                <span><i class="fa-regular fa-envelope"></i><?= e($user['email']) ?></span>
                <span><i class="fa-solid fa-phone"></i><?= e($user['phone'] ?: 'No phone number added') ?></span>
                <span><i class="fa-solid fa-location-dot"></i><?= e($user['address'] ?: 'No address added') ?></span>
                <span><i class="fa-regular fa-calendar"></i>Member since <?= e(format_date($user['created_at'], 'M j, Y')) ?></span>
                <span><i class="fa-regular fa-clock"></i>Last sign in <?= e($user['last_login'] ? time_ago($user['last_login']) : 'First session') ?></span>
            </div>
        </div>

        <div class="content-card mb-3">
            <div class="content-card-head"><div><h3>My activity</h3><p>Based on your current role</p></div></div>
            <div class="content-card-body">
                <div class="row g-3">
                    <?php foreach ($stats as $stat): ?>
                        <div class="col-4">
                            <div class="analytics-mini-stat">
                                <span><?= e($stat['label']) ?></span>
                                <strong><?= number_format($stat['value']) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="content-card">
            <div class="content-card-head"><div><h3>Account security</h3><p>How your account is protected</p></div></div>
            <div class="content-card-body">
                <ul class="list-clean">
                    <li><span class="list-label"><i class="fa-solid fa-lock text-teal"></i><span>Password hashing</span></span><strong>Enabled</strong></li>
                    <li><span class="list-label"><i class="fa-solid fa-shield-halved text-teal"></i><span>Role-based access</span></span><strong><?= e(role_label($role)) ?></strong></li>
                    <li><span class="list-label"><i class="fa-solid fa-user-clock text-teal"></i><span>Session timeout</span></span><strong>60 min idle</strong></li>
                    <li><span class="list-label"><i class="fa-solid fa-fingerprint text-teal"></i><span>CSRF protection</span></span><strong>Active</strong></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="content-card mb-3">
            <div class="content-card-head">
                <div><h3>Personal information</h3><p>Update the details associated with your account</p></div>
            </div>
            <div class="content-card-body">
                <form method="post" action="<?= e(url('profile.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="full_name">Full name</label>
                            <input class="form-control" type="text" id="full_name" name="full_name" value="<?= e($values['full_name']) ?>" maxlength="120" autocomplete="name" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="phone">Phone number</label>
                            <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($values['phone']) ?>" maxlength="30" autocomplete="tel" placeholder="+1 555 0100">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="email">Sign-in email</label>
                            <input class="form-control" type="email" id="email" name="email" value="<?= e($values['email']) ?>" maxlength="160" autocomplete="email" required>
                            <div class="form-text">Changing your email requires your current password.</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="profile_current_password">Current password</label>
                            <div class="position-relative">
                                <input class="form-control pe-5" type="password" id="profile_current_password" name="current_password" autocomplete="current-password" placeholder="Only needed for email changes">
                                <button type="button" class="password-toggle" data-toggle-password="#profile_current_password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Home address <span class="text-body-secondary fw-normal">(optional)</span></label>
                            <textarea class="form-control" id="address" name="address" rows="2" maxlength="255" placeholder="Street, neighbourhood, city"><?= e($values['address']) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> Save changes</button>
                        <a class="btn btn-soft" href="<?= e(url('profile.php')) ?>">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="content-card mb-3">
            <div class="content-card-head">
                <div><h3>Change password</h3><p>Use a strong password you do not reuse elsewhere</p></div>
            </div>
            <div class="content-card-body">
                <form method="post" action="<?= e(url('profile.php')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="current_password">Current password</label>
                            <div class="position-relative">
                                <input class="form-control pe-5" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                                <button type="button" class="password-toggle" data-toggle-password="#current_password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_password">New password</label>
                            <div class="position-relative">
                                <input class="form-control pe-5" type="password" id="new_password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-toggle-password="#new_password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirm">Confirm new password</label>
                            <div class="position-relative">
                                <input class="form-control pe-5" type="password" id="password_confirm" name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-toggle-password="#password_confirm" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2">At least 8 characters, including an uppercase letter, a lowercase letter and a number.</div>

                    <button class="btn btn-navy mt-3" type="submit"><i class="fa-solid fa-key me-1"></i> Update password</button>
                </form>
            </div>
        </div>

        <?php if ($role === 'citizen'): ?>
            <div class="content-card">
                <div class="content-card-head"><div><h3>Quick links</h3><p>Continue working with your reports</p></div></div>
                <div class="content-card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('user/submit_report.php')) ?>"><i class="fa-solid fa-circle-plus me-1"></i> Report an issue</a>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('user/reports.php')) ?>"><i class="fa-solid fa-clipboard-list me-1"></i> My reports</a>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('notifications.php')) ?>"><i class="fa-solid fa-bell me-1"></i> Notifications</a>
                    </div>
                </div>
            </div>
        <?php elseif ($role === 'staff'): ?>
            <div class="content-card">
                <div class="content-card-head"><div><h3>Department</h3><p>Your service team</p></div></div>
                <div class="content-card-body">
                    <ul class="detail-list">
                        <li><span class="label">Team</span><span class="value"><?= e($user['department_name'] ?: 'Not assigned') ?></span></li>
                        <li><span class="label">Role</span><span class="value">Department Staff</span></li>
                        <li><span class="label">Work email</span><span class="value"><?= e($user['email']) ?></span></li>
                    </ul>
                    <a class="btn btn-soft btn-sm mt-3" href="<?= e(url('staff/reports.php')) ?>"><i class="fa-solid fa-list-check me-1"></i> My assigned cases</a>
                </div>
            </div>
        <?php else: ?>
            <div class="content-card">
                <div class="content-card-head"><div><h3>Administrator tools</h3><p>Jump back into platform management</p></div></div>
                <div class="content-card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/reports.php')) ?>"><i class="fa-solid fa-clipboard-list me-1"></i> Manage reports</a>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('admin/users.php')) ?>"><i class="fa-solid fa-users me-1"></i> Users</a>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('admin/analytics.php')) ?>"><i class="fa-solid fa-chart-line me-1"></i> Analytics</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/dashboard_footer.php'; ?>
