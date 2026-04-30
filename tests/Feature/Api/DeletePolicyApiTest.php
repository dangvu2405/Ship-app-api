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

class DeletePolicyApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'role' => 'admin',
        ]);
    }

    public function test_company_delete_returns_operation_not_allowed(): void
    {
        Sanctum::actingAs($this->adminUser());
        $company = Company::factory()->create();

        $response = $this->deleteJson('/api/companies/'.$company->id);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'OPERATION_NOT_ALLOWED');
    }

    public function test_trip_delete_returns_operation_not_allowed(): void
    {
        Sanctum::actingAs($this->adminUser());

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
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'OPERATION_NOT_ALLOWED');
    }

    public function test_driver_delete_returns_dependency_restriction_when_has_active_trip(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        Trip::factory()->create([
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'status' => 'in_progress',
        ]);

        $response = $this->deleteJson('/api/drivers/'.$driver->id, [], $this->tenant_headers($company));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.code', 'DEPENDENCY_RESTRICTION')
            ->assertJsonStructure(['errors' => ['details']]);
    }
}
