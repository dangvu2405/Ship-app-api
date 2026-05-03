<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportCache>
 */
class ReportCacheFactory extends Factory
{
    public function definition(): array
    {
        $types = ['revenue', 'expense', 'trip', 'vehicle', 'driver', 'debt', 'maintenance'];
        
        return [
            'type' => fake()->randomElement($types),
            'month' => fake()->optional(0.8)->numberBetween(1, 12),
            'year' => fake()->optional(0.8)->numberBetween(2020, 2024),
            'data_json' => json_encode(['total' => fake()->numberBetween(1000000, 100000000)]),
            'expires_at' => fake()->optional(0.7)->dateTimeBetween('now', '+30 days'),
        ];
    }
}
