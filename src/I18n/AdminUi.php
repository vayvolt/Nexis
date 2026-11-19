<?php

declare(strict_types=1);

namespace Nexis\I18n;

use Nexis\Auth\User;

/**
 * Resolves admin GUI strings for the signed-in user's ui_locale.
 */
final class AdminUi
{
    public function __construct(
        private Translator $translator,
    ) {
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    public function get(User $user, string $key, array $replace = [], ?string $default = null): string
    {
        return $this->translator->withLocale($this->localeFor($user))->get($key, $replace, $default);
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    public function getLocale(string $uiLocale, string $key, array $replace = [], ?string $default = null): string
    {
        return $this->translator->withLocale($this->normalize($uiLocale))->get($key, $replace, $default);
    }

    public function localeFor(User $user): string
    {
        return Translator::normalizeUiLocale($user->uiLocale);
    }

    private function normalize(string $locale): string
    {
        return Translator::normalizeUiLocale($locale);
    }
}
