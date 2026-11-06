<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

$pageTitle = 'All Reports';
$pageSubtitle = 'Search, filter and manage every community issue.';
$activePage = 'reports';

$statusFilter = get('status');
$categoryFilter = get_int('category');
$departmentFilter = get_int('department');
$priorityFilter = get('priority');
$search = get('search');
$sort = get('sort', 'newest');

if (!array_key_exists($statusFilter, statuses())) {
    $statusFilter = '';
}
if (!in_array($priorityFilter, ['low', 'medium', 'high', 'critical'], true)) {
    $priorityFilter = '';
}
$sortOptions = [
    'newest' => 'r.created_at DESC',
    'oldest' => 'r.created_at ASC',
    'priority' => "FIELD(r.priority, 'critical', 'high', 'medium', 'low'), r.created_at DESC",
    'updated' => 'r.updated_at DESC',
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

$where = [];
$params = [];
if ($statusFilter !== '') {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($categoryFilter > 0) {
    $where[] = 'r.category_id = ?';
    $params[] = $categoryFilter;
}
if ($departmentFilter > 0) {
    $where[] = 'r.department_id = ?';
    $params[] = $departmentFilter;
}
if ($priorityFilter !== '') {
    $where[] = 'r.priority = ?';
    $params[] = $priorityFilter;
}
if ($search !== '') {
    $where[] = '(r.reference_no LIKE ? OR r.title LIKE ? OR r.description LIKE ? OR r.address LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countSql = 'SELECT COUNT(*) FROM reports r JOIN users u ON u.id = r.user_id' . $whereSql;
$total = (int) fetch_value($countSql, $params, 0);
$pagination = paginate($total, 12);
$offset = (int) $pagination['offset'];
$limit = (int) $pagination['per_page'];

$sql = "SELECT r.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon,
               u.full_name AS citizen_name, d.name AS department_name,
               s.full_name AS staff_name,
               (SELECT COUNT(*) FROM report_images ri WHERE ri.report_id = r.id AND ri.image_type = 'report') AS image_count
        FROM reports r
        JOIN categories c ON c.id = r.category_id
        JOIN users u ON u.id = r.user_id
        LEFT JOIN departments d ON d.id = r.department_id
        LEFT JOIN users s ON s.id = r.assigned_staff_id
        {$whereSql}
        ORDER BY {$orderBy}
        LIMIT {$limit} OFFSET {$offset}";
$reports = fetch_all($sql, $params);

$categories = fetch_all('SELECT id, name FROM categories ORDER BY name');
$departments = fetch_all('SELECT id, name FROM departments ORDER BY name');

$exportQuery = $_GET;
unset($exportQuery['page']);
$exportUrl = url('admin/export_reports.php') . ($exportQuery ? '?' . http_build_query($exportQuery) : '');

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Community reports</h2>
        <p><?= number_format($total) ?> report<?= $total === 1 ? '' : 's' ?> match the current view.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e($exportUrl) ?>"><i class="fa-solid fa-file-csv me-1"></i> Export CSV</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/analytics.php')) ?>"><i class="fa-solid fa-chart-line me-1"></i> Analytics</a>
    </div>
</div>

<form method="get" action="<?= e(url('admin/reports.php')) ?>" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-lg-3">
            <label class="form-label" for="search">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Reference, title, citizen, address…">
            </div>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (statuses() as $key => $meta): ?>
                    <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="category">Category</label>
            <select class="form-select" id="category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= $categoryFilter === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="department">Department</label>
            <select class="form-select" id="department" name="department">
                <option value="">All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= (int) $department['id'] ?>" <?= $departmentFilter === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-1">
            <label class="form-label" for="priority">Priority</label>
            <select class="form-select" id="priority" name="priority">
                <option value="">All</option>
                <?php foreach (['critical', 'high', 'medium', 'low'] as $priority): ?>
                    <option value="<?= e($priority) ?>" <?= $priorityFilter === $priority ? 'selected' : '' ?>><?= e(ucfirst($priority)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-1">
            <label class="form-label" for="sort">Sort</label>
            <select class="form-select" id="sort" name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Updated</option>
            </select>
        </div>
        <div class="col-12 col-lg-1 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="fa-solid fa-filter me-1"></i><span class="d-lg-none">Apply</span></button>
            <a class="btn btn-soft" href="<?= e(url('admin/reports.php')) ?>" title="Reset filters"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
    </div>
</form>

<div class="content-card">
    <div class="content-card-head">
        <div>
            <h3>Report register</h3>
            <p>Showing <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?> of <?= number_format($total) ?></p>
        </div>
        <span class="filter-count"><i class="fa-solid fa-circle-info me-1"></i> Click any report to review or assign it.</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Report</th>
                    <th>Category</th>
                    <th>Citizen</th>
                    <th>Department / Staff</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reports): foreach ($reports as $report): ?>
                    <tr>
                        <td>
                            <a class="table-title" href="<?= e(url('admin/report_view.php?id=' . (int) $report['id'])) ?>"><?= e(short_text($report['title'], 42)) ?></a>
                            <span class="table-sub"><?= e($report['reference_no']) ?><?= (int) $report['image_count'] > 0 ? ' · ' . (int) $report['image_count'] . ' photo' . ((int) $report['image_count'] === 1 ? '' : 's') : '' ?></span>
                        </td>
                        <td>
                            <span class="d-inline-flex align-items-center gap-2">
                                <span class="dot" style="background:<?= e($report['category_color']) ?>"></span>
                                <?= e($report['category_name']) ?>
                            </span>
                        </td>
                        <td><span class="fw-600"><?= e($report['citizen_name']) ?></span></td>
                        <td>
                            <?php if ($report['department_name']): ?>
                                <span class="d-block"><?= e($report['department_name']) ?></span>
                                <span class="table-sub"><?= e($report['staff_name'] ?: 'Unassigned staff') ?></span>
                            <?php else: ?>
                                <span class="text-body-secondary fs-8">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?= priority_badge($report['priority']) ?></td>
                        <td><?= status_badge($report['status']) ?></td>
                        <td><span class="table-sub"><?= e(format_date($report['created_at'], 'M j, Y')) ?><br><?= e(time_ago($report['created_at'])) ?></span></td>
                        <td class="text-end">
                            <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('admin/report_view.php?id=' . (int) $report['id'])) ?>" title="Review report"><i class="fa-solid fa-arrow-right"></i></a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
                                <h4>No reports found</h4>
                                <p>Try clearing your search or choosing different filters.</p>
                                <a class="btn btn-soft btn-sm" href="<?= e(url('admin/reports.php')) ?>">Reset filters</a>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination['pages'] > 1): ?>
        <div class="card-footer py-3"><?= pagination_html($pagination) ?></div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
