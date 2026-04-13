<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VehicleAssignment>
 */
class VehicleAssignmentFactory extends Factory
{
    public function definition(): array
    {
        $fromDate = fake()->date('Y-m-d', '-2 years');
        $toDate = fake()->optional(0.7)->date('Y-m-d', $fromDate);

        return [
            'vehicle_id' => Vehicle::factory(),
            'driver_id' => Driver::factory(),
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (VehicleAssignment $assignment): void {
            $driver = $assignment->driver()->first();
            $vehicle = $assignment->vehicle()->first();

            if ($driver === null || $vehicle === null) {
                return;
            }

            if ((int) $vehicle->office_id !== (int) $driver->office_id) {
                $vehicle->forceFill([
                    'office_id' => $driver->office_id,
                    'company_id' => $driver->company_id,
                ])->saveQuietly();
            }
        });
    }
}
