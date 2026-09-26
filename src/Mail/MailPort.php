<?php

declare(strict_types=1);

namespace Nexis\Mail;

interface MailPort
{
    public function send(MailMessage $message): void;
}
