<?php

declare(strict_types=1);

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

final class TripAssignPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'role' => 'admin',
        ]);
    }

    public function test_assign_blocked_when_trip_already_has_other_driver(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driverA = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $driverB = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $trip = Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driverA->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/trips/'.$trip->id.'/assign', [
            'driver_id' => $driverB->id,
        ], $this->tenant_headers($company));

        $response->assertStatus(422)
            ->assertJsonPath('errors.code', 'OPERATION_NOT_ALLOWED');

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'driver_id' => $driverA->id,
        ]);
    }

    public function test_assign_blocked_when_driver_has_other_active_trip(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicleA = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $vehicleB = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        Trip::factory()->create([
            'vehicle_id' => $vehicleA->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'in_progress',
        ]);

        $targetTrip = Trip::factory()->create([
            'vehicle_id' => $vehicleB->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/trips/'.$targetTrip->id.'/assign', [
            'driver_id' => $driver->id,
        ], $this->tenant_headers($company));

        $response->assertStatus(422)
            ->assertJsonPath('errors.code', 'DEPENDENCY_RESTRICTION');
    }
}
