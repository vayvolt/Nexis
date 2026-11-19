<?php

declare(strict_types=1);

namespace Nexis\Tests\Plugin;

use Nexis\Plugins\Blog\BlogPaths;
use PHPUnit\Framework\TestCase;

final class BlogPathsTest extends TestCase
{
    protected function setUp(): void
    {
        $matches = glob(dirname(__DIR__, 2) . '/plugins/*/blog/src/BlogPaths.php') ?: [];
        if ($matches === []) {
            self::markTestSkipped('Blog plugin not installed under plugins/*/blog');
        }
        require_once $matches[0];
    }

    public function testArchiveAndPostPaths(): void
    {
        self::assertSame('/blog', BlogPaths::archivePath());
        self::assertSame('/blog/mein-beitrag', BlogPaths::postPath('Mein Beitrag'));
    }
}
