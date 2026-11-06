<?php
require_once __DIR__ . '/includes/functions.php';

$pageTitle = APP_NAME . ' – ' . APP_TAGLINE;
$metaDescription = 'Report road damage, water leaks, broken streetlights and other community issues. Track progress from submission to resolution with CivicWatch.';

$stats = [
    'reports' => (int) fetch_value('SELECT COUNT(*) FROM reports', [], 0),
    'resolved' => (int) fetch_value("SELECT COUNT(*) FROM reports WHERE status = 'resolved'", [], 0),
    'citizens' => (int) fetch_value("SELECT COUNT(*) FROM users WHERE role = 'citizen' AND is_active = 1", [], 0),
    'departments' => (int) fetch_value('SELECT COUNT(*) FROM departments WHERE is_active = 1', [], 0),
];

$categories = fetch_all(
    'SELECT c.*, COUNT(r.id) AS report_count
     FROM categories c
     LEFT JOIN reports r ON r.category_id = c.id
     WHERE c.is_active = 1
     GROUP BY c.id
     ORDER BY c.name ASC'
);

$recentReports = fetch_all(
    "SELECT r.*, c.name AS category_name, c.icon AS category_icon, c.color AS category_color,
            d.name AS department_name,
            (SELECT ri.file_path FROM report_images ri
             WHERE ri.report_id = r.id AND ri.image_type = 'report'
             ORDER BY ri.id ASC LIMIT 1) AS cover_image
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     LEFT JOIN departments d ON d.id = r.department_id
     WHERE r.status <> 'rejected'
     ORDER BY r.created_at DESC
     LIMIT 6"
);

$mapReports = fetch_all(
    "SELECT r.reference_no, r.title, r.status, r.latitude, r.longitude,
            c.name AS category_name, c.color AS category_color
     FROM reports r
     JOIN categories c ON c.id = r.category_id
     WHERE r.status <> 'rejected'
     ORDER BY r.created_at DESC
     LIMIT 80"
);

$announcement = fetch_one(
    "SELECT * FROM announcements
     WHERE is_published = 1 AND audience IN ('all', 'citizens')
     ORDER BY created_at DESC LIMIT 1"
);

$resolutionRate = $stats['reports'] > 0 ? round(($stats['resolved'] / $stats['reports']) * 100) : 0;

require __DIR__ . '/includes/public_header.php';
?>

<section class="hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <span class="hero-eyebrow"><span class="pulse"></span> Community issues, resolved together</span>
                <h1>Your city speaks.<br><span class="accent">CivicWatch</span> listens.</h1>
                <p class="hero-lead">Report potholes, water leaks, broken lights and more in under a minute. Follow every update as the right department takes action — no more wondering what happened to your report.</p>
                <div class="hero-actions">
                    <?php if (auth_user()): ?>
                        <a class="btn btn-light btn-lg" href="<?= e(url(dashboard_path(auth_user()['role']))) ?>">
                            <i class="fa-solid fa-gauge-high me-2"></i> Open my dashboard
                        </a>
                    <?php else: ?>
                        <a class="btn btn-light btn-lg" href="<?= e(url('register.php')) ?>">
                            <i class="fa-solid fa-circle-plus me-2"></i> Report an issue
                        </a>
                    <?php endif; ?>
                    <a class="btn btn-outline-light btn-lg" href="#how-it-works">
                        See how it works <i class="fa-solid fa-arrow-down ms-1"></i>
                    </a>
                </div>
                <div class="hero-trust">
                    <div class="hero-trust-item">
                        <strong><?= number_format($stats['reports']) ?></strong>
                        <span>Issues reported</span>
                    </div>
                    <div class="hero-trust-item">
                        <strong><?= number_format($stats['resolved']) ?></strong>
                        <span>Resolved</span>
                    </div>
                    <div class="hero-trust-item">
                        <strong><?= e($resolutionRate) ?>%</strong>
                        <span>Resolution rate</span>
                    </div>
                    <div class="hero-trust-item">
                        <strong><?= number_format($stats['citizens']) ?></strong>
                        <span>Active citizens</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hero-visual">
                    <div class="hero-map-card">
                        <div class="map-badge"><i class="fa-solid fa-location-dot"></i> Live community map</div>
                        <div id="communityMap" class="hero-map" aria-label="Map of recent community reports"></div>
                    </div>
                    <div class="hero-floating-card">
                        <div class="icon-blob"><i class="fa-solid fa-bolt"></i></div>
                        <div>
                            <strong>Real-time tracking</strong>
                            <span>Every status update is visible to you</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($announcement): ?>
