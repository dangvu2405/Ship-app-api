<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory()->state(['type' => 'driver']),
            'license_no' => strtoupper(fake()->unique()->bothify('DL#######')),
            'license_class' => fake()->randomElement(['B1', 'B2', 'C', 'D', 'E']),
            'expired_date' => fake()->date('Y-m-d', '+1 year', '+5 years'),
            'available_status' => fake()->randomElement(['available', 'busy', 'offline']),
        ];
    }
}
