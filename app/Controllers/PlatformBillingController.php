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
use App\Models\BillingPaymentMethod;
use App\Models\BillingGatewayTransaction;
use App\Models\BillingPaymentSubmission;
use App\Models\BillingPlan;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Services\BillingOperationsService;
use App\Services\BillingPaymentService;
use Throwable;

class PlatformBillingController extends Controller
{
    public function index(Request $request): void
    {
        $planModel = new BillingPlan();
        $subscriptionModel = new CompanySubscription();
        $invoiceModel = new BillingInvoice();
        $paymentMethodModel = new BillingPaymentMethod();
        $billingReady = $planModel->schemaReady();
        $paymentsReady = $paymentMethodModel->schemaReady();
        $billingOps = new BillingOperationsService();

        if ($billingReady) {
            $billingOps->runCycle(issueDueInvoices: false, sendNotifications: false);
        }

        $this->render('platform/billing', [
            'title' => 'Billing Desk',
            'breadcrumbs' => ['Platform Admin', 'Billing'],
            'billingReady' => $billingReady,
            'paymentsReady' => $paymentsReady,
            'billingSchemaMessage' => $this->billingSchemaMessage(),
            'paymentSchemaMessage' => $this->paymentSchemaMessage(),
            'platformSettings' => $billingOps->platformDeskSettings(),
            'planSummary' => $billingReady ? $planModel->summary() : [],
            'subscriptionSummary' => $billingReady ? $subscriptionModel->summary() : [],
            'invoiceSummary' => $billingReady ? $invoiceModel->summary() : [],
            'plans' => $billingReady ? $planModel->all() : [],
            'subscriptions' => $billingReady ? $subscriptionModel->platformList() : [],
            'recentInvoices' => $billingReady ? $invoiceModel->recent(20) : [],
            'paymentMethods' => $paymentsReady ? $paymentMethodModel->all() : [],
            'recentGatewayTransactions' => (new BillingGatewayTransaction())->schemaReady() ? (new BillingGatewayTransaction())->recent(20) : [],
            'pendingPaymentSubmissions' => $paymentsReady ? (new BillingPaymentSubmission())->pendingList(20) : [],
            'billingCurrencies' => billing_currency_options(),
        ], 'platform');
    }

    public function updateSettings(Request $request): void
    {
        if (!$this->billingSchemaReady()) {
            Session::flash('error', $this->billingSchemaMessage());
            $this->redirect('platform/billing');
        }

        $payload = [
            'sender_name' => trim((string) $request->input('sender_name', '')),
            'sender_email' => trim((string) $request->input('sender_email', '')),
            'support_email' => trim((string) $request->input('support_email', '')),
            'invoice_due_days' => trim((string) $request->input('invoice_due_days', '7')),
            'grace_days' => trim((string) $request->input('grace_days', '7')),
            'auto_suspend_days' => trim((string) $request->input('auto_suspend_days', '14')),
            'payment_instructions' => trim((string) $request->input('payment_instructions', '')),
            'invoice_footer' => trim((string) $request->input('invoice_footer', '')),
            'notify_invoice_issued' => $request->boolean('notify_invoice_issued') ? 1 : 0,
            'notify_overdue' => $request->boolean('notify_overdue') ? 1 : 0,
            'notify_suspended' => $request->boolean('notify_suspended') ? 1 : 0,
            'gateway_enabled' => $request->boolean('gateway_enabled') ? 1 : 0,
            'gateway_provider' => trim((string) $request->input('gateway_provider', 'paystack')),
            'gateway_public_key' => trim((string) $request->input('gateway_public_key', '')),
            'gateway_secret_key' => trim((string) $request->input('gateway_secret_key', '')),
            'gateway_channels' => $request->input('gateway_channels', []),
        ];

        $errors = Validator::validate($payload, [
            'sender_name' => 'required|min:2|max:150',
            'sender_email' => 'nullable|email|max:150',
            'support_email' => 'nullable|email|max:150',
            'invoice_due_days' => 'required|integer',
            'grace_days' => 'required|integer',
            'auto_suspend_days' => 'required|integer',
            'payment_instructions' => 'nullable|max:2000',
            'invoice_footer' => 'nullable|max:1000',
            'gateway_provider' => 'required|in:paystack',
            'gateway_public_key' => 'nullable|max:255',
            'gateway_secret_key' => 'nullable|max:255',
        ]);

        foreach (['invoice_due_days', 'grace_days', 'auto_suspend_days'] as $field) {
            if ((int) $payload[$field] < 0) {
                $errors[$field][] = 'Timing values must be zero or greater.';
            }
        }

        if (!empty($payload['gateway_enabled']) && trim($payload['gateway_secret_key']) === '') {
            $errors['gateway_secret_key'][] = 'Enter the Paystack secret key before enabling hosted checkout.';
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the billing settings and try again.'));
            $this->redirect('platform/billing');
        }

        (new BillingOperationsService())->savePlatformSettings($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_platform_billing_settings',
            entityType: 'settings',
            entityId: null,
            description: 'Updated platform billing automation and invoice settings.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Platform billing settings updated successfully.');
        $this->redirect('platform/billing');
    }

