<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Src\Application\Shared\Contracts\EventDispatcherInterface;
use Src\Application\Shared\Contracts\TransactionManagerInterface;
use Src\Domain\Employee\Repositories\EmployeeQueryRepositoryInterface;
use Src\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use Src\Infrastructure\Events\LaravelEventDispatcher;
use Src\Infrastructure\Persistence\Eloquent\Repositories\EloquentEmployeeQueryRepository;
use Src\Infrastructure\Persistence\Eloquent\Repositories\EloquentEmployeeRepository;
use Src\Infrastructure\Persistence\Mappers\EmployeeMapper;
use Src\Infrastructure\Persistence\TransactionManager;

final class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        // Infrastructure contracts
        TransactionManagerInterface::class => TransactionManager::class,
        EventDispatcherInterface::class => LaravelEventDispatcher::class,

        // Employee module repositories
        EmployeeQueryRepositoryInterface::class => EloquentEmployeeQueryRepository::class,
    ];

    public function register(): void
    {
        // Register mappers
        $this->app->singleton(EmployeeMapper::class, fn () => new EmployeeMapper());

        // Register repositories with dependencies
        $this->app->bind(
            EmployeeRepositoryInterface::class,
            fn ($app) => new EloquentEmployeeRepository(
                $app->make(EmployeeMapper::class)
            )
        );

        // Register LoggerInterface
        $this->app->bind(LoggerInterface::class, fn ($app) => $app->make('log'));
    }

    public function boot(): void
    {
        //
    }
}
