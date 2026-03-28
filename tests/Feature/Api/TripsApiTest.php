<?php

namespace Tests\Feature\Api;

use App\Models\Trip;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Office;
use App\Models\Driver;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_trips_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Trip::factory()->count(2)->create();

        $response = $this->getJson('/api/trips');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta'
                ],
            ]);
    }

    public function test_admin_can_create_trip(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Driver::factory()->create();
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/trips', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'code' => 'TRP-001',
            'start_point' => 'HN',
            'end_point' => 'HCM',
            'status' => 'pending',
            'start_time' => '2026-05-01 08:00:00',
            'distance_km' => 1500,
            'price' => 20000000,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['code' => 'TRP-001']);

        $this->assertDatabaseHas('trips', ['code' => 'TRP-001']);
    }

    public function test_admin_can_update_trip(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);
        
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Driver::factory()->create();
        $customer = Customer::factory()->create();

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending'
        ]);

        $response = $this->putJson('/api/trips/' . $trip->id, [
            'status' => 'in_progress',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'in_progress']);
    }

    public function test_admin_can_delete_trip(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Driver::factory()->create();
        $customer = Customer::factory()->create();

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending'
        ]);

        $response = $this->deleteJson('/api/trips/' . $trip->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('trips', ['id' => $trip->id]);
    }
}
