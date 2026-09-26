<?php

declare(strict_types=1);

namespace Nexis\Http;

/**
 * Builds a Content-Security-Policy header from defaults plus optional contributors.
 */
final class ContentSecurityPolicy
{
    /** @var list<string> */
    private array $defaultSrc = ["'self'"];

    /** @var list<string> */
    private array $scriptSrc = ["'self'"];

    /** @var list<string> */
    private array $styleSrc = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];

    /** @var list<string> */
    private array $fontSrc = ["'self'", 'https://fonts.gstatic.com', 'data:'];

    /** @var list<string> */
    private array $imgSrc = ["'self'", 'data:', 'https:'];

    /** @var list<string> */
    private array $connectSrc = ["'self'"];

    /** @var list<string> */
    private array $frameSrc = ["'self'", 'https://www.openstreetmap.org'];

    /** @var list<string> */
    private array $objectSrc = ["'none'"];

    /** @var list<string> */
    private array $baseUri = ["'self'"];

    /** @var list<string> */
    private array $frameAncestors = ["'self'"];

    /** @var list<string> */
    private array $formAction = ["'self'"];

    public function allowScript(string ...$sources): self
    {
        $this->scriptSrc = $this->merge($this->scriptSrc, ...$sources);

        return $this;
    }

    public function allowConnect(string ...$sources): self
    {
        $this->connectSrc = $this->merge($this->connectSrc, ...$sources);

        return $this;
    }

    public function allowFrame(string ...$sources): self
    {
        $this->frameSrc = $this->merge($this->frameSrc, ...$sources);

        return $this;
    }

    public function allowImg(string ...$sources): self
    {
        $this->imgSrc = $this->merge($this->imgSrc, ...$sources);

        return $this;
    }

    public function toHeaderValue(): string
    {
        return implode('; ', [
            'default-src ' . implode(' ', $this->defaultSrc),
            'script-src ' . implode(' ', $this->scriptSrc),
            'style-src ' . implode(' ', $this->styleSrc),
            'font-src ' . implode(' ', $this->fontSrc),
            'img-src ' . implode(' ', $this->imgSrc),
            'connect-src ' . implode(' ', $this->connectSrc),
            'frame-src ' . implode(' ', $this->frameSrc),
            'object-src ' . implode(' ', $this->objectSrc),
            'base-uri ' . implode(' ', $this->baseUri),
            'frame-ancestors ' . implode(' ', $this->frameAncestors),
            'form-action ' . implode(' ', $this->formAction),
        ]);
    }

    /**
     * @param list<string> $existing
     * @return list<string>
     */
    private function merge(array $existing, string ...$extra): array
    {
        foreach ($extra as $source) {
            $source = trim($source);
            if ($source === '' || in_array($source, $existing, true)) {
                continue;
            }
            $existing[] = $source;
        }

        return $existing;
    }
}
