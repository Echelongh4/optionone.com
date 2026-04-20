<div data-refresh-region="stock-transfer-register">
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Transfers</span><span>Network</span></div>
        <h3><?= e((string) $summary['total_transfers']) ?></h3>
        <div class="text-muted">Transfers visible to your current branch scope.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Draft</span><span>Pending</span></div>
        <h3><?= e((string) $summary['draft_count']) ?></h3>
        <div class="text-muted">Transfers waiting to be dispatched.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>In Transit</span><span>Moving</span></div>
        <h3><?= e((string) $summary['in_transit_count']) ?></h3>
        <div class="text-muted">Shipments currently between branches.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Completed</span><span>Closed</span></div>
        <h3><?= e((string) $summary['completed_count']) ?></h3>
        <div class="text-muted">Transfers fully received into destination stock.</div>
    </section>
</div>

<div class="surface-card card-panel mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <p class="eyebrow mb-1">Branch Logistics</p>
            <h3 class="mb-0">Stock Transfer Filters</h3>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(url('inventory')) ?>" class="btn btn-outline-secondary">Inventory</a>
            <a href="<?= e(url('inventory/transfers/create')) ?>" class="btn btn-primary" data-modal data-title="New Transfer" data-refresh-target='[data-refresh-region="stock-transfer-register"]'><i class="bi bi-plus-lg me-1"></i>New Transfer</a>
        </div>
    </div>
    <form method="get" action="<?= e(url('inventory/transfers')) ?>" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Reference, branch, or creator" value="<?= e((string) ($filters['search'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach (['draft', 'in_transit', 'completed', 'cancelled'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Direction</label>
            <select name="direction" class="form-select">
                <option value="">All directions</option>
                <option value="outgoing" <?= $filters['direction'] === 'outgoing' ? 'selected' : '' ?>>Outgoing from my branch</option>
                <option value="incoming" <?= $filters['direction'] === 'incoming' ? 'selected' : '' ?>>Incoming to my branch</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Created from</label>
            <input type="date" name="date_from" class="form-control" value="<?= e((string) ($filters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Created to</label>
            <input type="date" name="date_to" class="form-control" value="<?= e((string) ($filters['date_to'] ?? '')) ?>">
        </div>
        <div class="col-md-4 d-grid gap-2">
            <button type="submit" class="btn btn-outline-primary">Apply Filters</button>
            <a href="<?= e(url('inventory/transfers')) ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="surface-card card-panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-truck me-1"></i>Transfer Register</p>
            <h3 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Branch movement history</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th><i class="bi bi-hash me-1"></i>Reference</th>
                <th><i class="bi bi-geo-alt me-1"></i>Route</th>
                <th><i class="bi bi-arrow-right-left me-1"></i>Direction</th>
                <th><i class="bi bi-info-circle me-1"></i>Status</th>
                <th><i class="bi bi-list-ol me-1"></i>Lines</th>
                <th><i class="bi bi-box-seam me-1"></i>Units</th>
                <th><i class="bi bi-calendar-event me-1"></i>Dispatch Date</th>
                <th><i class="bi bi-person me-1"></i>Created By</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($transfers as $transfer): ?>
                <?php
                $direction = (int) $transfer['source_branch_id'] === (int) $currentBranchId ? 'Outgoing' : 'Incoming';
                $canEdit = (string) $transfer['status'] === 'draft'
                    && ($canManageAllBranches || (int) $transfer['source_branch_id'] === (int) $currentBranchId);
                ?>
                <tr>
                    <td>
                        <a href="<?= e(url('inventory/transfers/show?id=' . $transfer['id'])) ?>" class="fw-semibold text-decoration-none">
                            <?= e($transfer['reference_number']) ?>
                        </a>
                        <div class="small text-muted"><?= e((string) $transfer['created_at']) ?></div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($transfer['source_branch_name']) ?></div>
                        <div class="small text-muted">to <?= e($transfer['destination_branch_name']) ?></div>
                    </td>
                    <td><span class="badge-soft"><?= e($direction) ?></span></td>
                    <td><span class="badge-soft text-capitalize"><?= e(str_replace('_', ' ', $transfer['status'])) ?></span></td>
                    <td><?= e((string) $transfer['item_count']) ?></td>
                    <td><?= e(number_format((float) $transfer['total_units'], 2)) ?></td>
                    <td><?= e((string) ($transfer['transfer_date'] ?: 'Not dispatched')) ?></td>
                    <td><?= e($transfer['created_by_name']) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                            <a href="<?= e(url('inventory/transfers/show?id=' . $transfer['id'])) ?>" class="btn btn-sm btn-outline-primary">Open</a>
                            <?php if ($canEdit): ?>
                                <a href="<?= e(url('inventory/transfers/edit?id=' . $transfer['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-modal data-title="Edit Transfer" data-refresh-target='[data-refresh-region="stock-transfer-register"]'><i class="bi bi-pencil-square"></i></a>
                            <?php endif; ?>
                            <form action="<?= e(url('inventory/transfers/duplicate')) ?>" method="post" class="d-inline" data-loading-form>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $transfer['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" data-confirm-action data-confirm-title="Duplicate this transfer?" data-confirm-text="A new draft transfer will be created with the same route and items." data-confirm-button="Duplicate Transfer"><i class="bi bi-copy"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
