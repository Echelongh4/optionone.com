<?php
$summary = array_merge([
    'total_companies' => 0,
    'active_companies' => 0,
    'inactive_companies' => 0,
    'new_companies_30d' => 0,
    'total_branches' => 0,
    'total_users' => 0,
    'companies_pending_owner_verification' => 0,
], $summary ?? []);
$recentCompanies = is_array($recentCompanies ?? null) ? $recentCompanies : [];
$attentionCompanies = is_array($attentionCompanies ?? null) ? $attentionCompanies : [];
$recentActivity = is_array($recentActivity ?? null) ? $recentActivity : [];
$platformAdminSummary = array_merge([
    'total' => 0,
    'active' => 0,
    'pending_verification' => 0,
    'database_managed' => 0,
    'env_managed' => 0,
], $platformAdminSummary ?? []);
$billingReady = (bool) ($billingReady ?? false);
$billingPlanSummary = array_merge([
    'total_plans' => 0,
    'active_plans' => 0,
    'featured_plans' => 0,
    'default_plans' => 0,
], $billingPlanSummary ?? []);
$billingSubscriptionSummary = array_merge([
    'total_subscriptions' => 0,
    'active_subscriptions' => 0,
    'trialing_subscriptions' => 0,
    'past_due_subscriptions' => 0,
    'suspended_subscriptions' => 0,
    'monthly_recurring_revenue' => 0,
], $billingSubscriptionSummary ?? []);
$billingInvoiceSummary = array_merge([
    'total_invoices' => 0,
    'issued_invoices' => 0,
    'overdue_invoices' => 0,
    'paid_invoices' => 0,
    'outstanding_balance' => 0,
    'paid_this_month' => 0,
], $billingInvoiceSummary ?? []);
$platformOverviewSettings = array_merge([
    'currency' => default_currency_code(),
    'tenant_default_currency' => default_currency_code(),
], $platformOverviewSettings ?? []);
$platformDisplayCurrency = normalize_billing_currency((string) ($platformOverviewSettings['currency'] ?? default_currency_code()), default_currency_code());
$activationRate = (int) $summary['total_companies'] > 0
    ? round(((int) $summary['active_companies'] / (int) $summary['total_companies']) * 100, 1)
    : 0;

$statusTone = static function (string $status): string {
    return $status === 'active' ? 'success' : 'danger';
};

$ownerStateLabel = static function (array $company): string {
    if (trim((string) ($company['owner_email'] ?? '')) === '') {
        return 'Owner missing';
    }

    if ((int) ($company['pending_owner_verification_count'] ?? 0) > 0) {
        return 'Verification pending';
    }

    return 'Owner verified';
};

$activityLabel = static function (?string $value, string $fallback = 'No login yet'): string {
    $value = trim((string) $value);

    return $value !== '' ? date('M d, Y H:i', strtotime($value)) : $fallback;
};
?>

<section class="dashboard-hero surface-card card-panel">
    <div class="dashboard-hero__main">
        <p class="eyebrow mb-2">Portfolio Command</p>
        <h2 class="dashboard-hero__title">Manage tenant onboarding, access, and support from one platform console.</h2>
        <p class="dashboard-hero__copy">
            Track how many companies are live, which owners still need verification, and where support action is required before tenants can operate smoothly.
        </p>
        <div class="dashboard-hero__meta">
            <span class="badge-soft"><i class="bi bi-buildings me-1"></i><?= e((string) $summary['total_companies']) ?> companies</span>
            <span class="badge-soft"><i class="bi bi-check2-circle me-1"></i><?= e((string) $activationRate) ?>% active</span>
            <span class="badge-soft"><i class="bi bi-envelope-exclamation me-1"></i><?= e((string) $summary['companies_pending_owner_verification']) ?> pending verification</span>
            <span class="badge-soft"><i class="bi bi-cash-coin me-1"></i><?= e($platformDisplayCurrency) ?> control currency</span>
        </div>
    </div>

    <div class="dashboard-hero__rail">
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Active Companies</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $summary['active_companies']) ?></strong>
            <span class="dashboard-hero-stat__meta">Live tenant workspaces</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Needs Attention</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) count($attentionCompanies)) ?></strong>
            <span class="dashboard-hero-stat__meta">Verification, inactivity, or missing owner</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">New in 30 Days</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $summary['new_companies_30d']) ?></strong>
            <span class="dashboard-hero-stat__meta">Recent company signups</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Tenant Users</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $summary['total_users']) ?></strong>
            <span class="dashboard-hero-stat__meta">Across all company workspaces</span>
        </article>
    </div>
