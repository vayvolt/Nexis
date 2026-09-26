<?php

declare(strict_types=1);

namespace Nexis\Tests\Content;

use Nexis\Content\Page;
use Nexis\Content\PageId;
use Nexis\Content\PageSeoAnalysis;
use Nexis\Content\PageStatus;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PHPUnit\Framework\TestCase;

final class PageSeoAnalysisTest extends TestCase
{
    public function testEmptyPageScoresLow(): void
    {
        $page = new Page(
            new PageId(Uuid::v7()),
            new SiteId(Uuid::v7()),
            Uuid::v7(),
            'de',
            'home',
            '/',
            'Hi',
            null,
            PageStatus::Draft,
        );
        $result = PageSeoAnalysis::analyze($page);
        self::assertSame(10, $result['score']); // only robots_index
        self::assertFalse($this->checkOk($result, 'keyword_set'));
    }

    public function testStrongSeoScoresHigh(): void
    {
        $page = new Page(
            new PageId(Uuid::v7()),
            new SiteId(Uuid::v7()),
            Uuid::v7(),
            'de',
            'kitchen',
            '/kitchen',
            'Kitchen planning Munich experts',
            null,
            PageStatus::Draft,
            metaTitle: 'Kitchen planning Munich – consult now',
            metaDescription: 'Kitchen planning Munich: layout advice, materials and installation for your new fitted kitchen.',
            robots: 'index,follow',
            focusKeyword: 'kitchen planning munich',
        );
        $result = PageSeoAnalysis::analyze($page);
        self::assertSame(100, $result['score']);
        foreach ($result['checks'] as $check) {
            self::assertTrue($check['ok'], $check['id']);
        }
    }

    /**
     * @param array{score: int, checks: list<array{id: string, ok: bool, weight: int}>} $result
     */
    private function checkOk(array $result, string $id): bool
    {
        foreach ($result['checks'] as $check) {
            if ($check['id'] === $id) {
                return $check['ok'];
            }
        }

        return false;
    }
}
