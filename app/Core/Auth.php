<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\AuditLog;
use App\Models\User;

class Auth
{
    private static ?array $user = null;

    public static function boot(): void
    {
        self::logoutIfExpired();

        if (!self::check()) {
            self::attemptRememberLogin();
        }

        if (self::check()) {
            Session::put('last_activity', time());
        }
    }

    public static function attempt(string $identifier, string $password, bool $remember, string $ipAddress, string $userAgent): bool
    {
        $userModel = new User();
        $limiter = new RateLimiter();
        $identifier = trim(strtolower($identifier));

        if (!$userModel->supportsTenantSchema()) {
            self::clearAuthSession();
            return false;
        }

        if ($limiter->isBlocked($identifier, $ipAddress)) {
            return false;
        }

        $user = $userModel->findByLogin($identifier);

        if ($user === null || !password_verify($password, (string) $user['password'])) {
            $limiter->recordFailure($identifier, $ipAddress, $user['id'] ?? null);
            return false;
        }

        if (($user['status'] ?? 'inactive') !== 'active') {
            return false;
        }

        if (($user['company_status'] ?? 'inactive') !== 'active' && !self::matchesPlatformAdmin($user)) {
            return false;
        }

        $limiter->recordSuccess($identifier, $ipAddress, (int) $user['id']);
        self::login($user, $remember);

        (new AuditLog())->record(
            userId: (int) $user['id'],
            action: 'login',
            entityType: 'user',
            entityId: (int) $user['id'],
            description: 'User logged into the POS system.',
            ipAddress: $ipAddress,
            userAgent: $userAgent
        );

        return true;
    }

    public static function login(array $user, bool $remember = false): void
    {
        self::establishSession($user, $remember, true);
    }

    public static function startImpersonation(array $targetUser, string $reason = ''): bool
    {
        $impersonator = self::user();
        if ($impersonator === null) {
            return false;
        }

        $meta = [
            'impersonator_user_id' => (int) $impersonator['id'],
            'impersonator_name' => (string) ($impersonator['full_name'] ?? trim(($impersonator['first_name'] ?? '') . ' ' . ($impersonator['last_name'] ?? ''))),
            'impersonator_email' => (string) ($impersonator['email'] ?? ''),
            'target_user_id' => (int) ($targetUser['id'] ?? 0),
            'target_user_name' => (string) ($targetUser['full_name'] ?? trim(($targetUser['first_name'] ?? '') . ' ' . ($targetUser['last_name'] ?? ''))),
            'target_company_id' => (int) ($targetUser['company_id'] ?? 0),
            'target_company_name' => (string) ($targetUser['company_name'] ?? ''),
            'reason' => trim($reason),
            'started_at' => date('Y-m-d H:i:s'),
        ];

        self::establishSession($targetUser, false, false);
        Session::put('auth_impersonation', $meta);

        return true;
    }

    public static function stopImpersonation(): bool
    {
        $meta = self::impersonationMeta();
        if ($meta === null) {
            return false;
        }

        $impersonatorId = (int) ($meta['impersonator_user_id'] ?? 0);
        if ($impersonatorId <= 0) {
            self::clearAuthSession();
            return false;
        }

        $impersonator = (new User())->findByIdGlobal($impersonatorId);
        if ($impersonator === null) {
            self::clearAuthSession();
            return false;
        }

        self::establishSession($impersonator, false, false);
        Session::forget('auth_impersonation');

        return true;
    }

    public static function logout(): void
    {
        $user = self::user();

        if ($user !== null && !self::isImpersonating()) {
            (new User())->clearRememberToken((int) $user['id']);
        }

        self::clearRememberCookie();
        self::$user = null;
        Session::invalidate();
        Session::start();
    }

    public static function check(): bool
    {
        if (!Session::has('auth_user_id')) {
            return false;
        }

        return self::user() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }

        $userId = Session::get('auth_user_id');

        if ($userId === null) {
            return null;
        }

        $userModel = new User();
        if (!$userModel->supportsTenantSchema()) {
            self::clearAuthSession();
            return null;
        }

        try {
            $user = $userModel->findById((int) $userId);
        } catch (\Throwable) {
            self::clearAuthSession();
            return null;
        }

        if ($user === null) {
            self::clearAuthSession();
            return null;
        }

        $permissions = Session::get('auth_permissions');
        if (!is_array($permissions)) {
            $permissions = $userModel->permissionsForUser((int) $userId);
            Session::put('auth_permissions', $permissions);
        }

        self::$user = array_merge($user, ['permissions' => $permissions]);

