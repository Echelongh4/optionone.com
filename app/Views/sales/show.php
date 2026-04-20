<?php
$loyaltyEntries = $sale['loyalty_entries'] ?? [];
$creditTransactions = $sale['credit_transactions'] ?? [];
$loyaltyDiscount = (float) ($sale['loyalty_discount_total'] ?? 0);
$redeemedPoints = (int) ($sale['loyalty_points_redeemed'] ?? 0);
$earnedPoints = (int) ($sale['loyalty_points_earned'] ?? 0);
$creditAmount = (float) ($sale['credit_amount'] ?? 0);
$cashTendered = (float) ($sale['cash_tendered'] ?? 0);
$collectedAmount = (float) ($sale['collected_amount'] ?? $sale['amount_paid']);
$outstandingCreditAmount = (float) ($sale['outstanding_credit_amount'] ?? $creditAmount);
$activeVoidRequest = $activeVoidRequest ?? null;
$voidRequestHistory = $voidRequestHistory ?? [];
$canReviewVoidRequest = can_permission('approve_voids') && $activeVoidRequest !== null && (int) ($activeVoidRequest['requested_by'] ?? 0) !== (int) (current_user()['id'] ?? 0);
$loyaltySummary = $redeemedPoints > 0 || $earnedPoints > 0
    ? trim(($redeemedPoints > 0 ? '-' . $redeemedPoints . ' pts redeemed' : '') . ($redeemedPoints > 0 && $earnedPoints > 0 ? ' / ' : '') . ($earnedPoints > 0 ? '+' . $earnedPoints . ' pts earned' : ''))
    : 'No loyalty activity';
$saleCreatedLabel = !empty($sale['created_at']) ? date('M d, Y H:i', strtotime((string) $sale['created_at'])) : 'Unknown';
$saleCompletedLabel = !empty($sale['completed_at']) ? date('M d, Y H:i', strtotime((string) $sale['completed_at'])) : 'Pending';
$saleStatusLabel = $activeVoidRequest !== null
    ? 'Void pending'
    : ucwords(str_replace('_', ' ', (string) ($sale['status'] ?? 'unknown')));
$saleStatusClass = $activeVoidRequest !== null
    ? 'status-pill status-pill--warning'
    : match ((string) ($sale['status'] ?? '')) {
        'completed' => 'status-pill status-pill--success',
        'partial_return' => 'status-pill status-pill--warning',
        'refunded', 'voided' => 'status-pill status-pill--danger',
        default => 'status-pill status-pill--info',
    };
?>
<div data-refresh-region="sale-detail" data-sale-detail-root>
<section class="surface-card card-panel sale-detail-hero">
    <div class="sale-detail-hero__copy">
        <p class="eyebrow mb-1"><i class="bi bi-receipt-cutoff me-1"></i>Sale Workspace</p>
        <h3 class="mb-1"><?= e((string) ($sale['sale_number'] ?? 'Sale detail')) ?></h3>
        <div class="sale-detail-hero__meta">
            <span class="<?= e($saleStatusClass) ?>"><?= e($saleStatusLabel) ?></span>
            <span class="badge-soft"><i class="bi bi-diagram-3 me-1"></i><?= e((string) ($sale['branch_name'] ?? 'Main Branch')) ?></span>
            <span class="badge-soft"><i class="bi bi-person-badge me-1"></i><?= e((string) ($sale['cashier_name'] ?? 'Cashier')) ?></span>
            <span class="badge-soft"><i class="bi bi-clock me-1"></i>Created <?= e($saleCreatedLabel) ?></span>
            <span class="badge-soft"><i class="bi bi-check2-circle me-1"></i>Completed <?= e($saleCompletedLabel) ?></span>
        </div>
    </div>
    <div class="sale-detail-hero__actions">
        <a href="<?= e(url('sales')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Sales</a>
        <a href="<?= e(url('pos/receipt?id=' . $sale['id'])) ?>" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i>Receipt</a>
        <a href="<?= e(url('pos')) ?>" class="btn btn-primary"><i class="bi bi-cart-check me-1"></i>Open POS</a>
    </div>
</section>

