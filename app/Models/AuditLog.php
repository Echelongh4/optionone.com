<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class AuditLog extends Model
{
    protected string $table = 'audit_logs';

    private function baseSelect(): string
    {
        return 'SELECT al.*,
                       CONCAT(u.first_name, " ", u.last_name) AS user_name,
                       r.name AS role_name,
                       b.name AS branch_name,
                       c.name AS actor_company_name
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN branches b ON b.id = u.branch_id
                LEFT JOIN companies c ON c.id = u.company_id';
    }

    public function record(
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        string $ipAddress,
        string $userAgent
    ): void {
        $this->insert([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'ip_address' => $ipAddress,
            'user_agent' => mb_substr($userAgent, 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recent(int $limit = 20, ?int $userId = null): array
    {
        $limit = max(1, $limit);
        $sql = $this->baseSelect();
        $params = [];

        if ($userId !== null) {
            $sql .= ' WHERE al.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $sql = $this->applyCompanyScope($sql, $params);
        $sql .= ' ORDER BY al.created_at DESC LIMIT ' . $limit;

        return $this->fetchAll($sql, $params);
    }

    public function platformRecent(int $limit = 20): array
    {
        $limit = max(1, $limit);

        return $this->fetchAll(
            $this->baseSelect() . ' ORDER BY al.created_at DESC LIMIT ' . $limit
        );
    }

    public function recentForCompany(int $companyId, int $limit = 20): array
    {
        $limit = max(1, $limit);

        return $this->fetchAll(
            $this->baseSelect() . ' WHERE (
                    u.company_id = :company_id_user
                    OR (al.entity_type = "company" AND al.entity_id = :company_id_company)
                    OR (
                        al.entity_type = "branch"
                        AND EXISTS (
                            SELECT 1
                            FROM branches bx
                            WHERE bx.id = al.entity_id
                              AND bx.company_id = :company_id_branch
                        )
                    )
                    OR (
                        al.entity_type = "user"
                        AND EXISTS (
                            SELECT 1
                            FROM users ux
                            WHERE ux.id = al.entity_id
                              AND ux.company_id = :company_id_user_entity
                        )
                    )
                )
                ORDER BY al.created_at DESC
                LIMIT ' . $limit,
            [
                'company_id_user' => $companyId,
                'company_id_company' => $companyId,
                'company_id_branch' => $companyId,
                'company_id_user_entity' => $companyId,
            ]
        );
    }

    public function search(array $filters = [], ?int $limit = 250): array
    {
        $params = [];
        $sql = $this->applyFilters($this->baseSelect(), $filters, $params);
        $sql = $this->applyCompanyScope($sql, $params);
        $sql .= ' ORDER BY al.created_at DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit);
        }

        return $this->fetchAll($sql, $params);
    }

    public function summary(array $filters = []): array
    {
        $params = [];
        $sql = 'SELECT COUNT(*) AS total_events,
                       SUM(CASE WHEN DATE(al.created_at) = CURDATE() THEN 1 ELSE 0 END) AS events_today,
                       COUNT(DISTINCT al.user_id) AS active_users,
                       SUM(CASE WHEN al.action IN ("delete", "void", "backup", "restore", "download", "toggle_status") THEN 1 ELSE 0 END) AS high_impact_events
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN branches b ON b.id = u.branch_id';

        $sql = $this->applyFilters($sql, $filters, $params);
        $sql = $this->applyCompanyScope($sql, $params);

        return $this->fetch($sql, $params) ?? [
            'total_events' => 0,
            'events_today' => 0,
            'active_users' => 0,
            'high_impact_events' => 0,
        ];
    }

    public function actions(): array
    {
        $params = [];
        $sql = $this->applyCompanyScope(
            'SELECT DISTINCT al.action
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id',
            $params
        ) . ' ORDER BY al.action';

        return array_map(
            static fn (array $row): string => (string) $row['action'],
            $this->fetchAll($sql, $params)
        );
    }

    public function entityTypes(): array
    {
        $params = [];
        $sql = $this->applyCompanyScope(
            'SELECT DISTINCT al.entity_type
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id',
            $params
        ) . ' ORDER BY al.entity_type';

        return array_map(
            static fn (array $row): string => (string) $row['entity_type'],
            $this->fetchAll($sql, $params)
        );
    }

    public function exportDataset(array $filters = []): array
    {
        $rows = $this->search($filters, null);

        return [
            'title' => 'Audit Trail',
            'filename' => 'audit-trail-' . date('Ymd-His') . '.csv',
            'headers' => ['When', 'User', 'Role', 'Branch', 'Action', 'Entity', 'Entity ID', 'Description', 'IP Address', 'User Agent'],
            'rows' => array_map(static function (array $row): array {
                return [
                    (string) ($row['created_at'] ?? ''),
                    (string) ($row['user_name'] ?? 'System'),
                    (string) ($row['role_name'] ?? 'System'),
                    (string) ($row['branch_name'] ?? 'All Branches'),
                    (string) ($row['action'] ?? ''),
                    (string) ($row['entity_type'] ?? ''),
                    (string) ($row['entity_id'] ?? ''),
                    (string) ($row['description'] ?? ''),
                    (string) ($row['ip_address'] ?? ''),
                    (string) ($row['user_agent'] ?? ''),
                ];
            }, $rows),
        ];
    }

    private function applyFilters(string $sql, array $filters, array &$params): string
    {
        $clauses = [];
        $userId = trim((string) ($filters['user_id'] ?? ''));
        $action = trim((string) ($filters['action'] ?? ''));
        $entityType = trim((string) ($filters['entity_type'] ?? ''));
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        $term = trim((string) ($filters['term'] ?? ''));

        if ($userId !== '' && ctype_digit($userId)) {
            $clauses[] = 'al.user_id = :user_id';
            $params['user_id'] = (int) $userId;
        }

        if ($action !== '') {
            $clauses[] = 'al.action = :action';
            $params['action'] = $action;
        }

        if ($entityType !== '') {
            $clauses[] = 'al.entity_type = :entity_type';
            $params['entity_type'] = $entityType;
        }

        if ($dateFrom !== '') {
            $clauses[] = 'DATE(al.created_at) >= :date_from';
            $params['date_from'] = $dateFrom;
        }

        if ($dateTo !== '') {
            $clauses[] = 'DATE(al.created_at) <= :date_to';
            $params['date_to'] = $dateTo;
        }

        if ($term !== '') {
            $clauses[] = '(al.description LIKE :term
                        OR al.ip_address LIKE :term
                        OR al.entity_type LIKE :term
                        OR CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) LIKE :term)';
            $params['term'] = '%' . $term . '%';
        }

        if ($clauses !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        return $sql;
    }

    private function applyCompanyScope(string $sql, array &$params): string
    {
        $companyId = current_company_id();
        if ($companyId === null) {
            return $sql;
        }

        $params['company_id'] = $companyId;
        $clause = 'u.company_id = :company_id';

        if (stripos($sql, ' WHERE ') !== false) {
            return $sql . ' AND ' . $clause;
        }

        return $sql . ' WHERE ' . $clause;
    }
}
