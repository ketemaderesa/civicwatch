<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('staff');
$staffId = (int) $user['id'];

$reportId = get_int('id');
$report = fetch_one(
    "SELECT r.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon,
            u.full_name AS citizen_name, u.email AS citizen_email, u.phone AS citizen_phone,
            d.name AS department_name, d.email AS department_email, d.phone AS department_phone
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     JOIN users u ON u.id = r.user_id
     LEFT JOIN departments d ON d.id = r.department_id
     WHERE r.id = ? AND r.assigned_staff_id = ?
     LIMIT 1",
    [$reportId, $staffId]
);

if (!$report) {
    abort(404, 'That report is not assigned to your account.');
}

$citizenPhotos = report_images($reportId, 'report');
$evidence = report_images($reportId, 'evidence');
$remainingEvidence = max(0, MAX_REPORT_IMAGES - count($evidence));
$statusLocked = in_array($report['status'], ['resolved', 'rejected'], true);

if (is_post()) {
    require_csrf();
    $action = post('action');

    if ($action === 'update_status') {
        $newStatus = post('status');
        $note = post('status_note');
        $allowed = ['in_progress', 'resolved'];

        if ($statusLocked) {
            flash('warning', $report['status'] === 'resolved'
                ? 'This report is already resolved. Add a note if you need to record extra information.'
                : 'Rejected reports cannot be updated by staff.');
        } elseif (!in_array($newStatus, $allowed, true)) {
            flash('danger', 'Choose a valid next step: in progress or resolved.');
        } elseif ($newStatus === $report['status']) {
            flash('info', 'The report is already in that status. Use the progress note form for routine updates.');
        } elseif (mb_strlen($note) < 3 || mb_strlen($note) > 2000) {
            flash('danger', 'Add a resident-friendly progress note between 3 and 2,000 characters.');
        } elseif ($newStatus === 'resolved' && !post_bool('completion_confirm')) {
            flash('danger', 'Please confirm that the work is complete before resolving the report.');
        } else {
            try {
                db()->beginTransaction();

                $resolvedAtSql = $newStatus === 'resolved' ? 'NOW()' : 'NULL';
                $stmt = db()->prepare(
                    "UPDATE reports SET status = ?, resolved_at = {$resolvedAtSql}, updated_at = NOW() WHERE id = ? AND assigned_staff_id = ?"
                );
                $stmt->execute([$newStatus, $reportId, $staffId]);

                record_report_update($reportId, $staffId, $newStatus, $note);
                db()->commit();

                send_notification(
                    (int) $report['user_id'],
                    $newStatus === 'resolved' ? 'Report resolved' : 'Work has started',
                    $newStatus === 'resolved'
                        ? 'Good news — your report ' . $report['reference_no'] . ' has been marked as resolved. ' . $note
                        : 'Work has started on your report ' . $report['reference_no'] . '. ' . $note,
                    $reportId
                );

                if ($newStatus === 'resolved') {
                    notify_admins(
                        'Report resolved',
                        $user['full_name'] . ' resolved report ' . $report['reference_no'] . ' — ' . $report['title'] . '.',
                        $reportId
                    );
                }

                flash('success', $newStatus === 'resolved'
                    ? 'The report has been marked as resolved and the citizen has been notified.'
                    : 'The report is now in progress. The citizen has been notified.');
            } catch (Throwable $e) {
                if (db()->inTransaction()) {
                    db()->rollBack();
                }
                flash('danger', 'The status could not be updated. Please try again.');
            }
        }

        redirect('staff/report_view.php?id=' . $reportId);
    }

    if ($action === 'add_note') {
        $note = post('note');

        if ($report['status'] === 'rejected') {
            flash('warning', 'Rejected reports cannot receive new staff notes.');
        } elseif (mb_strlen($note) < 3 || mb_strlen($note) > 2000) {
            flash('danger', 'Write a progress note between 3 and 2,000 characters.');
        } else {
            record_report_update($reportId, $staffId, null, $note);
            send_notification(
                (int) $report['user_id'],
                'New update on your report',
                'A progress note was added to ' . $report['reference_no'] . ': ' . $note,
                $reportId
            );
            flash('success', 'Progress note added and the citizen has been notified.');
        }

        redirect('staff/report_view.php?id=' . $reportId);
    }

    if ($action === 'upload_evidence') {
        $files = normalized_files('evidence');
        $errors = [];

        if ($report['status'] === 'rejected') {
            $errors[] = 'Evidence cannot be added to a rejected report.';
        }
        if ($remainingEvidence <= 0) {
            $errors[] = 'The maximum of ' . MAX_REPORT_IMAGES . ' evidence images has already been reached.';
        }
        if (!$files) {
            $errors[] = 'Choose at least one completion photo to upload.';
        }
        if (count($files) > $remainingEvidence) {
            $errors[] = 'You can add ' . $remainingEvidence . ' more evidence image' . ($remainingEvidence === 1 ? '' : 's') . '.';
        }
        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'One of the selected images could not be uploaded. Please try again.';
                break;
            }
            if ((int) ($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
                $errors[] = 'Each image must be smaller than 5 MB.';
                break;
            }
        }

        if ($errors) {
            foreach (array_unique($errors) as $error) {
                flash('danger', $error);
            }
            redirect('staff/report_view.php?id=' . $reportId);
        }

        try {
            $result = save_report_images($files, 'evidence', $reportId, $staffId, 'evidence');
            if ($result['uploaded'] > 0) {
                record_report_update(
                    $reportId,
                    $staffId,
                    null,
                    'Completion evidence uploaded (' . $result['uploaded'] . ' image' . ($result['uploaded'] === 1 ? '' : 's') . ').'
                );
                send_notification(
                    (int) $report['user_id'],
                    'Completion evidence added',
                    $result['uploaded'] . ' completion photo' . ($result['uploaded'] === 1 ? '' : 's') . ' added to your report ' . $report['reference_no'] . '.',
                    $reportId
                );
                flash('success', $result['uploaded'] . ' evidence image' . ($result['uploaded'] === 1 ? '' : 's') . ' uploaded.');
            }
            foreach (array_unique($result['errors']) as $error) {
                flash('warning', $error);
            }
        } catch (Throwable $e) {
            flash('danger', 'The evidence images could not be saved. Please try again.');
        }

        redirect('staff/report_view.php?id=' . $reportId);
    }

    flash('danger', 'Unsupported action.');
    redirect('staff/report_view.php?id=' . $reportId);
}

