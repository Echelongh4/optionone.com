<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = config('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new PDO(
            $dsn,
            (string) $config['username'],
            (string) $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$connection;
    }

    public static function transaction(callable $callback): mixed
    {
        $connection = self::connection();
        $connection->beginTransaction();

        try {
            $result = $callback($connection);
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}