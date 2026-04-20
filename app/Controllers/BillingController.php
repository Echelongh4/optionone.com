<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\BillingPlan;
use App\Models\CompanySubscription;
use App\Models\Setting;
use App\Services\BillingOperationsService;
use App\Services\BillingPaymentService;
use App\Services\BillingUsageService;
use Throwable;

class BillingController extends Controller
{
    public function index(Request $request): void
    {
        $planModel = new BillingPlan();
        $billingReady = $planModel->schemaReady();
        $companyId = current_company_id();
        $company = current_company();
        $billingOps = new BillingOperationsService();
        $plans = [];
        $subscription = null;
        $usage = [];
        $invoices = [];
        $alerts = [];
        $paymentService = new BillingPaymentService();

        if ($billingReady && $companyId !== null) {
            $billingOps->syncCompany($companyId, false, false);
            $plans = $planModel->all(true);
            $subscription = (new CompanySubscription())->findByCompany($companyId);
            $invoices = (new BillingInvoice())->recent(20, $companyId);
            $usage = (new BillingUsageService())->snapshot($companyId);
            $alerts = $billingOps->tenantAlerts($companyId, $subscription, $usage, $invoices);

            if (
                $subscription !== null
                && !empty($subscription['billing_plan_id'])
                && !array_filter($plans, static fn (array $plan): bool => (int) ($plan['id'] ?? 0) === (int) $subscription['billing_plan_id'])
            ) {
                $currentPlan = $planModel->find((int) $subscription['billing_plan_id']);
                if ($currentPlan !== null) {
                    array_unshift($plans, $currentPlan);
                }
            }
        }

        $this->render('billing/index', [
            'title' => 'Billing',
            'breadcrumbs' => ['Dashboard', 'Billing'],
            'billingReady' => $billingReady,
            'paymentsReady' => $paymentService->schemaReady(),
            'billingSchemaMessage' => $this->billingSchemaMessage(),
            'paymentSchemaMessage' => $this->paymentSchemaMessage(),
            'company' => $company,
            'billingSettings' => $this->billingSettings(),
            'platformSettings' => $billingOps->platformSettings(),
            'plans' => $plans,
            'subscription' => $subscription,
            'usage' => $usage,
            'invoices' => $invoices,
            'alerts' => $alerts,
            'paymentMethods' => $subscription !== null
                ? $paymentService->availableMethodsForInvoice([
                    'currency' => normalize_billing_currency((string) ($subscription['currency'] ?? default_currency_code()), default_currency_code()),
                ])
                : [],
            'billingCurrencies' => billing_currency_options([
                (string) ($subscription['currency'] ?? ''),
            ]),
        ]);
    }

    public function showInvoice(Request $request): void
    {
        $companyId = current_company_id();
        if ($companyId === null) {
            throw new HttpException(404, 'Billing invoice not found.');
        }

        $invoiceId = (int) $request->query('id', 0);
        $invoiceData = (new BillingOperationsService())->invoiceViewData($invoiceId);
        if ($invoiceData === null) {
            throw new HttpException(404, 'Billing invoice not found.');
        }

        $invoice = $invoiceData['invoice'];
        if ((int) ($invoice['company_id'] ?? 0) !== $companyId) {
            throw new HttpException(404, 'Billing invoice not found.');
        }

        $this->render('billing/show', [
            'title' => 'Invoice ' . (string) ($invoice['invoice_number'] ?? ('#' . $invoiceId)),
            'breadcrumbs' => ['Dashboard', 'Billing', (string) ($invoice['invoice_number'] ?? 'Invoice')],
            'invoice' => $invoice,
            'company' => $invoiceData['company'],
            'subscription' => $invoiceData['subscription'],
            'payments' => $invoiceData['payments'],
            'paymentMethods' => $invoiceData['paymentMethods'] ?? [],
            'paymentSubmissions' => $invoiceData['paymentSubmissions'] ?? [],
            'paymentsReady' => (bool) ($invoiceData['paymentsReady'] ?? false),
            'billingProfile' => $invoiceData['billingProfile'],
            'platformSettings' => $invoiceData['platformSettings'],
            'backPath' => 'billing',
            'backLabel' => 'Back to Billing',
            'isPlatformView' => false,
        ]);
    }

