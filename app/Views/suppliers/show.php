<div data-refresh-region="supplier-detail">
<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Supplier Profile</p>
            <h3><i class="bi bi-truck me-2"></i><?= e($supplier['name']) ?></h3>
            <div class="inline-note"><?= e($supplier['tax_number'] ?: 'No tax number') ?></div>
        </div>
        <div class="workspace-panel__actions">
            <a href="<?= e(url('suppliers')) ?>" class="btn btn-outline-secondary">Back</a>
            <a href="<?= e(url('suppliers/edit?id=' . $supplier['id'])) ?>" class="btn btn-primary" data-modal data-title="Edit Supplier" data-refresh-target='[data-refresh-region="supplier-detail"]'>Edit Supplier</a>
        </div>
    </div>

    <div class="form-grid">
        <div class="record-card">
            <div class="record-card__header">
                <div class="workspace-panel__intro">
                    <h4>Contact</h4>
                </div>
            </div>
            <div class="stack-grid">
                <div><div class="small text-muted">Contact Person</div><div class="fw-semibold"><?= e($supplier['contact_person'] ?: 'Not provided') ?></div></div>
                <div><div class="small text-muted">Email</div><div class="fw-semibold"><?= e($supplier['email'] ?: 'Not provided') ?></div></div>
                <div><div class="small text-muted">Phone</div><div class="fw-semibold"><?= e($supplier['phone'] ?: 'Not provided') ?></div></div>
                <div><div class="small text-muted">Address</div><div class="fw-semibold"><?= e($supplier['address'] ?: 'Not provided') ?></div></div>
            </div>
        </div>
        <div class="record-card">
            <div class="record-card__header">
                <div class="workspace-panel__intro">
                    <h4>Performance</h4>
                </div>
            </div>
            <div class="stack-grid">
                <div><div class="small text-muted">Linked Products</div><div class="fw-semibold"><?= e((string) $supplier['total_products']) ?></div></div>
                <div><div class="small text-muted">Purchase Orders</div><div class="fw-semibold"><?= e((string) $supplier['total_purchase_orders']) ?></div></div>
                <div><div class="small text-muted">Purchase Value</div><div class="fw-semibold"><?= e(format_currency((float) ($supplier['total_purchase_value'] ?? 0))) ?></div></div>
                <div><div class="small text-muted">Last Purchase Order</div><div class="fw-semibold"><?= e($supplier['last_purchase_order_at'] ? date('Y-m-d H:i', strtotime((string) $supplier['last_purchase_order_at'])) : 'None') ?></div></div>
            </div>
        </div>
    </div>
</section>

<div class="content-grid">
    <section class="surface-card card-panel table-shell">
        <div class="table-shell__header">
            <div>
                <p class="eyebrow mb-1">Catalog</p>
                <h3 class="mb-0"><i class="bi bi-box-seam me-2"></i>Supplied products</h3>
            </div>
            <div class="table-shell__meta">
                <span class="badge-soft"><?= e((string) count($supplier['products'])) ?> rows</span>
            </div>
        </div>
        <?php if ($supplier['products'] === []): ?>
            <div class="empty-state">No active products are currently linked to this supplier.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle data-table">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Stock</th>
                        <th>Price</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($supplier['products'] as $product): ?>
                        <tr>
                            <td>
                                <div class="entity-cell">
                                    <div class="entity-cell__title"><?= e($product['name']) ?></div>
                                    <div class="entity-cell__meta"><?= e($product['sku']) ?></div>
                                </div>
                            </td>
                            <td><?= e($product['category_name'] ?? 'Uncategorized') ?></td>
                            <td><span class="badge-soft text-capitalize"><?= e((string) $product['status']) ?></span></td>
                            <td><?= e((string) $product['stock_quantity']) ?></td>
                            <td><?= e(format_currency((float) $product['price'])) ?></td>
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
                <p class="eyebrow mb-1">Procurement</p>
                <h3 class="mb-0"><i class="bi bi-journal-check me-2"></i>Recent purchase orders</h3>
            </div>
            <div class="table-shell__meta">
                <span class="badge-soft"><?= e((string) count($supplier['purchase_orders'])) ?> rows</span>
            </div>
        </div>
        <?php if ($supplier['purchase_orders'] === []): ?>
            <div class="empty-state">No purchase orders have been recorded for this supplier yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle data-table">
                    <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>Created</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($supplier['purchase_orders'] as $order): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('inventory/purchase-orders/show?id=' . $order['id'])) ?>" class="fw-semibold text-decoration-none">
                                    <?= e($order['po_number']) ?>
                                </a>
                            </td>
                            <td><?= e($order['branch_name']) ?></td>
                            <td><span class="badge-soft text-capitalize"><?= e(str_replace('_', ' ', (string) $order['status'])) ?></span></td>
                            <td><?= e(format_currency((float) $order['total'])) ?></td>
                            <td><?= e(date('Y-m-d', strtotime((string) $order['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</div>
