<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface as CountryRulesContract;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Domain\Models\PaySlipLine;
use App\Modules\Payroll\Domain\Models\SalaryStructure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * #5591 (slice 3) — calcul d'un run de régularisation, extrait du god-object
 * PayrollCalculator.
 *
 * Un run `regularization` recalcule chaque bulletin du run ORIGINAL (verrouillé)
 * avec les règles et structures ACTUELLES, et persiste le DIFFÉRENTIEL
 * (corrigé − original) par employé et par ligne (delta nul = pas de bulletin).
 * Comportement STRICTEMENT identique à l'ancien code (copie), les tests de
 * régularisation + golden servent de filet.
 */
class PayrollRegularizationService
{
    public function __construct(
        /** Calcul des valeurs de bulletin (instance partagée avec PayrollCalculator). */
        private readonly PaySlipValueCalculator $slipValues,
    ) {}

    /**
     * Issue #1983 — calcul d'un run de régularisation (type=regularization) :
     * DIFFÉRENTIEL par employé affecté, jamais un bulletin complet.
     *
     * Périmètre : les employés ayant un bulletin dans le run ORIGINAL
     * (verrouillé) — les départs en cours de période sont donc couverts et les
     * embauchés après la période exclus (aucun bulletin original).
     *
     * Valeur corrigée : recalcul complet du bulletin pour la période d'origine
     * avec les règles pays résolues asOf ($run->period_start == période
     * d'origine) et la structure salariale/le salaire de base ACTUELS de
     * l'employé (pas d'historique de structures — limite documentée). Les
     * entrées de travail (présences, heures sup., congés) sont celles
     * calculées au moment du run de régularisation (données actuelles).
     *
     * Delta = corrigé − original (par ligne et par champ). Un employé dont le
     * delta est nul ne reçoit AUCUN bulletin. Chaque bulletin de
     * régularisation référence son original (`original_slip_id`).
     *
     * @throws \RuntimeException si le run original manque ou n'est pas verrouillé
     */
    public function calculateRegularizationRun(PayrollRun $run, CountryRulesContract $rules): PayrollRun
    {
        /** @var PayrollRun|null $original */
        $original = $run->originalRun;
        if ($original === null) {
            throw new \RuntimeException('Un run de régularisation doit référencer son run original (original_run_id).');
        }
        if ($original->status !== PayrollRun::STATUS_LOCKED) {
            throw new \RuntimeException('Le run original doit être verrouillé pour calculer un différentiel de régularisation.');
        }

        /** @var Collection<int, SalaryStructure> $structuresCollection */
        $structuresCollection = SalaryStructure::query()
            ->where('company_id', $run->company_id)
            ->where('country_code', $run->country_code)
            ->where('active', true)
            ->with('components')
            ->get();

        /** @var Collection<int|string, SalaryStructure> $structures */
        $structures = $structuresCollection->keyBy('id');

        /** @var SalaryStructure|null $defaultStructure */
        $defaultStructure = $structures->first();

        // Issue #2221 : version/identifiant/période des règles EFFECTIVES
        // persistées aussi sur les runs de régularisation (promesse #1871).
        $rulesVersion = $rules->rulesVersion();
        $rulesIdentifier = (new \ReflectionClass($rules))->getShortName();
        $rulesPeriod = $run->period_start->toDateString();

        DB::transaction(function () use ($run, $original, $structures, $defaultStructure, $rules): void {
            // Recalcul idempotent : on repart de zéro (aucune double application).
            $run->paySlips()->delete();

            $totalGross = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;
            $totalEmployerCost = 0.0;

            /** @var Collection<int, PaySlip> $originalSlips */
            $originalSlips = $original->paySlips()->with(['employee', 'lines'])->get();

            foreach ($originalSlips as $originalSlip) {
                /** @var Employee|null $employee */
                $employee = $originalSlip->employee;
                if ($employee === null) {
                    continue;
                }

                // Embauché APRÈS la période → aucun bulletin (garde défensive :
                // sans bulletin original, l'employé n'apparaît pas ici).
                if ($employee->contract_start !== null && $run->period_end < $employee->contract_start) {
                    continue;
                }

                /** @var SalaryStructure|null $structure */
                $structure = $employee->salary_structure_id !== null
                    ? ($structures->get($employee->salary_structure_id) ?? $defaultStructure)
                    : $defaultStructure;

                if ($structure === null) {
                    continue;
                }

                $corrected = $this->slipValues->computeSlipValues($run, $employee, $structure, $rules);

                $delta = [
                    'gross_salary' => round($corrected['gross_salary'] - (float) $originalSlip->gross_salary, 2),
                    'total_deductions' => round($corrected['total_deductions'] - (float) $originalSlip->total_deductions, 2),
                    'net_salary' => round($corrected['net_salary'] - (float) $originalSlip->net_salary, 2),
                    'employer_contributions' => round($corrected['employer_contributions'] - (float) $originalSlip->employer_contributions, 2),
                    'total_cost' => round($corrected['total_cost'] - (float) $originalSlip->total_cost, 2),
                ];

                // Aucun changement pour cet employé → pas de bulletin.
                if (abs($delta['gross_salary']) < 0.005
                    && abs($delta['total_deductions']) < 0.005
                    && abs($delta['net_salary']) < 0.005
                    && abs($delta['total_cost']) < 0.005) {
                    continue;
                }

                /** @var PaySlip $slip */
                $slip = PaySlip::create([
                    'payroll_run_id' => $run->id,
                    'company_id' => $run->company_id,
                    'employee_id' => $employee->id,
                    'period_start' => $run->period_start,
                    'period_end' => $run->period_end,
                    'gross_salary' => $delta['gross_salary'],
                    'total_deductions' => $delta['total_deductions'],
                    'net_salary' => $delta['net_salary'],
                    'employer_contributions' => $delta['employer_contributions'],
                    'total_cost' => $delta['total_cost'],
                    'working_days' => $corrected['working_days'],
                    'actual_days_worked' => $corrected['actual_days_worked'],
                    'overtime_hours' => $corrected['overtime_hours'],
                    // Issue #5245 — snapshot des entrées congés/absences/fériés
                    // du calcul corrigé (transparence du différentiel).
                    'paid_leave_days' => $corrected['paid_leave_days'],
                    'unpaid_leave_days' => $corrected['unpaid_leave_days'],
                    'public_holiday_days' => $corrected['public_holiday_days'],
                    'has_attendance_data' => $corrected['has_attendance_data'],
                    'status' => 'calculated',
                    'original_slip_id' => $originalSlip->id,
                ]);

                $this->createDeltaLines($slip, $corrected['lines'], $originalSlip);

                $totalGross += $delta['gross_salary'];
                $totalDeductions += $delta['total_deductions'];
                $totalNet += $delta['net_salary'];
                $totalEmployerCost += $delta['total_cost'];
            }

            $run->update([
                'status' => 'calculated',
                // Issue #1871 — mêmes règles EFFECTIVES que le run standard :
                // l'audit lit rules_version/rules_identifier depuis le run.
                'rules_version' => $rules->rulesVersion(),
                'rules_identifier' => (new \ReflectionClass($rules))->getShortName(),
                'rules_period' => $run->period_start->toDateString(),
                'total_gross' => round($totalGross, 2),
                'total_deductions' => round($totalDeductions, 2),
                'total_net' => round($totalNet, 2),
                'total_employer_cost' => round($totalEmployerCost, 2),
                'employee_count' => $run->paySlips()->count(),
                'calculated_at' => now(),
            ]);
        });

        return $run->refresh();
    }

