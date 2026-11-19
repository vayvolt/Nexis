<?php

declare(strict_types=1);

namespace Nexis\Theme;

use RuntimeException;

final class ThemeAssetPublisher
{
    public function __construct(
        private string $projectRoot,
    ) {
    }

    /**
     * Publishes theme CSS (including inherited assets) into assets/themes/{hash}/.
     *
     * @return array{cssUrl: string, hash: string}
     */
    public function publish(ThemeManifest $theme, TokenResolver $tokens): array
    {
        $chain = $tokens->inheritanceChain($theme);
        $cssParts = [];
        foreach ($chain as $manifest) {
            foreach ($manifest->assets as $relative) {
                $file = $manifest->directory . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
                if (!is_file($file)) {
                    continue;
                }
                $content = file_get_contents($file);
                if (is_string($content) && $content !== '') {
                    $cssParts[] = "/* {$manifest->id}:{$relative} */\n" . $content;
                }
            }
        }
        $bundle = implode("\n\n", $cssParts);
        $hash = substr(hash('sha256', $theme->id . "\n" . $bundle), 0, 12);
        $dir = $this->projectRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . $hash;
        $target = $dir . DIRECTORY_SEPARATOR . 'theme.css';
        if (is_file($target) && filesize($target) === strlen($bundle)) {
            return [
                'cssUrl' => '/assets/themes/' . $hash . '/theme.css',
                'hash' => $hash,
            ];
        }
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Theme-Assets konnten nicht geschrieben werden.');
        }
        if (!is_file($target) || file_get_contents($target) !== $bundle) {
            file_put_contents($target, $bundle);
        }

        return [
            'cssUrl' => '/assets/themes/' . $hash . '/theme.css',
            'hash' => $hash,
        ];
    }
}
