<?php

declare(strict_types=1);

namespace Nexis\Http\Controller;

use Nexis\Http\Middleware\RequestIdMiddleware;
use Nexis\Http\ResponseFactory;
use Nexis\Infrastructure\Database\DatabaseHealth;
use Nexis\Queue\JobQueue;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function __construct(
        private ResponseFactory $responses,
        private DatabaseHealth $databaseHealth,
        private JobQueue $jobs,
        private string $storagePath,
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $requestId = (string) $request->getAttribute(RequestIdMiddleware::ATTRIBUTE, '');
        $database = $this->databaseHealth->status();
        $storage = $this->storageStatus();
        $queuePending = null;
        $queueStatus = 'unknown';
        try {
            $queuePending = $this->jobs->pendingCount();
            $queueStatus = 'ok';
        } catch (\Throwable) {
            $queueStatus = 'error';
        }
        $ok = $database === 'ok' && $storage === 'ok' && $queueStatus === 'ok';

        return $this->responses->json([
            'status' => $ok ? 'ok' : 'degraded',
            'requestId' => $requestId,
            'php' => PHP_VERSION,
            'database' => $database,
            'storage' => $storage,
            'queue' => [
                'status' => $queueStatus,
                'pending' => $queuePending,
            ],
        ], $ok ? 200 : 503);
    }

    private function storageStatus(): string
    {
        if (!is_dir($this->storagePath) && !mkdir($this->storagePath, 0775, true) && !is_dir($this->storagePath)) {
            return 'error';
        }
        if (!is_writable($this->storagePath)) {
            return 'error';
        }
        $probe = $this->storagePath . DIRECTORY_SEPARATOR . '.health-' . bin2hex(random_bytes(4));
        if (file_put_contents($probe, 'ok') === false) {
            return 'error';
        }
        @unlink($probe);

        return 'ok';
    }
}
