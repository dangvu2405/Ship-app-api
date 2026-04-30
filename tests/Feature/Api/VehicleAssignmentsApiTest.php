<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Office;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleAssignmentsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'role' => 'admin',
        ]);
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
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);

        $response = $this->postJson('/api/v1/vehicle_assignments', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'from_date' => '2026-01-01',
        ], $this->tenant_headers($company));

        $response->assertStatus(201);
        $this->assertDatabaseHas('vehicle_assignments', ['vehicle_id' => $vehicle->id]);
    }

    public function test_admin_can_delete_vehicle_assignment(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);

        $va = VehicleAssignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
        ]);

        $response = $this->deleteJson('/api/v1/vehicle_assignments/'.$va->id, [], $this->tenant_headers($company));

        $response->assertStatus(200);
    }

    public function test_cannot_create_overlapping_assignment_for_same_vehicle(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driverA = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driverB = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);

        VehicleAssignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverA->id,
            'from_date' => '2026-01-01',
            'to_date' => null,
        ]);

        $response = $this->postJson('/api/v1/vehicle_assignments', [
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverB->id,
            'from_date' => '2026-02-01',
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }

    public function test_cannot_create_overlapping_assignment_for_same_driver(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicleA = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $vehicleB = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);

        VehicleAssignment::factory()->create([
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driver->id,
            'from_date' => '2026-01-01',
            'to_date' => null,
        ]);

        $response = $this->postJson('/api/v1/vehicle_assignments', [
            'vehicle_id' => $vehicleB->id,
            'driver_id' => $driver->id,
            'from_date' => '2026-02-01',
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }
}
