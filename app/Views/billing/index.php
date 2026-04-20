<?php
$company = is_array($company ?? null) ? $company : [];
$billingSettings = array_merge([
    'billing_contact_name' => '',
    'billing_contact_email' => '',
    'billing_contact_phone' => '',
    'billing_address' => '',
    'billing_tax_number' => '',
    'billing_notification_emails' => '',
    'billing_notes' => '',
], $billingSettings ?? []);
$paymentsReady = (bool) ($paymentsReady ?? false);
$paymentSchemaMessage = (string) ($paymentSchemaMessage ?? '');
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$platformSettings = array_merge([
    'support_email' => '',
    'payment_instructions' => '',
    'invoice_footer' => '',
], $platformSettings ?? []);
$plans = is_array($plans ?? null) ? $plans : [];
$subscription = is_array($subscription ?? null) ? $subscription : null;
$usage = array_merge([
    'branch_count' => 0,
    'active_branch_count' => 0,
    'user_count' => 0,
    'active_user_count' => 0,
    'product_count' => 0,
    'active_product_count' => 0,
    'monthly_sale_count' => 0,
    'monthly_revenue' => 0,
    'last_sale_at' => null,
], $usage ?? []);
$invoices = is_array($invoices ?? null) ? $invoices : [];
$alerts = is_array($alerts ?? null) ? $alerts : [];
$billingReady = (bool) ($billingReady ?? false);
$billingSchemaMessage = (string) ($billingSchemaMessage ?? '');

$subscriptionStatus = (string) ($subscription['status'] ?? 'unassigned');
$statusTone = static function (string $status): string {
    return match ($status) {
        'active', 'paid' => 'success',
        'trialing', 'issued' => 'warning',
        'past_due', 'overdue' => 'danger',
        'suspended', 'cancelled', 'void' => 'secondary',
        default => 'secondary',
    };
};
$limitMeta = static function (int|float $used, mixed $limit): array {
    if ($limit === null || $limit === '' || (int) $limit <= 0) {
        return ['label' => 'Unlimited', 'detail' => 'No cap configured', 'progress' => 0];
    }

    $limit = (int) $limit;
    $progress = $limit > 0 ? min(100, (int) round(($used / $limit) * 100)) : 0;

    return [
        'label' => (string) $used . ' / ' . (string) $limit,
        'detail' => (string) max(0, $limit - (int) $used) . ' remaining',
        'progress' => $progress,
    ];
};
$formatDate = static function (?string $value, string $fallback = 'Not scheduled'): string {
    $value = trim((string) $value);

    return $value !== '' ? date('M d, Y H:i', strtotime($value)) : $fallback;
};
$outstandingBalance = array_reduce($invoices, static function (float $carry, array $invoice): float {
    $status = (string) ($invoice['status'] ?? '');
    if (!in_array($status, ['issued', 'overdue'], true)) {
        return $carry;
    }

    return $carry + (float) ($invoice['balance_due'] ?? 0);
}, 0.0);
$currentPlanName = (string) ($subscription['plan_name'] ?? $subscription['plan_name_snapshot'] ?? 'Unassigned');
$currentPlanId = (int) ($subscription['billing_plan_id'] ?? 0);
$lastSaleLabel = $formatDate($usage['last_sale_at'] ?? null, 'No completed sale yet');
$defaultCurrency = default_currency_code();
$subscriptionCurrency = normalize_billing_currency((string) ($subscription['currency'] ?? $defaultCurrency), $defaultCurrency);
?>

