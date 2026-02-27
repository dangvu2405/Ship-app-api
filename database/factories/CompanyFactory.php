<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('C??###')),
            'name' => fake()->company(),
            'tax_code' => fake()->optional()->numerify('##########'),
            'address' => fake()->address(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'status' => 'active',
        ];
    }
}
