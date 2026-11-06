<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

$today = date('Y-m-d');
$defaultFrom = date('Y-m-d', strtotime('-90 days'));
$fromDate = get('from', $defaultFrom);
$toDate = get('to', $today);

$fromObj = DateTimeImmutable::createFromFormat('Y-m-d', $fromDate);
$toObj = DateTimeImmutable::createFromFormat('Y-m-d', $toDate);
if (!$fromObj || $fromObj->format('Y-m-d') !== $fromDate) {
    $fromDate = $defaultFrom;
    $fromObj = new DateTimeImmutable($fromDate);
}
if (!$toObj || $toObj->format('Y-m-d') !== $toDate) {
    $toDate = $today;
    $toObj = new DateTimeImmutable($toDate);
}
if ($fromObj > $toObj) {
    [$fromDate, $toDate] = [$toDate, $fromDate];
    [$fromObj, $toObj] = [$toObj, $fromObj];
}
if ($fromObj->diff($toObj)->days > 730) {
    $fromDate = date('Y-m-d', strtotime('-730 days'));
    $fromObj = new DateTimeImmutable($fromDate);
}

$rangeSql = 'r.created_at >= ? AND r.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
$rangeParams = [$fromDate, $toDate];

$totalInRange = (int) fetch_value("SELECT COUNT(*) FROM reports r WHERE {$rangeSql}", $rangeParams, 0);
$resolvedInRange = (int) fetch_value("SELECT COUNT(*) FROM reports r WHERE {$rangeSql} AND r.status = 'resolved'", $rangeParams, 0);
$rejectedInRange = (int) fetch_value("SELECT COUNT(*) FROM reports r WHERE {$rangeSql} AND r.status = 'rejected'", $rangeParams, 0);
$openInRange = (int) fetch_value("SELECT COUNT(*) FROM reports r WHERE {$rangeSql} AND r.status NOT IN ('resolved','rejected')", $rangeParams, 0);
$criticalInRange = (int) fetch_value("SELECT COUNT(*) FROM reports r WHERE {$rangeSql} AND r.priority IN ('high','critical') AND r.status NOT IN ('resolved','rejected')", $rangeParams, 0);
$avgHours = fetch_value(
    "SELECT AVG(TIMESTAMPDIFF(HOUR, r.created_at, r.resolved_at))
     FROM reports r WHERE {$rangeSql} AND r.status = 'resolved' AND r.resolved_at IS NOT NULL",
    $rangeParams,
    null
);
$avgDays = $avgHours !== null ? (float) $avgHours / 24 : null;
$resolutionRate = $totalInRange > 0 ? round(($resolvedInRange / $totalInRange) * 100) : 0;

$statusRows = fetch_all("SELECT r.status, COUNT(*) AS total FROM reports r WHERE {$rangeSql} GROUP BY r.status", $rangeParams);
$statusCounts = array_fill_keys(array_keys(statuses()), 0);
foreach ($statusRows as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}

$categoryRows = fetch_all(
    "SELECT c.name, c.color, COUNT(r.id) AS total,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS resolved
     FROM categories c
     LEFT JOIN reports r ON r.category_id = c.id AND r.created_at >= ? AND r.created_at < DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY c.id
     ORDER BY total DESC, c.name ASC",
    $rangeParams
);

$priorityRows = fetch_all("SELECT r.priority, COUNT(*) AS total FROM reports r WHERE {$rangeSql} GROUP BY r.priority", $rangeParams);
$priorityCounts = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
foreach ($priorityRows as $row) {
    $priorityCounts[$row['priority']] = (int) $row['total'];
}

$departmentRows = fetch_all(
    "SELECT d.name, d.email,
            COUNT(r.id) AS total,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS resolved,
            SUM(CASE WHEN r.status NOT IN ('resolved','rejected') THEN 1 ELSE 0 END) AS open_cases,
            AVG(CASE WHEN r.status = 'resolved' AND r.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, r.created_at, r.resolved_at) END) AS avg_hours
     FROM departments d
     LEFT JOIN reports r ON r.department_id = d.id AND r.created_at >= ? AND r.created_at < DATE_ADD(?, INTERVAL 1 DAY)
     GROUP BY d.id
     ORDER BY total DESC, d.name ASC",
    $rangeParams
);

