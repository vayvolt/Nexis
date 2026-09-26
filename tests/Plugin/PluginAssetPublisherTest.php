<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugin\PluginAssetPublisher;
use Nexis\Plugin\PluginManifest;
use PHPUnit\Framework\TestCase;

final class PluginAssetPublisherTest extends TestCase
{
    public function testPublishesResourcesAssetsUnderPublicAssetsPlugins(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-plugin-assets-' . bin2hex(random_bytes(4));
        $pluginDir = $root . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo';
        $assetsSrc = $pluginDir . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'assets';
        mkdir($assetsSrc, 0775, true);
        file_put_contents($assetsSrc . DIRECTORY_SEPARATOR . 'widget.css', '.w{}');
        file_put_contents($assetsSrc . DIRECTORY_SEPARATOR . 'widget.js', 'void 0;');

        try {
            $manifest = new PluginManifest(
                id: 'acme/demo',
                name: 'Demo',
                version: '1.0.0',
                compatibleCore: '^0.3',
                php: '>=8.4',
                autoloadNamespace: 'Acme\\Demo\\',
                providerClass: 'Acme\\Demo\\Provider',
                directory: $pluginDir,
                blocks: [],
                permissions: [],
                permissionRoles: [],
                slots: [],
                migrationsDir: $pluginDir . DIRECTORY_SEPARATOR . 'migrations',
                raw: [],
            );
            $publisher = new PluginAssetPublisher($root);
            $url = $publisher->publish($manifest);
            self::assertSame('/assets/plugins/acme/demo', $url);
            $css = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'plugins'
                . DIRECTORY_SEPARATOR . 'acme' . DIRECTORY_SEPARATOR . 'demo'
                . DIRECTORY_SEPARATOR . 'widget.css';
            self::assertFileExists($css);
            self::assertSame('.w{}', (string) file_get_contents($css));
        } finally {
            $this->removeTree($root);
        }
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
