<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Position;
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
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Driver::factory()->count(2)->create(['office_id' => $office->id]);

        $response = $this->getJson('/api/v1/drivers', $this->tenant_headers($company));

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_driver(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['office_id' => $office->id]);
        $position = Position::factory()->create(['company_id' => $company->id]);

        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/drivers', [
            'code' => 'DRV-TEST-001',
            'name' => 'Test Driver',
            'office_id' => $office->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'status' => 'active',
            'join_date' => '2024-01-01',
            'license_no' => 'DL-TEST-001',
            'license_class' => 'C',
            'expired_date' => '2028-12-31',
            'available_status' => 'available',
        ], $this->tenant_headers($company));

        $response->assertStatus(201);
        $this->assertDatabaseHas('drivers', ['license_no' => 'DL-TEST-001']);
    }

    public function test_admin_can_show_driver(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $driver = Driver::factory()->create(['office_id' => $office->id]);

        $response = $this->getJson('/api/v1/drivers/'.$driver->id, $this->tenant_headers($company));

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $driver->id]);
    }

    public function test_admin_can_update_driver(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $driver = Driver::factory()->create(['office_id' => $office->id]);

        $response = $this->putJson('/api/v1/drivers/'.$driver->id, [
            'available_status' => 'busy',
        ], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertDatabaseHas('drivers', ['id' => $driver->id, 'available_status' => 'busy']);
    }

    public function test_admin_delete_driver_marks_inactive_instead_of_soft_delete(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $driver = Driver::factory()->create(['office_id' => $office->id]);

        $response = $this->deleteJson('/api/v1/drivers/'.$driver->id, [], $this->tenant_headers($company));

        $response->assertStatus(200);
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'status' => 'inactive',
            'available_status' => 'off',
        ]);
    }
}
