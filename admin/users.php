<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

if (is_post()) {
    require_csrf();
    $action = post('action');
    $targetId = post_int('user_id');
    $target = fetch_one('SELECT * FROM users WHERE id = ? LIMIT 1', [$targetId]);

    if (!$target) {
        flash('danger', 'That user could not be found.');
        redirect('admin/users.php');
    }

    if ($action === 'toggle' || $action === 'delete') {
        if ($targetId === (int) $user['id']) {
            flash('danger', 'You cannot deactivate or delete your own account.');
            redirect('admin/users.php');
        }

        if ($target['role'] === 'admin') {
            $adminCount = (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1", [], 0);
            if ($adminCount <= 1) {
                flash('danger', 'At least one active administrator account must remain.');
                redirect('admin/users.php');
            }
        }

        $openAssigned = (int) fetch_value(
            "SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status NOT IN ('resolved','rejected')",
            [$targetId],
            0
        );
        if ($target['role'] === 'staff' && $openAssigned > 0 && ($action === 'delete' || (int) $target['is_active'] === 1)) {
            flash('danger', 'This staff member still has ' . $openAssigned . ' open assigned report(s). Reassign those reports before deactivating or deleting the account.');
            redirect('admin/users.php');
        }
    }

    if ($action === 'toggle') {
        $newActive = (int) $target['is_active'] === 1 ? 0 : 1;
        $stmt = db()->prepare('UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newActive, $targetId]);
        flash('success', 'Account for ' . $target['full_name'] . ' has been ' . ($newActive ? 'activated' : 'deactivated') . '.');
        redirect('admin/users.php');
    }

    if ($action === 'delete') {
        $reportCount = (int) fetch_value('SELECT COUNT(*) FROM reports WHERE user_id = ?', [$targetId], 0);
        if ($reportCount > 0) {
            flash('danger', 'This citizen has ' . $reportCount . ' report(s) on record. Deactivate the account instead to preserve the report history.');
            redirect('admin/users.php');
        }

        try {
            $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$targetId]);
            flash('success', 'User account for ' . $target['full_name'] . ' has been deleted.');
        } catch (Throwable $e) {
            flash('danger', 'This account could not be deleted because it is referenced by other records. Deactivate it instead.');
        }
        redirect('admin/users.php');
    }

    flash('danger', 'Unsupported action.');
    redirect('admin/users.php');
}

