<?php
$brandName = (string) setting_value('business_name', config('app.name'));
$brandLogo = (string) setting_value('business_logo_path', '');
$businessEmail = (string) setting_value('business_email', '');
$businessPhone = (string) setting_value('business_phone', '');
$supportEmail = $businessEmail !== '' ? $businessEmail : 'Contact administrator';
$supportPhone = $businessPhone !== '' ? $businessPhone : 'Support desk';
$brandWords = preg_split('/\s+/', trim($brandName)) ?: [];
$brandInitials = '';
foreach (array_slice(array_values(array_filter($brandWords, static fn (string $word): bool => $word !== '')), 0, 2) as $word) {
    $brandInitials .= strtoupper(substr($word, 0, 1));
}
$brandInitials = $brandInitials !== '' ? $brandInitials : 'NP';
$emailErrorId = !empty($errors['email']) ? 'forgot-email-error' : '';
?>

<div class="auth-card card-panel auth-login">
    <div class="auth-card__grid auth-card__grid--login">
        <section class="auth-card__intro auth-card__intro--login">
            <div class="auth-brand-block">
                <?php if ($brandLogo !== ''): ?>
                    <img src="<?= e(url($brandLogo)) ?>" alt="<?= e($brandName) ?>" class="auth-brand-logo">
                <?php else: ?>
                    <div class="auth-brand-mark"><?= e($brandInitials) ?></div>
                <?php endif; ?>
                <div>
                    <p class="eyebrow mb-2">Secure Recovery</p>
                    <h1 class="mb-2">Recover access to <?= e($brandName) ?></h1>
                    <p class="auth-lead text-muted mb-0">Enter the account email address and we will prepare a secure password reset link.</p>
                </div>
            </div>

            <div class="hero-metrics auth-hero-metrics">
                <div class="hero-metric">
                    <span class="small text-muted">Delivery</span>
                    <strong>Email link</strong>
                </div>
                <div class="hero-metric">
                    <span class="small text-muted">Token expiry</span>
                    <strong><?= e((string) config('app.password_reset_lifetime_minutes', 60)) ?> minutes</strong>
                </div>
                <div class="hero-metric">
                    <span class="small text-muted">Access</span>
                    <strong>Guest recovery</strong>
                </div>
            </div>

            <div class="auth-trust-card">
                <div class="table-kicker"><i class="bi bi-shield-check"></i>Recovery Standards</div>
                <div class="auth-trust-list">
                    <div class="auth-trust-item">
                        <div class="auth-trust-item__icon"><i class="bi bi-envelope-check"></i></div>
                        <div>
                            <strong>Email-based verification</strong>
                            <div class="small text-muted">Reset access is delivered to the account email on record.</div>
                        </div>
                    </div>
                    <div class="auth-trust-item">
                        <div class="auth-trust-item__icon"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <strong>Time-limited tokens</strong>
                            <div class="small text-muted">Recovery links expire automatically to reduce unauthorized reuse.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="auth-support-card">
                <div class="table-kicker"><i class="bi bi-life-preserver"></i>Need help?</div>
                <div class="auth-support-grid">
                    <div>
                        <div class="small text-muted">Business email</div>
                        <div class="fw-semibold"><?= e($supportEmail) ?></div>
                    </div>
                    <div>
                        <div class="small text-muted">Business phone</div>
                        <div class="fw-semibold"><?= e($supportPhone) ?></div>
                    </div>
                </div>
                <div class="small text-muted">If the account email is no longer accessible, contact your administrator or support desk.</div>
            </div>
        </section>

        <section class="auth-card__form auth-card__form--login">
            <div class="auth-form-header">
                <div class="auth-form-header__copy">
                    <p class="eyebrow mb-1">Password Recovery</p>
                    <h2 class="mb-1">Reset your password</h2>
                    <p class="text-muted mb-0">Submit the email assigned to your account.</p>
                </div>
                <span class="badge-soft auth-form-badge"><i class="bi bi-envelope-paper"></i>Secure recovery</span>
            </div>

            <form action="<?= e(url('forgot-password')) ?>" method="post" class="auth-login-form" data-loading-form>
                <?= csrf_field() ?>

                <div class="auth-field">
                    <label class="form-label" for="forgot_email">Email address</label>
                    <input
                        id="forgot_email"
                        type="email"
                        name="email"
                        class="form-control"
                        placeholder="you@business.com"
                        value="<?= e($form['email'] ?? '') ?>"
                        autocomplete="username"
                        inputmode="email"
                        spellcheck="false"
                        autocapitalize="none"
                        aria-invalid="<?= !empty($errors['email']) ? 'true' : 'false' ?>"
                        <?= $emailErrorId !== '' ? 'aria-describedby="' . e($emailErrorId) . '"' : '' ?>
                        required
                        autofocus
                    >
                    <?php if ($emailErrorId !== ''): ?>
                        <small class="text-danger d-block mt-2" id="<?= e($emailErrorId) ?>"><?= e($errors['email'][0]) ?></small>
                    <?php endif; ?>
                </div>

                <button type="submit" class="btn btn-primary w-100 auth-submit">
                    <i class="bi bi-send-check me-2"></i>Send Reset Link
                </button>

                <div class="auth-assistance">
                    <div class="small text-muted">If the account exists, a reset link will be prepared without confirming whether the email is registered.</div>
                    <a href="<?= e(url('login')) ?>" class="auth-inline-link"><i class="bi bi-arrow-left me-1"></i>Back to sign in</a>
                </div>
            </form>
        </section>
    </div>
</div>
