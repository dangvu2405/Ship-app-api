<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('EMP####')),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0' . fake()->numerify('#########'),
            'dob' => fake()->date('Y-m-d', '-20 years', '-18 years'),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'address' => fake()->address(),
            'office_id' => Office::factory(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'type' => fake()->randomElement(['office', 'driver']),
            'status' => fake()->randomElement(['active', 'inactive', 'resigned']),
            'join_date' => fake()->date('Y-m-d', '-5 years', 'now'),
            'resign_date' => fake()->optional(0.1)->date('Y-m-d', '-1 year', 'now'),
        ];
    }
}
