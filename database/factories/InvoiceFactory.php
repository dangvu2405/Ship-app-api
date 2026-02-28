<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 1000000, 50000000);
        $vatRate = fake()->randomFloat(2, 0, 10);
        $vatAmount = $subtotal * ($vatRate / 100);
        $totalAmount = $subtotal + $vatAmount;
        
        return [
            'code' => strtoupper(fake()->unique()->bothify('INV#######')),
            'trip_id' => null,
            'customer_id' => Customer::factory(),
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total_amount' => $totalAmount,
            'status' => fake()->randomElement(['draft', 'issued', 'paid', 'cancelled']),
            'issued_at' => fake()->optional(0.8)->dateTimeBetween('-1 year', 'now'),
            'paid_at' => fake()->optional(0.5)->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