</section>

<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Total Companies</span><span>Portfolio</span></div>
        <h3><?= e((string) $summary['total_companies']) ?></h3>
        <div class="text-muted">Every registered tenant workspace.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Active</span><span>Accessible</span></div>
        <h3><?= e((string) $summary['active_companies']) ?></h3>
        <div class="text-muted">Companies currently allowed to sign in.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Suspended</span><span>Blocked</span></div>
        <h3><?= e((string) $summary['inactive_companies']) ?></h3>
        <div class="text-muted">Workspaces paused by platform administration.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Owner Verification</span><span>Queue</span></div>
        <h3><?= e((string) $summary['companies_pending_owner_verification']) ?></h3>
        <div class="text-muted">Companies waiting on owner email verification.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Branches</span><span>Footprint</span></div>
        <h3><?= e((string) $summary['total_branches']) ?></h3>
        <div class="text-muted">Locations configured across tenants.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Platform Admins</span><span>Access</span></div>
        <h3><?= e((string) $platformAdminSummary['total']) ?></h3>
        <div class="text-muted"><?= e((string) $platformAdminSummary['active']) ?> active, <?= e((string) $platformAdminSummary['pending_verification']) ?> pending verification.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>MRR</span><span>Billing</span></div>
        <h3><?= e(format_money((float) $billingSubscriptionSummary['monthly_recurring_revenue'], $platformDisplayCurrency)) ?></h3>
        <div class="text-muted">
            <?php if ($billingReady): ?>
                <?= e((string) $billingSubscriptionSummary['active_subscriptions']) ?> active, <?= e((string) $billingSubscriptionSummary['past_due_subscriptions']) ?> past due. Displayed in <?= e($platformDisplayCurrency) ?>.
            <?php else: ?>
                Apply the billing migration to unlock subscription metrics.
            <?php endif; ?>
        </div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>New 30 Days</span><span>Growth</span></div>
        <h3><?= e((string) $summary['new_companies_30d']) ?></h3>
        <div class="text-muted">Fresh registrations in the last month.</div>
    </section>
</div>

<section class="surface-card card-panel dashboard-toolbar">
    <div class="dashboard-toolbar__copy">
        <p class="eyebrow mb-1">Platform Actions</p>
        <h3 class="mb-1">Move from portfolio view into tenant detail quickly.</h3>
        <p class="text-muted mb-0">Use the company directory for filtering, support follow-up, suspension control, and onboarding recovery.</p>
    </div>
    <div class="dashboard-toolbar__actions">
        <a href="<?= e(url('platform/companies')) ?>" class="btn btn-primary"><i class="bi bi-buildings me-1"></i>Manage Companies</a>
        <a href="<?= e(url('platform/settings')) ?>" class="btn btn-outline-secondary"><i class="bi bi-sliders me-1"></i>General Settings</a>
        <a href="<?= e(url('platform/billing')) ?>" class="btn btn-outline-secondary"><i class="bi bi-credit-card me-1"></i>Manage Billing</a>
        <a href="<?= e(url('platform/admin-users')) ?>" class="btn btn-outline-secondary"><i class="bi bi-people me-1"></i>Manage Admin Users</a>
        <a href="<?= e(url('platform/companies?onboarding=pending_owner_verification')) ?>" class="btn btn-outline-secondary"><i class="bi bi-envelope-check me-1"></i>Pending Verification</a>
        <a href="<?= e(url('platform/companies?status=inactive')) ?>" class="btn btn-outline-secondary"><i class="bi bi-pause-circle me-1"></i>Suspended Companies</a>
    </div>
</section>

