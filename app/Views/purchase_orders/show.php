<?php
$statusMeta = static function (string $status): array {
    return match ($status) {
        'draft' => ['Draft', 'status-pill'],
        'ordered' => ['Ordered', 'status-pill status-pill--info'],
        'partial_received' => ['Partial Receipt', 'status-pill status-pill--warning'],
        'received' => ['Received', 'status-pill status-pill--success'],
        'cancelled' => ['Cancelled', 'status-pill status-pill--danger'],
        default => [ucfirst(str_replace('_', ' ', $status)), 'status-pill'],
    };
};
[$statusLabel, $statusClass] = $statusMeta((string) $order['status']);
?>
<div data-refresh-region="purchase-order-detail">
<div class="workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-upc-scan me-1"></i>Purchase Order</p>
            <h3 class="mb-1"><?= e($order['po_number']) ?></h3>
            <div class="text-muted">Supplier receipt workflow with partial receiving, outstanding quantities, and inventory sync.</div>
        </div>
        <div class="workspace-panel__actions d-flex gap-2 flex-wrap">
            <a href="<?= e(url('inventory/purchase-orders')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
            <?php if ($canEdit): ?>
                <a href="<?= e(url('inventory/purchase-orders/edit?id=' . $order['id'])) ?>" class="btn btn-outline-secondary" data-modal data-title="Edit Purchase Order" data-refresh-target='[data-refresh-region="purchase-order-detail"]'><i class="bi bi-pencil-square me-1"></i>Edit Draft</a>
            <?php endif; ?>
            <form action="<?= e(url('inventory/purchase-orders/duplicate')) ?>" method="post" class="d-inline" data-loading-form>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $order['id']) ?>">
                <button type="submit" class="btn btn-outline-secondary" data-confirm-action data-confirm-title="Duplicate this purchase order?" data-confirm-text="A new draft will be created with the same supplier and order lines." data-confirm-button="Duplicate Order"><i class="bi bi-copy me-1"></i>Duplicate</button>
            </form>
            <?php if ($canReceive): ?>
                <a href="#receive-stock" class="btn btn-primary"><i class="bi bi-box-arrow-in-down me-1"></i>Receive Stock</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="metric-grid mb-4">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Ordered Units</span><span>Total</span></div>
        <h3><?= e(number_format((float) $order['ordered_units'], 2)) ?></h3>
        <div class="text-muted">All units requested from the supplier.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Received Units</span><span>Processed</span></div>
        <h3><?= e(number_format((float) $order['received_units'], 2)) ?></h3>
        <div class="text-muted">Units already posted into inventory.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Outstanding</span><span>Remaining</span></div>
        <h3><?= e(number_format((float) $order['remaining_units'], 2)) ?></h3>
        <div class="text-muted">Units still expected on this order.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Completion</span><span>Progress</span></div>
        <h3><?= e(number_format((float) $order['completion_rate'], 1)) ?>%</h3>
        <div class="text-muted"><?= e((string) $order['pending_item_count']) ?> line(s) still open.</div>
    </section>
</div>

<div class="content-grid mb-4">
    <section class="surface-card card-panel">
        <div class="table-shell__header mb-3">
            <div>
                <p class="eyebrow mb-1">Order Summary</p>
                <h3 class="mb-0">Procurement details</h3>
            </div>
            <div class="table-shell__meta">
                <span class="<?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
            </div>
        </div>

        <div class="form-grid">
            <div><div class="small text-muted">Supplier</div><div class="fw-semibold"><?= e($order['supplier_name']) ?></div></div>
            <div><div class="small text-muted">Branch</div><div class="fw-semibold"><?= e($order['branch_name']) ?></div></div>
            <div><div class="small text-muted">Created By</div><div class="fw-semibold"><?= e($order['created_by_name']) ?></div></div>
            <div><div class="small text-muted">Expected Delivery</div><div class="fw-semibold"><?= e((string) ($order['expected_at'] ?: 'Not scheduled')) ?></div></div>
            <div><div class="small text-muted">Ordered At</div><div class="fw-semibold"><?= e((string) ($order['ordered_at'] ?: 'Not issued')) ?></div></div>
            <div><div class="small text-muted">Fully Received At</div><div class="fw-semibold"><?= e((string) ($order['received_at'] ?: 'Not completed')) ?></div></div>
        </div>

        <?php if (!empty($order['notes'])): ?>
            <hr>
            <div class="small text-muted mb-1">Notes</div>
            <div><?= nl2br(e((string) $order['notes'])) ?></div>
        <?php endif; ?>
    </section>

    <section class="surface-card card-panel">
        <div class="table-shell__header mb-3">
            <div>
                <p class="eyebrow mb-1">Workflow Actions</p>
                <h3 class="mb-0">Order controls</h3>
            </div>
        </div>

        <?php if ($order['status'] === 'draft'): ?>
            <form action="<?= e(url('inventory/purchase-orders/status')) ?>" method="post" class="d-grid gap-3 mb-3" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="purchase-order-detail"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="submission_key" value="<?= e((string) ($workflowSubmissionKey ?? '')) ?>">
                <input type="hidden" name="id" value="<?= e((string) $order['id']) ?>">
                <input type="hidden" name="status" value="ordered">
                <textarea name="note" rows="3" class="form-control" placeholder="Optional issue note for this supplier order"></textarea>
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-send-check me-1"></i>Mark As Ordered</button>
            </form>
        <?php endif; ?>

        <?php if ($canCancel): ?>
            <form action="<?= e(url('inventory/purchase-orders/status')) ?>" method="post" class="d-grid gap-3" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="purchase-order-detail"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="submission_key" value="<?= e((string) ($workflowSubmissionKey ?? '')) ?>">
                <input type="hidden" name="id" value="<?= e((string) $order['id']) ?>">
                <input type="hidden" name="status" value="cancelled">
                <textarea name="note" rows="3" class="form-control" placeholder="Explain why this order is being closed or cancelled"></textarea>
                <button type="submit" class="btn btn-outline-danger" data-confirm-action data-confirm-title="Cancel this purchase order?" data-confirm-text="Any remaining quantities will be closed and can no longer be received from this order." data-confirm-button="Cancel Order"><i class="bi bi-x-octagon me-1"></i>Cancel Order</button>
            </form>
        <?php else: ?>
            <div class="text-muted">This purchase order is closed and no further workflow actions are available.</div>
        <?php endif; ?>
    </section>
