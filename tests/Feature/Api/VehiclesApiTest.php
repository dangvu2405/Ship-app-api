<?php

namespace Tests\Feature\Api;

use App\Models\Vehicle;
use App\Models\Company;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
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
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Vehicle::factory()->count(2)->create();

        $response = $this->getJson('/api/vehicles');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta'
                ],
            ]);
    }

    public function test_admin_can_create_vehicle(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);

        $response = $this->postJson('/api/vehicles', [
            'office_id' => $office->id,
            'code' => 'V-001',
            'plate_number' => '51C-12345',
            'type' => 'truck',
            'weight_capacity' => 10000,
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['plate_number' => '51C-12345']);

        $this->assertDatabaseHas('vehicles', ['plate_number' => '51C-12345']);
    }

    public function test_admin_can_update_vehicle(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id, 'status' => 'active']);

        $response = $this->putJson('/api/vehicles/' . $vehicle->id, [
            'status' => 'maintenance',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vehicles', ['id' => $vehicle->id, 'status' => 'maintenance']);
    }

    public function test_admin_can_delete_vehicle(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);

        $response = $this->deleteJson('/api/vehicles/' . $vehicle->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }
}
