<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

if (is_post()) {
    require_csrf();
    $action = post('action');

    if ($action === 'create' || $action === 'update') {
        $categoryId = post_int('category_id');
        $name = post('name');
        $slugInput = post('slug');
        $description = post('description');
        $icon = post('icon', 'fa-circle-exclamation');
        $color = post('color', '#0f9b8e');
        $isActive = post_bool('is_active') ? 1 : 0;
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Category name must be between 2 and 120 characters.';
        }
        if ($description !== '' && mb_strlen($description) > 255) {
            $errors[] = 'The description must be 255 characters or fewer.';
        }
        if (!preg_match('/^fa-[a-z0-9-]+$/', $icon)) {
            $errors[] = 'The icon must be a valid Font Awesome class such as fa-road.';
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $errors[] = 'Please choose a valid hex colour.';
        }

        $duplicate = fetch_one(
            'SELECT id FROM categories WHERE name = ?' . ($action === 'update' ? ' AND id <> ?' : '') . ' LIMIT 1',
            $action === 'update' ? [$name, $categoryId] : [$name]
        );
        if ($duplicate) {
            $errors[] = 'A category with this name already exists.';
        }

        $baseSlug = slugify($slugInput !== '' ? $slugInput : $name);
        if ($baseSlug === '') {
            $baseSlug = 'category';
        }
        $slug = $baseSlug;
        $counter = 2;
        while (true) {
            $slugCheck = fetch_one(
                'SELECT id FROM categories WHERE slug = ?' . ($action === 'update' ? ' AND id <> ?' : '') . ' LIMIT 1',
                $action === 'update' ? [$slug, $categoryId] : [$slug]
            );
            if (!$slugCheck) {
                break;
            }
            $slug = $baseSlug . '-' . $counter++;
        }

        if ($action === 'update' && !fetch_one('SELECT id FROM categories WHERE id = ?', [$categoryId])) {
            $errors[] = 'The category could not be found.';
        }

        if ($errors) {
            foreach ($errors as $error) {
                flash('danger', $error);
            }
            redirect('admin/categories.php');
        }

        if ($action === 'create') {
            $stmt = db()->prepare(
                'INSERT INTO categories (name, slug, description, icon, color, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([$name, $slug, $description ?: null, $icon, $color, $isActive]);
            flash('success', 'Category “' . $name . '” created successfully.');
        } else {
            $stmt = db()->prepare(
                'UPDATE categories SET name = ?, slug = ?, description = ?, icon = ?, color = ?, is_active = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$name, $slug, $description ?: null, $icon, $color, $isActive, $categoryId]);
            flash('success', 'Category updated successfully.');
        }
        redirect('admin/categories.php');
    }

    if ($action === 'delete') {
        $categoryId = post_int('category_id');
        $category = fetch_one('SELECT * FROM categories WHERE id = ?', [$categoryId]);
        if (!$category) {
            flash('danger', 'Category not found.');
            redirect('admin/categories.php');
        }

        $reportCount = (int) fetch_value('SELECT COUNT(*) FROM reports WHERE category_id = ?', [$categoryId], 0);
        if ($reportCount > 0) {
            flash('danger', 'This category is used by ' . $reportCount . ' report(s). Deactivate it instead of deleting it.');
        } else {
            $stmt = db()->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$categoryId]);
            flash('success', 'Category deleted.');
        }
        redirect('admin/categories.php');
    }

    flash('danger', 'Unsupported action.');
    redirect('admin/categories.php');
}

$categories = fetch_all(
    "SELECT c.*,
            (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id) AS report_count,
            (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id AND r.status NOT IN ('resolved','rejected')) AS open_count,
            (SELECT COUNT(*) FROM reports r WHERE r.category_id = c.id AND r.status = 'resolved') AS resolved_count
     FROM categories c
     ORDER BY c.is_active DESC, c.name ASC"
);
$summary = [
    'total' => count($categories),
    'active' => count(array_filter($categories, static fn ($c) => (int) $c['is_active'] === 1)),
    'reports' => (int) fetch_value('SELECT COUNT(*) FROM reports', [], 0),
    'top' => $categories[0]['name'] ?? '—',
];

$pageTitle = 'Issue Categories';
$pageSubtitle = 'Organise the types of community issues citizens can report.';
$activePage = 'categories';

require __DIR__ . '/../includes/dashboard_header.php';
?>

<div class="page-heading">
    <div>
        <h2>Issue categories</h2>
        <p><?= number_format($summary['active']) ?> active categories covering <?= number_format($summary['reports']) ?> reports.</p>
    </div>
    <div class="page-heading-actions">
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#createCategoryModal"><i class="fa-solid fa-plus me-1"></i> New category</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Categories</div><div class="stat-value"><?= number_format($summary['total']) ?></div><div class="stat-note">Configured issue types</div></div><div class="stat-icon indigo"><i class="fa-solid fa-layer-group"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Active</div><div class="stat-value"><?= number_format($summary['active']) ?></div><div class="stat-note">Available to citizens</div></div><div class="stat-icon green"><i class="fa-solid fa-circle-check"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Classified reports</div><div class="stat-value"><?= number_format($summary['reports']) ?></div><div class="stat-note">Across all categories</div></div><div class="stat-icon blue"><i class="fa-solid fa-clipboard-list"></i></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="stat-card"><div class="stat-top"><div><div class="stat-label">Most used</div><div class="stat-value fs-4" style="margin-top:.8rem;"><?= e(short_text($summary['top'], 16)) ?></div><div class="stat-note">By total reports</div></div><div class="stat-icon teal"><i class="fa-solid fa-arrow-trend-up"></i></div></div></div></div>
</div>

