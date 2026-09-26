<?php

declare(strict_types=1);

namespace Nexis\Http;

use Nexis\Auth\User;
use Nexis\I18n\Translator;
use Nexis\Plugin\PluginKernel;
use Nexis\Site\Site;
use Nexis\Site\SiteRepository;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class ViewRenderer
{
    /** @var list<string> */
    private array $extraPaths = [];

    public function __construct(
        private string $basePath,
        private ?ContainerInterface $container = null,
    ) {
    }

    /**
     * Register a plugin (or other) views directory. Checked before the core base path.
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
        array_unshift($this->extraPaths, $path);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $name, array $data = [], ?string $layout = null): string
    {
        if ($layout !== null) {
            if ($layout === 'admin.layout') {
                $data = $this->enrichAdminLayout($data);
            }
            $data['content'] = $this->render($name, $data);

            return $this->render($layout, $data);
        }

        $file = $this->resolveFile($name);
        if ($file === null) {
            throw new RuntimeException('View fehlt: ' . $name);
        }

        $e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $render = static function (string $file, array $data, callable $e): void {
            extract($data, EXTR_SKIP);
            require $file;
        };

        ob_start();
        $render($file, $data, $e);

        return (string) ob_get_clean();
    }

    private function resolveFile(string $name): ?string
    {
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $name) . '.php';
        foreach ($this->extraPaths as $dir) {
            $file = $dir . DIRECTORY_SEPARATOR . $relative;
            if (is_file($file)) {
                return $file;
            }
        }
        $core = $this->basePath . DIRECTORY_SEPARATOR . $relative;

        return is_file($core) ? $core : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function enrichAdminLayout(array $data): array
    {
        $basePath = (string) ($data['basePath'] ?? '');
        if (!isset($data['site']) && $this->container !== null && $this->container->has(SiteRepository::class)) {
            /** @var SiteRepository $sites */
            $sites = $this->container->get(SiteRepository::class);
            $installed = $sites->installed();
            if ($installed instanceof Site) {
                $data['site'] = $installed;
            }
        }

        $site = $data['site'] ?? null;
        $contentLocale = '';
        if (is_string($data['locale'] ?? null) && $data['locale'] !== '') {
            $contentLocale = (string) $data['locale'];
        } elseif (is_string($data['contentLocale'] ?? null) && $data['contentLocale'] !== '') {
            $contentLocale = (string) $data['contentLocale'];
        } elseif ($site instanceof Site && $this->container !== null && $this->container->has(AdminContentLocale::class)) {
            /** @var AdminContentLocale $adminLocale */
            $adminLocale = $this->container->get(AdminContentLocale::class);
            $layoutUser = ($data['user'] ?? null) instanceof User ? $data['user'] : null;
            $contentLocale = $adminLocale->current($site, $layoutUser);
        } elseif ($site instanceof Site) {
            $contentLocale = $site->defaultLocale;
        }
        $data['contentLocale'] = $contentLocale;

        $uiLocale = is_string($data['uiLocale'] ?? null) && $data['uiLocale'] !== ''
            ? (string) $data['uiLocale']
            : 'de';
        $user = $data['user'] ?? null;
        if ($user instanceof User && $user->uiLocale !== '') {
            $uiLocale = $user->uiLocale;
        }
        if (!in_array($uiLocale, Translator::supportedUiLocales(), true)) {
            $uiLocale = str_contains($uiLocale, '-') ? explode('-', $uiLocale, 2)[0] : 'de';
            if (!in_array($uiLocale, Translator::supportedUiLocales(), true)) {
                $uiLocale = 'de';
            }
        }
        $data['uiLocale'] = $uiLocale;
        $data['activeAdminNav'] = $this->resolveActiveAdminNav($data);
        $data['adminViews'] = $this->basePath;

        if ($this->container !== null && $this->container->has(Translator::class)) {
            /** @var Translator $translator */
            $translator = $this->container->get(Translator::class)->withLocale($uiLocale);
            $data['translator'] = $translator;
            $data['t'] = static fn (string $key, array $replace = [], ?string $default = null): string => $translator->get($key, $replace, $default);
        } else {
            $data['t'] = static fn (string $key, array $replace = [], ?string $default = null): string => $default ?? $key;
        }

        if (!isset($data['pluginNav'])) {
            $data['pluginNav'] = $this->pluginSlotHtml(
                'admin.nav',
                $basePath,
                $contentLocale,
                $data['t'],
                $uiLocale,
                (string) $data['activeAdminNav'],
            );
        }
        if (!isset($data['dashboardContentSlots'])) {
            $data['dashboardContentSlots'] = $this->pluginSlotHtml(
                'admin.dashboard.content',
                $basePath,
                $contentLocale,
                $data['t'],
                $uiLocale,
            );
        }
        if (!isset($data['dashboardSiteSlots'])) {
            $data['dashboardSiteSlots'] = $this->pluginSlotHtml(
                'admin.dashboard.site',
                $basePath,
                $contentLocale,
                $data['t'],
                $uiLocale,
            );
        }
        if (!isset($data['pluginHead'])) {
            $data['pluginHead'] = $this->pluginSlotHtml(
                'admin.head',
                $basePath,
                $contentLocale,
                $data['t'],
                $uiLocale,
            );
        }
        if (!isset($data['accountPluginNav'])) {
            $data['accountPluginNav'] = $this->pluginSlotHtml(
                'account.nav',
                $basePath,
                $contentLocale,
                $data['t'],
                $uiLocale,
            );
        }

        return $data;
    }

    /**
     * Which primary admin nav item should appear active for the current screen.
     * Core routes only — plugins register via PluginKernel::registerAdminNavSection().
     *
     * @param array<string, mixed> $data
     */
    private function resolveActiveAdminNav(array $data): string
    {
        $basePath = (string) ($data['basePath'] ?? '');
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
        $page = $data['page'] ?? null;
        $pageObj = $page instanceof \Nexis\Content\Page ? $page : null;

        if ($this->container !== null && $this->container->has(PluginKernel::class)) {
            /** @var PluginKernel $plugins */
            $plugins = $this->container->get(PluginKernel::class);
            $pluginNav = $plugins->matchAdminNav($path, $basePath, $pageObj);
            if ($pluginNav !== null && $pluginNav !== '') {
                return $pluginNav;
            }
        }

        // Builder/edit for plugin-owned page types without a registered section: highlight nothing in core.
        if ($pageObj !== null && $pageObj->type !== \Nexis\Content\PageType::PAGE) {
            return '';
        }

        $checks = [
            'menus' => '/admin/menus',
            'media' => '/admin/media',
            'patterns' => '/admin/patterns',
            'globals' => '/admin/globals',
            'theme' => '/admin/theme',
            'settings' => '/admin/settings',
            'users' => '/admin/users',
            'plugins' => '/admin/plugins',
            'audit' => '/admin/audit',
            'mail' => '/admin/mail',
            'logs' => '/admin/logs',
            'health' => '/admin/health',
            'webhooks' => '/admin/webhooks',
            'export' => '/admin/export',
            'security' => '/admin/security',
            'about' => '/admin/about',
            'pages' => '/admin/pages',
        ];
        foreach ($checks as $key => $needle) {
            $full = rtrim($basePath . $needle, '/') ?: '/';
            $normalized = rtrim($path, '/') ?: '/';
            if ($normalized === $full || str_starts_with($normalized, $full . '/')) {
                return $key;
            }
        }

        return '';
    }

    /**
     * @param callable(string, array<string, scalar|null>, ?string): string $t
     * @return list<string>
     */
    private function pluginSlotHtml(
        string $slotName,
        string $basePath,
        string $contentLocale,
        callable $t,
        string $uiLocale,
        string $activeAdminNav = '',
    ): array {
        if ($this->container === null || !$this->container->has(PluginKernel::class)) {
            return [];
        }

        /** @var PluginKernel $plugins */
        $plugins = $this->container->get(PluginKernel::class);
        $out = [];
        foreach ($plugins->slots($slotName) as $slot) {
            $html = trim($slot->render([
                'basePath' => $basePath,
                'locale' => $contentLocale,
                't' => $t,
                'uiLocale' => $uiLocale,
                'activeAdminNav' => $activeAdminNav,
            ]));
            if ($html !== '') {
                $out[] = $html;
            }
        }

        return $out;
    }
}
