<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverWorkSchedule;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_user_cannot_access_admin_endpoints(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/drivers');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden: Insufficient role',
            ]);
    }

    public function test_admin_can_access_admin_endpoints(): void
    {
        $adminRole = Role::factory()->create(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/drivers');

        $response->assertStatus(200);
    }

    public function test_user_permissions_can_grant_module_action_without_admin_role(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company->id, ['is_default' => true]);

        $schedule = DriverWorkSchedule::query()->create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'office_id' => $office->id,
            'work_date' => '2026-06-01',
            'shift_code' => 'day',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'vehicle_id' => null,
            'status' => 'approved',
        ]);

        UserPermission::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'module' => 'schedule',
            'can_view' => true,
            'can_approve' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Tenant-ID', (string) $company->id)
            ->putJson('/api/v1/workforce/driver-schedules/'.$schedule->id.'/lock');

        // Permission passed and lock action can be executed.
        $response->assertStatus(200);
    }

    public function test_user_permissions_deny_module_action_without_matching_flag(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $user = User::factory()->create(['status' => 'active']);
        $user->companies()->attach($company->id, ['is_default' => true]);

        $schedule = DriverWorkSchedule::query()->create([
            'driver_id' => $driver->id,
            'company_id' => $company->id,
            'office_id' => $office->id,
            'work_date' => '2026-06-02',
            'shift_code' => 'day',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'vehicle_id' => null,
            'status' => 'approved',
        ]);

        UserPermission::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'module' => 'schedule',
            'can_view' => true,
            'can_approve' => false,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->withHeader('X-Tenant-ID', (string) $company->id)
            ->putJson('/api/v1/workforce/driver-schedules/'.$schedule->id.'/lock');

        $response->assertStatus(403);
    }
}
