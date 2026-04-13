<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoggerInterface::class, fn ($app) => $app->make('log'));
    }

    public function boot(): void
    {
        //
    }
}
