<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehiclesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);

        return $user;
    }

    public function test_vehicles_index_returns_paginated_list(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Vehicle::factory()->count(2)->create(['office_id' => $office->id]);

        $response = $this->getJson('/api/v1/vehicles', $this->tenant_headers($company));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta',
                ],
            ]);
    }

    public function test_admin_can_create_vehicle(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/vehicles', [
            'office_id' => $office->id,
            'plate_number' => '51C-12345',
            'type' => 'truck',
            'capacity' => 10000,
            'status' => 'active',
        ], $this->tenant_headers($company));

        $response->assertStatus(201)
            ->assertJsonFragment(['plate_number' => '51C-12345']);

        $this->assertDatabaseHas('vehicles', ['plate_number' => '51C-12345']);
    }

    public function test_admin_can_update_vehicle(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $vehicle = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);

        $response = $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'status' => 'maintenance',
        ], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'status' => 'maintenance']);
    }

    public function test_admin_delete_vehicle_marks_out_of_service_instead_of_soft_delete(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);

        $response = $this->deleteJson('/api/v1/vehicles/'.$vehicle->id, [], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'status' => 'out_of_service']);
    }

    public function test_vehicle_plate_number_must_be_unique(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'plate_number' => '51D-99999',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/vehicles', [
            'office_id' => $office->id,
            'plate_number' => '51D-99999',
            'type' => 'truck',
            'capacity' => 1000,
            'status' => 'active',
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }

    public function test_update_vehicle_odometer_successfully(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $vehicle = Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);

        $response = $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'current_odometer_km' => 125000,
        ], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'current_odometer_km' => 125000]);
    }

    public function test_cannot_mark_vehicle_broken_or_maintenance_while_trip_in_progress(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $vehicle = Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);

        Trip::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'status' => 'in_progress',
        ]);

        $maintenanceResponse = $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'status' => 'maintenance',
        ], $this->tenant_headers($company));
        $maintenanceResponse->assertStatus(422);

        $brokenResponse = $this->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'status' => 'broken',
        ], $this->tenant_headers($company));
        $brokenResponse->assertStatus(422);
    }

    public function test_filter_vehicles_by_status_returns_expected_records(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'maintenance']);

        $response = $this->getJson('/api/v1/vehicles?status=active', $this->tenant_headers($company));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.status', 'active');
    }
}
