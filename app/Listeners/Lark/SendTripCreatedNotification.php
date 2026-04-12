<?php

declare(strict_types=1);

namespace App\Listeners\Lark;

use App\Events\Lark\TripCreatedEvent;
use App\Jobs\Lark\SyncLarkBaseRecordJob;
use App\Services\Lark\LarkNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTripCreatedNotification implements ShouldQueue
{
    public function handle(TripCreatedEvent $event): void
    {
        app(LarkNotificationService::class)->notifyTripCreated($event->tripId, $event->tripCode);
        SyncLarkBaseRecordJob::dispatch('trip', $event->tripId);
    }
}
