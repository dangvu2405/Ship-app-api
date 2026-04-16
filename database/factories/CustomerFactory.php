<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        $companyId = \App\Models\Company::query()->inRandomOrder()->value('id') ?? 1;

        return [
            'company_id' => $companyId,
            'type' => fake()->randomElement(['individual', 'company']),
            'name' => fake()->company(),
            'tax_code' => fake()->optional(0.7)->numerify('##########'),
            'phone' => '0' . fake()->numerify('#########'),
            'email' => fake()->optional(0.8)->safeEmail(),
            'address' => fake()->address(),
        ];
    }
}
