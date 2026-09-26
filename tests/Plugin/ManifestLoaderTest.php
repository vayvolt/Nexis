<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugin\ManifestLoader;
use PHPUnit\Framework\TestCase;

final class ManifestLoaderTest extends TestCase
{
    public function testLoadsFormsManifest(): void
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . 'forms';
        $manifest = (new ManifestLoader())->load($root);
        self::assertSame('nexis/forms', $manifest->id);
        self::assertSame('Vayvolt', $manifest->author);
        self::assertSame('Nexis\\Plugins\\Forms\\FormsServiceProvider', $manifest->providerClass);
        self::assertContains('nexis/forms/contact', $manifest->blocks);
    }

    public function testLoadsCatalogManifest(): void
    {
        $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'nexis' . DIRECTORY_SEPARATOR . 'catalog';
        $manifest = (new ManifestLoader())->load($root);
        self::assertSame('nexis/catalog', $manifest->id);
        self::assertSame('Vayvolt', $manifest->author);
        self::assertContains('nexis/catalog/products', $manifest->blocks);
        self::assertContains('catalog.manage', $manifest->permissions);
    }

    public function testParsesRequiresPlugins(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-manifest-' . bin2hex(random_bytes(4));
        mkdir($dir, 0775, true);
        try {
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'plugin.json', json_encode([
                'id' => 'acme/shop',
                'name' => 'Shop',
                'version' => '1.0.0',
                'compatibleCore' => '^0.3',
                'autoload' => 'Acme\\Shop\\',
                'provider' => 'Acme\\Shop\\ShopServiceProvider',
                'requires' => ['plugins' => ['nexis/catalog', 'nexis/forms']],
            ], JSON_THROW_ON_ERROR));
            $manifest = (new ManifestLoader())->load($dir);
            self::assertSame(['nexis/catalog', 'nexis/forms'], $manifest->requiredPlugins);
        } finally {
            array_map('unlink', glob($dir . DIRECTORY_SEPARATOR . '*') ?: []);
            rmdir($dir);
        }
    }
}
