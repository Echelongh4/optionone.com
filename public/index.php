<?php

declare(strict_types=1);

$projectRoot = (static function (): string {
    $parent = dirname(__DIR__);
    $candidates = [
        $parent,
        $parent . DIRECTORY_SEPARATOR . 'app_root',
        $parent . DIRECTORY_SEPARATOR . 'pos-system',
        $parent . DIRECTORY_SEPARATOR . 'pos-system-production',
    ];

    foreach (glob($parent . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
        $candidates[] = $directory;
    }

    $seen = [];

    foreach ($candidates as $candidate) {
        $candidate = rtrim((string) $candidate, DIRECTORY_SEPARATOR);

        if ($candidate === '' || isset($seen[$candidate])) {
            continue;
        }

        $seen[$candidate] = true;

        if (
            is_file($candidate . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'bootstrap.php')
            && is_dir($candidate . DIRECTORY_SEPARATOR . 'app')
        ) {
            return $candidate;
        }
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Application bootstrap was not found. Upload the full app outside the web root and keep public/index.php in the web root.';
    exit;
})();

define('BASE_PATH', $projectRoot);

require BASE_PATH . '/config/bootstrap.php';

use App\Core\Application;

$app = new Application();
$router = $app->router();

require config_path('routes.php');

$app->run();
