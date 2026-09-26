<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\Auth\User;
use Nexis\Site\Site;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Active content locale for the admin ACP.
 *
 * Prefers the admin UI language when that language is an enabled site content locale,
 * so list titles/paths match the language switcher in the header.
 * Explicit ?locale= still wins (and is remembered).
 */
final class AdminContentLocale
{
    public const SESSION_KEY = 'admin.content_locale';

    public function __construct(
        private SessionStore $session,
    ) {
    }

    public function resolve(Site $site, ServerRequestInterface $request): string
    {
        $fromQuery = RequestInput::query($request, 'locale');
        if ($fromQuery !== '' && $this->isEnabled($site, $fromQuery)) {
            $this->remember($fromQuery);

            return $fromQuery;
        }

        $user = $request->getAttribute('user');
        if ($user instanceof User && $this->isEnabled($site, $user->uiLocale)) {
            $this->remember($user->uiLocale);

            return $user->uiLocale;
        }

        return $this->current($site);
    }

    public function current(Site $site, ?User $user = null): string
    {
        if ($user !== null && $this->isEnabled($site, $user->uiLocale)) {
            return $user->uiLocale;
        }

        $fromSession = $this->session->get(self::SESSION_KEY);
        if (is_string($fromSession) && $this->isEnabled($site, $fromSession)) {
            return $fromSession;
        }

        return $site->defaultLocale;
    }

    public function remember(string $locale): void
    {
        $this->session->set(self::SESSION_KEY, $locale);
    }

    /**
     * Build redirect target after switching locale in the ACP chrome.
     */
    public function redirectAfterSwitch(Site $site, string $basePath, string $locale, string $returnUrl): string
    {
        if (!$this->isEnabled($site, $locale)) {
            $locale = $site->defaultLocale;
        }
        $this->remember($locale);

        $path = $basePath . '/admin/pages';
        $query = ['locale' => $locale];

        $parts = parse_url($returnUrl);
        if (is_array($parts) && isset($parts['path']) && is_string($parts['path'])) {
            $candidate = $parts['path'];
            $adminPrefix = $basePath . '/admin';
            if ($candidate === $adminPrefix || str_starts_with($candidate, $adminPrefix . '/')) {
                $listPath = $this->listPathFor($candidate, $basePath);
                $path = $listPath;
                if ($listPath === $candidate && isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
                    parse_str($parts['query'], $existing);
                    foreach ($existing as $key => $value) {
                        if (!is_string($key) || $key === 'locale' || !is_scalar($value)) {
                            continue;
                        }
                        $query[$key] = (string) $value;
                    }
                } elseif (str_contains($listPath, '/menus') && isset($parts['query']) && is_string($parts['query'])) {
                    parse_str($parts['query'], $existing);
                    if (isset($existing['handle']) && is_scalar($existing['handle'])) {
                        $query['handle'] = (string) $existing['handle'];
                    }
                }
            }
        }

        $query['locale'] = $locale;

        return $path . '?' . http_build_query($query);
    }

    private function listPathFor(string $path, string $basePath): string
    {
        // Detail/builder URLs → locale list for that section.
        if (preg_match('#/admin/blog(?:/|$)#', $path) === 1) {
            return $basePath . '/admin/blog';
        }
        if (preg_match('#/admin/catalog(?:/|$)#', $path) === 1) {
            return $basePath . '/admin/catalog';
        }
        if (preg_match('#/admin/menus(?:/|$)#', $path) === 1) {
            return $basePath . '/admin/menus';
        }
        if (preg_match('#/admin/pages/[0-9a-f-]{36}#i', $path) === 1) {
            return $basePath . '/admin/pages';
        }
        if (preg_match('#/admin/pages(?:/new)?$#', $path) === 1) {
            return $path;
        }
        if (preg_match('#/admin/workshop(?:/|$)#', $path) === 1) {
            if (str_contains($path, '/services')) {
                return $basePath . '/admin/workshop/services';
            }

            return $basePath . '/admin/workshop';
        }
        if (preg_match('#/admin/(forms|redirects|consent)(?:/|$)#', $path) === 1) {
            return explode('?', $path)[0];
        }

        return $path;
    }

    private function isEnabled(Site $site, string $locale): bool
    {
        $match = $site->locale($locale);

        return $match !== null && $match->enabled;
    }
}
