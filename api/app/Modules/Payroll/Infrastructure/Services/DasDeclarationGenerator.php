<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Support\Collection;

/**
 * #5243 — Déclaration Annuelle des Salaires (DAS) DZ : CSV une ligne par
 * employé (NIS, nom, salaires bruts de l'année, CNAS salariale 9 % / patronale
 * 26 %, IRG retenu, net versé, mois) + ligne TOTAUX.
 *
 * Agrégée depuis les bulletins VALIDÉS de l'année (status = validated) —
 * les runs non validés sont exclus. Les montants de cotisations/IRG sont
 * dérivés des lignes du bulletin quand elles existent, sinon des taux légaux
 * DZ résolus via {@see CountryRulesResolver} (fallback documenté 9 %/26 %).
 *
 * ⚠️ Format à valider avec un comptable DZ (colonnes attendues par les
 * autorités) — structure interne documentée, comme la déclaration CNAS.
 */
class DasDeclarationGenerator
{
    public const CNAS_EMPLOYEE_FALLBACK_RATE = 9.0;

    public const CNAS_EMPLOYER_FALLBACK_RATE = 26.0;

    /**
     * Génère la DAS d'une année à partir des bulletins validés du tenant.
     *
     * @param  Collection<int, PaySlip>  $slips  bulletins validés de l'année (relations `employee`, `lines` chargées)
     */
    public function generate(string $companyName, string $companyNis, int $year, Collection $slips): string
    {
        $rules = $this->rulesFor('DZ');

        $grouped = $slips->groupBy('employee_id');
        $rows = [];
        $totals = ['gross' => 0.0, 'cnas_employee' => 0.0, 'cnas_employer' => 0.0, 'irg' => 0.0, 'net' => 0.0];

        $seq = 1;
        foreach ($grouped as $employeeId => $employeeSlips) {
            /** @var PaySlip $first */
            $first = $employeeSlips->first();
            $employee = $first->employee;

            $gross = (float) $employeeSlips->sum('gross_salary');
            $net = (float) $employeeSlips->sum('net_salary');
            $months = $employeeSlips
                ->map(static fn (PaySlip $slip): string => $slip->period_start->format('Y-m'))
                ->filter()
                ->unique()
                ->count();

            $cnasEmployee = (float) $employeeSlips->sum(
                fn (PaySlip $slip): float => $this->cnasEmployeeAmount($slip, $rules)
            );
            $cnasEmployer = (float) $employeeSlips->sum(
                fn (PaySlip $slip): float => $this->cnasEmployerAmount($slip, $rules)
            );
            $irg = (float) $employeeSlips->sum(fn (PaySlip $slip): float => $this->irgAmount($slip));

            $rows[] = implode('|', [
                'LIGNE',
                str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                $this->sanitize((string) ($employee->national_id ?? $employee->matricule ?? $employeeId)),
                mb_strtoupper($this->sanitize((string) ($employee->last_name ?? ''))),
                mb_strtoupper($this->sanitize((string) ($employee->first_name ?? ''))),
                (string) $months,
                number_format($gross, 2, '.', ''),
                number_format($cnasEmployee, 2, '.', ''),
                number_format($cnasEmployer, 2, '.', ''),
                number_format($irg, 2, '.', ''),
                number_format($net, 2, '.', ''),
            ]);

            $totals['gross'] += $gross;
            $totals['cnas_employee'] += $cnasEmployee;
            $totals['cnas_employer'] += $cnasEmployer;
            $totals['irg'] += $irg;
            $totals['net'] += $net;
            $seq++;
        }

        $lines = [];
        $lines[] = implode('|', [
            'ENTETE',
            $this->sanitize($companyName),
            $this->sanitize($companyNis),
            (string) $year,
            now()->format('d/m/Y'),
            (string) $grouped->count(),
        ]);
        array_push($lines, ...$rows);
        $lines[] = implode('|', [
            'TOTAUX',
            (string) $grouped->count(),
            number_format($totals['gross'], 2, '.', ''),
            number_format($totals['cnas_employee'], 2, '.', ''),
            number_format($totals['cnas_employer'], 2, '.', ''),
            number_format($totals['irg'], 2, '.', ''),
            number_format($totals['net'], 2, '.', ''),
        ]);

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * CNAS salariale du bulletin : ligne dédiée si présente, sinon taux légal
     * DZ appliqué au brut (9 %).
     */
    private function cnasEmployeeAmount(PaySlip $slip, CountryRulesInterface $rules): float
    {
        $amount = (float) $slip->lines
            ->where('type', 'deduction')
            ->where('name', 'Cotisations salariales')
            ->sum('amount');

        if ($amount > 0.0) {
            return $amount;
        }

        $social = $rules->calculateSocialCharges((float) $slip->gross_salary);

        return round((float) $social['employee'], 2);
    }

    /**
     * CNAS patronale du bulletin : ligne dédiée si présente, sinon taux légal
     * DZ appliqué au brut (26 %).
     */
    private function cnasEmployerAmount(PaySlip $slip, CountryRulesInterface $rules): float
    {
        $amount = (float) $slip->lines
            ->where('type', 'employer_contribution')
            ->sum('amount');

        if ($amount > 0.0) {
            return $amount;
        }

        $social = $rules->calculateSocialCharges((float) $slip->gross_salary);

        return round((float) $social['employer'], 2);
    }

    /**
     * IRG retenu : somme des déductions hors cotisations salariales
     * (lignes « Impot sur le revenu » et tranches progressives).
     */
    private function irgAmount(PaySlip $slip): float
    {
        return round((float) $slip->lines
            ->where('type', 'deduction')
            ->where('name', '!=', 'Cotisations salariales')
            ->sum('amount'), 2);
    }

    private function rulesFor(string $countryCode): CountryRulesInterface
    {
        // MULTI-PAYS (#1868) : AUCUN fallback silencieux — pays inconnu = erreur typée.
        return (new CountryRulesResolver)->resolve($countryCode);
    }

    private function sanitize(string $value): string
    {
        return trim(str_replace(['|', "\r", "\n"], ' ', $value));
    }
}
