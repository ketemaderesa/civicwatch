<?php
/**
 * CivicWatch – Shared helpers, security and business logic.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ---------------------------------------------------------------------------
// Output and URL helpers
// ---------------------------------------------------------------------------

/** Escape a value for safe HTML output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an application URL (works when installed in a sub-folder). */
function url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if ($path === '') {
        return BASE_URL !== '' ? BASE_URL . '/' : '/';
    }

    return (BASE_URL !== '' ? BASE_URL : '') . '/' . ltrim($path, '/');
}

/** Build an asset URL. */
function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

/** Redirect to an application path and stop. */
function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Render a friendly HTTP error page and stop. */
function abort(int $code, string $message = 'Something went wrong.'): never
{
    http_response_code($code);
    $titles = [
        403 => 'Access denied',
        404 => 'Page not found',
        419 => 'Session expired',
        500 => 'Server error',
    ];
    $title = $titles[$code] ?? 'Request failed';
    $home = url('index.php');

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . e($title) . ' · ' . e(APP_NAME) . '</title>'
        . '<link rel="stylesheet" href="' . e(url('assets/css/style.css')) . '"></head>'
        . '<body class="error-page"><div class="error-card">'
        . '<div class="error-code">' . $code . '</div>'
        . '<h1>' . e($title) . '</h1><p>' . e($message) . '</p>'
        . '<a class="btn btn-primary" href="' . e($home) . '">Return home</a>'
        . '</div></body></html>';
    exit;
}

// ---------------------------------------------------------------------------
// Request helpers
// ---------------------------------------------------------------------------

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? trim((string) $value) : $default;
}

function get(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_scalar($value) ? trim((string) $value) : $default;
}

function post_int(string $key, int $default = 0): int
{
    return (int) ($_POST[$key] ?? $default);
}

function get_int(string $key, int $default = 0): int
{
    return (int) ($_GET[$key] ?? $default);
}

function post_bool(string $key): bool
{
    return isset($_POST[$key]) && in_array((string) $_POST[$key], ['1', 'on', 'true', 'yes'], true);
}

function input_array(string $key): array
{
    $value = $_POST[$key] ?? [];
    return is_array($value) ? $value : [];
}

// ---------------------------------------------------------------------------
// Flash messages and old form values
// ---------------------------------------------------------------------------

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($flashes) ? $flashes : [];
}

