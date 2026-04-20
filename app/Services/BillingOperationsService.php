<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BillingInvoice;
use App\Models\BillingPaymentSubmission;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\User;

class BillingOperationsService
{
    public function platformSettings(): array
    {
        $defaults = $this->defaultPlatformSettings();
        $platformCompanyId = $this->platformCompanyId();

        if ($platformCompanyId === null) {
            return $defaults;
        }

        $settings = new Setting();

        return [
            'sender_name' => trim((string) $settings->get('saas_billing_sender_name', $defaults['sender_name'], $platformCompanyId)),
            'sender_email' => trim((string) $settings->get('saas_billing_sender_email', $defaults['sender_email'], $platformCompanyId)),
            'support_email' => trim((string) $settings->get('saas_billing_support_email', $defaults['support_email'], $platformCompanyId)),
            'invoice_due_days' => max(0, (int) $settings->get('saas_billing_invoice_due_days', (string) $defaults['invoice_due_days'], $platformCompanyId)),
            'grace_days' => max(0, (int) $settings->get('saas_billing_grace_days', (string) $defaults['grace_days'], $platformCompanyId)),
            'auto_suspend_days' => max(0, (int) $settings->get('saas_billing_auto_suspend_days', (string) $defaults['auto_suspend_days'], $platformCompanyId)),
            'payment_instructions' => trim((string) $settings->get('saas_billing_payment_instructions', $defaults['payment_instructions'], $platformCompanyId)),
            'invoice_footer' => trim((string) $settings->get('saas_billing_invoice_footer', $defaults['invoice_footer'], $platformCompanyId)),
            'notify_invoice_issued' => $this->toBool($settings->get('saas_billing_notify_invoice_issued', $defaults['notify_invoice_issued'] ? 'true' : 'false', $platformCompanyId)),
            'notify_overdue' => $this->toBool($settings->get('saas_billing_notify_overdue', $defaults['notify_overdue'] ? 'true' : 'false', $platformCompanyId)),
            'notify_suspended' => $this->toBool($settings->get('saas_billing_notify_suspended', $defaults['notify_suspended'] ? 'true' : 'false', $platformCompanyId)),
            'gateway_enabled' => $this->toBool($settings->get('saas_gateway_enabled', $defaults['gateway_enabled'] ? 'true' : 'false', $platformCompanyId)),
            'gateway_provider' => trim((string) $settings->get('saas_gateway_provider', $defaults['gateway_provider'], $platformCompanyId)),
            'gateway_public_key' => trim((string) $settings->get('saas_gateway_public_key', $defaults['gateway_public_key'], $platformCompanyId)),
            'gateway_channels' => $this->gatewayChannels(
                (string) $settings->get('saas_gateway_channels', json_encode($defaults['gateway_channels']), $platformCompanyId)
            ),
        ];
    }

    public function platformDeskSettings(): array
    {
        $settings = $this->platformSettings();
        $platformCompanyId = $this->platformCompanyId();

        if ($platformCompanyId === null) {
            $settings['gateway_secret_key'] = '';
            return $settings;
        }

        $settings['gateway_secret_key'] = trim((string) (new Setting())->get('saas_gateway_secret_key', '', $platformCompanyId));

        return $settings;
    }

