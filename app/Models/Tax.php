<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Tax extends Model
{
    protected string $table = 'taxes';

    public function all(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT t.*,
                    (
                        SELECT COUNT(*)
                        FROM products p
                        WHERE p.tax_id = t.id
                          AND p.deleted_at IS NULL
                    ) AS product_count
             FROM taxes t
             WHERE t.company_id = :company_id
             ORDER BY t.name ASC',
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
            'SELECT t.*,
                    (
                        SELECT COUNT(*)
                        FROM products p
                        WHERE p.tax_id = t.id
                          AND p.deleted_at IS NULL
                    ) AS product_count
             FROM taxes t
             WHERE t.id = :id
               AND t.company_id = :company_id
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

        $sql = 'SELECT id FROM taxes WHERE company_id = :company_id AND name = :name';
        $params = ['company_id' => $companyId, 'name' => trim($name)];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createTax(array $payload): int
    {
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            throw new \RuntimeException('Company context is required to create a tax.');
        }

        $payload['company_id'] = $companyId;
        $payload['name'] = trim((string) $payload['name']);
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($payload);
    }

    public function updateTax(int $id, array $payload): bool
    {
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            return false;
        }

        $payload['company_id'] = $companyId;
        $payload['name'] = trim((string) $payload['name']);
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord(
            $payload,
            'id = :id AND company_id = :company_id',
            ['id' => $id, 'company_id' => $companyId]
        );
    }

    public function deleteTax(int $id, ?int $companyId = null): bool
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }

        return $this->execute(
            'DELETE FROM taxes WHERE id = :id AND company_id = :company_id',
            ['id' => $id, 'company_id' => $companyId]
        );
    }

    private function resolveCompanyId(?int $companyId = null): ?int
    {
        $companyId ??= current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }
}
