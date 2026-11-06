<?php
/**
 * Admin CSV export – honours the same filters as the report register.
 */
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

$statusFilter = get('status');
$categoryFilter = get_int('category');
$departmentFilter = get_int('department');
$priorityFilter = get('priority');
$search = get('search');
$sort = get('sort', 'newest');
$fromDate = get('from');
$toDate = get('to');

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

// Optional date range, validated strictly so it can never change the SQL shape.
$dateFilters = ['from' => $fromDate, 'to' => $toDate];
foreach ($dateFilters as $key => $value) {
    if ($value !== '') {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            $dateFilters[$key] = '';
        }
    }
}
$fromDate = $dateFilters['from'];
$toDate = $dateFilters['to'];

if ($fromDate !== '') {
    $where[] = 'r.created_at >= ?';
    $params[] = $fromDate . ' 00:00:00';
}
if ($toDate !== '') {
    $where[] = 'r.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $toDate . ' 00:00:00';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$reports = fetch_all(
    "SELECT r.reference_no, r.title, c.name AS category_name, u.full_name AS citizen_name,
            u.email AS citizen_email, d.name AS department_name, s.full_name AS staff_name,
            r.priority, r.status, r.address, r.latitude, r.longitude,
            r.rejection_reason, r.created_at, r.updated_at, r.resolved_at
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     JOIN users u ON u.id = r.user_id
     LEFT JOIN departments d ON d.id = r.department_id
     LEFT JOIN users s ON s.id = r.assigned_staff_id
     {$whereSql}
     ORDER BY {$orderBy}",
    $params
);

$filename = 'civicwatch-reports-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'wb');
if ($output === false) {
    abort(500, 'The export file could not be generated.');
}

// UTF-8 BOM keeps Excel happy with the special characters used in addresses.
fwrite($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'Reference',
    'Title',
    'Category',
    'Citizen',
    'Citizen email',
    'Department',
    'Assigned staff',
    'Priority',
    'Status',
    'Address',
    'Latitude',
    'Longitude',
    'Submitted',
    'Last updated',
    'Resolved',
    'Rejection reason',
], ',', '"', '');

/**
 * Prevent spreadsheet formula injection while exporting free-text values.
 */
$safe = static function (mixed $value): string {
    $text = (string) $value;
    if ($text !== '' && preg_match('/^[=+\-@\t\r]/', $text) === 1) {
        return "'" . $text;
    }
    return $text;
};

foreach ($reports as $report) {
    fputcsv($output, [
        $safe($report['reference_no']),
        $safe($report['title']),
        $safe($report['category_name']),
        $safe($report['citizen_name']),
        $safe($report['citizen_email']),
        $safe($report['department_name'] ?: 'Not assigned'),
        $safe($report['staff_name'] ?: 'Not assigned'),
        priority_meta($report['priority'])['label'],
        status_label($report['status']),
        $safe($report['address']),
        number_format((float) $report['latitude'], 6, '.', ''),
        number_format((float) $report['longitude'], 6, '.', ''),
        format_datetime($report['created_at']),
        format_datetime($report['updated_at']),
        $report['resolved_at'] ? format_datetime($report['resolved_at']) : '',
        $safe($report['rejection_reason'] ?? ''),
    ], ',', '"', '');
}

fclose($output);
exit;