<section class="dashboard-hero surface-card card-panel">
    <div class="dashboard-hero__main">
        <p class="eyebrow mb-2">Billing Workspace</p>
        <h2 class="dashboard-hero__title"><?= e((string) ($company['name'] ?? 'Company')) ?> subscription, invoicing, and usage.</h2>
        <p class="dashboard-hero__copy">
            Keep billing contacts current, monitor plan limits, and review issued invoices without leaving the tenant workspace.
        </p>
        <div class="dashboard-hero__meta">
            <span class="status-pill status-pill--<?= e($statusTone($subscriptionStatus)) ?>"><?= e(ucfirst(str_replace('_', ' ', $subscriptionStatus))) ?></span>
            <span class="badge-soft"><i class="bi bi-credit-card-2-front me-1"></i><?= e($currentPlanName) ?></span>
            <span class="badge-soft"><i class="bi bi-cash-stack me-1"></i><?= e(format_money($outstandingBalance, $subscriptionCurrency)) ?> outstanding</span>
        </div>
    </div>

    <div class="dashboard-hero__rail">
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Current Plan</span>
            <strong class="dashboard-hero-stat__value"><?= e($currentPlanName) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e((string) ($subscription['billing_cycle'] ?? 'Plan not assigned')) ?></span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Recurring Amount</span>
            <strong class="dashboard-hero-stat__value"><?= e(format_money((float) ($subscription['amount'] ?? 0), $subscriptionCurrency)) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e($subscriptionCurrency) ?></span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Next Invoice</span>
            <strong class="dashboard-hero-stat__value"><?= e(trim((string) ($subscription['next_invoice_at'] ?? '')) !== '' ? date('M d', strtotime((string) $subscription['next_invoice_at'])) : 'TBD') ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e($formatDate((string) ($subscription['next_invoice_at'] ?? ''), 'Platform admin has not scheduled one yet')) ?></span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Last Sale</span>
            <strong class="dashboard-hero-stat__value"><?= e(trim((string) ($usage['last_sale_at'] ?? '')) !== '' ? date('M d', strtotime((string) $usage['last_sale_at'])) : 'None') ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e($lastSaleLabel) ?></span>
        </article>
    </div>
</section>

<?php if (!$billingReady): ?>
    <section class="surface-card card-panel">
        <div class="alert alert-warning rounded-4 mb-0"><?= e($billingSchemaMessage) ?></div>
    </section>
<?php endif; ?>

<?php if ($billingReady && !$paymentsReady): ?>
    <section class="surface-card card-panel">
        <div class="alert alert-warning rounded-4 mb-0"><?= e($paymentSchemaMessage) ?></div>
    </section>
<?php endif; ?>

