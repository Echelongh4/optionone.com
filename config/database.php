<?php

declare(strict_types=1);

return [
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => (string) env('DB_DATABASE', 'pos_system'),
    'username' => (string) env('DB_USERNAME', 'root'),
    'password' => (string) env('DB_PASSWORD', ''),
    'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
];
