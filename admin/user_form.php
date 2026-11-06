<?php
require_once __DIR__ . '/../includes/functions.php';
$currentAdmin = require_role('admin');

$editId = get_int('id');
$target = $editId > 0 ? fetch_one('SELECT * FROM users WHERE id = ? LIMIT 1', [$editId]) : null;
if ($editId > 0 && !$target) {
    abort(404, 'The requested user account could not be found.');
}
$isEdit = $target !== null;

if (is_post()) {
    require_csrf();
    $postId = post_int('user_id');
    if ($postId > 0) {
        $editId = $postId;
        $target = fetch_one('SELECT * FROM users WHERE id = ? LIMIT 1', [$editId]);
        if (!$target) {
            abort(404, 'The requested user account could not be found.');
        }
        $isEdit = true;
    }

    $fullName = post('full_name');
    $email = strtolower(post('email'));
    $phone = post('phone');
    $address = post('address');
    $role = post('role');
    $departmentId = post_int('department_id');
    $password = post('password');
    $passwordConfirm = post('password_confirm');
    $isActive = post_bool('is_active') ? 1 : 0;

    $errors = [];
    if (mb_strlen($fullName) < 3 || mb_strlen($fullName) > 120) {
        $errors[] = 'Full name must be between 3 and 120 characters.';
    }
    if (!valid_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
        $errors[] = 'Please enter a valid phone number.';
    }
    if (mb_strlen($address) > 255) {
        $errors[] = 'The address must be 255 characters or fewer.';
    }
    if (!in_array($role, ['admin', 'citizen', 'staff'], true)) {
        $errors[] = 'Please select a valid user role.';
    }

    if ($role === 'staff') {
        $department = fetch_one('SELECT id FROM departments WHERE id = ? AND is_active = 1', [$departmentId]);
        if (!$department) {
            $errors[] = 'Department staff must be assigned to an active department.';
        }
    } else {
        $departmentId = 0;
    }

    if (!$isEdit && $password === '') {
        $errors[] = 'A temporary password is required for a new account.';
    }
    if ($password !== '' || !$isEdit) {
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errors[] = 'The password must be between 8 and 72 characters.';
        } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $errors[] = 'The password must include an uppercase letter, a lowercase letter and a number.';
        }
        if ($password !== $passwordConfirm) {
            $errors[] = 'The password confirmation does not match.';
        }
    }

    $duplicate = fetch_one('SELECT id FROM users WHERE email = ?' . ($isEdit ? ' AND id <> ?' : '') . ' LIMIT 1', $isEdit ? [$email, $editId] : [$email]);
    if ($duplicate) {
        $errors[] = 'Another account already uses this email address.';
    }

    if ($isEdit) {
        if ((int) $target['id'] === (int) $currentAdmin['id']) {
            if ($role !== 'admin') {
                $errors[] = 'You cannot change your own administrator role.';
            }
            if ($isActive !== 1) {
                $errors[] = 'You cannot deactivate your own account.';
            }
        }

        if ($target['role'] === 'admin' && ($role !== 'admin' || $isActive !== 1)) {
            $activeAdmins = (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1", [], 0);
            if ($activeAdmins <= 1) {
                $errors[] = 'At least one active administrator account must remain.';
            }
        }

        if ($target['role'] === 'staff' && $role !== 'staff') {
            $openAssigned = (int) fetch_value(
                "SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status NOT IN ('resolved','rejected')",
                [$editId],
                0
            );
            if ($openAssigned > 0) {
                $errors[] = 'This staff member has ' . $openAssigned . ' open assigned report(s). Reassign them before changing the role.';
            }
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('danger', $error);
        }
        flash_old([
            'full_name' => $fullName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'role' => $role,
            'department_id' => (string) $departmentId,
            'is_active' => (string) $isActive,
        ]);
        redirect('admin/user_form.php' . ($isEdit ? '?id=' . $editId : ''));
    }

    try {
        if ($isEdit) {
            $sql = 'UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, role = ?, department_id = ?, is_active = ?, updated_at = NOW()';
            $params = [
                $fullName,
                $email,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $role,
                $role === 'staff' ? $departmentId : null,
                $isActive,
            ];
            if ($password !== '') {
                $sql .= ', password_hash = ?';
                $params[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $sql .= ' WHERE id = ?';
            $params[] = $editId;
            $stmt = db()->prepare($sql);
            $stmt->execute($params);
            clear_old();
            flash('success', 'User account updated successfully.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO users (full_name, email, phone, address, password_hash, role, department_id, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $fullName,
                $email,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                password_hash($password, PASSWORD_DEFAULT),
                $role,
                $role === 'staff' ? $departmentId : null,
                $isActive,
            ]);
            $newId = (int) db()->lastInsertId();
            if ($role !== 'admin') {
                send_notification(
                    $newId,
                    'Your CivicWatch account is ready',
                    'An administrator has created your ' . role_label($role) . ' account. Use the credentials provided to you to sign in.',
                    null
                );
            }
            clear_old();
            flash('success', 'New ' . role_label($role) . ' account created for ' . $fullName . '.');
        }
        redirect('admin/users.php');
    } catch (Throwable $e) {
        flash('danger', 'The account could not be saved. Please try again.');
        redirect('admin/user_form.php' . ($isEdit ? '?id=' . $editId : ''));
    }
}

$oldRole = old('role', $target['role'] ?? 'citizen');
$form = [
    'full_name' => old('full_name', $target['full_name'] ?? ''),
    'email' => old('email', $target['email'] ?? ''),
    'phone' => old('phone', $target['phone'] ?? ''),
    'address' => old('address', $target['address'] ?? ''),
    'role' => $oldRole,
    'department_id' => (int) old('department_id', (string) ($target['department_id'] ?? 0)),
    'is_active' => old('is_active', (string) ($target['is_active'] ?? 1)),
];
clear_old();

$departments = fetch_all('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name');

$pageTitle = $isEdit ? 'Edit User' : 'Add User';
$pageSubtitle = $isEdit ? 'Update account details, role and access.' : 'Create a citizen, staff or administrator account.';
$activePage = 'users';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('admin/users.php')) ?>" title="Back to users"><i class="fa-solid fa-arrow-left"></i></a>
            <span class="fs-8 text-body-secondary text-uppercase fw-700">User management</span>
        </div>
        <h2><?= $isEdit ? 'Edit ' . e($target['full_name']) : 'Add a new user' ?></h2>
        <p><?= $isEdit ? 'Update profile details or access for this account.' : 'Create an account and provide the temporary password to the user securely.' ?></p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="content-card">
            <div class="content-card-head">
                <div><h3>Account information</h3><p>Fields marked with an asterisk are required.</p></div>
            </div>
            <div class="content-card-body">
                <form method="post" action="<?= e(url('admin/user_form.php' . ($isEdit ? '?id=' . $editId : ''))) ?>" novalidate>
                    <?= csrf_field() ?>
                    <?php if ($isEdit): ?><input type="hidden" name="user_id" value="<?= (int) $editId ?>"><?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="full_name">Full name *</label>
                            <input class="form-control" type="text" id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" maxlength="120" required autofocus>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="phone">Phone</label>
                            <input class="form-control" type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="30">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="email">Email address *</label>
                            <input class="form-control" type="email" id="email" name="email" value="<?= e($form['email']) ?>" maxlength="160" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="role">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="citizen" <?= $form['role'] === 'citizen' ? 'selected' : '' ?>>Citizen</option>
                                <option value="staff" <?= $form['role'] === 'staff' ? 'selected' : '' ?>>Department staff</option>
                                <option value="admin" <?= $form['role'] === 'admin' ? 'selected' : '' ?>>Administrator</option>
                            </select>
                        </div>
                        <div class="col-md-7" id="departmentField">
                            <label class="form-label" for="department_id">Department *</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Choose department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= $form['department_id'] === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Required for department staff accounts.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="address">Home address</label>
                            <input class="form-control" type="text" id="address" name="address" value="<?= e($form['address']) ?>" maxlength="255" placeholder="Street, neighbourhood, city">
                        </div>
                    </div>

                    <hr class="my-4">
                    <h4 class="fs-7 fw-700 mb-1"><?= $isEdit ? 'Reset password' : 'Temporary password' ?></h4>
                    <p class="fs-8 text-body-secondary mb-3"><?= $isEdit ? 'Leave both fields blank to keep the current password.' : 'Use at least 8 characters with uppercase, lowercase and a number.' ?></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="password"><?= $isEdit ? 'New password' : 'Password *' ?></label>
                            <div class="position-relative">
                                <input class="form-control pe-5" type="password" id="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                                <button type="button" class="password-toggle" data-toggle-password="#password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="password_confirm">Confirm password</label>
                            <div class="position-relative">
                                <input class="form-control pe-5" type="password" id="password_confirm" name="password_confirm" minlength="8" maxlength="72" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                                <button type="button" class="password-toggle" data-toggle-password="#password_confirm" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" <?= (int) $form['is_active'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label fs-7" for="is_active">Account is active and can sign in</label>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i><?= $isEdit ? 'Save changes' : 'Create account' ?></button>
                        <a class="btn btn-soft" href="<?= e(url('admin/users.php')) ?>">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="side-card">
            <div class="side-card-head"><i class="fa-solid fa-shield-halved"></i> Access guidance</div>
            <div class="side-card-body">
                <div class="d-flex gap-2 mb-3">
                    <span class="stat-icon teal" style="width:36px;height:36px;font-size:.8rem;"><i class="fa-solid fa-user"></i></span>
                    <div><strong class="fs-7 d-block">Citizen</strong><span class="fs-8 text-body-secondary">Can submit reports, upload photos and track their own issues.</span></div>
                </div>
                <div class="d-flex gap-2 mb-3">
                    <span class="stat-icon blue" style="width:36px;height:36px;font-size:.8rem;"><i class="fa-solid fa-helmet-safety"></i></span>
                    <div><strong class="fs-7 d-block">Department staff</strong><span class="fs-8 text-body-secondary">Can view and update reports assigned to them by an administrator.</span></div>
                </div>
                <div class="d-flex gap-2">
                    <span class="stat-icon indigo" style="width:36px;height:36px;font-size:.8rem;"><i class="fa-solid fa-user-shield"></i></span>
                    <div><strong class="fs-7 d-block">Administrator</strong><span class="fs-8 text-body-secondary">Full access to users, reports, departments, analytics and announcements.</span></div>
                </div>
            </div>
        </div>

        <?php if ($isEdit): ?>
            <div class="side-card">
                <div class="side-card-head"><i class="fa-solid fa-clock-rotate-left"></i> Account history</div>
                <div class="side-card-body">
                    <ul class="detail-list">
                        <li><span class="label">Created</span><span class="value"><?= e(format_date($target['created_at'], 'M j, Y')) ?></span></li>
                        <li><span class="label">Last sign-in</span><span class="value"><?= $target['last_login'] ? e(time_ago($target['last_login'])) : 'Never' ?></span></li>
                        <li><span class="label">Reports submitted</span><span class="value"><?= (int) fetch_value('SELECT COUNT(*) FROM reports WHERE user_id = ?', [$editId], 0) ?></span></li>
                        <li><span class="label">Open assignments</span><span class="value"><?= (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status NOT IN ('resolved','rejected')", [$editId], 0) ?></span></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <div class="info-banner mt-3">
            <i class="fa-solid fa-circle-info"></i>
            <span>Never send passwords over public channels. Ask users to change their temporary password from their profile after signing in.</span>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var role = document.getElementById('role');
    var departmentField = document.getElementById('departmentField');
    var department = document.getElementById('department_id');
    if (!role || !departmentField || !department) return;

    function syncDepartment() {
        var isStaff = role.value === 'staff';
        departmentField.style.display = isStaff ? '' : 'none';
        department.required = isStaff;
        if (!isStaff) department.value = '';
    }
    role.addEventListener('change', syncDepartment);
    syncDepartment();
});
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
