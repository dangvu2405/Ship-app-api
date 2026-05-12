<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleType>
 */
class VehicleTypeFactory extends Factory
{
    protected $model = VehicleType::class;

    public function definition(): array
    {
        $classes = ['B1', 'B2', 'C', 'D', 'E'];

        return [
            'company_id'             => Company::factory(),
            'name'                   => fake()->unique()->randomElement(['Xe tải nhỏ', 'Xe tải trung', 'Xe tải lớn', 'Container 20ft', 'Container 40ft', 'Xe đông lạnh', 'Xe đầu kéo']).' '.fake()->numerify('##'),
            'max_load_ton'           => fake()->randomFloat(1, 0.5, 30.0),
            'volume_m3'              => fake()->optional(0.7)->randomFloat(1, 2.0, 60.0),
            'required_license_class' => fake()->randomElement($classes),
            'description'            => fake()->optional(0.5)->sentence(),
            'is_active'              => true,
            'sort_order'             => fake()->numberBetween(1, 100),
        ];
    }
}
