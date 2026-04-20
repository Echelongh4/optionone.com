<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case Manager = 'Manager';
    case Cashier = 'Cashier';

    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}