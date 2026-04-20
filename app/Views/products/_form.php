<?php
$variantSeed = $product['variants'] ?? [['variant_name' => '', 'variant_value' => '', 'sku' => '', 'barcode' => '', 'price_adjustment' => 0, 'stock_quantity' => 0]];
$isModalRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
?>
<div class="surface-card card-panel" data-product-form-root x-data='productForm(<?= json_encode($variantSeed, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' x-init="init()">
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-4">
            <strong>Please fix the highlighted fields.</strong>
        </div>
    <?php endif; ?>

    <form action="<?= e($action) ?>" method="post" enctype="multipart/form-data" class="d-grid gap-4" data-loading-form data-ajax="true">
        <?= csrf_field() ?>
        <?php if (!empty($submissionKey)): ?>
            <input type="hidden" name="submission_key" value="<?= e((string) $submissionKey) ?>">
        <?php endif; ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
        <?php endif; ?>

        <section>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Catalog Item</p>
                    <h3 class="mb-0"><?= e($isEdit ? 'Edit Product' : 'Create Product') ?></h3>
                </div>
                <span class="badge-soft">Code128-ready barcode</span>
            </div>
            <?php if (!$isEdit): ?>
                <div class="alert alert-info rounded-4">
                    Matching an existing product by SKU, barcode, exact name, or detected model code will add the submitted opening stock to that item instead of creating a duplicate.
                </div>
            <?php endif; ?>
            <div class="form-grid">
                <div>
                    <label class="form-label">Product name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($product['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label class="form-label">Brand</label>
                    <input type="text" name="brand" class="form-control" value="<?= e($product['brand'] ?? '') ?>" placeholder="Brand or manufacturer">
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>" <?= (string) ($product['category_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>>
                                <?= e($category['parent_name'] ? $category['parent_name'] . ' / ' . $category['name'] : $category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">Select supplier</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= e((string) $supplier['id']) ?>" <?= (string) ($product['supplier_id'] ?? '') === (string) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Tax profile</label>
                    <select name="tax_id" class="form-select">
                        <option value="">No tax</option>
                        <?php foreach ($taxes as $tax): ?>
                            <option value="<?= e((string) $tax['id']) ?>" <?= (string) ($product['tax_id'] ?? '') === (string) $tax['id'] ? 'selected' : '' ?>><?= e($tax['name']) ?> (<?= e((string) $tax['rate']) ?>%)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Selling price</label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= e((string) ($product['price'] ?? 0)) ?>" required>
                </div>
                <div>
                    <label class="form-label">Cost price</label>
                    <input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="<?= e((string) ($product['cost_price'] ?? 0)) ?>" required>
                </div>
                <div>
                    <label class="form-label">Unit</label>
                    <select name="unit" class="form-select" required>
                        <?php foreach (['pcs', 'kg', 'litre', 'box'] as $unit): ?>
                            <option value="<?= e($unit) ?>" <?= ($product['unit'] ?? 'pcs') === $unit ? 'selected' : '' ?>><?= e(strtoupper($unit)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Low stock alert threshold</label>
                    <input type="number" step="0.01" min="0" name="low_stock_threshold" class="form-control" value="<?= e((string) ($product['low_stock_threshold'] ?? 5)) ?>">
                    <div class="form-text">This only controls the warning level. It does not add stock.</div>
                </div>
                <?php if (!$isEdit): ?>
                    <div>
                        <label class="form-label">Opening stock</label>
                        <input type="number" step="0.01" min="0" name="opening_stock" class="form-control" value="<?= e((string) ($product['opening_stock'] ?? 0)) ?>">
                        <div class="form-text">This is the actual quantity added to inventory when you save the product.</div>
                    </div>
                <?php endif; ?>
                <div>
                    <label class="form-label">Inventory valuation</label>
                    <select name="inventory_method" class="form-select">
                        <option value="FIFO" <?= ($product['inventory_method'] ?? 'FIFO') === 'FIFO' ? 'selected' : '' ?>>FIFO</option>
                        <option value="LIFO" <?= ($product['inventory_method'] ?? 'FIFO') === 'LIFO' ? 'selected' : '' ?>>LIFO</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">SKU / Model code</label>
                    <div class="input-group">
                        <input type="text" name="sku" class="form-control" value="<?= e($product['sku'] ?? '') ?>">
                        <button type="button" class="btn btn-outline-secondary" @click="generate('sku')">Generate</button>
                    </div>
                    <div class="form-text">For tiles, use the model code such as FGP33139G. If left blank, the system will use a detected model code from the product name before falling back to a generated SKU.</div>
                </div>
                <div>
                    <label class="form-label">Barcode</label>
                    <div class="input-group">
                        <input type="text" name="barcode" class="form-control" value="<?= e($product['barcode'] ?? '') ?>">
                        <button type="button" class="btn btn-outline-secondary" @click="generate('barcode')">Generate</button>
                    </div>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($product['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($product['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="track_stock" id="track_stock" value="1" <?= (int) ($product['track_stock'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="track_stock">Track stock</label>
                    </div>
                </div>
                <div class="form-grid" style="grid-column: 1 / -1;">
                    <div>
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?= e($product['description'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-control" accept="image/png,image/jpeg,image/webp">
                        <?php if (!empty($product['image_path'])): ?>
                            <img src="<?= e(url($product['image_path'])) ?>" alt="Product" class="img-fluid rounded-4 mt-3" style="max-height: 180px; object-fit: cover;">
                        <?php endif; ?>
                        <?php if (!empty($product['barcode'])): ?>
                            <div class="mt-3"><svg data-barcode="<?= e($product['barcode']) ?>"></svg></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <p class="eyebrow mb-1">Variants</p>
                    <h3 class="mb-0">Size, color, and more</h3>
                </div>
                <button type="button" class="btn btn-outline-secondary" @click="addVariant()">Add Variant</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle" data-product-variant-table>
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Value</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Price Adj.</th>
                        <th>Stock</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template x-for="(variant, index) in variants" :key="index">
                        <tr>
                            <td><input class="form-control" :name="`variant_name[${index}]`" x-model="variant.variant_name"></td>
                            <td><input class="form-control" :name="`variant_value[${index}]`" x-model="variant.variant_value"></td>
                            <td><input class="form-control" :name="`variant_sku[${index}]`" x-model="variant.sku"></td>
                            <td><input class="form-control" :name="`variant_barcode[${index}]`" x-model="variant.barcode"></td>
                            <td><input class="form-control" type="number" step="0.01" :name="`variant_price_adjustment[${index}]`" x-model="variant.price_adjustment"></td>
                            <td><input class="form-control" type="number" step="0.01" :name="`variant_stock_quantity[${index}]`" x-model="variant.stock_quantity"></td>
                            <td><button type="button" class="btn btn-sm btn-outline-danger" @click="removeVariant(index)">Remove</button></td>
                        </tr>
                    </template>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="d-flex gap-2 justify-content-end">
            <?php if ($isModalRequest): ?>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <?php else: ?>
                <a href="<?= e(url('products')) ?>" class="btn btn-outline-secondary">Cancel</a>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><?= e($buttonLabel) ?></button>
        </div>
    </form>
</div>