<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Grand Total</span><span><?= e($sale['sale_number']) ?></span></div>
        <h3><?= e(format_currency($sale['grand_total'])) ?></h3>
        <div class="text-muted">Original sale total.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Collected Now</span><span>Status</span></div>
        <h3><?= e(format_currency($collectedAmount)) ?></h3>
        <div class="text-muted text-capitalize"><?= e(str_replace('_', ' ', $sale['status'])) ?></div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>On Credit</span><span>Account</span></div>
        <h3><?= e(format_currency($creditAmount)) ?></h3>
        <div class="text-muted">Outstanding allocation still on the customer ledger: <?= e(format_currency($outstandingCreditAmount)) ?></div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Loyalty Impact</span><span>Customer</span></div>
        <h3><?= e($loyaltyDiscount > 0 ? format_currency($loyaltyDiscount) : format_currency(0)) ?></h3>
        <div class="text-muted"><?= e($loyaltySummary) ?></div>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="eyebrow mb-1">Items</p>
                <h3 class="mb-0">Line Items</h3>
            </div>
            <a href="<?= e(url('pos/receipt?id=' . $sale['id'])) ?>" class="btn btn-outline-primary">Receipt</a>
        </div>
        <div class="sale-line-toolbar">
            <div class="sale-line-toolbar__search">
                <i class="bi bi-search"></i>
                <input id="saleItemSearch" type="search" class="form-control" placeholder="Find an item by name or SKU inside this sale">
            </div>
            <div class="sale-line-toolbar__filters">
                <button type="button" class="pos-filter-pill is-active" data-sale-line-filter="all">All lines</button>
                <button type="button" class="pos-filter-pill" data-sale-line-filter="returnable">Returnable only</button>
                <button type="button" class="pos-filter-pill" data-sale-line-filter="returned">Returned only</button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle data-table" data-sale-line-table data-table-search="false" data-table-buttons="false" data-table-paging="false" data-table-info="false" data-table-responsive="false">
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Returned</th>
                    <th>Price</th>
                    <th>Tax</th>
                    <th>Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sale['items'] as $item): ?>
                    <?php $returnableQty = max(0, (float) $item['quantity'] - (float) $item['returned_quantity']); ?>
                    <tr data-sale-line-row data-line-returnable="<?= $returnableQty > 0 ? '1' : '0' ?>" data-line-returned="<?= (float) $item['returned_quantity'] > 0 ? '1' : '0' ?>" data-line-search="<?= e(strtolower(trim((string) ($item['product_name'] ?? '')) . ' ' . trim((string) ($item['sku'] ?? '')))) ?>">
                        <td>
                            <div class="fw-semibold"><?= e($item['product_name']) ?></div>
                            <div class="small text-muted"><?= e($item['sku']) ?></div>
                        </td>
                        <td><?= e((string) $item['quantity']) ?></td>
                        <td><?= e((string) $item['returned_quantity']) ?></td>
                        <td><?= e(format_currency($item['unit_price'])) ?></td>
                        <td><?= e(format_currency($item['tax_total'])) ?></td>
                        <td><?= e(format_currency($item['line_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Settlement</p>
        <h3 class="mb-3">Payments, Credit, and Loyalty</h3>
        <div class="summary-row"><span>Subtotal</span><strong><?= e(format_currency($sale['subtotal'])) ?></strong></div>
        <div class="summary-row"><span>Item Discounts</span><strong><?= e(format_currency($sale['item_discount_total'])) ?></strong></div>
        <div class="summary-row"><span>Order Discount</span><strong><?= e(format_currency($sale['order_discount_total'])) ?></strong></div>
        <?php if ($loyaltyDiscount > 0): ?>
            <div class="summary-row"><span>Loyalty Discount</span><strong><?= e(format_currency($loyaltyDiscount)) ?></strong></div>
        <?php endif; ?>
        <div class="summary-row"><span>Tax</span><strong><?= e(format_currency($sale['tax_total'])) ?></strong></div>
        <div class="summary-row summary-row--total"><span>Total</span><strong><?= e(format_currency($sale['grand_total'])) ?></strong></div>
        <div class="summary-row"><span>Collected Now</span><strong><?= e(format_currency($collectedAmount)) ?></strong></div>
        <?php if ($cashTendered > 0): ?>
            <div class="summary-row"><span>Cash Tendered</span><strong><?= e(format_currency($cashTendered)) ?></strong></div>
        <?php endif; ?>
        <div class="summary-row"><span>Assigned to Credit</span><strong><?= e(format_currency($creditAmount)) ?></strong></div>
        <div class="summary-row"><span>Change Due</span><strong><?= e(format_currency($sale['change_due'])) ?></strong></div>
        <hr>
        <?php foreach ($sale['payments'] as $payment): ?>
            <?php $paymentSummary = pos_payment_detail_summary($payment); ?>
            <div class="border-bottom pb-2 mb-2">
                <div class="fw-semibold"><?= e(pos_payment_method_label((string) ($payment['payment_method'] ?? ''))) ?></div>
                <div class="small text-muted"><?= e(format_currency($payment['amount'])) ?></div>
                <?php if ($paymentSummary !== ''): ?>
                    <div class="small text-muted mt-1"><?= e($paymentSummary) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if ($sale['payments'] === []): ?>
            <div class="text-muted mb-3">No payment rows recorded.</div>
        <?php endif; ?>
        <hr>
        <p class="eyebrow mb-1">Loyalty Timeline</p>
        <?php if ($loyaltyEntries !== []): ?>
            <?php foreach ($loyaltyEntries as $entry): ?>
                <?php $points = (int) ($entry['points'] ?? 0); ?>
                <div class="border-bottom pb-2 mb-2">
                    <div class="fw-semibold text-capitalize"><?= e(str_replace('_', ' ', (string) $entry['transaction_type'])) ?></div>
                    <div class="small text-muted">
                        <?= e(($points > 0 ? '+' : '') . (string) $points) ?> pts
                        <?php if (($entry['balance_after'] ?? null) !== null): ?>
                            | Balance <?= e((string) $entry['balance_after']) ?> pts
                        <?php endif; ?>
                    </div>
                    <div class="small text-muted"><?= e($entry['notes'] ?: 'Loyalty activity recorded for this sale.') ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-muted">No loyalty activity recorded for this sale.</div>
        <?php endif; ?>
        <hr>
        <p class="eyebrow mb-1">Credit Ledger</p>
        <?php if ($creditTransactions !== []): ?>
            <?php foreach ($creditTransactions as $entry): ?>
                <?php $amount = (float) ($entry['amount'] ?? 0); ?>
                <div class="border-bottom pb-2 mb-2">
                    <div class="fw-semibold text-capitalize"><?= e(str_replace('_', ' ', (string) $entry['transaction_type'])) ?> <?= e(($amount > 0 ? '+' : '') . format_currency($amount)) ?></div>
                    <div class="small text-muted">Balance <?= e(format_currency($entry['balance_after'])) ?><?php if (!empty($entry['user_name'])): ?> | <?= e($entry['user_name']) ?><?php endif; ?></div>
                    <div class="small text-muted"><?= e($entry['notes'] ?: 'Credit activity recorded for this sale.') ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-muted">No credit activity recorded for this sale.</div>
        <?php endif; ?>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Returns</p>
        <h3 class="mb-3">Process Partial or Full Returns</h3>
        <?php if (in_array($sale['status'], ['completed', 'partial_return'], true) && $activeVoidRequest === null): ?>
            <form action="<?= e(url('sales/return')) ?>" method="post" class="d-grid gap-3" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="sale-detail"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="sale_id" value="<?= e((string) $sale['id']) ?>">
                <div>
                    <label class="form-label">Overall return reason</label>
                    <input type="text" name="reason" class="form-control" placeholder="Customer changed mind, defect, wrong item">
                </div>
                <div class="table-responsive">
                    <table class="table align-middle data-table" data-table-search="false" data-table-buttons="false" data-table-paging="false" data-table-info="false" data-table-ordering="false" data-table-responsive="false">
                        <thead>
                        <tr>
                            <th>Item</th>
                            <th>Max Returnable</th>
                            <th>Return Qty</th>
                            <th>Line Reason</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sale['items'] as $item): ?>
                            <?php $maxReturnable = (float) $item['quantity'] - (float) $item['returned_quantity']; ?>
                            <tr data-sale-line-row data-line-returnable="<?= $maxReturnable > 0 ? '1' : '0' ?>" data-line-returned="<?= (float) $item['returned_quantity'] > 0 ? '1' : '0' ?>" data-line-search="<?= e(strtolower(trim((string) ($item['product_name'] ?? '')) . ' ' . trim((string) ($item['sku'] ?? '')))) ?>">
                                <td><?= e($item['product_name']) ?></td>
                                <td><?= e((string) $maxReturnable) ?></td>
                                <td><input type="number" min="0" max="<?= e((string) $maxReturnable) ?>" step="0.01" name="return_quantity[<?= e((string) $item['id']) ?>]" class="form-control" value="0"></td>
                                <td><input type="text" name="return_reason[<?= e((string) $item['id']) ?>]" class="form-control" placeholder="Optional line note"></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Process Return</button>
                </div>
            </form>
        <?php else: ?>
            <div class="text-muted"><?= $activeVoidRequest !== null ? 'Returns are disabled while a void request is pending.' : 'Returns are unavailable for this sale status.' ?></div>
        <?php endif; ?>
    </section>

    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Void Control</p>
        <h3 class="mb-3">Approval Workflow</h3>

        <?php if ($sale['status'] === 'voided'): ?>
            <div class="alert alert-warning rounded-4 mb-3">
                <strong>Sale already voided.</strong>
                <?= e($sale['void_reason'] ?: 'No void reason was captured.') ?>
            </div>
        <?php elseif ($activeVoidRequest !== null): ?>
            <div class="list-card mb-3">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <div class="fw-semibold">Pending void approval</div>
                        <div class="small text-muted">Requested by <?= e($activeVoidRequest['requested_by_name']) ?> on <?= e((string) $activeVoidRequest['created_at']) ?></div>
                    </div>
                    <span class="badge-soft text-capitalize">Pending</span>
                </div>
                <div class="small text-muted mt-3"><?= e($activeVoidRequest['reason']) ?></div>
            </div>

            <?php if ($canReviewVoidRequest): ?>
                <form action="<?= e(url('sales/void-review')) ?>" method="post" class="d-grid gap-3" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="sale-detail"]'>
                    <?= csrf_field() ?>
                    <input type="hidden" name="sale_id" value="<?= e((string) $sale['id']) ?>">
                    <input type="hidden" name="request_id" value="<?= e((string) $activeVoidRequest['id']) ?>">
                    <div>
                        <label class="form-label">Review notes</label>
                        <textarea name="review_notes" rows="3" class="form-control" placeholder="Optional supervisor note for approval or rejection"></textarea>
                    </div>
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <button type="submit" name="decision" value="rejected" class="btn btn-outline-secondary" data-confirm-action data-confirm-title="Reject void request?" data-confirm-text="This sale will remain active and the requester will be notified." data-confirm-button="Reject Request">Reject Request</button>
                        <button type="submit" name="decision" value="approved" class="btn btn-outline-danger" data-confirm-action data-confirm-title="Approve and void sale?" data-confirm-text="This will void the sale, restore stock, reverse loyalty, and clear any related customer credit." data-confirm-button="Approve Void">Approve and Void</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-muted">This request is waiting for supervisor review.</div>
            <?php endif; ?>
        <?php elseif ($sale['status'] === 'completed'): ?>
            <form action="<?= e(url('sales/void-request')) ?>" method="post" class="d-grid gap-3" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="sale-detail"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="sale_id" value="<?= e((string) $sale['id']) ?>">
                <div>
                    <label class="form-label">Reason for void request</label>
                    <textarea name="void_reason" rows="4" class="form-control" required placeholder="Explain why this transaction should be voided"></textarea>
                </div>
                <button type="submit" class="btn btn-outline-danger" data-confirm-action data-confirm-title="Submit void approval request?" data-confirm-text="This will send the sale for supervisor review instead of voiding it immediately." data-confirm-button="Submit Request">Request Void Approval</button>
            </form>
        <?php else: ?>
            <div class="text-muted">Void requests are only available for completed sales.</div>
        <?php endif; ?>

        <hr>
        <p class="eyebrow mb-1">Void Request History</p>
        <?php if ($voidRequestHistory !== []): ?>
            <?php foreach ($voidRequestHistory as $requestEntry): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <div class="fw-semibold text-capitalize"><?= e($requestEntry['status']) ?> request</div>
                            <div class="small text-muted">Requested by <?= e($requestEntry['requested_by_name']) ?> on <?= e((string) $requestEntry['created_at']) ?></div>
                        </div>
                        <span class="badge-soft text-capitalize"><?= e($requestEntry['status']) ?></span>
                    </div>
                    <div class="small text-muted mt-2">Reason: <?= e($requestEntry['reason']) ?></div>
                    <?php if (!empty($requestEntry['reviewed_by_name'])): ?>
                        <div class="small text-muted mt-2">Reviewed by <?= e($requestEntry['reviewed_by_name']) ?> on <?= e((string) ($requestEntry['reviewed_at'] ?? '')) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($requestEntry['review_notes'])): ?>
                        <div class="small text-muted mt-1">Review notes: <?= e($requestEntry['review_notes']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-muted">No void approval requests recorded for this sale.</div>
        <?php endif; ?>

        <hr>
        <p class="eyebrow mb-1">Return Log</p>
        <?php foreach ($sale['returns'] as $return): ?>
            <div class="border-bottom pb-2 mb-2">
                <div class="fw-semibold">
                    <a href="<?= e(url('returns/show?id=' . $return['id'])) ?>" class="text-decoration-none"><?= e($return['return_number']) ?></a>
                    | <?= e(format_currency($return['total_refund'])) ?>
                </div>
                <div class="small text-muted"><?= e($return['processed_by_name']) ?> | <?= e((string) $return['created_at']) ?></div>
                <div class="small text-muted"><?= e($return['reason'] ?: 'No reason provided') ?></div>
            </div>
        <?php endforeach; ?>
        <?php if ($sale['returns'] === []): ?>
            <div class="text-muted">No returns recorded for this sale.</div>
        <?php endif; ?>
    </section>
</div>
</div>
