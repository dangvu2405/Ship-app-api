<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExportLog>
 */
class ExportLogFactory extends Factory
{
    public function definition(): array
    {
        $types = ['drivers', 'trips', 'vehicles', 'customers', 'invoices', 'reconciliations', 'payments'];

        return [
            'user_id' => null,
            'type' => fake()->randomElement($types),
            'file_name' => fake()->word().'.xlsx',
            'file_path' => 'exports/'.fake()->uuid().'.xlsx',
            'record_count' => fake()->numberBetween(10, 10000),
        ];
    }
}
