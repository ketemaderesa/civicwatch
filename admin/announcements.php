<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

function notify_announcement(array $announcement): void
{
    $roleCondition = match ($announcement['audience']) {
        'citizens' => "role = 'citizen'",
        'staff' => "role = 'staff'",
        default => "role IN ('citizen','staff')",
    };
    $recipients = fetch_all("SELECT id FROM users WHERE is_active = 1 AND {$roleCondition}");
    foreach ($recipients as $recipient) {
        send_notification(
            (int) $recipient['id'],
            'Announcement: ' . $announcement['title'],
            short_text($announcement['content'], 300),
            null
        );
    }
}

if (is_post()) {
    require_csrf();
    $action = post('action');

    if ($action === 'create' || $action === 'update') {
        $announcementId = post_int('announcement_id');
        $title = post('title');
        $content = post('content');
        $audience = post('audience', 'all');
        $isPublished = post_bool('is_published') ? 1 : 0;
        $errors = [];

        if (mb_strlen($title) < 4 || mb_strlen($title) > 180) {
            $errors[] = 'Announcement title must be between 4 and 180 characters.';
        }
        if (mb_strlen($content) < 10 || mb_strlen($content) > 10000) {
            $errors[] = 'Announcement content must be between 10 and 10,000 characters.';
        }
        if (!in_array($audience, ['all', 'citizens', 'staff'], true)) {
            $errors[] = 'Please choose a valid audience.';
        }

        $existing = null;
        if ($action === 'update') {
            $existing = fetch_one('SELECT * FROM announcements WHERE id = ?', [$announcementId]);
            if (!$existing) {
                $errors[] = 'The announcement could not be found.';
            }
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
            redirect('admin/announcements.php');
        }

        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO announcements (title, content, audience, published_by, is_published, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$title, $content, $audience, $user['id'], $isPublished]);
            $announcementId = (int) db()->lastInsertId();
            if ($isPublished) {
                $created = fetch_one('SELECT * FROM announcements WHERE id = ?', [$announcementId]);
                notify_announcement($created);
            }
            flash('success', 'Announcement created' . ($isPublished ? ' and published to the selected audience.' : ' as a draft.'));
        } else {
            $stmt = db()->prepare(
                'UPDATE announcements SET title = ?, content = ?, audience = ?, is_published = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$title, $content, $audience, $isPublished, $announcementId]);
            if ($isPublished && !(int) $existing['is_published']) {
                $updated = fetch_one('SELECT * FROM announcements WHERE id = ?', [$announcementId]);
                notify_announcement($updated);
            }
            flash('success', 'Announcement updated successfully.');
        }
        redirect('admin/announcements.php');
    }

    if ($action === 'toggle') {
        $announcementId = post_int('announcement_id');
        $announcement = fetch_one('SELECT * FROM announcements WHERE id = ?', [$announcementId]);
        if (!$announcement) {
            flash('danger', 'Announcement not found.');
        } else {
            $newPublished = (int) $announcement['is_published'] === 1 ? 0 : 1;
            $stmt = db()->prepare('UPDATE announcements SET is_published = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$newPublished, $announcementId]);
            if ($newPublished) {
                notify_announcement($announcement);
            }
            flash('success', 'Announcement ' . ($newPublished ? 'published.' : 'unpublished.'));
        }
        redirect('admin/announcements.php');
    }

    if ($action === 'delete') {
        $announcementId = post_int('announcement_id');
        $stmt = db()->prepare('DELETE FROM announcements WHERE id = ?');
        $stmt->execute([$announcementId]);
        flash('success', 'Announcement deleted.');
        redirect('admin/announcements.php');
    }

    flash('danger', 'Unsupported action.');
    redirect('admin/announcements.php');
}

$announcements = fetch_all(
    'SELECT a.*, u.full_name AS publisher_name
     FROM announcements a
     LEFT JOIN users u ON u.id = a.published_by
     ORDER BY a.created_at DESC, a.id DESC'
);

