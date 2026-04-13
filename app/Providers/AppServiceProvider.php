<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Trip;
use App\Observers\PayrollLineObserver;
use App\Observers\PayrollObserver;
use App\Observers\TripObserver;
use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, static fn (): TenantContext => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Trip::observe(TripObserver::class);
        Payroll::observe(PayrollObserver::class);
        PayrollLine::observe(PayrollLineObserver::class);

        // Ghi lại Log Login
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            \App\Listeners\LogSuccessfulLogin::class
        );
    }
}
