<?php

declare(strict_types=1);

namespace Nexis\Mail;

use Nexis\Kernel\Config;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Transport from MAIL_TRANSPORT: log (default), mail (PHP mail()), smtp.
 * Every send is persisted to mail_log. SMTP secrets stay in .env.
 */
final class EnvMailPort implements MailPort
{
    /** @var list<string> */
    private array $smtpTranscript = [];

    private int $smtpAuthSecretsRemaining = 0;

    public function __construct(
        private Config $config,
        private LoggerInterface $logger,
        private MailLogRepository $mailLog,
        private Clock $clock,
    ) {
    }

    public function send(MailMessage $message): void
    {
        if ($message->to === []) {
            throw new RuntimeException('Mail ohne Empfänger.');
        }
        $transport = strtolower((string) $this->config->get('mail.transport', 'log'));
        if (!in_array($transport, ['log', 'mail', 'smtp'], true)) {
            $transport = 'log';
        }

        $this->smtpTranscript = [];
        $this->smtpAuthSecretsRemaining = 0;
        $error = null;
        try {
            match ($transport) {
                'mail' => $this->sendPhpMail($message),
                'smtp' => $this->sendSmtp($message),
                default => $this->sendLog($message),
            };
        } catch (Throwable $e) {
            $error = $e;
        }

        $this->persistLog($message, $transport, $error);
        if ($error !== null) {
            throw $error instanceof RuntimeException
                ? $error
                : new RuntimeException($error->getMessage(), 0, $error);
        }
    }

    private function persistLog(MailMessage $message, string $transport, ?Throwable $error): void
    {
        $fromAddress = (string) $this->config->get('mail.from_address', 'noreply@localhost');
        $smtpLog = null;
        if ($transport === 'smtp' && $this->smtpTranscript !== []) {
            $smtpLog = implode('', $this->smtpTranscript);
        }

        try {
            $this->mailLog->record(new MailLogEntry(
                Uuid::v7(),
                $message->siteId,
                $transport,
                $error === null ? 'sent' : 'failed',
                $message->to,
                $fromAddress !== '' ? $fromAddress : null,
                $message->subject,
                $message->textBody,
                $message->replyTo,
                $message->context,
                $error?->getMessage(),
                $smtpLog,
                $this->clock->now()->format('Y-m-d H:i:s.v'),
            ));
        } catch (Throwable $logError) {
            $this->logger->error('mail.log_persist_failed', [
                'error' => $logError->getMessage(),
                'mail_error' => $error?->getMessage(),
            ]);
        }
    }

