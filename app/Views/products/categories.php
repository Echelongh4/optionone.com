<?php
$totalCategories = (int) ($categorySummary['total_categories'] ?? 0);
$topLevelCategories = (int) ($categorySummary['top_level_categories'] ?? 0);
$subcategories = (int) ($categorySummary['subcategories'] ?? 0);
$assignedProducts = (int) ($categorySummary['assigned_products'] ?? 0);
$topLevelRows = array_values(array_filter(
    $productCategories,
    static fn (array $category): bool => empty($category['parent_id'])
));
$subcategoryRows = array_values(array_filter(
    $productCategories,
    static fn (array $category): bool => !empty($category['parent_id'])
));
$childrenByParent = [];
$showLegacyCreateForms = $categoryCreateErrors !== [];

foreach ($subcategoryRows as $subcategory) {
    $parentId = (int) ($subcategory['parent_id'] ?? 0);
    if ($parentId <= 0) {
        continue;
    }

    $childrenByParent[$parentId][] = $subcategory;
}
?>
<div data-refresh-region="product-category-manager">
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Categories</span><span>Structure</span></div>
        <h3><?= e((string) $totalCategories) ?></h3>
        <div class="text-muted">All active catalog categories and subcategories.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Top Level</span><span>Primary</span></div>
        <h3><?= e((string) $topLevelCategories) ?></h3>
        <div class="text-muted">Main category groups at the root of the catalog.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Subcategories</span><span>Nested</span></div>
        <h3><?= e((string) $subcategories) ?></h3>
        <div class="text-muted">Child categories assigned under a parent category.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Assigned Products</span><span>Usage</span></div>
        <h3><?= e((string) $assignedProducts) ?></h3>
        <div class="text-muted">Product records currently linked to active categories.</div>
    </section>
</div>

