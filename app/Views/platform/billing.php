<?php
$billingReady = (bool) ($billingReady ?? false);
$paymentsReady = (bool) ($paymentsReady ?? false);
$billingSchemaMessage = (string) ($billingSchemaMessage ?? '');
$paymentSchemaMessage = (string) ($paymentSchemaMessage ?? '');
$plans = is_array($plans ?? null) ? $plans : [];
$subscriptions = is_array($subscriptions ?? null) ? $subscriptions : [];
$recentInvoices = is_array($recentInvoices ?? null) ? $recentInvoices : [];
$paymentMethods = is_array($paymentMethods ?? null) ? $paymentMethods : [];
$pendingPaymentSubmissions = is_array($pendingPaymentSubmissions ?? null) ? $pendingPaymentSubmissions : [];
$billingCurrencies = billing_currency_options(is_array($billingCurrencies ?? null) ? $billingCurrencies : []);
$platformSettings = array_merge([
    'sender_name' => config('mail.from_name', config('app.name', 'NovaPOS')),
    'sender_email' => config('mail.from_address', ''),
    'support_email' => config('mail.from_address', ''),
    'invoice_due_days' => 7,
    'grace_days' => 7,
    'auto_suspend_days' => 14,
    'payment_instructions' => '',
    'invoice_footer' => '',
    'notify_invoice_issued' => true,
    'notify_overdue' => true,
    'notify_suspended' => true,
], $platformSettings ?? []);
$planSummary = array_merge([
    'total_plans' => 0,
    'active_plans' => 0,
    'featured_plans' => 0,
    'default_plans' => 0,
], $planSummary ?? []);
$subscriptionSummary = array_merge([
    'total_subscriptions' => 0,
    'active_subscriptions' => 0,
    'trialing_subscriptions' => 0,
    'past_due_subscriptions' => 0,
    'suspended_subscriptions' => 0,
    'monthly_recurring_revenue' => 0,
], $subscriptionSummary ?? []);
$invoiceSummary = array_merge([
    'total_invoices' => 0,
    'issued_invoices' => 0,
    'overdue_invoices' => 0,
    'paid_invoices' => 0,
    'outstanding_balance' => 0,
    'paid_this_month' => 0,
], $invoiceSummary ?? []);

$statusTone = static function (string $status): string {
    return match ($status) {
        'active', 'paid' => 'success',
        'trialing', 'issued' => 'warning',
        'past_due', 'overdue' => 'danger',
        'suspended', 'cancelled', 'inactive', 'void' => 'secondary',
        default => 'secondary',
    };
};
$formatDate = static function (?string $value, string $fallback = 'Not scheduled'): string {
    $value = trim((string) $value);

    return $value !== '' ? date('M d, Y H:i', strtotime($value)) : $fallback;
};
$paymentMethodsForCurrency = static function (string $currency) use ($paymentMethods): array {
    $currency = normalize_billing_currency($currency);

    return array_values(array_filter($paymentMethods, static function (array $method) use ($currency): bool {
        $supportedCurrencies = is_array($method['supported_currencies'] ?? null)
            ? $method['supported_currencies']
            : [];

        return $supportedCurrencies === [] || in_array($currency, $supportedCurrencies, true);
    }));
};
$activeBillingCurrencies = array_values(array_unique(array_filter(array_merge(
    array_map(static fn (array $plan): string => normalize_billing_currency((string) ($plan['currency'] ?? '')), $plans),
    array_map(static fn (array $subscription): string => normalize_billing_currency((string) ($subscription['currency'] ?? '')), $subscriptions),
    array_map(static fn (array $invoice): string => normalize_billing_currency((string) ($invoice['currency'] ?? '')), $recentInvoices)
))));
$platformBillingCurrency = in_array('GHS', $billingCurrencies, true)
    ? 'GHS'
    : normalize_billing_currency((string) ($billingCurrencies[0] ?? config('app.currency', 'GHS')), 'GHS');
$formatPlatformBillingAmount = static fn (float|int|string|null $amount): string => format_money((float) $amount, $platformBillingCurrency);
?>

