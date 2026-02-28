<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoginLog>
 */
class LoginLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'ip' => fake()->ipv4(),
            'device' => fake()->optional(0.7)->userAgent(),
            'login_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
