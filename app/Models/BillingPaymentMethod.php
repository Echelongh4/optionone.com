<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class BillingPaymentMethod extends Model
{
    protected string $table = 'billing_payment_methods';

    private static ?bool $schemaReady = null;
    private static ?bool $integrationColumnsReady = null;

    public function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        try {
            foreach (['billing_payment_methods', 'billing_payment_submissions'] as $table) {
                if (!$this->tableExists($table)) {
                    self::$schemaReady = false;
                    return false;
                }
            }

            self::$schemaReady = true;
        } catch (\Throwable) {
            self::$schemaReady = false;
        }

        return self::$schemaReady;
    }

    public function all(bool $activeOnly = false): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $sql = 'SELECT *
                FROM billing_payment_methods';
        $params = [];

        if ($activeOnly) {
            $sql .= ' WHERE status = :status';
            $params['status'] = 'active';
        }

        $sql .= ' ORDER BY is_default DESC, sort_order ASC, name ASC';

        return array_map(fn (array $method): array => $this->hydrateMethod($method), $this->fetchAll($sql, $params));
    }

    public function activeForCurrency(?string $currency = null): array
    {
        $currency = strtoupper(trim((string) $currency));

        return array_values(array_filter(
            $this->all(true),
            fn (array $method): bool => $this->supportsCurrency($method, $currency)
        ));
    }

    public function find(int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $method = $this->fetch(
            'SELECT *
             FROM billing_payment_methods
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return $method !== null ? $this->hydrateMethod($method) : null;
    }

    public function createMethod(array $payload): int
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing payment methods are unavailable.');
        }

        $prepared = $this->preparePayload($payload);
        if ((int) $prepared['is_default'] === 1 || $this->countDefaults() === 0) {
            $prepared['is_default'] = $prepared['status'] === 'active' ? 1 : 0;
            if ((int) $prepared['is_default'] === 1) {
                $this->execute('UPDATE billing_payment_methods SET is_default = 0');
            }
        }

        return $this->insert($prepared);
    }

    public function updateMethod(int $id, array $payload): bool
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing payment methods are unavailable.');
        }

        $prepared = $this->preparePayload($payload, $id);
        if ((int) $prepared['is_default'] === 1) {
            $this->execute(
                'UPDATE billing_payment_methods
                 SET is_default = 0
                 WHERE id <> :id',
                ['id' => $id]
            );
        } elseif ($this->countDefaults($id) === 0 && $prepared['status'] === 'active') {
            $prepared['is_default'] = 1;
        }

        return $this->updateRecord($prepared, 'id = :id', ['id' => $id]);
    }

    public function supportsCurrency(array $method, ?string $currency): bool
    {
        $currency = strtoupper(trim((string) $currency));
        $supportedCurrencies = is_array($method['supported_currencies'] ?? null)
            ? $method['supported_currencies']
            : [];

        if ($currency === '' || $supportedCurrencies === []) {
            return true;
        }

        return in_array($currency, $supportedCurrencies, true);
    }

    private function preparePayload(array $payload, ?int $exceptId = null): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $slug = $this->uniqueSlug(trim((string) ($payload['slug'] ?? $name)), $exceptId);
        $type = trim((string) ($payload['type'] ?? 'bank_transfer'));
        $status = trim((string) ($payload['status'] ?? 'active'));
        $prepared = [
            'slug' => $slug,
            'name' => $name,
            'type' => in_array($type, ['bank_transfer', 'mobile_money', 'card', 'cash', 'other'], true) ? $type : 'bank_transfer',
            'description' => trim((string) ($payload['description'] ?? '')),
            'provider_name' => trim((string) ($payload['provider_name'] ?? '')),
            'account_name' => trim((string) ($payload['account_name'] ?? '')),
            'account_number' => trim((string) ($payload['account_number'] ?? '')),
            'checkout_url' => trim((string) ($payload['checkout_url'] ?? '')),
            'supported_currencies_json' => $this->currenciesJson($payload['supported_currencies'] ?? []),
            'instructions' => trim((string) ($payload['instructions'] ?? '')),
            'requires_reference' => !empty($payload['requires_reference']) ? 1 : 0,
            'requires_proof' => !empty($payload['requires_proof']) ? 1 : 0,
            'is_default' => !empty($payload['is_default']) ? 1 : 0,
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
            ...($exceptId === null ? ['created_at' => date('Y-m-d H:i:s')] : []),
        ];

        if ($this->integrationColumnsReady()) {
            $prepared['integration_driver'] = $this->normalizeIntegrationDriver((string) ($payload['integration_driver'] ?? 'manual'));
            $prepared['integration_config_json'] = $this->integrationConfigJson($payload['integration_config'] ?? null);
        }

        return $prepared;
    }

    private function hydrateMethod(array $method): array
    {
        $decodedCurrencies = json_decode((string) ($method['supported_currencies_json'] ?? '[]'), true);
        $method['supported_currencies'] = is_array($decodedCurrencies)
            ? array_values(array_filter(array_map(
                static fn (mixed $currency): string => strtoupper(trim((string) $currency)),
                $decodedCurrencies
            )))
            : [];

        $method['integration_driver'] = $this->normalizeIntegrationDriver((string) ($method['integration_driver'] ?? 'manual'));
        $decodedConfig = json_decode((string) ($method['integration_config_json'] ?? 'null'), true);
        $method['integration_config'] = is_array($decodedConfig) ? $decodedConfig : [];

        return $method;
    }

    private function currenciesJson(mixed $value): ?string
    {
        $currencies = is_array($value) ? $value : explode(',', (string) $value);
        $currencies = array_values(array_unique(array_filter(array_map(
            static fn (mixed $currency): string => strtoupper(substr(trim((string) $currency), 0, 10)),
            $currencies
        ))));

        return $currencies === []
            ? null
            : (json_encode($currencies, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null);
    }

    private function uniqueSlug(string $value, ?int $exceptId = null): string
    {
        $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
        $base = $base !== '' ? $base : 'payment-method';
        $candidate = $base;
        $suffix = 1;

        while ($this->slugExists($candidate, $exceptId)) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id
                FROM billing_payment_methods
                WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    private function countDefaults(?int $exceptId = null): int
    {
        $sql = 'SELECT COUNT(*) AS aggregate
                FROM billing_payment_methods
                WHERE is_default = 1
                  AND status = "active"';
        $params = [];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        $row = $this->fetch($sql . ' LIMIT 1', $params);

        return (int) ($row['aggregate'] ?? 0);
    }

    private function integrationColumnsReady(): bool
    {
        if (self::$integrationColumnsReady !== null) {
            return self::$integrationColumnsReady;
        }

        self::$integrationColumnsReady = $this->columnExists('billing_payment_methods', 'integration_driver')
            && $this->columnExists('billing_payment_methods', 'integration_config_json');

        return self::$integrationColumnsReady;
    }

    private function normalizeIntegrationDriver(string $driver): string
    {
        $driver = strtolower(trim($driver));

        return in_array($driver, ['manual', 'paystack'], true) ? $driver : 'manual';
    }

    private function integrationConfigJson(mixed $value): ?string
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
            ['table_name' => $table, 'column_name' => $column]
        ) !== null;
    }
}
