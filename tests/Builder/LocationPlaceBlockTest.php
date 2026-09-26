<?php

declare(strict_types=1);

namespace Nexis\Tests\Builder;

use Nexis\Builder\Core\LocationPlaceBlock;
use PHPUnit\Framework\TestCase;

final class LocationPlaceBlockTest extends TestCase
{
    public function testRendersAddressWithoutMapWhenCoordsMissing(): void
    {
        $html = (new LocationPlaceBlock())->render([
            'type' => 'nexis/location/place',
            'props' => [
                'name' => 'Büro',
                'address' => "Hauptstr. 1\n12345 Stadt",
                'phone' => '+49 30 123',
                'email' => 'hi@example.test',
            ],
        ], []);
        self::assertStringContainsString('Büro', $html);
        self::assertStringContainsString('Hauptstr. 1', $html);
        self::assertStringContainsString('tel:', $html);
        self::assertStringContainsString('mailto:hi@example.test', $html);
        self::assertStringNotContainsString('openstreetmap.org/export/embed', $html);
    }

    public function testRendersOsmEmbedWithCoords(): void
    {
        $html = (new LocationPlaceBlock())->render([
            'type' => 'nexis/location/place',
            'props' => [
                'name' => 'Büro',
                'lat' => '52.52',
                'lon' => '13.405',
            ],
        ], []);
        self::assertStringContainsString('openstreetmap.org/export/embed', $html);
        self::assertStringContainsString('iframe', $html);
    }
}
