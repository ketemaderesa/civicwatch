<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('citizen');
$userId = (int) $user['id'];

$reportId = get_int('id');
$report = fetch_one(
    "SELECT r.*, c.name AS category_name, c.color AS category_color, c.icon AS category_icon,
            d.name AS department_name, d.email AS department_email, d.phone AS department_phone,
            s.full_name AS staff_name, s.email AS staff_email, s.phone AS staff_phone
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     LEFT JOIN departments d ON d.id = r.department_id
     LEFT JOIN users s ON s.id = r.assigned_staff_id
     WHERE r.id = ? AND r.user_id = ?
     LIMIT 1",
    [$reportId, $userId]
);

if (!$report) {
    abort(404, 'That report could not be found in your account.');
}

$reportImages = report_images($reportId, 'report');
$evidence = report_images($reportId, 'evidence');
$remainingPhotos = max(0, MAX_REPORT_IMAGES - count($reportImages));

if (is_post()) {
    require_csrf();
    $action = post('action');

    if ($action === 'add_photos') {
        if (in_array($report['status'], ['resolved', 'rejected'], true)) {
            flash('warning', 'Photos can only be added while a report is still open.');
            redirect('user/report_view.php?id=' . $reportId);
        }

        if ($remainingPhotos <= 0) {
            flash('info', 'You have already attached the maximum of ' . MAX_REPORT_IMAGES . ' photos to this report.');
            redirect('user/report_view.php?id=' . $reportId);
        }

        $files = normalized_files('images');
        $errors = [];
        if (!$files) {
            $errors[] = 'Choose at least one photo to upload.';
        }
        if (count($files) > $remainingPhotos) {
            $errors[] = 'You can add ' . $remainingPhotos . ' more photo' . ($remainingPhotos === 1 ? '' : 's') . ' to this report.';
        }
        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errors[] = 'One of the selected photos could not be uploaded. Please try again.';
                break;
            }
            if ((int) ($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
                $errors[] = 'Each photo must be smaller than 5 MB.';
                break;
            }
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
            redirect('user/report_view.php?id=' . $reportId);
        }

        try {
            $result = save_report_images($files, 'reports', $reportId, $userId, 'report');
            if ($result['uploaded'] > 0) {
                record_report_update(
                    $reportId,
                    $userId,
                    null,
                    'Citizen added ' . $result['uploaded'] . ' additional photo' . ($result['uploaded'] === 1 ? '' : 's') . ' to the report.'
                );
                notify_admins(
                    'Additional photos added',
                    $user['full_name'] . ' added ' . $result['uploaded'] . ' photo' . ($result['uploaded'] === 1 ? '' : 's') . ' to report ' . $report['reference_no'] . '.',
                    $reportId
                );
                if ($report['assigned_staff_id']) {
                    send_notification(
                        (int) $report['assigned_staff_id'],
                        'New photos for your assigned report',
                        'The citizen added new photos to ' . $report['reference_no'] . ' — ' . $report['title'] . '.',
                        $reportId
                    );
                }
                flash('success', $result['uploaded'] . ' photo' . ($result['uploaded'] === 1 ? '' : 's') . ' added to your report.');
            }
            foreach (array_unique($result['errors']) as $error) {
                flash('warning', $error);
            }
        } catch (Throwable $e) {
            flash('danger', 'The photos could not be saved right now. Please try again.');
        }

        redirect('user/report_view.php?id=' . $reportId);
    }

    flash('danger', 'Unsupported action.');
    redirect('user/report_view.php?id=' . $reportId);
}

$updates = report_updates($reportId);
$flow = ['pending', 'under_review', 'approved', 'assigned', 'in_progress', 'resolved'];
$currentIndex = array_search($report['status'], $flow, true);
$isRejected = $report['status'] === 'rejected';
$coverImage = $reportImages[0]['file_path'] ?? null;

