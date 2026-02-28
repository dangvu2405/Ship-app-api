<?php

namespace Database\Factories;

use App\Models\User;
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
            'old_data' => fake()->optional(0.5)->json(),
            'new_data' => fake()->optional(0.7)->json(),
            'ip_address' => fake()->ipv4(),
        ];
    }
}
