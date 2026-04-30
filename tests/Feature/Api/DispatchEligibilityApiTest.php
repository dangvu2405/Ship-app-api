<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\LeaveType;
use App\Models\Office;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class DispatchEligibilityApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
            'role' => 'admin',
        ]);
    }

    public function test_cannot_create_trip_with_vehicle_in_maintenance_or_broken(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'status' => 'maintenance',
        ]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        $response = $this->postJson('/api/trips', [
            'code' => 'TRIP-DISPATCH-VEH-01',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'start_point' => 'HCM',
            'end_point' => 'BD',
            'status' => 'pending',
            'price' => 500000,
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }

    public function test_cannot_create_trip_when_driver_has_approved_leave_on_trip_date(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $vehicle = Vehicle::factory()->create([
            'company_id' => $company->id,
            'office_id' => $office->id,
            'status' => 'active',
        ]);
        $driver = Driver::factory()->create(['company_id' => $company->id, 'office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);
        $leaveType = LeaveType::query()->create([
            'code' => 'AL',
            'name' => 'Annual Leave',
            'is_paid' => true,
            'annual_quota_days' => 12,
            'allow_carry_forward' => false,
            'requires_attachment' => false,
            'status' => 'active',
        ]);

        DB::table('leave_requests')->insert([
            'driver_id' => $driver->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-01',
            'total_days' => 1,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/trips', [
            'code' => 'TRIP-DISPATCH-DRV-01',
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'start_point' => 'HCM',
            'end_point' => 'BD',
            'status' => 'pending',
            'start_time' => '2026-07-01 09:00:00',
            'price' => 500000,
        ], $this->tenant_headers($company));

        $response->assertStatus(422);
    }
}
