<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Deduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeDeduction>
 */
class EmployeeDeductionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'deduction_id' => Deduction::factory(),
            'amount' => fake()->randomFloat(2, 100000, 3000000),
        ];
    }
}
