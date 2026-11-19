<?php

declare(strict_types=1);

namespace Nexis\Content;

use Nexis\Builder\PublishService;
use Nexis\Kernel\Config;
use Nexis\Support\Clock;
use Psr\Log\LoggerInterface;
use Throwable;

final class ScheduledPublishWorker
{
    public function __construct(
        private PageRepository $pages,
        private PublishService $publish,
        private Clock $clock,
        private Config $config,
        private LoggerInterface $logger,
    ) {
    }

    public function processDue(int $limit = 20): int
    {
        $done = 0;
        $basePath = $this->basePath();
        foreach ($this->pages->listDueScheduled($this->clock->now(), $limit) as $page) {
            $actor = $page->scheduledBy;
            if ($actor === null) {
                $this->logger->warning('Scheduled page missing actor', ['page' => $page->id->value]);
                continue;
            }
            try {
                $this->publish->publish($page, null, $actor, $basePath);
                $done++;
            } catch (Throwable $e) {
                $this->logger->warning('Scheduled publish failed', [
                    'page' => $page->id->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $done;
    }

    public function processDueUnpublish(int $limit = 20): int
    {
        $done = 0;
        foreach ($this->pages->listDueUnpublish($this->clock->now(), $limit) as $page) {
            $actor = $page->unpublishBy ?? $page->scheduledBy;
            if ($actor === null) {
                $this->logger->warning('Scheduled unpublish missing actor', ['page' => $page->id->value]);
                continue;
            }
            try {
                $this->publish->unpublish($page, $actor);
                $done++;
            } catch (Throwable $e) {
                $this->logger->warning('Scheduled unpublish failed', [
                    'page' => $page->id->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $done;
    }

    private function basePath(): string
    {
        $url = (string) $this->config->get('app.url', '');
        if ($url === '') {
            return '';
        }
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) ? rtrim($path, '/') : '';
    }
}
