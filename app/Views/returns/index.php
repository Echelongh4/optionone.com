<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Returns</span><span>Processed</span></div>
        <h3><?= e((string) $summary['total_returns']) ?></h3>
        <div class="text-muted">Completed return records.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Refunded</span><span>Total</span></div>
        <h3><?= e(format_currency($summary['total_refund'])) ?></h3>
        <div class="text-muted">Refund value in the current result set.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Items</span><span>Returned</span></div>
        <h3><?= e(number_format((float) $summary['items_returned'], 2)) ?></h3>
        <div class="text-muted">Units recorded across return lines.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Sales</span><span>Affected</span></div>
        <h3><?= e((string) $summary['linked_sales']) ?></h3>
        <div class="text-muted"><?= e((string) $summary['customers_impacted']) ?> customers impacted.</div>
    </section>
</div>

<div class="surface-card card-panel mb-4">
    <form method="get" action="<?= e(url('returns')) ?>" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" value="<?= e($filters['search']) ?>" placeholder="Return, sale, customer, phone">
        </div>
        <div class="col-md-2">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <?php foreach (['completed', 'pending', 'rejected'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= $filters['status'] === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Processed By</label>
            <select name="processed_by" class="form-select">
                <option value="">All users</option>
                <?php foreach ($processedByUsers as $user): ?>
                    <option value="<?= e((string) $user['id']) ?>" <?= $filters['processed_by'] === (string) $user['id'] ? 'selected' : '' ?>><?= e($user['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="<?= e($filters['date_from']) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="<?= e($filters['date_to']) ?>">
        </div>
        <div class="col-12 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="<?= e(url('returns')) ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div class="surface-card card-panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="eyebrow mb-1"><i class="bi bi-arrow-counterclockwise me-1"></i>Returns</p>
            <h3 class="mb-0"><i class="bi bi-receipt-cutoff me-2"></i>Return Register</h3>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= e(url('sales')) ?>" class="btn btn-outline-secondary"><i class="bi bi-cart me-1"></i>Sales</a>
            <a href="<?= e(url('pos')) ?>" class="btn btn-outline-primary"><i class="bi bi-cash-stack me-1"></i>Open POS</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th>Return</th>
                <th>Sale</th>
                <th>Customer</th>
                <th>Processed By</th>
                <th>Status</th>
                <th>Items</th>
                <th>Refund</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($returns as $returnRow): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($returnRow['return_number']) ?></div>
                        <div class="small text-muted"><?= e((string) $returnRow['created_at']) ?></div>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= e($returnRow['sale_number']) ?></div>
                        <div class="small text-muted"><?= e($returnRow['branch_name'] ?? 'Branch') ?></div>
                    </td>
                    <td>
                        <div><?= e($returnRow['customer_name']) ?></div>
                        <div class="small text-muted"><?= e($returnRow['reason'] ?: 'No reason provided') ?></div>
                    </td>
                    <td><?= e($returnRow['processed_by_name']) ?></td>
                    <td><span class="badge-soft text-capitalize"><?= e($returnRow['status']) ?></span></td>
                    <td><?= e(number_format((float) $returnRow['items_returned'], 2)) ?></td>
                    <td><?= e(format_currency($returnRow['total_refund'])) ?></td>
                    <td class="text-end">
                        <div class="d-none d-md-flex justify-content-end gap-2">
                            <a href="<?= e(url('returns/show?id=' . $returnRow['id'])) ?>" class="btn btn-sm btn-outline-secondary">Detail</a>
                            <a href="<?= e(url('sales/show?id=' . $returnRow['sale_id'])) ?>" class="btn btn-sm btn-outline-primary">Sale</a>
                        </div>
                        <div class="d-md-none dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Actions</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?= e(url('returns/show?id=' . $returnRow['id'])) ?>">Detail</a></li>
                                <li><a class="dropdown-item" href="<?= e(url('sales/show?id=' . $returnRow['sale_id'])) ?>">Sale</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
