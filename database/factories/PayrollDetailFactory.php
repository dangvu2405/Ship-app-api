<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollDetail>
 */
class PayrollDetailFactory extends Factory
{
    public function definition(): array
    {
        $baseSalary = fake()->randomFloat(2, 5000000, 20000000);
        $workingDays = fake()->numberBetween(20, 26);
        $overtime = fake()->randomFloat(2, 0, 5000000);
        $bonus = fake()->randomFloat(2, 0, 3000000);
        $allowance = fake()->randomFloat(2, 0, 2000000);
        $deduction = fake()->randomFloat(2, 0, 3000000);
        $fuelCost = fake()->randomFloat(2, 0, 5000000);
        $tax = fake()->randomFloat(2, 0, 2000000);
        $netSalary = $baseSalary + $overtime + $bonus + $allowance - $deduction - $fuelCost - $tax;
        
        return [
            'payroll_id' => Payroll::factory(),
            'employee_id' => Employee::factory(),
            'base_salary' => $baseSalary,
            'working_days' => $workingDays,
            'overtime' => $overtime,
            'bonus' => $bonus,
            'allowance' => $allowance,
            'deduction' => $deduction,
            'fuel_cost' => $fuelCost,
            'tax' => $tax,
            'net_salary' => max(0, $netSalary),
            'meta_json' => null,
        ];
    }
}
