<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Trip;
use App\Models\Role;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomersApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);

        return $user;
    }

    public function test_customers_index_returns_paginated_list(): void
    {
        $company = Company::factory()->create();
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Customer::factory()->count(2)->create(['company_id' => $company->id]);

        $response = $this->getJson('/api/v1/customers', $this->tenant_headers($company));

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_customer(): void
    {
        $company = Company::factory()->create();
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/customers', [
            'type' => 'company',
            'name' => 'Acme Corp',
            'phone' => '0912345678',
            'email' => 'acme@example.com',
            'address' => '456 Business Ave',
        ], $this->tenant_headers($company));

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', ['name' => 'Acme Corp']);
    }

    public function test_admin_can_show_customer(): void
    {
        $company = Company::factory()->create();
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->getJson('/api/v1/customers/'.$customer->id, $this->tenant_headers($company));

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $customer->id]);
    }

    public function test_admin_can_update_customer(): void
    {
        $company = Company::factory()->create();
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->putJson('/api/v1/customers/'.$customer->id, [
            'name' => 'Updated Customer',
        ], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Updated Customer']);
    }

    public function test_admin_can_delete_customer(): void
    {
        $company = Company::factory()->create();
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->deleteJson('/api/v1/customers/'.$customer->id, [], $this->tenant_headers($company));

        $response->assertStatus(200);
    }

    public function test_admin_cannot_delete_customer_when_has_any_trip(): void
    {
        $company = Company::factory()->create();
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $driver = Driver::factory()->create(['office_id' => $office->id]);
        Trip::query()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'code' => 'TRIP-LOCK-CUST-01',
            'start_point' => 'A',
            'end_point' => 'B',
            'status' => 'completed',
            'price' => 1000000,
        ]);

        $response = $this->deleteJson('/api/v1/customers/'.$customer->id, [], $this->tenant_headers($company));

        $response->assertStatus(422)
            ->assertJsonPath('errors.code', 'DEPENDENCY_RESTRICTION');
    }
}
