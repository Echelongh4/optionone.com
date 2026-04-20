<?php
$company = is_array($company ?? null) ? $company : [];
$owner = is_array($owner ?? null) ? $owner : null;
$supportAccessTarget = is_array($supportAccessTarget ?? null) ? $supportAccessTarget : null;
$branches = is_array($branches ?? null) ? $branches : [];
$users = is_array($users ?? null) ? $users : [];
$recentActivity = is_array($recentActivity ?? null) ? $recentActivity : [];
$billingReady = (bool) ($billingReady ?? false);
$paymentsReady = (bool) ($paymentsReady ?? false);
$billingSchemaMessage = (string) ($billingSchemaMessage ?? '');
$paymentSchemaMessage = (string) ($paymentSchemaMessage ?? '');
$availablePlans = is_array($availablePlans ?? null) ? $availablePlans : [];
$subscription = is_array($subscription ?? null) ? $subscription : null;
$billingUsage = array_merge([
    'branch_count' => 0,
    'active_branch_count' => 0,
    'user_count' => 0,
    'active_user_count' => 0,
    'product_count' => 0,
    'active_product_count' => 0,
    'monthly_sale_count' => 0,
    'monthly_revenue' => 0,
    'last_sale_at' => null,
], $billingUsage ?? []);
$billingInvoices = is_array($billingInvoices ?? null) ? $billingInvoices : [];
$billingPaymentMethods = is_array($billingPaymentMethods ?? null) ? $billingPaymentMethods : [];
$pendingPaymentSubmissions = is_array($pendingPaymentSubmissions ?? null) ? $pendingPaymentSubmissions : [];
$billingCurrencies = billing_currency_options(is_array($billingCurrencies ?? null) ? $billingCurrencies : []);
$platformBillingSettings = array_merge([
    'invoice_due_days' => 7,
    'support_email' => '',
    'payment_instructions' => '',
], $platformBillingSettings ?? []);
$workspaceSettings = array_merge([
    'currency' => default_currency_code(),
    'barcode_format' => 'CODE128',
    'receipt_header' => 'Thank you for your business.',
    'receipt_footer' => 'Goods sold are subject to store policy.',
    'multi_branch_enabled' => false,
    'email_low_stock_alerts_enabled' => true,
    'email_daily_summary_enabled' => true,
    'ops_email_recipient_scope' => 'business_and_team',
    'ops_email_additional_recipients' => '',
    'mail_host' => '',
    'mail_port' => (string) config('mail.port', 587),
    'mail_username' => '',
    'mail_encryption' => (string) config('mail.encryption', 'tls'),
    'mail_from_address' => '',
    'mail_from_name' => '',
    'mail_configured' => false,
], $workspaceSettings ?? []);
$platformDefaultSettings = array_merge([
    'tenant_default_currency' => default_currency_code(),
    'tenant_default_barcode_format' => 'CODE128',
    'tenant_default_receipt_header' => 'Thank you for your business.',
    'tenant_default_receipt_footer' => 'Goods sold are subject to store policy.',
    'tenant_default_multi_branch_enabled' => 'false',
    'tenant_default_email_low_stock_alerts_enabled' => 'true',
    'tenant_default_email_daily_summary_enabled' => 'true',
    'tenant_default_ops_email_recipient_scope' => 'business_and_team',
    'tenant_default_ops_email_additional_recipients' => '',
    'tenant_default_mail_host' => '',
    'tenant_default_mail_port' => (string) config('mail.port', 587),
    'tenant_default_mail_username' => '',
    'tenant_default_mail_encryption' => (string) config('mail.encryption', 'tls'),
    'tenant_default_mail_from_address' => '',
    'tenant_default_mail_from_name' => '',
], $platformDefaultSettings ?? []);
$workspaceSettingsComparison = array_merge([
    'count' => 0,
    'items' => [],
], $workspaceSettingsComparison ?? []);
$canReapplyPlatformDefaults = (bool) ($canReapplyPlatformDefaults ?? false);

$companyStatus = (string) ($company['status'] ?? 'inactive');
$companyStatusTone = $companyStatus === 'active' ? 'success' : 'danger';
$ownerVerified = $owner !== null && trim((string) ($owner['email_verified_at'] ?? '')) !== '';
$ownerStatus = $owner !== null ? (string) ($owner['status'] ?? 'inactive') : 'inactive';
$ownerStateLabel = $owner === null
    ? 'No owner assigned'
    : ($ownerVerified ? 'Owner verified' : 'Verification pending');
$ownerStateTone = $owner === null ? 'warning' : ($ownerVerified ? 'success' : 'warning');
$lastActivity = trim((string) ($company['last_login_at'] ?? ''));
$lastActivityLabel = $lastActivity !== '' ? date('M d, Y H:i', strtotime($lastActivity)) : 'No tenant login yet';

$formatDate = static function (?string $value, string $fallback = 'Not available'): string {
    $value = trim((string) $value);

    return $value !== '' ? date('M d, Y H:i', strtotime($value)) : $fallback;
};

$userStatusTone = static function (string $status): string {
    return $status === 'active' ? 'success' : 'warning';
};

$billingStatusTone = static function (string $status): string {
    return match ($status) {
        'active', 'paid' => 'success',
        'trialing', 'issued' => 'warning',
        'past_due', 'overdue' => 'danger',
        'suspended', 'cancelled', 'void', 'inactive' => 'secondary',
        default => 'secondary',
    };
};

