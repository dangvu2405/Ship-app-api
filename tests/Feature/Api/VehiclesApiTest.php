<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Office;
use App\Models\Role;
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

    public function test_admin_can_delete_vehicle(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);

        $response = $this->deleteJson('/api/v1/vehicles/'.$vehicle->id, [], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    }
}
