<?php

declare(strict_types=1);

namespace Src\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use Src\Application\Shared\Contracts\EventDispatcherInterface;

final readonly class LaravelEventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private Dispatcher $dispatcher
    ) {}

    public function dispatch(object $event): void
    {
        $this->dispatcher->dispatch($event);
    }

    /**
     * @param array<object> $events
     */
    public function dispatchMany(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
