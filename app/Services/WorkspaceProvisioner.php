<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Customer;
use App\Models\EmailVerificationToken;
use App\Models\ExpenseCategory;
use App\Models\Setting;
use App\Models\User;

class WorkspaceProvisioner
{
    public function provisionTenantWorkspace(array $form, string $passwordHash, bool $supportsUsername): array
    {
        return Database::transaction(function () use ($form, $passwordHash, $supportsUsername): array {
            $userModel = new User();
            $adminRoleId = $userModel->roleIdByName('Admin');

            if ($adminRoleId === null) {
                throw new \RuntimeException('The Admin role is not configured in this environment.');
            }

            $companyModel = new Company();
            $branchModel = new Branch();
            $tokenModel = new EmailVerificationToken();

            $companyId = $companyModel->createCompany([
                'name' => trim((string) ($form['company_name'] ?? '')),
                'email' => trim((string) ($form['email'] ?? '')),
                'phone' => trim((string) ($form['phone'] ?? '')),
                'address' => trim((string) ($form['address'] ?? '')),
                'status' => 'active',
            ]);

            $branchId = $this->createDefaultBranch(
                branchModel: $branchModel,
                companyId: $companyId,
                name: 'Main Branch',
                code: 'MAIN',
                email: trim((string) ($form['email'] ?? '')),
                phone: trim((string) ($form['phone'] ?? '')),
                address: trim((string) ($form['address'] ?? ''))
            );

            $this->seedWorkspaceDefaults(
                companyId: $companyId,
                companyName: trim((string) ($form['company_name'] ?? '')),
                companyEmail: trim((string) ($form['email'] ?? '')),
                companyPhone: trim((string) ($form['phone'] ?? '')),
                companyAddress: trim((string) ($form['address'] ?? '')),
                billingContactName: trim((string) (($form['first_name'] ?? '') . ' ' . ($form['last_name'] ?? '')))
            );

            $userId = $userModel->createUser([
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'role_id' => $adminRoleId,
                'first_name' => trim((string) ($form['first_name'] ?? '')),
                'last_name' => trim((string) ($form['last_name'] ?? '')),
                'username' => $supportsUsername ? strtolower(trim((string) ($form['username'] ?? ''))) : '',
                'email' => trim((string) ($form['email'] ?? '')),
                'phone' => trim((string) ($form['phone'] ?? '')),
                'password' => $passwordHash,
                'status' => 'inactive',
                'email_verified_at' => null,
            ]);

            $user = $userModel->findById($userId, $companyId);
            if ($user === null) {
                throw new \RuntimeException('The company workspace could not be created.');
            }

            $subscriptionModel = new CompanySubscription();
            if ($subscriptionModel->schemaReady()) {
                $subscriptionModel->ensureDefaultForCompany($companyId);
            }

            $verificationToken = $tokenModel->createForUser(
                $user,
                (int) config('app.email_verification_lifetime_minutes', 1440)
            );

            return [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'user' => $user,
                'verification_link' => absolute_url(
                    'verify-email?email=' . rawurlencode((string) $user['email']) . '&token=' . rawurlencode($verificationToken)
                ),
            ];
        });
    }

    public function ensurePlatformWorkspace(): array
    {
        return Database::transaction(function (): array {
            $companyModel = new Company();
            $branchModel = new Branch();
            $companySlug = trim((string) config('app.platform_internal_company_slug', 'platform-operations-internal'));

            $company = $companyModel->findBySlug($companySlug);
            if ($company === null) {
                $companyId = $companyModel->createCompany([
                    'name' => trim((string) config(
                        'app.platform_internal_company_name',
                        (string) config('app.name', 'NovaPOS') . ' Platform Operations'
                    )),
                    'slug' => $companySlug,
                    'email' => trim((string) config('mail.from_address', '')),
                    'phone' => '',
                    'address' => '',
                    'status' => 'active',
                ]);
                $company = $companyModel->find($companyId);
            }

            if ($company === null) {
                throw new \RuntimeException('The platform operations workspace could not be prepared.');
            }

            $companyId = (int) $company['id'];
            if ((string) ($company['status'] ?? 'inactive') !== 'active') {
                $companyModel->updateStatus($companyId, 'active');
                $company = $companyModel->find($companyId) ?? $company;
            }

            $branchId = $branchModel->defaultId($companyId);
            if ($branchId === null) {
                $branchId = $this->createDefaultBranch(
                    branchModel: $branchModel,
                    companyId: $companyId,
                    name: trim((string) config('app.platform_internal_branch_name', 'Control Center')),
                    code: trim((string) config('app.platform_internal_branch_code', 'CTRL')),
                    email: trim((string) ($company['email'] ?? '')),
                    phone: trim((string) ($company['phone'] ?? '')),
                    address: trim((string) ($company['address'] ?? ''))
                );
            }

            $branch = $branchModel->find($branchId, $companyId);
            if ($branch === null) {
                throw new \RuntimeException('The platform operations branch could not be prepared.');
            }

            $this->seedWorkspaceDefaults(
                companyId: $companyId,
                companyName: trim((string) ($company['name'] ?? config('app.name', 'NovaPOS'))),
                companyEmail: trim((string) ($company['email'] ?? '')),
                companyPhone: trim((string) ($company['phone'] ?? '')),
                companyAddress: trim((string) ($company['address'] ?? '')),
                billingContactName: trim((string) ($company['name'] ?? 'Platform Operations'))
            );

            return [
                'company' => $company,
                'branch' => $branch,
            ];
        });
    }

