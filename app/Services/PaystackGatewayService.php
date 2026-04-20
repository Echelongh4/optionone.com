<?php

declare(strict_types=1);

namespace App\Services;

class PaystackGatewayService
{
    private ?string $lastError = null;

    public function settings(): array
    {
        $channels = platform_setting_value('saas_gateway_channels', '[]');
        $decodedChannels = json_decode((string) $channels, true);
        if (!is_array($decodedChannels)) {
            $decodedChannels = array_filter(array_map(
                static fn (string $channel): string => trim($channel),
                explode(',', (string) $channels)
            ));
        }

        return [
            'enabled' => filter_var((string) platform_setting_value('saas_gateway_enabled', 'false'), FILTER_VALIDATE_BOOLEAN),
            'provider' => strtolower(trim((string) platform_setting_value('saas_gateway_provider', 'paystack'))),
            'public_key' => trim((string) platform_setting_value('saas_gateway_public_key', '')),
            'secret_key' => trim((string) platform_setting_value('saas_gateway_secret_key', '')),
            'channels' => array_values(array_filter(array_map(
                static fn (mixed $channel): string => trim((string) $channel),
                $decodedChannels
            ))),
        ];
    }

    public function configured(?array $settings = null): bool
    {
        $settings ??= $this->settings();

        return !empty($settings['enabled'])
            && (string) ($settings['provider'] ?? '') === 'paystack'
            && trim((string) ($settings['secret_key'] ?? '')) !== '';
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function initializeTransaction(array $payload, ?array $settings = null): array
    {
        $settings ??= $this->settings();
        $this->assertConfigured($settings);

        $body = [
            'email' => trim((string) ($payload['email'] ?? '')),
            'amount' => (string) $this->amountInSubunits((float) ($payload['amount'] ?? 0)),
            'currency' => normalize_billing_currency((string) ($payload['currency'] ?? default_currency_code()), default_currency_code()),
            'reference' => trim((string) ($payload['reference'] ?? '')),
            'callback_url' => trim((string) ($payload['callback_url'] ?? '')),
            'metadata' => $payload['metadata'] ?? [],
        ];

        $channels = is_array($payload['channels'] ?? null)
            ? $payload['channels']
            : (array) ($settings['channels'] ?? []);
        $channels = array_values(array_filter(array_map(
            static fn (mixed $channel): string => trim((string) $channel),
            $channels
        )));

        if ($channels !== []) {
            $body['channels'] = $channels;
        }

        return $this->request('POST', '/transaction/initialize', $settings, $body);
    }

    public function verifyTransaction(string $reference, ?array $settings = null): array
    {
        $settings ??= $this->settings();
        $this->assertConfigured($settings);

        return $this->request(
            'GET',
            '/transaction/verify/' . rawurlencode(trim($reference)),
            $settings
        );
    }

    public function validWebhookSignature(string $payload, string $signature, ?array $settings = null): bool
    {
        $settings ??= $this->settings();
        $secretKey = trim((string) ($settings['secret_key'] ?? ''));

        if ($secretKey === '' || trim($signature) === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secretKey), trim($signature));
    }

    private function request(string $method, string $path, array $settings, ?array $body = null): array
    {
        $this->lastError = null;

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL support is required for Paystack payments.');
        }

        $ch = curl_init('https://api.paystack.co' . $path);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize the payment gateway client.');
        }

        $headers = [
            'Authorization: Bearer ' . trim((string) ($settings['secret_key'] ?? '')),
            'Cache-Control: no-cache',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];

        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);
        $rawResponse = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            throw new \RuntimeException($curlError !== '' ? $curlError : 'The payment gateway request failed.');
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('The payment gateway returned an unreadable response.');
        }

        if ($statusCode < 200 || $statusCode >= 300 || empty($decoded['status'])) {
            $message = trim((string) ($decoded['message'] ?? 'Payment gateway request failed.'));
            $this->lastError = $message;
            throw new \RuntimeException($message !== '' ? $message : 'Payment gateway request failed.');
        }

        return $decoded;
    }

    private function amountInSubunits(float $amount): int
    {
        return (int) round(max(0, $amount) * 100);
    }

    private function assertConfigured(array $settings): void
    {
        if (!$this->configured($settings)) {
            throw new \RuntimeException('Paystack is not configured on the platform billing desk.');
        }
    }
}
