<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class BillingInvoice extends Model
{
    protected string $table = 'billing_invoices';
    private static ?bool $paymentGatewayColumnsReady = null;

    public function schemaReady(): bool
    {
        return (new BillingPlan())->schemaReady();
    }

    public function summary(): array
    {
        if (!$this->schemaReady()) {
            return [
                'total_invoices' => 0,
                'issued_invoices' => 0,
                'overdue_invoices' => 0,
                'paid_invoices' => 0,
                'outstanding_balance' => 0,
                'paid_this_month' => 0,
            ];
        }

        return $this->fetch(
            'SELECT
                COUNT(*) AS total_invoices,
                COALESCE(SUM(CASE WHEN status = "issued" THEN 1 ELSE 0 END), 0) AS issued_invoices,
                COALESCE(SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END), 0) AS overdue_invoices,
                COALESCE(SUM(CASE WHEN status = "paid" THEN 1 ELSE 0 END), 0) AS paid_invoices,
                COALESCE(SUM(CASE WHEN status IN ("issued", "overdue") THEN balance_due ELSE 0 END), 0) AS outstanding_balance,
                COALESCE(SUM(CASE
                    WHEN status = "paid"
                     AND paid_at >= DATE_FORMAT(NOW(), "%Y-%m-01")
                    THEN amount_paid
                    ELSE 0
                END), 0) AS paid_this_month
             FROM billing_invoices'
        ) ?? [
            'total_invoices' => 0,
            'issued_invoices' => 0,
            'overdue_invoices' => 0,
            'paid_invoices' => 0,
            'outstanding_balance' => 0,
            'paid_this_month' => 0,
        ];
    }

    public function recent(int $limit = 20, ?int $companyId = null): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $params = [];
        $sql = $this->baseSelect();

        if ($companyId !== null) {
            $sql .= ' WHERE bi.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' ORDER BY COALESCE(bi.issued_at, bi.created_at) DESC, bi.id DESC LIMIT ' . $limit;

        return $this->fetchAll($sql, $params);
    }

    public function find(int $invoiceId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        return $this->fetch(
            $this->baseSelect() . ' WHERE bi.id = :id LIMIT 1',
            ['id' => $invoiceId]
        );
    }

    public function findForCompany(int $invoiceId, int $companyId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        return $this->fetch(
            $this->baseSelect() . ' WHERE bi.id = :id AND bi.company_id = :company_id LIMIT 1',
            ['id' => $invoiceId, 'company_id' => $companyId]
        );
    }

    public function findBySubscriptionPeriod(int $subscriptionId, ?string $periodStart, ?string $periodEnd): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        $clauses = ['bi.company_subscription_id = :company_subscription_id', 'bi.status <> "void"'];
        $params = ['company_subscription_id' => $subscriptionId];

        if ($periodStart !== null) {
            $clauses[] = 'bi.period_start <=> :period_start';
            $params['period_start'] = $periodStart;
        } else {
            $clauses[] = 'bi.period_start IS NULL';
        }

        if ($periodEnd !== null) {
            $clauses[] = 'bi.period_end <=> :period_end';
            $params['period_end'] = $periodEnd;
        } else {
            $clauses[] = 'bi.period_end IS NULL';
        }

        return $this->fetch(
            $this->baseSelect() . ' WHERE ' . implode(' AND ', $clauses) . ' ORDER BY bi.id DESC LIMIT 1',
            $params
        );
    }

    public function companyRiskSummary(int $companyId): array
    {
        if (!$this->schemaReady()) {
            return [
                'open_invoice_count' => 0,
                'overdue_invoice_count' => 0,
                'outstanding_balance' => 0,
                'oldest_overdue_due_at' => null,
            ];
        }

        return $this->fetch(
            'SELECT
                COALESCE(SUM(CASE WHEN status IN ("issued", "overdue") THEN 1 ELSE 0 END), 0) AS open_invoice_count,
                COALESCE(SUM(CASE WHEN status = "overdue" THEN 1 ELSE 0 END), 0) AS overdue_invoice_count,
                COALESCE(SUM(CASE WHEN status IN ("issued", "overdue") THEN balance_due ELSE 0 END), 0) AS outstanding_balance,
                MIN(CASE WHEN status = "overdue" THEN due_at ELSE NULL END) AS oldest_overdue_due_at
             FROM billing_invoices
             WHERE company_id = :company_id',
            ['company_id' => $companyId]
        ) ?? [
            'open_invoice_count' => 0,
            'overdue_invoice_count' => 0,
            'outstanding_balance' => 0,
            'oldest_overdue_due_at' => null,
        ];
    }

    public function syncDueStatuses(?int $companyId = null): int
    {
        if (!$this->schemaReady()) {
            return 0;
        }

        $sql = 'UPDATE billing_invoices
                SET status = CASE
                        WHEN balance_due <= 0 THEN "paid"
                        WHEN due_at IS NOT NULL AND due_at < NOW() THEN "overdue"
                        ELSE "issued"
                    END,
                    paid_at = CASE
                        WHEN balance_due <= 0 THEN COALESCE(paid_at, NOW())
                        ELSE NULL
                    END,
                    updated_at = NOW()
                WHERE status IN ("issued", "overdue", "paid")';
        $params = [];

        if ($companyId !== null) {
            $sql .= ' AND company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        return $this->query($sql, $params)->rowCount();
    }

    public function createInvoice(array $payload): int
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing schema support is not available.');
        }

        $subtotal = round(max(0, (float) ($payload['subtotal'] ?? 0)), 2);
        $taxTotal = round(max(0, (float) ($payload['tax_total'] ?? 0)), 2);
        $total = round($subtotal + $taxTotal, 2);
        $dueAt = $this->normalizeDateTime($payload['due_at'] ?? null) ?? date('Y-m-d H:i:s', strtotime('+7 days'));
        $status = $total > 0 ? 'issued' : 'paid';

        return $this->insert([
            'company_id' => (int) ($payload['company_id'] ?? 0),
            'company_subscription_id' => !empty($payload['company_subscription_id']) ? (int) $payload['company_subscription_id'] : null,
            'invoice_number' => $this->nextInvoiceNumber(),
            'status' => $status,
            'currency' => normalize_billing_currency((string) ($payload['currency'] ?? default_currency_code()), default_currency_code()),
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'tax_total' => number_format($taxTotal, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'amount_paid' => $status === 'paid' ? number_format($total, 2, '.', '') : '0.00',
            'balance_due' => $status === 'paid' ? '0.00' : number_format($total, 2, '.', ''),
            'description' => trim((string) ($payload['description'] ?? '')),
            'period_start' => $this->normalizeDateTime($payload['period_start'] ?? null),
            'period_end' => $this->normalizeDateTime($payload['period_end'] ?? null),
            'issued_at' => $this->normalizeDateTime($payload['issued_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'due_at' => $dueAt,
            'paid_at' => $status === 'paid' ? date('Y-m-d H:i:s') : null,
            'payment_reference' => trim((string) ($payload['payment_reference'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function recordPayment(int $invoiceId, array $payload): int
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing schema support is not available.');
        }

        $invoice = $this->find($invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Billing invoice not found.');
        }

        if ((string) ($invoice['status'] ?? 'issued') === 'void') {
            throw new \RuntimeException('Void invoices cannot receive payments.');
        }

        $amount = round((float) ($payload['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Payment amount must be greater than zero.');
        }

        $balanceDue = round((float) ($invoice['balance_due'] ?? 0), 2);
        if ($amount - $balanceDue > 0.01) {
            throw new \RuntimeException('Payment amount cannot exceed the invoice balance due.');
        }

        $paymentMethodId = $this->paymentFeaturesReady()
            ? (!empty($payload['billing_payment_method_id']) ? (int) $payload['billing_payment_method_id'] : null)
            : null;
        $paymentMethod = $this->resolvePaymentMethod(
            (string) ($payload['payment_method'] ?? 'bank_transfer'),
            $paymentMethodId
        );

        $columns = [
            'billing_invoice_id',
            'recorded_by_user_id',
            'payment_method',
            'amount',
            'gateway_provider',
            'reference',
            'gateway_reference',
            'notes',
            'paid_at',
            'created_at',
        ];
        $params = [
            'billing_invoice_id' => $invoiceId,
            'recorded_by_user_id' => !empty($payload['recorded_by_user_id']) ? (int) $payload['recorded_by_user_id'] : null,
            'payment_method' => $paymentMethod,
            'amount' => number_format($amount, 2, '.', ''),
            'paid_at' => $this->normalizeDateTime($payload['paid_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $reference = trim((string) ($payload['reference'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        if ($this->paymentGatewayColumnsReady()) {
            $columns[] = 'gateway_payload_json';
            $params['gateway_provider'] = trim((string) ($payload['gateway_provider'] ?? ''));
            $params['gateway_reference'] = trim((string) ($payload['gateway_reference'] ?? ''));
            $params['gateway_payload_json'] = $this->jsonOrNull($payload['gateway_payload'] ?? null);
        } else {
            $columns = array_values(array_filter($columns, static fn (string $column): bool => !in_array($column, ['gateway_provider', 'gateway_reference'], true)));
        }

        $params['reference'] = $reference;
        $params['notes'] = $notes;

        if ($paymentMethodId !== null) {
            $columns[] = 'billing_payment_method_id';
            $params['billing_payment_method_id'] = $paymentMethodId;
        }

        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $sql = 'INSERT INTO billing_invoice_payments (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $placeholders) . ')';
        $this->db->prepare($sql)->execute($params);
        $paymentId = (int) $this->db->lastInsertId();

        $this->recalculateInvoice($invoiceId);

        return $paymentId;
    }

    public function updateInvoiceStatus(int $invoiceId, string $status, string $note = ''): void
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing schema support is not available.');
        }

        $invoice = $this->find($invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Billing invoice not found.');
        }

        $status = in_array($status, ['draft', 'issued', 'paid', 'void', 'overdue'], true) ? $status : 'issued';
        if ($status === 'void' && (float) ($invoice['amount_paid'] ?? 0) > 0) {
            throw new \RuntimeException('Paid invoices cannot be voided.');
        }

        if ($status === 'overdue' && (float) ($invoice['balance_due'] ?? 0) <= 0) {
            throw new \RuntimeException('Only open invoices can be marked overdue.');
        }

        $note = trim($note);
        $existingNotes = trim((string) ($invoice['notes'] ?? ''));
        $mergedNotes = $note !== ''
            ? trim($existingNotes . ($existingNotes !== '' ? "\n" : '') . $note)
            : $existingNotes;

        $this->updateRecord([
            'status' => $status,
            'notes' => $mergedNotes,
            'paid_at' => $status === 'paid' ? date('Y-m-d H:i:s') : ($status === 'void' ? null : ($invoice['paid_at'] ?? null)),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $invoiceId]);
    }

    public function paymentsForInvoice(int $invoiceId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $sql = 'SELECT
                    bip.*,
                    CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, "")) AS recorded_by_name';

        if ($this->paymentFeaturesReady()) {
            $sql .= ',
                    bpm.name AS payment_method_name,
                    bpm.slug AS payment_method_slug';
        }

        if ($this->paymentGatewayColumnsReady()) {
            $sql .= ',
                    bip.gateway_provider,
                    bip.gateway_reference,
                    bip.gateway_payload_json';
        }

        $sql .= '
                 FROM billing_invoice_payments bip
                 LEFT JOIN users u ON u.id = bip.recorded_by_user_id';

        if ($this->paymentFeaturesReady()) {
            $sql .= '
                 LEFT JOIN billing_payment_methods bpm ON bpm.id = bip.billing_payment_method_id';
        }

        $sql .= '
                 WHERE bip.billing_invoice_id = :billing_invoice_id
                 ORDER BY bip.paid_at DESC, bip.id DESC';

        return $this->fetchAll($sql, ['billing_invoice_id' => $invoiceId]);
    }

    private function recalculateInvoice(int $invoiceId): void
    {
        $invoice = $this->fetch(
            'SELECT *
             FROM billing_invoices
             WHERE id = :id
             LIMIT 1',
            ['id' => $invoiceId]
        );

        if ($invoice === null) {
            return;
        }

        $payments = $this->fetch(
            'SELECT
                COALESCE(SUM(amount), 0) AS total_paid,
                MAX(paid_at) AS latest_paid_at
             FROM billing_invoice_payments
             WHERE billing_invoice_id = :billing_invoice_id',
            ['billing_invoice_id' => $invoiceId]
        ) ?? ['total_paid' => 0, 'latest_paid_at' => null];

        $totalPaid = round((float) ($payments['total_paid'] ?? 0), 2);
        $invoiceTotal = round((float) ($invoice['total'] ?? 0), 2);
        $balanceDue = max(0, round($invoiceTotal - $totalPaid, 2));
        $status = $balanceDue <= 0
            ? 'paid'
            : $this->openStatus((string) ($invoice['due_at'] ?? ''));

        $this->updateRecord([
            'amount_paid' => number_format($totalPaid, 2, '.', ''),
            'balance_due' => number_format($balanceDue, 2, '.', ''),
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($payments['latest_paid_at'] ?? date('Y-m-d H:i:s')) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $invoiceId]);
    }

    private function baseSelect(): string
    {
        return 'SELECT
                    bi.*,
                    c.name AS company_name,
                    c.slug AS company_slug,
                    cs.plan_name_snapshot,
                    bp.name AS plan_name
                FROM billing_invoices bi
                INNER JOIN companies c ON c.id = bi.company_id
                LEFT JOIN company_subscriptions cs ON cs.id = bi.company_subscription_id
                LEFT JOIN billing_plans bp ON bp.id = cs.billing_plan_id';
    }

    private function openStatus(string $dueAt): string
    {
        $timestamp = strtotime($dueAt);

        return $timestamp !== false && $timestamp < time() ? 'overdue' : 'issued';
    }

    private function normalizePaymentMethod(string $paymentMethod): string
    {
        return in_array($paymentMethod, ['bank_transfer', 'card', 'cash', 'mobile_money', 'other'], true)
            ? $paymentMethod
            : 'bank_transfer';
    }

    private function paymentFeaturesReady(): bool
    {
        return class_exists(BillingPaymentMethod::class) && (new BillingPaymentMethod())->schemaReady();
    }

    private function paymentGatewayColumnsReady(): bool
    {
        if (self::$paymentGatewayColumnsReady !== null) {
            return self::$paymentGatewayColumnsReady;
        }

        self::$paymentGatewayColumnsReady = $this->columnExists('billing_invoice_payments', 'gateway_provider')
            && $this->columnExists('billing_invoice_payments', 'gateway_reference')
            && $this->columnExists('billing_invoice_payments', 'gateway_payload_json');

        return self::$paymentGatewayColumnsReady;
    }

    private function resolvePaymentMethod(string $paymentMethod, ?int $paymentMethodId = null): string
    {
        if ($this->paymentFeaturesReady() && $paymentMethodId !== null) {
            $method = (new BillingPaymentMethod())->find($paymentMethodId);
            if ($method !== null) {
                return (string) ($method['slug'] ?? $paymentMethod);
            }
        }

        return $this->normalizePaymentMethod($paymentMethod);
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

    private function nextInvoiceNumber(): string
    {
        do {
            $candidate = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        } while ($this->fetch(
            'SELECT id
             FROM billing_invoices
             WHERE invoice_number = :invoice_number
             LIMIT 1',
            ['invoice_number' => $candidate]
        ) !== null);

        return $candidate;
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
