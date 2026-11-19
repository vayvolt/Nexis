<?php

declare(strict_types=1);

namespace Nexis\Webhook;

interface WebhookClient
{
    /**
     * @param array<string, mixed> $body
     *
     * @return int HTTP status code on success
     */
    public function post(string $url, string $secret, array $body): int;
}
