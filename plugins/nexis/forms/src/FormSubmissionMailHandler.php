<?php

declare(strict_types=1);

namespace Nexis\Plugins\Forms;

use Nexis\I18n\PublicUi;
use Nexis\Mail\MailJobHandler;
use Nexis\Mail\MailMessage;
use Nexis\Mail\MailPort;

/**
 * Handles queued/sync mail jobs of type `form_submission`.
 */
final class FormSubmissionMailHandler implements MailJobHandler
{
    public function __construct(
        private MailPort $mail,
        private PublicUi $ui,
    ) {
    }

    public function handle(array $payload): void
    {
        $to = (string) ($payload['to'] ?? '');
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $name = (string) ($payload['name'] ?? '');
        $email = (string) ($payload['email'] ?? '');
        $message = (string) ($payload['message'] ?? '');
        $locale = (string) ($payload['locale'] ?? 'de');
        $submissionId = (string) ($payload['submission_id'] ?? '');
        $body = $this->ui->get($locale, 'mail.form_submission.body', [
            'locale' => $locale,
            'id' => $submissionId,
            'name' => $name,
            'email' => $email,
            'message' => $message,
        ]);
        $this->mail->send(new MailMessage(
            [$to],
            $this->ui->get($locale, 'mail.form_submission.subject'),
            $body,
            null,
            $email !== '' ? $email : null,
            is_string($payload['site_id'] ?? null) ? (string) $payload['site_id'] : null,
            $submissionId !== '' ? 'form_submission:' . $submissionId : 'form_submission',
        ));
    }
}
