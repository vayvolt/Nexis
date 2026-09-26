<?php

declare(strict_types=1);

namespace Nexis\Support;

use InvalidArgumentException;

readonly class UuidId
{
    public function __construct(public string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidArgumentException('Ungültige UUID v7.');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
