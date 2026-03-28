<?php

namespace Tests\Feature\Api;

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
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Customer::factory()->count(2)->create();

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_customer(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/customers', [
            'type' => 'company',
            'name' => 'Acme Corp',
            'phone' => '0912345678',
            'email' => 'acme@example.com',
            'address' => '456 Business Ave',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', ['name' => 'Acme Corp']);
    }

    public function test_admin_can_show_customer(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $customer = Customer::factory()->create();

        $response = $this->getJson('/api/customers/' . $customer->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $customer->id]);
    }

    public function test_admin_can_update_customer(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $customer = Customer::factory()->create();

        $response = $this->putJson('/api/customers/' . $customer->id, [
            'name' => 'Updated Customer',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Updated Customer']);
    }

    public function test_admin_can_delete_customer(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $customer = Customer::factory()->create();

        $response = $this->deleteJson('/api/customers/' . $customer->id);

        $response->assertStatus(200);
    }
}
