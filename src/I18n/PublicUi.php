<?php

declare(strict_types=1);

namespace Nexis\I18n;

use Nexis\Http\SessionStore;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves public / front-office GUI strings for a content locale.
 */
final class PublicUi
{
    private const SESSION_KEY = '_nexis_ui_locale';

    public function __construct(
        private Translator $translator,
        private ?SessionStore $session = null,
    ) {
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    public function get(string $locale, string $key, array $replace = [], ?string $default = null): string
    {
        return $this->translator($locale)->get($key, $replace, $default);
    }

    public function translator(string $locale): Translator
    {
        return $this->translator->withLocale(Translator::normalizeUiLocale($locale));
    }

    /**
     * Labels for theme shell chrome (nav aria, locale switcher).
     *
     * @return array{ariaPrimary: string, ariaLocales: string, ariaFooter: string, unavailable: string}
     */
    public function themeChrome(string $locale): array
    {
        return [
            'ariaPrimary' => $this->get($locale, 'theme.nav.primary'),
            'ariaLocales' => $this->get($locale, 'theme.nav.locales'),
            'ariaFooter' => $this->get($locale, 'theme.nav.footer'),
            'unavailable' => $this->get($locale, 'theme.locale.unavailable'),
        ];
    }

    /**
     * Prefer guest UI locale for middleware / anonymous HTTP errors.
     */
    public function fromRequest(ServerRequestInterface $request, ?string $fallback = null): Translator
    {
        return $this->translator($this->localeFromRequest($request, $fallback));
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    public function getRequest(ServerRequestInterface $request, string $key, array $replace = [], ?string $default = null): string
    {
        return $this->fromRequest($request)->get($key, $replace, $default);
    }

    /**
     * Persist a guest UI locale (e.g. from the content locale of the current page).
     */
    public function rememberLocale(string $locale): void
    {
        $picked = $this->matchSupported($locale);
        if ($picked !== null) {
            $this->session?->set(self::SESSION_KEY, $picked);
        }
    }

    /**
     * Guest UI locale: ?lang|ui|locale → session → Accept-Language → fallback → de.
     */
    public function localeFromRequest(ServerRequestInterface $request, ?string $fallback = null): string
    {
        $candidates = [];
        $query = $request->getQueryParams();
        foreach (['lang', 'ui', 'locale'] as $param) {
            $raw = $query[$param] ?? null;
            if (is_string($raw)) {
                $candidates[] = $raw;
            }
        }
        $body = $request->getParsedBody();
        if (is_array($body)) {
            foreach (['lang', 'ui', 'locale'] as $param) {
                $raw = $body[$param] ?? null;
                if (is_string($raw)) {
                    $candidates[] = $raw;
                }
            }
        }
        foreach ($candidates as $raw) {
            $picked = $this->matchSupported(strtolower(trim($raw)));
            if ($picked !== null) {
                $this->session?->set(self::SESSION_KEY, $picked);

                return $picked;
            }
        }

        $fromSession = $this->session?->get(self::SESSION_KEY);
        if (is_string($fromSession)) {
            $picked = $this->matchSupported($fromSession);
            if ($picked !== null) {
                return $picked;
            }
        }

        $fromHeader = $this->fromAcceptLanguage($request->getHeaderLine('Accept-Language'));
        if ($fromHeader !== null) {
            return $fromHeader;
        }

        if ($fallback !== null) {
            $picked = $this->matchSupported($fallback);
            if ($picked !== null) {
                return $picked;
            }
        }

        return 'de';
    }

    private function matchSupported(string $code): ?string
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            return null;
        }
        if (in_array($code, Translator::supportedUiLocales(), true)) {
            return $code;
        }
        $short = explode('-', $code, 2)[0];
        if (in_array($short, Translator::supportedUiLocales(), true)) {
            return $short;
        }

        return null;
    }

    private function fromAcceptLanguage(string $header): ?string
    {
        if ($header === '') {
            return null;
        }
        $best = null;
        $bestQ = -1.0;
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $bits = explode(';', $part);
            $tag = strtolower(trim($bits[0]));
            $q = 1.0;
            if (isset($bits[1]) && preg_match('/q\s*=\s*([0-9.]+)/i', $bits[1], $m) === 1) {
                $q = (float) $m[1];
            }
            $picked = $this->matchSupported($tag);
            if ($picked === null) {
                continue;
            }
            if ($q > $bestQ) {
                $bestQ = $q;
                $best = $picked;
            }
        }

        return $best;
    }
}
