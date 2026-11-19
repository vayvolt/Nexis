<?php

declare(strict_types=1);

namespace Nexis\Auth;

use Nexis\I18n\PublicUi;
use Nexis\I18n\Translator;
use Nexis\Plugin\PluginKernel;
use Nexis\Site\Site;
use Psr\Container\ContainerInterface;

/**
 * Public header links for account login / register / dashboard.
 */
final class AccountNavLinks
{
    public function __construct(
        private AuthSettings $settings,
        private LoginService $login,
        private PublicUi $ui,
        private ContainerInterface $container,
    ) {
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    public function forSite(Site $site, string $locale, string $basePath, ?User $user = null): array
    {
        $user ??= $this->login->current();

        if ($user instanceof User) {
            $links = [[
                'label' => $this->ui->get($locale, 'public.nav.account'),
                'url' => $basePath . '/account',
            ]];
            if ($this->container->has(PluginKernel::class)) {
                /** @var PluginKernel $plugins */
                $plugins = $this->container->get(PluginKernel::class);
                foreach ($plugins->accountNavLinks([
                    'basePath' => $basePath,
                    'locale' => $locale,
                    'site' => $site,
                    'user' => $user,
                ]) as $extra) {
                    $links[] = $extra;
                }
            }

            return $links;
        }

        $links = [[
            'label' => $this->ui->get($locale, 'public.nav.login'),
            'url' => $basePath . '/account/login?lang=' . rawurlencode(Translator::normalizeUiLocale($locale)),
        ]];
        if ($this->settings->registrationEnabled($site->id)) {
            $links[] = [
                'label' => $this->ui->get($locale, 'public.nav.register'),
                'url' => $basePath . '/account/register?lang=' . rawurlencode(Translator::normalizeUiLocale($locale)),
            ];
        }

        return $links;
    }
}
