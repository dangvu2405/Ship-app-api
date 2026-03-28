<?php

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendancesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_attendances_index_returns_paginated_list(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Attendance::factory()->count(2)->create();

        $response = $this->getJson('/api/attendances');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_create_attendance(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $employee = Employee::factory()->create();

        $response = $this->postJson('/api/attendances', [
            'employee_id' => $employee->id,
            'date' => '2026-03-15',
            'check_in' => '08:00',
            'check_out' => '17:00',
            'work_hours' => 8,
            'overtime_hours' => 0,
            'status' => 'present',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendances', ['employee_id' => $employee->id, 'date' => '2026-03-15 00:00:00']);
    }

    public function test_admin_can_show_attendance(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $att = Attendance::factory()->create();

        $response = $this->getJson('/api/attendances/' . $att->id);

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $att->id]);
    }

    public function test_admin_can_update_attendance(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $att = Attendance::factory()->create();

        $response = $this->putJson('/api/attendances/' . $att->id, [
            'status' => 'late',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('attendances', ['id' => $att->id, 'status' => 'late']);
    }

    public function test_admin_can_delete_attendance(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $att = Attendance::factory()->create();

        $response = $this->deleteJson('/api/attendances/' . $att->id);

        $response->assertStatus(200);
    }
}
