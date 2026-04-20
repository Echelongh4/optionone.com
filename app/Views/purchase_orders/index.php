<?php
$statusMeta = static function (string $status): array {
    return match ($status) {
        'draft' => ['Draft', 'status-pill'],
        'ordered' => ['Ordered', 'status-pill status-pill--info'],
        'partial_received' => ['Partial', 'status-pill status-pill--warning'],
        'received' => ['Received', 'status-pill status-pill--success'],
        'cancelled' => ['Cancelled', 'status-pill status-pill--danger'],
        default => [ucfirst(str_replace('_', ' ', $status)), 'status-pill'],
    };
};
?>
<div data-refresh-region="purchase-order-register">
<div class="workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-journal-check me-1"></i>Procurement Workspace</p>
            <h3 class="mb-1">Purchase Orders</h3>
            <div class="text-muted">Track supplier orders, receipt progress, and remaining inbound stock from one standard workflow.</div>
        </div>
        <div class="workspace-panel__actions d-flex gap-2 flex-wrap">
            <a href="<?= e(url('inventory')) ?>" class="btn btn-outline-secondary"><i class="bi bi-box-seam me-1"></i>Inventory</a>
            <a href="<?= e(url('inventory/purchase-orders/create')) ?>" class="btn btn-primary" data-modal data-title="New Purchase Order" data-refresh-target='[data-refresh-region="purchase-order-register"]'><i class="bi bi-plus-lg me-1"></i>New PO</a>
        </div>
    </div>
</div>

<div class="metric-grid mb-4">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Orders</span><span>Visible</span></div>
        <h3><?= e((string) count($orders)) ?></h3>
        <div class="text-muted">Purchase orders in the current filtered result.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Open Orders</span><span>Outstanding</span></div>
        <h3><?= e((string) count(array_filter($orders, static fn (array $order): bool => in_array((string) $order['status'], ['draft', 'ordered', 'partial_received'], true)))) ?></h3>
        <div class="text-muted">Orders that can still receive additional stock.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Outstanding Units</span><span>Inbound</span></div>
        <h3><?= e(number_format(array_reduce($orders, static fn (float $carry, array $order): float => $carry + (float) ($order['remaining_units'] ?? 0), 0.0), 2)) ?></h3>
        <div class="text-muted">Units still expected from suppliers.</div>
    </section>
</div>

<section class="surface-card card-panel mb-4">
    <form method="get" action="<?= e(url('inventory/purchase-orders')) ?>" class="form-grid align-items-end">
        <div>
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" placeholder="PO number, supplier, or creator" value="<?= e((string) ($filters['search'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label">Supplier</label>
            <select name="supplier_id" class="form-select">
                <option value="">All suppliers</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= e((string) $supplier['id']) ?>" <?= $filters['supplier_id'] === (string) $supplier['id'] ? 'selected' : '' ?>><?= e($supplier['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach (['draft', 'ordered', 'partial_received', 'received', 'cancelled'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Created from</label>
            <input type="date" name="date_from" class="form-control" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>
        <div>
            <label class="form-label">Created to</label>
            <input type="date" name="date_to" class="form-control" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="d-flex gap-2 flex-wrap" style="grid-column: 1 / -1;">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
            <a href="<?= e(url('inventory/purchase-orders')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
        </div>
    </form>
</section>

<section class="surface-card card-panel table-shell">
    <div class="table-shell__header">
        <div>
            <p class="eyebrow mb-1">Procurement Ledger</p>
            <h3 class="mb-0"><i class="bi bi-receipt-cutoff me-2"></i>Purchase order register</h3>
        </div>
        <div class="table-shell__meta">
            <span class="badge-soft"><i class="bi bi-truck"></i><?= e((string) count($orders)) ?> orders</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th>Purchase Order</th>
                <th>Supplier</th>
                <th>Status</th>
                <th>Progress</th>
                <th>Outstanding</th>
                <th>Total</th>
                <th>Expected</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <?php
                [$statusLabel, $statusClass] = $statusMeta((string) $order['status']);
                $orderedUnits = (float) ($order['ordered_units'] ?? 0);
                $receivedUnits = (float) ($order['received_units'] ?? 0);
                $remainingUnits = max((float) ($order['remaining_units'] ?? 0), 0);
                $progress = $orderedUnits > 0 ? min(100, round(($receivedUnits / $orderedUnits) * 100, 1)) : 0;
                $canEdit = (string) $order['status'] === 'draft' && $receivedUnits <= 0.0001;
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($order['po_number']) ?></div>
                        <div class="small text-muted">Created by <?= e($order['created_by_name']) ?></div>
                        <div class="small text-muted"><?= e((string) $order['created_at']) ?></div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($order['supplier_name']) ?></div>
                        <div class="small text-muted"><?= e((string) $order['item_count']) ?> lines</div>
                    </td>
                    <td><span class="<?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td>
                    <td style="min-width: 14rem;">
                        <div class="small fw-semibold mb-1"><?= e(number_format($receivedUnits, 2)) ?> / <?= e(number_format($orderedUnits, 2)) ?> units</div>
                        <div class="progress" style="height: 0.5rem;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?= e((string) $progress) ?>%;" aria-valuenow="<?= e((string) $progress) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e(number_format($remainingUnits, 2)) ?></div>
                        <div class="small text-muted">units remaining</div>
                    </td>
                    <td><?= e(format_currency($order['total'])) ?></td>
                    <td><?= e((string) ($order['expected_at'] ?: 'Not scheduled')) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                            <a href="<?= e(url('inventory/purchase-orders/show?id=' . $order['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                            <?php if ($canEdit): ?>
                                <a href="<?= e(url('inventory/purchase-orders/edit?id=' . $order['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-modal data-title="Edit Purchase Order" data-refresh-target='[data-refresh-region="purchase-order-register"]'><i class="bi bi-pencil-square"></i></a>
                            <?php endif; ?>
                            <form action="<?= e(url('inventory/purchase-orders/duplicate')) ?>" method="post" class="d-inline" data-loading-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $order['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" data-confirm-action data-confirm-title="Duplicate this purchase order?" data-confirm-text="A new draft will be created with the same line items and supplier." data-confirm-button="Duplicate Order"><i class="bi bi-copy"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>
