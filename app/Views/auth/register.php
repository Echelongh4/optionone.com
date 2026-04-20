<?php
$platformName = (string) config('app.name', 'NovaPOS');
$supportsUsername = !empty($supportsUsername);
$tenantSchemaReady = !isset($tenantSchemaReady) || (bool) $tenantSchemaReady;
$alertMessage = $errors['registration'][0] ?? null;
if ($alertMessage === null) {
    foreach ($errors as $messages) {
        if (is_array($messages) && $messages !== []) {
            $alertMessage = $messages[0];
            break;
        }
    }
}
?>

<div class="auth-card card-panel auth-login">
    <div class="auth-card__grid auth-card__grid--login">
        <section class="auth-card__intro auth-card__intro--login">
            <div class="auth-brand-block">
                <div class="auth-brand-mark"><?= e(substr($platformName, 0, 2)) ?></div>
                <div>
                    <p class="eyebrow mb-2">Multi-Company Setup</p>
                    <h1 class="mb-2">Create your workspace</h1>
                    <p class="auth-lead text-muted mb-0">Register your company, create the first admin account, and verify the email address before the first sign-in.</p>
                </div>
            </div>

            <div class="auth-compact-note">
                <div class="auth-compact-note__item">
                    <span class="small text-muted">Platform</span>
                    <strong><?= e($platformName) ?></strong>
                </div>
                <div class="auth-compact-note__item">
                    <span class="small text-muted">Default branch</span>
                    <strong>Main Branch</strong>
                </div>
                <div class="auth-compact-note__item">
                    <span class="small text-muted">First role</span>
                    <strong>Admin</strong>
                </div>
            </div>
        </section>

        <section class="auth-card__form auth-card__form--login">
            <div class="auth-form-header">
                <div class="auth-form-header__copy">
                    <h2 class="mb-1">Company registration</h2>
                    <p class="text-muted mb-0">This creates an isolated company account, settings profile, and an owner account that must be verified by email.</p>
                </div>
            </div>

            <?php if ($alertMessage !== null): ?>
                <div class="alert alert-danger rounded-4 mb-4" role="alert">
                    <?= e((string) $alertMessage) ?>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('register')) ?>" method="post" class="auth-login-form">
                <?= csrf_field() ?>

                <div class="auth-field">
                    <label class="form-label" for="register_company_name">Company name</label>
                    <input id="register_company_name" type="text" name="company_name" class="form-control" value="<?= e($form['company_name'] ?? '') ?>" required>
                </div>

                <div class="form-grid">
                    <div class="field-stack">
                        <label class="form-label" for="register_first_name">First name</label>
                        <input id="register_first_name" type="text" name="first_name" class="form-control" value="<?= e($form['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="field-stack">
                        <label class="form-label" for="register_last_name">Last name</label>
                        <input id="register_last_name" type="text" name="last_name" class="form-control" value="<?= e($form['last_name'] ?? '') ?>" required>
                    </div>
                </div>

                <?php if ($supportsUsername): ?>
                    <div class="auth-field">
                        <label class="form-label" for="register_username">Username <span class="small text-muted">(optional)</span></label>
                        <input id="register_username" type="text" name="username" class="form-control" value="<?= e($form['username'] ?? '') ?>" placeholder="Leave blank to auto-generate">
                        <div class="small text-muted mt-2">You can leave this blank and the system will create a valid username from the owner email or name.</div>
                    </div>
                <?php endif; ?>

                <div class="auth-field">
                    <label class="form-label" for="register_email">Email</label>
                    <input id="register_email" type="email" name="email" class="form-control" value="<?= e($form['email'] ?? '') ?>" required>
                </div>

                <div class="form-grid">
                    <div class="field-stack">
                        <label class="form-label" for="register_phone">Phone</label>
                        <input id="register_phone" type="text" name="phone" class="form-control" value="<?= e($form['phone'] ?? '') ?>">
                    </div>
                    <div class="field-stack">
                        <label class="form-label" for="register_address">Address</label>
                        <input id="register_address" type="text" name="address" class="form-control" value="<?= e($form['address'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="field-stack">
                        <label class="form-label" for="register_password">Password</label>
                        <input id="register_password" type="password" name="password" class="form-control" required>
                    </div>
                    <div class="field-stack">
                        <label class="form-label" for="register_password_confirmation">Confirm password</label>
                        <input id="register_password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit" <?= !$tenantSchemaReady ? 'disabled' : '' ?>>
                    <i class="bi bi-building-add me-2"></i>Create Company Workspace
                </button>

                <div class="auth-assistance">
                    <?php if (!$tenantSchemaReady): ?>
                        <div class="small text-danger">Apply the required registration migrations, then reload this page.</div>
                    <?php endif; ?>
                    <div class="small text-muted">Already have a workspace? <a href="<?= e(url('login')) ?>" class="auth-inline-link">Sign in</a></div>
                </div>
            </form>
        </section>
    </div>
</div>
