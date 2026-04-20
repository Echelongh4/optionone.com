<?php

declare(strict_types=1);

namespace App\Core;

class Logger
{
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $directory = storage_path('logs');

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            error_log($level . ': ' . $message);
            return;
        }

        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        $payload = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            $payload = sprintf('[%s] %s %s', date('c'), $level, $message);
        }

        @file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'app.log',
            $payload . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
