<?php

declare(strict_types=1);

namespace App\Listeners\Lark;

use App\Events\Lark\DriverAssignedEvent;
use App\Services\Lark\LarkNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDriverAssignedNotification implements ShouldQueue
{
    public function handle(DriverAssignedEvent $event): void
    {
        app(LarkNotificationService::class)->notifyDriverAssigned($event->assignmentId, $event->driverId, $event->vehicleId);
    }
}