<section class="dashboard-hero surface-card card-panel">
    <div class="dashboard-hero__main">
        <p class="eyebrow mb-2">Platform Billing</p>
        <h2 class="dashboard-hero__title">Control plans, recurring revenue, company subscriptions, and collections from one desk.</h2>
        <p class="dashboard-hero__copy">
            The billing desk keeps your SaaS operations professional: package companies onto plans, issue invoices, record collections, and track risk before accounts slip into support debt.
        </p>
        <div class="dashboard-hero__meta">
            <span class="badge-soft"><i class="bi bi-graph-up-arrow me-1"></i><?= e($formatPlatformBillingAmount((float) $subscriptionSummary['monthly_recurring_revenue'])) ?> MRR</span>
            <span class="badge-soft"><i class="bi bi-hourglass-split me-1"></i><?= e((string) $invoiceSummary['overdue_invoices']) ?> overdue invoices</span>
            <span class="badge-soft"><i class="bi bi-wallet2 me-1"></i><?= e($formatPlatformBillingAmount((float) $invoiceSummary['outstanding_balance'])) ?> outstanding</span>
            <span class="badge-soft"><i class="bi bi-cash-coin me-1"></i><?= e($platformBillingCurrency) ?> display</span>
        </div>
    </div>

    <div class="dashboard-hero__rail">
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">MRR</span>
            <strong class="dashboard-hero-stat__value"><?= e($formatPlatformBillingAmount((float) $subscriptionSummary['monthly_recurring_revenue'])) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e((string) $subscriptionSummary['active_subscriptions']) ?> active subscriptions</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Collections</span>
            <strong class="dashboard-hero-stat__value"><?= e($formatPlatformBillingAmount((float) $invoiceSummary['paid_this_month'])) ?></strong>
            <span class="dashboard-hero-stat__meta">Paid this month</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Open Risk</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $subscriptionSummary['past_due_subscriptions']) ?></strong>
            <span class="dashboard-hero-stat__meta">Past due subscriptions</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Plan Catalog</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $planSummary['active_plans']) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e((string) $planSummary['featured_plans']) ?> featured plans</span>
        </article>
    </div>
</section>

