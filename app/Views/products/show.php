<?php
$variants = $product['variants'] ?? [];
$movements = $product['movements'] ?? [];
$stockQuantity = (float) ($product['stock_quantity'] ?? 0);
$threshold = (float) ($product['low_stock_threshold'] ?? 0);
$stockState = $stockQuantity <= $threshold ? 'Low' : 'Healthy';
$isModalRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
?>

<div data-refresh-region="product-detail">
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Retail Price</span><span>Sell</span></div>
        <h3><?= e(format_currency($product['price'])) ?></h3>
        <div class="text-muted">Current selling price for this item.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Cost Price</span><span>Buy</span></div>
        <h3><?= e(format_currency($product['cost_price'])) ?></h3>
        <div class="text-muted">Base procurement cost on the product record.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>On Hand</span><span>Stock</span></div>
        <h3><?= e((string) $stockQuantity) ?></h3>
        <div class="text-muted"><?= e($stockState) ?> against threshold <?= e((string) $threshold) ?>.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Variants</span><span>Catalog</span></div>
        <h3><?= e((string) count($variants)) ?></h3>
        <div class="text-muted">Configured option rows for this product.</div>
    </section>
</div>

<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Catalog Record</p>
            <h3><i class="bi bi-box-seam me-2"></i><?= e($product['name']) ?></h3>
            <div class="inline-note">
                Brand <?= e($product['brand'] !== '' ? $product['brand'] : 'Unbranded') ?>
                | SKU / Model <?= e($product['sku']) ?>
                | Barcode <?= e($product['barcode']) ?>
            </div>
        </div>
        <div class="workspace-panel__actions">
            <?php if ($isModalRequest): ?>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            <?php else: ?>
                <a href="<?= e(url('products')) ?>" class="btn btn-outline-secondary">Back</a>
            <?php endif; ?>
            <a href="<?= e(url('products/edit?id=' . $product['id'])) ?>" class="btn btn-primary" data-modal data-modal-size="xl" data-title="Edit Product" data-refresh-target='[data-refresh-region="product-detail"]'>Edit Product</a>
            <a href="<?= e(url('inventory?product_id=' . (int) $product['id'] . '&prefill_direction=increase&prefill_unit_cost=' . urlencode((string) $product['cost_price']) . '&prefill_reason=' . urlencode('Received from supplier - PO #') . '&prefill_reason_code=' . urlencode('Received from supplier - PO'))) ?>" class="btn btn-success" data-modal data-title="Restock Product" data-refresh-target='[data-refresh-region="product-detail"]'><i class="bi bi-plus-circle me-1"></i>Quick Restock</a>
            <form action="<?= e(url('products/delete')) ?>" method="post" class="d-inline-block m-0"<?= $isModalRequest ? ' data-ajax="true" data-loading-form' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                <button type="submit" class="btn btn-outline-danger" data-confirm-delete data-confirm-title="Archive this product?" data-confirm-text="The product will be soft archived and hidden from active operations." data-confirm-button="Archive Product"><i class="bi bi-archive me-1"></i>Archive</button>
            </form>
        </div>
    </div>

    <div class="form-grid">
        <div class="record-card">
            <div class="record-card__header">
                <div class="workspace-panel__intro">
                    <h4>Product Details</h4>
                </div>
            </div>
            <div class="stack-grid">
                <div><div class="small text-muted">Brand</div><div class="fw-semibold"><?= e($product['brand'] !== '' ? $product['brand'] : 'Unbranded') ?></div></div>
                <div><div class="small text-muted">Category</div><div class="fw-semibold"><?= e($product['parent_category_name'] ? $product['parent_category_name'] . ' / ' . $product['category_name'] : ($product['category_name'] ?? 'Uncategorized')) ?></div></div>
                <div><div class="small text-muted">Supplier</div><div class="fw-semibold"><?= e($product['supplier_name'] ?? 'Unassigned') ?></div></div>
                <div><div class="small text-muted">Tax</div><div class="fw-semibold"><?= e($product['tax_name'] ? $product['tax_name'] . ' (' . number_format((float) $product['tax_rate'], 2) . '%)' : 'No tax') ?></div></div>
                <div><div class="small text-muted">Unit</div><div class="fw-semibold"><?= e($product['unit']) ?></div></div>
                <div><div class="small text-muted">Tracking</div><div class="fw-semibold"><?= e((int) ($product['track_stock'] ?? 0) === 1 ? 'Stock tracked' : 'Not tracked') ?></div></div>
                <div><div class="small text-muted">Status</div><div class="fw-semibold text-capitalize"><?= e((string) $product['status']) ?></div></div>
            </div>
            <?php if (!empty($product['description'])): ?>
                <div class="mt-3">
                    <div class="small text-muted">Description</div>
                    <div class="fw-semibold"><?= e($product['description']) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <div class="record-card">
            <div class="record-card__header">
                <div class="workspace-panel__intro">
                    <h4>Media & Supply</h4>
                </div>
            </div>
            <?php if (!empty($product['image_path'])): ?>
                <div class="media-preview mb-3">
                    <img src="<?= e(url((string) $product['image_path'])) ?>" alt="<?= e($product['name']) ?>" class="img-fluid">
                </div>
            <?php endif; ?>
            <div class="stack-grid">
                <div><div class="small text-muted">Supplier Contact</div><div class="fw-semibold"><?= e($product['supplier_contact_person'] ?? 'Not provided') ?></div></div>
                <div><div class="small text-muted">Supplier Email</div><div class="fw-semibold"><?= e($product['supplier_email'] ?? 'Not provided') ?></div></div>
                <div><div class="small text-muted">Supplier Phone</div><div class="fw-semibold"><?= e($product['supplier_phone'] ?? 'Not provided') ?></div></div>
            </div>
        </div>
    </div>
