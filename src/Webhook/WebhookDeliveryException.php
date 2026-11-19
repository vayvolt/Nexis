<?php

declare(strict_types=1);

namespace Nexis\Webhook;

use RuntimeException;

final class WebhookDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
