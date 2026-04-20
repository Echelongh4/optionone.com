<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class BillingGatewayTransaction extends Model
{
    protected string $table = 'billing_gateway_transactions';

    private static ?bool $schemaReady = null;

    public function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        try {
            self::$schemaReady = $this->tableExists('billing_gateway_transactions');
        } catch (\Throwable) {
            self::$schemaReady = false;
        }

        return self::$schemaReady;
    }

    public function find(int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $row = $this->fetch($this->baseSelect() . ' WHERE bgt.id = :id LIMIT 1', ['id' => $id]);

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function findByReference(string $reference): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $row = $this->fetch(
            $this->baseSelect() . ' WHERE bgt.provider_reference = :provider_reference LIMIT 1',
            ['provider_reference' => trim($reference)]
        );

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function listForInvoice(int $invoiceId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        return array_map(
            fn (array $row): array => $this->hydrate($row),
            $this->fetchAll(
                $this->baseSelect() . ' WHERE bgt.billing_invoice_id = :billing_invoice_id
                    ORDER BY bgt.created_at DESC, bgt.id DESC',
                ['billing_invoice_id' => $invoiceId]
            )
        );
    }

    public function recent(int $limit = 20): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $limit = max(1, min(100, $limit));

        return array_map(
            fn (array $row): array => $this->hydrate($row),
            $this->fetchAll(
                $this->baseSelect() . ' ORDER BY bgt.created_at DESC, bgt.id DESC LIMIT ' . $limit
            )
        );
    }

    public function createTransaction(array $payload): int
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing gateway transaction support is unavailable.');
        }

        $now = date('Y-m-d H:i:s');

        return $this->insert([
            'company_id' => (int) ($payload['company_id'] ?? 0),
            'billing_invoice_id' => (int) ($payload['billing_invoice_id'] ?? 0),
            'billing_payment_method_id' => (int) ($payload['billing_payment_method_id'] ?? 0),
            'billing_invoice_payment_id' => !empty($payload['billing_invoice_payment_id']) ? (int) $payload['billing_invoice_payment_id'] : null,
            'billing_payment_submission_id' => !empty($payload['billing_payment_submission_id']) ? (int) $payload['billing_payment_submission_id'] : null,
            'initiated_by_user_id' => !empty($payload['initiated_by_user_id']) ? (int) $payload['initiated_by_user_id'] : null,
            'provider' => trim((string) ($payload['provider'] ?? 'paystack')),
            'provider_reference' => trim((string) ($payload['provider_reference'] ?? '')),
            'provider_transaction_id' => $this->nullableString($payload['provider_transaction_id'] ?? null),
            'access_code' => $this->nullableString($payload['access_code'] ?? null),
            'authorization_url' => $this->nullableString($payload['authorization_url'] ?? null),
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? 'initialized')),
            'amount' => number_format(max(0, (float) ($payload['amount'] ?? 0)), 2, '.', ''),
            'currency' => normalize_billing_currency((string) ($payload['currency'] ?? default_currency_code()), default_currency_code()),
            'payer_name' => trim((string) ($payload['payer_name'] ?? '')),
            'payer_email' => strtolower(trim((string) ($payload['payer_email'] ?? ''))),
            'payer_phone' => trim((string) ($payload['payer_phone'] ?? '')),
            'metadata_json' => $this->jsonOrNull($payload['metadata'] ?? null),
            'verification_payload_json' => $this->jsonOrNull($payload['verification_payload'] ?? null),
            'failure_reason' => $this->nullableString($payload['failure_reason'] ?? null),
            'last_checked_at' => $this->normalizeDateTime($payload['last_checked_at'] ?? null),
            'verified_at' => $this->normalizeDateTime($payload['verified_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateTransaction(int $id, array $payload): bool
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing gateway transaction support is unavailable.');
        }

        $data = [];

        foreach ([
            'billing_invoice_payment_id',
            'billing_payment_submission_id',
            'provider_transaction_id',
            'access_code',
            'authorization_url',
            'failure_reason',
        ] as $field) {
            if (array_key_exists($field, $payload)) {
                $data[$field] = $this->nullableString($payload[$field]);
            }
        }

        if (array_key_exists('status', $payload)) {
            $data['status'] = $this->normalizeStatus((string) $payload['status']);
        }

        if (array_key_exists('metadata', $payload)) {
            $data['metadata_json'] = $this->jsonOrNull($payload['metadata']);
        }

        if (array_key_exists('verification_payload', $payload)) {
            $data['verification_payload_json'] = $this->jsonOrNull($payload['verification_payload']);
        }

        if (array_key_exists('last_checked_at', $payload)) {
            $data['last_checked_at'] = $this->normalizeDateTime($payload['last_checked_at']);
        }

        if (array_key_exists('verified_at', $payload)) {
            $data['verified_at'] = $this->normalizeDateTime($payload['verified_at']);
        }

        if ($data === []) {
            return true;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord($data, 'id = :id', ['id' => $id]);
    }

    private function baseSelect(): string
    {
        return 'SELECT
                    bgt.*,
                    bi.invoice_number,
                    bi.status AS invoice_status,
                    bi.balance_due AS invoice_balance_due,
                    c.name AS company_name,
                    bpm.name AS payment_method_name,
                    bpm.slug AS payment_method_slug
                FROM billing_gateway_transactions bgt
                INNER JOIN billing_invoices bi ON bi.id = bgt.billing_invoice_id
                INNER JOIN companies c ON c.id = bgt.company_id
                INNER JOIN billing_payment_methods bpm ON bpm.id = bgt.billing_payment_method_id';
    }

    private function hydrate(array $row): array
    {
        $row['metadata'] = $this->decodeJson((string) ($row['metadata_json'] ?? ''));
        $row['verification_payload'] = $this->decodeJson((string) ($row['verification_payload_json'] ?? ''));

        return $row;
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['initialized', 'pending', 'success', 'failed', 'cancelled'], true)
            ? $status
            : 'initialized';
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

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function jsonOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
            }
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }

    private function decodeJson(string $value): array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
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