$pageTitle = 'Report ' . $report['reference_no'];
$pageSubtitle = $report['title'];
$activePage = 'reports';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('user/reports.php')) ?>" title="Back to my reports"><i class="fa-solid fa-arrow-left"></i></a>
            <span class="badge-status status-approved" style="background:var(--cw-surface-2);color:var(--cw-muted);"><i class="fa-solid fa-hashtag"></i> <?= e($report['reference_no']) ?></span>
            <?= status_badge($report['status']) ?>
            <?= priority_badge($report['priority']) ?>
        </div>
        <h2><?= e($report['title']) ?></h2>
        <p>Submitted <?= e(format_datetime($report['created_at'])) ?> · last updated <?= e(time_ago($report['updated_at'])) ?></p>
    </div>
    <div class="page-heading-actions">
        <a class="btn btn-soft btn-sm" href="<?= e(url('user/reports.php')) ?>"><i class="fa-solid fa-list-check me-1"></i> All my reports</a>
        <a class="btn btn-primary btn-sm" href="<?= e(url('user/submit_report.php')) ?>"><i class="fa-solid fa-circle-plus me-1"></i> New report</a>
    </div>
</div>

<?php if ($isRejected && $report['rejection_reason']): ?>
    <div class="alert alert-danger">
        <strong><i class="fa-solid fa-circle-xmark me-2"></i>This report was not accepted.</strong>
        <div class="mt-1"><?= nl2br(e($report['rejection_reason'])) ?></div>
        <div class="small mt-2">If you believe this is a mistake, contact the civic office with your tracking number.</div>
    </div>
<?php endif; ?>

