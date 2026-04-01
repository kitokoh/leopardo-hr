<?php

namespace Database\Factories\Tenant;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * AttendanceLogFactory
 *
 * RÈGLES :
 * - check_in/check_out stockés en UTC
 * - Le calcul des retards se fait en timezone entreprise (dans les tests, utiliser Africa/Algiers)
 * - session_number = 1 pour journée normale
 */
class AttendanceLogFactory extends Factory
{
    public function definition(): array
    {
        $checkIn = $this->faker->dateTimeBetween('today 08:00', 'today 09:00');
        $checkOut = clone $checkIn;
        $checkOut->modify('+8 hours +' . rand(0, 60) . ' minutes');

        return [
            'date'           => date('Y-m-d'),
            'session_number' => 1,
            'check_in'       => $checkIn->format('Y-m-d H:i:s'),
            'check_out'      => $checkOut->format('Y-m-d H:i:s'),
            'method'         => $this->faker->randomElement(['mobile', 'qr', 'biometric']),
            'status'         => 'ontime',
            'hours_worked'   => 8.0,
            'overtime_hours' => 0,
            'late_minutes'   => 0,
        ];
    }

    public function late(): static
    {
        return $this->state(function () {
            $lateMinutes = rand(16, 60);
            $checkIn = now()->setTime(8, $lateMinutes, 0);
            return [
                'check_in'    => $checkIn,
                'status'      => 'late',
                'late_minutes' => $lateMinutes,
            ];
        });
    }

    public function withOvertime(float $hours = 2.0): static
    {
        return $this->state(fn () => [
            'overtime_hours' => $hours,
            'hours_worked'   => 8 + $hours,
            'status'         => 'ontime',
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => [
            'method'          => 'manual',
            'correction_note' => 'Correction manuelle par le responsable',
        ]);
    }
}


/**
 * AbsenceFactory
 */
class AbsenceFactory extends Factory
{
    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $days  = rand(1, 10);
        $end   = (clone $start)->modify("+{$days} days");

        return [
            'start_date'  => $start->format('Y-m-d'),
            'end_date'    => $end->format('Y-m-d'),
            'days_count'  => $days,
            'status'      => 'pending',
            'reason'      => $this->faker->sentence(5),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status'          => 'rejected',
            'rejected_reason' => 'Période chargée — reporter la demande',
        ]);
    }

    public function past(): static
    {
        return $this->state(function () {
            $start = $this->faker->dateTimeBetween('-30 days', '-5 days');
            $days  = rand(1, 5);
            $end   = (clone $start)->modify("+{$days} days");
            return [
                'start_date' => $start->format('Y-m-d'),
                'end_date'   => $end->format('Y-m-d'),
                'days_count' => $days,
                'status'     => 'approved',
            ];
        });
    }
}


/**
 * SalaryAdvanceFactory
 *
 * STATUTS (décision figée) : pending → approved → active → repaid (+ rejected)
 * 'active' = en cours de remboursement — le PayrollService filtre WHERE status='active'
 */
class SalaryAdvanceFactory extends Factory
{
    public function definition(): array
    {
        $amount = round(rand(10000, 100000) / 1000) * 1000; // Multiple de 1000

        return [
            'amount'            => $amount,
            'reason'            => $this->faker->sentence(6),
            'status'            => 'pending',
            'repayment_months'  => rand(1, 6),
            'monthly_deduction' => null,
            'amount_remaining'  => 0,
            'repayment_plan'    => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            $months    = $attributes['repayment_months'] ?? 3;
            $monthly   = round($attributes['amount'] / $months, 2);
            return [
                'status'            => 'approved',
                'monthly_deduction' => $monthly,
            ];
        });
    }

    /**
     * Avance active = approuvée + remboursement en cours
     * PayrollService filtre sur ce statut pour les déductions mensuelles
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            $amount    = $attributes['amount'] ?? 30000;
            $months    = $attributes['repayment_months'] ?? 3;
            $monthly   = round($amount / $months, 2);
            $remaining = $amount - $monthly; // 1 mensualité déjà payée

            return [
                'status'            => 'active',
                'monthly_deduction' => $monthly,
                'amount_remaining'  => $remaining,
                'repayment_plan'    => json_encode(
                    array_map(fn ($i) => [
                        'month'  => now()->addMonths($i)->format('Y-m'),
                        'amount' => $monthly,
                        'paid'   => $i === 0,
                    ], range(0, $months - 1))
                ),
            ];
        });
    }

    public function repaid(): static
    {
        return $this->state(fn () => [
            'status'           => 'repaid',
            'amount_remaining' => 0,
        ]);
    }
}


/**
 * PayrollFactory
 *
 * NOTE : gross_salary ≠ employees.salary_base
 * gross_salary = salary_base + overtime + bonuses (calculé par PayrollService)
 */
class PayrollFactory extends Factory
{
    public function definition(): array
    {
        $grossSalary  = rand(80000, 300000);
        $cotisations  = round($grossSalary * 0.11, 2); // Taux salarial DZ ~11%
        $ir           = round(max(0, ($grossSalary - cotisations) * 0.23 - 55200 / 12), 2);
        $netSalary    = round($grossSalary - $cotisations - $ir, 2);
        $lastMonth    = now()->subMonth();

        return [
            'period_month'      => (int) $lastMonth->format('m'),
            'period_year'       => (int) $lastMonth->format('Y'),
            'gross_salary'      => $grossSalary,
            'overtime_amount'   => 0,
            'bonuses'           => json_encode([]),
            'deductions'        => json_encode([]),
            'cotisations'       => json_encode([
                ['name' => 'Retraite (salarié)',  'rate' => 0.09,  'base' => $grossSalary, 'amount' => round($grossSalary * 0.09, 2)],
                ['name' => 'Assurance chômage',   'rate' => 0.005, 'base' => $grossSalary, 'amount' => round($grossSalary * 0.005, 2)],
                ['name' => 'Mutuelle (AMO)',       'rate' => 0.015, 'base' => $grossSalary, 'amount' => round($grossSalary * 0.015, 2)],
            ]),
            'ir_amount'         => $ir,
            'advance_deduction' => 0,
            'absence_deduction' => 0,
            'penalty_deduction' => 0,
            'net_salary'        => $netSalary,
            'status'            => 'draft',
        ];
    }

    public function validated(): static
    {
        return $this->state(fn () => [
            'status'       => 'validated',
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
                'net_salary'        => max(0, $attributes['net_salary'] - $amount),
            ];
        });
    }
}
