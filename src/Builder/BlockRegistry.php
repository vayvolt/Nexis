<?php

declare(strict_types=1);

namespace Nexis\Builder;

final class BlockRegistry
{
    /** @var array<string, BlockType> */
    private array $types = [];

    /**
     * @param iterable<BlockType> $types
     */
    public function __construct(iterable $types = [])
    {
        foreach ($types as $type) {
            $this->register($type);
        }
    }

    public function register(BlockType $type): void
    {
        $this->types[$type->type()] = $type;
    }

    public function get(string $type): ?BlockType
    {
        return $this->types[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /**
     * @return list<BlockType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }

    /**
     * @return list<array{type: string, label: string, allowsChildren: bool, propsSchema: mixed, defaultProps: array<string, mixed>}>
     */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->types as $type) {
            $out[] = [
                'type' => $type->type(),
                'label' => $type->label(),
                'allowsChildren' => $type->allowsChildren(),
                'propsSchema' => $type->propsSchema(),
                'defaultProps' => $type->defaultProps(),
            ];
        }

        return $out;
    }
}
