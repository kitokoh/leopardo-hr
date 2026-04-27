<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class AbsenceTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'           => $this->faker->words(2, true),
            'code'           => strtoupper($this->faker->unique()->lexify('TYPE_????')),
            'is_paid'        => true,
            'deducts_leave'  => true,
            'requires_proof' => false,
            'max_days_once'  => null,
        ];
    }

    public function nonDeductible(): static
    {
        return $this->state(fn () => ['deducts_leave' => false]);
    }
}
