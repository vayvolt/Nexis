<?php

declare(strict_types=1);

namespace Nexis\Mail;

final class MailMessage
{
    /**
     * @param list<string> $to
     */
    public function __construct(
        public private(set) array $to,
        public private(set) string $subject,
        public private(set) string $textBody,
        public private(set) ?string $htmlBody = null,
        public private(set) ?string $replyTo = null,
        public private(set) ?string $siteId = null,
        public private(set) ?string $context = null,
    ) {
    }
}