    public function runCycle(Request $request): void
    {
        if (!$this->billingSchemaReady()) {
            Session::flash('error', $this->billingSchemaMessage());
            $this->redirect('platform/billing');
        }

        $summary = (new BillingOperationsService())->runCycle(issueDueInvoices: true, sendNotifications: true);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'run_billing_cycle',
            entityType: 'billing',
            entityId: null,
            description: 'Ran the platform billing cycle across company subscriptions.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash(
            'success',
            sprintf(
                'Billing cycle processed %d companies, issued %d invoices, synced %d subscription states, sent %d emails, and created %d in-app notifications.',
                (int) ($summary['companies_processed'] ?? 0),
                (int) ($summary['invoices_created'] ?? 0),
                (int) ($summary['statuses_synced'] ?? 0),
                (int) ($summary['emails_sent'] ?? 0),
                (int) ($summary['notifications_created'] ?? 0)
            )
        );
        $this->redirect('platform/billing');
    }

    public function showInvoice(Request $request): void
    {
        if (!$this->billingSchemaReady()) {
            throw new HttpException(404, 'Billing is unavailable.');
        }

        $invoiceId = (int) $request->query('id', 0);
        $invoiceData = (new BillingOperationsService())->invoiceViewData($invoiceId);
        if ($invoiceData === null) {
            throw new HttpException(404, 'Billing invoice not found.');
        }

        $invoice = $invoiceData['invoice'];

        $this->render('billing/show', [
            'title' => 'Invoice ' . (string) ($invoice['invoice_number'] ?? ('#' . $invoiceId)),
            'breadcrumbs' => ['Platform Admin', 'Billing', (string) ($invoice['invoice_number'] ?? 'Invoice')],
            'invoice' => $invoice,
            'company' => $invoiceData['company'],
            'subscription' => $invoiceData['subscription'],
            'payments' => $invoiceData['payments'],
            'paymentMethods' => $invoiceData['paymentMethods'] ?? [],
            'paymentSubmissions' => $invoiceData['paymentSubmissions'] ?? [],
            'paymentsReady' => (bool) ($invoiceData['paymentsReady'] ?? false),
            'billingProfile' => $invoiceData['billingProfile'],
            'platformSettings' => $invoiceData['platformSettings'],
            'backPath' => 'platform/companies/show?id=' . (int) ($invoice['company_id'] ?? 0),
            'backLabel' => 'Back to Company',
            'isPlatformView' => true,
        ], 'platform');
    }

    public function createPlan(Request $request): void
    {
        $returnTo = (string) $request->input('return_to', 'platform/billing');
        if (!$this->billingSchemaReady()) {
            Session::flash('error', $this->billingSchemaMessage());
            $this->redirectToReturnPath($returnTo);
        }

        $payload = [
            'name' => trim((string) $request->input('name', '')),
            'description' => trim((string) $request->input('description', '')),
            'billing_cycle' => trim((string) $request->input('billing_cycle', 'monthly')),
            'price' => trim((string) $request->input('price', '0')),
            'currency' => normalize_billing_currency((string) $request->input('currency', default_currency_code()), default_currency_code()),
            'trial_days' => trim((string) $request->input('trial_days', '0')),
            'max_branches' => trim((string) $request->input('max_branches', '')),
            'max_users' => trim((string) $request->input('max_users', '')),
            'max_products' => trim((string) $request->input('max_products', '')),
            'max_monthly_sales' => trim((string) $request->input('max_monthly_sales', '')),
            'features' => trim((string) $request->input('features', '')),
            'status' => trim((string) $request->input('status', 'active')),
            'sort_order' => trim((string) $request->input('sort_order', '0')),
            'is_featured' => $request->boolean('is_featured') ? 1 : 0,
            'is_default' => $request->boolean('is_default') ? 1 : 0,
        ];

        $errors = Validator::validate($payload, [
            'name' => 'required|min:2|max:120',
            'description' => 'nullable|max:255',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly,custom',
            'price' => 'required|numeric',
            'currency' => 'required|max:10',
            'trial_days' => 'required|integer',
            'max_branches' => 'nullable|integer',
            'max_users' => 'nullable|integer',
            'max_products' => 'nullable|integer',
            'max_monthly_sales' => 'nullable|integer',
            'features' => 'nullable|max:3000',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'required|integer',
        ]);

        if ((float) $payload['price'] < 0) {
            $errors['price'][] = 'Plan price must be zero or greater.';
        }

        if ((int) $payload['trial_days'] < 0) {
            $errors['trial_days'][] = 'Trial days must be zero or greater.';
        }

        foreach (['max_branches', 'max_users', 'max_products', 'max_monthly_sales'] as $field) {
            if ($payload[$field] !== '' && (int) $payload[$field] <= 0) {
                $errors[$field][] = 'Limits must be greater than zero or left blank for unlimited.';
            }
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the plan details and try again.'));
            $this->redirectToReturnPath($returnTo);
        }

        $planId = (new BillingPlan())->createPlan($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create_billing_plan',
            entityType: 'billing_plan',
            entityId: $planId,
            description: 'Created billing plan ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Billing plan created successfully.');
        $this->redirectToReturnPath($returnTo);
    }

    public function updatePlan(Request $request): void
    {
        $returnTo = (string) $request->input('return_to', 'platform/billing');
        if (!$this->billingSchemaReady()) {
            Session::flash('error', $this->billingSchemaMessage());
            $this->redirectToReturnPath($returnTo);
        }

        $planId = (int) $request->input('plan_id', 0);
        $plan = (new BillingPlan())->find($planId);
        if ($plan === null) {
            throw new HttpException(404, 'Billing plan not found.');
        }

        $payload = [
            'name' => trim((string) $request->input('name', '')),
            'description' => trim((string) $request->input('description', '')),
            'billing_cycle' => trim((string) $request->input('billing_cycle', 'monthly')),
            'price' => trim((string) $request->input('price', '0')),
            'currency' => normalize_billing_currency((string) $request->input('currency', default_currency_code()), default_currency_code()),
            'trial_days' => trim((string) $request->input('trial_days', '0')),
            'max_branches' => trim((string) $request->input('max_branches', '')),
            'max_users' => trim((string) $request->input('max_users', '')),
            'max_products' => trim((string) $request->input('max_products', '')),
            'max_monthly_sales' => trim((string) $request->input('max_monthly_sales', '')),
            'features' => trim((string) $request->input('features', '')),
            'status' => trim((string) $request->input('status', 'active')),
            'sort_order' => trim((string) $request->input('sort_order', '0')),
            'is_featured' => $request->boolean('is_featured') ? 1 : 0,
            'is_default' => $request->boolean('is_default') ? 1 : 0,
        ];

        $errors = Validator::validate($payload, [
            'name' => 'required|min:2|max:120',
            'description' => 'nullable|max:255',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly,custom',
            'price' => 'required|numeric',
            'currency' => 'required|max:10',
            'trial_days' => 'required|integer',
            'max_branches' => 'nullable|integer',
            'max_users' => 'nullable|integer',
            'max_products' => 'nullable|integer',
            'max_monthly_sales' => 'nullable|integer',
            'features' => 'nullable|max:3000',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'required|integer',
        ]);

        if ((float) $payload['price'] < 0) {
            $errors['price'][] = 'Plan price must be zero or greater.';
        }

        if ((int) $payload['trial_days'] < 0) {
            $errors['trial_days'][] = 'Trial days must be zero or greater.';
        }

        foreach (['max_branches', 'max_users', 'max_products', 'max_monthly_sales'] as $field) {
            if ($payload[$field] !== '' && (int) $payload[$field] <= 0) {
                $errors[$field][] = 'Limits must be greater than zero or left blank for unlimited.';
            }
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the plan details and try again.'));
            $this->redirectToReturnPath($returnTo);
        }

        (new BillingPlan())->updatePlan($planId, $payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_billing_plan',
            entityType: 'billing_plan',
            entityId: $planId,
            description: 'Updated billing plan ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Billing plan updated successfully.');
        $this->redirectToReturnPath($returnTo);
    }

    public function updateSubscription(Request $request): void
    {
        $companyId = (int) $request->input('company_id', 0);
        $returnTo = (string) $request->input('return_to', 'platform/companies/show?id=' . $companyId);
        if (!$this->billingSchemaReady()) {
            Session::flash('error', $this->billingSchemaMessage());
            $this->redirectToReturnPath($returnTo, 'platform/billing');
        }

        $payload = [
            'company_id' => (string) $companyId,
            'billing_plan_id' => trim((string) $request->input('billing_plan_id', '')),
            'status' => trim((string) $request->input('status', 'trialing')),
            'amount' => trim((string) $request->input('amount', '0')),
            'currency' => normalize_billing_currency((string) $request->input('currency', default_currency_code()), default_currency_code()),
            'billing_cycle' => trim((string) $request->input('billing_cycle', 'monthly')),
            'trial_ends_at' => trim((string) $request->input('trial_ends_at', '')),
            'current_period_start' => trim((string) $request->input('current_period_start', '')),
            'current_period_end' => trim((string) $request->input('current_period_end', '')),
            'next_invoice_at' => trim((string) $request->input('next_invoice_at', '')),
            'grace_ends_at' => trim((string) $request->input('grace_ends_at', '')),
            'max_branches' => trim((string) $request->input('max_branches', '')),
            'max_users' => trim((string) $request->input('max_users', '')),
            'max_products' => trim((string) $request->input('max_products', '')),
            'max_monthly_sales' => trim((string) $request->input('max_monthly_sales', '')),
            'notes' => trim((string) $request->input('notes', '')),
            'auto_renew' => $request->boolean('auto_renew') ? 1 : 0,
        ];

        $errors = Validator::validate($payload, [
            'company_id' => 'required|integer',
            'billing_plan_id' => 'required|integer',
            'status' => 'required|in:trialing,active,past_due,suspended,cancelled',
            'amount' => 'required|numeric',
            'currency' => 'required|max:10',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly,custom',
            'trial_ends_at' => 'nullable|max:40',
            'current_period_start' => 'nullable|max:40',
            'current_period_end' => 'nullable|max:40',
            'next_invoice_at' => 'nullable|max:40',
            'grace_ends_at' => 'nullable|max:40',
            'max_branches' => 'nullable|integer',
            'max_users' => 'nullable|integer',
            'max_products' => 'nullable|integer',
            'max_monthly_sales' => 'nullable|integer',
            'notes' => 'nullable|max:1000',
        ]);

        if ((float) $payload['amount'] < 0) {
            $errors['amount'][] = 'Subscription amount must be zero or greater.';
        }

        $company = (new Company())->find($companyId);
        if ($company === null) {
            throw new HttpException(404, 'Company not found.');
        }

        $plan = (new BillingPlan())->find((int) $payload['billing_plan_id']);
        if ($plan === null) {
            $errors['billing_plan_id'][] = 'Select a valid billing plan.';
        }

        $platformSettings = (new BillingOperationsService())->platformSettings();

        $trialEndsAt = $this->validatedDateTimeField($payload['trial_ends_at'], 'trial_ends_at', $errors);
        $currentPeriodStart = $this->validatedDateTimeField($payload['current_period_start'], 'current_period_start', $errors)
            ?? date('Y-m-d H:i:s');
        $currentPeriodEnd = $this->validatedDateTimeField($payload['current_period_end'], 'current_period_end', $errors)
            ?? $this->periodEndForCycle($currentPeriodStart, $payload['billing_cycle']);
        $nextInvoiceAt = $this->validatedDateTimeField($payload['next_invoice_at'], 'next_invoice_at', $errors)
            ?? $currentPeriodEnd;
        $graceEndsAt = $this->validatedDateTimeField($payload['grace_ends_at'], 'grace_ends_at', $errors);

        if ($payload['status'] === 'trialing' && $trialEndsAt === null && $plan !== null && (int) ($plan['trial_days'] ?? 0) > 0) {
            $trialEndsAt = date('Y-m-d H:i:s', strtotime($currentPeriodStart . ' +' . (int) $plan['trial_days'] . ' days'));
        }

        if ($payload['status'] === 'past_due' && $graceEndsAt === null) {
            $graceEndsAt = date('Y-m-d H:i:s', strtotime($currentPeriodEnd . ' +' . max(0, (int) $platformSettings['grace_days']) . ' days'));
        }

        if (strtotime($currentPeriodEnd) < strtotime($currentPeriodStart)) {
            $errors['current_period_end'][] = 'Current period end must be after the period start.';
        }

        foreach (['max_branches', 'max_users', 'max_products', 'max_monthly_sales'] as $field) {
            if ($payload[$field] !== '' && (int) $payload[$field] <= 0) {
                $errors[$field][] = 'Limits must be greater than zero or left blank for unlimited.';
            }
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the subscription fields and try again.'));
            $this->redirectToReturnPath($returnTo, 'platform/companies/show?id=' . $companyId);
        }

        try {
            $subscriptionId = (new CompanySubscription())->upsertForCompany($companyId, [
                'billing_plan_id' => (int) $payload['billing_plan_id'],
                'plan_name_snapshot' => (string) ($plan['name'] ?? 'Plan'),
                'billing_cycle' => $payload['billing_cycle'],
                'amount' => (float) $payload['amount'],
                'currency' => strtoupper($payload['currency']),
                'status' => $payload['status'],
                'trial_ends_at' => $trialEndsAt,
                'current_period_start' => $currentPeriodStart,
                'current_period_end' => $currentPeriodEnd,
                'next_invoice_at' => $nextInvoiceAt,
                'grace_ends_at' => $graceEndsAt,
                'max_branches' => $payload['max_branches'],
                'max_users' => $payload['max_users'],
                'max_products' => $payload['max_products'],
                'max_monthly_sales' => $payload['max_monthly_sales'],
                'notes' => $payload['notes'],
                'auto_renew' => $payload['auto_renew'],
            ]);
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirectToReturnPath($returnTo, 'platform/companies/show?id=' . $companyId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_company_subscription',
            entityType: 'company_subscription',
            entityId: $subscriptionId,
            description: 'Updated the billing subscription for ' . (string) ($company['name'] ?? 'company') . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Company subscription updated successfully.');
        $this->redirectToReturnPath($returnTo, 'platform/companies/show?id=' . $companyId);
    }

    public function createInvoice(Request $request): void
    {
        $companyId = (int) $request->input('company_id', 0);
        $returnTo = (string) $request->input('return_to', 'platform/companies/show?id=' . $companyId);
        if (!$this->billingSchemaReady()) {
            Session::flash('error', $this->billingSchemaMessage());
            $this->redirectToReturnPath($returnTo, 'platform/billing');
        }

        $payload = [
            'company_id' => (string) $companyId,
            'description' => trim((string) $request->input('description', '')),
            'subtotal' => trim((string) $request->input('subtotal', '0')),
            'tax_total' => trim((string) $request->input('tax_total', '0')),
            'due_at' => trim((string) $request->input('due_at', '')),
            'period_start' => trim((string) $request->input('period_start', '')),
            'period_end' => trim((string) $request->input('period_end', '')),
            'notes' => trim((string) $request->input('notes', '')),
        ];

        $errors = Validator::validate($payload, [
            'company_id' => 'required|integer',
            'description' => 'required|min:3|max:255',
            'subtotal' => 'required|numeric',
            'tax_total' => 'required|numeric',
            'due_at' => 'nullable|max:40',
            'period_start' => 'nullable|max:40',
            'period_end' => 'nullable|max:40',
            'notes' => 'nullable|max:1000',
        ]);

        if ((float) $payload['subtotal'] < 0) {
            $errors['subtotal'][] = 'Subtotal must be zero or greater.';
        }

        if ((float) $payload['tax_total'] < 0) {
            $errors['tax_total'][] = 'Tax total must be zero or greater.';
        }

        $company = (new Company())->find($companyId);
        if ($company === null) {
            throw new HttpException(404, 'Company not found.');
        }

        $billingOps = new BillingOperationsService();
        $platformSettings = $billingOps->platformSettings();
        $dueAt = $this->validatedDateTimeField($payload['due_at'], 'due_at', $errors)
            ?? date('Y-m-d H:i:s', strtotime('+' . max(0, (int) $platformSettings['invoice_due_days']) . ' days'));
        $periodStart = $this->validatedDateTimeField($payload['period_start'], 'period_start', $errors);
        $periodEnd = $this->validatedDateTimeField($payload['period_end'], 'period_end', $errors);

        if ($periodStart !== null && $periodEnd !== null && strtotime($periodEnd) < strtotime($periodStart)) {
            $errors['period_end'][] = 'Billing period end must be after the period start.';
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the invoice fields and try again.'));
            $this->redirectToReturnPath($returnTo, 'platform/companies/show?id=' . $companyId);
        }

        $subscription = (new CompanySubscription())->findByCompany($companyId);
        try {
            $invoiceId = (new BillingInvoice())->createInvoice([
                'company_id' => $companyId,
                'company_subscription_id' => $subscription['id'] ?? null,
                'currency' => normalize_billing_currency((string) ($subscription['currency'] ?? default_currency_code()), default_currency_code()),
                'description' => $payload['description'],
                'subtotal' => (float) $payload['subtotal'],
                'tax_total' => (float) $payload['tax_total'],
                'due_at' => $dueAt,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'notes' => $payload['notes'],
            ]);
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirectToReturnPath($returnTo, 'platform/companies/show?id=' . $companyId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create_billing_invoice',
            entityType: 'billing_invoice',
            entityId: $invoiceId,
            description: 'Created a billing invoice for ' . (string) ($company['name'] ?? 'company') . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $billingOps->notifyInvoiceIssued($invoiceId);
        $billingOps->syncCompany($companyId, false, false);

        Session::flash('success', 'Billing invoice issued successfully.');
        $this->redirectToReturnPath($returnTo, 'platform/companies/show?id=' . $companyId);
    }

    public function recordPayment(Request $request): void
    {
        $invoiceId = (int) $request->input('invoice_id', 0);
        $returnTo = (string) $request->input('return_to', 'platform/billing');
        if (!$this->billingSchemaReady()) {
            Session::flash('error', $this->billingSchemaMessage());
            $this->redirectToReturnPath($returnTo);
        }

        $payload = [
            'invoice_id' => (string) $invoiceId,
            'amount' => trim((string) $request->input('amount', '0')),
            'billing_payment_method_id' => trim((string) $request->input('billing_payment_method_id', '')),
            'payment_method' => trim((string) $request->input('payment_method', 'bank_transfer')),
            'reference' => trim((string) $request->input('reference', '')),
            'paid_at' => trim((string) $request->input('paid_at', '')),
            'notes' => trim((string) $request->input('notes', '')),
        ];

        $errors = Validator::validate($payload, [
            'invoice_id' => 'required|integer',
            'amount' => 'required|numeric',
            'billing_payment_method_id' => 'nullable|integer',
            'payment_method' => 'required|max:80',
            'reference' => 'nullable|max:150',
            'paid_at' => 'nullable|max:40',
            'notes' => 'nullable|max:255',
        ]);

        if ((float) $payload['amount'] <= 0) {
            $errors['amount'][] = 'Payment amount must be greater than zero.';
        }

        $invoiceModel = new BillingInvoice();
        $invoice = $invoiceModel->find($invoiceId);
        if ($invoice === null) {
            throw new HttpException(404, 'Billing invoice not found.');
        }

        $paymentsReady = (new BillingPaymentMethod())->schemaReady();
        $selectedMethod = null;
        if ($paymentsReady && $payload['billing_payment_method_id'] !== '') {
            $selectedMethod = (new BillingPaymentMethod())->find((int) $payload['billing_payment_method_id']);
            if ($selectedMethod === null || (string) ($selectedMethod['status'] ?? 'inactive') !== 'active') {
                $errors['billing_payment_method_id'][] = 'Select a valid active payment method.';
            } else {
                $payload['payment_method'] = (string) ($selectedMethod['slug'] ?? $payload['payment_method']);
            }
        } elseif (!in_array($payload['payment_method'], ['bank_transfer', 'card', 'cash', 'mobile_money', 'other'], true)) {
            $errors['payment_method'][] = 'Choose a valid payment method.';
        }

        $paidAt = $this->validatedDateTimeField($payload['paid_at'], 'paid_at', $errors) ?? date('Y-m-d H:i:s');
        if ((float) $payload['amount'] - (float) ($invoice['balance_due'] ?? 0) > 0.01) {
            $errors['amount'][] = 'Payment amount cannot exceed the invoice balance due.';
        }

        if (!empty($selectedMethod['requires_reference']) && $payload['reference'] === '') {
            $errors['reference'][] = 'A payment reference is required for this method.';
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the payment details and try again.'));
            $this->redirectToReturnPath($returnTo, 'platform/billing');
        }

        try {
            $invoiceModel->recordPayment($invoiceId, [
                'amount' => (float) $payload['amount'],
                'billing_payment_method_id' => $payload['billing_payment_method_id'] !== '' ? (int) $payload['billing_payment_method_id'] : null,
                'payment_method' => $payload['payment_method'],
                'reference' => $payload['reference'],
                'paid_at' => $paidAt,
                'notes' => $payload['notes'],
                'recorded_by_user_id' => Auth::id(),
            ]);
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirectToReturnPath($returnTo, 'platform/billing');
        }

        (new BillingOperationsService())->syncCompany((int) ($invoice['company_id'] ?? 0), false, true);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'record_billing_payment',
            entityType: 'billing_invoice',
            entityId: $invoiceId,
            description: 'Recorded a payment against billing invoice ' . (string) ($invoice['invoice_number'] ?? ('#' . $invoiceId)) . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Invoice payment recorded successfully.');
        $this->redirectToReturnPath($returnTo, 'platform/billing');
    }

    public function updateInvoiceStatus(Request $request): void
    {
        $invoiceId = (int) $request->input('invoice_id', 0);
        $returnTo = (string) $request->input('return_to', 'platform/billing');
        if (!$this->billingSchemaReady()) {
            Session::flash('error', $this->billingSchemaMessage());
            $this->redirectToReturnPath($returnTo);
        }

        $payload = [
            'invoice_id' => (string) $invoiceId,
            'status' => trim((string) $request->input('status', '')),
            'note' => trim((string) $request->input('note', '')),
        ];

        $errors = Validator::validate($payload, [
            'invoice_id' => 'required|integer',
            'status' => 'required|in:overdue,void',
            'note' => 'nullable|max:255',
        ]);

        $invoiceModel = new BillingInvoice();
        $invoice = $invoiceModel->find($invoiceId);
        if ($invoice === null) {
            throw new HttpException(404, 'Billing invoice not found.');
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Choose a valid invoice action.'));
            $this->redirectToReturnPath($returnTo, 'platform/billing');
        }

        try {
            $invoiceModel->updateInvoiceStatus($invoiceId, $payload['status'], $payload['note']);
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirectToReturnPath($returnTo, 'platform/billing');
        }

        (new BillingOperationsService())->syncCompany(
            (int) ($invoice['company_id'] ?? 0),
            false,
            $payload['status'] === 'overdue'
        );

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_billing_invoice_status',
            entityType: 'billing_invoice',
            entityId: $invoiceId,
            description: 'Changed billing invoice ' . (string) ($invoice['invoice_number'] ?? ('#' . $invoiceId)) . ' to ' . $payload['status'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Invoice status updated successfully.');
        $this->redirectToReturnPath($returnTo, 'platform/billing');
    }

    public function createPaymentMethod(Request $request): void
    {
        $returnTo = (string) $request->input('return_to', 'platform/billing');
        if (!$this->paymentSchemaReady()) {
            Session::flash('error', $this->paymentSchemaMessage());
            $this->redirectToReturnPath($returnTo);
        }

        $payload = $this->paymentMethodPayload($request);
        $errors = $this->validatePaymentMethodPayload($payload);

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the payment method details and try again.'));
            $this->redirectToReturnPath($returnTo);
        }

        $methodId = (new BillingPaymentMethod())->createMethod($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create_billing_payment_method',
            entityType: 'billing_payment_method',
            entityId: $methodId,
            description: 'Created billing payment method ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Payment method created successfully.');
        $this->redirectToReturnPath($returnTo);
    }

    public function updatePaymentMethod(Request $request): void
    {
        $returnTo = (string) $request->input('return_to', 'platform/billing');
        if (!$this->paymentSchemaReady()) {
            Session::flash('error', $this->paymentSchemaMessage());
            $this->redirectToReturnPath($returnTo);
        }

        $methodId = (int) $request->input('payment_method_id', 0);
        $method = (new BillingPaymentMethod())->find($methodId);
        if ($method === null) {
            throw new HttpException(404, 'Billing payment method not found.');
        }

        $payload = $this->paymentMethodPayload($request);
        $errors = $this->validatePaymentMethodPayload($payload);

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the payment method details and try again.'));
            $this->redirectToReturnPath($returnTo);
        }

        (new BillingPaymentMethod())->updateMethod($methodId, $payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_billing_payment_method',
            entityType: 'billing_payment_method',
            entityId: $methodId,
            description: 'Updated billing payment method ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Payment method updated successfully.');
        $this->redirectToReturnPath($returnTo);
    }

    public function approvePaymentSubmission(Request $request): void
    {
        $submissionId = (int) $request->input('submission_id', 0);
        $returnTo = (string) $request->input('return_to', 'platform/billing');
        if (!$this->paymentSchemaReady()) {
            Session::flash('error', $this->paymentSchemaMessage());
            $this->redirectToReturnPath($returnTo);
        }

        $reviewNote = trim((string) $request->input('review_note', ''));
        $errors = Validator::validate([
            'submission_id' => (string) $submissionId,
            'review_note' => $reviewNote,
        ], [
            'submission_id' => 'required|integer',
            'review_note' => 'nullable|max:255',
        ]);

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Review the payment decision and try again.'));
            $this->redirectToReturnPath($returnTo);
        }

        try {
            $result = (new BillingPaymentService())->approveSubmission($submissionId, (int) Auth::id(), $reviewNote);
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirectToReturnPath($returnTo);
        }

        (new BillingOperationsService())->syncCompany((int) ($result['company_id'] ?? 0), false, true);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'approve_billing_payment_submission',
            entityType: 'billing_payment_submission',
            entityId: $submissionId,
            description: 'Approved a submitted billing payment.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Payment submission approved and posted to the invoice.');
        $this->redirectToReturnPath($returnTo);
    }

    public function rejectPaymentSubmission(Request $request): void
    {
        $submissionId = (int) $request->input('submission_id', 0);
        $returnTo = (string) $request->input('return_to', 'platform/billing');
        if (!$this->paymentSchemaReady()) {
            Session::flash('error', $this->paymentSchemaMessage());
            $this->redirectToReturnPath($returnTo);
        }

        $reviewNote = trim((string) $request->input('review_note', ''));
        $errors = Validator::validate([
            'submission_id' => (string) $submissionId,
            'review_note' => $reviewNote,
        ], [
            'submission_id' => 'required|integer',
            'review_note' => 'required|min:3|max:255',
        ]);

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Explain why the submission is being rejected.'));
            $this->redirectToReturnPath($returnTo);
        }

        try {
            $result = (new BillingPaymentService())->rejectSubmission($submissionId, (int) Auth::id(), $reviewNote);
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirectToReturnPath($returnTo);
        }

        (new BillingOperationsService())->syncCompany((int) ($result['company_id'] ?? 0), false, false);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'reject_billing_payment_submission',
            entityType: 'billing_payment_submission',
            entityId: $submissionId,
            description: 'Rejected a submitted billing payment.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Payment submission rejected.');
        $this->redirectToReturnPath($returnTo);
    }

    private function billingSchemaReady(): bool
    {
        return (new BillingPlan())->schemaReady();
    }

    private function billingSchemaMessage(): string
    {
        return 'Billing is unavailable until database/migrations/013_billing_management_support.sql is applied.';
    }

    private function paymentSchemaReady(): bool
    {
        return (new BillingPaymentMethod())->schemaReady();
    }

    private function paymentSchemaMessage(): string
    {
        return 'Billing payment methods are unavailable until database/migrations/014_billing_payment_methods_support.sql is applied.';
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

    private function redirectToReturnPath(string $requestedPath, string $fallback = 'platform/billing'): never
    {
        $normalizedPath = ltrim(trim($requestedPath), '/');
        if ($normalizedPath === '' || !str_starts_with($normalizedPath, 'platform/')) {
            $normalizedPath = $fallback;
        }

        $this->redirect($normalizedPath);
    }

    private function validatedDateTimeField(string $value, string $field, array &$errors): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            $errors[$field][] = 'Enter a valid date and time.';
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function periodEndForCycle(string $startAt, string $cycle): string
    {
        $timestamp = strtotime($startAt);
        if ($timestamp === false) {
            return date('Y-m-d H:i:s', strtotime('+1 month'));
        }

        return match ($cycle) {
            'quarterly' => date('Y-m-d H:i:s', strtotime('+3 months', $timestamp)),
            'yearly' => date('Y-m-d H:i:s', strtotime('+1 year', $timestamp)),
            default => date('Y-m-d H:i:s', strtotime('+1 month', $timestamp)),
        };
    }

    private function paymentMethodPayload(Request $request): array
    {
        $integrationDriver = trim((string) $request->input('integration_driver', 'manual'));
        $gatewayChannels = $request->input('gateway_channels', []);

        return [
            'name' => trim((string) $request->input('name', '')),
            'type' => trim((string) $request->input('type', 'bank_transfer')),
            'integration_driver' => $integrationDriver,
            'description' => trim((string) $request->input('description', '')),
            'provider_name' => trim((string) $request->input('provider_name', '')),
            'account_name' => trim((string) $request->input('account_name', '')),
            'account_number' => trim((string) $request->input('account_number', '')),
            'checkout_url' => trim((string) $request->input('checkout_url', '')),
            'integration_config' => $integrationDriver === 'paystack'
                ? ['channels' => is_array($gatewayChannels) ? $gatewayChannels : explode(',', (string) $gatewayChannels)]
                : [],
            'supported_currencies' => $request->input('supported_currencies', []),
            'instructions' => trim((string) $request->input('instructions', '')),
            'requires_reference' => $request->boolean('requires_reference') ? 1 : 0,
            'requires_proof' => $request->boolean('requires_proof') ? 1 : 0,
            'is_default' => $request->boolean('is_default') ? 1 : 0,
            'status' => trim((string) $request->input('status', 'active')),
            'sort_order' => trim((string) $request->input('sort_order', '0')),
        ];
    }

    private function validatePaymentMethodPayload(array $payload): array
    {
        $errors = Validator::validate($payload, [
            'name' => 'required|min:2|max:120',
            'type' => 'required|in:bank_transfer,mobile_money,card,cash,other',
            'integration_driver' => 'required|in:manual,paystack',
            'description' => 'nullable|max:255',
            'provider_name' => 'nullable|max:150',
            'account_name' => 'nullable|max:150',
            'account_number' => 'nullable|max:150',
            'checkout_url' => 'nullable|max:255',
            'instructions' => 'nullable|max:2000',
            'status' => 'required|in:active,inactive',
            'sort_order' => 'required|integer',
        ]);

        if (trim((string) $payload['checkout_url']) !== '' && filter_var((string) $payload['checkout_url'], FILTER_VALIDATE_URL) === false) {
            $errors['checkout_url'][] = 'Enter a valid checkout URL.';
        }

        if ((string) ($payload['integration_driver'] ?? 'manual') === 'paystack') {
            if ((string) ($payload['type'] ?? 'card') === 'cash') {
                $errors['type'][] = 'Cash settlement cannot use hosted gateway checkout.';
            }

            if (!empty($payload['requires_proof'])) {
                $errors['requires_proof'][] = 'Hosted checkout methods do not require manual proof uploads.';
            }
        }

        return $errors;
    }
}