    /**
     * Issue #1983 — lignes du bulletin de régularisation : différentiel
     * corrigé − original par libellé (zéro exclu).
     *
     * @param  array<int, array<string, mixed>>  $correctedLines
     */
    private function createDeltaLines(PaySlip $slip, array $correctedLines, PaySlip $originalSlip): void
    {
        /** @var Collection<string, PaySlipLine> $originalLinesByName */
        $originalLinesByName = $originalSlip->lines->keyBy('name');

        $order = 0;
        foreach ($correctedLines as $correctedLine) {
            /** @var string $lineName */
            $lineName = $correctedLine['name'];
            /** @var PaySlipLine|null $originalLine */
            $originalLine = $originalLinesByName->get($lineName);

            /** @var int|float $correctedAmount */
            $correctedAmount = $correctedLine['amount'];
            $originalAmount = $originalLine !== null ? $originalLine->amount : 0.0;

            $deltaAmount = round((float) $correctedAmount - (float) $originalAmount, 2);
            if (abs($deltaAmount) < 0.005) {
                continue;
            }

            /** @var int|float|null $correctedBase */
            $correctedBase = $correctedLine['base_amount'] ?? 0.0;
            $originalBase = $originalLine !== null ? $originalLine->base_amount : 0.0;

            PaySlipLine::create([
                'pay_slip_id' => $slip->id,
                'name' => $lineName,
                'type' => $correctedLine['type'],
                'base_amount' => round((float) $correctedBase - (float) $originalBase, 2),
                'rate' => $correctedLine['rate'] ?? null,
                'amount' => $deltaAmount,
                'order' => $order++,
            ]);
        }
    }
}
