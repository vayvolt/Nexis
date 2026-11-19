<?php

declare(strict_types=1);

namespace Nexis\Http;

/**
 * Path prefixes under /ext/ that skip CSRF (e.g. payment provider callbacks).
 * Plugins must verify authenticity themselves (HMAC / signature).
 */
final class CsrfExemptRegistry
{
    /** @var list<string> */
    private array $prefixes = [];

    public function register(string $pathPrefix): void
    {
        $path = '/' . trim($pathPrefix, '/');
        if ($path === '/' || !str_starts_with($path, '/ext/')) {
            return;
        }
        if (!in_array($path, $this->prefixes, true)) {
            $this->prefixes[] = $path;
        }
    }

    public function isExempt(string $path): bool
    {
        foreach ($this->prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function prefixes(): array
    {
        return $this->prefixes;
    }
}
