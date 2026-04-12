<?php

declare(strict_types=1);

namespace App\Listeners\Lark;

use App\Events\Lark\PayrollApprovedEvent;
use App\Services\Lark\LarkNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendPayrollApprovedNotification implements ShouldQueue
{
    public function handle(PayrollApprovedEvent $event): void
    {
        app(LarkNotificationService::class)->notifyPayrollApproved($event->payrollId, $event->period);
    }
}
