<?php

declare(strict_types=1);

namespace Nexis\Webhook;

use Nexis\Event\PagePublished;
use Nexis\Event\PageReviewRejected;
use Nexis\Event\PageSubmittedForReview;
use Nexis\Event\PageUnpublished;
use Nexis\Event\PluginToggled;
use Nexis\Queue\JobQueue;
use Nexis\Site\SiteId;
use Psr\Log\LoggerInterface;

final class WebhookDispatcher
{
    public function __construct(
        private WebhookRepository $endpoints,
        private JobQueue $jobs,
        private WebhookClient $client,
        private LoggerInterface $logger,
    ) {
    }

    public function onPagePublished(PagePublished $event): void
    {
        $this->dispatchEvent('page.published', $event->siteId, [
            'event' => 'page.published',
            'site_id' => $event->siteId->value,
            'page_id' => $event->pageId->value,
            'snapshot_id' => $event->snapshotId->value,
            'locale' => $event->locale,
            'path' => $event->path,
            'title' => $event->title,
            'actor_id' => $event->actorId->value,
        ]);
    }

    public function onPageUnpublished(PageUnpublished $event): void
    {
        $this->dispatchEvent('page.unpublished', $event->siteId, [
            'event' => 'page.unpublished',
            'site_id' => $event->siteId->value,
            'page_id' => $event->pageId->value,
            'locale' => $event->locale,
            'path' => $event->path,
            'title' => $event->title,
            'actor_id' => $event->actorId->value,
        ]);
    }

    public function onPageSubmittedForReview(PageSubmittedForReview $event): void
    {
        $this->dispatchEvent('page.review_submitted', $event->siteId, [
            'event' => 'page.review_submitted',
            'site_id' => $event->siteId->value,
            'page_id' => $event->pageId->value,
            'locale' => $event->locale,
            'path' => $event->path,
            'title' => $event->title,
            'actor_id' => $event->actorId->value,
        ]);
    }

    public function onPageReviewRejected(PageReviewRejected $event): void
    {
        $this->dispatchEvent('page.review_rejected', $event->siteId, [
            'event' => 'page.review_rejected',
            'site_id' => $event->siteId->value,
            'page_id' => $event->pageId->value,
            'locale' => $event->locale,
            'path' => $event->path,
            'title' => $event->title,
            'actor_id' => $event->actorId->value,
        ]);
    }

    public function onPluginToggled(PluginToggled $event): void
    {
        $name = $event->eventName();
        $this->dispatchEvent($name, $event->siteId, [
            'event' => $name,
            'site_id' => $event->siteId->value,
            'plugin' => $event->pluginKey,
            'enabled' => $event->enabled,
            'actor_id' => $event->actorId->value,
        ]);
    }

    /**
     * Dispatch a webhook event (core or plugin-registered) to matching endpoints.
     *
     * @param array<string, mixed> $body Should include `event`; filled from $eventName if missing.
     */
    public function dispatch(string $eventName, SiteId $siteId, array $body = []): void
    {
        if (!isset($body['event']) || !is_string($body['event']) || $body['event'] === '') {
            $body['event'] = $eventName;
        }
        $this->dispatchEvent($eventName, $siteId, $body);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function dispatchEvent(string $eventName, SiteId $siteId, array $body): void
    {
        foreach ($this->endpoints->activeForEvent($siteId, $eventName) as $endpoint) {
            try {
                $this->deliverNow($endpoint, $body);
            } catch (\Throwable $e) {
                $this->logger->warning('Webhook delivery failed; queued for retry', [
                    'endpoint' => $endpoint->id,
                    'error' => $e->getMessage(),
                ]);
                $this->jobs->push('webhooks', [
                    'endpoint_id' => $endpoint->id,
                    'body' => $body,
                ], 30);
            }
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    public function deliverNow(WebhookEndpoint $endpoint, array $body, int $attempts = 1): void
    {
        $eventName = is_string($body['event'] ?? null) ? (string) $body['event'] : 'unknown';
        try {
            $status = $this->client->post($endpoint->url, $endpoint->secret, $body);
            $this->endpoints->logDelivery(
                $endpoint->id,
                $endpoint->siteId,
                $eventName,
                'ok',
                $status,
                null,
                $attempts,
            );
        } catch (WebhookDeliveryException $e) {
            $this->endpoints->logDelivery(
                $endpoint->id,
                $endpoint->siteId,
                $eventName,
                'failed',
                $e->httpStatus,
                $e->getMessage(),
                $attempts,
            );
            throw $e;
        } catch (\Throwable $e) {
            $this->endpoints->logDelivery(
                $endpoint->id,
                $endpoint->siteId,
                $eventName,
                'failed',
                null,
                $e->getMessage(),
                $attempts,
            );
            throw $e;
        }
    }

    public function processQueued(int $limit = 20): int
    {
        $done = 0;
        foreach ($this->jobs->reserve('webhooks', $limit) as $job) {
            $endpointId = (string) ($job['payload']['endpoint_id'] ?? '');
            $body = $job['payload']['body'] ?? null;
            if ($endpointId === '' || !is_array($body)) {
                $this->jobs->delete($job['id']);
                continue;
            }
            /** @var array<string, mixed> $body */
            $endpoint = $this->endpoints->find($endpointId);
            if ($endpoint === null || !$endpoint->isActive) {
                $this->jobs->delete($job['id']);
                continue;
            }
            try {
                $this->deliverNow($endpoint, $body, max(1, (int) $job['attempts']));
                $this->jobs->delete($job['id']);
                $done++;
            } catch (\Throwable $e) {
                $this->logger->warning('Webhook retry failed', [
                    'job' => $job['id'],
                    'error' => $e->getMessage(),
                ]);
                if ($job['attempts'] >= 5) {
                    $this->jobs->fail($job['id'], 'webhooks', $job['payload'], $e->getMessage());
                    $this->logger->error('Webhook job moved to failed_jobs', [
                        'job' => $job['id'],
                        'error' => $e->getMessage(),
                    ]);
                } else {
                    $delay = (int) (2 ** min(4, $job['attempts'])) * 30;
                    $this->jobs->release($job['id'], $delay);
                }
            }
        }

        return $done;
    }
}
