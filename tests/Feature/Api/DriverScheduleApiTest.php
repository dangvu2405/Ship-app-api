<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverWorkSchedule;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DriverScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active', 'driver_id' => null]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_index_rejects_invalid_status_query(): void
    {
        $company = Company::factory()->create();
        $this->actingAdmin();

        $response = $this->getJson(
            '/api/v1/driver-schedules?status=not-a-status',
            ['X-Tenant-ID' => (string) $company->id],
        );

        $response->assertStatus(422);
    }

    public function test_hos_check_returns_summary_shape(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);

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
            'notes' => null,
        ]);

        $this->actingAdmin();

        $response = $this->getJson(
            '/api/v1/driver-schedules/'.$schedule->id.'/hos-check',
            ['X-Tenant-ID' => (string) $company->id],
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.work_date', '2026-06-01')
            ->assertJsonStructure(['data' => ['total_hours', 'limit_hours', 'is_ok']]);
    }
}
