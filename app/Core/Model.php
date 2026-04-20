<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::connection();
    }

    protected function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    protected function fetch(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();

        return $result !== false ? $result : null;
    }

    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    protected function execute(string $sql, array $params = []): bool
    {
        return $this->query($sql, $params)->rowCount() >= 0;
    }

    protected function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);

        return (int) $this->db->lastInsertId();
    }

    protected function updateRecord(array $data, string $where, array $whereParams = []): bool
    {
        $assignments = array_map(static fn (string $column): string => $column . ' = :' . $column, array_keys($data));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->table, implode(', ', $assignments), $where);

        return $this->execute($sql, array_merge($data, $whereParams));
    }

    protected function softDelete(int $id): bool
    {
        return $this->updateRecord(
            data: ['deleted_at' => date('Y-m-d H:i:s')],
            where: $this->primaryKey . ' = :id',
            whereParams: ['id' => $id]
        );
    }
}