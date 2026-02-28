<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Allowance>
 */
class AllowanceFactory extends Factory
{
    public function definition(): array
    {
        $names = ['Transport Allowance', 'Meal Allowance', 'Phone Allowance', 'Housing Allowance', 'Travel Allowance'];
        
        return [
            'code' => strtoupper(fake()->unique()->bothify('ALL###')),
            'name' => fake()->randomElement($names),
            'default_amount' => fake()->randomFloat(2, 100000, 2000000),
            'taxable' => fake()->boolean(30),
        ];
    }
}
