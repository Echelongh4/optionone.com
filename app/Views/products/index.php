<?php
$totalProducts = count($products);
$trackedProducts = count(array_filter($products, static fn (array $product): bool => (int) ($product['track_stock'] ?? 0) === 1));
$lowStockProducts = count(array_filter($products, static fn (array $product): bool => (float) ($product['stock_quantity'] ?? 0) <= (float) ($product['low_stock_threshold'] ?? 0)));
$taxableProducts = count(array_filter($products, static fn (array $product): bool => !empty($product['tax_name'])));
$totalCategories = (int) ($categorySummary['total_categories'] ?? 0);
$selectedCategoryId = (string) ($selectedCategoryId ?? '');
$selectedBrand = (string) ($selectedBrand ?? '');
$productBrands = $productBrands ?? [];
$importReport = is_array($importReport ?? null) ? $importReport : [];
$importIssues = array_slice((array) ($importReport['issues'] ?? []), 0, 8);
$importTotalImported = (int) ($importReport['created_count'] ?? 0) + (int) ($importReport['updated_count'] ?? 0);
?>
<section class="surface-card card-panel table-shell" data-refresh-region="products-catalog">
    <div class="toolbar-card">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-tags me-1"></i>Products</p>
            <h3 class="mb-0"><i class="bi bi-box-seam me-2"></i>Catalog</h3>
        </div>
        <div class="stat-pills">
            <div class="stat-pill">
                <div class="small text-muted">Products</div>
                <strong><?= e((string) $totalProducts) ?></strong>
            </div>
            <div class="stat-pill">
                <div class="small text-muted">Tracked</div>
                <strong><?= e((string) $trackedProducts) ?></strong>
            </div>
            <div class="stat-pill">
                <div class="small text-muted">Taxed</div>
                <strong><?= e((string) $taxableProducts) ?></strong>
            </div>
            <div class="stat-pill">
                <div class="small text-muted">Categories</div>
                <strong><?= e((string) $totalCategories) ?></strong>
            </div>
        </div>
    </div>

    <div class="toolbar-search">
        <form action="<?= e(url('products')) ?>" method="get" class="d-flex flex-grow-1 gap-2 flex-wrap align-items-start">
            <div class="input-group flex-grow-1">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Search by product name, brand, SKU, or barcode" value="<?= e($search) ?>">
            </div>
            <select name="category_id" class="form-select" style="max-width: 280px;">
                <option value="">All categories</option>
                <?php foreach ($productCategories as $category): ?>
                    <option value="<?= e((string) $category['id']) ?>" <?= $selectedCategoryId === (string) $category['id'] ? 'selected' : '' ?>>
                        <?= e($category['parent_name'] ? $category['parent_name'] . ' / ' . $category['name'] : $category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="brand" class="form-select" style="max-width: 240px;">
                <option value="">All brands</option>
                <?php foreach ($productBrands as $brandOption): ?>
                    <option value="<?= e((string) $brandOption['brand_key']) ?>" <?= $selectedBrand === (string) $brandOption['brand_key'] ? 'selected' : '' ?>>
                        <?= e((string) $brandOption['brand_label']) ?> (<?= e((string) $brandOption['product_count']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-secondary">Filter</button>
            <?php if ($search !== '' || $selectedCategoryId !== '' || $selectedBrand !== ''): ?>
                <a href="<?= e(url('products')) ?>" class="btn btn-outline-secondary">Reset</a>
            <?php endif; ?>
        </form>
        <a href="<?= e(url('products/categories')) ?>" class="btn btn-outline-secondary"><i class="bi bi-diagram-3 me-1"></i>Categories</a>
        <a href="<?= e(url('products/import')) ?>" class="btn btn-outline-secondary" data-modal data-modal-size="lg" data-title="Import Products" data-refresh-target='[data-refresh-region="products-catalog"]'><i class="bi bi-file-earmark-arrow-up me-1"></i>Import XLSX</a>
        <button id="bulk-archive" type="button" class="btn btn-outline-danger" disabled><i class="bi bi-archive me-1"></i>Archive Selected</button>
        <a href="<?= e(url('products/create')) ?>" class="btn btn-primary" data-modal data-modal-size="xl" data-title="Add Product" data-refresh-target='[data-refresh-region="products-catalog"]'><i class="bi bi-plus-lg me-1"></i>Add Product</a>
    </div>

    <?php if ($importReport !== []): ?>
        <div class="alert <?= $importTotalImported > 0 ? 'alert-info' : 'alert-warning' ?> rounded-4 d-grid gap-3 mt-3 mb-0">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                    <strong>Latest import report</strong>
                    <div class="small text-muted">
                        <?= e((string) ($importReport['file_name'] ?? 'Workbook')) ?> processed on
                        <?= e(date('M d, Y H:i', strtotime((string) ($importReport['completed_at'] ?? 'now')))) ?>.
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <span class="badge-soft"><?= e((string) ($importReport['processed_rows'] ?? 0)) ?> rows</span>
                    <span class="badge-soft"><?= e((string) ($importReport['created_count'] ?? 0)) ?> created</span>
                    <span class="badge-soft"><?= e((string) ($importReport['updated_count'] ?? 0)) ?> updated</span>
                    <span class="badge-soft"><?= e((string) ($importReport['failed_count'] ?? 0)) ?> issues</span>
                    <?php if ((int) ($importReport['created_categories_count'] ?? 0) > 0): ?>
                        <span class="badge-soft"><?= e((string) ($importReport['created_categories_count'] ?? 0)) ?> categories auto-created</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($importIssues !== []): ?>
                <div class="d-grid gap-2">
                    <?php foreach ($importIssues as $issue): ?>
                        <div class="small">
                            <strong>Row <?= e((string) ($issue['row'] ?? '')) ?></strong>
                            <?php if (!empty($issue['product'])): ?>
                                <span class="text-muted">· <?= e((string) $issue['product']) ?></span>
                            <?php endif; ?>
                            <div class="text-muted"><?= e((string) ($issue['message'] ?? 'Import issue')) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ((int) ($importReport['failed_count'] ?? 0) > count($importIssues)): ?>
                        <div class="small text-muted">More rows need review. Fix the spreadsheet and import again.</div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="table-responsive">
        <table id="products-table" class="table align-middle data-table" data-csrf="<?= e(csrf_token()) ?>" data-bulk-archive-url="<?= e(url('products/bulk-archive')) ?>">
            <thead>
                <tr>
                    <th style="width:32px"><input id="select-all" type="checkbox" aria-label="Select all"></th>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Tax</th>
                    <th>Barcode</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <?php
                    $stockQuantity = (float) ($product['stock_quantity'] ?? 0);
                    $threshold = (float) ($product['low_stock_threshold'] ?? 0);
                    $stockClass = $stockQuantity <= $threshold ? 'status-pill status-pill--warning' : 'status-pill status-pill--success';
                    ?>
                    <tr data-product-id="<?= e((string) $product['id']) ?>">
                        <td><input class="row-select" type="checkbox" value="<?= e((string) $product['id']) ?>" aria-label="Select product <?= e($product['name']) ?>"></td>
                        <td>
                            <div class="product-meta-stack">
                                <div class="fw-semibold"><?= e($product['name']) ?></div>
                                <div class="small text-muted">SKU / Model <?= e($product['sku']) ?><?php if (!empty($product['unit'])): ?> | <?= e($product['unit']) ?><?php endif; ?></div>
                            </div>
                        </td>
                        <td><?= e($product['brand'] !== '' ? $product['brand'] : 'Unbranded') ?></td>
                        <td><?= e($product['parent_category_name'] ? $product['parent_category_name'] . ' / ' . $product['category_name'] : ($product['category_name'] ?? 'Uncategorized')) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e(format_currency($product['price'])) ?></div>
                            <div class="small text-muted">Cost <?= e(format_currency($product['cost_price'])) ?></div>
                        </td>
                        <td>
                            <span class="<?= e($stockClass) ?>"><?= e((string) $stockQuantity) ?> in stock</span>
                            <div class="small text-muted mt-2">Threshold <?= e((string) $threshold) ?></div>
                        </td>
                        <td><?= e($product['tax_name'] ?? 'No tax') ?></td>
                        <td><svg data-barcode="<?= e($product['barcode']) ?>"></svg></td>
                        <td class="text-end">
                            <div class="d-none d-lg-flex justify-content-end gap-2">
                                <a href="<?= e(url('products/show?id=' . $product['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-modal data-modal-size="xl" data-title="Product Details" data-refresh-target='[data-refresh-region="products-catalog"]'><i class="bi bi-eye me-1"></i>View</a>
                                <a href="<?= e(url('products/edit?id=' . $product['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-modal data-modal-size="xl" data-title="Edit Product" data-refresh-target='[data-refresh-region="products-catalog"]'><i class="bi bi-pencil-square me-1"></i>Edit</a>
                                <a href="<?= e(url('inventory')) ?>?open_adjustment=1&product_id=<?= e((string) $product['id']) ?>" class="btn btn-sm btn-outline-secondary" data-modal data-title="Restock Product" data-refresh-target='[data-refresh-region="products-catalog"]'><i class="bi bi-box-arrow-in-down me-1"></i>Restock</a>
                                <form action="<?= e(url('products/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="products-catalog"]'>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm-delete data-confirm-title="Archive this product?" data-confirm-text="The product will be soft archived and hidden from active operations." data-confirm-button="Archive Product"><i class="bi bi-archive me-1"></i>Archive</button>
                                </form>
                            </div>

                            <div class="d-lg-none dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= e(url('products/show?id=' . $product['id'])) ?>" data-modal data-modal-size="xl" data-title="Product Details" data-refresh-target='[data-refresh-region="products-catalog"]'><i class="bi bi-eye me-2"></i>View</a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('products/edit?id=' . $product['id'])) ?>" data-modal data-modal-size="xl" data-title="Edit Product" data-refresh-target='[data-refresh-region="products-catalog"]'><i class="bi bi-pencil-square me-2"></i>Edit</a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('inventory')) ?>?open_adjustment=1&product_id=<?= e((string) $product['id']) ?>" data-modal data-title="Restock Product" data-refresh-target='[data-refresh-region="products-catalog"]'><i class="bi bi-box-arrow-in-down me-2"></i>Restock</a></li>
                                    <li>
                                        <form action="<?= e(url('products/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="products-catalog"]'>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                                            <button type="submit" class="dropdown-item text-danger" data-confirm-delete data-confirm-title="Archive this product?" data-confirm-text="The product will be soft archived and hidden from active operations." data-confirm-button="Archive Product"><i class="bi bi-archive me-2"></i>Archive</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="surface-card card-panel workspace-panel mt-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Catalog Structure</p>
            <h3><i class="bi bi-diagram-3 me-2"></i>Product Categories</h3>
            <div class="text-muted">Categories now live in a dedicated page so create, edit, and subcategory management are easy to find.</div>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><?= e((string) ($categorySummary['total_categories'] ?? 0)) ?> total</span>
            <span class="badge-soft"><?= e((string) ($categorySummary['top_level_categories'] ?? 0)) ?> top level</span>
            <span class="badge-soft"><?= e((string) ($categorySummary['subcategories'] ?? 0)) ?> subcategories</span>
        </div>
    </div>

    <div class="form-grid">
        <article class="record-card">
            <div class="record-card__header">
                <div class="workspace-panel__intro">
                    <h4>Dedicated category manager</h4>
                    <div class="small text-muted">Use the separate category workspace for hierarchy changes, usage checks, and safe deletion.</div>
                </div>
                <div class="record-card__meta">
                    <a href="<?= e(url('products/categories')) ?>" class="btn btn-primary"><i class="bi bi-sliders me-1"></i>Open Category Manager</a>
                </div>
            </div>
        </article>
    </div>
</section>