<section class="surface-card card-panel workspace-panel mb-4 span-two">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Category Workspace</p>
            <h3><i class="bi bi-diagram-3 me-2"></i>Product Categories</h3>
            <div class="text-muted">Create, reorganize, and retire catalog groups from one place.</div>
        </div>
        <div class="workspace-panel__actions">
            <a href="<?= e(url('products')) ?>" class="btn btn-outline-secondary"><i class="bi bi-box-seam me-1"></i>Back to Products</a>
            <button type="button" class="btn btn-primary" data-open-category-modal><i class="bi bi-plus-lg me-1"></i>New Category</button>
        </div>
    </div>

    <div class="content-grid">
        <section class="surface-card card-panel table-shell span-two">
            <div class="toolbar-card">
                <div>
                    <p class="eyebrow mb-1">Categories</p>
                    <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Category List</h3>
                </div>
                <div class="stat-pills">
                    <div class="stat-pill"><div class="small text-muted">Total</div><strong><?= e((string) $totalCategories) ?></strong></div>
                    <div class="stat-pill"><div class="small text-muted">Top level</div><strong><?= e((string) $topLevelCategories) ?></strong></div>
                    <div class="stat-pill"><div class="small text-muted">Subcategories</div><strong><?= e((string) $subcategories) ?></strong></div>
                    <div class="stat-pill"><div class="small text-muted">Assigned</div><strong><?= e((string) $assignedProducts) ?></strong></div>
                </div>
            </div>

            <div class="toolbar-search d-flex gap-2 align-items-center">
                <div style="flex:1">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input id="category-search" type="text" class="form-control" placeholder="Search categories by name or description">
                        <button id="clear-search" class="btn btn-outline-secondary" type="button">Clear</button>
                    </div>
                </div>
                <div>
                    <select id="parent-filter" class="form-select">
                        <option value="">All parents</option>
                        <?php foreach ($parentCategoryOptions as $parentOption): ?>
                            <option value="<?= e((string) $parentOption['id']) ?>"><?= e($parentOption['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <button id="bulk-delete" class="btn btn-outline-danger" type="button" disabled>Delete Selected</button>
                    <button id="open-new-category-modal" class="btn btn-primary" type="button"><i class="bi bi-plus-lg me-1"></i>New Category</button>
                </div>
            </div>

            <div class="table-responsive">
                <table id="categories-table" class="table align-middle data-table" data-csrf="<?= e(csrf_token()) ?>" data-delete-url="<?= e(url('products/categories/delete')) ?>">
                    <thead>
                        <tr>
                            <th><input id="select-all" type="checkbox"></th>
                            <th>Name</th>
                            <th>Parent</th>
                            <th>Products</th>
                            <th>Subcategories</th>
                            <th>Description</th>
                            <th>Created</th>
                                <th class="text-end actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productCategories as $category): ?>
                            <tr data-category-id="<?= e((string) $category['id']) ?>" data-parent-id="<?= e((string) ($category['parent_id'] ?? '')) ?>">
                                <td><input class="row-select" type="checkbox" value="<?= e((string) $category['id']) ?>"></td>
                                <td>
                                    <div class="fw-semibold"><?= e($category['name']) ?></div>
                                    <div class="small text-muted">Slug: <?= e($category['slug'] ?? '-') ?></div>
                                </td>
                                <td><?= e($category['parent_name'] ?? 'Top level') ?></td>
                                <td><?= e((string) ($category['product_count'] ?? 0)) ?></td>
                                <td><?= e((string) ($category['child_count'] ?? 0)) ?></td>
                                <td class="text-truncate" style="max-width:300px"><?= e($category['description'] ?? '') ?></td>
                                <td><?= e((string) ($category['created_at'] ?? '')) ?></td>
                                <td class="text-end actions">
                                    <div class="d-none d-lg-flex justify-content-end gap-2">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('products')) ?>?category_id=<?= e((string) $category['id']) ?>"><i class="bi bi-box-seam me-1"></i>View Products</a>
                                        <button class="btn btn-sm btn-outline-secondary edit-row" type="button" data-edit="true"><i class="bi bi-pencil-square me-1"></i>Edit</button>
                                        <form action="<?= e(url('products/categories/delete')) ?>" method="post" class="m-0 d-inline" data-ajax="true" data-refresh-target='[data-refresh-region="product-category-manager"]'>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" <?= ((int) ($category['product_count'] ?? 0) > 0 || (int) ($category['child_count'] ?? 0) > 0) ? 'disabled' : '' ?> data-confirm-action data-confirm-title="Delete this category?" data-confirm-text="This category will be removed permanently." data-confirm-button="Delete Category"><i class="bi bi-trash me-1"></i>Delete</button>
                                        </form>
                                    </div>
                                    <div class="d-lg-none dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="<?= e(url('products')) ?>?category_id=<?= e((string) $category['id']) ?>">View Products</a></li>
                                            <li><button class="dropdown-item edit-row" type="button">Edit</button></li>
                                            <li>
                                                <form action="<?= e(url('products/categories/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="product-category-manager"]'>
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                                                    <button type="submit" class="dropdown-item text-danger" <?= ((int) ($category['product_count'] ?? 0) > 0 || (int) ($category['child_count'] ?? 0) > 0) ? 'disabled' : '' ?> data-confirm-action data-confirm-title="Delete this category?" data-confirm-text="This category will be removed permanently." data-confirm-button="Delete Category">Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <!-- edit-row-form moved to hidden container to keep table structure consistent for DataTables -->
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</section>
</div>

<!-- Legacy create forms (kept for server-side error rendering) hidden to keep page concise -->
<div class="<?= $showLegacyCreateForms ? '' : 'd-none' ?>" id="legacy-create-forms">
<section class="surface-card card-panel workspace-panel" id="new-category">
    <?php if ($categoryCreateErrors !== []): ?>
        <div class="alert alert-danger rounded-4 mb-3">
            <strong>Please fix the category form errors and try again.</strong>
        </div>
    <?php endif; ?>
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Create Categories</p>
            <h3><i class="bi bi-plus-square me-2"></i>New Top-Level Category or Subcategory</h3>
        </div>
    </div>
    <div class="content-grid">
        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Top-Level Category</p>
                    <h4>Add a root catalog group</h4>
                </div>
            </div>
            <form action="<?= e(url('products/categories/store')) ?>" method="post" class="stack-grid" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="product-category-manager"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="parent_id" value="">
                <div class="field-stack">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= empty($categoryForm['parent_id']) ? e($categoryForm['name']) : '' ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-control"><?= empty($categoryForm['parent_id']) ? e($categoryForm['description']) : '' ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Create Top-Level Category</button>
            </form>
        </section>

        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Subcategory</p>
                    <h4>Add a child category</h4>
                </div>
            </div>
            <form action="<?= e(url('products/categories/store')) ?>" method="post" class="stack-grid" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="product-category-manager"]'>
                <?= csrf_field() ?>
                <div class="field-stack">
                    <label class="form-label">Parent category</label>
                    <select name="parent_id" class="form-select" required>
                        <option value="">Select parent</option>
                        <?php foreach ($parentCategoryOptions as $parentOption): ?>
                            <option value="<?= e((string) $parentOption['id']) ?>" <?= (string) ($categoryForm['parent_id'] ?? '') === (string) $parentOption['id'] ? 'selected' : '' ?>>
                                <?= e($parentOption['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= !empty($categoryForm['parent_id']) ? e($categoryForm['name']) : '' ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="3" class="form-control"><?= !empty($categoryForm['parent_id']) ? e($categoryForm['description']) : '' ?></textarea>
                </div>
                <button type="submit" class="btn btn-outline-primary">Create Subcategory</button>
            </form>
        </section>
    </div>
</section>
<!-- Hidden edit forms moved outside the table to avoid DataTables column mismatch -->
<div id="category-edit-forms" class="d-none">
    <?php foreach ($productCategories as $category): ?>
        <form id="edit-form-<?= e((string) $category['id']) ?>" data-category-id="<?= e((string) $category['id']) ?>" action="<?= e(url('products/categories/update')) ?>" method="post" class="row-edit-form" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="product-category-manager"]'>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($category['name']) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Parent</label>
                    <select name="parent_id" class="form-select">
                        <option value="">Top level</option>
                        <?php foreach ($parentCategoryOptions as $parentOption): ?>
                            <?php if ((int) $parentOption['id'] === (int) $category['id']) { continue; } ?>
                            <option value="<?= e((string) $parentOption['id']) ?>" <?= (string) ($category['parent_id'] ?? '') === (string) $parentOption['id'] ? 'selected' : '' ?>><?= e($parentOption['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack field-span-full">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="2" class="form-control"><?= e($category['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="workspace-panel__actions justify-content-end gap-2 mt-3">
                <button type="submit" class="btn btn-outline-primary">Save</button>
                <button type="button" class="btn btn-outline-secondary cancel-edit">Cancel</button>
            </div>
        </form>
    <?php endforeach; ?>
</div>