function render_flashes(): void
{
    foreach (get_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true) ? $flash['type'] : 'info';
        $icon = match ($type) {
            'success' => 'fa-circle-check',
            'danger' => 'fa-circle-exclamation',
            'warning' => 'fa-triangle-exclamation',
            default => 'fa-circle-info',
        };
        echo '<div class="alert alert-' . e($type) . ' alert-dismissible fade show app-alert" role="alert">'
            . '<i class="fa-solid ' . e($icon) . ' me-2"></i>' . e($flash['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

function flash_old(array $data): void
{
    unset($data['password'], $data['password_confirm'], $data['current_password'], $data['new_password']);
    $_SESSION['_old'] = $data;
}

function old(string $key, string $default = ''): string
{
    $value = $_SESSION['_old'][$key] ?? $default;
    return is_scalar($value) ? (string) $value : $default;
}

function clear_old(): void
{
    unset($_SESSION['_old']);
}

// ---------------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function require_csrf(): void
{
    if (!is_post()) {
        return;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        abort(403, 'Your security token is invalid or has expired. Refresh the page and try again.');
    }
}

// ---------------------------------------------------------------------------
// Authentication and roles
// ---------------------------------------------------------------------------

function auth_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    // Session integrity checks.
    if (!empty($_SESSION['_agent']) && !hash_equals($_SESSION['_agent'], hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        logout_user();
        return null;
    }

    if (!empty($_SESSION['_last_activity']) && (time() - (int) $_SESSION['_last_activity']) > 3600) {
        logout_user();
        return null;
    }
    $_SESSION['_last_activity'] = time();

    $stmt = db()->prepare(
        'SELECT u.*, d.name AS department_name
         FROM users u
         LEFT JOIN departments d ON d.id = u.department_id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !(int) $row['is_active']) {
        logout_user();
        return null;
    }

    $user = $row;
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['_agent'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    $_SESSION['_last_activity'] = time();
    unset($_SESSION['csrf_token']); // Rotate token on privilege change.
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $user = auth_user();
    if (!$user) {
        flash('warning', 'Please sign in to continue.');
        redirect('login.php');
    }
    return $user;
}

/**
 * @param string|array<string> $roles
 */
function require_role(string|array $roles): array
{
    $user = require_login();
    $roles = is_array($roles) ? $roles : [$roles];
    if (!in_array($user['role'], $roles, true)) {
        abort(403, 'Your account does not have permission to open this page.');
    }
    return $user;
}

function require_guest(): void
{
    $user = auth_user();
    if ($user) {
        redirect(dashboard_path($user['role']));
    }
}

function dashboard_path(string $role): string
{
    return match ($role) {
        'admin' => 'admin/index.php',
        'staff' => 'staff/index.php',
        default => 'user/index.php',
    };
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrator',
        'staff' => 'Department Staff',
        default => 'Citizen',
    };
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? 'U', 0, 1);
    $last = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
    return mb_strtoupper($first . $last);
}

// ---------------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------------

function format_date(?string $date, string $format = 'M j, Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function format_datetime(?string $date): string
{
    return format_date($date, 'M j, Y · g:i A');
}

function time_ago(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    if (!$ts) {
        return '—';
    }
    $diff = time() - $ts;

    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . ' min' . ($mins === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
    }
    return format_date($date);
}

function short_text(?string $text, int $limit = 110): string
{
    $text = trim((string) $text);
    if (mb_strlen($text) <= $limit) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $limit - 1)) . '…';
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

// ---------------------------------------------------------------------------
// Issue status and priority metadata
// ---------------------------------------------------------------------------

function statuses(): array
{
    return [
        'pending'      => ['label' => 'Pending', 'class' => 'status-pending', 'icon' => 'fa-hourglass-half', 'percent' => 10],
        'under_review' => ['label' => 'Under Review', 'class' => 'status-under-review', 'icon' => 'fa-magnifying-glass', 'percent' => 25],
        'approved'     => ['label' => 'Approved', 'class' => 'status-approved', 'icon' => 'fa-circle-check', 'percent' => 40],
        'assigned'     => ['label' => 'Assigned', 'class' => 'status-assigned', 'icon' => 'fa-user-check', 'percent' => 55],
        'in_progress'  => ['label' => 'In Progress', 'class' => 'status-in-progress', 'icon' => 'fa-person-digging', 'percent' => 75],
        'resolved'     => ['label' => 'Resolved', 'class' => 'status-resolved', 'icon' => 'fa-check-double', 'percent' => 100],
        'rejected'     => ['label' => 'Rejected', 'class' => 'status-rejected', 'icon' => 'fa-circle-xmark', 'percent' => 100],
    ];
}

function status_meta(string $status): array
{
    $all = statuses();
    return $all[$status] ?? ['label' => ucwords(str_replace('_', ' ', $status)), 'class' => 'status-pending', 'icon' => 'fa-circle-info', 'percent' => 0];
}

function status_badge(string $status): string
{
    $meta = status_meta($status);
    return '<span class="badge-status ' . e($meta['class']) . '"><i class="fa-solid ' . e($meta['icon']) . '"></i> ' . e($meta['label']) . '</span>';
}

function status_label(string $status): string
{
    return status_meta($status)['label'];
}

function priority_meta(string $priority): array
{
    return match ($priority) {
        'critical' => ['label' => 'Critical', 'class' => 'priority-critical'],
        'high'     => ['label' => 'High', 'class' => 'priority-high'],
        'low'      => ['label' => 'Low', 'class' => 'priority-low'],
        default    => ['label' => 'Medium', 'class' => 'priority-medium'],
    };
}

function priority_badge(string $priority): string
{
    $meta = priority_meta($priority);
    return '<span class="badge-priority ' . e($meta['class']) . '">' . e($meta['label']) . '</span>';
}

function progress_percent(string $status): int
{
    return (int) status_meta($status)['percent'];
}

// ---------------------------------------------------------------------------
// Database convenience helpers
// ---------------------------------------------------------------------------

function fetch_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetch_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetch_value(string $sql, array $params = [], mixed $default = 0): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

// ---------------------------------------------------------------------------
// Notifications, updates and announcements
// ---------------------------------------------------------------------------

function send_notification(int $userId, string $title, string $message, ?int $reportId = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, report_id, title, message, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())'
    );
    $stmt->execute([$userId, $reportId, $title, $message]);
}

