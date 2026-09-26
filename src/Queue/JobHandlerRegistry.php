<?php

declare(strict_types=1);

namespace Nexis\Queue;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;

final class JobHandlerRegistry
{
    /** @var array<string, JobHandler> */
    private array $handlers = [];

    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function register(string $queue, JobHandler $handler): void
    {
        $queue = trim($queue);
        if ($queue === '') {
            throw new InvalidArgumentException('Job queue name must not be empty.');
        }
        if (isset($this->handlers[$queue])) {
            $this->logger->warning('Job handler skipped, queue already registered', [
                'queue' => $queue,
            ]);

            return;
        }
        $this->handlers[$queue] = $handler;
    }

    public function has(string $queue): bool
    {
        return isset($this->handlers[trim($queue)]);
    }

    /**
     * @return list<string>
     */
    public function queues(): array
    {
        return array_keys($this->handlers);
    }

    public function processAll(int $limit = 20): int
    {
        $done = 0;
        foreach ($this->handlers as $handler) {
            $done += $handler->processQueued($limit);
        }

        return $done;
    }
}