<div class="content-grid">
    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Attention Queue</p>
                <h4>Companies needing intervention</h4>
            </div>
        </div>
        <div class="stack-grid">
            <?php if ($attentionCompanies === []): ?>
                <div class="empty-state">No urgent company issues are currently flagged.</div>
            <?php else: ?>
                <?php foreach ($attentionCompanies as $company): ?>
                    <article class="record-card">
                        <div class="record-card__header">
                            <div class="workspace-panel__intro">
                                <h4><?= e((string) ($company['name'] ?? 'Company')) ?></h4>
                                <div class="inline-note"><?= e((string) ($company['owner_email'] ?? 'No owner email')) ?></div>
                            </div>
                            <div class="record-card__meta">
                                <span class="status-pill status-pill--<?= e($statusTone((string) ($company['status'] ?? 'inactive'))) ?>"><?= e(ucfirst((string) ($company['status'] ?? 'inactive'))) ?></span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge-soft"><?= e($ownerStateLabel($company)) ?></span>
                            <span class="badge-soft"><?= e((string) ($company['branch_count'] ?? 0)) ?> branches</span>
                            <span class="badge-soft"><?= e((string) ($company['user_count'] ?? 0)) ?> users</span>
                        </div>
                        <div class="small text-muted mb-3">Last login: <?= e($activityLabel((string) ($company['last_login_at'] ?? ''), 'No tenant activity yet')) ?></div>
                        <a href="<?= e(url('platform/companies/show?id=' . (int) $company['id'])) ?>" class="btn btn-sm btn-outline-primary">Open Company</a>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Recent Platform Activity</p>
                <h4>Latest audit events</h4>
            </div>
        </div>
        <div class="stack-grid">
            <?php if ($recentActivity === []): ?>
                <div class="empty-state">No platform activity recorded yet.</div>
            <?php else: ?>
                <?php foreach ($recentActivity as $log): ?>
                    <article class="record-card">
                        <div class="record-card__header">
                            <div class="workspace-panel__intro">
                                <h4><?= e(ucwords(str_replace('_', ' ', (string) ($log['action'] ?? 'event')))) ?></h4>
                                <div class="inline-note"><?= e((string) ($log['user_name'] ?? 'System')) ?></div>
                            </div>
                            <div class="record-card__meta">
                                <span class="badge-soft"><?= e((string) ($log['entity_type'] ?? 'system')) ?></span>
                            </div>
                        </div>
                        <div class="small text-muted"><?= e((string) ($log['description'] ?? '')) ?></div>
                        <div class="small text-muted mt-2"><?= e($activityLabel((string) ($log['created_at'] ?? ''), 'Just now')) ?></div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="surface-card card-panel table-shell">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Recent Signups</p>
            <h3><i class="bi bi-buildings me-2"></i>Newest companies</h3>
        </div>
        <div class="workspace-panel__meta">
            <a href="<?= e(url('platform/companies')) ?>" class="btn btn-sm btn-outline-primary">View Full Directory</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Company</th>
                <th>Owner</th>
                <th>Onboarding</th>
                <th>Access</th>
                <th>Last Activity</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recentCompanies as $company): ?>
                <tr>
                    <td>
                        <div class="entity-copy">
                            <div class="entity-title"><?= e((string) ($company['name'] ?? 'Company')) ?></div>
                            <div class="entity-subtitle"><?= e((string) ($company['slug'] ?? '')) ?></div>
                        </div>
                    </td>
                    <td>
                        <div><?= e((string) ($company['owner_name'] ?? 'No owner assigned')) ?></div>
                        <div class="small text-muted"><?= e((string) ($company['owner_email'] ?? '')) ?></div>
                    </td>
                    <td><span class="badge-soft"><?= e($ownerStateLabel($company)) ?></span></td>
                    <td><span class="status-pill status-pill--<?= e($statusTone((string) ($company['status'] ?? 'inactive'))) ?>"><?= e(ucfirst((string) ($company['status'] ?? 'inactive'))) ?></span></td>
                    <td><?= e($activityLabel((string) ($company['last_login_at'] ?? ''), 'No login yet')) ?></td>
                    <td class="text-end"><a href="<?= e(url('platform/companies/show?id=' . (int) $company['id'])) ?>" class="btn btn-sm btn-outline-secondary">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
