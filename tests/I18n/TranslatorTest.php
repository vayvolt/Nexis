<?php

declare(strict_types=1);

namespace Nexis\Tests\I18n;

use Nexis\I18n\Translator;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    public function testFallsBackAndReplaces(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-lang-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'de.json', json_encode([
            'admin.hello' => 'Hallo :name',
        ], JSON_THROW_ON_ERROR));
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'en.json', json_encode([
            'admin.hello' => 'Hello :name',
        ], JSON_THROW_ON_ERROR));

        try {
            $de = new Translator($dir, 'de');
            self::assertSame('Hallo Ada', $de->get('admin.hello', ['name' => 'Ada']));

            $en = $de->withLocale('en');
            self::assertSame('Hello Ada', $en->get('admin.hello', ['name' => 'Ada']));
            self::assertSame('missing', $en->get('admin.missing', default: 'missing'));
            self::assertSame('en', Translator::normalizeUiLocale('en-US'));
            self::assertSame('de', Translator::normalizeUiLocale('fr'));
        } finally {
            @unlink($dir . DIRECTORY_SEPARATOR . 'de.json');
            @unlink($dir . DIRECTORY_SEPARATOR . 'en.json');
            @rmdir($dir);
        }
    }

    public function testPluginCatalogueOverlaysCore(): void
    {
        $core = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-lang-core-' . bin2hex(random_bytes(4));
        $plugin = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-lang-plugin-' . bin2hex(random_bytes(4));
        mkdir($core);
        mkdir($plugin);
        file_put_contents($core . DIRECTORY_SEPARATOR . 'de.json', json_encode([
            'admin.hello' => 'Kern',
            'admin.only_core' => 'Nur Kern',
        ], JSON_THROW_ON_ERROR));
        file_put_contents($plugin . DIRECTORY_SEPARATOR . 'de.json', json_encode([
            'admin.hello' => 'Plugin',
            'admin.plugin_only' => 'Nur Plugin',
        ], JSON_THROW_ON_ERROR));

        try {
            $t = new Translator($core, 'de');
            $t->addPath($plugin);
            self::assertSame('Plugin', $t->get('admin.hello'));
            self::assertSame('Nur Kern', $t->get('admin.only_core'));
            self::assertSame('Nur Plugin', $t->get('admin.plugin_only'));

            $en = $t->withLocale('en');
            self::assertSame('Plugin', $en->get('admin.hello'));
            self::assertSame('missing', $en->get('admin.missing', default: 'missing'));
            self::assertSame([$core, $plugin], $t->paths());
        } finally {
            @unlink($core . DIRECTORY_SEPARATOR . 'de.json');
            @unlink($plugin . DIRECTORY_SEPARATOR . 'de.json');
            @rmdir($core);
            @rmdir($plugin);
        }
    }
}
