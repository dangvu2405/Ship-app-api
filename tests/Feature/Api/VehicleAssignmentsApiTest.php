<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Office;
use App\Models\Vehicle;
use App\Models\Employee;
use App\Models\VehicleAssignment;
use App\Models\VehicleExpense;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleAssignmentsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_vehicle_assignments_index_returns_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/vehicle_assignments');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_create_vehicle_assignment(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Employee::factory()->create(['type' => 'driver']);

        $response = $this->postJson('/api/v1/vehicle_assignments', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'from_date' => '2026-01-01',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vehicle_assignments', ['vehicle_id' => $vehicle->id]);
    }

    public function test_admin_can_delete_vehicle_assignment(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Employee::factory()->create(['type' => 'driver']);

        $va = VehicleAssignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
        ]);

        $response = $this->deleteJson('/api/v1/vehicle_assignments/' . $va->id);

        $response->assertStatus(200);
    }
}
