<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeAllowance;
use App\Models\EmployeeDeduction;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Trip;
use App\Models\TripBonusRule;
use App\Models\VehicleExpense;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    protected const STANDARD_WORKING_DAYS = 22;

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

            $employeesQuery = Employee::query()
                ->where('status', 'active')
                ->whereHas('office', function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })
                ->orderBy('id');

            if (! $employeesQuery->exists()) {
                throw new Exception('No active employees found for this company.');
            }

            $bonusRules = TripBonusRule::orderBy('min_km')->get();

            foreach ($employeesQuery->lazy() as $employee) {
                $this->calculateEmployeePayroll($payroll, $employee, $month, $year, $bonusRules);
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
            throw new Exception('Failed to generate payroll: '.$e->getMessage());
        }
    }

    /**
     * Calculate payroll for a single employee
     */
    protected function calculateEmployeePayroll(Payroll $payroll, Employee $employee, int $month, int $year, Collection $bonusRules): PayrollDetail
    {
        // Base salary calculation
        $baseSalary = $this->calculateBaseSalary($employee, $month, $year);

        // Working days
        $workingDays = $this->getWorkingDays($employee, $month, $year);

        // Overtime
        $overtime = $this->calculateOvertime($employee, $month, $year);

        // Bonus (for drivers based on trips)
        $bonus = $this->calculateBonus($employee, $month, $year, $bonusRules);

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
        if (! $position) {
            return 0.0;
        }

        $baseSalary = (float) $position->base_salary;

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
    protected function calculateBonus(Employee $employee, int $month, int $year, Collection $bonusRules): float
    {
        if ($employee->type !== 'driver') {
            return 0;
        }

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $totalKm = Trip::where('driver_id', $employee->id)
            ->where('status', 'completed')
            ->whereBetween('start_time', [$startDate, $endDate])
            ->sum('distance_km');

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
     * Calculate tax (Progressive Tax Bracket - VN Simplified)
     */
    protected function calculateTax(float $taxableIncome): float
    {
        // Thuế suất 누진 (Luỹ tiến từng phần) - Mô phỏng
        // Bậc 1: Dưới 5 triệu VND (5%)
        // Bậc 2: Từ 5 - 10 triệu VND (10%)
        // Bậc 3: Từ 10 triệu trở lên (15%)
        $tax = 0;

        if ($taxableIncome > 10000000) {
            $tax += ($taxableIncome - 10000000) * 0.15;
            $taxableIncome = 10000000;
        }
        
        if ($taxableIncome > 5000000) {
            $tax += ($taxableIncome - 5000000) * 0.10;
            $taxableIncome = 5000000;
        }

        if ($taxableIncome > 0) {
            $tax += $taxableIncome * 0.05;
        }

        return $tax;
    }

    /**
     * Approve payroll
     */
    public function approvePayroll(int $payrollId): Payroll
    {
        $payroll = Payroll::findOrFail($payrollId);

        if ($payroll->status === 'locked') {
            throw new Exception('Locked payroll cannot be approved.');
        }

        if ($payroll->status !== 'draft') {
            throw new Exception('Only draft payroll can be approved.');
        }

        $payroll->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return $payroll;
    }

    /**
     * Lock payroll (prevent further changes)
     */
    public function lockPayroll(int $payrollId): Payroll
    {
        $payroll = Payroll::findOrFail($payrollId);

        if ($payroll->status === 'locked') {
            throw new Exception('Payroll is already locked.');
        }

        if ($payroll->status !== 'approved') {
            throw new Exception('Only approved payroll can be locked.');
        }

        $payroll->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        return $payroll;
    }
}
