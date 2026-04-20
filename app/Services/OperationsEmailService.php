<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class OperationsEmailService
{
    public function lowStockAlertsEnabled(?int $companyId = null): bool
    {
        return filter_var((string) setting_value_for_company('email_low_stock_alerts_enabled', 'true', $companyId), FILTER_VALIDATE_BOOLEAN);
    }

    public function dailySummaryEnabled(?int $companyId = null): bool
    {
        return filter_var((string) setting_value_for_company('email_daily_summary_enabled', 'true', $companyId), FILTER_VALIDATE_BOOLEAN);
    }

    public function sendLowStockAlert(array $context, ?int $branchId = null, ?int $companyId = null): int
    {
        $companyId ??= current_company_id();
        if ($companyId === null || $companyId <= 0 || !$this->lowStockAlertsEnabled($companyId)) {
            return 0;
        }

        $recipients = $this->notificationRecipients($branchId, $companyId);
        if ($recipients === []) {
            return 0;
        }

        $brandName = (string) setting_value_for_company('business_name', config('app.name'), $companyId);
        $branchName = trim((string) ($context['branch_name'] ?? 'Primary branch'));
        $productName = (string) ($context['product_name'] ?? 'Product');
        $sku = trim((string) ($context['sku'] ?? ''));
        $quantity = number_format((float) ($context['quantity_on_hand'] ?? 0), 2);
        $threshold = number_format((float) ($context['threshold'] ?? 0), 2);
        $inventoryLink = absolute_url('inventory/show?id=' . (int) ($context['product_id'] ?? 0));

        $subject = $brandName . ' low stock alert: ' . $productName;
        $htmlBody = '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 12px;">Low stock alert</h2>
                <p><strong>' . e($productName) . '</strong> has reached the low stock threshold for ' . e($branchName) . '.</p>
                <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
                    <tr><td style="padding: 8px 0; color: #6b7280;">SKU</td><td style="padding: 8px 0;"><strong>' . e($sku !== '' ? $sku : 'N/A') . '</strong></td></tr>
                    <tr><td style="padding: 8px 0; color: #6b7280;">On hand</td><td style="padding: 8px 0;"><strong>' . e($quantity) . '</strong></td></tr>
                    <tr><td style="padding: 8px 0; color: #6b7280;">Threshold</td><td style="padding: 8px 0;"><strong>' . e($threshold) . '</strong></td></tr>
                </table>
                <p>
                    <a href="' . e($inventoryLink) . '" style="display:inline-block;padding:12px 18px;background:#2872A1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                        Review Inventory
                    </a>
                </p>
            </div>';
        $textBody = "Low stock alert\n\n{$productName} is at {$quantity} units in {$branchName}. Threshold: {$threshold}.\nReview inventory: {$inventoryLink}";

        $mailService = new MailService();

        return $mailService->sendMany($recipients, $subject, $htmlBody, $textBody, $mailService->settings($companyId));
    }

    public function sendDailySalesSummary(array $context, ?int $branchId = null, ?int $companyId = null): int
    {
        $companyId ??= current_company_id();
        if ($companyId === null || $companyId <= 0 || !$this->dailySummaryEnabled($companyId)) {
            return 0;
        }

        $recipients = $this->notificationRecipients($branchId, $companyId);
        if ($recipients === []) {
            return 0;
        }

        $brandName = (string) setting_value_for_company('business_name', config('app.name'), $companyId);
        $currency = normalize_billing_currency(
            (string) setting_value_for_company('currency', default_currency_code(), $companyId),
            default_currency_code()
        );
        $branchName = trim((string) ($context['branch_name'] ?? 'Primary branch'));
        $reportDate = (string) ($context['report_date'] ?? date('Y-m-d'));
        $dailySales = (int) ($context['daily_sales_count'] ?? 0);
        $dailyRevenue = format_money((float) ($context['daily_revenue'] ?? 0), $currency);
        $dailyExpenses = format_money((float) ($context['daily_expenses'] ?? 0), $currency);
        $outstandingCredit = format_money((float) ($context['outstanding_credit'] ?? 0), $currency);
        $customersOnCredit = (int) ($context['customers_on_credit'] ?? 0);
        $lowStockCount = (int) ($context['low_stock_count'] ?? 0);
        $topProducts = array_slice((array) ($context['top_products'] ?? []), 0, 5);
        $reportLink = absolute_url('dashboard');

        $topProductRows = '';
        foreach ($topProducts as $product) {
            $topProductRows .= '<tr>
                <td style="padding: 8px 0; border-top: 1px solid #e5e7eb;">' . e((string) ($product['product_name'] ?? 'Product')) . '</td>
                <td style="padding: 8px 0; border-top: 1px solid #e5e7eb; text-align:right;"><strong>' . e(number_format((float) ($product['quantity_sold'] ?? 0), 2)) . '</strong></td>
            </tr>';
        }

        if ($topProductRows === '') {
            $topProductRows = '<tr><td colspan="2" style="padding: 8px 0; color: #6b7280;">No sales recorded today.</td></tr>';
        }

        $subject = $brandName . ' daily sales summary - ' . $branchName;
        $htmlBody = '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 12px;">Daily sales summary</h2>
                <p>Summary for <strong>' . e($branchName) . '</strong> on ' . e($reportDate) . '.</p>
                <table style="border-collapse: collapse; width: 100%; margin: 16px 0;">
                    <tr><td style="padding: 8px 0; color: #6b7280;">Completed sales</td><td style="padding: 8px 0;"><strong>' . e((string) $dailySales) . '</strong></td></tr>
                    <tr><td style="padding: 8px 0; color: #6b7280;">Revenue</td><td style="padding: 8px 0;"><strong>' . e($dailyRevenue) . '</strong></td></tr>
                    <tr><td style="padding: 8px 0; color: #6b7280;">Expenses</td><td style="padding: 8px 0;"><strong>' . e($dailyExpenses) . '</strong></td></tr>
                    <tr><td style="padding: 8px 0; color: #6b7280;">Open credit balance</td><td style="padding: 8px 0;"><strong>' . e($outstandingCredit) . '</strong> across ' . e((string) $customersOnCredit) . ' customers</td></tr>
                    <tr><td style="padding: 8px 0; color: #6b7280;">Low stock alerts</td><td style="padding: 8px 0;"><strong>' . e((string) $lowStockCount) . '</strong></td></tr>
                </table>
                <h3 style="margin: 20px 0 8px;">Top products today</h3>
                <table style="border-collapse: collapse; width: 100%;">' . $topProductRows . '</table>
                <p style="margin-top: 18px;">
                    <a href="' . e($reportLink) . '" style="display:inline-block;padding:12px 18px;background:#2872A1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                        Open Dashboard
                    </a>
                </p>
            </div>';
        $textBody = "Daily sales summary for {$branchName} on {$reportDate}\n\nCompleted sales: {$dailySales}\nRevenue: {$dailyRevenue}\nExpenses: {$dailyExpenses}\nOpen credit balance: {$outstandingCredit} across {$customersOnCredit} customers\nLow stock alerts: {$lowStockCount}\nDashboard: {$reportLink}";

        $mailService = new MailService();

        return $mailService->sendMany($recipients, $subject, $htmlBody, $textBody, $mailService->settings($companyId));
    }

    public function sendDailySummary(array $context, ?int $branchId = null, ?int $companyId = null): int
    {
        return $this->sendDailySalesSummary($context, $branchId, $companyId);
    }

    private function notificationRecipients(?int $branchId = null, ?int $companyId = null): array
    {
        $recipients = [];
        $scope = (string) setting_value_for_company('ops_email_recipient_scope', 'business_and_team', $companyId);
        $businessEmail = trim((string) setting_value_for_company('business_email', '', $companyId));
        $includeBusiness = in_array($scope, ['business', 'business_and_team'], true);
        $includeTeam = in_array($scope, ['team', 'business_and_team'], true);

        if ($includeBusiness && filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = [
                'email' => $businessEmail,
                'name' => (string) setting_value_for_company('business_name', config('app.name'), $companyId),
            ];
        }

        if ($includeTeam) {
            foreach ((new User())->notificationRecipients($branchId, $companyId) as $user) {
                $email = trim((string) ($user['email'] ?? ''));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $recipients[] = [
                    'email' => $email,
                    'name' => trim((string) ($user['full_name'] ?? $email)),
                ];
            }
        }

        foreach ($this->additionalRecipients($companyId) as $email) {
            $recipients[] = [
                'email' => $email,
                'name' => $email,
            ];
        }

        $deduped = [];
        foreach ($recipients as $recipient) {
            $deduped[strtolower((string) $recipient['email'])] = $recipient;
        }

        return array_values($deduped);
    }

    private function additionalRecipients(?int $companyId = null): array
    {
        $csv = (string) setting_value_for_company('ops_email_additional_recipients', '', $companyId);
        $emails = array_filter(array_map(
            static fn (string $email): string => trim($email),
            explode(',', $csv)
        ));

        return array_values(array_filter(
            $emails,
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ));
    }
}
