<?php

namespace Tests\Feature\Api;

use App\Models\Deduction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeductionsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_deductions_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Deduction::factory()->count(2)->create();

        $response = $this->getJson('/api/deductions');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_deduction(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/deductions', [
            'code' => 'DED-BHXH',
            'name' => 'Social Insurance',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('deductions', ['code' => 'DED-BHXH']);
    }

    public function test_admin_can_show_deduction(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $ded = Deduction::factory()->create();

        $response = $this->getJson('/api/deductions/' . $ded->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $ded->id]);
    }

    public function test_admin_can_update_deduction(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $ded = Deduction::factory()->create();

        $response = $this->putJson('/api/deductions/' . $ded->id, [
            'name' => 'Updated Deduction',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('deductions', ['id' => $ded->id, 'name' => 'Updated Deduction']);
    }

    public function test_admin_can_delete_deduction(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $ded = Deduction::factory()->create();

        $response = $this->deleteJson('/api/deductions/' . $ded->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('deductions', ['id' => $ded->id]);
    }
}
