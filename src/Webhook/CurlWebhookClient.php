<?php

declare(strict_types=1);

namespace Nexis\Webhook;

use Nexis\Security\HmacSignature;

final class CurlWebhookClient implements WebhookClient
{
    /**
     * @param array<string, mixed> $body
     */
    public function post(string $url, string $secret, array $body): int
    {
        $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $signature = HmacSignature::sign($json, $secret);
        if (!function_exists('curl_init')) {
            throw new WebhookDeliveryException('ext-curl fehlt für Webhooks.');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new WebhookDeliveryException('curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Nexis-Signature: ' . $signature,
                'User-Agent: Nexis-Webhook/0.2',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new WebhookDeliveryException('Webhook HTTP error: ' . $error, $status > 0 ? $status : null);
        }
        if ($status < 200 || $status >= 300) {
            throw new WebhookDeliveryException('Webhook HTTP status ' . $status, $status);
        }

        return $status;
    }
}
