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
use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Models\Tax;
use App\Services\DatabaseBackupService;
use App\Services\MailService;
use App\Services\SystemHealthService;
use App\Services\ThermalPrinterService;
use App\Services\UploadService;
use Throwable;

class SettingsController extends Controller
{
    public function index(Request $request): void
    {
        $this->renderIndex();
    }

    public function update(Request $request): void
    {
        $settingsModel = new Setting();
        $current = $this->settingsDefaults();
        $payload = [
            'business_name' => trim((string) $request->input('business_name', '')),
            'business_address' => trim((string) $request->input('business_address', '')),
            'business_phone' => trim((string) $request->input('business_phone', '')),
            'business_email' => trim((string) $request->input('business_email', '')),
            'currency' => trim((string) $request->input('currency', '')),
            'receipt_header' => trim((string) $request->input('receipt_header', '')),
            'receipt_footer' => trim((string) $request->input('receipt_footer', '')),
            'barcode_format' => trim((string) $request->input('barcode_format', 'CODE128')),
            'tax_default' => trim((string) $request->input('tax_default', '')),
            'multi_branch_enabled' => $request->boolean('multi_branch_enabled'),
            'email_low_stock_alerts_enabled' => $request->boolean('email_low_stock_alerts_enabled'),
            'email_daily_summary_enabled' => $request->boolean('email_daily_summary_enabled'),
            'ops_email_recipient_scope' => trim((string) $request->input('ops_email_recipient_scope', 'business_and_team')),
            'ops_email_additional_recipients' => trim((string) $request->input('ops_email_additional_recipients', '')),
            'mail_host' => trim((string) $request->input('mail_host', '')),
            'mail_port' => trim((string) $request->input('mail_port', '587')),
            'mail_username' => trim((string) $request->input('mail_username', '')),
            'mail_password' => (string) $current['mail_password'],
            'mail_encryption' => trim((string) $request->input('mail_encryption', 'tls')),
            'mail_from_address' => trim((string) $request->input('mail_from_address', '')),
            'mail_from_name' => trim((string) $request->input('mail_from_name', '')),
            'thermal_printer_enabled' => $request->boolean('thermal_printer_enabled'),
            'thermal_printer_connector' => trim((string) $request->input('thermal_printer_connector', 'windows')),
            'thermal_printer_target' => trim((string) $request->input('thermal_printer_target', '')),
            'thermal_printer_host' => trim((string) $request->input('thermal_printer_host', '')),
            'thermal_printer_port' => trim((string) $request->input('thermal_printer_port', '9100')),
            'business_logo_path' => $current['business_logo_path'],
        ];

        $submittedMailPassword = (string) $request->input('mail_password', '');
        if ($submittedMailPassword !== '') {
            $payload['mail_password'] = $submittedMailPassword;
        }

        if ($payload['mail_username'] === '') {
            $payload['mail_password'] = '';
        }

        $errors = Validator::validate($payload, [
            'business_name' => 'required|min:2|max:150',
            'business_address' => 'nullable|max:255',
            'business_phone' => 'nullable|max:50',
            'business_email' => 'nullable|email|max:150',
            'currency' => 'required|min:1|max:10',
            'receipt_header' => 'nullable|max:255',
            'receipt_footer' => 'nullable|max:255',
            'barcode_format' => 'required|in:CODE128,CODE39,EAN13,UPC',
            'ops_email_recipient_scope' => 'required|in:business,team,business_and_team',
            'ops_email_additional_recipients' => 'nullable|max:500',
            'mail_host' => 'nullable|max:255',
            'mail_port' => 'nullable|integer',
            'mail_username' => 'nullable|max:255',
            'mail_password' => 'nullable|max:255',
            'mail_encryption' => 'required|in:tls,ssl,none',
            'mail_from_address' => 'nullable|email|max:150',
            'mail_from_name' => 'nullable|max:150',
            'thermal_printer_connector' => 'required|in:windows,network,file',
            'thermal_printer_target' => 'nullable|max:255',
            'thermal_printer_host' => 'nullable|max:255',
            'thermal_printer_port' => 'nullable|integer',
        ]);

        foreach ($this->invalidRecipientEmails($payload['ops_email_additional_recipients']) as $invalidEmail) {
            $errors['ops_email_additional_recipients'][] = 'Invalid recipient email: ' . $invalidEmail;
        }

        if ($payload['mail_port'] !== '') {
            $mailPort = (int) $payload['mail_port'];
            if ($mailPort < 1 || $mailPort > 65535) {
                $errors['mail_port'][] = 'SMTP port must be between 1 and 65535.';
            }
        }

        if ($payload['mail_host'] !== '' && $payload['mail_from_address'] === '') {
            $errors['mail_from_address'][] = 'Enter the sender email address used for outgoing mail.';
        }

        if ($payload['mail_host'] === '' && $payload['mail_from_address'] !== '') {
            $errors['mail_host'][] = 'Enter the SMTP host to enable mail delivery.';
        }

        if ($payload['thermal_printer_enabled']) {
            if ($payload['thermal_printer_connector'] === 'network') {
                if ($payload['thermal_printer_host'] === '') {
                    $errors['thermal_printer_host'][] = 'Enter a printer IP address or hostname for network printing.';
                }

                $port = (int) $payload['thermal_printer_port'];
                if ($port < 1 || $port > 65535) {
                    $errors['thermal_printer_port'][] = 'Thermal printer port must be between 1 and 65535.';
                }
            } elseif ($payload['thermal_printer_target'] === '') {
                $errors['thermal_printer_target'][] = 'Enter a printer target for Windows or file printing.';
            }
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                return;
            }

            $payload['mail_password'] = '';

            $this->renderIndex([
                'settings' => $payload,
                'generalErrors' => $errors,
            ]);
            return;
        }

