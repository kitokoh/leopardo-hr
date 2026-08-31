<?php

namespace Database\Factories;

use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalaryAdvance> */
class SalaryAdvanceFactory extends Factory
{
    protected $model = SalaryAdvance::class;

    /**
     * Issue #3597 : les champs sensibles (role/status/company_id/...) ne sont
     * plus mass-assignables sur le modèle. La factory force l'assignation
     * (forceFill) pour préserver les états de test (manager, archived, ...)
     * sans affaiblir la protection applicative.
     */
    public function newModel(array $attributes = [])
    {
        $model = new $this->model;
        $model->forceFill($attributes);

        return $model;
    }

    public function definition(): array
    {
        $amount = round(rand(10000, 100000) / 1000) * 1000;

        return [
            'amount' => $amount,
            'currency' => 'DZD',
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
