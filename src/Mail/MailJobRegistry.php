<?php

declare(strict_types=1);

namespace Nexis\Mail;

use InvalidArgumentException;

final class MailJobRegistry
{
    /** @var array<string, MailJobHandler> */
    private array $handlers = [];

    public function register(string $type, MailJobHandler $handler): void
    {
        $type = trim($type);
        if ($type === '') {
            throw new InvalidArgumentException('Mail job type must not be empty.');
        }
        $this->handlers[$type] = $handler;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[trim($type)]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(string $type, array $payload): void
    {
        $type = trim($type);
        if (!isset($this->handlers[$type])) {
            throw new InvalidArgumentException('Unknown mail job type: ' . $type);
        }
        $this->handlers[$type]->handle($payload);
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->handlers);
    }
}