$usageLimitMeta = static function (int|float $used, mixed $limit): array {
    if ($limit === null || $limit === '' || (int) $limit <= 0) {
        return ['label' => 'Unlimited', 'detail' => 'No cap configured'];
    }

    $limit = (int) $limit;

    return [
        'label' => (string) $used . ' / ' . (string) $limit,
        'detail' => (string) max(0, $limit - (int) $used) . ' remaining',
    ];
};
$paymentMethodsForCurrency = static function (string $currency) use ($billingPaymentMethods): array {
    $currency = normalize_billing_currency($currency);

    return array_values(array_filter($billingPaymentMethods, static function (array $method) use ($currency): bool {
        $supportedCurrencies = is_array($method['supported_currencies'] ?? null)
            ? $method['supported_currencies']
            : [];

        return $supportedCurrencies === [] || in_array($currency, $supportedCurrencies, true);
    }));
};
$defaultCurrency = default_currency_code();
$workspaceCurrency = normalize_billing_currency((string) ($workspaceSettings['currency'] ?? ($subscription['currency'] ?? $defaultCurrency)), $defaultCurrency);
$subscriptionCurrency = normalize_billing_currency((string) ($subscription['currency'] ?? $workspaceCurrency), $workspaceCurrency);
$recipientScopeLabels = [
    'business' => 'Business email only',
    'team' => 'Admin and manager team only',
    'business_and_team' => 'Business email and team',
];
$workspaceRecipientScopeLabel = $recipientScopeLabels[(string) ($workspaceSettings['ops_email_recipient_scope'] ?? 'business_and_team')]
    ?? $recipientScopeLabels['business_and_team'];
$workspaceSettingsDriftItems = is_array($workspaceSettingsComparison['items'] ?? null)
    ? $workspaceSettingsComparison['items']
    : [];
$workspaceSettingsDriftCount = max(0, (int) ($workspaceSettingsComparison['count'] ?? count($workspaceSettingsDriftItems)));
$workspaceDefaultsTone = $workspaceSettingsDriftCount > 0 ? 'warning' : 'success';
$workspaceDefaultsLabel = $workspaceSettingsDriftCount > 0
    ? $workspaceSettingsDriftCount . ' setting' . ($workspaceSettingsDriftCount === 1 ? '' : 's') . ' differ from platform defaults'
    : 'Matches platform defaults';
$workspaceDefaultsMoreCount = max(0, $workspaceSettingsDriftCount - 6);
?>

<section class="dashboard-hero surface-card card-panel">
    <div class="dashboard-hero__main">
        <p class="eyebrow mb-2">Company Workspace</p>
        <h2 class="dashboard-hero__title"><?= e((string) ($company['name'] ?? 'Company')) ?></h2>
        <p class="dashboard-hero__copy">
            Review onboarding status, branch footprint, user access, and the latest tenant activity before taking support or lifecycle actions.
        </p>
        <div class="dashboard-hero__meta">
            <span class="status-pill status-pill--<?= e($companyStatusTone) ?>"><?= e(ucfirst($companyStatus)) ?></span>
            <span class="status-pill status-pill--<?= e($ownerStateTone) ?>"><?= e($ownerStateLabel) ?></span>
            <span class="badge-soft"><i class="bi bi-hash me-1"></i><?= e((string) ($company['slug'] ?? '')) ?></span>
            <span class="badge-soft"><i class="bi bi-calendar-event me-1"></i>Created <?= e($formatDate((string) ($company['created_at'] ?? ''), 'Unknown')) ?></span>
        </div>
    </div>

    <div class="dashboard-hero__rail">
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Branches</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) ($company['branch_count'] ?? 0)) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e((string) ($company['active_branch_count'] ?? 0)) ?> active locations</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Users</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) ($company['user_count'] ?? 0)) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e((string) ($company['active_user_count'] ?? 0)) ?> active users</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Owner State</span>
            <strong class="dashboard-hero-stat__value"><?= e($owner !== null ? ($ownerVerified ? 'Verified' : 'Pending') : 'Missing') ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e($owner !== null ? (string) ($owner['email'] ?? '') : 'No owner record') ?></span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Last Activity</span>
            <strong class="dashboard-hero-stat__value"><?= e($lastActivity !== '' ? date('M d', strtotime($lastActivity)) : 'None') ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e($lastActivityLabel) ?></span>
        </article>
    </div>
</section>

<section class="surface-card card-panel dashboard-toolbar">
    <div class="dashboard-toolbar__copy">
        <p class="eyebrow mb-1">Lifecycle Controls</p>
        <h3 class="mb-1">Manage workspace access and onboarding recovery.</h3>
        <p class="text-muted mb-0">Suspension blocks tenant sign-in. Owner verification resend is available while onboarding remains incomplete.</p>
    </div>
    <div class="dashboard-toolbar__actions">
        <form action="<?= e(url('platform/companies/status')) ?>" method="post" class="d-inline" data-loading-form>
            <?= csrf_field() ?>
            <input type="hidden" name="company_id" value="<?= e((string) ($company['id'] ?? 0)) ?>">
            <input type="hidden" name="status" value="<?= e($companyStatus === 'active' ? 'inactive' : 'active') ?>">
            <button
                type="submit"
                class="btn <?= $companyStatus === 'active' ? 'btn-outline-danger' : 'btn-primary' ?>"
                data-confirm-action
                data-confirm-title="<?= e($companyStatus === 'active' ? 'Suspend this company?' : 'Activate this company?') ?>"
                data-confirm-text="<?= e($companyStatus === 'active'
                    ? 'Tenant users will be blocked from signing in until the company is reactivated.'
                    : 'Tenant users will be allowed to sign in again immediately.') ?>"
                data-confirm-button="<?= e($companyStatus === 'active' ? 'Suspend Company' : 'Activate Company') ?>"
            >
                <i class="bi <?= e($companyStatus === 'active' ? 'bi-pause-circle' : 'bi-play-circle') ?> me-1"></i>
                <?= e($companyStatus === 'active' ? 'Suspend Company' : 'Activate Company') ?>
            </button>
        </form>

        <?php if ($owner !== null && !$ownerVerified): ?>
            <form action="<?= e(url('platform/companies/resend-owner-verification')) ?>" method="post" class="d-inline" data-loading-form>
                <?= csrf_field() ?>
                <input type="hidden" name="company_id" value="<?= e((string) ($company['id'] ?? 0)) ?>">
                <button type="submit" class="btn btn-outline-secondary">
                    <i class="bi bi-envelope-arrow-up me-1"></i>Resend Owner Verification
                </button>
            </form>
        <?php endif; ?>

        <a href="<?= e(url('platform/billing')) ?>" class="btn btn-outline-secondary"><i class="bi bi-credit-card me-1"></i>Billing Desk</a>
        <a href="<?= e(url('platform/settings')) ?>" class="btn btn-outline-secondary"><i class="bi bi-sliders me-1"></i>General Settings</a>
        <a href="<?= e(url('platform/companies')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Companies</a>
    </div>
