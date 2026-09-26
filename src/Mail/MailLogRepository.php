<?php

declare(strict_types=1);

namespace Nexis\Mail;

interface MailLogRepository
{
    public function record(MailLogEntry $entry): void;

    /**
     * @return list<MailLogEntry>
     */
    public function recent(int $limit = 100, ?string $status = null): array;

    public function find(string $id): ?MailLogEntry;
}
