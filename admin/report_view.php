<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

$reportId = get_int('id');
$report = fetch_one(
    "SELECT r.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon,
            u.full_name AS citizen_name, u.email AS citizen_email, u.phone AS citizen_phone,
            d.name AS department_name, d.email AS department_email,
            s.full_name AS staff_name, s.email AS staff_email
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     JOIN users u ON u.id = r.user_id
     LEFT JOIN departments d ON d.id = r.department_id
     LEFT JOIN users s ON s.id = r.assigned_staff_id
     WHERE r.id = ? LIMIT 1",
    [$reportId]
);

if (!$report) {
    abort(404, 'The requested report could not be found.');
}

if (is_post()) {
    require_csrf();
    $action = post('action');
    $nowStatus = $report['status'];

    if ($action === 'approve') {
        if ($nowStatus === 'resolved') {
            flash('warning', 'This report is already resolved. Use the status update panel to reopen it if needed.');
        } else {
            $stmt = db()->prepare("UPDATE reports SET status = 'approved', rejection_reason = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reportId]);
            record_report_update($reportId, (int) $user['id'], 'approved', 'Report approved by the civic administrator.');
            send_notification((int) $report['user_id'], 'Report approved', 'Your report ' . $report['reference_no'] . ' has been approved and is being routed to the responsible department.', $reportId);
            flash('success', 'Report approved. You can now assign it to a department and staff member.');
        }
        redirect('admin/report_view.php?id=' . $reportId);
    }

    if ($action === 'reject') {
        $reason = post('rejection_reason');
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            flash('danger', 'Please provide a rejection reason between 5 and 500 characters.');
        } elseif ($nowStatus === 'resolved') {
            flash('warning', 'Resolved reports cannot be rejected. Reopen the report first if necessary.');
        } else {
            $stmt = db()->prepare("UPDATE reports SET status = 'rejected', rejection_reason = ?, resolved_at = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reason, $reportId]);
            record_report_update($reportId, (int) $user['id'], 'rejected', 'Report rejected: ' . $reason);
            send_notification((int) $report['user_id'], 'Report rejected', 'Your report ' . $report['reference_no'] . ' was rejected. Reason: ' . $reason, $reportId);
            flash('success', 'Report rejected and the citizen has been notified.');
        }
        redirect('admin/report_view.php?id=' . $reportId);
    }

    if ($action === 'assign') {
        $departmentId = post_int('department_id');
        $staffId = post_int('staff_id');
        $priority = post('priority');
        $assignmentNote = post('assignment_note');

        $department = fetch_one('SELECT * FROM departments WHERE id = ? AND is_active = 1', [$departmentId]);
        $staff = fetch_one("SELECT * FROM users WHERE id = ? AND role = 'staff' AND is_active = 1", [$staffId]);

        if (!$department) {
            flash('danger', 'Please choose a valid, active department.');
        } elseif (!$staff || (int) $staff['department_id'] !== $departmentId) {
            flash('danger', 'Please choose a staff member who belongs to the selected department.');
        } elseif (!in_array($priority, ['low', 'medium', 'high', 'critical'], true)) {
            flash('danger', 'Please select a valid priority level.');
        } elseif (in_array($nowStatus, ['resolved', 'rejected'], true)) {
            flash('warning', 'Resolved or rejected reports must be reopened before they can be assigned.');
        } else {
            try {
                db()->beginTransaction();
                $stmt = db()->prepare(
                    "UPDATE reports
                     SET department_id = ?, assigned_staff_id = ?, priority = ?, status = 'assigned', rejection_reason = NULL, updated_at = NOW()
                     WHERE id = ?"
                );
                $stmt->execute([$departmentId, $staffId, $priority, $reportId]);

                $stmt = db()->prepare(
                    'INSERT INTO report_assignments (report_id, department_id, assigned_by, assigned_to, note, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$reportId, $departmentId, $user['id'], $staffId, $assignmentNote !== '' ? $assignmentNote : null]);

                $note = 'Assigned to ' . $staff['full_name'] . ' at ' . $department['name'] . '.';
                if ($assignmentNote !== '') {
                    $note .= ' Note: ' . $assignmentNote;
                }
                record_report_update($reportId, (int) $user['id'], 'assigned', $note);
                db()->commit();

                send_notification((int) $report['user_id'], 'Report assigned', 'Your report ' . $report['reference_no'] . ' has been assigned to ' . $department['name'] . ' and your case officer is ' . $staff['full_name'] . '.', $reportId);
                send_notification((int) $staff['id'], 'New report assigned', 'Report ' . $report['reference_no'] . ' — ' . $report['title'] . ' — has been assigned to you. Priority: ' . ucfirst($priority) . '.', $reportId);
                flash('success', 'Report assigned to ' . $staff['full_name'] . ' at ' . $department['name'] . '.');
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('danger', 'The assignment could not be saved. Please try again.');
            }
        }
        redirect('admin/report_view.php?id=' . $reportId);
    }

    if ($action === 'update_status') {
        $newStatus = post('status');
        $note = post('status_note');
        $allowed = ['under_review', 'approved', 'in_progress', 'resolved'];

        if (!in_array($newStatus, $allowed, true)) {
            flash('danger', 'Please choose a valid status.');
        } elseif ($newStatus === $nowStatus) {
            flash('info', 'The report is already in that status. Add a progress note from the assigned staff account for routine updates.');
        } elseif (mb_strlen($note) < 3) {
            flash('danger', 'Please add a short note explaining this status update (at least 3 characters).');
        } else {
            $resolvedAtSql = $newStatus === 'resolved' ? 'NOW()' : 'NULL';
            $stmt = db()->prepare("UPDATE reports SET status = ?, resolved_at = {$resolvedAtSql}, rejection_reason = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$newStatus, $reportId]);
            record_report_update($reportId, (int) $user['id'], $newStatus, $note);
            send_notification(
                (int) $report['user_id'],
                'Report status updated',
                'Your report ' . $report['reference_no'] . ' is now ' . status_label($newStatus) . '. ' . $note,
                $reportId
            );
            flash('success', 'Report status updated to ' . status_label($newStatus) . '.');
        }
        redirect('admin/report_view.php?id=' . $reportId);
    }

    if ($action === 'save_note') {
        $internalNote = post('admin_notes');
        if (mb_strlen($internalNote) > 5000) {
            flash('danger', 'Internal notes must be 5,000 characters or fewer.');
        } else {
            $stmt = db()->prepare('UPDATE reports SET admin_notes = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$internalNote !== '' ? $internalNote : null, $reportId]);
            flash('success', 'Internal notes saved.');
        }
        redirect('admin/report_view.php?id=' . $reportId);
    }

    flash('danger', 'Unsupported action.');
    redirect('admin/report_view.php?id=' . $reportId);
}

$images = report_images($reportId, 'report');
$evidence = report_images($reportId, 'evidence');
$updates = report_updates($reportId);
$assignments = fetch_all(
    'SELECT ra.*, d.name AS department_name, a.full_name AS assigned_by_name, s.full_name AS staff_name
     FROM report_assignments ra
     JOIN departments d ON d.id = ra.department_id
     LEFT JOIN users a ON a.id = ra.assigned_by
     LEFT JOIN users s ON s.id = ra.assigned_to
     WHERE ra.report_id = ?
     ORDER BY ra.created_at DESC, ra.id DESC',
    [$reportId]
);

$departments = fetch_all('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name');
$staffMembers = fetch_all(
    "SELECT id, full_name, department_id FROM users
     WHERE role = 'staff' AND is_active = 1
     ORDER BY full_name"
);

$flow = ['pending', 'under_review', 'approved', 'assigned', 'in_progress', 'resolved'];
$currentIndex = array_search($report['status'], $flow, true);
$isRejected = $report['status'] === 'rejected';
$coverImage = $images[0]['file_path'] ?? null;

$pageTitle = 'Report ' . $report['reference_no'];
$pageSubtitle = $report['title'];
$activePage = 'reports';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('admin/reports.php')) ?>" title="Back to reports"><i class="fa-solid fa-arrow-left"></i></a>
            <span class="badge-status status-approved" style="background:var(--cw-surface-2);color:var(--cw-muted);"><i class="fa-solid fa-hashtag"></i> <?= e($report['reference_no']) ?></span>
            <?= status_badge($report['status']) ?>
            <?= priority_badge($report['priority']) ?>
        </div>
        <h2><?= e($report['title']) ?></h2>
        <p>Submitted by <?= e($report['citizen_name']) ?> · <?= e(format_datetime($report['created_at'])) ?></p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e(url('admin/reports.php?search=' . urlencode($report['reference_no']))) ?>"><i class="fa-solid fa-magnifying-glass me-1"></i> Find similar</a>
        <?php if (!in_array($report['status'], ['resolved', 'rejected'], true)): ?>
            <form method="post" action="<?= e(url('admin/report_view.php?id=' . $reportId)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-circle-check me-1"></i> Approve report</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="detail-grid">
    <div>
        <div class="report-hero mb-3">
            <div class="report-hero-cover">
                <?php if ($coverImage): ?>
                    <a href="<?= e(url($coverImage)) ?>" target="_blank" rel="noopener"><img src="<?= e(url($coverImage)) ?>" alt="Citizen photo for report <?= e($report['reference_no']) ?>"></a>
                <?php else: ?>
                    <div class="cover-placeholder">
                        <i class="fa-solid <?= e($report['category_icon']) ?>"></i>
                        <span>No citizen photo was attached</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="report-hero-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge-status" style="background:<?= e($report['category_color']) ?>1f;color:<?= e($report['category_color']) ?>;"><i class="fa-solid <?= e($report['category_icon']) ?>"></i> <?= e($report['category_name']) ?></span>
                    <span class="report-ref"><?= e($report['reference_no']) ?></span>
                </div>
                <div class="description-block"><?= nl2br(e($report['description'])) ?></div>

                <?php if ($report['status'] === 'rejected' && $report['rejection_reason']): ?>
                    <div class="alert alert-danger mt-3 mb-0"><strong>Rejection reason:</strong> <?= e($report['rejection_reason']) ?></div>
                <?php endif; ?>

                <?php if (!$isRejected): ?>
                    <div class="workflow-strip mt-4">
                        <?php foreach ($flow as $index => $step): ?>
                            <?php
                            $stepClass = '';
                            if ($currentIndex !== false && $index < $currentIndex) {
                                $stepClass = 'done';
                            } elseif ($currentIndex !== false && $index === $currentIndex) {
                                $stepClass = 'done current';
                            }
                            ?>
                            <div class="workflow-step <?= e($stepClass) ?>">
                                <div class="workflow-dot"><i class="fa-solid <?= $stepClass ? 'fa-check' : e(status_meta($step)['icon']) ?>"></i></div>
                                <span><?= e(status_meta($step)['label']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="report-meta-grid">
                    <div class="meta-item">
                        <span>Location</span>
                        <strong><?= e(short_text($report['address'], 55)) ?></strong>
                    </div>
                    <div class="meta-item">
                        <span>GPS coordinates</span>
                        <strong><?= e(number_format((float) $report['latitude'], 5)) ?>, <?= e(number_format((float) $report['longitude'], 5)) ?></strong>
                    </div>
                    <div class="meta-item">
                        <span>Last updated</span>
                        <strong><?= e(time_ago($report['updated_at'])) ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card mb-3">
            <div class="content-card-head">
                <div><h3>Location on map</h3><p>Exact coordinates supplied by the citizen</p></div>
                <a class="btn btn-soft btn-sm" href="https://www.openstreetmap.org/?mlat=<?= e((string) $report['latitude']) ?>&mlon=<?= e((string) $report['longitude']) ?>#map=17/<?= e((string) $report['latitude']) ?>/<?= e((string) $report['longitude']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open map</a>
            </div>
            <div class="content-card-body">
                <div id="reportMap" class="map-panel"></div>
            </div>
        </div>

        <div class="content-card mb-3">
            <div class="content-card-head">
                <div><h3>Citizen photos (<?= count($images) ?>)</h3><p>Evidence attached when the report was submitted</p></div>
            </div>
            <div class="content-card-body">
                <?php if ($images): ?>
                    <div class="image-gallery">
                        <?php foreach ($images as $image): ?>
                            <a class="gallery-thumb" href="<?= e(url($image['file_path'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($image['file_path'])) ?>" alt="<?= e($image['original_name'] ?: 'Report photo') ?>" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-body-secondary fs-7 mb-0">No photos were attached to this report.</p>
                <?php endif; ?>

                <?php if ($evidence): ?>
                    <h4 class="fs-7 text-uppercase text-body-secondary fw-700 mt-4 mb-2"><i class="fa-solid fa-camera-retro me-1"></i> Completion evidence (<?= count($evidence) ?>)</h4>
                    <div class="image-gallery">
                        <?php foreach ($evidence as $image): ?>
                            <a class="gallery-thumb" href="<?= e(url($image['file_path'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($image['file_path'])) ?>" alt="<?= e($image['original_name'] ?: 'Completion evidence') ?>" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="content-card-head"><div><h3>Report history</h3><p>Every workflow update, newest activity at the bottom</p></div></div>
            <div class="content-card-body">
                <div class="timeline">
                    <?php foreach ($updates as $update): ?>
                        <div class="timeline-item <?= e($update['status'] ?: '') ?>">
                            <span class="timeline-dot"></span>
                            <h5><?= e($update['status'] ? status_label($update['status']) : 'Progress note') ?></h5>
                            <div class="timeline-meta">
                                <?= e($update['full_name'] ?: 'System') ?>
                                <?php if ($update['role']): ?> · <?= e(role_label($update['role'])) ?><?php endif; ?>
                                · <?= e(format_datetime($update['created_at'])) ?>
                            </div>
                            <?php if ($update['note']): ?>
                                <div class="timeline-note"><?= nl2br(e($update['note'])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <aside>
        <div class="side-card">
            <div class="side-card-head"><i class="fa-solid fa-circle-info"></i> Report details</div>
            <div class="side-card-body">
                <ul class="detail-list">
                    <li><span class="label">Reference</span><span class="value"><?= e($report['reference_no']) ?></span></li>
                    <li><span class="label">Category</span><span class="value"><?= e($report['category_name']) ?></span></li>
                    <li><span class="label">Status</span><span class="value"><?= status_badge($report['status']) ?></span></li>
                    <li><span class="label">Priority</span><span class="value"><?= priority_badge($report['priority']) ?></span></li>
                    <li><span class="label">Department</span><span class="value"><?= e($report['department_name'] ?: 'Not assigned') ?></span></li>
                    <li><span class="label">Case officer</span><span class="value"><?= e($report['staff_name'] ?: 'Not assigned') ?></span></li>
                    <li><span class="label">Submitted</span><span class="value"><?= e(format_date($report['created_at'], 'M j, Y')) ?></span></li>
                    <li><span class="label">Resolved</span><span class="value"><?= e($report['resolved_at'] ? format_date($report['resolved_at'], 'M j, Y') : '—') ?></span></li>
                </ul>
            </div>
        </div>

        <div class="side-card">
            <div class="side-card-head"><i class="fa-solid fa-user"></i> Citizen</div>
            <div class="side-card-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="avatar"><?= e(initials($report['citizen_name'])) ?></span>
                    <div class="min-w-0">
                        <strong class="d-block fs-7 text-truncate"><?= e($report['citizen_name']) ?></strong>
                        <span class="fs-8 text-body-secondary">Citizen reporter</span>
                    </div>
                </div>
                <ul class="detail-list">
                    <li><span class="label">Email</span><span class="value"><a href="mailto:<?= e($report['citizen_email']) ?>"><?= e($report['citizen_email']) ?></a></span></li>
                    <li><span class="label">Phone</span><span class="value"><?= e($report['citizen_phone'] ?: '—') ?></span></li>
                </ul>
            </div>
        </div>

        <?php if (!in_array($report['status'], ['resolved'], true)): ?>
            <div class="side-card">
                <div class="side-card-head"><i class="fa-solid fa-people-carry-box"></i> Assign to department</div>
                <div class="side-card-body">
                    <form method="post" action="<?= e(url('admin/report_view.php?id=' . $reportId)) ?>" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="assign">
                        <div class="mb-2">
                            <label class="form-label" for="assignDepartment">Department</label>
                            <select class="form-select form-select-sm" id="assignDepartment" name="department_id" required>
                                <option value="">Choose department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?= (int) $department['id'] ?>" <?= (int) $report['department_id'] === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="assignStaff">Assigned staff</label>
                            <select class="form-select form-select-sm" id="assignStaff" name="staff_id" required>
                                <option value="">Choose staff member</option>
                                <?php foreach ($staffMembers as $staff): ?>
                                    <option value="<?= (int) $staff['id'] ?>" data-department="<?= (int) $staff['department_id'] ?>" <?= (int) $report['assigned_staff_id'] === (int) $staff['id'] ? 'selected' : '' ?>><?= e($staff['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text" id="staffHint">Staff options update with the selected department.</div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="assignPriority">Priority</label>
                            <select class="form-select form-select-sm" id="assignPriority" name="priority" required>
                                <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $report['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="assignmentNote">Assignment note</label>
                            <textarea class="form-control form-control-sm" id="assignmentNote" name="assignment_note" rows="2" maxlength="500" placeholder="Instructions for the department (optional)"></textarea>
                        </div>
                        <button class="btn btn-primary btn-sm w-100" type="submit"><i class="fa-solid fa-paper-plane me-1"></i> Assign report</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="side-card">
            <div class="side-card-head"><i class="fa-solid fa-arrow-right-arrow-left"></i> Update status</div>
            <div class="side-card-body">
                <form method="post" action="<?= e(url('admin/report_view.php?id=' . $reportId)) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_status">
                    <div class="mb-2">
                        <label class="form-label" for="statusSelect">New status</label>
                        <select class="form-select form-select-sm" id="statusSelect" name="status" required>
                            <?php foreach (['under_review', 'approved', 'in_progress', 'resolved'] as $status): ?>
                                <option value="<?= e($status) ?>" <?= $report['status'] === $status ? 'selected' : '' ?>><?= e(status_meta($status)['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="statusNote">Update note</label>
                        <textarea class="form-control form-control-sm" id="statusNote" name="status_note" rows="2" maxlength="1000" placeholder="Visible to the citizen" required></textarea>
                    </div>
                    <button class="btn btn-navy btn-sm w-100" type="submit"><i class="fa-solid fa-rotate me-1"></i> Save status</button>
                </form>
                <?php if ($report['status'] !== 'rejected'): ?>
                    <hr class="my-3">
                    <button class="btn btn-outline-danger btn-sm w-100" type="button" data-bs-toggle="collapse" data-bs-target="#rejectPanel" aria-expanded="false" aria-controls="rejectPanel">
                        <i class="fa-solid fa-circle-xmark me-1"></i> Reject report
                    </button>
                    <div class="collapse mt-2" id="rejectPanel">
                        <form method="post" action="<?= e(url('admin/report_view.php?id=' . $reportId)) ?>" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <label class="form-label" for="rejectionReason">Rejection reason</label>
                            <textarea class="form-control form-control-sm mb-2" id="rejectionReason" name="rejection_reason" rows="3" maxlength="500" placeholder="Explain why this report cannot be actioned" required></textarea>
                            <button class="btn btn-danger btn-sm w-100" type="submit"><i class="fa-solid fa-xmark me-1"></i> Confirm rejection</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="side-card">
            <div class="side-card-head"><i class="fa-solid fa-note-sticky"></i> Internal notes</div>
            <div class="side-card-body">
                <form method="post" action="<?= e(url('admin/report_view.php?id=' . $reportId)) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_note">
                    <textarea class="form-control form-control-sm" name="admin_notes" rows="4" maxlength="5000" placeholder="Visible to administrators only"><?= e($report['admin_notes']) ?></textarea>
                    <button class="btn btn-soft btn-sm w-100 mt-2" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i> Save internal note</button>
                </form>
            </div>
        </div>

        <?php if ($assignments): ?>
            <div class="side-card">
                <div class="side-card-head"><i class="fa-solid fa-clock-rotate-left"></i> Assignment history</div>
                <div class="side-card-body">
                    <div class="timeline">
                        <?php foreach ($assignments as $assignment): ?>
                            <div class="timeline-item">
                                <span class="timeline-dot"></span>
                                <h5><?= e($assignment['department_name']) ?></h5>
                                <div class="timeline-meta"><?= e($assignment['staff_name'] ?: 'No staff selected') ?> · <?= e(format_datetime($assignment['created_at'])) ?></div>
                                <?php if ($assignment['note']): ?><div class="timeline-note"><?= e($assignment['note']) ?></div><?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var departmentSelect = document.getElementById('assignDepartment');
    var staffSelect = document.getElementById('assignStaff');
    var hint = document.getElementById('staffHint');

    function filterStaff() {
        if (!departmentSelect || !staffSelect) return;
        var departmentId = departmentSelect.value;
        var visible = 0;
        Array.prototype.forEach.call(staffSelect.options, function (option) {
            if (!option.value) return;
            var matches = option.getAttribute('data-department') === departmentId;
            option.hidden = !matches;
            option.disabled = !matches;
            if (!matches && option.selected) option.selected = false;
            if (matches) visible++;
        });
        if (hint) {
            hint.textContent = departmentId === '' ? 'Choose a department to see its staff.' : (visible ? visible + ' staff member' + (visible === 1 ? '' : 's') + ' available.' : 'No active staff are assigned to this department.');
        }
    }

    if (departmentSelect) {
        departmentSelect.addEventListener('change', filterStaff);
        filterStaff();
    }

    var mapElement = document.getElementById('reportMap');
    if (mapElement && typeof L !== 'undefined') {
        var lat = <?= json_encode((float) $report['latitude']) ?>;
        var lng = <?= json_encode((float) $report['longitude']) ?>;
        var map = L.map(mapElement).setView([lat, lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
        }).addTo(map);
        var icon = L.divIcon({
            className: 'cw-map-marker',
            html: '<div class="cw-marker-pin <?= e($report['status'] === 'resolved' ? 'resolved' : ($report['status'] === 'rejected' ? 'rejected' : ($report['status'] === 'pending' ? 'pending' : ''))) ?>"><i class="fa-solid fa-location-dot"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 30],
            popupAnchor: [0, -30]
        });
        L.marker([lat, lng], { icon: icon }).addTo(map)
            .bindPopup('<strong><?= e($report['reference_no']) ?></strong><?= e(short_text($report['address'], 80)) ?>')
            .openPopup();
    }
});
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
