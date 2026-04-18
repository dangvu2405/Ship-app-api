<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TripBonusRule>
 */
class TripBonusRuleFactory extends Factory
{
    public function definition(): array
    {
        $minKm = fake()->numberBetween(0, 5000);
        $maxKm = fake()->optional(0.8)->numberBetween($minKm + 1, 10000);
        $effectiveFrom = fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d');
        $effectiveTo = fake()->optional()->boolean(70) ? fake()->dateTimeBetween($effectiveFrom, '+2 years')->format('Y-m-d') : null;

        return [
            'company_id' => \App\Models\Company::factory(),
            'effective_from' => $effectiveFrom,
            'effective_to' => $effectiveTo,
            'min_km' => $minKm,
            'max_km' => $maxKm,
            'bonus_per_km' => fake()->randomFloat(2, 500, 5000),
        ];
    }
}
