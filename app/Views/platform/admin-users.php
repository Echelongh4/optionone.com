<?php
$filters = array_merge([
    'search' => '',
    'status' => '',
    'verification' => '',
    'source' => '',
], is_array($filters ?? null) ? $filters : []);
$summary = array_merge([
    'total' => 0,
    'active' => 0,
    'pending_verification' => 0,
    'database_managed' => 0,
    'env_managed' => 0,
], is_array($summary ?? null) ? $summary : []);
$platformAdmins = is_array($platformAdmins ?? null) ? $platformAdmins : [];
$createErrors = is_array($createErrors ?? null) ? $createErrors : [];
$createForm = array_merge([
    'first_name' => '',
    'last_name' => '',
    'username' => '',
    'email' => '',
    'phone' => '',
    'role_name' => 'Super Admin',
], is_array($createForm ?? null) ? $createForm : []);
$supportsUsername = !empty($supportsUsername);
$currentUserId = (int) (current_user()['id'] ?? 0);
$createAlert = null;
foreach ($createErrors as $messages) {
    if (is_array($messages) && $messages !== []) {
        $createAlert = (string) $messages[0];
        break;
    }
}

$formatDate = static function (?string $value, string $fallback = 'Never'): string {
    $value = trim((string) $value);

    return $value !== '' ? date('M d, Y H:i', strtotime($value)) : $fallback;
};

$statusTone = static function (string $status): string {
    return $status === 'active' ? 'success' : 'warning';
};
?>

<section class="dashboard-hero surface-card card-panel">
    <div class="dashboard-hero__main">
        <p class="eyebrow mb-2">Access Control</p>
        <h2 class="dashboard-hero__title">Manage platform-admin accounts without mixing them into tenant registration.</h2>
        <p class="dashboard-hero__copy">
            Bootstrap the internal admin team, promote approved existing users, and keep at least one recoverable platform admin account online.
        </p>
        <div class="dashboard-hero__meta">
            <span class="badge-soft"><i class="bi bi-people me-1"></i><?= e((string) $summary['total']) ?> admins</span>
            <span class="badge-soft"><i class="bi bi-check2-circle me-1"></i><?= e((string) $summary['active']) ?> active</span>
            <span class="badge-soft"><i class="bi bi-envelope-exclamation me-1"></i><?= e((string) $summary['pending_verification']) ?> pending verification</span>
        </div>
    </div>

    <div class="dashboard-hero__rail">
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Total Admins</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $summary['total']) ?></strong>
            <span class="dashboard-hero-stat__meta">All effective platform accounts</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Database Managed</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $summary['database_managed']) ?></strong>
            <span class="dashboard-hero-stat__meta">Recoverable from the portal</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Env Managed</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $summary['env_managed']) ?></strong>
            <span class="dashboard-hero-stat__meta">Whitelisted through deployment config</span>
        </article>
        <article class="dashboard-hero-stat">
            <span class="dashboard-hero-stat__label">Needs Follow-up</span>
            <strong class="dashboard-hero-stat__value"><?= e((string) $summary['pending_verification']) ?></strong>
            <span class="dashboard-hero-stat__meta">Accounts that cannot log in yet</span>
        </article>
    </div>
</section>

<section class="surface-card card-panel dashboard-toolbar">
    <div class="dashboard-toolbar__copy">
        <p class="eyebrow mb-1">Admin Operations</p>
        <h3 class="mb-1">Create new admins or grant platform access to vetted users.</h3>
        <p class="text-muted mb-0">Database-managed admins are safer for recovery because they can still be managed from inside the portal.</p>
    </div>
    <div class="dashboard-toolbar__actions">
        <a href="<?= e(url('platform')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Overview</a>
        <a href="<?= e(url('platform/settings')) ?>" class="btn btn-outline-secondary"><i class="bi bi-sliders me-1"></i>General Settings</a>
        <a href="<?= e(url('platform/companies')) ?>" class="btn btn-outline-secondary"><i class="bi bi-buildings me-1"></i>Companies</a>
    </div>
