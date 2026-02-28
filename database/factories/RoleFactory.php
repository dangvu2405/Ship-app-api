<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        $roles = ['Admin', 'Manager', 'Supervisor', 'Staff', 'Driver', 'Accountant', 'HR', 'Operator'];
        
        return [
            'name' => strtolower(fake()->unique()->randomElement($roles)),
            'description' => fake()->optional(0.7)->sentence(),
        ];
    }
}