$topCitizens = fetch_all(
    "SELECT u.full_name, u.email, COUNT(r.id) AS total,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS resolved
     FROM users u
     JOIN reports r ON r.user_id = u.id
     WHERE u.role = 'citizen' AND {$rangeSql}
     GROUP BY u.id
     ORDER BY total DESC, u.full_name ASC
     LIMIT 6",
    $rangeParams
);

$staffPerformance = fetch_all(
    "SELECT u.full_name, d.name AS department_name,
            COUNT(r.id) AS total,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS resolved,
            AVG(CASE WHEN r.status = 'resolved' AND r.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, r.created_at, r.resolved_at) END) AS avg_hours
     FROM users u
     JOIN reports r ON r.assigned_staff_id = u.id AND r.created_at >= ? AND r.created_at < DATE_ADD(?, INTERVAL 1 DAY)
     LEFT JOIN departments d ON d.id = u.department_id
     WHERE u.role = 'staff'
     GROUP BY u.id
     ORDER BY resolved DESC, total DESC
     LIMIT 6",
    $rangeParams
);

// Monthly trend within the selected range (capped to a readable 12 months).
$monthStart = $fromObj->modify('first day of this month');
$monthEnd = $toObj->modify('first day of this month');
$monthCount = (int) $monthStart->diff($monthEnd)->format('%m') + ((int) $monthEnd->format('Y') - (int) $monthStart->format('Y')) * 12 + 1;
if ($monthCount > 12) {
    $monthStart = $monthEnd->modify('-11 months');
}
$monthlyRows = fetch_all(
    "SELECT DATE_FORMAT(r.created_at, '%Y-%m') AS month, COUNT(*) AS total,
            SUM(CASE WHEN r.status = 'resolved' THEN 1 ELSE 0 END) AS resolved
     FROM reports r
     WHERE {$rangeSql}
     GROUP BY DATE_FORMAT(r.created_at, '%Y-%m')
     ORDER BY month ASC",
    $rangeParams
);
$monthlyMap = [];
foreach ($monthlyRows as $row) {
    $monthlyMap[$row['month']] = ['total' => (int) $row['total'], 'resolved' => (int) $row['resolved']];
}
$trendLabels = [];
$trendReported = [];
$trendResolved = [];
$cursor = $monthStart;
while ($cursor <= $monthEnd) {
    $key = $cursor->format('Y-m');
    $trendLabels[] = $cursor->format('M Y');
    $trendReported[] = $monthlyMap[$key]['total'] ?? 0;
    $trendResolved[] = $monthlyMap[$key]['resolved'] ?? 0;
    $cursor = $cursor->modify('+1 month');
}

$pageTitle = 'Analytics & Reports';
$pageSubtitle = 'Measure demand, resolution performance and service trends.';
$activePage = 'analytics';

$exportUrl = url('admin/export_reports.php') . '?' . http_build_query(['from' => $fromDate, 'to' => $toDate]);
$rangeLabel = format_date($fromDate, 'M j, Y') . ' – ' . format_date($toDate, 'M j, Y');

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Service analytics</h2>
        <p>Reporting period: <strong><?= e($rangeLabel) ?></strong></p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e($exportUrl) ?>"><i class="fa-solid fa-file-csv me-1"></i> Export CSV</a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print report</button>
    </div>
</div>

<div class="d-none d-print-block mb-4">
    <h2><?= e(APP_NAME) ?> — Community Report Summary</h2>
    <p><?= e(COMMUNITY_NAME) ?> · <?= e($rangeLabel) ?> · Generated <?= e(date('M j, Y g:i A')) ?></p>
</div>

