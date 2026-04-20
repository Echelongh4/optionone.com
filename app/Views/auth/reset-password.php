<?php
$brandName = (string) setting_value('business_name', config('app.name'));
$brandLogo = (string) setting_value('business_logo_path', '');
$brandWords = preg_split('/\s+/', trim($brandName)) ?: [];
$brandInitials = '';
foreach (array_slice(array_values(array_filter($brandWords, static fn (string $word): bool => $word !== '')), 0, 2) as $word) {
    $brandInitials .= strtoupper(substr($word, 0, 1));
}
$brandInitials = $brandInitials !== '' ? $brandInitials : 'NP';
$emailErrorId = !empty($errors['email']) ? 'reset-email-error' : '';
$passwordErrorId = !empty($errors['password']) ? 'reset-password-error' : '';
$confirmErrorId = !empty($errors['password_confirmation']) ? 'reset-confirm-error' : '';
$credentialErrorId = !empty($errors['credentials']) ? 'reset-credentials-error' : '';
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
                    <p class="eyebrow mb-2">Credential Update</p>
                    <h1 class="mb-2">Set a new password for <?= e($brandName) ?></h1>
                    <p class="auth-lead text-muted mb-0">Choose a new password for the account below. Existing recovery links are invalidated after a successful reset.</p>
                </div>
            </div>

            <div class="hero-metrics auth-hero-metrics">
                <div class="hero-metric">
                    <span class="small text-muted">Account</span>
                    <strong><?= e($form['email'] ?? '') ?></strong>
                </div>
                <div class="hero-metric">
                    <span class="small text-muted">Reset link</span>
                    <strong>Time-limited</strong>
                </div>
                <div class="hero-metric">
                    <span class="small text-muted">Session</span>
                    <strong>Re-auth required</strong>
                </div>
            </div>

            <div class="auth-trust-card">
                <div class="table-kicker"><i class="bi bi-shield-lock"></i>Password Standards</div>
                <div class="auth-trust-list">
                    <div class="auth-trust-item">
                        <div class="auth-trust-item__icon"><i class="bi bi-lock"></i></div>
                        <div>
                            <strong>Use a unique password</strong>
                            <div class="small text-muted">Avoid reusing credentials from other systems or shared devices.</div>
                        </div>
                    </div>
                    <div class="auth-trust-item">
                        <div class="auth-trust-item__icon"><i class="bi bi-key"></i></div>
                        <div>
                            <strong>All recovery tokens rotate</strong>
                            <div class="small text-muted">Other unused reset tokens are invalidated once this password is updated.</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="auth-card__form auth-card__form--login">
            <div class="auth-form-header">
                <div class="auth-form-header__copy">
                    <p class="eyebrow mb-1">Reset Password</p>
                    <h2 class="mb-1">Choose a new credential</h2>
                    <p class="text-muted mb-0">Your new password will replace the previous account password immediately.</p>
                </div>
                <span class="badge-soft auth-form-badge"><i class="bi bi-shield-lock"></i>Protected update</span>
            </div>

            <?php if ($credentialErrorId !== ''): ?>
                <div class="alert alert-danger rounded-4 mb-0" id="<?= e($credentialErrorId) ?>" role="alert">
                    <?= e($errors['credentials'][0]) ?>
                </div>
            <?php endif; ?>

            <form action="<?= e(url('reset-password')) ?>" method="post" class="auth-login-form" data-loading-form>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($form['token'] ?? '') ?>">

                <div class="auth-field">
                    <label class="form-label" for="reset_email">Email address</label>
                    <input
                        id="reset_email"
                        type="email"
                        name="email"
                        class="form-control"
                        value="<?= e($form['email'] ?? '') ?>"
                        readonly
                        aria-invalid="<?= !empty($errors['email']) ? 'true' : 'false' ?>"
                        <?= $emailErrorId !== '' ? 'aria-describedby="' . e($emailErrorId) . '"' : '' ?>
                    >
                    <?php if ($emailErrorId !== ''): ?>
                        <small class="text-danger d-block mt-2" id="<?= e($emailErrorId) ?>"><?= e($errors['email'][0]) ?></small>
                    <?php endif; ?>
                </div>

                <div class="auth-field">
                    <label class="form-label" for="reset_password">New password</label>
                    <div class="auth-password-field">
                        <input
                            id="reset_password"
                            type="password"
                            name="password"
                            class="form-control"
                            placeholder="Enter your new password"
                            autocomplete="new-password"
                            aria-invalid="<?= !empty($errors['password']) ? 'true' : 'false' ?>"
                            <?= $passwordErrorId !== '' ? 'aria-describedby="' . e($passwordErrorId) . '"' : '' ?>
                            required
                            data-auth-password
                        >
                        <button type="button" class="auth-password-toggle" data-password-toggle aria-label="Show password" aria-controls="reset_password">
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

                <div class="auth-field">
                    <label class="form-label" for="reset_password_confirmation">Confirm password</label>
                    <input
                        id="reset_password_confirmation"
                        type="password"
                        name="password_confirmation"
                        class="form-control"
                        placeholder="Re-enter your new password"
                        autocomplete="new-password"
                        aria-invalid="<?= !empty($errors['password_confirmation']) ? 'true' : 'false' ?>"
                        <?= $confirmErrorId !== '' ? 'aria-describedby="' . e($confirmErrorId) . '"' : '' ?>
                        required
                    >
                    <?php if ($confirmErrorId !== ''): ?>
                        <small class="text-danger d-block mt-2" id="<?= e($confirmErrorId) ?>"><?= e($errors['password_confirmation'][0]) ?></small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit">
                    <i class="bi bi-shield-check me-2"></i>Update Password
                </button>

                <div class="auth-assistance">
                    <div class="small text-muted">After reset, sign in again with the new password and keep it private to your assigned account.</div>
                    <a href="<?= e(url('login')) ?>" class="auth-inline-link"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a>
                </div>
            </form>
        </section>
    </div>
</div>