        $logoPath = (new UploadService())->store($request->file('business_logo'), 'branding');
        if ($logoPath !== null) {
            $payload['business_logo_path'] = $logoPath;
        }

        $settingsModel->saveMany([
            'business_name' => ['value' => $payload['business_name'], 'type' => 'string'],
            'business_address' => ['value' => $payload['business_address'], 'type' => 'string'],
            'business_phone' => ['value' => $payload['business_phone'], 'type' => 'string'],
            'business_email' => ['value' => $payload['business_email'], 'type' => 'string'],
            'currency' => ['value' => $payload['currency'], 'type' => 'string'],
            'receipt_header' => ['value' => $payload['receipt_header'], 'type' => 'string'],
            'receipt_footer' => ['value' => $payload['receipt_footer'], 'type' => 'string'],
            'barcode_format' => ['value' => $payload['barcode_format'], 'type' => 'string'],
            'tax_default' => ['value' => $payload['tax_default'], 'type' => 'string'],
            'multi_branch_enabled' => ['value' => $payload['multi_branch_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'email_low_stock_alerts_enabled' => ['value' => $payload['email_low_stock_alerts_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'email_daily_summary_enabled' => ['value' => $payload['email_daily_summary_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'ops_email_recipient_scope' => ['value' => $payload['ops_email_recipient_scope'], 'type' => 'string'],
            'ops_email_additional_recipients' => ['value' => $payload['ops_email_additional_recipients'], 'type' => 'string'],
            'mail_host' => ['value' => $payload['mail_host'], 'type' => 'string'],
            'mail_port' => ['value' => $payload['mail_port'] !== '' ? $payload['mail_port'] : '587', 'type' => 'string'],
            'mail_username' => ['value' => $payload['mail_username'], 'type' => 'string'],
            'mail_password' => ['value' => $payload['mail_password'], 'type' => 'string'],
            'mail_encryption' => ['value' => $payload['mail_encryption'], 'type' => 'string'],
            'mail_from_address' => ['value' => $payload['mail_from_address'], 'type' => 'string'],
            'mail_from_name' => ['value' => $payload['mail_from_name'], 'type' => 'string'],
            'thermal_printer_enabled' => ['value' => $payload['thermal_printer_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'thermal_printer_connector' => ['value' => $payload['thermal_printer_connector'], 'type' => 'string'],
            'thermal_printer_target' => ['value' => $payload['thermal_printer_target'], 'type' => 'string'],
            'thermal_printer_host' => ['value' => $payload['thermal_printer_host'], 'type' => 'string'],
            'thermal_printer_port' => ['value' => $payload['thermal_printer_port'] !== '' ? $payload['thermal_printer_port'] : '9100', 'type' => 'string'],
            'business_logo_path' => ['value' => $payload['business_logo_path'], 'type' => 'string'],
        ]);

        $companyId = current_company_id();
        if ($companyId !== null) {
            (new Company())->updateCompanyProfile($companyId, [
                'name' => $payload['business_name'],
                'email' => $payload['business_email'],
                'phone' => $payload['business_phone'],
                'address' => $payload['business_address'],
            ]);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'settings',
            entityId: null,
            description: 'Updated business and receipt settings.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Settings updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully.']);
            return;
        }

        $this->redirect('settings');
    }

    public function sendTestPrint(Request $request): void
    {
        $printerService = new ThermalPrinterService();

        try {
            $printerService->printTestPage([
                'requested_by' => trim((string) ((current_user()['first_name'] ?? '') . ' ' . (current_user()['last_name'] ?? ''))),
                'branch' => (string) (current_user()['branch_name'] ?? current_user()['branch'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('settings');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'test',
            entityType: 'thermal_printer',
            entityId: null,
            description: 'Sent a thermal printer test page to ' . $printerService->summary() . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $message = 'Thermal printer test sent successfully.';
        Session::flash('success', $message);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            return;
        }

        $this->redirect('settings');
    }

    public function createBackup(Request $request): void
    {
        try {
            $backup = (new DatabaseBackupService())->create(
                prefix: $request->boolean('schema_only') ? 'schema-backup' : 'backup',
                schemaOnly: $request->boolean('schema_only')
            );
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('settings');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'backup',
            entityType: 'database',
            entityId: null,
            description: 'Created database backup ' . $backup['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
        // If requested via AJAX, return backup metadata and download URL instead of streaming
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'name' => $backup['name'],
                'download_url' => url('settings/backups/download?file=' . rawurlencode($backup['name'])),
            ]);
            return;
        }

        $this->streamFile($backup['path'], $backup['name']);
    }

    public function createRestoreKit(Request $request): void
    {
        $backupName = trim((string) $request->input('backup_name', ''));

        try {
            $service = new DatabaseBackupService();
            $kit = $backupName !== ''
                ? $service->createRestoreKit($service->pathFor($backupName), $backupName)
                : $service->createRestoreKit();
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('settings');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'backup_restore_kit',
            entityType: 'database_backup',
            entityId: null,
            description: 'Created offline restore kit ' . $kit['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'name' => $kit['name'],
                'download_url' => url('settings/backups/download?file=' . rawurlencode($kit['name'])),
            ]);
            return;
        }

        $this->streamFile($kit['path'], $kit['name']);
    }

    public function downloadBackup(Request $request): void
    {
        $filename = trim((string) $request->query('file', ''));
        $service = new DatabaseBackupService();
        $path = $service->pathFor($filename);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'download',
            entityType: 'database_backup',
            entityId: null,
            description: 'Downloaded backup ' . basename($path) . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->streamFile($path, basename($path));
    }

    public function restoreBackup(Request $request): void
    {
        if (!(bool) config('app.allow_database_restore', false)) {
            $message = 'Database restore is disabled. Set ALLOW_DB_RESTORE=true in .env before using this maintenance action.';

            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('settings');
        }

        try {
            $result = (new DatabaseBackupService())->restoreFromUpload($request->file('backup_file'));
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('settings');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'restore',
            entityType: 'database',
            entityId: null,
            description: 'Restored the database from ' . $result['source_name'] . '. Pre-restore backup: ' . $result['backup']['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $message = 'Database restored successfully from ' . $result['source_name'] . '. Automatic pre-restore backup: ' . $result['backup']['name'] . '.';
        Session::flash('success', $message);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            return;
        }

        $this->redirect('settings');
    }

    public function sendTestEmail(Request $request): void
    {
        $recipientEmail = trim((string) $request->input(
            'recipient_email',
            setting_value('business_email', current_user()['email'] ?? '')
        ));
        $errors = Validator::validate(
            ['recipient_email' => $recipientEmail],
            ['recipient_email' => 'required|email|max:150']
        );

        if ($errors !== []) {
            $message = $errors['recipient_email'][0] ?? 'Provide a valid email address.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message, 'errors' => $errors]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('settings');
        }

        $mailService = new MailService();
        if (!$mailService->configured()) {
            $message = 'Mail is not configured. Save the SMTP host and sender address before sending a test message.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('settings');
        }

        $brandName = (string) setting_value('business_name', config('app.name', 'NovaPOS'));
        $sent = $mailService->send(
            toEmail: $recipientEmail,
            toName: $recipientEmail,
            subject: $brandName . ' mail connectivity test',
            htmlBody: '<h2 style="margin:0 0 12px;">Mail connectivity verified</h2>'
                . '<p style="margin:0 0 12px;">This is a test message from the NovaPOS settings workspace.</p>'
                . '<p style="margin:0;">Generated at ' . e(date('Y-m-d H:i:s')) . '.</p>',
            textBody: 'Mail connectivity verified. Generated at ' . date('Y-m-d H:i:s') . '.'
        );

        if (!$sent) {
            $message = 'The test email could not be delivered. '
                . ($mailService->lastError() ?: 'Verify SMTP host, credentials, encryption, and sender settings.');

            if (str_contains((string) ($mailService->settings()['host'] ?? ''), 'gmail.com')) {
                $message .= ' Gmail SMTP usually requires a Google app password with spaces removed.';
            }

            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('settings');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'test',
            entityType: 'mail',
            entityId: null,
            description: 'Sent a settings test email to ' . $recipientEmail . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $message = 'Test email sent successfully to ' . $recipientEmail . '.';
        Session::flash('success', $message);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            return;
        }

        $this->redirect('settings');
    }

    public function storeBranch(Request $request): void
    {
        $branchModel = new Branch();
        $payload = $this->branchPayload($request);
        $errors = $this->validateBranch($payload);

        if ($branchModel->codeExists($payload['code'])) {
            $errors['code'][] = 'That branch code is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                return;
            }

            $this->renderIndex([
                'branchForm' => $payload,
                'branchCreateErrors' => $errors,
            ]);
            return;
        }

        $branchId = $branchModel->createBranch($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'branch',
            entityId: $branchId,
            description: 'Created branch ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Branch created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Branch created successfully.']);
            return;
        }

        $this->redirect('settings');
    }

    public function updateBranch(Request $request): void
    {
        $branchModel = new Branch();
        $branchId = (int) $request->input('id');
        $existing = $branchModel->find($branchId);

        if ($existing === null) {
            throw new HttpException(404, 'Branch not found.');
        }

        $payload = $this->branchPayload($request);
        $errors = $this->validateBranch($payload);

        if ($branchModel->codeExists($payload['code'], $branchId)) {
            $errors['code'][] = 'That branch code is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                return;
            }

            $this->renderIndex([
                'editBranchId' => $branchId,
                'branchEditErrors' => $errors,
            ]);
            return;
        }

        $branchModel->updateBranch($branchId, $payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'branch',
            entityId: $branchId,
            description: 'Updated branch ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Branch updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Branch updated successfully.']);
            return;
        }

        $this->redirect('settings');
    }

