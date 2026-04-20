<?php

declare(strict_types=1);

return [
    'platform_admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('PLATFORM_ADMIN_EMAILS', ''))
    ), static fn (string $email): bool => $email !== '')),
    'platform_internal_company_name' => (string) env('PLATFORM_INTERNAL_COMPANY_NAME', (string) env('APP_NAME', 'NovaPOS') . ' Platform Operations'),
    'platform_internal_company_slug' => (string) env('PLATFORM_INTERNAL_COMPANY_SLUG', 'platform-operations-internal'),
    'platform_internal_branch_name' => (string) env('PLATFORM_INTERNAL_BRANCH_NAME', 'Control Center'),
    'platform_internal_branch_code' => strtoupper((string) env('PLATFORM_INTERNAL_BRANCH_CODE', 'CTRL')),
    'name' => (string) env('APP_NAME', 'NovaPOS'),
    'developer_name' => (string) env('APP_DEVELOPER_NAME', ''),
    'env' => (string) env('APP_ENV', 'local'),
    'debug' => (bool) env('APP_DEBUG', true),
    'url' => (string) env('APP_URL', 'http://localhost'),
    'base_path' => (string) env('APP_BASE_PATH', ''),
    'timezone' => (string) env('APP_TIMEZONE', 'UTC'),
    'force_https' => (bool) env('APP_FORCE_HTTPS', false),
    'trust_proxy_headers' => (bool) env('APP_TRUST_PROXY_HEADERS', false),
    'session_timeout' => (int) env('SESSION_TIMEOUT', 120),
    'session_cookie' => (string) env('SESSION_COOKIE', 'novapos_session'),
    'remember_lifetime_days' => (int) env('REMEMBER_LIFETIME_DAYS', 30),
    'remember_cookie' => (string) env('REMEMBER_COOKIE', 'pos_remember'),
    'password_reset_lifetime_minutes' => (int) env('PASSWORD_RESET_LIFETIME_MINUTES', 60),
    'email_verification_lifetime_minutes' => (int) env('EMAIL_VERIFICATION_LIFETIME_MINUTES', 1440),
    'email_verification_resend_cooldown_seconds' => (int) env('EMAIL_VERIFICATION_RESEND_COOLDOWN_SECONDS', 90),
    'allow_database_restore' => (bool) env('ALLOW_DB_RESTORE', false),
    'currency' => (string) env('BUSINESS_CURRENCY', 'GHS'),
    'billing_supported_currencies' => array_values(array_filter(array_map(
        static fn (string $currency): string => strtoupper(substr(trim($currency), 0, 10)),
        explode(',', (string) env('BILLING_SUPPORTED_CURRENCIES', 'USD,GHS,EUR,GBP,NGN,KES,ZAR'))
    ), static fn (string $currency): bool => $currency !== '')),
    'billing_default_due_days' => (int) env('BILLING_DEFAULT_DUE_DAYS', 7),
    'billing_default_grace_days' => (int) env('BILLING_DEFAULT_GRACE_DAYS', 7),
    'billing_auto_suspend_days' => (int) env('BILLING_AUTO_SUSPEND_DAYS', 14),
    'uploads_disk' => (string) env('UPLOADS_DISK', 'storage'),
    'inventory' => [
        'presets' => [
            'Received from supplier - PO',
            'Manual restock (stocktake correction)',
            'Stock transfer from branch',
            'Return to inventory',
            'Opening balance adjustment',
        ],
        'large_adjustment_threshold' => (int) env('LARGE_ADJUSTMENT_THRESHOLD', 1000),
    ],
];