</section>

<div class="content-grid">
    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Create Platform Admin</p>
                <h4>Internal platform workspace account</h4>
            </div>
        </div>

        <?php if ($createAlert !== null): ?>
            <div class="alert alert-danger rounded-4 mb-3"><?= e($createAlert) ?></div>
        <?php endif; ?>

        <form action="<?= e(url('platform/admin-users/create')) ?>" method="post" class="stack-grid" data-loading-form>
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label" for="platform_admin_first_name">First name</label>
                    <input id="platform_admin_first_name" type="text" name="first_name" class="form-control" value="<?= e($createForm['first_name']) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label" for="platform_admin_last_name">Last name</label>
                    <input id="platform_admin_last_name" type="text" name="last_name" class="form-control" value="<?= e($createForm['last_name']) ?>" required>
                </div>
            </div>

            <?php if ($supportsUsername): ?>
                <div class="field-stack">
                    <label class="form-label" for="platform_admin_username">Username <span class="small text-muted">(optional)</span></label>
                    <input id="platform_admin_username" type="text" name="username" class="form-control" value="<?= e($createForm['username']) ?>" placeholder="Leave blank to auto-generate">
                    <div class="small text-muted mt-1">If you skip this, a valid username will be generated automatically from the email or name.</div>
                </div>
            <?php endif; ?>

            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label" for="platform_admin_email">Email</label>
                    <input id="platform_admin_email" type="email" name="email" class="form-control" value="<?= e($createForm['email']) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label" for="platform_admin_phone">Phone</label>
                    <input id="platform_admin_phone" type="text" name="phone" class="form-control" value="<?= e($createForm['phone']) ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label" for="platform_admin_role_name">Workspace role</label>
                    <select id="platform_admin_role_name" name="role_name" class="form-select">
                        <?php foreach (['Super Admin', 'Admin'] as $roleName): ?>
                            <option value="<?= e($roleName) ?>" <?= $createForm['role_name'] === $roleName ? 'selected' : '' ?>><?= e($roleName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label" for="platform_admin_password">Password</label>
                    <input id="platform_admin_password" type="password" name="password" class="form-control" required>
                </div>
            </div>

            <div class="field-stack">
                <label class="form-label" for="platform_admin_password_confirmation">Confirm password</label>
                <input id="platform_admin_password_confirmation" type="password" name="password_confirmation" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i>Create Platform Admin
            </button>
        </form>
    </section>

    <section class="utility-card">
        <div class="utility-card__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">Promote Existing User</p>
                <h4>Grant platform access to a current account</h4>
            </div>
        </div>
        <form action="<?= e(url('platform/admin-users/promote')) ?>" method="post" class="stack-grid" data-loading-form>
            <?= csrf_field() ?>
            <div class="field-stack">
                <label class="form-label" for="platform_admin_promote_login"><?= e($supportsUsername ? 'Email or username' : 'Email address') ?></label>
                <input id="platform_admin_promote_login" type="text" name="login" class="form-control" placeholder="<?= e($supportsUsername ? 'owner@example.com or username' : 'owner@example.com') ?>" required>
                <div class="small text-muted mt-1">Use this when a vetted internal user already exists and only needs platform access added.</div>
            </div>
            <button type="submit" class="btn btn-outline-primary">
                <i class="bi bi-shield-plus me-1"></i>Grant Platform Access
            </button>
        </form>

        <div class="record-card mt-3">
            <div class="small text-muted">Recovery rule</div>
            <div class="fw-semibold">Keep at least one database-managed, active, verified platform admin.</div>
            <div class="small text-muted mt-2">The portal blocks changes that would remove the last recoverable admin or the last usable admin session.</div>
        </div>
    </section>
</div>

<section class="surface-card card-panel table-shell">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Admin Directory</p>
            <h3><i class="bi bi-shield-lock me-2"></i>Platform admin accounts</h3>
        </div>
    </div>

    <form action="<?= e(url('platform/admin-users')) ?>" method="get" class="dashboard-filters mb-3">
        <div class="dashboard-filters__group">
            <input type="search" name="search" class="form-control" placeholder="Search name, email, company, role..." value="<?= e($filters['search']) ?>">
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <select name="verification" class="form-select">
                <option value="">Any verification</option>
                <option value="verified" <?= $filters['verification'] === 'verified' ? 'selected' : '' ?>>Verified</option>
                <option value="pending" <?= $filters['verification'] === 'pending' ? 'selected' : '' ?>>Pending</option>
            </select>
            <select name="source" class="form-select">
                <option value="">Any source</option>
                <option value="database" <?= $filters['source'] === 'database' ? 'selected' : '' ?>>Database</option>
                <option value="environment" <?= $filters['source'] === 'environment' ? 'selected' : '' ?>>Environment</option>
                <option value="hybrid" <?= $filters['source'] === 'hybrid' ? 'selected' : '' ?>>Hybrid</option>
            </select>
        </div>
        <div class="dashboard-filters__actions">
            <button type="submit" class="btn btn-outline-secondary">Filter</button>
            <a href="<?= e(url('platform/admin-users')) ?>" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                <th>Admin</th>
                <th>Company</th>
                <th>Workspace Role</th>
                <th>Access Source</th>
                <th>Status</th>
                <th>Verification</th>
                <th>Last Login</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($platformAdmins === []): ?>
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">No platform admin accounts matched the current filters.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($platformAdmins as $admin): ?>
                    <?php
                    $adminId = (int) ($admin['id'] ?? 0);
                    $isVerified = trim((string) ($admin['email_verified_at'] ?? '')) !== '';
                    $isActive = (string) ($admin['status'] ?? 'inactive') === 'active';
                    ?>
                    <tr>
                        <td>
                            <div><?= e((string) ($admin['full_name'] ?? 'User')) ?></div>
                            <div class="small text-muted"><?= e((string) ($admin['email'] ?? '')) ?></div>
                            <?php if ($adminId === $currentUserId): ?>
                                <div class="small text-muted">Current session</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?= e((string) ($admin['company_name'] ?? 'No company')) ?></div>
                            <div class="small text-muted"><?= e((string) ($admin['company_slug'] ?? '')) ?></div>
                        </td>
                        <td><span class="badge-soft"><?= e((string) ($admin['role_name'] ?? 'User')) ?></span></td>
                        <td>
                            <span class="badge-soft"><?= e((string) ($admin['platform_access_source'] ?? 'Unknown')) ?></span>
                        </td>
                        <td><span class="status-pill status-pill--<?= e($statusTone((string) ($admin['status'] ?? 'inactive'))) ?>"><?= e(ucfirst((string) ($admin['status'] ?? 'inactive'))) ?></span></td>
                        <td><span class="badge-soft"><?= e($isVerified ? 'Verified' : 'Pending') ?></span></td>
                        <td><?= e($formatDate((string) ($admin['last_login_at'] ?? ''), 'Never')) ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <form action="<?= e(url('platform/admin-users/status')) ?>" method="post" class="d-inline" data-loading-form>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= e((string) $adminId) ?>">
                                    <input type="hidden" name="status" value="<?= e($isActive ? 'inactive' : 'active') ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e($isActive ? 'Suspend' : 'Activate') ?></button>
                                </form>

                                <?php if (!$isVerified): ?>
                                    <form action="<?= e(url('platform/admin-users/resend-verification')) ?>" method="post" class="d-inline" data-loading-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= e((string) $adminId) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">Resend Verification</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($admin['has_database_access'])): ?>
                                    <form action="<?= e(url('platform/admin-users/revoke')) ?>" method="post" class="d-inline" data-loading-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= e((string) $adminId) ?>">
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            data-confirm-action
                                            data-confirm-title="Remove platform access?"
                                            data-confirm-text="This removes database-managed platform access from the selected account."
                                            data-confirm-button="Remove Access"
                                        >
                                            Revoke DB Access
                                        </button>
                                    </form>
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