$summary = [
    'total' => count($announcements),
    'published' => count(array_filter($announcements, static fn ($a) => (int) $a['is_published'] === 1)),
    'drafts' => count(array_filter($announcements, static fn ($a) => (int) $a['is_published'] === 0)),
    'audience' => count(array_filter($announcements, static fn ($a) => (int) $a['is_published'] === 1 && $a['audience'] === 'all')),
];

$pageTitle = 'Announcements';
$pageSubtitle = 'Share updates and service notices with citizens and staff.';
$activePage = 'announcements';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Community announcements</h2>
        <p><?= number_format($summary['published']) ?> published · <?= number_format($summary['drafts']) ?> draft<?= $summary['drafts'] === 1 ? '' : 's' ?></p>
    </div>
    <div class="page-heading-actions">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal"><i class="fa-solid fa-bullhorn me-1"></i> New announcement</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Announcements</div><div class="stat-value"><?= number_format($summary['total']) ?></div><div class="stat-note">All time</div></div><div class="stat-icon indigo"><i class="fa-solid fa-bullhorn"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Published</div><div class="stat-value"><?= number_format($summary['published']) ?></div><div class="stat-note">Visible to communities</div></div><div class="stat-icon green"><i class="fa-solid fa-eye"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Drafts</div><div class="stat-value"><?= number_format($summary['drafts']) ?></div><div class="stat-note">Not yet published</div></div><div class="stat-icon amber"><i class="fa-solid fa-pen-to-square"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Public notices</div><div class="stat-value"><?= number_format($summary['audience']) ?></div><div class="stat-note">Published to everyone</div></div><div class="stat-icon teal"><i class="fa-solid fa-users"></i></div></div></div></div>
</div>

