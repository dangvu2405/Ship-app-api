<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Position>
 */
class PositionFactory extends Factory
{
    public function definition(): array
    {
        $positions = ['Manager', 'Supervisor', 'Staff', 'Driver', 'Assistant', 'Coordinator', 'Analyst', 'Specialist'];

        return [
            'company_id' => \App\Models\Company::factory(),
            'code' => strtoupper(fake()->unique()->bothify('POS###')),
            'name' => fake()->randomElement($positions),
            'base_salary' => fake()->numberBetween(5000000, 20000000),
            'level' => fake()->numberBetween(1, 10),
        ];
    }
}
