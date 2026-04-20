<?php
$brandName = (string) setting_value('business_name', config('app.name'));
$brandLogo = (string) setting_value('business_logo_path', '');
$businessAddress = (string) setting_value('business_address', '');
$businessEmail = (string) setting_value('business_email', '');
$businessPhone = (string) setting_value('business_phone', '');
$sessionTimeout = (int) config('app.session_timeout');
$showDemoAccess = (bool) config('app.debug', false) || (string) config('app.env', 'local') !== 'production';
$supportLabel = $businessPhone !== '' ? $businessPhone : ($businessEmail !== '' ? $businessEmail : 'Contact administrator');
$brandWords = preg_split('/\s+/', trim($brandName)) ?: [];
$brandInitials = '';
foreach (array_slice(array_values(array_filter($brandWords, static fn (string $word): bool => $word !== '')), 0, 2) as $word) {
    $brandInitials .= strtoupper(substr($word, 0, 1));
}
$brandInitials = $brandInitials !== '' ? $brandInitials : 'NP';
$supportsUsername = !empty($supportsUsername);
$tenantSchemaReady = !isset($tenantSchemaReady) || (bool) $tenantSchemaReady;
$platformBootstrapAvailable = !empty($platformBootstrapAvailable);
$loginErrorId = !empty($errors['login']) ? 'login-login-error' : '';
$passwordErrorId = !empty($errors['password']) ? 'login-password-error' : '';
$credentialErrorId = !empty($errors['credentials']) ? 'login-credentials-error' : '';
$verificationPending = !empty($errors['verification_pending']);
$verificationIdentifier = trim((string) ($form['login'] ?? ''));
$platformRegisterUrl = url('platform/register');
?>

