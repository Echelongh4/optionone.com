<div data-refresh-region="supplier-directory">
<section class="surface-card card-panel table-shell mb-4">
    <div class="toolbar-card">
        <div>
            <h3 class="mb-0"><i class="bi bi-truck me-2"></i>Suppliers</h3>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('inventory')) ?>" class="btn btn-outline-secondary">Back to Inventory</a>
            <a href="<?= e(url('suppliers/create')) ?>" class="btn btn-primary" data-modal data-title="Add Supplier" data-refresh-target='[data-refresh-region="supplier-directory"]'><i class="bi bi-plus-lg me-1"></i>Add Supplier</a>
        </div>
    </div>
</section>

<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Suppliers</span><span>Active</span></div>
        <h3><?= e((string) $summary['total_suppliers']) ?></h3>
        <div class="text-muted">Visible supplier records in the current branch scope.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Reachable</span><span>Contact</span></div>
        <h3><?= e((string) $summary['reachable_suppliers']) ?></h3>
        <div class="text-muted">Suppliers with phone or email details on file.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Linked Products</span><span>Catalog</span></div>
        <h3><?= e((string) $summary['linked_products']) ?></h3>
        <div class="text-muted">Products currently mapped to supplier records.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Purchase Volume</span><span>Historical</span></div>
        <h3><?= e(format_currency($summary['purchase_value'])) ?></h3>
        <div class="text-muted"><?= e((string) $summary['purchase_orders']) ?> purchase orders recorded.</div>
    </section>
</div>

<section class="surface-card card-panel table-shell mb-4">
    <div class="toolbar-card">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-search me-1"></i>Directory Filter</p>
            <h3 class="mb-0"><i class="bi bi-funnel me-2"></i>Search suppliers</h3>
        </div>
    </div>

    <form method="get" action="<?= e(url('suppliers')) ?>" class="form-grid align-items-end">
        <div class="field-stack">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" value="<?= e($filters['search']) ?>" placeholder="Supplier, contact, email, phone, or tax number">
        </div>
        <div class="workspace-panel__actions align-items-end">
            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-search me-1"></i>Apply Filter</button>
            <a href="<?= e(url('suppliers')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
        </div>
    </form>
</section>

<section class="surface-card card-panel table-shell">
    <div class="table-shell__header">
        <div>
            <p class="eyebrow mb-1">Vendor Directory</p>
            <h3 class="mb-0"><i class="bi bi-building me-2"></i>Supplier register</h3>
        </div>
        <div class="table-shell__meta">
            <span class="badge-soft"><i class="bi bi-collection"></i><?= e((string) count($suppliers)) ?> suppliers</span>
        </div>
    </div>

    <?php if ($suppliers === []): ?>
        <div class="empty-state">No suppliers matched the current filter.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle data-table">
                <thead>
                <tr>
                    <th><i class="bi bi-building me-1"></i>Supplier</th>
                    <th><i class="bi bi-person-badge me-1"></i>Contact</th>
                    <th><i class="bi bi-box-seam me-1"></i>Products</th>
                    <th><i class="bi bi-journal-check me-1"></i>POs</th>
                    <th><i class="bi bi-cash-stack me-1"></i>Spend</th>
                    <th><i class="bi bi-clock-history me-1"></i>Last PO</th>
                    <th class="text-end"><i class="bi bi-list-check me-1"></i>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($suppliers as $supplier): ?>
                    <tr>
                        <td>
                            <div class="entity-cell">
                                <div class="entity-cell__title"><?= e($supplier['name']) ?></div>
                                <div class="entity-cell__meta"><?= e($supplier['tax_number'] ?: 'No tax number') ?></div>
                            </div>
                        </td>
                        <td>
                            <div><?= e($supplier['contact_person'] ?: 'No contact person') ?></div>
                            <div class="small text-muted"><?= e($supplier['email'] ?: ($supplier['phone'] ?: 'No contact detail')) ?></div>
                        </td>
                        <td><?= e((string) $supplier['total_products']) ?></td>
                        <td><?= e((string) $supplier['total_purchase_orders']) ?></td>
                        <td><?= e(format_currency((float) ($supplier['total_purchase_value'] ?? 0))) ?></td>
                        <td><?= e($supplier['last_purchase_order_at'] ? date('Y-m-d', strtotime((string) $supplier['last_purchase_order_at'])) : 'None') ?></td>
                        <td class="text-end">
                            <div class="d-none d-lg-flex justify-content-end gap-2">
                                <a href="<?= e(url('suppliers/show?id=' . $supplier['id'])) ?>" class="btn btn-sm btn-outline-secondary">View</a>
                                <a href="<?= e(url('suppliers/edit?id=' . $supplier['id'])) ?>" class="btn btn-sm btn-outline-primary" data-modal data-title="Edit Supplier" data-refresh-target='[data-refresh-region="supplier-directory"]'>Edit</a>
                                <form action="<?= e(url('suppliers/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="supplier-directory"]'>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e((string) $supplier['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm-action data-confirm-title="Archive this supplier?" data-confirm-text="The supplier will be removed from active selection lists but historical records will stay intact." data-confirm-button="Archive Supplier">Archive</button>
                                </form>
                            </div>
                            <div class="dropdown d-lg-none">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?= e(url('suppliers/show?id=' . $supplier['id'])) ?>">View Details</a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('suppliers/edit?id=' . $supplier['id'])) ?>" data-modal data-title="Edit Supplier" data-refresh-target='[data-refresh-region="supplier-directory"]'>Edit</a></li>
                                    <li>
                                        <form action="<?= e(url('suppliers/delete')) ?>" method="post" class="m-0" data-ajax="true" data-refresh-target='[data-refresh-region="supplier-directory"]'>
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= e((string) $supplier['id']) ?>">
                                            <button type="submit" class="dropdown-item text-danger" data-confirm-action data-confirm-title="Archive this supplier?" data-confirm-text="The supplier will be removed from active selection lists but historical records will stay intact." data-confirm-button="Archive Supplier">Archive</button>
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
    <?php endif; ?>
</section>
</div>