    public function storeTax(Request $request): void
    {
        $taxModel = new Tax();
        $payload = $this->taxPayload($request);
        $errors = $this->validateTax($payload);

        if ($taxModel->nameExists($payload['name'])) {
            $errors['name'][] = 'That tax name is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                return;
            }

            $this->renderIndex([
                'taxForm' => $payload,
                'taxCreateErrors' => $errors,
            ]);
            return;
        }

        $taxId = $taxModel->createTax([
            'name' => $payload['name'],
            'rate' => $payload['rate'],
            'inclusive' => $payload['inclusive'],
        ]);

        if ($payload['set_as_default']) {
            (new Setting())->save('tax_default', $payload['name'], 'string');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'tax',
            entityId: $taxId,
            description: 'Created tax ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Tax created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Tax created successfully.']);
            return;
        }

        $this->redirect('settings');
    }

    public function updateTax(Request $request): void
    {
        $taxModel = new Tax();
        $taxId = (int) $request->input('id');
        $existing = $taxModel->find($taxId);

        if ($existing === null) {
            throw new HttpException(404, 'Tax not found.');
        }

        $payload = $this->taxPayload($request);
        $errors = $this->validateTax($payload);

        if ($taxModel->nameExists($payload['name'], $taxId)) {
            $errors['name'][] = 'That tax name is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                return;
            }

            $this->renderIndex([
                'editTaxId' => $taxId,
                'taxEditErrors' => $errors,
            ]);
            return;
        }

        $taxModel->updateTax($taxId, [
            'name' => $payload['name'],
            'rate' => $payload['rate'],
            'inclusive' => $payload['inclusive'],
        ]);

        $settingsModel = new Setting();
        $currentDefaultTax = (string) setting_value('tax_default', '');
        if ($payload['set_as_default'] || $currentDefaultTax === (string) $existing['name']) {
            $settingsModel->save('tax_default', $payload['name'], 'string');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'tax',
            entityId: $taxId,
            description: 'Updated tax ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Tax updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Tax updated successfully.']);
            return;
        }

        $this->redirect('settings');
    }

    public function deleteTax(Request $request): void
    {
        $taxModel = new Tax();
        $taxId = (int) $request->input('id');
        $tax = $taxModel->find($taxId);

        if ($tax === null) {
            throw new HttpException(404, 'Tax not found.');
        }

        if ((int) ($tax['product_count'] ?? 0) > 0) {
            $message = 'This tax is assigned to active products and cannot be deleted.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('settings');
        }

        $taxModel->deleteTax($taxId);

        if ((string) setting_value('tax_default', '') === (string) $tax['name']) {
            (new Setting())->save('tax_default', '', 'string');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'delete',
            entityType: 'tax',
            entityId: $taxId,
            description: 'Deleted tax ' . $tax['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Tax deleted successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Tax deleted successfully.']);
            return;
        }

        $this->redirect('settings');
    }

    private function renderIndex(array $overrides = []): void
    {
        $settings = $overrides['settings'] ?? $this->settingsDefaults();
        $branches = (new Branch())->all();
        $taxes = (new Tax())->all();
        $backups = (new DatabaseBackupService())->list();

        $this->render('settings/index', [
            'title' => 'Settings',
            'breadcrumbs' => ['Dashboard', 'Settings'],
            'settings' => $settings,
            'branches' => $branches,
            'taxes' => $taxes,
            'backups' => $backups,
            'systemHealth' => (new SystemHealthService())->snapshot(),
            'mailTestDefaults' => [
                'recipient_email' => (string) setting_value('business_email', current_user()['email'] ?? ''),
            ],
            'generalErrors' => $overrides['generalErrors'] ?? [],
            'taxCreateErrors' => $overrides['taxCreateErrors'] ?? [],
            'taxEditErrors' => $overrides['taxEditErrors'] ?? [],
            'editTaxId' => $overrides['editTaxId'] ?? null,
            'taxForm' => $overrides['taxForm'] ?? [
                'name' => '',
                'rate' => '0.00',
                'inclusive' => 0,
                'set_as_default' => 0,
            ],
            'branchCreateErrors' => $overrides['branchCreateErrors'] ?? [],
            'branchEditErrors' => $overrides['branchEditErrors'] ?? [],
            'editBranchId' => $overrides['editBranchId'] ?? null,
            'branchForm' => $overrides['branchForm'] ?? [
                'name' => '',
                'code' => '',
                'address' => '',
                'phone' => '',
                'email' => '',
                'status' => 'active',
                'is_default' => 0,
            ],
            'summary' => [
                'total_branches' => count($branches),
                'active_branches' => count(array_filter($branches, static fn (array $branch): bool => $branch['status'] === 'active')),
                'default_branch' => array_values(array_filter($branches, static fn (array $branch): bool => (int) $branch['is_default'] === 1))[0]['name'] ?? 'None',
                'multi_branch_enabled' => filter_var($settings['multi_branch_enabled'], FILTER_VALIDATE_BOOLEAN),
                'total_taxes' => count($taxes),
                'backup_count' => count($backups),
                'latest_backup' => $backups[0]['modified_at'] ?? 'No local backups',
            ],
        ]);
    }

    private function settingsDefaults(): array
    {
        return [
            'business_name' => (string) setting_value('business_name', config('app.name')),
            'business_address' => (string) setting_value('business_address', ''),
            'business_phone' => (string) setting_value('business_phone', ''),
            'business_email' => (string) setting_value('business_email', ''),
            'currency' => (string) setting_value('currency', default_currency_code()),
            'receipt_header' => (string) setting_value('receipt_header', 'Thank you for your business.'),
            'receipt_footer' => (string) setting_value('receipt_footer', 'Goods sold are subject to store policy.'),
            'barcode_format' => (string) setting_value('barcode_format', 'CODE128'),
            'tax_default' => (string) setting_value('tax_default', ''),
            'multi_branch_enabled' => (string) setting_value('multi_branch_enabled', 'false'),
            'email_low_stock_alerts_enabled' => (string) setting_value('email_low_stock_alerts_enabled', 'true'),
            'email_daily_summary_enabled' => (string) setting_value('email_daily_summary_enabled', 'true'),
            'ops_email_recipient_scope' => (string) setting_value('ops_email_recipient_scope', 'business_and_team'),
            'ops_email_additional_recipients' => (string) setting_value('ops_email_additional_recipients', ''),
            'mail_host' => (string) setting_value('mail_host', config('mail.host', '')),
            'mail_port' => (string) setting_value('mail_port', (string) config('mail.port', 587)),
            'mail_username' => (string) setting_value('mail_username', config('mail.username', '')),
            'mail_password' => (string) setting_value('mail_password', config('mail.password', '')),
            'mail_encryption' => (string) setting_value('mail_encryption', config('mail.encryption', 'tls')),
            'mail_from_address' => (string) setting_value('mail_from_address', config('mail.from_address', (string) setting_value('business_email', ''))),
            'mail_from_name' => (string) setting_value('mail_from_name', config('mail.from_name', (string) setting_value('business_name', config('app.name')))),
            'thermal_printer_enabled' => (string) setting_value('thermal_printer_enabled', 'false'),
            'thermal_printer_connector' => (string) setting_value('thermal_printer_connector', 'windows'),
            'thermal_printer_target' => (string) setting_value('thermal_printer_target', ''),
            'thermal_printer_host' => (string) setting_value('thermal_printer_host', ''),
            'thermal_printer_port' => (string) setting_value('thermal_printer_port', '9100'),
            'business_logo_path' => (string) setting_value('business_logo_path', ''),
        ];
    }

    private function invalidRecipientEmails(string $csv): array
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

    private function branchPayload(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name', '')),
            'code' => strtoupper(trim((string) $request->input('code', ''))),
            'address' => trim((string) $request->input('address', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'email' => trim((string) $request->input('email', '')),
            'status' => (string) $request->input('status', 'active'),
            'is_default' => $request->boolean('is_default') ? 1 : 0,
        ];
    }

    private function validateBranch(array $payload): array
    {
        return Validator::validate($payload, [
            'name' => 'required|min:2|max:150',
            'code' => 'required|min:2|max:40',
            'address' => 'nullable|max:255',
            'phone' => 'nullable|max:50',
            'email' => 'nullable|email|max:150',
            'status' => 'required|in:active,inactive',
        ]);
    }

    private function taxPayload(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name', '')),
            'rate' => (float) $request->input('rate', 0),
            'inclusive' => $request->boolean('inclusive') ? 1 : 0,
            'set_as_default' => $request->boolean('set_as_default') ? 1 : 0,
        ];
    }

    private function validateTax(array $payload): array
    {
        $errors = Validator::validate($payload, [
            'name' => 'required|min:2|max:100',
            'rate' => 'required|numeric',
        ]);

        if ((float) $payload['rate'] < 0 || (float) $payload['rate'] > 100) {
            $errors['rate'][] = 'Tax rate must be between 0 and 100.';
        }

        return $errors;
    }

    private function streamFile(string $path, string $downloadName): never
    {
        if (!is_file($path)) {
            throw new HttpException(404, 'The requested file could not be found.');
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($path);
        exit;
    }
}
