<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('staff');
$staffId = (int) $user['id'];

$pageTitle = 'Assigned Reports';
$pageSubtitle = 'Review your caseload, add progress notes and record resolutions.';
$activePage = 'reports';

$statusFilter = get('status');
$categoryFilter = get_int('category');
$priorityFilter = get('priority');
$search = get('search');
$sort = get('sort', 'priority');

if (!array_key_exists($statusFilter, statuses())) {
    $statusFilter = '';
}
if (!in_array($priorityFilter, ['low', 'medium', 'high', 'critical'], true)) {
    $priorityFilter = '';
}

$sortOptions = [
    'priority' => "FIELD(r.priority, 'critical', 'high', 'medium', 'low'), r.updated_at DESC",
    'newest' => 'r.created_at DESC',
    'oldest' => 'r.created_at ASC',
    'updated' => 'r.updated_at DESC',
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['priority'];

$where = ['r.assigned_staff_id = ?'];
$params = [$staffId];
if ($statusFilter !== '') {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($categoryFilter > 0) {
    $where[] = 'r.category_id = ?';
    $params[] = $categoryFilter;
}
if ($priorityFilter !== '') {
    $where[] = 'r.priority = ?';
    $params[] = $priorityFilter;
}
if ($search !== '') {
    $where[] = '(r.reference_no LIKE ? OR r.title LIKE ? OR r.description LIKE ? OR r.address LIKE ? OR u.full_name LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}

$whereSql = ' WHERE ' . implode(' AND ', $where);
$total = (int) fetch_value(
    'SELECT COUNT(*) FROM reports r JOIN users u ON u.id = r.user_id' . $whereSql,
    $params,
    0
);
$pagination = paginate($total, 12);
$offset = (int) $pagination['offset'];
$limit = (int) $pagination['per_page'];

$reports = fetch_all(
    "SELECT r.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
            u.full_name AS citizen_name,
            (SELECT COUNT(*) FROM report_images ri WHERE ri.report_id = r.id AND ri.image_type = 'report') AS image_count,
            (SELECT COUNT(*) FROM report_images ri WHERE ri.report_id = r.id AND ri.image_type = 'evidence') AS evidence_count
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     JOIN users u ON u.id = r.user_id
     {$whereSql}
     ORDER BY {$orderBy}
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$categories = fetch_all('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name');

$counts = [
    'assigned' => (int) fetch_value('SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ?', [$staffId], 0),
    'open' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status NOT IN ('resolved','rejected')", [$staffId], 0),
    'in_progress' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status = 'in_progress'", [$staffId], 0),
    'resolved' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status = 'resolved'", [$staffId], 0),
];

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>My assigned reports</h2>
        <p><?= number_format($total) ?> case<?= $total === 1 ? '' : 's' ?> match the current view · <?= number_format($counts['open']) ?> open · <?= number_format($counts['resolved']) ?> resolved.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e(url('staff/index.php')) ?>"><i class="fa-solid fa-chart-pie me-1"></i> Dashboard</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('staff/reports.php?status=in_progress')) ?>"><i class="fa-solid fa-person-digging me-1"></i> In progress (<?= number_format($counts['in_progress']) ?>)</a>
    </div>
</div>

<form method="get" action="<?= e(url('staff/reports.php')) ?>" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4">
            <label class="form-label" for="search">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Reference, title, citizen or address…">
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
                <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority</option>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Updated</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="fa-solid fa-filter me-1"></i> Apply</button>
            <a class="btn btn-soft" href="<?= e(url('staff/reports.php')) ?>" title="Reset filters"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
    </div>
</form>

<div class="content-card">
    <div class="content-card-head">
        <div>
            <h3>Case queue</h3>
            <p>Showing <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?> of <?= number_format($total) ?></p>
        </div>
        <span class="filter-count"><i class="fa-solid fa-circle-info me-1"></i> Add a progress note or completion evidence from the report page.</span>
    </div>

    <?php if ($reports): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Citizen</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Evidence</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                <a class="table-title" href="<?= e(url('staff/report_view.php?id=' . (int) $report['id'])) ?>"><?= e(short_text($report['title'], 40)) ?></a>
                                <span class="table-sub"><?= e($report['reference_no']) ?><?= (int) $report['image_count'] > 0 ? ' · ' . (int) $report['image_count'] . ' citizen photo' . ((int) $report['image_count'] === 1 ? '' : 's') : '' ?></span>
                            </td>
                            <td><span class="fw-600 fs-7"><?= e($report['citizen_name']) ?></span></td>
                            <td>
                                <span class="d-inline-flex align-items-center gap-2">
                                    <span class="dot" style="background:<?= e($report['category_color']) ?>"></span>
                                    <?= e($report['category_name']) ?>
                                </span>
                            </td>
                            <td><?= priority_badge($report['priority']) ?></td>
                            <td><?= status_badge($report['status']) ?></td>
                            <td>
                                <?php if ((int) $report['evidence_count'] > 0): ?>
                                    <span class="badge text-bg-success"><i class="fa-solid fa-camera-retro me-1"></i><?= (int) $report['evidence_count'] ?></span>
                                <?php else: ?>
                                    <span class="text-body-secondary fs-8">None</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="table-sub"><?= e(time_ago($report['updated_at'])) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('staff/report_view.php?id=' . (int) $report['id'])) ?>" title="Open case"><i class="fa-solid fa-arrow-right"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination['pages'] > 1): ?>
            <div class="card-footer py-3"><?= pagination_html($pagination) ?></div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
            <h4>No reports found</h4>
            <p><?= $search !== '' || $statusFilter !== '' || $categoryFilter > 0 || $priorityFilter !== '' ? 'Try clearing your search or choosing different filters.' : 'You have no assigned reports yet. The civic office will notify you when a case is routed to your department.' ?></p>
            <?php if ($search !== '' || $statusFilter !== '' || $categoryFilter > 0 || $priorityFilter !== ''): ?>
                <a class="btn btn-soft" href="<?= e(url('staff/reports.php')) ?>">Reset filters</a>
            <?php else: ?>
                <a class="btn btn-soft" href="<?= e(url('staff/index.php')) ?>">Back to dashboard</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
