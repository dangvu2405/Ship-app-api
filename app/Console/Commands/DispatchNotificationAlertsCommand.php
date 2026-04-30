<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NotificationAlertService;
use Illuminate\Console\Command;

final class DispatchNotificationAlertsCommand extends Command
{
    protected $signature = 'alerts:dispatch-notifications {--within-days=7}';

    protected $description = 'Dispatch document expiry and cost approval notifications';

    public function handle(NotificationAlertService $notificationAlertService): int
    {
        $count = $notificationAlertService->dispatchDueAlerts((int) $this->option('within-days'));
        $this->info("Created {$count} notifications.");

        return self::SUCCESS;
    }
}
