<?php

declare(strict_types=1);

namespace Nexis\Theme;

use Nexis\Site\Site;
use RuntimeException;

final class ThemeService
{
    public function __construct(
        private ThemeDiscovery $discovery,
        private ThemeCatalog $catalog,
        private TokenResolver $tokens,
        private ThemeAssetPublisher $assets,
    ) {
    }

    public function sync(): void
    {
        $this->catalog->sync($this->discovery->discover());
    }

    public function activeManifest(Site $site): ThemeManifest
    {
        $key = $this->catalog->themeKeyForSite($site->id) ?? 'nexis/nexis';
        if ($key === 'nexis/werkstatt') {
            $key = 'nexis/nord';
        }
        $manifest = $this->discovery->find($key);
        if ($manifest === null) {
            $manifest = $this->discovery->find('nexis/nexis')
                ?? $this->discovery->find('nexis/atelier');
        }
        if ($manifest === null) {
            throw new RuntimeException('Kein Theme verfügbar.');
        }

        return $manifest;
    }

    /**
     * Theme-Tokens ohne Site-Overrides (Defaults aus tokens.json inkl. Vererbung).
     *
     * @return array<string, string>
     */
    public function defaultsFor(Site $site): array
    {
        return $this->tokens->resolve($this->activeManifest($site), []);
    }

    /**
     * @return array{
     *   manifest: ThemeManifest,
     *   tokens: array<string, string>,
     *   cssVariables: string,
     *   themeCssUrl: string,
     *   customCss: string,
     *   logoUrl: string,
     *   layout: array{
     *     navPosition: string,
     *     footerPosition: string,
     *     pageTitle: string,
     *     showSiteName: bool,
     *     logoInNav: bool,
     *     footerShowSiteName: bool,
     *     footerShowThemeName: bool
     *   }
     * }
     */
    public function resolveFor(Site $site, string $basePath = ''): array
    {
        $manifest = $this->activeManifest($site);
        $overrides = $this->catalog->overrides($site->id);
        $tokenMap = $this->tokens->resolve($manifest, $overrides);
        $published = $this->assets->publish($manifest, $this->tokens);
        $cssUrl = $basePath . $published['cssUrl'];
        $navPosition = $tokenMap['layout.nav.position'] ?? 'right';
        if (!in_array($navPosition, ['right', 'left', 'below'], true)) {
            $navPosition = 'right';
        }
        $footerPosition = $tokenMap['layout.footer.position'] ?? 'split';
        if (!in_array($footerPosition, ['split', 'center', 'stack', 'reverse'], true)) {
            $footerPosition = 'split';
        }
        $pageTitle = $tokenMap['layout.pageTitle'] ?? 'hide';
        if (!in_array($pageTitle, ['show', 'hide'], true)) {
            $pageTitle = 'hide';
        }

        return [
            'manifest' => $manifest,
            'tokens' => $tokenMap,
            'cssVariables' => $this->tokens->toCssVariables($tokenMap),
            'themeCssUrl' => $cssUrl,
            'customCss' => $this->catalog->customCss($site->id),
            'logoUrl' => $tokenMap['brand.logoUrl'] ?? '',
            'layout' => [
                'navPosition' => $navPosition,
                'footerPosition' => $footerPosition,
                'pageTitle' => $pageTitle,
                'showSiteName' => ($tokenMap['brand.showSiteName'] ?? 'true') !== 'false',
                'logoInNav' => ($tokenMap['brand.logoInNav'] ?? 'false') === 'true',
                'footerShowSiteName' => ($tokenMap['layout.footer.showSiteName'] ?? 'true') !== 'false',
                'footerShowThemeName' => ($tokenMap['layout.footer.showThemeName'] ?? 'true') !== 'false',
            ],
        ];
    }

    public function resolveTemplate(ThemeManifest $theme, string $name): string
    {
        foreach (array_reverse($this->tokens->inheritanceChain($theme)) as $manifest) {
            if (!isset($manifest->templates[$name])) {
                continue;
            }
            $path = $manifest->directory . DIRECTORY_SEPARATOR . str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $manifest->templates[$name],
            );
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Theme-Template fehlt: ' . $name);
    }
}