    private function seedWorkspaceDefaults(
        int $companyId,
        string $companyName,
        string $companyEmail,
        string $companyPhone,
        string $companyAddress,
        string $billingContactName = ''
    ): void {
        $settingModel = new Setting();
        $existingSettings = $settingModel->allAsMap($companyId);
        $tenantDefaults = $this->platformTenantDefaults($companyId);
        $defaultSettings = [
            'business_name' => ['value' => $companyName, 'type' => 'string'],
            'business_address' => ['value' => $companyAddress, 'type' => 'string'],
            'business_phone' => ['value' => $companyPhone, 'type' => 'string'],
            'business_email' => ['value' => $companyEmail, 'type' => 'string'],
            'currency' => ['value' => $tenantDefaults['currency'], 'type' => 'string'],
            'receipt_header' => ['value' => $tenantDefaults['receipt_header'], 'type' => 'string'],
            'receipt_footer' => ['value' => $tenantDefaults['receipt_footer'], 'type' => 'string'],
            'barcode_format' => ['value' => $tenantDefaults['barcode_format'], 'type' => 'string'],
            'tax_default' => ['value' => '', 'type' => 'string'],
            'multi_branch_enabled' => ['value' => $tenantDefaults['multi_branch_enabled'], 'type' => 'boolean'],
            'email_low_stock_alerts_enabled' => ['value' => $tenantDefaults['email_low_stock_alerts_enabled'], 'type' => 'boolean'],
            'email_daily_summary_enabled' => ['value' => $tenantDefaults['email_daily_summary_enabled'], 'type' => 'boolean'],
            'ops_email_recipient_scope' => ['value' => $tenantDefaults['ops_email_recipient_scope'], 'type' => 'string'],
            'ops_email_additional_recipients' => ['value' => $tenantDefaults['ops_email_additional_recipients'], 'type' => 'string'],
            'mail_host' => ['value' => $tenantDefaults['mail_host'], 'type' => 'string'],
            'mail_port' => ['value' => $tenantDefaults['mail_port'], 'type' => 'string'],
            'mail_username' => ['value' => $tenantDefaults['mail_username'], 'type' => 'string'],
            'mail_password' => ['value' => $tenantDefaults['mail_password'], 'type' => 'string'],
            'mail_encryption' => ['value' => $tenantDefaults['mail_encryption'], 'type' => 'string'],
            'mail_from_address' => ['value' => $tenantDefaults['mail_from_address'] !== '' ? $tenantDefaults['mail_from_address'] : $companyEmail, 'type' => 'string'],
            'mail_from_name' => ['value' => $tenantDefaults['mail_from_name'] !== '' ? $tenantDefaults['mail_from_name'] : $companyName, 'type' => 'string'],
            'thermal_printer_enabled' => ['value' => 'false', 'type' => 'boolean'],
            'thermal_printer_connector' => ['value' => 'windows', 'type' => 'string'],
            'thermal_printer_target' => ['value' => '', 'type' => 'string'],
            'thermal_printer_host' => ['value' => '', 'type' => 'string'],
            'thermal_printer_port' => ['value' => '9100', 'type' => 'string'],
            'business_logo_path' => ['value' => '', 'type' => 'string'],
            'billing_contact_name' => ['value' => trim($billingContactName) !== '' ? trim($billingContactName) : ($companyName . ' Billing'), 'type' => 'string'],
            'billing_contact_email' => ['value' => $companyEmail, 'type' => 'string'],
            'billing_contact_phone' => ['value' => $companyPhone, 'type' => 'string'],
            'billing_address' => ['value' => $companyAddress, 'type' => 'string'],
            'billing_tax_number' => ['value' => '', 'type' => 'string'],
            'billing_notification_emails' => ['value' => $companyEmail, 'type' => 'string'],
            'billing_notes' => ['value' => '', 'type' => 'string'],
        ];

        $missingSettings = [];
        foreach ($defaultSettings as $key => $payload) {
            if (!array_key_exists($key, $existingSettings)) {
                $missingSettings[$key] = $payload;
            }
        }

        if ($missingSettings !== []) {
            $settingModel->saveMany($missingSettings, $companyId);
        }

        $customerModel = new Customer();
        if (!$customerModel->groupNameExists('Retail', null, $companyId)) {
            $customerModel->createGroup([
                'company_id' => $companyId,
                'name' => 'Retail',
                'discount_type' => 'none',
                'discount_value' => 0,
                'description' => 'Default customer tier',
            ]);
        }

        $expenseCategoryModel = new ExpenseCategory();
        if (!$expenseCategoryModel->nameExists('General', null, $companyId)) {
            $expenseCategoryModel->createCategory([
                'company_id' => $companyId,
                'name' => 'General',
                'description' => 'Default expense category',
            ]);
        }
    }

