<?php
$form = $form ?? [
    'create_missing_categories' => 1,
    'update_existing_products' => 1,
];
$errors = $errors ?? [];
$isModalRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
?>
<div class="surface-card card-panel">
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-4">
            <strong>Review the import settings and file before trying again.</strong>
        </div>
    <?php endif; ?>

    <form action="<?= e(url('products/import')) ?>" method="post" enctype="multipart/form-data" class="d-grid gap-4" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="products-catalog"]'>
        <?= csrf_field() ?>
        <?php if (!empty($submissionKey)): ?>
            <input type="hidden" name="submission_key" value="<?= e((string) $submissionKey) ?>">
        <?php endif; ?>

        <section class="d-grid gap-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <p class="eyebrow mb-1">Catalog Import</p>
                    <h3 class="mb-1"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Import Products from XLSX</h3>
                    <div class="text-muted">Upload one Excel sheet and the importer will translate category names or category paths into the correct `category_id` values automatically.</div>
                </div>
                <a href="<?= e(url('products/import-template')) ?>" class="btn btn-outline-secondary" download data-download="true" data-no-loader="true">
                    <i class="bi bi-download me-1"></i>Download Template
                </a>
            </div>

            <div class="alert alert-info rounded-4 mb-0">
                Use `category_path` values like `Beverages / Soft Drinks`. If you enable missing-category creation, the importer will create each missing level and then attach the product to the final category.
            </div>
        </section>

        <section class="form-grid">
            <div style="grid-column: 1 / -1;">
                <label class="form-label" for="product-import-file">XLSX workbook</label>
                <input id="product-import-file" type="file" name="import_file" class="form-control" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                <div class="form-text">The first worksheet should use the template headers. Product images and variants stay outside this import.</div>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="create_missing_categories" name="create_missing_categories" value="1" <?= (int) ($form['create_missing_categories'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="create_missing_categories">Create missing categories automatically</label>
                <div class="form-text">Recommended when the spreadsheet introduces new category paths.</div>
            </div>

            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="update_existing_products" name="update_existing_products" value="1" <?= (int) ($form['update_existing_products'] ?? 1) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="update_existing_products">Update and restock existing products</label>
                <div class="form-text">Matches the existing catalog by SKU, barcode, exact name, or detected model code.</div>
            </div>
        </section>

        <section class="row g-3">
            <div class="col-lg-6">
                <div class="record-card h-100">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4>Template columns</h4>
                            <div class="small text-muted">Required fields stay minimal.</div>
                        </div>
                    </div>
                    <div class="small text-muted d-grid gap-2">
                        <div><strong>`name`</strong> and <strong>`price`</strong> are required. <strong>`cost_price`</strong> is optional and defaults to `0.00` when left blank.</div>
                        <div><strong>`category_path`</strong> can be a root name or a full path like `Hardware / Paint`.</div>
                        <div><strong>`supplier_name`</strong> and <strong>`tax_name`</strong> are optional but must match existing records if used.</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="record-card h-100">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4>Import behavior</h4>
                            <div class="small text-muted">The importer uses the same rules as manual product creation.</div>
                        </div>
                    </div>
                    <div class="small text-muted d-grid gap-2">
                        <div>Blank categories import as uncategorized products.</div>
                        <div>Blank SKU or barcode values are generated automatically using the existing catalog logic.</div>
                        <div>Opening stock creates inventory for new rows and adds stock to matched products when updates are enabled.</div>
                    </div>
                </div>
            </div>
        </section>

        <div class="d-flex justify-content-end gap-2">
            <?php if ($isModalRequest): ?>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <?php else: ?>
                <a href="<?= e(url('products')) ?>" class="btn btn-outline-secondary">Cancel</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-cloud-upload me-1"></i>Import Products
            </button>
        </div>
    </form>
</div>
