<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

if (is_post()) {
    require_csrf();
    $action = post('action');

    if ($action === 'create' || $action === 'update') {
        $departmentId = post_int('department_id');
        $name = post('name');
        $slugInput = post('slug');
        $description = post('description');
        $email = strtolower(post('email'));
        $phone = post('phone');
        $isActive = post_bool('is_active') ? 1 : 0;
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Department name must be between 2 and 120 characters.';
        }
        if ($description !== '' && mb_strlen($description) > 1000) {
            $errors[] = 'The description must be 1,000 characters or fewer.';
        }
        if ($email !== '' && !valid_email($email)) {
            $errors[] = 'Please enter a valid department email address.';
        }
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{7,30}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }

        $duplicate = fetch_one(
            'SELECT id FROM departments WHERE name = ?' . ($action === 'update' ? ' AND id <> ?' : '') . ' LIMIT 1',
            $action === 'update' ? [$name, $departmentId] : [$name]
        );
        if ($duplicate) {
            $errors[] = 'A department with this name already exists.';
        }

        $baseSlug = slugify($slugInput !== '' ? $slugInput : $name);
        if ($baseSlug === '') {
            $baseSlug = 'department';
        }
        $slug = $baseSlug;
        $counter = 2;
        while (true) {
            $slugCheck = fetch_one(
                'SELECT id FROM departments WHERE slug = ?' . ($action === 'update' ? ' AND id <> ?' : '') . ' LIMIT 1',
                $action === 'update' ? [$slug, $departmentId] : [$slug]
            );
            if (!$slugCheck) {
                break;
            }
            $slug = $baseSlug . '-' . $counter++;
        }

        if ($action === 'update') {
            $existing = fetch_one('SELECT * FROM departments WHERE id = ?', [$departmentId]);
            if (!$existing) {
                $errors[] = 'The department could not be found.';
            }
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
            redirect('admin/departments.php');
        }

        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO departments (name, slug, description, email, phone, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$name, $slug, $description ?: null, $email ?: null, $phone ?: null, $isActive]);
            flash('success', 'Department “' . $name . '” created successfully.');
        } else {
            $stmt = db()->prepare(
                'UPDATE departments SET name = ?, slug = ?, description = ?, email = ?, phone = ?, is_active = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$name, $slug, $description ?: null, $email ?: null, $phone ?: null, $isActive, $departmentId]);
            flash('success', 'Department updated successfully.');
        }
        redirect('admin/departments.php');
    }

    if ($action === 'delete') {
        $departmentId = post_int('department_id');
        $department = fetch_one('SELECT * FROM departments WHERE id = ?', [$departmentId]);
        if (!$department) {
            flash('danger', 'Department not found.');
            redirect('admin/departments.php');
        }

        $staffCount = (int) fetch_value("SELECT COUNT(*) FROM users WHERE department_id = ? AND role = 'staff'", [$departmentId], 0);
        $reportCount = (int) fetch_value('SELECT COUNT(*) FROM reports WHERE department_id = ?', [$departmentId], 0);
        $assignmentCount = (int) fetch_value('SELECT COUNT(*) FROM report_assignments WHERE department_id = ?', [$departmentId], 0);

        if ($staffCount + $reportCount + $assignmentCount > 0) {
            flash('danger', 'This department is still linked to staff, reports or assignment history. Deactivate it instead of deleting it.');
        } else {
            $stmt = db()->prepare('DELETE FROM departments WHERE id = ?');
            $stmt->execute([$departmentId]);
            flash('success', 'Department deleted.');
        }
        redirect('admin/departments.php');
    }

    flash('danger', 'Unsupported action.');
    redirect('admin/departments.php');
}

$departments = fetch_all(
    "SELECT d.*,
            (SELECT COUNT(*) FROM users u WHERE u.department_id = d.id AND u.role = 'staff') AS staff_count,
            (SELECT COUNT(*) FROM reports r WHERE r.department_id = d.id) AS report_count,
            (SELECT COUNT(*) FROM reports r WHERE r.department_id = d.id AND r.status NOT IN ('resolved','rejected')) AS open_count,
            (SELECT COUNT(*) FROM reports r WHERE r.department_id = d.id AND r.status = 'resolved') AS resolved_count
     FROM departments d
     ORDER BY d.is_active DESC, d.name ASC"
);

