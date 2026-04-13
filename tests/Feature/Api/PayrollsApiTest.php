<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Company;
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
use App\Models\Customer;
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
        Sanctum::actingAs($this->adminUser());

        TripBonusRule::query()->delete();
        TripBonusRule::factory()->create([
            'min_km' => 0,
            'max_km' => null,
            'bonus_per_km' => 1000,
        ]);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['office_id' => $office->id]);
        $position = Position::factory()->create(['base_salary' => 10_000_000.00]);
        $driver = Driver::factory()->create([
            'office_id' => $office->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
            'status' => 'active',
        ]);

        $vehicle = Vehicle::factory()->create();
        $customer = Customer::factory()->create();

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
        ]);

        $create->assertStatus(201);
        $payrollId = (int) $create->json('data.id');
        $this->assertNotSame(0, $payrollId);

        $line = $create->json('data.lines.0');
        $this->assertSame($driver->id, $line['driver_id']);
        $this->assertEquals(30_000.0, (float) $line['trip_bonus']);
        $this->assertEquals(1_050_000.0, (float) $line['deduction']);
        $this->assertEquals(8_980_000.0, (float) $line['net_salary']);

        $lock = $this->postJson("/api/v1/payrolls/{$payrollId}/lock");
        $lock->assertStatus(200);
        $this->assertDatabaseHas('payrolls', ['id' => $payrollId, 'status' => 'locked']);

        $blocked = $this->postJson('/api/v1/payrolls', [
            'company_id' => $company->id,
            'month' => 6,
            'year' => 2026,
        ]);
        $blocked->assertStatus(403);

        $show = $this->getJson("/api/v1/payrolls/{$payrollId}");
        $show->assertStatus(200);
        $show->assertJsonPath('data.lines.0.driver.id', $driver->id);
    }

    public function test_payrolls_index_filters_by_company_month_year(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        Payroll::query()->create([
            'company_id' => $company->id,
            'month' => 3,
            'year' => 2025,
            'status' => 'draft',
        ]);

        $response = $this->getJson('/api/v1/payrolls?company_id='.$company->id.'&month=3&year=2025');
        $response->assertStatus(200);
        $response->assertJsonPath('data.meta.total', 1);
    }

    public function test_payrolls_index_supports_sort_by_year_with_pagination(): void
    {
        Sanctum::actingAs($this->adminUser());

        $company = Company::factory()->create();
        Payroll::query()->create([
            'company_id' => $company->id,
            'month' => 1,
            'year' => 2024,
            'status' => 'draft',
        ]);

        $response = $this->getJson(
            '/api/v1/payrolls?page=1&per_page=15&sort_by=year&sort_order=desc&company_id='.$company->id
        );

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['data', 'meta']]);
    }
}
