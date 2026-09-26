<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Auth\User;
use Nexis\Auth\UserRepository;
use Nexis\Http\AdminContentLocale;
use Nexis\Http\RequestInput;
use Nexis\Http\ResponseFactory;
use Nexis\I18n\Translator;
use Nexis\Site\SiteRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UiLocaleAdminController
{
    public function __construct(
        private ResponseFactory $responses,
        private UserRepository $users,
        private SiteRepository $sites,
        private AdminContentLocale $contentLocale,
    ) {
    }

    public function switch(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute('user');
        if (!$user instanceof User) {
            return $this->responses->html('Unauthorized', 401);
        }
        $basePath = (string) $request->getAttribute('base_path', '');
        $locale = strtolower(trim(RequestInput::query($request, 'locale', $user->uiLocale)));
        if (!in_array($locale, Translator::supportedUiLocales(), true)) {
            $locale = 'de';
        }

        $this->users->updateUiLocale($user->id, $locale);

        $site = $this->sites->installed();
        if ($site !== null && $site->locale($locale) !== null && $site->locale($locale)->enabled) {
            $this->contentLocale->remember($locale);
        }

        $return = RequestInput::query($request, 'return', $basePath . '/admin');
        $parts = parse_url($return);
        $path = is_array($parts) && isset($parts['path']) && is_string($parts['path'])
            ? $parts['path']
            : $basePath . '/admin';
        $adminPrefix = $basePath . '/admin';
        if ($path !== $adminPrefix && !str_starts_with($path, $adminPrefix . '/')) {
            $path = $basePath . '/admin';
        }
        $query = '';
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $query = '?' . $parts['query'];
        }

        return $this->responses->redirect($path . $query);
    }
}
