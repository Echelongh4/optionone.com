<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

class SystemHealthService
{
    public function snapshot(): array
    {
        $checks = [
            $this->webRootCheck(),
            $this->appUrlCheck(),
            $this->databaseCheck(),
            $this->mailCheck(),
            $this->thermalPrinterCheck(),
            $this->backupStorageCheck(),
            $this->exportCheck(),
            $this->securityCheck(),
            $this->restoreGuardCheck(),
            $this->demoDataCheck(),
            $this->businessProfileCheck(),
        ];

        return [
            'checks' => $checks,
            'healthy' => count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'healthy')),
            'warning' => count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'warning')),
            'critical' => count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'critical')),
        ];
    }

    private function databaseCheck(): array
    {
        try {
            $connection = Database::connection();
            $version = (string) ($connection->query('SELECT VERSION()')->fetchColumn() ?: 'Connected');

            return [
                'label' => 'Database',
                'status' => 'healthy',
                'value' => config('database.database', 'pos_system'),
                'detail' => 'Connected successfully. Version: ' . $version,
                'icon' => 'bi-database-check',
            ];
        } catch (Throwable $exception) {
            return [
                'label' => 'Database',
                'status' => 'critical',
                'value' => 'Unavailable',
                'detail' => 'Connection check failed: ' . $exception->getMessage(),
                'icon' => 'bi-database-x',
            ];
        }
    }

    private function webRootCheck(): array
    {
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $publicRoot = realpath(public_path()) ?: '';
        $healthy = $documentRoot !== '' && $publicRoot !== '' && strcasecmp($documentRoot, $publicRoot) === 0;

        return [
            'label' => 'Web Root',
            'status' => $healthy ? 'healthy' : 'critical',
            'value' => $documentRoot !== '' ? $documentRoot : 'Unknown',
            'detail' => $healthy
                ? 'Web server document root points to the public directory.'
                : 'Document root does not point to public/. Sensitive project files must not be served directly in production.',
            'icon' => $healthy ? 'bi-folder2-open' : 'bi-folder-x',
        ];
    }

    private function mailCheck(): array
    {
        $mailService = new MailService();
        $settings = $mailService->settings();
        $host = $settings['host'];
        $from = $settings['from_address'];
        $mailerPresent = class_exists(\PHPMailer\PHPMailer\PHPMailer::class);

        if ($mailerPresent && $mailService->configured()) {
            return [
                'label' => 'Mail Delivery',
                'status' => 'healthy',
                'value' => $host . ' -> ' . $from,
                'detail' => 'SMTP host, sender address, and runtime mail delivery are configured.',
                'icon' => 'bi-envelope-check',
            ];
        }

        return [
            'label' => 'Mail Delivery',
            'status' => 'warning',
            'value' => $host !== '' ? $host : 'Not configured',
            'detail' => $mailerPresent
                ? 'Save the SMTP host and sender address in Settings to enable password resets and summary emails.'
                : 'PHPMailer is unavailable. Run composer install to enable email delivery.',
            'icon' => 'bi-envelope-exclamation',
        ];
    }

    private function appUrlCheck(): array
    {
        $appUrl = trim((string) config('app.url', ''));
        $basePath = trim((string) config('app.base_path', ''));
        $parsed = $appUrl !== '' ? parse_url($appUrl) : false;
        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));
        $localHosts = ['localhost', '127.0.0.1', '::1'];
        $looksLocal = $host === ''
            || in_array($host, $localHosts, true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test');
        $secureUrl = $scheme === 'https';
        $healthy = !$looksLocal && $secureUrl;

        $detail = $appUrl === ''
            ? 'Set APP_URL to the final public URL before going live.'
            : sprintf(
                'APP_URL resolves to %s over %s.%s',
                $host !== '' ? $host : 'an unknown host',
                $scheme !== '' ? strtoupper($scheme) : 'an unknown scheme',
                $basePath !== '' ? ' Base path: ' . $basePath . '.' : ''
            );

        if ($looksLocal) {
            $detail .= ' Replace localhost or test hosts with the real public hostname before launch.';
        } elseif (!$secureUrl) {
            $detail .= ' Use an HTTPS URL in production.';
        }

        return [
            'label' => 'Application URL',
            'status' => $healthy ? 'healthy' : 'warning',
            'value' => $appUrl !== '' ? $appUrl : 'Not configured',
            'detail' => $detail,
            'icon' => $healthy ? 'bi-globe2' : 'bi-globe-americas',
        ];
    }

    private function backupStorageCheck(): array
    {
        $storageRoot = storage_path();
        $backupDirectory = storage_path('backups');
        $storageReady = is_dir($storageRoot) || @mkdir($storageRoot, 0775, true);
        $backupReady = is_dir($backupDirectory) || @mkdir($backupDirectory, 0775, true);
        $writable = $storageReady && $backupReady && is_writable($backupDirectory);
        $backups = (new DatabaseBackupService())->list();

        return [
            'label' => 'Backup Storage',
            'status' => $writable ? 'healthy' : 'critical',
            'value' => count($backups) . ' snapshots',
            'detail' => $writable
                ? 'Backup directory is writable at ' . str_replace(base_path() . DIRECTORY_SEPARATOR, '', $backupDirectory)
                : 'Backup directory is not writable. Check storage permissions before relying on restore points.',
            'icon' => $writable ? 'bi-hdd-stack' : 'bi-hdd-network',
        ];
    }

    private function thermalPrinterCheck(): array
    {
        $printer = new ThermalPrinterService();

        if (!$printer->enabled()) {
            return [
                'label' => 'Thermal Printer',
                'status' => 'warning',
                'value' => 'Disabled',
                'detail' => 'Direct receipt printing is disabled. Enable and configure a connector in Settings when a printer is ready.',
                'icon' => 'bi-printer',
            ];
        }

        if (!$printer->available()) {
            return [
                'label' => 'Thermal Printer',
                'status' => 'critical',
                'value' => 'Library unavailable',
                'detail' => 'ESC/POS support is missing. Keep the local printer package or install the dependency before using direct printing.',
                'icon' => 'bi-printer-fill',
            ];
        }

        if (!$printer->configured()) {
            return [
                'label' => 'Thermal Printer',
                'status' => 'warning',
                'value' => 'Configuration incomplete',
                'detail' => 'Provide a printer target or network host and port for the selected connector.',
                'icon' => 'bi-printer',
            ];
        }

        return [
            'label' => 'Thermal Printer',
            'status' => 'healthy',
            'value' => $printer->summary(),
            'detail' => 'Direct ESC/POS receipt printing is ready for POS receipts and test pages.',
            'icon' => 'bi-printer-fill',
        ];
    }

    private function exportCheck(): array
    {
        $spreadsheetReady = class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class);
        $pdfReady = class_exists(\Dompdf\Dompdf::class);

        return [
            'label' => 'Advanced Exports',
            'status' => $spreadsheetReady && $pdfReady ? 'healthy' : 'warning',
            'value' => $spreadsheetReady && $pdfReady ? 'Excel + PDF ready' : 'CSV only',
            'detail' => $spreadsheetReady && $pdfReady
                ? 'Composer export dependencies are available.'
                : 'Install PhpSpreadsheet and Dompdf through Composer to enable Excel and PDF downloads.',
            'icon' => 'bi-file-earmark-spreadsheet',
        ];
    }

    private function securityCheck(): array
    {
        $environment = (string) config('app.env', 'local');
        $debug = (bool) config('app.debug', true);
        $forceHttps = (bool) config('app.force_https', false);
        $status = (!$debug && $forceHttps) ? 'healthy' : 'warning';

        return [
            'label' => 'Runtime Security',
            'status' => $status,
            'value' => strtoupper($environment),
            'detail' => sprintf(
                'Debug is %s and HTTPS enforcement is %s.',
                $debug ? 'enabled' : 'disabled',
                $forceHttps ? 'enabled' : 'disabled'
            ),
            'icon' => 'bi-shield-lock',
        ];
    }

    private function restoreGuardCheck(): array
    {
        $restoreEnabled = (bool) config('app.allow_database_restore', false);

        return [
            'label' => 'Database Restore',
            'status' => $restoreEnabled ? 'warning' : 'healthy',
            'value' => $restoreEnabled ? 'Enabled' : 'Disabled',
            'detail' => $restoreEnabled
                ? 'In-app SQL restore is enabled. Keep this off in production and enable it only for controlled maintenance windows.'
                : 'In-app SQL restore is disabled by default.',
            'icon' => 'bi-arrow-repeat',
        ];
    }

    private function demoDataCheck(): array
    {
        $demoEmails = [
            'superadmin@novapos.test',
            'admin@novapos.test',
            'manager@novapos.test',
            'cashier@novapos.test',
        ];

        try {
            $placeholders = implode(', ', array_fill(0, count($demoEmails), '?'));
            $statement = Database::connection()->prepare(
                "SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND LOWER(email) IN ($placeholders)"
            );
            $statement->execute($demoEmails);
            $count = (int) $statement->fetchColumn();

            return [
                'label' => 'Demo Accounts',
                'status' => $count > 0 ? 'warning' : 'healthy',
                'value' => $count > 0 ? $count . ' detected' : 'None detected',
                'detail' => $count > 0
                    ? 'Bundled demo user records are still present. Remove or rotate them before production use.'
                    : 'No bundled demo user records were detected.',
                'icon' => $count > 0 ? 'bi-person-exclamation' : 'bi-person-check',
            ];
        } catch (Throwable $exception) {
            return [
                'label' => 'Demo Accounts',
                'status' => 'warning',
                'value' => 'Check unavailable',
                'detail' => 'Could not verify whether demo user records still exist: ' . $exception->getMessage(),
                'icon' => 'bi-person-exclamation',
            ];
        }
    }

    private function businessProfileCheck(): array
    {
        $fields = [
            'business_name' => (string) setting_value('business_name', config('app.name', 'NovaPOS')),
            'business_email' => (string) setting_value('business_email', ''),
            'business_phone' => (string) setting_value('business_phone', ''),
            'business_address' => (string) setting_value('business_address', ''),
            'currency' => (string) setting_value('currency', default_currency_code()),
        ];

        $completed = count(array_filter($fields, static fn (string $value): bool => trim($value) !== ''));
        $status = $completed >= 4 ? 'healthy' : 'warning';

        return [
            'label' => 'Business Profile',
            'status' => $status,
            'value' => $completed . '/' . count($fields) . ' fields ready',
            'detail' => $completed >= 4
                ? 'Core branding and receipt fields are configured.'
                : 'Complete business email, phone, address, and currency to strengthen receipts and notifications.',
            'icon' => 'bi-building',
        ];
    }
}
