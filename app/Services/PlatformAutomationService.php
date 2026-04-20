<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AutomationRun;
use App\Models\Company;
use App\Models\Dashboard;

class PlatformAutomationService
{
    public function settings(): array
    {
        return [
            'token' => trim((string) platform_setting_value('platform_automation_token', '')),
            'billing_cycle_enabled' => filter_var((string) platform_setting_value('platform_automation_billing_cycle_enabled', 'false'), FILTER_VALIDATE_BOOLEAN),
            'billing_cycle_time' => $this->normalizedTime((string) platform_setting_value('platform_automation_billing_cycle_time', '02:00')),
            'daily_summary_enabled' => filter_var((string) platform_setting_value('platform_automation_daily_summary_enabled', 'false'), FILTER_VALIDATE_BOOLEAN),
            'daily_summary_time' => $this->normalizedTime((string) platform_setting_value('platform_automation_daily_summary_time', '20:00')),
            'backup_enabled' => filter_var((string) platform_setting_value('platform_automation_backup_enabled', 'false'), FILTER_VALIDATE_BOOLEAN),
            'backup_time' => $this->normalizedTime((string) platform_setting_value('platform_automation_backup_time', '23:30')),
            'backup_retention_count' => max(3, (int) platform_setting_value('platform_automation_backup_retention_count', '14')),
            'backup_restore_kit_enabled' => filter_var((string) platform_setting_value('platform_automation_backup_restore_kit_enabled', 'true'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public function recentRuns(int $limit = 12): array
    {
        return (new AutomationRun())->recent($limit);
    }

    public function validToken(string $token): bool
    {
        $configuredToken = trim((string) ($this->settings()['token'] ?? ''));

        return $configuredToken !== '' && hash_equals($configuredToken, trim($token));
    }

    public function dispatchScheduled(?string $task = null): array
    {
        return $this->dispatch('scheduled', $task, null);
    }

    public function dispatchManual(string $task, ?int $createdByUserId = null): array
    {
        return $this->dispatch('manual', $task, $createdByUserId);
    }

    private function dispatch(string $triggerMode, ?string $task, ?int $createdByUserId): array
    {
        $settings = $this->settings();
        $tasks = $task !== null && trim($task) !== ''
            ? [trim($task)]
            : ['billing_cycle', 'daily_summary', 'backup_snapshot'];
        $results = [];
        $ran = 0;
        $skipped = 0;

        foreach ($tasks as $taskKey) {
            if (!in_array($taskKey, ['billing_cycle', 'daily_summary', 'backup_snapshot'], true)) {
                $results[$taskKey] = ['status' => 'failed', 'message' => 'Unknown automation task requested.'];
                continue;
            }

            if ($triggerMode === 'scheduled' && !$this->taskShouldRunNow($taskKey, $settings)) {
                $results[$taskKey] = ['status' => 'skipped', 'message' => 'Task is not yet due or automation is disabled.'];
                $skipped++;
                continue;
            }

            $results[$taskKey] = $this->runTask($taskKey, $triggerMode, $createdByUserId, $settings);
            if ((string) ($results[$taskKey]['status'] ?? '') === 'succeeded') {
                $ran++;
            }
        }

        return [
            'trigger_mode' => $triggerMode,
            'tasks_run' => $ran,
            'tasks_skipped' => $skipped,
            'results' => $results,
        ];
    }

    private function runTask(string $taskKey, string $triggerMode, ?int $createdByUserId, array $settings): array
    {
        $runModel = new AutomationRun();
        $runId = $runModel->start($taskKey, $triggerMode, null, $createdByUserId);

        try {
            $summary = match ($taskKey) {
                'billing_cycle' => $this->runBillingCycle(),
                'daily_summary' => $this->runDailySummaries(),
                'backup_snapshot' => $this->runBackupSnapshot($settings),
            };

            if ($runId > 0) {
                $runModel->complete($runId, $summary, (string) ($summary['message'] ?? 'Completed successfully.'));
            }

            return ['status' => 'succeeded', 'summary' => $summary];
        } catch (\Throwable $exception) {
            if ($runId > 0) {
                $runModel->fail($runId, $exception->getMessage());
            }

            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }
    }

    private function runBillingCycle(): array
    {
        $summary = (new BillingOperationsService())->runCycle(issueDueInvoices: true, sendNotifications: true);
        $summary['message'] = sprintf(
            'Processed %d companies and issued %d invoices.',
            (int) ($summary['companies_processed'] ?? 0),
            (int) ($summary['invoices_created'] ?? 0)
        );

        return $summary;
    }

    private function runDailySummaries(): array
    {
        $companies = (new Company())->platformList();
        $platformCompanyId = platform_company_id();
        $dashboard = new Dashboard();
        $emailService = new OperationsEmailService();
        $smsService = new OperationsSmsService();
        $summary = [
            'companies_processed' => 0,
            'emails_sent' => 0,
            'sms_sent' => 0,
        ];

        foreach ($companies as $company) {
            $companyId = (int) ($company['id'] ?? 0);
            if ($companyId <= 0 || $companyId === $platformCompanyId || (string) ($company['status'] ?? 'inactive') !== 'active') {
                continue;
            }

            $payload = $dashboard->dailySummaryPayload(null);
            $payload['branch_name'] = 'All branches';
            $payload['company_name'] = (string) ($company['name'] ?? 'Company');

            $summary['emails_sent'] += $emailService->sendDailySalesSummary($payload, null, $companyId);
            $summary['sms_sent'] += $smsService->sendDailySummary($payload, null, $companyId);
            $summary['companies_processed']++;
        }

        $summary['message'] = sprintf(
            'Processed daily summaries for %d companies.',
            (int) $summary['companies_processed']
        );

        return $summary;
    }

    private function runBackupSnapshot(array $settings): array
    {
        $backupService = new DatabaseBackupService();
        $backup = $backupService->create('scheduled-backup', false);
        $restoreKit = null;

        if (!empty($settings['backup_restore_kit_enabled'])) {
            $restoreKit = $backupService->createRestoreKit($backup['path'], $backup['name']);
        }

        $pruned = $backupService->prune((int) ($settings['backup_retention_count'] ?? 14));

        return [
            'backup_name' => (string) ($backup['name'] ?? ''),
            'restore_kit_name' => is_array($restoreKit) ? (string) ($restoreKit['name'] ?? '') : '',
            'pruned_backups' => $pruned,
            'message' => 'Created scheduled backup ' . (string) ($backup['name'] ?? 'snapshot') . '.',
        ];
    }

    private function taskShouldRunNow(string $taskKey, array $settings): bool
    {
        $enabled = match ($taskKey) {
            'billing_cycle' => (bool) ($settings['billing_cycle_enabled'] ?? false),
            'daily_summary' => (bool) ($settings['daily_summary_enabled'] ?? false),
            'backup_snapshot' => (bool) ($settings['backup_enabled'] ?? false),
        };

        if (!$enabled) {
            return false;
        }

        $timeLabel = match ($taskKey) {
            'billing_cycle' => (string) ($settings['billing_cycle_time'] ?? '02:00'),
            'daily_summary' => (string) ($settings['daily_summary_time'] ?? '20:00'),
            'backup_snapshot' => (string) ($settings['backup_time'] ?? '23:30'),
        };

        $timezone = new \DateTimeZone((string) config('app.timezone', 'UTC'));
        $now = new \DateTimeImmutable('now', $timezone);
        $scheduledAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', $now->format('Y-m-d') . ' ' . $timeLabel, $timezone);
        if (!$scheduledAt instanceof \DateTimeImmutable || $now < $scheduledAt) {
            return false;
        }

        $lastSuccess = (new AutomationRun())->latestSuccessfulAt($taskKey);
        if ($lastSuccess === null || trim($lastSuccess) === '') {
            return true;
        }

        $lastSuccessAt = new \DateTimeImmutable($lastSuccess, $timezone);

        return $lastSuccessAt < $scheduledAt;
    }

    private function normalizedTime(string $value, string $fallback = '00:00'): string
    {
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            [$hours, $minutes] = array_map('intval', explode(':', $value, 2));
            if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59) {
                return sprintf('%02d:%02d', $hours, $minutes);
            }
        }

        return $fallback;
    }
}
