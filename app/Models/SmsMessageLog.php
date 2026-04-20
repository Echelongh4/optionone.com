<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SmsMessageLog extends Model
{
    protected string $table = 'sms_message_logs';

    public function schemaReady(): bool
    {
        try {
            return $this->tableExists('sms_message_logs');
        } catch (\Throwable) {
            return false;
        }
    }

    public function createLog(array $payload): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }

        return $this->insert([
            'company_id' => !empty($payload['company_id']) ? (int) $payload['company_id'] : null,
            'user_id' => !empty($payload['user_id']) ? (int) $payload['user_id'] : null,
            'provider' => trim((string) ($payload['provider'] ?? 'twilio')),
            'recipient_phone' => trim((string) ($payload['recipient_phone'] ?? '')),
            'sender_identity' => $this->nullableString($payload['sender_identity'] ?? null),
            'message_body' => trim((string) ($payload['message_body'] ?? '')),
            'status' => in_array((string) ($payload['status'] ?? 'queued'), ['queued', 'sent', 'failed'], true)
                ? (string) ($payload['status'] ?? 'queued')
                : 'queued',
            'external_message_id' => $this->nullableString($payload['external_message_id'] ?? null),
            'error_message' => $this->nullableString($payload['error_message'] ?? null),
            'payload_json' => $this->jsonOrNull($payload['payload'] ?? null),
            'sent_at' => $this->normalizeDateTime($payload['sent_at'] ?? null),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function updateLog(int $id, array $payload): bool
    {
        if (!$this->schemaReady() || $id <= 0) {
            return false;
        }

        $data = [];

        foreach (['provider', 'recipient_phone', 'message_body', 'status', 'external_message_id', 'error_message', 'sender_identity'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $data[$field] = in_array($field, ['external_message_id', 'error_message', 'sender_identity'], true)
                ? $this->nullableString($payload[$field])
                : trim((string) $payload[$field]);
        }

        if (array_key_exists('payload', $payload)) {
            $data['payload_json'] = $this->jsonOrNull($payload['payload']);
        }

        if (array_key_exists('sent_at', $payload)) {
            $data['sent_at'] = $this->normalizeDateTime($payload['sent_at']);
        }

        if ($data === []) {
            return true;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord($data, 'id = :id', ['id' => $id]);
    }

    public function recent(int $limit = 20): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        return array_map(function (array $row): array {
            $decoded = json_decode((string) ($row['payload_json'] ?? ''), true);
            $row['payload'] = is_array($decoded) ? $decoded : [];

            return $row;
        }, $this->fetchAll(
            'SELECT *
             FROM sms_message_logs
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit
        ));
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function jsonOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
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
