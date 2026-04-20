<?php
$generalErrors = is_array($generalErrors ?? null) ? $generalErrors : [];
$settings = is_array($settings ?? null) ? $settings : [];
$summary = is_array($summary ?? null) ? $summary : [];
$supportedCurrencies = is_array($supportedCurrencies ?? null) ? $supportedCurrencies : billing_currency_options();
$applyToExistingWorkspaces = (bool) ($applyToExistingWorkspaces ?? false);
$defaultMultiBranchEnabled = filter_var((string) ($settings['tenant_default_multi_branch_enabled'] ?? 'false'), FILTER_VALIDATE_BOOLEAN);
$defaultLowStockEnabled = filter_var((string) ($settings['tenant_default_email_low_stock_alerts_enabled'] ?? 'true'), FILTER_VALIDATE_BOOLEAN);
$defaultDailySummaryEnabled = filter_var((string) ($settings['tenant_default_email_daily_summary_enabled'] ?? 'true'), FILTER_VALIDATE_BOOLEAN);
?>

<section class="dashboard-hero surface-card card-panel">
    <div class="dashboard-hero__main">
        <p class="eyebrow mb-2">Platform Settings</p>
        <h2 class="dashboard-hero__title">Set the control-plane identity and define the defaults every tenant workspace should inherit.</h2>
        <p class="dashboard-hero__copy">
            These settings give the platform admin one place to manage shared defaults. Save them for future workspaces only, or sync the tenant defaults into all existing company workspaces when you need a global rollout.
        </p>
        <div class="dashboard-hero__meta">
            <span class="badge-soft"><i class="bi bi-buildings me-1"></i><?= e((string) ($summary['managed_companies'] ?? 0)) ?> managed companies</span>
            <span class="badge-soft"><i class="bi bi-cash-coin me-1"></i><?= e((string) ($summary['tenant_default_currency'] ?? config('app.currency', 'GHS'))) ?> tenant default</span>
            <span class="badge-soft"><i class="bi bi-envelope-check me-1"></i><?= !empty($summary['tenant_default_mail_configured']) ? 'Mail defaults configured' : 'Mail defaults not configured' ?></span>
        </div>
    </div>

    <div class="dashboard-hero__rail">
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Managed</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) ($summary['managed_companies'] ?? 0)) ?></strong>
            <span class="dashboard-hero-stat__meta"><?= e((string) ($summary['active_companies'] ?? 0)) ?> active companies</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Platform Currency</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) ($summary['platform_currency'] ?? config('app.currency', 'GHS'))) ?></strong>
            <span class="dashboard-hero-stat__meta">Control-plane display currency</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Tenant Currency</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) ($summary['tenant_default_currency'] ?? config('app.currency', 'GHS'))) ?></strong>
            <span class="dashboard-hero-stat__meta">Applied to new workspaces</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Default Mail</span>
            <strong class="dashboard-hero-stat__value"><?= !empty($summary['tenant_default_mail_configured']) ? 'Ready' : 'Pending' ?></strong>
            <span class="dashboard-hero-stat__meta">Tenant SMTP inheritance</span>
        </article>
    </div>
</section>

<section class="surface-card card-panel dashboard-toolbar">
    <div class="dashboard-toolbar__copy">
        <p class="eyebrow mb-1">Platform Navigation</p>
        <h3 class="mb-1">Keep platform identity, billing policy, and company operations aligned.</h3>
        <p class="text-muted mb-0">General settings define the baseline. Billing and company pages remain the place for financial operations and workspace-level intervention.</p>
    </div>
    <div class="dashboard-toolbar__actions">
        <a href="<?= e(url('platform')) ?>" class="btn btn-outline-secondary"><i class="bi bi-grid-1x2 me-1"></i>Overview</a>
        <a href="<?= e(url('platform/billing')) ?>" class="btn btn-outline-secondary"><i class="bi bi-credit-card me-1"></i>Billing</a>
        <a href="<?= e(url('platform/companies')) ?>" class="btn btn-outline-secondary"><i class="bi bi-buildings me-1"></i>Companies</a>
        <a href="<?= e(url('platform/admin-users')) ?>" class="btn btn-outline-secondary"><i class="bi bi-people me-1"></i>Admin Users</a>
    </div>
</section>

<div class="metric-grid">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Platform Name</span><span>Identity</span></div>
        <h3><?= e((string) ($settings['business_name'] ?? config('app.name', 'NovaPOS'))) ?></h3>
        <div class="text-muted">Used for the platform workspace profile and outgoing defaults.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Platform Email</span><span>Support</span></div>
        <h3><?= e(trim((string) ($settings['business_email'] ?? '')) !== '' ? (string) $settings['business_email'] : 'Not set') ?></h3>
        <div class="text-muted">Primary platform contact stored on the internal control workspace.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Receipt Defaults</span><span>Tenant</span></div>
        <h3><?= e((string) ($settings['tenant_default_barcode_format'] ?? 'CODE128')) ?></h3>
        <div class="text-muted">Barcode format and receipt copy applied to new tenants.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Rollout Mode</span><span>Sync</span></div>
        <h3><?= $applyToExistingWorkspaces ? 'Queued' : 'Future Only' ?></h3>
        <div class="text-muted">Enable the rollout checkbox below to push defaults into existing companies.</div>
    </section>
