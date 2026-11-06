<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('staff');
$staffId = (int) $user['id'];

$pageTitle = 'Staff Dashboard';
$pageSubtitle = 'Your assigned cases and the work that needs attention.';
$activePage = 'dashboard';

$totalAssigned = (int) fetch_value('SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ?', [$staffId], 0);
$openCases = (int) fetch_value(
    "SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status NOT IN ('resolved','rejected')",
    [$staffId],
    0
);
$inProgress = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status = 'in_progress'", [$staffId], 0);
$resolvedCases = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND status = 'resolved'", [$staffId], 0);
$urgentOpen = (int) fetch_value(
    "SELECT COUNT(*) FROM reports WHERE assigned_staff_id = ? AND priority IN ('high','critical') AND status NOT IN ('resolved','rejected')",
    [$staffId],
    0
);
$resolvedThisMonth = (int) fetch_value(
    "SELECT COUNT(*) FROM reports
     WHERE assigned_staff_id = ? AND status = 'resolved'
       AND resolved_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
    [$staffId],
    0
);
$avgResolutionDays = fetch_value(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) / 24
     FROM reports
     WHERE assigned_staff_id = ? AND status = 'resolved' AND resolved_at IS NOT NULL",
    [$staffId],
    null
);

$recentReports = fetch_all(
    "SELECT r.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
            u.full_name AS citizen_name
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     JOIN users u ON u.id = r.user_id
     WHERE r.assigned_staff_id = ?
     ORDER BY FIELD(r.priority, 'critical', 'high', 'medium', 'low'), r.updated_at DESC
     LIMIT 6",
    [$staffId]
);

$statusRows = fetch_all(
    'SELECT status, COUNT(*) AS total FROM reports WHERE assigned_staff_id = ? GROUP BY status',
    [$staffId]
);
$statusCounts = array_fill_keys(array_keys(statuses()), 0);
foreach ($statusRows as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}

$recentActivity = fetch_all(
    "SELECT ru.*, r.reference_no, r.title, r.id AS report_id
     FROM report_updates ru
     JOIN reports r ON r.id = ru.report_id
     WHERE r.assigned_staff_id = ?
     ORDER BY ru.created_at DESC, ru.id DESC
     LIMIT 6",
    [$staffId]
);

$announcements = fetch_all(
    "SELECT * FROM announcements
     WHERE is_published = 1 AND audience IN ('all', 'staff')
     ORDER BY created_at DESC
     LIMIT 2"
);

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h2>
        <p>You have <strong><?= number_format($openCases) ?></strong> open case<?= $openCases === 1 ? '' : 's' ?> assigned to you<?= $user['department_name'] ? ' at ' . e($user['department_name']) : '' ?>.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e(url('staff/reports.php')) ?>"><i class="fa-solid fa-list-check me-1"></i> My assigned reports</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('staff/reports.php?status=in_progress')) ?>"><i class="fa-solid fa-person-digging me-1"></i> Active work</a>
    </div>
</div>

