<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payroll>
 */
class PayrollFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'month' => fake()->numberBetween(1, 12),
            'year' => fake()->numberBetween(2020, 2024),
            'status' => fake()->randomElement(['draft', 'approved', 'paid', 'locked']),
            'locked_at' => fake()->optional(0.3)->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
