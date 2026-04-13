<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
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

        $response = $this->getJson('/api/v1/trips');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta',
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
        $driver = Driver::factory()->create(['office_id' => $office->id]);
        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/v1/trips', [
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
        $driver = Driver::factory()->create(['office_id' => $office->id]);
        $customer = Customer::factory()->create();

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = $this->putJson('/api/v1/trips/'.$trip->id, [
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
        $driver = Driver::factory()->create(['office_id' => $office->id]);
        $customer = Customer::factory()->create();

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = $this->deleteJson('/api/v1/trips/'.$trip->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('trips', ['id' => $trip->id]);
    }

    public function test_trips_index_filters_by_company_id_and_office_id(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $companyA = Company::factory()->create();
        $officeA = Office::factory()->create(['company_id' => $companyA->id]);
        $vehicleA = Vehicle::factory()->create(['office_id' => $officeA->id]);

        $companyB = Company::factory()->create();
        $officeB = Office::factory()->create(['company_id' => $companyB->id]);
        $vehicleB = Vehicle::factory()->create(['office_id' => $officeB->id]);

        $driverA = Driver::factory()->create(['office_id' => $officeA->id]);
        $driverB = Driver::factory()->create(['office_id' => $officeB->id]);
        $customer = Customer::factory()->create();

        $tripA = Trip::factory()->create([
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driverA->id,
            'customer_id' => $customer->id,
        ]);
        $tripB = Trip::factory()->create([
            'vehicle_id' => $vehicleB->id,
            'driver_id' => $driverB->id,
            'customer_id' => $customer->id,
        ]);

        $byCompany = $this->getJson('/api/v1/trips?company_id='.$companyA->id.'&per_page=100');
        $byCompany->assertStatus(200);
        $ids = collect($byCompany->json('data.data'))->pluck('id')->all();
        $this->assertContains($tripA->id, $ids);
        $this->assertNotContains($tripB->id, $ids);

        $byOffice = $this->getJson('/api/v1/trips?office_id='.$officeA->id.'&per_page=100');
        $byOffice->assertStatus(200);
        $idsOffice = collect($byOffice->json('data.data'))->pluck('id')->all();
        $this->assertContains($tripA->id, $idsOffice);
        $this->assertNotContains($tripB->id, $idsOffice);
    }
}