<?php if ($urgentOpen > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <div>
            <strong><?= number_format($urgentOpen) ?> urgent case<?= $urgentOpen === 1 ? '' : 's' ?> need attention.</strong>
            <div class="small mt-1">High and critical priority reports are still open in your queue. <a class="fw-600" href="<?= e(url('staff/reports.php?sort=priority')) ?>">Review them now</a>.</div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Assigned to me</div>
                    <div class="stat-value"><?= number_format($totalAssigned) ?></div>
                    <div class="stat-note"><?= number_format($openCases) ?> currently open</div>
                </div>
                <div class="stat-icon blue"><i class="fa-solid fa-clipboard-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">In progress</div>
                    <div class="stat-value"><?= number_format($inProgress) ?></div>
                    <div class="stat-note">Work has started on these</div>
                </div>
                <div class="stat-icon amber"><i class="fa-solid fa-person-digging"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Resolved</div>
                    <div class="stat-value"><?= number_format($resolvedCases) ?></div>
                    <div class="stat-note"><?= number_format($resolvedThisMonth) ?> completed this month</div>
                </div>
                <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Avg. resolution time</div>
                    <div class="stat-value"><?= $avgResolutionDays !== null ? number_format((float) $avgResolutionDays, 1) : '—' ?><?= $avgResolutionDays !== null ? '<small class="fs-7"> d</small>' : '' ?></div>
                    <div class="stat-note">From submission to resolution</div>
                </div>
                <div class="stat-icon teal"><i class="fa-solid fa-stopwatch"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>Priority queue</h3>
                    <p>Your open and recent cases, urgent first</p>
                </div>
                <a class="btn btn-soft btn-sm" href="<?= e(url('staff/reports.php')) ?>">View all <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <?php if ($recentReports): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Citizen</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Updated</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentReports as $report): ?>
                                <tr>
                                    <td>
                                        <a class="table-title" href="<?= e(url('staff/report_view.php?id=' . (int) $report['id'])) ?>"><?= e(short_text($report['title'], 42)) ?></a>
                                        <span class="table-sub"><?= e($report['reference_no']) ?> · <?= e($report['category_name']) ?></span>
                                    </td>
                                    <td><span class="fw-600 fs-7"><?= e($report['citizen_name']) ?></span></td>
                                    <td><?= priority_badge($report['priority']) ?></td>
                                    <td><?= status_badge($report['status']) ?></td>
                                    <td><span class="table-sub"><?= e(time_ago($report['updated_at'])) ?></span></td>
                                    <td class="text-end">
                                        <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('staff/report_view.php?id=' . (int) $report['id'])) ?>" title="Open case"><i class="fa-solid fa-arrow-right"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                    <h4>No cases assigned yet</h4>
                    <p>When the civic office assigns a community report to your department, it will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>My case snapshot</h3>
                    <p>Assigned reports by status</p>
                </div>
            </div>
            <div class="content-card-body">
                <?php if ($totalAssigned > 0): ?>
                    <div class="chart-wrap-sm"><canvas id="staffStatusChart"></canvas></div>
                    <div class="chart-legend justify-content-center mt-2" id="staffStatusLegend"></div>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="empty-icon"><i class="fa-solid fa-chart-pie"></i></div>
                        <p class="mb-0">Your case chart will appear once a report is assigned to you.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer py-2 text-end">
                <a class="small fw-600 text-teal" href="<?= e(url('staff/reports.php')) ?>">Open my assigned reports <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>Recent activity on my cases</h3>
                    <p>Latest progress notes and status changes</p>
                </div>
            </div>
            <div class="content-card-body pt-1">
                <ul class="activity-list">
                    <?php if ($recentActivity): foreach ($recentActivity as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon"><i class="fa-solid <?= e(status_meta($activity['status'] ?? 'pending')['icon']) ?>"></i></div>
                            <div class="min-w-0">
                                <strong><?= e($activity['status'] ? status_label($activity['status']) : 'Progress note') ?> · <?= e($activity['reference_no']) ?></strong>
                                <span class="d-block text-truncate"><?= e(short_text($activity['note'] ?: $activity['title'], 64)) ?></span>
                            </div>
                            <time><?= e(time_ago($activity['created_at'])) ?></time>
                        </li>
                    <?php endforeach; else: ?>
                        <li class="text-body-secondary fs-7">No activity has been recorded on your cases yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>Staff announcements</h3>
                    <p>Notices from the civic office</p>
                </div>
            </div>
            <div class="content-card-body">
                <?php if ($announcements): ?>
                    <div class="row g-3">
                        <?php foreach ($announcements as $announcement): ?>
                            <div class="col-md-12">
                                <article class="announcement-card">
                                    <div class="announcement-icon"><i class="fa-solid fa-bullhorn"></i></div>
                                    <h4><?= e($announcement['title']) ?></h4>
                                    <p><?= nl2br(e(short_text($announcement['content'], 220))) ?></p>
                                    <time><i class="fa-regular fa-clock me-1"></i><?= e(time_ago($announcement['created_at'])) ?></time>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-body-secondary fs-7 mb-0">There are no staff announcements at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($totalAssigned > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined' || !window.CivicWatch) return;
    var palette = CivicWatch.palette();
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = palette.text;

    var canvas = document.getElementById('staffStatusChart');
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

        var legend = document.getElementById('staffStatusLegend');
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