        return self::$user;
    }

    public static function id(): ?int
    {
        $user = self::user();

        return $user !== null ? (int) $user['id'] : null;
    }

    public static function hasRole(array|string $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $user = self::user();

        return $user !== null && in_array((string) $user['role_name'], $roles, true);
    }

    public static function permissions(): array
    {
        $user = self::user();

        return is_array($user['permissions'] ?? null) ? $user['permissions'] : [];
    }

    public static function hasPermission(array|string $permissions): bool
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        $userPermissions = self::permissions();

        if ($userPermissions === []) {
            return false;
        }

        foreach ($permissions as $permission) {
            if (in_array((string) $permission, $userPermissions, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isPlatformAdmin(?array $user = null): bool
    {
        if ($user === null && Session::has('auth_is_platform_admin')) {
            return (bool) Session::get('auth_is_platform_admin', false);
        }

        $user ??= self::user();
        if ($user === null) {
            return false;
        }

        return self::matchesPlatformAdmin($user);
    }

    public static function isImpersonating(): bool
    {
        return self::impersonationMeta() !== null;
    }

    public static function impersonationMeta(): ?array
    {
        $meta = Session::get('auth_impersonation');

        return is_array($meta) ? $meta : null;
    }

    public static function refresh(): void
    {
        $userId = (int) Session::get('auth_user_id', 0);
        if ($userId <= 0) {
            self::$user = null;
            return;
        }

        $userModel = new User();
        $user = $userModel->findByIdGlobal($userId);

        if ($user === null) {
            self::clearAuthSession();
            return;
        }

        $permissions = $userModel->permissionsForUser($userId);
        Session::put('auth_company_id', (int) ($user['company_id'] ?? 0));
        Session::put('auth_company_name', (string) ($user['company_name'] ?? ''));
        Session::put('auth_role_name', (string) ($user['role_name'] ?? ''));
        Session::put('auth_branch_id', $user['branch_id'] !== null ? (int) $user['branch_id'] : null);
        Session::put('auth_is_platform_admin', self::matchesPlatformAdmin($user));
        Session::put('auth_permissions', $permissions);
        self::$user = array_merge($user, ['permissions' => $permissions]);
    }

    private static function logoutIfExpired(): void
    {
        if (!self::check()) {
            return;
        }

        $lastActivity = (int) Session::get('last_activity', time());
        $timeoutMinutes = (int) config('app.session_timeout', 120);

        if ((time() - $lastActivity) > ($timeoutMinutes * 60)) {
            self::logout();
            Session::flash('warning', 'Your session expired due to inactivity.');
        }
    }

    private static function attemptRememberLogin(): void
    {
        $cookieName = (string) config('app.remember_cookie', 'pos_remember');
        $cookie = $_COOKIE[$cookieName] ?? null;
        $userModel = new User();

        if (!$userModel->supportsTenantSchema()) {
            self::clearAuthSession();
            return;
        }

        if ($cookie === null || !str_contains((string) $cookie, '|')) {
            return;
        }

        [$userId, $token] = explode('|', (string) $cookie, 2);
        $user = $userModel->findRememberedUser((int) $userId, hash('sha256', $token));

        if ($user === null) {
            self::clearRememberCookie();
            return;
        }

        self::login($user, false);
    }

    private static function clearRememberCookie(): void
    {
        setcookie(
            (string) config('app.remember_cookie', 'pos_remember'),
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => request_is_secure((bool) config('app.trust_proxy_headers', false)),
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    private static function clearAuthSession(): void
    {
        self::$user = null;
        self::clearRememberCookie();
        Session::forget('auth_user_id');
        Session::forget('auth_company_id');
        Session::forget('auth_company_name');
        Session::forget('auth_role_name');
        Session::forget('auth_branch_id');
        Session::forget('auth_is_platform_admin');
        Session::forget('auth_permissions');
        Session::forget('auth_impersonation');
        Session::forget('last_activity');
    }

    private static function establishSession(array $user, bool $remember, bool $touchLogin): void
    {
        $permissions = (new User())->permissionsForUser((int) $user['id']);
        Session::regenerate();
        Session::put('auth_user_id', (int) $user['id']);
        Session::put('auth_company_id', (int) ($user['company_id'] ?? 0));
        Session::put('auth_company_name', (string) ($user['company_name'] ?? ''));
        Session::put('auth_role_name', (string) $user['role_name']);
        Session::put('auth_branch_id', $user['branch_id'] !== null ? (int) $user['branch_id'] : null);
        Session::put('auth_is_platform_admin', self::matchesPlatformAdmin($user));
        Session::put('auth_permissions', $permissions);
        Session::put('last_activity', time());
        self::$user = array_merge($user, ['permissions' => $permissions]);

        if ($touchLogin) {
            (new User())->touchLogin((int) $user['id']);
        }

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . config('app.remember_lifetime_days', 30) . ' days'));
            (new User())->storeRememberToken((int) $user['id'], hash('sha256', $token), $expiresAt);

            setcookie(
                (string) config('app.remember_cookie', 'pos_remember'),
                (string) $user['id'] . '|' . $token,
                [
                    'expires' => strtotime($expiresAt),
                    'path' => '/',
                    'secure' => request_is_secure((bool) config('app.trust_proxy_headers', false)),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        }
    }

    private static function matchesPlatformAdmin(array $user): bool
    {
        if ((int) ($user['is_platform_admin'] ?? 0) === 1) {
            return true;
        }

        $email = strtolower(trim((string) ($user['email'] ?? '')));
        $platformAdminEmails = config('app.platform_admin_emails', []);
        if (!is_array($platformAdminEmails) || $email === '') {
            return false;
        }

        return in_array($email, $platformAdminEmails, true);
    }
}
