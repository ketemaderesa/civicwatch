<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('citizen');
$userId = (int) $user['id'];

$categories = fetch_all('SELECT id, name, icon, color FROM categories WHERE is_active = 1 ORDER BY name ASC');

$values = [
    'title' => '',
    'category_id' => '',
    'description' => '',
    'address' => '',
    'latitude' => (string) DEFAULT_MAP_LAT,
    'longitude' => (string) DEFAULT_MAP_LNG,
    'priority' => 'medium',
];
$errors = [];

if (is_post()) {
    require_csrf();

    $values['title'] = post('title');
    $values['category_id'] = (string) post_int('category_id');
    $values['description'] = post('description');
    $values['address'] = post('address');
    $values['latitude'] = post('latitude');
    $values['longitude'] = post('longitude');
    $values['priority'] = post('priority', 'medium');

    $title = $values['title'];
    $description = $values['description'];
    $address = $values['address'];
    $categoryId = (int) $values['category_id'];
    $priority = $values['priority'];

    $category = $categoryId > 0
        ? fetch_one('SELECT id, name FROM categories WHERE id = ? AND is_active = 1', [$categoryId])
        : null;

    if (mb_strlen($title) < 6 || mb_strlen($title) > 180) {
        $errors[] = 'Give your report a clear title between 6 and 180 characters.';
    }
    if (!$category) {
        $errors[] = 'Choose the category that best describes the issue.';
    }
    if (mb_strlen($description) < 20 || mb_strlen($description) > 5000) {
        $errors[] = 'Describe the issue in 20–5,000 characters so the team knows what to expect.';
    }
    if (mb_strlen($address) < 5 || mb_strlen($address) > 255) {
        $errors[] = 'Enter the street, landmark or neighbourhood where the issue is located.';
    }
    if (!is_numeric($values['latitude']) || !is_numeric($values['longitude'])) {
        $errors[] = 'Choose the exact location on the map.';
    } else {
        $latitude = (float) $values['latitude'];
        $longitude = (float) $values['longitude'];
        if (!valid_coordinate($latitude, $longitude)) {
            $errors[] = 'The selected map location is not valid. Please place the pin again.';
        }
    }
    if (!in_array($priority, ['low', 'medium', 'high', 'critical'], true)) {
        $priority = 'medium';
        $values['priority'] = 'medium';
    }

    $files = normalized_files('images');
    if (count($files) > MAX_REPORT_IMAGES) {
        $errors[] = 'Please attach no more than ' . MAX_REPORT_IMAGES . ' photos.';
    }
    foreach ($files as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errors[] = 'One of the selected photos could not be uploaded. Please choose it again.';
            break;
        }
        if ((int) ($file['size'] ?? 0) > MAX_UPLOAD_BYTES) {
            $errors[] = 'Each photo must be smaller than 5 MB.';
            break;
        }
    }

    if (!$errors) {
        $reportId = 0;

        try {
            db()->beginTransaction();

            $reference = generate_reference();
            $stmt = db()->prepare(
                "INSERT INTO reports
                 (reference_no, user_id, category_id, title, description, address, latitude, longitude,
                  priority, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())"
            );
            $stmt->execute([
                $reference,
                $userId,
                $categoryId,
                $title,
                $description,
                $address,
                number_format($latitude, 8, '.', ''),
                number_format($longitude, 8, '.', ''),
                $priority,
            ]);
            $reportId = (int) db()->lastInsertId();

            record_report_update(
                $reportId,
                $userId,
                'pending',
                'Report submitted with a map location.'
            );

            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = 'We could not save your report right now. Please try again in a moment.';
        }

        if ($reportId > 0) {
            $uploadResult = ['uploaded' => 0, 'errors' => []];
            try {
                $uploadResult = save_report_images($files, 'reports', $reportId, $userId, 'report');
            } catch (Throwable $e) {
                $uploadResult['errors'][] = 'Your report was saved, but the photos could not be attached. You can add them from the report page.';
            }

            try {
                send_notification(
                    $userId,
                    'Report received',
                    'Your report ' . $reference . ' has been received and is now awaiting review by the civic office.',
                    $reportId
                );

                $adminTitle = in_array($priority, ['high', 'critical'], true)
                    ? 'New ' . $priority . ' priority report'
                    : 'New report submitted';
                notify_admins(
                    $adminTitle,
                    $user['full_name'] . ' submitted ' . $reference . ' — ' . $title . ' in ' . $category['name'] . '.',
                    $reportId
                );
            } catch (Throwable $e) {
                // The report is already saved; do not lose it because a notification failed.
            }

            flash('success', 'Your report was submitted successfully. Your tracking number is ' . $reference . '.');
            if ($uploadResult['uploaded'] > 0) {
                flash('info', $uploadResult['uploaded'] . ' photo' . ($uploadResult['uploaded'] === 1 ? '' : 's') . ' attached to your report.');
            }
            foreach (array_unique($uploadResult['errors']) as $uploadError) {
                flash('warning', $uploadError);
            }

            redirect('user/report_view.php?id=' . $reportId);
        }
    }
}