<?php if ($alerts !== []): ?>
    <section class="surface-card card-panel mb-4">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Billing Alerts</p>
                <h3><i class="bi bi-exclamation-triangle me-2"></i>Action needed</h3>
            </div>
        </div>
        <div class="stack-grid">
            <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-<?= e((string) ($alert['tone'] ?? 'warning')) ?> rounded-4 mb-0">
                    <div class="fw-semibold"><?= e((string) ($alert['title'] ?? 'Billing alert')) ?></div>
                    <div><?= e((string) ($alert['message'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<div class="metric-grid">
    <?php
    $branchLimit = $limitMeta((int) $usage['branch_count'], $subscription['max_branches'] ?? null);
    $userLimit = $limitMeta((int) $usage['active_user_count'], $subscription['max_users'] ?? null);
    $productLimit = $limitMeta((int) $usage['product_count'], $subscription['max_products'] ?? null);
    $salesLimit = $limitMeta((int) $usage['monthly_sale_count'], $subscription['max_monthly_sales'] ?? null);
    ?>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Branches</span><span>Usage</span></div>
        <h3><?= e($branchLimit['label']) ?></h3>
        <div class="text-muted"><?= e((string) $usage['active_branch_count']) ?> active branches. <?= e($branchLimit['detail']) ?>.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Active Users</span><span>Usage</span></div>
        <h3><?= e($userLimit['label']) ?></h3>
        <div class="text-muted"><?= e((string) $usage['user_count']) ?> total user accounts. <?= e($userLimit['detail']) ?>.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Products</span><span>Catalog</span></div>
        <h3><?= e($productLimit['label']) ?></h3>
        <div class="text-muted"><?= e((string) $usage['active_product_count']) ?> active products. <?= e($productLimit['detail']) ?>.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Monthly Sales</span><span>Throughput</span></div>
        <h3><?= e($salesLimit['label']) ?></h3>
        <div class="text-muted"><?= e(format_currency((float) $usage['monthly_revenue'])) ?> revenue this month. <?= e($salesLimit['detail']) ?>.</div>
    </section>
</div>

<div class="content-grid">
    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Subscription Snapshot</p>
                <h4>Plan details and renewal cadence</h4>
            </div>
        </div>
        <div class="stack-grid">
            <?php if ($subscription === null): ?>
                <div class="empty-state">No subscription has been assigned yet. Contact the platform administrator to activate billing for this workspace.</div>
            <?php else: ?>
                <article class="record-card">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4><?= e($currentPlanName) ?></h4>
                            <div class="inline-note"><?= e(ucfirst((string) ($subscription['billing_cycle'] ?? 'monthly'))) ?> billing</div>
                        </div>
                        <div class="record-card__meta">
                            <span class="status-pill status-pill--<?= e($statusTone($subscriptionStatus)) ?>"><?= e(ucfirst(str_replace('_', ' ', $subscriptionStatus))) ?></span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge-soft"><?= e(format_money((float) ($subscription['amount'] ?? 0), $subscriptionCurrency)) ?></span>
                        <span class="badge-soft"><?= e('Auto renew: ' . (!empty($subscription['auto_renew']) ? 'Enabled' : 'Disabled')) ?></span>
                        <span class="badge-soft"><?= e('Next invoice: ' . $formatDate((string) ($subscription['next_invoice_at'] ?? ''), 'Not scheduled')) ?></span>
                    </div>
                    <?php if (trim((string) ($subscription['notes'] ?? '')) !== ''): ?>
                        <div class="small text-muted"><?= nl2br(e((string) $subscription['notes'])) ?></div>
                    <?php endif; ?>
                </article>
            <?php endif; ?>
        </div>
    </section>

    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Billing Contacts</p>
                <h4>Keep invoice recipients up to date</h4>
            </div>
        </div>
        <form action="<?= e(url('billing/settings/update')) ?>" method="post" class="stack-grid" data-loading-form>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="billing_contact_name">Billing contact</label>
                    <input type="text" class="form-control" id="billing_contact_name" name="billing_contact_name" value="<?= e($billingSettings['billing_contact_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="billing_contact_email">Billing email</label>
                    <input type="email" class="form-control" id="billing_contact_email" name="billing_contact_email" value="<?= e($billingSettings['billing_contact_email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="billing_contact_phone">Billing phone</label>
                    <input type="text" class="form-control" id="billing_contact_phone" name="billing_contact_phone" value="<?= e($billingSettings['billing_contact_phone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="billing_tax_number">Tax number</label>
                    <input type="text" class="form-control" id="billing_tax_number" name="billing_tax_number" value="<?= e($billingSettings['billing_tax_number']) ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="billing_address">Billing address</label>
                    <textarea class="form-control" id="billing_address" name="billing_address" rows="3"><?= e($billingSettings['billing_address']) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="billing_notification_emails">Additional invoice recipients</label>
                    <input type="text" class="form-control" id="billing_notification_emails" name="billing_notification_emails" value="<?= e($billingSettings['billing_notification_emails']) ?>" placeholder="finance@example.com, owner@example.com">
                </div>
                <div class="col-12">
                    <label class="form-label" for="billing_notes">Invoice notes</label>
                    <textarea class="form-control" id="billing_notes" name="billing_notes" rows="4"><?= e($billingSettings['billing_notes']) ?></textarea>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Billing Settings</button>
            </div>
        </form>
    </section>

    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Payment Help</p>
                <h4>How invoices should be settled</h4>
            </div>
        </div>
        <div class="stack-grid">
            <article class="record-card">
                <div class="record-card__header">
                    <div class="workspace-panel__intro">
                        <h4>Support contact</h4>
                    </div>
                </div>
                <div class="small text-muted"><?= e(trim((string) $platformSettings['support_email']) !== '' ? (string) $platformSettings['support_email'] : 'Your platform admin will share support contact details here.') ?></div>
            </article>
            <article class="record-card">
                <div class="record-card__header">
                    <div class="workspace-panel__intro">
                        <h4>Payment instructions</h4>
                    </div>
                </div>
                <div class="small text-muted"><?= trim((string) $platformSettings['payment_instructions']) !== '' ? nl2br(e((string) $platformSettings['payment_instructions'])) : 'Payment instructions have not been published yet.' ?></div>
            </article>
            <?php if ($paymentMethods !== []): ?>
                <?php foreach ($paymentMethods as $method): ?>
                    <article class="record-card">
                        <div class="record-card__header">
                            <div class="workspace-panel__intro">
                                <h4><?= e((string) ($method['name'] ?? 'Payment method')) ?></h4>
                                <div class="inline-note"><?= e(ucfirst(str_replace('_', ' ', (string) ($method['type'] ?? 'other')))) ?></div>
                            </div>
                            <?php if (trim((string) ($method['checkout_url'] ?? '')) !== ''): ?>
                                <div class="record-card__meta">
                                    <a href="<?= e((string) $method['checkout_url']) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Open Checkout</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="small text-muted">
                            <?= e(trim((string) ($method['description'] ?? '')) !== '' ? (string) $method['description'] : 'Configured as an available payment route for billing invoices.') ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="badge-soft"><?= e($subscriptionCurrency) ?> invoices</span>
                            <span class="badge-soft"><?= !empty($method['requires_reference']) ? 'Reference required' : 'Reference optional' ?></span>
                            <span class="badge-soft"><?= !empty($method['requires_proof']) ? 'Proof required' : 'Proof optional' ?></span>
                        </div>
                        <?php if (trim((string) ($method['provider_name'] ?? '')) !== '' || trim((string) ($method['account_name'] ?? '')) !== '' || trim((string) ($method['account_number'] ?? '')) !== ''): ?>
                            <div class="small text-muted mt-2">
                                <?= e(trim(implode(' | ', array_values(array_filter([
                                    (string) ($method['provider_name'] ?? ''),
                                    (string) ($method['account_name'] ?? ''),
                                    (string) ($method['account_number'] ?? ''),
                                ]))))) ?>
                            </div>
                        <?php endif; ?>
                        <?php if (trim((string) ($method['instructions'] ?? '')) !== ''): ?>
                            <div class="small text-muted mt-2"><?= nl2br(e((string) $method['instructions'])) ?></div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Available Plans</p>
            <h3><i class="bi bi-stars me-2"></i>Plan catalog</h3>
        </div>
    </div>
    <div class="stack-grid">
        <?php if ($plans === []): ?>
            <div class="empty-state">Plan information will appear here after platform billing is configured.</div>
        <?php else: ?>
            <?php foreach ($plans as $plan): ?>
                <article class="record-card">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4><?= e((string) ($plan['name'] ?? 'Plan')) ?></h4>
                            <div class="inline-note"><?= e(ucfirst((string) ($plan['billing_cycle'] ?? 'monthly'))) ?> billing</div>
                        </div>
                        <div class="record-card__meta d-flex flex-wrap gap-2">
                            <?php if ((int) ($plan['id'] ?? 0) === $currentPlanId): ?>
                                <span class="status-pill status-pill--success">Current Plan</span>
                            <?php endif; ?>
                            <?php if (!empty($plan['is_featured'])): ?>
                                <span class="badge-soft">Featured</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge-soft"><?= e(format_money((float) ($plan['price'] ?? 0), normalize_billing_currency((string) ($plan['currency'] ?? $defaultCurrency), $defaultCurrency))) ?></span>
                        <span class="badge-soft"><?= e((int) ($plan['trial_days'] ?? 0)) ?> trial days</span>
                        <span class="badge-soft"><?= e(($plan['max_users'] ?? null) !== null ? ((string) $plan['max_users'] . ' users') : 'Unlimited users') ?></span>
                    </div>
                    <?php if (trim((string) ($plan['description'] ?? '')) !== ''): ?>
                        <div class="small text-muted mb-2"><?= e((string) $plan['description']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($plan['features'])): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ((array) $plan['features'] as $feature): ?>
                                <span class="badge-soft"><?= e((string) $feature) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="surface-card card-panel table-shell">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Invoice History</p>
            <h3><i class="bi bi-receipt me-2"></i>Billing invoices</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><?= e(format_money($outstandingBalance, $subscriptionCurrency)) ?> outstanding</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Invoice</th>
                <th>Status</th>
                <th>Total</th>
                <th>Balance</th>
                <th>Due</th>
                <th>Issued</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($invoices === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No billing invoices have been issued yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td>
                            <div><?= e((string) ($invoice['invoice_number'] ?? 'Invoice')) ?></div>
                            <div class="small text-muted"><?= e((string) ($invoice['description'] ?? '')) ?></div>
                        </td>
                        <td><span class="status-pill status-pill--<?= e($statusTone((string) ($invoice['status'] ?? 'issued'))) ?>"><?= e(ucfirst((string) ($invoice['status'] ?? 'issued'))) ?></span></td>
                        <td><?= e(format_money((float) ($invoice['total'] ?? 0), normalize_billing_currency((string) ($invoice['currency'] ?? $subscriptionCurrency), $subscriptionCurrency))) ?></td>
                        <td><?= e(format_money((float) ($invoice['balance_due'] ?? 0), normalize_billing_currency((string) ($invoice['currency'] ?? $subscriptionCurrency), $subscriptionCurrency))) ?></td>
                        <td><?= e($formatDate((string) ($invoice['due_at'] ?? ''), 'Not set')) ?></td>
                        <td><?= e($formatDate((string) ($invoice['issued_at'] ?? ''), 'Pending')) ?></td>
                        <td><a href="<?= e(url('billing/invoices/show?id=' . (int) ($invoice['id'] ?? 0))) ?>" class="btn btn-sm btn-outline-secondary">View Invoice</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
