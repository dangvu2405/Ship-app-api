<?php

namespace Database\Factories;

use App\Models\Vehicle;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleAssignment>
 */
class VehicleAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $fromDate = fake()->date('Y-m-d', '-2 years', 'now');
        $toDate = fake()->optional(0.7)->date('Y-m-d', $fromDate, '+1 year');
        
        return [
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Employee::factory()->state(['type' => 'driver']),
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }
}