function notify_admins(string $title, string $message, ?int $reportId = null): void
{
    $admins = fetch_all("SELECT id FROM users WHERE role = 'admin' AND is_active = 1");
    foreach ($admins as $admin) {
        send_notification((int) $admin['id'], $title, $message, $reportId);
    }
}

function unread_notifications(int $userId): int
{
    return (int) fetch_value(
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId],
        0
    );
}

function record_report_update(int $reportId, ?int $userId, ?string $status, string $note): void
{
    $stmt = db()->prepare(
        'INSERT INTO report_updates (report_id, user_id, status, note, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$reportId, $userId, $status, $note]);
}

function report_updates(int $reportId): array
{
    return fetch_all(
        'SELECT ru.*, u.full_name, u.role
         FROM report_updates ru
         LEFT JOIN users u ON u.id = ru.user_id
         WHERE ru.report_id = ?
         ORDER BY ru.created_at ASC, ru.id ASC',
        [$reportId]
    );
}

function report_images(int $reportId, ?string $type = null): array
{
    if ($type !== null) {
        return fetch_all(
            'SELECT * FROM report_images WHERE report_id = ? AND image_type = ? ORDER BY id ASC',
            [$reportId, $type]
        );
    }

    return fetch_all('SELECT * FROM report_images WHERE report_id = ? ORDER BY id ASC', [$reportId]);
}

function announcement_audience_for(string $role): array
{
    return match ($role) {
        'staff' => ['all', 'staff'],
        'admin' => ['all', 'citizens', 'staff'],
        default => ['all', 'citizens'],
    };
}

// ---------------------------------------------------------------------------
// Validation helpers
// ---------------------------------------------------------------------------

function valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($email) <= 160;
}

function valid_coordinate(float $lat, float $lng): bool
{
    return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat !== 0.0 || $lng !== 0.0);
}

function generate_reference(): string
{
    for ($i = 0; $i < 8; $i++) {
        $candidate = 'CW-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $exists = fetch_value('SELECT COUNT(*) FROM reports WHERE reference_no = ?', [$candidate], 0);
        if ((int) $exists === 0) {
            return $candidate;
        }
    }
    return 'CW-' . date('YmdHis') . '-' . random_int(100, 999);
}

// ---------------------------------------------------------------------------
// Upload helpers
// ---------------------------------------------------------------------------

/**
 * Normalise the PHP $_FILES array for a multiple-upload field.
 *
 * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function normalized_files(string $field): array
{
    if (empty($_FILES[$field])) {
        return [];
    }

    $files = $_FILES[$field];
    $result = [];

    if (!is_array($files['name'])) {
        return [$files];
    }

    foreach ($files['name'] as $index => $name) {
        if (($files['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $result[] = [
            'name' => (string) $name,
            'type' => (string) ($files['type'][$index] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($files['size'][$index] ?? 0),
        ];
    }

    return $result;
}

/**
 * Validate and store one image.
 *
 * @return array{ok:bool,path?:string,original?:string,mime?:string,size?:int,error?:string}
 */
