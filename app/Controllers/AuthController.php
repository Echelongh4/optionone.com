<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\EmailVerificationToken;
use App\Models\PasswordResetToken;
use App\Models\Setting;
use App\Models\User;
use App\Services\MailService;
use App\Services\WorkspaceProvisioner;
use Throwable;

class AuthController extends Controller
{
    public function showLogin(Request $request): void
    {
        $this->renderLogin(
            $this->tenantSchemaReady()
                ? []
                : ['credentials' => [$this->tenantSchemaMessage()]]
        );
    }

    public function showForgotPassword(Request $request): void
    {
        $this->renderForgotPassword();
    }

    public function showRegister(Request $request): void
    {
        $this->renderRegister(
            $this->registrationSchemaReady()
                ? []
                : ['registration' => [$this->registrationSchemaMessage()]]
        );
    }

    public function sendResetLink(Request $request): void
    {
        $form = [
            'email' => trim((string) $request->input('email', '')),
        ];
        $errors = Validator::validate($request->all(), [
            'email' => 'required|email|max:150',
        ]);

        if ($errors !== []) {
            $this->renderForgotPassword($errors, $form);
            return;
        }

        $user = (new User())->findByEmail($form['email']);

        if ($user !== null && (string) ($user['status'] ?? 'inactive') === 'active') {
            $token = (new PasswordResetToken())->createForUser(
                $user,
                (int) config('app.password_reset_lifetime_minutes', 60)
            );

            $resetLink = absolute_url(
                'reset-password?email=' . rawurlencode((string) $user['email']) . '&token=' . rawurlencode($token)
            );

            $sent = $this->sendPasswordResetMail($user, $resetLink);

            (new AuditLog())->record(
                userId: null,
                action: 'password_reset_request',
                entityType: 'user',
                entityId: (int) $user['id'],
                description: 'Password reset instructions requested for user account.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            if (!$sent && (bool) config('app.debug', false)) {
                Session::flash('warning', 'Mail delivery is not configured in this local environment. Use the generated reset link below.');
                Session::flash('reset_link', $resetLink);
            }
        }

        Session::flash('success', 'If the account exists, password reset instructions have been prepared.');
        $this->redirect('forgot-password');
    }

    public function showResetPassword(Request $request): void
    {
        $form = [
            'email' => trim((string) $request->query('email', '')),
            'token' => trim((string) $request->query('token', '')),
        ];

        $resetRequest = $this->resolveResetRequest($form['email'], $form['token']);
        if ($resetRequest === null) {
            Session::flash('error', 'This password reset link is invalid or expired. Request a new one.');
            $this->redirect('forgot-password');
        }

        $this->renderResetPassword([], $form);
    }

