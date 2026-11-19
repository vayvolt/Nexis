<?php

declare(strict_types=1);

namespace Nexis\Content;

use DateTimeImmutable;
use InvalidArgumentException;

final class PageEditorialItem
{
    public const KIND_COMMENT = 'comment';
    public const KIND_TASK = 'task';
    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';

    public const MAX_BODY = 4000;

    public function __construct(
        public private(set) PageEditorialItemId $id,
        public private(set) PageId $pageId,
        public private(set) string $kind,
        public private(set) string $body,
        public private(set) string $status,
        public private(set) ?string $createdBy,
        public private(set) DateTimeImmutable $createdAt,
        public private(set) DateTimeImmutable $updatedAt,
        public private(set) ?DateTimeImmutable $resolvedAt = null,
        public private(set) ?string $resolvedBy = null,
        public private(set) ?string $authorName = null,
    ) {
        if (!in_array($this->kind, [self::KIND_COMMENT, self::KIND_TASK], true)) {
            throw new InvalidArgumentException('Ungültiger Redaktions-Typ.');
        }
        if (!in_array($this->status, [self::STATUS_OPEN, self::STATUS_DONE], true)) {
            throw new InvalidArgumentException('Ungültiger Redaktions-Status.');
        }
        $body = trim($this->body);
        if ($body === '') {
            throw new InvalidArgumentException('Text darf nicht leer sein.');
        }
        if (mb_strlen($body) > self::MAX_BODY) {
            throw new InvalidArgumentException('Text ist zu lang.');
        }
        $this->body = $body;
        if ($this->kind === self::KIND_COMMENT) {
            $this->status = self::STATUS_OPEN;
            $this->resolvedAt = null;
            $this->resolvedBy = null;
        }
    }

    public bool $isTask {
        get => $this->kind === self::KIND_TASK;
    }

    public bool $isDone {
        get => $this->status === self::STATUS_DONE;
    }

    public function markDone(string $userId, DateTimeImmutable $at): void
    {
        if (!$this->isTask) {
            throw new InvalidArgumentException('Nur Aufgaben können erledigt werden.');
        }
        $this->status = self::STATUS_DONE;
        $this->resolvedAt = $at;
        $this->resolvedBy = $userId;
        $this->updatedAt = $at;
    }

    public function reopen(DateTimeImmutable $at): void
    {
        if (!$this->isTask) {
            throw new InvalidArgumentException('Nur Aufgaben können wieder geöffnet werden.');
        }
        $this->status = self::STATUS_OPEN;
        $this->resolvedAt = null;
        $this->resolvedBy = null;
        $this->updatedAt = $at;
    }
}