    public function submitPayment(Request $request): void
    {
        $companyId = current_company_id();
        if ($companyId === null) {
            Session::flash('error', 'A company workspace is required for billing payments.');
            $this->redirect('dashboard');
        }

        $invoiceId = (int) $request->input('invoice_id', 0);
        $returnTo = (string) $request->input('return_to', 'billing');
        $payload = [
            'invoice_id' => (string) $invoiceId,
            'billing_payment_method_id' => trim((string) $request->input('billing_payment_method_id', '')),
            'amount' => trim((string) $request->input('amount', '0')),
            'payer_name' => trim((string) $request->input('payer_name', '')),
            'payer_email' => trim((string) $request->input('payer_email', '')),
            'customer_reference' => trim((string) $request->input('customer_reference', '')),
            'gateway_reference' => trim((string) $request->input('gateway_reference', '')),
            'note' => trim((string) $request->input('note', '')),
        ];

        $errors = Validator::validate($payload, [
            'invoice_id' => 'required|integer',
            'billing_payment_method_id' => 'required|integer',
            'amount' => 'required|numeric',
            'payer_name' => 'nullable|max:150',
            'payer_email' => 'nullable|email|max:150',
            'customer_reference' => 'nullable|max:150',
            'gateway_reference' => 'nullable|max:150',
            'note' => 'nullable|max:2000',
        ]);

        if ((float) $payload['amount'] <= 0) {
            $errors['amount'][] = 'Payment amount must be greater than zero.';
        }

        $invoice = (new BillingInvoice())->findForCompany($invoiceId, $companyId);
        if ($invoice === null) {
            throw new HttpException(404, 'Billing invoice not found.');
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the payment submission and try again.'));
            $this->redirectToBillingPath($returnTo);
        }

        try {
            $submissionId = (new BillingPaymentService())->submitInvoicePayment(
                $invoiceId,
                [
                    'billing_payment_method_id' => (int) $payload['billing_payment_method_id'],
                    'amount' => (float) $payload['amount'],
                    'payer_name' => $payload['payer_name'],
                    'payer_email' => $payload['payer_email'],
                    'customer_reference' => $payload['customer_reference'],
                    'gateway_reference' => $payload['gateway_reference'],
                    'note' => $payload['note'],
                ],
                $request->file('proof'),
                Auth::id()
            );
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirectToBillingPath($returnTo);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'submit_billing_payment',
            entityType: 'billing_payment_submission',
            entityId: $submissionId,
            description: 'Submitted a billing payment review request for invoice ' . (string) ($invoice['invoice_number'] ?? ('#' . $invoiceId)) . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Payment submitted for platform review.');
        $this->redirectToBillingPath($returnTo);
    }

    public function startCheckout(Request $request): void
    {
        $companyId = current_company_id();
        if ($companyId === null) {
            Session::flash('error', 'A company workspace is required for billing payments.');
            $this->redirect('dashboard');
        }

        $invoiceId = (int) $request->input('invoice_id', 0);
        $returnTo = (string) $request->input('return_to', 'billing');
        $payload = [
            'invoice_id' => (string) $invoiceId,
            'billing_payment_method_id' => trim((string) $request->input('billing_payment_method_id', '')),
            'amount' => trim((string) $request->input('amount', '0')),
            'payer_name' => trim((string) $request->input('payer_name', '')),
            'payer_email' => trim((string) $request->input('payer_email', '')),
            'payer_phone' => trim((string) $request->input('payer_phone', '')),
            'return_to' => $returnTo,
        ];

        $errors = Validator::validate($payload, [
            'invoice_id' => 'required|integer',
            'billing_payment_method_id' => 'required|integer',
            'amount' => 'required|numeric',
            'payer_name' => 'nullable|max:150',
            'payer_email' => 'required|email|max:150',
            'payer_phone' => 'nullable|max:50',
        ]);

        if ((float) $payload['amount'] <= 0) {
            $errors['amount'][] = 'Payment amount must be greater than zero.';
        }

        $invoice = (new BillingInvoice())->findForCompany($invoiceId, $companyId);
        if ($invoice === null) {
            throw new HttpException(404, 'Billing invoice not found.');
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the secure checkout fields and try again.'));
            $this->redirectToBillingPath($returnTo);
        }

        try {
            $checkout = (new BillingPaymentService())->initializeHostedCheckout(
                $invoiceId,
                [
                    'billing_payment_method_id' => (int) $payload['billing_payment_method_id'],
                    'amount' => (float) $payload['amount'],
                    'payer_name' => $payload['payer_name'],
                    'payer_email' => $payload['payer_email'],
                    'payer_phone' => $payload['payer_phone'],
                    'return_to' => $payload['return_to'],
                ],
                Auth::id()
            );
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirectToBillingPath($returnTo);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'start_billing_checkout',
            entityType: 'billing_invoice',
            entityId: $invoiceId,
            description: 'Started hosted checkout for invoice ' . (string) ($invoice['invoice_number'] ?? ('#' . $invoiceId)) . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $authorizationUrl = trim((string) ($checkout['authorization_url'] ?? ''));
        if ($authorizationUrl === '') {
            Session::flash('error', 'The payment gateway did not return a checkout URL.');
            $this->redirectToBillingPath($returnTo);
        }

        header('Location: ' . $authorizationUrl);
        exit;
    }

    public function paymentCallback(Request $request): void
    {
        $reference = trim((string) $request->query('reference', $request->query('trxref', '')));
        $redirectPath = 'billing';

        try {
            $result = (new BillingPaymentService())->verifyHostedCheckout($reference);
            $redirectPath = (string) ($result['return_to'] ?? $redirectPath);

            if (!empty($result['company_id'])) {
                (new BillingOperationsService())->syncCompany((int) $result['company_id'], false, true);
            }

            $message = match ((string) ($result['status'] ?? 'pending')) {
                'success' => !empty($result['already_processed'])
                    ? 'Your payment was already verified for this invoice.'
                    : (!empty($result['already_settled'])
                        ? 'The invoice is already settled. Hosted checkout completed without posting another payment.'
                        : 'Your payment was verified successfully.'),
                'failed' => (string) ($result['gateway_message'] ?? 'The payment was not completed successfully.'),
                default => (string) ($result['gateway_message'] ?? 'The payment is still pending confirmation.'),
            };

            Session::flash((string) ($result['status'] ?? 'pending') === 'success' ? 'success' : 'error', $message);
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
        }

        $this->redirectToBillingPath($redirectPath);
    }

    public function paystackWebhook(Request $request): void
    {
        $rawPayload = (string) file_get_contents('php://input');
        $signature = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');

        try {
            $result = (new BillingPaymentService())->handlePaystackWebhook($rawPayload, $signature);
            if (!empty($result['company_id'])) {
                (new BillingOperationsService())->syncCompany((int) $result['company_id'], false, true);
            }

            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(['ok' => true, 'result' => $result]);
            return;
        } catch (Throwable $exception) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => $exception->getMessage()]);
            return;
        }
    }

    public function updateSettings(Request $request): void
    {
        $companyId = current_company_id();
        if ($companyId === null) {
            Session::flash('error', 'Billing settings require a company workspace.');
            $this->redirect('dashboard');
        }

        $payload = [
            'billing_contact_name' => trim((string) $request->input('billing_contact_name', '')),
            'billing_contact_email' => trim((string) $request->input('billing_contact_email', '')),
            'billing_contact_phone' => trim((string) $request->input('billing_contact_phone', '')),
            'billing_address' => trim((string) $request->input('billing_address', '')),
            'billing_tax_number' => trim((string) $request->input('billing_tax_number', '')),
            'billing_notification_emails' => trim((string) $request->input('billing_notification_emails', '')),
            'billing_notes' => trim((string) $request->input('billing_notes', '')),
        ];

        $errors = Validator::validate($payload, [
            'billing_contact_name' => 'nullable|max:150',
            'billing_contact_email' => 'nullable|email|max:150',
            'billing_contact_phone' => 'nullable|max:50',
            'billing_address' => 'nullable|max:255',
            'billing_tax_number' => 'nullable|max:100',
            'billing_notification_emails' => 'nullable|max:500',
            'billing_notes' => 'nullable|max:1000',
        ]);

        foreach ($this->invalidEmailsCsv($payload['billing_notification_emails']) as $invalidEmail) {
            $errors['billing_notification_emails'][] = 'Invalid notification email: ' . $invalidEmail;
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the billing settings fields and try again.'));
            $this->redirect('billing');
        }

        (new Setting())->saveMany([
            'billing_contact_name' => ['value' => $payload['billing_contact_name'], 'type' => 'string'],
            'billing_contact_email' => ['value' => $payload['billing_contact_email'], 'type' => 'string'],
            'billing_contact_phone' => ['value' => $payload['billing_contact_phone'], 'type' => 'string'],
            'billing_address' => ['value' => $payload['billing_address'], 'type' => 'string'],
            'billing_tax_number' => ['value' => $payload['billing_tax_number'], 'type' => 'string'],
            'billing_notification_emails' => ['value' => $payload['billing_notification_emails'], 'type' => 'string'],
            'billing_notes' => ['value' => $payload['billing_notes'], 'type' => 'string'],
        ], $companyId);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_billing_settings',
            entityType: 'settings',
            entityId: null,
            description: 'Updated tenant billing contacts and notification settings.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Billing settings updated successfully.');
        $this->redirect('billing');
    }

    private function billingSettings(): array
    {
        $fullName = trim((string) ((current_user()['first_name'] ?? '') . ' ' . (current_user()['last_name'] ?? '')));
        $businessEmail = (string) setting_value('business_email', current_user()['email'] ?? '');
        $businessPhone = (string) setting_value('business_phone', '');
        $businessAddress = (string) setting_value('business_address', '');
        $companyName = (string) setting_value('business_name', config('app.name', 'NovaPOS'));

        return [
            'billing_contact_name' => (string) setting_value('billing_contact_name', $fullName !== '' ? $fullName : ($companyName . ' Billing')),
            'billing_contact_email' => (string) setting_value('billing_contact_email', $businessEmail),
            'billing_contact_phone' => (string) setting_value('billing_contact_phone', $businessPhone),
            'billing_address' => (string) setting_value('billing_address', $businessAddress),
            'billing_tax_number' => (string) setting_value('billing_tax_number', ''),
            'billing_notification_emails' => (string) setting_value('billing_notification_emails', $businessEmail),
            'billing_notes' => (string) setting_value('billing_notes', ''),
        ];
    }

    private function billingSchemaMessage(): string
    {
        return 'Billing features are unavailable until database/migrations/013_billing_management_support.sql is applied.';
    }

    private function paymentSchemaMessage(): string
    {
        return 'Billing payment methods are unavailable until database/migrations/014_billing_payment_methods_support.sql is applied.';
    }

    private function invalidEmailsCsv(string $csv): array
    {
        $emails = array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', $csv)
        ));

        return array_values(array_filter(
            $emails,
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ));
    }

    private function firstError(array $errors, string $fallback): string
    {
        foreach ($errors as $messages) {
            if (is_array($messages) && $messages !== []) {
                return (string) $messages[0];
            }
        }

        return $fallback;
    }

    private function redirectToBillingPath(string $requestedPath, string $fallback = 'billing'): never
    {
        $normalizedPath = ltrim(trim($requestedPath), '/');
        if ($normalizedPath === '' || !str_starts_with($normalizedPath, 'billing')) {
            $normalizedPath = $fallback;
        }

        $this->redirect($normalizedPath);
    }
}
