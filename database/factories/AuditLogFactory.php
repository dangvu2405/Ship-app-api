<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        $tables = ['users', 'employees', 'trips', 'vehicles', 'customers', 'invoices', 'payrolls'];

        return [
            'user_id' => null,
            'action' => fake()->randomElement(['create', 'update', 'delete']),
            'table_name' => fake()->randomElement($tables),
            'record_id' => fake()->numberBetween(1, 1000),
            'old_data' => fake()->optional(0.5)->passthrough(json_encode([
                'before' => fake()->word(),
                'at' => now()->toDateTimeString(),
            ])),
            'new_data' => fake()->optional(0.7)->passthrough(json_encode([
                'after' => fake()->word(),
                'at' => now()->toDateTimeString(),
            ])),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
