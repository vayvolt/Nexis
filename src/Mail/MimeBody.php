<?php

declare(strict_types=1);

namespace Nexis\Mail;

/**
 * Builds text/plain or multipart/alternative MIME bodies for outbound mail.
 *
 * Wire format uses CRLF throughout. Parts are quoted-printable so UTF-8
 * (and HTML) survives SMTP without relying on 8BITMIME.
 */
final class MimeBody
{
    /**
     * @return array{headers: list<string>, body: string}
     */
    public static function build(MailMessage $message): array
    {
        $text = self::encodeQuotedPrintable($message->textBody);
        $htmlRaw = is_string($message->htmlBody) && $message->htmlBody !== ''
            ? $message->htmlBody
            : null;

        if ($htmlRaw === null) {
            return [
                'headers' => [
                    'Content-Type: text/plain; charset=UTF-8',
                    'Content-Transfer-Encoding: quoted-printable',
                ],
                'body' => $text,
            ];
        }

        $html = self::encodeQuotedPrintable($htmlRaw);
        $boundary = 'nexis_' . bin2hex(random_bytes(12));
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . $text . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . $html . "\r\n"
            . '--' . $boundary . "--\r\n";

        return [
            'headers' => [
                'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            ],
            'body' => $body,
        ];
    }

    /**
     * Escape a DATA payload for SMTP (CRLF + leading-dot stuffing).
     */
    public static function smtpData(string $body): string
    {
        $body = self::toCrlf($body);
        if (str_starts_with($body, '.')) {
            $body = '.' . $body;
        }

        return str_replace("\r\n.", "\r\n..", $body);
    }

    private static function encodeQuotedPrintable(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);
        $lines = explode("\n", $normalized);
        $encoded = [];
        foreach ($lines as $line) {
            $qp = quoted_printable_encode($line);
            // Soft line breaks from quoted_printable_encode use platform EOLs; normalize to CRLF.
            $encoded[] = str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $qp));
        }

        return implode("\r\n", $encoded);
    }

    private static function toCrlf(string $value): string
    {
        return str_replace("\n", "\r\n", str_replace(["\r\n", "\r"], "\n", $value));
    }
}
