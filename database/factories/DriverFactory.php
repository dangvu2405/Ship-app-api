<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Department;
use App\Models\Office;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Driver>
 */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        $attributes = [
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
            'company_id' => Company::factory(),
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

        if (Schema::hasColumn('drivers', 'office_id')) {
            $attributes['office_id'] = Office::factory();
        }

        if (Schema::hasColumn('drivers', 'department_id')) {
            $attributes['department_id'] = Department::factory();
        }

        if (Schema::hasColumn('drivers', 'position_id')) {
            $attributes['position_id'] = Position::factory();
        }

        return $attributes;
    }
}
