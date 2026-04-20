<div data-refresh-region="stock-transfer-detail">
<div class="content-grid">
    <section class="surface-card card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <p class="eyebrow mb-1">Transfer Summary</p>
                <h3 class="mb-0"><?= e($transfer['reference_number']) ?></h3>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= e(url('inventory/transfers')) ?>" class="btn btn-outline-secondary">Back</a>
                <?php if ($canEdit): ?>
                    <a href="<?= e(url('inventory/transfers/edit?id=' . $transfer['id'])) ?>" class="btn btn-outline-secondary" data-modal data-title="Edit Transfer" data-refresh-target='[data-refresh-region="stock-transfer-detail"]'><i class="bi bi-pencil-square me-1"></i>Edit Draft</a>
                <?php endif; ?>
                <form action="<?= e(url('inventory/transfers/duplicate')) ?>" method="post" class="d-inline" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= e((string) $transfer['id']) ?>">
                    <button type="submit" class="btn btn-outline-secondary" data-confirm-action data-confirm-title="Duplicate this transfer?" data-confirm-text="A new draft transfer will be created with the same route and quantities." data-confirm-button="Duplicate Transfer"><i class="bi bi-copy me-1"></i>Duplicate</button>
                </form>
                <?php if ($canReceive): ?>
                    <form action="<?= e(url('inventory/transfers/receive')) ?>" method="post" class="d-inline" data-ajax="true" data-refresh-target='[data-refresh-region="stock-transfer-detail"]'>
                        <?= csrf_field() ?>
                        <input type="hidden" name="submission_key" value="<?= e((string) ($receiveSubmissionKey ?? '')) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $transfer['id']) ?>">
                        <button type="submit" class="btn btn-primary" data-confirm-action data-confirm-title="Receive this stock transfer?" data-confirm-text="Inventory will be added to the destination branch and this transfer will close." data-confirm-button="Receive Transfer">Receive Stock</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-grid">
            <div><div class="small text-muted">Status</div><div class="fw-semibold text-capitalize"><?= e(str_replace('_', ' ', $transfer['status'])) ?></div></div>
            <div><div class="small text-muted">Source Branch</div><div class="fw-semibold"><?= e($transfer['source_branch_name']) ?></div></div>
            <div><div class="small text-muted">Destination Branch</div><div class="fw-semibold"><?= e($transfer['destination_branch_name']) ?></div></div>
            <div><div class="small text-muted">Created By</div><div class="fw-semibold"><?= e($transfer['created_by_name']) ?></div></div>
            <div><div class="small text-muted">Created At</div><div class="fw-semibold"><?= e((string) $transfer['created_at']) ?></div></div>
            <div><div class="small text-muted">Dispatch Date</div><div class="fw-semibold"><?= e((string) ($transfer['transfer_date'] ?: 'Not dispatched')) ?></div></div>
        </div>
        <?php if (!empty($transfer['notes'])): ?>
            <hr>
            <div class="small text-muted">Notes</div>
            <div><?= nl2br(e($transfer['notes'])) ?></div>
        <?php endif; ?>
    </section>

    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Workflow</p>
        <h3 class="mb-3">Available actions</h3>
        <?php if ($canSend): ?>
            <form action="<?= e(url('inventory/transfers/status')) ?>" method="post" class="d-grid gap-3 mb-3" data-ajax="true" data-refresh-target='[data-refresh-region="stock-transfer-detail"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="submission_key" value="<?= e((string) ($workflowSubmissionKey ?? '')) ?>">
                <input type="hidden" name="id" value="<?= e((string) $transfer['id']) ?>">
                <input type="hidden" name="action" value="send">
                <button type="submit" class="btn btn-outline-primary" data-confirm-action data-confirm-title="Dispatch this stock transfer?" data-confirm-text="Stock will be deducted from the source branch immediately." data-confirm-button="Dispatch Transfer">Dispatch Transfer</button>
            </form>
        <?php endif; ?>

        <?php if ($canCancel): ?>
            <form action="<?= e(url('inventory/transfers/status')) ?>" method="post" class="d-grid gap-3" data-ajax="true" data-refresh-target='[data-refresh-region="stock-transfer-detail"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="submission_key" value="<?= e((string) ($workflowSubmissionKey ?? '')) ?>">
                <input type="hidden" name="id" value="<?= e((string) $transfer['id']) ?>">
                <input type="hidden" name="action" value="cancel">
                <button type="submit" class="btn btn-outline-danger" data-confirm-action data-confirm-title="Cancel this stock transfer?" data-confirm-text="Draft transfers will close immediately. In-transit transfers will return stock to the source branch." data-confirm-button="Cancel Transfer">Cancel Transfer</button>
            </form>
        <?php elseif (!$canReceive && !$canSend): ?>
            <div class="text-muted">This transfer is closed or no workflow action is available for your branch.</div>
        <?php endif; ?>
    </section>
</div>

<div class="surface-card card-panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="eyebrow mb-1">Transfer Items</p>
            <h3 class="mb-0">Current stock snapshot</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th>Product</th>
                <th>Requested Qty</th>
                <th>Source On Hand</th>
                <th>Destination On Hand</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($transfer['items'] as $item): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                        <div class="small text-muted"><?= e($item['sku']) ?></div>
                    </td>
                    <td><?= e(number_format((float) $item['quantity'], 2)) ?></td>
                    <td><?= e(number_format((float) $item['source_quantity_on_hand'], 2)) ?></td>
                    <td><?= e(number_format((float) $item['destination_quantity_on_hand'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="small text-muted mt-3">Source and destination balances reflect current stock, not historical balances at the time this transfer was created.</div>
</div>
</div>
