<div data-refresh-region="expense-detail">
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Amount</span><span>Recorded</span></div>
        <h3><?= e(format_currency($expense['amount'])) ?></h3>
        <div class="text-muted">Expense value recorded for this entry.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Status</span><span>Workflow</span></div>
        <h3><?= e(ucfirst((string) $expense['status'])) ?></h3>
        <div class="text-muted">Current approval state for this expense.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Date</span><span>Booked</span></div>
        <h3><?= e((string) $expense['expense_date']) ?></h3>
        <div class="text-muted">Expense posting date.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Category</span><span>Bucket</span></div>
        <h3><?= e($expense['category_name']) ?></h3>
        <div class="text-muted">Assigned spending category.</div>
    </section>
</div>

<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Expense Detail</p>
            <h3><i class="bi bi-receipt me-2"></i>Expense Record</h3>
        </div>
        <div class="workspace-panel__actions">
            <a href="<?= e(url('expenses')) ?>" class="btn btn-outline-secondary">Back</a>
            <a href="<?= e(url('expenses/edit?id=' . $expense['id'])) ?>" class="btn btn-primary" data-modal data-title="Edit Expense" data-refresh-target='[data-refresh-region="expense-detail"]'>Edit Expense</a>
            <form action="<?= e(url('expenses/delete')) ?>" method="post" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $expense['id']) ?>">
                <button type="submit" class="btn btn-outline-danger" data-confirm-action data-confirm-title="Archive this expense?" data-confirm-text="The expense will be removed from the active register while audit history stays intact." data-confirm-button="Archive Expense">Archive</button>
            </form>
        </div>
    </div>

    <div class="form-grid">
        <div class="record-card">
            <div class="record-card__header">
                <div class="workspace-panel__intro">
                    <h4>Expense Summary</h4>
                </div>
            </div>
            <div class="stack-grid">
                <div><div class="small text-muted">Description</div><div class="fw-semibold"><?= e($expense['description']) ?></div></div>
                <div><div class="small text-muted">Category</div><div class="fw-semibold"><?= e($expense['category_name']) ?></div></div>
                <div><div class="small text-muted">Logged By</div><div class="fw-semibold"><?= e($expense['created_by_name']) ?></div></div>
                <div><div class="small text-muted">Status</div><div class="fw-semibold text-capitalize"><?= e((string) $expense['status']) ?></div></div>
            </div>
        </div>
        <div class="record-card">
            <div class="record-card__header">
                <div class="workspace-panel__intro">
                    <h4>Receipt</h4>
                </div>
            </div>
            <?php if (!empty($expense['receipt_path'])): ?>
                <div class="media-preview mb-3">
                    <img src="<?= e(url((string) $expense['receipt_path'])) ?>" alt="Expense receipt" class="img-fluid">
                </div>
                <a href="<?= e(url((string) $expense['receipt_path'])) ?>" target="_blank" class="btn btn-outline-secondary">Open Receipt</a>
            <?php else: ?>
                <div class="empty-state">No receipt has been attached to this expense.</div>
            <?php endif; ?>
        </div>
    </div>
</section>
</div>
