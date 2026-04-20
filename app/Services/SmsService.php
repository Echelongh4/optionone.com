<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SmsMessageLog;

class SmsService
{
    private ?string $lastError = null;

    public function settings(): array
    {
        $defaultCountryCode = preg_replace('/\D+/', '', (string) platform_setting_value('platform_default_phone_country_code', '233')) ?: '';

        return [
            'enabled' => filter_var((string) platform_setting_value('platform_sms_enabled', 'false'), FILTER_VALIDATE_BOOLEAN),
            'provider' => strtolower(trim((string) platform_setting_value('platform_sms_provider', 'twilio'))),
            'account_sid' => trim((string) platform_setting_value('platform_sms_account_sid', '')),
            'auth_token' => trim((string) platform_setting_value('platform_sms_auth_token', '')),
            'from_number' => trim((string) platform_setting_value('platform_sms_from_number', '')),
            'messaging_service_sid' => trim((string) platform_setting_value('platform_sms_messaging_service_sid', '')),
            'daily_summary_enabled' => filter_var((string) platform_setting_value('platform_sms_daily_summary_enabled', 'false'), FILTER_VALIDATE_BOOLEAN),
            'billing_alerts_enabled' => filter_var((string) platform_setting_value('platform_sms_billing_alerts_enabled', 'false'), FILTER_VALIDATE_BOOLEAN),
            'default_country_code' => $defaultCountryCode,
        ];
    }

    public function configured(?array $settings = null): bool
    {
        $settings ??= $this->settings();

        if (empty($settings['enabled']) || (string) ($settings['provider'] ?? '') !== 'twilio') {
            return false;
        }

        return trim((string) ($settings['account_sid'] ?? '')) !== ''
            && trim((string) ($settings['auth_token'] ?? '')) !== ''
            && (
                trim((string) ($settings['from_number'] ?? '')) !== ''
                || trim((string) ($settings['messaging_service_sid'] ?? '')) !== ''
            );
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function send(
        string $toPhone,
        string $message,
        ?int $companyId = null,
        ?int $userId = null,
        ?array $settings = null
    ): bool {
        $this->lastError = null;
        $settings ??= $this->settings();
        $provider = strtolower(trim((string) ($settings['provider'] ?? 'twilio')));
        $normalizedPhone = normalize_phone_number($toPhone, (string) ($settings['default_country_code'] ?? ''));
        $senderIdentity = trim((string) ($settings['messaging_service_sid'] ?? '')) !== ''
            ? trim((string) $settings['messaging_service_sid'])
            : trim((string) ($settings['from_number'] ?? ''));

        $logId = (new SmsMessageLog())->createLog([
            'company_id' => $companyId,
            'user_id' => $userId,
            'provider' => $provider,
            'recipient_phone' => $normalizedPhone !== '' ? $normalizedPhone : trim($toPhone),
            'sender_identity' => $senderIdentity,
            'message_body' => $message,
            'status' => 'queued',
        ]);

        if ($normalizedPhone === '') {
            $this->lastError = 'SMS delivery requires phone numbers stored in E.164 format or a configured default country code.';
            if ($logId > 0) {
                (new SmsMessageLog())->updateLog($logId, [
                    'status' => 'failed',
                    'error_message' => $this->lastError,
                ]);
            }

            return false;
        }

        if (!$this->configured($settings)) {
            $this->lastError = 'SMS delivery is not configured. Save the provider credentials and sender identity first.';
            if ($logId > 0) {
                (new SmsMessageLog())->updateLog($logId, [
                    'status' => 'failed',
                    'error_message' => $this->lastError,
                ]);
            }

            return false;
        }

        try {
            $response = match ($provider) {
                'twilio' => $this->sendViaTwilio($normalizedPhone, $message, $settings),
                default => throw new \RuntimeException('Unsupported SMS provider configured.'),
            };
        } catch (\Throwable $exception) {
            $this->lastError = $exception->getMessage();
            if ($logId > 0) {
                (new SmsMessageLog())->updateLog($logId, [
                    'status' => 'failed',
                    'error_message' => $this->lastError,
                ]);
            }

            return false;
        }

        if ($logId > 0) {
            (new SmsMessageLog())->updateLog($logId, [
                'status' => in_array((string) ($response['status'] ?? ''), ['queued', 'accepted', 'sending', 'sent', 'delivered'], true)
                    ? 'sent'
                    : 'failed',
                'external_message_id' => (string) ($response['sid'] ?? ''),
                'error_message' => (string) ($response['error_message'] ?? ''),
                'payload' => $response,
                'sent_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return true;
    }

    public function sendMany(array $recipients, string $message, ?int $companyId = null, ?array $settings = null): int
    {
        $sent = 0;
        $settings ??= $this->settings();
        $uniqueRecipients = [];

        foreach ($recipients as $recipient) {
            $phone = normalize_phone_number(
                (string) ($recipient['phone'] ?? ''),
                (string) ($settings['default_country_code'] ?? '')
            );

            if ($phone === '' || isset($uniqueRecipients[$phone])) {
                continue;
            }

            $uniqueRecipients[$phone] = [
                'phone' => $phone,
                'user_id' => !empty($recipient['user_id']) ? (int) $recipient['user_id'] : null,
            ];
        }

        foreach ($uniqueRecipients as $recipient) {
            if ($this->send($recipient['phone'], $message, $companyId, $recipient['user_id'], $settings)) {
                $sent++;
            }
        }

        return $sent;
    }

    private function sendViaTwilio(string $toPhone, string $message, array $settings): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL support is required for Twilio SMS delivery.');
        }

        $accountSid = trim((string) ($settings['account_sid'] ?? ''));
        $authToken = trim((string) ($settings['auth_token'] ?? ''));
        $payload = [
            'To' => $toPhone,
            'Body' => trim($message),
        ];

        $messagingServiceSid = trim((string) ($settings['messaging_service_sid'] ?? ''));
        if ($messagingServiceSid !== '') {
            $payload['MessagingServiceSid'] = $messagingServiceSid;
        } else {
            $payload['From'] = normalize_phone_number(
                (string) ($settings['from_number'] ?? ''),
                (string) ($settings['default_country_code'] ?? '')
            );
        }

        $ch = curl_init('https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json');
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize the SMS delivery client.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => $accountSid . ':' . $authToken,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_POSTFIELDS => http_build_query($payload),
        ]);

        $rawResponse = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new \RuntimeException($curlError !== '' ? $curlError : 'The SMS provider request failed.');
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('The SMS provider returned an unreadable response.');
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = trim((string) ($decoded['message'] ?? $decoded['detail'] ?? 'SMS delivery failed.'));
            throw new \RuntimeException($message !== '' ? $message : 'SMS delivery failed.');
        }

        return $decoded;
    }
}
