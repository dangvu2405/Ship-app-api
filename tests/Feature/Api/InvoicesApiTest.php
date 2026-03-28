<?php

namespace Tests\Feature\Api;

use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_invoices_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Invoice::factory()->count(2)->create();

        $response = $this->getJson('/api/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_invoice(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $customer = Customer::factory()->create();

        $response = $this->postJson('/api/invoices', [
            'code' => 'INV-TEST-001',
            'customer_id' => $customer->id,
            'subtotal' => 5000000,
            'vat_rate' => 10,
            'vat_amount' => 500000,
            'total_amount' => 5500000,
            'status' => 'draft',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('invoices', ['code' => 'INV-TEST-001']);
    }

    public function test_admin_can_show_invoice(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $invoice = Invoice::factory()->create();

        $response = $this->getJson('/api/invoices/' . $invoice->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $invoice->id]);
    }

    public function test_admin_can_update_invoice(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $invoice = Invoice::factory()->create();

        $response = $this->putJson('/api/invoices/' . $invoice->id, [
            'status' => 'issued',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'issued']);
    }

    public function test_admin_can_delete_invoice(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $invoice = Invoice::factory()->create();

        $response = $this->deleteJson('/api/invoices/' . $invoice->id);

        $response->assertStatus(200);
    }
}
