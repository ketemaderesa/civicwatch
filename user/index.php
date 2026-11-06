<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('citizen');
$userId = (int) $user['id'];

$pageTitle = 'Citizen Dashboard';
$pageSubtitle = 'Track your reports and see how the city is responding.';
$activePage = 'dashboard';

$totalReports = (int) fetch_value('SELECT COUNT(*) FROM reports WHERE user_id = ?', [$userId], 0);
$openReports = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status NOT IN ('resolved','rejected')", [$userId], 0);
$resolvedReports = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'resolved'", [$userId], 0);
$awaitingReview = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status IN ('pending','under_review')", [$userId], 0);
$inProgress = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status IN ('assigned','in_progress')", [$userId], 0);
$rejectedReports = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE user_id = ? AND status = 'rejected'", [$userId], 0);

$recentReports = fetch_all(
    "SELECT r.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
            d.name AS department_name,
            (SELECT ri.file_path FROM report_images ri
             WHERE ri.report_id = r.id AND ri.image_type = 'report'
             ORDER BY ri.id ASC LIMIT 1) AS cover_image
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     LEFT JOIN departments d ON d.id = r.department_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC
     LIMIT 6",
    [$userId]
);

$statusRows = fetch_all(
    'SELECT status, COUNT(*) AS total FROM reports WHERE user_id = ? GROUP BY status',
    [$userId]
);
$statusCounts = array_fill_keys(array_keys(statuses()), 0);
foreach ($statusRows as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}

$recentActivity = fetch_all(
    "SELECT ru.*, r.reference_no, r.title, r.id AS report_id
     FROM report_updates ru
     JOIN reports r ON r.id = ru.report_id
     WHERE r.user_id = ?
     ORDER BY ru.created_at DESC, ru.id DESC
     LIMIT 6",
    [$userId]
);

$announcements = fetch_all(
    "SELECT * FROM announcements
     WHERE is_published = 1 AND audience IN ('all', 'citizens')
     ORDER BY created_at DESC
     LIMIT 3"
);

$recentlyResolved = fetch_one(
    "SELECT r.*, c.name AS category_name, c.icon AS category_icon
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     WHERE r.user_id = ? AND r.status = 'resolved'
     ORDER BY r.resolved_at DESC
     LIMIT 1",
    [$userId]
);

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h2>
        <p>Here is the latest progress on the issues you have reported.</p>
    </div>
    <div class="page-heading-actions">
        <?php if ($awaitingReview > 0): ?>
            <a class="btn btn-soft btn-sm" href="<?= e(url('user/reports.php?status=pending')) ?>"><i class="fa-solid fa-hourglass-half me-1"></i> <?= $awaitingReview ?> awaiting review</a>
        <?php endif; ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url('user/submit_report.php')) ?>"><i class="fa-solid fa-circle-plus me-1"></i> Report an issue</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">My reports</div>
                    <div class="stat-value"><?= number_format($totalReports) ?></div>
                    <div class="stat-note"><?= number_format($openReports) ?> still open</div>
                </div>
                <div class="stat-icon blue"><i class="fa-solid fa-clipboard-list"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Awaiting review</div>
                    <div class="stat-value"><?= number_format($awaitingReview) ?></div>
                    <div class="stat-note">The civic office is checking these</div>
                </div>
                <div class="stat-icon amber"><i class="fa-solid fa-magnifying-glass"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Work in progress</div>
                    <div class="stat-value"><?= number_format($inProgress) ?></div>
                    <div class="stat-note">Assigned to a service team</div>
                </div>
                <div class="stat-icon indigo"><i class="fa-solid fa-person-digging"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Resolved</div>
                    <div class="stat-value"><?= number_format($resolvedReports) ?></div>
                    <div class="stat-note"><?= $rejectedReports > 0 ? number_format($rejectedReports) . ' closed as rejected' : 'Thank you for helping your community' ?></div>
                </div>
                <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </div>
    </div>
</div>

