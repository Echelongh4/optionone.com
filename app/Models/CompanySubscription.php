<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use DateTimeImmutable;

class CompanySubscription extends Model
{
    protected string $table = 'company_subscriptions';

    public function schemaReady(): bool
    {
        return (new BillingPlan())->schemaReady();
    }

    public function findByCompany(int $companyId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        return $this->fetch(
            $this->baseSelect() . ' WHERE cs.company_id = :company_id LIMIT 1',
            ['company_id' => $companyId]
        );
    }

    public function platformList(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        return $this->fetchAll(
            $this->baseSelect() . ' ORDER BY
                FIELD(cs.status, "past_due", "suspended", "trialing", "active", "cancelled"),
                cs.next_invoice_at IS NULL,
                cs.next_invoice_at ASC,
                c.name ASC'
        );
    }

    public function summary(): array
    {
        if (!$this->schemaReady()) {
            return [
                'total_subscriptions' => 0,
                'active_subscriptions' => 0,
                'trialing_subscriptions' => 0,
                'past_due_subscriptions' => 0,
                'suspended_subscriptions' => 0,
                'monthly_recurring_revenue' => 0,
            ];
        }

        return $this->fetch(
            'SELECT
                COUNT(*) AS total_subscriptions,
                COALESCE(SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END), 0) AS active_subscriptions,
                COALESCE(SUM(CASE WHEN status = "trialing" THEN 1 ELSE 0 END), 0) AS trialing_subscriptions,
                COALESCE(SUM(CASE WHEN status = "past_due" THEN 1 ELSE 0 END), 0) AS past_due_subscriptions,
                COALESCE(SUM(CASE WHEN status = "suspended" THEN 1 ELSE 0 END), 0) AS suspended_subscriptions,
                COALESCE(SUM(
                    CASE
                        WHEN status IN ("active", "trialing", "past_due") THEN
                            CASE billing_cycle
                                WHEN "yearly" THEN amount / 12
                                WHEN "quarterly" THEN amount / 3
                                ELSE amount
                            END
                        ELSE 0
                    END
                ), 0) AS monthly_recurring_revenue
             FROM company_subscriptions'
        ) ?? [
            'total_subscriptions' => 0,
            'active_subscriptions' => 0,
            'trialing_subscriptions' => 0,
            'past_due_subscriptions' => 0,
            'suspended_subscriptions' => 0,
            'monthly_recurring_revenue' => 0,
        ];
    }

    public function upsertForCompany(int $companyId, array $payload): int
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing schema support is not available.');
        }

        $existing = $this->findByCompany($companyId);
        $prepared = [
            'company_id' => $companyId,
            'billing_plan_id' => (int) ($payload['billing_plan_id'] ?? 0),
            'plan_name_snapshot' => trim((string) ($payload['plan_name_snapshot'] ?? '')),
            'billing_cycle' => $this->normalizeCycle((string) ($payload['billing_cycle'] ?? 'monthly')),
            'amount' => number_format(max(0, (float) ($payload['amount'] ?? 0)), 2, '.', ''),
            'currency' => normalize_billing_currency((string) ($payload['currency'] ?? default_currency_code()), default_currency_code()),
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? 'trialing')),
            'trial_ends_at' => $this->normalizeDateTime($payload['trial_ends_at'] ?? null),
            'current_period_start' => $this->normalizeDateTime($payload['current_period_start'] ?? null),
            'current_period_end' => $this->normalizeDateTime($payload['current_period_end'] ?? null),
            'next_invoice_at' => $this->normalizeDateTime($payload['next_invoice_at'] ?? null),
            'grace_ends_at' => $this->normalizeDateTime($payload['grace_ends_at'] ?? null),
            'max_branches' => $this->nullableLimit($payload['max_branches'] ?? null),
            'max_users' => $this->nullableLimit($payload['max_users'] ?? null),
            'max_products' => $this->nullableLimit($payload['max_products'] ?? null),
            'max_monthly_sales' => $this->nullableLimit($payload['max_monthly_sales'] ?? null),
            'auto_renew' => !empty($payload['auto_renew']) ? 1 : 0,
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($prepared['billing_plan_id'] <= 0) {
            throw new \RuntimeException('A billing plan is required.');
        }

        if ($prepared['plan_name_snapshot'] === '') {
            throw new \RuntimeException('A billing plan snapshot name is required.');
        }

        if ($existing !== null) {
            $this->updateRecord($prepared, 'company_id = :company_id', ['company_id' => $companyId]);
            return (int) $existing['id'];
        }

        $prepared['created_at'] = date('Y-m-d H:i:s');

        return $this->insert($prepared);
    }

    public function ensureDefaultForCompany(int $companyId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $existing = $this->findByCompany($companyId);
        if ($existing !== null) {
            return $existing;
        }

        $plan = (new BillingPlan())->defaultPlan();
        if ($plan === null) {
            return null;
        }

        $startedAt = new DateTimeImmutable('now');
        $trialDays = max(0, (int) ($plan['trial_days'] ?? 0));
        $periodEnd = $this->periodEnd($startedAt, (string) ($plan['billing_cycle'] ?? 'monthly'));
        $trialEndsAt = $trialDays > 0 ? $startedAt->modify('+' . $trialDays . ' days') : null;
        $nextInvoiceAt = $trialEndsAt ?? $periodEnd;

        $this->upsertForCompany($companyId, [
            'billing_plan_id' => (int) $plan['id'],
            'plan_name_snapshot' => (string) ($plan['name'] ?? 'Plan'),
            'billing_cycle' => (string) ($plan['billing_cycle'] ?? 'monthly'),
            'amount' => (float) ($plan['price'] ?? 0),
            'currency' => normalize_billing_currency((string) ($plan['currency'] ?? default_currency_code()), default_currency_code()),
            'status' => $trialDays > 0 ? 'trialing' : 'active',
            'trial_ends_at' => $trialEndsAt?->format('Y-m-d H:i:s'),
            'current_period_start' => $startedAt->format('Y-m-d H:i:s'),
            'current_period_end' => $periodEnd->format('Y-m-d H:i:s'),
            'next_invoice_at' => $nextInvoiceAt?->format('Y-m-d H:i:s'),
            'grace_ends_at' => null,
            'max_branches' => $plan['max_branches'] ?? null,
            'max_users' => $plan['max_users'] ?? null,
            'max_products' => $plan['max_products'] ?? null,
            'max_monthly_sales' => $plan['max_monthly_sales'] ?? null,
            'auto_renew' => 1,
            'notes' => 'Provisioned automatically from the default billing plan.',
        ]);

        return $this->findByCompany($companyId);
    }

    private function baseSelect(): string
    {
        return 'SELECT
                    cs.*,
                    c.name AS company_name,
                    c.slug AS company_slug,
                    c.status AS company_status,
                    bp.name AS plan_name,
                    bp.slug AS plan_slug,
                    bp.status AS plan_status,
                    bp.is_featured AS plan_is_featured,
                    bp.is_default AS plan_is_default,
                    (
                        SELECT COALESCE(SUM(bi.balance_due), 0)
                        FROM billing_invoices bi
                        WHERE bi.company_id = cs.company_id
                          AND bi.status IN ("issued", "overdue")
                    ) AS outstanding_balance,
                    (
                        SELECT COUNT(*)
                        FROM billing_invoices bi
                        WHERE bi.company_id = cs.company_id
                          AND bi.status = "overdue"
                    ) AS overdue_invoice_count
                FROM company_subscriptions cs
                INNER JOIN companies c ON c.id = cs.company_id
                LEFT JOIN billing_plans bp ON bp.id = cs.billing_plan_id';
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['trialing', 'active', 'past_due', 'suspended', 'cancelled'], true)
            ? $status
            : 'trialing';
    }

    private function normalizeCycle(string $cycle): string
    {
        return in_array($cycle, ['monthly', 'quarterly', 'yearly', 'custom'], true)
            ? $cycle
            : 'monthly';
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

    private function nullableLimit(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $limit = (int) $value;

        return $limit > 0 ? $limit : null;
    }

    private function periodEnd(DateTimeImmutable $start, string $cycle): DateTimeImmutable
    {
        return match ($cycle) {
            'quarterly' => $start->modify('+3 months'),
            'yearly' => $start->modify('+1 year'),
            default => $start->modify('+1 month'),
        };
    }
}