<div class="detail-grid">
    <div>
        <div class="report-hero mb-3">
            <div class="report-hero-cover">
                <?php if ($coverImage): ?>
                    <a href="<?= e(url($coverImage)) ?>" target="_blank" rel="noopener"><img src="<?= e(url($coverImage)) ?>" alt="Photo attached to your report <?= e($report['reference_no']) ?>"></a>
                <?php else: ?>
                    <div class="cover-placeholder">
                        <i class="fa-solid <?= e($report['category_icon']) ?>"></i>
                        <span>No photo was attached to this report</span>
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
                        <span>Resolved</span>
                        <strong><?= e($report['resolved_at'] ? format_date($report['resolved_at'], 'M j, Y') : 'Not yet') ?></strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card mb-3">
            <div class="content-card-head">
                <div><h3>Location on the map</h3><p>The exact coordinates you supplied</p></div>
                <a class="btn btn-soft btn-sm" href="https://www.openstreetmap.org/?mlat=<?= e((string) $report['latitude']) ?>&mlon=<?= e((string) $report['longitude']) ?>#map=17/<?= e((string) $report['latitude']) ?>/<?= e((string) $report['longitude']) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open map</a>
            </div>
            <div class="content-card-body">
                <div id="reportMap" class="map-panel"></div>
            </div>
        </div>

        <div class="content-card mb-3">
            <div class="content-card-head">
                <div><h3>Photos (<?= count($reportImages) ?>)</h3><p>Images you attached to this report</p></div>
                <span class="filter-count"><i class="fa-solid fa-camera me-1"></i> <?= (int) $remainingPhotos ?> slot<?= $remainingPhotos === 1 ? '' : 's' ?> remaining</span>
            </div>
            <div class="content-card-body">
                <?php if ($reportImages): ?>
                    <div class="image-gallery">
                        <?php foreach ($reportImages as $image): ?>
                            <a class="gallery-thumb" href="<?= e(url($image['file_path'])) ?>" target="_blank" rel="noopener">
                                <img src="<?= e(url($image['file_path'])) ?>" alt="<?= e($image['original_name'] ?: 'Report photo') ?>" loading="lazy">
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-body-secondary fs-7 mb-0">No photos were attached. You can add photos below while the report is open.</p>
                <?php endif; ?>

                <?php if ($evidence): ?>
                    <h4 class="fs-7 text-uppercase text-body-secondary fw-700 mt-4 mb-2"><i class="fa-solid fa-camera-retro me-1"></i> Completion evidence from the department (<?= count($evidence) ?>)</h4>
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
            <div class="content-card-head"><div><h3>Report history</h3><p>Every step from submission to the latest update</p></div></div>
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
            <div class="side-card-head"><i class="fa-solid fa-circle-info"></i> Report details</div>
            <div class="side-card-body">
                <ul class="detail-list">
                    <li><span class="label">Reference</span><span class="value"><?= e($report['reference_no']) ?></span></li>
                    <li><span class="label">Category</span><span class="value"><?= e($report['category_name']) ?></span></li>
                    <li><span class="label">Status</span><span class="value"><?= status_badge($report['status']) ?></span></li>
                    <li><span class="label">Priority</span><span class="value"><?= priority_badge($report['priority']) ?></span></li>
                    <li><span class="label">Department</span><span class="value"><?= e($report['department_name'] ?: 'Not assigned yet') ?></span></li>
                    <li><span class="label">Case officer</span><span class="value"><?= e($report['staff_name'] ?: 'Not assigned yet') ?></span></li>
                    <li><span class="label">Submitted</span><span class="value"><?= e(format_date($report['created_at'], 'M j, Y')) ?></span></li>
                    <li><span class="label">Last update</span><span class="value"><?= e(time_ago($report['updated_at'])) ?></span></li>
                </ul>
                <button class="btn btn-soft btn-sm w-100 mt-3" type="button" data-copy="<?= e($report['reference_no']) ?>"><i class="fa-regular fa-copy me-1"></i> Copy tracking number</button>
            </div>
        </div>

        <?php if ($report['department_name']): ?>
            <div class="side-card">
                <div class="side-card-head"><i class="fa-solid fa-building-columns"></i> Responsible department</div>
                <div class="side-card-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="cell-icon"><i class="fa-solid fa-people-carry-box"></i></span>
                        <div class="min-w-0">
                            <strong class="d-block fs-7"><?= e($report['department_name']) ?></strong>
                            <span class="fs-8 text-body-secondary">Service team handling your report</span>
                        </div>
                    </div>
                    <?php if ($report['staff_name']): ?>
                        <div class="d-flex align-items-center gap-2 mt-3 mb-2">
                            <span class="avatar avatar-sm"><?= e(initials($report['staff_name'])) ?></span>
                            <div class="min-w-0">
                                <strong class="d-block fs-7"><?= e($report['staff_name']) ?></strong>
                                <span class="fs-8 text-body-secondary">Your case officer</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <ul class="detail-list">
                        <?php if ($report['department_email']): ?><li><span class="label">Email</span><span class="value"><a href="mailto:<?= e($report['department_email']) ?>"><?= e($report['department_email']) ?></a></span></li><?php endif; ?>
                        <?php if ($report['department_phone']): ?><li><span class="label">Phone</span><span class="value"><?= e($report['department_phone']) ?></span></li><?php endif; ?>
                        <?php if ($report['staff_email']): ?><li><span class="label">Officer email</span><span class="value"><a href="mailto:<?= e($report['staff_email']) ?>"><?= e($report['staff_email']) ?></a></span></li><?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!in_array($report['status'], ['resolved', 'rejected'], true)): ?>
            <div class="side-card">
                <div class="side-card-head"><i class="fa-solid fa-camera"></i> Add more photos</div>
                <div class="side-card-body">
                    <?php if ($remainingPhotos > 0): ?>
                        <form method="post" action="<?= e(url('user/report_view.php?id=' . $reportId)) ?>" enctype="multipart/form-data">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_photos">
                            <div class="upload-drop">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <strong>Add photos</strong>
                                <span><?= (int) $remainingPhotos ?> more allowed · 5 MB each</span>
                                <input class="form-control form-control-sm mt-2" type="file" id="moreImages" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-preview="#morePreview" data-max-files="<?= (int) $remainingPhotos ?>">
                            </div>
                            <div class="upload-preview" id="morePreview"></div>
                            <button class="btn btn-primary btn-sm w-100 mt-2" type="submit"><i class="fa-solid fa-upload me-1"></i> Upload photos</button>
                        </form>
                    <?php else: ?>
                        <p class="text-body-secondary fs-7 mb-0">You have reached the maximum of <?= (int) MAX_REPORT_IMAGES ?> photos for this report.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="side-card">
            <div class="side-card-head"><i class="fa-solid fa-headset"></i> Need help?</div>
            <div class="side-card-body">
                <p class="fs-7 text-body-secondary">Quote your tracking number when contacting the civic office about this report.</p>
                <ul class="detail-list">
                    <li><span class="label">Email</span><span class="value"><a href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a></span></li>
                    <li><span class="label">Phone</span><span class="value"><?= e(CONTACT_PHONE) ?></span></li>
                    <li><span class="label">Office hours</span><span class="value">Mon–Fri, 8:00 AM – 5:00 PM</span></li>
                </ul>
            </div>
        </div>
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
});
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
