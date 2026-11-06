<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('citizen');
$userId = (int) $user['id'];

$pageTitle = 'My Reports';
$pageSubtitle = 'Search and follow every issue you have submitted.';
$activePage = 'reports';

$statusFilter = get('status');
$categoryFilter = get_int('category');
$search = get('search');
$sort = get('sort', 'newest');

if (!array_key_exists($statusFilter, statuses())) {
    $statusFilter = '';
}

$sortOptions = [
    'newest' => 'r.created_at DESC',
    'oldest' => 'r.created_at ASC',
    'updated' => 'r.updated_at DESC',
    'status' => "FIELD(r.status, 'pending', 'under_review', 'approved', 'assigned', 'in_progress', 'resolved', 'rejected'), r.created_at DESC",
];
$orderBy = $sortOptions[$sort] ?? $sortOptions['newest'];

$where = ['r.user_id = ?'];
$params = [$userId];
if ($statusFilter !== '') {
    $where[] = 'r.status = ?';
    $params[] = $statusFilter;
}
if ($categoryFilter > 0) {
    $where[] = 'r.category_id = ?';
    $params[] = $categoryFilter;
}
if ($search !== '') {
    $where[] = '(r.reference_no LIKE ? OR r.title LIKE ? OR r.description LIKE ? OR r.address LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

$whereSql = ' WHERE ' . implode(' AND ', $where);
$total = (int) fetch_value('SELECT COUNT(*) FROM reports r' . $whereSql, $params, 0);
$pagination = paginate($total, 10);
$offset = (int) $pagination['offset'];
$limit = (int) $pagination['per_page'];

$reports = fetch_all(
    "SELECT r.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
            d.name AS department_name, s.full_name AS staff_name,
            (SELECT ri.file_path FROM report_images ri
             WHERE ri.report_id = r.id AND ri.image_type = 'report'
             ORDER BY ri.id ASC LIMIT 1) AS cover_image
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     LEFT JOIN departments d ON d.id = r.department_id
     LEFT JOIN users s ON s.id = r.assigned_staff_id
     {$whereSql}
     ORDER BY {$orderBy}
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$categories = fetch_all('SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name');

$counts = [
    'total' => (int) fetch_value('SELECT COUNT(*) FROM reports WHERE user_id = ?', [$userId], 0),
    'open' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status NOT IN ('resolved','rejected')", [$userId], 0),
    'resolved' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'resolved'", [$userId], 0),
];

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>My report register</h2>
        <p><?= number_format($total) ?> report<?= $total === 1 ? '' : 's' ?> match the current view · <?= number_format($counts['open']) ?> open · <?= number_format($counts['resolved']) ?> resolved.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-primary btn-sm" href="<?= e(url('user/submit_report.php')) ?>"><i class="fa-solid fa-circle-plus me-1"></i> Report an issue</a>
    </div>
</div>

<form method="get" action="<?= e(url('user/reports.php')) ?>" class="filter-bar">
    <div class="row g-2 align-items-end">
        <div class="col-lg-4">
            <label class="form-label" for="search">Search my reports</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Reference, title or address…">
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
            <label class="form-label" for="sort">Sort by</label>
            <select class="form-select" id="sort" name="sort">
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Recently updated</option>
                <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Workflow status</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2 d-flex gap-2">
            <button class="btn btn-primary flex-grow-1" type="submit"><i class="fa-solid fa-filter me-1"></i> Apply</button>
            <a class="btn btn-soft" href="<?= e(url('user/reports.php')) ?>" title="Reset filters"><i class="fa-solid fa-rotate-left"></i></a>
        </div>
    </div>
</form>

<div class="content-card">
    <div class="content-card-head">
        <div>
            <h3>All my reports</h3>
            <p>Showing <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?> of <?= number_format($total) ?></p>
        </div>
        <span class="filter-count"><i class="fa-solid fa-circle-info me-1"></i> Open a report to see progress notes, photos and the map location.</span>
    </div>

    <?php if ($reports): ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned to</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($report['cover_image']): ?>
                                        <img class="rounded-cw" src="<?= e(url($report['cover_image'])) ?>" alt="" width="44" height="44" style="object-fit:cover;flex:0 0 auto;" loading="lazy">
                                    <?php else: ?>
                                        <span class="cell-icon" style="background:<?= e($report['category_color']) ?>1f;color:<?= e($report['category_color']) ?>;"><i class="fa-solid <?= e($report['category_icon']) ?>"></i></span>
                                    <?php endif; ?>
                                    <div class="min-w-0">
                                        <a class="table-title" href="<?= e(url('user/report_view.php?id=' . (int) $report['id'])) ?>"><?= e(short_text($report['title'], 42)) ?></a>
                                        <span class="table-sub"><?= e($report['reference_no']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="d-inline-flex align-items-center gap-2">
                                    <span class="dot" style="background:<?= e($report['category_color']) ?>"></span>
                                    <?= e($report['category_name']) ?>
                                </span>
                            </td>
                            <td><?= priority_badge($report['priority']) ?></td>
                            <td><?= status_badge($report['status']) ?></td>
                            <td>
                                <?php if ($report['department_name']): ?>
                                    <span class="d-block fs-7"><?= e($report['department_name']) ?></span>
                                    <span class="table-sub"><?= e($report['staff_name'] ?: 'Awaiting case officer') ?></span>
                                <?php else: ?>
                                    <span class="text-body-secondary fs-8">Not assigned yet</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="table-sub"><?= e(time_ago($report['updated_at'])) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('user/report_view.php?id=' . (int) $report['id'])) ?>" title="Open report"><i class="fa-solid fa-arrow-right"></i></a>
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
            <div class="empty-icon"><i class="fa-solid fa-magnifying-glass"></i></div>
            <h4>No reports found</h4>
            <p><?= $search !== '' || $statusFilter !== '' || $categoryFilter > 0 ? 'Try clearing your search or choosing different filters.' : 'You have not submitted a report yet. Spotted an issue? Let the city know.' ?></p>
            <?php if ($search !== '' || $statusFilter !== '' || $categoryFilter > 0): ?>
                <a class="btn btn-soft" href="<?= e(url('user/reports.php')) ?>">Reset filters</a>
            <?php else: ?>
                <a class="btn btn-primary" href="<?= e(url('user/submit_report.php')) ?>"><i class="fa-solid fa-circle-plus me-1"></i> Submit your first report</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
