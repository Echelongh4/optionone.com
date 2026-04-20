<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        string $message = ''
    ) {
        parent::__construct($message !== '' ? $message : 'HTTP error', $statusCode);
    }
}