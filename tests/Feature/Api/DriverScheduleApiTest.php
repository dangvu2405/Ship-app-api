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

    public function test_t13_01_create_driver_schedule_for_date(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $this->actingAdmin();

        $workDate = now()->addDay()->toDateString();
        $response = $this->postJson('/api/v1/driver-schedules', [
            'driver_id' => $driver->id,
            'office_id' => $office->id,
            'work_date' => $workDate,
            'shift_code' => 'day',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ], ['X-Tenant-ID' => (string) $company->id]);

        $response->assertStatus(201)
            ->assertJsonPath('data.driver_id', $driver->id);
        $this->assertStringStartsWith($workDate, (string) $response->json('data.work_date'));
    }

    public function test_t13_02_duplicate_shift_same_day_returns_422(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $this->actingAdmin();

        $workDate = now()->addDay()->toDateString();
        $payload = [
            'driver_id' => $driver->id,
            'office_id' => $office->id,
            'work_date' => $workDate,
            'shift_code' => 'day',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ];

        $this->postJson('/api/v1/driver-schedules', $payload, ['X-Tenant-ID' => (string) $company->id])->assertStatus(201);
        $this->postJson('/api/v1/driver-schedules', $payload, ['X-Tenant-ID' => (string) $company->id])->assertStatus(422);
    }

    public function test_t13_03_schedule_workflow_draft_submitted_approved(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $this->actingAdmin();

        $workDate = now()->addDay()->toDateString();
        $created = $this->postJson('/api/v1/driver-schedules', [
            'driver_id' => $driver->id,
            'office_id' => $office->id,
            'work_date' => $workDate,
            'shift_code' => 'day',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ], ['X-Tenant-ID' => (string) $company->id])->assertStatus(201);

        $id = (int) $created->json('data.id');
        $this->postJson('/api/v1/driver-schedules/'.$id.'/submit', [], ['X-Tenant-ID' => (string) $company->id])->assertStatus(200);
        $this->postJson('/api/v1/driver-schedules/'.$id.'/approve', [], ['X-Tenant-ID' => (string) $company->id])->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_t13_04_query_driver_schedules_for_tomorrow_returns_expected_rows(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $this->actingAdmin();

        $tomorrow = now()->addDay()->toDateString();
        $create = $this->postJson('/api/v1/driver-schedules', [
            'driver_id' => $driver->id,
            'office_id' => $office->id,
            'work_date' => $tomorrow,
            'shift_code' => 'day',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ], ['X-Tenant-ID' => (string) $company->id])->assertStatus(201);
        $id = (int) $create->json('data.id');
        $this->postJson('/api/v1/driver-schedules/'.$id.'/submit', [], ['X-Tenant-ID' => (string) $company->id])->assertStatus(200);
        $this->postJson('/api/v1/driver-schedules/'.$id.'/approve', [], ['X-Tenant-ID' => (string) $company->id])->assertStatus(200);

        $response = $this->getJson('/api/v1/driver-schedules?work_date='.$tomorrow, ['X-Tenant-ID' => (string) $company->id]);
        $response->assertStatus(200);
        $this->assertStringContainsString((string) $driver->id, (string) $response->getContent());
    }

    public function test_t13_05_hos_violation_requires_override_reason(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $this->actingAdmin();

        $workDate = now()->addDay()->toDateString();
        $created = $this->postJson('/api/v1/driver-schedules', [
            'driver_id' => $driver->id,
            'office_id' => $office->id,
            'work_date' => $workDate,
            'shift_code' => 'day',
            'start_time' => '08:00',
            'end_time' => '20:30',
        ], ['X-Tenant-ID' => (string) $company->id])->assertStatus(201);
        $id = (int) $created->json('data.id');

        $this->postJson('/api/v1/driver-schedules/'.$id.'/submit', [], ['X-Tenant-ID' => (string) $company->id])->assertStatus(200);

        $this->postJson('/api/v1/driver-schedules/'.$id.'/approve', [], ['X-Tenant-ID' => (string) $company->id])
            ->assertStatus(422);

        $this->postJson('/api/v1/driver-schedules/'.$id.'/approve', [
            'hos_override' => true,
        ], ['X-Tenant-ID' => (string) $company->id])->assertStatus(422);

        $this->postJson('/api/v1/driver-schedules/'.$id.'/approve', [
            'hos_override' => true,
            'override_reason' => 'Emergency dispatch coverage',
        ], ['X-Tenant-ID' => (string) $company->id])->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }
}