    public function savePlatformSettings(array $payload): void
    {
        $platformCompanyId = $this->platformCompanyId(true);
        if ($platformCompanyId === null) {
            throw new \RuntimeException('The platform billing workspace is not available.');
        }

        (new Setting())->saveMany([
            'saas_billing_sender_name' => ['value' => trim((string) ($payload['sender_name'] ?? '')), 'type' => 'string'],
            'saas_billing_sender_email' => ['value' => trim((string) ($payload['sender_email'] ?? '')), 'type' => 'string'],
            'saas_billing_support_email' => ['value' => trim((string) ($payload['support_email'] ?? '')), 'type' => 'string'],
            'saas_billing_invoice_due_days' => ['value' => (string) max(0, (int) ($payload['invoice_due_days'] ?? 0)), 'type' => 'integer'],
            'saas_billing_grace_days' => ['value' => (string) max(0, (int) ($payload['grace_days'] ?? 0)), 'type' => 'integer'],
            'saas_billing_auto_suspend_days' => ['value' => (string) max(0, (int) ($payload['auto_suspend_days'] ?? 0)), 'type' => 'integer'],
            'saas_billing_payment_instructions' => ['value' => trim((string) ($payload['payment_instructions'] ?? '')), 'type' => 'string'],
            'saas_billing_invoice_footer' => ['value' => trim((string) ($payload['invoice_footer'] ?? '')), 'type' => 'string'],
            'saas_billing_notify_invoice_issued' => ['value' => !empty($payload['notify_invoice_issued']) ? 'true' : 'false', 'type' => 'boolean'],
            'saas_billing_notify_overdue' => ['value' => !empty($payload['notify_overdue']) ? 'true' : 'false', 'type' => 'boolean'],
            'saas_billing_notify_suspended' => ['value' => !empty($payload['notify_suspended']) ? 'true' : 'false', 'type' => 'boolean'],
            'saas_gateway_enabled' => ['value' => !empty($payload['gateway_enabled']) ? 'true' : 'false', 'type' => 'boolean'],
            'saas_gateway_provider' => ['value' => trim((string) ($payload['gateway_provider'] ?? 'paystack')), 'type' => 'string'],
            'saas_gateway_public_key' => ['value' => trim((string) ($payload['gateway_public_key'] ?? '')), 'type' => 'string'],
            'saas_gateway_secret_key' => ['value' => trim((string) ($payload['gateway_secret_key'] ?? '')), 'type' => 'string'],
            'saas_gateway_channels' => ['value' => json_encode($this->gatewayChannels($payload['gateway_channels'] ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', 'type' => 'json'],
        ], $platformCompanyId);
    }

    public function invoiceViewData(int $invoiceId): ?array
    {
        $invoice = (new BillingInvoice())->find($invoiceId);
        if ($invoice === null) {
            return null;
        }

        $companyId = (int) ($invoice['company_id'] ?? 0);
        $company = (new Company())->findDetailed($companyId);

        return [
            'invoice' => $invoice,
            'company' => $company,
            'subscription' => (new CompanySubscription())->findByCompany($companyId),
            'payments' => (new BillingInvoice())->paymentsForInvoice($invoiceId),
            'paymentMethods' => (new BillingPaymentService())->availableMethodsForInvoice($invoice),
            'paymentSubmissions' => (new BillingPaymentSubmission())->schemaReady()
                ? (new BillingPaymentSubmission())->listForInvoice($invoiceId)
                : [],
            'gatewayTransactions' => (new BillingPaymentService())->gatewayTransactionsForInvoice($invoiceId),
            'paymentsReady' => (new BillingPaymentService())->schemaReady(),
            'billingProfile' => $this->tenantBillingProfile($companyId),
            'platformSettings' => $this->platformSettings(),
        ];
    }

    public function runCycle(?int $companyId = null, bool $issueDueInvoices = true, bool $sendNotifications = false): array
    {
        $summary = [
            'companies_processed' => 0,
            'invoices_created' => 0,
            'emails_sent' => 0,
            'notifications_created' => 0,
            'subscriptions_past_due' => 0,
            'subscriptions_suspended' => 0,
            'subscriptions_reactivated' => 0,
            'statuses_synced' => 0,
        ];

        if (!(new CompanySubscription())->schemaReady()) {
            return $summary;
        }

        $subscriptions = $companyId !== null
            ? array_filter([(new CompanySubscription())->findByCompany($companyId)])
            : (new CompanySubscription())->platformList();

        foreach ($subscriptions as $subscription) {
            $companyKey = (int) ($subscription['company_id'] ?? 0);
            if ($companyKey <= 0) {
                continue;
            }

            $companySummary = $this->syncCompany($companyKey, $issueDueInvoices, $sendNotifications);
            $summary['companies_processed']++;
            $summary['invoices_created'] += (int) ($companySummary['invoices_created'] ?? 0);
            $summary['emails_sent'] += (int) ($companySummary['emails_sent'] ?? 0);
            $summary['notifications_created'] += (int) ($companySummary['notifications_created'] ?? 0);
            $summary['subscriptions_past_due'] += (int) ($companySummary['subscriptions_past_due'] ?? 0);
            $summary['subscriptions_suspended'] += (int) ($companySummary['subscriptions_suspended'] ?? 0);
            $summary['subscriptions_reactivated'] += (int) ($companySummary['subscriptions_reactivated'] ?? 0);
            $summary['statuses_synced'] += (int) ($companySummary['statuses_synced'] ?? 0);
        }

        return $summary;
    }

    public function syncCompany(int $companyId, bool $issueDueInvoices = true, bool $sendNotifications = false): array
    {
        $summary = [
            'invoices_created' => 0,
            'emails_sent' => 0,
            'notifications_created' => 0,
            'subscriptions_past_due' => 0,
            'subscriptions_suspended' => 0,
            'subscriptions_reactivated' => 0,
            'statuses_synced' => 0,
        ];

        $subscriptionModel = new CompanySubscription();
        if (!$subscriptionModel->schemaReady()) {
            return $summary;
        }

        $invoiceModel = new BillingInvoice();
        $invoiceModel->syncDueStatuses($companyId);

        $subscription = $subscriptionModel->findByCompany($companyId);
        if ($subscription === null) {
            return $summary;
        }

        if ($issueDueInvoices) {
            $issueSummary = $this->issueDueInvoiceIfNeeded($subscription, $sendNotifications);
            $summary['invoices_created'] += (int) ($issueSummary['invoices_created'] ?? 0);
            $summary['emails_sent'] += (int) ($issueSummary['emails_sent'] ?? 0);
            $summary['notifications_created'] += (int) ($issueSummary['notifications_created'] ?? 0);
            $subscription = $subscriptionModel->findByCompany($companyId) ?? $subscription;
        }

        $stateSummary = $this->syncSubscriptionState($subscription, $sendNotifications);
        $summary['emails_sent'] += (int) ($stateSummary['emails_sent'] ?? 0);
        $summary['notifications_created'] += (int) ($stateSummary['notifications_created'] ?? 0);
        $summary['subscriptions_past_due'] += (int) ($stateSummary['subscriptions_past_due'] ?? 0);
        $summary['subscriptions_suspended'] += (int) ($stateSummary['subscriptions_suspended'] ?? 0);
        $summary['subscriptions_reactivated'] += (int) ($stateSummary['subscriptions_reactivated'] ?? 0);
        $summary['statuses_synced'] += (int) ($stateSummary['statuses_synced'] ?? 0);

        return $summary;
    }

    public function notifyInvoiceIssued(int $invoiceId): array
    {
        return $this->notifyInvoiceEvent($invoiceId);
    }

    public function tenantAlerts(
        int $companyId,
        ?array $subscription = null,
        ?array $usage = null,
        ?array $invoices = null
    ): array {
        $subscription ??= (new CompanySubscription())->findByCompany($companyId);
        if ($subscription === null) {
            return [];
        }

        $usage ??= (new BillingUsageService())->snapshot($companyId);
        $invoices ??= (new BillingInvoice())->recent(20, $companyId);

        $alerts = [];
        $overdueInvoices = array_values(array_filter(
            $invoices,
            static fn (array $invoice): bool => (string) ($invoice['status'] ?? '') === 'overdue'
        ));
        $openInvoices = array_values(array_filter(
            $invoices,
            static fn (array $invoice): bool => in_array((string) ($invoice['status'] ?? ''), ['issued', 'overdue'], true)
        ));
        $outstandingBalance = array_reduce($openInvoices, static function (float $carry, array $invoice): float {
            return $carry + (float) ($invoice['balance_due'] ?? 0);
        }, 0.0);

        $subscriptionStatus = (string) ($subscription['status'] ?? 'trialing');
        if ($subscriptionStatus === 'suspended') {
            $alerts[] = [
                'tone' => 'danger',
                'title' => 'Subscription suspended',
                'message' => 'Your workspace billing access is suspended. Clear overdue invoices and contact platform support to restore the account.',
            ];
        } elseif ($subscriptionStatus === 'past_due') {
            $alerts[] = [
                'tone' => 'warning',
                'title' => 'Subscription past due',
                'message' => 'There are overdue billing obligations on this workspace. Review open invoices and settle the balance before service restrictions escalate.',
            ];
        } elseif ($subscriptionStatus === 'trialing') {
            $trialEndsAt = trim((string) ($subscription['trial_ends_at'] ?? ''));
            if ($trialEndsAt !== '') {
                $daysLeft = (int) floor((strtotime($trialEndsAt) - time()) / 86400);
                if ($daysLeft <= 3) {
                    $alerts[] = [
                        'tone' => 'info',
                        'title' => 'Trial ending soon',
                        'message' => 'The current trial window ends on ' . date('M d, Y H:i', strtotime($trialEndsAt)) . '. Confirm billing details before the first invoice is due.',
                    ];
                }
            }
        }

        if ($overdueInvoices !== []) {
            $alerts[] = [
                'tone' => 'danger',
                'title' => count($overdueInvoices) . ' overdue invoice' . (count($overdueInvoices) === 1 ? '' : 's'),
                'message' => 'Outstanding overdue balance: ' . format_money($outstandingBalance, normalize_billing_currency((string) ($subscription['currency'] ?? default_currency_code()), default_currency_code())) . '.',
            ];
        } elseif ($outstandingBalance > 0) {
            $alerts[] = [
                'tone' => 'warning',
                'title' => 'Open billing balance',
                'message' => 'There are issued invoices awaiting payment totaling ' . format_money($outstandingBalance, normalize_billing_currency((string) ($subscription['currency'] ?? default_currency_code()), default_currency_code())) . '.',
            ];
        }

        foreach ([
            'max_branches' => ['used' => (int) ($usage['branch_count'] ?? 0), 'label' => 'branches'],
            'max_users' => ['used' => (int) ($usage['active_user_count'] ?? 0), 'label' => 'active users'],
            'max_products' => ['used' => (int) ($usage['product_count'] ?? 0), 'label' => 'products'],
            'max_monthly_sales' => ['used' => (int) ($usage['monthly_sale_count'] ?? 0), 'label' => 'monthly sales'],
        ] as $field => $meta) {
            $limit = isset($subscription[$field]) ? (int) $subscription[$field] : 0;
            if ($limit <= 0) {
                continue;
            }

            $used = $meta['used'];
            if ($used >= $limit) {
                $alerts[] = [
                    'tone' => 'danger',
                    'title' => ucfirst((string) $meta['label']) . ' limit reached',
                    'message' => 'Current usage is ' . $used . ' out of ' . $limit . '. Upgrade the plan or reduce usage to avoid operational friction.',
                ];
            } elseif ($used >= (int) ceil($limit * 0.85)) {
                $alerts[] = [
                    'tone' => 'warning',
                    'title' => ucfirst((string) $meta['label']) . ' nearing limit',
                    'message' => 'Current usage is ' . $used . ' out of ' . $limit . '. Review plan capacity before the workspace hits its cap.',
                ];
            }
        }

        return $alerts;
    }

    public function tenantBillingProfile(int $companyId): array
    {
        $settings = new Setting();
        $company = (new Company())->find($companyId) ?? [];
        $owner = (new Company())->primaryOwner($companyId);
        $companyName = trim((string) ($company['name'] ?? config('app.name', 'NovaPOS')));
        $ownerName = trim((string) (($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? '')));
        $ownerEmail = trim((string) ($owner['email'] ?? ''));

        return [
            'contact_name' => trim((string) $settings->get('billing_contact_name', $ownerName !== '' ? $ownerName : ($companyName . ' Billing'), $companyId)),
            'contact_email' => trim((string) $settings->get('billing_contact_email', $ownerEmail, $companyId)),
            'contact_phone' => trim((string) $settings->get('billing_contact_phone', (string) ($company['phone'] ?? ''), $companyId)),
            'address' => trim((string) $settings->get('billing_address', (string) ($company['address'] ?? ''), $companyId)),
            'tax_number' => trim((string) $settings->get('billing_tax_number', '', $companyId)),
            'notification_emails' => trim((string) $settings->get('billing_notification_emails', $ownerEmail, $companyId)),
            'notes' => trim((string) $settings->get('billing_notes', '', $companyId)),
        ];
    }

    private function defaultPlatformSettings(): array
    {
        $platformName = (string) config('mail.from_name', config('app.name', 'NovaPOS'));
        $platformEmail = (string) config('mail.from_address', '');
        $platformCompanyId = $this->platformCompanyId();

        if ($platformCompanyId !== null) {
            $settings = new Setting();
            $platformName = trim((string) $settings->get('business_name', $platformName, $platformCompanyId));
            $platformEmail = trim((string) $settings->get('business_email', $platformEmail, $platformCompanyId));
        }

        if ($platformName === '') {
            $platformName = (string) config('app.name', 'NovaPOS');
        }

        return [
            'sender_name' => $platformName,
            'sender_email' => $platformEmail,
            'support_email' => $platformEmail,
            'invoice_due_days' => max(0, (int) config('app.billing_default_due_days', 7)),
            'grace_days' => max(0, (int) config('app.billing_default_grace_days', 7)),
            'auto_suspend_days' => max(0, (int) config('app.billing_auto_suspend_days', 14)),
            'payment_instructions' => '',
            'invoice_footer' => 'Thank you for partnering with ' . $platformName . '.',
            'notify_invoice_issued' => true,
            'notify_overdue' => true,
            'notify_suspended' => true,
            'gateway_enabled' => false,
            'gateway_provider' => 'paystack',
            'gateway_public_key' => '',
            'gateway_channels' => [],
        ];
    }

    private function issueDueInvoiceIfNeeded(array $subscription, bool $sendNotifications): array
    {
        $summary = [
            'invoices_created' => 0,
            'emails_sent' => 0,
            'notifications_created' => 0,
        ];

        if (empty($subscription['auto_renew'])) {
            return $summary;
        }

        $status = (string) ($subscription['status'] ?? 'trialing');
        if (in_array($status, ['suspended', 'cancelled'], true)) {
            return $summary;
        }

        $nextInvoiceAt = trim((string) ($subscription['next_invoice_at'] ?? ''));
        if ($nextInvoiceAt === '' || strtotime($nextInvoiceAt) === false || strtotime($nextInvoiceAt) > time()) {
            return $summary;
        }

        if ((string) ($subscription['company_status'] ?? 'active') !== 'active') {
            return $summary;
        }

        $companyId = (int) ($subscription['company_id'] ?? 0);
        $subscriptionId = (int) ($subscription['id'] ?? 0);
        if ($companyId <= 0 || $subscriptionId <= 0) {
            return $summary;
        }

        $invoiceModel = new BillingInvoice();
        $subscriptionModel = new CompanySubscription();
        $settings = $this->platformSettings();
        $periodStart = $this->normalizedDateTime((string) ($subscription['current_period_start'] ?? '')) ?? date('Y-m-d H:i:s');
        $periodEnd = $this->normalizedDateTime((string) ($subscription['current_period_end'] ?? ''))
            ?? $this->periodEndForCycle($periodStart, (string) ($subscription['billing_cycle'] ?? 'monthly'));

        $existingInvoice = $invoiceModel->findBySubscriptionPeriod($subscriptionId, $periodStart, $periodEnd);
        if ($existingInvoice === null) {
            $invoiceId = $invoiceModel->createInvoice([
                'company_id' => $companyId,
                'company_subscription_id' => $subscriptionId,
                'currency' => normalize_billing_currency((string) ($subscription['currency'] ?? default_currency_code()), default_currency_code()),
                'description' => $this->subscriptionInvoiceDescription($subscription, $periodStart, $periodEnd),
                'subtotal' => (float) ($subscription['amount'] ?? 0),
                'tax_total' => 0,
                'due_at' => date('Y-m-d H:i:s', strtotime('+' . max(0, (int) $settings['invoice_due_days']) . ' days')),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'notes' => trim((string) ($settings['invoice_footer'] ?? '')),
            ]);

            $summary['invoices_created']++;

            if ($sendNotifications) {
                $noticeSummary = $this->notifyInvoiceIssued($invoiceId);
                $summary['emails_sent'] += (int) ($noticeSummary['emails_sent'] ?? 0);
                $summary['notifications_created'] += (int) ($noticeSummary['notifications_created'] ?? 0);
            }
        }

        $nextPeriodStart = $periodEnd;
        $nextPeriodEnd = $this->periodEndForCycle($nextPeriodStart, (string) ($subscription['billing_cycle'] ?? 'monthly'));

        $subscriptionModel->upsertForCompany($companyId, array_merge($subscription, [
            'billing_plan_id' => (int) ($subscription['billing_plan_id'] ?? 0),
            'plan_name_snapshot' => (string) ($subscription['plan_name_snapshot'] ?? $subscription['plan_name'] ?? 'Plan'),
            'billing_cycle' => (string) ($subscription['billing_cycle'] ?? 'monthly'),
            'amount' => (float) ($subscription['amount'] ?? 0),
            'currency' => normalize_billing_currency((string) ($subscription['currency'] ?? default_currency_code()), default_currency_code()),
            'status' => (string) ($subscription['status'] ?? 'trialing'),
            'trial_ends_at' => $subscription['trial_ends_at'] ?? null,
            'current_period_start' => $nextPeriodStart,
            'current_period_end' => $nextPeriodEnd,
            'next_invoice_at' => $nextPeriodEnd,
            'grace_ends_at' => $subscription['grace_ends_at'] ?? null,
            'max_branches' => $subscription['max_branches'] ?? null,
            'max_users' => $subscription['max_users'] ?? null,
            'max_products' => $subscription['max_products'] ?? null,
            'max_monthly_sales' => $subscription['max_monthly_sales'] ?? null,
            'auto_renew' => 1,
            'notes' => (string) ($subscription['notes'] ?? ''),
        ]));

        return $summary;
    }

    private function syncSubscriptionState(array $subscription, bool $sendNotifications): array
    {
        $summary = [
            'emails_sent' => 0,
            'notifications_created' => 0,
            'subscriptions_past_due' => 0,
            'subscriptions_suspended' => 0,
            'subscriptions_reactivated' => 0,
            'statuses_synced' => 0,
        ];

        $companyId = (int) ($subscription['company_id'] ?? 0);
        if ($companyId <= 0) {
            return $summary;
        }

        $currentStatus = (string) ($subscription['status'] ?? 'trialing');
        if ($currentStatus === 'cancelled') {
            return $summary;
        }

        $risk = (new BillingInvoice())->companyRiskSummary($companyId);
        $settings = $this->platformSettings();
        $trialEndsAt = trim((string) ($subscription['trial_ends_at'] ?? ''));
        $graceEndsAt = trim((string) ($subscription['grace_ends_at'] ?? ''));
        $newStatus = $currentStatus;
        $newGraceEndsAt = $graceEndsAt !== '' ? $graceEndsAt : null;

        if ((int) ($risk['overdue_invoice_count'] ?? 0) > 0) {
            $oldestOverdue = $this->normalizedDateTime((string) ($risk['oldest_overdue_due_at'] ?? '')) ?? date('Y-m-d H:i:s');
            if ($newGraceEndsAt === null) {
                $newGraceEndsAt = date('Y-m-d H:i:s', strtotime('+' . max(0, (int) $settings['grace_days']) . ' days', strtotime($oldestOverdue)));
            }

            if ($currentStatus === 'suspended') {
                $newStatus = 'suspended';
            } elseif ((int) $settings['auto_suspend_days'] > 0 && strtotime($oldestOverdue . ' +' . (int) $settings['auto_suspend_days'] . ' days') <= time()) {
                $newStatus = 'suspended';
            } else {
                $newStatus = 'past_due';
            }
        } else {
            $newGraceEndsAt = null;

            if ($currentStatus === 'suspended') {
                $newStatus = 'suspended';
            } elseif ($trialEndsAt !== '' && strtotime($trialEndsAt) !== false && strtotime($trialEndsAt) > time()) {
                $newStatus = 'trialing';
            } else {
                $newStatus = 'active';
            }
        }

        if ($newStatus === $currentStatus && $newGraceEndsAt === ($graceEndsAt !== '' ? $graceEndsAt : null)) {
            return $summary;
        }

        (new CompanySubscription())->upsertForCompany($companyId, array_merge($subscription, [
            'billing_plan_id' => (int) ($subscription['billing_plan_id'] ?? 0),
            'plan_name_snapshot' => (string) ($subscription['plan_name_snapshot'] ?? $subscription['plan_name'] ?? 'Plan'),
            'billing_cycle' => (string) ($subscription['billing_cycle'] ?? 'monthly'),
            'amount' => (float) ($subscription['amount'] ?? 0),
            'currency' => normalize_billing_currency((string) ($subscription['currency'] ?? default_currency_code()), default_currency_code()),
            'status' => $newStatus,
            'trial_ends_at' => $subscription['trial_ends_at'] ?? null,
            'current_period_start' => $subscription['current_period_start'] ?? null,
            'current_period_end' => $subscription['current_period_end'] ?? null,
            'next_invoice_at' => $subscription['next_invoice_at'] ?? null,
            'grace_ends_at' => $newGraceEndsAt,
            'max_branches' => $subscription['max_branches'] ?? null,
            'max_users' => $subscription['max_users'] ?? null,
            'max_products' => $subscription['max_products'] ?? null,
            'max_monthly_sales' => $subscription['max_monthly_sales'] ?? null,
            'auto_renew' => !empty($subscription['auto_renew']) ? 1 : 0,
            'notes' => (string) ($subscription['notes'] ?? ''),
        ]));

        $summary['statuses_synced'] = 1;

        if ($newStatus === 'past_due' && $currentStatus !== 'past_due') {
            $summary['subscriptions_past_due'] = 1;
        }

        if ($newStatus === 'suspended' && $currentStatus !== 'suspended') {
            $summary['subscriptions_suspended'] = 1;
        }

        if (in_array($currentStatus, ['past_due', 'trialing'], true) && $newStatus === 'active') {
            $summary['subscriptions_reactivated'] = 1;
        }

        if ($sendNotifications) {
            $noticeSummary = $this->notifySubscriptionStateChange($companyId, $subscription, $currentStatus, $newStatus);
            $summary['emails_sent'] += (int) ($noticeSummary['emails_sent'] ?? 0);
            $summary['notifications_created'] += (int) ($noticeSummary['notifications_created'] ?? 0);
        }

        return $summary;
    }

    private function notifyInvoiceEvent(int $invoiceId): array
    {
        $summary = ['emails_sent' => 0, 'notifications_created' => 0];
        $invoice = (new BillingInvoice())->find($invoiceId);
        if ($invoice === null) {
            return $summary;
        }

        $companyId = (int) ($invoice['company_id'] ?? 0);
        if ($companyId <= 0) {
            return $summary;
        }

        $platformSettings = $this->platformSettings();
        if (empty($platformSettings['notify_invoice_issued'])) {
            return $summary;
        }

        $company = (new Company())->find($companyId) ?? [];
        $profile = $this->tenantBillingProfile($companyId);
        $recipients = $this->billingRecipients($companyId);
        $mailSettings = $this->mailSettingsFromPlatform($platformSettings);
        $amountLabel = format_money((float) ($invoice['total'] ?? 0), normalize_billing_currency((string) ($invoice['currency'] ?? default_currency_code()), default_currency_code()));
        $invoiceUrl = absolute_url('billing/invoices/show?id=' . $invoiceId);
        $platformName = (string) config('app.name', 'NovaPOS');
        $subject = $platformName . ' invoice ' . (string) ($invoice['invoice_number'] ?? ('#' . $invoiceId));
        $paymentInstructions = trim((string) ($platformSettings['payment_instructions'] ?? ''));

        $htmlBody = '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 12px;">New billing invoice issued</h2>
                <p>Hello ' . e((string) ($profile['contact_name'] !== '' ? $profile['contact_name'] : ($company['name'] ?? 'team'))) . ',</p>
                <p>An invoice has been issued for <strong>' . e((string) ($company['name'] ?? 'your workspace')) . '</strong>.</p>
                <p>
                    Invoice: <strong>' . e((string) ($invoice['invoice_number'] ?? 'Invoice')) . '</strong><br>
                    Amount: <strong>' . e($amountLabel) . '</strong><br>
                    Due: <strong>' . e(trim((string) ($invoice['due_at'] ?? '')) !== '' ? date('M d, Y H:i', strtotime((string) $invoice['due_at'])) : 'Not scheduled') . '</strong>
                </p>
                <p>' . e((string) ($invoice['description'] ?? 'Subscription billing invoice')) . '</p>
                ' . ($paymentInstructions !== '' ? '<p><strong>Payment instructions:</strong><br>' . nl2br(e($paymentInstructions)) . '</p>' : '') . '
                <p>
                    <a href="' . e($invoiceUrl) . '" style="display:inline-block;padding:12px 18px;background:#2872A1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                        Open Invoice
                    </a>
                </p>
                <p>' . nl2br(e((string) ($platformSettings['invoice_footer'] ?? ''))) . '</p>
            </div>';
        $textBody = "New billing invoice issued\n\nInvoice: " . (string) ($invoice['invoice_number'] ?? 'Invoice') . "\nAmount: {$amountLabel}\nDue: " . (trim((string) ($invoice['due_at'] ?? '')) !== '' ? date('M d, Y H:i', strtotime((string) $invoice['due_at'])) : 'Not scheduled') . "\n\nOpen: {$invoiceUrl}";

        if ($recipients !== []) {
            $summary['emails_sent'] = $this->sendManyWithSettings($recipients, $subject, $htmlBody, $textBody, $mailSettings);
        }

        $summary['notifications_created'] = $this->notifyCompanyAdmins(
            $companyId,
            'billing',
            'Invoice issued: ' . (string) ($invoice['invoice_number'] ?? 'Invoice'),
            'A billing invoice for ' . $amountLabel . ' has been issued and is due ' . (trim((string) ($invoice['due_at'] ?? '')) !== '' ? date('M d, Y', strtotime((string) $invoice['due_at'])) : 'soon') . '.',
            'billing/invoices/show?id=' . $invoiceId
        );

        return $summary;
    }

    private function notifySubscriptionStateChange(int $companyId, array $subscription, string $oldStatus, string $newStatus): array
    {
        $summary = ['emails_sent' => 0, 'notifications_created' => 0];
        if ($newStatus === $oldStatus) {
            return $summary;
        }

        $platformSettings = $this->platformSettings();
        $company = (new Company())->find($companyId) ?? [];
        $recipients = $this->billingRecipients($companyId);
        $mailSettings = $this->mailSettingsFromPlatform($platformSettings);
        $workspaceUrl = absolute_url('billing');
        $profile = $this->tenantBillingProfile($companyId);

        if ($newStatus === 'past_due' && !empty($platformSettings['notify_overdue'])) {
            $risk = (new BillingInvoice())->companyRiskSummary($companyId);
            $balanceLabel = format_money((float) ($risk['outstanding_balance'] ?? 0), normalize_billing_currency((string) ($subscription['currency'] ?? default_currency_code()), default_currency_code()));
            $subject = (string) config('app.name', 'NovaPOS') . ' account past due';
            $htmlBody = '
                <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                    <h2 style="margin-bottom: 12px;">Your billing account is now past due</h2>
                    <p>Hello ' . e((string) ($profile['contact_name'] !== '' ? $profile['contact_name'] : ($company['name'] ?? 'team'))) . ',</p>
                    <p>The workspace for <strong>' . e((string) ($company['name'] ?? 'your business')) . '</strong> has overdue billing obligations totaling <strong>' . e($balanceLabel) . '</strong>.</p>
                    <p>Please review open invoices and settle the balance as soon as possible.</p>
                    <p><a href="' . e($workspaceUrl) . '" style="display:inline-block;padding:12px 18px;background:#B45309;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">Open Billing Workspace</a></p>
                </div>';
            $textBody = "Your billing account is now past due.\nOutstanding balance: {$balanceLabel}\nOpen billing workspace: {$workspaceUrl}";

            if ($recipients !== []) {
                $summary['emails_sent'] += $this->sendManyWithSettings($recipients, $subject, $htmlBody, $textBody, $mailSettings);
            }

            $summary['notifications_created'] += $this->notifyCompanyAdmins(
                $companyId,
                'billing',
                'Billing account past due',
                'Open invoices are overdue. Review the billing workspace and clear the balance before the account is suspended.',
                'billing'
            );
        }

        if ($newStatus === 'suspended' && !empty($platformSettings['notify_suspended'])) {
            $subject = (string) config('app.name', 'NovaPOS') . ' account suspended';
            $supportEmail = trim((string) ($platformSettings['support_email'] ?? ''));
            $htmlBody = '
                <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                    <h2 style="margin-bottom: 12px;">Your workspace billing status is suspended</h2>
                    <p>Hello ' . e((string) ($profile['contact_name'] !== '' ? $profile['contact_name'] : ($company['name'] ?? 'team'))) . ',</p>
                    <p>The workspace for <strong>' . e((string) ($company['name'] ?? 'your business')) . '</strong> has been suspended because overdue invoices were not resolved in time.</p>
                    <p>Clear the billing balance and contact support to restore access.' . ($supportEmail !== '' ? ' Support: ' . e($supportEmail) . '.' : '') . '</p>
                    <p><a href="' . e($workspaceUrl) . '" style="display:inline-block;padding:12px 18px;background:#B91C1C;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">Open Billing Workspace</a></p>
                </div>';
            $textBody = "Your workspace billing status is suspended.\nOpen billing workspace: {$workspaceUrl}" . ($supportEmail !== '' ? "\nSupport: {$supportEmail}" : '');

            if ($recipients !== []) {
                $summary['emails_sent'] += $this->sendManyWithSettings($recipients, $subject, $htmlBody, $textBody, $mailSettings);
            }

            $summary['notifications_created'] += $this->notifyCompanyAdmins(
                $companyId,
                'billing',
                'Workspace suspended for billing',
                'The workspace is suspended because overdue invoices were not resolved. Contact platform support after settling the balance.',
                'billing'
            );
        }

        if ($oldStatus === 'past_due' && $newStatus === 'active') {
            $summary['notifications_created'] += $this->notifyCompanyAdmins(
                $companyId,
                'billing',
                'Billing account restored',
                'The billing account is back in good standing. Open invoices are no longer overdue.',
                'billing'
            );
        }

        return $summary;
    }

    private function billingRecipients(int $companyId): array
    {
        $profile = $this->tenantBillingProfile($companyId);
        $owner = (new Company())->primaryOwner($companyId);
        $emails = [];

        if ($profile['contact_email'] !== '') {
            $emails[] = [
                'email' => $profile['contact_email'],
                'name' => $profile['contact_name'] !== '' ? $profile['contact_name'] : $profile['contact_email'],
            ];
        }

        foreach ($this->parseEmailsCsv((string) ($profile['notification_emails'] ?? '')) as $email) {
            $emails[] = ['email' => $email, 'name' => $email];
        }

        if ($owner !== null && trim((string) ($owner['email'] ?? '')) !== '') {
            $ownerName = trim((string) (($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? '')));
            $emails[] = [
                'email' => trim((string) $owner['email']),
                'name' => $ownerName !== '' ? $ownerName : trim((string) $owner['email']),
            ];
        }

        $unique = [];
        foreach ($emails as $recipient) {
            $email = strtolower(trim((string) ($recipient['email'] ?? '')));
            if ($email === '' || isset($unique[$email])) {
                continue;
            }

            $unique[$email] = [
                'email' => $email,
                'name' => trim((string) ($recipient['name'] ?? $email)),
            ];
        }

        return array_values($unique);
    }

    private function notifyCompanyAdmins(int $companyId, string $type, string $title, string $message, string $linkUrl): int
    {
        $count = 0;
        $notificationModel = new Notification();
        $users = (new User())->listUsers(null, $companyId);

        foreach ($users as $user) {
            if ((string) ($user['status'] ?? 'inactive') !== 'active') {
                continue;
            }

            if (!in_array((string) ($user['role_name'] ?? ''), ['Super Admin', 'Admin'], true)) {
                continue;
            }

            $notificationModel->createUserNotification(
                (int) $user['id'],
                !empty($user['branch_id']) ? (int) $user['branch_id'] : null,
                $type,
                $title,
                $message,
                $linkUrl,
                false
            );
            $count++;
        }

        return $count;
    }

    private function subscriptionInvoiceDescription(array $subscription, string $periodStart, string $periodEnd): string
    {
        $planName = (string) ($subscription['plan_name'] ?? $subscription['plan_name_snapshot'] ?? 'Subscription');

        return $planName . ' subscription billing for '
            . date('M d, Y', strtotime($periodStart))
            . ' to '
            . date('M d, Y', strtotime($periodEnd));
    }

    private function mailSettingsFromPlatform(array $platformSettings): array
    {
        $mailService = new MailService();

        return array_merge($mailService->globalSettings(), [
            'from_address' => trim((string) ($platformSettings['sender_email'] ?? '')) !== ''
                ? trim((string) $platformSettings['sender_email'])
                : (string) config('mail.from_address', ''),
            'from_name' => trim((string) ($platformSettings['sender_name'] ?? '')) !== ''
                ? trim((string) $platformSettings['sender_name'])
                : (string) config('mail.from_name', config('app.name', 'NovaPOS')),
        ]);
    }

    private function sendManyWithSettings(array $recipients, string $subject, string $htmlBody, string $textBody, array $settings): int
    {
        $mailService = new MailService();
        $sent = 0;

        foreach ($recipients as $recipient) {
            $email = trim((string) ($recipient['email'] ?? ''));
            if ($email === '') {
                continue;
            }

            if ($mailService->send(
                toEmail: $email,
                toName: trim((string) ($recipient['name'] ?? $email)),
                subject: $subject,
                htmlBody: $htmlBody,
                textBody: $textBody,
                settings: $settings
            )) {
                $sent++;
            }
        }

        return $sent;
    }

    private function parseEmailsCsv(string $csv): array
    {
        return array_values(array_unique(array_filter(array_map(
            static function (string $email): string {
                $email = strtolower(trim($email));
                return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
            },
            explode(',', $csv)
        ))));
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

    private function normalizedDateTime(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

    private function platformCompanyId(bool $ensure = false): ?int
    {
        $company = (new Company())->findBySlug((string) config('app.platform_internal_company_slug', 'platform-operations-internal'));
        if ($company !== null) {
            return (int) ($company['id'] ?? 0) ?: null;
        }

        if (!$ensure) {
            return null;
        }

        $workspace = (new WorkspaceProvisioner())->ensurePlatformWorkspace();

        return (int) ($workspace['company']['id'] ?? 0) ?: null;
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function gatewayChannels(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        $channels = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $channel): string => strtolower(trim((string) $channel)),
            $channels
        ))));
    }
}
