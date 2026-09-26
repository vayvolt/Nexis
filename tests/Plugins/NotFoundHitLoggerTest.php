<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugins;

use Nexis\Event\PublicNotFound;
use Nexis\Plugins\Redirects\NotFoundHitLogger;
use Nexis\Site\SiteId;
use Nexis\Support\Uuid;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/plugins/nexis/redirects/src/NotFoundHitLogger.php';

final class NotFoundHitLoggerTest extends TestCase
{
    public function testExecuteUsesDistinctTimestampParams(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->expects($this->once())
            ->method('execute')
            ->with($this->callback(static function (array $params): bool {
                return isset($params['first_seen'], $params['last_seen'], $params['path'])
                    && !array_key_exists('now', $params)
                    && $params['path'] === '/missing-page';
            }))
            ->willReturn(true);

        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->once())
            ->method('prepare')
            ->with($this->callback(static function (string $sql): bool {
                return str_contains($sql, ':first_seen')
                    && str_contains($sql, ':last_seen')
                    && !str_contains($sql, ':now');
            }))
            ->willReturn($stmt);

        $logger = new NotFoundHitLogger($pdo);
        $logger->onPublicNotFound(new PublicNotFound(
            new SiteId(Uuid::v7()),
            '/missing-page',
            'de',
            'GET',
            null,
            'phpunit',
        ));
    }

    public function testSkipsAdminPathsWithoutQuery(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $logger = new NotFoundHitLogger($pdo);
        $logger->onPublicNotFound(new PublicNotFound(
            new SiteId(Uuid::v7()),
            '/admin/pages',
            'de',
            'GET',
            null,
            null,
        ));
    }
}
