<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
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
}
