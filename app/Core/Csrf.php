<?php

declare(strict_types=1);

namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (!Session::has('_csrf_token')) {
            Session::put('_csrf_token', bin2hex(random_bytes(32)));
        }

        return (string) Session::get('_csrf_token');
    }

    public static function validate(?string $token): bool
    {
        $stored = (string) Session::get('_csrf_token', '');

        return $stored !== '' && $token !== null && hash_equals($stored, $token);
    }
}