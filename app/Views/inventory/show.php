<?php
$stockState = (string) ($item['stock_state'] ?? 'normal');
$statusClass = match ($stockState) {
    'out_of_stock' => 'status-pill status-pill--danger',
    'low' => 'status-pill status-pill--warning',
    'not_tracked' => 'status-pill',
    default => 'status-pill status-pill--success',
};
?>

<section class="surface-card card-panel table-shell mb-4">
    <div class="toolbar-card">
        <div>
            <p class="eyebrow mb-1">Inventory Detail</p>
            <h3 class="mb-1"><?= e($item['name']) ?></h3>
            <div class="text-muted small">
                <?= e($item['sku']) ?>
                <?php if (!empty($item['barcode'])): ?>
                    | <?= e($item['barcode']) ?>
                <?php endif; ?>
                | <?= e($item['unit'] ?: 'unit') ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= e(url('inventory')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Inventory</a>
            <a href="<?= e(url('inventory?product_id=' . (int) $item['id'])) ?>" class="btn btn-outline-primary"><i class="bi bi-tools me-1"></i>Adjust on Register</a>
            <a href="<?= e(url('inventory?product_id=' . (int) $item['id'] . '&prefill_direction=increase&prefill_unit_cost=' . urlencode((string) $item['average_cost']) . '&prefill_reason=' . urlencode('Received from supplier - PO #') . '&prefill_reason_code=' . urlencode('Received from supplier - PO'))) ?>" class="btn btn-success"><i class="bi bi-plus-circle me-1"></i>Quick Restock</a>
            <a href="<?= e(url('products/show?id=' . (int) $item['id'])) ?>" class="btn btn-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Open Product</a>
        </div>
    </div>
</section>

<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>On Hand</span><span><?= e($item['unit'] ?: 'unit') ?></span></div>
        <h3><?= e(number_format((float) $item['quantity_on_hand'], 2)) ?></h3>
        <div class="text-muted">Current physical stock recorded for this branch.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Available</span><span>Sellable</span></div>
        <h3><?= e(number_format((float) $item['available_quantity'], 2)) ?></h3>
        <div class="text-muted"><?= e(number_format((float) $item['quantity_reserved'], 2)) ?> units are reserved.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Stock State</span><span>Health</span></div>
        <h3 class="mb-2"><span class="<?= e($statusClass) ?>"><?= e(str_replace('_', ' ', $stockState)) ?></span></h3>
        <div class="text-muted">Threshold shortfall: <?= e(number_format((float) $item['shortfall_quantity'], 2)) ?> units.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Inventory Value</span><span>Average cost</span></div>
        <h3><?= e(format_currency($item['inventory_value'])) ?></h3>
        <div class="text-muted">Average cost <?= e(format_currency($item['average_cost'])) ?> per unit.</div>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel table-shell">
        <div class="table-shell__header">
            <div>
                <p class="eyebrow mb-1">Stock Profile</p>
                <h3 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Inventory snapshot</h3>
            </div>
            <div class="table-shell__meta">
                <span class="badge-soft"><i class="bi bi-archive"></i><?= e($item['inventory_method'] ?: 'FIFO') ?></span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <tbody>
                    <tr>
                        <th scope="row">Category</th>
                        <td><?= e($item['category_name'] ?? 'Uncategorized') ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Supplier</th>
                        <td>
                            <?php if (!empty($item['supplier_id'])): ?>
                                <a href="<?= e(url('suppliers/show?id=' . (int) $item['supplier_id'])) ?>" class="text-decoration-none"><?= e($item['supplier_name'] ?? 'Supplier') ?></a>
                            <?php else: ?>
                                <?= e($item['supplier_name'] ?? 'Unassigned') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sales Price</th>
                        <td><?= e(format_currency($item['price'])) ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Tax</th>
                        <td><?= e(($item['tax_name'] ?? 'No tax') . (!empty($item['tax_rate']) ? ' (' . number_format((float) $item['tax_rate'], 2) . '%)' : '')) ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Reorder Threshold</th>
                        <td><?= e(number_format((float) $item['low_stock_threshold'], 2)) ?> <?= e($item['unit'] ?: '') ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Open Purchase Quantity</th>
                        <td><?= e(number_format((float) $item['open_purchase_quantity'], 2)) ?> <?= e($item['unit'] ?: '') ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Inbound 30 Days</th>
                        <td><?= e(number_format((float) $item['inbound_units_30d'], 2)) ?> <?= e($item['unit'] ?: '') ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Sold 30 Days</th>
                        <td><?= e(number_format((float) $item['units_sold_30d'], 2)) ?> <?= e($item['unit'] ?: '') ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Last Restocked</th>
                        <td><?= e($item['last_restocked_at'] ?? 'Not recorded') ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Last Movement</th>
                        <td><?= e($item['last_movement_at'] ?? 'No movement recorded') ?></td>
                    </tr>
                    <?php if (!empty($item['description'])): ?>
                        <tr>
                            <th scope="row">Notes</th>
                            <td><?= e($item['description']) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="surface-card card-panel table-shell">
        <div class="table-shell__header">
            <div>
                <p class="eyebrow mb-1">Replenishment Context</p>
                <h3 class="mb-0"><i class="bi bi-journal-check me-2"></i>Recent purchase orders</h3>
            </div>
            <div class="table-shell__meta">
                <span class="badge-soft"><i class="bi bi-truck"></i><?= e((string) count($purchaseOrders)) ?> linked orders</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0 data-table">
                <thead>
                    <tr>
                        <th>PO</th>
                        <th>Status</th>
                        <th>Ordered</th>
                        <th>Received</th>
                        <th>Expected</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($purchaseOrders === []): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No linked purchase orders found for this item.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($purchaseOrders as $purchaseOrder): ?>
                        <tr>
                            <td>
                                <div class="inventory-meta-stack">
                                    <div class="fw-semibold"><?= e($purchaseOrder['po_number']) ?></div>
                                    <div class="small text-muted"><?= e($purchaseOrder['supplier_name'] ?? 'Supplier not assigned') ?></div>
                                </div>
                            </td>
                            <td><span class="status-pill"><?= e(str_replace('_', ' ', (string) $purchaseOrder['status'])) ?></span></td>
                            <td><?= e(number_format((float) $purchaseOrder['quantity'], 2)) ?></td>
                            <td><?= e(number_format((float) $purchaseOrder['received_quantity'], 2)) ?></td>
                            <td><?= e($purchaseOrder['expected_at'] ?? 'Not scheduled') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="surface-card card-panel table-shell">
    <div class="table-shell__header">
        <div>
            <p class="eyebrow mb-1">Movement Ledger</p>
            <h3 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Product movement history</h3>
        </div>
        <div class="table-shell__meta">
            <span class="badge-soft"><i class="bi bi-clock-history"></i><?= e((string) count($movements)) ?> recent movements</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Type</th>
                    <th>Change</th>
                    <th>Balance</th>
                    <th>Reason</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($movements === []): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No stock movements recorded for this product yet.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($movements as $movement): ?>
                    <?php
                    $movementClass = (float) ($movement['quantity_change'] ?? 0) < 0 ? 'status-pill status-pill--danger' : 'status-pill status-pill--success';
                    ?>
                    <tr>
                        <td><?= e((string) $movement['created_at']) ?></td>
                        <td><span class="status-pill"><?= e(str_replace('_', ' ', (string) $movement['movement_type'])) ?></span></td>
                        <td><span class="<?= e($movementClass) ?>"><?= e(number_format((float) $movement['quantity_change'], 2)) ?></span></td>
                        <td><?= e(number_format((float) $movement['balance_after'], 2)) ?></td>
                        <td><?= e($movement['reason'] ?? '') ?></td>
                        <td><?= e($movement['user_name'] ?? 'System') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
