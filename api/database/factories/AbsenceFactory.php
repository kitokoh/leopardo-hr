<?php

namespace Database\Factories;

use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Database\Eloquent\Factories\Factory;

use App\Modules\Attendance\Domain\Models\Absence;

class AbsenceFactory extends Factory
{
    protected $model = Absence::class;
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $days = rand(1, 10);
        $end = (clone $start)->modify("+{$days} days");

        return [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'days_count' => $days,
            'status' => 'pending',
            'reason' => $this->faker->sentence(5),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'rejected_reason' => 'Periode chargee - reporter la demande',
        ]);
    }

    public function past(): static
    {
        return $this->state(function () {
            $start = $this->faker->dateTimeBetween('-30 days', '-5 days');
            $days = rand(1, 5);
            $end = (clone $start)->modify("+{$days} days");

            return [
                'start_date' => $start->format('Y-m-d'),
                'end_date' => $end->format('Y-m-d'),
                'days_count' => $days,
                'status' => 'approved',
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => 'cancelled']);
    }

    public function withType(AbsenceType $type): static
    {
        return $this->state(fn () => ['absence_type_id' => $type->id]);
    }
}

