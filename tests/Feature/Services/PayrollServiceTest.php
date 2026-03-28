<?php

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Office;
use App\Models\Position;
use App\Models\Trip;
use App\Models\TripBonusRule;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Attendance;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PayrollService $payrollService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->payrollService = new PayrollService();
    }

    public function test_calculate_base_salary_for_office_staff(): void
    {
        // Setup data
        $company = Company::create(['code' => 'COM01', 'name' => 'Company A']);
        $office = Office::create(['company_id' => $company->id, 'code' => 'OFF01', 'name' => 'Main Office']);
        $position = Position::create(['code' => 'POS01', 'name' => 'Accountant', 'base_salary' => 11000000]); // 11M expected

        $employee = Employee::create([
            'code' => 'EMP01',
            'name' => 'John Doe',
            'office_id' => $office->id,
            'position_id' => $position->id,
            'type' => 'office',
            'status' => 'active',
            'join_date' => Carbon::now()->subMonths(1)->format('Y-m-d')
        ]);

        $month = 3;
        $year = 2026;

        // Attendances: 20 days present
        for ($i = 1; $i <= 20; $i++) {
            Attendance::create([
                'employee_id' => $employee->id,
                'date' => Carbon::create($year, $month, $i)->format('Y-m-d'),
                'status' => 'present',
                'overtime_hours' => 0
            ]);
        }

        // Expected: 11,000,000 * (20 / 22) = 10,000,000
        $payroll = $this->payrollService->generatePayroll($company->id, $month, $year);

        $details = $payroll->details()->where('employee_id', $employee->id)->first();

        $this->assertNotNull($details);
        $this->assertEquals(10000000, $details->base_salary);
        $this->assertEquals(20, $details->working_days);
    }

    public function test_calculate_bonus_for_driver(): void
    {
        // Setup data
        $company = Company::create(['code' => 'COM02', 'name' => 'Company B']);
        $office = Office::create(['company_id' => $company->id, 'code' => 'OFF02', 'name' => 'Branch']);
        $position = Position::create(['code' => 'DRV01', 'name' => 'Driver', 'base_salary' => 5000000]);

        $driver = Employee::create([
            'code' => 'DVR01',
            'name' => 'Jane Driver',
            'office_id' => $office->id,
            'position_id' => $position->id,
            'type' => 'driver',
            'status' => 'active',
            'join_date' => Carbon::now()->subMonths(1)->format('Y-m-d')
        ]);

        $month = 4;
        $year = 2026;

        // Create Bonus Rules
        TripBonusRule::create(['min_km' => 0, 'max_km' => 500, 'bonus_per_km' => 1000]);
        TripBonusRule::create(['min_km' => 501, 'max_km' => 1000, 'bonus_per_km' => 1500]);
        TripBonusRule::create(['min_km' => 1001, 'max_km' => null, 'bonus_per_km' => 2000]);

        $tripDate = Carbon::create($year, $month, 10);

        // Trips: 1200 km total in the month
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->create(['office_id' => $office->id]);

        Trip::create([
            'code' => 'TRP01',
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_point' => 'A',
            'end_point' => 'B',
            'distance_km' => 600,
            'start_time' => $tripDate,
            'end_time' => $tripDate->copy()->addHours(5),
            'status' => 'completed'
        ]);

        Trip::create([
            'code' => 'TRP02',
            'driver_id' => $driver->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_point' => 'B',
            'end_point' => 'C',
            'distance_km' => 600,
            'start_time' => $tripDate,
            'end_time' => $tripDate->copy()->addHours(5),
            'status' => 'completed'
        ]);

        $payroll = $this->payrollService->generatePayroll($company->id, $month, $year);
        $details = $payroll->details()->where('employee_id', $driver->id)->first();

        $this->assertNotNull($details);
        $this->assertEquals(5000000, $details->base_salary); // Driver gets full base salary
        
        // Total km = 1200, rule applied should be > 1000km rule (2000 per km)
        // Bonus = 1200 * 2000 = 2,400,000
        $this->assertEquals(2400000, $details->bonus); 
    }
}