function store_image(array $file, string $subdir): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'One of the selected files could not be uploaded. Please try again.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > MAX_UPLOAD_BYTES) {
        return ['ok' => false, 'error' => 'Each image must be smaller than 5 MB.'];
    }

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return ['ok' => false, 'error' => 'Invalid upload source.'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!is_string($mime) || !isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'Only JPG, PNG, WEBP and GIF images are allowed.'];
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return ['ok' => false, 'error' => 'The selected file is not a valid image.'];
    }

    $subdir = trim(str_replace(['..', '\\'], '', $subdir), '/');
    $targetDir = UPLOAD_ROOT . DIRECTORY_SEPARATOR . $subdir;
    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return ['ok' => false, 'error' => 'The upload directory is not writable.'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    $target = $targetDir . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'The image could not be saved. Check folder permissions.'];
    }

    return [
        'ok' => true,
        'path' => 'uploads/' . $subdir . '/' . $filename,
        'original' => mb_substr(basename((string) ($file['name'] ?? 'image')), 0, 180),
        'mime' => $mime,
        'size' => $size,
    ];
}

/**
 * Store and persist uploaded images for a report.
 *
 * @param array<int,array> $files
 * @return array{uploaded:int,errors:array<string>}
 */
function save_report_images(array $files, string $subdir, int $reportId, int $uploadedBy, string $type): array
{
    $uploaded = 0;
    $errors = [];

    foreach ($files as $file) {
        $result = store_image($file, $subdir);
        if (!$result['ok']) {
            $errors[] = $result['error'] ?? 'Upload failed.';
            continue;
        }

        try {
            $stmt = db()->prepare(
                'INSERT INTO report_images
                 (report_id, uploaded_by, image_type, file_path, original_name, mime_type, file_size, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $reportId,
                $uploadedBy,
                $type,
                $result['path'],
                $result['original'],
                $result['mime'],
                $result['size'],
            ]);
            $uploaded++;
        } catch (Throwable $e) {
            @unlink(APP_ROOT . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $result['path']));
            $errors[] = 'An image could not be linked to the report.';
        }
    }

    return ['uploaded' => $uploaded, 'errors' => $errors];
}

// ---------------------------------------------------------------------------
// Pagination helpers
// ---------------------------------------------------------------------------

/**
 * @return array{page:int,per_page:int,total:int,pages:int,offset:int,from:int,to:int}
 */
function paginate(int $total, int $perPage = 10, ?int $page = null): array
{
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = $page ?? max(1, get_int('page', 1));
    $page = max(1, min($page, $pages));

    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
        'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
        'to' => min($page * $perPage, $total),
    ];
}

function pagination_html(array $meta): string
{
    if ($meta['pages'] <= 1) {
        return '';
    }

    $query = $_GET;
    $html = '<nav aria-label="Pagination"><ul class="pagination pagination-sm justify-content-center mb-0">';

    $makeLink = static function (int $page, string $label, bool $active = false, bool $disabled = false) use ($query): string {
        $query['page'] = $page;
        $url = '?' . http_build_query($query);
        $class = 'page-item' . ($active ? ' active' : '') . ($disabled ? ' disabled' : '');
        if ($disabled) {
            return '<li class="' . $class . '"><span class="page-link">' . $label . '</span></li>';
        }
        return '<li class="' . $class . '"><a class="page-link" href="' . e($url) . '">' . $label . '</a></li>';
    };

    $html .= $makeLink(max(1, $meta['page'] - 1), '<i class="fa-solid fa-chevron-left"></i>', false, $meta['page'] <= 1);

    $start = max(1, $meta['page'] - 2);
    $end = min($meta['pages'], $meta['page'] + 2);
    if ($start > 1) {
        $html .= $makeLink(1, '1');
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }
    for ($i = $start; $i <= $end; $i++) {
        $html .= $makeLink($i, (string) $i, $i === $meta['page']);
    }
    if ($end < $meta['pages']) {
        if ($end < $meta['pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= $makeLink($meta['pages'], (string) $meta['pages']);
    }

    $html .= $makeLink(min($meta['pages'], $meta['page'] + 1), '<i class="fa-solid fa-chevron-right"></i>', false, $meta['page'] >= $meta['pages']);
    $html .= '</ul></nav>';

    return $html;
}

/** Build an export URL preserving current filters. */
function query_with(array $overrides): string
{
    $query = array_merge($_GET, $overrides);
    return '?' . http_build_query($query);
}
