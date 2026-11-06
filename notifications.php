<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();
$userId = (int) $user['id'];

$reportUrl = static function (?int $reportId) use ($user): ?string {
    if (!$reportId) {
        return null;
    }
    return match ($user['role']) {
        'admin' => url('admin/report_view.php?id=' . $reportId),
        'staff' => url('staff/report_view.php?id=' . $reportId),
        default => url('user/report_view.php?id=' . $reportId),
    };
};

$filter = get('filter', 'all');
if (!in_array($filter, ['all', 'unread'], true)) {
    $filter = 'all';
}

if (is_post()) {
    require_csrf();
    $action = post('action');
    $returnFilter = post('return_filter', 'all');
    if (!in_array($returnFilter, ['all', 'unread'], true)) {
        $returnFilter = 'all';
    }
    $returnPage = max(1, post_int('return_page', 1));
    $back = 'notifications.php?filter=' . $returnFilter . '&page=' . $returnPage;

    if ($action === 'mark_all_read') {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        $stmt->execute([$userId]);
        $updated = $stmt->rowCount();
        flash($updated > 0 ? 'success' : 'info', $updated > 0
            ? $updated . ' notification' . ($updated === 1 ? '' : 's') . ' marked as read.'
            : 'You have no unread notifications.');
        redirect('notifications.php');
    }

    if ($action === 'mark_read') {
        $notificationId = post_int('notification_id');
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);
        flash('success', 'Notification marked as read.');
        redirect($back);
    }

    if ($action === 'delete') {
        $notificationId = post_int('notification_id');
        $stmt = db()->prepare('DELETE FROM notifications WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, $userId]);
        flash('success', 'Notification deleted.');
        redirect($back);
    }

    if ($action === 'open') {
        $notificationId = post_int('notification_id');
        $notification = fetch_one('SELECT * FROM notifications WHERE id = ? AND user_id = ? LIMIT 1', [$notificationId, $userId]);
        if (!$notification) {
            flash('danger', 'That notification could not be found.');
            redirect($back);
        }

        if (!(int) $notification['is_read']) {
            $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([$notificationId, $userId]);
        }

        $target = $reportUrl($notification['report_id'] ? (int) $notification['report_id'] : null);
        redirect($target ?? 'notifications.php');
    }

    flash('danger', 'Unsupported action.');
    redirect('notifications.php');
}

