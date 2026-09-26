<?php

declare(strict_types=1);

namespace Nexis\Builder;

final class DocumentHash
{
    /**
     * @param array<string, mixed> $document
     */
    public static function of(array $document): string
    {
        return hash('sha256', json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
