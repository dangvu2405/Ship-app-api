<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Lark\DriverAssignedEvent;
use App\Events\Lark\PayrollApprovedEvent;
use App\Events\Lark\TripCreatedEvent;
use App\Jobs\Lark\SyncLarkBaseRecordJob;
use App\Listeners\Lark\SendDriverAssignedNotification;
use App\Listeners\Lark\SendPayrollApprovedNotification;
use App\Listeners\Lark\SendTripCreatedNotification;
use App\Models\Employee;
use App\Models\Trip;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ghi lại Log Login
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\LogSuccessfulLogin::class
        );
        \Illuminate\Support\Facades\Event::listen(TripCreatedEvent::class, SendTripCreatedNotification::class);
        \Illuminate\Support\Facades\Event::listen(PayrollApprovedEvent::class, SendPayrollApprovedNotification::class);
        \Illuminate\Support\Facades\Event::listen(DriverAssignedEvent::class, SendDriverAssignedNotification::class);
        Employee::saved(static function (Employee $employee): void {
            SyncLarkBaseRecordJob::dispatch('employee', (int) $employee->id);
        });
        Trip::updated(static function (Trip $trip): void {
            SyncLarkBaseRecordJob::dispatch('trip', (int) $trip->id);
        });

        // Register Clean Architecture API routes (v2)
        Route::prefix('api')
            ->middleware('api')
            ->group(base_path('routes/api_v2.php'));
    }
}
