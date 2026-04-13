<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Office;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('DRV#####')),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '0' . fake()->numerify('#########'),
            'dob' => fake()->date('Y-m-d', '-25 years'),
            'gender' => fake()->randomElement(['male', 'female']),
            'address' => fake()->address(),
            'avatar_url' => null,
            'national_id_no' => fake()->unique()->numerify('############'),
            'national_id_issue_date' => fake()->date('Y-m-d', '-5 years'),
            'national_id_issue_place' => fake()->city(),
            'social_insurance_no' => fake()->optional(0.8)->numerify('##########'),
            'health_insurance_no' => fake()->optional(0.8)->numerify('##########'),
            'insurance_registered_at' => fake()->optional(0.8)->date('Y-m-d', '-3 years'),
            'office_id' => Office::factory(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'status' => fake()->randomElement(['active', 'inactive', 'resigned']),
            'join_date' => fake()->date('Y-m-d', '-3 years'),
            'resign_date' => null,
            'bank_name' => fake()->randomElement(['Vietcombank', 'Techcombank', 'BIDV', 'Agribank', 'MB']),
            'bank_account_no' => fake()->numerify('##########'),
            'bank_account_name' => fake()->name(),
            'license_no' => strtoupper(fake()->unique()->bothify('DL#######')),
            'license_class' => fake()->randomElement(['B1', 'B2', 'C', 'D', 'E']),
            'expired_date' => fake()->date('Y-m-d', '+1 year'),
            'available_status' => fake()->randomElement(['available', 'busy', 'offline']),
        ];
    }
}
