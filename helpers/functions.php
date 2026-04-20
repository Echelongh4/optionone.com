<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
}

function app_path(string $path = ''): string
{
    return base_path('app' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
}

function config_path(string $path = ''): string
{
    return base_path('config' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
}

function public_path(string $path = ''): string
{
    return base_path('public' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
}

function request_is_secure(bool $trustProxyHeaders = false): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }

    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    if (!$trustProxyHeaders) {
        return false;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto !== '') {
        $protocols = array_map('trim', explode(',', $forwardedProto));
        if (in_array('https', $protocols, true)) {
            return true;
        }
    }

    $forwardedSsl = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')));
    if ($forwardedSsl === 'on' || $forwardedSsl === '1') {
        return true;
    }

    if ((int) ($_SERVER['HTTP_X_FORWARDED_PORT'] ?? 0) === 443) {
        return true;
    }

    return strtolower(trim((string) ($_SERVER['HTTP_FRONT_END_HTTPS'] ?? ''))) === 'on';
}

function normalize_env_value(mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }

    $trimmed = trim($value);
    $lower = strtolower($trimmed);

    return match ($lower) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        'empty', '(empty)' => '',
        default => is_numeric($trimmed)
            ? (str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed)
            : trim($trimmed, "\"'"),
    };
}

function load_env_file(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim($value);

        $_ENV[$name] = normalize_env_value($value);
        $_SERVER[$name] = $_ENV[$name];
        putenv($name . '=' . (string) $_ENV[$name]);
    }
}

function env(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        return normalize_env_value($_ENV[$key]);
    }

    if (array_key_exists($key, $_SERVER)) {
        return normalize_env_value($_SERVER[$key]);
    }

    return $default;
}

