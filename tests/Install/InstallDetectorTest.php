<?php

declare(strict_types=1);

namespace Nexis\Tests\Install;

use Nexis\Install\InstallDetector;
use PHPUnit\Framework\TestCase;

final class InstallDetectorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-install-' . bin2hex(random_bytes(4));
        mkdir($this->root . DIRECTORY_SEPARATOR . 'storage', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteTree($this->root);
    }

    public function testNeedsInstallWhenEnvMissing(): void
    {
        $detector = new InstallDetector($this->root);
        self::assertTrue($detector->needsInstall());
        self::assertFalse($detector->isLocked());
    }

    public function testLockWithoutEnvStillNeedsInstall(): void
    {
        $detector = new InstallDetector($this->root);
        $detector->markInstalled('stale');
        self::assertTrue($detector->isLocked());
        self::assertTrue($detector->needsInstall());
        self::assertFalse($detector->isLocked());
    }

    public function testMarkInstalledCreatesLockFile(): void
    {
        $detector = new InstallDetector($this->root);
        $detector->markInstalled('test');
        self::assertTrue($detector->isLocked());
        self::assertFileExists($detector->lockPath());
        // Without .env the install is still considered incomplete.
        self::assertTrue($detector->needsInstall());
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }

            return;
        }
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($full)) {
                $this->deleteTree($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($path);
    }
}
