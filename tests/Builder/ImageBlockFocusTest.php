<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Builder\Core\ImageBlock;
use PHPUnit\Framework\TestCase;

final class ImageBlockFocusTest extends TestCase
{
    public function testRendersObjectPositionFromFocusProps(): void
    {
        $html = (new ImageBlock())->render([
            'type' => 'core/image',
            'props' => [
                'src' => '/img/a.jpg',
                'alt' => 'A',
                'focusX' => '25',
                'focusY' => '75',
            ],
        ], []);

        self::assertStringContainsString('object-position: 25% 75%', $html);
        self::assertStringContainsString('alt="A"', $html);
    }

    public function testOmitsDefaultCenterFocusStyle(): void
    {
        $html = (new ImageBlock())->render([
            'type' => 'core/image',
            'props' => [
                'src' => '/img/a.jpg',
                'alt' => 'A',
                'focusX' => '50',
                'focusY' => '50',
            ],
        ], []);

        self::assertStringNotContainsString('object-position', $html);
    }
}
