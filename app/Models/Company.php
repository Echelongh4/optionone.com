<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Company extends Model
{
    protected string $table = 'companies';

    public function platformSummary(): array
    {
        return $this->fetch(
            'SELECT
                COUNT(*) AS total_companies,
                COALESCE(SUM(CASE WHEN c.status = "active" THEN 1 ELSE 0 END), 0) AS active_companies,
                COALESCE(SUM(CASE WHEN c.status = "inactive" THEN 1 ELSE 0 END), 0) AS inactive_companies,
                COALESCE(SUM(CASE WHEN c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS new_companies_30d,
                COALESCE(SUM((SELECT COUNT(*) FROM branches b WHERE b.company_id = c.id AND b.deleted_at IS NULL)), 0) AS total_branches,
                COALESCE(SUM((SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.deleted_at IS NULL)), 0) AS total_users,
                COALESCE(SUM(CASE WHEN EXISTS (
                    SELECT 1
                    FROM users u
                    INNER JOIN roles r ON r.id = u.role_id
                    WHERE u.company_id = c.id
                      AND u.deleted_at IS NULL
                      AND r.name IN ("Super Admin", "Admin")
                      AND u.email_verified_at IS NULL
                ) THEN 1 ELSE 0 END), 0) AS companies_pending_owner_verification
             FROM companies c'
        ) ?? [
            'total_companies' => 0,
            'active_companies' => 0,
            'inactive_companies' => 0,
            'new_companies_30d' => 0,
            'total_branches' => 0,
            'total_users' => 0,
            'companies_pending_owner_verification' => 0,
        ];
    }

    public function platformList(array $filters = []): array
    {
        $sql = $this->platformSelect();
        $params = [];
        $clauses = [];

        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $onboarding = trim((string) ($filters['onboarding'] ?? ''));
        $activity = trim((string) ($filters['activity'] ?? ''));

        if ($search !== '') {
            $clauses[] = '(c.name LIKE :search
                OR c.slug LIKE :search_slug
                OR c.email LIKE :search_email
                OR c.phone LIKE :search_phone
                OR EXISTS (
                    SELECT 1
                    FROM users u
                    WHERE u.company_id = c.id
                      AND u.deleted_at IS NULL
                      AND (
                          u.email LIKE :search_owner_email
                          OR CONCAT(u.first_name, " ", u.last_name) LIKE :search_owner_name
                      )
                ))';
            $params['search'] = '%' . $search . '%';
            $params['search_slug'] = '%' . $search . '%';
            $params['search_email'] = '%' . $search . '%';
            $params['search_phone'] = '%' . $search . '%';
            $params['search_owner_email'] = '%' . $search . '%';
            $params['search_owner_name'] = '%' . $search . '%';
        }

        if (in_array($status, ['active', 'inactive'], true)) {
            $clauses[] = 'c.status = :status';
            $params['status'] = $status;
        }

        if ($onboarding === 'verified_owner') {
            $clauses[] = 'EXISTS (
                SELECT 1
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.company_id = c.id
                  AND u.deleted_at IS NULL
                  AND r.name IN ("Super Admin", "Admin")
                  AND u.email_verified_at IS NOT NULL
            )';
        } elseif ($onboarding === 'pending_owner_verification') {
            $clauses[] = 'EXISTS (
                SELECT 1
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.company_id = c.id
                  AND u.deleted_at IS NULL
                  AND r.name IN ("Super Admin", "Admin")
                  AND u.email_verified_at IS NULL
            )';
        } elseif ($onboarding === 'no_owner') {
            $clauses[] = 'NOT EXISTS (
                SELECT 1
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.company_id = c.id
                  AND u.deleted_at IS NULL
                  AND r.name IN ("Super Admin", "Admin")
            )';
        }

        if ($activity === 'active_30d') {
            $clauses[] = 'EXISTS (
                SELECT 1
                FROM users u
                WHERE u.company_id = c.id
                  AND u.deleted_at IS NULL
                  AND u.last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            )';
        } elseif ($activity === 'inactive_30d') {
            $clauses[] = 'NOT EXISTS (
                SELECT 1
                FROM users u
                WHERE u.company_id = c.id
                  AND u.deleted_at IS NULL
                  AND u.last_login_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            )';
        } elseif ($activity === 'never_logged_in') {
            $clauses[] = 'NOT EXISTS (
                SELECT 1
                FROM users u
                WHERE u.company_id = c.id
                  AND u.deleted_at IS NULL
                  AND u.last_login_at IS NOT NULL
            )';
        }

        if ($clauses !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $sql .= ' ORDER BY c.created_at DESC, c.id DESC';

        return $this->fetchAll($sql, $params);
    }

    public function find(int $id): ?array
    {
        return $this->fetch(
            'SELECT *
             FROM companies
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->fetch(
            'SELECT *
             FROM companies
             WHERE slug = :slug
             LIMIT 1',
            ['slug' => trim($slug)]
        );
    }

    public function findDetailed(int $id): ?array
    {
        return $this->fetch(
            $this->platformSelect() . ' WHERE c.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function createCompany(array $payload): int
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));

        return $this->insert([
            'name' => $name,
            'slug' => $slug !== '' ? $this->uniqueSlug($slug) : $this->uniqueSlug($name),
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'address' => trim((string) ($payload['address'] ?? '')),
            'status' => (string) ($payload['status'] ?? 'active'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateCompanyProfile(int $id, array $payload): bool
    {
        return $this->updateRecord([
            'name' => trim((string) ($payload['name'] ?? '')),
            'email' => trim((string) ($payload['email'] ?? '')),
            'phone' => trim((string) ($payload['phone'] ?? '')),
            'address' => trim((string) ($payload['address'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function updateStatus(int $id, string $status): bool
    {
        return $this->updateRecord([
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function primaryOwner(int $companyId): ?array
    {
        return $this->fetch(
            'SELECT u.*, r.name AS role_name,
                    CONCAT(u.first_name, " ", u.last_name) AS full_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.company_id = :company_id
               AND u.deleted_at IS NULL
               AND r.name IN ("Super Admin", "Admin")
             ORDER BY FIELD(r.name, "Super Admin", "Admin"), u.id ASC
             LIMIT 1',
            ['company_id' => $companyId]
        );
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM companies WHERE slug = :slug';
        $params = ['slug' => $this->slugify($slug)];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function uniqueSlug(string $value, ?int $exceptId = null): string
    {
        $base = $this->slugify($value);
        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug, $exceptId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));

        return $slug !== '' ? $slug : 'company';
    }

    private function platformSelect(): string
    {
        return 'SELECT c.*,
                       (SELECT COUNT(*) FROM branches b WHERE b.company_id = c.id AND b.deleted_at IS NULL) AS branch_count,
                       (SELECT COUNT(*) FROM branches b WHERE b.company_id = c.id AND b.status = "active" AND b.deleted_at IS NULL) AS active_branch_count,
                       (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.deleted_at IS NULL) AS user_count,
                       (SELECT COUNT(*) FROM users u WHERE u.company_id = c.id AND u.status = "active" AND u.deleted_at IS NULL) AS active_user_count,
                       (SELECT COUNT(*)
                        FROM users u
                        INNER JOIN roles r ON r.id = u.role_id
                        WHERE u.company_id = c.id
                          AND u.deleted_at IS NULL
                          AND r.name IN ("Super Admin", "Admin")
                          AND u.email_verified_at IS NULL) AS pending_owner_verification_count,
                       (SELECT MAX(u.last_login_at) FROM users u WHERE u.company_id = c.id AND u.deleted_at IS NULL) AS last_login_at,
                       (SELECT CONCAT(u.first_name, " ", u.last_name)
                        FROM users u
                        INNER JOIN roles r ON r.id = u.role_id
                        WHERE u.company_id = c.id
                          AND u.deleted_at IS NULL
                          AND r.name IN ("Super Admin", "Admin")
                        ORDER BY FIELD(r.name, "Super Admin", "Admin"), u.id ASC
                        LIMIT 1) AS owner_name,
                       (SELECT u.email
                        FROM users u
                        INNER JOIN roles r ON r.id = u.role_id
                        WHERE u.company_id = c.id
                          AND u.deleted_at IS NULL
                          AND r.name IN ("Super Admin", "Admin")
                        ORDER BY FIELD(r.name, "Super Admin", "Admin"), u.id ASC
                        LIMIT 1) AS owner_email,
                       (SELECT u.status
                        FROM users u
                        INNER JOIN roles r ON r.id = u.role_id
                        WHERE u.company_id = c.id
                          AND u.deleted_at IS NULL
                          AND r.name IN ("Super Admin", "Admin")
                        ORDER BY FIELD(r.name, "Super Admin", "Admin"), u.id ASC
                        LIMIT 1) AS owner_status,
                       (SELECT u.email_verified_at
                        FROM users u
                        INNER JOIN roles r ON r.id = u.role_id
                        WHERE u.company_id = c.id
                          AND u.deleted_at IS NULL
                          AND r.name IN ("Super Admin", "Admin")
                        ORDER BY FIELD(r.name, "Super Admin", "Admin"), u.id ASC
                        LIMIT 1) AS owner_verified_at
                FROM companies c';
    }
}