<div class="auth-card card-panel auth-login" data-auth-login>
    <div class="auth-card__grid auth-card__grid--login">
        <section class="auth-card__intro auth-card__intro--login">
            <div class="auth-brand-block">
                <?php if ($brandLogo !== ''): ?>
                    <img src="<?= e(url($brandLogo)) ?>" alt="<?= e($brandName) ?>" class="auth-brand-logo">
                <?php else: ?>
                    <div class="auth-brand-mark"><?= e($brandInitials) ?></div>
                <?php endif; ?>
                <div>
                    <p class="eyebrow mb-2">Business Access</p>
                    <h1 class="mb-2">Sign in</h1>
                    <p class="auth-lead text-muted mb-0">
                        <?= e($supportsUsername ? 'Use your email or username to access the POS workspace.' : 'Use your business email to access the POS workspace.') ?>
                    </p>
                </div>
            </div>

            <div class="auth-compact-note">
                <div class="auth-compact-note__item">
                    <span class="small text-muted">Workspace</span>
                    <strong><?= e($brandName) ?></strong>
                </div>
                <div class="auth-compact-note__item">
                    <span class="small text-muted">Session timeout</span>
                    <strong><?= e((string) $sessionTimeout) ?> min</strong>
                </div>
                <div class="auth-compact-note__item">
                    <span class="small text-muted">Support</span>
                    <strong><?= e($supportLabel) ?></strong>
                </div>
            </div>

            <?php if ($showDemoAccess): ?>
                <details class="auth-disclosure auth-disclosure--compact">
                    <summary>
                        <span>
                            <span class="table-kicker"><i class="bi bi-stars"></i>Local Demo Access</span>
                            <strong>Demo accounts</strong>
                        </span>
                        <span class="badge-soft"><i class="bi bi-key"></i>Password@123</span>
                    </summary>
                    <div class="auth-account-list">
                        <div class="auth-account-item">
                            <div>
                                <strong>Super Admin</strong>
                                <div class="small text-muted"><?= e($supportsUsername ? '@superadmin' : 'superadmin@novapos.test') ?></div>
                            </div>
                            <span>Full access</span>
                        </div>
                        <div class="auth-account-item">
                            <div>
                                <strong>Admin</strong>
                                <div class="small text-muted"><?= e($supportsUsername ? '@admin' : 'admin@novapos.test') ?></div>
                            </div>
                            <span>Operations</span>
                        </div>
                        <div class="auth-account-item">
                            <div>
                                <strong>Manager</strong>
                                <div class="small text-muted"><?= e($supportsUsername ? '@manager' : 'manager@novapos.test') ?></div>
                            </div>
                            <span>Oversight</span>
                        </div>
                        <div class="auth-account-item">
                            <div>
                                <strong>Cashier</strong>
                                <div class="small text-muted"><?= e($supportsUsername ? '@cashier' : 'cashier@novapos.test') ?></div>
                            </div>
                            <span>POS</span>
                        </div>
                    </div>
                </details>
            <?php endif; ?>
        </section>

        <section class="auth-card__form auth-card__form--login">
            <div class="auth-form-header">
                <div class="auth-form-header__copy">
                    <h2 class="mb-1">Welcome back</h2>
                    <p class="text-muted mb-0"><?= e($supportsUsername ? 'Enter your email or username and password. New accounts must verify email before first sign-in.' : 'Enter your email and password. New accounts must verify email before first sign-in.') ?></p>
                </div>
            </div>

            <?php if ($credentialErrorId !== ''): ?>
                <div class="alert alert-danger rounded-4 mb-4" id="<?= e($credentialErrorId) ?>" role="alert">
                    <?= e($errors['credentials'][0]) ?>
                </div>
            <?php endif; ?>

            <?php if ($verificationPending): ?>
                <div class="alert alert-warning rounded-4 mb-4" role="alert">
                    <?= e($errors['verification_pending'][0]) ?>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('login')) ?>" method="post" class="auth-login-form" data-loading-form>
                <?= csrf_field() ?>

                <div class="auth-field">
                    <label class="form-label" for="login_identifier"><?= e($supportsUsername ? 'Email or username' : 'Email address') ?></label>
                    <input
                        id="login_identifier"
                        type="text"
                        name="login"
                        class="form-control"
                        placeholder="<?= e($supportsUsername ? 'you@business.com or username' : 'you@business.com') ?>"
                        value="<?= e($form['login'] ?? '') ?>"
                        autocomplete="username"
                        <?= !$supportsUsername ? 'inputmode="email"' : '' ?>
                        spellcheck="false"
                        autocapitalize="none"
                        aria-invalid="<?= !empty($errors['login']) ? 'true' : 'false' ?>"
                        <?= $loginErrorId !== '' ? 'aria-describedby="' . e($loginErrorId) . '"' : '' ?>
                        required
                        autofocus
                    >
                    <?php if ($loginErrorId !== ''): ?>
                        <small class="text-danger d-block mt-2" id="<?= e($loginErrorId) ?>"><?= e($errors['login'][0]) ?></small>
                    <?php endif; ?>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="login_password">Password</label>
                    <div class="auth-password-field">
                        <input
                            id="login_password"
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            aria-invalid="<?= !empty($errors['password']) ? 'true' : 'false' ?>"
                            <?= $passwordErrorId !== '' ? 'aria-describedby="' . e($passwordErrorId) . '"' : '' ?>
                            required
                            data-auth-password
                        >
                        <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password" aria-controls="login_password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <?php if ($passwordErrorId !== ''): ?>
                        <small class="text-danger d-block mt-2" id="<?= e($passwordErrorId) ?>"><?= e($errors['password'][0]) ?></small>
                    <?php endif; ?>
                    <div class="auth-caps-warning" data-caps-warning hidden aria-live="polite">
                        <i class="bi bi-exclamation-triangle"></i>Caps Lock is on.
                    </div>
                </div>

                <div class="auth-form-meta">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">Remember this device</label>
                    </div>
                    <div class="auth-form-links">
                        <span class="inline-stat"><i class="bi bi-shield-lock"></i>Login throttling active</span>
                        <a href="<?= e(url('forgot-password')) ?>" class="auth-inline-link">Forgot password?</a>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit" <?= !$tenantSchemaReady ? 'disabled' : '' ?>>
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>

                <div class="auth-assistance">
                    <?php if (!$tenantSchemaReady): ?>
                        <div class="small text-danger">Apply <code>database/migrations/010_multi_company_support.sql</code> before signing in or creating a company workspace.</div>
                    <?php endif; ?>
                    <div class="small text-muted">Need help? Use password reset, or <a href="<?= e(url('register')) ?>" class="auth-inline-link">create a company workspace</a>.</div>
                    <?php if ($platformBootstrapAvailable): ?>
                        <div class="auth-platform-callout">
                            <div class="auth-platform-callout__copy">
                                <div class="table-kicker"><i class="bi bi-shield-check"></i>Platform Admin Access</div>
                                <strong>Register the first platform admin</strong>
                                <div class="small text-muted">Use this only once to create the product owner account for cross-company control, billing, and support access.</div>
                            </div>
                            <a href="<?= e($platformRegisterUrl) ?>" class="btn btn-outline-primary auth-platform-callout__action">
                                <i class="bi bi-person-plus me-2"></i>Register Platform Admin
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ($verificationPending && $verificationIdentifier !== '' && $tenantSchemaReady): ?>
                <form action="<?= e(url('verify-email/resend')) ?>" method="post" class="auth-login-form mt-3">
                    <?= csrf_field() ?>
                    <input type="hidden" name="login" value="<?= e($verificationIdentifier) ?>">
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-envelope-arrow-up me-2"></i>Resend Verification Email
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
