<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deduction>
 */
class DeductionFactory extends Factory
{
    public function definition(): array
    {
        $names = ['Social Insurance', 'Health Insurance', 'Unemployment Insurance', 'Income Tax', 'Advance Payment'];
        
        return [
            'code' => strtoupper(fake()->unique()->bothify('DED###')),
            'name' => fake()->randomElement($names),
        ];
    }
}
