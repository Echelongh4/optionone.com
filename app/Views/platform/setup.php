<?php
$platformName = (string) config('app.name', 'NovaPOS');
$supportsUsername = !empty($supportsUsername);
$platformSetupReady = !empty($platformSetupReady);
$platformRegisterPath = (string) ($platformRegisterPath ?? 'platform/register');
$alertMessage = $errors['setup'][0] ?? null;
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
                    <p class="eyebrow mb-2">Platform Registration</p>
                    <h1 class="mb-2">Register the first platform admin</h1>
                    <p class="auth-lead text-muted mb-0">This protected first-run flow creates the internal platform workspace and the owner account that manages companies, billing, and support access.</p>
                </div>
            </div>

            <div class="auth-compact-note">
                <div class="auth-compact-note__item">
                    <span class="small text-muted">Portal</span>
                    <strong><?= e($platformName) ?></strong>
                </div>
                <div class="auth-compact-note__item">
                    <span class="small text-muted">Registration mode</span>
                    <strong>First platform owner</strong>
                </div>
                <div class="auth-compact-note__item">
                    <span class="small text-muted">Access</span>
                    <strong>Email verification required</strong>
                </div>
            </div>

            <div class="auth-trust-card">
                <div class="table-kicker"><i class="bi bi-diagram-3"></i>What This Creates</div>
                <div class="auth-trust-list">
                    <div class="auth-trust-item">
                        <div class="auth-trust-item__icon"><i class="bi bi-buildings"></i></div>
                        <div>
                            <strong>Platform workspace</strong>
                            <div class="small text-muted">An internal operations company and control-center branch reserved for platform administration.</div>
                        </div>
                    </div>
                    <div class="auth-trust-item">
                        <div class="auth-trust-item__icon"><i class="bi bi-shield-lock"></i></div>
                        <div>
                            <strong>Platform admin account</strong>
                            <div class="small text-muted">The first owner account gets cross-company access to companies, billing, admin users, and support sessions.</div>
                        </div>
                    </div>
                    <div class="auth-trust-item">
                        <div class="auth-trust-item__icon"><i class="bi bi-envelope-check"></i></div>
                        <div>
                            <strong>Verified access flow</strong>
                            <div class="small text-muted">The account stays inactive until the verification email is opened, then signs in from the normal login page.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-support-card">
                <div class="table-kicker"><i class="bi bi-info-circle"></i>Important</div>
                <div class="auth-support-grid">
                    <div>
                        <strong>Not for tenant companies</strong>
                        <div class="small text-muted">Businesses should still use the normal company registration flow at <code>/register</code>.</div>
                    </div>
                    <div>
                        <strong>One-time public entry</strong>
                        <div class="small text-muted">This page is intended only until the first platform admin exists, then platform access continues through sign in.</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="auth-card__form auth-card__form--login">
            <div class="auth-form-header">
                <div class="auth-form-header__copy">
                    <h2 class="mb-1">Platform admin registration</h2>
                    <p class="text-muted mb-0">Use the product owner or operations lead email. After registration, the account must verify email before the first sign in.</p>
                </div>
                <div class="auth-form-badge"><i class="bi bi-lock"></i>Internal access</div>
            </div>

            <?php if ($alertMessage !== null): ?>
                <div class="alert alert-danger rounded-4 mb-4" role="alert">
                    <?= e((string) $alertMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (!$platformSetupReady): ?>
                <div class="alert alert-warning rounded-4 mb-4" role="alert">
                    <?= e((string) ($platformSetupMessage ?? 'Platform setup is not available yet.')) ?>
                </div>
            <?php endif; ?>

            <form action="<?= e(url($platformRegisterPath)) ?>" method="post" class="auth-login-form" data-loading-form>
                <?= csrf_field() ?>

                <div class="form-grid">
                    <div class="field-stack">
                        <label class="form-label" for="platform_setup_first_name">First name</label>
                        <input id="platform_setup_first_name" type="text" name="first_name" class="form-control" value="<?= e($form['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="field-stack">
                        <label class="form-label" for="platform_setup_last_name">Last name</label>
                        <input id="platform_setup_last_name" type="text" name="last_name" class="form-control" value="<?= e($form['last_name'] ?? '') ?>" required>
                    </div>
                </div>

                <?php if ($supportsUsername): ?>
                    <div class="auth-field">
                        <label class="form-label" for="platform_setup_username">Username <span class="small text-muted">(optional)</span></label>
                        <input id="platform_setup_username" type="text" name="username" class="form-control" value="<?= e($form['username'] ?? '') ?>" placeholder="Leave blank to auto-generate">
                        <div class="small text-muted mt-2">If omitted, a valid username will be generated automatically from the email or name.</div>
                    </div>
                <?php endif; ?>

                <div class="auth-field">
                    <label class="form-label" for="platform_setup_email">Email</label>
                    <input id="platform_setup_email" type="email" name="email" class="form-control" value="<?= e($form['email'] ?? '') ?>" required>
                    <div class="small text-muted mt-2">Use the email that should receive platform verification and platform access notices.</div>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="platform_setup_phone">Phone</label>
                    <input id="platform_setup_phone" type="text" name="phone" class="form-control" value="<?= e($form['phone'] ?? '') ?>">
                </div>

                <div class="form-grid">
                    <div class="field-stack">
                        <label class="form-label" for="platform_setup_password">Password</label>
                        <input id="platform_setup_password" type="password" name="password" class="form-control" required>
                    </div>
                    <div class="field-stack">
                        <label class="form-label" for="platform_setup_password_confirmation">Confirm password</label>
                        <input id="platform_setup_password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit" <?= !$platformSetupReady ? 'disabled' : '' ?>>
                    <i class="bi bi-person-plus me-2"></i>Register Platform Admin
                </button>

                <div class="auth-assistance">
                    <?php if (!$platformSetupReady): ?>
                        <div class="small text-danger"><?= e((string) ($platformSetupMessage ?? 'Required platform migrations are missing.')) ?></div>
                    <?php endif; ?>
                    <div class="small text-muted">Already initialized? <a href="<?= e(url('login')) ?>" class="auth-inline-link">Go to sign in</a></div>
                    <div class="small text-muted">Need a tenant company instead? <a href="<?= e(url('register')) ?>" class="auth-inline-link">Create a company workspace</a>.</div>
                </div>
            </form>
        </section>
    </div>
</div>
