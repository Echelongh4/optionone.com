<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class BillingPlan extends Model
{
    protected string $table = 'billing_plans';

    private static ?bool $schemaReady = null;

    public function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        try {
            foreach (['billing_plans', 'company_subscriptions', 'billing_invoices', 'billing_invoice_payments'] as $table) {
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
                FROM billing_plans';
        $params = [];

        if ($activeOnly) {
            $sql .= ' WHERE status = :status';
            $params['status'] = 'active';
        }

        $sql .= ' ORDER BY is_default DESC, is_featured DESC, sort_order ASC, price ASC, name ASC';

        return array_map(fn (array $plan): array => $this->hydratePlan($plan), $this->fetchAll($sql, $params));
    }

    public function find(int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $plan = $this->fetch(
            'SELECT *
             FROM billing_plans
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );

        return $plan !== null ? $this->hydratePlan($plan) : null;
    }

    public function defaultPlan(): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $plan = $this->fetch(
            'SELECT *
             FROM billing_plans
             WHERE status = "active"
             ORDER BY is_default DESC, is_featured DESC, sort_order ASC, price ASC, id ASC
             LIMIT 1'
        );

        return $plan !== null ? $this->hydratePlan($plan) : null;
    }

    public function summary(): array
    {
        if (!$this->schemaReady()) {
            return [
                'total_plans' => 0,
                'active_plans' => 0,
                'featured_plans' => 0,
                'default_plans' => 0,
            ];
        }

        return $this->fetch(
            'SELECT
                COUNT(*) AS total_plans,
                COALESCE(SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END), 0) AS active_plans,
                COALESCE(SUM(CASE WHEN status = "active" AND is_featured = 1 THEN 1 ELSE 0 END), 0) AS featured_plans,
                COALESCE(SUM(CASE WHEN is_default = 1 THEN 1 ELSE 0 END), 0) AS default_plans
             FROM billing_plans'
        ) ?? [
            'total_plans' => 0,
            'active_plans' => 0,
            'featured_plans' => 0,
            'default_plans' => 0,
        ];
    }

    public function createPlan(array $payload): int
    {
        $prepared = $this->preparePayload($payload);

        if ($prepared['status'] !== 'active') {
            $prepared['is_default'] = 0;
        }

        if ((int) $prepared['is_default'] === 1 || $this->countDefaultPlans() === 0) {
            $prepared['is_default'] = $prepared['status'] === 'active' ? 1 : 0;
            if ((int) $prepared['is_default'] === 1) {
                $this->execute('UPDATE billing_plans SET is_default = 0');
            }
        }

        return $this->insert($prepared);
    }

    public function updatePlan(int $id, array $payload): bool
    {
        $prepared = $this->preparePayload($payload, $id);

        if ($prepared['status'] !== 'active') {
            $prepared['is_default'] = 0;
        }

        if ((int) $prepared['is_default'] === 1) {
            $this->execute(
                'UPDATE billing_plans
                 SET is_default = 0
                 WHERE id <> :id',
                ['id' => $id]
            );
        } elseif ($this->countDefaultPlans($id) === 0 && $prepared['status'] === 'active') {
            $prepared['is_default'] = 1;
        }

        return $this->updateRecord($prepared, 'id = :id', ['id' => $id]);
    }

    private function preparePayload(array $payload, ?int $exceptId = null): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $slugInput = trim((string) ($payload['slug'] ?? $name));
        $status = trim((string) ($payload['status'] ?? 'active'));
        $billingCycle = trim((string) ($payload['billing_cycle'] ?? 'monthly'));

        $prepared = [
            'name' => $name,
            'slug' => $this->uniqueSlug($slugInput, $exceptId),
            'description' => trim((string) ($payload['description'] ?? '')),
            'billing_cycle' => in_array($billingCycle, ['monthly', 'quarterly', 'yearly', 'custom'], true) ? $billingCycle : 'monthly',
            'price' => number_format(max(0, (float) ($payload['price'] ?? 0)), 2, '.', ''),
            'currency' => normalize_billing_currency((string) ($payload['currency'] ?? default_currency_code()), default_currency_code()),
            'trial_days' => max(0, (int) ($payload['trial_days'] ?? 0)),
            'max_branches' => $this->nullableLimit($payload['max_branches'] ?? null),
            'max_users' => $this->nullableLimit($payload['max_users'] ?? null),
            'max_products' => $this->nullableLimit($payload['max_products'] ?? null),
            'max_monthly_sales' => $this->nullableLimit($payload['max_monthly_sales'] ?? null),
            'features_json' => $this->featuresJson($payload['features'] ?? ($payload['features_json'] ?? '')),
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
            'is_featured' => !empty($payload['is_featured']) ? 1 : 0,
            'is_default' => !empty($payload['is_default']) ? 1 : 0,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($exceptId === null) {
            $prepared['created_at'] = date('Y-m-d H:i:s');
        }

        return $prepared;
    }

    private function hydratePlan(array $plan): array
    {
        $decodedFeatures = json_decode((string) ($plan['features_json'] ?? '[]'), true);
        $plan['features'] = is_array($decodedFeatures)
            ? array_values(array_filter(array_map(static fn (mixed $feature): string => trim((string) $feature), $decodedFeatures)))
            : [];

        return $plan;
    }

    private function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id
                FROM billing_plans
                WHERE slug = :slug';
        $params = ['slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    private function uniqueSlug(string $value, ?int $exceptId = null): string
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

        return $slug !== '' ? $slug : 'plan';
    }

    private function featuresJson(mixed $value): string
    {
        if (is_array($value)) {
            $features = $value;
        } else {
            $normalized = str_replace(["\r\n", "\r"], "\n", trim((string) $value));
            $features = $normalized === ''
                ? []
                : (preg_split('/[\n,]+/', $normalized) ?: []);
        }

        $features = array_values(array_filter(array_map(
            static fn (mixed $feature): string => trim((string) $feature),
            $features
        )));

        return json_encode($features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function nullableLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $limit = (int) $value;

        return $limit > 0 ? $limit : null;
    }

    private function countDefaultPlans(?int $exceptId = null): int
    {
        $sql = 'SELECT COUNT(*) AS aggregate
                FROM billing_plans
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
