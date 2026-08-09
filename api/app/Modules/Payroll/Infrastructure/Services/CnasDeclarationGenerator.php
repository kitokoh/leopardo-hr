<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\AlgeriaPayrollRules;
use App\Modules\Payroll\Infrastructure\Services\CountryRulesInterface;

/**
 * Programme FOCUS — F-10 : déclaration CNAS mensuelle (CSV).
 *
 * Une ligne par bulletin validé : matricule, nom, assiette (brut), CNAS
 * salariale (9 %), CNAS patronale (26 %) — taux résolus depuis les règles
 * pays du run (fallback DZ). Ligne de totaux pour contrôle.
 *
 * ⚠️ Format à valider avec un comptable DZ (colonnes attendues par la CNAS) —
 * la structure ici est la version interne documentée.
 */
class CnasDeclarationGenerator
{
    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule', 'lines'])
            ->where('status', 'validated')
            ->get();

        $rules = $this->rulesFor($run->country_code);

        $header = ['matricule', 'nom', 'assiette_brut', 'cnas_salariale', 'cnas_patronale'];
        $rows = [$header];

        $totalAssiette = 0.0;
        $totalSalariale = 0.0;
        $totalPatronale = 0.0;

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $employeeRate = $this->rateFromLines($slip->lines, 'Cotisations salariales', $gross, $rules, 'CNAS_EMP');
            $employerRate = $this->rateFromLines($slip->lines, null, $gross, $rules, 'CNAS_PAT', 'employer_contribution');

            $employeeCtas = round($gross * $employeeRate / 100, 2);
            $employerCtas = round($gross * $employerRate / 100, 2);

            $totalAssiette += $gross;
            $totalSalariale += $employeeCtas;
            $totalPatronale += $employerCtas;

            $rows[] = [
                (string) ($slip->employee->matricule ?? $slip->employee_id),
                trim(($slip->employee->first_name ?? '').' '.($slip->employee->last_name ?? '')),
                number_format($gross, 2, '.', ''),
                number_format($employeeCtas, 2, '.', ''),
                number_format($employerCtas, 2, '.', ''),
            ];
        }

        $rows[] = [
            'TOTAL',
            "{$slips->count()} bulletins",
            number_format($totalAssiette, 2, '.', ''),
            number_format($totalSalariale, 2, '.', ''),
            number_format($totalPatronale, 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    private function rulesFor(string $countryCode): CountryRulesInterface
    {
        $rulesMap = [
            'DZ' => new AlgeriaPayrollRules(),
        ];

        return $rulesMap[$countryCode] ?? new AlgeriaPayrollRules();
    }

    /**
     * Taux effectif : priorité aux lignes du bulletin (calcul réel), sinon taux
     * de la règle pays.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Payroll\Domain\Models\PaySlipLine>  $lines
     */
    private function rateFromLines(
        \Illuminate\Support\Collection $lines,
        ?string $lineName,
        float $gross,
        CountryRulesInterface $rules,
        string $rateCode,
        ?string $lineType = null
    ): float {
        $query = $lines;
        if ($lineName !== null) {
            $query = $query->where('name', $lineName);
        }
        if ($lineType !== null) {
            $query = $query->where('type', $lineType);
        }
        $amount = (float) $query->sum('amount');

        if ($gross > 0.0 && $amount > 0.0) {
            return round($amount / $gross * 100, 2);
        }

        $social = $rules->calculateSocialCharges($gross > 0.0 ? $gross : 1000.0);

        return $rateCode === 'CNAS_EMP' ? 9.0 : 26.0;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        $lines = array_map(static function (array $row): string {
            return implode(',', array_map(static function ($cell): string {
                $cell = (string) $cell;

                return '"'.str_replace('"', '""', $cell).'"';
            }, $row));
        }, $rows);

        return implode("\n", $lines)."\n";
    }
}
