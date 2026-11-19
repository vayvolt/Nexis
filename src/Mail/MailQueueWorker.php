<?php

declare(strict_types=1);

namespace Nexis\Mail;

use Nexis\I18n\PublicUi;
use Nexis\Queue\JobQueue;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;
use Psr\Log\LoggerInterface;
use Throwable;

final class MailQueueWorker
{
    public const QUEUE = 'mail';

    public function __construct(
        private JobQueue $jobs,
        private MailPort $mail,
        private PdoSiteSettingsRepository $settings,
        private LoggerInterface $logger,
        private PublicUi $ui,
        private MailJobRegistry $mailJobs,
    ) {
    }

    public function processQueued(int $limit = 20): int
    {
        $done = 0;
        foreach ($this->jobs->reserve(self::QUEUE, $limit) as $job) {
            try {
                $this->handle($job['payload']);
                $this->jobs->delete($job['id']);
                $done++;
            } catch (Throwable $e) {
                $this->logger->warning('Mail job failed', [
                    'job' => $job['id'],
                    'error' => $e->getMessage(),
                ]);
                if ($job['attempts'] >= 5) {
                    $this->jobs->fail($job['id'], self::QUEUE, $job['payload'], $e->getMessage());
                    $this->logger->error('Mail job moved to failed_jobs', [
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

    /**
     * Send immediately or push to the mail queue on failure.
     *
     * @param array<string, mixed> $payload Must include string `type` (core or plugin-registered).
     */
    public function dispatch(array $payload): void
    {
        try {
            $this->handle($payload);
        } catch (Throwable $e) {
            $this->logger->warning('Mail sync failed; queued', [
                'type' => (string) ($payload['type'] ?? ''),
                'error' => $e->getMessage(),
            ]);
            $this->jobs->push(self::QUEUE, $payload, 5);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void
    {
        $type = (string) ($payload['type'] ?? '');
        if ($this->mailJobs->has($type)) {
            $this->mailJobs->handle($type, $payload);

            return;
        }
        match ($type) {
            'test' => $this->sendTest($payload),
            default => throw new \InvalidArgumentException('Unknown mail job type: ' . $type),
        };
    }

    /**
     * Queue or send immediately a workshop order notification.
     * Uses registered handler `workshop_order` (core default until workshop plugin owns it).
     *
     * @param array<string, string> $customer
     */
    public function notifyWorkshopOrder(
        SiteId $siteId,
        string $orderId,
        string $reference,
        string $customerName,
        string $customerEmail,
        string $serviceTitle,
        string $message,
        string $locale,
        array $customer = [],
    ): void {
        $to = trim((string) $this->settings->get($siteId, 'plugin:nexis/workshop.notify_email', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $this->dispatch([
            'type' => 'workshop_order',
            'site_id' => $siteId->value,
            'order_id' => $orderId,
            'reference' => $reference,
            'to' => $to,
            'name' => $customerName,
            'email' => $customerEmail,
            'service' => $serviceTitle,
            'message' => $message,
            'locale' => $locale,
            'payment_method' => (string) ($customer['payment_method'] ?? 'invoice'),
            'company' => (string) ($customer['customer_company'] ?? ''),
            'phone' => (string) ($customer['customer_phone'] ?? ''),
            'billing_street' => (string) ($customer['billing_street'] ?? ''),
            'billing_postal_code' => (string) ($customer['billing_postal_code'] ?? ''),
            'billing_city' => (string) ($customer['billing_city'] ?? ''),
            'billing_country' => (string) ($customer['billing_country'] ?? ''),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendTest(array $payload): void
    {
        $to = (string) ($payload['to'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException($this->ui->get('de', 'mail.test.invalid_recipient'));
        }
        $this->mail->send(new MailMessage(
            [$to],
            $this->ui->get('de', 'admin.mail.test_subject'),
            $this->ui->get('de', 'admin.mail.test_body', ['time' => gmdate('c')]),
            null,
            null,
            is_string($payload['site_id'] ?? null) ? (string) $payload['site_id'] : null,
            'test',
        ));
    }
}
