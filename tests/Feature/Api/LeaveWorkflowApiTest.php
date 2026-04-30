<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Office;
use App\Models\Role;
use App\Models\RouteTemplate;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class LeaveWorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::query()->firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create(['status' => 'active']);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        return $admin;
    }

    private function leaveType(): LeaveType
    {
        return LeaveType::query()->firstOrCreate(
            ['code' => 'ANNUAL'],
            ['name' => 'Annual Leave', 'is_paid' => true, 'status' => 'active', 'annual_quota_days' => 12],
        );
    }

    public function test_t14_01_create_leave_request(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $leaveType = $this->leaveType();
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/leave', [
            'driver_id' => $driver->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'total_days' => 2,
            'reason' => 'Family event',
        ], $this->tenant_headers($company));

        $response->assertStatus(201)
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_t14_02_invalid_leave_dates_return_422(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $leaveType = $this->leaveType();
        Sanctum::actingAs($this->adminUser());

        $response = $this->postJson('/api/v1/leave', [
            'driver_id' => $driver->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => now()->addDays(6)->toDateString(),
            'to_date' => now()->addDays(4)->toDateString(),
            'total_days' => 1,
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }

    public function test_t14_03_approve_leave_request(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $leaveType = $this->leaveType();
        $creator = $this->adminUser();
        $approver = $this->adminUser();

        Sanctum::actingAs($creator);
        $created = $this->postJson('/api/v1/leave', [
            'driver_id' => $driver->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(),
            'total_days' => 1,
        ], $this->tenant_headers($company))->assertStatus(201);

        $leaveId = (int) $created->json('data.id');
        Sanctum::actingAs($approver);
        $this->postJson('/api/v1/leave/'.$leaveId.'/approve', [], $this->tenant_headers($company))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_t14_04_reject_leave_without_reason_returns_422(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $leaveType = $this->leaveType();
        $creator = $this->adminUser();
        $approver = $this->adminUser();

        Sanctum::actingAs($creator);
        $created = $this->postJson('/api/v1/leave', [
            'driver_id' => $driver->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => now()->addDays(3)->toDateString(),
            'to_date' => now()->addDays(3)->toDateString(),
            'total_days' => 1,
        ], $this->tenant_headers($company))->assertStatus(201);
        $leaveId = (int) $created->json('data.id');

        Sanctum::actingAs($approver);
        $this->postJson('/api/v1/leave/'.$leaveId.'/reject', [], $this->tenant_headers($company))
            ->assertStatus(422);
    }

    public function test_t14_05_approved_leave_blocks_trip_assignment_creation(): void
    {
        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        $vehicle = Vehicle::factory()->create(['company_id' => $company->id, 'office_id' => $office->id, 'status' => 'active']);
        RouteTemplate::query()->create(['company_id' => $company->id, 'name' => 'HN-HP']);
        $leaveType = $this->leaveType();
        $creator = $this->adminUser();
        $approver = $this->adminUser();

        $tripDate = now()->addDays(5)->toDateString();
        Sanctum::actingAs($creator);
        $created = $this->postJson('/api/v1/leave', [
            'driver_id' => $driver->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => $tripDate,
            'to_date' => $tripDate,
            'total_days' => 1,
        ], $this->tenant_headers($company))->assertStatus(201);
        $leaveId = (int) $created->json('data.id');

        Sanctum::actingAs($approver);
        $this->postJson('/api/v1/leave/'.$leaveId.'/approve', [], $this->tenant_headers($company))->assertStatus(200);

        Sanctum::actingAs($creator);
        $response = $this->postJson('/api/v1/trips', [
            'code' => 'TRIP-T14-05',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'start_point' => 'Ha Noi',
            'end_point' => 'Hai Phong',
            'start_time' => $tripDate.' 08:00:00',
            'status' => 'pending',
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }
}
