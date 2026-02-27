<?php

declare(strict_types=1);

namespace Src\Application\Shared\Contracts;

interface EventDispatcherInterface
{
    /**
     * Dispatch an event.
     *
     * @param object $event
     * @return void
     */
    public function dispatch(object $event): void;

    /**
     * Dispatch multiple events.
     *
     * @param array<object> $events
     * @return void
     */
    public function dispatchMany(array $events): void;
}
