<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\User;

class OperationsSmsService
{
    public function dailySummaryEnabled(?int $companyId = null): bool
    {
        if (!(new SmsService())->configured()) {
            return false;
        }

        $companyId ??= current_company_id();
        if ($companyId === null || $companyId <= 0) {
            return false;
        }

        return filter_var((string) platform_setting_value('platform_sms_daily_summary_enabled', 'false'), FILTER_VALIDATE_BOOLEAN)
            && filter_var((string) setting_value_for_company('email_daily_summary_enabled', 'true', $companyId), FILTER_VALIDATE_BOOLEAN);
    }

    public function sendDailySummary(array $context, ?int $branchId = null, ?int $companyId = null): int
    {
        $companyId ??= current_company_id();
        if ($companyId === null || $companyId <= 0 || !$this->dailySummaryEnabled($companyId)) {
            return 0;
        }

        $company = (new Company())->find($companyId) ?? [];
        $branchName = trim((string) ($context['branch_name'] ?? 'All branches'));
        $reportDate = (string) ($context['report_date'] ?? date('Y-m-d'));
        $brandName = trim((string) ($company['name'] ?? setting_value_for_company('business_name', config('app.name', 'NovaPOS'), $companyId)));
        $message = sprintf(
            '%s daily summary for %s on %s: %d sales, revenue %s, expenses %s, open credit %s, low stock %d.',
            $brandName !== '' ? $brandName : (string) config('app.name', 'NovaPOS'),
            $branchName,
            $reportDate,
            (int) ($context['daily_sales_count'] ?? 0),
            format_money((float) ($context['daily_revenue'] ?? 0), (string) setting_value_for_company('currency', default_currency_code(), $companyId)),
            format_money((float) ($context['daily_expenses'] ?? 0), (string) setting_value_for_company('currency', default_currency_code(), $companyId)),
            format_money((float) ($context['outstanding_credit'] ?? 0), (string) setting_value_for_company('currency', default_currency_code(), $companyId)),
            (int) ($context['low_stock_count'] ?? 0)
        );

        return (new SmsService())->sendMany($this->notificationRecipients($branchId, $companyId), $message, $companyId);
    }

    private function notificationRecipients(?int $branchId = null, ?int $companyId = null): array
    {
        $companyId ??= current_company_id();
        if ($companyId === null || $companyId <= 0) {
            return [];
        }

        $recipients = [];
        $company = (new Company())->find($companyId);
        $companyPhone = trim((string) ($company['phone'] ?? setting_value_for_company('business_phone', '', $companyId)));
        if ($companyPhone !== '') {
            $recipients[] = ['phone' => $companyPhone];
        }

        $billingPhone = trim((string) setting_value_for_company('billing_contact_phone', '', $companyId));
        if ($billingPhone !== '') {
            $recipients[] = ['phone' => $billingPhone];
        }

        foreach ((new User())->phoneNotificationRecipients($branchId, $companyId) as $user) {
            $phone = trim((string) ($user['phone'] ?? ''));
            if ($phone === '') {
                continue;
            }

            $recipients[] = [
                'phone' => $phone,
                'user_id' => (int) ($user['id'] ?? 0),
            ];
        }

        return $recipients;
    }
}