<div class="content-card">
    <div class="content-card-head">
        <div><h3>Announcement history</h3><p>Newest announcements appear first</p></div>
        <span class="filter-count"><i class="fa-solid fa-circle-info me-1"></i> Published announcements are sent as notifications.</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Announcement</th>
                    <th>Audience</th>
                    <th>Status</th>
                    <th>Published by</th>
                    <th>Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($announcements): foreach ($announcements as $announcement): ?>
                    <tr>
                        <td style="max-width:420px;">
                            <span class="table-title"><?= e(short_text($announcement['title'], 72)) ?></span>
                            <span class="table-sub d-block text-truncate"><?= e(short_text($announcement['content'], 100)) ?></span>
                        </td>
                        <td>
                            <?php
                            $audienceMeta = [
                                'all' => ['label' => 'Everyone', 'class' => 'status-approved', 'icon' => 'fa-users'],
                                'citizens' => ['label' => 'Citizens', 'class' => 'status-under-review', 'icon' => 'fa-user'],
                                'staff' => ['label' => 'Staff', 'class' => 'status-assigned', 'icon' => 'fa-helmet-safety'],
                            ][$announcement['audience']];
                            ?>
                            <span class="badge-status <?= e($audienceMeta['class']) ?>"><i class="fa-solid <?= e($audienceMeta['icon']) ?>"></i> <?= e($audienceMeta['label']) ?></span>
                        </td>
                        <td>
                            <?php if ((int) $announcement['is_published']): ?>
                                <span class="badge-status status-resolved"><i class="fa-solid fa-circle"></i> Published</span>
                            <?php else: ?>
                                <span class="badge-status status-pending"><i class="fa-solid fa-circle"></i> Draft</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="fs-8"><?= e($announcement['publisher_name'] ?: 'Deleted user') ?></span></td>
                        <td><span class="table-sub"><?= e(format_date($announcement['created_at'], 'M j, Y')) ?><br><?= e(time_ago($announcement['created_at'])) ?></span></td>
                        <td class="text-end">
                            <div class="table-actions">
                                <button class="btn btn-soft btn-sm btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#editAnnouncement<?= (int) $announcement['id'] ?>" title="Edit announcement"><i class="fa-solid fa-pen"></i></button>
                                <form method="post" action="<?= e(url('admin/announcements.php')) ?>" class="d-inline" data-confirm="<?= (int) $announcement['is_published'] ? 'Unpublish this announcement?' : 'Publish this announcement now?' ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                                    <button class="btn btn-soft btn-sm btn-icon" type="submit" title="<?= (int) $announcement['is_published'] ? 'Unpublish' : 'Publish' ?>"><i class="fa-solid <?= (int) $announcement['is_published'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i></button>
                                </form>
                                <form method="post" action="<?= e(url('admin/announcements.php')) ?>" class="d-inline" data-confirm="Delete this announcement permanently?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                                    <button class="btn btn-soft btn-sm btn-icon text-danger" type="submit" title="Delete announcement"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6"><div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-bullhorn"></i></div><h4>No announcements yet</h4><p>Create your first announcement to keep residents informed.</p><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">New announcement</button></div></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php foreach ($announcements as $announcement): ?>
    <div class="modal fade" id="editAnnouncement<?= (int) $announcement['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="<?= e(url('admin/announcements.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="announcement_id" value="<?= (int) $announcement['id'] ?>">
                    <div class="modal-header"><h5 class="modal-title">Edit announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="annTitle<?= (int) $announcement['id'] ?>">Title *</label>
                            <input class="form-control" id="annTitle<?= (int) $announcement['id'] ?>" name="title" value="<?= e($announcement['title']) ?>" maxlength="180" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="annContent<?= (int) $announcement['id'] ?>">Content *</label>
                            <textarea class="form-control" id="annContent<?= (int) $announcement['id'] ?>" name="content" rows="6" maxlength="10000" required><?= e($announcement['content']) ?></textarea>
                        </div>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label" for="annAudience<?= (int) $announcement['id'] ?>">Audience</label>
                                <select class="form-select" id="annAudience<?= (int) $announcement['id'] ?>" name="audience">
                                    <option value="all" <?= $announcement['audience'] === 'all' ? 'selected' : '' ?>>Everyone</option>
                                    <option value="citizens" <?= $announcement['audience'] === 'citizens' ? 'selected' : '' ?>>Citizens only</option>
                                    <option value="staff" <?= $announcement['audience'] === 'staff' ? 'selected' : '' ?>>Department staff only</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="annPublished<?= (int) $announcement['id'] ?>" name="is_published" value="1" <?= (int) $announcement['is_published'] ? 'checked' : '' ?>>
                                    <label class="form-check-label fs-7" for="annPublished<?= (int) $announcement['id'] ?>">Publish announcement</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-soft btn-sm" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal fade" id="createAnnouncementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= e(url('admin/announcements.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Create announcement</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="newAnnTitle">Title *</label>
                        <input class="form-control" id="newAnnTitle" name="title" maxlength="180" placeholder="e.g. Water maintenance on Saturday" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newAnnContent">Content *</label>
                        <textarea class="form-control" id="newAnnContent" name="content" rows="6" maxlength="10000" placeholder="Write a clear update for your audience…" required></textarea>
                    </div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label" for="newAnnAudience">Audience</label>
                            <select class="form-select" id="newAnnAudience" name="audience">
                                <option value="all">Everyone</option>
                                <option value="citizens">Citizens only</option>
                                <option value="staff">Department staff only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="newAnnPublished" name="is_published" value="1" checked>
                                <label class="form-check-label fs-7" for="newAnnPublished">Publish immediately</label>
                            </div>
                        </div>
                    </div>
                    <div class="info-banner mt-3"><i class="fa-solid fa-bell"></i><span>Publishing sends a notification to every active user in the selected audience.</span></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-soft btn-sm" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-bullhorn me-1"></i>Create announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
