<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Allowance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeAllowance>
 */
class EmployeeAllowanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'allowance_id' => Allowance::factory(),
            'amount' => fake()->randomFloat(2, 100000, 2000000),
        ];
    }
}