function config(string $key, mixed $default = null): mixed
{
    $segments = explode('.', $key);
    $value = $GLOBALS['app_config'] ?? [];

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function normalize_app_relative_path(string $path = ''): string
{
    $basePath = trim((string) config('app.base_path', ''), '/');
    $normalized = trim($path, '/');

    if ($normalized === '' || $basePath === '') {
        return $normalized;
    }

    while ($normalized === $basePath || str_starts_with($normalized, $basePath . '/')) {
        $normalized = ltrim(substr($normalized, strlen($basePath)), '/');
    }

    return $normalized;
}

function url(string $path = ''): string
{
    $basePath = trim((string) config('app.base_path', ''), '/');
    $path = normalize_app_relative_path($path);
    $prefix = $basePath !== '' ? '/' . $basePath : '';

    return $prefix . ($path !== '' ? '/' . $path : '');
}

function asset(string $path = ''): string
{
    return url('assets/' . ltrim($path, '/'));
}

function absolute_url(string $path = ''): string
{
    $baseUrl = str_replace(' ', '%20', rtrim((string) config('app.url', ''), '/'));
    $path = normalize_app_relative_path($path);

    if ($baseUrl === '') {
        return url($path);
    }

    return $baseUrl . ($path !== '' ? '/' . $path : '');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect_to(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function current_company_id(): ?int
{
    $companyId = $_SESSION['auth_company_id'] ?? null;

    if ($companyId === null || $companyId === '') {
        return null;
    }

    $companyId = (int) $companyId;

    return $companyId > 0 ? $companyId : null;
}

function current_company(): ?array
{
    $companyId = current_company_id();

    if ($companyId === null || !class_exists(\App\Models\Company::class)) {
        return null;
    }

    try {
        return (new \App\Models\Company())->find($companyId);
    } catch (\Throwable) {
        return null;
    }
}

function current_branch_id(): ?int
{
    $user = current_user();

    if ($user !== null && isset($user['branch_id']) && (int) $user['branch_id'] > 0) {
        return (int) $user['branch_id'];
    }

    $companyId = current_company_id();
    if ($companyId === null || !class_exists(\App\Models\Branch::class)) {
        return null;
    }

    try {
        return (new \App\Models\Branch())->defaultId($companyId);
    } catch (\Throwable) {
        return null;
    }
}

function setting_value(string $key, mixed $default = null): mixed
{
    return setting_value_for_company($key, $default, current_company_id());
}

function setting_value_for_company(string $key, mixed $default = null, ?int $companyId = null): mixed
{
    static $settingsByCompany = [];

    if ($companyId === null || $companyId <= 0) {
        return $default;
    }

    if (!array_key_exists($companyId, $settingsByCompany)) {
        try {
            $settingsByCompany[$companyId] = class_exists(\App\Models\Setting::class)
                ? (new \App\Models\Setting())->allAsMap($companyId)
                : [];
        } catch (\Throwable) {
            $settingsByCompany[$companyId] = [];
        }
    }

    return $settingsByCompany[$companyId][$key] ?? $default;
}

function platform_company_id(bool $ensure = false): ?int
{
    if (!class_exists(\App\Models\Company::class)) {
        return null;
    }

    try {
        $company = (new \App\Models\Company())->findBySlug((string) config('app.platform_internal_company_slug', 'platform-operations-internal'));
        if (is_array($company) && (int) ($company['id'] ?? 0) > 0) {
            return (int) $company['id'];
        }

        if ($ensure && class_exists(\App\Services\WorkspaceProvisioner::class)) {
            $workspace = (new \App\Services\WorkspaceProvisioner())->ensurePlatformWorkspace();
            if (is_array($workspace['company'] ?? null) && (int) ($workspace['company']['id'] ?? 0) > 0) {
                return (int) $workspace['company']['id'];
            }
        }
    } catch (\Throwable) {
        return null;
    }

    return null;
}

function platform_setting_value(string $key, mixed $default = null, bool $ensure = false): mixed
{
    return setting_value_for_company($key, $default, platform_company_id($ensure));
}

function normalize_phone_number(?string $phone, ?string $defaultCountryCode = null): string
{
    $phone = trim((string) $phone);
    if ($phone === '') {
        return '';
    }

    $phone = preg_replace('/[^\d+]+/', '', $phone) ?? '';
    if ($phone === '') {
        return '';
    }

    if (str_starts_with($phone, '00')) {
        $phone = '+' . substr($phone, 2);
    }

    if (!str_starts_with($phone, '+')) {
        $defaultCountryCode = preg_replace('/\D+/', '', (string) $defaultCountryCode) ?? '';
        if ($defaultCountryCode === '') {
            return '';
        }

        $local = preg_replace('/\D+/', '', $phone) ?? '';
        if ($local === '') {
            return '';
        }

        if (str_starts_with($local, '0')) {
            $local = substr($local, 1);
        }

        $phone = '+' . $defaultCountryCode . $local;
    }

    return preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1 ? $phone : '';
}

function default_currency_code(): string
{
    return normalize_billing_currency((string) config('app.currency', env('BUSINESS_CURRENCY', 'GHS')), 'GHS');
}

function currency_symbol(): string
{
    return normalize_billing_currency((string) setting_value('currency', default_currency_code()), default_currency_code());
}

function billing_currencies(): array
{
    $configured = config('app.billing_supported_currencies', []);
    $fallbackCurrency = default_currency_code();
    $currentCurrency = normalize_billing_currency((string) setting_value('currency', $fallbackCurrency), $fallbackCurrency);
    $currencies = array_merge(
        [$currentCurrency, $fallbackCurrency],
        is_array($configured) ? $configured : []
    );

    $currencies = array_values(array_unique(array_filter(array_map(
        static fn (mixed $currency): string => strtoupper(substr(trim((string) $currency), 0, 10)),
        $currencies
    ), static fn (string $currency): bool => $currency !== '')));

    return $currencies !== [] ? $currencies : [$fallbackCurrency];
}

function billing_currency_options(array $currencies = []): array
{
    return array_values(array_unique(array_merge(
        array_values(array_filter(array_map(
            static fn (mixed $currency): string => strtoupper(substr(trim((string) $currency), 0, 10)),
            $currencies
        ), static fn (string $currency): bool => $currency !== '')),
        billing_currencies()
    )));
}

function normalize_billing_currency(?string $currency, ?string $fallback = null): string
{
    $currency = strtoupper(substr(trim((string) $currency), 0, 10));
    if ($currency !== '' && preg_match('/^[A-Z0-9]{3,10}$/', $currency) === 1) {
        return $currency;
    }

    $fallback = strtoupper(substr(trim((string) $fallback), 0, 10));
    if ($fallback !== '' && preg_match('/^[A-Z0-9]{3,10}$/', $fallback) === 1) {
        return $fallback;
    }

    return billing_currencies()[0] ?? default_currency_code();
}

function format_currency(float|int|string|null $amount): string
{
    return currency_symbol() . ' ' . number_format((float) $amount, 2);
}

function format_money(float|int|string|null $amount, ?string $currency = null): string
{
    $currency = strtoupper(trim((string) $currency));

    if ($currency === '') {
        return format_currency($amount);
    }

    return $currency . ' ' . number_format((float) $amount, 2);
}

function pos_payment_method_label(?string $method): string
{
    return match (strtolower(trim((string) $method))) {
        'cash' => 'Cash',
        'card' => 'Card',
        'mobile_money' => 'Mobile Money',
        'cheque' => 'Cheque',
        'split' => 'Split Payment',
        'credit' => 'Open Account',
        default => ucwords(str_replace('_', ' ', trim((string) $method))),
    };
}

function pos_payment_detail_lines(array $payment): array
{
    $method = strtolower(trim((string) ($payment['payment_method'] ?? $payment['method'] ?? '')));
    $reference = trim((string) ($payment['reference'] ?? ''));
    $notes = trim((string) ($payment['notes'] ?? ''));
    $chequeNumber = trim((string) ($payment['cheque_number'] ?? ''));
    $chequeBank = trim((string) ($payment['cheque_bank'] ?? ''));
    $chequeDate = trim((string) ($payment['cheque_date'] ?? ''));
    $lines = [];

    if ($method === 'cheque') {
        if ($chequeNumber !== '') {
            $lines[] = 'Cheque #: ' . $chequeNumber;
        }

        if ($chequeBank !== '') {
            $lines[] = 'Bank: ' . $chequeBank;
        }

        if ($chequeDate !== '') {
            $timestamp = strtotime($chequeDate);
            $lines[] = 'Date: ' . ($timestamp !== false ? date('M d, Y', $timestamp) : $chequeDate);
        }
    }

    if ($reference !== '' && !($method === 'cheque' && strcasecmp($reference, $chequeNumber) === 0)) {
        $lines[] = 'Ref: ' . $reference;
    }

    if ($notes !== '') {
        $lines[] = $notes;
    }

    return $lines;
}

function pos_payment_detail_summary(array $payment): string
{
    return implode(' | ', pos_payment_detail_lines($payment));
}

function old(string $key, mixed $default = null): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function csrf_token(): string
{
    return \App\Core\Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function current_user(): ?array
{
    return \App\Core\Auth::user();
}

function can(array|string $roles): bool
{
    return \App\Core\Auth::hasRole($roles);
}

function can_permission(array|string $permissions): bool
{
    return \App\Core\Auth::hasPermission($permissions);
}

function current_user_permissions(): array
{
    return \App\Core\Auth::permissions();
}

function format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB'];
    $size = $bytes / 1024;

    foreach ($units as $unit) {
        if ($size < 1024 || $unit === 'GB') {
            return number_format($size, 2) . ' ' . $unit;
        }

        $size /= 1024;
    }

    return number_format($size, 2) . ' GB';
}
