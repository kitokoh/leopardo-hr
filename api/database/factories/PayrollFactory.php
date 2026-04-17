<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PayrollFactory extends Factory
{
    public function definition(): array
    {
        $grossSalary = rand(80000, 300000);
        $cotisations = round($grossSalary * 0.11, 2);
        $ir = round(max(0, ($grossSalary - $cotisations) * 0.23 - 55200 / 12), 2);
        $netSalary = round($grossSalary - $cotisations - $ir, 2);
        $lastMonth = now()->subMonth();

        return [
            'period_month' => (int) $lastMonth->format('m'),
            'period_year' => (int) $lastMonth->format('Y'),
            'gross_salary' => $grossSalary,
            'overtime_amount' => 0,
            'bonuses' => json_encode([]),
            'deductions' => json_encode([]),
            'cotisations' => json_encode([
                ['name' => 'Retraite (salarie)', 'rate' => 0.09, 'base' => $grossSalary, 'amount' => round($grossSalary * 0.09, 2)],
                ['name' => 'Assurance chomage', 'rate' => 0.005, 'base' => $grossSalary, 'amount' => round($grossSalary * 0.005, 2)],
                ['name' => 'Mutuelle (AMO)', 'rate' => 0.015, 'base' => $grossSalary, 'amount' => round($grossSalary * 0.015, 2)],
            ]),
            'ir_amount' => $ir,
            'advance_deduction' => 0,
            'absence_deduction' => 0,
            'penalty_deduction' => 0,
            'net_salary' => $netSalary,
            'status' => 'draft',
        ];
    }

    public function validated(): static
    {
        return $this->state(fn () => [
            'status' => 'validated',
            'validated_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid']);
    }

    public function withAdvanceDeduction(float $amount): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            return [
                'advance_deduction' => $amount,
                'net_salary' => max(0, $attributes['net_salary'] - $amount),
            ];
        });
    }
}