<div class="row g-3">
    <?php foreach ($categories as $category): ?>
        <div class="col-md-6 col-xl-3">
            <div class="content-card h-100">
                <div class="content-card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="category-icon" style="background: <?= e($category['color']) ?>1f; color: <?= e($category['color']) ?>;"><i class="fa-solid <?= e($category['icon']) ?>"></i></div>
                        <?php if ((int) $category['is_active']): ?>
                            <span class="badge-status status-resolved"><i class="fa-solid fa-circle"></i> Active</span>
                        <?php else: ?>
                            <span class="badge-status status-rejected"><i class="fa-solid fa-circle"></i> Inactive</span>
                        <?php endif; ?>
                    </div>
                    <h3 class="fs-7 fw-700 mb-1"><?= e($category['name']) ?></h3>
                    <p class="fs-8 text-body-secondary" style="min-height:36px;"><?= e(short_text($category['description'] ?: 'No description provided.', 90)) ?></p>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="fs-8 text-body-secondary"><?= (int) $category['report_count'] ?> report<?= (int) $category['report_count'] === 1 ? '' : 's' ?></span>
                        <span class="fs-8 text-body-secondary"><?= (int) $category['open_count'] ?> open · <?= (int) $category['resolved_count'] ?> resolved</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-soft btn-sm flex-grow-1" type="button" data-bs-toggle="modal" data-bs-target="#editCategory<?= (int) $category['id'] ?>"><i class="fa-solid fa-pen me-1"></i> Edit</button>
                        <a class="btn btn-soft btn-sm" href="<?= e(url('admin/reports.php?category=' . (int) $category['id'])) ?>" title="View reports"><i class="fa-solid fa-list"></i></a>
                        <form method="post" action="<?= e(url('admin/categories.php')) ?>" data-confirm="Delete this category? This is only possible when no reports use it.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                            <button class="btn btn-soft btn-sm text-danger" type="submit" title="Delete category"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editCategory<?= (int) $category['id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post" action="<?= e(url('admin/categories.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                        <div class="modal-header"><h5 class="modal-title">Edit category</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="name<?= (int) $category['id'] ?>">Category name *</label>
                                <input class="form-control" id="name<?= (int) $category['id'] ?>" name="name" value="<?= e($category['name']) ?>" maxlength="120" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="desc<?= (int) $category['id'] ?>">Description</label>
                                <textarea class="form-control" id="desc<?= (int) $category['id'] ?>" name="description" rows="2" maxlength="255"><?= e($category['description']) ?></textarea>
                            </div>
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label class="form-label" for="icon<?= (int) $category['id'] ?>">Font Awesome icon</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fa-solid <?= e($category['icon']) ?>" data-icon-preview="icon<?= (int) $category['id'] ?>"></i></span>
                                        <input class="form-control" id="icon<?= (int) $category['id'] ?>" name="icon" value="<?= e($category['icon']) ?>" maxlength="60" data-icon-input>
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="color<?= (int) $category['id'] ?>">Colour</label>
                                    <input class="form-control form-control-color w-100" type="color" id="color<?= (int) $category['id'] ?>" name="color" value="<?= e($category['color']) ?>">
                                </div>
                            </div>
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="active<?= (int) $category['id'] ?>" name="is_active" value="1" <?= (int) $category['is_active'] ? 'checked' : '' ?>>
                                <label class="form-check-label fs-7" for="active<?= (int) $category['id'] ?>">Active category</label>
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
</div>

<div class="modal fade" id="createCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= e(url('admin/categories.php')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Create issue category</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="newCategoryName">Category name *</label>
                        <input class="form-control" id="newCategoryName" name="name" maxlength="120" placeholder="e.g. Park Maintenance" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="newCategoryDescription">Description</label>
                        <textarea class="form-control" id="newCategoryDescription" name="description" rows="2" maxlength="255" placeholder="What kinds of issues belong in this category?"></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-7">
                            <label class="form-label" for="newCategoryIcon">Font Awesome icon</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-solid fa-circle-exclamation" data-icon-preview="newCategoryIcon"></i></span>
                                <input class="form-control" id="newCategoryIcon" name="icon" value="fa-circle-exclamation" maxlength="60" data-icon-input>
                            </div>
                            <div class="form-text">Example: fa-tree, fa-bus, fa-school</div>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="newCategoryColor">Colour</label>
                            <input class="form-control form-control-color w-100" type="color" id="newCategoryColor" name="color" value="#0f9b8e">
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="newCategoryActive" name="is_active" value="1" checked>
                        <label class="form-check-label fs-7" for="newCategoryActive">Active category</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-soft btn-sm" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary btn-sm" type="submit"><i class="fa-solid fa-plus me-1"></i>Create category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-icon-input]').forEach(function (input) {
        var preview = document.querySelector('[data-icon-preview="' + input.id + '"]');
        if (!preview) return;
        input.addEventListener('input', function () {
            preview.className = 'fa-solid ' + input.value.trim();
        });
    });
});
</script>

<?php require __DIR__ . '/../includes/dashboard_footer.php'; ?>