$updates = report_updates($reportId);
$flow = ['pending', 'under_review', 'approved', 'assigned', 'in_progress', 'resolved'];
$currentIndex = array_search($report['status'], $flow, true);
$isRejected = $report['status'] === 'rejected';
$coverImage = $citizenPhotos[0]['file_path'] ?? null;

$pageTitle = 'Case ' . $report['reference_no'];
$pageSubtitle = $report['title'];
$activePage = 'reports';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('staff/reports.php')) ?>" title="Back to assigned reports"><i class="fa-solid fa-arrow-left"></i></a>
            <span class="badge-status status-approved" style="background:var(--cw-surface-2);color:var(--cw-muted);"><i class="fa-solid fa-hashtag"></i> <?= e($report['reference_no']) ?></span>
            <?= status_badge($report['status']) ?>
            <?= priority_badge($report['priority']) ?>
        </div>
        <h2><?= e($report['title']) ?></h2>
        <p>Reported by <?= e($report['citizen_name']) ?> · <?= e(format_datetime($report['created_at'])) ?></p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e(url('staff/reports.php')) ?>"><i class="fa-solid fa-list-check me-1"></i> My cases</a>
        <?php if (!$statusLocked): ?>
            <a class="btn btn-primary btn-sm" href="#progressPanel"><i class="fa-solid fa-pen-to-square me-1"></i> Update progress</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($isRejected && $report['rejection_reason']): ?>
    <div class="alert alert-danger">
        <strong><i class="fa-solid fa-circle-xmark me-2"></i>This report was rejected by an administrator.</strong>
        <div class="mt-1"><?= nl2br(e($report['rejection_reason'])) ?></div>
    </div>
<?php endif; ?>

<?php if ($report['status'] === 'resolved'): ?>
    <div class="alert alert-success d-flex align-items-start gap-2">
        <i class="fa-solid fa-circle-check mt-1"></i>
        <div>
            <strong>This case is resolved.</strong>
            <div class="small mt-1">Resolved <?= e(format_datetime($report['resolved_at'])) ?>. You can still add a note or completion evidence if something needs to be recorded.</div>
        </div>
    </div>