<?php if ($recentlyResolved): ?>
    <div class="info-banner mb-3">
        <i class="fa-solid fa-circle-check"></i>
        <div>
            <strong>Recently resolved:</strong> <?= e($recentlyResolved['title']) ?>
            <span class="d-block mt-1"><?= e($recentlyResolved['category_name']) ?> · resolved <?= e(time_ago($recentlyResolved['resolved_at'])) ?>.
                <a class="fw-600 text-teal" href="<?= e(url('user/report_view.php?id=' . (int) $recentlyResolved['id'])) ?>">View the completion details <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </span>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>My latest reports</h3>
                    <p>Every report you have submitted, newest first</p>
                </div>
                <a class="btn btn-soft btn-sm" href="<?= e(url('user/reports.php')) ?>">View all <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <?php if ($recentReports): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Submitted</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReports as $report): ?>
                                <?php $percent = progress_percent($report['status']); ?>
                                <tr>
                                    <td>
                                        <a class="table-title" href="<?= e(url('user/report_view.php?id=' . (int) $report['id'])) ?>"><?= e(short_text($report['title'], 44)) ?></a>
                                        <span class="table-sub"><?= e($report['reference_no']) ?><?= $report['department_name'] ? ' · ' . e($report['department_name']) : '' ?></span>
                                    </td>
                                    <td>
                                        <span class="d-inline-flex align-items-center gap-2">
                                            <span class="dot" style="background:<?= e($report['category_color']) ?>"></span>
                                            <?= e($report['category_name']) ?>
                                        </span>
                                    </td>
                                    <td><?= status_badge($report['status']) ?></td>
                                    <td style="min-width:110px;">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:5px;">
                                                <div class="progress-bar <?= $report['status'] === 'rejected' ? 'bg-danger' : '' ?>" style="width:<?= $percent ?>%"></div>
                                            </div>
                                            <span class="fs-8 text-body-secondary"><?= $percent ?>%</span>
                                        </div>
                                    </td>
                                    <td><span class="table-sub"><?= e(time_ago($report['created_at'])) ?></span></td>
                                    <td class="text-end">
                                        <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('user/report_view.php?id=' . (int) $report['id'])) ?>" title="Open report"><i class="fa-solid fa-arrow-right"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-camera"></i></div>
                    <h4>You have not reported an issue yet</h4>
                    <p>Spotted a pothole, a water leak or a streetlight that is out? Let the city know in under a minute.</p>
                    <a class="btn btn-primary" href="<?= e(url('user/submit_report.php')) ?>"><i class="fa-solid fa-circle-plus me-1"></i> Submit your first report</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>Progress snapshot</h3>
                    <p>Your reports by workflow status</p>
                </div>
            </div>
            <div class="content-card-body">
                <?php if ($totalReports > 0): ?>
                    <div class="chart-wrap-sm"><canvas id="myStatusChart"></canvas></div>
                    <div class="chart-legend justify-content-center mt-2" id="myStatusLegend"></div>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="empty-icon"><i class="fa-solid fa-chart-pie"></i></div>
                        <p class="mb-0">Your status chart will appear after your first report.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer py-2 text-end">
                <a class="small fw-600 text-teal" href="<?= e(url('user/reports.php')) ?>">Open my report register <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>Community announcements</h3>
                    <p>Updates from the CivicWatch office</p>
                </div>
            </div>
            <div class="content-card-body">
                <?php if ($announcements): ?>
                    <div class="row g-3">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="col-md-6">
                                <article class="announcement-card">
                                    <div class="announcement-icon"><i class="fa-solid fa-bullhorn"></i></div>
                                    <h4><?= e($announcement['title']) ?></h4>
                                    <p><?= nl2br(e(short_text($announcement['content'], 170))) ?></p>
                                    <time><i class="fa-regular fa-clock me-1"></i><?= e(time_ago($announcement['created_at'])) ?></time>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-body-secondary fs-7 mb-0">There are no announcements at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>Latest activity on my reports</h3>
                    <p>Progress notes from the civic team</p>
                </div>
            </div>
            <div class="content-card-body pt-1">
                <ul class="activity-list">
                    <?php if ($recentActivity): foreach ($recentActivity as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon"><i class="fa-solid <?= e(status_meta($activity['status'] ?? 'pending')['icon']) ?>"></i></div>
                            <div class="min-w-0">
                                <strong><?= e($activity['status'] ? status_label($activity['status']) : 'Progress note') ?> · <?= e($activity['reference_no']) ?></strong>
                                <span class="d-block text-truncate"><?= e(short_text($activity['note'] ?: $activity['title'], 62)) ?></span>
                            </div>
                            <time><?= e(time_ago($activity['created_at'])) ?></time>
                        </li>
                    <?php endforeach; else: ?>
                        <li class="text-body-secondary fs-7">No activity has been recorded yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if ($totalReports > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined' || !window.CivicWatch) return;
    var palette = CivicWatch.palette();
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = palette.text;

    var canvas = document.getElementById('myStatusChart');
    var labels = <?= json_encode(array_map(static fn ($key) => status_meta($key)['label'], array_keys($statusCounts))) ?>;
    var data = <?= json_encode(array_values($statusCounts)) ?>;
    var colors = ['#e0a02a', '#4c9de0', '#0f9b8e', '#5b58c9', '#2a76d2', '#1a9e64', '#d64545'];

    if (canvas) {
        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{ data: data, backgroundColor: colors, borderWidth: 3, borderColor: getComputedStyle(document.body).backgroundColor || '#fff', hoverOffset: 5 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } }
        });

        var legend = document.getElementById('myStatusLegend');
        if (legend) {
            labels.forEach(function (label, index) {
                if (data[index] === 0) return;
                var item = document.createElement('span');
                item.className = 'legend-item';
                item.innerHTML = '<span class="legend-dot" style="background:' + colors[index] + '"></span>' + label;
                legend.appendChild(item);
            });
        }
    }
});
</script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
