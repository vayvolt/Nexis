<?php

declare(strict_types=1);

namespace Nexis\Queue;

/**
 * Plugin-owned background worker for a named queue.
 */
interface JobHandler
{
    public function queue(): string;

    /**
     * Reserve and process up to $limit jobs. Returns number successfully completed.
     */
    public function processQueued(int $limit = 20): int;
}