<div class="modal fade" id="createPlanModal" tabindex="-1" aria-labelledby="createPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content surface-card border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <div>
                    <p class="eyebrow mb-1">Create Plan</p>
                    <h3 class="mb-1" id="createPlanModalLabel">Add a new subscription package</h3>
                    <p class="text-muted mb-0">Build the plan once here, then assign or override it from each company workspace.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body pt-3">
                <form action="<?= e(url('platform/billing/plans/create')) ?>" method="post" class="stack-grid" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="platform/billing">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="modal_plan_name">Plan name</label>
                            <input type="text" class="form-control" id="modal_plan_name" name="name" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modal_plan_price">Price</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="modal_plan_price" name="price" value="0.00" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modal_plan_currency">Currency</label>
                            <select class="form-select" id="modal_plan_currency" name="currency" required>
                                <?php foreach ($billingCurrencies as $currency): ?>
                                    <option value="<?= e($currency) ?>" <?= $currency === $platformBillingCurrency ? 'selected' : '' ?>><?= e($currency) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modal_plan_cycle">Billing cycle</label>
                            <select class="form-select" id="modal_plan_cycle" name="billing_cycle">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="yearly">Yearly</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modal_plan_trial">Trial days</label>
                            <input type="number" min="0" class="form-control" id="modal_plan_trial" name="trial_days" value="14" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modal_plan_sort">Sort order</label>
                            <input type="number" class="form-control" id="modal_plan_sort" name="sort_order" value="0" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modal_plan_branches">Branch limit</label>
                            <input type="number" min="1" class="form-control" id="modal_plan_branches" name="max_branches" placeholder="Unlimited">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modal_plan_users">User limit</label>
                            <input type="number" min="1" class="form-control" id="modal_plan_users" name="max_users" placeholder="Unlimited">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modal_plan_products">Product limit</label>
                            <input type="number" min="1" class="form-control" id="modal_plan_products" name="max_products" placeholder="Unlimited">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="modal_plan_sales">Monthly sale limit</label>
                            <input type="number" min="1" class="form-control" id="modal_plan_sales" name="max_monthly_sales" placeholder="Unlimited">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="modal_plan_description">Description</label>
                            <input type="text" class="form-control" id="modal_plan_description" name="description">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="modal_plan_features">Feature list</label>
                            <textarea class="form-control" id="modal_plan_features" name="features" rows="4" placeholder="One feature per line"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="modal_plan_status">Status</label>
                            <select class="form-select" id="modal_plan_status" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-8 d-flex align-items-end gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_plan_featured" name="is_featured" value="1">
                                <label class="form-check-label" for="modal_plan_featured">Featured</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="modal_plan_default" name="is_default" value="1">
                                <label class="form-check-label" for="modal_plan_default">Default plan</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Plan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($paymentsReady): ?>
    <div class="modal fade" id="createPaymentMethodModal" tabindex="-1" aria-labelledby="createPaymentMethodModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content surface-card border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <p class="eyebrow mb-1">Create Payment Method</p>
                        <h3 class="mb-1" id="createPaymentMethodModalLabel">Add a real settlement option</h3>
                        <p class="text-muted mb-0">Set the provider, account details, review rules, and currency coverage before exposing it on invoices.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3">
                    <form action="<?= e(url('platform/billing/payment-methods/create')) ?>" method="post" class="stack-grid" data-loading-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="platform/billing">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label" for="modal_payment_method_name">Method name</label>
                                <input type="text" class="form-control" id="modal_payment_method_name" name="name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="modal_payment_method_type">Type</label>
                                <select class="form-select" id="modal_payment_method_type" name="type">
                                    <?php foreach (['bank_transfer' => 'Bank Transfer', 'mobile_money' => 'Mobile Money', 'card' => 'Card', 'cash' => 'Cash', 'other' => 'Other'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="modal_payment_method_status">Status</label>
                                <select class="form-select" id="modal_payment_method_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label" for="modal_payment_method_sort">Sort</label>
                                <input type="number" class="form-control" id="modal_payment_method_sort" name="sort_order" value="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="modal_payment_method_provider">Provider</label>
                                <input type="text" class="form-control" id="modal_payment_method_provider" name="provider_name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="modal_payment_method_account_name">Account name</label>
                                <input type="text" class="form-control" id="modal_payment_method_account_name" name="account_name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="modal_payment_method_account_number">Account / wallet</label>
                                <input type="text" class="form-control" id="modal_payment_method_account_number" name="account_number">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="modal_payment_method_checkout_url">Checkout URL</label>
                                <input type="url" class="form-control" id="modal_payment_method_checkout_url" name="checkout_url" placeholder="https://payments.example.com/checkout">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="modal_payment_method_description">Description</label>
                                <input type="text" class="form-control" id="modal_payment_method_description" name="description">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="modal_payment_method_currencies">Supported currencies</label>
                                <select class="form-select" id="modal_payment_method_currencies" name="supported_currencies[]" multiple size="<?= e((string) min(6, max(3, count($billingCurrencies)))) ?>">
                                    <?php foreach ($billingCurrencies as $currency): ?>
                                        <option value="<?= e($currency) ?>"><?= e($currency) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="small text-muted mt-1">Leave all options unselected to allow any invoice currency.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="modal_payment_method_instructions">Instructions</label>
                                <textarea class="form-control" id="modal_payment_method_instructions" name="instructions" rows="4"></textarea>
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modal_payment_requires_reference" name="requires_reference" value="1" checked>
                                    <label class="form-check-label" for="modal_payment_requires_reference">Require reference</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modal_payment_requires_proof" name="requires_proof" value="1">
                                    <label class="form-check-label" for="modal_payment_requires_proof">Require proof image</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modal_payment_is_default" name="is_default" value="1">
                                    <label class="form-check-label" for="modal_payment_is_default">Default method</label>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Payment Method</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

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

<?php if (count($activeBillingCurrencies) > 1): ?>
    <section class="surface-card card-panel">
        <div class="alert alert-info rounded-4 mb-0">
            Multiple billing currencies are active on the platform: <?= e(implode(', ', $activeBillingCurrencies)) ?>.
            Headline metrics on this page are displayed in <?= e($platformBillingCurrency) ?>, so review plan, subscription, and invoice rows by currency before comparing totals.
        </div>
    </section>
<?php endif; ?>

<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Plans</span><span>Catalog</span></div>
        <h3><?= e((string) $planSummary['total_plans']) ?></h3>
        <div class="text-muted"><?= e((string) $planSummary['active_plans']) ?> active plans in circulation.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Subscriptions</span><span>Portfolio</span></div>
        <h3><?= e((string) $subscriptionSummary['total_subscriptions']) ?></h3>
        <div class="text-muted"><?= e((string) $subscriptionSummary['trialing_subscriptions']) ?> trialing, <?= e((string) $subscriptionSummary['suspended_subscriptions']) ?> suspended.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>MRR</span><span>Recurring</span></div>
        <h3><?= e($formatPlatformBillingAmount((float) $subscriptionSummary['monthly_recurring_revenue'])) ?></h3>
        <div class="text-muted">Displayed in <?= e($platformBillingCurrency) ?> on the billing desk.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Open Invoices</span><span>Collections</span></div>
        <h3><?= e((string) $invoiceSummary['issued_invoices']) ?></h3>
        <div class="text-muted"><?= e((string) $invoiceSummary['overdue_invoices']) ?> overdue invoices.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Outstanding</span><span>Exposure</span></div>
        <h3><?= e($formatPlatformBillingAmount((float) $invoiceSummary['outstanding_balance'])) ?></h3>
        <div class="text-muted">Open exposure displayed in <?= e($platformBillingCurrency) ?>.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Collected</span><span>This Month</span></div>
        <h3><?= e($formatPlatformBillingAmount((float) $invoiceSummary['paid_this_month'])) ?></h3>
        <div class="text-muted">Collections displayed in <?= e($platformBillingCurrency) ?>.</div>
    </section>
</div>

<section class="surface-card card-panel dashboard-toolbar">
    <div class="dashboard-toolbar__copy">
        <p class="eyebrow mb-1">Billing Controls</p>
        <h3 class="mb-1">Manage the catalog here, then fine-tune companies from their detail pages.</h3>
        <p class="text-muted mb-0">Plan configuration lives on this screen. Company-specific subscription overrides and invoice issuing stay available on each company workspace page.</p>
    </div>
    <div class="dashboard-toolbar__actions">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createPlanModal">
            <i class="bi bi-plus-circle me-1"></i>Create Plan
        </button>
        <?php if ($paymentsReady): ?>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#createPaymentMethodModal">
                <i class="bi bi-credit-card-2-front me-1"></i>Create Payment Method
            </button>
        <?php endif; ?>
        <form action="<?= e(url('platform/billing/cycle/run')) ?>" method="post" class="m-0" data-loading-form>
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Run Billing Cycle</button>
        </form>
        <a href="<?= e(url('platform/companies')) ?>" class="btn btn-primary"><i class="bi bi-buildings me-1"></i>Open Companies</a>
        <a href="<?= e(url('platform/companies?status=inactive')) ?>" class="btn btn-outline-secondary"><i class="bi bi-pause-circle me-1"></i>Suspended</a>
        <a href="<?= e(url('platform/companies?activity=inactive_30d')) ?>" class="btn btn-outline-secondary"><i class="bi bi-clock-history me-1"></i>Inactive 30 Days</a>
    </div>
</section>

<div class="content-grid mb-4">
    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Billing Settings</p>
                <h4>Sender identity, payment instructions, and automation windows</h4>
            </div>
        </div>
        <form action="<?= e(url('platform/billing/settings/update')) ?>" method="post" class="stack-grid" data-loading-form>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="sender_name">Sender name</label>
                    <input type="text" class="form-control" id="sender_name" name="sender_name" value="<?= e((string) $platformSettings['sender_name']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sender_email">Sender email</label>
                    <input type="email" class="form-control" id="sender_email" name="sender_email" value="<?= e((string) $platformSettings['sender_email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="support_email">Support email</label>
                    <input type="email" class="form-control" id="support_email" name="support_email" value="<?= e((string) $platformSettings['support_email']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="invoice_due_days">Due days</label>
                    <input type="number" min="0" class="form-control" id="invoice_due_days" name="invoice_due_days" value="<?= e((string) $platformSettings['invoice_due_days']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="grace_days">Grace days</label>
                    <input type="number" min="0" class="form-control" id="grace_days" name="grace_days" value="<?= e((string) $platformSettings['grace_days']) ?>" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="auto_suspend_days">Suspend after</label>
                    <input type="number" min="0" class="form-control" id="auto_suspend_days" name="auto_suspend_days" value="<?= e((string) $platformSettings['auto_suspend_days']) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="payment_instructions">Payment instructions</label>
                    <textarea class="form-control" id="payment_instructions" name="payment_instructions" rows="4" placeholder="Bank account, mobile money, remittance notes, or other settlement steps"><?= e((string) $platformSettings['payment_instructions']) ?></textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="invoice_footer">Invoice footer</label>
                    <textarea class="form-control" id="invoice_footer" name="invoice_footer" rows="3"><?= e((string) $platformSettings['invoice_footer']) ?></textarea>
                </div>
                <div class="col-12 d-flex flex-wrap gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notify_invoice_issued" name="notify_invoice_issued" value="1" <?= !empty($platformSettings['notify_invoice_issued']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify_invoice_issued">Email and notify on invoice issue</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notify_overdue" name="notify_overdue" value="1" <?= !empty($platformSettings['notify_overdue']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify_overdue">Email and notify on past-due status</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notify_suspended" name="notify_suspended" value="1" <?= !empty($platformSettings['notify_suspended']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notify_suspended">Email and notify on suspension</label>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Save Billing Settings</button>
            </div>
        </form>
    </section>

    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Automation Profile</p>
                <h4>What the billing cycle will do</h4>
            </div>
        </div>
        <div class="stack-grid">
            <article class="record-card">
                <div class="record-card__header">
                    <div class="workspace-panel__intro">
                        <h4>Recurring invoicing</h4>
                        <div class="inline-note">Auto-renew subscriptions only</div>
                    </div>
                </div>
                <div class="small text-muted">The cycle creates the current period invoice once `next invoice` is due, then advances the subscription window to the next billing period.</div>
            </article>
            <article class="record-card">
                <div class="record-card__header">
                    <div class="workspace-panel__intro">
                        <h4>Collections policy</h4>
                        <div class="inline-note"><?= e((string) $platformSettings['invoice_due_days']) ?> due days</div>
                    </div>
                </div>
                <div class="small text-muted">Invoices are issued with a default due window of <?= e((string) $platformSettings['invoice_due_days']) ?> days unless a platform admin overrides the due date manually.</div>
            </article>
            <article class="record-card">
                <div class="record-card__header">
                    <div class="workspace-panel__intro">
                        <h4>Risk escalation</h4>
                        <div class="inline-note"><?= e((string) $platformSettings['grace_days']) ?> grace / <?= e((string) $platformSettings['auto_suspend_days']) ?> suspend</div>
                    </div>
                </div>
                <div class="small text-muted">Overdue accounts move to `past due`, then to `suspended` after the configured delay if invoices remain unresolved.</div>
            </article>
            <?php if (trim((string) $platformSettings['payment_instructions']) !== ''): ?>
                <article class="record-card">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4>Current payment instructions</h4>
                        </div>
                    </div>
                    <div class="small text-muted"><?= nl2br(e((string) $platformSettings['payment_instructions'])) ?></div>
                </article>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php if ($paymentsReady): ?>
    <div class="content-grid mb-4">
        <section class="utility-card span-two">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Payment Methods</p>
                    <h4>Edit settlement details and currency support</h4>
                </div>
            </div>
            <div class="stack-grid">
                <?php if ($paymentMethods === []): ?>
                    <div class="empty-state">No billing payment methods have been configured yet.</div>
                <?php else: ?>
                    <?php foreach ($paymentMethods as $method): ?>
                        <form action="<?= e(url('platform/billing/payment-methods/update')) ?>" method="post" class="record-card" data-loading-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="payment_method_id" value="<?= e((string) ($method['id'] ?? 0)) ?>">
                            <input type="hidden" name="return_to" value="platform/billing">
                            <div class="record-card__header">
                                <div class="workspace-panel__intro">
                                    <h4><?= e((string) ($method['name'] ?? 'Method')) ?></h4>
                                    <div class="inline-note"><?= e((string) ($method['slug'] ?? '')) ?></div>
                                </div>
                                <div class="record-card__meta d-flex flex-wrap gap-2">
                                    <?php if (!empty($method['is_default'])): ?>
                                        <span class="badge-soft">Default</span>
                                    <?php endif; ?>
                                    <span class="status-pill status-pill--<?= e($statusTone((string) ($method['status'] ?? 'inactive'))) ?>"><?= e(ucfirst((string) ($method['status'] ?? 'inactive'))) ?></span>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Method name</label>
                                    <input type="text" class="form-control" name="name" value="<?= e((string) ($method['name'] ?? '')) ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Type</label>
                                    <select class="form-select" name="type">
                                        <?php foreach (['bank_transfer' => 'Bank Transfer', 'mobile_money' => 'Mobile Money', 'card' => 'Card', 'cash' => 'Cash', 'other' => 'Other'] as $value => $label): ?>
                                            <option value="<?= e($value) ?>" <?= (string) ($method['type'] ?? 'bank_transfer') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?= (string) ($method['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= (string) ($method['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Sort</label>
                                    <input type="number" class="form-control" name="sort_order" value="<?= e((string) ($method['sort_order'] ?? 0)) ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Provider</label>
                                    <input type="text" class="form-control" name="provider_name" value="<?= e((string) ($method['provider_name'] ?? '')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Account name</label>
                                    <input type="text" class="form-control" name="account_name" value="<?= e((string) ($method['account_name'] ?? '')) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Account / wallet</label>
                                    <input type="text" class="form-control" name="account_number" value="<?= e((string) ($method['account_number'] ?? '')) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Checkout URL</label>
                                    <input type="url" class="form-control" name="checkout_url" value="<?= e((string) ($method['checkout_url'] ?? '')) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <input type="text" class="form-control" name="description" value="<?= e((string) ($method['description'] ?? '')) ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Supported currencies</label>
                                    <select class="form-select" name="supported_currencies[]" multiple size="<?= e((string) min(6, max(3, count($billingCurrencies)))) ?>">
                                        <?php foreach (billing_currency_options((array) ($method['supported_currencies'] ?? [])) as $currency): ?>
                                            <option value="<?= e($currency) ?>" <?= in_array($currency, (array) ($method['supported_currencies'] ?? []), true) ? 'selected' : '' ?>><?= e($currency) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Instructions</label>
                                    <textarea class="form-control" name="instructions" rows="3"><?= e((string) ($method['instructions'] ?? '')) ?></textarea>
                                </div>
                                <div class="col-12 d-flex flex-wrap gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="requires_reference" value="1" <?= !empty($method['requires_reference']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Require reference</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="requires_proof" value="1" <?= !empty($method['requires_proof']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Require proof image</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_default" value="1" <?= !empty($method['is_default']) ? 'checked' : '' ?>>
                                        <label class="form-check-label">Default method</label>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="small text-muted">
                                    <?= !empty($method['supported_currencies']) ? e('Currencies: ' . implode(', ', (array) $method['supported_currencies'])) : 'All invoice currencies allowed.' ?>
                                </div>
                                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Save Method</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="surface-card card-panel table-shell mb-4">
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Payment Reviews</p>
                <h3><i class="bi bi-shield-check me-2"></i>Pending payment submissions</h3>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    <th>Company</th>
                    <th>Invoice</th>
                    <th>Method</th>
                    <th>Amount</th>
                    <th>Reference</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($pendingPaymentSubmissions === []): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No payment submissions are waiting for review.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($pendingPaymentSubmissions as $submission): ?>
                        <tr>
                            <td>
                                <div><?= e((string) ($submission['company_name'] ?? 'Company')) ?></div>
                                <div class="small text-muted"><?= e((string) ($submission['payer_name'] ?? '')) ?></div>
                            </td>
                            <td>
                                <div><?= e((string) ($submission['invoice_number'] ?? 'Invoice')) ?></div>
                                <div class="small text-muted"><?= e((string) ($submission['invoice_status'] ?? 'issued')) ?></div>
                            </td>
                            <td><?= e((string) ($submission['payment_method_name'] ?? 'Payment method')) ?></td>
                            <td><?= e(format_money((float) ($submission['amount'] ?? 0), (string) ($submission['currency'] ?? $platformBillingCurrency))) ?></td>
                            <td><?= e(trim(implode(' | ', array_values(array_filter([(string) ($submission['customer_reference'] ?? ''), (string) ($submission['gateway_reference'] ?? '')])))) ?: 'Not provided') ?></td>
                            <td>
                                <div><?= e($formatDate((string) ($submission['submitted_at'] ?? ''), 'Pending')) ?></div>
                                <?php if (trim((string) ($submission['proof_path'] ?? '')) !== ''): ?>
                                    <a href="<?= e(url((string) $submission['proof_path'])) ?>" target="_blank" rel="noopener" class="small">View proof</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-2">
                                    <a href="<?= e(url('platform/billing/invoices/show?id=' . (int) ($submission['billing_invoice_id'] ?? 0))) ?>" class="btn btn-sm btn-outline-secondary">Open Invoice</a>
                                    <form action="<?= e(url('platform/billing/payments/submissions/approve')) ?>" method="post" class="d-flex flex-column gap-2" data-loading-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="submission_id" value="<?= e((string) ($submission['id'] ?? 0)) ?>">
                                        <input type="hidden" name="return_to" value="platform/billing">
                                        <input type="text" class="form-control form-control-sm" name="review_note" placeholder="Approval note (optional)">
                                        <button type="submit" class="btn btn-sm btn-primary">Approve and Post</button>
                                    </form>
                                    <form action="<?= e(url('platform/billing/payments/submissions/reject')) ?>" method="post" class="d-flex flex-column gap-2" data-loading-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="submission_id" value="<?= e((string) ($submission['id'] ?? 0)) ?>">
                                        <input type="hidden" name="return_to" value="platform/billing">
                                        <input type="text" class="form-control form-control-sm" name="review_note" placeholder="Reason for rejection" required>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<div class="content-grid">
    <section class="utility-card span-two">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Catalog</p>
                <h4>Edit active and archived plans</h4>
            </div>
        </div>
        <div class="stack-grid">
            <?php if ($plans === []): ?>
                <div class="empty-state">No billing plans have been configured yet.</div>
            <?php else: ?>
                <?php foreach ($plans as $plan): ?>
                    <form action="<?= e(url('platform/billing/plans/update')) ?>" method="post" class="record-card" data-loading-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="plan_id" value="<?= e((string) ($plan['id'] ?? 0)) ?>">
                        <input type="hidden" name="return_to" value="platform/billing">
                        <div class="record-card__header">
                            <div class="workspace-panel__intro">
                                <h4><?= e((string) ($plan['name'] ?? 'Plan')) ?></h4>
                                <div class="inline-note"><?= e((string) ($plan['slug'] ?? '')) ?></div>
                            </div>
                            <div class="record-card__meta d-flex flex-wrap gap-2">
                                <?php if (!empty($plan['is_default'])): ?>
                                    <span class="badge-soft">Default</span>
                                <?php endif; ?>
                                <?php if (!empty($plan['is_featured'])): ?>
                                    <span class="badge-soft">Featured</span>
                                <?php endif; ?>
                                <span class="status-pill status-pill--<?= e($statusTone((string) ($plan['status'] ?? 'inactive'))) ?>"><?= e(ucfirst((string) ($plan['status'] ?? 'inactive'))) ?></span>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Plan name</label>
                                <input type="text" class="form-control" name="name" value="<?= e((string) ($plan['name'] ?? '')) ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Price</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="price" value="<?= e((string) ($plan['price'] ?? '0.00')) ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Currency</label>
                                <select class="form-select" name="currency" required>
                                    <?php foreach (billing_currency_options([(string) ($plan['currency'] ?? $platformBillingCurrency)]) as $currency): ?>
                                        <option value="<?= e($currency) ?>" <?= (string) ($plan['currency'] ?? $platformBillingCurrency) === $currency ? 'selected' : '' ?>><?= e($currency) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Sort</label>
                                <input type="number" class="form-control" name="sort_order" value="<?= e((string) ($plan['sort_order'] ?? 0)) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Cycle</label>
                                <select class="form-select" name="billing_cycle">
                                    <?php foreach (['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'custom' => 'Custom'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= (string) ($plan['billing_cycle'] ?? 'monthly') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Trial days</label>
                                <input type="number" min="0" class="form-control" name="trial_days" value="<?= e((string) ($plan['trial_days'] ?? 0)) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="status">
                                    <option value="active" <?= (string) ($plan['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= (string) ($plan['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-3 flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_featured" value="1" <?= !empty($plan['is_featured']) ? 'checked' : '' ?>>
                                    <label class="form-check-label">Featured</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_default" value="1" <?= !empty($plan['is_default']) ? 'checked' : '' ?>>
                                    <label class="form-check-label">Default</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Branch limit</label>
                                <input type="number" min="1" class="form-control" name="max_branches" value="<?= e((string) ($plan['max_branches'] ?? '')) ?>" placeholder="Unlimited">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">User limit</label>
                                <input type="number" min="1" class="form-control" name="max_users" value="<?= e((string) ($plan['max_users'] ?? '')) ?>" placeholder="Unlimited">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Product limit</label>
                                <input type="number" min="1" class="form-control" name="max_products" value="<?= e((string) ($plan['max_products'] ?? '')) ?>" placeholder="Unlimited">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Monthly sale limit</label>
                                <input type="number" min="1" class="form-control" name="max_monthly_sales" value="<?= e((string) ($plan['max_monthly_sales'] ?? '')) ?>" placeholder="Unlimited">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <input type="text" class="form-control" name="description" value="<?= e((string) ($plan['description'] ?? '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Features</label>
                                <textarea class="form-control" name="features" rows="3"><?php foreach ((array) ($plan['features'] ?? []) as $index => $feature): ?><?= $index > 0 ? "\n" : '' ?><?= e((string) $feature) ?><?php endforeach; ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Save Plan</button>
                        </div>
                    </form>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="surface-card card-panel table-shell mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Company Subscriptions</p>
            <h3><i class="bi bi-buildings me-2"></i>Portfolio billing state</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Company</th>
                <th>Plan</th>
                <th>Status</th>
                <th>Amount</th>
                <th>Next Invoice</th>
                <th>Outstanding</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($subscriptions === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No company subscriptions have been assigned yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($subscriptions as $subscription): ?>
                    <tr>
                        <td>
                            <div><?= e((string) ($subscription['company_name'] ?? 'Company')) ?></div>
                            <div class="small text-muted"><?= e((string) ($subscription['company_slug'] ?? '')) ?></div>
                        </td>
                        <td>
                            <div><?= e((string) ($subscription['plan_name'] ?? $subscription['plan_name_snapshot'] ?? 'Plan')) ?></div>
                            <div class="small text-muted"><?= e(ucfirst((string) ($subscription['billing_cycle'] ?? 'monthly'))) ?></div>
                        </td>
                        <td><span class="status-pill status-pill--<?= e($statusTone((string) ($subscription['status'] ?? 'trialing'))) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) ($subscription['status'] ?? 'trialing')))) ?></span></td>
                        <td><?= e(format_money((float) ($subscription['amount'] ?? 0), (string) ($subscription['currency'] ?? $platformBillingCurrency))) ?></td>
                        <td><?= e($formatDate((string) ($subscription['next_invoice_at'] ?? ''), 'Not scheduled')) ?></td>
                        <td><?= e(format_money((float) ($subscription['outstanding_balance'] ?? 0), (string) ($subscription['currency'] ?? $platformBillingCurrency))) ?></td>
                        <td class="text-end">
                            <a href="<?= e(url('platform/companies/show?id=' . (int) ($subscription['company_id'] ?? 0))) ?>" class="btn btn-sm btn-outline-secondary">Manage</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="surface-card card-panel table-shell">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Invoice Desk</p>
            <h3><i class="bi bi-receipt me-2"></i>Recent invoices and collections</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Invoice</th>
                <th>Company</th>
                <th>Status</th>
                <th>Total</th>
                <th>Balance</th>
                <th>Due</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($recentInvoices === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No billing invoices exist yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentInvoices as $invoice): ?>
                    <tr>
                        <td>
                            <div><?= e((string) ($invoice['invoice_number'] ?? 'Invoice')) ?></div>
                            <div class="small text-muted"><?= e((string) ($invoice['description'] ?? '')) ?></div>
                        </td>
                        <td>
                            <div><?= e((string) ($invoice['company_name'] ?? 'Company')) ?></div>
                            <div class="small text-muted"><?= e((string) ($invoice['plan_name'] ?? $invoice['plan_name_snapshot'] ?? '')) ?></div>
                        </td>
                        <td><span class="status-pill status-pill--<?= e($statusTone((string) ($invoice['status'] ?? 'issued'))) ?>"><?= e(ucfirst((string) ($invoice['status'] ?? 'issued'))) ?></span></td>
                        <td><?= e(format_money((float) ($invoice['total'] ?? 0), (string) ($invoice['currency'] ?? $platformBillingCurrency))) ?></td>
                        <td><?= e(format_money((float) ($invoice['balance_due'] ?? 0), (string) ($invoice['currency'] ?? $platformBillingCurrency))) ?></td>
                        <td><?= e($formatDate((string) ($invoice['due_at'] ?? ''), 'Not set')) ?></td>
                        <td>
                            <div class="d-flex flex-column gap-2">
                                <a href="<?= e(url('platform/billing/invoices/show?id=' . (int) ($invoice['id'] ?? 0))) ?>" class="btn btn-sm btn-outline-secondary">View Invoice</a>
                                <a href="<?= e(url('platform/companies/show?id=' . (int) ($invoice['company_id'] ?? 0))) ?>" class="btn btn-sm btn-outline-secondary">Open Company</a>
                                <?php if (in_array((string) ($invoice['status'] ?? ''), ['issued', 'overdue'], true) && (float) ($invoice['balance_due'] ?? 0) > 0): ?>
                                    <?php $invoicePaymentMethods = $paymentsReady ? $paymentMethodsForCurrency((string) ($invoice['currency'] ?? $platformBillingCurrency)) : []; ?>
                                    <form action="<?= e(url('platform/billing/invoices/payments/store')) ?>" method="post" class="d-flex flex-column gap-2" data-loading-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">
                                        <input type="hidden" name="return_to" value="platform/billing">
                                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm" name="amount" value="<?= e((string) ($invoice['balance_due'] ?? 0)) ?>">
                                        <?php if ($invoicePaymentMethods !== []): ?>
                                            <select class="form-select form-select-sm" name="billing_payment_method_id">
                                                <?php foreach ($invoicePaymentMethods as $method): ?>
                                                    <option value="<?= e((string) ($method['id'] ?? 0)) ?>"><?= e((string) ($method['name'] ?? 'Method')) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <select class="form-select form-select-sm" name="payment_method">
                                                <option value="bank_transfer">Bank transfer</option>
                                                <option value="card">Card</option>
                                                <option value="cash">Cash</option>
                                                <option value="mobile_money">Mobile money</option>
                                                <option value="other">Other</option>
                                            </select>
                                        <?php endif; ?>
                                        <input type="text" class="form-control form-control-sm" name="reference" placeholder="Reference">
                                        <button type="submit" class="btn btn-sm btn-primary">Record Payment</button>
                                    </form>
                                    <div class="d-flex gap-2">
                                        <?php if ((string) ($invoice['status'] ?? '') === 'issued'): ?>
                                            <form action="<?= e(url('platform/billing/invoices/status')) ?>" method="post" class="m-0" data-loading-form>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">
                                                <input type="hidden" name="status" value="overdue">
                                                <input type="hidden" name="return_to" value="platform/billing">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">Mark Overdue</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ((float) ($invoice['amount_paid'] ?? 0) <= 0): ?>
                                            <form action="<?= e(url('platform/billing/invoices/status')) ?>" method="post" class="m-0" data-loading-form>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">
                                                <input type="hidden" name="status" value="void">
                                                <input type="hidden" name="return_to" value="platform/billing">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Void</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