    public function resetPassword(Request $request): void
    {
        $form = [
            'email' => trim((string) $request->input('email', '')),
            'token' => trim((string) $request->input('token', '')),
        ];
        $errors = Validator::validate($request->all(), [
            'email' => 'required|email|max:150',
            'token' => 'required|min:20|max:255',
            'password' => 'required|min:8|max:120',
            'password_confirmation' => 'required|min:8|max:120',
        ]);

        if ((string) $request->input('password') !== (string) $request->input('password_confirmation')) {
            $errors['password_confirmation'][] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            $this->renderResetPassword($errors, $form);
            return;
        }

        $resetRequest = $this->resolveResetRequest($form['email'], $form['token']);
        if ($resetRequest === null) {
            $this->renderResetPassword([
                'credentials' => ['This password reset link is invalid or expired.'],
            ], $form);
            return;
        }

        $userId = (int) $resetRequest['user_id'];
        $tokenId = (int) $resetRequest['id'];
        $passwordHash = password_hash((string) $request->input('password'), PASSWORD_BCRYPT);
        $userModel = new User();
        $tokenModel = new PasswordResetToken();

        Database::transaction(function () use ($userId, $tokenId, $passwordHash, $userModel, $tokenModel): void {
            $userModel->updateSecurityFields($userId, [
                'password' => $passwordHash,
                'remember_token' => null,
                'remember_expires_at' => null,
                'failed_login_attempts' => 0,
                'locked_until' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $tokenModel->markUsed($tokenId);
            $tokenModel->invalidateOtherOpenTokens($userId, $tokenId);
        });

        (new AuditLog())->record(
            userId: $userId,
            action: 'password_reset',
            entityType: 'user',
            entityId: $userId,
            description: 'Password was reset using a recovery token.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Password updated successfully. Sign in with your new credentials.');
        $this->redirect('login');
    }

    public function register(Request $request): void
    {
        $supportsUsername = (new User())->supportsUsername();
        $form = [
            'company_name' => trim((string) $request->input('company_name', '')),
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'username' => trim((string) $request->input('username', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'phone' => trim((string) $request->input('phone', '')),
            'address' => trim((string) $request->input('address', '')),
        ];

        if (!$this->registrationSchemaReady()) {
            $this->renderRegister([
                'registration' => [$this->registrationSchemaMessage()],
            ], $form);
            return;
        }

        $errors = Validator::validate($request->all(), [
            'company_name' => 'required|min:2|max:150',
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|max:50',
            'address' => 'nullable|max:255',
            'password' => 'required|min:8|max:120',
            'password_confirmation' => 'required|min:8|max:120',
        ]);

        if ($supportsUsername) {
            $form['username'] = (new User())->resolveSignupUsername(
                preferredUsername: (string) $form['username'],
                email: (string) $form['email'],
                firstName: (string) $form['first_name'],
                lastName: (string) $form['last_name']
            );
        }

        if ((new User())->emailExists($form['email'])) {
            $errors['email'][] = 'This email address is already in use.';
        }

        if ((string) $request->input('password') !== (string) $request->input('password_confirmation')) {
            $errors['password_confirmation'][] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            $this->renderRegister($errors, $form);
            return;
        }

        try {
            $registrationResult = (new WorkspaceProvisioner())->provisionTenantWorkspace(
                form: $form,
                passwordHash: password_hash((string) $request->input('password'), PASSWORD_BCRYPT),
                supportsUsername: $supportsUsername
            );
        } catch (Throwable $exception) {
            $this->renderRegister([
                'registration' => [$exception->getMessage()],
            ], $form);
            return;
        }

        $user = $registrationResult['user'] ?? null;
        $verificationLink = (string) ($registrationResult['verification_link'] ?? '');

        if ($user === null) {
            $this->renderRegister([
                'registration' => ['The company workspace could not be created.'],
            ], $form);
            return;
        }

        $verificationSent = $verificationLink !== '' && $this->sendEmailVerificationMail($user, $verificationLink);

        (new AuditLog())->record(
            userId: (int) $user['id'],
            action: 'register',
            entityType: 'company',
            entityId: (int) $user['company_id'],
            description: 'Created a new company workspace and owner account.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$verificationSent && (bool) config('app.debug', false) && $verificationLink !== '') {
            Session::flash('warning', 'Mail delivery is not configured in this local environment. Use the generated verification link below.');
            Session::flash('verification_link', $verificationLink);
        } elseif (!$verificationSent) {
            Session::flash('warning', 'Your workspace was created, but the verification email could not be sent. Contact support to complete account activation.');
        }

        Session::flash('success', 'Your company workspace was created. Verify your email address first, then sign in.');
        $this->redirect('login');
    }

    public function login(Request $request): void
    {
        $form = [
            'login' => trim((string) $request->input('login', '')),
        ];
        $supportsUsername = (new User())->supportsUsername();

        if (!$this->tenantSchemaReady()) {
            $this->renderLogin([
                'credentials' => [$this->tenantSchemaMessage()],
            ], $form);
            return;
        }

        $errors = Validator::validate($request->all(), [
            'login' => 'required|max:150',
            'password' => 'required|min:8|max:120',
        ]);

        if (!$supportsUsername && $form['login'] !== '' && filter_var($form['login'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['login'][] = 'Enter a valid email address.';
        }

        if ($errors !== []) {
            $this->renderLogin($errors, $form);
            return;
        }

        $authenticated = Auth::attempt(
            identifier: $form['login'],
            password: (string) $request->input('password'),
            remember: $request->boolean('remember'),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$authenticated) {
            if ($this->emailVerificationSchemaReady()) {
                $user = (new User())->findByLogin($form['login']);
                if (
                    $user !== null
                    && password_verify((string) $request->input('password'), (string) ($user['password'] ?? ''))
                    && (string) ($user['status'] ?? 'inactive') !== 'active'
                ) {
                    $verifiedAt = trim((string) ($user['email_verified_at'] ?? ''));
                    if ($verifiedAt === '') {
                        $this->renderLogin(
                            [
                                'credentials' => ['Verify your email address first. Check the verification message sent during registration, then sign in.'],
                                'verification_pending' => ['If the original link expired or was not delivered, request a fresh verification email below.'],
                            ],
                            $form
                        );
                        return;
                    }
                }
            }

            $this->renderLogin(
                ['credentials' => ['The credentials were rejected or the account is temporarily locked.']],
                $form
            );
            return;
        }

        Session::flash('success', 'Welcome back.');
        $this->redirect(Auth::isPlatformAdmin() ? 'platform' : 'dashboard');
    }

    public function resendVerification(Request $request): void
    {
        $form = [
            'login' => trim((string) $request->input('login', '')),
        ];
        $supportsUsername = (new User())->supportsUsername();

        if (!$this->registrationSchemaReady()) {
            $this->renderLogin([
                'credentials' => [$this->registrationSchemaMessage()],
            ], $form);
            return;
        }

        $errors = Validator::validate($request->all(), [
            'login' => 'required|max:150',
        ]);

        if (!$supportsUsername && $form['login'] !== '' && filter_var($form['login'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['login'][] = 'Enter a valid email address.';
        }

        if ($errors !== []) {
            $errors['verification_pending'][] = 'Enter the same email or username you used for registration to resend the verification email.';
            $this->renderLogin($errors, $form);
            return;
        }

        $user = (new User())->findByLogin($form['login']);
        if ($user === null) {
            Session::flash('success', 'If the account is awaiting verification, a fresh verification email has been prepared.');
            $this->redirect('login');
        }

        $verifiedAt = trim((string) ($user['email_verified_at'] ?? ''));
        if ($verifiedAt !== '') {
            if ((string) ($user['status'] ?? 'inactive') === 'active') {
                Session::flash('info', 'This account is already verified. You can sign in normally.');
            } else {
                Session::flash('warning', 'This account has already been verified, but it is currently inactive. Contact your administrator.');
            }
            $this->redirect('login');
        }

        $tokenModel = new EmailVerificationToken();
        $cooldownSeconds = (int) config('app.email_verification_resend_cooldown_seconds', 90);

        if ($tokenModel->issuedRecentlyForUser((int) $user['id'], $cooldownSeconds)) {
            $this->renderLogin(
                [
                    'credentials' => ['A verification email was sent recently. Please wait a moment before requesting another one.'],
                    'verification_pending' => ['Check your inbox and spam folder first, or wait before requesting another verification email.'],
                ],
                $form
            );
            return;
        }

        $verificationToken = $tokenModel->createForUser(
            $user,
            (int) config('app.email_verification_lifetime_minutes', 1440)
        );
        $verificationLink = absolute_url(
            'verify-email?email=' . rawurlencode((string) $user['email']) . '&token=' . rawurlencode($verificationToken)
        );
        $verificationSent = $this->sendEmailVerificationMail($user, $verificationLink);

        (new AuditLog())->record(
            userId: (int) $user['id'],
            action: 'email_verification_resent',
            entityType: 'user',
            entityId: (int) $user['id'],
            description: 'Requested a new email verification link.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$verificationSent && (bool) config('app.debug', false)) {
            Session::flash('warning', 'Mail delivery is not configured in this local environment. Use the generated verification link below.');
            Session::flash('verification_link', $verificationLink);
        } elseif (!$verificationSent) {
            Session::flash('warning', 'The verification email could not be sent. Contact support to complete account activation.');
        } else {
            Session::flash('success', 'A fresh verification email has been sent. Check your inbox and spam folder.');
        }

        $this->redirect('login');
    }

    public function verifyEmail(Request $request): void
    {
        if (!$this->registrationSchemaReady()) {
            Session::flash('error', $this->registrationSchemaMessage());
            $this->redirect('login');
        }

        $email = trim((string) $request->query('email', ''));
        $token = trim((string) $request->query('token', ''));
        $verificationRequest = $this->resolveEmailVerificationRequest($email, $token);

        if ($verificationRequest === null) {
            if ($email !== '') {
                $this->renderLogin(
                    [
                        'credentials' => ['This email verification link is invalid or expired.'],
                        'verification_pending' => ['Request a fresh verification email below and use the latest link sent to you.'],
                    ],
                    ['login' => $email]
                );
                return;
            }

            Session::flash('error', 'This email verification link is invalid or expired.');
            $this->redirect('login');
        }

        $userId = (int) $verificationRequest['user_id'];
        $tokenId = (int) $verificationRequest['id'];
        $userModel = new User();
        $tokenModel = new EmailVerificationToken();

        Database::transaction(function () use ($userId, $tokenId, $userModel, $tokenModel): void {
            $userModel->updateSecurityFields($userId, [
                'status' => 'active',
                'email_verified_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $tokenModel->markUsed($tokenId);
            $tokenModel->invalidateOtherOpenTokens($userId, $tokenId);
        });

        (new AuditLog())->record(
            userId: $userId,
            action: 'email_verified',
            entityType: 'user',
            entityId: $userId,
            description: 'User verified their email address and activated the account.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Email verified successfully. You can sign in now.');
        $this->redirect('login');
    }

    public function logout(Request $request): void
    {
        $user = Auth::user();

        if (Auth::isImpersonating() && $user !== null) {
            $meta = Auth::impersonationMeta();
            $targetCompanyId = (int) ($meta['target_company_id'] ?? ($user['company_id'] ?? 0));
            $targetCompanyName = (string) ($meta['target_company_name'] ?? ($user['company_name'] ?? 'company'));
            $targetUserName = (string) ($meta['target_user_name'] ?? ($user['full_name'] ?? 'tenant user'));
            $restored = Auth::stopImpersonation();

            if ($restored) {
                (new AuditLog())->record(
                    userId: Auth::id(),
                    action: 'impersonation_end',
                    entityType: 'company',
                    entityId: $targetCompanyId,
                    description: sprintf(
                        'Ended support access for %s after acting as %s via logout.',
                        $targetCompanyName,
                        $targetUserName
                    ),
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                Session::flash('success', 'Returned to the platform admin session.');
                $this->redirect('platform');
            }
        }

        if ($user !== null) {
            (new AuditLog())->record(
                userId: (int) $user['id'],
                action: 'logout',
                entityType: 'user',
                entityId: (int) $user['id'],
                description: 'User logged out of the POS system.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        Auth::logout();
        Session::flash('success', 'You have been signed out.');
        $this->redirect('login');
    }

    private function renderLogin(array $errors = [], array $form = []): void
    {
        $this->render('auth/login', [
            'title' => 'Sign In',
            'errors' => $errors,
            'form' => array_merge(['login' => ''], $form),
            'supportsUsername' => (new User())->supportsUsername(),
            'tenantSchemaReady' => $this->tenantSchemaReady(),
            'platformBootstrapAvailable' => $this->platformBootstrapAvailable(),
            'layoutMode' => 'auth',
        ]);
    }

    private function renderRegister(array $errors = [], array $form = []): void
    {
        $this->render('auth/register', [
            'title' => 'Create Workspace',
            'errors' => $errors,
            'form' => array_merge([
                'company_name' => '',
                'first_name' => '',
                'last_name' => '',
                'username' => '',
                'email' => '',
                'phone' => '',
                'address' => '',
            ], $form),
            'supportsUsername' => (new User())->supportsUsername(),
            'tenantSchemaReady' => $this->registrationSchemaReady(),
            'layoutMode' => 'auth',
        ]);
    }

    private function renderForgotPassword(array $errors = [], array $form = []): void
    {
        $this->render('auth/forgot-password', [
            'title' => 'Recover Access',
            'errors' => $errors,
            'form' => array_merge(['email' => ''], $form),
            'layoutMode' => 'auth',
        ]);
    }

    private function renderResetPassword(array $errors = [], array $form = []): void
    {
        $this->render('auth/reset-password', [
            'title' => 'Reset Password',
            'errors' => $errors,
            'form' => array_merge([
                'email' => '',
                'token' => '',
            ], $form),
            'layoutMode' => 'auth',
        ]);
    }

    private function resolveResetRequest(string $email, string $token): ?array
    {
        if ($email === '' || $token === '') {
            return null;
        }

        return (new PasswordResetToken())->findValid($email, $token);
    }

    private function resolveEmailVerificationRequest(string $email, string $token): ?array
    {
        if ($email === '' || $token === '') {
            return null;
        }

        return (new EmailVerificationToken())->findValid($email, $token);
    }

    private function sendPasswordResetMail(array $user, string $resetLink): bool
    {
        $brandName = (string) (new Setting())->get(
            'business_name',
            config('app.name'),
            isset($user['company_id']) ? (int) $user['company_id'] : null
        );
        $lifetimeMinutes = (int) config('app.password_reset_lifetime_minutes', 60);
        $fullName = trim((string) ($user['full_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));

        $subject = $brandName . ' password reset';
        $htmlBody = '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 12px;">Reset your password</h2>
                <p>Hello ' . e($fullName !== '' ? $fullName : (string) $user['email']) . ',</p>
                <p>We received a request to reset your password for ' . e($brandName) . '.</p>
                <p>
                    <a href="' . e($resetLink) . '" style="display:inline-block;padding:12px 18px;background:#2872A1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                        Reset Password
                    </a>
                </p>
                <p>This link expires in ' . e((string) $lifetimeMinutes) . ' minutes. If you did not request this change, you can safely ignore this email.</p>
            </div>';

        $textBody = "Reset your password for {$brandName}\n\nOpen this link: {$resetLink}\n\nThis link expires in {$lifetimeMinutes} minutes.";

        return (new MailService())->send(
            toEmail: (string) $user['email'],
            toName: $fullName !== '' ? $fullName : (string) $user['email'],
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody
        );
    }

    private function sendEmailVerificationMail(array $user, string $verificationLink): bool
    {
        $brandName = (string) (new Setting())->get(
            'business_name',
            (string) ($user['company_name'] ?? config('app.name')),
            isset($user['company_id']) ? (int) $user['company_id'] : null
        );
        $lifetimeMinutes = (int) config('app.email_verification_lifetime_minutes', 1440);
        $fullName = trim((string) ($user['full_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));

        $subject = $brandName . ' email verification';
        $htmlBody = '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 12px;">Verify your email address</h2>
                <p>Hello ' . e($fullName !== '' ? $fullName : (string) $user['email']) . ',</p>
                <p>Your company workspace has been created for ' . e($brandName) . '.</p>
                <p>Please verify your email address before signing in.</p>
                <p>
                    <a href="' . e($verificationLink) . '" style="display:inline-block;padding:12px 18px;background:#2872A1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                        Verify Email
                    </a>
                </p>
                <p>This link expires in ' . e((string) $lifetimeMinutes) . ' minutes.</p>
            </div>';

        $textBody = "Verify your email for {$brandName}\n\nOpen this link: {$verificationLink}\n\nThis link expires in {$lifetimeMinutes} minutes.";

        return (new MailService())->send(
            toEmail: (string) $user['email'],
            toName: $fullName !== '' ? $fullName : (string) $user['email'],
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody
        );
    }

    private function tenantSchemaReady(): bool
    {
        return (new User())->supportsTenantSchema();
    }

    private function emailVerificationSchemaReady(): bool
    {
        return (new User())->supportsEmailVerificationSchema();
    }

    private function registrationSchemaReady(): bool
    {
        return $this->tenantSchemaReady() && $this->emailVerificationSchemaReady();
    }

    private function platformAdminSchemaReady(): bool
    {
        return $this->registrationSchemaReady() && (new User())->supportsPlatformAdminSchema();
    }

    private function platformBootstrapAvailable(): bool
    {
        if (!$this->platformAdminSchemaReady()) {
            return false;
        }

        $userModel = new User();

        return $userModel->listDirectPlatformAdmins() === []
            && $userModel->findByEmails((array) config('app.platform_admin_emails', [])) === [];
    }

    private function tenantSchemaMessage(): string
    {
        return 'Registration is unavailable until database/migrations/010_multi_company_support.sql is applied. The current database is still using the old single-company schema.';
    }

    private function registrationSchemaMessage(): string
    {
        if (!$this->tenantSchemaReady()) {
            return $this->tenantSchemaMessage();
        }

        return 'Registration is unavailable until database/migrations/011_email_verification_support.sql is applied. The current database is missing the email verification schema.';
    }
}
