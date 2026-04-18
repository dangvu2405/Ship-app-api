<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\Position;
use App\Models\Role;
use App\Models\Trip;
use App\Models\TripBonusRule;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollsApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);

        return $user;
    }

    public function test_post_payroll_calculates_trip_bonus_and_net_then_locked_blocks_recalculate(): void
    {
        $company = Company::factory()->create();
        $headers = $this->tenant_headers($company);

        Sanctum::actingAs($this->adminUser());

        TripBonusRule::query()->delete();
        TripBonusRule::factory()->create([
            'company_id' => $company->id,
            'effective_from' => '2026-01-01',
            'effective_to' => null,
            'min_km' => 0,
            'max_km' => null,
            'bonus_per_km' => 1000,
        ]);

        $office = Office::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['office_id' => $office->id]);
        $position = Position::factory()->create([
            'company_id' => $company->id,
            'base_salary' => 10_000_000.00,
        ]);
        $driver = Driver::factory()->create([
            'office_id' => $office->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);
        $customer = Customer::factory()->create(['company_id' => $company->id]);

        Trip::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'distance_km' => 10,
            'end_time' => '2026-06-10 12:00:00',
        ]);
        Trip::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'customer_id' => $customer->id,
            'status' => 'completed',
            'distance_km' => 20,
            'end_time' => '2026-06-20 15:00:00',
        ]);

        $create = $this->postJson('/api/v1/payrolls', [
            'company_id' => $company->id,
            'month' => 6,
            'year' => 2026,
        ], $headers);

        $create->assertStatus(201);
        $payrollId = (int) $create->json('data.id');
        $this->assertNotSame(0, $payrollId);

        $line = $create->json('data.lines.0');
        $this->assertSame($driver->id, $line['driver_id']);
        $this->assertEquals(30_000.0, (float) $line['trip_bonus']);
        // Employee insurance is computed on gross (base + trip bonus + other income components).
        $this->assertEquals(1_053_150.0, (float) $line['deduction']);
        $this->assertEquals(8_976_850.0, (float) $line['net_salary']);

        $lock = $this->postJson("/api/v1/payrolls/{$payrollId}/lock", [], $headers);
        $lock->assertStatus(200);
        $this->assertDatabaseHas('payrolls', ['id' => $payrollId, 'status' => 'locked']);

        $blocked = $this->postJson('/api/v1/payrolls', [
            'company_id' => $company->id,
            'month' => 6,
            'year' => 2026,
        ], $headers);
        $blocked->assertStatus(403);

        $show = $this->getJson("/api/v1/payrolls/{$payrollId}", $headers);
        $show->assertStatus(200);
        $show->assertJsonPath('data.lines.0.driver.id', $driver->id);
    }

    public function test_payrolls_index_filters_by_company_month_year(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        Payroll::query()->create([
            'company_id' => $company->id,
            'month' => 3,
            'year' => 2025,
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/payrolls?company_id='.$company->id.'&month=3&year=2025', $this->tenant_headers($company));
        $response->assertStatus(200);
        $response->assertJsonPath('data.meta.total', 1);
    }

    public function test_payrolls_index_supports_sort_by_year_with_pagination(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        Payroll::query()->create([
            'company_id' => $company->id,
            'month' => 1,
            'year' => 2024,
            'status' => 'draft',
        ]);

        $response = $this->getJson(
            '/api/v1/payrolls?page=1&per_page=15&sort_by=year&sort_order=desc&company_id='.$company->id,
            $this->tenant_headers($company),
        );

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }

    public function test_admin_can_get_driver_monthly_payroll(): void
    {
        $company = Company::factory()->create();
        Sanctum::actingAs($this->adminUser());

        $office = Office::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['office_id' => $office->id]);
        $position = Position::factory()->create([
            'company_id' => $company->id,
            'base_salary' => 10_000_000.00,
        ]);
        $driver = Driver::factory()->create([
            'office_id' => $office->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'status' => 'active',
        ]);

        $payroll = Payroll::query()->create([
            'company_id' => $company->id,
            'month' => 6,
            'year' => 2026,
            'status' => 'approved',
        ]);
        \App\Models\PayrollLine::query()->create([
            'payroll_id' => $payroll->id,
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'base_salary' => 9_000_000,
            'trip_bonus' => 200_000,
            'allowance' => 0,
            'deduction' => 0,
            'fuel_cost' => 0,
            'tax' => 0,
            'net_salary' => 9_200_000,
            'working_days' => 22,
            'trips_completed_count' => 10,
            'total_distance_km' => 500,
        ]);

        $response = $this->getJson('/api/v1/payrolls/driver/'.$driver->id.'?month=6&year=2026', $this->tenant_headers($company));
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.month', 6)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.line.net_salary', '9200000.00');
    }
}