$search = get('search');
$roleFilter = get('role');
$statusFilter = get('status');
$sort = get('sort', 'newest');
if (!in_array($roleFilter, ['admin', 'citizen', 'staff'], true)) {
    $roleFilter = '';
}
if (!in_array($statusFilter, ['active', 'inactive'], true)) {
    $statusFilter = '';
}
$sortOptions = [
    'newest' => 'u.created_at DESC',
    'name' => 'u.full_name ASC',
    'role' => "FIELD(u.role, 'admin', 'staff', 'citizen'), u.full_name ASC",
    'last_login' => 'u.last_login DESC',
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

$where = [];
$params = [];
if ($roleFilter !== '') {
    $where[] = 'u.role = ?';
    $params[] = $roleFilter;
}
if ($statusFilter !== '') {
    $where[] = 'u.is_active = ?';
    $params[] = $statusFilter === 'active' ? 1 : 0;
}
if ($search !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int) fetch_value('SELECT COUNT(*) FROM users u' . $whereSql, $params, 0);
$pagination = paginate($total, 12);
$limit = (int) $pagination['per_page'];
$offset = (int) $pagination['offset'];

$users = fetch_all(
    "SELECT u.*, d.name AS department_name,
            (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id) AS report_count,
            (SELECT COUNT(*) FROM reports r WHERE r.assigned_staff_id = u.id AND r.status NOT IN ('resolved','rejected')) AS open_assigned
     FROM users u
     LEFT JOIN departments d ON d.id = u.department_id
     {$whereSql}
     ORDER BY {$orderBy}
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$totals = [
    'all' => (int) fetch_value('SELECT COUNT(*) FROM users', [], 0),
    'citizens' => (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'citizen'", [], 0),
    'staff' => (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'staff'", [], 0),
    'admins' => (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'admin'", [], 0),
    'active' => (int) fetch_value('SELECT COUNT(*) FROM users WHERE is_active = 1', [], 0),
];

$pageTitle = 'User Management';
$pageSubtitle = 'Manage citizens, department staff and administrator access.';
$activePage = 'users';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Platform users</h2>
        <p><?= number_format($totals['all']) ?> total accounts · <?= number_format($totals['active']) ?> active</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/user_form.php')) ?>"><i class="fa-solid fa-user-plus me-1"></i> Add user</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div><div class="stat-label">Citizens</div><div class="stat-value"><?= number_format($totals['citizens']) ?></div><div class="stat-note">Registered residents</div></div>
                <div class="stat-icon teal"><i class="fa-solid fa-users"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div><div class="stat-label">Department staff</div><div class="stat-value"><?= number_format($totals['staff']) ?></div><div class="stat-note">Service team accounts</div></div>
                <div class="stat-icon blue"><i class="fa-solid fa-helmet-safety"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div><div class="stat-label">Administrators</div><div class="stat-value"><?= number_format($totals['admins']) ?></div><div class="stat-note">Full platform access</div></div>
                <div class="stat-icon indigo"><i class="fa-solid fa-user-shield"></i></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div><div class="stat-label">Active accounts</div><div class="stat-value"><?= number_format($totals['active']) ?></div><div class="stat-note"><?= number_format($totals['all'] - $totals['active']) ?> deactivated</div></div>
                <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
            </div>
        </div>
    </div>
</div>

<form method="get" action="<?= e(url('admin/users.php')) ?>" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-lg-5">
            <label class="form-label" for="search">Search users</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Name, email address or phone…">
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="role">Role</label>
            <select class="form-select" id="role" name="role">
                <option value="">All roles</option>
                <option value="citizen" <?= $roleFilter === 'citizen' ? 'selected' : '' ?>>Citizen</option>
                <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Staff</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrator</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Deactivated</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="sort">Sort by</label>
            <select class="form-select" id="sort" name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name</option>
                <option value="role" <?= $sort === 'role' ? 'selected' : '' ?>>Role</option>
                <option value="last_login" <?= $sort === 'last_login' ? 'selected' : '' ?>>Last sign-in</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-1 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="fa-solid fa-filter"></i></button>
            <a class="btn btn-soft" href="<?= e(url('admin/users.php')) ?>" title="Reset"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
    </div>
</form>

<div class="content-card">
    <div class="content-card-head">
        <div><h3>User directory</h3><p>Showing <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?> of <?= number_format($total) ?></p></div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Reports</th>
                    <th>Status</th>
                    <th>Last sign-in</th>
                    <th>Joined</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users): foreach ($users as $row): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="avatar avatar-sm"><?= e(initials($row['full_name'])) ?></span>
                                <div class="min-w-0">
                                    <span class="table-title text-truncate"><?= e($row['full_name']) ?><?= (int) $row['id'] === (int) $user['id'] ? ' <span class="badge text-bg-light fs-8">You</span>' : '' ?></span>
                                    <span class="table-sub d-block text-truncate"><?= e($row['email']) ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php
                            $roleClass = $row['role'] === 'admin' ? 'status-assigned' : ($row['role'] === 'staff' ? 'status-approved' : 'status-under-review');
                            ?>
                            <span class="badge-status <?= e($roleClass) ?>"><?= e(role_label($row['role'])) ?></span>
                        </td>
                        <td><?= e($row['department_name'] ?: '—') ?></td>
                        <td>
                            <?php if ($row['role'] === 'citizen'): ?>
                                <span class="fw-600"><?= (int) $row['report_count'] ?></span>
                            <?php elseif ($row['role'] === 'staff'): ?>
                                <span class="fw-600"><?= (int) $row['open_assigned'] ?></span> <span class="table-sub">open</span>
                            <?php else: ?>
                                <span class="text-body-secondary">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $row['is_active']): ?>
                                <span class="badge-status status-resolved"><i class="fa-solid fa-circle"></i> Active</span>
                            <?php else: ?>
                                <span class="badge-status status-rejected"><i class="fa-solid fa-circle"></i> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="table-sub"><?= $row['last_login'] ? e(time_ago($row['last_login'])) : 'Never' ?></span></td>
                        <td><span class="table-sub"><?= e(format_date($row['created_at'], 'M j, Y')) ?></span></td>
                        <td class="text-end">
                            <div class="table-actions">
                                <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('admin/user_form.php?id=' . (int) $row['id'])) ?>" title="Edit user"><i class="fa-solid fa-pen"></i></a>
                                <?php if ((int) $row['id'] !== (int) $user['id']): ?>
                                    <form method="post" action="<?= e(url('admin/users.php')) ?>" class="d-inline" data-confirm="Are you sure you want to <?= (int) $row['is_active'] ? 'deactivate' : 'activate' ?> this account?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                        <button class="btn btn-soft btn-sm btn-icon" type="submit" title="<?= (int) $row['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                            <i class="fa-solid <?= (int) $row['is_active'] ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                        </button>
                                    </form>
                                    <?php if ($row['role'] !== 'citizen' || (int) $row['report_count'] === 0): ?>
                                        <form method="post" action="<?= e(url('admin/users.php')) ?>" class="d-inline" data-confirm="Permanently delete this account? This action cannot be undone.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                            <button class="btn btn-soft btn-sm btn-icon text-danger" type="submit" title="Delete user"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="8"><div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-users"></i></div><h4>No users found</h4><p>Try a different search or filter.</p><a class="btn btn-soft btn-sm" href="<?= e(url('admin/users.php')) ?>">Reset filters</a></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['pages'] > 1): ?><div class="card-footer py-3"><?= pagination_html($pagination) ?></div><?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
