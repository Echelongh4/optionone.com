<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Branch extends Model
{
    protected string $table = 'branches';

    public function all(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT b.*,
                    (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id AND u.deleted_at IS NULL) AS total_users,
                    (SELECT COUNT(*) FROM products p WHERE p.branch_id = b.id AND p.deleted_at IS NULL) AS total_products
             FROM branches b
             WHERE b.company_id = :company_id
               AND b.deleted_at IS NULL
             ORDER BY b.is_default DESC, b.name ASC',
            ['company_id' => $companyId]
        );
    }

    public function active(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT *
             FROM branches
             WHERE company_id = :company_id
               AND status = "active"
               AND deleted_at IS NULL
             ORDER BY is_default DESC, name ASC',
            ['company_id' => $companyId]
        );
    }

    public function defaultId(?int $companyId = null): ?int
    {
        $branch = $this->fetchDefault($companyId);

        return $branch !== null ? (int) $branch['id'] : null;
    }

    public function find(int $id, ?int $companyId = null): ?array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $sql = 'SELECT *
                FROM branches
                WHERE id = :id
                  AND deleted_at IS NULL';
        $params = ['id' => $id];

        if ($companyId !== null) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params);
    }

    public function codeExists(string $code, ?int $exceptId = null, ?int $companyId = null): bool
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }

        $sql = 'SELECT id
                FROM branches
                WHERE company_id = :company_id
                  AND code = :code
                  AND deleted_at IS NULL';
        $params = [
            'company_id' => $companyId,
            'code' => strtoupper(trim($code)),
        ];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createBranch(array $payload): int
    {
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            throw new \RuntimeException('Company context is required to create a branch.');
        }

        if ((int) ($payload['is_default'] ?? 0) === 1) {
            $this->db->prepare(
                'UPDATE branches
                 SET is_default = 0, updated_at = NOW()
                 WHERE company_id = :company_id
                   AND deleted_at IS NULL'
            )->execute(['company_id' => $companyId]);
            $payload['status'] = 'active';
        }

        $payload['company_id'] = $companyId;
        $payload['code'] = strtoupper(trim((string) $payload['code']));
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($payload);
    }

    public function updateBranch(int $id, array $payload): bool
    {
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        $existing = $this->find($id, $companyId);
        if ($existing === null) {
            return false;
        }

        if ((int) ($payload['is_default'] ?? 0) === 1) {
            $this->db->prepare(
                'UPDATE branches
                 SET is_default = 0, updated_at = NOW()
                 WHERE company_id = :company_id
                   AND deleted_at IS NULL'
            )->execute(['company_id' => (int) $existing['company_id']]);
            $payload['status'] = 'active';
        } elseif ((int) $existing['is_default'] === 1) {
            $payload['is_default'] = 1;
        }

        $payload['company_id'] = (int) $existing['company_id'];
        $payload['code'] = strtoupper(trim((string) $payload['code']));
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord(
            $payload,
            'id = :id AND company_id = :company_id',
            ['id' => $id, 'company_id' => (int) $existing['company_id']]
        );
    }

    private function fetchDefault(?int $companyId = null): ?array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return null;
        }

        return $this->fetch(
            'SELECT *
             FROM branches
             WHERE company_id = :company_id
               AND is_default = 1
               AND deleted_at IS NULL
             LIMIT 1',
            ['company_id' => $companyId]
        );
    }

    private function resolveCompanyId(?int $companyId = null): ?int
    {
        $companyId ??= current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }
}