    private function sendLog(MailMessage $message): void
    {
        $this->logger->info('mail.sent', [
            'transport' => 'log',
            'to' => $message->to,
            'subject' => $message->subject,
            'reply_to' => $message->replyTo,
            'body' => $message->textBody,
            'html' => $message->htmlBody !== null && $message->htmlBody !== '',
        ]);
        $root = $this->config->rootPath;
        $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $body = $message->textBody;
        if (is_string($message->htmlBody) && $message->htmlBody !== '') {
            $body .= "\n\n[html]\n" . $message->htmlBody;
        }
        $line = sprintf(
            "[%s] to=%s subject=%s\n%s\n---\n",
            gmdate('c'),
            implode(',', $message->to),
            $message->subject,
            $body,
        );
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'mail.log', $line, FILE_APPEND);
    }

    private function sendPhpMail(MailMessage $message): void
    {
        $mime = MimeBody::build($message);
        $from = $this->fromHeader();
        $headers = array_merge(
            ['MIME-Version: 1.0'],
            $mime['headers'],
            ['From: ' . $from],
        );
        if (is_string($message->replyTo) && $message->replyTo !== '') {
            $headers[] = 'Reply-To: ' . $message->replyTo;
        }
        $ok = mail(
            implode(', ', $message->to),
            '=?UTF-8?B?' . base64_encode($message->subject) . '?=',
            $mime['body'],
            implode("\r\n", $headers),
        );
        if (!$ok) {
            throw new RuntimeException('PHP mail() fehlgeschlagen.');
        }
    }

    private function sendSmtp(MailMessage $message): void
    {
        $host = (string) $this->config->get('mail.host', '127.0.0.1');
        $port = (int) $this->config->get('mail.port', 587);
        $encryption = strtolower((string) $this->config->get('mail.encryption', 'tls'));
        $username = (string) $this->config->get('mail.username', '');
        $password = (string) $this->config->get('mail.password', '');
        $fromAddress = (string) $this->config->get('mail.from_address', 'noreply@localhost');
        $fromName = (string) $this->config->get('mail.from_name', 'Nexis');

        $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $this->smtpNote('>>> CONNECT ' . $host . ':' . $port . ' encryption=' . $encryption);
        $fp = @stream_socket_client($remote, $errno, $errstr, 15);
        if ($fp === false) {
            throw new RuntimeException('SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($fp, 15);
        try {
            $this->expect($fp, 220);
            $this->command($fp, 'EHLO nexis.local', 250);
            if ($encryption === 'tls') {
                $this->command($fp, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP STARTTLS failed.');
                }
                $this->smtpNote('>>> TLS enabled');
                $this->command($fp, 'EHLO nexis.local', 250);
            }
            if ($username !== '') {
                $this->command($fp, 'AUTH LOGIN', 334);
                $this->smtpAuthSecretsRemaining = 2;
                $this->command($fp, base64_encode($username), 334);
                $this->command($fp, base64_encode($password), 235);
            }
            $this->command($fp, 'MAIL FROM:<' . $fromAddress . '>', 250);
            foreach ($message->to as $to) {
                $this->command($fp, 'RCPT TO:<' . $to . '>', 250);
            }
            $this->command($fp, 'DATA', 354);
            $mime = MimeBody::build($message);
            $headers = [
                'From: ' . $this->formatAddress($fromName, $fromAddress),
                'To: ' . implode(', ', $message->to),
                'Subject: =?UTF-8?B?' . base64_encode($message->subject) . '?=',
                'MIME-Version: 1.0',
                'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            ];
            foreach ($mime['headers'] as $header) {
                $headers[] = $header;
            }
            if (is_string($message->replyTo) && $message->replyTo !== '') {
                $headers[] = 'Reply-To: ' . $message->replyTo;
            }
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . MimeBody::smtpData($mime['body']);
            $this->smtpNote('>>> DATA (' . strlen($payload) . ' bytes)');
            fwrite($fp, $payload . "\r\n.\r\n");
            $this->expect($fp, 250);
            $this->command($fp, 'QUIT', 221);
        } finally {
            fclose($fp);
        }
    }

    private function fromHeader(): string
    {
        $fromAddress = (string) $this->config->get('mail.from_address', 'noreply@localhost');
        $fromName = (string) $this->config->get('mail.from_name', 'Nexis');

        return $this->formatAddress($fromName, $fromAddress);
    }

    private function formatAddress(string $name, string $email): string
    {
        if ($name === '') {
            return $email;
        }

        return sprintf('%s <%s>', $this->encodeHeader($name), $email);
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /**
     * @param resource $fp
     */
    private function command($fp, string $line, int $expected): void
    {
        $logged = $line;
        if ($this->smtpAuthSecretsRemaining > 0) {
            $logged = '[REDACTED]';
            $this->smtpAuthSecretsRemaining--;
        }
        $this->smtpNote('>>> ' . $logged);
        fwrite($fp, $line . "\r\n");
        $this->expect($fp, $expected);
    }

    /**
     * @param resource $fp
     */
    private function expect($fp, int $code): void
    {
        $response = '';
        while (($line = fgets($fp, 512)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $this->smtpNote('<<< ' . trim($response));
        if (!str_starts_with($response, (string) $code)) {
            throw new RuntimeException('SMTP unexpected response (want ' . $code . '): ' . trim($response));
        }
    }

    private function smtpNote(string $line): void
    {
        $this->smtpTranscript[] = $line . "\n";
    }
}
