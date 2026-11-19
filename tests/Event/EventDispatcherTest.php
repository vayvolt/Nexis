<?php

declare(strict_types=1);

namespace Nexis\Tests\Event;

use Nexis\Event\EventDispatcher;
use PHPUnit\Framework\TestCase;

final class EventDispatcherTest extends TestCase
{
    public function testDispatchesListeners(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = [];
        $dispatcher->listen(\stdClass::class, static function (object $event) use (&$seen): void {
            $seen[] = $event;
        });
        $event = new \stdClass();
        $dispatcher->dispatch($event);
        self::assertSame([$event], $seen);
    }
}
