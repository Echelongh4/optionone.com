<?php

declare(strict_types=1);

require dirname(__DIR__) . '/helpers/functions.php';

load_env_file(base_path('.env'));

$composerAutoload = base_path('vendor/autoload.php');

if (is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }

        $relative = substr($class, 4);
        $file = app_path(str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php');

        if (is_file($file)) {
            require $file;
        }
    });
}

$GLOBALS['app_config'] = [
    'app' => require config_path('app.php'),
    'database' => require config_path('database.php'),
    'mail' => require config_path('mail.php'),
];

date_default_timezone_set((string) config('app.timezone', 'UTC'));

if (!is_dir(storage_path('logs'))) {
    @mkdir(storage_path('logs'), 0775, true);
}

error_reporting(E_ALL);
ini_set('display_errors', (bool) config('app.debug', false) ? '1' : '0');
ini_set('log_errors', '1');

$phpErrorLog = storage_path('logs/php-errors.log');
if (is_dir(dirname($phpErrorLog))) {
    ini_set('error_log', $phpErrorLog);
}

if (PHP_SAPI === 'cli') {
    return;
}

$isSecure = request_is_secure((bool) config('app.trust_proxy_headers', false));

if ((bool) config('app.force_https', false)) {
    if (!$isSecure && isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])) {
        header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    if ((bool) config('app.force_https', false) && $isSecure) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

\App\Core\Session::start();
\App\Core\Auth::boot();
