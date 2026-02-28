<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExportLog>
 */
class ExportLogFactory extends Factory
{
    public function definition(): array
    {
        $types = ['employees', 'trips', 'vehicles', 'customers', 'invoices', 'payrolls', 'attendance'];
        
        return [
            'user_id' => null,
            'type' => fake()->randomElement($types),
            'file_name' => fake()->word() . '.xlsx',
            'file_path' => 'exports/' . fake()->uuid() . '.xlsx',
            'record_count' => fake()->numberBetween(10, 10000),
        ];
    }
}
