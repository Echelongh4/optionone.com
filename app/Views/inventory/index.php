<?php
$movementTypes = ['purchase', 'sale', 'return', 'adjustment', 'transfer_in', 'transfer_out', 'void', 'opening'];
$stockStates = [
    '' => 'All stock states',
    'out_of_stock' => 'Out of stock',
    'low' => 'Low stock',
    'normal' => 'Healthy stock',
    'not_tracked' => 'Not tracked',
];
$sortOptions = [
    'priority' => 'Priority queue',
    'available_asc' => 'Lowest availability',
    'sales_desc' => 'Fast movers (30d)',
    'value_desc' => 'Highest value',
    'movement_desc' => 'Latest movement',
];

$filters = $filters ?? [];
$summary = $summary ?? [];
$items = $items ?? [];
$movements = $movements ?? [];
$products = $products ?? [];
$selectedProduct = $selectedProduct ?? null;
$attentionItems = $attentionItems ?? [];
$filterMeta = $filterMeta ?? ['active_count' => 0, 'has_advanced' => false];
$movementLimit = (int) ($movementLimit ?? count($movements));

$adjustQuery = array_filter([
    'search' => $filters['search'] ?? '',
    'stock_state' => $filters['stock_state'] ?? '',
    'product_id' => $filters['product_id'] ?? '',
    'sort' => $filters['sort'] ?? 'priority',
    'movement_type' => $filters['movement_type'] ?? '',
    'date_from' => $filters['date_from'] ?? '',
    'date_to' => $filters['date_to'] ?? '',
], static fn ($value): bool => $value !== '' && $value !== null);

$adjustAction = 'inventory/adjust';
if ($adjustQuery !== []) {
    $adjustAction .= '?' . http_build_query($adjustQuery);
}

$adjustErrors = $adjustErrors ?? [];
$adjustForm = $adjustForm ?? [];
$adjustHasErrors = $adjustErrors !== [];
$adjustGeneralErrors = $adjustErrors['general'] ?? [];
$productFieldError = $adjustErrors['product_id'][0] ?? null;
$directionFieldError = $adjustErrors['direction'][0] ?? null;
$quantityFieldError = $adjustErrors['quantity'][0] ?? null;
$unitCostFieldError = $adjustErrors['unit_cost'][0] ?? null;
$reasonFieldError = $adjustErrors['reason'][0] ?? null;

$activeSort = (string) ($filters['sort'] ?? 'priority');
$alertCount = (int) ($summary['low_stock_count'] ?? 0) + (int) ($summary['out_of_stock_count'] ?? 0);
$restockReason = 'Received from supplier - PO #';
$restockReasonCode = 'Received from supplier - PO';

$statusClass = static function (string $stockState): string {
    return match ($stockState) {
        'out_of_stock' => 'status-pill status-pill--danger',
        'low' => 'status-pill status-pill--warning',
        'not_tracked' => 'status-pill',
        default => 'status-pill status-pill--success',
    };
};

$movementClass = static function (float $quantityChange): string {
    return $quantityChange < 0 ? 'status-pill status-pill--danger' : 'status-pill status-pill--success';
};

$inventoryUrl = static function (array $overrides = [], array $remove = []) use ($filters): string {
    $params = array_merge($filters, $overrides);

    foreach ($remove as $key) {
        unset($params[$key]);
    }

    $params = array_filter($params, static fn ($value): bool => $value !== '' && $value !== null);

    return url('inventory') . ($params !== [] ? '?' . http_build_query($params) : '');
};

