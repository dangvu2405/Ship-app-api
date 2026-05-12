<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_customers_for_authenticated_user(): void
    {
        Customer::factory()->count(3)->create(['company_id' => $this->company->id]);

        $response = $this->getJson('/api/customers');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_returns_only_own_company_customers(): void
    {
        Customer::factory()->count(3)->create(['company_id' => $this->company->id]);
        $other = Customer::factory()->count(5)->create();

        $response = $this->getJson('/api/customers');

        $response->assertOk();
        $data = $response->json('data');

        $returnedIds = collect($data)->pluck('id')->all();
        foreach ($other as $o) {
            $this->assertNotContains($o->id, $returnedIds);
        }
    }

    public function test_store_creates_customer_with_valid_data(): void
    {
        $payload = [
            'name'  => 'Công ty ABC',
            'type'  => 'company',
            'phone' => '0909123456',
            'email' => 'abc@example.com',
        ];

        $response = $this->postJson('/api/customers', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Công ty ABC')
            ->assertJsonStructure(['data' => ['id', 'name', 'type', 'phone', 'email']]);

        $this->assertDatabaseHas('customers', [
            'name'       => 'Công ty ABC',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->postJson('/api/customers', [
            'type' => 'individual',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_valid_type(): void
    {
        $response = $this->postJson('/api/customers', [
            'name' => 'Customer X',
            'type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_duplicate_email(): void
    {
        Customer::factory()->create([
            'company_id' => $this->company->id,
            'email'      => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/customers', [
            'name'  => 'New Customer',
            'type'  => 'individual',
            'email' => 'existing@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_show_returns_customer_belonging_to_company(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $customer->id);
    }

    public function test_show_denies_access_to_other_company_customer(): void
    {
        $otherCustomer = Customer::factory()->create();

        $response = $this->getJson("/api/customers/{$otherCustomer->id}");

        $response->assertForbidden();
    }

    public function test_update_modifies_customer_name(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->putJson("/api/customers/{$customer->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('customers', [
            'id'   => $customer->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_denies_access_to_other_company_customer(): void
    {
        $otherCustomer = Customer::factory()->create();

        $response = $this->putJson("/api/customers/{$otherCustomer->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden();
    }

    public function test_destroy_soft_deletes_customer(): void
    {
        $customer = Customer::factory()->create(['company_id' => $this->company->id]);

        $response = $this->deleteJson("/api/customers/{$customer->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_destroy_denies_access_to_other_company_customer(): void
    {
        $otherCustomer = Customer::factory()->create();

        $response = $this->deleteJson("/api/customers/{$otherCustomer->id}");

        $response->assertForbidden();
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/customers');

        $response->assertUnauthorized();
    }
}