</section>

<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Branches</span><span>Footprint</span></div>
        <h3><?= e((string) ($company['branch_count'] ?? 0)) ?></h3>
        <div class="text-muted"><?= e((string) ($company['active_branch_count'] ?? 0)) ?> active branch locations.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Users</span><span>Access</span></div>
        <h3><?= e((string) ($company['user_count'] ?? 0)) ?></h3>
        <div class="text-muted"><?= e((string) ($company['active_user_count'] ?? 0)) ?> active user accounts.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Owner Verification</span><span>State</span></div>
        <h3><?= e($ownerVerified ? 'Verified' : ($owner !== null ? 'Pending' : 'Missing')) ?></h3>
        <div class="text-muted"><?= e($owner !== null ? ((string) ($owner['email'] ?? '')) : 'No owner email recorded') ?></div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Pending Owners</span><span>Queue</span></div>
        <h3><?= e((string) ($company['pending_owner_verification_count'] ?? 0)) ?></h3>
        <div class="text-muted">Owner accounts still awaiting verification.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Workspace Status</span><span>Lifecycle</span></div>
        <h3><?= e(ucfirst($companyStatus)) ?></h3>
        <div class="text-muted">Controls whether the company can sign in.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Last Login</span><span>Activity</span></div>
        <h3><?= e($lastActivity !== '' ? date('M d', strtotime($lastActivity)) : 'None') ?></h3>
        <div class="text-muted"><?= e($lastActivityLabel) ?></div>
    </section>
</div>