</div>

<section class="surface-card card-panel table-shell mb-4">
    <div class="table-shell__header">
        <div>
            <p class="eyebrow mb-1">Line Items</p>
            <h3 class="mb-0">Ordered vs received</h3>
        </div>
        <div class="table-shell__meta">
            <span class="badge-soft"><i class="bi bi-list-check"></i><?= e((string) count($order['items'])) ?> lines</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th>Product</th>
                <th>Ordered</th>
                <th>Received</th>
                <th>Remaining</th>
                <th>Progress</th>
                <th>Last Receipt</th>
                <th>Line Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($order['items'] as $item): ?>
                <?php $progress = min(100, max(0, (float) ($item['completion_percent'] ?? 0))); ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                        <div class="small text-muted"><?= e($item['sku']) ?></div>
                    </td>
                    <td><?= e(number_format((float) $item['quantity'], 2)) ?></td>
                    <td><?= e(number_format((float) $item['received_quantity'], 2)) ?></td>
                    <td><?= e(number_format((float) $item['remaining_quantity'], 2)) ?></td>
                    <td style="min-width: 12rem;">
                        <div class="small fw-semibold mb-1"><?= e(number_format($progress, 1)) ?>%</div>
                        <div class="progress" style="height: 0.5rem;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= e((string) $progress) ?>%;" aria-valuenow="<?= e((string) $progress) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </td>
                    <td><?= e((string) ($item['last_received_at'] ?: 'Not received')) ?></td>
                    <td>
                        <div class="fw-semibold"><?= e(format_currency($item['total'])) ?></div>
                        <div class="small text-muted">Received value: <?= e(format_currency($item['received_total'])) ?></div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
            <tr>
                <th colspan="6" class="text-end">Subtotal</th>
                <th><?= e(format_currency($order['subtotal'])) ?></th>
            </tr>
            <tr>
                <th colspan="6" class="text-end">Tax</th>
                <th><?= e(format_currency($order['tax_total'])) ?></th>
            </tr>
            <tr>
                <th colspan="6" class="text-end">Total</th>
                <th><?= e(format_currency($order['total'])) ?></th>
            </tr>
            </tfoot>
        </table>
    </div>
</section>

<?php if ($canReceive): ?>
    <section class="surface-card card-panel" id="receive-stock">
        <div class="table-shell__header mb-3">
            <div>
                <p class="eyebrow mb-1">Receipt Workspace</p>
                <h3 class="mb-0">Receive incoming stock</h3>
            </div>
            <div class="table-shell__meta">
                <span class="badge-soft"><i class="bi bi-box-arrow-in-down"></i><?= e(number_format((float) $order['remaining_units'], 2)) ?> units open</span>
            </div>
        </div>

        <form action="<?= e(url('inventory/purchase-orders/receive')) ?>" method="post" class="d-grid gap-4" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="purchase-order-detail"]'>
            <?= csrf_field() ?>
            <input type="hidden" name="submission_key" value="<?= e((string) ($receiveSubmissionKey ?? '')) ?>">
            <input type="hidden" name="id" value="<?= e((string) $order['id']) ?>">

            <div class="table-responsive">
                <table class="table align-middle data-table" data-table-search="false" data-table-buttons="false" data-table-paging="false" data-table-info="false" data-table-responsive="false">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>Outstanding</th>
                        <th>Unit Cost</th>
                        <th>Receive Now</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <?php $remainingQuantity = max((float) $item['remaining_quantity'], 0); ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                                <div class="small text-muted"><?= e($item['sku']) ?></div>
                            </td>
                            <td><?= e(number_format($remainingQuantity, 2)) ?></td>
                            <td><?= e(format_currency($item['unit_cost'])) ?></td>
                            <td style="max-width: 12rem;">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="<?= e((string) $remainingQuantity) ?>"
                                    name="received_quantity[<?= e((string) $item['id']) ?>]"
                                    class="form-control"
                                    value="<?= e($remainingQuantity > 0 ? number_format($remainingQuantity, 2, '.', '') : '0.00') ?>"
                                    <?= $remainingQuantity <= 0 ? 'readonly' : '' ?>
                                >
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div>
                <label class="form-label">Receipt note</label>
                <textarea name="note" rows="3" class="form-control" placeholder="Optional note for this receipt, such as invoice number, shortage details, or receiving remarks"></textarea>
            </div>

            <div class="d-flex gap-2 flex-wrap justify-content-end">
                <button type="submit" class="btn btn-primary" data-confirm-action data-confirm-title="Post this stock receipt?" data-confirm-text="Inventory balances will be updated immediately for the quantities entered." data-confirm-button="Receive Stock"><i class="bi bi-box-arrow-in-down me-1"></i>Receive Selected Stock</button>
            </div>
        </form>
    </section>
<?php endif; ?>
</div>
