<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Setting extends Model
{
    protected string $table = 'settings';

    public function allAsMap(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        $rows = $this->fetchAll(
            'SELECT key_name, value_text
             FROM settings
             WHERE company_id = :company_id
             ORDER BY key_name',
            ['company_id' => $companyId]
        );
        $map = [];

        foreach ($rows as $row) {
            $map[$row['key_name']] = $row['value_text'];
        }

        return $map;
    }

    public function get(string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return $default;
        }

        $setting = $this->fetch(
            'SELECT value_text
             FROM settings
             WHERE company_id = :company_id
               AND key_name = :key
             LIMIT 1',
            ['company_id' => $companyId, 'key' => $key]
        );

        return $setting['value_text'] ?? $default;
    }

    public function save(string $key, ?string $value, string $type = 'string', ?int $companyId = null): void
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return;
        }

        $this->db->prepare(
            'INSERT INTO settings (company_id, key_name, value_text, type, created_at, updated_at)
             VALUES (:company_id, :key_name, :value_text, :type, NOW(), NOW())
             ON DUPLICATE KEY UPDATE value_text = VALUES(value_text), type = VALUES(type), updated_at = NOW()'
        )->execute([
            'company_id' => $companyId,
            'key_name' => $key,
            'value_text' => $value,
            'type' => $type,
        ]);
    }

    public function saveMany(array $settings, ?int $companyId = null): void
    {
        foreach ($settings as $key => $payload) {
            $value = is_array($payload) ? (string) ($payload['value'] ?? '') : (string) $payload;
            $type = is_array($payload) ? (string) ($payload['type'] ?? 'string') : 'string';
            $this->save($key, $value, $type, $companyId);
        }
    }

    private function resolveCompanyId(?int $companyId = null): ?int
    {
        $companyId ??= current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }
}
