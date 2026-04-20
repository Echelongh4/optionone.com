<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ExpenseCategory extends Model
{
    protected string $table = 'expense_categories';

    public function allWithUsage(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT ec.*,
                    (
                        SELECT COUNT(*)
                        FROM expenses e
                        WHERE e.expense_category_id = ec.id
                          AND e.deleted_at IS NULL
                    ) AS expense_count
             FROM expense_categories ec
             WHERE ec.company_id = :company_id
               AND ec.deleted_at IS NULL
             ORDER BY ec.name ASC',
            ['company_id' => $companyId]
        );
    }

    public function find(int $id, ?int $companyId = null): ?array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return null;
        }

        return $this->fetch(
            'SELECT ec.*,
                    (
                        SELECT COUNT(*)
                        FROM expenses e
                        WHERE e.expense_category_id = ec.id
                          AND e.deleted_at IS NULL
                    ) AS expense_count
             FROM expense_categories ec
             WHERE ec.id = :id
               AND ec.company_id = :company_id
               AND ec.deleted_at IS NULL
             LIMIT 1',
            ['id' => $id, 'company_id' => $companyId]
        );
    }

    public function nameExists(string $name, ?int $exceptId = null, ?int $companyId = null): bool
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }

        $sql = 'SELECT id
                FROM expense_categories
                WHERE company_id = :company_id
                  AND deleted_at IS NULL
                  AND name = :name';
        $params = ['company_id' => $companyId, 'name' => trim($name)];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createCategory(array $payload): int
    {
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            throw new \RuntimeException('Company context is required to create an expense category.');
        }

        $payload['company_id'] = $companyId;
        $payload['name'] = trim((string) $payload['name']);
        $payload['description'] = trim((string) ($payload['description'] ?? ''));
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($payload);
    }

    public function updateCategory(int $id, array $payload): bool
    {
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            return false;
        }

        $payload['company_id'] = $companyId;
        $payload['name'] = trim((string) $payload['name']);
        $payload['description'] = trim((string) ($payload['description'] ?? ''));
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord(
            $payload,
            'id = :id AND company_id = :company_id',
            ['id' => $id, 'company_id' => $companyId]
        );
    }

    public function deleteCategory(int $id): bool
    {
        return $this->softDelete($id);
    }

    private function resolveCompanyId(?int $companyId = null): ?int
    {
        $companyId ??= current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }
}
