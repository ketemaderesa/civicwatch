<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

$pageTitle = 'Administrator Dashboard';
$pageSubtitle = 'A live overview of community reports and service performance.';
$activePage = 'dashboard';

$totalReports = (int) fetch_value('SELECT COUNT(*) FROM reports', [], 0);
$openReports = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE status NOT IN ('resolved','rejected')", [], 0);
$resolvedReports = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE status = 'resolved'", [], 0);
$pendingReview = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE status IN ('pending','under_review')", [], 0);
$criticalOpen = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE priority = 'critical' AND status NOT IN ('resolved','rejected')", [], 0);
$unassigned = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE assigned_staff_id IS NULL AND status NOT IN ('resolved','rejected')", [], 0);
$activeCitizens = (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'citizen' AND is_active = 1", [], 0);
$staffCount = (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'staff' AND is_active = 1", [], 0);
$departmentCount = (int) fetch_value('SELECT COUNT(*) FROM departments WHERE is_active = 1', [], 0);
$newThisWeek = (int) fetch_value('SELECT COUNT(*) FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)', [], 0);
$resolvedThisWeek = (int) fetch_value("SELECT COUNT(*) FROM reports WHERE status = 'resolved' AND resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [], 0);
$avgResolutionDays = fetch_value(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) / 24 FROM reports WHERE status = 'resolved' AND resolved_at IS NOT NULL",
    [],
    null
);

$statusRows = fetch_all('SELECT status, COUNT(*) AS total FROM reports GROUP BY status');
$statusCounts = array_fill_keys(array_keys(statuses()), 0);
foreach ($statusRows as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}

$categoryRows = fetch_all(
    'SELECT c.name, c.color, COUNT(r.id) AS total
     FROM categories c LEFT JOIN reports r ON r.category_id = c.id
     GROUP BY c.id ORDER BY total DESC, c.name ASC LIMIT 6'
);

$departments = fetch_all(
    "SELECT d.id, d.name, COUNT(r.id) AS total_reports,
            SUM(CASE WHEN r.status NOT IN ('resolved','rejected') THEN 1 ELSE 0 END) AS open_reports,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS resolved_reports
     FROM departments d
     LEFT JOIN reports r ON r.department_id = d.id
     WHERE d.is_active = 1
     GROUP BY d.id
     ORDER BY open_reports DESC, d.name ASC
     LIMIT 6"
);

$recentReports = fetch_all(
    "SELECT r.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
            u.full_name AS citizen_name, d.name AS department_name
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     JOIN users u ON u.id = r.user_id
     LEFT JOIN departments d ON d.id = r.department_id
     ORDER BY r.created_at DESC LIMIT 7"
);

$recentActivity = fetch_all(
    "SELECT ru.*, u.full_name, u.role, r.reference_no, r.title
     FROM report_updates ru
     JOIN reports r ON r.id = ru.report_id
     LEFT JOIN users u ON u.id = ru.user_id
     ORDER BY ru.created_at DESC, ru.id DESC LIMIT 8"
);

// Seven day trend, including days with zero reports.
$trendLabels = [];
$trendValues = [];
$trendResolved = [];
$rawTrend = fetch_all(
    "SELECT DATE(created_at) AS day, COUNT(*) AS total
     FROM reports
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(created_at)"
);
$trendMap = [];
foreach ($rawTrend as $row) {
    $trendMap[$row['day']] = (int) $row['total'];
}
$rawResolved = fetch_all(
    "SELECT DATE(resolved_at) AS day, COUNT(*) AS total
     FROM reports
     WHERE status = 'resolved' AND resolved_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(resolved_at)"
);
$resolvedMap = [];
foreach ($rawResolved as $row) {
    $resolvedMap[$row['day']] = (int) $row['total'];
}
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $trendLabels[] = date('D', strtotime($date));
    $trendValues[] = $trendMap[$date] ?? 0;
    $trendResolved[] = $resolvedMap[$date] ?? 0;
}

$resolutionRate = $totalReports > 0 ? round(($resolvedReports / $totalReports) * 100) : 0;

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $user['full_name'])[0]) ?> 👋</h2>
        <p>Here is what is happening across <?= e(COMMUNITY_NAME) ?> today.</p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e(url('admin/reports.php')) ?>"><i class="fa-solid fa-list-check me-1"></i> Review reports</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('admin/analytics.php')) ?>"><i class="fa-solid fa-chart-line me-1"></i> Full analytics</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Total reports</div>
                    <div class="stat-value"><?= number_format($totalReports) ?></div>
                    <div class="stat-note"><strong>+<?= number_format($newThisWeek) ?></strong> in the last 7 days</div>
                </div>
                <div class="stat-icon blue"><i class="fa-solid fa-clipboard-list"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Open cases</div>
                    <div class="stat-value"><?= number_format($openReports) ?></div>
                    <div class="stat-note"><?= number_format($pendingReview) ?> awaiting review</div>
                </div>
                <div class="stat-icon amber"><i class="fa-solid fa-hourglass-half"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Resolved</div>
                    <div class="stat-value"><?= number_format($resolvedReports) ?></div>
                    <div class="stat-note"><strong><?= e($resolutionRate) ?>%</strong> resolution rate</div>
                </div>
                <div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-top">
                <div>
                    <div class="stat-label">Active citizens</div>
                    <div class="stat-value"><?= number_format($activeCitizens) ?></div>
                    <div class="stat-note"><?= number_format($staffCount) ?> staff across <?= number_format($departmentCount) ?> departments</div>
                </div>
                <div class="stat-icon teal"><i class="fa-solid fa-users"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4">
        <div class="content-card h-100">
            <div class="content-card-body d-flex align-items-center gap-3 py-3">
                <div class="stat-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <div class="fs-8 text-body-secondary text-uppercase fw-600">Critical open</div>
                    <strong class="fs-5"><?= number_format($criticalOpen) ?></strong>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="content-card h-100">
            <div class="content-card-body d-flex align-items-center gap-3 py-3">
                <div class="stat-icon indigo"><i class="fa-solid fa-user-clock"></i></div>
                <div>
                    <div class="fs-8 text-body-secondary text-uppercase fw-600">Unassigned</div>
                    <strong class="fs-5"><?= number_format($unassigned) ?></strong>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="content-card h-100">
            <div class="content-card-body d-flex align-items-center gap-3 py-3">
                <div class="stat-icon green"><i class="fa-solid fa-stopwatch"></i></div>
                <div>
                    <div class="fs-8 text-body-secondary text-uppercase fw-600">Avg. resolution time</div>
                    <strong class="fs-5"><?= $avgResolutionDays !== null ? number_format((float) $avgResolutionDays, 1) . ' days' : '—' ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>Reports overview</h3>
                    <p>New reports compared with resolutions over the last 7 days</p>
                </div>
                <div class="chart-legend">
                    <span class="legend-item"><span class="legend-dot" style="background:#0f9b8e"></span>Reported</span>
                    <span class="legend-item"><span class="legend-dot" style="background:#1a9e64"></span>Resolved</span>
                </div>
            </div>
            <div class="content-card-body">
                <div class="chart-wrap"><canvas id="trendChart"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div>
                    <h3>Status distribution</h3>
                    <p>All reports by current workflow state</p>
                </div>
            </div>
            <div class="content-card-body">
                <div class="chart-wrap-sm"><canvas id="statusChart"></canvas></div>
                <div class="chart-legend justify-content-center mt-2" id="statusLegend"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="content-card">
            <div class="content-card-head">
                <div>
                    <h3>Latest reports</h3>
                    <p>Newest community submissions across all categories</p>
                </div>
                <a class="btn btn-soft btn-sm" href="<?= e(url('admin/reports.php')) ?>">View all <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Report</th>
                            <th>Category</th>
                            <th>Citizen</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recentReports): foreach ($recentReports as $report): ?>
                            <tr>
                                <td>
                                    <a class="table-title" href="<?= e(url('admin/report_view.php?id=' . (int) $report['id'])) ?>"><?= e(short_text($report['title'], 42)) ?></a>
                                    <span class="table-sub"><?= e($report['reference_no']) ?> · <?= e($report['department_name'] ?: 'Not assigned') ?></span>
                                </td>
                                <td><span class="d-inline-flex align-items-center gap-2"><span class="dot" style="background:<?= e($report['category_color']) ?>"></span><?= e($report['category_name']) ?></span></td>
                                <td><?= e($report['citizen_name']) ?></td>
                                <td><?= status_badge($report['status']) ?></td>
                                <td><span class="table-sub"><?= e(time_ago($report['created_at'])) ?></span></td>
                                <td class="text-end"><a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('admin/report_view.php?id=' . (int) $report['id'])) ?>" title="Open report"><i class="fa-solid fa-arrow-right"></i></a></td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6"><div class="empty-state py-4"><p class="mb-0">No reports have been submitted yet.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="content-card mb-3">
            <div class="content-card-head"><div><h3>Department workload</h3><p>Open reports by department</p></div></div>
            <div class="content-card-body pt-1">
                <?php if ($departments): foreach ($departments as $department): ?>
                    <div class="workload-item">
                        <div class="dept-icon"><i class="fa-solid fa-building-columns"></i></div>
                        <div class="workload-info">
                            <strong><?= e($department['name']) ?></strong>
                            <span><?= (int) $department['resolved_reports'] ?> resolved · <?= (int) $department['total_reports'] ?> total</span>
                        </div>
                        <span class="workload-count"><?= (int) $department['open_reports'] ?></span>
                    </div>
                <?php endforeach; else: ?>
                    <p class="text-body-secondary fs-7 mb-0">No departments configured.</p>
                <?php endif; ?>
            </div>
            <div class="card-footer py-2 text-end">
                <a class="small fw-600 text-teal" href="<?= e(url('admin/departments.php')) ?>">Manage departments <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
        </div>

        <div class="content-card">
            <div class="content-card-head"><div><h3>Recent activity</h3><p>Latest workflow updates</p></div></div>
            <div class="content-card-body pt-1">
                <ul class="activity-list">
                    <?php if ($recentActivity): foreach ($recentActivity as $activity): ?>
                        <li class="activity-item">
                            <div class="activity-icon"><i class="fa-solid <?= e(status_meta($activity['status'] ?? 'pending')['icon']) ?>"></i></div>
                            <div class="min-w-0">
                                <strong><?= e($activity['full_name'] ?: 'System') ?> updated <?= e($activity['reference_no']) ?></strong>
                                <span class="d-block text-truncate"><?= e(short_text($activity['title'], 44)) ?></span>
                            </div>
                            <time><?= e(time_ago($activity['created_at'])) ?></time>
                        </li>
                    <?php endforeach; else: ?>
                        <li class="text-body-secondary fs-7">No activity recorded yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined' || !window.CivicWatch) return;
    var palette = CivicWatch.palette();
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = palette.text;

    var trendCanvas = document.getElementById('trendChart');
    if (trendCanvas) {
        new Chart(trendCanvas, {
            type: 'line',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [
                    {
                        label: 'Reported',
                        data: <?= json_encode($trendValues) ?>,
                        borderColor: palette.teal,
                        backgroundColor: palette.tealLight,
                        fill: true,
                        tension: .4,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: palette.teal
                    },
                    {
                        label: 'Resolved',
                        data: <?= json_encode($trendResolved) ?>,
                        borderColor: palette.green,
                        backgroundColor: 'rgba(26,158,100,.06)',
                        fill: true,
                        tension: .4,
                        borderWidth: 2.5,
                        pointRadius: 3,
                        pointBackgroundColor: palette.green
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: palette.grid }, border: { display: false } }
                }
            }
        });
    }

    var statusCanvas = document.getElementById('statusChart');
    var statusData = <?= json_encode(array_values($statusCounts)) ?>;
    var statusLabels = <?= json_encode(array_map(static fn ($key) => status_meta($key)['label'], array_keys($statusCounts))) ?>;
    var statusColors = ['#e0a02a', '#4c9de0', '#0f9b8e', '#5b58c9', '#2a76d2', '#1a9e64', '#d64545'];

    if (statusCanvas) {
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusData,
                    backgroundColor: statusColors,
                    borderWidth: 3,
                    borderColor: getComputedStyle(document.body).backgroundColor || '#fff',
                    hoverOffset: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: { legend: { display: false } }
            }
        });

        var legend = document.getElementById('statusLegend');
        if (legend) {
            statusLabels.forEach(function (label, index) {
                var item = document.createElement('span');
                item.className = 'legend-item';
                item.innerHTML = '<span class="legend-dot" style="background:' + statusColors[index] + '"></span>' + label;
                legend.appendChild(item);
            });
        }
    }
});
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