$unread = unread_notifications($userId);
$total = (int) fetch_value('SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$userId], 0);
$where = ' WHERE user_id = ?';
$whereAliased = ' WHERE n.user_id = ?';
$params = [$userId];
if ($filter === 'unread') {
    $where .= ' AND is_read = 0';
    $whereAliased .= ' AND n.is_read = 0';
}

$filteredTotal = (int) fetch_value('SELECT COUNT(*) FROM notifications' . $where, $params, 0);
$pagination = paginate($filteredTotal, 15);
$offset = (int) $pagination['offset'];
$limit = (int) $pagination['per_page'];

$notifications = fetch_all(
    "SELECT n.*, r.reference_no, r.title AS report_title
     FROM notifications n
     LEFT JOIN reports r ON r.id = n.report_id
     {$whereAliased}
     ORDER BY n.created_at DESC, n.id DESC
     LIMIT {$limit} OFFSET {$offset}",
    $params
);

$pageTitle = 'Notifications';
$pageSubtitle = 'Status changes, assignments and updates on your reports.';
$activePage = 'notifications';

require __DIR__ . '/includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Your notifications</h2>
        <p><?= $unread > 0 ? '<strong>' . number_format($unread) . '</strong> unread of ' . number_format($total) . ' total notification' . ($total === 1 ? '' : 's') . '.' : 'You are all caught up — no unread notifications.' ?></p>
    </div>
    <div class="page-heading-actions">
        <?php if ($unread > 0): ?>
            <form method="post" action="<?= e(url('notifications.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-check-double me-1"></i> Mark all as read</button>
            </form>
        <?php endif; ?>
        <a class="btn btn-soft btn-sm" href="<?= e(url(dashboard_path($user['role']))) ?>"><i class="fa-solid fa-gauge-high me-1"></i> Dashboard</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="content-card">
            <div class="content-card-head">
                <div>
                    <h3>Notification centre</h3>
                    <p>Showing <?= (int) $pagination['from'] ?>–<?= (int) $pagination['to'] ?> of <?= number_format($filteredTotal) ?></p>
                </div>
                <div class="d-flex gap-1">
                    <a class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-soft' ?>" href="<?= e(url('notifications.php?filter=all')) ?>">All (<?= number_format($total) ?>)</a>
                    <a class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-soft' ?>" href="<?= e(url('notifications.php?filter=unread')) ?>">Unread (<?= number_format($unread) ?>)</a>
                </div>
            </div>

            <?php if ($notifications): ?>
                <ul class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $isUnread = !(int) $notification['is_read'];
                        $title = mb_strtolower($notification['title']);
                        $icon = 'fa-bell';
                        if (str_contains($title, 'assign')) {
                            $icon = 'fa-user-check';
                        } elseif (str_contains($title, 'resolv')) {
                            $icon = 'fa-circle-check';
                        } elseif (str_contains($title, 'reject')) {
                            $icon = 'fa-circle-xmark';
                        } elseif (str_contains($title, 'evidence') || str_contains($title, 'photo')) {
                            $icon = 'fa-camera-retro';
                        } elseif (str_contains($title, 'progress') || str_contains($title, 'update') || str_contains($title, 'started')) {
                            $icon = 'fa-arrow-right-arrow-left';
                        }
                        $target = $reportUrl($notification['report_id'] ? (int) $notification['report_id'] : null);
                        ?>
                        <li class="notification-item <?= $isUnread ? 'unread' : '' ?>">
                            <div class="notif-icon"><i class="fa-solid <?= e($icon) ?>"></i></div>
                            <div class="notif-content">
                                <strong><?= e($notification['title']) ?></strong>
                                <p><?= nl2br(e($notification['message'])) ?></p>
                                <time>
                                    <i class="fa-regular fa-clock me-1"></i><?= e(time_ago($notification['created_at'])) ?>
                                    <?php if ($notification['reference_no']): ?>
                                        · <span class="report-ref"><?= e($notification['reference_no']) ?></span>
                                    <?php endif; ?>
                                </time>

                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <?php if ($target): ?>
                                        <form method="post" action="<?= e(url('notifications.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="open">
                                            <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                            <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                                            <input type="hidden" name="return_page" value="<?= (int) $pagination['page'] ?>">
                                            <button class="btn btn-soft btn-sm" type="submit"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Open report</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isUnread): ?>
                                        <form method="post" action="<?= e(url('notifications.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                            <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                                            <input type="hidden" name="return_page" value="<?= (int) $pagination['page'] ?>">
                                            <button class="btn btn-soft btn-sm" type="submit"><i class="fa-solid fa-check me-1"></i> Mark read</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" action="<?= e(url('notifications.php')) ?>" data-confirm="Delete this notification?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>">
                                        <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
                                        <input type="hidden" name="return_page" value="<?= (int) $pagination['page'] ?>">
                                        <button class="btn btn-outline-danger btn-sm" type="submit"><i class="fa-regular fa-trash-can me-1"></i> Delete</button>
                                    </form>
                                </div>
                            </div>
                            <?php if ($isUnread): ?><span class="unread-dot" title="Unread"></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($pagination['pages'] > 1): ?>
                    <div class="card-footer py-3"><?= pagination_html($pagination) ?></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fa-solid fa-bell-slash"></i></div>
                    <h4><?= $filter === 'unread' ? 'No unread notifications' : 'No notifications yet' ?></h4>
                    <p><?= $filter === 'unread' ? 'You have read everything. New updates will appear here.' : 'When your reports move through the workflow, updates will appear here.' ?></p>
                    <?php if ($filter === 'unread'): ?>
                        <a class="btn btn-soft" href="<?= e(url('notifications.php?filter=all')) ?>">View all notifications</a>
                    <?php else: ?>
                        <a class="btn btn-soft" href="<?= e(url(dashboard_path($user['role']))) ?>">Back to dashboard</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="side-card mb-3">
            <div class="side-card-head"><i class="fa-solid fa-chart-simple"></i> Notification summary</div>
            <div class="side-card-body">
                <ul class="list-clean">
                    <li><span class="list-label"><i class="fa-solid fa-bell text-teal"></i><span>Total notifications</span></span><strong><?= number_format($total) ?></strong></li>
                    <li><span class="list-label"><i class="fa-solid fa-circle text-teal"></i><span>Unread</span></span><strong><?= number_format($unread) ?></strong></li>
                    <li><span class="list-label"><i class="fa-solid fa-check-double text-teal"></i><span>Read</span></span><strong><?= number_format(max(0, $total - $unread)) ?></strong></li>
                </ul>
                <?php if ($unread > 0): ?>
                    <form method="post" action="<?= e(url('notifications.php')) ?>" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="mark_all_read">
                        <button class="btn btn-soft btn-sm w-100" type="submit"><i class="fa-solid fa-check-double me-1"></i> Mark all as read</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="side-card mb-3">
            <div class="side-card-head"><i class="fa-solid fa-circle-info"></i> How notifications work</div>
            <div class="side-card-body">
                <p class="fs-7 text-body-secondary mb-0">You receive a notification whenever a report is submitted, reviewed, assigned, updated with a progress note, or resolved. Notifications are private to your account.</p>
            </div>
        </div>

        <div class="side-card">
            <div class="side-card-head"><i class="fa-solid fa-shield-halved"></i> Privacy</div>
            <div class="side-card-body">
                <p class="fs-7 text-body-secondary mb-0">Only you can see and manage your notifications. Deleting a notification does not delete the related report or its history.</p>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/dashboard_footer.php'; ?>
