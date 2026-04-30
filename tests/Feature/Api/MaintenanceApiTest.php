<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\MaintenanceSchedule;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MaintenanceApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        return $user;
    }

    public function test_t10_01_create_maintenance_schedule_by_km_with_next_due_km(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/maintenance-schedules', [
            'vehicle_id' => $vehicle->id,
            'task_name' => 'Bao duong dinh ky',
            'interval_type' => 'by_km',
            'interval_km' => 5000,
            'last_done_km' => 100000,
        ], $this->tenant_headers($company));

        $response->assertStatus(201)->assertJsonPath('data.next_due_km', '105000.00');
    }

    public function test_t10_02_create_unscheduled_maintenance_record_without_schedule(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/maintenance-records', [
            'vehicle_id' => $vehicle->id,
            'type' => 'unscheduled',
            'title' => 'Sua lop dot xuat',
            'started_date' => now()->subDay()->toDateString(),
            'status' => 'completed',
            'odometer_km' => 120500,
            'completed_date' => now()->toDateString(),
        ], $this->tenant_headers($company));

        $response->assertStatus(201)->assertJsonPath('data.maintenance_schedule_id', null);
    }

    public function test_t10_03_completed_record_updates_next_due_fields(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        $schedule = MaintenanceSchedule::query()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'task_name' => 'Thay dau',
            'interval_type' => 'both',
            'interval_km' => 5000,
            'interval_days' => 30,
            'last_done_km' => 100000,
            'last_done_date' => now()->subDays(30)->toDateString(),
            'next_due_km' => 105000,
            'next_due_date' => now()->toDateString(),
        ]);

        $completedDate = now()->toDateString();
        $this->postJson('/api/v1/maintenance-records', [
            'vehicle_id' => $vehicle->id,
            'maintenance_schedule_id' => $schedule->id,
            'type' => 'scheduled',
            'title' => 'Bao duong thang',
            'started_date' => now()->subDay()->toDateString(),
            'status' => 'completed',
            'odometer_km' => 103000,
            'completed_date' => $completedDate,
        ], $this->tenant_headers($company))->assertStatus(201);

        $schedule->refresh();
        $this->assertSame('108000.00', number_format((float) $schedule->next_due_km, 2, '.', ''));
        $this->assertSame(now()->parse($completedDate)->addDays(30)->toDateString(), $schedule->next_due_date?->toDateString());
    }

    public function test_t10_04_due_soon_schedule_can_be_listed_by_next_due_date(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        Sanctum::actingAs($this->adminUser());

        MaintenanceSchedule::query()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'task_name' => 'Dang den han',
            'interval_type' => 'by_days',
            'interval_days' => 7,
            'next_due_date' => now()->addDays(3)->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/maintenance-schedules?sort=next_due_date', $this->tenant_headers($company));
        $response->assertStatus(200)->assertJsonCount(1, 'data.data');
    }
}
