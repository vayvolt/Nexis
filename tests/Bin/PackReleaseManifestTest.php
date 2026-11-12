<?php

declare(strict_types=1);

namespace Nexis\Tests\Bin;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PackReleaseManifestTest extends TestCase
{
    /** @var array{plugins: list<string>, themes: list<string>, forbidden: list<string>} */
    private array $manifest;

    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
        $loaded = require $this->root . '/bin/pack-release-manifest.php';
        self::assertIsArray($loaded);
        self::assertArrayHasKey('plugins', $loaded);
        self::assertArrayHasKey('themes', $loaded);
        self::assertArrayHasKey('forbidden', $loaded);
        self::assertIsList($loaded['plugins']);
        self::assertIsList($loaded['themes']);
        self::assertIsList($loaded['forbidden']);
        /** @var array{plugins: list<string>, themes: list<string>, forbidden: list<string>} $manifest */
        $manifest = $loaded;
        $this->manifest = $manifest;
        require_once $this->root . '/bin/pack-release-lib.php';
    }

    public function testForbiddenPackagesNeverOnShipLists(): void
    {
        foreach ($this->manifest['forbidden'] as $forbidden) {
            if (str_starts_with($forbidden, 'plugins/')) {
                $id = substr($forbidden, strlen('plugins/'));
                self::assertNotContains(
                    $id,
                    $this->manifest['plugins'],
                    "Forbidden plugin {$id} must not be on the ship list",
                );
            }
            if (str_starts_with($forbidden, 'themes/')) {
                $id = substr($forbidden, strlen('themes/'));
                self::assertNotContains(
                    $id,
                    $this->manifest['themes'],
                    "Forbidden theme {$id} must not be on the ship list",
                );
            }
        }
    }

    public function testKnownRepoOnlyPackagesStayForbidden(): void
    {
        foreach ([
            'themes/nexis/nord',
            'plugins/acme',
            'plugins/nexis/workshop',
            'plugins/nexis/experiments',
        ] as $mustStayOut) {
            self::assertContains(
                $mustStayOut,
                $this->manifest['forbidden'],
                "{$mustStayOut} must remain pack-forbidden",
            );
        }
    }

    public function testShipEntriesExistInRepo(): void
    {
        foreach ($this->manifest['plugins'] as $plugin) {
            $path = $this->root . '/plugins/' . $plugin;
            self::assertDirectoryExists($path, "Ship plugin missing: {$plugin}");
            self::assertFileExists($path . '/plugin.json');
        }
        foreach ($this->manifest['themes'] as $theme) {
            $path = $this->root . '/themes/' . $theme;
            self::assertDirectoryExists($path, "Ship theme missing: {$theme}");
            self::assertFileExists($path . '/theme.json');
        }
    }

    public function testPackPathsExcludeForbiddenTrees(): void
    {
        $paths = packPaths($this->root, $this->manifest);
        $joined = "\n" . implode("\n", $paths) . "\n";

        foreach ($this->manifest['forbidden'] as $forbidden) {
            self::assertStringNotContainsString(
                "\n{$forbidden}\n",
                $joined,
                "packPaths must not include forbidden {$forbidden}",
            );
            self::assertStringNotContainsString(
                "\n{$forbidden}/",
                $joined,
                "packPaths must not include children of forbidden {$forbidden}",
            );
        }

        self::assertNotContains('plugins', $paths);
        self::assertNotContains('themes', $paths);
        self::assertContains('plugins/nexis/forms', $paths);
        self::assertContains('themes/nexis/nexis', $paths);
    }

    public function testAssertStagingCleanRejectsForbiddenLeak(): void
    {
        $tmp = sys_get_temp_dir() . '/nexis-pack-assert-' . bin2hex(random_bytes(4));
        mkdir($tmp . '/themes/nexis/nord', 0775, true);
        file_put_contents($tmp . '/themes/nexis/nord/theme.json', '{}');

        try {
            assertPackStagingClean($tmp, $this->manifest['forbidden']);
            self::fail('Expected RuntimeException for nord leak');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('themes/nexis/nord', $e->getMessage());
        } finally {
            removeTree($tmp);
        }
    }
}