<form method="get" action="<?= e(url('admin/analytics.php')) ?>" class="filter-bar no-print">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4 col-lg-3">
            <label class="form-label" for="from">From date</label>
            <input class="form-control" type="date" id="from" name="from" value="<?= e($fromDate) ?>" max="<?= e($today) ?>">
        </div>
        <div class="col-sm-4 col-lg-3">
            <label class="form-label" for="to">To date</label>
            <input class="form-control" type="date" id="to" name="to" value="<?= e($toDate) ?>" max="<?= e($today) ?>">
        </div>
        <div class="col-sm-4 col-lg-3">
            <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i> Apply date range</button>
        </div>
        <div class="col-lg-3 text-lg-end">
            <a class="btn btn-soft" href="<?= e(url('admin/analytics.php')) ?>"><i class="fa-solid fa-rotate-left me-1"></i> Reset (90 days)</a>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Reports in period</div><div class="stat-value"><?= number_format($totalInRange) ?></div><div class="stat-note"><?= number_format($criticalInRange) ?> high/critical still open</div></div><div class="stat-icon blue"><i class="fa-solid fa-clipboard-list"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Resolved</div><div class="stat-value"><?= number_format($resolvedInRange) ?></div><div class="stat-note"><strong><?= e($resolutionRate) ?>%</strong> resolution rate</div></div><div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Avg. resolution time</div><div class="stat-value"><?= $avgDays !== null ? number_format($avgDays, 1) : '—' ?><?= $avgDays !== null ? '<small class="fs-7"> d</small>' : '' ?></div><div class="stat-note">From submission to resolution</div></div><div class="stat-icon amber"><i class="fa-solid fa-stopwatch"></i></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Still open</div><div class="stat-value"><?= number_format($openInRange) ?></div><div class="stat-note"><?= number_format($rejectedInRange) ?> rejected in period</div></div><div class="stat-icon red"><i class="fa-solid fa-person-digging"></i></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="content-card h-100">
            <div class="content-card-head">
                <div><h3>Monthly report trend</h3><p>Submitted reports and resolutions by month</p></div>
                <div class="chart-legend"><span class="legend-item"><span class="legend-dot" style="background:#0f9b8e"></span>Reported</span><span class="legend-item"><span class="legend-dot" style="background:#1a9e64"></span>Resolved</span></div>
            </div>
            <div class="content-card-body"><div class="chart-wrap"><canvas id="monthlyChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="content-card h-100">
            <div class="content-card-head"><div><h3>Current status</h3><p>Reports in this period</p></div></div>
            <div class="content-card-body"><div class="chart-wrap"><canvas id="analyticsStatusChart"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="content-card h-100">
            <div class="content-card-head"><div><h3>Reports by category</h3><p>Demand across community issue types</p></div></div>
            <div class="content-card-body"><div class="chart-wrap"><canvas id="categoryChart"></canvas></div></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="content-card h-100">
            <div class="content-card-head"><div><h3>Priority mix</h3><p>Urgency of reports in this period</p></div></div>
            <div class="content-card-body"><div class="chart-wrap"><canvas id="priorityChart"></canvas></div></div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-xl-7">
        <div class="content-card">
            <div class="content-card-head"><div><h3>Department performance</h3><p>Reports created within the selected period</p></div></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>Department</th><th>Total</th><th>Open</th><th>Resolved</th><th>Avg. days</th><th>Rate</th></tr></thead>
                    <tbody>
                        <?php foreach ($departmentRows as $department): ?>
                            <?php $rate = (int) $department['total'] > 0 ? round(((int) $department['resolved'] / (int) $department['total']) * 100) : 0; ?>
                            <tr>
                                <td><span class="fw-600"><?= e($department['name']) ?></span><span class="table-sub d-block"><?= e($department['email'] ?: '') ?></span></td>
                                <td><?= (int) $department['total'] ?></td>
                                <td><?= (int) $department['open_cases'] ?></td>
                                <td><?= (int) $department['resolved'] ?></td>
                                <td><?= $department['avg_hours'] !== null ? e(number_format((float) $department['avg_hours'] / 24, 1)) : '—' ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2" style="min-width:95px;">
                                        <div class="progress flex-grow-1" style="height:5px;"><div class="progress-bar" style="width:<?= $rate ?>%"></div></div>
                                        <span class="fs-8"><?= $rate ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="content-card mb-3">
            <div class="content-card-head"><div><h3>Most active citizens</h3><p>Top reporters in this period</p></div></div>
            <div class="content-card-body pt-1">
                <?php if ($topCitizens): foreach ($topCitizens as $citizen): ?>
                    <div class="workload-item">
                        <span class="avatar avatar-sm"><?= e(initials($citizen['full_name'])) ?></span>
                        <div class="workload-info"><strong><?= e($citizen['full_name']) ?></strong><span><?= (int) $citizen['resolved'] ?> resolved · <?= e($citizen['email']) ?></span></div>
                        <span class="workload-count"><?= (int) $citizen['total'] ?></span>
                    </div>
                <?php endforeach; else: ?><p class="text-body-secondary fs-7 mb-0">No citizen reports in this period.</p><?php endif; ?>
            </div>
        </div>
        <div class="content-card">
            <div class="content-card-head"><div><h3>Staff performance</h3><p>Resolved assignments in this period</p></div></div>
            <div class="content-card-body pt-1">
                <?php if ($staffPerformance): foreach ($staffPerformance as $staff): ?>
                    <div class="workload-item">
                        <span class="avatar avatar-sm"><?= e(initials($staff['full_name'])) ?></span>
                        <div class="workload-info"><strong><?= e($staff['full_name']) ?></strong><span><?= e($staff['department_name'] ?: 'No department') ?> · <?= $staff['avg_hours'] !== null ? e(number_format((float) $staff['avg_hours'] / 24, 1)) . ' d avg' : 'No resolutions' ?></span></div>
                        <span class="workload-count"><?= (int) $staff['resolved'] ?>/<?= (int) $staff['total'] ?></span>
                    </div>
                <?php endforeach; else: ?><p class="text-body-secondary fs-7 mb-0">No staff assignments in this period.</p><?php endif; ?>
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

    var monthly = document.getElementById('monthlyChart');
    if (monthly) {
        new Chart(monthly, {
            type: 'bar',
            data: {
                labels: <?= json_encode($trendLabels) ?>,
                datasets: [
                    { label: 'Reported', data: <?= json_encode($trendReported) ?>, backgroundColor: 'rgba(15,155,142,.78)', borderRadius: 6, maxBarThickness: 34 },
                    { label: 'Resolved', data: <?= json_encode($trendResolved) ?>, backgroundColor: 'rgba(26,158,100,.7)', borderRadius: 6, maxBarThickness: 34 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: palette.grid }, border: { display: false } }
                }
            }
        });
    }

    var status = document.getElementById('analyticsStatusChart');
    if (status) {
        new Chart(status, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map(static fn ($key) => status_meta($key)['label'], array_keys($statusCounts))) ?>,
                datasets: [{ data: <?= json_encode(array_values($statusCounts)) ?>, backgroundColor: ['#e0a02a','#4c9de0','#0f9b8e','#5b58c9','#2a76d2','#1a9e64','#d64545'], borderWidth: 0, hoverOffset: 4 }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '64%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 9, padding: 10, font: { size: 10 } } } } }
        });
    }

    var category = document.getElementById('categoryChart');
    if (category) {
        new Chart(category, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_map(static fn ($row) => $row['name'], $categoryRows)) ?>,
                datasets: [{ label: 'Reports', data: <?= json_encode(array_map(static fn ($row) => (int) $row['total'], $categoryRows)) ?>, backgroundColor: <?= json_encode(array_map(static fn ($row) => $row['color'], $categoryRows)) ?>, borderRadius: 5, maxBarThickness: 24 }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: palette.grid }, border: { display: false } },
                    y: { grid: { display: false }, border: { display: false } }
                }
            }
        });
    }

    var priority = document.getElementById('priorityChart');
    if (priority) {
        new Chart(priority, {
            type: 'polarArea',
            data: {
                labels: ['Low', 'Medium', 'High', 'Critical'],
                datasets: [{ data: <?= json_encode(array_values($priorityCounts)) ?>, backgroundColor: ['rgba(26,158,100,.75)','rgba(42,118,210,.75)','rgba(224,160,42,.8)','rgba(214,69,69,.8)'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 9, padding: 10, font: { size: 10 } } } }, scales: { r: { grid: { color: palette.grid }, ticks: { display: false } } } }
        });
    }
});
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
