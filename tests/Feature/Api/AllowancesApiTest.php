<?php

namespace Tests\Feature\Api;

use App\Models\Allowance;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AllowancesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_allowances_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Allowance::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/allowances');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_allowance(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/allowances', [
            'code' => 'ALL-MEAL',
            'name' => 'Meal Allowance',
            'default_amount' => 500000,
            'taxable' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('allowances', ['code' => 'ALL-MEAL']);
    }

    public function test_admin_can_show_allowance(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $allowance = Allowance::factory()->create();

        $response = $this->getJson('/api/v1/allowances/' . $allowance->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $allowance->id]);
    }

    public function test_admin_can_update_allowance(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $allowance = Allowance::factory()->create();

        $response = $this->putJson('/api/v1/allowances/' . $allowance->id, [
            'name' => 'Updated Allowance',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('allowances', ['id' => $allowance->id, 'name' => 'Updated Allowance']);
    }

    public function test_admin_can_delete_allowance(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $allowance = Allowance::factory()->create();

        $response = $this->deleteJson('/api/v1/allowances/' . $allowance->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('allowances', ['id' => $allowance->id]);
    }
}