$pageTitle = 'Report an Issue';
$pageSubtitle = 'Tell us what needs attention and pin the exact location.';
$activePage = 'submit';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a class="btn btn-soft btn-sm btn-icon" href="<?= e(url('user/index.php')) ?>" title="Back to dashboard"><i class="fa-solid fa-arrow-left"></i></a>
            <span class="section-kicker mb-0"><i class="fa-solid fa-camera"></i> New community report</span>
        </div>
        <h2>Report an issue</h2>
        <p>The more detail you provide, the faster the right team can act.</p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <div class="fw-600 mb-1"><i class="fa-solid fa-circle-exclamation me-2"></i>Please fix the following:</div>
        <ul class="mb-0 ps-4">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('user/submit_report.php')) ?>" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="content-card mb-3">
                <div class="content-card-head">
                    <div>
                        <h3><span class="badge rounded-pill text-bg-light me-1">1</span> What is the issue?</h3>
                        <p>Choose a category and describe what you can see.</p>
                    </div>
                </div>
                <div class="content-card-body">
                    <div class="mb-3">
                        <label class="form-label" for="title">Report title</label>
                        <input class="form-control" type="text" id="title" name="title" maxlength="180" value="<?= e($values['title']) ?>" placeholder="e.g. Large pothole near the school crossing" required>
                        <div class="form-text">A short, specific summary helps the team triage your report.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label" for="category_id">Category</label>
                            <select class="form-select" id="category_id" name="category_id" required>
                                <option value="">Choose a category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === $values['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="priority">How urgent is it?</label>
                            <select class="form-select" id="priority" name="priority">
                                <?php foreach (['low' => 'Low – can wait', 'medium' => 'Medium – needs attention', 'high' => 'High – unsafe or disruptive', 'critical' => 'Critical – immediate danger'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $values['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">The civic office confirms the final priority.</div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="6" maxlength="5000" placeholder="Describe what you found, how long it has been there, and who it affects. Mention any immediate safety risk." required><?= e($values['description']) ?></textarea>
                        <div class="form-text d-flex justify-content-between">
                            <span>Include landmarks and anything the crew should know before arriving.</span>
                            <span id="descriptionCount">0 / 5000</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-card mb-3">
                <div class="content-card-head">
                    <div>
                        <h3><span class="badge rounded-pill text-bg-light me-1">2</span> Where is it?</h3>
                        <p>Place the pin as accurately as you can — exact coordinates help crews find the issue.</p>
                    </div>
                </div>
                <div class="content-card-body">
                    <div class="mb-3">
                        <label class="form-label" for="address">Street address or landmark</label>
                        <textarea class="form-control" id="address" name="address" rows="2" maxlength="255" placeholder="e.g. Bole Road, opposite Medhanialem Church, near the bus stop" required><?= e($values['address']) ?></textarea>
                    </div>

                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <span class="form-label mb-0">Pin the location</span>
                        <button class="btn btn-soft btn-sm" type="button" id="useLocation"><i class="fa-solid fa-location-crosshairs me-1"></i> Use my current location</button>
                    </div>
                    <div id="locationMap" class="map-picker" aria-label="Map for choosing the report location"></div>
                    <div class="map-help"><i class="fa-solid fa-hand-pointer me-1"></i>Click or tap the map to move the pin. Drag the pin for fine adjustments.</div>
                    <div class="row g-2 mt-2">
                        <div class="col-6">
                            <label class="form-label" for="latitude">Latitude</label>
                            <input class="form-control" type="number" id="latitude" name="latitude" value="<?= e($values['latitude']) ?>" min="-90" max="90" step="0.00000001" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="longitude">Longitude</label>
                            <input class="form-control" type="number" id="longitude" name="longitude" value="<?= e($values['longitude']) ?>" min="-180" max="180" step="0.00000001" required>
                        </div>
                    </div>
                    <div class="form-text" id="geoStatus" aria-live="polite">You can also enter the coordinates manually.</div>
                </div>
            </div>

            <div class="content-card">
                <div class="content-card-head">
                    <div>
                        <h3><span class="badge rounded-pill text-bg-light me-1">3</span> Add photos <span class="text-body-secondary fw-normal">(optional)</span></h3>
                        <p>Clear photos from different angles make verification much easier.</p>
                    </div>
                </div>
                <div class="content-card-body">
                    <div class="upload-drop">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <strong>Choose photos or drag them into this box</strong>
                        <span>Up to <?= (int) MAX_REPORT_IMAGES ?> images · JPG, PNG, WEBP or GIF · maximum 5 MB each</span>
                        <input class="form-control mt-3" type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-preview="#uploadPreview" data-max-files="<?= (int) MAX_REPORT_IMAGES ?>">
                    </div>
                    <div class="upload-preview" id="uploadPreview"></div>
                    <div class="form-text mt-2"><i class="fa-solid fa-shield-halved me-1"></i>Only image files are accepted. Uploads are validated and stored securely.</div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mt-3">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane me-1"></i> Submit report</button>
                <a class="btn btn-soft" href="<?= e(url('user/index.php')) ?>">Cancel</a>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="side-card mb-3">
                <div class="side-card-head"><i class="fa-solid fa-lightbulb"></i> A great report includes</div>
                <div class="side-card-body">
                    <ul class="list-clean">
                        <li><span class="list-label"><i class="fa-solid fa-location-dot text-teal"></i><span>An exact pin on the map</span></span><i class="fa-solid fa-check text-teal"></i></li>
                        <li><span class="list-label"><i class="fa-solid fa-camera text-teal"></i><span>A clear, well-lit photo</span></span><i class="fa-solid fa-check text-teal"></i></li>
                        <li><span class="list-label"><i class="fa-solid fa-ruler text-teal"></i><span>Size, depth or spread of the issue</span></span><i class="fa-solid fa-check text-teal"></i></li>
                        <li><span class="list-label"><i class="fa-solid fa-person-falling text-teal"></i><span>Any immediate safety risk</span></span><i class="fa-solid fa-check text-teal"></i></li>
                        <li><span class="list-label"><i class="fa-solid fa-clock text-teal"></i><span>How long it has been there</span></span><i class="fa-solid fa-check text-teal"></i></li>
                    </ul>
                </div>
            </div>

            <div class="side-card mb-3">
                <div class="side-card-head"><i class="fa-solid fa-route"></i> What happens next?</div>
                <div class="side-card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <span class="timeline-dot"></span>
                            <h5>1. Confirmation</h5>
                            <div class="timeline-note">You receive a tracking number and a notification as soon as your report is saved.</div>
                        </div>
                        <div class="timeline-item">
                            <span class="timeline-dot"></span>
                            <h5>2. Review and assignment</h5>
                            <div class="timeline-note">The civic office reviews the report and routes it to the responsible department.</div>
                        </div>
                        <div class="timeline-item">
                            <span class="timeline-dot"></span>
                            <h5>3. Work and resolution</h5>
                            <div class="timeline-note">Progress notes keep you informed until the issue is fixed.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="info-banner">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Immediate danger?</strong>
                    <div class="mt-1">If someone is in danger, contact emergency services first. CivicWatch is for non-emergency community issues.</div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var latInput = document.getElementById('latitude');
    var lngInput = document.getElementById('longitude');
    var mapElement = document.getElementById('locationMap');
    var status = document.getElementById('geoStatus');
    var description = document.getElementById('description');
    var counter = document.getElementById('descriptionCount');
    var useLocation = document.getElementById('useLocation');
    var map = null;
    var marker = null;

    function updateCounter() {
        if (description && counter) counter.textContent = description.value.length + ' / 5000';
    }
    if (description) {
        description.addEventListener('input', updateCounter);
        updateCounter();
    }

    function setInputs(lat, lng) {
        if (latInput) latInput.value = Number(lat).toFixed(8);
        if (lngInput) lngInput.value = Number(lng).toFixed(8);
    }

    function placeMarker(lat, lng, pan) {
        if (!map) return;
        var latlng = [lat, lng];
        if (!marker) {
            var icon = L.divIcon({
                className: 'cw-map-marker',
                html: '<div class="cw-marker-pin"><i class="fa-solid fa-location-dot"></i></div>',
                iconSize: [30, 30],
                iconAnchor: [15, 30]
            });
            marker = L.marker(latlng, { icon: icon, draggable: true }).addTo(map);
            marker.on('dragend', function () {
                var position = marker.getLatLng();
                setInputs(position.lat, position.lng);
            });
        } else {
            marker.setLatLng(latlng);
        }
        setInputs(lat, lng);
        if (pan) map.panTo(latlng);
    }

    if (mapElement && typeof L !== 'undefined') {
        map = L.map(mapElement).setView([<?= json_encode((float) DEFAULT_MAP_LAT) ?>, <?= json_encode((float) DEFAULT_MAP_LNG) ?>], <?= (int) DEFAULT_MAP_ZOOM ?>);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors'
        }).addTo(map);

        var initialLat = parseFloat(latInput && latInput.value ? latInput.value : '<?= e((string) DEFAULT_MAP_LAT) ?>');
        var initialLng = parseFloat(lngInput && lngInput.value ? lngInput.value : '<?= e((string) DEFAULT_MAP_LNG) ?>');
        if (isFinite(initialLat) && isFinite(initialLng) && initialLat >= -90 && initialLat <= 90 && initialLng >= -180 && initialLng <= 180) {
            placeMarker(initialLat, initialLng, false);
            map.setView([initialLat, initialLng], <?= (int) DEFAULT_MAP_ZOOM ?>);
        }

        map.on('click', function (event) {
            placeMarker(event.latlng.lat, event.latlng.lng, false);
        });

        [latInput, lngInput].forEach(function (input) {
            if (!input) return;
            input.addEventListener('change', function () {
                var lat = parseFloat(latInput.value);
                var lng = parseFloat(lngInput.value);
                if (isFinite(lat) && isFinite(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    placeMarker(lat, lng, true);
                }
            });
        });

        if (window.ResizeObserver) {
            new ResizeObserver(function () { map.invalidateSize(); }).observe(mapElement);
        }
    }

    if (useLocation && navigator.geolocation) {
        useLocation.addEventListener('click', function () {
            useLocation.disabled = true;
            useLocation.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Locating…';
            if (status) status.textContent = 'Requesting your current location…';

            navigator.geolocation.getCurrentPosition(function (position) {
                placeMarker(position.coords.latitude, position.coords.longitude, true);
                if (map) map.setZoom(17);
                if (status) status.textContent = 'Location captured. Adjust the pin if needed.';
                useLocation.disabled = false;
                useLocation.innerHTML = '<i class="fa-solid fa-location-crosshairs me-1"></i> Use my current location';
            }, function () {
                if (status) status.textContent = 'We could not access your location. Click the map or enter the coordinates manually.';
                useLocation.disabled = false;
                useLocation.innerHTML = '<i class="fa-solid fa-location-crosshairs me-1"></i> Use my current location';
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
        });
    } else if (useLocation) {
        useLocation.disabled = true;
        useLocation.title = 'Geolocation is not supported by this browser.';
    }
});
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
