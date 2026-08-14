<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CemacPayrollRules;

/**
 * Issue #1823 — déclaration CNPS mensuelle Cameroun (format DAS :
 * Déclaration et Attestation de Salaires).
 *
 * Une ligne par bulletin validé : immatriculation CNPS employé, nom, prénom,
 * salaire brut, assiette plafonnée (min(brut, 750 000 XAF)), cotisations
 * vieillesse salariale/patronale, famille patronale, AT patronale, total
 * patronal. Ligne TOTAUX en fin de fichier pour contrôle.
 *
 * Taux et plafonds résolus depuis les règles pays CM (CemacPayrollRules) —
 * source de vérité unique alignée sur docs/payroll/CM_COMPLIANCE.md §3.
 * ⚠️ Format à valider avec un comptable CNPS camerounais (structure interne
 * documentée, comme CnasDeclarationGenerator pour la DZ).
 */
class CnpsDeclarationGenerator
{
    public const CNPS_CAP = 750000.0;

    public function generate(PayrollRun $run): string
    {
        $slips = $run->paySlips()
            ->with(['employee:id,first_name,last_name,matricule,cnps_matricule'])
            ->where('status', 'validated')
            ->get();

        $contributions = $this->contributions($run);

        $header = [
            'immatriculation_cnps',
            'nom',
            'prenom',
            'salaire_brut',
            'assiette_plafonnee',
            'vieillesse_salariale',
            'vieillesse_patronale',
            'famille_patronale',
            'at_patronale',
            'total_patronal',
        ];
        $rows = [$header];

        $totals = $this->emptyTotals();

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $cappedBase = min($gross, self::CNPS_CAP);

            $vieillesseSalariale = $this->contribution($cappedBase, $contributions, 'CNPS_CM_VIE_EMP');
            $vieillessePatronale = $this->contribution($cappedBase, $contributions, 'CNPS_CM_VIE_PAT');
            $famillePatronale = $this->contribution($cappedBase, $contributions, 'CNPS_CM_FAM_PAT');
            $atPatronale = $this->contribution($gross, $contributions, 'CNPS_CM_AT_PAT');
            $totalPatronal = round($vieillessePatronale + $famillePatronale + $atPatronale, 2);

            $totals['assiette_plafonnee'] += $cappedBase;
            $totals['vieillesse_salariale'] += $vieillesseSalariale;
            $totals['vieillesse_patronale'] += $vieillessePatronale;
            $totals['famille_patronale'] += $famillePatronale;
            $totals['at_patronale'] += $atPatronale;
            $totals['total_patronal'] += $totalPatronal;

            $rows[] = [
                (string) ($slip->employee->cnps_matricule ?? $slip->employee->matricule ?? $slip->employee_id),
                (string) ($slip->employee->last_name ?? ''),
                (string) ($slip->employee->first_name ?? ''),
                number_format($gross, 2, '.', ''),
                number_format($cappedBase, 2, '.', ''),
                number_format($vieillesseSalariale, 2, '.', ''),
                number_format($vieillessePatronale, 2, '.', ''),
                number_format($famillePatronale, 2, '.', ''),
                number_format($atPatronale, 2, '.', ''),
                number_format($totalPatronal, 2, '.', ''),
            ];
        }

        $rows[] = [
            'TOTAUX',
            "{$slips->count()} bulletins",
            '',
            number_format($totals['assiette_plafonnee'], 2, '.', ''),
            number_format($totals['vieillesse_salariale'], 2, '.', ''),
            number_format($totals['vieillesse_patronale'], 2, '.', ''),
            number_format($totals['famille_patronale'], 2, '.', ''),
            number_format($totals['at_patronale'], 2, '.', ''),
            number_format($totals['total_patronal'], 2, '.', ''),
        ];

        return $this->toCsv($rows);
    }

    /**
     * Ligne de totaux (sommes brutes, non formatées) pour vérification.
     *
     * @return array{assiette_plafonnee: float, vieillesse_salariale: float, vieillesse_patronale: float, famille_patronale: float, at_patronale: float, total_patronal: float, slips: int}
     */
    public function totals(PayrollRun $run): array
    {
        $slips = $run->paySlips()->where('status', 'validated')->get();
        $contributions = $this->contributions($run);

        $totals = $this->emptyTotals();

        foreach ($slips as $slip) {
            $gross = (float) $slip->gross_salary;
            $cappedBase = min($gross, self::CNPS_CAP);

            $totals['assiette_plafonnee'] += $cappedBase;
            $totals['vieillesse_salariale'] += $this->contribution($cappedBase, $contributions, 'CNPS_CM_VIE_EMP');
            $totals['vieillesse_patronale'] += $this->contribution($cappedBase, $contributions, 'CNPS_CM_VIE_PAT');
            $totals['famille_patronale'] += $this->contribution($cappedBase, $contributions, 'CNPS_CM_FAM_PAT');
            $totals['at_patronale'] += $this->contribution($gross, $contributions, 'CNPS_CM_AT_PAT');
        }

        $totals['total_patronal'] = round(
            $totals['vieillesse_patronale'] + $totals['famille_patronale'] + $totals['at_patronale'],
            2
        );
        $totals['slips'] = $slips->count();

        return $totals;
    }

    /**
     * @return array<string, array{rate: float, cap: float|null}>
     */
    private function contributions(PayrollRun $run): array
    {
        $rules = (new CemacPayrollRules('CM'))
            ->forCompany($run->company_id)
            ->asOf($run->period_start);

        $contributions = [];
        foreach ($rules->socialContributions() as $contribution) {
            $contributions[(string) $contribution['code']] = [
                'rate' => (float) $contribution['rate'],
                'cap' => $contribution['cap'] !== null ? (float) $contribution['cap'] : null,
            ];
        }

        return $contributions;
    }

    /**
     * @param  array<string, array{rate: float, cap: float|null}>  $contributions
     */
    private function contribution(float $base, array $contributions, string $code): float
    {
        $contribution = $contributions[$code] ?? null;
        $rate = $contribution['rate'] ?? 0.0;
        $cap = $contribution['cap'] ?? null;
        $cappedBase = $cap !== null ? min($base, $cap) : $base;

        return round($cappedBase * $rate / 100, 2);
    }

    /**
     * @return array{assiette_plafonnee: float, vieillesse_salariale: float, vieillesse_patronale: float, famille_patronale: float, at_patronale: float, total_patronal: float, slips: int}
     */
    private function emptyTotals(): array
    {
        return [
            'assiette_plafonnee' => 0.0,
            'vieillesse_salariale' => 0.0,
            'vieillesse_patronale' => 0.0,
            'famille_patronale' => 0.0,
            'at_patronale' => 0.0,
            'total_patronal' => 0.0,
            'slips' => 0,
        ];
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
