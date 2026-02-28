<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Trip>
 */
class TripFactory extends Factory
{
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('-1 year', 'now');
        $endTime = fake()->dateTimeBetween($startTime, '+3 days');
        
        return [
            'code' => strtoupper(fake()->unique()->bothify('TRIP#######')),
            'customer_id' => Customer::factory(),
            'driver_id' => Employee::factory()->state(['type' => 'driver']),
            'vehicle_id' => Vehicle::factory(),
            'start_point' => fake()->city(),
            'end_point' => fake()->city(),
            'distance_km' => fake()->randomFloat(2, 50, 2000),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'price' => fake()->randomFloat(2, 1000000, 50000000),
            'status' => fake()->randomElement(['pending', 'in_progress', 'completed', 'cancelled']),
        ];
    }
}