$quickViews = [
    [
        'label' => 'Priority',
        'href' => $inventoryUrl(['stock_state' => '', 'sort' => 'priority']),
        'active' => ($filters['stock_state'] ?? '') === '' && $activeSort === 'priority',
        'meta' => $alertCount . ' alerts',
    ],
    [
        'label' => 'Out of Stock',
        'href' => $inventoryUrl(['stock_state' => 'out_of_stock', 'sort' => 'priority']),
        'active' => ($filters['stock_state'] ?? '') === 'out_of_stock',
        'meta' => (string) ($summary['out_of_stock_count'] ?? 0),
    ],
    [
        'label' => 'Low Stock',
        'href' => $inventoryUrl(['stock_state' => 'low', 'sort' => 'priority']),
        'active' => ($filters['stock_state'] ?? '') === 'low',
        'meta' => (string) ($summary['low_stock_count'] ?? 0),
    ],
    [
        'label' => 'Fast Movers',
        'href' => $inventoryUrl(['stock_state' => '', 'sort' => 'sales_desc']),
        'active' => $activeSort === 'sales_desc' && ($filters['stock_state'] ?? '') === '',
        'meta' => '30d',
    ],
    [
        'label' => 'Highest Value',
        'href' => $inventoryUrl(['stock_state' => '', 'sort' => 'value_desc']),
        'active' => $activeSort === 'value_desc' && ($filters['stock_state'] ?? '') === '',
        'meta' => format_currency((float) ($summary['total_inventory_value'] ?? 0)),
    ],
];

$selectedProductRestockHref = $selectedProduct !== null
    ? $inventoryUrl([
        'open_adjustment' => '1',
        'product_id' => (string) $selectedProduct['id'],
        'prefill_direction' => 'increase',
        'prefill_unit_cost' => (string) ($selectedProduct['average_cost'] ?? $selectedProduct['cost_price'] ?? 0),
        'prefill_reason' => $restockReason,
        'prefill_reason_code' => $restockReasonCode,
    ])
    : null;
?>

