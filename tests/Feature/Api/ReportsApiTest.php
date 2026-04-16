<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\Office;
use App\Models\Payroll;
use App\Models\PayrollLine;
use App\Models\Position;
use App\Models\Role;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getAdminUser()
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['status' => 'active']);
        $user->roles()->attach($adminRole->id);
        return $user;
    }

    public function test_dashboard_report_returns_data(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        Company::factory()->count(3)->create();

        $response = $this->getJson('/api/reports/dashboard?month=3&year=2026');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'companies_count',
                    'payrolls_count',
                    'companies' => ['total', 'active'],
                    'employees' => ['total', 'active'],
                    'vehicles' => ['total', 'active'],
                    'trips' => ['total', 'pending', 'completed'],
                    'payrolls' => ['total', 'pending', 'completed'],
                ],
            ]);
    }

    public function test_payroll_summary_report_returns_data(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();

        $response = $this->getJson('/api/reports/payroll-summary?company_id=' . $company->id . '&month=3&year=2026');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_dashboard_requires_admin(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reports/dashboard');

        $response->assertStatus(403);
    }

    public function test_dashboard_validates_month_year_range(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/reports/dashboard?month=13&year=1800');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['month', 'year']);
    }

    public function test_payroll_summary_requires_company_id(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/reports/payroll-summary?month=3&year=2026');

        $response->assertStatus(422)
            ->assertJsonValidationErrors('company_id');
    }

    public function test_revenue_summary_returns_formula_based_metrics(): void
    {
        $admin = $this->getAdminUser();
        Sanctum::actingAs($admin);

        $company = Company::factory()->create();
        $office = Office::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['office_id' => $office->id]);
        $position = Position::factory()->create();
        $driver = Driver::factory()->create([
            'office_id' => $office->id,
            'department_id' => $department->id,
            'position_id' => $position->id,
        ]);
        $vehicle = Vehicle::factory()->create([
            'office_id' => $office->id,
            'company_id' => $company->id,
        ]);
        $customer = Customer::factory()->create();
        $trip = Trip::factory()->create([
            'company_id' => $company->id,
            'customer_id' => $customer->id,
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
        ]);

        Invoice::factory()->create([
            'trip_id' => $trip->id,
            'customer_id' => $customer->id,
            'status' => 'paid',
            'total_amount' => 1000000,
            'issued_at' => '2026-04-14 09:00:00',
            'paid_at' => '2026-04-14 10:00:00',
        ]);

        $payroll = Payroll::query()->create([
            'company_id' => $company->id,
            'month' => 4,
            'year' => 2026,
            'status' => 'approved',
        ]);
        PayrollLine::query()->create([
            'payroll_id' => $payroll->id,
            'company_id' => $company->id,
            'driver_id' => $driver->id,
            'base_salary' => 0,
            'trip_bonus' => 0,
            'allowance' => 0,
            'deduction' => 0,
            'fuel_cost' => 0,
            'tax' => 0,
            'net_salary' => 300000,
            'working_days' => 0,
            'trips_completed_count' => 0,
            'total_distance_km' => 0,
        ]);

        VehicleExpense::factory()->create([
            'company_id' => $company->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'amount' => 100000,
            'type' => 'fuel',
            'expense_date' => '2026-04-14',
        ]);

        $response = $this->getJson('/api/v1/reports/revenue-summary?company_id='.$company->id.'&from=2026-04-01&to=2026-04-30');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.revenue.total_invoiced', 1000000)
            ->assertJsonPath('data.revenue.total_collected', 1000000)
            ->assertJsonPath('data.revenue.cross_month_allocated', 1000000)
            ->assertJsonPath('data.costs.total_net_salary', 300000)
            ->assertJsonPath('data.costs.total_vehicle_fuel', 100000)
            ->assertJsonPath('data.costs.total_vehicle_maintenance', 0)
            ->assertJsonPath('data.costs.total_overhead', 0)
            ->assertJsonPath('data.company_take_home_revenue', 600000)
            ->assertJsonPath('data.operating_margin', 600000);

        $response->assertJsonPath('data._deprecation_notice', 'operating_margin will be removed in v2. Use company_take_home_revenue')
            ->assertJsonPath('data.company_take_home_analysis.actual', 600000)
            ->assertJsonPath('data.company_take_home_analysis.budget', 0)
            ->assertJsonPath('data.company_take_home_analysis.variance', 600000)
            ->assertJsonPath('data.company_take_home_analysis.variance_pct', null)
            ->assertJsonPath('data._meta.formula', 'cross_month_allocated - total_net_salary - total_vehicle_fuel - total_vehicle_maintenance - total_overhead');
    }
}
