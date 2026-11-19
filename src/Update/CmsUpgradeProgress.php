<?php

declare(strict_types=1);

namespace Nexis\Update;

use Nexis\Kernel\Config;
use Nexis\Kernel\Nexis;
use Nexis\Site\SiteId;
use RuntimeException;

/**
 * File-backed CMS upgrade job + live progress for the admin UI.
 *
 * @phpstan-type Step array{key: string, state: 'pending'|'active'|'done'|'failed'}
 * @phpstan-type Progress array{
 *   id: string,
 *   status: 'queued'|'running'|'done'|'failed',
 *   from: string,
 *   to: string,
 *   current: string,
 *   steps: list<Step>,
 *   message: string,
 *   backupStamp: ?string,
 *   error: ?string,
 *   startedAt: ?string,
 *   finishedAt: ?string,
 *   siteId: string,
 *   cms: array{latest: string, phpRequirement?: string, downloadUrl: string, updateAvailable?: bool},
 *   spawned: bool
 * }
 */
final class CmsUpgradeProgress
{
    public const STEP_KEYS = [
        'maintenance',
        'backup',
        'download',
        'extract',
        'snapshot',
        'apply',
        'migrate',
        'cache',
        'finish',
    ];

    private string $path;
    private string $lockPath;

    public function __construct(Config $config)
    {
        $dir = rtrim($config->rootPath, '\\/') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create storage/tmp for upgrade progress.');
        }
        $this->path = $dir . DIRECTORY_SEPARATOR . 'cms-upgrade-progress.json';
        $this->lockPath = $dir . DIRECTORY_SEPARATOR . 'cms-upgrade.lock';
    }

    /**
     * @param array{latest: string, phpRequirement?: string, downloadUrl: string, updateAvailable?: bool} $cms
     * @return Progress
     */
    public function queue(array $cms, SiteId $siteId): array
    {
        $existing = $this->read();
        if ($existing !== null && in_array($existing['status'], ['queued', 'running'], true)) {
            throw new RuntimeException('An upgrade is already in progress.');
        }

        $to = trim((string) ($cms['latest'] ?? ''));
        $steps = [];
        foreach (self::STEP_KEYS as $key) {
            $steps[] = ['key' => $key, 'state' => 'pending'];
        }

        $progress = [
            'id' => bin2hex(random_bytes(8)),
            'status' => 'queued',
            'from' => Nexis::VERSION,
            'to' => $to,
            'current' => 'queued',
            'steps' => $steps,
            'message' => '',
            'backupStamp' => null,
            'error' => null,
            'startedAt' => null,
            'finishedAt' => null,
            'siteId' => $siteId->value,
            'cms' => $cms,
            'spawned' => false,
        ];
        $this->write($progress);

        return $progress;
    }

    /**
     * @return Progress|null
     */
    public function read(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }
        $raw = @file_get_contents($this->path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded) || !isset($decoded['id'], $decoded['status'], $decoded['cms'])) {
            return null;
        }
        /** @var Progress $decoded */
        return $decoded;
    }

    public function markSpawned(string $id): void
    {
        $progress = $this->read();
        if ($progress === null || $progress['id'] !== $id) {
            return;
        }
        $progress['spawned'] = true;
        $this->write($progress);
    }

    /**
     * Acquire exclusive lock and move queued → running. Returns false if another worker holds it.
     *
     * @return Progress|null
     */
    public function claim(string $id): ?array
    {
        $lock = $this->openLock();
        if ($lock === null) {
            return null;
        }
        try {
            $progress = $this->read();
            if ($progress === null || $progress['id'] !== $id) {
                return null;
            }
            if ($progress['status'] === 'running') {
                return null;
            }
            if ($progress['status'] !== 'queued') {
                return null;
            }
            $progress['status'] = 'running';
            $progress['startedAt'] = gmdate('c');
            $progress['current'] = 'maintenance';
            $this->write($progress);

            return $progress;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function setStep(string $key, string $state = 'active', string $message = ''): void
    {
        $progress = $this->read();
        if ($progress === null || !in_array($progress['status'], ['queued', 'running'], true)) {
            return;
        }
        if (!in_array($state, ['pending', 'active', 'done', 'failed'], true)) {
            return;
        }
        $progress['current'] = $key;
        if ($message !== '') {
            $progress['message'] = $message;
        }
        foreach ($progress['steps'] as $i => $step) {
            if ($step['key'] === $key) {
                $progress['steps'][$i]['state'] = $state;
            } elseif ($state === 'active' && $step['state'] === 'active') {
                $progress['steps'][$i]['state'] = 'done';
            }
        }
        if ($progress['status'] === 'queued') {
            $progress['status'] = 'running';
            $progress['startedAt'] = $progress['startedAt'] ?? gmdate('c');
        }
        $this->write($progress);
    }

    /**
     * @param array{from: string, to: string, backupStamp: string} $result
     */
    public function complete(array $result): void
    {
        $progress = $this->read();
        if ($progress === null) {
            return;
        }
        foreach ($progress['steps'] as $i => $step) {
            if ($step['state'] !== 'done') {
                $progress['steps'][$i]['state'] = 'done';
            }
        }
        $progress['status'] = 'done';
        $progress['current'] = 'done';
        $progress['from'] = $result['from'];
        $progress['to'] = $result['to'];
        $progress['backupStamp'] = $result['backupStamp'];
        $progress['error'] = null;
        $progress['finishedAt'] = gmdate('c');
        $progress['message'] = '';
        $this->write($progress);
    }

    public function fail(string $error): void
    {
        $progress = $this->read();
        if ($progress === null) {
            return;
        }
        $current = $progress['current'];
        foreach ($progress['steps'] as $i => $step) {
            if ($step['key'] === $current && $step['state'] === 'active') {
                $progress['steps'][$i]['state'] = 'failed';
            }
        }
        $progress['status'] = 'failed';
        $progress['error'] = $error;
        $progress['finishedAt'] = gmdate('c');
        $progress['message'] = $error;
        $this->write($progress);
    }

    /**
     * @return resource|null
     */
    private function openLock()
    {
        $handle = @fopen($this->lockPath, 'c+');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * @param Progress $progress
     */
    private function write(array $progress): void
    {
        $json = json_encode($progress, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tmp = $this->path . '.tmp';
        if (@file_put_contents($tmp, $json) === false) {
            throw new RuntimeException('Cannot write upgrade progress.');
        }
        if (!@rename($tmp, $this->path)) {
            @unlink($this->path);
            if (!@rename($tmp, $this->path)) {
                throw new RuntimeException('Cannot publish upgrade progress.');
            }
        }
    }
}
