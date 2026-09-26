<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\Mail\MailQueueWorker;
use Nexis\Plugin\PluginSettings;
use Nexis\Plugin\SettingsSchemaRegistry;
use Nexis\Site\PdoSiteSettingsRepository;
use Nexis\Site\SiteId;
use Psr\Log\LoggerInterface;
use Throwable;

final class FormMailNotifier
{
    public const PLUGIN_ID = 'nexis/forms';

    private PluginSettings $settings;

    public function __construct(
        private MailQueueWorker $mailQueue,
        SettingsSchemaRegistry $schema,
        PdoSiteSettingsRepository $store,
        private LoggerInterface $logger,
    ) {
        $this->settings = new PluginSettings(self::PLUGIN_ID, $schema, $store);
    }

    public function notifySubmission(
        SiteId $siteId,
        string $submissionId,
        string $name,
        string $email,
        string $message,
        string $locale,
    ): void {
        $to = trim((string) $this->settings->get($siteId, 'notify_email', ''));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $payload = [
            'type' => 'form_submission',
            'site_id' => $siteId->value,
            'submission_id' => $submissionId,
            'to' => $to,
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'locale' => $locale,
        ];
        try {
            $this->mailQueue->dispatch($payload);
        } catch (Throwable $e) {
            $this->logger->warning('Form mail dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}