    private function platformTenantDefaults(?int $targetCompanyId = null): array
    {
        $defaults = [
            'currency' => default_currency_code(),
            'receipt_header' => 'Thank you for your business.',
            'receipt_footer' => 'Goods sold are subject to store policy.',
            'barcode_format' => 'CODE128',
            'multi_branch_enabled' => 'false',
            'email_low_stock_alerts_enabled' => 'true',
            'email_daily_summary_enabled' => 'true',
            'ops_email_recipient_scope' => 'business_and_team',
            'ops_email_additional_recipients' => '',
            'mail_host' => '',
            'mail_port' => (string) config('mail.port', 587),
            'mail_username' => '',
            'mail_password' => '',
            'mail_encryption' => (string) config('mail.encryption', 'tls'),
            'mail_from_address' => '',
            'mail_from_name' => '',
        ];

        $platformCompany = (new Company())->findBySlug((string) config('app.platform_internal_company_slug', 'platform-operations-internal'));
        if ($platformCompany === null) {
            return $defaults;
        }

        $platformCompanyId = (int) ($platformCompany['id'] ?? 0);
        if ($platformCompanyId <= 0 || $platformCompanyId === $targetCompanyId) {
            return $defaults;
        }

        $settings = (new Setting())->allAsMap($platformCompanyId);

        return [
            'currency' => normalize_billing_currency((string) ($settings['tenant_default_currency'] ?? $defaults['currency']), $defaults['currency']),
            'receipt_header' => (string) ($settings['tenant_default_receipt_header'] ?? $defaults['receipt_header']),
            'receipt_footer' => (string) ($settings['tenant_default_receipt_footer'] ?? $defaults['receipt_footer']),
            'barcode_format' => (string) ($settings['tenant_default_barcode_format'] ?? $defaults['barcode_format']),
            'multi_branch_enabled' => (string) ($settings['tenant_default_multi_branch_enabled'] ?? $defaults['multi_branch_enabled']),
            'email_low_stock_alerts_enabled' => (string) ($settings['tenant_default_email_low_stock_alerts_enabled'] ?? $defaults['email_low_stock_alerts_enabled']),
            'email_daily_summary_enabled' => (string) ($settings['tenant_default_email_daily_summary_enabled'] ?? $defaults['email_daily_summary_enabled']),
            'ops_email_recipient_scope' => (string) ($settings['tenant_default_ops_email_recipient_scope'] ?? $defaults['ops_email_recipient_scope']),
            'ops_email_additional_recipients' => (string) ($settings['tenant_default_ops_email_additional_recipients'] ?? $defaults['ops_email_additional_recipients']),
            'mail_host' => (string) ($settings['tenant_default_mail_host'] ?? $defaults['mail_host']),
            'mail_port' => (string) ($settings['tenant_default_mail_port'] ?? $defaults['mail_port']),
            'mail_username' => (string) ($settings['tenant_default_mail_username'] ?? $defaults['mail_username']),
            'mail_password' => (string) ($settings['tenant_default_mail_password'] ?? $defaults['mail_password']),
            'mail_encryption' => (string) ($settings['tenant_default_mail_encryption'] ?? $defaults['mail_encryption']),
            'mail_from_address' => (string) ($settings['tenant_default_mail_from_address'] ?? $defaults['mail_from_address']),
            'mail_from_name' => (string) ($settings['tenant_default_mail_from_name'] ?? $defaults['mail_from_name']),
        ];
    }

    private function createDefaultBranch(
        Branch $branchModel,
        int $companyId,
        string $name,
        string $code,
        string $email,
        string $phone,
        string $address
    ): int {
        $normalizedCode = strtoupper(trim($code));
        if ($normalizedCode === '') {
            $normalizedCode = 'MAIN';
        }

        $uniqueCode = $this->uniqueBranchCode($branchModel, $companyId, $normalizedCode);

        return $branchModel->createBranch([
            'company_id' => $companyId,
            'name' => trim($name) !== '' ? trim($name) : 'Main Branch',
            'code' => $uniqueCode,
            'address' => trim($address),
            'phone' => trim($phone),
            'email' => trim($email),
            'status' => 'active',
            'is_default' => 1,
        ]);
    }

    private function uniqueBranchCode(Branch $branchModel, int $companyId, string $baseCode): string
    {
        $code = strtoupper(trim($baseCode));
        $suffix = 1;

        while ($branchModel->codeExists($code, null, $companyId)) {
            $code = strtoupper(trim($baseCode)) . $suffix;
            $suffix++;
        }

        return $code;
    }
}