<div class="content-grid">
    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Workspace Profile</p>
                <h4>Business details</h4>
            </div>
        </div>
        <div class="stack-grid">
            <div class="record-card">
                <div class="small text-muted">Company email</div>
                <div class="fw-semibold"><?= e((string) ($company['email'] ?? 'Not set')) ?></div>
            </div>
            <div class="record-card">
                <div class="small text-muted">Phone</div>
                <div class="fw-semibold"><?= e((string) ($company['phone'] ?? 'Not set')) ?></div>
            </div>
            <div class="record-card">
                <div class="small text-muted">Address</div>
                <div class="fw-semibold"><?= e((string) ($company['address'] ?? 'Not set')) ?></div>
            </div>
        </div>
    </section>

    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Owner & Onboarding</p>
                <h4>Primary owner account</h4>
            </div>
        </div>
        <div class="stack-grid">
            <?php if ($owner === null): ?>
                <div class="empty-state">No owner account has been attached to this company yet.</div>
            <?php else: ?>
                <article class="record-card">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4><?= e((string) ($owner['full_name'] ?? 'Owner')) ?></h4>
                            <div class="inline-note"><?= e((string) ($owner['role_name'] ?? 'Owner')) ?></div>
                        </div>
                        <div class="record-card__meta">
                            <span class="status-pill status-pill--<?= e($ownerStateTone) ?>"><?= e($ownerStateLabel) ?></span>
                        </div>
                    </div>
                    <div class="small text-muted mb-2"><?= e((string) ($owner['email'] ?? 'No email')) ?></div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge-soft"><?= e('Account ' . ucfirst($ownerStatus)) ?></span>
                        <span class="badge-soft"><?= e('Last login: ' . $formatDate((string) ($owner['last_login_at'] ?? ''), 'Never')) ?></span>
                    </div>
                </article>
                <?php if (!$ownerVerified): ?>
                    <div class="alert alert-warning rounded-4 mb-0">
                        Owner verification is incomplete. The tenant cannot complete first-time access until the owner uses the latest verification link.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<div class="content-grid mb-4">
    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Workspace Configuration</p>
                <h4>Live tenant defaults and communication settings</h4>
            </div>
            <div class="record-card__meta d-flex flex-wrap gap-2 align-items-center">
                <span class="status-pill status-pill--<?= e($workspaceDefaultsTone) ?>"><?= e($workspaceDefaultsLabel) ?></span>
                <?php if ($canReapplyPlatformDefaults): ?>
                    <form action="<?= e(url('platform/companies/defaults/reapply')) ?>" method="post" class="d-inline" data-loading-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="company_id" value="<?= e((string) ($company['id'] ?? 0)) ?>">
                        <button
                            type="submit"
                            class="btn btn-outline-secondary btn-sm"
                            data-confirm-action
                            data-confirm-title="Reapply platform defaults to this workspace?"
                            data-confirm-text="This resets the tenant settings keys for this company to the current platform defaults. Billing records, subscriptions, invoices, and company profile details will not be changed."
                            data-confirm-button="Reapply Defaults"
                        >
                            <i class="bi bi-arrow-repeat me-1"></i>Reapply Defaults
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <div class="stack-grid">
            <article class="record-card">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge-soft"><?= e($workspaceCurrency) ?> currency</span>
                    <span class="badge-soft"><?= e((string) ($workspaceSettings['barcode_format'] ?? 'CODE128')) ?> barcode</span>
                    <span class="badge-soft"><?= !empty($workspaceSettings['multi_branch_enabled']) ? 'Multi-branch enabled' : 'Single-branch default' ?></span>
                    <span class="badge-soft"><?= !empty($workspaceSettings['mail_configured']) ? 'Mail defaults ready' : 'Mail defaults pending' ?></span>
                </div>
                <div class="small text-muted">Platform default currency: <?= e((string) ($platformDefaultSettings['tenant_default_currency'] ?? $workspaceCurrency)) ?></div>
                <div class="small text-muted mt-2">Receipt header: <?= e((string) ($workspaceSettings['receipt_header'] ?? '')) ?></div>
                <div class="small text-muted mt-2">Receipt footer: <?= e((string) ($workspaceSettings['receipt_footer'] ?? '')) ?></div>
                <div class="small text-muted mt-2">Alert recipients: <?= e($workspaceRecipientScopeLabel) ?></div>
                <div class="small text-muted mt-2">
                    Email alerts:
                    <?= !empty($workspaceSettings['email_low_stock_alerts_enabled']) ? 'low-stock on' : 'low-stock off' ?>,
                    <?= !empty($workspaceSettings['email_daily_summary_enabled']) ? 'daily summary on' : 'daily summary off' ?>
                </div>
                <?php if (trim((string) ($workspaceSettings['ops_email_additional_recipients'] ?? '')) !== ''): ?>
                    <div class="small text-muted mt-2">Extra recipients: <?= e((string) $workspaceSettings['ops_email_additional_recipients']) ?></div>
                <?php endif; ?>
                <?php if (!empty($workspaceSettings['mail_configured'])): ?>
                    <div class="small text-muted mt-2">
                        Mail sender: <?= e((string) ($workspaceSettings['mail_from_name'] ?? 'Mailer')) ?> |
                        <?= e((string) ($workspaceSettings['mail_from_address'] ?? '')) ?>
                    </div>
                <?php endif; ?>
                <div class="small text-muted mt-3">
                    Reapplying defaults only resets tenant settings for this company. Billing records, invoices, subscriptions, and company profile fields stay unchanged.
                </div>
            </article>
            <?php if ($workspaceSettingsDriftCount > 0): ?>
                <article class="record-card">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4>Drift from platform defaults</h4>
                            <div class="inline-note">Current tenant values that differ from the shared platform baseline.</div>
                        </div>
                        <div class="record-card__meta">
                            <span class="status-pill status-pill--warning"><?= e((string) $workspaceSettingsDriftCount) ?> changed</span>
                        </div>
                    </div>
                    <div class="stack-grid">
                        <?php foreach (array_slice($workspaceSettingsDriftItems, 0, 6) as $difference): ?>
                            <div class="record-card">
                                <div class="fw-semibold"><?= e((string) ($difference['label'] ?? 'Setting')) ?></div>
                                <div class="small text-muted mt-2">Current: <?= e((string) ($difference['current'] ?? '')) ?></div>
                                <div class="small text-muted">Platform default: <?= e((string) ($difference['default'] ?? '')) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($workspaceDefaultsMoreCount > 0): ?>
                        <div class="small text-muted mt-3">+<?= e((string) $workspaceDefaultsMoreCount) ?> more settings differ from the platform defaults.</div>
                    <?php endif; ?>
                </article>
            <?php else: ?>
                <div class="alert alert-success rounded-4 mb-0">
                    This workspace is currently aligned with the active platform tenant defaults.
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Billing Command</p>
            <h3><i class="bi bi-credit-card-2-front me-2"></i>Subscription and Invoice Controls</h3>
        </div>
        <?php if ($subscription !== null): ?>
            <div class="workspace-panel__meta d-flex flex-wrap gap-2">
                <span class="status-pill status-pill--<?= e($billingStatusTone((string) ($subscription['status'] ?? 'trialing'))) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) ($subscription['status'] ?? 'trialing')))) ?></span>
                <span class="badge-soft"><?= e(format_money((float) ($subscription['amount'] ?? 0), $subscriptionCurrency)) ?></span>
                <span class="badge-soft"><?= e((string) ($subscription['plan_name'] ?? $subscription['plan_name_snapshot'] ?? 'Plan')) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$billingReady): ?>
        <div class="alert alert-warning rounded-4 mb-0"><?= e($billingSchemaMessage) ?></div>
    <?php else: ?>
        <?php if (!$paymentsReady): ?>
            <div class="alert alert-warning rounded-4 mb-4"><?= e($paymentSchemaMessage) ?></div>
        <?php endif; ?>
        <?php
        $currentPlanId = (int) ($subscription['billing_plan_id'] ?? 0);
        $currentPlanName = (string) ($subscription['plan_name'] ?? $subscription['plan_name_snapshot'] ?? 'Unassigned');
        $branchUsageMeta = $usageLimitMeta((int) $billingUsage['branch_count'], $subscription['max_branches'] ?? null);
        $userUsageMeta = $usageLimitMeta((int) $billingUsage['active_user_count'], $subscription['max_users'] ?? null);
        $productUsageMeta = $usageLimitMeta((int) $billingUsage['product_count'], $subscription['max_products'] ?? null);
        $salesUsageMeta = $usageLimitMeta((int) $billingUsage['monthly_sale_count'], $subscription['max_monthly_sales'] ?? null);
        $outstandingBalance = array_reduce($billingInvoices, static function (float $carry, array $invoice): float {
            $status = (string) ($invoice['status'] ?? '');
            if (!in_array($status, ['issued', 'overdue'], true)) {
                return $carry;
            }

            return $carry + (float) ($invoice['balance_due'] ?? 0);
        }, 0.0);
        ?>
        <div class="metric-grid mb-4">
            <section class="metric-card card-panel">
                <div class="metric-meta"><span>Plan</span><span>Subscription</span></div>
                <h3><?= e($currentPlanName) ?></h3>
                <div class="text-muted"><?= e($subscription !== null ? ucfirst((string) ($subscription['billing_cycle'] ?? 'monthly')) . ' billing' : 'No plan assigned yet.') ?></div>
            </section>
            <section class="metric-card card-panel">
                <div class="metric-meta"><span>Outstanding</span><span>Collections</span></div>
                <h3><?= e(format_money($outstandingBalance, $subscriptionCurrency)) ?></h3>
                <div class="text-muted"><?= e((string) count(array_filter($billingInvoices, static fn (array $invoice): bool => in_array((string) ($invoice['status'] ?? ''), ['issued', 'overdue'], true))) ) ?> open invoices.</div>
            </section>
            <section class="metric-card card-panel">
                <div class="metric-meta"><span>Branches</span><span>Usage</span></div>
                <h3><?= e($branchUsageMeta['label']) ?></h3>
                <div class="text-muted"><?= e($branchUsageMeta['detail']) ?></div>
            </section>
            <section class="metric-card card-panel">
                <div class="metric-meta"><span>Users</span><span>Usage</span></div>
                <h3><?= e($userUsageMeta['label']) ?></h3>
                <div class="text-muted"><?= e($userUsageMeta['detail']) ?></div>
            </section>
            <section class="metric-card card-panel">
                <div class="metric-meta"><span>Products</span><span>Usage</span></div>
                <h3><?= e($productUsageMeta['label']) ?></h3>
                <div class="text-muted"><?= e($productUsageMeta['detail']) ?></div>
            </section>
            <section class="metric-card card-panel">
                <div class="metric-meta"><span>Monthly Sales</span><span>Usage</span></div>
                <h3><?= e($salesUsageMeta['label']) ?></h3>
                <div class="text-muted"><?= e(format_money((float) $billingUsage['monthly_revenue'], $workspaceCurrency)) ?> revenue this month.</div>
            </section>
        </div>

        <div class="content-grid">
            <section class="utility-card">
                <div class="utility-card__header">
                    <div class="workspace-panel__intro">
                        <p class="eyebrow mb-1">Subscription Controls</p>
                        <h4>Assign plan, billing status, and limits</h4>
                    </div>
                </div>
                <form action="<?= e(url('platform/billing/subscriptions/update')) ?>" method="post" class="stack-grid" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= e((string) ($company['id'] ?? 0)) ?>">
                    <input type="hidden" name="return_to" value="<?= e('platform/companies/show?id=' . (int) ($company['id'] ?? 0)) ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="billing_plan_id">Billing plan</label>
                            <select class="form-select" id="billing_plan_id" name="billing_plan_id" required>
                                <option value="">Select a plan</option>
                                <?php foreach ($availablePlans as $plan): ?>
                                    <option
                                        value="<?= e((string) ($plan['id'] ?? 0)) ?>"
                                        data-plan-amount="<?= e((string) ($plan['price'] ?? '0.00')) ?>"
                                        data-plan-currency="<?= e(normalize_billing_currency((string) ($plan['currency'] ?? $workspaceCurrency), $workspaceCurrency)) ?>"
                                        data-plan-cycle="<?= e((string) ($plan['billing_cycle'] ?? 'monthly')) ?>"
                                        data-plan-max-branches="<?= e((string) ($plan['max_branches'] ?? '')) ?>"
                                        data-plan-max-users="<?= e((string) ($plan['max_users'] ?? '')) ?>"
                                        data-plan-max-products="<?= e((string) ($plan['max_products'] ?? '')) ?>"
                                        data-plan-max-monthly-sales="<?= e((string) ($plan['max_monthly_sales'] ?? '')) ?>"
                                        <?= (int) ($plan['id'] ?? 0) === $currentPlanId ? 'selected' : '' ?>
                                    >
                                        <?= e((string) ($plan['name'] ?? 'Plan')) ?> | <?= e(format_money((float) ($plan['price'] ?? 0), normalize_billing_currency((string) ($plan['currency'] ?? $workspaceCurrency), $workspaceCurrency))) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="billing_status">Status</label>
                            <select class="form-select" id="billing_status" name="status">
                                <?php foreach (['trialing' => 'Trialing', 'active' => 'Active', 'past_due' => 'Past Due', 'suspended' => 'Suspended', 'cancelled' => 'Cancelled'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= (string) ($subscription['status'] ?? 'trialing') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="billing_amount">Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="billing_amount" name="amount" value="<?= e((string) ($subscription['amount'] ?? '0.00')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="billing_currency">Currency</label>
                            <select class="form-select" id="billing_currency" name="currency" required>
                                <?php foreach (billing_currency_options([$subscriptionCurrency, $workspaceCurrency]) as $currency): ?>
                                    <option value="<?= e($currency) ?>" <?= $subscriptionCurrency === $currency ? 'selected' : '' ?>><?= e($currency) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="billing_cycle">Billing cycle</label>
                            <select class="form-select" id="billing_cycle" name="billing_cycle">
                                <?php foreach (['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly', 'custom' => 'Custom'] as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= (string) ($subscription['billing_cycle'] ?? 'monthly') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="billing_auto_renew" name="auto_renew" value="1" <?= !empty($subscription['auto_renew']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="billing_auto_renew">Auto renew</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="trial_ends_at">Trial ends</label>
                            <input type="datetime-local" class="form-control" id="trial_ends_at" name="trial_ends_at" value="<?= e(trim((string) ($subscription['trial_ends_at'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string) $subscription['trial_ends_at'])) : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="next_invoice_at">Next invoice</label>
                            <input type="datetime-local" class="form-control" id="next_invoice_at" name="next_invoice_at" value="<?= e(trim((string) ($subscription['next_invoice_at'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string) $subscription['next_invoice_at'])) : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="current_period_start">Current period start</label>
                            <input type="datetime-local" class="form-control" id="current_period_start" name="current_period_start" value="<?= e(trim((string) ($subscription['current_period_start'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string) $subscription['current_period_start'])) : date('Y-m-d\TH:i')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="current_period_end">Current period end</label>
                            <input type="datetime-local" class="form-control" id="current_period_end" name="current_period_end" value="<?= e(trim((string) ($subscription['current_period_end'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string) $subscription['current_period_end'])) : '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="grace_ends_at">Grace ends</label>
                            <input type="datetime-local" class="form-control" id="grace_ends_at" name="grace_ends_at" value="<?= e(trim((string) ($subscription['grace_ends_at'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string) $subscription['grace_ends_at'])) : '') ?>">
                        </div>
                        <div class="col-md-6"></div>
                        <div class="col-md-3">
                            <label class="form-label" for="max_branches">Branch limit</label>
                            <input type="number" min="1" class="form-control" id="max_branches" name="max_branches" value="<?= e((string) ($subscription['max_branches'] ?? '')) ?>" placeholder="Unlimited">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="max_users">User limit</label>
                            <input type="number" min="1" class="form-control" id="max_users" name="max_users" value="<?= e((string) ($subscription['max_users'] ?? '')) ?>" placeholder="Unlimited">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="max_products">Product limit</label>
                            <input type="number" min="1" class="form-control" id="max_products" name="max_products" value="<?= e((string) ($subscription['max_products'] ?? '')) ?>" placeholder="Unlimited">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="max_monthly_sales">Monthly sale limit</label>
                            <input type="number" min="1" class="form-control" id="max_monthly_sales" name="max_monthly_sales" value="<?= e((string) ($subscription['max_monthly_sales'] ?? '')) ?>" placeholder="Unlimited">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="subscription_notes">Notes</label>
                            <textarea class="form-control" id="subscription_notes" name="notes" rows="4"><?= e((string) ($subscription['notes'] ?? '')) ?></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Subscription</button>
                    </div>
                </form>
            </section>

            <section class="utility-card">
                <div class="utility-card__header">
                    <div class="workspace-panel__intro">
                        <p class="eyebrow mb-1">Issue Invoice</p>
                        <h4>Generate a manual billing invoice</h4>
                    </div>
                </div>
                <form action="<?= e(url('platform/billing/invoices/create')) ?>" method="post" class="stack-grid" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= e((string) ($company['id'] ?? 0)) ?>">
                    <input type="hidden" name="return_to" value="<?= e('platform/companies/show?id=' . (int) ($company['id'] ?? 0)) ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="invoice_description">Description</label>
                            <input type="text" class="form-control" id="invoice_description" name="description" value="<?= e($subscription !== null ? ((string) ($subscription['plan_name'] ?? $subscription['plan_name_snapshot'] ?? 'Subscription') . ' billing invoice') : 'Subscription billing invoice') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="invoice_subtotal">Subtotal</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="invoice_subtotal" name="subtotal" value="<?= e((string) ($subscription['amount'] ?? '0.00')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="invoice_tax_total">Tax total</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="invoice_tax_total" name="tax_total" value="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="invoice_due_at">Due at</label>
                            <input type="datetime-local" class="form-control" id="invoice_due_at" name="due_at" value="<?= e(date('Y-m-d\TH:i', strtotime('+' . (int) ($platformBillingSettings['invoice_due_days'] ?? 7) . ' days'))) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="invoice_period_start">Period start</label>
                            <input type="datetime-local" class="form-control" id="invoice_period_start" name="period_start" value="<?= e(trim((string) ($subscription['current_period_start'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string) $subscription['current_period_start'])) : date('Y-m-d\TH:i')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="invoice_period_end">Period end</label>
                            <input type="datetime-local" class="form-control" id="invoice_period_end" name="period_end" value="<?= e(trim((string) ($subscription['current_period_end'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string) $subscription['current_period_end'])) : '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="invoice_notes">Notes</label>
                            <textarea class="form-control" id="invoice_notes" name="notes" rows="4"></textarea>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-outline-primary"><i class="bi bi-receipt me-1"></i>Issue Invoice</button>
                    </div>
                </form>
            </section>
        </div>

        <?php if ($paymentsReady): ?>
            <section class="surface-card card-panel table-shell mt-4">
                <div class="workspace-panel__header">
                    <div class="workspace-panel__intro">
                        <p class="eyebrow mb-1">Payment Reviews</p>
                        <h3><i class="bi bi-shield-check me-2"></i>Pending submissions for this company</h3>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr>
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
                                <td colspan="6" class="text-center text-muted py-4">No payment submissions are waiting for review for this company.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pendingPaymentSubmissions as $submission): ?>
                                <tr>
                                    <td>
                                        <div><?= e((string) ($submission['invoice_number'] ?? 'Invoice')) ?></div>
                                        <div class="small text-muted"><?= e((string) ($submission['payer_name'] ?? '')) ?></div>
                                    </td>
                                    <td><?= e((string) ($submission['payment_method_name'] ?? 'Payment method')) ?></td>
                                    <td><?= e(format_money((float) ($submission['amount'] ?? 0), normalize_billing_currency((string) ($submission['currency'] ?? $subscriptionCurrency), $subscriptionCurrency))) ?></td>
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
                                                <input type="hidden" name="return_to" value="<?= e('platform/companies/show?id=' . (int) ($company['id'] ?? 0)) ?>">
                                                <input type="text" class="form-control form-control-sm" name="review_note" placeholder="Approval note (optional)">
                                                <button type="submit" class="btn btn-sm btn-primary">Approve and Post</button>
                                            </form>
                                            <form action="<?= e(url('platform/billing/payments/submissions/reject')) ?>" method="post" class="d-flex flex-column gap-2" data-loading-form>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="submission_id" value="<?= e((string) ($submission['id'] ?? 0)) ?>">
                                                <input type="hidden" name="return_to" value="<?= e('platform/companies/show?id=' . (int) ($company['id'] ?? 0)) ?>">
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

        <section class="surface-card card-panel table-shell mt-4">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Invoice History</p>
                    <h3><i class="bi bi-cash-coin me-2"></i>Collections and invoice actions</h3>
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
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($billingInvoices === []): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No billing invoices have been created for this company.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($billingInvoices as $invoice): ?>
                            <tr>
                                <td>
                                    <div><?= e((string) ($invoice['invoice_number'] ?? 'Invoice')) ?></div>
                                    <div class="small text-muted"><?= e((string) ($invoice['description'] ?? '')) ?></div>
                                </td>
                                <td><span class="status-pill status-pill--<?= e($billingStatusTone((string) ($invoice['status'] ?? 'issued'))) ?>"><?= e(ucfirst((string) ($invoice['status'] ?? 'issued'))) ?></span></td>
                                <td><?= e(format_money((float) ($invoice['total'] ?? 0), normalize_billing_currency((string) ($invoice['currency'] ?? $subscriptionCurrency), $subscriptionCurrency))) ?></td>
                                <td><?= e(format_money((float) ($invoice['balance_due'] ?? 0), normalize_billing_currency((string) ($invoice['currency'] ?? $subscriptionCurrency), $subscriptionCurrency))) ?></td>
                                <td><?= e($formatDate((string) ($invoice['due_at'] ?? ''), 'Not set')) ?></td>
                                <td>
                                    <div class="d-flex flex-column gap-2">
                                        <a href="<?= e(url('platform/billing/invoices/show?id=' . (int) ($invoice['id'] ?? 0))) ?>" class="btn btn-sm btn-outline-secondary">View Invoice</a>
                                        <?php if (in_array((string) ($invoice['status'] ?? ''), ['issued', 'overdue'], true) && (float) ($invoice['balance_due'] ?? 0) > 0): ?>
                                            <?php $invoicePaymentMethods = $paymentsReady ? $paymentMethodsForCurrency((string) ($invoice['currency'] ?? $subscriptionCurrency)) : []; ?>
                                            <form action="<?= e(url('platform/billing/invoices/payments/store')) ?>" method="post" class="d-flex flex-column gap-2" data-loading-form>
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">
                                                <input type="hidden" name="return_to" value="<?= e('platform/companies/show?id=' . (int) ($company['id'] ?? 0)) ?>">
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
                                                        <input type="hidden" name="return_to" value="<?= e('platform/companies/show?id=' . (int) ($company['id'] ?? 0)) ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning">Mark Overdue</button>
                                                    </form>
                                                <?php endif; ?>
                                                <?php if ((float) ($invoice['amount_paid'] ?? 0) <= 0): ?>
                                                    <form action="<?= e(url('platform/billing/invoices/status')) ?>" method="post" class="m-0" data-loading-form>
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="invoice_id" value="<?= e((string) ($invoice['id'] ?? 0)) ?>">
                                                        <input type="hidden" name="status" value="void">
                                                        <input type="hidden" name="return_to" value="<?= e('platform/companies/show?id=' . (int) ($company['id'] ?? 0)) ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">Void</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="small text-muted">No collection action required.</span>
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

        <script>
            (() => {
                const planSelect = document.getElementById('billing_plan_id');
                if (!planSelect) {
                    return;
                }

                const fieldMap = {
                    amount: document.getElementById('billing_amount'),
                    currency: document.getElementById('billing_currency'),
                    cycle: document.getElementById('billing_cycle'),
                    maxBranches: document.getElementById('max_branches'),
                    maxUsers: document.getElementById('max_users'),
                    maxProducts: document.getElementById('max_products'),
                    maxMonthlySales: document.getElementById('max_monthly_sales'),
                };

                const syncPlanDefaults = () => {
                    const selectedOption = planSelect.options[planSelect.selectedIndex];
                    if (!selectedOption || selectedOption.value === '') {
                        return;
                    }

                    if (fieldMap.amount) fieldMap.amount.value = selectedOption.dataset.planAmount || fieldMap.amount.value;
                    if (fieldMap.currency) fieldMap.currency.value = selectedOption.dataset.planCurrency || fieldMap.currency.value;
                    if (fieldMap.cycle) fieldMap.cycle.value = selectedOption.dataset.planCycle || fieldMap.cycle.value;
                    if (fieldMap.maxBranches) fieldMap.maxBranches.value = selectedOption.dataset.planMaxBranches || '';
                    if (fieldMap.maxUsers) fieldMap.maxUsers.value = selectedOption.dataset.planMaxUsers || '';
                    if (fieldMap.maxProducts) fieldMap.maxProducts.value = selectedOption.dataset.planMaxProducts || '';
                    if (fieldMap.maxMonthlySales) fieldMap.maxMonthlySales.value = selectedOption.dataset.planMaxMonthlySales || '';
                };

                planSelect.addEventListener('change', syncPlanDefaults);
            })();
        </script>
    <?php endif; ?>
</section>

<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Support Access</p>
            <h3><i class="bi bi-person-workspace me-2"></i>Impersonation Session</h3>
        </div>
    </div>
    <?php if ($supportAccessTarget === null): ?>
        <div class="alert alert-warning rounded-4 mb-0">
            No active verified user is available for support access in this company. Activate or verify an internal user first.
        </div>
    <?php else: ?>
        <div class="content-grid">
            <section class="utility-card">
                <div class="utility-card__header">
                    <div class="workspace-panel__intro">
                        <p class="eyebrow mb-1">Target Account</p>
                        <h4><?= e((string) ($supportAccessTarget['full_name'] ?? 'Tenant User')) ?></h4>
                    </div>
                </div>
                <div class="stack-grid">
                    <div class="record-card">
                        <div class="small text-muted">Role</div>
                        <div class="fw-semibold"><?= e((string) ($supportAccessTarget['role_name'] ?? 'User')) ?></div>
                    </div>
                    <div class="record-card">
                        <div class="small text-muted">Email</div>
                        <div class="fw-semibold"><?= e((string) ($supportAccessTarget['email'] ?? '')) ?></div>
                    </div>
                    <div class="record-card">
                        <div class="small text-muted">Last Login</div>
                        <div class="fw-semibold"><?= e($formatDate((string) ($supportAccessTarget['last_login_at'] ?? ''), 'Never')) ?></div>
                    </div>
                </div>
            </section>

            <section class="utility-card">
                <div class="utility-card__header">
                    <div class="workspace-panel__intro">
                        <p class="eyebrow mb-1">Start Support Session</p>
                        <h4>Reason is required and audit logged</h4>
                    </div>
                </div>
                <form action="<?= e(url('platform/companies/impersonate')) ?>" method="post" class="stack-grid" data-loading-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= e((string) ($company['id'] ?? 0)) ?>">
                    <div class="field-stack">
                        <label class="form-label" for="support_reason">Support reason</label>
                        <textarea id="support_reason" name="reason" class="form-control" rows="4" maxlength="255" placeholder="Example: investigating owner report that POS checkout is failing after activation." required></textarea>
                        <div class="small text-muted mt-1">Keep this specific. The reason is recorded in the audit trail for accountability.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Open Tenant Workspace
                    </button>
                </form>
            </section>
        </div>
    <?php endif; ?>
</section>

<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Branch Footprint</p>
            <h3><i class="bi bi-shop me-2"></i>Branches</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><?= e((string) count($branches)) ?> configured</span>
        </div>
    </div>
    <div class="stack-grid">
        <?php if ($branches === []): ?>
            <div class="empty-state">No branches are configured for this company.</div>
        <?php else: ?>
            <?php foreach ($branches as $branch): ?>
                <article class="record-card">
                    <div class="record-card__header">
                        <div class="workspace-panel__intro">
                            <h4><?= e((string) ($branch['name'] ?? 'Branch')) ?></h4>
                            <div class="inline-note"><?= e((string) ($branch['code'] ?? '')) ?></div>
                        </div>
                        <div class="record-card__meta">
                            <span class="badge-soft"><?= e((int) ($branch['is_default'] ?? 0) === 1 ? 'Default Branch' : ucfirst((string) ($branch['status'] ?? 'inactive'))) ?></span>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge-soft"><?= e((string) ($branch['total_users'] ?? 0)) ?> users</span>
                        <span class="badge-soft"><?= e((string) ($branch['total_products'] ?? 0)) ?> products</span>
                        <?php if (trim((string) ($branch['email'] ?? '')) !== ''): ?>
                            <span class="badge-soft"><?= e((string) $branch['email']) ?></span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="surface-card card-panel table-shell mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">User Access</p>
            <h3><i class="bi bi-people me-2"></i>Users</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><?= e((string) count($users)) ?> total</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Branch</th>
                <th>Status</th>
                <th>Verified</th>
                <th>Last Login</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">No users are attached to this company yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($users as $tenantUser): ?>
                    <tr>
                        <td>
                            <div><?= e((string) ($tenantUser['full_name'] ?? 'User')) ?></div>
                            <div class="small text-muted"><?= e((string) ($tenantUser['email'] ?? '')) ?></div>
                        </td>
                        <td><span class="badge-soft"><?= e((string) ($tenantUser['role_name'] ?? 'User')) ?></span></td>
                        <td><?= e((string) ($tenantUser['branch_name'] ?? 'Unassigned')) ?></td>
                        <td><span class="status-pill status-pill--<?= e($userStatusTone((string) ($tenantUser['status'] ?? 'inactive'))) ?>"><?= e(ucfirst((string) ($tenantUser['status'] ?? 'inactive'))) ?></span></td>
                        <td><span class="badge-soft"><?= e(trim((string) ($tenantUser['email_verified_at'] ?? '')) !== '' ? 'Verified' : 'Pending') ?></span></td>
                        <td><?= e($formatDate((string) ($tenantUser['last_login_at'] ?? ''), 'Never')) ?></td>
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
            <p class="eyebrow mb-1">Recent Activity</p>
            <h3><i class="bi bi-clipboard-data me-2"></i>Audit Trail</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>When</th>
                <th>Actor</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Description</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($recentActivity === []): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">No audit events were found for this company.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recentActivity as $log): ?>
                    <tr>
                        <td><?= e($formatDate((string) ($log['created_at'] ?? ''), 'Just now')) ?></td>
                        <td>
                            <div><?= e((string) ($log['user_name'] ?? 'System')) ?></div>
                            <?php if (trim((string) ($log['actor_company_name'] ?? '')) !== ''): ?>
                                <div class="small text-muted"><?= e((string) $log['actor_company_name']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge-soft"><?= e(ucwords(str_replace('_', ' ', (string) ($log['action'] ?? 'event')))) ?></span></td>
                        <td><?= e((string) ($log['entity_type'] ?? 'system')) ?></td>
                        <td><?= e((string) ($log['description'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
