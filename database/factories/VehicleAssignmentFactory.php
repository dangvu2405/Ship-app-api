<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Company;
use App\Models\Driver;
use App\Models\Vehicle;
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
        $company = Company::factory();

        return [
            'company_id' => $company,
            'vehicle_id' => Vehicle::factory(['company_id' => $company]),
            'driver_id' => Driver::factory(['company_id' => $company]),
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];
    }

    public function configure(): static
    {
        return $this;
    }
}
