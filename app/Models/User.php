<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    private static ?bool $supportsUsername = null;
    private static ?bool $supportsTenantSchema = null;
    private static ?bool $supportsEmailVerificationSchema = null;
    private static ?bool $supportsPlatformAdminSchema = null;

    private function baseSelect(): string
    {
        return 'SELECT u.*, r.name AS role_name, b.name AS branch_name,
                       c.name AS company_name, c.slug AS company_slug, c.status AS company_status,
                       CONCAT(u.first_name, " ", u.last_name) AS full_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                INNER JOIN companies c ON c.id = u.company_id
                LEFT JOIN branches b ON b.id = u.branch_id';
    }

    public function listUsers(?int $branchId = null, ?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $sql = $this->baseSelect() . ' WHERE u.deleted_at IS NULL';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND u.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        if ($branchId !== null) {
            $sql .= ' AND (u.branch_id = :branch_id OR u.branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY FIELD(r.name, "Super Admin", "Admin", "Manager", "Cashier"), u.first_name, u.last_name';

        return $this->fetchAll($sql, $params);
    }

    public function allActive(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $sql = $this->baseSelect() . ' WHERE u.status = "active" AND u.deleted_at IS NULL';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND u.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' ORDER BY u.first_name, u.last_name';

        return $this->fetchAll($sql, $params);
    }

    public function notificationRecipients(?int $branchId = null, ?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $sql = 'SELECT DISTINCT u.email,
                       CONCAT(u.first_name, " ", u.last_name) AS full_name,
                       r.name AS role_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.status = "active"
                  AND u.deleted_at IS NULL
                  AND u.email IS NOT NULL
                  AND u.email <> ""
                  AND r.name IN ("Super Admin", "Admin", "Manager")';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND u.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        if ($branchId !== null) {
            $sql .= ' AND (
                        r.name IN ("Super Admin", "Admin")
                        OR u.branch_id = :branch_id
                        OR u.branch_id IS NULL
                     )';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY FIELD(r.name, "Super Admin", "Admin", "Manager"), u.first_name, u.last_name';

        return $this->fetchAll($sql, $params);
    }

    public function phoneNotificationRecipients(?int $branchId = null, ?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $sql = 'SELECT DISTINCT
                    u.id,
                    u.phone,
                    CONCAT(u.first_name, " ", u.last_name) AS full_name,
                    r.name AS role_name
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.status = "active"
                  AND u.deleted_at IS NULL
                  AND u.phone IS NOT NULL
                  AND u.phone <> ""
                  AND r.name IN ("Super Admin", "Admin", "Manager")';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND u.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        if ($branchId !== null) {
            $sql .= ' AND (
                        r.name IN ("Super Admin", "Admin")
                        OR u.branch_id = :branch_id
                        OR u.branch_id IS NULL
                     )';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY FIELD(r.name, "Super Admin", "Admin", "Manager"), u.first_name, u.last_name';

        return $this->fetchAll($sql, $params);
    }

    public function permissionsForUser(int $userId): array
    {
        $rows = $this->fetchAll(
            'SELECT DISTINCT p.name
             FROM users u
             INNER JOIN role_permissions rp ON rp.role_id = u.role_id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE u.id = :user_id
               AND u.deleted_at IS NULL
             ORDER BY p.name',
            ['user_id' => $userId]
        );

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    public function permissionsCatalog(): array
    {
        return $this->fetchAll(
            'SELECT p.*,
                    COUNT(rp.role_id) AS role_count
             FROM permissions p
             LEFT JOIN role_permissions rp ON rp.permission_id = p.id
             GROUP BY p.id, p.name, p.module, p.description, p.created_at, p.updated_at
             ORDER BY p.module, p.name'
        );
    }

    public function rolePermissionIds(): array
    {
        $matrix = [];
        $rows = $this->fetchAll('SELECT role_id, permission_id FROM role_permissions ORDER BY role_id, permission_id');

        foreach ($rows as $row) {
            $roleId = (int) $row['role_id'];
            $matrix[$roleId] ??= [];
            $matrix[$roleId][] = (int) $row['permission_id'];
        }

        return $matrix;
    }

    public function syncRolePermissions(int $roleId, array $permissionIds): void
    {
        $permissionIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $permissionIds)));
        $timestamp = date('Y-m-d H:i:s');

        Database::transaction(function () use ($roleId, $permissionIds, $timestamp): void {
            $statement = $this->db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $statement->execute(['role_id' => $roleId]);

            if ($permissionIds === []) {
                return;
            }

            $insert = $this->db->prepare(
                'INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, :created_at)'
            );

            foreach ($permissionIds as $permissionId) {
                $insert->execute([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                    'created_at' => $timestamp,
                ]);
            }
        });
    }

    public function roles(): array
    {
        return $this->fetchAll('SELECT * FROM roles ORDER BY id');
    }

    public function roleIdByName(string $roleName): ?int
    {
        foreach ($this->roles() as $role) {
            if ((string) ($role['name'] ?? '') === $roleName) {
                return (int) $role['id'];
            }
        }

        return null;
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = :email AND deleted_at IS NULL';
        $params = ['email' => strtolower(trim($email))];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function usernameExists(string $username, ?int $exceptId = null): bool
    {
        if (!$this->supportsUsername()) {
            return false;
        }

        $sql = 'SELECT id FROM users WHERE username = :username AND deleted_at IS NULL';
        $params = ['username' => strtolower(trim($username))];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function supportsUsername(): bool
    {
        if (self::$supportsUsername !== null) {
            return self::$supportsUsername;
        }

        try {
            self::$supportsUsername = $this->fetch("SHOW COLUMNS FROM users LIKE 'username'") !== null;
        } catch (\Throwable) {
            self::$supportsUsername = false;
        }

        return self::$supportsUsername;
    }

    public function resolveSignupUsername(
        string $preferredUsername = '',
        string $email = '',
        string $firstName = '',
        string $lastName = '',
        ?int $exceptId = null
    ): string {
        if (!$this->supportsUsername()) {
            return '';
        }

        $candidates = array_values(array_filter([
            $preferredUsername,
            preg_replace('/@.*$/', '', strtolower(trim($email))),
            trim($firstName . '.' . $lastName, ". \t\n\r\0\x0B"),
            trim($firstName . $lastName),
            trim($firstName),
            'user',
        ], static fn (mixed $value): bool => trim((string) $value) !== ''));

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeUsername((string) $candidate);
            if ($normalized === '') {
                continue;
            }

            return $this->uniqueUsername($normalized, $exceptId);
        }

        return $this->uniqueUsername('user', $exceptId);
    }

    public function supportsTenantSchema(): bool
    {
        if (self::$supportsTenantSchema !== null) {
            return self::$supportsTenantSchema;
        }

        try {
            $requiredTables = [
                'companies',
            ];
            $requiredColumns = [
                ['users', 'company_id'],
                ['branches', 'company_id'],
                ['settings', 'company_id'],
                ['taxes', 'company_id'],
                ['categories', 'company_id'],
                ['customer_groups', 'company_id'],
                ['expense_categories', 'company_id'],
                ['products', 'company_id'],
            ];

            foreach ($requiredTables as $table) {
                if (!$this->tableExists($table)) {
                    self::$supportsTenantSchema = false;
                    return false;
                }
            }

            foreach ($requiredColumns as [$table, $column]) {
                if (!$this->columnExists($table, $column)) {
                    self::$supportsTenantSchema = false;
                    return false;
                }
            }

            self::$supportsTenantSchema = true;
        } catch (\Throwable) {
            self::$supportsTenantSchema = false;
        }

        return self::$supportsTenantSchema;
    }

    public function supportsEmailVerificationSchema(): bool
    {
        if (self::$supportsEmailVerificationSchema !== null) {
            return self::$supportsEmailVerificationSchema;
        }

        try {
            self::$supportsEmailVerificationSchema = $this->supportsTenantSchema()
                && $this->tableExists('email_verification_tokens')
                && $this->columnExists('users', 'email_verified_at');
        } catch (\Throwable) {
            self::$supportsEmailVerificationSchema = false;
        }

        return self::$supportsEmailVerificationSchema;
    }

    public function supportsPlatformAdminSchema(): bool
    {
        if (self::$supportsPlatformAdminSchema !== null) {
            return self::$supportsPlatformAdminSchema;
        }

        try {
            self::$supportsPlatformAdminSchema = $this->supportsTenantSchema()
                && $this->columnExists('users', 'is_platform_admin');
        } catch (\Throwable) {
            self::$supportsPlatformAdminSchema = false;
        }

        return self::$supportsPlatformAdminSchema;
    }

    public function createUser(array $payload): int
    {
        $companyId = isset($payload['company_id']) ? (int) $payload['company_id'] : $this->resolveCompanyId();
        if ($companyId === null) {
            throw new \RuntimeException('Company context is required to create a user.');
        }

        $payload['company_id'] = $companyId;
        $payload['email'] = strtolower(trim((string) $payload['email']));
        if ($this->supportsUsername()) {
            $payload['username'] = strtolower(trim((string) ($payload['username'] ?? '')));
        } else {
            unset($payload['username']);
        }
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($payload);
    }

    public function updateUserProfile(int $id, array $payload): bool
    {
        $companyId = isset($payload['company_id']) ? (int) $payload['company_id'] : $this->resolveCompanyId();
        if ($companyId === null) {
            return false;
        }

        $payload['company_id'] = $companyId;
        $payload['email'] = strtolower(trim((string) $payload['email']));
        if ($this->supportsUsername()) {
            $payload['username'] = strtolower(trim((string) ($payload['username'] ?? '')));
        } else {
            unset($payload['username']);
        }
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord($payload, 'id = :id AND company_id = :company_id', ['id' => $id, 'company_id' => $companyId]);
    }

    public function setPassword(int $id, string $passwordHash): void
    {
        $this->updateRecord([
            'password' => $passwordHash,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        $this->updateRecord([
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->fetch(
            $this->baseSelect() . ' WHERE u.email = :email AND u.deleted_at IS NULL LIMIT 1',
            ['email' => strtolower(trim($email))]
        );
    }

    public function findByUsername(string $username): ?array
    {
        if (!$this->supportsUsername()) {
            return null;
        }

        return $this->fetch(
            $this->baseSelect() . ' WHERE u.username = :username AND u.deleted_at IS NULL LIMIT 1',
            ['username' => strtolower(trim($username))]
        );
    }

    public function findByLogin(string $identifier): ?array
    {
        $identifier = trim(strtolower($identifier));
        if ($identifier === '') {
            return null;
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false) {
            return $this->findByEmail($identifier);
        }

        return $this->findByUsername($identifier);
    }

    public function findById(int $id, ?int $companyId = null): ?array
    {
        $companyId = $this->resolveCompanyId($companyId);
        $sql = $this->baseSelect() . ' WHERE u.id = :id AND u.deleted_at IS NULL';
        $params = ['id' => $id];

        if ($companyId !== null) {
            $sql .= ' AND u.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params);
    }

    public function findByIdGlobal(int $id): ?array
    {
        return $this->fetch(
            $this->baseSelect() . ' WHERE u.id = :id AND u.deleted_at IS NULL LIMIT 1',
            ['id' => $id]
        );
    }

    public function listDirectPlatformAdmins(): array
    {
        if (!$this->supportsPlatformAdminSchema()) {
            return [];
        }

        return $this->fetchAll(
            $this->baseSelect() . ' WHERE u.deleted_at IS NULL
                AND u.is_platform_admin = 1
                ORDER BY FIELD(u.status, "active", "inactive"), u.first_name, u.last_name'
        );
    }

    public function findByEmails(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            $emails
        ), static fn (string $email): bool => $email !== '')));

        if ($emails === []) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($emails as $index => $email) {
            $key = 'email_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $email;
        }

        return $this->fetchAll(
            $this->baseSelect() . ' WHERE u.deleted_at IS NULL
                AND LOWER(u.email) IN (' . implode(', ', $placeholders) . ')
                ORDER BY FIELD(u.status, "active", "inactive"), u.first_name, u.last_name',
            $params
        );
    }

    public function supportAccessTargetForCompany(int $companyId): ?array
    {
        $preferred = $this->fetch(
            $this->baseSelect() . ' WHERE u.company_id = :company_id
                AND u.status = "active"
                AND u.deleted_at IS NULL
                AND u.email_verified_at IS NOT NULL
                AND r.name IN ("Super Admin", "Admin", "Manager")
                ORDER BY FIELD(r.name, "Super Admin", "Admin", "Manager"), u.last_login_at DESC, u.id ASC
                LIMIT 1',
            ['company_id' => $companyId]
        );

        if ($preferred !== null) {
            return $preferred;
        }

        return $this->fetch(
            $this->baseSelect() . ' WHERE u.company_id = :company_id
                AND u.status = "active"
                AND u.deleted_at IS NULL
                AND u.email_verified_at IS NOT NULL
                ORDER BY u.last_login_at DESC, u.id ASC
                LIMIT 1',
            ['company_id' => $companyId]
        );
    }

    public function findRememberedUser(int $id, string $tokenHash): ?array
    {
        return $this->fetch(
            $this->baseSelect() . ' WHERE u.id = :id AND u.remember_token = :token AND u.remember_expires_at >= NOW() AND u.deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'token' => $tokenHash]
        );
    }

    public function touchLogin(int $id): void
    {
        $this->updateRecord([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function storeRememberToken(int $id, string $tokenHash, string $expiresAt): void
    {
        $this->updateRecord([
            'remember_token' => $tokenHash,
            'remember_expires_at' => $expiresAt,
        ], 'id = :id', ['id' => $id]);
    }

    public function clearRememberToken(int $id): void
    {
        $this->updateRecord([
            'remember_token' => null,
            'remember_expires_at' => null,
        ], 'id = :id', ['id' => $id]);
    }

    public function updateSecurityFields(int $id, array $payload): void
    {
        $this->updateRecord($payload, 'id = :id', ['id' => $id]);
    }

    public function setPlatformAdminFlag(int $id, bool $enabled): void
    {
        if (!$this->supportsPlatformAdminSchema()) {
            throw new \RuntimeException('Platform admin schema support is not available.');
        }

        $this->updateRecord([
            'is_platform_admin' => $enabled ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function countDirectPlatformAdmins(?int $exceptUserId = null, ?string $status = null): int
    {
        if (!$this->supportsPlatformAdminSchema()) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS aggregate
                FROM users u
                WHERE u.deleted_at IS NULL
                  AND u.is_platform_admin = 1';
        $params = [];

        if ($exceptUserId !== null) {
            $sql .= ' AND u.id <> :except_user_id';
            $params['except_user_id'] = $exceptUserId;
        }

        if ($status !== null && in_array($status, ['active', 'inactive'], true)) {
            $sql .= ' AND u.status = :status';
            $params['status'] = $status;
        }

        $row = $this->fetch($sql . ' LIMIT 1', $params);

        return (int) ($row['aggregate'] ?? 0);
    }

    private function resolveCompanyId(?int $companyId = null): ?int
    {
        $companyId ??= current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }

    private function normalizeUsername(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            $value = (string) preg_replace('/@.*$/', '', $value);
        }

        $value = (string) preg_replace('/\s+/', '.', $value);
        $value = (string) preg_replace('/[^a-z0-9._-]+/', '', $value);
        $value = trim($value, '._-');

        if ($value === '') {
            return '';
        }

        if (mb_strlen($value) < 3) {
            $value .= substr('user', 0, 3 - mb_strlen($value));
        }

        if (mb_strlen($value) > 100) {
            $value = substr($value, 0, 100);
            $value = rtrim($value, '._-');
        }

        return $value !== '' ? $value : 'user';
    }

    private function uniqueUsername(string $value, ?int $exceptId = null): string
    {
        $base = $this->normalizeUsername($value);
        if ($base === '') {
            $base = 'user';
        }

        $username = $base;
        $suffix = 1;

        while ($this->usernameExists($username, $exceptId)) {
            $suffixText = (string) $suffix;
            $maxBaseLength = 100 - strlen($suffixText);
            $username = substr($base, 0, max(1, $maxBaseLength)) . $suffixText;
            $suffix++;
        }

        return $username;
    }

    private function tableExists(string $table): bool
    {
        return $this->fetch(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
             LIMIT 1',
            ['table_name' => $table]
        ) !== null;
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->fetch(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name
             LIMIT 1',
            [
                'table_name' => $table,
                'column_name' => $column,
            ]
        ) !== null;
    }
}
