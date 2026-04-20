<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

class MailService
{
    private ?string $lastError = null;

    public function settings(?int $companyId = null): array
    {
        return $this->resolveSettings($companyId, true);
    }

    public function globalSettings(): array
    {
        return $this->resolveSettings(null, false);
    }

    public function configured(?array $settings = null): bool
    {
        $settings ??= $this->settings();

        return class_exists(PHPMailer::class)
            && $settings['host'] !== ''
            && $settings['from_address'] !== '';
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?array $settings = null
    ): bool {
        $this->lastError = null;
        $settings ??= $this->settings();

        if (!$this->configured($settings)) {
            $this->lastError = 'Mail is not configured. Save the SMTP host and sender email first.';
            return false;
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $settings['host'];
            $mail->Port = max(1, (int) $settings['port']);

            $username = $settings['username'];
            $password = $settings['password'];

            if ($username !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $username;
                $mail->Password = $password;
            } else {
                $mail->SMTPAuth = false;
                $mail->Username = '';
                $mail->Password = '';
            }

            if ($settings['encryption'] === PHPMailer::ENCRYPTION_STARTTLS) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($settings['encryption'] === PHPMailer::ENCRYPTION_SMTPS) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom(
                $settings['from_address'],
                $settings['from_name'] !== '' ? $settings['from_name'] : (string) config('app.name', 'NovaPOS')
            );
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody !== null && $textBody !== '' ? $textBody : strip_tags($htmlBody);

            return $mail->send();
        } catch (Throwable $exception) {
            $this->lastError = $exception->getMessage();
            return false;
        }
    }

    public function sendMany(
        array $recipients,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        ?array $settings = null
    ): int
    {
        $sent = 0;
        $settings ??= $this->settings();

        foreach ($recipients as $recipient) {
            $email = trim((string) ($recipient['email'] ?? ''));
            if ($email === '') {
                continue;
            }

            $delivered = $this->send(
                toEmail: $email,
                toName: trim((string) ($recipient['name'] ?? $email)),
                subject: $subject,
                htmlBody: $htmlBody,
                textBody: $textBody,
                settings: $settings
            );

            if ($delivered) {
                $sent++;
            }
        }

        return $sent;
    }

    private function resolveSettings(?int $companyId, bool $preferCompanySettings): array
    {
        $settingsModel = null;
        if ($preferCompanySettings) {
            $companyId ??= current_company_id();
            if ($companyId !== null && $companyId > 0) {
                $settingsModel = new Setting();
            }
        }

        $setting = static function (string $key, mixed $default) use ($settingsModel, $companyId): mixed {
            if (!$settingsModel instanceof Setting || $companyId === null || $companyId <= 0) {
                return $default;
            }

            return $settingsModel->get($key, $default, $companyId);
        };

        $host = strtolower(trim((string) $setting('mail_host', config('mail.host', ''))));
        $encryption = strtolower(trim((string) $setting('mail_encryption', config('mail.encryption', 'tls'))));
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }

        return [
            'host' => $host,
            'port' => (int) $setting('mail_port', (string) config('mail.port', 587)),
            'username' => trim((string) $setting('mail_username', config('mail.username', ''))),
            'password' => $this->normalizePassword(
                $host,
                (string) $setting('mail_password', config('mail.password', ''))
            ),
            'encryption' => $encryption,
            'from_address' => trim((string) $setting(
                'mail_from_address',
                config('mail.from_address', (string) $setting('business_email', ''))
            )),
            'from_name' => trim((string) $setting(
                'mail_from_name',
                config('mail.from_name', (string) $setting('business_name', config('app.name', 'NovaPOS')))
            )),
        ];
    }

    private function normalizePassword(string $host, string $password): string
    {
        $password = trim($password);

        if ($password === '') {
            return '';
        }

        if (str_contains($host, 'gmail.com')) {
            return preg_replace('/\s+/', '', $password) ?? $password;
        }

        return $password;
    }
}
