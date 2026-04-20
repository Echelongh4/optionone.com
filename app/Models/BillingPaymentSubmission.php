<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class BillingPaymentSubmission extends Model
{
    protected string $table = 'billing_payment_submissions';

    private static ?bool $schemaReady = null;

    public function schemaReady(): bool
    {
        if (self::$schemaReady !== null) {
            return self::$schemaReady;
        }

        try {
            foreach (['billing_payment_submissions', 'billing_payment_methods'] as $table) {
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

    public function pendingList(int $limit = 20, ?int $companyId = null): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $limit = max(1, min(200, $limit));
        $sql = $this->baseSelect() . ' WHERE bps.status = :status';
        $params = ['status' => 'submitted'];

        if ($companyId !== null) {
            $sql .= ' AND bps.company_id = :company_id';
            $params['company_id'] = $companyId;
        }

        $sql .= ' ORDER BY bps.submitted_at ASC, bps.id ASC LIMIT ' . $limit;

        return $this->fetchAll($sql, $params);
    }

    public function listForInvoice(int $invoiceId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        return $this->fetchAll(
            $this->baseSelect() . ' WHERE bps.billing_invoice_id = :billing_invoice_id
                ORDER BY FIELD(bps.status, "submitted", "rejected", "approved", "cancelled"), bps.submitted_at DESC, bps.id DESC',
            ['billing_invoice_id' => $invoiceId]
        );
    }

    public function listForCompany(int $companyId, int $limit = 20): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        return $this->fetchAll(
            $this->baseSelect() . ' WHERE bps.company_id = :company_id
                ORDER BY bps.submitted_at DESC, bps.id DESC
                LIMIT ' . $limit,
            ['company_id' => $companyId]
        );
    }

    public function find(int $id): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        return $this->fetch(
            $this->baseSelect() . ' WHERE bps.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function findForCompany(int $id, int $companyId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }

        return $this->fetch(
            $this->baseSelect() . ' WHERE bps.id = :id AND bps.company_id = :company_id LIMIT 1',
            ['id' => $id, 'company_id' => $companyId]
        );
    }

    public function createSubmission(array $payload): int
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing payment submissions are unavailable.');
        }

        $now = date('Y-m-d H:i:s');

        return $this->insert([
            'company_id' => (int) ($payload['company_id'] ?? 0),
            'billing_invoice_id' => (int) ($payload['billing_invoice_id'] ?? 0),
            'billing_payment_method_id' => (int) ($payload['billing_payment_method_id'] ?? 0),
            'billing_invoice_payment_id' => !empty($payload['billing_invoice_payment_id']) ? (int) $payload['billing_invoice_payment_id'] : null,
            'submitted_by_user_id' => !empty($payload['submitted_by_user_id']) ? (int) $payload['submitted_by_user_id'] : null,
            'reviewed_by_user_id' => !empty($payload['reviewed_by_user_id']) ? (int) $payload['reviewed_by_user_id'] : null,
            'amount' => number_format(max(0, (float) ($payload['amount'] ?? 0)), 2, '.', ''),
            'currency' => normalize_billing_currency((string) ($payload['currency'] ?? default_currency_code()), default_currency_code()),
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? 'submitted')),
            'payer_name' => trim((string) ($payload['payer_name'] ?? '')),
            'payer_email' => strtolower(trim((string) ($payload['payer_email'] ?? ''))),
            'customer_reference' => trim((string) ($payload['customer_reference'] ?? '')),
            'gateway_reference' => trim((string) ($payload['gateway_reference'] ?? '')),
            'note' => trim((string) ($payload['note'] ?? '')),
            'review_note' => trim((string) ($payload['review_note'] ?? '')),
            'proof_path' => $this->nullableString($payload['proof_path'] ?? null),
            'submitted_at' => $this->normalizeDateTime($payload['submitted_at'] ?? null) ?? $now,
            'reviewed_at' => $this->normalizeDateTime($payload['reviewed_at'] ?? null),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function review(int $id, string $status, ?int $reviewedByUserId, string $reviewNote = '', ?int $invoicePaymentId = null): bool
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing payment submissions are unavailable.');
        }

        return $this->updateRecord([
            'status' => $this->normalizeStatus($status),
            'reviewed_by_user_id' => $reviewedByUserId,
            'billing_invoice_payment_id' => $invoicePaymentId,
            'review_note' => trim($reviewNote),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    private function baseSelect(): string
    {
        return 'SELECT
                    bps.*,
                    c.name AS company_name,
                    bi.invoice_number,
                    bi.status AS invoice_status,
                    bi.balance_due AS invoice_balance_due,
                    bi.currency AS invoice_currency,
                    bpm.name AS payment_method_name,
                    bpm.slug AS payment_method_slug,
                    bpm.type AS payment_method_type,
                    CONCAT(COALESCE(submitter.first_name, ""), " ", COALESCE(submitter.last_name, "")) AS submitted_by_name,
                    CONCAT(COALESCE(reviewer.first_name, ""), " ", COALESCE(reviewer.last_name, "")) AS reviewed_by_name
                FROM billing_payment_submissions bps
                INNER JOIN companies c ON c.id = bps.company_id
                INNER JOIN billing_invoices bi ON bi.id = bps.billing_invoice_id
                INNER JOIN billing_payment_methods bpm ON bpm.id = bps.billing_payment_method_id
                LEFT JOIN users submitter ON submitter.id = bps.submitted_by_user_id
                LEFT JOIN users reviewer ON reviewer.id = bps.reviewed_by_user_id';
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['submitted', 'approved', 'rejected', 'cancelled'], true)
            ? $status
            : 'submitted';
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
