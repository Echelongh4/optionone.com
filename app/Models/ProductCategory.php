<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class ProductCategory extends Model
{
    protected string $table = 'categories';

    public function allWithUsage(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT c.*,
                    p.name AS parent_name,
                    (
                        SELECT COUNT(*)
                        FROM products pr
                        WHERE pr.category_id = c.id
                          AND pr.deleted_at IS NULL
                    ) AS product_count,
                    (
                        SELECT COUNT(*)
                        FROM categories child
                        WHERE child.parent_id = c.id
                          AND child.deleted_at IS NULL
                    ) AS child_count
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             WHERE c.company_id = :company_id
               AND c.deleted_at IS NULL
             ORDER BY COALESCE(p.name, c.name), c.name',
            ['company_id' => $companyId]
        );
    }

    public function parentOptions(?int $exceptId = null, ?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        $sql = 'SELECT id, name, parent_id
                FROM categories
                WHERE company_id = :company_id
                  AND deleted_at IS NULL';
        $params = ['company_id' => $companyId];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $sql .= ' ORDER BY name';

        return $this->fetchAll($sql, $params);
    }

    public function find(int $id, ?int $companyId = null): ?array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return null;
        }

        return $this->fetch(
            'SELECT c.*,
                    p.name AS parent_name,
                    (
                        SELECT COUNT(*)
                        FROM products pr
                        WHERE pr.category_id = c.id
                          AND pr.deleted_at IS NULL
                    ) AS product_count,
                    (
                        SELECT COUNT(*)
                        FROM categories child
                        WHERE child.parent_id = c.id
                          AND child.deleted_at IS NULL
                    ) AS child_count
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             WHERE c.id = :id
               AND c.company_id = :company_id
               AND c.deleted_at IS NULL
             LIMIT 1',
            ['id' => $id, 'company_id' => $companyId]
        );
    }

    public function nameExists(string $name, ?int $parentId = null, ?int $exceptId = null, ?int $companyId = null): bool
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }

        $sql = 'SELECT id
                FROM categories
                WHERE company_id = :company_id
                  AND deleted_at IS NULL
                  AND name = :name
                  AND ' . ($parentId === null ? 'parent_id IS NULL' : 'parent_id = :parent_id');
        $params = ['company_id' => $companyId, 'name' => trim($name)];

        if ($parentId !== null) {
            $params['parent_id'] = $parentId;
        }

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createCategory(array $payload): int
    {
        $name = trim((string) $payload['name']);
        $parentId = $payload['parent_id'] !== null ? (int) $payload['parent_id'] : null;
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            throw new \RuntimeException('Company context is required to create a category.');
        }

        return $this->insert([
            'company_id' => $companyId,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $this->uniqueSlug($name, null, $companyId),
            'description' => trim((string) ($payload['description'] ?? '')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateCategory(int $id, array $payload): bool
    {
        $name = trim((string) $payload['name']);
        $parentId = $payload['parent_id'] !== null ? (int) $payload['parent_id'] : null;
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            return false;
        }

        return $this->updateRecord([
            'company_id' => $companyId,
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $this->uniqueSlug($name, $id, $companyId),
            'description' => trim((string) ($payload['description'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id AND company_id = :company_id', ['id' => $id, 'company_id' => $companyId]);
    }

    public function deleteCategory(int $id): bool
    {
        return $this->softDelete($id);
    }

    public function descendantIds(int $categoryId, ?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        $descendants = [];
        $pending = [$categoryId];

        while ($pending !== []) {
            $currentId = array_shift($pending);
            $children = $this->fetchAll(
                'SELECT id
                 FROM categories
                 WHERE parent_id = :parent_id
                   AND company_id = :company_id
                   AND deleted_at IS NULL',
                ['parent_id' => $currentId, 'company_id' => $companyId]
            );

            foreach ($children as $child) {
                $childId = (int) ($child['id'] ?? 0);
                if ($childId === 0 || in_array($childId, $descendants, true)) {
                    continue;
                }

                $descendants[] = $childId;
                $pending[] = $childId;
            }
        }

        return $descendants;
    }

    private function uniqueSlug(string $value, ?int $exceptId = null, ?int $companyId = null): string
    {
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
        $base = $base !== '' ? $base : 'category';
        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug, $exceptId, $companyId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $exceptId = null, ?int $companyId = null): bool
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }

        $sql = 'SELECT id FROM categories WHERE company_id = :company_id AND slug = :slug';
        $params = ['company_id' => $companyId, 'slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    private function resolveCompanyId(?int $companyId = null): ?int
    {
        $companyId ??= current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }
}
