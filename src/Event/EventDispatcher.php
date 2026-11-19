<?php

declare(strict_types=1);

namespace Nexis\Event;

final class EventDispatcher
{
    /** @var array<class-string, list<callable>> */
    private array $listeners = [];

    /**
     * @template T of object
     * @param class-string<T> $eventClass
     * @param callable(T): void $listener
     */
    public function listen(string $eventClass, callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): object
    {
        $class = $event::class;
        foreach ($this->listeners[$class] ?? [] as $listener) {
            $listener($event);
        }

        return $event;
    }
}
