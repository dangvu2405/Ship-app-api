<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Permission>
 */
class PermissionFactory extends Factory
{
    public function definition(): array
    {
        $permissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
            'trips.view', 'trips.create', 'trips.edit', 'trips.delete',
            'vehicles.view', 'vehicles.create', 'vehicles.edit', 'vehicles.delete',
            'payrolls.view', 'payrolls.create', 'payrolls.edit', 'payrolls.delete',
        ];
        
        return [
            'code' => fake()->unique()->randomElement($permissions),
            'name' => fake()->words(2, true),
            'description' => fake()->optional(0.6)->sentence(),
        ];
    }
}
