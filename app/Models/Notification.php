<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Notification extends Model
{
    protected string $table = 'notifications';

    public function unreadCount(?int $userId, ?int $branchId): int
    {
        $result = $this->fetch(
            'SELECT COUNT(*) AS total
             FROM notifications
             WHERE is_read = 0
               AND ' . $this->scopeClause(),
            $this->scopeParams($userId, $branchId)
        );

        return (int) ($result['total'] ?? 0);
    }

    public function recent(?int $userId, ?int $branchId, int $limit = 5): array
    {
        $statement = $this->db->prepare(
            'SELECT *
             FROM notifications
             WHERE ' . $this->scopeClause() . '
             ORDER BY is_read ASC, created_at DESC
             LIMIT :limit'
        );

        foreach ($this->scopeParams($userId, $branchId) as $key => $value) {
            $statement->bindValue(':' . $key, $value, \PDO::PARAM_INT);
        }

        $statement->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public function listForUser(?int $userId, ?int $branchId, array $filters = []): array
    {
        $sql = 'SELECT *
                FROM notifications
                WHERE ' . $this->scopeClause();
        $params = $this->scopeParams($userId, $branchId);

        $status = trim((string) ($filters['status'] ?? 'all'));
        if ($status === 'unread') {
            $sql .= ' AND is_read = 0';
        } elseif ($status === 'read') {
            $sql .= ' AND is_read = 1';
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $sql .= ' AND type = :type';
            $params['type'] = $type;
        }

        $sql .= ' ORDER BY is_read ASC, created_at DESC';

        return $this->fetchAll($sql, $params);
    }

    public function summaryForUser(?int $userId, ?int $branchId): array
    {
        $summary = $this->fetch(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread,
                    SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) AS read_count,
                    SUM(CASE WHEN link_url IS NOT NULL AND link_url <> "" THEN 1 ELSE 0 END) AS actionable
             FROM notifications
             WHERE ' . $this->scopeClause(),
            $this->scopeParams($userId, $branchId)
        ) ?: [];

        return [
            'total' => (int) ($summary['total'] ?? 0),
            'unread' => (int) ($summary['unread'] ?? 0),
            'read' => (int) ($summary['read_count'] ?? 0),
            'actionable' => (int) ($summary['actionable'] ?? 0),
        ];
    }

    public function typesForUser(?int $userId, ?int $branchId): array
    {
        $rows = $this->fetchAll(
            'SELECT DISTINCT type
             FROM notifications
             WHERE ' . $this->scopeClause() . '
               AND type IS NOT NULL
               AND type <> ""
             ORDER BY type ASC',
            $this->scopeParams($userId, $branchId)
        );

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['type'] ?? ''),
            $rows
        )));
    }

    public function findForUser(int $notificationId, ?int $userId, ?int $branchId): ?array
    {
        return $this->fetch(
            'SELECT *
             FROM notifications
             WHERE id = :id
               AND ' . $this->scopeClause(),
            [
                'id' => $notificationId,
                ...$this->scopeParams($userId, $branchId),
            ]
        );
    }

    public function markAllRead(?int $userId, ?int $branchId): void
    {
        $this->query(
            'UPDATE notifications
             SET is_read = 1
             WHERE ' . $this->scopeClause(),
            $this->scopeParams($userId, $branchId)
        );
    }

    public function markRead(int $notificationId, ?int $userId, ?int $branchId): bool
    {
        return $this->updateReadState($notificationId, $userId, $branchId, true);
    }

    public function markUnread(int $notificationId, ?int $userId, ?int $branchId): bool
    {
        return $this->updateReadState($notificationId, $userId, $branchId, false);
    }

    public function createUserNotification(int $userId, ?int $branchId, string $type, string $title, string $message, ?string $linkUrl = null, bool $sendEmail = false): int
    {
        return $this->insert([
            'user_id' => $userId,
            'branch_id' => $branchId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link_url' => $linkUrl,
            'is_read' => 0,
            'send_email' => $sendEmail ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function createBranchNotification(int $branchId, string $type, string $title, string $message, ?string $linkUrl = null, bool $sendEmail = false): int
    {
        return $this->insert([
            'user_id' => null,
            'branch_id' => $branchId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link_url' => $linkUrl,
            'is_read' => 0,
            'send_email' => $sendEmail ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function updateReadState(int $notificationId, ?int $userId, ?int $branchId, bool $isRead): bool
    {
        $statement = $this->db->prepare(
            'UPDATE notifications
             SET is_read = :is_read
             WHERE id = :id
               AND ' . $this->scopeClause()
        );

        $statement->bindValue(':is_read', $isRead ? 1 : 0, \PDO::PARAM_INT);
        $statement->bindValue(':id', $notificationId, \PDO::PARAM_INT);
        foreach ($this->scopeParams($userId, $branchId) as $key => $value) {
            $statement->bindValue(':' . $key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement->rowCount() > 0;
    }

    private function scopeClause(): string
    {
        return '(user_id = :user_id OR (user_id IS NULL AND branch_id = :branch_id))';
    }

    private function scopeParams(?int $userId, ?int $branchId): array
    {
        return [
            'user_id' => $userId ?? 0,
            'branch_id' => $branchId ?? 0,
        ];
    }
}