<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Journee',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'break_minutes' => 60,
            'work_days' => [1, 2, 3, 4, 5],
            'rest_days' => [6, 7],
            'late_tolerance_minutes' => 10,
            'overtime_threshold_daily' => '8.00',
            'overtime_threshold_weekly' => '40.00',
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
