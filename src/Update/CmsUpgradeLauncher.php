<?php

declare(strict_types=1);

namespace Nexis\Update;

use Nexis\Kernel\Config;

/**
 * Starts the CMS upgrade worker outside the browser request when possible.
 */
final class CmsUpgradeLauncher
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @return array{spawned: bool, detail: string}
     */
    public function spawn(string $jobId): array
    {
        $php = PHP_BINARY;
        if (!is_file($php)) {
            return ['spawned' => false, 'detail' => 'PHP_BINARY unavailable'];
        }

        $root = rtrim($this->config->rootPath, '\\/');
        $script = $root . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'cms-upgrade.php';
        if (!is_file($script)) {
            return ['spawned' => false, 'detail' => 'bin/cms-upgrade.php missing'];
        }

        $log = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp'
            . DIRECTORY_SEPARATOR . 'cms-upgrade-cli.log';

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B "" ' . $this->winQuote($php) . ' '
                . $this->winQuote($script) . ' ' . $this->winQuote($jobId);
            $handle = @popen($cmd, 'r');
            if ($handle === false) {
                return ['spawned' => false, 'detail' => 'popen failed'];
            }
            pclose($handle);

            return ['spawned' => true, 'detail' => 'windows start /B'];
        }

        // Prefer popen over exec — exec() is often listed in disable_functions.
        $cmd = \escapeshellarg($php) . ' ' . \escapeshellarg($script) . ' ' . \escapeshellarg($jobId)
            . ' >> ' . \escapeshellarg($log) . ' 2>&1 &';
        $handle = @\popen($cmd, 'r');
        if ($handle === false) {
            return ['spawned' => false, 'detail' => 'popen failed'];
        }
        \pclose($handle);

        return ['spawned' => true, 'detail' => 'posix background'];
    }

    private function winQuote(string $path): string
    {
        return '"' . str_replace('"', '', $path) . '"';
    }
}
