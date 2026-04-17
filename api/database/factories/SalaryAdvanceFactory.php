<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryAdvanceFactory extends Factory
{
    public function definition(): array
    {
        $amount = round(rand(10000, 100000) / 1000) * 1000;

        return [
            'amount' => $amount,
            'reason' => $this->faker->sentence(6),
            'status' => 'pending',
            'repayment_months' => rand(1, 6),
            'monthly_deduction' => null,
            'amount_remaining' => 0,
            'repayment_plan' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            $months = $attributes['repayment_months'] ?? 3;
            $monthly = round($attributes['amount'] / $months, 2);

            return [
                'status' => 'approved',
                'monthly_deduction' => $monthly,
            ];
        });
    }

    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'] ?? 30000;
            $months = $attributes['repayment_months'] ?? 3;
            $monthly = round($amount / $months, 2);
            $remaining = $amount - $monthly;

            return [
                'status' => 'active',
                'monthly_deduction' => $monthly,
                'amount_remaining' => $remaining,
                'repayment_plan' => json_encode(
                    array_map(fn ($i) => [
                        'month' => now()->addMonths($i)->format('Y-m'),
                        'amount' => $monthly,
                        'paid' => $i === 0,
                    ], range(0, $months - 1))
                ),
            ];
        });
    }

    public function repaid(): static
    {
        return $this->state(fn () => [
            'status' => 'repaid',
            'amount_remaining' => 0,
        ]);
    }
}
