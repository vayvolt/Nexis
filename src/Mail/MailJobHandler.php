<?php

declare(strict_types=1);

namespace Nexis\Mail;

/**
 * Plugin-registered handler for a mail job `type` (payload key).
 */
interface MailJobHandler
{
    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void;
}
