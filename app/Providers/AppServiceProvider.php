<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Trip;
use App\Observers\DriverRagObserver;
use App\Observers\TripObserver;
use App\Observers\TripRagObserver;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
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
        ResetPassword::createUrlUsing(function (mixed $notifiable, string $token): string {
            $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');

            return $frontend.'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });

        Trip::observe(TripObserver::class);
        Trip::observe(TripRagObserver::class);
        Driver::observe(DriverRagObserver::class);

        // Ghi lại Log Login (listener optional in some deployments).
        if (class_exists(\App\Listeners\LogSuccessfulLogin::class)) {
            \Illuminate\Support\Facades\Event::listen(
                \Illuminate\Auth\Events\Login::class,
                \App\Listeners\LogSuccessfulLogin::class
            );
        }

        // Legacy users.role is still the primary seeded RBAC path. Admins get
        // full permissions only inside the resolved tenant; super_admin stays global.
        Gate::before(function ($user, string $ability, array $arguments = []) {
            if ($user->hasRole('super_admin')) {
                return true;
            }

            if (! $user->hasRole('admin')) {
                return null;
            }

            $tenantCompanyId = app(TenantContext::class)->getCompanyId()
                ?? ($user->company_id !== null ? (int) $user->company_id : null);

            if ($tenantCompanyId === null || $tenantCompanyId <= 0) {
                return null;
            }

            $hasScopedArgument = false;

            foreach ($arguments as $argument) {
                if ($argument instanceof Company) {
                    $hasScopedArgument = true;
                    if ((int) $argument->getKey() !== $tenantCompanyId) {
                        return false;
                    }

                    continue;
                }

                if (is_object($argument) && isset($argument->company_id)) {
                    $hasScopedArgument = true;
                    if ((int) $argument->company_id !== $tenantCompanyId) {
                        return false;
                    }
                }
            }

            return $hasScopedArgument ? true : null;
        });
    }
}
