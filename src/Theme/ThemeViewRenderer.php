<?php

declare(strict_types=1);

namespace Nexis\Theme;

use Nexis\Content\GlobalContent;
use Nexis\Plugin\PluginKernel;
use Nexis\Site\Site;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class ThemeViewRenderer
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function renderFile(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new RuntimeException('Theme-View fehlt: ' . $file);
        }

        $data['slot'] = function (string $name) use ($data): string {
            return $this->renderSlot($name, $data);
        };

        if (str_ends_with(strtolower($file), '.twig')) {
            return $this->renderTwig($file, $data);
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

    /**
     * @param array<string, mixed> $context
     */
    public function renderSlot(string $name, array $context = []): string
    {
        $html = '';
        if ($name === 'theme.head') {
            $html .= $this->coreHeadAssets($context);
        }
        $html .= $this->coreGlobalContent($name, $context);
        $plugins = $this->plugins();
        if ($plugins === null) {
            return $html;
        }
        foreach ($plugins->slots($name) as $slot) {
            try {
                $html .= $slot->render($context);
            } catch (\Throwable) {
                // Slot failures must not break the page.
            }
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function coreGlobalContent(string $name, array $context): string
    {
        if ($name !== 'theme.header-actions' && $name !== 'theme.footer') {
            return '';
        }
        $site = $context['site'] ?? null;
        $locale = (string) ($context['locale'] ?? '');
        if (!$site instanceof Site || $locale === '') {
            return '';
        }
        try {
            if (!$this->container->has(GlobalContent::class)) {
                return '';
            }
            /** @var GlobalContent $globals */
            $globals = $this->container->get(GlobalContent::class);
        } catch (\Throwable) {
            return '';
        }
        $basePath = (string) ($context['basePath'] ?? '');
        if ($name === 'theme.header-actions') {
            return $globals->renderHeaderCta($site, $locale, $basePath);
        }

        return $globals->renderFooterTeaser($site, $locale);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function coreHeadAssets(array $context): string
    {
        $base = htmlspecialchars((string) ($context['basePath'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<link rel="stylesheet" href="' . $base . '/assets/core/faq.css">' . "\n"
            . '<link rel="stylesheet" href="' . $base . '/assets/core/location.css">' . "\n"
            . '<link rel="stylesheet" href="' . $base . '/assets/core/content-blocks.css">' . "\n"
            . (string) ($context['jsonLdScript'] ?? '');
    }

    private function plugins(): ?PluginKernel
    {
        try {
            if (!$this->container->has(PluginKernel::class)) {
                return null;
            }

            return $this->container->get(PluginKernel::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderTwig(string $file, array $data): string
    {
        $dir = dirname($file);
        $name = basename($file);
        $loader = new FilesystemLoader($dir);
        $twig = new Environment($loader, [
            'cache' => false,
            'autoescape' => 'html',
            'strict_variables' => false,
        ]);
        $twig->addFunction(new TwigFunction(
            'slot',
            function (string $slotName) use ($data): string {
                return $this->renderSlot($slotName, $data);
            },
            ['is_safe' => ['html']],
        ));
        if ($this->container->has(TwigExtensionRegistry::class)) {
            foreach ($this->container->get(TwigExtensionRegistry::class)->all() as $extension) {
                $twig->addExtension($extension);
            }
        }

        return $twig->render($name, $data);
    }
}
