<?php

declare(strict_types=1);

namespace Nexis\Theme;

use Twig\Extension\ExtensionInterface;

final class TwigExtensionRegistry
{
    /** @var list<ExtensionInterface> */
    private array $extensions = [];

    public function register(ExtensionInterface $extension): void
    {
        $this->extensions[] = $extension;
    }

    /**
     * @return list<ExtensionInterface>
     */
    public function all(): array
    {
        return $this->extensions;
    }
}
