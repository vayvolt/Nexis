<?php

declare(strict_types=1);

namespace Nexis\Content;

final readonly class NavLink
{
    /**
     * @param list<NavLink> $children
     */
    public function __construct(
        public string $label,
        public string $url,
        public array $children = [],
    ) {
    }

    /**
     * @return array{label: string, url: string, children: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'url' => $this->url,
            'children' => array_map(
                static fn (NavLink $child): array => $child->toArray(),
                $this->children,
            ),
        ];
    }
}
