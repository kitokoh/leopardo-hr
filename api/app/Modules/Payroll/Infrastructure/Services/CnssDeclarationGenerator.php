<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CedeaoPayrollRules;

/**
 * Issue #1830 — déclaration CNSS mensuelle Côte d'Ivoire (CSV).
 *
 * Une ligne par bulletin validé : matricule CNSS employé, nom, prénom,
 * salaire brut, assiette plafonnée (min(brut, 1 647 315 XOF)), cotisations
 * retraite salariale (3,2 %) / retraite patronale (4,5 %) / famille
 * patronale (5,75 %) / AT patronale (2 %), totaux salarial et patronal.
 * Ligne TOTAUX en fin de fichier pour contrôle.
 *
 * Taux et plafonds résolus depuis les règles pays CI (CedeaoPayrollRules)
 * — source de vérité unique alignée sur docs/payroll/CI_COMPLIANCE.md §2.
 * ⚠️ Format à valider avec un comptable CNSS ivoirien (structure interne
 * documentée, comme CnasDeclarationGenerator pour la DZ).
 */
class CnssDeclarationGenerator
{
    public const CNSS_CI_CAP = 1647315.0;

    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule,cnss_ci_matricule'])
            ->where('status', 'validated')
            ->get();

        $contributions = $this->contributions();

        $header = [
            'matricule_cnss',
            'nom',
            'prenom',
            'salaire_brut',
            'assiette_plafonnee',
            'retraite_salariale',
            'retraite_patronale',
            'famille_patronale',
            'at_patronale',
            'total_salarial',
            'total_patronal',
        ];
        $rows = [$header];

        $totals = $this->emptyTotals();

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $cappedBase = min($gross, self::CNSS_CI_CAP);

            $retraiteSalariale = $this->contribution($cappedBase, $contributions, 'CNSS_CI_RET_EMP');
            $retraitePatronale = $this->contribution($cappedBase, $contributions, 'CNSS_CI_RET_PAT');
            $famillePatronale = $this->contribution($cappedBase, $contributions, 'CNSS_CI_FAM_PAT');
            $atPatronale = $this->contribution($gross, $contributions, 'CNSS_CI_AT_PAT');
            $totalPatronal = round($retraitePatronale + $famillePatronale + $atPatronale, 2);

            $totals['assiette_plafonnee'] += $cappedBase;
            $totals['retraite_salariale'] += $retraiteSalariale;
            $totals['retraite_patronale'] += $retraitePatronale;
            $totals['famille_patronale'] += $famillePatronale;
            $totals['at_patronale'] += $atPatronale;
            $totals['total_salarial'] += $retraiteSalariale;
            $totals['total_patronal'] += $totalPatronal;

            $rows[] = [
                (string) ($slip->employee->cnss_ci_matricule ?? $slip->employee->matricule ?? $slip->employee_id),
                (string) ($slip->employee->last_name ?? ''),
                (string) ($slip->employee->first_name ?? ''),
                number_format($gross, 2, '.', ''),
                number_format($cappedBase, 2, '.', ''),
                number_format($retraiteSalariale, 2, '.', ''),
                number_format($retraitePatronale, 2, '.', ''),
                number_format($famillePatronale, 2, '.', ''),
                number_format($atPatronale, 2, '.', ''),
                number_format($retraiteSalariale, 2, '.', ''),
                number_format($totalPatronal, 2, '.', ''),
            ];
        }

        $rows[] = [
            'TOTAUX',
            "{$slips->count()} bulletins",
            '',
            number_format($totals['assiette_plafonnee'], 2, '.', ''),
            number_format($totals['assiette_plafonnee'], 2, '.', ''),
            number_format($totals['retraite_salariale'], 2, '.', ''),
            number_format($totals['retraite_patronale'], 2, '.', ''),
            number_format($totals['famille_patronale'], 2, '.', ''),
            number_format($totals['at_patronale'], 2, '.', ''),
            number_format($totals['total_salarial'], 2, '.', ''),
            number_format($totals['total_patronal'], 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    /**
     * Totaux de la déclaration (consommés par le contrôleur pour l'en-tête X-*).
     *
     * @return array{slips: int, assiette_plafonnee: float, retraite_salariale: float, total_patronal: float}
     */
    public function totals(PayrollRun $run): array
    {
        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule,cnss_ci_matricule'])
            ->where('status', 'validated')
            ->get();

        $totals = $this->emptyTotals();

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $cappedBase = min($gross, self::CNSS_CI_CAP);

            $totals['assiette_plafonnee'] += $cappedBase;
            $totals['retraite_salariale'] += $this->contribution($cappedBase, $this->contributions(), 'CNSS_CI_RET_EMP');
            $totals['total_patronal'] += round(
                $this->contribution($cappedBase, $this->contributions(), 'CNSS_CI_RET_PAT')
                    + $this->contribution($cappedBase, $this->contributions(), 'CNSS_CI_FAM_PAT')
                    + $this->contribution($gross, $this->contributions(), 'CNSS_CI_AT_PAT'),
                2
            );
        }

        return [
            'slips' => $slips->count(),
            'assiette_plafonnee' => round($totals['assiette_plafonnee'], 2),
            'retraite_salariale' => round($totals['retraite_salariale'], 2),
            'total_patronal' => round($totals['total_patronal'], 2),
        ];
    }

    /**
     * @return array<string, float>
     */
    private function contributions(): array
    {
        $rules = new CedeaoPayrollRules('CI');

        return collect($rules->socialContributions())
            ->mapWithKeys(fn (array $c): array => [$c['code'] => (float) $c['rate']])
            ->all();
    }

    /**
     * @param  array<string, float>  $contributions
     */
    private function contribution(float $base, array $contributions, string $code): float
    {
        return round($base * ($contributions[$code] ?? 0.0) / 100, 2);
    }

    /**
     * @return array{assiette_plafonnee: float, retraite_salariale: float, retraite_patronale: float, famille_patronale: float, at_patronale: float, total_salarial: float, total_patronal: float}
     */
    private function emptyTotals(): array
    {
        return [
            'assiette_plafonnee' => 0.0,
            'retraite_salariale' => 0.0,
            'retraite_patronale' => 0.0,
            'famille_patronale' => 0.0,
            'at_patronale' => 0.0,
            'total_salarial' => 0.0,
            'total_patronal' => 0.0,
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function toCsv(array $rows): string
    {
        // Champs systématiquement quotés (format déclaration stable pour le
        // dépôt RH) — échappement des guillemets internes.
        $lines = array_map(function (array $row): string {
            return '"'.implode('","', array_map(
                fn (string $field): string => str_replace('"', '""', $field),
                $row
            )).'"';
        }, $rows);

        return implode("\n", $lines)."\n";
    }
}