$summary = [
    'total' => count($departments),
    'active' => count(array_filter($departments, static fn ($d) => (int) $d['is_active'] === 1)),
    'staff' => (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'staff' AND is_active = 1", [], 0),
    'open' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE department_id IS NOT NULL AND status NOT IN ('resolved','rejected')", [], 0),
];

$pageTitle = 'Departments';
$pageSubtitle = 'Manage the service teams responsible for resolving community reports.';
$activePage = 'departments';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Service departments</h2>
        <p><?= number_format($summary['active']) ?> active department<?= $summary['active'] === 1 ? '' : 's' ?> · <?= number_format($summary['staff']) ?> active staff</p>
    </div>
    <div class="page-heading-actions">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createDepartmentModal">
            <i class="fa-solid fa-plus me-1"></i> New department
        </button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="stat-card"><div class="stat-top"><div><div class="stat-label">Departments</div><div class="stat-value"><?= number_format($summary['total']) ?></div><div class="stat-note">Configured service teams</div></div><div class="stat-icon indigo"><i class="fa-solid fa-building-columns"></i></div></div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card"><div class="stat-top"><div><div class="stat-label">Active</div><div class="stat-value"><?= number_format($summary['active']) ?></div><div class="stat-note">Accepting new assignments</div></div><div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div></div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card"><div class="stat-top"><div><div class="stat-label">Department staff</div><div class="stat-value"><?= number_format($summary['staff']) ?></div><div class="stat-note">Active staff accounts</div></div><div class="stat-icon blue"><i class="fa-solid fa-helmet-safety"></i></div></div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card"><div class="stat-top"><div><div class="stat-label">Open assigned work</div><div class="stat-value"><?= number_format($summary['open']) ?></div><div class="stat-note">Reports with a department</div></div><div class="stat-icon amber"><i class="fa-solid fa-list-check"></i></div></div></div>
    </div>
</div>

<div class="row g-3">
    <?php if ($departments): foreach ($departments as $department): ?>
        <div class="col-md-6 col-xl-4">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                            <span class="stat-icon indigo"><i class="fa-solid fa-building-columns"></i></span>
                            <div class="min-w-0">
                                <h3 class="fs-7 fw-700 mb-0 text-truncate"><?= e($department['name']) ?></h3>
                                <span class="fs-8 text-body-secondary"><?= (int) $department['staff_count'] ?> staff member<?= (int) $department['staff_count'] === 1 ? '' : 's' ?></span>
                            </div>
                        </div>
                        <?php if ((int) $department['is_active']): ?>
                            <span class="badge-status status-resolved"><i class="fa-solid fa-circle"></i> Active</span>
                        <?php else: ?>
                            <span class="badge-status status-rejected"><i class="fa-solid fa-circle"></i> Inactive</span>
                        <?php endif; ?>
                    </div>
                    <p class="fs-8 text-body-secondary" style="min-height:38px;"><?= e(short_text($department['description'] ?: 'No description provided.', 105)) ?></p>
                    <div class="row g-2 mb-3 text-center">
                        <div class="col-4"><div class="bg-soft rounded-cw py-2"><strong class="d-block"><?= (int) $department['open_count'] ?></strong><span class="fs-8 text-body-secondary">Open</span></div></div>
                        <div class="col-4"><div class="bg-soft rounded-cw py-2"><strong class="d-block"><?= (int) $department['resolved_count'] ?></strong><span class="fs-8 text-body-secondary">Resolved</span></div></div>
                        <div class="col-4"><div class="bg-soft rounded-cw py-2"><strong class="d-block"><?= (int) $department['report_count'] ?></strong><span class="fs-8 text-body-secondary">Total</span></div></div>
                    </div>
                    <ul class="detail-list mb-3">
                        <li><span class="label"><i class="fa-regular fa-envelope me-1"></i>Email</span><span class="value"><?= e($department['email'] ?: '—') ?></span></li>
                        <li><span class="label"><i class="fa-solid fa-phone me-1"></i>Phone</span><span class="value"><?= e($department['phone'] ?: '—') ?></span></li>
                    </ul>
                    <div class="d-flex gap-2">
                        <button class="btn btn-soft btn-sm flex-grow-1" type="button" data-bs-toggle="modal" data-bs-target="#editDept<?= (int) $department['id'] ?>"><i class="fa-solid fa-pen me-1"></i> Edit</button>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('admin/reports.php?department=' . (int) $department['id'])) ?>" title="View department reports"><i class="fa-solid fa-list"></i></a>
                        <form method="post" action="<?= e(url('admin/departments.php')) ?>" class="d-inline" data-confirm="Delete this department? This is only possible when it has no linked staff or reports.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="department_id" value="<?= (int) $department['id'] ?>">
                            <button class="btn btn-soft btn-sm text-danger" type="submit" title="Delete department"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editDept<?= (int) $department['id'] ?>" tabindex="-1" aria-labelledby="editDeptLabel<?= (int) $department['id'] ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="<?= e(url('admin/departments.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="department_id" value="<?= (int) $department['id'] ?>">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editDeptLabel<?= (int) $department['id'] ?>">Edit department</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="deptName<?= (int) $department['id'] ?>">Department name *</label>
                                <input class="form-control" id="deptName<?= (int) $department['id'] ?>" name="name" value="<?= e($department['name']) ?>" maxlength="120" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="deptSlug<?= (int) $department['id'] ?>">Slug</label>
                                <input class="form-control" id="deptSlug<?= (int) $department['id'] ?>" name="slug" value="<?= e($department['slug']) ?>" maxlength="140">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="deptDescription<?= (int) $department['id'] ?>">Description</label>
                                <textarea class="form-control" id="deptDescription<?= (int) $department['id'] ?>" name="description" rows="3" maxlength="1000"><?= e($department['description']) ?></textarea>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label class="form-label" for="deptEmail<?= (int) $department['id'] ?>">Email</label>
                                    <input class="form-control" type="email" id="deptEmail<?= (int) $department['id'] ?>" name="email" value="<?= e($department['email']) ?>" maxlength="160">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="deptPhone<?= (int) $department['id'] ?>">Phone</label>
                                    <input class="form-control" type="tel" id="deptPhone<?= (int) $department['id'] ?>" name="phone" value="<?= e($department['phone']) ?>" maxlength="30">
                                </div>
                            </div>
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="deptActive<?= (int) $department['id'] ?>" name="is_active" value="1" <?= (int) $department['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label fs-7" for="deptActive<?= (int) $department['id'] ?>">Active department</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-soft btn-sm" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; else: ?>
        <div class="col-12"><div class="content-card"><div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-building-columns"></i></div><h4>No departments configured</h4><p>Create the first service department to begin routing reports.</p><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createDepartmentModal">Create department</button></div></div></div>
    <?php endif; ?>
</div>

<div class="modal fade" id="createDepartmentModal" tabindex="-1" aria-labelledby="createDepartmentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= e(url('admin/departments.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title" id="createDepartmentLabel">Create department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="newDeptName">Department name *</label>
                        <input class="form-control" id="newDeptName" name="name" maxlength="120" placeholder="e.g. Parks & Recreation" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newDeptDescription">Description</label>
                        <textarea class="form-control" id="newDeptDescription" name="description" rows="3" maxlength="1000" placeholder="What services does this department handle?"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-7">
                            <label class="form-label" for="newDeptEmail">Email</label>
                            <input class="form-control" type="email" id="newDeptEmail" name="email" maxlength="160" placeholder="department@civicwatch.local">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="newDeptPhone">Phone</label>
                            <input class="form-control" type="tel" id="newDeptPhone" name="phone" maxlength="30" placeholder="+1 555 0100">
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="newDeptActive" name="is_active" value="1" checked>
                        <label class="form-check-label fs-7" for="newDeptActive">Active department</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-soft btn-sm" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-plus me-1"></i>Create department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
