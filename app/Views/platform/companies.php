<?php
$filters = array_merge([
    'search' => '',
    'status' => '',
    'onboarding' => '',
    'activity' => '',
], $filters ?? []);
$companies = is_array($companies ?? null) ? $companies : [];

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

$ownerStateTone = static function (array $company): string {
    if (trim((string) ($company['owner_email'] ?? '')) === '') {
        return 'warning';
    }

    return (int) ($company['pending_owner_verification_count'] ?? 0) > 0 ? 'warning' : 'success';
};

$activityLabel = static function (?string $value): string {
    $value = trim((string) $value);

    return $value !== '' ? date('M d, Y H:i', strtotime($value)) : 'No login yet';
};

$activeMatches = count(array_filter($companies, static fn (array $company): bool => (string) ($company['status'] ?? 'inactive') === 'active'));
$pendingMatches = count(array_filter($companies, static fn (array $company): bool => (int) ($company['pending_owner_verification_count'] ?? 0) > 0));
?>

<section class="dashboard-hero surface-card card-panel">
    <div class="dashboard-hero__main">
        <p class="eyebrow mb-2">Company Directory</p>
        <h2 class="dashboard-hero__title">Search, filter, and intervene across every tenant workspace from one portfolio screen.</h2>
        <p class="dashboard-hero__copy">
            Use the directory to track onboarding health, suspended accounts, inactive workspaces, and owner verification gaps before they become support escalations.
        </p>
        <div class="dashboard-hero__meta">
            <span class="badge-soft"><i class="bi bi-buildings me-1"></i><?= e((string) count($companies)) ?> visible companies</span>
            <span class="badge-soft"><i class="bi bi-check2-circle me-1"></i><?= e((string) $activeMatches) ?> active</span>
            <span class="badge-soft"><i class="bi bi-envelope-exclamation me-1"></i><?= e((string) $pendingMatches) ?> pending verification</span>
        </div>
    </div>

    <div class="dashboard-hero__rail">
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Visible</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) count($companies)) ?></strong>
            <span class="dashboard-hero-stat__meta">Results in the current filter scope</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Active</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $activeMatches) ?></strong>
            <span class="dashboard-hero-stat__meta">Companies ready for sign-in</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Verification</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $pendingMatches) ?></strong>
            <span class="dashboard-hero-stat__meta">Owners still blocked on email confirmation</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Filters</span>
            <strong class="dashboard-hero-stat__value"><?= e(array_filter($filters) !== [] ? 'Active' : 'Open') ?></strong>
            <span class="dashboard-hero-stat__meta">Search, status, onboarding, and activity controls</span>
        </article>
    </div>
</section>

<section class="surface-card card-panel dashboard-toolbar">
    <div class="dashboard-toolbar__copy">
        <p class="eyebrow mb-1">Portfolio Actions</p>
        <h3 class="mb-1">Move between platform operations without losing context.</h3>
        <p class="text-muted mb-0">Use quick links for verification queues, suspended accounts, billing checks, and global settings.</p>
    </div>
    <div class="dashboard-toolbar__actions">
        <a href="<?= e(url('platform')) ?>" class="btn btn-outline-secondary"><i class="bi bi-grid-1x2 me-1"></i>Overview</a>
        <a href="<?= e(url('platform/settings')) ?>" class="btn btn-outline-secondary"><i class="bi bi-sliders me-1"></i>General Settings</a>
        <a href="<?= e(url('platform/billing')) ?>" class="btn btn-outline-secondary"><i class="bi bi-credit-card me-1"></i>Billing</a>
        <a href="<?= e(url('platform/companies?onboarding=pending_owner_verification')) ?>" class="btn btn-outline-secondary"><i class="bi bi-envelope-check me-1"></i>Pending Verification</a>
        <a href="<?= e(url('platform/companies?status=inactive')) ?>" class="btn btn-outline-secondary"><i class="bi bi-pause-circle me-1"></i>Suspended</a>
    </div>
