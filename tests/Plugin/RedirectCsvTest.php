<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugins\Redirects\RedirectCsv;
use PHPUnit\Framework\TestCase;

final class RedirectCsvTest extends TestCase
{
    protected function setUp(): void
    {
        $file = dirname(__DIR__, 2) . '/plugins/nexis/redirects/src/RedirectCsv.php';
        if (!is_file($file)) {
            self::markTestSkipped('Redirects plugin not installed');
        }
        require_once $file;
    }

    public function testParseSkipsHeaderAndNormalizesPaths(): void
    {
        $csv = "from_path,to_url,locale,status_code\n"
            . "alt,/de/neu,,301\n"
            . "/old-en,/en/new,en,302\n"
            . "# comment\n"
            . "bad-only\n";

        $rows = RedirectCsv::parse($csv);
        self::assertCount(2, $rows);
        self::assertSame('/alt', $rows[0]['from_path']);
        self::assertSame('/de/neu', $rows[0]['to_url']);
        self::assertNull($rows[0]['locale']);
        self::assertSame(301, $rows[0]['status_code']);
        self::assertSame('/old-en', $rows[1]['from_path']);
        self::assertSame('en', $rows[1]['locale']);
        self::assertSame(302, $rows[1]['status_code']);
    }

    public function testExportRoundTripHeader(): void
    {
        $csv = RedirectCsv::export([
            ['from_path' => '/a', 'to_url' => '/b', 'locale' => null, 'status_code' => 301],
        ]);
        self::assertStringContainsString('from_path,to_url,locale,status_code', $csv);
        $rows = RedirectCsv::parse($csv);
        self::assertCount(1, $rows);
        self::assertSame('/a', $rows[0]['from_path']);
    }
}
