<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceLogFactory extends Factory
{
    protected $model = AttendanceLog::class;

    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('today 08:00', 'today 09:00');
        $checkOut = clone $checkIn;
        $checkOut->modify('+8 hours +'.rand(0, 60).' minutes');

        return [
            'date' => date('Y-m-d'),
            'session_number' => 1,
            'check_in' => $checkIn->format('Y-m-d H:i:s'),
            'check_out' => $checkOut->format('Y-m-d H:i:s'),
            'method' => $this->faker->randomElement(['mobile', 'qr', 'biometric']),
            'status' => 'ontime',
            'hours_worked' => 8.0,
            'overtime_hours' => 0,
            'late_minutes' => 0,
        ];
    }

    public function late(): static
    {
        return $this->state(function () {
            $lateMinutes = rand(16, 60);
            $checkIn = now()->setTime(8, $lateMinutes, 0);

            return [
                'check_in' => $checkIn,
                'status' => 'late',
                'late_minutes' => $lateMinutes,
            ];
        });
    }

    public function withOvertime(float $hours = 2.0): static
    {
        return $this->state(fn () => [
            'overtime_hours' => $hours,
            'hours_worked' => 8 + $hours,
            'status' => 'ontime',
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'method' => 'manual',
            'correction_note' => 'Correction manuelle par le responsable',
        ]);
    }
}
