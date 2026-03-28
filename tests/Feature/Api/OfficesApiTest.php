<?php

namespace Tests\Feature\Api;

use App\Models\Office;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_offices_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Office::factory()->count(2)->create();

        $response = $this->getJson('/api/offices');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_office(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();

        $response = $this->postJson('/api/offices', [
            'company_id' => $company->id,
            'code' => 'OFF-TEST',
            'name' => 'Test Office',
            'address' => '123 Main St',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('offices', ['code' => 'OFF-TEST']);
    }

    public function test_admin_can_show_office(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $office = Office::factory()->create();

        $response = $this->getJson('/api/offices/' . $office->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $office->id]);
    }

    public function test_admin_can_update_office(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $office = Office::factory()->create();

        $response = $this->putJson('/api/offices/' . $office->id, [
            'name' => 'Updated Office Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('offices', ['id' => $office->id, 'name' => 'Updated Office Name']);
    }

    public function test_admin_can_delete_office(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $office = Office::factory()->create();

        $response = $this->deleteJson('/api/offices/' . $office->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('offices', ['id' => $office->id]);
    }
}