<?php elseif (!$statusLocked && count($evidence) === 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="fa-solid fa-camera mt-1"></i>
        <div>
            <strong>No completion evidence yet.</strong>
            <div class="small mt-1">Please upload a photo of the completed work before marking this case as resolved.</div>
        </div>
    </div>
<?php endif; ?>

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
                    <?= priority_badge($report['priority']) ?>
                </div>
                <div class="description-block"><?= nl2br(e($report['description'])) ?></div>

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
                        <strong><?= e(short_text($report['address'], 58)) ?></strong>
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
                <div><h3>Location on the map</h3><p>Navigate to the exact reported coordinates</p></div>
                <a class="btn btn-soft btn-sm" href="https://www.openstreetmap.org/?mlat=<?= e((string) $report['latitude']) ?>&mlon=<?= e((string) $report['longitude']) ?>#map=17/<?= e((string) $report['latitude']) ?>/<?= e((string) $report['longitude']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open map</a>
            </div>
            <div class="content-card-body">
                <div id="reportMap" class="map-panel"></div>
            </div>
        </div>

        <div class="content-card mb-3">
            <div class="content-card-head">
                <div><h3>Citizen photos (<?= count($citizenPhotos) ?>)</h3><p>Evidence supplied when the report was submitted</p></div>
            </div>
            <div class="content-card-body">
                <?php if ($citizenPhotos): ?>
                    <div class="image-gallery">
                        <?php foreach ($citizenPhotos as $image): ?>
                            <a class="gallery-thumb" href="<?= e(url($image['file_path'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($image['file_path'])) ?>" alt="<?= e($image['original_name'] ?: 'Citizen photo') ?>" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-body-secondary fs-7 mb-0">No photos were attached to this report.</p>
                <?php endif; ?>

                <h4 class="fs-7 text-uppercase text-body-secondary fw-700 mt-4 mb-2"><i class="fa-solid fa-camera-retro me-1"></i> Completion evidence (<?= count($evidence) ?>)</h4>
                <?php if ($evidence): ?>
                    <div class="image-gallery">
                        <?php foreach ($evidence as $image): ?>
                            <a class="gallery-thumb" href="<?= e(url($image['file_path'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($image['file_path'])) ?>" alt="<?= e($image['original_name'] ?: 'Completion evidence') ?>" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-body-secondary fs-7 mb-0">No completion evidence has been uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="content-card-head"><div><h3>Case history</h3><p>Every workflow update, oldest first</p></div></div>
            <div class="content-card-body">
                <div class="timeline">
                    <?php foreach ($updates as $update): ?>
                        <div class="timeline-item <?= e($update['status'] ?: '') ?>">
                            <span class="timeline-dot"></span>
                            <h5><?= e($update['status'] ? status_label($update['status']) : 'Progress note') ?></h5>
                            <div class="timeline-meta">
                                <?= e($update['full_name'] ?: 'CivicWatch') ?>
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
            <div class="side-card-head"><i class="fa-solid fa-circle-info"></i> Case details</div>
            <div class="side-card-body">
                <ul class="detail-list">
                    <li><span class="label">Reference</span><span class="value"><?= e($report['reference_no']) ?></span></li>
                    <li><span class="label">Category</span><span class="value"><?= e($report['category_name']) ?></span></li>
                    <li><span class="label">Status</span><span class="value"><?= status_badge($report['status']) ?></span></li>
                    <li><span class="label">Priority</span><span class="value"><?= priority_badge($report['priority']) ?></span></li>
                    <li><span class="label">Department</span><span class="value"><?= e($report['department_name'] ?: 'Not recorded') ?></span></li>
                    <li><span class="label">Submitted</span><span class="value"><?= e(format_date($report['created_at'], 'M j, Y')) ?></span></li>
                    <li><span class="label">Resolved</span><span class="value"><?= e($report['resolved_at'] ? format_date($report['resolved_at'], 'M j, Y') : '—') ?></span></li>
                </ul>
            </div>
        </div>

        <div class="side-card">
            <div class="side-card-head"><i class="fa-solid fa-user"></i> Citizen reporter</div>
            <div class="side-card-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="avatar"><?= e(initials($report['citizen_name'])) ?></span>
                    <div class="min-w-0">
                        <strong class="d-block fs-7 text-truncate"><?= e($report['citizen_name']) ?></strong>
                        <span class="fs-8 text-body-secondary">Community resident</span>
                    </div>
                </div>
                <ul class="detail-list">
                    <li><span class="label">Email</span><span class="value"><a href="mailto:<?= e($report['citizen_email']) ?>"><?= e($report['citizen_email']) ?></a></span></li>
                    <li><span class="label">Phone</span><span class="value"><?= e($report['citizen_phone'] ?: '—') ?></span></li>
                </ul>
            </div>
        </div>

        <?php if (!$statusLocked): ?>
            <div class="side-card" id="progressPanel">
                <div class="side-card-head"><i class="fa-solid fa-arrow-right-arrow-left"></i> Update progress</div>
                <div class="side-card-body">
                    <form method="post" action="<?= e(url('staff/report_view.php?id=' . $reportId)) ?>" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <div class="mb-2">
                            <label class="form-label" for="statusSelect">Next step</label>
                            <select class="form-select form-select-sm" id="statusSelect" name="status" required>
                                <option value="in_progress" <?= $report['status'] === 'in_progress' ? 'selected' : '' ?>>Work in progress</option>
                                <option value="resolved">Resolved — work complete</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="statusNote">Progress note for the citizen</label>
                            <textarea class="form-control form-control-sm" id="statusNote" name="status_note" rows="3" maxlength="2000" placeholder="What did you inspect or fix, and what happens next?" required></textarea>
                        </div>
                        <div class="form-check mb-2" id="completionCheck">
                            <input class="form-check-input" type="checkbox" value="1" id="completionConfirm" name="completion_confirm">
                            <label class="form-check-label fs-8" for="completionConfirm">I confirm the work is complete and this case can be closed.</label>
                        </div>
                        <button class="btn btn-primary btn-sm w-100" type="submit"><i class="fa-solid fa-rotate me-1"></i> Save progress</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($report['status'] !== 'rejected'): ?>
            <div class="side-card">
                <div class="side-card-head"><i class="fa-solid fa-note-sticky"></i> Add a progress note</div>
                <div class="side-card-body">
                    <form method="post" action="<?= e(url('staff/report_view.php?id=' . $reportId)) ?>" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add_note">
                        <textarea class="form-control form-control-sm" name="note" rows="3" maxlength="2000" placeholder="Share an update without changing the status" required></textarea>
                        <button class="btn btn-soft btn-sm w-100 mt-2" type="submit"><i class="fa-solid fa-comment-dots me-1"></i> Post note</button>
                    </form>
                </div>
            </div>

            <div class="side-card">
                <div class="side-card-head"><i class="fa-solid fa-camera-retro"></i> Completion evidence</div>
                <div class="side-card-body">
                    <?php if ($remainingEvidence > 0): ?>
                        <form method="post" action="<?= e(url('staff/report_view.php?id=' . $reportId)) ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="upload_evidence">
                            <div class="upload-drop">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <strong>Upload completion photos</strong>
                                <span><?= (int) $remainingEvidence ?> more allowed · 5 MB each</span>
                                <input class="form-control form-control-sm mt-2" type="file" id="evidenceFiles" name="evidence[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-preview="#evidencePreview" data-max-files="<?= (int) $remainingEvidence ?>">
                            </div>
                            <div class="upload-preview" id="evidencePreview"></div>
                            <button class="btn btn-navy btn-sm w-100 mt-2" type="submit"><i class="fa-solid fa-upload me-1"></i> Upload evidence</button>
                        </form>
                    <?php else: ?>
                        <p class="text-body-secondary fs-7 mb-0">The maximum of <?= (int) MAX_REPORT_IMAGES ?> evidence images has been reached.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($report['department_name']): ?>
            <div class="side-card">
                <div class="side-card-head"><i class="fa-solid fa-building-columns"></i> Department</div>
                <div class="side-card-body">
                    <ul class="detail-list">
                        <li><span class="label">Team</span><span class="value"><?= e($report['department_name']) ?></span></li>
                        <?php if ($report['department_email']): ?><li><span class="label">Email</span><span class="value"><a href="mailto:<?= e($report['department_email']) ?>"><?= e($report['department_email']) ?></a></span></li><?php endif; ?>
                        <?php if ($report['department_phone']): ?><li><span class="label">Phone</span><span class="value"><?= e($report['department_phone']) ?></span></li><?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var mapElement = document.getElementById('reportMap');
    if (mapElement && typeof L !== 'undefined') {
        var lat = <?= json_encode((float) $report['latitude']) ?>;
        var lng = <?= json_encode((float) $report['longitude']) ?>;
        var map = L.map(mapElement).setView([lat, lng], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
        }).addTo(map);
        var statusClass = <?= json_encode(in_array($report['status'], ['resolved', 'rejected', 'pending'], true) ? $report['status'] : '') ?>;
        var icon = L.divIcon({
            className: 'cw-map-marker',
            html: '<div class="cw-marker-pin ' + statusClass + '"><i class="fa-solid fa-location-dot"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 30],
            popupAnchor: [0, -30]
        });
        L.marker([lat, lng], { icon: icon }).addTo(map)
            .bindPopup('<strong><?= e($report['reference_no']) ?></strong><?= e(short_text($report['address'], 80)) ?>')
            .openPopup();
    }

    var statusSelect = document.getElementById('statusSelect');
    var completionCheck = document.getElementById('completionCheck');
    if (statusSelect && completionCheck) {
        function toggleCompletion() {
            completionCheck.style.display = statusSelect.value === 'resolved' ? '' : 'none';
            var checkbox = document.getElementById('completionConfirm');
            if (statusSelect.value !== 'resolved' && checkbox) checkbox.checked = false;
        }
        statusSelect.addEventListener('change', toggleCompletion);
        toggleCompletion();
    }
});
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
