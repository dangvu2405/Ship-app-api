<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Attendance;
use App\Models\Trip;
use App\Models\TripBonusRule;
use App\Models\VehicleExpense;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    protected const STANDARD_WORKING_DAYS = 22;
    protected const TAX_RATE = 0.1; // 10%

    /**
     * Generate payroll for a company in a specific month/year
     */
    public function generatePayroll(int $companyId, int $month, int $year): Payroll
    {
        DB::beginTransaction();
        try {
            // Validate inputs
            if ($month < 1 || $month > 12) {
                throw new Exception('Invalid month. Month must be between 1 and 12.');
            }
            
            if ($year < 2000 || $year > 2100) {
                throw new Exception('Invalid year.');
            }
            
            // Create or get payroll
            $payroll = Payroll::firstOrCreate(
                [
                    'company_id' => $companyId,
                    'month' => $month,
                    'year' => $year,
                ],
                [
                    'status' => 'draft',
                ]
            );

            // Get all active employees
            $employees = Employee::where('status', 'active')
                ->whereHas('office', function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })
                ->get();

            if ($employees->isEmpty()) {
                throw new Exception('No active employees found for this company.');
            }

            foreach ($employees as $employee) {
                $this->calculateEmployeePayroll($payroll, $employee, $month, $year);
            }

            DB::commit();
            return $payroll->fresh();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Payroll generation failed', [
                'company_id' => $companyId,
                'month' => $month,
                'year' => $year,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to generate payroll: ' . $e->getMessage());
        }
    }

    /**
     * Calculate payroll for a single employee
     */
    protected function calculateEmployeePayroll(Payroll $payroll, Employee $employee, int $month, int $year): PayrollDetail
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Base salary calculation
        $baseSalary = $this->calculateBaseSalary($employee, $month, $year);

        // Working days
        $workingDays = $this->getWorkingDays($employee, $month, $year);

        // Overtime
        $overtime = $this->calculateOvertime($employee, $month, $year);

        // Bonus (for drivers based on trips)
        $bonus = $this->calculateBonus($employee, $month, $year);

        // Allowances
        $allowance = $this->calculateAllowances($employee);

        // Deductions
        $deduction = $this->calculateDeductions($employee);

        // Fuel cost (for drivers)
        $fuelCost = $this->calculateFuelCost($employee, $month, $year);

        // Tax calculation
        $taxableIncome = $baseSalary + $bonus + $allowance - $deduction;
        $tax = $this->calculateTax($taxableIncome);

        // Net salary
        $netSalary = $baseSalary + $overtime + $bonus + $allowance - $deduction - $fuelCost - $tax;

        // Save payroll detail
        $payrollDetail = PayrollDetail::updateOrCreate(
            [
                'payroll_id' => $payroll->id,
                'employee_id' => $employee->id,
            ],
            [
                'base_salary' => $baseSalary,
                'working_days' => $workingDays,
                'overtime' => $overtime,
                'bonus' => $bonus,
                'allowance' => $allowance,
                'deduction' => $deduction,
                'fuel_cost' => $fuelCost,
                'tax' => $tax,
                'net_salary' => $netSalary,
                'meta_json' => [
                    'calculated_at' => now()->toDateTimeString(),
                    'employee_type' => $employee->type,
                ],
            ]
        );

        return $payrollDetail;
    }

    /**
     * Calculate base salary
     */
    protected function calculateBaseSalary(Employee $employee, int $month, int $year): float
    {
        $position = $employee->position;
        if (!$position) {
            return 0;
        }

        $baseSalary = $position->base_salary;

        if ($employee->type === 'office') {
            // Office staff: base_salary * working_days / standard_days
            $workingDays = $this->getWorkingDays($employee, $month, $year);
            return ($baseSalary * $workingDays) / self::STANDARD_WORKING_DAYS;
        }

        // Drivers: full base salary
        return $baseSalary;
    }

    /**
     * Get working days for an employee in a month
     */
    protected function getWorkingDays(Employee $employee, int $month, int $year): int
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        return Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->where('status', 'present')
            ->count();
    }

    /**
     * Calculate overtime hours
     */
    protected function calculateOvertime(Employee $employee, int $month, int $year): float
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $totalOvertime = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->sum('overtime_hours');

        // Overtime rate: 1.5x hourly rate
        $hourlyRate = $employee->position->base_salary / (self::STANDARD_WORKING_DAYS * 8);
        return $totalOvertime * $hourlyRate * 1.5;
    }

    /**
     * Calculate bonus for drivers based on trips
     */
    protected function calculateBonus(Employee $employee, int $month, int $year): float
    {
        if ($employee->type !== 'driver') {
            return 0;
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Get total distance for completed trips
        $totalKm = Trip::where('driver_id', $employee->id)
            ->where('status', 'completed')
            ->whereBetween('start_time', [$startDate, $endDate])
            ->sum('distance_km');

        // Get bonus rules
        $bonusRules = TripBonusRule::orderBy('min_km')->get();
        $bonus = 0;

        foreach ($bonusRules as $rule) {
            if ($totalKm >= $rule->min_km) {
                if ($rule->max_km === null || $totalKm <= $rule->max_km) {
                    $bonus = $totalKm * $rule->bonus_per_km;
                    break;
                }
            }
        }

        return $bonus;
    }

    /**
     * Calculate total allowances
     */
    protected function calculateAllowances(Employee $employee): float
    {
        return EmployeeAllowance::where('employee_id', $employee->id)
            ->sum('amount');
    }

    /**
     * Calculate total deductions
     */
    protected function calculateDeductions(Employee $employee): float
    {
        return EmployeeDeduction::where('employee_id', $employee->id)
            ->sum('amount');
    }

    /**
     * Calculate fuel cost for drivers
     */
    protected function calculateFuelCost(Employee $employee, int $month, int $year): float
    {
        if ($employee->type !== 'driver') {
            return 0;
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        return VehicleExpense::where('driver_id', $employee->id)
            ->where('type', 'fuel')
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * Calculate tax
     */
    protected function calculateTax(float $taxableIncome): float
    {
        // Simple tax calculation: 10% of taxable income
        // You can implement more complex tax brackets here
        return $taxableIncome * self::TAX_RATE;
    }

    /**
     * Approve payroll
     */
    public function approvePayroll(int $payrollId): Payroll
    {
        $payroll = Payroll::findOrFail($payrollId);
        $payroll->update(['status' => 'approved']);
        return $payroll;
    }

    /**
     * Lock payroll (prevent further changes)
     */
    public function lockPayroll(int $payrollId): Payroll
    {
        $payroll = Payroll::findOrFail($payrollId);
        $payroll->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);
        return $payroll;
    }
}
