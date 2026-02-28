<?php

namespace Database\Factories;

use App\Models\PayrollDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payroll_detail_id' => PayrollDetail::factory(),
            'type' => fake()->randomElement(['addition', 'deduction']),
            'reason' => fake()->sentence(),
            'amount' => fake()->randomFloat(2, 100000, 5000000),
        ];
    }
}
