<?php

declare(strict_types=1);

namespace Nexis\Install;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Standalone DE/EN strings for the web installer (before full app boot).
 */
final class InstallUi
{
    private const LOCALES = ['de', 'en'];

    /** @var array<string, string> */
    private array $messages;

    /**
     * @param array<string, string> $messages
     */
    private function __construct(
        public readonly string $locale,
        array $messages,
    ) {
        $this->messages = $messages;
    }

    public static function forLocale(string $locale, string $rootPath): self
    {
        if (!in_array($locale, self::LOCALES, true)) {
            $locale = 'de';
        }
        $path = rtrim($rootPath, '\\/') . DIRECTORY_SEPARATOR . 'resources'
            . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . 'install'
            . DIRECTORY_SEPARATOR . $locale . '.json';
        $raw = is_file($path) ? file_get_contents($path) : false;
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $messages = [];
        if (is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (is_string($key) && is_string($value)) {
                    $messages[$key] = $value;
                }
            }
        }
        if ($messages === [] && $locale !== 'de') {
            return self::forLocale('de', $rootPath);
        }

        return new self($locale, $messages);
    }

    /**
     * Persist ?lang= in session (caller must have started the install session).
     */
    public static function resolveLocale(ServerRequestInterface $request): string
    {
        $query = $request->getQueryParams();
        $fromQuery = isset($query['lang']) ? strtolower(trim((string) $query['lang'])) : '';
        if (in_array($fromQuery, self::LOCALES, true)) {
            $_SESSION['_nexis_install_lang'] = $fromQuery;

            return $fromQuery;
        }
        $fromSession = isset($_SESSION['_nexis_install_lang'])
            ? strtolower((string) $_SESSION['_nexis_install_lang'])
            : '';
        if (in_array($fromSession, self::LOCALES, true)) {
            return $fromSession;
        }

        return self::fromAcceptLanguage($request->getHeaderLine('Accept-Language'));
    }

    /**
     * @param array<string, scalar|null> $replace
     */
    public function get(string $key, array $replace = []): string
    {
        $text = $this->messages[$key] ?? $key;
        foreach ($replace as $name => $value) {
            $text = str_replace(':' . $name, (string) $value, $text);
        }

        return $text;
    }

    private static function fromAcceptLanguage(string $header): string
    {
        if ($header === '') {
            return 'de';
        }
        $best = 'de';
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
            $code = explode('-', $tag)[0];
            if (!in_array($code, self::LOCALES, true)) {
                continue;
            }
            if ($q > $bestQ) {
                $bestQ = $q;
                $best = $code;
            }
        }

        return $best;
    }
}