</div>

<section class="surface-card card-panel workspace-panel">
    <?php if ($generalErrors !== []): ?>
        <div class="alert alert-danger rounded-4 mb-0">
            <strong>Please fix the platform settings errors and try again.</strong>
        </div>
    <?php endif; ?>

    <form action="<?= e(url('platform/settings/update')) ?>" method="post" class="workspace-panel" data-loading-form>
        <?= csrf_field() ?>

        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Platform Profile</p>
                    <h3><i class="bi bi-globe2 me-2"></i>Control Plane Identity</h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft">Updates the internal platform workspace</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label" for="platform_business_name">Platform name</label>
                    <input type="text" class="form-control" id="platform_business_name" name="business_name" value="<?= e((string) ($settings['business_name'] ?? '')) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label" for="platform_business_email">Platform email</label>
                    <input type="email" class="form-control" id="platform_business_email" name="business_email" value="<?= e((string) ($settings['business_email'] ?? '')) ?>">
                </div>
                <div class="field-stack">
                    <label class="form-label" for="platform_business_phone">Platform phone</label>
                    <input type="text" class="form-control" id="platform_business_phone" name="business_phone" value="<?= e((string) ($settings['business_phone'] ?? '')) ?>">
                </div>
                <div class="field-stack">
                    <label class="form-label" for="platform_currency">Platform currency</label>
                    <select class="form-select" id="platform_currency" name="currency" required>
                        <?php foreach ($supportedCurrencies as $currency): ?>
                            <option value="<?= e($currency) ?>" <?= (string) ($settings['currency'] ?? '') === $currency ? 'selected' : '' ?>><?= e($currency) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack field-span-full">
                    <label class="form-label" for="platform_business_address">Platform address</label>
                    <textarea class="form-control" id="platform_business_address" name="business_address" rows="3"><?= e((string) ($settings['business_address'] ?? '')) ?></textarea>
                </div>
            </div>
        </section>

        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Tenant Defaults</p>
                    <h3><i class="bi bi-diagram-3 me-2"></i>Workspace Baseline</h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft">Used when a company workspace is created</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_currency">Default tenant currency</label>
                    <select class="form-select" id="tenant_default_currency" name="tenant_default_currency" required>
                        <?php foreach ($supportedCurrencies as $currency): ?>
                            <option value="<?= e($currency) ?>" <?= (string) ($settings['tenant_default_currency'] ?? '') === $currency ? 'selected' : '' ?>><?= e($currency) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_barcode_format">Default barcode format</label>
                    <select class="form-select" id="tenant_default_barcode_format" name="tenant_default_barcode_format">
                        <?php foreach (['CODE128', 'CODE39', 'EAN13', 'UPC'] as $format): ?>
                            <option value="<?= e($format) ?>" <?= (string) ($settings['tenant_default_barcode_format'] ?? 'CODE128') === $format ? 'selected' : '' ?>><?= e($format) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack field-span-full">
                    <label class="form-label" for="tenant_default_receipt_header">Default receipt header</label>
                    <textarea class="form-control" id="tenant_default_receipt_header" name="tenant_default_receipt_header" rows="3"><?= e((string) ($settings['tenant_default_receipt_header'] ?? '')) ?></textarea>
                </div>
                <div class="field-stack field-span-full">
                    <label class="form-label" for="tenant_default_receipt_footer">Default receipt footer</label>
                    <textarea class="form-control" id="tenant_default_receipt_footer" name="tenant_default_receipt_footer" rows="3"><?= e((string) ($settings['tenant_default_receipt_footer'] ?? '')) ?></textarea>
                </div>
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_ops_email_recipient_scope">Default recipient scope</label>
                    <select class="form-select" id="tenant_default_ops_email_recipient_scope" name="tenant_default_ops_email_recipient_scope">
                        <option value="business" <?= (string) ($settings['tenant_default_ops_email_recipient_scope'] ?? 'business_and_team') === 'business' ? 'selected' : '' ?>>Business email only</option>
                        <option value="team" <?= (string) ($settings['tenant_default_ops_email_recipient_scope'] ?? 'business_and_team') === 'team' ? 'selected' : '' ?>>Admin and manager team only</option>
                        <option value="business_and_team" <?= (string) ($settings['tenant_default_ops_email_recipient_scope'] ?? 'business_and_team') === 'business_and_team' ? 'selected' : '' ?>>Business email and team</option>
                    </select>
                </div>
                <div class="field-stack field-span-full">
                    <label class="form-label" for="tenant_default_ops_email_additional_recipients">Default extra recipients</label>
                    <textarea class="form-control" id="tenant_default_ops_email_additional_recipients" name="tenant_default_ops_email_additional_recipients" rows="3" placeholder="ops@example.com, owner@example.com"><?= e((string) ($settings['tenant_default_ops_email_additional_recipients'] ?? '')) ?></textarea>
                    <div class="small text-muted mt-1">Optional comma-separated recipients appended to tenant operations emails.</div>
                </div>
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="tenant_default_multi_branch_enabled" name="tenant_default_multi_branch_enabled" value="1" <?= $defaultMultiBranchEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tenant_default_multi_branch_enabled">Enable multi-branch by default</label>
                    </div>
                </div>
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="tenant_default_email_low_stock_alerts_enabled" name="tenant_default_email_low_stock_alerts_enabled" value="1" <?= $defaultLowStockEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tenant_default_email_low_stock_alerts_enabled">Enable low-stock email alerts by default</label>
                    </div>
                </div>
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="tenant_default_email_daily_summary_enabled" name="tenant_default_email_daily_summary_enabled" value="1" <?= $defaultDailySummaryEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tenant_default_email_daily_summary_enabled">Enable daily summary emails by default</label>
                    </div>
                </div>
                <div class="field-stack field-span-full">
                    <div class="alert alert-info rounded-4 mb-0">
                        These values are written into new workspaces automatically. They do not overwrite existing tenant settings unless you enable the rollout option below.
                    </div>
                </div>
            </div>
        </section>

        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Mail Defaults</p>
                    <h3><i class="bi bi-envelope-at me-2"></i>Tenant SMTP Baseline</h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft">Optional shared defaults for tenant delivery</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_mail_host">Default SMTP host</label>
                    <input type="text" class="form-control" id="tenant_default_mail_host" name="tenant_default_mail_host" value="<?= e((string) ($settings['tenant_default_mail_host'] ?? '')) ?>" placeholder="smtp.example.com">
                </div>
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_mail_port">Default SMTP port</label>
                    <input type="number" class="form-control" id="tenant_default_mail_port" name="tenant_default_mail_port" min="1" max="65535" value="<?= e((string) ($settings['tenant_default_mail_port'] ?? '587')) ?>" placeholder="587">
                </div>
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_mail_encryption">Default encryption</label>
                    <select class="form-select" id="tenant_default_mail_encryption" name="tenant_default_mail_encryption">
                        <option value="tls" <?= (string) ($settings['tenant_default_mail_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                        <option value="ssl" <?= (string) ($settings['tenant_default_mail_encryption'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL/TLS (465)</option>
                        <option value="none" <?= (string) ($settings['tenant_default_mail_encryption'] ?? 'tls') === 'none' ? 'selected' : '' ?>>No encryption</option>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_mail_username">Default SMTP username</label>
                    <input type="text" class="form-control" id="tenant_default_mail_username" name="tenant_default_mail_username" value="<?= e((string) ($settings['tenant_default_mail_username'] ?? '')) ?>" placeholder="mailer@example.com">
                </div>
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_mail_password">Default SMTP password</label>
                    <input type="password" class="form-control" id="tenant_default_mail_password" name="tenant_default_mail_password" value="" placeholder="Leave blank to keep the saved password" autocomplete="new-password">
                    <div class="small text-muted mt-1">Leave this blank to keep the current stored default password.</div>
                </div>
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_mail_from_address">Default sender email</label>
                    <input type="email" class="form-control" id="tenant_default_mail_from_address" name="tenant_default_mail_from_address" value="<?= e((string) ($settings['tenant_default_mail_from_address'] ?? '')) ?>" placeholder="noreply@example.com">
                </div>
                <div class="field-stack">
                    <label class="form-label" for="tenant_default_mail_from_name">Default sender name</label>
                    <input type="text" class="form-control" id="tenant_default_mail_from_name" name="tenant_default_mail_from_name" value="<?= e((string) ($settings['tenant_default_mail_from_name'] ?? '')) ?>" placeholder="Platform Mailer">
                </div>
                <div class="field-stack field-span-full">
                    <div class="alert alert-info rounded-4 mb-0">
                        Save SMTP defaults here if most tenants should start with the same delivery configuration. Tenants can still override their own settings later.
                    </div>
                </div>
            </div>
        </section>

        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Rollout</p>
                    <h3><i class="bi bi-arrow-repeat me-2"></i>Apply Defaults Broadly</h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft">Optional overwrite for current company workspaces</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="check-card field-span-full">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="apply_to_existing_workspaces" name="apply_to_existing_workspaces" value="1" <?= $applyToExistingWorkspaces ? 'checked' : '' ?>>
                        <label class="form-check-label" for="apply_to_existing_workspaces">Apply the tenant default settings above to all existing company workspaces now</label>
                    </div>
                </div>
                <div class="field-stack field-span-full">
                    <div class="alert alert-warning rounded-4 mb-0">
                        This rollout updates the tenant settings keys for every current company except the platform workspace. It does not change company names, company contact details, plans, invoices, or billing records.
                    </div>
                </div>
            </div>
        </section>

        <div class="workspace-panel__actions justify-content-between flex-wrap gap-2">
            <a href="<?= e(url('platform/billing')) ?>" class="btn btn-outline-secondary"><i class="bi bi-credit-card me-1"></i>Open Billing Settings</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save General Settings</button>
        </div>
    </form>
</section>
