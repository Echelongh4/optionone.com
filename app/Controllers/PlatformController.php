<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\BillingInvoice;
use App\Models\BillingPaymentMethod;
use App\Models\BillingPaymentSubmission;
use App\Models\BillingPlan;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\EmailVerificationToken;
use App\Models\Setting;
use App\Models\User;
use App\Services\BillingUsageService;
use App\Services\MailService;
use App\Services\BillingOperationsService;
use App\Services\PlatformAutomationService;
use App\Services\WorkspaceProvisioner;
use Throwable;

class PlatformController extends Controller
{
    public function showSetup(Request $request): void
    {
        if (!$this->platformAdminSchemaReady()) {
            $this->renderSetupPage([
                'setup' => [$this->platformSchemaMessage()],
            ]);
            return;
        }

        if (!$this->platformBootstrapAvailable()) {
            Session::flash('info', 'The first platform admin is already configured. Sign in to continue.');
            $this->redirect('login');
        }

        $this->renderSetupPage();
    }

    public function setup(Request $request): void
    {
        $form = [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'username' => trim((string) $request->input('username', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'phone' => trim((string) $request->input('phone', '')),
        ];

        if (!$this->platformAdminSchemaReady()) {
            $this->renderSetupPage([
                'setup' => [$this->platformSchemaMessage()],
            ], $form);
            return;
        }

        if (!$this->platformBootstrapAvailable()) {
            Session::flash('info', 'The first platform admin is already configured. Sign in to continue.');
            $this->redirect('login');
        }

        $userModel = new User();
        $supportsUsername = $userModel->supportsUsername();
        $errors = Validator::validate($request->all(), [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|max:50',
            'password' => 'required|min:8|max:120',
            'password_confirmation' => 'required|min:8|max:120',
        ]);

        if ($supportsUsername) {
            $form['username'] = $userModel->resolveSignupUsername(
                preferredUsername: (string) $form['username'],
                email: (string) $form['email'],
                firstName: (string) $form['first_name'],
                lastName: (string) $form['last_name']
            );
        }

        if ($userModel->emailExists($form['email'])) {
            $errors['email'][] = 'This email address is already in use.';
        }

        if ((string) $request->input('password') !== (string) $request->input('password_confirmation')) {
            $errors['password_confirmation'][] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            $this->renderSetupPage($errors, $form);
            return;
        }

        try {
            $workspace = (new WorkspaceProvisioner())->ensurePlatformWorkspace();
            $branch = $workspace['branch'] ?? null;
            $company = $workspace['company'] ?? null;

            if (!is_array($branch) || !is_array($company)) {
                throw new \RuntimeException('The platform operations workspace could not be prepared.');
            }

            $platformRoleId = $userModel->roleIdByName('Super Admin') ?? $userModel->roleIdByName('Admin');
            if ($platformRoleId === null) {
                throw new \RuntimeException('The Super Admin or Admin role is not configured in this environment.');
            }

            $setupResult = Database::transaction(function () use (
                $userModel,
                $platformRoleId,
                $branch,
                $company,
                $form,
                $request,
                $supportsUsername
            ): array {
                $userId = $userModel->createUser([
                    'company_id' => (int) $company['id'],
                    'branch_id' => (int) $branch['id'],
                    'role_id' => $platformRoleId,
                    'first_name' => $form['first_name'],
                    'last_name' => $form['last_name'],
                    'username' => $supportsUsername ? strtolower(trim((string) $form['username'])) : '',
                    'email' => $form['email'],
                    'phone' => $form['phone'],
                    'password' => password_hash((string) $request->input('password'), PASSWORD_BCRYPT),
                    'status' => 'inactive',
                    'email_verified_at' => null,
                    'is_platform_admin' => 1,
                ]);

                $user = $userModel->findByIdGlobal($userId);
                if ($user === null) {
                    throw new \RuntimeException('The platform admin account could not be created.');
                }

                $verificationToken = (new EmailVerificationToken())->createForUser(
                    $user,
                    (int) config('app.email_verification_lifetime_minutes', 1440)
                );

                return [
                    'user' => $user,
                    'verification_link' => $this->verificationLinkFor($user, $verificationToken),
                ];
            });
        } catch (Throwable $exception) {
            $this->renderSetupPage([
                'setup' => [$exception->getMessage()],
            ], $form);
            return;
        }

        $user = $setupResult['user'] ?? null;
        $verificationLink = (string) ($setupResult['verification_link'] ?? '');

        if (!is_array($user)) {
            $this->renderSetupPage([
                'setup' => ['The platform admin account could not be created.'],
            ], $form);
            return;
        }

        $verificationSent = $verificationLink !== '' && $this->sendPlatformVerificationMail($user, $verificationLink);

        (new AuditLog())->record(
            userId: (int) $user['id'],
            action: 'platform_setup',
            entityType: 'user',
            entityId: (int) $user['id'],
            description: 'Created the first platform admin account through the setup flow.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$verificationSent && (bool) config('app.debug', false) && $verificationLink !== '') {
            Session::flash('warning', 'Mail delivery is not configured in this local environment. Use the generated verification link below.');
            Session::flash('verification_link', $verificationLink);
        } elseif (!$verificationSent) {
            Session::flash('warning', 'The platform admin account was created, but the verification email could not be sent. Check SMTP settings before first login.');
        }

        Session::flash('success', 'Platform admin setup is complete. Verify the email address first, then sign in.');
        $this->redirect('login');
    }

    public function index(Request $request): void
    {
        $companyModel = new Company();
        $auditLogModel = new AuditLog();
        $companies = $companyModel->platformList();
        $summary = $companyModel->platformSummary();
        $platformAdmins = $this->platformAdminAccounts();
        $billingReady = (new BillingPlan())->schemaReady();
        $platformCompany = $companyModel->findBySlug((string) config('app.platform_internal_company_slug', 'platform-operations-internal'));
        $platformOverviewSettings = is_array($platformCompany)
            ? $this->platformSettingsDefaults((int) ($platformCompany['id'] ?? 0), $platformCompany)
            : [
                'currency' => default_currency_code(),
                'tenant_default_currency' => default_currency_code(),
            ];

        $attentionCompanies = array_values(array_filter($companies, static function (array $company): bool {
            if ((string) ($company['status'] ?? 'inactive') !== 'active') {
                return true;
            }

            if ((int) ($company['pending_owner_verification_count'] ?? 0) > 0) {
                return true;
            }

            if (trim((string) ($company['owner_email'] ?? '')) === '') {
                return true;
            }

            return trim((string) ($company['last_login_at'] ?? '')) === '';
        }));

        usort($attentionCompanies, static function (array $left, array $right): int {
            $leftScore = ((string) ($left['status'] ?? 'inactive') !== 'active' ? 100 : 0)
                + ((int) ($left['pending_owner_verification_count'] ?? 0) > 0 ? 10 : 0)
                + (trim((string) ($left['owner_email'] ?? '')) === '' ? 5 : 0)
                + (trim((string) ($left['last_login_at'] ?? '')) === '' ? 1 : 0);
            $rightScore = ((string) ($right['status'] ?? 'inactive') !== 'active' ? 100 : 0)
                + ((int) ($right['pending_owner_verification_count'] ?? 0) > 0 ? 10 : 0)
                + (trim((string) ($right['owner_email'] ?? '')) === '' ? 5 : 0)
                + (trim((string) ($right['last_login_at'] ?? '')) === '' ? 1 : 0);

            return $rightScore <=> $leftScore;
        });

        $this->render('platform/index', [
            'title' => 'Platform Admin',
            'breadcrumbs' => ['Platform Admin'],
            'summary' => $summary,
            'billingReady' => $billingReady,
            'billingPlanSummary' => $billingReady ? (new BillingPlan())->summary() : [],
            'billingSubscriptionSummary' => $billingReady ? (new CompanySubscription())->summary() : [],
            'billingInvoiceSummary' => $billingReady ? (new BillingInvoice())->summary() : [],
            'platformOverviewSettings' => $platformOverviewSettings,
            'platformAdminSummary' => $this->summarizePlatformAdmins($platformAdmins),
            'recentCompanies' => array_slice($companies, 0, 8),
            'attentionCompanies' => array_slice($attentionCompanies, 0, 6),
            'recentActivity' => $auditLogModel->platformRecent(12),
        ], 'platform');
    }

    public function settings(Request $request): void
    {
        $this->renderPlatformSettingsPage();
    }

    public function updateSettings(Request $request): void
    {
        $workspace = (new WorkspaceProvisioner())->ensurePlatformWorkspace();
        $company = $workspace['company'] ?? null;

        if (!is_array($company) || (int) ($company['id'] ?? 0) <= 0) {
            Session::flash('error', 'The platform operations workspace could not be prepared.');
            $this->redirect('platform');
        }

        $platformCompanyId = (int) $company['id'];
        $current = $this->platformSettingsDefaults($platformCompanyId, $company);
        $payload = [
            'business_name' => trim((string) $request->input('business_name', '')),
            'business_address' => trim((string) $request->input('business_address', '')),
            'business_phone' => trim((string) $request->input('business_phone', '')),
            'business_email' => trim((string) $request->input('business_email', '')),
            'currency' => normalize_billing_currency((string) $request->input('currency', $current['currency']), $current['currency']),
            'tenant_default_currency' => normalize_billing_currency((string) $request->input('tenant_default_currency', $current['tenant_default_currency']), $current['tenant_default_currency']),
            'tenant_default_receipt_header' => trim((string) $request->input('tenant_default_receipt_header', '')),
            'tenant_default_receipt_footer' => trim((string) $request->input('tenant_default_receipt_footer', '')),
            'tenant_default_barcode_format' => trim((string) $request->input('tenant_default_barcode_format', 'CODE128')),
            'tenant_default_multi_branch_enabled' => $request->boolean('tenant_default_multi_branch_enabled'),
            'tenant_default_email_low_stock_alerts_enabled' => $request->boolean('tenant_default_email_low_stock_alerts_enabled'),
            'tenant_default_email_daily_summary_enabled' => $request->boolean('tenant_default_email_daily_summary_enabled'),
            'tenant_default_ops_email_recipient_scope' => trim((string) $request->input('tenant_default_ops_email_recipient_scope', 'business_and_team')),
            'tenant_default_ops_email_additional_recipients' => trim((string) $request->input('tenant_default_ops_email_additional_recipients', '')),
            'tenant_default_mail_host' => trim((string) $request->input('tenant_default_mail_host', '')),
            'tenant_default_mail_port' => trim((string) $request->input('tenant_default_mail_port', '587')),
            'tenant_default_mail_username' => trim((string) $request->input('tenant_default_mail_username', '')),
            'tenant_default_mail_password' => (string) $current['tenant_default_mail_password'],
            'tenant_default_mail_encryption' => trim((string) $request->input('tenant_default_mail_encryption', 'tls')),
            'tenant_default_mail_from_address' => trim((string) $request->input('tenant_default_mail_from_address', '')),
            'tenant_default_mail_from_name' => trim((string) $request->input('tenant_default_mail_from_name', '')),
            'platform_sms_enabled' => $request->boolean('platform_sms_enabled'),
            'platform_sms_provider' => trim((string) $request->input('platform_sms_provider', 'twilio')),
            'platform_sms_account_sid' => trim((string) $request->input('platform_sms_account_sid', '')),
            'platform_sms_auth_token' => trim((string) $request->input('platform_sms_auth_token', '')),
            'platform_sms_from_number' => trim((string) $request->input('platform_sms_from_number', '')),
            'platform_sms_messaging_service_sid' => trim((string) $request->input('platform_sms_messaging_service_sid', '')),
            'platform_sms_daily_summary_enabled' => $request->boolean('platform_sms_daily_summary_enabled'),
            'platform_sms_billing_alerts_enabled' => $request->boolean('platform_sms_billing_alerts_enabled'),
            'platform_default_phone_country_code' => preg_replace('/\D+/', '', (string) $request->input('platform_default_phone_country_code', '233')) ?: '233',
            'platform_automation_token' => trim((string) $request->input('platform_automation_token', '')),
            'platform_automation_billing_cycle_enabled' => $request->boolean('platform_automation_billing_cycle_enabled'),
            'platform_automation_billing_cycle_time' => trim((string) $request->input('platform_automation_billing_cycle_time', '02:00')),
            'platform_automation_daily_summary_enabled' => $request->boolean('platform_automation_daily_summary_enabled'),
            'platform_automation_daily_summary_time' => trim((string) $request->input('platform_automation_daily_summary_time', '20:00')),
            'platform_automation_backup_enabled' => $request->boolean('platform_automation_backup_enabled'),
            'platform_automation_backup_time' => trim((string) $request->input('platform_automation_backup_time', '23:30')),
            'platform_automation_backup_retention_count' => trim((string) $request->input('platform_automation_backup_retention_count', '14')),
            'platform_automation_backup_restore_kit_enabled' => $request->boolean('platform_automation_backup_restore_kit_enabled'),
        ];
        $applyToExistingWorkspaces = $request->boolean('apply_to_existing_workspaces');

        $submittedDefaultMailPassword = (string) $request->input('tenant_default_mail_password', '');
        if ($submittedDefaultMailPassword !== '') {
            $payload['tenant_default_mail_password'] = $submittedDefaultMailPassword;
        }

        if ($payload['tenant_default_mail_username'] === '') {
            $payload['tenant_default_mail_password'] = '';
        }

        $errors = Validator::validate($payload, [
            'business_name' => 'required|min:2|max:150',
            'business_address' => 'nullable|max:255',
            'business_phone' => 'nullable|max:50',
            'business_email' => 'nullable|email|max:150',
            'currency' => 'required|min:3|max:10',
            'tenant_default_currency' => 'required|min:3|max:10',
            'tenant_default_receipt_header' => 'nullable|max:255',
            'tenant_default_receipt_footer' => 'nullable|max:255',
            'tenant_default_barcode_format' => 'required|in:CODE128,CODE39,EAN13,UPC',
            'tenant_default_ops_email_recipient_scope' => 'required|in:business,team,business_and_team',
            'tenant_default_ops_email_additional_recipients' => 'nullable|max:500',
            'tenant_default_mail_host' => 'nullable|max:255',
            'tenant_default_mail_port' => 'nullable|integer',
            'tenant_default_mail_username' => 'nullable|max:255',
            'tenant_default_mail_password' => 'nullable|max:255',
            'tenant_default_mail_encryption' => 'required|in:tls,ssl,none',
            'tenant_default_mail_from_address' => 'nullable|email|max:150',
            'tenant_default_mail_from_name' => 'nullable|max:150',
            'platform_sms_provider' => 'required|in:twilio',
            'platform_sms_account_sid' => 'nullable|max:150',
            'platform_sms_auth_token' => 'nullable|max:150',
            'platform_sms_from_number' => 'nullable|max:50',
            'platform_sms_messaging_service_sid' => 'nullable|max:150',
            'platform_default_phone_country_code' => 'required|min:1|max:6',
            'platform_automation_token' => 'nullable|max:120',
            'platform_automation_billing_cycle_time' => 'required|max:5',
            'platform_automation_daily_summary_time' => 'required|max:5',
            'platform_automation_backup_time' => 'required|max:5',
            'platform_automation_backup_retention_count' => 'required|integer',
        ]);

        foreach ($this->invalidRecipientEmails((string) $payload['tenant_default_ops_email_additional_recipients']) as $invalidEmail) {
            $errors['tenant_default_ops_email_additional_recipients'][] = 'Invalid recipient email: ' . $invalidEmail;
        }

        if ($payload['tenant_default_mail_port'] !== '') {
            $mailPort = (int) $payload['tenant_default_mail_port'];
            if ($mailPort < 1 || $mailPort > 65535) {
                $errors['tenant_default_mail_port'][] = 'Default SMTP port must be between 1 and 65535.';
            }
        }

        if ($payload['tenant_default_mail_host'] !== '' && $payload['tenant_default_mail_from_address'] === '') {
            $errors['tenant_default_mail_from_address'][] = 'Enter the sender email address used for outgoing mail defaults.';
        }

        if ($payload['tenant_default_mail_host'] === '' && $payload['tenant_default_mail_from_address'] !== '') {
            $errors['tenant_default_mail_host'][] = 'Enter the SMTP host before saving a default sender address.';
        }

        if ($payload['platform_sms_enabled']) {
            if ($payload['platform_sms_account_sid'] === '') {
                $errors['platform_sms_account_sid'][] = 'Enter the Twilio Account SID before enabling SMS.';
            }

            if ($payload['platform_sms_auth_token'] === '') {
                $errors['platform_sms_auth_token'][] = 'Enter the Twilio auth token before enabling SMS.';
            }

            if ($payload['platform_sms_from_number'] === '' && $payload['platform_sms_messaging_service_sid'] === '') {
                $errors['platform_sms_from_number'][] = 'Provide either a Twilio sender number or a Messaging Service SID.';
            }
        }

        foreach ([
            'platform_automation_billing_cycle_time',
            'platform_automation_daily_summary_time',
            'platform_automation_backup_time',
        ] as $field) {
            $payload[$field] = $this->normalizedTimeValue((string) $payload[$field], (string) $current[$field]);
            if (!$this->validTimeValue((string) $payload[$field])) {
                $errors[$field][] = 'Enter time values in 24-hour HH:MM format.';
            }
        }

        if ((int) $payload['platform_automation_backup_retention_count'] < 3) {
            $errors['platform_automation_backup_retention_count'][] = 'Keep at least 3 scheduled backups for safe recovery.';
        }

        if ($payload['platform_automation_token'] === '') {
            try {
                $payload['platform_automation_token'] = bin2hex(random_bytes(24));
            } catch (Throwable) {
                $payload['platform_automation_token'] = sha1((string) microtime(true) . '-' . (string) mt_rand());
            }
        }

        if ($errors !== []) {
            $this->renderPlatformSettingsPage([
                'workspace' => $workspace,
                'settings' => $payload,
                'generalErrors' => $errors,
                'applyToExistingWorkspaces' => $applyToExistingWorkspaces,
            ]);
            return;
        }

        (new Setting())->saveMany([
            'business_name' => ['value' => $payload['business_name'], 'type' => 'string'],
            'business_address' => ['value' => $payload['business_address'], 'type' => 'string'],
            'business_phone' => ['value' => $payload['business_phone'], 'type' => 'string'],
            'business_email' => ['value' => $payload['business_email'], 'type' => 'string'],
            'currency' => ['value' => $payload['currency'], 'type' => 'string'],
            'tenant_default_currency' => ['value' => $payload['tenant_default_currency'], 'type' => 'string'],
            'tenant_default_receipt_header' => ['value' => $payload['tenant_default_receipt_header'], 'type' => 'string'],
            'tenant_default_receipt_footer' => ['value' => $payload['tenant_default_receipt_footer'], 'type' => 'string'],
            'tenant_default_barcode_format' => ['value' => $payload['tenant_default_barcode_format'], 'type' => 'string'],
            'tenant_default_multi_branch_enabled' => ['value' => $payload['tenant_default_multi_branch_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'tenant_default_email_low_stock_alerts_enabled' => ['value' => $payload['tenant_default_email_low_stock_alerts_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'tenant_default_email_daily_summary_enabled' => ['value' => $payload['tenant_default_email_daily_summary_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'tenant_default_ops_email_recipient_scope' => ['value' => $payload['tenant_default_ops_email_recipient_scope'], 'type' => 'string'],
            'tenant_default_ops_email_additional_recipients' => ['value' => $payload['tenant_default_ops_email_additional_recipients'], 'type' => 'string'],
            'tenant_default_mail_host' => ['value' => $payload['tenant_default_mail_host'], 'type' => 'string'],
            'tenant_default_mail_port' => ['value' => $payload['tenant_default_mail_port'] !== '' ? $payload['tenant_default_mail_port'] : '587', 'type' => 'string'],
            'tenant_default_mail_username' => ['value' => $payload['tenant_default_mail_username'], 'type' => 'string'],
            'tenant_default_mail_password' => ['value' => $payload['tenant_default_mail_password'], 'type' => 'string'],
            'tenant_default_mail_encryption' => ['value' => $payload['tenant_default_mail_encryption'], 'type' => 'string'],
            'tenant_default_mail_from_address' => ['value' => $payload['tenant_default_mail_from_address'], 'type' => 'string'],
            'tenant_default_mail_from_name' => ['value' => $payload['tenant_default_mail_from_name'], 'type' => 'string'],
            'platform_sms_enabled' => ['value' => $payload['platform_sms_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'platform_sms_provider' => ['value' => $payload['platform_sms_provider'], 'type' => 'string'],
            'platform_sms_account_sid' => ['value' => $payload['platform_sms_account_sid'], 'type' => 'string'],
            'platform_sms_auth_token' => ['value' => $payload['platform_sms_auth_token'], 'type' => 'string'],
            'platform_sms_from_number' => ['value' => $payload['platform_sms_from_number'], 'type' => 'string'],
            'platform_sms_messaging_service_sid' => ['value' => $payload['platform_sms_messaging_service_sid'], 'type' => 'string'],
            'platform_sms_daily_summary_enabled' => ['value' => $payload['platform_sms_daily_summary_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'platform_sms_billing_alerts_enabled' => ['value' => $payload['platform_sms_billing_alerts_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'platform_default_phone_country_code' => ['value' => $payload['platform_default_phone_country_code'], 'type' => 'string'],
            'platform_automation_token' => ['value' => $payload['platform_automation_token'], 'type' => 'string'],
            'platform_automation_billing_cycle_enabled' => ['value' => $payload['platform_automation_billing_cycle_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'platform_automation_billing_cycle_time' => ['value' => $payload['platform_automation_billing_cycle_time'], 'type' => 'string'],
            'platform_automation_daily_summary_enabled' => ['value' => $payload['platform_automation_daily_summary_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'platform_automation_daily_summary_time' => ['value' => $payload['platform_automation_daily_summary_time'], 'type' => 'string'],
            'platform_automation_backup_enabled' => ['value' => $payload['platform_automation_backup_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
            'platform_automation_backup_time' => ['value' => $payload['platform_automation_backup_time'], 'type' => 'string'],
            'platform_automation_backup_retention_count' => ['value' => (string) max(3, (int) $payload['platform_automation_backup_retention_count']), 'type' => 'integer'],
            'platform_automation_backup_restore_kit_enabled' => ['value' => $payload['platform_automation_backup_restore_kit_enabled'] ? 'true' : 'false', 'type' => 'boolean'],
        ], $platformCompanyId);

        (new Company())->updateCompanyProfile($platformCompanyId, [
            'name' => $payload['business_name'],
            'email' => $payload['business_email'],
            'phone' => $payload['business_phone'],
            'address' => $payload['business_address'],
        ]);

        $syncedWorkspaces = 0;
        if ($applyToExistingWorkspaces) {
            $syncedWorkspaces = $this->applyTenantDefaultsToExistingCompanies($payload, $platformCompanyId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_platform_settings',
            entityType: 'settings',
            entityId: $platformCompanyId,
            description: $applyToExistingWorkspaces
                ? 'Updated platform general settings and synced tenant defaults to ' . $syncedWorkspaces . ' company workspaces.'
                : 'Updated platform general settings and tenant workspace defaults.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash(
            'success',
            $applyToExistingWorkspaces
                ? 'Platform settings updated. Tenant defaults were synced to ' . $syncedWorkspaces . ' company workspaces.'
                : 'Platform settings updated successfully.'
        );
        $this->redirect('platform/settings');
    }

    public function companies(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
            'onboarding' => trim((string) $request->query('onboarding', '')),
            'activity' => trim((string) $request->query('activity', '')),
        ];
        $companies = (new Company())->platformList($filters);

        $this->render('platform/companies', [
            'title' => 'Companies',
            'breadcrumbs' => ['Platform Admin', 'Companies'],
            'filters' => $filters,
            'companies' => $companies,
        ], 'platform');
    }

    public function adminUsers(Request $request): void
    {
        $this->renderAdminUsersPage([
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
            'verification' => trim((string) $request->query('verification', '')),
            'source' => trim((string) $request->query('source', '')),
        ]);
    }

    public function createPlatformAdmin(Request $request): void
    {
        $form = [
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'username' => trim((string) $request->input('username', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'phone' => trim((string) $request->input('phone', '')),
            'role_name' => trim((string) $request->input('role_name', 'Super Admin')),
        ];

        if (!$this->platformAdminSchemaReady()) {
            Session::flash('error', $this->platformSchemaMessage());
            $this->redirect('platform/admin-users');
        }

        $userModel = new User();
        $supportsUsername = $userModel->supportsUsername();
        $errors = Validator::validate($request->all(), [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|max:50',
            'role_name' => 'required|in:Super Admin,Admin',
            'password' => 'required|min:8|max:120',
            'password_confirmation' => 'required|min:8|max:120',
        ]);

        if ($supportsUsername) {
            $form['username'] = $userModel->resolveSignupUsername(
                preferredUsername: (string) $form['username'],
                email: (string) $form['email'],
                firstName: (string) $form['first_name'],
                lastName: (string) $form['last_name']
            );
        }

        if ($userModel->emailExists($form['email'])) {
            $errors['email'][] = 'This email address is already in use.';
        }

        if ((string) $request->input('password') !== (string) $request->input('password_confirmation')) {
            $errors['password_confirmation'][] = 'Password confirmation does not match.';
        }

        if ($errors !== []) {
            $this->renderAdminUsersPage([], [
                'createErrors' => $errors,
                'createForm' => $form,
            ]);
            return;
        }

        try {
            $workspace = (new WorkspaceProvisioner())->ensurePlatformWorkspace();
            $branch = $workspace['branch'] ?? null;
            $company = $workspace['company'] ?? null;

            if (!is_array($branch) || !is_array($company)) {
                throw new \RuntimeException('The platform operations workspace could not be prepared.');
            }

            $roleId = $userModel->roleIdByName($form['role_name']);
            if ($roleId === null) {
                throw new \RuntimeException('The selected role is not available.');
            }

            $creationResult = Database::transaction(function () use (
                $userModel,
                $roleId,
                $branch,
                $company,
                $form,
                $request,
                $supportsUsername
            ): array {
                $userId = $userModel->createUser([
                    'company_id' => (int) $company['id'],
                    'branch_id' => (int) $branch['id'],
                    'role_id' => $roleId,
                    'first_name' => $form['first_name'],
                    'last_name' => $form['last_name'],
                    'username' => $supportsUsername ? strtolower(trim((string) $form['username'])) : '',
                    'email' => $form['email'],
                    'phone' => $form['phone'],
                    'password' => password_hash((string) $request->input('password'), PASSWORD_BCRYPT),
                    'status' => 'inactive',
                    'email_verified_at' => null,
                    'is_platform_admin' => 1,
                ]);

                $user = $userModel->findByIdGlobal($userId);
                if ($user === null) {
                    throw new \RuntimeException('The platform admin account could not be created.');
                }

                $verificationToken = (new EmailVerificationToken())->createForUser(
                    $user,
                    (int) config('app.email_verification_lifetime_minutes', 1440)
                );

                return [
                    'user' => $user,
                    'verification_link' => $this->verificationLinkFor($user, $verificationToken),
                ];
            });
        } catch (Throwable $exception) {
            $this->renderAdminUsersPage([], [
                'createErrors' => ['create' => [$exception->getMessage()]],
                'createForm' => $form,
            ]);
            return;
        }

        $user = $creationResult['user'] ?? null;
        $verificationLink = (string) ($creationResult['verification_link'] ?? '');

        if (!is_array($user)) {
            $this->renderAdminUsersPage([], [
                'createErrors' => ['create' => ['The platform admin account could not be created.']],
                'createForm' => $form,
            ]);
            return;
        }

        $verificationSent = $verificationLink !== '' && $this->sendPlatformVerificationMail($user, $verificationLink);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create_platform_admin',
            entityType: 'user',
            entityId: (int) $user['id'],
            description: 'Created a new platform admin account for ' . (string) ($user['email'] ?? 'platform user') . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$verificationSent && (bool) config('app.debug', false) && $verificationLink !== '') {
            Session::flash('warning', 'Mail delivery is not configured in this local environment. Use the generated verification link below.');
            Session::flash('verification_link', $verificationLink);
        } elseif (!$verificationSent) {
            Session::flash('warning', 'The platform admin account was created, but the verification email could not be sent.');
        }

        Session::flash('success', 'Platform admin account created. Email verification is required before first login.');
        $this->redirect('platform/admin-users');
    }

    public function promotePlatformAdmin(Request $request): void
    {
        $payload = [
            'login' => trim((string) $request->input('login', '')),
        ];
        $userModel = new User();
        $supportsUsername = $userModel->supportsUsername();
        $errors = Validator::validate($payload, [
            'login' => 'required|max:150',
        ]);

        if (!$supportsUsername && $payload['login'] !== '' && filter_var($payload['login'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['login'][] = 'Enter a valid email address.';
        }

        if ($errors !== []) {
            Session::flash('error', $this->firstError($errors, 'Enter a valid user email or username.'));
            $this->redirect('platform/admin-users');
        }

        $user = $userModel->findByLogin($payload['login']);
        if ($user === null) {
            Session::flash('error', 'No matching user account was found.');
            $this->redirect('platform/admin-users');
        }

        $platformAccount = $this->platformAdminAccountById((int) $user['id']);
        if ($platformAccount !== null && (bool) ($platformAccount['has_database_access'] ?? false)) {
            Session::flash('info', 'This user already has database-managed platform access.');
            $this->redirect('platform/admin-users');
        }

        $userModel->setPlatformAdminFlag((int) $user['id'], true);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'grant_platform_admin',
            entityType: 'user',
            entityId: (int) $user['id'],
            description: 'Granted database-managed platform access to ' . (string) ($user['email'] ?? 'user') . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ((int) $user['id'] === (int) Auth::id()) {
            Auth::refresh();
        }

        $message = 'Platform access granted.';
        if ((string) ($user['status'] ?? 'inactive') !== 'active') {
            $message .= ' The account is still inactive until it is activated.';
        } elseif (trim((string) ($user['email_verified_at'] ?? '')) === '') {
            $message .= ' The account still needs email verification before login.';
        }

        Session::flash('success', $message);
        $this->redirect('platform/admin-users');
    }

    public function updatePlatformAdminStatus(Request $request): void
    {
        $payload = [
            'user_id' => (string) $request->input('user_id', ''),
            'status' => trim((string) $request->input('status', '')),
        ];
        $errors = Validator::validate($payload, [
            'user_id' => 'required|integer',
            'status' => 'required|in:active,inactive',
        ]);

        if ($errors !== []) {
            Session::flash('error', 'Choose a valid platform admin and status.');
            $this->redirect('platform/admin-users');
        }

        $userId = (int) $payload['user_id'];
        $target = (new User())->findByIdGlobal($userId);
        if ($target === null) {
            throw new HttpException(404, 'Platform admin account not found.');
        }

        $platformAccount = $this->platformAdminAccountById($userId);
        if ($platformAccount === null) {
            Session::flash('warning', 'This user does not currently have platform access.');
            $this->redirect('platform/admin-users');
        }

        $newStatus = $payload['status'];
        $currentStatus = (string) ($target['status'] ?? 'inactive');
        if ($currentStatus === $newStatus) {
            Session::flash('info', 'The account status is already up to date.');
            $this->redirect('platform/admin-users');
        }

        if ($userId === (int) Auth::id() && $newStatus === 'inactive') {
            Session::flash('error', 'You cannot deactivate your own platform admin account.');
            $this->redirect('platform/admin-users');
        }

        if (
            $newStatus === 'inactive'
            && $this->isOperationalPlatformAdmin($platformAccount)
            && !$this->hasOtherOperationalPlatformAdmin($userId)
        ) {
            Session::flash('error', 'Keep at least one other active verified platform admin before suspending this account.');
            $this->redirect('platform/admin-users');
        }

        (new User())->setStatus($userId, $newStatus);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: $newStatus === 'active' ? 'activate_platform_admin' : 'deactivate_platform_admin',
            entityType: 'user',
            entityId: $userId,
            description: ($newStatus === 'active' ? 'Activated' : 'Suspended') . ' platform admin account ' . (string) ($target['email'] ?? 'user') . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', $newStatus === 'active'
            ? 'Platform admin account activated.'
            : 'Platform admin account suspended.');
        $this->redirect('platform/admin-users');
    }

    public function revokePlatformAdmin(Request $request): void
    {
        $payload = [
            'user_id' => (string) $request->input('user_id', ''),
        ];
        $errors = Validator::validate($payload, [
            'user_id' => 'required|integer',
        ]);

        if ($errors !== []) {
            Session::flash('error', 'Choose a valid platform admin first.');
            $this->redirect('platform/admin-users');
        }

        $userId = (int) $payload['user_id'];
        $target = (new User())->findByIdGlobal($userId);
        if ($target === null) {
            throw new HttpException(404, 'Platform admin account not found.');
        }

        $platformAccount = $this->platformAdminAccountById($userId);
        if ($platformAccount === null || !(bool) ($platformAccount['has_database_access'] ?? false)) {
            Session::flash('info', 'This account does not have database-managed platform access to revoke.');
            $this->redirect('platform/admin-users');
        }

        if ((new User())->countDirectPlatformAdmins($userId) <= 0) {
            Session::flash('error', 'Keep at least one database-managed platform admin account for platform recovery.');
            $this->redirect('platform/admin-users');
        }

        if (
            !(bool) ($platformAccount['is_env_whitelisted'] ?? false)
            && $this->isOperationalPlatformAdmin($platformAccount)
            && !$this->hasOtherOperationalPlatformAdmin($userId)
        ) {
            Session::flash('error', 'Keep at least one other active verified platform admin before removing this account\'s platform access.');
            $this->redirect('platform/admin-users');
        }

        (new User())->setPlatformAdminFlag($userId, false);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'revoke_platform_admin',
            entityType: 'user',
            entityId: $userId,
            description: 'Revoked database-managed platform access from ' . (string) ($target['email'] ?? 'user') . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Database-managed platform access removed.');

        if ($userId === (int) Auth::id()) {
            Auth::refresh();
            $this->redirect(Auth::isPlatformAdmin() ? 'platform/admin-users' : 'dashboard');
        }

        $this->redirect('platform/admin-users');
    }

    public function resendPlatformAdminVerification(Request $request): void
    {
        $payload = [
            'user_id' => (string) $request->input('user_id', ''),
        ];
        $errors = Validator::validate($payload, [
            'user_id' => 'required|integer',
        ]);

        if ($errors !== []) {
            Session::flash('error', 'Choose a valid platform admin first.');
            $this->redirect('platform/admin-users');
        }

        $userId = (int) $payload['user_id'];
        $user = (new User())->findByIdGlobal($userId);
        if ($user === null) {
            throw new HttpException(404, 'Platform admin account not found.');
        }

        if ($this->platformAdminAccountById($userId) === null) {
            Session::flash('warning', 'This user does not currently have platform access.');
            $this->redirect('platform/admin-users');
        }

        if (trim((string) ($user['email'] ?? '')) === '') {
            Session::flash('warning', 'This account does not have an email address.');
            $this->redirect('platform/admin-users');
        }

        if (trim((string) ($user['email_verified_at'] ?? '')) !== '') {
            Session::flash('info', 'This platform admin email is already verified.');
            $this->redirect('platform/admin-users');
        }

        $tokenModel = new EmailVerificationToken();
        $cooldownSeconds = (int) config('app.email_verification_resend_cooldown_seconds', 90);

        if ($tokenModel->issuedRecentlyForUser($userId, $cooldownSeconds)) {
            Session::flash('warning', 'A verification email was sent recently. Wait a moment before sending another.');
            $this->redirect('platform/admin-users');
        }

        $verificationToken = $tokenModel->createForUser(
            $user,
            (int) config('app.email_verification_lifetime_minutes', 1440)
        );
        $verificationLink = $this->verificationLinkFor($user, $verificationToken);
        $verificationSent = $verificationLink !== '' && $this->sendPlatformVerificationMail($user, $verificationLink);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'platform_admin_verification_resent',
            entityType: 'user',
            entityId: $userId,
            description: 'Resent the platform admin verification email for ' . (string) ($user['email'] ?? 'user') . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$verificationSent && (bool) config('app.debug', false)) {
            Session::flash('warning', 'Mail delivery is not configured in this local environment. Use the generated verification link below.');
            Session::flash('verification_link', $verificationLink);
        } elseif (!$verificationSent) {
            Session::flash('warning', 'The verification email could not be sent. Check the global SMTP settings.');
        } else {
            Session::flash('success', 'A fresh platform admin verification email has been sent.');
        }

        $this->redirect('platform/admin-users');
    }

    public function showCompany(Request $request): void
    {
        $companyId = (int) $request->query('id', 0);
        if ($companyId <= 0) {
            throw new HttpException(404, 'Company not found.');
        }

        $companyModel = new Company();
        $company = $companyModel->findDetailed($companyId);
        if ($company === null) {
            throw new HttpException(404, 'Company not found.');
        }

        $billingReady = (new BillingPlan())->schemaReady();
        $paymentsReady = (new BillingPaymentMethod())->schemaReady();
        $billingOps = new BillingOperationsService();
        if ($billingReady) {
            $billingOps->syncCompany($companyId, false, false);
        }
        $subscription = $billingReady ? (new CompanySubscription())->findByCompany($companyId) : null;
        $platformCompany = $companyModel->findBySlug((string) config('app.platform_internal_company_slug', 'platform-operations-internal'));
        $platformDefaultSettings = is_array($platformCompany)
            ? $this->platformSettingsDefaults((int) ($platformCompany['id'] ?? 0), $platformCompany)
            : [
                'tenant_default_currency' => default_currency_code(),
                'tenant_default_barcode_format' => 'CODE128',
                'tenant_default_receipt_header' => 'Thank you for your business.',
                'tenant_default_receipt_footer' => 'Goods sold are subject to store policy.',
                'tenant_default_multi_branch_enabled' => 'false',
                'tenant_default_email_low_stock_alerts_enabled' => 'true',
                'tenant_default_email_daily_summary_enabled' => 'true',
                'tenant_default_ops_email_recipient_scope' => 'business_and_team',
                'tenant_default_ops_email_additional_recipients' => '',
                'tenant_default_mail_host' => '',
                'tenant_default_mail_port' => (string) config('mail.port', 587),
                'tenant_default_mail_username' => '',
                'tenant_default_mail_encryption' => (string) config('mail.encryption', 'tls'),
                'tenant_default_mail_from_address' => '',
                'tenant_default_mail_from_name' => '',
            ];
        $workspaceSettings = $this->companyWorkspaceSettingsSummary($companyId);
        $platformCompanyId = (int) ($platformCompany['id'] ?? 0);

        $this->render('platform/show', [
            'title' => (string) ($company['name'] ?? 'Company Workspace'),
            'breadcrumbs' => ['Platform Admin', 'Companies', (string) ($company['name'] ?? 'Company')],
            'billingReady' => $billingReady,
            'paymentsReady' => $paymentsReady,
            'billingSchemaMessage' => 'Billing is unavailable until database/migrations/013_billing_management_support.sql is applied.',
            'paymentSchemaMessage' => 'Billing payment methods are unavailable until database/migrations/014_billing_payment_methods_support.sql is applied.',
            'platformBillingSettings' => $billingOps->platformSettings(),
            'availablePlans' => $billingReady ? (new BillingPlan())->all() : [],
            'subscription' => $subscription,
            'billingUsage' => $billingReady ? (new BillingUsageService())->snapshot($companyId) : [],
            'billingInvoices' => $billingReady ? (new BillingInvoice())->recent(20, $companyId) : [],
            'billingPaymentMethods' => $paymentsReady ? (new BillingPaymentMethod())->all(true) : [],
            'pendingPaymentSubmissions' => $paymentsReady ? (new BillingPaymentSubmission())->pendingList(20, $companyId) : [],
            'billingCurrencies' => billing_currency_options([
                (string) ($subscription['currency'] ?? ''),
            ]),
            'company' => $company,
            'workspaceSettings' => $workspaceSettings,
            'workspaceSettingsComparison' => $this->workspaceSettingsComparison($workspaceSettings, $platformDefaultSettings),
            'platformDefaultSettings' => $platformDefaultSettings,
            'canReapplyPlatformDefaults' => $platformCompanyId > 0 && $companyId !== $platformCompanyId,
            'owner' => $companyModel->primaryOwner($companyId),
            'supportAccessTarget' => (new User())->supportAccessTargetForCompany($companyId),
            'branches' => (new Branch())->all($companyId),
            'users' => (new User())->listUsers(null, $companyId),
            'recentActivity' => (new AuditLog())->recentForCompany($companyId, 20),
        ], 'platform');
    }

    public function reapplyCompanyDefaults(Request $request): void
    {
        $payload = [
            'company_id' => (string) $request->input('company_id', ''),
        ];
        $errors = Validator::validate($payload, [
            'company_id' => 'required|integer',
        ]);

        if ($errors !== []) {
            Session::flash('error', 'Choose a valid company before reapplying platform defaults.');
            $this->redirect('platform/companies');
        }

        $companyId = (int) $payload['company_id'];
        $companyModel = new Company();
        $company = $companyModel->findDetailed($companyId);
        if ($company === null) {
            throw new HttpException(404, 'Company not found.');
        }

        $workspace = (new WorkspaceProvisioner())->ensurePlatformWorkspace();
        $platformCompany = $workspace['company'] ?? null;
        if (!is_array($platformCompany) || (int) ($platformCompany['id'] ?? 0) <= 0) {
            Session::flash('error', 'The platform workspace is not ready for tenant default sync.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        $platformCompanyId = (int) $platformCompany['id'];
        if ($companyId === $platformCompanyId) {
            Session::flash('warning', 'Platform defaults cannot be reapplied to the internal platform workspace.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        $this->applyTenantDefaultsToCompany(
            $this->platformSettingsDefaults($platformCompanyId, $platformCompany),
            $companyId
        );

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'sync_company_workspace_defaults',
            entityType: 'company',
            entityId: $companyId,
            description: 'Reapplied platform tenant defaults to ' . (string) ($company['name'] ?? ('company #' . $companyId)) . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Platform tenant defaults were reapplied to this company workspace.');
        $this->redirect('platform/companies/show?id=' . $companyId);
    }

    public function updateCompanyStatus(Request $request): void
    {
        $payload = [
            'company_id' => (string) $request->input('company_id', ''),
            'status' => trim((string) $request->input('status', '')),
        ];
        $errors = Validator::validate($payload, [
            'company_id' => 'required|integer',
            'status' => 'required|in:active,inactive',
        ]);

        if ($errors !== []) {
            Session::flash('error', 'Choose a valid company and status.');
            $this->redirect('platform');
        }

        $companyId = (int) $payload['company_id'];
        $companyModel = new Company();
        $company = $companyModel->findDetailed($companyId);
        if ($company === null) {
            throw new HttpException(404, 'Company not found.');
        }

        $status = $payload['status'];
        $currentStatus = (string) ($company['status'] ?? 'inactive');
        if ($currentStatus === $status) {
            Session::flash('info', 'Company status is already up to date.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        $companyModel->updateStatus($companyId, $status);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: $status === 'active' ? 'activate' : 'deactivate',
            entityType: 'company',
            entityId: $companyId,
            description: sprintf(
                'Platform admin changed company %s to %s.',
                (string) ($company['name'] ?? ('#' . $companyId)),
                $status
            ),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', $status === 'active'
            ? 'Company activated. Tenant users can sign in again.'
            : 'Company suspended. Tenant sign-in is now blocked for that workspace.');
        $this->redirect('platform/companies/show?id=' . $companyId);
    }

    public function resendOwnerVerification(Request $request): void
    {
        $payload = [
            'company_id' => (string) $request->input('company_id', ''),
        ];
        $errors = Validator::validate($payload, [
            'company_id' => 'required|integer',
        ]);

        if ($errors !== []) {
            Session::flash('error', 'Choose a valid company first.');
            $this->redirect('platform');
        }

        $companyId = (int) $payload['company_id'];
        $companyModel = new Company();
        $company = $companyModel->findDetailed($companyId);
        if ($company === null) {
            throw new HttpException(404, 'Company not found.');
        }

        $owner = $companyModel->primaryOwner($companyId);
        if ($owner === null) {
            Session::flash('warning', 'This company does not have a primary owner account yet.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        if (trim((string) ($owner['email'] ?? '')) === '') {
            Session::flash('warning', 'The primary owner account has no email address.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        if (trim((string) ($owner['email_verified_at'] ?? '')) !== '') {
            Session::flash('info', 'The primary owner email is already verified.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        $tokenModel = new EmailVerificationToken();
        $cooldownSeconds = (int) config('app.email_verification_resend_cooldown_seconds', 90);

        if ($tokenModel->issuedRecentlyForUser((int) $owner['id'], $cooldownSeconds)) {
            Session::flash('warning', 'A verification email was sent recently. Wait a moment before sending another.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        $verificationToken = $tokenModel->createForUser(
            $owner,
            (int) config('app.email_verification_lifetime_minutes', 1440)
        );
        $verificationLink = absolute_url(
            'verify-email?email=' . rawurlencode((string) $owner['email']) . '&token=' . rawurlencode($verificationToken)
        );
        $verificationSent = $this->sendOwnerVerificationMail($company, $owner, $verificationLink);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'email_verification_resent',
            entityType: 'company',
            entityId: $companyId,
            description: 'Platform admin resent the owner verification email for ' . (string) ($company['name'] ?? 'company') . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if (!$verificationSent && (bool) config('app.debug', false)) {
            Session::flash('warning', 'Mail delivery is not configured in this local environment. Use the generated verification link below.');
            Session::flash('verification_link', $verificationLink);
        } elseif (!$verificationSent) {
            Session::flash('warning', 'The verification email could not be sent. Check SMTP settings or contact support.');
        } else {
            Session::flash('success', 'A fresh owner verification email has been sent.');
        }

        $this->redirect('platform/companies/show?id=' . $companyId);
    }

    public function impersonateCompany(Request $request): void
    {
        $payload = [
            'company_id' => (string) $request->input('company_id', ''),
            'reason' => trim((string) $request->input('reason', '')),
        ];
        $errors = Validator::validate($payload, [
            'company_id' => 'required|integer',
            'reason' => 'required|min:10|max:255',
        ]);

        $companyId = (int) $payload['company_id'];
        if ($errors !== []) {
            Session::flash('error', 'Provide a support reason of at least 10 characters before starting tenant access.');
            $this->redirect('platform/companies/show?id=' . max(1, $companyId));
        }

        $company = (new Company())->findDetailed($companyId);
        if ($company === null) {
            throw new HttpException(404, 'Company not found.');
        }

        $supportTarget = (new User())->supportAccessTargetForCompany($companyId);
        if ($supportTarget === null) {
            Session::flash('warning', 'No active verified user is available for support access in this company.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        $platformAdminId = Auth::id();
        $reason = $payload['reason'];
        $started = Auth::startImpersonation($supportTarget, $reason);

        if (!$started) {
            Session::flash('error', 'Support access could not be started.');
            $this->redirect('platform/companies/show?id=' . $companyId);
        }

        (new AuditLog())->record(
            userId: $platformAdminId,
            action: 'impersonation_start',
            entityType: 'company',
            entityId: $companyId,
            description: sprintf(
                'Started support access for %s as %s. Reason: %s',
                (string) ($company['name'] ?? ('company #' . $companyId)),
                (string) ($supportTarget['full_name'] ?? 'tenant user'),
                $reason
            ),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Support access started. You are now operating inside the tenant workspace.');
        $this->redirect('dashboard');
    }

    public function stopImpersonation(Request $request): void
    {
        $meta = Auth::impersonationMeta();
        $impersonatedUser = Auth::user();

        if ($meta === null || $impersonatedUser === null) {
            Session::flash('info', 'No support session is active.');
            $this->redirect(Auth::isPlatformAdmin() ? 'platform' : 'dashboard');
        }

        $targetCompanyId = (int) ($meta['target_company_id'] ?? ($impersonatedUser['company_id'] ?? 0));
        $targetCompanyName = (string) ($meta['target_company_name'] ?? ($impersonatedUser['company_name'] ?? 'company'));
        $targetUserName = (string) ($meta['target_user_name'] ?? ($impersonatedUser['full_name'] ?? 'tenant user'));
        $restored = Auth::stopImpersonation();

        if (!$restored) {
            Auth::logout();
            Session::flash('warning', 'The support session ended, but the original platform session could not be restored. Sign in again.');
            $this->redirect('login');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'impersonation_end',
            entityType: 'company',
            entityId: $targetCompanyId,
            description: sprintf(
                'Ended support access for %s after acting as %s.',
                $targetCompanyName,
                $targetUserName
            ),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Returned to the platform admin session.');
        $this->redirect('platform');
    }

    private function renderPlatformSettingsPage(array $state = []): void
    {
        $workspace = $state['workspace'] ?? (new WorkspaceProvisioner())->ensurePlatformWorkspace();
        $company = $workspace['company'] ?? null;

        if (!is_array($company) || (int) ($company['id'] ?? 0) <= 0) {
            throw new \RuntimeException('The platform operations workspace could not be prepared.');
        }

        $platformCompanyId = (int) $company['id'];
        $allCompanies = (new Company())->platformList();
        $managedCompanies = array_values(array_filter(
            $allCompanies,
            static fn (array $entry): bool => (int) ($entry['id'] ?? 0) !== $platformCompanyId
        ));
        $settings = $state['settings'] ?? $this->platformSettingsDefaults($platformCompanyId, $company);

        $this->render('platform/settings', [
            'title' => 'General Settings',
            'breadcrumbs' => ['Platform Admin', 'General Settings'],
            'platformCompany' => $company,
            'settings' => $settings,
            'supportedCurrencies' => billing_currency_options([
                (string) ($settings['currency'] ?? ''),
                (string) ($settings['tenant_default_currency'] ?? ''),
            ]),
            'generalErrors' => $state['generalErrors'] ?? [],
            'applyToExistingWorkspaces' => (bool) ($state['applyToExistingWorkspaces'] ?? false),
            'recentAutomationRuns' => (new PlatformAutomationService())->recentRuns(8),
            'automationEndpoint' => absolute_url('automation/run?token=' . rawurlencode((string) ($settings['platform_automation_token'] ?? ''))),
            'summary' => [
                'managed_companies' => count($managedCompanies),
                'active_companies' => count(array_filter(
                    $managedCompanies,
                    static fn (array $entry): bool => (string) ($entry['status'] ?? 'inactive') === 'active'
                )),
                'platform_currency' => (string) ($settings['currency'] ?? config('app.currency', 'GHS')),
                'tenant_default_currency' => (string) ($settings['tenant_default_currency'] ?? config('app.currency', 'GHS')),
                'tenant_default_mail_configured' => trim((string) ($settings['tenant_default_mail_host'] ?? '')) !== ''
                    && trim((string) ($settings['tenant_default_mail_from_address'] ?? '')) !== '',
                'platform_sms_configured' => !empty($settings['platform_sms_enabled'])
                    && trim((string) ($settings['platform_sms_account_sid'] ?? '')) !== ''
                    && (
                        trim((string) ($settings['platform_sms_from_number'] ?? '')) !== ''
                        || trim((string) ($settings['platform_sms_messaging_service_sid'] ?? '')) !== ''
                    ),
                'automation_enabled' => !empty($settings['platform_automation_billing_cycle_enabled'])
                    || !empty($settings['platform_automation_daily_summary_enabled'])
                    || !empty($settings['platform_automation_backup_enabled']),
            ],
        ], 'platform');
    }

    private function platformSettingsDefaults(int $companyId, ?array $company = null): array
    {
        $company ??= (new Company())->find($companyId) ?? [];
        $settings = (new Setting())->allAsMap($companyId);
        $platformName = trim((string) ($settings['business_name'] ?? $company['name'] ?? config('app.name', 'NovaPOS')));
        $platformEmail = trim((string) ($settings['business_email'] ?? $company['email'] ?? ''));
        $platformCurrency = normalize_billing_currency((string) ($settings['currency'] ?? config('app.currency', 'GHS')), config('app.currency', 'GHS'));

        return [
            'business_name' => $platformName !== '' ? $platformName : (string) config('app.name', 'NovaPOS'),
            'business_address' => trim((string) ($settings['business_address'] ?? $company['address'] ?? '')),
            'business_phone' => trim((string) ($settings['business_phone'] ?? $company['phone'] ?? '')),
            'business_email' => $platformEmail,
            'currency' => $platformCurrency,
            'tenant_default_currency' => normalize_billing_currency((string) ($settings['tenant_default_currency'] ?? $platformCurrency), $platformCurrency),
            'tenant_default_receipt_header' => (string) ($settings['tenant_default_receipt_header'] ?? 'Thank you for your business.'),
            'tenant_default_receipt_footer' => (string) ($settings['tenant_default_receipt_footer'] ?? 'Goods sold are subject to store policy.'),
            'tenant_default_barcode_format' => (string) ($settings['tenant_default_barcode_format'] ?? 'CODE128'),
            'tenant_default_multi_branch_enabled' => (string) ($settings['tenant_default_multi_branch_enabled'] ?? 'false'),
            'tenant_default_email_low_stock_alerts_enabled' => (string) ($settings['tenant_default_email_low_stock_alerts_enabled'] ?? 'true'),
            'tenant_default_email_daily_summary_enabled' => (string) ($settings['tenant_default_email_daily_summary_enabled'] ?? 'true'),
            'tenant_default_ops_email_recipient_scope' => (string) ($settings['tenant_default_ops_email_recipient_scope'] ?? 'business_and_team'),
            'tenant_default_ops_email_additional_recipients' => (string) ($settings['tenant_default_ops_email_additional_recipients'] ?? ''),
            'tenant_default_mail_host' => (string) ($settings['tenant_default_mail_host'] ?? config('mail.host', '')),
            'tenant_default_mail_port' => (string) ($settings['tenant_default_mail_port'] ?? (string) config('mail.port', 587)),
            'tenant_default_mail_username' => (string) ($settings['tenant_default_mail_username'] ?? ''),
            'tenant_default_mail_password' => (string) ($settings['tenant_default_mail_password'] ?? ''),
            'tenant_default_mail_encryption' => (string) ($settings['tenant_default_mail_encryption'] ?? config('mail.encryption', 'tls')),
            'tenant_default_mail_from_address' => (string) ($settings['tenant_default_mail_from_address'] ?? config('mail.from_address', $platformEmail)),
            'tenant_default_mail_from_name' => (string) ($settings['tenant_default_mail_from_name'] ?? config('mail.from_name', $platformName)),
            'platform_sms_enabled' => filter_var((string) ($settings['platform_sms_enabled'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
            'platform_sms_provider' => (string) ($settings['platform_sms_provider'] ?? 'twilio'),
            'platform_sms_account_sid' => (string) ($settings['platform_sms_account_sid'] ?? ''),
            'platform_sms_auth_token' => (string) ($settings['platform_sms_auth_token'] ?? ''),
            'platform_sms_from_number' => (string) ($settings['platform_sms_from_number'] ?? ''),
            'platform_sms_messaging_service_sid' => (string) ($settings['platform_sms_messaging_service_sid'] ?? ''),
            'platform_sms_daily_summary_enabled' => filter_var((string) ($settings['platform_sms_daily_summary_enabled'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
            'platform_sms_billing_alerts_enabled' => filter_var((string) ($settings['platform_sms_billing_alerts_enabled'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
            'platform_default_phone_country_code' => preg_replace('/\D+/', '', (string) ($settings['platform_default_phone_country_code'] ?? '233')) ?: '233',
            'platform_automation_token' => (string) ($settings['platform_automation_token'] ?? ''),
            'platform_automation_billing_cycle_enabled' => filter_var((string) ($settings['platform_automation_billing_cycle_enabled'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
            'platform_automation_billing_cycle_time' => $this->normalizedTimeValue((string) ($settings['platform_automation_billing_cycle_time'] ?? '02:00'), '02:00'),
            'platform_automation_daily_summary_enabled' => filter_var((string) ($settings['platform_automation_daily_summary_enabled'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
            'platform_automation_daily_summary_time' => $this->normalizedTimeValue((string) ($settings['platform_automation_daily_summary_time'] ?? '20:00'), '20:00'),
            'platform_automation_backup_enabled' => filter_var((string) ($settings['platform_automation_backup_enabled'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
            'platform_automation_backup_time' => $this->normalizedTimeValue((string) ($settings['platform_automation_backup_time'] ?? '23:30'), '23:30'),
            'platform_automation_backup_retention_count' => max(3, (int) ($settings['platform_automation_backup_retention_count'] ?? 14)),
            'platform_automation_backup_restore_kit_enabled' => filter_var((string) ($settings['platform_automation_backup_restore_kit_enabled'] ?? 'true'), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function applyTenantDefaultsToExistingCompanies(array $payload, int $platformCompanyId): int
    {
        $companies = (new Company())->platformList();
        $tenantSettings = $this->tenantWorkspaceSettingsPayload($payload);

        $updated = 0;
        foreach ($companies as $company) {
            $companyId = (int) ($company['id'] ?? 0);
            if ($companyId <= 0 || $companyId === $platformCompanyId) {
                continue;
            }

            (new Setting())->saveMany($tenantSettings, $companyId);
            $updated++;
        }

        return $updated;
    }

    private function companyWorkspaceSettingsSummary(int $companyId): array
    {
        $settings = (new Setting())->allAsMap($companyId);
        $fallbackCurrency = default_currency_code();
        $mailHost = trim((string) ($settings['mail_host'] ?? ''));
        $mailFromAddress = trim((string) ($settings['mail_from_address'] ?? ''));

        return [
            'currency' => normalize_billing_currency((string) ($settings['currency'] ?? $fallbackCurrency), $fallbackCurrency),
            'barcode_format' => (string) ($settings['barcode_format'] ?? 'CODE128'),
            'receipt_header' => (string) ($settings['receipt_header'] ?? 'Thank you for your business.'),
            'receipt_footer' => (string) ($settings['receipt_footer'] ?? 'Goods sold are subject to store policy.'),
            'multi_branch_enabled' => filter_var((string) ($settings['multi_branch_enabled'] ?? 'false'), FILTER_VALIDATE_BOOLEAN),
            'email_low_stock_alerts_enabled' => filter_var((string) ($settings['email_low_stock_alerts_enabled'] ?? 'true'), FILTER_VALIDATE_BOOLEAN),
            'email_daily_summary_enabled' => filter_var((string) ($settings['email_daily_summary_enabled'] ?? 'true'), FILTER_VALIDATE_BOOLEAN),
            'ops_email_recipient_scope' => (string) ($settings['ops_email_recipient_scope'] ?? 'business_and_team'),
            'ops_email_additional_recipients' => (string) ($settings['ops_email_additional_recipients'] ?? ''),
            'mail_host' => $mailHost,
            'mail_port' => (string) ($settings['mail_port'] ?? (string) config('mail.port', 587)),
            'mail_username' => (string) ($settings['mail_username'] ?? ''),
            'mail_encryption' => (string) ($settings['mail_encryption'] ?? config('mail.encryption', 'tls')),
            'mail_from_address' => $mailFromAddress,
            'mail_from_name' => (string) ($settings['mail_from_name'] ?? ''),
            'mail_configured' => $mailHost !== '' && $mailFromAddress !== '',
        ];
    }

    private function tenantWorkspaceSettingsPayload(array $payload): array
    {
        return [
            'currency' => ['value' => normalize_billing_currency((string) ($payload['tenant_default_currency'] ?? default_currency_code()), default_currency_code()), 'type' => 'string'],
            'receipt_header' => ['value' => (string) ($payload['tenant_default_receipt_header'] ?? ''), 'type' => 'string'],
            'receipt_footer' => ['value' => (string) ($payload['tenant_default_receipt_footer'] ?? ''), 'type' => 'string'],
            'barcode_format' => ['value' => (string) ($payload['tenant_default_barcode_format'] ?? 'CODE128'), 'type' => 'string'],
            'multi_branch_enabled' => ['value' => !empty($payload['tenant_default_multi_branch_enabled']) ? 'true' : 'false', 'type' => 'boolean'],
            'email_low_stock_alerts_enabled' => ['value' => !empty($payload['tenant_default_email_low_stock_alerts_enabled']) ? 'true' : 'false', 'type' => 'boolean'],
            'email_daily_summary_enabled' => ['value' => !empty($payload['tenant_default_email_daily_summary_enabled']) ? 'true' : 'false', 'type' => 'boolean'],
            'ops_email_recipient_scope' => ['value' => (string) ($payload['tenant_default_ops_email_recipient_scope'] ?? 'business_and_team'), 'type' => 'string'],
            'ops_email_additional_recipients' => ['value' => (string) ($payload['tenant_default_ops_email_additional_recipients'] ?? ''), 'type' => 'string'],
            'mail_host' => ['value' => (string) ($payload['tenant_default_mail_host'] ?? ''), 'type' => 'string'],
            'mail_port' => ['value' => (string) ($payload['tenant_default_mail_port'] ?? (string) config('mail.port', 587)), 'type' => 'string'],
            'mail_username' => ['value' => (string) ($payload['tenant_default_mail_username'] ?? ''), 'type' => 'string'],
            'mail_password' => ['value' => (string) ($payload['tenant_default_mail_password'] ?? ''), 'type' => 'string'],
            'mail_encryption' => ['value' => (string) ($payload['tenant_default_mail_encryption'] ?? config('mail.encryption', 'tls')), 'type' => 'string'],
            'mail_from_address' => ['value' => (string) ($payload['tenant_default_mail_from_address'] ?? ''), 'type' => 'string'],
            'mail_from_name' => ['value' => (string) ($payload['tenant_default_mail_from_name'] ?? ''), 'type' => 'string'],
        ];
    }

    private function applyTenantDefaultsToCompany(array $payload, int $companyId): void
    {
        (new Setting())->saveMany($this->tenantWorkspaceSettingsPayload($payload), $companyId);
    }

    private function workspaceSettingsComparison(array $workspaceSettings, array $platformDefaultSettings): array
    {
        $scopeLabels = [
            'business' => 'Business email only',
            'team' => 'Admin and manager team only',
            'business_and_team' => 'Business email and team',
        ];
        $boolLabel = static fn (bool $value, string $on, string $off): string => $value ? $on : $off;
        $toBool = static fn (mixed $value): bool => filter_var($value, FILTER_VALIDATE_BOOLEAN);

        $items = [];
        $comparisons = [
            'Currency' => [
                'current' => normalize_billing_currency((string) ($workspaceSettings['currency'] ?? default_currency_code()), default_currency_code()),
                'default' => normalize_billing_currency((string) ($platformDefaultSettings['tenant_default_currency'] ?? default_currency_code()), default_currency_code()),
            ],
            'Barcode format' => [
                'current' => (string) ($workspaceSettings['barcode_format'] ?? 'CODE128'),
                'default' => (string) ($platformDefaultSettings['tenant_default_barcode_format'] ?? 'CODE128'),
            ],
            'Receipt header' => [
                'current' => (string) ($workspaceSettings['receipt_header'] ?? ''),
                'default' => (string) ($platformDefaultSettings['tenant_default_receipt_header'] ?? ''),
            ],
            'Receipt footer' => [
                'current' => (string) ($workspaceSettings['receipt_footer'] ?? ''),
                'default' => (string) ($platformDefaultSettings['tenant_default_receipt_footer'] ?? ''),
            ],
            'Multi-branch mode' => [
                'current' => $boolLabel($toBool($workspaceSettings['multi_branch_enabled'] ?? false), 'Enabled', 'Disabled'),
                'default' => $boolLabel($toBool($platformDefaultSettings['tenant_default_multi_branch_enabled'] ?? false), 'Enabled', 'Disabled'),
            ],
            'Low-stock emails' => [
                'current' => $boolLabel($toBool($workspaceSettings['email_low_stock_alerts_enabled'] ?? true), 'Enabled', 'Disabled'),
                'default' => $boolLabel($toBool($platformDefaultSettings['tenant_default_email_low_stock_alerts_enabled'] ?? true), 'Enabled', 'Disabled'),
            ],
            'Daily summary emails' => [
                'current' => $boolLabel($toBool($workspaceSettings['email_daily_summary_enabled'] ?? true), 'Enabled', 'Disabled'),
                'default' => $boolLabel($toBool($platformDefaultSettings['tenant_default_email_daily_summary_enabled'] ?? true), 'Enabled', 'Disabled'),
            ],
            'Recipient scope' => [
                'current' => $scopeLabels[(string) ($workspaceSettings['ops_email_recipient_scope'] ?? 'business_and_team')] ?? (string) ($workspaceSettings['ops_email_recipient_scope'] ?? 'business_and_team'),
                'default' => $scopeLabels[(string) ($platformDefaultSettings['tenant_default_ops_email_recipient_scope'] ?? 'business_and_team')] ?? (string) ($platformDefaultSettings['tenant_default_ops_email_recipient_scope'] ?? 'business_and_team'),
            ],
            'Extra recipients' => [
                'current' => trim((string) ($workspaceSettings['ops_email_additional_recipients'] ?? '')) !== '' ? (string) $workspaceSettings['ops_email_additional_recipients'] : 'None',
                'default' => trim((string) ($platformDefaultSettings['tenant_default_ops_email_additional_recipients'] ?? '')) !== '' ? (string) $platformDefaultSettings['tenant_default_ops_email_additional_recipients'] : 'None',
            ],
            'SMTP host' => [
                'current' => trim((string) ($workspaceSettings['mail_host'] ?? '')) !== '' ? (string) $workspaceSettings['mail_host'] : 'Not set',
                'default' => trim((string) ($platformDefaultSettings['tenant_default_mail_host'] ?? '')) !== '' ? (string) $platformDefaultSettings['tenant_default_mail_host'] : 'Not set',
            ],
            'SMTP port' => [
                'current' => (string) ($workspaceSettings['mail_port'] ?? ''),
                'default' => (string) ($platformDefaultSettings['tenant_default_mail_port'] ?? ''),
            ],
            'SMTP username' => [
                'current' => trim((string) ($workspaceSettings['mail_username'] ?? '')) !== '' ? (string) $workspaceSettings['mail_username'] : 'Not set',
                'default' => trim((string) ($platformDefaultSettings['tenant_default_mail_username'] ?? '')) !== '' ? (string) $platformDefaultSettings['tenant_default_mail_username'] : 'Not set',
            ],
            'SMTP encryption' => [
                'current' => strtoupper((string) ($workspaceSettings['mail_encryption'] ?? '')),
                'default' => strtoupper((string) ($platformDefaultSettings['tenant_default_mail_encryption'] ?? '')),
            ],
            'Sender email' => [
                'current' => trim((string) ($workspaceSettings['mail_from_address'] ?? '')) !== '' ? (string) $workspaceSettings['mail_from_address'] : 'Not set',
                'default' => trim((string) ($platformDefaultSettings['tenant_default_mail_from_address'] ?? '')) !== '' ? (string) $platformDefaultSettings['tenant_default_mail_from_address'] : 'Not set',
            ],
            'Sender name' => [
                'current' => trim((string) ($workspaceSettings['mail_from_name'] ?? '')) !== '' ? (string) $workspaceSettings['mail_from_name'] : 'Not set',
                'default' => trim((string) ($platformDefaultSettings['tenant_default_mail_from_name'] ?? '')) !== '' ? (string) $platformDefaultSettings['tenant_default_mail_from_name'] : 'Not set',
            ],
        ];

        foreach ($comparisons as $label => $comparison) {
            if ((string) $comparison['current'] === (string) $comparison['default']) {
                continue;
            }

            $items[] = [
                'label' => $label,
                'current' => (string) $comparison['current'],
                'default' => (string) $comparison['default'],
            ];
        }

        return [
            'count' => count($items),
            'items' => $items,
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

    private function validTimeValue(string $value): bool
    {
        return preg_match('/^\d{2}:\d{2}$/', $value) === 1;
    }

    private function normalizedTimeValue(string $value, string $fallback = '00:00'): string
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

    private function renderSetupPage(array $errors = [], array $form = []): void
    {
        $this->render('platform/setup', [
            'title' => 'Register Platform Admin',
            'errors' => $errors,
            'form' => array_merge([
                'first_name' => '',
                'last_name' => '',
                'username' => '',
                'email' => '',
                'phone' => '',
            ], $form),
            'platformRegisterPath' => 'platform/register',
            'supportsUsername' => (new User())->supportsUsername(),
            'platformSetupReady' => $this->platformAdminSchemaReady(),
            'platformSetupMessage' => $this->platformSchemaMessage(),
            'layoutMode' => 'auth',
        ]);
    }

    private function renderAdminUsersPage(array $filters = [], array $state = []): void
    {
        $filters = array_merge([
            'search' => '',
            'status' => '',
            'verification' => '',
            'source' => '',
        ], $filters);

        $allPlatformAdmins = $this->platformAdminAccounts();
        $platformAdmins = $this->filterPlatformAdminAccounts($allPlatformAdmins, $filters);

        $this->render('platform/admin-users', [
            'title' => 'Platform Admins',
            'breadcrumbs' => ['Platform Admin', 'Admin Users'],
            'filters' => $filters,
            'platformAdmins' => $platformAdmins,
            'summary' => $this->summarizePlatformAdmins($allPlatformAdmins),
            'supportsUsername' => (new User())->supportsUsername(),
            'createErrors' => $state['createErrors'] ?? [],
            'createForm' => array_merge([
                'first_name' => '',
                'last_name' => '',
                'username' => '',
                'email' => '',
                'phone' => '',
                'role_name' => 'Super Admin',
            ], $state['createForm'] ?? []),
        ], 'platform');
    }

    private function platformAdminSchemaReady(): bool
    {
        $userModel = new User();

        return $userModel->supportsTenantSchema()
            && $userModel->supportsEmailVerificationSchema()
            && $userModel->supportsPlatformAdminSchema();
    }

    private function platformSchemaMessage(): string
    {
        $userModel = new User();

        if (!$userModel->supportsTenantSchema()) {
            return 'Platform setup is unavailable until database/migrations/010_multi_company_support.sql is applied.';
        }

        if (!$userModel->supportsEmailVerificationSchema()) {
            return 'Platform setup is unavailable until database/migrations/011_email_verification_support.sql is applied.';
        }

        return 'Platform setup is unavailable until database/migrations/012_platform_admin_support.sql is applied.';
    }

    private function platformBootstrapAvailable(): bool
    {
        if (!$this->platformAdminSchemaReady()) {
            return false;
        }

        return $this->platformAdminAccounts() === [];
    }

    private function platformAdminAccounts(): array
    {
        $userModel = new User();
        $accounts = [];

        foreach ($userModel->listDirectPlatformAdmins() as $user) {
            $user['access_sources'] = ['database'];
            $accounts[(int) $user['id']] = $user;
        }

        foreach ($userModel->findByEmails((array) config('app.platform_admin_emails', [])) as $user) {
            $userId = (int) $user['id'];
            if (!isset($accounts[$userId])) {
                $user['access_sources'] = ['environment'];
                $accounts[$userId] = $user;
                continue;
            }

            $accounts[$userId]['access_sources'][] = 'environment';
        }

        foreach ($accounts as &$account) {
            $sources = array_values(array_unique(array_map(
                static fn (mixed $source): string => (string) $source,
                is_array($account['access_sources'] ?? null) ? $account['access_sources'] : []
            )));
            sort($sources);

            $hasDatabaseAccess = in_array('database', $sources, true);
            $isEnvWhitelisted = in_array('environment', $sources, true);

            $account['access_sources'] = $sources;
            $account['has_database_access'] = $hasDatabaseAccess;
            $account['is_env_whitelisted'] = $isEnvWhitelisted;
            $account['platform_access_source'] = $hasDatabaseAccess && $isEnvWhitelisted
                ? 'Database + Env'
                : ($hasDatabaseAccess ? 'Database' : 'Environment');
        }
        unset($account);

        usort($accounts, static function (array $left, array $right): int {
            $leftScore = ((string) ($left['status'] ?? 'inactive') === 'active' ? 100 : 0)
                + (trim((string) ($left['email_verified_at'] ?? '')) !== '' ? 10 : 0)
                + ((bool) ($left['has_database_access'] ?? false) ? 5 : 0);
            $rightScore = ((string) ($right['status'] ?? 'inactive') === 'active' ? 100 : 0)
                + (trim((string) ($right['email_verified_at'] ?? '')) !== '' ? 10 : 0)
                + ((bool) ($right['has_database_access'] ?? false) ? 5 : 0);

            if ($leftScore !== $rightScore) {
                return $rightScore <=> $leftScore;
            }

            return strcmp(
                strtolower((string) ($left['full_name'] ?? $left['email'] ?? '')),
                strtolower((string) ($right['full_name'] ?? $right['email'] ?? ''))
            );
        });

        return array_values($accounts);
    }

    private function filterPlatformAdminAccounts(array $accounts, array $filters): array
    {
        $search = strtolower(trim((string) ($filters['search'] ?? '')));
        $status = trim((string) ($filters['status'] ?? ''));
        $verification = trim((string) ($filters['verification'] ?? ''));
        $source = trim((string) ($filters['source'] ?? ''));

        return array_values(array_filter($accounts, static function (array $account) use ($search, $status, $verification, $source): bool {
            if ($search !== '') {
                $haystack = strtolower(implode(' ', array_filter([
                    (string) ($account['full_name'] ?? ''),
                    (string) ($account['email'] ?? ''),
                    (string) ($account['company_name'] ?? ''),
                    (string) ($account['role_name'] ?? ''),
                    (string) ($account['platform_access_source'] ?? ''),
                ])));

                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }

            if (in_array($status, ['active', 'inactive'], true) && (string) ($account['status'] ?? 'inactive') !== $status) {
                return false;
            }

            if ($verification === 'verified' && trim((string) ($account['email_verified_at'] ?? '')) === '') {
                return false;
            }

            if ($verification === 'pending' && trim((string) ($account['email_verified_at'] ?? '')) !== '') {
                return false;
            }

            if ($source === 'database' && !(bool) ($account['has_database_access'] ?? false)) {
                return false;
            }

            if ($source === 'environment' && !(bool) ($account['is_env_whitelisted'] ?? false)) {
                return false;
            }

            if ($source === 'hybrid' && (
                !(bool) ($account['has_database_access'] ?? false)
                || !(bool) ($account['is_env_whitelisted'] ?? false)
            )) {
                return false;
            }

            return true;
        }));
    }

    private function summarizePlatformAdmins(array $accounts): array
    {
        return [
            'total' => count($accounts),
            'active' => count(array_filter($accounts, static fn (array $account): bool => (string) ($account['status'] ?? 'inactive') === 'active')),
            'pending_verification' => count(array_filter($accounts, static fn (array $account): bool => trim((string) ($account['email_verified_at'] ?? '')) === '')),
            'database_managed' => count(array_filter($accounts, static fn (array $account): bool => (bool) ($account['has_database_access'] ?? false))),
            'env_managed' => count(array_filter($accounts, static fn (array $account): bool => (bool) ($account['is_env_whitelisted'] ?? false))),
        ];
    }

    private function platformAdminAccountById(int $userId): ?array
    {
        foreach ($this->platformAdminAccounts() as $account) {
            if ((int) ($account['id'] ?? 0) === $userId) {
                return $account;
            }
        }

        return null;
    }

    private function hasOtherOperationalPlatformAdmin(int $excludedUserId): bool
    {
        foreach ($this->platformAdminAccounts() as $account) {
            if ((int) ($account['id'] ?? 0) === $excludedUserId) {
                continue;
            }

            if ($this->isOperationalPlatformAdmin($account)) {
                return true;
            }
        }

        return false;
    }

    private function isOperationalPlatformAdmin(array $account): bool
    {
        return (string) ($account['status'] ?? 'inactive') === 'active'
            && trim((string) ($account['email_verified_at'] ?? '')) !== '';
    }

    private function verificationLinkFor(array $user, string $token): string
    {
        return absolute_url(
            'verify-email?email=' . rawurlencode((string) ($user['email'] ?? '')) . '&token=' . rawurlencode($token)
        );
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

    private function sendPlatformVerificationMail(array $user, string $verificationLink): bool
    {
        $mailService = new MailService();
        $platformName = (string) config('app.name', 'NovaPOS');
        $lifetimeMinutes = (int) config('app.email_verification_lifetime_minutes', 1440);
        $fullName = trim((string) ($user['full_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))));

        $subject = $platformName . ' platform admin verification';
        $htmlBody = '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 12px;">Verify your platform admin email</h2>
                <p>Hello ' . e($fullName !== '' ? $fullName : (string) ($user['email'] ?? 'platform user')) . ',</p>
                <p>Your platform administration account is ready. Verify this email address before signing in.</p>
                <p>
                    <a href="' . e($verificationLink) . '" style="display:inline-block;padding:12px 18px;background:#2872A1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                        Verify Platform Access
                    </a>
                </p>
                <p>This link expires in ' . e((string) $lifetimeMinutes) . ' minutes.</p>
            </div>';
        $textBody = "Verify your platform admin email for {$platformName}\n\nOpen this link: {$verificationLink}\n\nThis link expires in {$lifetimeMinutes} minutes.";

        return $mailService->send(
            toEmail: (string) ($user['email'] ?? ''),
            toName: $fullName !== '' ? $fullName : (string) ($user['email'] ?? 'platform user'),
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            settings: $mailService->globalSettings()
        );
    }

    private function sendOwnerVerificationMail(array $company, array $owner, string $verificationLink): bool
    {
        $companyName = (string) ($company['name'] ?? config('app.name', 'NovaPOS'));
        $lifetimeMinutes = (int) config('app.email_verification_lifetime_minutes', 1440);
        $fullName = trim((string) ($owner['full_name'] ?? (($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''))));

        $subject = $companyName . ' owner email verification';
        $htmlBody = '
            <div style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
                <h2 style="margin-bottom: 12px;">Verify your owner email address</h2>
                <p>Hello ' . e($fullName !== '' ? $fullName : (string) $owner['email']) . ',</p>
                <p>Your workspace for ' . e($companyName) . ' is waiting for owner email verification before login.</p>
                <p>
                    <a href="' . e($verificationLink) . '" style="display:inline-block;padding:12px 18px;background:#2872A1;color:#ffffff;text-decoration:none;border-radius:10px;font-weight:700;">
                        Verify Email
                    </a>
                </p>
                <p>This link expires in ' . e((string) $lifetimeMinutes) . ' minutes.</p>
            </div>';
        $textBody = "Verify your email for {$companyName}\n\nOpen this link: {$verificationLink}\n\nThis link expires in {$lifetimeMinutes} minutes.";

        $mailService = new MailService();

        return $mailService->send(
            toEmail: (string) $owner['email'],
            toName: $fullName !== '' ? $fullName : (string) $owner['email'],
            subject: $subject,
            htmlBody: $htmlBody,
            textBody: $textBody,
            settings: $mailService->globalSettings()
        );
    }
}
