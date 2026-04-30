<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TripsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'role' => 'admin',
        ]);
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
        $vehicle = Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->postJson('/api/trips', [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'code' => 'TRP-001',
            'start_point' => 'HN',
            'end_point' => 'HCM',
            'status' => 'pending',
            'distance_km' => 1500,
            'price' => 20000000,
        ], $this->tenant_headers($company));

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
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = $this->putJson('/api/trips/'.$trip->id, [
            'status' => 'assigned',
            'start_time' => '2026-05-01 08:00:00',
        ], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'assigned']);
    }

    public function test_admin_cannot_delete_trip_due_to_policy(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = $this->deleteJson('/api/trips/'.$trip->id, [], $this->tenant_headers($company));

        $response->assertStatus(422)
            ->assertJsonPath('errors.code', 'OPERATION_NOT_ALLOWED');
        $this->assertDatabaseHas('trips', ['id' => $trip->id]);
    }

    public function test_trips_index_filters_by_company_id(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $companyA = Company::factory()->create();
        $officeA = Office::factory()->create(['company_id' => $companyA->id]);
        $vehicleA = Vehicle::factory()->create(['company_id' => $companyA->id, 'office_id' => $officeA->id]);

        $companyB = Company::factory()->create();
        $officeB = Office::factory()->create(['company_id' => $companyB->id]);
        $vehicleB = Vehicle::factory()->create(['company_id' => $companyB->id, 'office_id' => $officeB->id]);

        $driverA = Driver::factory()->create(['company_id' => $companyA->id, 'office_id' => $officeA->id]);
        $driverB = Driver::factory()->create(['company_id' => $companyB->id, 'office_id' => $officeB->id]);
        $customer = Customer::factory()->create(['company_id' => $companyA->id]);

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

        $byCompany = $this->getJson('/api/trips?company_id='.$companyA->id.'&per_page=100', $this->tenant_headers($companyA));
        $byCompany->assertStatus(200);
        $ids = collect($byCompany->json('data.data'))->pluck('id')->all();
        $this->assertContains($tripA->id, $ids);
        $this->assertNotContains($tripB->id, $ids);

    }

    public function test_trip_lifecycle_follows_business_statuses(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
            'start_time' => '2026-05-01 08:00:00',
            'end_time' => null,
        ]);

        $this->putJson('/api/trips/'.$trip->id, ['status' => 'assigned'], $this->tenant_headers($company))
            ->assertStatus(200);
        $this->putJson('/api/trips/'.$trip->id, ['status' => 'in_transit'], $this->tenant_headers($company))
            ->assertStatus(200);
        $this->putJson('/api/trips/'.$trip->id, [
            'status' => 'delivered',
            'end_time' => '2026-05-01 12:00:00',
        ], $this->tenant_headers($company))
            ->assertStatus(200);
        $this->postJson('/api/trips/'.$trip->id.'/complete', [], $this->tenant_headers($company))
            ->assertStatus(200);

        $this->assertDatabaseHas('trips', ['id' => $trip->id, 'status' => 'completed']);

        $this->postJson('/api/trips/'.$trip->id.'/cancel', ['reason' => 'late cancel'], $this->tenant_headers($company))
            ->assertStatus(422);
    }

    public function test_trip_rejects_invalid_transition_and_records_history_for_valid_updates(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
            'start_time' => '2026-05-02 08:00:00',
        ]);

        $invalid = $this->putJson('/api/trips/'.$trip->id, [
            'status' => 'delivered',
            'end_time' => '2026-05-02 12:00:00',
        ], $this->tenant_headers($company));
        $invalid->assertStatus(422);

        $this->putJson('/api/trips/'.$trip->id, ['status' => 'assigned'], $this->tenant_headers($company))
            ->assertStatus(200);
        $this->putJson('/api/trips/'.$trip->id, ['status' => 'in_transit'], $this->tenant_headers($company))
            ->assertStatus(200);

        $this->postJson('/api/trips/'.$trip->id.'/complete', [], $this->tenant_headers($company))
            ->assertStatus(200);

        $this->assertDatabaseHas('trip_status_histories', [
            'trip_id' => $trip->id,
            'to_status' => 'completed',
        ]);
    }
}
