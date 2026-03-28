<?php

namespace Tests\Feature\Api;

use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PositionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_positions_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Position::factory()->count(2)->create();

        $response = $this->getJson('/api/positions');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_position(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/positions', [
            'code' => 'POS-TEST',
            'name' => 'Senior Dev',
            'base_salary' => 20000000,
            'level' => 3,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('positions', ['code' => 'POS-TEST']);
    }

    public function test_admin_can_show_position(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $pos = Position::factory()->create();

        $response = $this->getJson('/api/positions/' . $pos->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $pos->id]);
    }

    public function test_admin_can_update_position(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $pos = Position::factory()->create();

        $response = $this->putJson('/api/positions/' . $pos->id, [
            'name' => 'Updated Position',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('positions', ['id' => $pos->id, 'name' => 'Updated Position']);
    }

    public function test_admin_can_delete_position(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $pos = Position::factory()->create();

        $response = $this->deleteJson('/api/positions/' . $pos->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('positions', ['id' => $pos->id]);
    }
}
