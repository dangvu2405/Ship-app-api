<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UsersRolesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    // ========== Users CRUD ==========

    public function test_users_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_create_user(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/users', [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_admin_can_show_user(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/users/' . $user->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $user->id]);
    }

    public function test_admin_can_delete_user(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $user = User::factory()->create();

        $response = $this->deleteJson('/api/v1/users/' . $user->id);

        $response->assertStatus(200);
    }

    // ========== Roles CRUD ==========

    public function test_roles_index_returns_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_admin_can_create_role(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/roles', [
            'name' => 'manager',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('roles', ['name' => 'manager']);
    }

    // ========== Sync Permissions ==========

    public function test_admin_can_sync_permissions(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $role = Role::create(['name' => 'test_role']);
        $perm1 = Permission::firstOrCreate(['code' => 'payroll.approve', 'name' => 'Approve Payroll']);
        $perm2 = Permission::firstOrCreate(['code' => 'payroll.lock', 'name' => 'Lock Payroll']);

        $response = $this->postJson('/api/v1/roles/' . $role->id . '/permissions', [
            'permission_ids' => [$perm1->id, $perm2->id],
        ]);

        $response->assertStatus(200);
    }
}
