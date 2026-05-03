<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

final class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Generate work schedules daily at 23:00 for next day (Spec 2.4)
        $schedule->command('schedule:generate')->dailyAt('23:00')
            ->description('Generate daily work schedules from vehicle assignments');

        // Send expiry alerts daily at 07:00 (Spec 2.8)
        $schedule->command('alerts:expiry --days=7')->dailyAt('07:00')
            ->description('Check for expiring documents, maintenance, contracts and outstanding debt');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
