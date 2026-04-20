<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\BillingGatewayTransaction;
use App\Models\BillingInvoice;
use App\Models\BillingPaymentMethod;
use App\Models\BillingPaymentSubmission;
use App\Models\Notification;
use App\Models\User;

class BillingPaymentService
{
    public function schemaReady(): bool
    {
        return (new BillingPaymentMethod())->schemaReady();
    }

    public function gatewayReady(): bool
    {
        return $this->schemaReady() && (new BillingGatewayTransaction())->schemaReady();
    }

    public function availableMethodsForInvoice(?array $invoice): array
    {
        if (!$this->schemaReady() || !is_array($invoice)) {
            return [];
        }

        return (new BillingPaymentMethod())->activeForCurrency((string) ($invoice['currency'] ?? ''));
    }

    public function gatewayTransactionsForInvoice(int $invoiceId): array
    {
        if (!$this->gatewayReady()) {
            return [];
        }

        return (new BillingGatewayTransaction())->listForInvoice($invoiceId);
    }

    public function submitInvoicePayment(int $invoiceId, array $payload, ?array $proofFile = null, ?int $submittedByUserId = null): int
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing payment methods are unavailable until migration 014 is applied.');
        }

        [$invoice, $method] = $this->validatedInvoiceAndMethod($invoiceId, (int) ($payload['billing_payment_method_id'] ?? 0));
        if ((string) ($method['integration_driver'] ?? 'manual') !== 'manual') {
            throw new \RuntimeException('This payment method uses secure checkout. Start the hosted checkout flow instead of manual submission.');
        }

        $amount = $this->validatedAmount($invoice, (float) ($payload['amount'] ?? 0));
        $customerReference = trim((string) ($payload['customer_reference'] ?? ''));
        $gatewayReference = trim((string) ($payload['gateway_reference'] ?? ''));
        if (!empty($method['requires_reference']) && $customerReference === '' && $gatewayReference === '') {
            throw new \RuntimeException('A payment reference is required for this method.');
        }

        $proofPath = (new UploadService())->store($proofFile, 'billing-proofs/' . date('Y/m'));
        if (!empty($method['requires_proof']) && $proofPath === null) {
            throw new \RuntimeException('Upload proof of payment for this method before submitting.');
        }

        $submissionId = (new BillingPaymentSubmission())->createSubmission([
            'company_id' => (int) ($invoice['company_id'] ?? 0),
            'billing_invoice_id' => $invoiceId,
            'billing_payment_method_id' => (int) ($method['id'] ?? 0),
            'submitted_by_user_id' => $submittedByUserId,
            'amount' => $amount,
            'currency' => normalize_billing_currency((string) ($invoice['currency'] ?? default_currency_code()), default_currency_code()),
            'payer_name' => trim((string) ($payload['payer_name'] ?? '')),
            'payer_email' => trim((string) ($payload['payer_email'] ?? '')),
            'customer_reference' => $customerReference,
            'gateway_reference' => $gatewayReference,
            'note' => trim((string) ($payload['note'] ?? '')),
            'proof_path' => $proofPath,
        ]);

        $this->notifyPlatformAdminsOfSubmission($invoice, $method, $amount);

        return $submissionId;
    }

    public function initializeHostedCheckout(int $invoiceId, array $payload, ?int $initiatedByUserId = null): array
    {
        if (!$this->gatewayReady()) {
            throw new \RuntimeException('Hosted checkout is unavailable until migration 015 is applied.');
        }

        [$invoice, $method] = $this->validatedInvoiceAndMethod($invoiceId, (int) ($payload['billing_payment_method_id'] ?? 0));
        if ((string) ($method['integration_driver'] ?? 'manual') !== 'paystack') {
            throw new \RuntimeException('This payment method does not support hosted checkout.');
        }

        $gateway = new PaystackGatewayService();
        $gatewaySettings = $gateway->settings();
        if (!$gateway->configured($gatewaySettings)) {
            throw new \RuntimeException('Paystack is not configured on the platform billing desk.');
        }

        $amount = $this->validatedAmount($invoice, (float) ($payload['amount'] ?? 0));
        $payerEmail = strtolower(trim((string) ($payload['payer_email'] ?? '')));
        if (!filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('A valid payer email is required for hosted checkout.');
        }

        $payerName = trim((string) ($payload['payer_name'] ?? ''));
        $payerPhone = trim((string) ($payload['payer_phone'] ?? ''));
        $returnTo = trim((string) ($payload['return_to'] ?? 'billing/invoices/show?id=' . $invoiceId));
        if ($returnTo === '' || !str_starts_with($returnTo, 'billing')) {
            $returnTo = 'billing/invoices/show?id=' . $invoiceId;
        }

        $reference = $this->uniqueGatewayReference($invoiceId);
        $metadata = [
            'invoice_id' => $invoiceId,
            'company_id' => (int) ($invoice['company_id'] ?? 0),
            'billing_payment_method_id' => (int) ($method['id'] ?? 0),
            'initiated_by_user_id' => $initiatedByUserId,
            'return_to' => $returnTo,
        ];

        $integrationConfig = is_array($method['integration_config'] ?? null) ? $method['integration_config'] : [];
        $channels = is_array($integrationConfig['channels'] ?? null) ? $integrationConfig['channels'] : ($gatewaySettings['channels'] ?? []);

        $response = $gateway->initializeTransaction([
            'email' => $payerEmail,
            'amount' => $amount,
            'currency' => normalize_billing_currency((string) ($invoice['currency'] ?? default_currency_code()), default_currency_code()),
            'reference' => $reference,
            'callback_url' => absolute_url('billing/payments/callback'),
            'metadata' => $metadata,
            'channels' => $channels,
        ], $gatewaySettings);

        $responseData = is_array($response['data'] ?? null) ? $response['data'] : [];
        $transactionId = (new BillingGatewayTransaction())->createTransaction([
            'company_id' => (int) ($invoice['company_id'] ?? 0),
            'billing_invoice_id' => $invoiceId,
            'billing_payment_method_id' => (int) ($method['id'] ?? 0),
            'initiated_by_user_id' => $initiatedByUserId,
            'provider' => 'paystack',
            'provider_reference' => $reference,
            'provider_transaction_id' => isset($responseData['id']) ? (string) $responseData['id'] : null,
            'access_code' => (string) ($responseData['access_code'] ?? ''),
            'authorization_url' => (string) ($responseData['authorization_url'] ?? ''),
            'status' => 'pending',
            'amount' => $amount,
            'currency' => normalize_billing_currency((string) ($invoice['currency'] ?? default_currency_code()), default_currency_code()),
            'payer_name' => $payerName,
            'payer_email' => $payerEmail,
            'payer_phone' => $payerPhone,
            'metadata' => $metadata,
        ]);

        return [
            'transaction_id' => $transactionId,
            'authorization_url' => (string) ($responseData['authorization_url'] ?? ''),
            'reference' => $reference,
            'invoice_id' => $invoiceId,
            'company_id' => (int) ($invoice['company_id'] ?? 0),
            'return_to' => $returnTo,
        ];
    }

    public function verifyHostedCheckout(string $reference): array
    {
        if (!$this->gatewayReady()) {
            throw new \RuntimeException('Hosted checkout verification is unavailable until migration 015 is applied.');
        }

        $reference = trim($reference);
        if ($reference === '') {
            throw new \RuntimeException('Hosted checkout reference is missing.');
        }

        $transactionModel = new BillingGatewayTransaction();
        $transaction = $transactionModel->findByReference($reference);
        if ($transaction === null) {
            throw new \RuntimeException('Hosted checkout transaction not found.');
        }

        if (!empty($transaction['billing_invoice_payment_id'])) {
            return [
                'status' => 'success',
                'reference' => $reference,
                'invoice_id' => (int) ($transaction['billing_invoice_id'] ?? 0),
                'company_id' => (int) ($transaction['company_id'] ?? 0),
                'payment_posted' => true,
                'already_processed' => true,
                'return_to' => $this->transactionReturnTo($transaction),
            ];
        }

        $gateway = new PaystackGatewayService();
        $verification = $gateway->verifyTransaction($reference, $gateway->settings());
        $verificationData = is_array($verification['data'] ?? null) ? $verification['data'] : [];
        $gatewayStatus = strtolower(trim((string) ($verificationData['status'] ?? '')));
        $transactionStatus = match ($gatewayStatus) {
            'success' => 'success',
            'failed', 'abandoned', 'reversed' => 'failed',
            default => 'pending',
        };

        $transactionModel->updateTransaction((int) ($transaction['id'] ?? 0), [
            'status' => $transactionStatus,
            'provider_transaction_id' => isset($verificationData['id']) ? (string) $verificationData['id'] : null,
            'verification_payload' => $verification,
            'last_checked_at' => date('Y-m-d H:i:s'),
            'verified_at' => $transactionStatus === 'success' ? date('Y-m-d H:i:s') : null,
            'failure_reason' => $transactionStatus === 'failed' ? (string) ($verificationData['gateway_response'] ?? $verification['message'] ?? 'Gateway payment failed.') : null,
        ]);

        if ($transactionStatus !== 'success') {
            return [
                'status' => $transactionStatus,
                'reference' => $reference,
                'invoice_id' => (int) ($transaction['billing_invoice_id'] ?? 0),
                'company_id' => (int) ($transaction['company_id'] ?? 0),
                'payment_posted' => false,
                'gateway_message' => trim((string) ($verificationData['gateway_response'] ?? $verification['message'] ?? 'Payment is still pending.')),
                'return_to' => $this->transactionReturnTo($transaction),
            ];
        }

        $invoiceModel = new BillingInvoice();
        $invoice = $invoiceModel->find((int) ($transaction['billing_invoice_id'] ?? 0));
        if ($invoice === null) {
            throw new \RuntimeException('Billing invoice not found.');
        }

        $verifiedAmount = round(((float) ($verificationData['amount'] ?? 0)) / 100, 2);
        $expectedAmount = round((float) ($transaction['amount'] ?? 0), 2);
        if (abs($verifiedAmount - $expectedAmount) > 0.01) {
            $transactionModel->updateTransaction((int) ($transaction['id'] ?? 0), [
                'status' => 'failed',
                'failure_reason' => 'Verified amount does not match the initiated invoice amount.',
                'last_checked_at' => date('Y-m-d H:i:s'),
            ]);

            throw new \RuntimeException('Verified payment amount does not match the initiated invoice amount.');
        }

        if ((float) ($invoice['balance_due'] ?? 0) <= 0) {
            return [
                'status' => 'success',
                'reference' => $reference,
                'invoice_id' => (int) ($invoice['id'] ?? 0),
                'company_id' => (int) ($invoice['company_id'] ?? 0),
                'payment_posted' => false,
                'already_settled' => true,
                'return_to' => $this->transactionReturnTo($transaction),
            ];
        }

        $paymentMethod = (new BillingPaymentMethod())->find((int) ($transaction['billing_payment_method_id'] ?? 0));
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $paymentId = $invoiceModel->recordPayment((int) $invoice['id'], [
                'amount' => $verifiedAmount,
                'billing_payment_method_id' => (int) ($transaction['billing_payment_method_id'] ?? 0),
                'payment_method' => (string) ($paymentMethod['slug'] ?? 'paystack'),
                'gateway_provider' => 'paystack',
                'reference' => trim((string) ($transaction['provider_reference'] ?? $reference)),
                'gateway_reference' => trim((string) ($transaction['provider_reference'] ?? $reference)),
                'gateway_payload' => $verification,
                'paid_at' => $this->gatewayPaidAt($verificationData),
                'notes' => 'Gateway payment verified automatically via Paystack.',
                'recorded_by_user_id' => !empty($transaction['initiated_by_user_id']) ? (int) $transaction['initiated_by_user_id'] : null,
            ]);

            $transactionModel->updateTransaction((int) ($transaction['id'] ?? 0), [
                'billing_invoice_payment_id' => $paymentId,
                'status' => 'success',
                'verified_at' => date('Y-m-d H:i:s'),
                'last_checked_at' => date('Y-m-d H:i:s'),
            ]);

            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        $freshTransaction = $transactionModel->find((int) ($transaction['id'] ?? 0)) ?? $transaction;
        $this->notifyCompanyAdminsOfGatewayConfirmation($invoice, $paymentMethod, $verifiedAmount);

        return [
            'status' => 'success',
            'reference' => $reference,
            'invoice_id' => (int) ($invoice['id'] ?? 0),
            'company_id' => (int) ($invoice['company_id'] ?? 0),
            'payment_posted' => !empty($freshTransaction['billing_invoice_payment_id']),
            'return_to' => $this->transactionReturnTo($freshTransaction),
        ];
    }

    public function handlePaystackWebhook(string $rawPayload, string $signature): array
    {
        if (!$this->gatewayReady()) {
            throw new \RuntimeException('Hosted checkout webhook handling is unavailable until migration 015 is applied.');
        }

        $gateway = new PaystackGatewayService();
        if (!$gateway->validWebhookSignature($rawPayload, $signature, $gateway->settings())) {
            throw new \RuntimeException('Invalid Paystack webhook signature.');
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Webhook payload could not be parsed.');
        }

        $event = strtolower(trim((string) ($payload['event'] ?? '')));
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $reference = trim((string) ($data['reference'] ?? ''));

        if ($reference === '') {
            return ['status' => 'ignored', 'message' => 'No gateway reference supplied.'];
        }

        if (!in_array($event, ['charge.success', 'charge.failed'], true)) {
            return ['status' => 'ignored', 'message' => 'Webhook event is not handled by the billing workflow.'];
        }

        if ($event === 'charge.failed') {
            $transaction = (new BillingGatewayTransaction())->findByReference($reference);
            if ($transaction !== null) {
                (new BillingGatewayTransaction())->updateTransaction((int) ($transaction['id'] ?? 0), [
                    'status' => 'failed',
                    'verification_payload' => $payload,
                    'last_checked_at' => date('Y-m-d H:i:s'),
                    'failure_reason' => trim((string) ($data['gateway_response'] ?? 'Gateway payment failed.')),
                ]);
            }

            return ['status' => 'failed', 'reference' => $reference];
        }

        return $this->verifyHostedCheckout($reference);
    }

    public function approveSubmission(int $submissionId, int $reviewedByUserId, string $reviewNote = ''): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing payment methods are unavailable until migration 014 is applied.');
        }

        $submissionModel = new BillingPaymentSubmission();
        $submission = $submissionModel->find($submissionId);
        if ($submission === null) {
            throw new \RuntimeException('Payment submission not found.');
        }

        if ((string) ($submission['status'] ?? 'submitted') !== 'submitted') {
            throw new \RuntimeException('Only submitted payments can be approved.');
        }

        $invoiceModel = new BillingInvoice();
        $invoice = $invoiceModel->find((int) ($submission['billing_invoice_id'] ?? 0));
        if ($invoice === null) {
            throw new \RuntimeException('Billing invoice not found.');
        }

        $amount = round((float) ($submission['amount'] ?? 0), 2);
        $balanceDue = round((float) ($invoice['balance_due'] ?? 0), 2);
        if ($amount <= 0 || $amount - $balanceDue > 0.01) {
            throw new \RuntimeException('The submitted amount no longer matches the open invoice balance.');
        }

        $gatewayProvider = (string) ($submission['payment_method_slug'] ?? '') === 'paystack' ? 'paystack' : '';
        $connection = Database::connection();
        $connection->beginTransaction();

        try {
            $paymentId = $invoiceModel->recordPayment((int) $invoice['id'], [
                'amount' => $amount,
                'billing_payment_method_id' => (int) ($submission['billing_payment_method_id'] ?? 0),
                'payment_method' => (string) ($submission['payment_method_slug'] ?? $submission['payment_method_type'] ?? 'other'),
                'gateway_provider' => $gatewayProvider,
                'reference' => $this->submissionReference($submission),
                'gateway_reference' => trim((string) ($submission['gateway_reference'] ?? '')),
                'paid_at' => (string) ($submission['submitted_at'] ?? date('Y-m-d H:i:s')),
                'notes' => $this->approvalNotes($submission, $reviewNote),
                'recorded_by_user_id' => $reviewedByUserId,
            ]);

            $submissionModel->review($submissionId, 'approved', $reviewedByUserId, $reviewNote, $paymentId);
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        $this->notifyCompanyAdminsOfReview($submission, 'approved', $reviewNote);

        return [
            'invoice_id' => (int) ($submission['billing_invoice_id'] ?? 0),
            'company_id' => (int) ($submission['company_id'] ?? 0),
        ];
    }

    public function rejectSubmission(int $submissionId, int $reviewedByUserId, string $reviewNote = ''): array
    {
        if (!$this->schemaReady()) {
            throw new \RuntimeException('Billing payment methods are unavailable until migration 014 is applied.');
        }

        $submissionModel = new BillingPaymentSubmission();
        $submission = $submissionModel->find($submissionId);
        if ($submission === null) {
            throw new \RuntimeException('Payment submission not found.');
        }

        if ((string) ($submission['status'] ?? 'submitted') !== 'submitted') {
            throw new \RuntimeException('Only submitted payments can be rejected.');
        }

        $submissionModel->review($submissionId, 'rejected', $reviewedByUserId, $reviewNote);
        $this->notifyCompanyAdminsOfReview($submission, 'rejected', $reviewNote);

        return [
            'invoice_id' => (int) ($submission['billing_invoice_id'] ?? 0),
            'company_id' => (int) ($submission['company_id'] ?? 0),
        ];
    }

    private function validatedInvoiceAndMethod(int $invoiceId, int $methodId): array
    {
        $invoice = (new BillingInvoice())->find($invoiceId);
        if ($invoice === null) {
            throw new \RuntimeException('Billing invoice not found.');
        }

        if (in_array((string) ($invoice['status'] ?? 'issued'), ['paid', 'void'], true) || (float) ($invoice['balance_due'] ?? 0) <= 0) {
            throw new \RuntimeException('This invoice is not accepting payments.');
        }

        $method = (new BillingPaymentMethod())->find($methodId);
        if ($method === null || (string) ($method['status'] ?? 'inactive') !== 'active') {
            throw new \RuntimeException('Select a valid active payment method.');
        }

        if (!(new BillingPaymentMethod())->supportsCurrency($method, (string) ($invoice['currency'] ?? ''))) {
            throw new \RuntimeException('The selected payment method does not support this invoice currency.');
        }

        return [$invoice, $method];
    }

    private function validatedAmount(array $invoice, float $amount): float
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            throw new \RuntimeException('Payment amount must be greater than zero.');
        }

        $balanceDue = round((float) ($invoice['balance_due'] ?? 0), 2);
        if ($amount - $balanceDue > 0.01) {
            throw new \RuntimeException('Payment amount cannot exceed the invoice balance due.');
        }

        return $amount;
    }

    private function approvalNotes(array $submission, string $reviewNote): string
    {
        $notes = [];
        $submissionNote = trim((string) ($submission['note'] ?? ''));
        if ($submissionNote !== '') {
            $notes[] = 'Submission note: ' . $submissionNote;
        }

        $reviewNote = trim($reviewNote);
        if ($reviewNote !== '') {
            $notes[] = 'Review note: ' . $reviewNote;
        }

        return implode("\n", $notes);
    }

    private function submissionReference(array $submission): string
    {
        $references = array_values(array_filter([
            trim((string) ($submission['customer_reference'] ?? '')),
            trim((string) ($submission['gateway_reference'] ?? '')),
        ]));

        return $references !== [] ? implode(' | ', $references) : '';
    }

    private function uniqueGatewayReference(int $invoiceId): string
    {
        $transactionModel = new BillingGatewayTransaction();

        do {
            $candidate = 'PAY-' . $invoiceId . '-' . strtoupper(bin2hex(random_bytes(4)));
        } while ($transactionModel->findByReference($candidate) !== null);

        return $candidate;
    }

    private function gatewayPaidAt(array $verificationData): string
    {
        $candidates = [
            (string) ($verificationData['paid_at'] ?? ''),
            (string) ($verificationData['transaction_date'] ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $timestamp = strtotime($candidate);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        return date('Y-m-d H:i:s');
    }

    private function transactionReturnTo(array $transaction): string
    {
        $metadata = is_array($transaction['metadata'] ?? null) ? $transaction['metadata'] : [];
        $returnTo = trim((string) ($metadata['return_to'] ?? ''));

        if ($returnTo === '' || !str_starts_with($returnTo, 'billing')) {
            $invoiceId = (int) ($transaction['billing_invoice_id'] ?? 0);
            return $invoiceId > 0 ? 'billing/invoices/show?id=' . $invoiceId : 'billing';
        }

        return $returnTo;
    }

    private function notifyPlatformAdminsOfSubmission(array $invoice, array $method, float $amount): void
    {
        $platformAdmins = (new User())->listDirectPlatformAdmins();
        if ($platformAdmins === []) {
            return;
        }

        $notification = new Notification();
        $invoiceId = (int) ($invoice['id'] ?? 0);
        $companyName = (string) ($invoice['company_name'] ?? 'Company');
        $amountLabel = format_money($amount, normalize_billing_currency((string) ($invoice['currency'] ?? default_currency_code()), default_currency_code()));
        $methodName = (string) ($method['name'] ?? 'Payment method');

        foreach ($platformAdmins as $admin) {
            $notification->createUserNotification(
                (int) ($admin['id'] ?? 0),
                !empty($admin['branch_id']) ? (int) $admin['branch_id'] : null,
                'billing',
                'Payment submission awaiting review',
                $companyName . ' submitted ' . $amountLabel . ' via ' . $methodName . '.',
                'platform/billing/invoices/show?id=' . $invoiceId,
                false
            );
        }
    }

    private function notifyCompanyAdminsOfReview(array $submission, string $status, string $reviewNote): void
    {
        $companyId = (int) ($submission['company_id'] ?? 0);
        if ($companyId <= 0) {
            return;
        }

        $users = (new User())->listUsers(null, $companyId);
        $notification = new Notification();
        $invoiceId = (int) ($submission['billing_invoice_id'] ?? 0);
        $amountLabel = format_money((float) ($submission['amount'] ?? 0), normalize_billing_currency((string) ($submission['currency'] ?? default_currency_code()), default_currency_code()));
        $methodName = (string) ($submission['payment_method_name'] ?? 'payment method');
        $title = $status === 'approved' ? 'Payment approved' : 'Payment rejected';
        $message = $status === 'approved'
            ? 'Your ' . $amountLabel . ' payment via ' . $methodName . ' has been approved.'
            : 'Your ' . $amountLabel . ' payment via ' . $methodName . ' was rejected.';

        if (trim($reviewNote) !== '') {
            $message .= ' Note: ' . trim($reviewNote);
        }

        foreach ($users as $user) {
            if ((string) ($user['status'] ?? 'inactive') !== 'active') {
                continue;
            }

            if (!in_array((string) ($user['role_name'] ?? ''), ['Super Admin', 'Admin'], true)) {
                continue;
            }

            $notification->createUserNotification(
                (int) ($user['id'] ?? 0),
                !empty($user['branch_id']) ? (int) $user['branch_id'] : null,
                'billing',
                $title,
                $message,
                'billing/invoices/show?id=' . $invoiceId,
                false
            );
        }
    }

    private function notifyCompanyAdminsOfGatewayConfirmation(?array $invoice, ?array $method, float $amount): void
    {
        if (!is_array($invoice)) {
            return;
        }

        $companyId = (int) ($invoice['company_id'] ?? 0);
        if ($companyId <= 0) {
            return;
        }

        $notification = new Notification();
        $invoiceId = (int) ($invoice['id'] ?? 0);
        $amountLabel = format_money($amount, normalize_billing_currency((string) ($invoice['currency'] ?? default_currency_code()), default_currency_code()));
        $methodName = (string) ($method['name'] ?? 'secure checkout');

        foreach ((new User())->listUsers(null, $companyId) as $user) {
            if ((string) ($user['status'] ?? 'inactive') !== 'active') {
                continue;
            }

            if (!in_array((string) ($user['role_name'] ?? ''), ['Super Admin', 'Admin'], true)) {
                continue;
            }

            $notification->createUserNotification(
                (int) ($user['id'] ?? 0),
                !empty($user['branch_id']) ? (int) $user['branch_id'] : null,
                'billing',
                'Payment confirmed',
                'Your ' . $amountLabel . ' payment via ' . $methodName . ' was confirmed automatically.',
                'billing/invoices/show?id=' . $invoiceId,
                false
            );
        }
    }
}
