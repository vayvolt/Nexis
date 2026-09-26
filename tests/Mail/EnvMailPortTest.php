<?php

declare(strict_types=1);

namespace Nexis\Tests\Mail;

use Nexis\Kernel\Config;
use Nexis\Mail\EnvMailPort;
use Nexis\Mail\MailLogEntry;
use Nexis\Mail\MailLogRepository;
use Nexis\Mail\MailMessage;
use Nexis\Support\SystemClock;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class EnvMailPortTest extends TestCase
{
    public function testLogTransportWritesMailLogAndDatabase(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-mail-' . bin2hex(random_bytes(4));
        mkdir($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);
        $config = new Config([
            'mail' => [
                'transport' => 'log',
                'from_address' => 'noreply@test',
                'from_name' => 'Test',
            ],
        ], $root);
        $repo = new InMemoryMailLogRepository();
        $port = new EnvMailPort($config, new NullLogger(), $repo, new SystemClock());
        $port->send(new MailMessage(
            ['a@example.com'],
            'Hello',
            "Body\n",
            null,
            null,
            'site-1',
            'unit-test',
        ));

        $log = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mail.log';
        self::assertFileExists($log);
        $contents = (string) file_get_contents($log);
        self::assertStringContainsString('a@example.com', $contents);
        self::assertStringContainsString('Hello', $contents);
        self::assertStringContainsString('Body', $contents);

        self::assertCount(1, $repo->entries);
        $entry = $repo->entries[0];
        self::assertSame('sent', $entry->status);
        self::assertSame('log', $entry->transport);
        self::assertSame(['a@example.com'], $entry->to);
        self::assertSame('Hello', $entry->subject);
        self::assertSame('site-1', $entry->siteId);
        self::assertSame('unit-test', $entry->context);
        self::assertNull($entry->errorMessage);
    }

    public function testFailedSendIsLoggedThenRethrown(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-mail-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $config = new Config([
            'mail' => [
                'transport' => 'smtp',
                'host' => '127.0.0.1',
                'port' => 1,
                'encryption' => 'tls',
                'from_address' => 'noreply@test',
                'from_name' => 'Test',
            ],
        ], $root);
        $repo = new InMemoryMailLogRepository();
        $port = new EnvMailPort($config, new NullLogger(), $repo, new SystemClock());

        try {
            $port->send(new MailMessage(['b@example.com'], 'Fail', 'x'));
            self::fail('Expected SMTP failure');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('SMTP', $e->getMessage());
        }

        self::assertCount(1, $repo->entries);
        self::assertSame('failed', $repo->entries[0]->status);
        self::assertSame('smtp', $repo->entries[0]->transport);
        self::assertNotNull($repo->entries[0]->errorMessage);
        self::assertNotNull($repo->entries[0]->smtpLog);
        self::assertStringContainsString('CONNECT', (string) $repo->entries[0]->smtpLog);
    }

    public function testLogTransportWritesHtmlWhenPresent(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nexis-mail-' . bin2hex(random_bytes(4));
        mkdir($root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs', 0777, true);
        $config = new Config([
            'mail' => [
                'transport' => 'log',
                'from_address' => 'noreply@test',
                'from_name' => 'Test',
            ],
        ], $root);
        $repo = new InMemoryMailLogRepository();
        $port = new EnvMailPort($config, new NullLogger(), $repo, new SystemClock());
        $port->send(new MailMessage(
            ['a@example.com'],
            'Hello',
            "Body\n",
            '<p>HTML body</p>',
        ));

        $log = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mail.log';
        $contents = (string) file_get_contents($log);
        self::assertStringContainsString('[html]', $contents);
        self::assertStringContainsString('<p>HTML body</p>', $contents);
    }
}

/**
 * @internal
 */
final class InMemoryMailLogRepository implements MailLogRepository
{
    /** @var list<MailLogEntry> */
    public array $entries = [];

    public function record(MailLogEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function recent(int $limit = 100, ?string $status = null): array
    {
        $rows = $this->entries;
        if ($status !== null && $status !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (MailLogEntry $e): bool => $e->status === $status,
            ));
        }

        return array_slice(array_reverse($rows), 0, $limit);
    }

    public function find(string $id): ?MailLogEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }
}
