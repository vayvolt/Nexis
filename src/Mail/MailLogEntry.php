<?php

declare(strict_types=1);

namespace Nexis\Mail;

final class MailLogEntry
{
    /**
     * @param list<string> $to
     */
    public function __construct(
        public private(set) string $id,
        public private(set) ?string $siteId,
        public private(set) string $transport,
        public private(set) string $status,
        public private(set) array $to,
        public private(set) ?string $fromAddress,
        public private(set) string $subject,
        public private(set) string $bodyText,
        public private(set) ?string $replyTo,
        public private(set) ?string $context,
        public private(set) ?string $errorMessage,
        public private(set) ?string $smtpLog,
        public private(set) string $createdAt,
    ) {
    }

    public bool $isFailed {
        get => $this->status === 'failed';
    }

    public bool $isSent {
        get => $this->status === 'sent';
    }

    public string $toList {
        get => implode(', ', $this->to);
    }
}
