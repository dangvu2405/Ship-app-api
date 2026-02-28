<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = fake()->time('H:i', '08:00');
        $checkOut = fake()->time('H:i', '17:00');
        $workHours = fake()->randomFloat(2, 4, 10);
        $overtimeHours = fake()->randomFloat(2, 0, 4);
        
        return [
            'employee_id' => Employee::factory(),
            'date' => fake()->date('Y-m-d', '-1 year', 'now'),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'work_hours' => $workHours,
            'overtime_hours' => $overtimeHours,
            'status' => fake()->randomElement(['present', 'absent', 'late', 'half_day', 'leave']),
        ];
    }
}
