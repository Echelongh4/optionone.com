<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }
}