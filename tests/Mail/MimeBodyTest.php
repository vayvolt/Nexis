<?php

declare(strict_types=1);

namespace Nexis\Tests\Mail;

use Nexis\Mail\MailMessage;
use Nexis\Mail\MimeBody;
use Nexis\Mail\TransactionalMailHtml;
use PHPUnit\Framework\TestCase;

final class MimeBodyTest extends TestCase
{
    public function testPlainTextUsesQuotedPrintableAndCrlf(): void
    {
        $mime = MimeBody::build(new MailMessage(['a@test'], 'S', "Hello\nWorld"));
        self::assertSame('Content-Type: text/plain; charset=UTF-8', $mime['headers'][0]);
        self::assertSame('Content-Transfer-Encoding: quoted-printable', $mime['headers'][1]);
        self::assertStringContainsString("Hello\r\nWorld", $mime['body']);
        self::assertSame(0, substr_count(str_replace("\r\n", '', $mime['body']), "\n"));
    }

    public function testMultipartWhenHtmlPresent(): void
    {
        $mime = MimeBody::build(new MailMessage(
            ['a@test'],
            'S',
            'Plain ä',
            '<p>Html ö</p>',
        ));
        self::assertCount(1, $mime['headers']);
        self::assertStringStartsWith('Content-Type: multipart/alternative; boundary="', $mime['headers'][0]);
        self::assertStringContainsString('Content-Transfer-Encoding: quoted-printable', $mime['body']);
        self::assertStringContainsString('Content-Type: text/html; charset=UTF-8', $mime['body']);
        self::assertStringContainsString('Plain', $mime['body']);
        self::assertStringContainsString('Html', $mime['body']);
        self::assertSame(0, substr_count(str_replace("\r\n", '', $mime['body']), "\n"));
    }

    public function testSmtpDataStuffsLeadingDots(): void
    {
        $stuffed = MimeBody::smtpData("line\r\n.\r\nnext");
        self::assertSame("line\r\n..\r\nnext", $stuffed);
    }

    public function testTransactionalHtmlEscapesUserContent(): void
    {
        $html = TransactionalMailHtml::wrap(
            'Title <b>',
            'Pre <script>',
            TransactionalMailHtml::badge('Ref', 'A<B>')
            . TransactionalMailHtml::rows(['Name' => 'O\'Neil & Co'])
            . TransactionalMailHtml::message('Msg', "Line 1\n<script>"),
        );
        self::assertStringContainsString('Title &lt;b&gt;', $html);
        self::assertStringContainsString('A&lt;B&gt;', $html);
        self::assertStringContainsString('O&#039;Neil &amp; Co', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('Nexis', $html);
    }

    public function testTransactionalHtmlUsesBrand(): void
    {
        $html = TransactionalMailHtml::wrap('Title', 'Pre', '<p>x</p>', 'Werkstatt Demo');
        self::assertStringContainsString('Werkstatt Demo', $html);
    }
}
