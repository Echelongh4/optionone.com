<?php
$returnedItems = $return['items'] ?? [];
$creditTransactions = $return['credit_transactions'] ?? [];
$totalReturnedQuantity = array_reduce(
    $returnedItems,
    static fn (float $carry, array $item): float => $carry + (float) ($item['quantity'] ?? 0),
    0.0
);
?>
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Refund Total</span><span><?= e($return['return_number']) ?></span></div>
        <h3><?= e(format_currency($return['total_refund'])) ?></h3>
        <div class="text-muted">Amount refunded for this return.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Items Returned</span><span>Units</span></div>
        <h3><?= e(number_format($totalReturnedQuantity, 2)) ?></h3>
        <div class="text-muted"><?= e((string) count($returnedItems)) ?> lines recorded.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Linked Sale</span><span>Status</span></div>
        <h3><?= e($return['sale_number']) ?></h3>
        <div class="text-muted text-capitalize"><?= e(str_replace('_', ' ', (string) ($return['sale_status'] ?? 'completed'))) ?></div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Customer</span><span>Processed By</span></div>
        <h3><?= e($return['customer_name']) ?></h3>
        <div class="text-muted"><?= e($return['processed_by_name']) ?></div>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="eyebrow mb-1">Return Items</p>
                <h3 class="mb-0">Lines</h3>
            </div>
            <a href="<?= e(url('sales/show?id=' . $return['sale_id'])) ?>" class="btn btn-outline-primary">Open Sale</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle data-table">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Sold Qty</th>
                    <th>Returned</th>
                    <th>Price</th>
                    <th>Tax</th>
                    <th>Total</th>
                    <th>Reason</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($returnedItems as $item): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                            <div class="small text-muted"><?= e($item['sku']) ?> | <?= e($item['barcode']) ?></div>
                        </td>
                        <td><?= e((string) $item['sold_quantity']) ?></td>
                        <td><?= e((string) $item['quantity']) ?></td>
                        <td><?= e(format_currency($item['unit_price'])) ?></td>
                        <td><?= e(format_currency($item['tax_total'])) ?></td>
                        <td><?= e(format_currency($item['line_total'])) ?></td>
                        <td><?= e($item['reason'] ?: 'No reason provided') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Return Summary</p>
        <h3 class="mb-3">Details</h3>
        <div class="summary-row"><span>Return Number</span><strong><?= e($return['return_number']) ?></strong></div>
        <div class="summary-row"><span>Status</span><strong class="text-capitalize"><?= e($return['status']) ?></strong></div>
        <div class="summary-row"><span>Sale</span><strong><?= e($return['sale_number']) ?></strong></div>
        <div class="summary-row"><span>Branch</span><strong><?= e($return['branch_name'] ?? 'Branch') ?></strong></div>
        <div class="summary-row"><span>Processed By</span><strong><?= e($return['processed_by_name']) ?></strong></div>
        <div class="summary-row"><span>Approved By</span><strong><?= e($return['approved_by_name'] ?? 'Auto-approved') ?></strong></div>
        <div class="summary-row"><span>Created</span><strong><?= e((string) $return['created_at']) ?></strong></div>
        <div class="summary-row"><span>Subtotal</span><strong><?= e(format_currency($return['subtotal'])) ?></strong></div>
        <div class="summary-row"><span>Tax</span><strong><?= e(format_currency($return['tax_total'])) ?></strong></div>
        <div class="summary-row summary-row--total"><span>Refund</span><strong><?= e(format_currency($return['total_refund'])) ?></strong></div>
        <hr>
        <div class="small text-muted"><?= e($return['reason'] ?: 'No overall reason was captured.') ?></div>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Customer</p>
        <h3 class="mb-3">Linked Customer</h3>
        <div class="summary-row"><span>Name</span><strong><?= e($return['customer_name']) ?></strong></div>
        <div class="summary-row"><span>Email</span><strong><?= e($return['customer_email'] ?: 'Not provided') ?></strong></div>
        <div class="summary-row"><span>Phone</span><strong><?= e($return['customer_phone'] ?: 'Not provided') ?></strong></div>
    </section>

    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Credit Impact</p>
        <h3 class="mb-3">Ledger</h3>
        <?php if ($creditTransactions !== []): ?>
            <?php foreach ($creditTransactions as $entry): ?>
                <?php $amount = (float) ($entry['amount'] ?? 0); ?>
                <div class="border-bottom pb-2 mb-2">
                    <div class="fw-semibold text-capitalize"><?= e(str_replace('_', ' ', (string) $entry['transaction_type'])) ?> <?= e(($amount > 0 ? '+' : '') . format_currency($amount)) ?></div>
                    <div class="small text-muted">Balance <?= e(format_currency($entry['balance_after'])) ?><?php if (!empty($entry['user_name'])): ?> | <?= e($entry['user_name']) ?><?php endif; ?></div>
                    <div class="small text-muted"><?= e($entry['notes'] ?: 'Credit ledger updated for this return.') ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-muted">No customer credit impact recorded for this return.</div>
        <?php endif; ?>
    </section>
</div>
