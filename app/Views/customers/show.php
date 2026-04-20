<?php
$creditHistory = $creditHistory ?? [];
$totalOrders = count($purchaseHistory ?? []);
?>
<div data-refresh-region="customer-detail">
<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Loyalty Balance</span><span>Points</span></div>
        <h3><?= e((string) $customer['loyalty_balance']) ?></h3>
        <div class="text-muted">Available reward points.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Credit Balance</span><span>Open Account</span></div>
        <h3><?= e(format_currency($customer['credit_balance'])) ?></h3>
        <div class="text-muted">Current outstanding customer debt.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Special Pricing</span><span>Rule</span></div>
        <h3><?= e(ucfirst($customer['special_pricing_type'])) ?></h3>
        <div class="text-muted">Value: <?= e((string) $customer['special_pricing_value']) ?></div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Orders</span><span>History</span></div>
        <h3><?= e((string) $totalOrders) ?></h3>
        <div class="text-muted">Completed and historical transactions on this profile.</div>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="eyebrow mb-1">Profile</p>
                <h3 class="mb-0"><?= e($customer['full_name']) ?></h3>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= e(url('customers')) ?>" class="btn btn-outline-secondary">Back</a>
                <a href="<?= e(url('customers/edit?id=' . $customer['id'])) ?>" class="btn btn-outline-primary" data-modal data-title="Edit Customer" data-refresh-target='[data-refresh-region="customer-detail"]'>Edit Profile</a>
                <?php if ((float) $customer['credit_balance'] <= 0): ?>
                    <form action="<?= e(url('customers/delete')) ?>" method="post" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= e((string) $customer['id']) ?>">
                        <button type="submit" class="btn btn-outline-danger" data-confirm-action data-confirm-title="Archive this customer?" data-confirm-text="The profile will be removed from the active customer register while keeping historical sales linked." data-confirm-button="Archive Customer">Archive</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-6"><strong>Phone:</strong><br><?= e($customer['phone'] ?: 'N/A') ?></div>
            <div class="col-md-6"><strong>Email:</strong><br><?= e($customer['email'] ?: 'N/A') ?></div>
            <div class="col-md-6"><strong>Group:</strong><br><?= e($customer['customer_group_name'] ?? 'Standard') ?></div>
            <div class="col-md-6"><strong>Address:</strong><br><?= e($customer['address'] ?: 'N/A') ?></div>
        </div>
    </section>
    <section class="surface-card card-panel">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <p class="eyebrow mb-1">Open Account</p>
                <h3 class="mb-0">Record Customer Payment</h3>
            </div>
            <span class="badge-soft">Outstanding <?= e(format_currency($customer['credit_balance'])) ?></span>
        </div>
        <form action="<?= e(url('customers/credit/payment')) ?>" method="post" class="d-grid gap-3" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="customer-detail"]'>
            <?= csrf_field() ?>
            <input type="hidden" name="customer_id" value="<?= e((string) $customer['id']) ?>">
            <div>
                <label class="form-label">Payment Amount</label>
                <input type="number" name="amount" min="0.01" max="<?= e((string) $customer['credit_balance']) ?>" step="0.01" class="form-control" placeholder="0.00" required>
            </div>
            <div>
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control" placeholder="Cash received at counter, mobile transfer, adjustment note">
            </div>
            <button type="submit" class="btn btn-primary" <?= (float) $customer['credit_balance'] <= 0 ? 'disabled' : '' ?>>Apply Payment</button>
            <?php if ((float) $customer['credit_balance'] <= 0): ?>
                <div class="text-muted small">This customer has no outstanding balance to settle.</div>
            <?php endif; ?>
        </form>
    </section>
</div>

<div class="content-grid">
    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Loyalty Timeline</p>
        <h3 class="mb-3">Recent Point Activity</h3>
        <?php foreach ($loyaltyHistory as $entry): ?>
            <div class="border-bottom pb-2 mb-2">
                <div class="fw-semibold"><?= e(ucfirst($entry['transaction_type'])) ?> <?= e((string) $entry['points']) ?> pts</div>
                <div class="small text-muted">Balance <?= e((string) $entry['balance_after']) ?> | <?= e((string) $entry['created_at']) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if ($loyaltyHistory === []): ?>
            <div class="text-muted">No loyalty activity yet.</div>
        <?php endif; ?>
    </section>
    <section class="surface-card card-panel">
        <p class="eyebrow mb-1">Credit Ledger</p>
        <h3 class="mb-3">Open Account Activity</h3>
        <?php foreach ($creditHistory as $entry): ?>
            <?php $amount = (float) ($entry['amount'] ?? 0); ?>
            <div class="border-bottom pb-2 mb-2">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <div class="fw-semibold text-capitalize"><?= e(str_replace('_', ' ', (string) $entry['transaction_type'])) ?></div>
                    <div class="fw-semibold <?= $amount < 0 ? 'text-success' : 'text-danger' ?>"><?= e(($amount > 0 ? '+' : '') . format_currency($amount)) ?></div>
                </div>
                <div class="small text-muted">
                    Balance <?= e(format_currency($entry['balance_after'])) ?>
                    <?php if (!empty($entry['sale_number'])): ?> | Sale <?= e($entry['sale_number']) ?><?php endif; ?>
                    <?php if (!empty($entry['return_number'])): ?> | Return <?= e($entry['return_number']) ?><?php endif; ?>
                </div>
                <div class="small text-muted">
                    <?= e($entry['notes'] ?: 'Customer credit activity recorded.') ?>
                    <?php if (!empty($entry['user_name'])): ?> | <?= e($entry['user_name']) ?><?php endif; ?>
                    | <?= e((string) $entry['created_at']) ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($creditHistory === []): ?>
            <div class="text-muted">No credit activity has been recorded yet.</div>
        <?php endif; ?>
    </section>
</div>

<div class="surface-card card-panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <p class="eyebrow mb-1">Purchase History</p>
            <h3 class="mb-0">Customer Orders</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead>
            <tr>
                <th>Sale</th>
                <th>Status</th>
                <th>Cashier</th>
                <th>Total</th>
                <th>Paid</th>
                <th>On Credit</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($purchaseHistory as $sale): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($sale['sale_number']) ?></div>
                        <div class="small text-muted"><?= e((string) $sale['completed_at']) ?></div>
                    </td>
                    <td><span class="badge-soft text-capitalize"><?= e($sale['status']) ?></span></td>
                    <td><?= e($sale['cashier_name']) ?></td>
                    <td><?= e(format_currency($sale['grand_total'])) ?></td>
                    <td><?= e(format_currency($sale['amount_paid'])) ?></td>
                    <td><?= e(format_currency($sale['credit_amount'] ?? 0)) ?></td>
                    <td class="text-end"><a href="<?= e(url('sales/show?id=' . $sale['id'])) ?>" class="btn btn-sm btn-outline-secondary">Open Sale</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</div>
