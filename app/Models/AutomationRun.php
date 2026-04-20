<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class AutomationRun extends Model
{
    protected string $table = 'platform_automation_runs';

    public function schemaReady(): bool
    {
        try {
            return $this->tableExists('platform_automation_runs');
        } catch (\Throwable) {
            return false;
        }
    }

    public function start(string $automationKey, string $triggerMode = 'manual', ?int $companyId = null, ?int $createdByUserId = null): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }

        return $this->insert([
            'automation_key' => trim($automationKey),
            'status' => 'running',
            'trigger_mode' => in_array($triggerMode, ['manual', 'scheduled', 'webhook'], true) ? $triggerMode : 'manual',
            'company_id' => $companyId,
            'started_at' => date('Y-m-d H:i:s'),
            'finished_at' => null,
            'message' => null,
            'summary_json' => null,
            'created_by_user_id' => $createdByUserId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function complete(int $id, array $summary = [], string $message = ''): void
    {
        if (!$this->schemaReady() || $id <= 0) {
            return;
        }

        $this->updateRecord([
            'status' => 'succeeded',
            'finished_at' => date('Y-m-d H:i:s'),
            'message' => trim($message),
            'summary_json' => $summary !== [] ? (json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function fail(int $id, string $message, array $summary = []): void
    {
        if (!$this->schemaReady() || $id <= 0) {
            return;
        }

        $this->updateRecord([
            'status' => 'failed',
            'finished_at' => date('Y-m-d H:i:s'),
            'message' => trim($message),
            'summary_json' => $summary !== [] ? (json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function latestSuccessfulAt(string $automationKey, ?int $companyId = null): ?string
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $sql = 'SELECT finished_at
                FROM platform_automation_runs
                WHERE automation_key = :automation_key
                  AND status = "succeeded"';
        $params = ['automation_key' => trim($automationKey)];

        if ($companyId !== null) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $row = $this->fetch($sql . ' ORDER BY finished_at DESC, id DESC LIMIT 1', $params);

        return trim((string) ($row['finished_at'] ?? '')) !== '' ? (string) $row['finished_at'] : null;
    }

    public function recent(int $limit = 12): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        return array_map(function (array $row): array {
            $decoded = json_decode((string) ($row['summary_json'] ?? ''), true);
            $row['summary'] = is_array($decoded) ? $decoded : [];

            return $row;
        }, $this->fetchAll(
            'SELECT *
             FROM platform_automation_runs
             ORDER BY started_at DESC, id DESC
             LIMIT ' . $limit
        ));
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
}
