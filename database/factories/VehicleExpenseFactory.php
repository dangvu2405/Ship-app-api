<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleExpense>
 */
class VehicleExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => null,
            'type' => fake()->randomElement(['fuel', 'maintenance', 'repair', 'toll', 'parking', 'other']),
            'amount' => fake()->randomFloat(2, 50000, 10000000),
            'note' => fake()->optional(0.6)->sentence(),
            'expense_date' => fake()->date('Y-m-d', '-1 year', 'now'),
        ];
    }
}
