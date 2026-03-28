<?php

namespace Tests\Feature\Api;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriversApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_drivers_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Driver::factory()->count(2)->create();

        $response = $this->getJson('/api/drivers');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_driver(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $employee = Employee::factory()->create(['type' => 'driver']);

        $response = $this->postJson('/api/drivers', [
            'employee_id' => $employee->id,
            'license_no' => 'DL-TEST-001',
            'license_class' => 'C',
            'expired_date' => '2028-12-31',
            'available_status' => 'available',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('drivers', ['license_no' => 'DL-TEST-001']);
    }

    public function test_admin_can_show_driver(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $driver = Driver::factory()->create();

        $response = $this->getJson('/api/drivers/' . $driver->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $driver->id]);
    }

    public function test_admin_can_update_driver(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $driver = Driver::factory()->create();

        $response = $this->putJson('/api/drivers/' . $driver->id, [
            'available_status' => 'busy',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('drivers', ['id' => $driver->id, 'available_status' => 'busy']);
    }

    public function test_admin_can_delete_driver(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $driver = Driver::factory()->create();

        $response = $this->deleteJson('/api/drivers/' . $driver->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('drivers', ['id' => $driver->id]);
    }
}
