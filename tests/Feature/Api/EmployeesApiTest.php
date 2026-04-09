<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use App\Models\Company;
use App\Models\Office;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_employees_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Employee::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/employees');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'meta'
                ],
            ]);
    }

    public function test_admin_can_create_employee(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['office_id' => $office->id]);
        $position = Position::factory()->create();

        $response = $this->postJson('/api/v1/employees', [
            'office_id' => $office->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'code' => 'EMP001',
            'name' => 'John Doe',
            'type' => 'office',
            'status' => 'active',
            'join_date' => '2026-01-01',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('employees', ['code' => 'EMP001']);
    }

    public function test_admin_can_show_employee(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $employee = Employee::factory()->create();

        $response = $this->getJson('/api/v1/employees/' . $employee->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $employee->id]);
    }

    public function test_admin_can_update_employee(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $employee = Employee::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson('/api/v1/employees/' . $employee->id, [
            'name' => 'New Empl Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'name' => 'New Empl Name']);
    }

    public function test_admin_can_delete_employee(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $employee = Employee::factory()->create();

        $response = $this->deleteJson('/api/v1/employees/' . $employee->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }
}
