<?php

declare(strict_types=1);

/**
 * Helpers for bin/pack-release.php — safe to require from tests.
 */

/**
 * @param array{plugins: list<string>, themes: list<string>, forbidden?: list<string>} $manifest
 * @return list<string>
 */
function packPaths(string $root, array $manifest): array
{
    $paths = [
        'index.php',
        '.htaccess',
        '.env.example',
        'LICENSE',
        'composer.json',
        'composer.lock',
        'bin/.htaccess',
        'bin/migrate.php',
        'bin/cron.php',
        'bin/backup.php',
        'bin/restore.php',
        'bin/seed.php',
        'bin/seed-legal-pages.php',
    ];

    foreach (['src', 'config', 'resources', 'database', 'vendor'] as $dir) {
        $abs = $root . DIRECTORY_SEPARATOR . $dir;
        if (is_dir($abs)) {
            $paths[] = $dir;
        }
    }

    // Allowlisted plugins/themes only — never copy whole trees (repo samples stay local).
    foreach (['plugins/.gitkeep', 'plugins/.htaccess', 'themes/.gitkeep', 'themes/.htaccess'] as $keep) {
        if (is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $keep))) {
            $paths[] = $keep;
        }
    }
    foreach ($manifest['plugins'] as $plugin) {
        $rel = 'plugins/' . $plugin;
        if (is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel))) {
            $paths[] = $rel;
        }
    }
    foreach ($manifest['themes'] as $theme) {
        $rel = 'themes/' . $theme;
        if (is_dir($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel))) {
            $paths[] = $rel;
        }
    }

    // Core public assets only — not published plugin/theme copies under assets/{plugins,themes}/
    // (those are generated at runtime from plugins/ and themes/).
    foreach (['admin', 'brand', 'core', 'account'] as $assetDir) {
        $abs = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . $assetDir;
        if (is_dir($abs)) {
            $paths[] = 'assets/' . $assetDir;
        }
    }

    foreach ([
        'storage/logs/.gitkeep',
        'storage/cache/.gitkeep',
        'storage/sessions/.gitkeep',
        'storage/media/.gitkeep',
        'storage/tmp/.gitkeep',
        'storage/backups/.gitkeep',
        'assets/.gitkeep',
        'assets/plugins/.gitkeep',
        'assets/themes/.gitkeep',
    ] as $keep) {
        if (is_file($root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $keep))) {
            $paths[] = $keep;
        }
    }

    return $paths;
}

/**
 * @param list<string> $forbidden
 */
function assertPackStagingClean(string $stageApp, array $forbidden): void
{
    foreach ($forbidden as $rel) {
        $abs = $stageApp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_dir($abs) || is_file($abs)) {
            throw new RuntimeException(
                "Pack refused: forbidden path leaked into staging: {$rel}"
                . ' (see bin/pack-release-manifest.php)',
            );
        }
    }

    foreach (['assets/plugins', 'assets/themes'] as $pub) {
        $dir = $stageApp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pub);
        if (!is_dir($dir)) {
            continue;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === '.gitkeep') {
                continue;
            }
            throw new RuntimeException(
                "Pack refused: published runtime assets under {$pub}/{$item}"
                . ' must not ship (generated at install/runtime)',
            );
        }
    }
}

function changelogSection(string $changelogFile, string $version): string
{
    if (!is_file($changelogFile)) {
        return "(kein CHANGELOG)\n";
    }
    $text = (string) file_get_contents($changelogFile);
    if (preg_match('/^## \\[' . preg_quote($version, '/') . '\\][^\\n]*\\n([\\s\\S]*?)(?=^## \\[|\\z)/m', $text, $m) !== 1) {
        return "(Abschnitt {$version} nicht gefunden)\n";
    }

    return '## [' . $version . "]\n" . trim($m[1]) . "\n";
}

function runComposerInstall(string $appRoot, bool $withDev): void
{
    $composer = findComposer();
    $flags = $withDev
        ? 'install --optimize-autoloader --no-interaction'
        : 'install --no-dev --optimize-autoloader --no-interaction';
    fwrite(STDOUT, 'composer ' . $flags . " …\n");
    $cmd = $composer . ' ' . $flags . ' --working-dir=' . escapeshellarg($appRoot);
    $code = 0;
    passthru($cmd, $code);
    if ($code !== 0) {
        throw new RuntimeException('composer install failed (exit ' . $code . ')');
    }
}

function findComposer(): string
{
    $localPhar = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'composer.phar';
    if (is_file($localPhar)) {
        return PHP_BINARY . ' ' . escapeshellarg($localPhar);
    }
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $lines = [];
        exec('where composer.bat 2>NUL', $lines, $code);
        if ($code === 0 && isset($lines[0]) && is_file(trim($lines[0]))) {
            return trim($lines[0]);
        }
        $lines = [];
        exec('where composer 2>NUL', $lines, $code);
        if ($code === 0 && isset($lines[0]) && trim($lines[0]) !== '') {
            return trim($lines[0]);
        }
    } else {
        $lines = [];
        exec('command -v composer 2>/dev/null', $lines, $code);
        if ($code === 0 && isset($lines[0]) && trim($lines[0]) !== '') {
            return trim($lines[0]);
        }
    }

    return 'composer';
}

function zipDirectory(string $sourceRoot, string $zipPath): void
{
    if (!class_exists(ZipArchive::class)) {
        throw new RuntimeException('ext-zip required');
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create ZIP');
    }
    $sourceRoot = rtrim($sourceRoot, '\\/');
    $prefixLen = strlen($sourceRoot) + 1;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    /** @var SplFileInfo $file */
    foreach ($iterator as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), $prefixLen));
        if ($relative === '') {
            continue;
        }
        if ($file->isDir()) {
            $zip->addEmptyDir($relative);
        } elseif ($file->isFile()) {
            $zip->addFile($file->getPathname(), $relative);
        }
    }
    if (!$zip->close()) {
        throw new RuntimeException('Cannot finalize ZIP');
    }
}

function copyTree(string $src, string $dst): void
{
    if (!is_dir($dst) && !mkdir($dst, 0775, true) && !is_dir($dst)) {
        throw new RuntimeException('mkdir failed: ' . $dst);
    }
    $items = scandir($src);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $from = $src . DIRECTORY_SEPARATOR . $item;
        $to = $dst . DIRECTORY_SEPARATOR . $item;
        if (is_dir($from) && !is_link($from)) {
            // Skip runtime junk inside storage/
            $norm = str_replace('\\', '/', $from);
            if (preg_match('#/storage/(logs|cache|sessions|media|tmp|backups)/.#', $norm) === 1
                && !str_ends_with($norm, '.gitkeep')
            ) {
                continue;
            }
            copyTree($from, $to);
        } elseif (is_file($from)) {
            if (!@copy($from, $to)) {
                throw new RuntimeException('copy failed: ' . $from);
            }
        }
    }
}

function removeTree(string $path): void
{
    if (!is_dir($path) && !is_file($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);

        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        removeTree($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}