</section>

<section class="surface-card card-panel workspace-panel platform-filter-shell">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Directory</p>
            <h3><i class="bi bi-buildings me-2"></i>Company Directory</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><?= e((string) count($companies)) ?> matches</span>
            <span class="badge-soft"><?= e((string) $activeMatches) ?> active</span>
            <span class="badge-soft"><?= e((string) $pendingMatches) ?> pending verification</span>
        </div>
    </div>

    <form action="<?= e(url('platform/companies')) ?>" method="get" class="form-grid platform-filter-form">
        <div class="field-stack">
            <label class="form-label" for="platform_search">Search</label>
            <input id="platform_search" type="text" name="search" class="form-control" value="<?= e((string) $filters['search']) ?>" placeholder="Company, slug, owner, or email">
        </div>
        <div class="field-stack">
            <label class="form-label" for="platform_status">Status</label>
            <select id="platform_status" name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="field-stack">
            <label class="form-label" for="platform_onboarding">Onboarding</label>
            <select id="platform_onboarding" name="onboarding" class="form-select">
                <option value="">All onboarding states</option>
                <option value="verified_owner" <?= $filters['onboarding'] === 'verified_owner' ? 'selected' : '' ?>>Verified owner</option>
                <option value="pending_owner_verification" <?= $filters['onboarding'] === 'pending_owner_verification' ? 'selected' : '' ?>>Pending verification</option>
                <option value="no_owner" <?= $filters['onboarding'] === 'no_owner' ? 'selected' : '' ?>>No owner</option>
            </select>
        </div>
        <div class="field-stack">
            <label class="form-label" for="platform_activity">Activity</label>
            <select id="platform_activity" name="activity" class="form-select">
                <option value="">All activity states</option>
                <option value="active_30d" <?= $filters['activity'] === 'active_30d' ? 'selected' : '' ?>>Active in 30 days</option>
                <option value="inactive_30d" <?= $filters['activity'] === 'inactive_30d' ? 'selected' : '' ?>>No login in 30 days</option>
                <option value="never_logged_in" <?= $filters['activity'] === 'never_logged_in' ? 'selected' : '' ?>>Never logged in</option>
            </select>
        </div>
        <div class="workspace-panel__actions justify-content-end platform-filter-actions">
            <a href="<?= e(url('platform/companies')) ?>" class="btn btn-outline-secondary">Reset</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search me-1"></i>Apply Filters</button>
        </div>
    </form>
</section>

<section class="surface-card card-panel table-shell">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Results</p>
            <h3><i class="bi bi-grid-1x2 me-2"></i>Tenant Portfolio</h3>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Company</th>
                <th>Owner</th>
                <th>Footprint</th>
                <th>Onboarding</th>
                <th>Access</th>
                <th>Last Activity</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($companies === []): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">No companies matched the selected filters.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($companies as $company): ?>
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
                        <td>
                            <div class="small"><?= e((string) ($company['branch_count'] ?? 0)) ?> branches</div>
                            <div class="small text-muted"><?= e((string) ($company['active_user_count'] ?? 0)) ?> active of <?= e((string) ($company['user_count'] ?? 0)) ?> users</div>
                        </td>
                        <td><span class="status-pill status-pill--<?= e($ownerStateTone($company)) ?>"><?= e($ownerStateLabel($company)) ?></span></td>
                        <td><span class="status-pill status-pill--<?= e($statusTone((string) ($company['status'] ?? 'inactive'))) ?>"><?= e(ucfirst((string) ($company['status'] ?? 'inactive'))) ?></span></td>
                        <td><?= e($activityLabel((string) ($company['last_login_at'] ?? ''))) ?></td>
                        <td class="text-end">
                            <a href="<?= e(url('platform/companies/show?id=' . (int) $company['id'])) ?>" class="btn btn-sm btn-outline-secondary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
