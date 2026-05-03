<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CetaApiContractTest extends TestCase
{
    public function test_legacy_api_surfaces_are_removed(): void
    {
        $legacyFragments = [
            'payroll',
            'roles',
            'permissions/',
            'offices',
            'departments',
            'positions',
            'attendances',
            'overtime',
            'violations',
            'vehicle_expenses',
            'work-schedule-templates',
        ];

        $legacyRoutes = collect(Route::getRoutes())
            ->map(fn ($route): string => $route->uri())
            ->filter(fn (string $uri): bool => collect($legacyFragments)->contains(
                fn (string $fragment): bool => str_contains($uri, $fragment)
            ))
            ->values()
            ->all();

        $this->assertSame([], $legacyRoutes);
    }

    public function test_ceta_schema_routes_are_registered(): void
    {
        $registered = collect(Route::getRoutes())
            ->flatMap(fn ($route): array => collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD')
                ->map(fn (string $method): string => "{$method} {$route->uri()}")
                ->all())
            ->values()
            ->all();

        foreach ($this->requiredRoutes() as $route) {
            $this->assertContains($route, $registered, "Missing route [{$route}]");
        }
    }

    /**
     * A representative contract across the 14 CETA groups.
     *
     * @return list<string>
     */
    private function requiredRoutes(): array
    {
        return [
            'POST api/auth/login',
            'GET api/auth/me',
            'POST api/upload',
            'DELETE api/upload',
            'GET api/users',
            'PUT api/users/{id}/permissions',
            'PATCH api/users/{id}/status',
            'GET api/vehicle-types',
            'PATCH api/vehicle-types/reorder',
            'GET api/cargo-types',
            'GET api/cost-categories',
            'GET api/spare-parts',
            'GET api/locations/search',
            'GET api/route-templates',
            'GET api/order-status-configs',
            'GET api/customers/search',
            'GET api/customers/{id}/price-lists',
            'POST api/price-lookup',
            'GET api/vehicles/available',
            'GET api/vehicles/{id}/documents',
            'POST api/vehicles/{id}/assignments',
            'PATCH api/vehicles/{id}/assignments/release',
            'GET api/vehicles/{id}/maintenance-schedules',
            'PATCH api/maintenance-records/{id}/complete',
            'GET api/drivers/available',
            'GET api/drivers/{id}/documents',
            'GET api/driver-teams',
            'GET api/work-schedules',
            'POST api/work-schedules/generate',
            'PATCH api/work-schedules/{id}/approve',
            'GET api/leave-requests',
            'GET api/leave-types',
            'PATCH api/leave-requests/{id}/approve',
            'GET api/transport-requests',
            'GET api/trips',
            'PATCH api/trips/{id}/assign',
            'PATCH api/trips/{id}/start',
            'PATCH api/trips/{id}/complete',
            'GET api/trips/{id}/stops',
            'PATCH api/trips/{id}/stops/{childId}/complete',
            'GET api/trips/{id}/surcharges',
            'GET api/trips/{id}/documents',
            'GET api/trips/{id}/costs',
            'GET api/cost-approvals',
            'PATCH api/cost-approvals/{id}/approve',
            'GET api/reconciliations',
            'PATCH api/reconciliations/{id}/confirm',
            'GET api/customers/{id}/payments',
            'GET api/debt-overview',
            'GET api/invoices',
            'PATCH api/invoices/{id}/issue',
            'GET api/invoices/{id}/status-histories',
            'GET api/notifications',
            'PATCH api/notifications/read-all',
            'GET api/reports/dashboard',
            'POST api/reports/export',
            'GET api/dispatch/board',
        ];
    }
}
