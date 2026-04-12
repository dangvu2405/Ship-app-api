<?php

namespace Tests\Feature\Services;

use App\Models\Role;
use App\Models\User;
use App\Services\Lark\LarkCommandRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LarkCommandRouterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_cannot_access_payroll_command(): void
    {
        $driverRole = Role::create(['name' => 'driver']);
        $user = User::factory()->create();
        $user->roles()->sync([$driverRole->id]);

        $service = app(LarkCommandRouterService::class);
        $response = $service->handle($user, '/payroll 03-2026');

        $this->assertSame('Forbidden: driver cannot access payroll command.', $response);
    }

    public function test_unknown_command_returns_helpful_message(): void
    {
        $adminRole = Role::create(['name' => 'admin']);
        $user = User::factory()->create();
        $user->roles()->sync([$adminRole->id]);

        $service = app(LarkCommandRouterService::class);
        $response = $service->handle($user, '/unknown');

        $this->assertSame('Unknown command. Supported: /trip, /status, /payroll', $response);
    }
}