</section>

<div class="content-grid">
    <section class="surface-card card-panel table-shell">
        <div class="table-shell__header">
            <div>
                <p class="eyebrow mb-1">Variants</p>
                <h3 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Product options</h3>
            </div>
        </div>
        <?php if ($variants === []): ?>
            <div class="empty-state">No variants are configured for this product.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle data-table">
                    <thead>
                    <tr>
                        <th>Variant</th>
                        <th>SKU</th>
                        <th>Barcode</th>
                        <th>Price Adj.</th>
                        <th>Stock</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($variants as $variant): ?>
                        <tr>
                            <td><?= e($variant['variant_name'] . ': ' . $variant['variant_value']) ?></td>
                            <td><?= e($variant['sku'] ?: 'None') ?></td>
                            <td><?= e($variant['barcode'] ?: 'None') ?></td>
                            <td><?= e(format_currency((float) ($variant['price_adjustment'] ?? 0))) ?></td>
                            <td><?= e((string) ($variant['stock_quantity'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="surface-card card-panel table-shell">
        <div class="table-shell__header">
            <div>
                <p class="eyebrow mb-1">Inventory Ledger</p>
                <h3 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Recent stock movements</h3>
            </div>
        </div>
        <?php if ($movements === []): ?>
            <div class="empty-state">No stock movement has been recorded for this product yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle data-table">
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Balance</th>
                        <th>Reason</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movements as $movement): ?>
                        <?php $change = (float) ($movement['quantity_change'] ?? 0); ?>
                        <tr>
                            <td><?= e((string) $movement['created_at']) ?></td>
                            <td><span class="badge-soft text-capitalize"><?= e(str_replace('_', ' ', (string) $movement['movement_type'])) ?></span></td>
                            <td class="<?= $change < 0 ? 'text-danger' : 'text-success' ?>"><?= e(($change > 0 ? '+' : '') . (string) $change) ?></td>
                            <td><?= e((string) ($movement['balance_after'] ?? 0)) ?></td>
                            <td><?= e($movement['reason'] ?: 'No note') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</div>
