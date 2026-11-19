<?php

declare(strict_types=1);

namespace Nexis\I18n;

/**
 * JSON message catalogues: core resources/lang plus optional plugin overlays.
 */
final class Translator
{
    /** @var array<string, array<string, string>> */
    private array $catalogues = [];

    /** @var list<string> */
    private array $extraPaths = [];

    public function __construct(
        private string $langPath,
        private string $locale = 'de',
        private string $fallback = 'de',
    ) {
    }

    public function withLocale(string $locale): self
    {
        $clone = clone $this;
        $clone->locale = $locale;
        $clone->catalogues = [];

        return $clone;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    /**
     * Register an additional catalogue directory (e.g. plugin resources/lang).
     * Later paths override earlier keys for the same locale.
     */
    public function addPath(string $path): void
    {
        $path = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if ($path === '' || !is_dir($path)) {
            return;
        }
        if (in_array($path, $this->extraPaths, true)) {
            return;
        }
        $this->extraPaths[] = $path;
        $this->catalogues = [];
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return [$this->langPath, ...$this->extraPaths];
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    public function get(string $key, array $replace = [], ?string $default = null): string
    {
        $message = $this->lookup($key) ?? $default ?? $key;
        foreach ($replace as $name => $value) {
            $message = str_replace(':' . $name, (string) $value, $message);
        }

        return $message;
    }

    /**
     * Locales that ship with core admin UI catalogues.
     *
     * @return list<string>
     */
    public static function supportedUiLocales(): array
    {
        return ['de', 'en'];
    }

    /**
     * Map any content/user locale onto a catalogue that ships with core.
     */
    public static function normalizeUiLocale(string $locale): string
    {
        $locale = trim($locale);
        if ($locale === '') {
            return 'de';
        }
        if (in_array($locale, self::supportedUiLocales(), true)) {
            return $locale;
        }
        $short = str_contains($locale, '-') ? explode('-', $locale, 2)[0] : $locale;
        if (in_array($short, self::supportedUiLocales(), true)) {
            return $short;
        }

        return 'de';
    }

    private function lookup(string $key): ?string
    {
        foreach ($this->candidates($this->locale) as $locale) {
            $catalogue = $this->catalogue($locale);
            if (isset($catalogue[$key]) && $catalogue[$key] !== '') {
                return $catalogue[$key];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function candidates(string $locale): array
    {
        $out = [$locale];
        if (str_contains($locale, '-')) {
            $out[] = explode('-', $locale, 2)[0];
        }
        if (!in_array($this->fallback, $out, true)) {
            $out[] = $this->fallback;
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function catalogue(string $locale): array
    {
        if (isset($this->catalogues[$locale])) {
            return $this->catalogues[$locale];
        }

        $flat = [];
        foreach ($this->paths() as $dir) {
            $file = $dir . DIRECTORY_SEPARATOR . $locale . '.json';
            if (!is_file($file)) {
                continue;
            }
            try {
                $decoded = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }
            if (!is_array($decoded)) {
                continue;
            }
            foreach ($decoded as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $flat[$key] = $value;
                }
            }
        }

        return $this->catalogues[$locale] = $flat;
    }
}