<div data-refresh-region="inventory-workspace">
<section class="surface-card card-panel table-shell mb-4 inventory-command-bar">
    <div class="toolbar-card">
        <div class="inventory-command-copy">
            <p class="eyebrow mb-1"><i class="bi bi-box-seam me-1"></i>Inventory Control</p>
            <h3 class="mb-1">Stock Workspace</h3>
            <div class="text-muted">Track availability, reorder pressure, purchase order coverage, and stock movements from one focused page.</div>
        </div>
        <div class="inventory-command-actions">
            <span class="badge-soft"><i class="bi bi-wallet2 me-1"></i><?= e(format_currency((float) ($summary['total_inventory_value'] ?? 0))) ?> value</span>
            <span class="badge-soft"><i class="bi bi-exclamation-triangle me-1"></i><?= e((string) $alertCount) ?> alerts</span>
            <a href="<?= e(url('inventory/purchase-orders')) ?>" class="btn btn-outline-secondary"><i class="bi bi-journal-check me-1"></i>Purchase Orders</a>
            <a href="<?= e(url('inventory/transfers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-right me-1"></i>Transfers</a>
            <a href="<?= e(url('suppliers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-truck me-1"></i>Suppliers</a>
            <a href="<?= e($inventoryUrl(['open_adjustment' => '1'])) ?>" class="btn btn-primary" data-open-inventory-adjustment><i class="bi bi-plus-lg me-1"></i>Adjust Stock</a>
        </div>
    </div>

    <div class="inventory-command-surface">
        <div class="inventory-quick-views">
            <?php foreach ($quickViews as $quickView): ?>
                <a href="<?= e($quickView['href']) ?>" class="inventory-filter-chip <?= $quickView['active'] ? 'inventory-filter-chip--active' : '' ?>">
                    <span><?= e($quickView['label']) ?></span>
                    <small><?= e((string) $quickView['meta']) ?></small>
                </a>
            <?php endforeach; ?>
            <?php if ($selectedProduct !== null): ?>
                <a href="<?= e($inventoryUrl([], ['product_id'])) ?>" class="inventory-filter-chip inventory-filter-chip--ghost">
                    <span>Clear Focus</span>
                    <small><?= e((string) $selectedProduct['name']) ?></small>
                </a>
            <?php endif; ?>
        </div>

        <form method="get" action="<?= e(url('inventory')) ?>" class="inventory-filter-grid">
            <div class="inventory-filter-grid__main">
                <div class="field-stack">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" value="<?= e((string) ($filters['search'] ?? '')) ?>" placeholder="Product name, brand, SKU, or barcode">
                </div>
                <div class="field-stack">
                    <label class="form-label">Stock State</label>
                    <select name="stock_state" class="form-select">
                        <?php foreach ($stockStates as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= (string) ($filters['stock_state'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label">Product Focus</label>
                    <select name="product_id" class="form-select">
                        <option value="">All products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= e((string) $product['id']) ?>" <?= (string) ($filters['product_id'] ?? '') === (string) $product['id'] ? 'selected' : '' ?>>
                                <?= e($product['name'] . (!empty($product['brand']) ? ' | ' . $product['brand'] : '') . ' (' . $product['sku'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label">Sort Register</label>
                    <select name="sort" class="form-select">
                        <?php foreach ($sortOptions as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= $activeSort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <details class="inventory-advanced-filters" <?= !empty($filterMeta['has_advanced']) ? 'open' : '' ?>>
                <summary>Advanced ledger filters</summary>
                <div class="inventory-filter-grid__advanced">
                    <div class="field-stack">
                        <label class="form-label">Movement Type</label>
                        <select name="movement_type" class="form-select">
                            <option value="">All movement types</option>
                            <?php foreach ($movementTypes as $type): ?>
                                <option value="<?= e($type) ?>" <?= (string) ($filters['movement_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field-stack">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
                    </div>
                    <div class="field-stack">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
                    </div>
                </div>
            </details>

            <div class="inventory-filter-actions">
                <div class="inventory-filter-meta">
                    <span class="badge-soft"><i class="bi bi-boxes me-1"></i><?= e((string) count($items)) ?> register rows</span>
                    <span class="badge-soft"><i class="bi bi-clock-history me-1"></i><?= e((string) count($movements)) ?> recent movements</span>
                    <?php if ((int) ($filterMeta['active_count'] ?? 0) > 0): ?>
                        <span class="badge-soft"><i class="bi bi-funnel me-1"></i><?= e((string) $filterMeta['active_count']) ?> active filters</span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Apply Filters</button>
                    <a href="<?= e(url('inventory')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
                </div>
            </div>
        </form>
    </div>
</section>

<div class="metric-grid mb-4">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Tracked Products</span><span>Catalog</span></div>
        <h3><?= e((string) ($summary['tracked_products'] ?? $summary['total_products'] ?? 0)) ?></h3>
        <div class="text-muted"><?= e((string) ($summary['total_products'] ?? 0)) ?> total products in this branch workspace.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Alerts</span><span>Immediate</span></div>
        <h3><?= e((string) $alertCount) ?></h3>
        <div class="text-muted"><?= e((string) ($summary['out_of_stock_count'] ?? 0)) ?> out of stock and <?= e((string) ($summary['low_stock_count'] ?? 0)) ?> low stock.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Reserved Units</span><span>Committed</span></div>
        <h3><?= e(number_format((float) ($summary['total_reserved_units'] ?? 0), 2)) ?></h3>
        <div class="text-muted"><?= e(number_format((float) ($summary['total_available_units'] ?? 0), 2)) ?> units remain sellable right now.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>On Order</span><span>Coverage</span></div>
        <h3><?= e(number_format((float) ($summary['total_open_purchase_units'] ?? 0), 2)) ?></h3>
        <div class="text-muted"><?= e((string) ($summary['items_on_order'] ?? 0)) ?> products have open purchase coverage.</div>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel table-shell">
        <div class="table-shell__header">
            <div>
                <p class="eyebrow mb-1">Stock Register</p>
                <h3 class="mb-0"><i class="bi bi-box me-2"></i>Inventory register</h3>
                <div class="text-muted">Availability, demand, and restock pressure are consolidated per SKU.</div>
            </div>
            <div class="table-shell__meta">
                <span class="badge-soft"><i class="bi bi-sort-down me-1"></i><?= e($sortOptions[$activeSort] ?? $sortOptions['priority']) ?></span>
                <?php if ($selectedProduct !== null): ?>
                    <span class="badge-soft"><i class="bi bi-crosshair me-1"></i><?= e((string) $selectedProduct['name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle data-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Stock</th>
                        <th>Flow</th>
                        <th>Last Movement</th>
                        <th>Status</th>
                        <th class="actions text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items === []): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No inventory items match the current filters.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($items as $item): ?>
                        <?php
                        $stockState = (string) ($item['stock_state'] ?? 'normal');
                        $itemStatusClass = $statusClass($stockState);
                        $itemRestockHref = $inventoryUrl([
                            'open_adjustment' => '1',
                            'product_id' => (string) $item['id'],
                            'prefill_direction' => 'increase',
                            'prefill_unit_cost' => (string) ($item['average_cost'] ?? 0),
                            'prefill_reason' => $restockReason,
                            'prefill_reason_code' => $restockReasonCode,
                        ]);
                        $itemAdjustHref = $inventoryUrl([
                            'open_adjustment' => '1',
                            'product_id' => (string) $item['id'],
                        ]);
                        ?>
                        <tr>
                            <td>
                                <div class="inventory-meta-stack">
                                    <div class="fw-semibold"><?= e((string) $item['name']) ?></div>
                                    <div class="small text-muted">
                                        <?php if (!empty($item['brand'])): ?>
                                            <?= e((string) $item['brand']) ?> |
                                        <?php endif; ?>
                                        <?= e((string) $item['sku']) ?>
                                        <?php if (!empty($item['category_name'])): ?>
                                            | <?= e((string) $item['category_name']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($item['supplier_name'])): ?>
                                            | <?= e((string) $item['supplier_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="inventory-table-metric">
                                    <strong><?= e(number_format((float) ($item['quantity_on_hand'] ?? 0), 2)) ?></strong>
                                    <span>on hand</span>
                                </div>
                                <div class="small text-muted mt-1">
                                    Available <?= e(number_format((float) ($item['available_quantity'] ?? 0), 2)) ?>
                                    <?php if ((float) ($item['quantity_reserved'] ?? 0) > 0): ?>
                                        | Reserved <?= e(number_format((float) ($item['quantity_reserved'] ?? 0), 2)) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="inventory-table-metric">
                                    <strong><?= e(number_format((float) ($item['units_sold_30d'] ?? 0), 2)) ?></strong>
                                    <span>sold in 30d</span>
                                </div>
                                <div class="small text-muted mt-1">
                                    On order <?= e(number_format((float) ($item['open_purchase_quantity'] ?? 0), 2)) ?>
                                    <?php if ((float) ($item['reorder_quantity'] ?? 0) > 0): ?>
                                        | Reorder <?= e(number_format((float) ($item['reorder_quantity'] ?? 0), 2)) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="inventory-table-metric">
                                    <strong><?= e((string) ($item['last_movement_at'] ?? 'No movement yet')) ?></strong>
                                    <span><?= e((string) ($item['unit'] ?? 'pcs')) ?> | <?= e((string) ($item['inventory_method'] ?? 'FIFO')) ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="inventory-meta-stack">
                                    <span class="<?= e($itemStatusClass) ?>"><?= e(str_replace('_', ' ', $stockState)) ?></span>
                                    <?php if ((string) ($item['status'] ?? 'active') !== 'active'): ?>
                                        <span class="badge-soft">Product <?= e((string) $item['status']) ?></span>
                                    <?php elseif ((float) ($item['inventory_value'] ?? 0) > 0): ?>
                                        <span class="small text-muted"><?= e(format_currency((float) ($item['inventory_value'] ?? 0))) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="actions text-end">
                                <div class="inventory-table-actions">
                                    <a href="<?= e($inventoryUrl(['product_id' => (string) $item['id']])) ?>" class="btn btn-sm btn-outline-secondary">Focus</a>
                                    <?php if ((int) ($item['track_stock'] ?? 1) === 1): ?>
                                        <a
                                            href="<?= e($itemRestockHref) ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            data-open-inventory-adjustment
                                            data-product-id="<?= e((string) $item['id']) ?>"
                                            data-direction="increase"
                                            data-unit-cost="<?= e((string) ($item['average_cost'] ?? 0)) ?>"
                                            data-reason="<?= e($restockReason) ?>"
                                            data-reason-code="<?= e($restockReasonCode) ?>"
                                        >Restock</a>
                                        <a
                                            href="<?= e($itemAdjustHref) ?>"
                                            class="btn btn-sm btn-outline-secondary"
                                            data-open-inventory-adjustment
                                            data-product-id="<?= e((string) $item['id']) ?>"
                                        >Adjust</a>
                                    <?php endif; ?>
                                    <a href="<?= e(url('inventory/show?id=' . (int) $item['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="surface-card card-panel table-shell">
        <div class="table-shell__header">
            <div>
                <p class="eyebrow mb-1">Operations</p>
                <h3 class="mb-0"><i class="bi bi-activity me-2"></i>Decision rail</h3>
                <div class="text-muted">Keep adjustments, replenishment, and top exceptions within reach.</div>
            </div>
            <div class="table-shell__meta">
                <span class="badge-soft"><i class="bi bi-shield-check me-1"></i>All adjustments are audited</span>
            </div>
        </div>

        <div class="inventory-ops-stack">
            <article class="inventory-ops-card inventory-focus-card">
                <?php if ($selectedProduct !== null): ?>
                    <div class="inventory-focus-card__header">
                        <div>
                            <p class="eyebrow mb-1">Focused Item</p>
                            <h4 class="mb-1"><?= e((string) $selectedProduct['name']) ?></h4>
                            <div class="small text-muted">
                                <?php if (!empty($selectedProduct['brand'])): ?>
                                    <?= e((string) $selectedProduct['brand']) ?> |
                                <?php endif; ?>
                                <?= e((string) $selectedProduct['sku']) ?>
                                <?php if (!empty($selectedProduct['barcode'])): ?>
                                    | <?= e((string) $selectedProduct['barcode']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="<?= e($statusClass((string) ($selectedProduct['stock_state'] ?? 'normal'))) ?>"><?= e(str_replace('_', ' ', (string) ($selectedProduct['stock_state'] ?? 'normal'))) ?></span>
                    </div>
                    <div class="inventory-focus-metrics">
                        <div class="inventory-mini-stat">
                            <span>On hand</span>
                            <strong><?= e(number_format((float) ($selectedProduct['quantity_on_hand'] ?? 0), 2)) ?></strong>
                        </div>
                        <div class="inventory-mini-stat">
                            <span>Available</span>
                            <strong><?= e(number_format((float) ($selectedProduct['available_quantity'] ?? 0), 2)) ?></strong>
                        </div>
                        <div class="inventory-mini-stat">
                            <span>On order</span>
                            <strong><?= e(number_format((float) ($selectedProduct['open_purchase_quantity'] ?? 0), 2)) ?></strong>
                        </div>
                        <div class="inventory-mini-stat">
                            <span>Value</span>
                            <strong><?= e(format_currency((float) ($selectedProduct['inventory_value'] ?? 0))) ?></strong>
                        </div>
                    </div>
                    <div class="small text-muted mb-3">
                        Last movement <?= e((string) ($selectedProduct['last_movement_at'] ?? 'not recorded')) ?>
                        | Sold in 30d <?= e(number_format((float) ($selectedProduct['units_sold_30d'] ?? 0), 2)) ?>
                    </div>
                    <div class="inventory-action-cluster">
                        <a
                            href="<?= e((string) $selectedProductRestockHref) ?>"
                            class="btn btn-primary"
                            data-open-inventory-adjustment
                            data-product-id="<?= e((string) $selectedProduct['id']) ?>"
                            data-direction="increase"
                            data-unit-cost="<?= e((string) ($selectedProduct['average_cost'] ?? $selectedProduct['cost_price'] ?? 0)) ?>"
                            data-reason="<?= e($restockReason) ?>"
                            data-reason-code="<?= e($restockReasonCode) ?>"
                        ><i class="bi bi-box-arrow-in-down me-1"></i>Quick Restock</a>
                        <a
                            href="<?= e($inventoryUrl(['open_adjustment' => '1', 'product_id' => (string) $selectedProduct['id']])) ?>"
                            class="btn btn-outline-secondary"
                            data-open-inventory-adjustment
                            data-product-id="<?= e((string) $selectedProduct['id']) ?>"
                        ><i class="bi bi-tools me-1"></i>Adjust Stock</a>
                        <a href="<?= e(url('inventory/show?id=' . (int) $selectedProduct['id'])) ?>" class="btn btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i>Open Detail</a>
                    </div>
                <?php else: ?>
                    <p class="eyebrow mb-1">Focused Item</p>
                    <h4 class="mb-2">No SKU selected</h4>
                    <div class="text-muted mb-3">Focus a product from the register to keep one SKU pinned while you review movements or adjust stock.</div>
                    <div class="inventory-action-cluster">
                        <a href="<?= e($inventoryUrl(['open_adjustment' => '1'])) ?>" class="btn btn-primary" data-open-inventory-adjustment><i class="bi bi-plus-lg me-1"></i>Open Adjustment</a>
                        <a href="<?= e(url('inventory/purchase-orders/create')) ?>" class="btn btn-outline-secondary" data-modal data-title="New Purchase Order" data-refresh-target='[data-refresh-region="inventory-workspace"]'><i class="bi bi-journal-plus me-1"></i>New PO</a>
                    </div>
                <?php endif; ?>
            </article>

            <article class="inventory-ops-card">
                <p class="eyebrow mb-1">Quick Actions</p>
                <div class="inventory-action-cluster">
                    <a href="<?= e(url('inventory/purchase-orders/create')) ?>" class="btn btn-outline-secondary" data-modal data-title="New Purchase Order" data-refresh-target='[data-refresh-region="inventory-workspace"]'><i class="bi bi-journal-plus me-1"></i>Create PO</a>
                    <a href="<?= e(url('inventory/transfers/create')) ?>" class="btn btn-outline-secondary" data-modal data-title="New Transfer" data-refresh-target='[data-refresh-region="inventory-workspace"]'><i class="bi bi-arrow-left-right me-1"></i>New Transfer</a>
                    <a href="<?= e(url('inventory/purchase-orders')) ?>" class="btn btn-outline-secondary"><i class="bi bi-list-check me-1"></i>Open POs</a>
                </div>
            </article>

            <article class="inventory-ops-card">
                <div class="inventory-list-header">
                    <div>
                        <p class="eyebrow mb-1">Need Attention</p>
                        <h4 class="mb-0">Priority products</h4>
                    </div>
                    <span class="badge-soft"><?= e((string) count($attentionItems)) ?> queued</span>
                </div>
                <?php if ($attentionItems === []): ?>
                    <div class="empty-state mt-3">No urgent items in the current register. Switch to priority or low-stock views for replenishment work.</div>
                <?php else: ?>
                    <div class="inventory-attention-list">
                        <?php foreach ($attentionItems as $attentionItem): ?>
                            <?php
                            $attentionRestockHref = $inventoryUrl([
                                'open_adjustment' => '1',
                                'product_id' => (string) $attentionItem['id'],
                                'prefill_direction' => 'increase',
                                'prefill_unit_cost' => (string) ($attentionItem['average_cost'] ?? 0),
                                'prefill_reason' => $restockReason,
                                'prefill_reason_code' => $restockReasonCode,
                            ]);
                            ?>
                            <article class="inventory-attention-item">
                                <div class="inventory-attention-item__head">
                                    <div>
                                        <strong><?= e((string) $attentionItem['name']) ?></strong>
                                        <div class="small text-muted">
                                            <?= e(!empty($attentionItem['brand']) ? (string) $attentionItem['brand'] . ' | ' : '') ?>
                                            <?= e((string) $attentionItem['sku']) ?>
                                        </div>
                                    </div>
                                    <span class="<?= e((string) ($attentionItem['attention_class'] ?? 'status-pill')) ?>"><?= e((string) ($attentionItem['attention_label'] ?? 'Monitor')) ?></span>
                                </div>
                                <div class="small text-muted"><?= e((string) ($attentionItem['attention_message'] ?? 'Review this product.')) ?></div>
                                <div class="inventory-attention-item__actions">
                                    <a href="<?= e($inventoryUrl(['product_id' => (string) $attentionItem['id']])) ?>" class="btn btn-sm btn-outline-secondary">Focus</a>
                                    <a
                                        href="<?= e($attentionRestockHref) ?>"
                                        class="btn btn-sm btn-outline-primary"
                                        data-open-inventory-adjustment
                                        data-product-id="<?= e((string) $attentionItem['id']) ?>"
                                        data-direction="increase"
                                        data-unit-cost="<?= e((string) ($attentionItem['average_cost'] ?? 0)) ?>"
                                        data-reason="<?= e($restockReason) ?>"
                                        data-reason-code="<?= e($restockReasonCode) ?>"
                                    >Restock</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </section>
</div>

<section class="surface-card card-panel table-shell mt-4">
    <div class="table-shell__header">
        <div>
            <p class="eyebrow mb-1">Movement Ledger</p>
            <h3 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Recent stock movements</h3>
            <div class="text-muted">Showing the latest <?= e((string) $movementLimit) ?> rows for the active branch filters.</div>
        </div>
        <div class="table-shell__meta">
            <?php if ($selectedProduct !== null): ?>
                <span class="badge-soft"><i class="bi bi-crosshair me-1"></i><?= e((string) $selectedProduct['name']) ?></span>
            <?php endif; ?>
            <span class="badge-soft"><i class="bi bi-clock-history me-1"></i><?= e((string) count($movements)) ?> rows loaded</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Product</th>
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
                        <td colspan="7" class="text-center text-muted py-4">No stock movements match the current filters.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($movements as $movement): ?>
                    <?php $rowMovementClass = $movementClass((float) ($movement['quantity_change'] ?? 0)); ?>
                    <tr>
                        <td><?= e((string) $movement['created_at']) ?></td>
                        <td>
                            <div class="inventory-meta-stack">
                                <div class="fw-semibold"><?= e((string) $movement['product_name']) ?></div>
                                <div class="small text-muted">
                                    <?= e(!empty($movement['brand']) ? (string) $movement['brand'] . ' | ' : '') ?>
                                    <?= e((string) $movement['sku']) ?>
                                </div>
                            </div>
                        </td>
                        <td><span class="status-pill"><?= e(str_replace('_', ' ', (string) $movement['movement_type'])) ?></span></td>
                        <td><span class="<?= e($rowMovementClass) ?>"><?= e(number_format((float) ($movement['quantity_change'] ?? 0), 2)) ?></span></td>
                        <td><?= e(number_format((float) ($movement['balance_after'] ?? 0), 2)) ?></td>
                        <td><?= e((string) ($movement['reason'] ?? '')) ?></td>
                        <td><?= e((string) ($movement['user_name'] ?? 'System')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
</div>

<div class="modal fade" id="adjustModal" tabindex="-1" aria-hidden="true" data-large-threshold="<?= (int) config('app.inventory.large_adjustment_threshold', 1000) ?>" data-has-errors="<?= $adjustHasErrors ? '1' : '0' ?>">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="<?= e(url($adjustAction)) ?>" method="post" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="inventory-workspace"]' class="modal-form inventory-adjustment-form">
                <?= csrf_field() ?>
                <input type="hidden" name="submission_key" value="<?= e((string) ($adjustSubmissionKey ?? '')) ?>">
                <div class="modal-body">
                    <?php if ($adjustHasErrors): ?>
                        <div class="alert alert-danger rounded-4">
                            <strong>Please fix the inventory adjustment errors and try again.</strong>
                            <?php if (!empty($adjustGeneralErrors[0])): ?>
                                <div class="small mt-2"><?= e((string) $adjustGeneralErrors[0]) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select <?= $productFieldError ? 'is-invalid' : '' ?>" required>
                            <option value="">Select a product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= e((string) $product['id']) ?>" <?= $adjustForm['product_id'] === (string) $product['id'] ? 'selected' : '' ?>>
                                    <?= e($product['name'] . ' (' . $product['sku'] . ') | On hand ' . number_format((float) $product['quantity_on_hand'], 2)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($productFieldError): ?>
                            <div class="invalid-feedback d-block"><?= e((string) $productFieldError) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Direction</label>
                            <select name="direction" class="form-select <?= $directionFieldError ? 'is-invalid' : '' ?>">
                                <option value="increase" <?= $adjustForm['direction'] === 'increase' ? 'selected' : '' ?>>Increase stock</option>
                                <option value="decrease" <?= $adjustForm['direction'] === 'decrease' ? 'selected' : '' ?>>Decrease stock</option>
                            </select>
                            <?php if ($directionFieldError): ?>
                                <div class="invalid-feedback d-block"><?= e((string) $directionFieldError) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control <?= $quantityFieldError ? 'is-invalid' : '' ?>" value="<?= e((string) $adjustForm['quantity']) ?>" required>
                            <?php if ($quantityFieldError): ?>
                                <div class="invalid-feedback d-block"><?= e((string) $quantityFieldError) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit Cost</label>
                            <input type="number" step="0.01" min="0" name="unit_cost" class="form-control <?= $unitCostFieldError ? 'is-invalid' : '' ?>" value="<?= e((string) $adjustForm['unit_cost']) ?>">
                            <div class="small text-muted mt-1">Used on stock increases to refresh average cost.</div>
                            <?php if ($unitCostFieldError): ?>
                                <div class="invalid-feedback d-block"><?= e((string) $unitCostFieldError) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Preset Reason</label>
                            <select id="preset-reason" class="form-select">
                                <option value="">-- choose a preset --</option>
                                <?php foreach ((array) config('app.inventory.presets', []) as $preset): ?>
                                    <option value="<?= e($preset) ?>" <?= (string) ($adjustForm['reason_code'] ?? '') === (string) $preset ? 'selected' : '' ?>><?= e($preset) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="reason_code" id="reason-code-field" value="<?= e((string) ($adjustForm['reason_code'] ?? '')) ?>">
                            <input type="hidden" name="confirm_large" id="confirm-large-field" value="">
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Reason</label>
                        <textarea id="reason-field" name="reason" class="form-control <?= $reasonFieldError ? 'is-invalid' : '' ?>" rows="3" required><?= e((string) $adjustForm['reason']) ?></textarea>
                        <?php if ($reasonFieldError): ?>
                            <div class="invalid-feedback d-block"><?= e((string) $reasonFieldError) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Apply Adjustment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmAdjustModal" tabindex="-1" aria-hidden="true" aria-labelledby="confirmAdjustModalLabel">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmAdjustModalLabel">Confirm large adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="confirmAdjustMessage">This will add X units. Are you sure?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAdjustProceed">Proceed</button>
            </div>
        </div>
    </div>
</div>
