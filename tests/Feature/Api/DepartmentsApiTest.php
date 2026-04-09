<?php

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_departments_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Department::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/departments');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_department(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $office = Office::factory()->create();

        $response = $this->postJson('/api/v1/departments', [
            'office_id' => $office->id,
            'code' => 'DEP-TEST',
            'name' => 'Test Department',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('departments', ['code' => 'DEP-TEST']);
    }

    public function test_admin_can_show_department(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $dept = Department::factory()->create();

        $response = $this->getJson('/api/v1/departments/' . $dept->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $dept->id]);
    }

    public function test_admin_can_update_department(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $dept = Department::factory()->create();

        $response = $this->putJson('/api/v1/departments/' . $dept->id, [
            'name' => 'Updated Dept',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'Updated Dept']);
    }

    public function test_admin_can_delete_department(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $dept = Department::factory()->create();

        $response = $this->deleteJson('/api/v1/departments/' . $dept->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('departments', ['id' => $dept->id]);
    }
}