<div class="container mt-4">
    <div class="alert alert-info d-flex align-items-start gap-2 mb-0">
        <i class="fa-solid fa-bullhorn mt-1"></i>
        <div>
            <strong><?= e($announcement['title']) ?></strong>
            <div class="small mt-1"><?= nl2br(e(short_text($announcement['content'], 260))) ?></div>
        </div>
    </div>
</div>
<?php endif; ?>

<section class="section" id="how-it-works">
    <div class="container">
        <div class="section-head mx-auto text-center">
            <span class="section-kicker"><i class="fa-solid fa-route"></i> Simple by design</span>
            <h2 class="section-title">From “I spotted it” to “It’s fixed.”</h2>
            <p class="section-lead">CivicWatch gives every report a clear path, the right department, and a visible history of what happened along the way.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-6 col-xl-3">
                <div class="feature-card">
                    <span class="feature-step">01</span>
                    <div class="feature-icon"><i class="fa-solid fa-camera"></i></div>
                    <h3>Capture the issue</h3>
                    <p>Add a photo, choose a category, describe the problem and drop a pin on the exact location.</p>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="feature-card">
                    <span class="feature-step">02</span>
                    <div class="feature-icon"><i class="fa-solid fa-paper-plane"></i></div>
                    <h3>Send your report</h3>
                    <p>Submit in seconds. You receive a unique tracking number and a confirmation notification immediately.</p>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="feature-card">
                    <span class="feature-step">03</span>
                    <div class="feature-icon"><i class="fa-solid fa-people-carry-box"></i></div>
                    <h3>We route it right</h3>
                    <p>Administrators review each report and assign it to the department responsible for that service.</p>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="feature-card">
                    <span class="feature-step">04</span>
                    <div class="feature-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <h3>Follow it to done</h3>
                    <p>Progress notes and evidence of completed work keep you informed from first review to resolution.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section-sm" id="categories">
    <div class="container">
        <div class="row align-items-end mb-4 g-3">
            <div class="col-lg-8">
                <span class="section-kicker"><i class="fa-solid fa-layer-group"></i> What you can report</span>
                <h2 class="section-title mb-0">One platform. Every community service.</h2>
            </div>
            <div class="col-lg-4 text-lg-end">
                <a class="btn btn-soft" href="<?= e(url('register.php')) ?>">Start a report <i class="fa-solid fa-arrow-right ms-1"></i></a>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($categories as $category): ?>
                <div class="col-sm-6 col-lg-3">
                    <div class="category-card">
                        <div class="category-icon" style="background: <?= e($category['color']) ?>1f; color: <?= e($category['color']) ?>;">
                            <i class="fa-solid <?= e($category['icon']) ?>"></i>
                        </div>
                        <div class="min-w-0">
                            <strong class="text-truncate"><?= e($category['name']) ?></strong>
                            <span><?= (int) $category['report_count'] ?> report<?= (int) $category['report_count'] === 1 ? '' : 's' ?></span>
                        </div>
                        <i class="fa-solid fa-arrow-right arrow"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section" id="recent-reports">
    <div class="container">
        <div class="section-head mx-auto text-center">
            <span class="section-kicker"><i class="fa-solid fa-clipboard-list"></i> Community activity</span>
            <h2 class="section-title">Recently reported</h2>
            <p class="section-lead">A transparent look at the issues neighbours are reporting and the progress being made across <?= e(COMMUNITY_NAME) ?>.</p>
        </div>
        <?php if ($recentReports): ?>
            <div class="row g-4">
                <?php foreach ($recentReports as $report): ?>
                    <div class="col-md-6 col-xl-4">
                        <article class="public-report-card">
                            <div class="public-report-cover">
                                <?php if (!empty($report['cover_image'])): ?>
                                    <img src="<?= e(url($report['cover_image'])) ?>" alt="Photo attached to report <?= e($report['reference_no']) ?>" loading="lazy">
                                <?php else: ?>
                                    <i class="fa-solid <?= e($report['category_icon']) ?> cover-icon"></i>
                                <?php endif; ?>
                                <?= status_badge($report['status']) ?>
                            </div>
                            <div class="public-report-body">
                                <div class="public-report-meta">
                                    <span><i class="fa-solid fa-tag"></i><?= e($report['category_name']) ?></span>
                                    <span><i class="fa-regular fa-clock"></i><?= e(time_ago($report['created_at'])) ?></span>
                                    <span><i class="fa-solid fa-location-dot"></i><?= e(short_text($report['address'], 34)) ?></span>
                                </div>
                                <h3><?= e($report['title']) ?></h3>
                                <p><?= e(short_text($report['description'], 105)) ?></p>
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <?= priority_badge($report['priority']) ?>
                                    <a class="small fw-600 text-teal" href="<?= e(url('login.php')) ?>">Track progress <i class="fa-solid fa-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state content-card">
                <div class="empty-icon"><i class="fa-solid fa-inbox"></i></div>
                <h4>No community reports yet</h4>
                <p>Be the first person to report an issue and help make your neighbourhood better.</p>
                <a class="btn btn-primary" href="<?= e(url('register.php')) ?>">Submit the first report</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="stats-band">
    <div class="container">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="stat-public">
                    <span class="number" data-counter="<?= e($stats['reports']) ?>">0</span>
                    <span class="label">Reports received</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-public">
                    <span class="number" data-counter="<?= e($stats['resolved']) ?>">0</span>
                    <span class="label">Issues resolved</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-public">
                    <span class="number" data-counter="<?= e($stats['departments']) ?>">0</span>
                    <span class="label">Service departments</span>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="stat-public">
                    <span class="number"><?= e($resolutionRate) ?><small>%</small></span>
                    <span class="label">Resolution rate</span>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section" id="contact">
    <div class="container">
        <div class="row g-4 align-items-stretch">
            <div class="col-lg-6">
                <span class="section-kicker"><i class="fa-solid fa-headset"></i> We’re here to help</span>
                <h2 class="section-title">Questions, feedback, or a community partnership?</h2>
                <p class="section-lead mb-4">CivicWatch is run in partnership with local service departments. Reach out if you need help with an existing report, want to partner with us, or have feedback about the platform.</p>
                <div class="contact-card">
                    <div class="contact-method">
                        <div class="icon"><i class="fa-solid fa-location-dot"></i></div>
                        <div><strong>CivicWatch office</strong><span><?= e(CONTACT_ADDRESS) ?></span></div>
                    </div>
                    <div class="contact-method">
                        <div class="icon"><i class="fa-solid fa-code"></i></div>
                        <div>
                            <strong>Project developer</strong>
                            <span class="d-block"><?= e(DEVELOPER_NAME) ?> &middot; <?= e(DEVELOPER_TITLE) ?></span>
                            <a href="mailto:<?= e(DEVELOPER_EMAIL) ?>"><?= e(DEVELOPER_EMAIL) ?></a>
                        </div>
                    </div>
                    <div class="contact-method">
                        <div class="icon"><i class="fa-solid fa-phone"></i></div>
                        <div><strong>Call the civic help desk</strong><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', CONTACT_PHONE)) ?>"><?= e(CONTACT_PHONE) ?></a></div>
                    </div>
                    <div class="contact-method">
                        <div class="icon"><i class="fa-solid fa-clock"></i></div>
                        <div><strong>Office hours</strong><span>Monday–Friday, 8:00 AM – 5:00 PM</span></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="h-100 d-flex flex-column justify-content-center p-4 p-lg-5 rounded-cw text-white" style="background: radial-gradient(circle at 85% 15%, rgba(27,188,171,.28), transparent 35%), linear-gradient(140deg,#0b1f3a,#123a63);">
                    <div class="feature-icon" style="background: rgba(255,255,255,.1); color: #4de0d0;"><i class="fa-solid fa-hand-holding-heart"></i></div>
                    <h2 class="section-title text-white mb-2">Be part of the solution.</h2>
                    <p style="color: rgba(255,255,255,.65); line-height: 1.7; font-size: .92rem;">Every report helps the city see what needs attention. Join <?= number_format($stats['citizens']) ?> residents already making <?= e(COMMUNITY_NAME) ?> a better place to live.</p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>"><i class="fa-solid fa-user-plus me-2"></i>Create an account</a>
                        <a class="btn btn-outline-light btn-lg" href="<?= e(url('login.php')) ?>">Sign in</a>
                    </div>
                    <div class="d-flex flex-wrap gap-4 mt-4 pt-3" style="border-top: 1px solid rgba(255,255,255,.14);">
                        <div><strong class="d-block" style="font-family:'Plus Jakarta Sans';font-size:1.2rem;">100%</strong><span style="font-size:.7rem;color:rgba(255,255,255,.5);">Free for residents</span></div>
                        <div><strong class="d-block" style="font-family:'Plus Jakarta Sans';font-size:1.2rem;">7</strong><span style="font-size:.7rem;color:rgba(255,255,255,.5);">Workflow states</span></div>
                        <div><strong class="d-block" style="font-family:'Plus Jakarta Sans';font-size:1.2rem;">24/7</strong><span style="font-size:.7rem;color:rgba(255,255,255,.5);">Report online</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var mapElement = document.getElementById('communityMap');
    if (!mapElement || typeof L === 'undefined') return;

    var reports = <?= json_encode($mapReports, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    var map = L.map(mapElement, { scrollWheelZoom: false, zoomControl: true }).setView([<?= e((string) DEFAULT_MAP_LAT) ?>, <?= e((string) DEFAULT_MAP_LNG) ?>], <?= e((string) DEFAULT_MAP_ZOOM) ?>);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
    }).addTo(map);

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : value;
        return div.innerHTML;
    }

    var bounds = [];
    reports.forEach(function (report) {
        var statusClass = ['resolved', 'rejected', 'pending'].indexOf(report.status) !== -1 ? report.status : '';
        var icon = L.divIcon({
            className: 'cw-map-marker',
            html: '<div class="cw-marker-pin ' + statusClass + '"><i class="fa-solid fa-location-dot"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 30],
            popupAnchor: [0, -30]
        });
        var marker = L.marker([parseFloat(report.latitude), parseFloat(report.longitude)], { icon: icon }).addTo(map);
        marker.bindPopup(
            '<strong>' + escapeHtml(report.title) + '</strong>' +
            '<span style="color:#68778c">' + escapeHtml(report.category_name) + ' · ' + escapeHtml(report.reference_no) + '</span>'
        );
        bounds.push([parseFloat(report.latitude), parseFloat(report.longitude)]);
    });

    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [32, 32], maxZoom: 14 });
    } else if (bounds.length === 1) {
        map.setView(bounds[0], 15);
    }

    if (window.ResizeObserver) {
        new ResizeObserver(function () { map.invalidateSize(); }).observe(mapElement);
    }

    // Animated public counters.
    document.querySelectorAll('[data-counter]').forEach(function (element) {
        var target = parseInt(element.getAttribute('data-counter'), 10) || 0;
        var duration = 900;
        var start = performance.now();
        function tick(now) {
            var progress = Math.min(1, (now - start) / duration);
            element.textContent = Math.round(target * (1 - Math.pow(1 - progress, 3))).toLocaleString();
            if (progress < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
    });
});
</script>

<?php require __DIR__ . '/includes/public_footer.php'; ?>
