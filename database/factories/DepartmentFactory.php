<?php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    public function definition(): array
    {
        $departments = ['HR', 'IT', 'Finance', 'Operations', 'Sales', 'Marketing', 'Fleet', 'Logistics'];
        
        return [
            'office_id' => Office::factory(),
            'parent_id' => null,
            'code' => strtoupper(fake()->unique()->bothify('DEPT###')),
            'name' => fake()->randomElement($departments) . ' Department',
        ];
    }
}
