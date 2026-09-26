<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Support\Uuid;
use InvalidArgumentException;

final class BlockDocument
{
    public const SCHEMA_VERSION = 1;

    /**
     * @param array<string, mixed> $root
     */
    public function __construct(
        public private(set) int $schemaVersion,
        public private(set) array $root,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['schemaVersion'], $data['root']) || !is_int($data['schemaVersion']) || !is_array($data['root'])) {
            throw new InvalidArgumentException('Ungültiges Blockdokument.');
        }

        return new self($data['schemaVersion'], $data['root']);
    }

    public static function empty(): self
    {
        return new self(self::SCHEMA_VERSION, [
            'id' => Uuid::v7(),
            'type' => 'core/section',
            'props' => [
                'width' => 'wide',
                'padding' => 'lg',
            ],
            'children' => [],
        ]);
    }

    /**
     * @return array{schemaVersion: int, root: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => $this->schemaVersion,
            'root' => $this->root,
        ];
    }

    public function hash(): string
    {
        return DocumentHash::of($this->toArray());
    }
}
