<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vehicle>
 */
class VehicleFactory extends Factory
{
    public function definition(): array
    {
        $brands = ['Toyota', 'Ford', 'Isuzu', 'Hyundai', 'Mitsubishi', 'Hino', 'Mercedes'];
        $models = ['Hiace', 'Transit', 'D-Max', 'County', 'Fuso', '300 Series', 'Sprinter'];
        
        return [
            'office_id' => Office::factory(),
            'plate_number' => fake()->unique()->regexify('[0-9]{2}[A-Z]-[0-9]{5}'),
            'type' => fake()->randomElement(['truck', 'van', 'car', 'motorcycle']),
            'brand' => fake()->randomElement($brands),
            'model' => fake()->randomElement($models),
            'year' => fake()->numberBetween(2015, 2024),
            'capacity' => fake()->numberBetween(4, 30),
            'status' => fake()->randomElement(['active', 'maintenance', 'inactive']),
        ];
    }
}
